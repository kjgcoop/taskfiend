<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function today()
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
            ->where('date', today()->format('Y-m-d'))
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments'])
            ->orderByRaw('time IS NULL, time ASC')
            ->get();

        return view('dashboard.today', compact('tasks'));
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
            ->where(function ($q) use ($inboxProject) {
                $q->whereNull('project_id');
                if ($inboxProject) {
                    $q->orWhere('project_id', $inboxProject->id);
                }
            })
            ->with(['creator', 'tags', 'assignees', 'attachments', 'comments'])
            ->orderByRaw('date IS NULL, date ASC, time ASC')
            ->get();

        return view('dashboard.inbox', compact('tasks'));
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
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments'])
            ->orderBy('date', 'asc')
            ->orderByRaw('time IS NULL, time ASC')
            ->get();

        return view('dashboard.overdue', compact('tasks'));
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
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('dashboard.undated', compact('tasks'));
    }

    public function calendar(Request $request)
    {
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
            ->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments'])
            ->orderByRaw('time IS NULL, time ASC')
            ->get();

        return view('dashboard.day', compact('tasks', 'date', 'carbonDate'));
    }
}
