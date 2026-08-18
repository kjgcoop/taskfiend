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
            'sort'               => 'nullable|in:date,name,created,location,duration',
            'reversed'           => 'nullable|boolean',
            'search_title'       => 'nullable|boolean',
            'search_description' => 'nullable|boolean',
            'no_date'            => 'nullable|boolean',
            'duration_min'       => 'nullable|integer|min:0',
            'duration_max'       => 'nullable|integer|min:0',
        ]);
    }

    private function buildSearchQuery(Request $request): Builder
    {
        $baseQuery = Task::visibleTo(Auth::id());

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
            $inTitle    = $request->boolean('search_title');
            $inDesc     = $request->boolean('search_description');

            if (!$inTitle && !$inDesc) {
                $inTitle = true;
                $inDesc  = true;
            }

            $baseQuery->where(function ($q) use ($searchText, $inTitle, $inDesc) {
                if ($inTitle && $inDesc) {
                    $q->where('name', 'like', '%' . $searchText . '%')
                      ->orWhere('description', 'like', '%' . $searchText . '%');
                } elseif ($inTitle) {
                    $q->where('name', 'like', '%' . $searchText . '%');
                } else {
                    $q->where('description', 'like', '%' . $searchText . '%');
                }
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
            } else {
                $baseQuery->where('project_id', $request->project_id);
            }
        }

        $filterHasDate = $request->boolean('has_date');
        $filterNoDate  = $request->boolean('no_date');

        if ($filterHasDate && !$filterNoDate) {
            $baseQuery->whereNotNull('date');
        } elseif (!$filterHasDate && $filterNoDate) {
            $baseQuery->whereNull('date');
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

        if ($request->filled('duration_min')) {
            $baseQuery->where('duration_minutes', '>=', (int) $request->duration_min);
        }

        if ($request->filled('duration_max')) {
            $baseQuery->where('duration_minutes', '<=', (int) $request->duration_max);
        }

        return $baseQuery;
    }

    private function applySort(Builder $query, string $sort, bool $reversed = false): Builder
    {
        return match ($sort) {
            'name'     => $reversed
                ? $query->orderByRaw('LOWER(name) DESC')
                : $query->orderByRaw('LOWER(name) ASC'),
            'created'  => $reversed
                ? $query->orderBy('created_at', 'asc')
                : $query->orderBy('created_at', 'desc'),
            'location' => $reversed
                ? $query->orderByRaw('(location IS NULL OR location = \'\') ASC, LOWER(location) DESC')
                : $query->orderByRaw('(location IS NULL OR location = \'\') ASC, LOWER(location) ASC'),
            'duration' => $reversed
                ? $query->orderByRaw('CASE WHEN duration_minutes IS NULL THEN 1 ELSE 0 END, duration_minutes DESC')
                : $query->orderByRaw('CASE WHEN duration_minutes IS NULL THEN 1 ELSE 0 END, duration_minutes ASC'),
            default    => $reversed
                ? $query->orderByRaw('(date IS NULL) ASC, date DESC, CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END, sort_order, time IS NULL, time DESC, created_at DESC')
                : $query->orderByRaw('date IS NULL, date ASC, CASE WHEN sort_order IS NULL THEN 1 ELSE 0 END, sort_order, time IS NULL, time ASC, created_at ASC'),
        };
    }

    public function index(Request $request)
    {
        $this->validateSearchRequest($request);

        $projects = Project::activeForUser(Auth::id())->get()->sort(fn ($a, $b) => strnatcasecmp($a->name, $b->name))->values();

        $tags  = Tag::active()->orderByRaw('LOWER(tag_name)')->get();
        $users = User::whereNull('email_enabled_at')->orderByRaw('LOWER(name)')->get(['id', 'name']);

        $locations = Task::where('status', 'incomplete')
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->visibleTo(Auth::id())
            ->distinct()
            ->orderByRaw('LOWER(location)')
            ->pluck('location');

        $with     = ['creator', 'project', 'tags', 'assignees', 'attachments', 'comments'];
        $sort     = $request->input('sort', 'date');
        $reversed = $request->boolean('reversed');

        $baseQuery = $this->buildSearchQuery($request);

        // Markdown export uses unbounded queries
        if ($request->input('export') === 'markdown') {
            $tasks          = $request->boolean('show_incomplete') ? $this->applySort((clone $baseQuery)->where('status', 'incomplete'), $sort, $reversed)->with($with)->get() : collect();
            $completedTasks = $request->boolean('show_done')       ? $this->applySort((clone $baseQuery)->where('status', 'done'),       $sort, $reversed)->with($with)->get() : collect();
            $archivedTasks  = $request->boolean('show_archived')   ? $this->applySort((clone $baseQuery)->where('status', 'archived'),   $sort, $reversed)->with($with)->get() : collect();

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

        $perPage = (int) config('taskfiend.pagination_per_page');

        [$tasks, $tasksHasMore, $tasksTotal]                         = $this->fetchPage($baseQuery, 'incomplete', $sort, $reversed, $with, $perPage, $request->boolean('show_incomplete'));
        [$completedTasks, $completedTasksHasMore, $completedTasksTotal] = $this->fetchPage($baseQuery, 'done',       $sort, $reversed, $with, $perPage, $request->boolean('show_done'));
        [$archivedTasks,  $archivedTasksHasMore,  $archivedTasksTotal]  = $this->fetchPage($baseQuery, 'archived',   $sort, $reversed, $with, $perPage, $request->boolean('show_archived'));

        $breakdown = $tasks
            ->groupBy(fn($t) => optional($t->project)->name ?? 'No Project')
            ->map(fn($g, $name) => ['name' => $name, 'count' => $g->count()])
            ->sortByDesc('count')
            ->values()
            ->toArray();

        return view('search.index', compact(
            'tasks', 'tasksHasMore', 'tasksTotal',
            'completedTasks', 'completedTasksHasMore', 'completedTasksTotal',
            'archivedTasks', 'archivedTasksHasMore', 'archivedTasksTotal',
            'projects', 'tags', 'users', 'locations', 'breakdown'
        ));
    }

    /** Fetch the first page for a given status; returns [$collection, $hasMore, $total]. */
    private function fetchPage(Builder $baseQuery, string $status, string $sort, bool $reversed, array $with, int $perPage, bool $enabled): array
    {
        if (!$enabled) {
            return [collect(), false, 0];
        }

        $total = (clone $baseQuery)->where('status', $status)->count();
        $raw   = $this->applySort((clone $baseQuery)->where('status', $status), $sort, $reversed)
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

        $perPage = (int) config('taskfiend.pagination_per_page');
        $page    = max(1, (int) $request->get('page', 1));
        $offset  = ($page - 1) * $perPage;
        $status   = $request->status;
        $sort     = $request->input('sort', 'date');
        $reversed = $request->boolean('reversed');
        $with     = ['creator', 'project', 'tags', 'assignees', 'attachments', 'comments'];

        $baseQuery = $this->buildSearchQuery($request);

        $raw = $this->applySort((clone $baseQuery)->where('status', $status), $sort, $reversed)
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
