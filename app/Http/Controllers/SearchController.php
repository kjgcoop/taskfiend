<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    public function index(Request $request)
    {
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

        $query = Task::query()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done');

        // Handle search text (searches in name and description)
        if ($request->filled('q')) {
            $searchText = $request->q;
            $query->where(function ($q) use ($searchText) {
                $q->where('name', 'like', '%' . $searchText . '%')
                  ->orWhere('description', 'like', '%' . $searchText . '%');
            });
        }

        // Handle tag filtering
        if ($request->filled('tag_ids')) {
            $tagIds = is_array($request->tag_ids) ? $request->tag_ids : [$request->tag_ids];
            foreach ($tagIds as $tagId) {
                $query->whereHas('tags', function ($q) use ($tagId) {
                    $q->where('tags.id', $tagId);
                });
            }
        }

        // Handle project filtering
        if ($request->filled('project_id')) {
            if ($request->project_id === 'none') {
                // Search all tasks regardless of project
                // No filter needed
            } elseif ($request->project_id === 'inbox') {
                // Search only in user's Inbox
                $inboxProject = Project::where('user_id', Auth::id())
                    ->where('is_inbox', true)
                    ->first();
                if ($inboxProject) {
                    $query->where('project_id', $inboxProject->id);
                }
            } else {
                $query->where('project_id', $request->project_id);
            }
        }

        $tasks = $query->with(['creator', 'project', 'tags', 'assignees', 'attachments', 'comments'])
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        return view('search.index', compact('tasks', 'projects', 'tags'));
    }
}
