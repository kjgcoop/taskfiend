<?php

namespace App\Http\Controllers;

use App\Models\ChangeLog;
use App\Models\Task;
use App\Models\Project;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChangeLogController extends Controller
{
    private static function perPage(): int
    {
        return max(1, (int) config('taskfiend.pagination_per_page'));
    }

    public function task(Request $request, Task $task)
    {
        $isCreator = $task->creator_id === Auth::id();
        $isAssignee = $task->assignees()->where('users.id', Auth::id())->exists();

        if (!$isCreator && !$isAssignee) {
            abort(403, 'You do not have access to this task.');
        }

        $perPage = self::perPage();
        $page    = max(1, (int) $request->get('page', 1));
        $offset  = ($page - 1) * $perPage;

        $changeLogs = $task->changeLogs()->with('user')->orderByDesc('date')
            ->skip($offset)->take($perPage + 1)->get();

        $hasMore = $changeLogs->count() > $perPage;
        if ($hasMore) {
            $changeLogs = $changeLogs->slice(0, $perPage);
        }

        if ($request->ajax()) {
            return response()->json([
                'html'     => view('changelogs.partials.context-entries', compact('changeLogs'))->render(),
                'hasMore'  => $hasMore,
                'nextPage' => $page + 1,
            ]);
        }

        return view('changelogs.index', compact('changeLogs', 'task', 'hasMore', 'page'));
    }

    public function project(Request $request, Project $project)
    {
        $isCreator = $project->user_id === Auth::id();
        $isProjectAssignee = $project->assignees()->where('users.id', Auth::id())->exists();
        $hasTaskInProject = $project->tasks()
            ->whereHas('assignees', function ($query) {
                $query->where('users.id', Auth::id());
            })
            ->exists();

        if (!$isCreator && !$isProjectAssignee && !$hasTaskInProject) {
            abort(403, 'You do not have access to this project.');
        }

        $perPage = self::perPage();
        $page    = max(1, (int) $request->get('page', 1));
        $offset  = ($page - 1) * $perPage;

        $taskIds = $project->tasks()->pluck('id');

        $changeLogs = ChangeLog::where(function ($q) use ($project, $taskIds) {
            $q->where(function ($subQ) use ($project) {
                $subQ->where('entity_type', 'projects')
                     ->where('entity_id', $project->id);
            })
            ->orWhere(function ($subQ) use ($taskIds) {
                $subQ->where('entity_type', 'tasks')
                     ->whereIn('entity_id', $taskIds);
            });
        })
        ->with('user')
        ->orderByDesc('date')
        ->skip($offset)->take($perPage + 1)
        ->get();

        $hasMore = $changeLogs->count() > $perPage;
        if ($hasMore) {
            $changeLogs = $changeLogs->slice(0, $perPage);
        }

        if ($request->ajax()) {
            return response()->json([
                'html'     => view('changelogs.partials.context-entries', compact('changeLogs'))->render(),
                'hasMore'  => $hasMore,
                'nextPage' => $page + 1,
            ]);
        }

        return view('changelogs.index', compact('changeLogs', 'project', 'hasMore', 'page'));
    }

    public function tag(Request $request, Tag $tag)
    {
        $perPage = self::perPage();
        $page    = max(1, (int) $request->get('page', 1));
        $offset  = ($page - 1) * $perPage;

        $taskIds = $tag->tasks()
            ->visibleTo(Auth::id())
            ->pluck('tasks.id');

        $changeLogs = ChangeLog::where(function ($q) use ($tag, $taskIds) {
            $q->where(function ($subQ) use ($tag) {
                $subQ->where('entity_type', 'tags')
                     ->where('entity_id', $tag->id);
            })
            ->orWhere(function ($subQ) use ($taskIds) {
                $subQ->where('entity_type', 'tasks')
                     ->whereIn('entity_id', $taskIds);
            });
        })
        ->with('user')
        ->orderByDesc('date')
        ->skip($offset)->take($perPage + 1)
        ->get();

        $hasMore = $changeLogs->count() > $perPage;
        if ($hasMore) {
            $changeLogs = $changeLogs->slice(0, $perPage);
        }

        if ($request->ajax()) {
            return response()->json([
                'html'     => view('changelogs.partials.context-entries', compact('changeLogs'))->render(),
                'hasMore'  => $hasMore,
                'nextPage' => $page + 1,
            ]);
        }

        return view('changelogs.index', compact('changeLogs', 'tag', 'hasMore', 'page'));
    }

    public function user(Request $request)
    {
        $query = ChangeLog::where('user_id', Auth::id())
            ->with('user')
            ->orderByDesc('date');

        // Search: match description text or task name
        if ($search = $request->get('search')) {
            $matchingTaskIds = Task::where('name', 'like', '%' . $search . '%')->pluck('id');
            $query->where(function ($q) use ($search, $matchingTaskIds) {
                $q->where('description', 'like', '%' . $search . '%')
                  ->orWhere(function ($sq) use ($matchingTaskIds) {
                      $sq->where('entity_type', 'tasks')
                         ->whereIn('entity_id', $matchingTaskIds);
                  });
            });
        }

        // Project filter: include project-level and task-level changes for those projects
        if ($projectIds = array_filter((array) $request->get('projects', []))) {
            $taskIds = Task::whereIn('project_id', $projectIds)->pluck('id');
            $query->where(function ($q) use ($projectIds, $taskIds) {
                $q->where(function ($sq) use ($projectIds) {
                    $sq->where('entity_type', 'projects')
                       ->whereIn('entity_id', $projectIds);
                })->orWhere(function ($sq) use ($taskIds) {
                    $sq->where('entity_type', 'tasks')
                       ->whereIn('entity_id', $taskIds);
                });
            });
        }

        // Tag filter: include tag-level and task-level changes for tasks with those tags
        if ($tagIds = array_filter((array) $request->get('tags', []))) {
            $taskIds = \DB::table('tag_task')->whereIn('tag_id', $tagIds)->pluck('task_id')->unique();
            $query->where(function ($q) use ($tagIds, $taskIds) {
                $q->where(function ($sq) use ($tagIds) {
                    $sq->where('entity_type', 'tags')
                       ->whereIn('entity_id', $tagIds);
                })->orWhere(function ($sq) use ($taskIds) {
                    $sq->where('entity_type', 'tasks')
                       ->whereIn('entity_id', $taskIds);
                });
            });
        }

        // Entity type filter
        if ($entityType = $request->get('entity_type')) {
            $query->where('entity_type', $entityType);
        }

        // Date range filter
        if ($dateFrom = $request->get('date_from')) {
            $query->where('date', '>=', $dateFrom);
        }
        if ($dateTo = $request->get('date_to')) {
            $query->where('date', '<=', $dateTo . ' 23:59:59');
        }

        $perPage = self::perPage();
        $page    = max(1, (int) $request->get('page', 1));
        $offset  = ($page - 1) * $perPage;

        // Fetch one extra to know if more pages exist
        $changeLogs = $query->skip($offset)->take($perPage + 1)->get();
        $hasMore    = $changeLogs->count() > $perPage;
        if ($hasMore) {
            $changeLogs = $changeLogs->slice(0, $perPage);
        }

        // Efficiently load related entities (3 queries instead of N)
        $this->attachEntities($changeLogs);

        if ($request->ajax()) {
            return response()->json([
                'html'     => view('changelogs.partials.entries', compact('changeLogs'))->render(),
                'hasMore'  => $hasMore,
                'nextPage' => $page + 1,
            ]);
        }

        $availableProjects = Project::forMember(Auth::id())
            ->orderByRaw('LOWER(name)')->get();

        $availableTags = Tag::orderByRaw('LOWER(tag_name)')->get();

        return view('changelogs.index', compact(
            'changeLogs', 'hasMore', 'page', 'availableProjects', 'availableTags'
        ));
    }

    /**
     * Bulk-load related entities to avoid N+1 queries.
     */
    private function attachEntities($changeLogs): void
    {
        $taskIds    = $changeLogs->where('entity_type', 'tasks')->pluck('entity_id')->unique();
        $projectIds = $changeLogs->where('entity_type', 'projects')->pluck('entity_id')->unique();
        $tagIds     = $changeLogs->where('entity_type', 'tags')->pluck('entity_id')->unique();

        $tasks    = $taskIds->isNotEmpty()    ? Task::whereIn('id', $taskIds)->get()->keyBy('id')       : collect();
        $projects = $projectIds->isNotEmpty() ? Project::whereIn('id', $projectIds)->get()->keyBy('id') : collect();
        $tags     = $tagIds->isNotEmpty()     ? Tag::whereIn('id', $tagIds)->get()->keyBy('id')         : collect();

        foreach ($changeLogs as $log) {
            $log->entity = match ($log->entity_type) {
                'tasks'    => $tasks->get($log->entity_id),
                'projects' => $projects->get($log->entity_id),
                'tags'     => $tags->get($log->entity_id),
                default    => null,
            };
        }
    }
}
