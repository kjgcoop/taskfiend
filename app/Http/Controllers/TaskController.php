<?php

namespace App\Http\Controllers;

use App\Models\ChangeLog;
use App\Models\Task;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use App\Services\DateParser;
use App\Services\QuickAddParser;
use App\Services\TaskLifecycle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::visibleTo(Auth::id());

        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', '!=', 'archived')
                  ->where('status', '!=', 'done');
        }

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        } else {
            $query->whereHas('project', fn($pq) => $pq->whereNotIn('status', ['archived', 'done']));
        }

        $sort     = $request->input('sort', 'date');
        $reversed = $request->boolean('reversed');

        match ($sort) {
            'created'  => $query->orderBy('created_at', $reversed ? 'asc' : 'desc'),
            'name'     => $query->orderByRaw($reversed ? 'LOWER(name) DESC' : 'LOWER(name) ASC'),
            'custom'   => $query->orderByRaw('CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END, sort_order ASC, date IS NULL, date ASC, time IS NULL, time ASC'),
            'location' => $query->orderByRaw($reversed ? '(location IS NULL OR location = \'\') ASC, LOWER(location) DESC' : '(location IS NULL OR location = \'\') ASC, LOWER(location) ASC'),
            default    => $query->orderByRaw($reversed ? 'date IS NULL, date DESC, time IS NULL, time DESC, created_at DESC' : 'date IS NULL, date ASC, time IS NULL, time ASC, created_at ASC'),
        };

        $tasks = $query->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments', 'completionLog.user'])
            ->get();

        $breakdown = $tasks
            ->groupBy(fn($t) => optional($t->project)->name ?? 'No Project')
            ->map(fn($g, $name) => ['name' => $name, 'count' => $g->count()])
            ->sortByDesc('count')
            ->values()
            ->toArray();

        return view('tasks.create', compact('tasks', 'sort', 'breakdown'));
    }

    public function create(Request $request)
    {
        $projects = Project::activeForUser(Auth::id())->get()->sort(fn ($a, $b) => strnatcasecmp($a->name, $b->name))->values();

        $tags = Tag::active()->orderByRaw('LOWER(tag_name)')->get();
        $users = User::whereNull('email_enabled_at')->get();

        $preselectedProjectId = $request->query('project_id')
            ?? Auth::user()->defaultProject()->id;
        $preselectedDate = $request->query('date');

        // Handle parent task preselection
        $preselectedParentId = $request->query('parent_id');
        $preselectedParentTask = null;
        $missingParentId = null;

        if ($preselectedParentId) {
            $preselectedParentTask = Task::find($preselectedParentId);
            if ($preselectedParentTask) {
                $this->authorizeTaskAccess($preselectedParentTask);
            } else {
                // Parent task no longer exists (deleted/never existed, stale link, etc.) —
                // fall back to the normal "no preselection" create form instead of carrying
                // around an id with nothing to point at. Remember the requested id so the
                // view can tell the user why nothing got preselected.
                $missingParentId = $preselectedParentId;
                $preselectedParentId = null;
            }
        }

        // Get available parent tasks (exclude archived)
        $availableParents = Task::visibleTo(Auth::id())
            ->where('status', '=', 'incomplete')
            ->with('parent', 'project') // For depth calculation and project name display
            ->orderByRaw('LOWER(name)')
            ->get();

        return view('tasks.create', compact(
            'projects', 'tags', 'users', 'missingParentId',
            'preselectedProjectId', 'preselectedDate',
            'preselectedParentId', 'preselectedParentTask', 'availableParents'
        ));
    }

    public function store(Request $request)
    {
        $longTextMax = (int) config('taskfiend.long_text_max_chars');
        $validated = $request->validate([
            'name' => 'required|string',  // max enforced per-line below to support bulk (multi-line) input
            'description' => "nullable|string|max:{$longTextMax}",
            'location' => 'nullable|string|max:255',
            'date' => 'nullable|string|max:255',
            'time' => 'nullable|date_format:H:i',
            'duration_minutes' => 'nullable|string|max:20',
            'project_id' => 'nullable|exists:projects,id',
            'parent_id' => 'nullable|exists:tasks,id',
            'recurrence_pattern' => 'nullable|string|max:100',
            'recurrence_floating' => 'nullable|boolean',
            'recurrence_end_date' => 'nullable|date',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
        ]);

        // Block task creation inside inaccessible or inactive projects
        if (!empty($validated['project_id'])) {
            $targetProject = Project::where('id', $validated['project_id'])
                ->forMember(Auth::id())
                ->first();
            if (!$targetProject) {
                return $this->storeError($request, ['project_id' => 'You do not have access to this project.']);
            }
            if (in_array($targetProject->status, ['done', 'archived'])) {
                return $this->storeError($request, ['project_id' => 'Cannot create tasks in an inactive project.']);
            }
        }

        // Authorization check for parent task
        if (isset($validated['parent_id'])) {
            $parentTask = Task::findOrFail($validated['parent_id']);
            $this->authorizeTaskAccess($parentTask);

            // Prevent creating subtask under archived parent
            if ($parentTask->status === 'archived') {
                return $this->storeError($request, ['parent_id' => 'Cannot create subtask under an archived task.']);
            }
        }

        // Guard against excessively large bulk input before any expensive processing.
        $bulkMaxChars = (int) config('taskfiend.bulk_input_max_chars');
        $bulkMaxLines = (int) config('taskfiend.bulk_input_max_lines');
        if (strlen($validated['name']) > $bulkMaxChars) {
            return $this->storeError($request, ['name' => "Input is too long. Maximum {$bulkMaxChars} characters total."]);
        }

        // Detect bulk (multi-line) input. Each non-empty line becomes a separate task.
        $lines = array_values(array_filter(array_map('trim', explode("\n", $validated['name']))));
        $isQuickAdd = $request->boolean('quick_add');

        if (count($lines) > $bulkMaxLines) {
            return $this->storeError($request, ['name' => "Too many lines. Maximum is {$bulkMaxLines} tasks at once."]);
        }

        if (count($lines) > 1) {
            return $this->storeBulk($request, $validated, $lines, $isQuickAdd);
        }

        // Single-task path: enforce 255-char limit that was removed from the validation rule.
        if (strlen($validated['name']) > 255) {
            return $this->storeError($request, ['name' => 'Task name is too long (' . strlen($validated['name']) . '/255 characters max).']);
        }

        $taskName = preg_replace('/^[-*] /', '', $validated['name']);
        $date = $validated['date'] ?? null;
        $time = $validated['time'] ?? null;
        $recurrencePattern = $validated['recurrence_pattern'] ?? null;
        $recurrenceFloating = !empty($validated['recurrence_floating']);

        // Parse inline tokens (#project, @tag, +location, &user) from the task name.
        // On quick-add, project_id may already be set by autocomplete — treat that as
        // resolved. On the full add form, project_id is always submitted from the
        // dropdown and does NOT indicate the #token was matched, so the lookup runs.
        // Location and assignee tokens are quick-add-only and never override an
        // explicitly provided value.
        $tokens = (new QuickAddParser(Auth::id()))->parse(
            $taskName,
            projectPreResolved: $isQuickAdd && isset($validated['project_id']),
            parseLocation: $isQuickAdd && empty($validated['location']),
            parseAssignees: $isQuickAdd && empty($validated['assignee_ids']),
        );
        $taskName = $tokens->name;
        if ($tokens->project) {
            $validated['project_id'] = $tokens->project->id;
        }
        if (!empty($tokens->tagIds)) {
            $validated['tag_ids'] = array_unique(array_merge($validated['tag_ids'] ?? [], $tokens->tagIds));
        }
        if ($tokens->location !== null) {
            $validated['location'] = $tokens->location;
            $validated['show_map'] = $tokens->showMap;
        }
        if (!empty($tokens->assigneeIds)) {
            $validated['assignee_ids'] = $tokens->assigneeIds;
        }

        $dateParser = new DateParser();

        // Parse natural language date if provided
        if ($date && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $parsedDate = $dateParser->resolveDate($date);
            if (!$parsedDate) {
                return $this->storeError($request, [
                    'date' => "Could not understand the date \"{$date}\". Try: tomorrow, next friday, march 15, 3/15, or 2026-03-15"
                ]);
            }
            $date = $parsedDate->format('Y-m-d');
        }

        // Validate and normalize explicitly provided recurrence pattern
        if ($recurrencePattern) {
            $normalized = $dateParser->normalizeRecurrencePattern($recurrencePattern);
            if ($normalized === null) {
                return $this->storeError($request, [
                    'recurrence_pattern' => "The recurrence pattern '{$recurrencePattern}' is not recognized. Supported patterns include: daily, every other day, every 4 days, weekdays, weekends, every Monday/Tuesday/etc., every other Monday/Tuesday/etc., every 2 weeks, every 1st (monthly), every first Monday (monthly), yearly, every 2 years, every January 3 (annual on specific date)."
                ]);
            }
            $recurrencePattern = $normalized;
        }

        // Auto-parse date and recurrence from task name when using quick-add and no
        // explicit pattern was provided. The regular form has dedicated fields for
        // date and recurrence, so the name is stored verbatim there.
        if ($isQuickAdd && !$recurrencePattern) {
            // Check for unrecognized recurrence patterns first
            $unrecognizedError = $dateParser->detectUnrecognizedPattern($taskName);
            if ($unrecognizedError) {
                return $this->storeError($request, ['name' => $unrecognizedError]);
            }

            $parsed = $dateParser->parseTaskInput($taskName);
            $taskName = $parsed['name'];
            if ($parsed['nodate']) {
                // Explicit "nodate" token always clears date and time.
                $date = null;
                $time = null;
            } elseif (!$date || ($parsed['date'] !== null && $parsed['date_explicit'])) {
                // An explicitly-typed date token (today, tomorrow, a day name, a specific
                // month/day, etc.) overrides the day-view's pre-filled hidden date.
                // Recurrence-interval defaults (yearly → today+1yr, weekly → today+1wk,
                // etc.) do NOT override a pre-filled date; they only apply when there is
                // no date already set.
                $date = $parsed['date'];
                $time = $parsed['time'];
            }
            $recurrencePattern = $parsed['recurrence_pattern'];
            // Pick up floating flag from "every!" syntax in task name
            if ($parsed['recurrence_floating']) {
                $recurrenceFloating = true;
            }
        }

        // Auto-populate date from recurrence pattern if recurrence is set but date is not
        if ($recurrencePattern && !$date) {
            $nextOccurrence = $dateParser->getNextOccurrence($recurrencePattern, now());
            if ($nextOccurrence) {
                $date = $nextOccurrence->format('Y-m-d');
            }
        }

        // Reject dates in the past (today is always valid)
        if ($date && Carbon::parse($date)->startOfDay()->lt(Carbon::today())) {
            return $this->storeError($request, ['date' => 'Task date cannot be in the past.']);
        }

        // project_id is NOT NULL in the database; fall back to the user's default project
        // when the form was submitted without one (e.g. quick-add without a #project token).
        if (empty($validated['project_id'])) {
            $validated['project_id'] = Auth::user()->defaultProject()->id;
        }

        $task = Task::create([
            'name' => $taskName,
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'show_map' => $validated['show_map'] ?? false,
            'date' => $date,
            'time' => $time,
            'duration_minutes' => $this->parseDurationInput($validated['duration_minutes'] ?? null),
            'project_id' => $validated['project_id'],
            'parent_id' => $validated['parent_id'] ?? null,
            'recurrence_pattern' => $recurrencePattern,
            'recurrence_floating' => $recurrenceFloating,
            'recurrence_end_date' => $validated['recurrence_end_date'] ?? null,
            'creator_id' => Auth::id(),
            'status' => 'incomplete',
        ]);

        if (isset($validated['tag_ids'])) {
            $task->tags()->sync($validated['tag_ids']);
        }

        // Auto-inherit parent assignees if no assignees specified
        if (isset($validated['parent_id']) && empty($validated['assignee_ids'])) {
            $parentTask = Task::find($validated['parent_id']);
            $assigneeIds = $parentTask->assignees->pluck('id')->toArray();
        } else {
            $assigneeIds = array_unique(array_merge($validated['assignee_ids'] ?? [], [Auth::id()]));
        }
        foreach ($assigneeIds as $assigneeId) {
            $task->assignments()->create([
                'assignee_id' => $assigneeId,
                'assigned_by_id' => Auth::id(),
            ]);
        }

        $this->logChange($task, 'created task', 'created');

        if ($request->boolean('quick_add')) {
            if ($request->wantsJson()) {
                $task->load(['project:id,name', 'tags:id,tag_name,color', 'assignees:id,name']);
                return response()->json(['ok' => true, 'tasks' => [$this->taskToastData($task)]]);
            }
            return redirect()->back()->with('success', 'Task created.');
        }

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Task created successfully.');
    }

    public function show(Task $task)
    {
        $this->authorizeTaskAccess($task);

        $task->load(['creator', 'project', 'tags', 'assignees', 'assignments.assignedBy',
                     'attachments', 'comments.user', 'changeLogs.user', 'children', 'parent']);

        $projects = Project::activeForUser(Auth::id())->get()->sort(fn ($a, $b) => strnatcasecmp($a->name, $b->name))->values();

        $tags = Tag::active()->orderByRaw('LOWER(tag_name)')->get();
        $users = User::whereNull('email_enabled_at')->get();
        $defaultProjectId = Auth::user()->defaultProject()->id;

        // Calculate next due date for recurring tasks
        $nextDueDate = null;
        if ($task->recurrence_pattern && $task->date) {
            $dateParser = new DateParser();
            $currentDate = Carbon::parse($task->date);
            $nextOccurrence = $dateParser->getNextOccurrence($task->recurrence_pattern, $currentDate);
            if ($nextOccurrence) {
                $nextDueDate = $nextOccurrence->format('l, F j, Y'); // e.g., "Monday, January 20, 2026"
            }
        }

        // Get available parent tasks (exclude self and descendants to prevent cycles)
        $excludeIds = $task->getAllDescendants()->pluck('id')->push($task->id);

        $availableParents = Task::visibleTo(Auth::id())
            ->whereNotIn('id', $excludeIds)
            ->where('status', '=', 'incomplete')
            ->with('parent', 'project') // For depth calculation and project name display
            ->orderByRaw('LOWER(name)')
            ->get();

        $isInactive = in_array($task->status, ['done', 'archived'])
            || ($task->project && in_array($task->project->status, ['done', 'archived']));

        // Find tasks that reference this task in their description or comments.
        $userId = Auth::id();
        $referencingTasks = Task::where('id', '!=', $task->id)
            ->referencingTask($task->id)
            ->visibleTo($userId)
            ->with(['creator', 'project', 'tags'])
            ->orderByRaw('LOWER(name)')
            ->get();

        return view('tasks.show', compact('task', 'projects', 'tags', 'users', 'nextDueDate', 'availableParents', 'defaultProjectId', 'isInactive', 'referencingTasks'));
    }

    public function panel(Task $task)
    {
        $this->authorizeTaskAccess($task);

        $task->load(['creator', 'project', 'tags', 'assignees', 'assignments.assignedBy',
                     'attachments', 'comments.user', 'changeLogs.user', 'children', 'parent']);

        $projects = Project::activeForUser(Auth::id())->get()->sort(fn ($a, $b) => strnatcasecmp($a->name, $b->name))->values();

        $tags = Tag::active()->orderByRaw('LOWER(tag_name)')->get();
        $users = User::whereNull('email_enabled_at')->get();
        $defaultProjectId = Auth::user()->defaultProject()->id;

        $nextDueDate = null;
        if ($task->recurrence_pattern && $task->date) {
            $dateParser = new DateParser();
            $currentDate = Carbon::parse($task->date);
            $nextOccurrence = $dateParser->getNextOccurrence($task->recurrence_pattern, $currentDate);
            if ($nextOccurrence) {
                $nextDueDate = $nextOccurrence->format('l, F j, Y');
            }
        }

        $isInactive = in_array($task->status, ['done', 'archived'])
            || ($task->project && in_array($task->project->status, ['done', 'archived']));

        $userId = Auth::id();
        $referencingTasks = Task::where('id', '!=', $task->id)
            ->referencingTask($task->id)
            ->visibleTo($userId)
            ->with(['creator', 'project', 'tags'])
            ->orderByRaw('LOWER(name)')
            ->get();

        return view('tasks._panel', compact('task', 'projects', 'tags', 'users', 'nextDueDate', 'defaultProjectId', 'isInactive', 'referencingTasks'));
    }

    public function edit(Task $task)
    {
        $this->authorizeTaskAccess($task);
        $this->assertProjectActive($task);

        $projects = Project::activeForUser(Auth::id())->get()->sort(fn ($a, $b) => strnatcasecmp($a->name, $b->name))->values();

        $tags = Tag::active()->orderByRaw('LOWER(tag_name)')->get();
        $users = User::whereNull('email_enabled_at')->get();
        $defaultProjectId = Auth::user()->defaultProject()->id;

        // Get available parent tasks (exclude self and descendants to prevent cycles)
        $excludeIds = $task->getAllDescendants()->pluck('id')->push($task->id);

        $availableParents = Task::visibleTo(Auth::id())
            ->whereNotIn('id', $excludeIds)
            ->where('status', '=', 'incomplete')
            ->with('parent', 'project') // For depth calculation and project name display
            ->orderByRaw('LOWER(name)')
            ->get();

        return view('tasks.edit', compact('task', 'projects', 'tags', 'users', 'availableParents', 'defaultProjectId'));
    }

    public function update(Request $request, Task $task)
    {
        $this->authorizeTaskAccess($task);
        $this->assertProjectActive($task, asJson: $request->ajax() || $request->wantsJson());

        $longTextMax = (int) config('taskfiend.long_text_max_chars');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => "nullable|string|max:{$longTextMax}",
            'location' => 'nullable|string|max:255',
            'show_map' => 'nullable|boolean',
            'date' => 'nullable|string|max:255',
            'time' => 'nullable|date_format:H:i',
            'duration_minutes' => 'nullable|string|max:20',
            'project_id' => 'nullable|exists:projects,id',
            'parent_id' => 'nullable|exists:tasks,id',
            'recurrence_pattern' => 'nullable|string|max:100',
            'recurrence_floating' => 'nullable|boolean',
            'recurrence_end_date' => 'nullable|date',
            'status' => 'in:incomplete,done,archived',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
        ]);

        // Parse natural language date if provided
        if (!empty($validated['date']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $validated['date'])) {
            $parsedDate = (new DateParser())->resolveDate($validated['date']);
            if (!$parsedDate) {
                return back()->withErrors([
                    'date' => "Could not understand the date \"{$validated['date']}\". Try: tomorrow, next friday, march 15, 3/15, or 2026-03-15"
                ])->withInput();
            }
            $validated['date'] = $parsedDate->format('Y-m-d');
        }

        // Reject dates in the past (today is always valid), but only when the
        // date is actually being changed — not when saving a task that already
        // has a past due date (e.g. marking it done). Quick-complete resubmits
        // the task's full state just to flip status to done; its hidden date
        // field can be stale (e.g. the task was rescheduled since this row was
        // rendered), so it must never be treated as an intentional date change.
        if (!$request->boolean('quick_complete')
            && !empty($validated['date'])
            && $validated['date'] !== $task->date
            && Carbon::parse($validated['date'])->startOfDay()->lt(Carbon::today())) {
            $msg = 'Task date cannot be in the past.';
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withErrors(['date' => $msg])->withInput();
        }

        // Validate project access when project is being changed
        if (isset($validated['project_id']) && $validated['project_id'] != $task->project_id) {
            $targetProject = Project::where('id', $validated['project_id'])
                ->forMember(Auth::id())
                ->first();
            if (!$targetProject) {
                $msg = 'You do not have access to this project.';
                if ($request->ajax()) {
                    return response()->json(['success' => false, 'message' => $msg], 403);
                }
                return back()->withErrors(['project_id' => $msg])->withInput();
            }
        }

        // Validate parent change (prevent circular references)
        if (isset($validated['parent_id'])) {
            $newParent = Task::find($validated['parent_id']);

            // Can't set self as parent
            if ($newParent && $newParent->id === $task->id) {
                return back()->withErrors([
                    'parent_id' => 'A task cannot be its own parent.'
                ])->withInput();
            }

            // Check if new parent is a descendant (would create cycle)
            if ($newParent && $task->isAncestorOf($newParent)) {
                return back()->withErrors([
                    'parent_id' => 'Cannot move task under its own descendant. This would create a circular reference.'
                ])->withInput();
            }

            if ($newParent) {
                $this->authorizeTaskAccess($newParent);
            }
        }

        // Validate and normalize recurrence pattern if provided
        if (isset($validated['recurrence_pattern']) && !empty($validated['recurrence_pattern'])) {
            $dateParser = new DateParser();
            $normalized = $dateParser->normalizeRecurrencePattern($validated['recurrence_pattern']);
            if ($normalized === null) {
                $msg = "The recurrence pattern '{$validated['recurrence_pattern']}' is not recognized. Supported patterns include: daily, every other day, every 4 days, weekdays, weekends, every Monday/Tuesday/etc., every other Monday/Tuesday/etc., every 2 weeks, every 1st (monthly), every first Monday (monthly), yearly, every 2 years, every January 3 (annual on specific date).";
                if ($request->ajax()) {
                    return response()->json(['success' => false, 'message' => $msg], 422);
                }
                return back()->withErrors(['recurrence_pattern' => $msg])->withInput();
            }
            $validated['recurrence_pattern'] = $normalized;
        }

        // Prevent subtasks from having recurrence patterns
        if (isset($validated['recurrence_pattern']) && $validated['recurrence_pattern'] && $task->parent_id) {
            $msg = 'Subtasks cannot have their own recurrence pattern. Only root-level tasks can recur.';
            if ($request->ajax()) {
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
            return back()->withErrors(['recurrence_pattern' => $msg])->withInput();
        }

        $lifecycle = new TaskLifecycle();

        // Completing a task that has incomplete subtasks completes the whole
        // subtree and skips any other submitted edits — the form snapshot may
        // be stale relative to the auto-completed children.
        if (isset($validated['status']) && $validated['status'] === 'done'
            && $task->status !== 'done' && $task->hasIncompleteDescendants()) {
            $statusChange = $lifecycle->changeStatus($task, 'done');

            if ($request->has('quick_complete')) {
                if ($request->ajax()) {
                    $resp = ['ok' => true];
                    if ($statusChange->nextRecurringTask) {
                        $resp['next_task_id'] = $statusChange->nextRecurringTask->id;
                    }
                    return response()->json($resp);
                }
                return redirect()->back()
                    ->with('success', 'Task and all subtasks marked as done.');
            }

            return redirect()->route('tasks.show', $task)
                ->with('success', 'Task and all subtasks marked as done.');
        }

        if (array_key_exists('duration_minutes', $validated)) {
            $validated['duration_minutes'] = $this->parseDurationInput($validated['duration_minutes']);
        }

        // Status is applied separately below via the lifecycle service.
        $changes = [];
        $updatableFields = ['name', 'description', 'location', 'show_map', 'date', 'time', 'duration_minutes', 'project_id', 'parent_id', 'recurrence_pattern', 'recurrence_floating'];
        // Quick-complete only ever intends to flip status to done — its other hidden
        // fields are just the last-rendered snapshot and may be stale, so don't let
        // them silently overwrite the task's actual current values.
        if ($request->boolean('quick_complete')) {
            $updatableFields = [];
        }
        foreach ($updatableFields as $field) {
            if (isset($validated[$field]) && $task->$field != $validated[$field]) {
                $changes[$field] = ['old' => $task->$field, 'new' => $validated[$field]];
                $task->$field = $validated[$field];
            }
        }

        if (isset($changes['project_id'])) {
            $task->project_sort_order = null;
        }

        $task->save();

        if (isset($validated['tag_ids'])) {
            $this->syncTagsPreservingArchived($task, $validated['tag_ids']);
        }

        if (isset($validated['assignee_ids']) && $task->creator_id === Auth::id()) {
            $currentAssigneeIds = $task->assignments->pluck('assignee_id')->toArray();
            // Creator cannot unassign themselves
            $newAssigneeIds = array_unique(array_merge($validated['assignee_ids'], [Auth::id()]));

            $toRemove = array_diff($currentAssigneeIds, $newAssigneeIds);
            $toAdd = array_diff($newAssigneeIds, $currentAssigneeIds);

            $task->assignments()->whereIn('assignee_id', $toRemove)->delete();

            foreach ($toAdd as $assigneeId) {
                $task->assignments()->create([
                    'assignee_id' => $assigneeId,
                    'assigned_by_id' => Auth::id(),
                ]);
            }
        }

        foreach ($changes as $field => $change) {
            $verb = match($field) {
                'date' => ($change['old'] && $change['new']) ? 'rescheduled' : ($change['new'] ? 'scheduled' : 'edited'),
                default => 'edited',
            };
            $this->logChange($task, "changed {$field} from {$change['old']} to {$change['new']}", $verb, $field, $change['old'], $change['new']);
        }

        // Apply the status change with all its side effects (descendant
        // cascades, completed_at, change logging, recurring rollover).
        $nextRecurringTask = isset($validated['status'])
            ? $lifecycle->changeStatus($task, $validated['status'])->nextRecurringTask
            : null;

        // Handle quick complete from task list
        if ($request->has('quick_complete')) {
            if ($request->ajax()) {
                $resp = ['ok' => true];
                if ($nextRecurringTask) {
                    $resp['next_task_id'] = $nextRecurringTask->id;
                }
                return response()->json($resp);
            }
            return redirect()->back()
                ->with('success', 'Task marked as done.');
        }

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Task updated successfully.');
    }

    public function updateField(Request $request, Task $task)
    {
        $this->authorizeTaskAccess($task);
        $this->assertProjectActive($task, asJson: true);

        $field = $request->input('field');
        $allowedFields = ['name', 'description', 'location', 'show_map', 'status', 'date', 'time', 'duration_minutes', 'project_id', 'parent_id', 'recurrence_pattern', 'recurrence_floating', 'recurrence_end_date', 'tag_ids', 'assignee_ids'];

        if (!in_array($field, $allowedFields)) {
            return response()->json(['success' => false, 'message' => 'Invalid field'], 400);
        }

        try {
            if ($field === 'parent_id') {
                $newParentId = $request->input('value');

                if ($newParentId) {
                    $newParent = Task::find($newParentId);

                    if (!$newParent) {
                        return response()->json(['success' => false, 'message' => 'Parent task not found'], 404);
                    }

                    if ($newParent->id === $task->id) {
                        return response()->json(['success' => false, 'message' => 'A task cannot be its own parent'], 400);
                    }

                    if ($task->isAncestorOf($newParent)) {
                        return response()->json(['success' => false, 'message' => 'Cannot create circular reference'], 400);
                    }

                    $this->authorizeTaskAccess($newParent);
                }

                $task->parent_id = $newParentId;
                $task->save();
                $this->logChange($task, "updated parent task");
            } elseif ($field === 'tag_ids') {
                $tagIds = $request->input('tag_ids', []);
                $this->syncTagsPreservingArchived($task, $tagIds);
                $this->logChange($task, 'updated tags');
            } elseif ($field === 'assignee_ids') {
                if ($task->creator_id !== Auth::id()) {
                    return response()->json(['success' => false, 'message' => 'Only creator can change assignees'], 403);
                }

                // Creator cannot unassign themselves
                $assigneeIds = array_unique(array_merge($request->input('assignee_ids', []), [Auth::id()]));
                $currentAssigneeIds = $task->assignments->pluck('assignee_id')->toArray();

                $toRemove = array_diff($currentAssigneeIds, $assigneeIds);
                $toAdd = array_diff($assigneeIds, $currentAssigneeIds);

                $task->assignments()->whereIn('assignee_id', $toRemove)->delete();

                foreach ($toAdd as $assigneeId) {
                    $task->assignments()->create([
                        'assignee_id' => $assigneeId,
                        'assigned_by_id' => Auth::id(),
                    ]);
                }

                $this->logChange($task, 'updated assignees');
            } elseif ($field === 'status') {
                $value = $request->input('value');

                if (!in_array($value, ['incomplete', 'done', 'archived'])) {
                    return response()->json(['success' => false, 'message' => 'Invalid status'], 400);
                }

                $statusChange = (new TaskLifecycle())
                    ->changeStatus($task, $value, $request->input('next_occurrence_action'));

                // Completing with incomplete subtasks completes the whole
                // subtree — the list view needs a reload to reflect every row.
                if ($statusChange->completedDescendants) {
                    $task->load(['project', 'tags']);
                    $resp = ['success' => true, 'reload' => true, 'taskData' => $this->buildTaskData($task, $field)];
                    if ($statusChange->nextRecurringTask) {
                        $resp['next_task_id'] = $statusChange->nextRecurringTask->id;
                    }
                    return response()->json($resp);
                }

                $nextRecurringTask = $statusChange->nextRecurringTask;
            } else {
                $value = $request->input('value');

                $longTextMax = (int) config('taskfiend.long_text_max_chars');
                if ($field === 'name' && strlen((string) $value) > 255) {
                    return response()->json(['success' => false, 'message' => 'Name cannot exceed 255 characters.'], 422);
                }
                if ($field === 'description' && strlen((string) $value) > $longTextMax) {
                    return response()->json(['success' => false, 'message' => "Description cannot exceed {$longTextMax} characters."], 422);
                }
                if ($field === 'recurrence_pattern' && strlen((string) $value) > 100) {
                    return response()->json(['success' => false, 'message' => 'Recurrence pattern is too long.'], 422);
                }

                // Parse natural language dates. Skip re-parsing when the value is already a
                // plain ISO date (e.g. sent by the date-picker widget) — mirrors update()
                // above. Re-parsing a human-formatted string like "Tuesday, August 11, 2026"
                // is unsafe: the weekday-name pattern is checked before the full-date pattern,
                // so it would resolve to "next Tuesday" and silently discard the real date.
                if ($field === 'date' && $value) {
                    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                        $parsed = (new DateParser())->resolveDate($value);
                        if (!$parsed) {
                            return response()->json(['success' => false, 'message' => "Could not parse date: \"{$value}\". Try formats like: tomorrow, next friday, march 15, 3/15, 2026-03-15"], 400);
                        }
                        $value = $parsed->format('Y-m-d');
                    }

                    // Reject dates in the past (today is always valid), but only when the
                    // date is actually being changed — not when resaving a task that already
                    // has a past due date (e.g. an open date field getting resent alongside
                    // another field save while the task itself is overdue).
                    if ($value !== $task->date
                        && Carbon::parse($value)->startOfDay()->lt(Carbon::today())) {
                        return response()->json(['success' => false, 'message' => 'Task date cannot be in the past.'], 422);
                    }
                }

                // Validate and normalize recurrence_end_date
                if ($field === 'recurrence_end_date') {
                    if ($value === null || $value === '') {
                        $value = null;
                    } elseif (!strtotime($value)) {
                        return response()->json(['success' => false, 'message' => 'Invalid end date format.'], 422);
                    } else {
                        $value = Carbon::parse($value)->format('Y-m-d');
                    }
                }

                // Coerce boolean fields properly ("false" string → false, "true" string → true)
                if ($field === 'recurrence_floating' || $field === 'show_map') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }

                // Parse flexible duration input (e.g. "2h 20m", "90", "1h")
                if ($field === 'duration_minutes') {
                    if ($value === null || $value === '') {
                        $value = null;
                    } else {
                        $parsed = $this->parseDurationInput((string) $value);
                        if ($parsed === null) {
                            return response()->json(['success' => false, 'message' => 'Could not parse duration. Try: 90, 1h 30m, 1h30m, 90m'], 400);
                        }
                        $value = $parsed;
                    }
                }

                if ($field === 'recurrence_pattern' && !empty($value)) {
                    if ($task->parent_id) {
                        return response()->json(['success' => false, 'message' => 'Subtasks cannot have their own recurrence pattern.'], 400);
                    }
                    $dateParser = new DateParser();
                    $normalized = $dateParser->normalizeRecurrencePattern($value);
                    if ($normalized === null) {
                        return response()->json(['success' => false, 'message' => "Recurrence pattern \"{$value}\" is not recognized. Try: daily, every Thursday, every other week, every third Sunday, every 3rd Sunday, etc."], 422);
                    }
                    $value = $normalized;
                }

                $previousValue = $task->$field;
                $task->$field = $value;

                if ($field === 'project_id') {
                    $task->project_sort_order = null;
                }

                $task->save();

                $verb = 'edited';
                if ($field === 'date') {
                    $verb = ($previousValue && $value) ? 'rescheduled' : ($value ? 'scheduled' : 'edited');
                }
                $this->logChange($task, "updated {$field}", $verb, $field, $previousValue, $value);

                if ($field === 'name') {
                    if ($request->has('tag_ids')) {
                        $this->syncTagsPreservingArchived($task, $request->input('tag_ids', []));
                        $this->logChange($task, 'updated tags');
                    }
                    if ($request->filled('new_project_id')) {
                        $task->project_id = $request->input('new_project_id');
                        $task->project_sort_order = null;
                        $task->save();
                        $this->logChange($task, 'updated project_id');
                    }
                }
            }

            $task->load(['project', 'tags']);
            $response = ['success' => true, 'taskData' => $this->buildTaskData($task, $field)];
            if ($field === 'description') {
                $response['rendered_description'] = render_body($task->description ?? '');
            }
            if (!empty($nextRecurringTask)) {
                $response['next_task_id'] = $nextRecurringTask->id;
            }
            return response()->json($response);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    private function buildTaskData(Task $task, ?string $updatedField = null): array
    {
        return [
            'id'                 => $task->id,
            'name'               => $task->name,
            'status'             => $task->status,
            'description'        => $task->description,
            'date'               => $task->date,
            'date_formatted'     => $task->date ? \Carbon\Carbon::parse($task->date)->format('l, F j, Y') : null,
            'time_formatted'     => $task->time ? \Carbon\Carbon::parse($task->time)->format('g:i A') : null,
            'project_id'         => $task->project_id,
            'project_name'       => $task->project?->name,
            'project_url'        => $task->project ? route('projects.show', $task->project) : null,
            'recurrence_pattern' => $task->recurrence_pattern,
            'tags'               => $task->tags->map(fn ($t) => ['name' => $t->tag_name, 'color' => $t->color])->values()->toArray(),
            'tag_ids'            => $task->tags->pluck('id')->toArray(),
            'url'                => route('tasks.show', $task),
            'inactive'           => in_array($task->status, ['done', 'archived']),
            'updated_field'      => $updatedField,
        ];
    }

    public function rowHtml(Task $task, Request $request)
    {
        $this->authorizeTaskAccess($task);

        $task->load(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments',
                     'children', 'children.assignees', 'children.tags', 'children.attachments',
                     'children.comments', 'completionLog.user']);

        $tasks        = collect([$task]);
        $hideDate     = $request->boolean('hide_date');
        $readOnly     = false;
        $showAsArchived = $task->status === 'archived';

        return response()->json([
            'html' => view('tasks.partials.completed-list', compact('tasks', 'hideDate', 'readOnly', 'showAsArchived'))->render(),
        ]);
    }

    /**
     * Parse a natural language date string and return the resolved date.
     * Used for live preview as the user types in the date field.
     */
    public function parseDate(Request $request)
    {
        $input = trim($request->input('input', ''));

        if (empty($input)) {
            return response()->json(['success' => false]);
        }

        $result = (new DateParser())->resolveDate($input);

        if ($result) {
            $dateStr = $result->format('Y-m-d');
            $tasks = Task::with('project:id,name')
                ->visibleTo(Auth::id())
                ->where('date', $dateStr)
                ->where('status', 'incomplete')
                ->get(['id', 'name', 'project_id']);

            $projects = $tasks->groupBy('project_id')
                ->map(function ($projectTasks) {
                    $names = $projectTasks->pluck('name');
                    $count = $names->count();
                    return [
                        'name'  => $projectTasks->first()->project->name ?? 'No Project',
                        'count' => $count,
                        'tasks' => $names->take(10)->values()->all(),
                        'more'  => max(0, $count - 10),
                    ];
                })
                ->sortByDesc('count')
                ->values()
                ->all();

            return response()->json([
                'success'   => true,
                'date'      => $dateStr,
                'formatted' => $result->format('l, F j, Y'),
                'projects'  => $projects,
            ]);
        }

        return response()->json(['success' => false]);
    }

    /**
     * Preview how the quick-add bar will interpret the typed input.
     * Returns parsed title, date, recurrence, project, and tags as JSON.
     * Used for the debounced live-preview line shown below the input.
     */
    public function previewQuickAdd(Request $request)
    {
        $input = trim($request->input('name', ''));

        if (empty($input)) {
            return response()->json(['has_special' => false]);
        }

        if (strlen($input) > 500) {
            return response()->json(['has_special' => false]);
        }

        // Same parser as store(), so the preview shows exactly what submitting will do.
        $tokens = (new QuickAddParser(Auth::id()))->parse($input);
        $taskName         = $tokens->name;
        $projectName      = $tokens->project?->name;
        $tagNames         = $tokens->tagNames;
        $location         = $tokens->location;
        $showMap          = $tokens->showMap;
        $assigneeNames    = $tokens->assigneeNames;
        $unknownAssignees = $tokens->unknownAssignees;

        // Parse natural language date/recurrence from the remaining task name
        $dateParser = new DateParser();
        $parsed = $dateParser->parseTaskInput($taskName);
        $title = $parsed['name'] ?: $taskName;

        $dateFormatted = null;
        if ($parsed['nodate']) {
            $dateFormatted = 'no date';
        } elseif ($parsed['date']) {
            $parsedDate = Carbon::parse($parsed['date']);
            // Only show the year when it differs from the current year - keeps the
            // common case ("Wed, Jun 10") short, but disambiguates an explicit
            // out-of-year date ("Wed, Jun 10, 2028") instead of silently hiding it.
            $dateFormatted = $parsedDate->year === Carbon::now()->year
                ? $parsedDate->format('D, M j')
                : $parsedDate->format('D, M j, Y');
        }

        $hasSpecial = $projectName !== null
            || !empty($tagNames)
            || $parsed['date'] !== null
            || $parsed['nodate']
            || $parsed['recurrence_pattern'] !== null
            || $location !== null
            || !empty($assigneeNames)
            || !empty($unknownAssignees);

        $freshProjects = Project::activeForUser(Auth::id())
            ->orderByRaw('LOWER(name)')
            ->get(['id', 'name']);

        return response()->json([
            'has_special'       => $hasSpecial,
            'title'             => $title,
            'project'           => $projectName,
            'tags_display'      => implode(' ', array_map(fn($t) => '@' . $t, $tagNames)),
            'date'              => $dateFormatted,
            'nodate'            => $parsed['nodate'],
            'recurrence'        => $parsed['recurrence_pattern'],
            'location'          => $location,
            'show_map'          => $showMap,
            'assignees_display' => implode(' ', array_map(fn($n) => '&' . preg_replace('/\s+/', '', strtolower($n)), $assigneeNames)),
            'unknown_assignees' => implode(' ', $unknownAssignees),
            'projects'          => $freshProjects,
        ]);
    }

    public function duplicate(Task $task)
    {
        $this->authorizeTaskAccess($task);
        $this->assertProjectActive($task);

        $newTask = $task->duplicate(['name' => 'Copy of ' . $task->name]);

        $this->logChange($newTask, 'duplicated from task #' . $task->id, 'created');

        return redirect()->route('tasks.show', $newTask)
            ->with('success', 'Task duplicated successfully.');
    }

    public function destroy(Task $task)
    {
        abort(403, 'Tasks cannot be deleted. Please archive instead.');
    }

    public function reorder(Request $request)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $userId = Auth::id();

        // Only reorder tasks the user has access to (creator or assignee)
        $accessibleIds = Task::visibleTo($userId)
            ->whereIn('id', $request->ids)
            ->pluck('id')
            ->flip(); // id => index for O(1) lookup

        foreach ($request->ids as $position => $id) {
            if ($accessibleIds->has($id)) {
                Task::where('id', $id)->update(['sort_order' => $position]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'task_ids'        => 'required|array|min:1',
            'task_ids.*'      => 'integer',
            'date'            => 'nullable|date_format:Y-m-d',
            'clear_date'      => 'nullable|boolean',
            'project_id'      => 'nullable|integer|exists:projects,id',
            'status'          => 'nullable|in:incomplete,done,archived',
            'tag_ids'         => 'nullable|array',
            'tag_ids.*'       => 'integer|exists:tags,id',
            'location'        => 'nullable|string|max:255',
            'clear_location'  => 'nullable|boolean',
            'duration_minutes' => 'nullable|string|max:20',
            'clear_duration'  => 'nullable|boolean',
        ]);

        $date          = $request->input('date');
        $clearDate     = $request->boolean('clear_date');
        $projectId     = $request->input('project_id');
        $status        = $request->input('status');
        $tagIds        = $request->input('tag_ids', []);
        $location      = $request->input('location');
        $clearLocation = $request->boolean('clear_location');
        $duration      = $request->input('duration_minutes');
        $clearDuration = $request->boolean('clear_duration');

        if ($date === null && !$clearDate && $projectId === null && $status === null && empty($tagIds) && $location === null && !$clearLocation && $duration === null && !$clearDuration) {
            return response()->json(['success' => false, 'message' => 'No changes specified.'], 422);
        }

        // Validate project access
        if ($projectId) {
            $project = Project::where('id', $projectId)
                ->forMember(Auth::id())
                ->first();

            if (!$project) {
                return response()->json(['success' => false, 'message' => 'Invalid project.'], 403);
            }
        }

        // Only update tasks the current user can access
        $tasks = Task::visibleTo(Auth::id())
            ->whereIn('id', $request->input('task_ids'))
            ->get();

        // Status is intentionally excluded here and applied separately via
        // TaskLifecycle::changeStatus() below, so it gets the same side effects
        // (completed_at stamping, descendant cascades, recurring rollover) as
        // every other status-changing code path in the app.
        $changes = [];
        if ($clearDate)          $changes['date']            = null;
        elseif ($date !== null)  $changes['date']            = $date;
        if ($projectId !== null) {
            $changes['project_id'] = $projectId;
            $changes['project_sort_order'] = null;
        }
        if ($clearLocation)      $changes['location']        = null;
        elseif ($location !== null) $changes['location']     = $location;
        if ($clearDuration)      $changes['duration_minutes'] = null;
        elseif ($duration !== null) $changes['duration_minutes'] = $this->parseDurationInput($duration);

        $changeFields = array_keys($changes);
        if ($clearDate)     $changeFields = array_map(fn($f) => $f === 'date' ? 'date (cleared)' : $f, $changeFields);
        if ($clearLocation) $changeFields = array_map(fn($f) => $f === 'location' ? 'location (cleared)' : $f, $changeFields);
        if ($clearDuration) $changeFields = array_map(fn($f) => $f === 'duration_minutes' ? 'duration (cleared)' : $f, $changeFields);
        $changeFields = array_map(fn($f) => $f === 'duration_minutes' ? 'duration' : $f, $changeFields);
        if ($status !== null) $changeFields[] = 'status';
        if (!empty($tagIds)) $changeFields[] = 'tags';

        $lifecycle = new TaskLifecycle();
        $updatedCount = 0;
        foreach ($tasks as $task) {
            if (!empty($changes)) {
                $task->update($changes);
            }
            if (!empty($tagIds)) {
                $task->tags()->syncWithoutDetaching($tagIds);
            }
            if (!empty($changeFields)) {
                $this->logChange($task, 'bulk updated: ' . implode(', ', $changeFields), 'edited');
            }
            if ($status !== null) {
                // Applies completed_at, descendant cascades, and recurring
                // rollover consistently with the single-task update paths.
                $lifecycle->changeStatus($task, $status);
            }
            $updatedCount++;
        }

        return response()->json([
            'success' => true,
            'updated' => $updatedCount,
            'message' => "Updated {$updatedCount} task(s).",
        ]);
    }

    protected function assertProjectActive(Task $task, bool $asJson = false)
    {
        $task->loadMissing('project');
        if ($task->project && in_array($task->project->status, ['done', 'archived'])) {
            $message = 'Tasks in inactive projects cannot be modified.';
            if ($asJson) {
                abort(response()->json(['success' => false, 'message' => $message], 403));
            }
            abort(403, $message);
        }
    }

    protected function authorizeTaskAccess(Task $task)
    {
        $isCreator = $task->creator_id === Auth::id();
        $isAssignee = $task->assignees->contains('id', Auth::id());

        // Check if user has access via any ancestor
        $hasAncestorAccess = false;
        foreach ($task->getAllAncestors() as $ancestor) {
            if ($ancestor->creator_id === Auth::id() || $ancestor->assignees->contains('id', Auth::id())) {
                $hasAncestorAccess = true;
                break;
            }
        }

        $isDirectProjectMember = false;
        if ($task->project_id) {
            $project = $task->relationLoaded('project') ? $task->project : \App\Models\Project::find($task->project_id);
            if ($project) {
                $isDirectProjectMember = $project->user_id === Auth::id()
                    || $project->assignees()->where('users.id', Auth::id())->exists();
            }
        }

        if (!$isCreator && !$isAssignee && !$hasAncestorAccess && !$isDirectProjectMember) {
            abort(403, 'You do not have access to this task.');
        }
    }

    private function taskToastData(Task $task): array
    {
        $datetime = $task->datetime ? \Carbon\Carbon::parse($task->datetime) : null;

        return [
            'id'        => $task->id,
            'name'      => $task->name,
            // Only show the year when it differs from the current year - keeps the
            // common case short but disambiguates a date scheduled in another year.
            'datetime'  => $datetime
                ? $datetime->format($datetime->year === now()->year ? 'D, M j' : 'D, M j, Y')
                : null,
            'time'      => $task->time
                ? \Carbon\Carbon::parse($task->time)->format('g:i A')
                : null,
            'duration'  => $task->duration_minutes
                ? Task::formatDuration($task->duration_minutes)
                : null,
            'location'  => $task->location ?: null,
            'project'   => $task->project
                ? ['id' => $task->project->id, 'name' => $task->project->name]
                : null,
            'tags'      => $task->tags->map(fn($t) => ['id' => $t->id, 'name' => $t->tag_name, 'color' => $t->color])->all(),
            'assignees' => $task->assignees->map(fn($a) => ['name' => $a->name])->all(),
        ];
    }

    /**
     * Sync a task's tags from a form-submitted tag_ids list, without dropping any archived
     * tag the task already carries. Archived tags are excluded from every tag picker (see
     * Tag::scopeActive()), so a submitted list can never include one on purpose — but a plain
     * sync() would otherwise silently detach it just because it wasn't offered as a checkbox.
     */
    protected function syncTagsPreservingArchived(Task $task, array $tagIds): void
    {
        $archivedAttachedIds = $task->tags()->whereNotNull('tags.archived_at')->pluck('tags.id')->toArray();
        $task->tags()->sync(array_unique(array_merge($tagIds, $archivedAttachedIds)));
    }

    protected function logChange(Task $task, string $description, string $verb = 'edited', ?string $field = null, mixed $oldValue = null, mixed $newValue = null)
    {
        $task->changeLogs()->create([
            'date'        => now(),
            'user_id'     => Auth::id(),
            'entity_type' => 'tasks',
            'entity_id'   => $task->id,
            'description' => $description,
            'verb'        => $verb,
            'field'       => $field,
            'old_value'   => $oldValue !== null ? (string) $oldValue : null,
            'new_value'   => $newValue !== null ? (string) $newValue : null,
        ]);
    }

    /**
     * Return an error response from store() that works for both AJAX (JSON 422)
     * and regular (redirect back with errors) requests.
     */
    private function storeError(Request $request, array $errors)
    {
        if ($request->wantsJson()) {
            return response()->json([
                'ok'     => false,
                'errors' => array_map(fn($msg) => [$msg], $errors),
            ], 422);
        }
        return back()->withErrors($errors)->withInput();
    }

    /**
     * Parse a flexible duration string into an integer number of minutes.
     *
     * Accepts:
     *   "90"       → 90  (plain integer = minutes)
     *   "2h 20m"   → 140
     *   "2h20m"    → 140
     *   "2h"       → 120
     *   "20m"      → 20
     *
     * Returns null for empty/unparseable input.
     */
    private function parseDurationInput(?string $input): ?int
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $input = trim($input);

        // Plain integer → treat as minutes
        if (ctype_digit($input)) {
            $v = (int) $input;
            return $v > 0 ? $v : null;
        }

        $totalMinutes = 0;
        $matched = false;

        // Hours component: 2h, 2hr, 2hrs, 2hour, 2hours
        if (preg_match('/(\d+)\s*h(?:r|rs|our|ours)?/i', $input, $m)) {
            $totalMinutes += (int) $m[1] * 60;
            $matched = true;
        }

        // Minutes component: 20m, 20min, 20mins, 20minute, 20minutes
        if (preg_match('/(\d+)\s*m(?:in|ins|inute|inutes)?(?!\s*\d)/i', $input, $m)) {
            $totalMinutes += (int) $m[1];
            $matched = true;
        }

        if ($matched && $totalMinutes > 0) {
            return $totalMinutes;
        }

        return null;
    }

    /**
     * Handle bulk (multi-line) task creation.
     * Each non-empty line is treated as a separate task name.
     * The form-level project, date, tags, assignees, etc. act as fallbacks / additions.
     * On partial success the successfully created tasks are kept and the failed
     * lines are returned to the form so the user can fix and resubmit them.
     */
    private function storeBulk(Request $request, array $validated, array $lines, bool $isQuickAdd)
    {
        // Pre-validate shared fields once so every line benefits from the same parsed values.

        // Resolve natural-language date (shared fallback for lines without an inline date).
        $globalDate = $validated['date'] ?? null;
        if ($globalDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $globalDate)) {
            $parsedDate = (new DateParser())->resolveDate($globalDate);
            if (!$parsedDate) {
                return $this->storeError($request, [
                    'date' => "Could not understand the date \"{$globalDate}\". Try: tomorrow, next friday, march 15, 3/15, or 2026-03-15",
                ]);
            }
            $globalDate = $parsedDate->format('Y-m-d');
        }

        // Validate and normalize recurrence pattern (shared for all lines).
        $globalRecurrence = $validated['recurrence_pattern'] ?? null;
        if ($globalRecurrence) {
            $dateParser = new DateParser();
            $normalized = $dateParser->normalizeRecurrencePattern($globalRecurrence);
            if ($normalized === null) {
                return $this->storeError($request, [
                    'recurrence_pattern' => "The recurrence pattern '{$globalRecurrence}' is not recognized. Supported patterns include: daily, every other day, every 4 days, weekdays, weekends, every Monday/Tuesday/etc., every other Monday/Tuesday/etc., every 2 weeks, every 1st (monthly), every first Monday (monthly), yearly, every 2 years, every January 3 (annual on specific date).",
                ]);
            }
            $globalRecurrence = $normalized;
        }

        $successes = [];
        $errors    = [];

        $createdTasks = [];
        foreach ($lines as $i => $line) {
            try {
                $task = $this->createSingleTaskFromLine($line, $validated, $globalDate, $globalRecurrence, $isQuickAdd);
                $successes[] = $task;
                $task->load(['project:id,name', 'tags:id,tag_name,color', 'assignees:id,name']);
                $createdTasks[] = $this->taskToastData($task);
            } catch (\Exception $e) {
                $errors[] = [
                    'line'  => $i + 1,
                    'input' => $line,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $totalLines   = count($lines);
        $successCount = count($successes);
        $hasErrors    = !empty($errors);

        // ── Quick-add (AJAX) response ────────────────────────────────────────────────
        if ($isQuickAdd) {
            if (!$hasErrors) {
                if ($request->wantsJson()) {
                    return response()->json(['ok' => true, 'count' => $successCount, 'tasks' => $createdTasks]);
                }
                return redirect()->back()->with('success', "{$successCount} tasks created.");
            }

            $errorMsg = $successCount > 0
                ? "Created {$successCount} of {$totalLines} tasks. Issues with remaining:"
                : 'Could not create tasks:';
            foreach ($errors as $err) {
                $errorMsg .= "\n• Line {$err['line']} \"{$err['input']}\": {$err['error']}";
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'ok'      => $successCount > 0,
                    'partial' => $successCount > 0,
                    'count'   => $successCount,
                    'tasks'   => $createdTasks,
                    'errors'  => $errors,
                    'message' => $errorMsg,
                ], $successCount > 0 ? 200 : 422);
            }
            return redirect()->back()->with($successCount > 0 ? 'warning' : 'error', $errorMsg);
        }

        // ── Full-form response ───────────────────────────────────────────────────────
        if (!$hasErrors) {
            return redirect()->route('tasks.create')
                ->with('success', "Created {$successCount} task" . ($successCount === 1 ? '' : 's') . " successfully.");
        }

        // Partial (or full) failure: put just the failed lines back into the form.
        $failedLines = implode("\n", array_map(fn($e) => $e['input'], $errors));
        $request->merge(['name' => $failedLines]);

        $response = back()->with('bulk_errors', $errors)->withInput();
        if ($successCount > 0) {
            $response = $response->with('success', "{$successCount} task" . ($successCount === 1 ? '' : 's') . " created successfully.");
        }
        return $response;
    }

    /**
     * Create a single task from a raw line of text.
     * Mirrors store() logic but throws exceptions instead of returning HTTP responses,
     * allowing storeBulk() to collect per-line errors and continue.
     *
     * @param  string       $rawName              The raw line (may contain #project / @tag tokens).
     * @param  array        $validated            Validated form data for shared fields.
     * @param  string|null  $resolvedGlobalDate   Already-resolved YYYY-MM-DD fallback date (or null).
     * @param  string|null  $globalRecurrence     Pre-validated recurrence pattern (or null).
     * @param  bool         $isQuickAdd           Whether to parse dates/recurrence from the task name.
     */
    private function createSingleTaskFromLine(
        string  $rawName,
        array   $validated,
        ?string $resolvedGlobalDate,
        ?string $globalRecurrence,
        bool    $isQuickAdd
    ): Task {
        $taskName = preg_replace('/^[-*] /', '', trim($rawName));

        if (strlen($taskName) > 255) {
            throw new \InvalidArgumentException(
                'Task name is too long (' . strlen($taskName) . '/255 characters max).'
            );
        }

        $date              = $resolvedGlobalDate;
        $time              = $validated['time'] ?? null;
        $recurrencePattern = $globalRecurrence;
        $recurrenceFloat   = !empty($validated['recurrence_floating']);
        $projectId         = $validated['project_id'] ?? null;
        $tagIds            = $validated['tag_ids'] ?? [];

        // ── Parse inline tokens — same parser as single-task store() and the preview,
        //    so a line behaves identically whether submitted alone or in bulk ─────────
        $tokens = (new QuickAddParser(Auth::id()))->parse(
            $taskName,
            parseLocation: $isQuickAdd && empty($validated['location']),
            parseAssignees: $isQuickAdd && empty($validated['assignee_ids']),
        );
        $taskName = $tokens->name;

        // #project overrides the form-level fallback project
        if ($tokens->project) {
            $projectId = $tokens->project->id;
        }

        // @tags are additive with form-level tags
        $tagIds = array_unique(array_merge($tagIds, $tokens->tagIds));

        $lineLocation = $tokens->location ?? ($validated['location'] ?? null);
        $lineShowMap  = $tokens->location !== null ? $tokens->showMap : ($validated['show_map'] ?? false);

        $lineAssigneeIds = !empty($tokens->assigneeIds)
            ? $tokens->assigneeIds
            : ($validated['assignee_ids'] ?? null);

        // ── Quick-add: parse date / recurrence out of the task name ─────────────────
        if ($isQuickAdd && !$recurrencePattern) {
            $dateParser = new DateParser();
            $unrecognizedError = $dateParser->detectUnrecognizedPattern($taskName);
            if ($unrecognizedError) {
                throw new \InvalidArgumentException($unrecognizedError);
            }

            $parsed    = $dateParser->parseTaskInput($taskName);
            $taskName  = $parsed['name'];
            if ($parsed['nodate']) {
                $date = null;
                $time = null;
            } elseif (!$date || ($parsed['date'] !== null && $parsed['date_explicit'])) {
                $date = $parsed['date'];
                $time = $parsed['time'];
            }
            $recurrencePattern = $parsed['recurrence_pattern'];
            if ($parsed['recurrence_floating']) {
                $recurrenceFloat = true;
            }
        }

        // ── Auto-populate date from recurrence when no date specified ────────────────
        if ($recurrencePattern && !$date) {
            $dateParser = new DateParser();
            $next = $dateParser->getNextOccurrence($recurrencePattern, now());
            if ($next) {
                $date = $next->format('Y-m-d');
            }
        }

        // ── Guard against empty name after token stripping ───────────────────────────
        if ($taskName === '') {
            throw new \InvalidArgumentException('Task name is empty after removing inline tokens.');
        }

        // ── Fallback project ─────────────────────────────────────────────────────────
        if (empty($projectId)) {
            $projectId = Auth::user()->defaultProject()->id;
        }

        // ── Create the task ──────────────────────────────────────────────────────────
        $task = Task::create([
            'name'                => $taskName,
            'description'         => $validated['description'] ?? null,
            'location'            => $lineLocation,
            'show_map'            => $lineShowMap,
            'date'                => $date,
            'time'                => $time,
            'duration_minutes'    => $this->parseDurationInput($validated['duration_minutes'] ?? null),
            'project_id'          => $projectId,
            'parent_id'           => $validated['parent_id'] ?? null,
            'recurrence_pattern'  => $recurrencePattern,
            'recurrence_floating' => $recurrenceFloat,
            'creator_id'          => Auth::id(),
            'status'              => 'incomplete',
        ]);

        if (!empty($tagIds)) {
            $task->tags()->sync(array_unique($tagIds));
        }

        // ── Assignees ────────────────────────────────────────────────────────────────
        if (!empty($validated['parent_id']) && empty($lineAssigneeIds)) {
            $parentTask  = Task::find($validated['parent_id']);
            $assigneeIds = $parentTask ? $parentTask->assignees->pluck('id')->toArray() : [Auth::id()];
        } else {
            $assigneeIds = $lineAssigneeIds ?? [Auth::id()];
        }

        foreach ($assigneeIds as $assigneeId) {
            $task->assignments()->create([
                'assignee_id'    => $assigneeId,
                'assigned_by_id' => Auth::id(),
            ]);
        }

        $this->logChange($task, 'created task', 'created');

        return $task;
    }
}
