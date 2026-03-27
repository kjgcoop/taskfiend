<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\ChangeLog;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /** Apply sort ordering to a task query based on the sort parameter. */
    private function applySortOrder($query, string $sort): void
    {
        match ($sort) {
            'created'  => $query->orderBy('created_at', 'desc'),
            'name'     => $query->orderByRaw('LOWER(name) ASC'),
            default    => $query->orderByRaw('date IS NULL, date ASC, CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END, sort_order, time IS NULL, time ASC'),
        };
    }

    /** Projects + tags needed by the quick-add autocomplete. */
    private function quickAddData(): array
    {
        $projects = Project::where(function ($q) {
                $q->where('user_id', Auth::id())
                  ->orWhereHas('assignees', fn($q2) => $q2->where('users.id', Auth::id()));
            })
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get(['id', 'name']);

        $tags = Tag::orderBy('tag_name')->get(['id', 'tag_name', 'color']);

        return compact('projects', 'tags');
    }

    public function inbox(Request $request)
    {
        $sort = $request->input('sort', 'date');

        // Get (or create) user's Inbox project so quick-add tasks land here.
        $inboxProject = \App\Models\Project::where('user_id', Auth::id())
            ->where('is_inbox', true)
            ->first();

        if (!$inboxProject) {
            $inboxProject = \App\Models\Project::create([
                'name'     => 'Inbox',
                'user_id'  => Auth::id(),
                'is_inbox' => true,
                'status'   => 'incomplete',
            ]);
        }

        $tasksQuery = Task::query()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done')
            ->where('project_id', $inboxProject?->id)
            ->with(['creator', 'tags', 'assignees', 'attachments', 'comments', 'completionLog.user']);

        $this->applySortOrder($tasksQuery, $sort);
        $tasks = $tasksQuery->get();

        $completedTasksQuery = Task::query()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', 'done')
            ->where('project_id', $inboxProject?->id)
            ->with(['creator', 'tags', 'assignees', 'attachments', 'comments', 'completionLog.user']);

        $this->applySortOrder($completedTasksQuery, $sort);
        $completedTasks = $completedTasksQuery->get();

        return view('dashboard.inbox', array_merge(compact('tasks', 'completedTasks', 'sort', 'inboxProject'), $this->quickAddData()));
    }

    public function overdue(Request $request)
    {
        $sort = $request->input('sort', 'date');

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

        $this->applySortOrder($tasksQuery, $sort);
        $tasks = $tasksQuery->get();

        return view('dashboard.overdue', array_merge(compact('tasks', 'sort'), $this->quickAddData()));
    }

    public function undated(Request $request)
    {
        $sort = $request->input('sort', 'created');

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

        $this->applySortOrder($tasksQuery, $sort);
        $tasks = $tasksQuery->get();

        return view('dashboard.undated', array_merge(compact('tasks', 'sort'), $this->quickAddData()));
    }

    public function all(Request $request)
    {
        $sort = $request->input('sort', 'date');

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

        $this->applySortOrder($tasksQuery, $sort);
        $tasks = $tasksQuery->get();

        return view('dashboard.all', array_merge(compact('tasks', 'sort'), $this->quickAddData()));
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

    public function day(Request $request)
    {
        $date = $request->input('date', today()->format('Y-m-d'));
        $carbonDate = Carbon::parse($date);
        $dateStr = $carbonDate->format('Y-m-d');

        // Past days get the review layout
        if ($carbonDate->startOfDay()->lt(today()->startOfDay())) {
            return $this->dayReview($carbonDate, $dateStr);
        }

        $sort = $request->input('sort', 'date');

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
        $this->applySortOrder($tasksQuery, $sort);
        $tasks = $tasksQuery->get();

        $completedTasks = Task::query()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', 'done')
            ->whereDate('completed_at', $dateStr)
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments', 'completionLog.user'])
            ->orderByRaw('time IS NULL, time ASC')
            ->get();

        // Tasks archived on this day (by completed_at, status differentiates done vs archived),
        // OR tasks dated for this day that belong to an archived/done project.
        $archivedTasks = Task::query()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
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
            ->get();

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

        return view('dashboard.day', array_merge(compact('tasks', 'completedTasks', 'archivedTasks', 'date', 'carbonDate', 'overdueCount', 'sort'), $this->quickAddData()));
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
