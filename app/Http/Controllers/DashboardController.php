<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\ChangeLog;
use App\Models\Project;
use App\Models\ProjectReminder;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Services\DayPdfExporter;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /** Build a project-breakdown array from a task collection for the count tooltip. */
    private function projectBreakdown(\Illuminate\Support\Collection $tasks): array
    {
        return $tasks
            ->groupBy(fn($t) => optional($t->project)->name ?? 'No Project')
            ->map(fn($g, $name) => ['name' => $name, 'count' => $g->count()])
            ->sortByDesc('count')
            ->values()
            ->toArray();
    }

    /** Apply sort ordering to a task query based on the sort parameter. */
    private function applySortOrder($query, string $sort, bool $reversed = false): void
    {
        match ($sort) {
            'created'  => $query->orderBy('created_at', $reversed ? 'asc' : 'desc'),
            'name'     => $query->orderByRaw($reversed ? 'LOWER(name) DESC' : 'LOWER(name) ASC'),
            'custom'   => $query->orderByRaw('CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END, sort_order ASC, date IS NULL, date ASC, time IS NULL, time ASC'),
            'location' => $query->orderByRaw($reversed ? '(location IS NULL OR location = \'\') ASC, LOWER(location) DESC' : '(location IS NULL OR location = \'\') ASC, LOWER(location) ASC'),
            'duration' => $query->orderByRaw($reversed ? 'CASE WHEN duration_minutes IS NULL THEN 1 ELSE 0 END, duration_minutes DESC' : 'CASE WHEN duration_minutes IS NULL THEN 1 ELSE 0 END, duration_minutes ASC'),
            default    => $query->orderByRaw($reversed ? 'date IS NULL, date DESC, time IS NULL, time DESC, created_at DESC' : 'date IS NULL, date ASC, time IS NULL, time ASC, created_at ASC'),
        };
    }

    /** The incomplete-task query behind the day view for a given date (also used by the PDF export). */
    private function incompleteTasksForDate(string $dateStr, string $sort, bool $reversed)
    {
        $query = Task::query()
            ->visibleTo(Auth::id())
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done')
            ->where('date', $dateStr)
            ->whereHas('project', fn($pq) => $pq->whereNotIn('status', ['archived', 'done']))
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments', 'completionLog.user']);
        $this->applySortOrder($query, $sort, $reversed);

        return $query;
    }

    /** Tasks completed (status = done) on the given date, in the day view's display order. */
    private function completedTasksForDate(string $dateStr)
    {
        return Task::query()
            ->visibleTo(Auth::id())
            ->where('status', 'done')
            ->whereDate('completed_at', $dateStr)
            ->orderByRaw('time IS NULL, time ASC');
    }

    /**
     * Tasks archived on the given date — either archived that day (by completed_at),
     * or dated for that day and belonging to a project that's since been
     * archived/marked done — in the day view's display order.
     */
    private function archivedTasksForDate(string $dateStr)
    {
        return Task::query()
            ->visibleTo(Auth::id())
            ->where('status', 'archived')
            ->where(function ($q) use ($dateStr) {
                $q->whereDate('completed_at', $dateStr)
                  ->orWhere(function ($q2) use ($dateStr) {
                      $q2->where('date', $dateStr)
                         ->whereHas('project', fn($pq) => $pq->whereIn('status', ['archived', 'done']));
                  });
            })
            ->orderByRaw('time IS NULL, time ASC');
    }

    /**
     * The three status groups (Incomplete/Done/Archived) for a day export — shared by
     * exportDayMarkdown() and exportDayPdf() so they define "this day's tasks" the same
     * way as each other, and the same way day() itself does.
     *
     * When the request carries an 'ids' param — even an empty one; day.blade.php's export
     * buttons always send it now, snapshotting exactly which tasks are currently rendered
     * visible on the page (on-page filter, plus whether Done/Archived are expanded) — every
     * group is narrowed to that ID set on top of its own already-authorized/scoped query,
     * so an id can only ever be excluded (wrong date, wrong status, not visible to this
     * user, ...), never included beyond what the query would already allow.
     *
     * No 'ids' param at all (a manually-typed/bookmarked URL, bypassing the page's JS)
     * falls back to the full unfiltered day: every incomplete task, Done/Archived empty —
     * each export's original behavior before either could be filtered.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection}
     */
    private function dayExportTaskGroups(string $dateStr, Request $request, string $sort = 'date', bool $reversed = false): array
    {
        $incompleteQuery = $this->incompleteTasksForDate($dateStr, $sort, $reversed);

        if (!$request->has('ids')) {
            return [$incompleteQuery->get(), collect(), collect()];
        }

        $ids = array_map('intval', (array) $request->input('ids', []));
        $doneQuery     = $this->completedTasksForDate($dateStr);
        $archivedQuery = $this->archivedTasksForDate($dateStr);

        foreach ([$incompleteQuery, $doneQuery, $archivedQuery] as $query) {
            $query->whereIn('id', $ids);
        }

        return [$incompleteQuery->get(), $doneQuery->get(), $archivedQuery->get()];
    }

    /** Projects, tags, users, and locations needed by the quick-add autocomplete. */
    private function quickAddData(): array
    {
        $projects = Project::activeForUser(Auth::id())->get(['id', 'name'])->sort(fn ($a, $b) => strnatcasecmp($a->name, $b->name))->values();

        $tags = Tag::active()->orderByRaw('LOWER(tag_name)')->get(['id', 'tag_name', 'color']);

        $users = User::whereNull('email_enabled_at')
            ->orderByRaw('LOWER(name)')
            ->get(['id', 'name']);

        $locations = Task::where('status', 'incomplete')
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->visibleTo(Auth::id())
            ->distinct()
            ->orderByRaw('LOWER(location)')
            ->pluck('location');

        return compact('projects', 'tags', 'users', 'locations');
    }

    public function inbox()
    {
        // /inbox is kept for bookmarks; redirect to the user's default project.
        $defaultProject = Auth::user()->defaultProject();
        return redirect()->route('projects.show', $defaultProject);
    }

    public function overdue(Request $request)
    {
        $sort     = $request->input('sort', 'date');
        $reversed = $request->boolean('reversed');
        $perPage  = (int) config('taskfiend.pagination_per_page');
        $page     = max(1, (int) $request->input('page', 1));
        $offset   = ($page - 1) * $perPage;

        $tasksQuery = Task::query()
            ->visibleTo(Auth::id())
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done')
            ->whereNotNull('date')
            ->where('date', '<', today()->format('Y-m-d'))
            ->whereHas('project', fn($pq) => $pq->whereNotIn('status', ['archived', 'done']))
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments', 'completionLog.user']);

        $this->applySortOrder($tasksQuery, $sort, $reversed);

        $totalCount = $tasksQuery->count();
        $tasksRaw   = $tasksQuery->skip($offset)->take($perPage + 1)->get();
        $hasMore    = $tasksRaw->count() > $perPage;
        $tasks      = $hasMore ? $tasksRaw->slice(0, $perPage) : $tasksRaw;

        $breakdown = $this->projectBreakdown($tasks);

        return view('dashboard.overdue', array_merge(
            compact('tasks', 'sort', 'breakdown', 'totalCount', 'hasMore', 'page', 'perPage'),
            $this->quickAddData()
        ));
    }

    public function undated(Request $request)
    {
        $sort     = $request->input('sort', 'created');
        $reversed = $request->boolean('reversed');

        $tasksQuery = Task::query()
            ->visibleTo(Auth::id())
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done')
            ->whereNull('date')
            ->whereHas('project', fn($pq) => $pq->whereNotIn('status', ['archived', 'done']))
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments', 'completionLog.user']);

        $this->applySortOrder($tasksQuery, $sort, $reversed);
        $tasks = $tasksQuery->get();

        $breakdown = $this->projectBreakdown($tasks);

        return view('dashboard.undated', array_merge(compact('tasks', 'sort', 'breakdown'), $this->quickAddData()));
    }

    public function all(Request $request)
    {
        $sort     = $request->input('sort', 'date');
        $reversed = $request->boolean('reversed');

        $tasksQuery = Task::query()
            ->visibleTo(Auth::id())
            ->where('status', '!=', 'archived')
            ->whereHas('project', fn($pq) => $pq->whereNotIn('status', ['archived', 'done']))
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments', 'completionLog.user'])
            ->orderByRaw("CASE WHEN status = 'done' THEN 1 ELSE 0 END ASC");

        $this->applySortOrder($tasksQuery, $sort, $reversed);
        $tasks = $tasksQuery->get();

        $breakdown = $this->projectBreakdown($tasks);

        return view('dashboard.all', array_merge(compact('tasks', 'sort', 'breakdown'), $this->quickAddData()));
    }

    public function calendar(Request $request)
    {
        $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year'  => 'nullable|integer|min:2000|max:2100',
        ]);

        $month = $request->input('month', now()->month);
        $year = $request->input('year', now()->year);

        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        $tasks = Task::query()
            ->visibleTo(Auth::id())
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done')
            ->whereNotNull('date')
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->whereHas('project', fn($pq) => $pq->whereNotIn('status', ['archived', 'done']))
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments', 'completionLog.user'])
            ->orderByRaw('time IS NULL, time ASC')
            ->get()
            ->groupBy(function ($task) {
                return $task->date instanceof \Carbon\Carbon ? $task->date->format('Y-m-d') : $task->date;
            });

        $overdueCount = Task::query()
            ->visibleTo(Auth::id())
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done')
            ->whereNotNull('date')
            ->where('date', '<', today()->format('Y-m-d'))
            ->whereHas('project', fn($pq) => $pq->whereNotIn('status', ['archived', 'done']))
            ->count();

        $undatedCount = Task::query()
            ->visibleTo(Auth::id())
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done')
            ->whereNull('date')
            ->whereHas('project', fn($pq) => $pq->whereNotIn('status', ['archived', 'done']))
            ->count();

        return view('dashboard.calendar', compact('tasks', 'month', 'year', 'startDate', 'overdueCount', 'undatedCount'));
    }

    public function exportOverdueMarkdown(Request $request)
    {
$tasks = Task::visibleTo(Auth::id())
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done')
            ->whereNotNull('date')
            ->where('date', '<', today()->format('Y-m-d'))
            ->whereHas('project', fn($pq) => $pq->whereNotIn('status', ['archived', 'done']))
            ->orderByRaw('date ASC, time IS NULL, time ASC')
            ->get();

        $lines = ['# Overdue Tasks'];
        foreach ($tasks as $task) {
            $lines[] = '* ' . $task->name;
        }

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type'        => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="overdue_' . today()->format('Y-m-d') . '.md"',
        ]);
    }

    /**
     * Mirrors whatever's currently on screen, same as exportDayPdf() below: the
     * day view's sort/reversed (round-tripped through the URL) and the on-page
     * filter/fold state, via the same 'ids[]' snapshot mechanism — see
     * dayExportTaskGroups() and day.blade.php's exportParams(). No 'ids' param
     * at all (a manually-typed/bookmarked URL, bypassing the page's JS) falls
     * back to the full unfiltered day: every incomplete task, Done/Archived
     * omitted — this export's original behavior before it could be filtered.
     */
    public function exportDayMarkdown(Request $request)
    {
        $date = $request->input('date', today()->format('Y-m-d'));
        $carbonDate = Carbon::parse($date);
        $dateStr = $carbonDate->format('Y-m-d');
        $sort = $request->input('sort', 'date');
        $reversed = $request->boolean('reversed');

        [$incomplete, $done, $archived] = $this->dayExportTaskGroups($dateStr, $request, $sort, $reversed);

        $lines = ['# ' . $carbonDate->format('l, F j, Y')];

        foreach ([['## Incomplete', $incomplete], ['## Done', $done], ['## Archived', $archived]] as [$heading, $group]) {
            if ($group->isNotEmpty()) {
                $lines[] = '';
                $lines[] = $heading;
                foreach ($group as $task) {
                    $lines[] = '* ' . $task->name;
                }
            }
        }

        return response(implode("\n", $lines) . "\n", 200, [
            'Content-Type'        => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="tasks_' . $dateStr . '.md"',
        ]);
    }

    /**
     * Printable PDF of a day's incomplete task list, for keeping the day's
     * tasks on paper instead of a phone. Defaults to today; any date the day
     * view itself supports works (today and future dates — past dates render
     * through the separate dayReview() view/template, which doesn't carry
     * this button at all, so they're out of scope here).
     *
     * Known limitation, unchanged by allowing arbitrary dates: a future
     * day's recurring tasks that haven't been generated yet (the previous
     * occurrence hasn't been completed) won't appear, since this only
     * exports task rows that already exist. Projecting anticipated-but-not-
     * yet-created recurring occurrences is a distinct, not-yet-built
     * feature — see CLAUDE.md.
     *
     * Mirrors whatever's currently on screen: same sort/reversed as the day
     * view (already round-tripped through the URL), plus the on-page filter
     * and Done/Archived fold state — client-side only, so day.blade.php's
     * export buttons send the exact set of currently-visible task IDs
     * (`ids[]`) rather than the raw filter text. See dayExportTaskGroups()
     * for how that ID set narrows each status group; `filter` (the raw
     * text) is still accepted, but purely for display in the PDF's header
     * meta line.
     */
    public function exportDayPdf(Request $request)
    {
        $date       = $request->input('date', today()->format('Y-m-d'));
        $carbonDate = Carbon::parse($date);
        $dateStr    = $carbonDate->format('Y-m-d');
        $sort       = $request->input('sort', 'date');
        $reversed   = $request->boolean('reversed');
        $filter     = $request->input('filter');

        [$incomplete, $done, $archived] = $this->dayExportTaskGroups($dateStr, $request, $sort, $reversed);
        $tasks = $incomplete->concat($done)->concat($archived);

        if ($tasks->isEmpty()) {
            return back()->with('error', 'No tasks to export.');
        }

        $pdf = DayPdfExporter::build($carbonDate, $tasks, $filter, $sort, $reversed, (int) config('taskfiend.day_export_columns'));

        return response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="taskfiend-day-' . $dateStr . '.pdf"',
        ]);
    }

    public function day(Request $request)
    {
        $date = $request->input('date', today()->format('Y-m-d'));
        $carbonDate = Carbon::parse($date);
        $dateStr = $carbonDate->format('Y-m-d');

        // Past days get the review layout
        if ($carbonDate->startOfDay()->lt(today()->startOfDay())) {
            return $this->dayReview($carbonDate, $dateStr);
        }

        $sort     = $request->input('sort', 'date');
        $reversed = $request->boolean('reversed');

        $tasks = $this->incompleteTasksForDate($dateStr, $sort, $reversed)->get();

        $perPage = (int) config('taskfiend.pagination_per_page');

$dayWith = ['creator', 'project', 'tags', 'assignees', 'attachments', 'comments', 'completionLog.user'];

        $completedTasksTotal = $this->completedTasksForDate($dateStr)->count();

        $completedTasksRaw = $this->completedTasksForDate($dateStr)
            ->with($dayWith)
            ->take($perPage + 1)
            ->get();

        $completedTasksHasMore = $completedTasksRaw->count() > $perPage;
        $completedTasks = $completedTasksHasMore ? $completedTasksRaw->slice(0, $perPage) : $completedTasksRaw;

        // Tasks archived on this day (by completed_at, status differentiates done vs archived),
        // OR tasks dated for this day that belong to an archived/done project.
        $archivedTasksTotal = $this->archivedTasksForDate($dateStr)->count();

        $archivedTasksRaw = $this->archivedTasksForDate($dateStr)
            ->with($dayWith)
            ->take($perPage + 1)
            ->get();

        $archivedTasksHasMore = $archivedTasksRaw->count() > $perPage;
        $archivedTasks = $archivedTasksHasMore ? $archivedTasksRaw->slice(0, $perPage) : $archivedTasksRaw;

        $overdueCount = 0;
        if ($carbonDate->isToday()) {
            $overdueCount = Task::visibleTo(Auth::id())
                ->where('status', '!=', 'archived')
                ->where('status', '!=', 'done')
                ->whereNotNull('date')
                ->where('date', '<', today()->format('Y-m-d'))
                ->whereHas('project', fn($pq) => $pq->whereNotIn('status', ['archived', 'done']))
                ->count();
        }

        $breakdown = $this->projectBreakdown($tasks);

        $projectReminders = ProjectReminder::select('project_reminders.*')
            ->where('p.user_id', Auth::id())
            ->join('projects as p', 'p.id', '=', 'project_reminders.project_id')
            ->where('status', 'incomplete')
            ->where('dismissed', false)
            ->whereDate('date', '<=', $dateStr)
            ->with('project')
            ->orderBy('date')
            ->get();

        return view('dashboard.day', array_merge(compact(
            'tasks', 'date', 'carbonDate', 'overdueCount', 'sort', 'breakdown',
            'completedTasks', 'completedTasksTotal', 'completedTasksHasMore',
            'archivedTasks', 'archivedTasksTotal', 'archivedTasksHasMore',
            'projectReminders'
        ), $this->quickAddData()));
    }

    public function dayCompletedTasks(Request $request)
    {
        $perPage = (int) config('taskfiend.pagination_per_page');
        $page    = max(1, (int) $request->get('page', 1));
        $offset  = ($page - 1) * $perPage;
        $dateStr = $request->get('date', today()->format('Y-m-d'));

$tasks = Task::visibleTo(Auth::id())
            ->where('status', 'done')
            ->whereDate('completed_at', $dateStr)
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments', 'completionLog.user'])
            ->orderByRaw('time IS NULL, time ASC')
            ->skip($offset)->take($perPage + 1)
            ->get();

        $hasMore = $tasks->count() > $perPage;
        if ($hasMore) {
            $tasks = $tasks->slice(0, $perPage);
        }

        $hideDate = true;
        return response()->json([
            'html'     => view('tasks.partials.completed-list', compact('tasks', 'hideDate'))->render(),
            'hasMore'  => $hasMore,
            'nextPage' => $page + 1,
        ]);
    }

    public function dayArchivedTasks(Request $request)
    {
        $perPage = (int) config('taskfiend.pagination_per_page');
        $page    = max(1, (int) $request->get('page', 1));
        $offset  = ($page - 1) * $perPage;
        $dateStr = $request->get('date', today()->format('Y-m-d'));

$tasks = Task::visibleTo(Auth::id())
            ->where('status', 'archived')
            ->where(function ($q) use ($dateStr) {
                $q->whereDate('completed_at', $dateStr)
                  ->orWhere(function ($q2) use ($dateStr) {
                      $q2->where('date', $dateStr)
                         ->whereHas('project', fn($pq) => $pq->whereIn('status', ['archived', 'done']));
                  });
            })
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments', 'completionLog.user'])
            ->orderByRaw('time IS NULL, time ASC')
            ->skip($offset)->take($perPage + 1)
            ->get();

        $hasMore = $tasks->count() > $perPage;
        if ($hasMore) {
            $tasks = $tasks->slice(0, $perPage);
        }

        $readOnly = true;
        $showAsArchived = true;
        $hideDate = true;
        return response()->json([
            'html'     => view('tasks.partials.completed-list', compact('tasks', 'readOnly', 'showAsArchived', 'hideDate'))->render(),
            'hasMore'  => $hasMore,
            'nextPage' => $page + 1,
        ]);
    }

    private function dayReview(Carbon $carbonDate, string $dateStr)
    {
        $userId = Auth::id();
        $taskWith = ['creator', 'project', 'tags', 'assignees', 'attachments', 'comments', 'completionLog.user'];

        // Tasks directly dated for this day where the user is creator or assignee
        $datedTasks = Task::visibleTo($userId)
            ->where('date', $dateStr)
            ->with($taskWith)
            ->orderByRaw('time IS NULL, time ASC')
            ->get()
            ->keyBy('id');

        // Change log entries where date was changed FROM this day
        $rescheduledLogs = ChangeLog::where('entity_type', 'tasks')
            ->where('field', 'date')
            ->where('old_value', $dateStr)
            ->get()
            ->keyBy('entity_id');

        $rescheduledTaskIds = $rescheduledLogs->keys()->toArray();

        // Load those tasks that I can see (creator or assignee) and aren't already in datedTasks
        $rescheduledTasks = collect();
        if (!empty($rescheduledTaskIds)) {
            $rescheduledTasks = Task::whereIn('id', $rescheduledTaskIds)
                ->whereNotIn('id', $datedTasks->keys()->toArray())
                ->visibleTo($userId)
                ->with($taskWith)
                ->get()
                ->keyBy('id');
        }

        // Assignments created on this day by someone else
        $newAssignmentTaskIds = Assignment::where('assignee_id', $userId)
            ->where('assigned_by_id', '!=', $userId)
            ->whereDate('created_at', $dateStr)
            ->pluck('task_id')
            ->toArray();

        // Load those tasks that aren't already captured above
        $alreadyCapturedIds = array_merge(
            $datedTasks->keys()->toArray(),
            $rescheduledTasks->keys()->toArray()
        );

        $assignedThatDayTasks = collect();
        if (!empty($newAssignmentTaskIds)) {
            $freshIds = array_diff($newAssignmentTaskIds, $alreadyCapturedIds);
            if (!empty($freshIds)) {
                $assignedThatDayTasks = Task::whereIn('id', $freshIds)
                    ->with($taskWith)
                    ->get()
                    ->keyBy('id');
            }
        }

        // Categorise dated tasks into sections
        $completedOnDay = collect();
        $completedLater = collect();
        $stillOpen      = collect();
        $archivedOnDay  = collect();

        foreach ($datedTasks as $task) {
            if ($task->status === 'done') {
                $completedAt = $task->completed_at ? Carbon::parse($task->completed_at) : null;
                if ($completedAt && $completedAt->isSameDay($carbonDate)) {
                    $completedOnDay->push($task);
                } else {
                    $completedLater->push($task);
                }
            } elseif ($task->status === 'archived' || ($task->project && in_array($task->project->status, ['archived', 'done']))) {
                $archivedOnDay->push($task);
            } else {
                $stillOpen->push($task);
            }
        }

        // Attach new_date to rescheduled tasks for display
        foreach ($rescheduledTasks as $task) {
            $log = $rescheduledLogs->get($task->id);
            $task->rescheduled_to = $log ? $log->new_value : null;
        }

        return view('dashboard.day-review', compact(
            'carbonDate',
            'dateStr',
            'completedOnDay',
            'completedLater',
            'stillOpen',
            'archivedOnDay',
            'rescheduledTasks',
            'assignedThatDayTasks'
        ));
    }
}
