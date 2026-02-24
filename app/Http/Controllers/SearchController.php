<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'q'                => 'nullable|string|max:255',
            'tag_ids'          => 'nullable|array',
            'tag_ids.*'        => 'integer|exists:tags,id',
            'project_id'       => 'nullable|string|max:20',
            'date_from'        => 'nullable|date',
            'date_to'          => 'nullable|date',
            'has_date'         => 'nullable|boolean',
            'show_completed'   => 'nullable|boolean',
            'include_archived' => 'nullable|boolean',
            'assignee_id'      => 'nullable|integer|exists:users,id',
            'creator_id'       => 'nullable|integer|exists:users,id',
        ]);

        // Get all projects and tags for the UI (exclude Inbox projects - they're handled separately)
        $projects = Project::where(function ($q) {
                $q->where('user_id', Auth::id())
                  ->orWhereHas('tasks.assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', '!=', 'archived')
            ->where('is_inbox', false)
            ->orderByRaw('LOWER(name)')
            ->get();

        $tags = Tag::orderByRaw('LOWER(tag_name)')->get();

        $users = User::whereNull('email_enabled_at')->orderBy('name')->get();

        $baseQuery = Task::query()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            });

        if (!$request->boolean('include_archived')) {
            $baseQuery->where('status', '!=', 'archived');
        }

        // Handle search text (searches in name and description)
        if ($request->filled('q')) {
            $searchText = $request->q;
            $baseQuery->where(function ($q) use ($searchText) {
                $q->where('name', 'like', '%' . $searchText . '%')
                  ->orWhere('description', 'like', '%' . $searchText . '%');
            });
        }

        // Handle tag filtering
        if ($request->filled('tag_ids')) {
            $tagIds = is_array($request->tag_ids) ? $request->tag_ids : [$request->tag_ids];
            foreach ($tagIds as $tagId) {
                $baseQuery->whereHas('tags', function ($q) use ($tagId) {
                    $q->where('tags.id', $tagId);
                });
            }
        }

        // Handle project filtering
        if ($request->filled('project_id')) {
            if ($request->project_id === 'none') {
                // Search all tasks regardless of project
            } elseif ($request->project_id === 'inbox') {
                $inboxProject = Project::where('user_id', Auth::id())
                    ->where('is_inbox', true)
                    ->first();
                if ($inboxProject) {
                    $baseQuery->where('project_id', $inboxProject->id);
                }
            } else {
                $baseQuery->where('project_id', $request->project_id);
            }
        }

        // Handle date filters
        if ($request->boolean('has_date')) {
            $baseQuery->whereNotNull('date');
        }

        if ($request->filled('date_from')) {
            $baseQuery->where('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $baseQuery->where('date', '<=', $request->date_to);
        }

        // Handle person filters
        if ($request->filled('assignee_id')) {
            $baseQuery->whereHas('assignees', function ($q) use ($request) {
                $q->where('users.id', $request->assignee_id);
            });
        }

        if ($request->filled('creator_id')) {
            $baseQuery->where('creator_id', $request->creator_id);
        }

        $with = ['creator', 'project', 'tags', 'assignees', 'attachments', 'comments'];

        $tasks = (clone $baseQuery)
            ->where('status', '!=', 'done')
            ->with($with)
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        $completedTasks = collect();
        if ($request->boolean('show_completed')) {
            $completedTasks = (clone $baseQuery)
                ->where('status', 'done')
                ->with($with)
                ->orderBy('date')
                ->orderBy('time')
                ->get();
        }

        return view('search.index', compact('tasks', 'completedTasks', 'projects', 'tags', 'users'));
    }
}
