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
            'q'               => 'nullable|string|max:255',
            'tag_ids'         => 'nullable|array',
            'tag_ids.*'       => 'integer|exists:tags,id',
            'project_id'      => 'nullable|string|max:20',
            'date_from'       => 'nullable|date',
            'date_to'         => 'nullable|date',
            'has_date'        => 'nullable|boolean',
            'show_incomplete' => 'nullable|boolean',
            'show_done'       => 'nullable|boolean',
            'show_archived'          => 'nullable|boolean',
            'show_archived_projects' => 'nullable|boolean',
            'assignee_id'            => 'nullable|integer|exists:users,id',
            'creator_id'      => 'nullable|integer|exists:users,id',
            'sort'            => 'nullable|in:date_asc,date_desc,name_asc,name_desc,created_desc',
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

        // By default, exclude tasks from archived or done projects
        if (!$request->boolean('show_archived_projects')) {
            $baseQuery->where(function ($q) {
                $q->whereNull('project_id')
                  ->orWhereHas('project', function ($q) {
                      $q->whereNotIn('status', ['archived', 'done']);
                  });
            });
        }

        // Status filtering is handled per-collection below

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

        $sort = $request->input('sort', 'date_asc');
        $applySort = function ($query) use ($sort) {
            return match ($sort) {
                'date_desc'    => $query->orderByRaw('(date IS NULL) ASC, date DESC')->orderBy('time'),
                'name_asc'     => $query->orderByRaw('LOWER(name) ASC'),
                'name_desc'    => $query->orderByRaw('LOWER(name) DESC'),
                'created_desc' => $query->orderBy('created_at', 'desc'),
                default        => $query->orderBy('date')->orderBy('time'),
            };
        };

        $tasks = collect();
        if ($request->boolean('show_incomplete')) {
            $tasks = $applySort((clone $baseQuery)->where('status', 'incomplete'))
                ->with($with)
                ->get();
        }

        $completedTasks = collect();
        if ($request->boolean('show_done')) {
            $completedTasks = $applySort((clone $baseQuery)->where('status', 'done'))
                ->with($with)
                ->get();
        }

        $archivedTasks = collect();
        if ($request->boolean('show_archived')) {
            $archivedTasks = $applySort((clone $baseQuery)->where('status', 'archived'))
                ->with($with)
                ->get();
        }

        if ($request->input('export') === 'markdown') {
            $lines = ['# Search Results'];
            foreach ([['## Incomplete', $tasks], ['## Done', $completedTasks], ['## Archived', $archivedTasks]] as [$heading, $group]) {
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
                'Content-Disposition' => 'attachment; filename="search_results_' . now()->format('Y-m-d') . '.md"',
            ]);
        }

        return view('search.index', compact('tasks', 'completedTasks', 'archivedTasks', 'projects', 'tags', 'users'));
    }
}
