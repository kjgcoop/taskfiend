<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\ChangeLog;
use App\Models\Project;
use App\Models\ProjectReminder;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
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

    /** Projects, tags, users, and locations needed by the quick-add autocomplete. */
    private function quickAddData(): array
    {
        $projects = Project::activeForUser(Auth::id())->get(['id', 'name'])->sort(fn ($a, $b) => strnatcasecmp($a->name, $b->name))->values();

        $tags = Tag::orderByRaw('LOWER(tag_name)')->get(['id', 'tag_name', 'color']);

        $users = User::whereNull('email_enabled_at')
            ->orderByRaw('LOWER(name)')
            ->get(['id', 'name']);

        $locations = Task::where('status', 'incomplete')
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($subq) {
                      $subq->where('users.id', Auth::id());
                  });
            })
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

        $tasksQuery = Task::query()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done')
            ->whereNotNull('date')
            ->where('date', '<', today()->format('Y-m-d'))
            ->whereHas('project', fn($pq) => $pq->whereNotIn('status', ['archived', 'done']))
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments', 'completionLog.user']);

        $this->applySortOrder($tasksQuery, $sort, $reversed);
        $tasks = $tasksQuery->get();

        $breakdown = $this->projectBreakdown($tasks);

        return view('dashboard.overdue', array_merge(compact('tasks', 'sort', 'breakdown'), $this->quickAddData()));
    }

    public function undated(Request $request)
    {
        $sort     = $request->input('sort', 'created');
        $reversed = $request->boolean('reversed');

        $tasksQuery = Task::query()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
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
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
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
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
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
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done')
            ->whereNotNull('date')
            ->where('date', '<', today()->format('Y-m-d'))
            ->whereHas('project', fn($pq) => $pq->whereNotIn('status', ['archived', 'done']))
            ->count();

        $undatedCount = Task::query()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done')
            ->whereNull('date')
            ->whereHas('project', fn($pq) => $pq->whereNotIn('status', ['archived', 'done']))
            ->count();

        return view('dashboard.calendar', compact('tasks', 'month', 'year', 'startDate', 'overdueCount', 'undatedCount'));
    }

    public function exportDayMarkdown(Request $request)
    {
        $date = $request->input('date', today()->format('Y-m-d'));
        $carbonDate = Carbon::parse($date);
        $dateStr = $carbonDate->format('Y-m-d');

        $userConstraint = function ($q) {
            $q->where('creator_id', Auth::id())
              ->orWhereHas('assignees', function ($query) {
                  $query->where('users.id', Auth::id());
              });
        };

        $incomplete = Task::query()->where($userConstraint)
            ->where('status', 'incomplete')->where('date', $dateStr)
            ->orderByRaw('time IS NULL, time ASC')->get();

        $done = Task::query()->where($userConstraint)
            ->where('status', 'done')->whereDate('completed_at', $dateStr)
            ->orderByRaw('time IS NULL, time ASC')->get();

        $archived = Task::query()->where($userConstraint)
            ->where('status', 'archived')->where('date', $dateStr)
            ->orderByRaw('time IS NULL, time ASC')->get();

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

        $tasksQuery = Task::query()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done')
            ->where('date', $dateStr)
            ->whereHas('project', fn($pq) => $pq->whereNotIn('status', ['archived', 'done']))
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments', 'completionLog.user']);
        $this->applySortOrder($tasksQuery, $sort, $reversed);
        $tasks = $tasksQuery->get();

        $perPage = (int) env('PAGINATION_PER_PAGE', 100);

        $userConstraint = function ($q) {
            $q->where('creator_id', Auth::id())
              ->orWhereHas('assignees', fn($q2) => $q2->where('users.id', Auth::id()));
        };

        $dayWith = ['creator', 'project', 'tags', 'assignees', 'attachments', 'comments', 'completionLog.user'];

        $completedTasksTotal = Task::query()->where($userConstraint)
            ->where('status', 'done')
            ->whereDate('completed_at', $dateStr)
            ->count();

        $completedTasksRaw = Task::query()->where($userConstraint)
            ->where('status', 'done')
            ->whereDate('completed_at', $dateStr)
            ->with($dayWith)
            ->orderByRaw('time IS NULL, time ASC')
            ->take($perPage + 1)
            ->get();

        $completedTasksHasMore = $completedTasksRaw->count() > $perPage;
        $completedTasks = $completedTasksHasMore ? $completedTasksRaw->slice(0, $perPage) : $completedTasksRaw;

        // Tasks archived on this day (by completed_at, status differentiates done vs archived),
        // OR tasks dated for this day that belong to an archived/done project.
        $archivedTasksTotal = Task::query()->where($userConstraint)
            ->where('status', 'archived')
            ->where(function ($q) use ($dateStr) {
                $q->whereDate('completed_at', $dateStr)
                  ->orWhere(function ($q2) use ($dateStr) {
                      $q2->where('date', $dateStr)
                         ->whereHas('project', fn($pq) => $pq->whereIn('status', ['archived', 'done']));
                  });
            })
            ->count();

        $archivedTasksRaw = Task::query()->where($userConstraint)
            ->where('status', 'archived')
            ->where(function ($q) use ($dateStr) {
                $q->whereDate('completed_at', $dateStr)
                  ->orWhere(function ($q2) use ($dateStr) {
                      $q2->where('date', $dateStr)
                         ->whereHas('project', fn($pq) => $pq->whereIn('status', ['archived', 'done']));
                  });
            })
            ->with($dayWith)
            ->orderByRaw('time IS NULL, time ASC')
            ->take($perPage + 1)
            ->get();

        $archivedTasksHasMore = $archivedTasksRaw->count() > $perPage;
        $archivedTasks = $archivedTasksHasMore ? $archivedTasksRaw->slice(0, $perPage) : $archivedTasksRaw;

        $overdueCount = 0;
        if ($carbonDate->isToday()) {
            $overdueCount = Task::query()
                ->where(function ($q) {
                    $q->where('creator_id', Auth::id())
                      ->orWhereHas('assignees', function ($query) {
                          $query->where('users.id', Auth::id());
                      });
                })
                ->where('status', '!=', 'archived')
                ->where('status', '!=', 'done')
                ->whereNotNull('date')
                ->where('date', '<', today()->format('Y-m-d'))
                ->whereHas('project', fn($pq) => $pq->whereNotIn('status', ['archived', 'done']))
                ->count();
        }

        $breakdown = $this->projectBreakdown($tasks);

        $projectReminders = ProjectReminder::where('user_id', Auth::id())
            ->where('dismissed', false)
            ->whereDate('date', $dateStr)
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
        $perPage = (int) env('PAGINATION_PER_PAGE', 100);
        $page    = max(1, (int) $request->get('page', 1));
        $offset  = ($page - 1) * $perPage;
        $dateStr = $request->get('date', today()->format('Y-m-d'));

        $userConstraint = function ($q) {
            $q->where('creator_id', Auth::id())
              ->orWhereHas('assignees', fn($q2) => $q2->where('users.id', Auth::id()));
        };

        $tasks = Task::query()->where($userConstraint)
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
        $perPage = (int) env('PAGINATION_PER_PAGE', 100);
        $page    = max(1, (int) $request->get('page', 1));
        $offset  = ($page - 1) * $perPage;
        $dateStr = $request->get('date', today()->format('Y-m-d'));

        $userConstraint = function ($q) {
            $q->where('creator_id', Auth::id())
              ->orWhereHas('assignees', fn($q2) => $q2->where('users.id', Auth::id()));
        };

        $tasks = Task::query()->where($userConstraint)
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
        $datedTasks = Task::query()
            ->where(function ($q) use ($userId) {
                $q->where('creator_id', $userId)
                  ->orWhereHas('assignees', fn($q2) => $q2->where('users.id', $userId));
            })
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
                ->where(function ($q) use ($userId) {
                    $q->where('creator_id', $userId)
                      ->orWhereHas('assignees', fn($q2) => $q2->where('users.id', $userId));
                })
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
