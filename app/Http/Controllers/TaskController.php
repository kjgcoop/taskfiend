<?php

namespace App\Http\Controllers;

use App\Models\ChangeLog;
use App\Models\Task;
use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use App\Services\DateParser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $query = Task::query()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            });

        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', '!=', 'archived')
                  ->where('status', '!=', 'done');
        }

        if ($request->has('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        $tasks = $query->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments'])
            ->orderBy('date')
            ->get();

        return view('tasks.index', compact('tasks'));
    }

    public function create(Request $request)
    {
        $projects = Project::where('user_id', Auth::id())
            ->orWhereHas('tasks.assignees', function ($query) {
                $query->where('users.id', Auth::id());
            })
            ->where('status', '!=', 'archived')
            ->orderByRaw('LOWER(name)')
            ->get();

        $tags = Tag::orderByRaw('LOWER(tag_name)')->get();
        $users = User::whereNull('email_enabled_at')->get();

        // Default to user's Inbox project if no project preselected
        $inboxProject = Project::where('user_id', Auth::id())->where('is_inbox', true)->first();
        $preselectedProjectId = $request->query('project_id') ?? ($inboxProject ? $inboxProject->id : null);
        $preselectedDate = $request->query('date');

        // Handle parent task preselection
        $preselectedParentId = $request->query('parent_id');
        $preselectedParentTask = null;

        if ($preselectedParentId) {
            $preselectedParentTask = Task::find($preselectedParentId);
            if ($preselectedParentTask) {
                $this->authorizeTaskAccess($preselectedParentTask);
            }
        }

        // Get available parent tasks (exclude archived)
        $availableParents = Task::where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', '=', 'incomplete')
            ->with('parent') // For depth calculation
            ->orderByRaw('LOWER(name)')
            ->get();

        return view('tasks.create', compact(
            'projects', 'tags', 'users',
            'preselectedProjectId', 'preselectedDate',
            'preselectedParentId', 'preselectedParentTask', 'availableParents'
        ));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'nullable|date_format:Y-m-d',
            'time' => 'nullable|date_format:H:i',
            'project_id' => 'nullable|exists:projects,id',
            'parent_id' => 'nullable|exists:tasks,id',
            'recurrence_pattern' => 'nullable|string',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
        ]);

        // Authorization check for parent task
        if (isset($validated['parent_id'])) {
            $parentTask = Task::findOrFail($validated['parent_id']);
            $this->authorizeTaskAccess($parentTask);

            // Prevent creating subtask under archived parent
            if ($parentTask->status === 'archived') {
                return back()->withErrors([
                    'parent_id' => 'Cannot create subtask under an archived task.'
                ])->withInput();
            }
        }

        $taskName = $validated['name'];
        $date = $validated['date'] ?? null;
        $time = $validated['time'] ?? null;
        $recurrencePattern = $validated['recurrence_pattern'] ?? null;

        $dateParser = new DateParser();

        // Validate explicitly provided recurrence pattern
        if ($recurrencePattern && !$dateParser->isValidRecurrencePattern($recurrencePattern)) {
            return back()->withErrors([
                'recurrence_pattern' => "The recurrence pattern '{$recurrencePattern}' is not recognized. Supported patterns include: daily, every other day, weekdays, weekends, every Monday/Tuesday/etc., every other Monday/Tuesday/etc., every 2 weeks, every 1st (monthly), every first Monday (monthly), yearly."
            ])->withInput();
        }

        // Auto-parse date and recurrence from task name if not explicitly provided
        if (!$recurrencePattern) {
            // Check for unrecognized recurrence patterns first
            $unrecognizedError = $dateParser->detectUnrecognizedPattern($taskName);
            if ($unrecognizedError) {
                return back()->withErrors(['name' => $unrecognizedError])->withInput();
            }

            $parsed = $dateParser->parseTaskInput($taskName);
            $taskName = $parsed['name'];
            // Only auto-fill date/time from task name if not explicitly provided
            if (!$date) {
                $date = $parsed['date'];
                $time = $parsed['time'];
            }
            $recurrencePattern = $parsed['recurrence_pattern'];
        }

        // Auto-populate date from recurrence pattern if recurrence is set but date is not
        if ($recurrencePattern && !$date) {
            $nextOccurrence = $dateParser->getNextOccurrence($recurrencePattern, now());
            if ($nextOccurrence) {
                $date = $nextOccurrence->format('Y-m-d');
            }
        }

        $task = Task::create([
            'name' => $taskName,
            'description' => $validated['description'] ?? null,
            'date' => $date,
            'time' => $time,
            'project_id' => $validated['project_id'] ?? null,
            'parent_id' => $validated['parent_id'] ?? null,
            'recurrence_pattern' => $recurrencePattern,
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
            $assigneeIds = $validated['assignee_ids'] ?? [Auth::id()];
        }
        foreach ($assigneeIds as $assigneeId) {
            $task->assignments()->create([
                'assignee_id' => $assigneeId,
                'assigned_by_id' => Auth::id(),
            ]);
        }

        $this->logChange($task, 'created task');

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Task created successfully.');
    }

    public function show(Task $task)
    {
        $this->authorizeTaskAccess($task);

        $task->load(['creator', 'project', 'tags', 'assignees', 'assignments.assignedBy',
                     'attachments', 'comments.user', 'changeLogs.user', 'children', 'parent']);

        $projects = Project::where('user_id', Auth::id())
            ->orWhereHas('tasks.assignees', function ($query) {
                $query->where('users.id', Auth::id());
            })
            ->where('status', '!=', 'archived')
            ->orderByRaw('LOWER(name)')
            ->get();

        $tags = Tag::orderByRaw('LOWER(tag_name)')->get();
        $users = User::whereNull('email_enabled_at')->get();
        $inboxProjectId = Project::where('user_id', Auth::id())->where('is_inbox', true)->value('id');

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

        $availableParents = Task::where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->whereNotIn('id', $excludeIds)
            ->where('status', '!=', 'archived')
            ->with('parent') // For depth calculation
            ->orderByRaw('LOWER(name)')
            ->get();

        return view('tasks.show', compact('task', 'projects', 'tags', 'users', 'nextDueDate', 'availableParents', 'inboxProjectId'));
    }

    public function edit(Task $task)
    {
        $this->authorizeTaskAccess($task);

        $projects = Project::where('user_id', Auth::id())
            ->orWhereHas('tasks.assignees', function ($query) {
                $query->where('users.id', Auth::id());
            })
            ->where('status', '!=', 'archived')
            ->orderByRaw('LOWER(name)')
            ->get();

        $tags = Tag::orderByRaw('LOWER(tag_name)')->get();
        $users = User::whereNull('email_enabled_at')->get();
        $inboxProjectId = Project::where('user_id', Auth::id())->where('is_inbox', true)->value('id');

        // Get available parent tasks (exclude self and descendants to prevent cycles)
        $excludeIds = $task->getAllDescendants()->pluck('id')->push($task->id);

        $availableParents = Task::where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->whereNotIn('id', $excludeIds)
            ->where('status', '!=', 'archived')
            ->with('parent') // For depth calculation
            ->orderByRaw('LOWER(name)')
            ->get();

        return view('tasks.edit', compact('task', 'projects', 'tags', 'users', 'availableParents', 'inboxProjectId'));
    }

    public function update(Request $request, Task $task)
    {
        $this->authorizeTaskAccess($task);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'date' => 'nullable|date_format:Y-m-d',
            'time' => 'nullable|date_format:H:i',
            'project_id' => 'nullable|exists:projects,id',
            'parent_id' => 'nullable|exists:tasks,id',
            'recurrence_pattern' => 'nullable|string',
            'status' => 'in:incomplete,done,archived',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
        ]);

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

        // Validate recurrence pattern if provided
        if (isset($validated['recurrence_pattern']) && !empty($validated['recurrence_pattern'])) {
            $dateParser = new DateParser();
            if (!$dateParser->isValidRecurrencePattern($validated['recurrence_pattern'])) {
                return back()->withErrors([
                    'recurrence_pattern' => "The recurrence pattern '{$validated['recurrence_pattern']}' is not recognized. Supported patterns include: daily, every other day, weekdays, weekends, every Monday/Tuesday/etc., every other Monday/Tuesday/etc., every 2 weeks, every 1st (monthly), every first Monday (monthly), yearly."
                ])->withInput();
            }
        }

        // Prevent subtasks from having recurrence patterns
        if (isset($validated['recurrence_pattern']) && $validated['recurrence_pattern'] && $task->parent_id) {
            return back()->withErrors([
                'recurrence_pattern' => 'Subtasks cannot have their own recurrence pattern. Only root-level tasks can recur.'
            ])->withInput();
        }

        // Check if marking as done with incomplete descendants
        $statusChangedToDone = isset($validated['status'])
            && $validated['status'] === 'done'
            && $task->status !== 'done';

        if ($statusChangedToDone && $task->hasIncompleteDescendants()) {
            // Auto-complete all descendants
            $this->completeTaskAndDescendants($task);
            $this->logChange($task, 'marked done with all subtasks');

            // Handle recurring task AFTER completion
            if ($task->recurrence_pattern) {
                $this->createRecurringTask($task);
            }

            if ($request->has('quick_complete')) {
                return redirect()->back()
                    ->with('success', 'Task and all subtasks marked as done.');
            }

            return redirect()->route('tasks.show', $task)
                ->with('success', 'Task and all subtasks marked as done.');
        }

        // Check if archiving with descendants
        if (isset($validated['status']) && $validated['status'] === 'archived' && $task->children->count() > 0) {
            // Auto-archive all descendants
            $descendants = $task->getAllDescendants();
            foreach ($descendants as $descendant) {
                if ($descendant->status !== 'archived') {
                    $descendant->status = 'archived';
                    $descendant->save();
                    $this->logChange($descendant, 'auto-archived (parent archived)');
                }
            }
        }

        $changes = [];
        foreach (['name', 'description', 'date', 'time', 'project_id', 'parent_id', 'recurrence_pattern', 'status'] as $field) {
            if (isset($validated[$field]) && $task->$field != $validated[$field]) {
                $changes[$field] = ['old' => $task->$field, 'new' => $validated[$field]];
                $task->$field = $validated[$field];
            }
        }

        $task->save();

        if (isset($validated['tag_ids'])) {
            $task->tags()->sync($validated['tag_ids']);
        }

        if (isset($validated['assignee_ids']) && $task->creator_id === Auth::id()) {
            $currentAssigneeIds = $task->assignments->pluck('assignee_id')->toArray();
            $newAssigneeIds = $validated['assignee_ids'];

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
            $this->logChange($task, "changed {$field} from {$change['old']} to {$change['new']}");
        }

        if ($task->status === 'done' && $task->recurrence_pattern) {
            $this->createRecurringTask($task);
        }

        // Handle quick complete from task list
        if ($request->has('quick_complete')) {
            return redirect()->back()
                ->with('success', 'Task marked as done.');
        }

        return redirect()->route('tasks.show', $task)
            ->with('success', 'Task updated successfully.');
    }

    public function updateField(Request $request, Task $task)
    {
        $this->authorizeTaskAccess($task);

        $field = $request->input('field');
        $allowedFields = ['name', 'description', 'status', 'date', 'time', 'project_id', 'parent_id', 'recurrence_pattern', 'tag_ids', 'assignee_ids'];

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
                $task->tags()->sync($tagIds);
                $this->logChange($task, 'updated tags');
            } elseif ($field === 'assignee_ids') {
                if ($task->creator_id !== Auth::id()) {
                    return response()->json(['success' => false, 'message' => 'Only creator can change assignees'], 403);
                }

                $assigneeIds = $request->input('assignee_ids', []);
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
            } else {
                $value = $request->input('value');

                if ($field === 'status') {
                    if (!in_array($value, ['incomplete', 'done', 'archived'])) {
                        return response()->json(['success' => false, 'message' => 'Invalid status'], 400);
                    }

                    // Handle completion with subtasks
                    if ($value === 'done' && $task->status !== 'done' && $task->hasIncompleteDescendants()) {
                        $this->completeTaskAndDescendants($task);
                        $this->logChange($task, 'marked done with all subtasks');

                        if ($task->recurrence_pattern) {
                            $this->createRecurringTask($task);
                        }

                        return response()->json(['success' => true, 'reload' => true]);
                    }

                    // Handle archiving with descendants
                    if ($value === 'archived' && $task->children->count() > 0) {
                        $descendants = $task->getAllDescendants();
                        foreach ($descendants as $descendant) {
                            if ($descendant->status !== 'archived') {
                                $descendant->status = 'archived';
                                $descendant->save();
                                $this->logChange($descendant, 'auto-archived (parent archived)');
                            }
                        }
                    }
                }

                $task->$field = $value;
                $task->save();

                $this->logChange($task, "updated {$field}");

                if ($field === 'status' && $value === 'done' && $task->recurrence_pattern) {
                    $this->createRecurringTask($task);
                }
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(Task $task)
    {
        abort(403, 'Tasks cannot be deleted. Please archive instead.');
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

        if (!$isCreator && !$isAssignee && !$hasAncestorAccess) {
            abort(403, 'You do not have access to this task.');
        }
    }

    protected function logChange(Task $task, string $description)
    {
        $task->changeLogs()->create([
            'date' => now(),
            'user_id' => Auth::id(),
            'entity_type' => 'tasks',
            'entity_id' => $task->id,
            'description' => $description,
        ]);
    }

    /**
     * Mark task and all descendant subtasks as done
     */
    protected function completeTaskAndDescendants(Task $task): void
    {
        // Mark all descendants as done first (bottom-up)
        $descendants = $task->getAllDescendants();

        foreach ($descendants as $descendant) {
            if ($descendant->status !== 'done') {
                $descendant->status = 'done';
                $descendant->save();
                $this->logChange($descendant, 'auto-completed (parent marked done)');
            }
        }

        // Mark parent as done
        if ($task->status !== 'done') {
            $task->status = 'done';
            $task->save();
        }
    }

    protected function createRecurringTask(Task $originalTask)
    {
        if (!$originalTask->recurrence_pattern) {
            return;
        }

        $dateParser = new DateParser();
        $baseDate = $originalTask->date ? Carbon::parse($originalTask->date) : now();
        $nextOccurrence = $dateParser->getNextOccurrence(
            $originalTask->recurrence_pattern,
            $baseDate
        );

        if (!$nextOccurrence) {
            return;
        }

        $nextDate = $nextOccurrence->format('Y-m-d');

        $existingTask = Task::where('creator_id', $originalTask->creator_id)
            ->where('name', $originalTask->name)
            ->where('recurrence_pattern', $originalTask->recurrence_pattern)
            ->where('status', 'incomplete')
            ->where('date', $nextDate)
            ->first();

        if ($existingTask) {
            return;
        }

        $newTask = Task::create([
            'name' => $originalTask->name,
            'description' => $originalTask->description,
            'date' => $nextDate,
            'time' => $originalTask->time,
            'project_id' => $originalTask->project_id,
            'parent_id' => null, // Recurring tasks are always root-level
            'recurrence_pattern' => $originalTask->recurrence_pattern,
            'creator_id' => $originalTask->creator_id,
            'status' => 'incomplete',
        ]);

        $newTask->tags()->sync($originalTask->tags->pluck('id'));

        foreach ($originalTask->assignments as $assignment) {
            $newTask->assignments()->create([
                'assignee_id' => $assignment->assignee_id,
                'assigned_by_id' => $assignment->assigned_by_id,
            ]);
        }

        foreach ($originalTask->attachments as $attachment) {
            $newTask->attachments()->create([
                'user_id' => $attachment->user_id,
                'file_path' => $attachment->file_path,
                'original_filename' => $attachment->original_filename,
                'mime_type' => $attachment->mime_type,
                'file_size' => $attachment->file_size,
            ]);
        }

        // Recursively copy all subtasks
        $this->copySubtasksToNewTask($originalTask, $newTask);

        $newTask->changeLogs()->create([
            'date' => now(),
            'user_id' => Auth::id(),
            'entity_type' => 'tasks',
            'entity_id' => $newTask->id,
            'description' => 'created recurring task',
        ]);
    }

    /**
     * Recursively copy all subtasks from original to new task
     */
    protected function copySubtasksToNewTask(Task $originalTask, Task $newTask): void
    {
        foreach ($originalTask->children as $originalSubtask) {
            // Create new subtask
            $newSubtask = Task::create([
                'name' => $originalSubtask->name,
                'description' => $originalSubtask->description,
                'date' => $originalSubtask->date,
                'time' => $originalSubtask->time,
                'project_id' => $originalSubtask->project_id,
                'recurrence_pattern' => null, // Subtasks don't have their own recurrence
                'parent_id' => $newTask->id,
                'creator_id' => $originalSubtask->creator_id,
                'status' => 'incomplete',
            ]);

            // Copy tags
            $newSubtask->tags()->sync($originalSubtask->tags->pluck('id'));

            // Copy assignments
            foreach ($originalSubtask->assignments as $assignment) {
                $newSubtask->assignments()->create([
                    'assignee_id' => $assignment->assignee_id,
                    'assigned_by_id' => $assignment->assigned_by_id,
                ]);
            }

            // Copy attachments
            foreach ($originalSubtask->attachments as $attachment) {
                $newSubtask->attachments()->create([
                    'user_id' => $attachment->user_id,
                    'file_path' => $attachment->file_path,
                    'original_filename' => $attachment->original_filename,
                    'mime_type' => $attachment->mime_type,
                    'file_size' => $attachment->file_size,
                ]);
            }

            // Log creation
            $newSubtask->changeLogs()->create([
                'date' => now(),
                'user_id' => Auth::id(),
                'entity_type' => 'tasks',
                'entity_id' => $newSubtask->id,
                'description' => 'created subtask from recurring parent',
            ]);

            // Recursively copy this subtask's subtasks
            $this->copySubtasksToNewTask($originalSubtask, $newSubtask);
        }
    }
}
