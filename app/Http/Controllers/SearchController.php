<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SearchController extends Controller
{
    private function validateSearchRequest(Request $request): void
    {
        $request->validate([
            'q'               => 'nullable|string|max:255',
            'tag_ids'         => 'nullable|array',
            'tag_ids.*'       => 'integer|exists:tags,id',
            'project_id'      => 'nullable|string|max:20',
            'location'        => 'nullable|string|max:255',
            'has_location'    => 'nullable|boolean',
            'date_from'       => 'nullable|date',
            'date_to'         => 'nullable|date',
            'has_date'        => 'nullable|boolean',
            'show_incomplete' => 'nullable|boolean',
            'show_done'       => 'nullable|boolean',
            'show_archived'          => 'nullable|boolean',
            'show_archived_projects' => 'nullable|boolean',
            'assignee_id'            => 'nullable|integer|exists:users,id',
            'creator_id'      => 'nullable|integer|exists:users,id',
            'sort'            => 'nullable|in:date_asc,date_desc,name_asc,name_desc,created_desc,location_asc,location_desc',
        ]);
    }

    private function buildSearchQuery(Request $request): Builder
    {
        $baseQuery = Task::query()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            });

        if (!$request->boolean('show_archived_projects')) {
            $baseQuery->where(function ($q) {
                $q->whereNull('project_id')
                  ->orWhereHas('project', function ($q) {
                      $q->whereNotIn('status', ['archived', 'done']);
                  });
            });
        }

        if ($request->filled('q')) {
            $searchText = $request->q;
            $baseQuery->where(function ($q) use ($searchText) {
                $q->where('name', 'like', '%' . $searchText . '%')
                  ->orWhere('description', 'like', '%' . $searchText . '%');
            });
        }

        if ($request->filled('tag_ids')) {
            $tagIds = is_array($request->tag_ids) ? $request->tag_ids : [$request->tag_ids];
            foreach ($tagIds as $tagId) {
                $baseQuery->whereHas('tags', function ($q) use ($tagId) {
                    $q->where('tags.id', $tagId);
                });
            }
        }

        if ($request->filled('project_id')) {
            if ($request->project_id === 'none') {
                // no filter
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

        if ($request->boolean('has_date')) {
            $baseQuery->whereNotNull('date');
        }

        if ($request->filled('date_from')) {
            $baseQuery->where('date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $baseQuery->where('date', '<=', $request->date_to);
        }

        if ($request->filled('assignee_id')) {
            $baseQuery->whereHas('assignees', function ($q) use ($request) {
                $q->where('users.id', $request->assignee_id);
            });
        }

        if ($request->filled('creator_id')) {
            $baseQuery->where('creator_id', $request->creator_id);
        }

        if ($request->filled('location')) {
            $baseQuery->where('location', 'like', '%' . $request->location . '%');
        }

        if ($request->boolean('has_location')) {
            $baseQuery->whereNotNull('location')->where('location', '!=', '');
        }

        return $baseQuery;
    }

    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'date_desc'     => $query->orderByRaw('(date IS NULL) ASC, date DESC, CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END, sort_order, time ASC'),
            'name_asc'      => $query->orderByRaw('LOWER(name) ASC'),
            'name_desc'     => $query->orderByRaw('LOWER(name) DESC'),
            'created_desc'  => $query->orderBy('created_at', 'desc'),
            'location_asc'  => $query->orderByRaw('(location IS NULL OR location = \'\') ASC, LOWER(location) ASC'),
            'location_desc' => $query->orderByRaw('(location IS NULL OR location = \'\') ASC, LOWER(location) DESC'),
            default         => $query->orderByRaw('date IS NULL, date ASC, CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END, sort_order, time IS NULL, time ASC'),
        };
    }

    public function index(Request $request)
    {
        $this->validateSearchRequest($request);

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

        $tags  = Tag::orderByRaw('LOWER(tag_name)')->get();
        $users = User::whereNull('email_enabled_at')->orderBy('name')->get();

        $with = ['creator', 'project', 'tags', 'assignees', 'attachments', 'comments'];
        $sort = $request->input('sort', 'date_asc');

        $baseQuery = $this->buildSearchQuery($request);

        // Markdown export uses unbounded queries
        if ($request->input('export') === 'markdown') {
            $tasks          = $request->boolean('show_incomplete') ? $this->applySort((clone $baseQuery)->where('status', 'incomplete'), $sort)->with($with)->get() : collect();
            $completedTasks = $request->boolean('show_done')       ? $this->applySort((clone $baseQuery)->where('status', 'done'),       $sort)->with($with)->get() : collect();
            $archivedTasks  = $request->boolean('show_archived')   ? $this->applySort((clone $baseQuery)->where('status', 'archived'),   $sort)->with($with)->get() : collect();

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

        $perPage = (int) env('PAGINATION_PER_PAGE', 100);

        [$tasks, $tasksHasMore, $tasksTotal]                         = $this->fetchPage($baseQuery, 'incomplete', $sort, $with, $perPage, $request->boolean('show_incomplete'));
        [$completedTasks, $completedTasksHasMore, $completedTasksTotal] = $this->fetchPage($baseQuery, 'done',       $sort, $with, $perPage, $request->boolean('show_done'));
        [$archivedTasks,  $archivedTasksHasMore,  $archivedTasksTotal]  = $this->fetchPage($baseQuery, 'archived',   $sort, $with, $perPage, $request->boolean('show_archived'));

        return view('search.index', compact(
            'tasks', 'tasksHasMore', 'tasksTotal',
            'completedTasks', 'completedTasksHasMore', 'completedTasksTotal',
            'archivedTasks', 'archivedTasksHasMore', 'archivedTasksTotal',
            'projects', 'tags', 'users'
        ));
    }

    /** Fetch the first page for a given status; returns [$collection, $hasMore, $total]. */
    private function fetchPage(Builder $baseQuery, string $status, string $sort, array $with, int $perPage, bool $enabled): array
    {
        if (!$enabled) {
            return [collect(), false, 0];
        }

        $total = (clone $baseQuery)->where('status', $status)->count();
        $raw   = $this->applySort((clone $baseQuery)->where('status', $status), $sort)
            ->with($with)
            ->take($perPage + 1)
            ->get();

        $hasMore = $raw->count() > $perPage;
        return [$hasMore ? $raw->slice(0, $perPage) : $raw, $hasMore, $total];
    }

    public function more(Request $request)
    {
        $this->validateSearchRequest($request);
        $request->validate([
            'status' => 'required|in:incomplete,done,archived',
            'page'   => 'nullable|integer|min:1',
        ]);

        $perPage = (int) env('PAGINATION_PER_PAGE', 100);
        $page    = max(1, (int) $request->get('page', 1));
        $offset  = ($page - 1) * $perPage;
        $status  = $request->status;
        $sort    = $request->input('sort', 'date_asc');
        $with    = ['creator', 'project', 'tags', 'assignees', 'attachments', 'comments'];

        $baseQuery = $this->buildSearchQuery($request);

        $raw = $this->applySort((clone $baseQuery)->where('status', $status), $sort)
            ->with($with)
            ->skip($offset)->take($perPage + 1)
            ->get();

        $hasMore = $raw->count() > $perPage;
        $tasks   = $hasMore ? $raw->slice(0, $perPage) : $raw;

        $showAsArchived = $status === 'archived';

        return response()->json([
            'html'     => view('tasks.partials.completed-list', compact('tasks', 'showAsArchived'))->render(),
            'hasMore'  => $hasMore,
            'nextPage' => $page + 1,
        ]);
    }
}
