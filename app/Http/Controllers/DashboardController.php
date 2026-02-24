<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
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

    public function inbox()
    {
        // Get user's Inbox project
        $inboxProject = \App\Models\Project::where('user_id', Auth::id())
            ->where('is_inbox', true)
            ->first();

        $tasks = Task::query()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done')
            ->where('project_id', $inboxProject?->id)
            ->with(['creator', 'tags', 'assignees', 'attachments', 'comments'])
            ->orderByRaw('date IS NULL, date ASC, time ASC')
            ->get();

        $completedTasks = Task::query()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', 'done')
            ->where('project_id', $inboxProject?->id)
            ->with(['creator', 'tags', 'assignees', 'attachments', 'comments'])
            ->orderByRaw('date IS NULL, date ASC, time ASC')
            ->get();

        return view('dashboard.inbox', array_merge(compact('tasks', 'completedTasks'), $this->quickAddData()));
    }

    public function overdue()
    {
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
            ->where('date', '<', today()->format('Y-m-d'))
            ->whereHas('project', fn($pq) => $pq->whereNotIn('status', ['archived', 'done']))
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments'])
            ->orderBy('date', 'asc')
            ->orderByRaw('time IS NULL, time ASC')
            ->get();

        return view('dashboard.overdue', array_merge(compact('tasks'), $this->quickAddData()));
    }

    public function undated()
    {
        $tasks = Task::query()
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
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('dashboard.undated', array_merge(compact('tasks'), $this->quickAddData()));
    }

    public function all()
    {
        $tasks = Task::query()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', '!=', 'archived')
            ->whereHas('project', fn($pq) => $pq->whereNotIn('status', ['archived', 'done']))
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments'])
            ->orderByRaw("CASE WHEN status = 'done' THEN 1 ELSE 0 END ASC")
            ->orderByRaw('date IS NULL, date ASC, time ASC')
            ->get();

        return view('dashboard.all', array_merge(compact('tasks'), $this->quickAddData()));
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
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments'])
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

        $tasks = Task::query()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done')
            ->where('date', $carbonDate->format('Y-m-d'))
            ->whereHas('project', fn($pq) => $pq->whereNotIn('status', ['archived', 'done']))
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments'])
            ->orderByRaw('time IS NULL, time ASC')
            ->get();

        $completedTasks = Task::query()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', 'done')
            ->whereDate('completed_at', $carbonDate->format('Y-m-d'))
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments'])
            ->orderByRaw('time IS NULL, time ASC')
            ->get();

        return view('dashboard.day', array_merge(compact('tasks', 'completedTasks', 'date', 'carbonDate'), $this->quickAddData()));
    }
}
