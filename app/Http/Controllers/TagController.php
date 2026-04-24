<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TagController extends Controller
{
    public function index()
    {
        $userId = Auth::id();

        $tags = Tag::withCount(['tasks' => function ($query) use ($userId) {
                $query->where(function ($q) use ($userId) {
                    $q->where('creator_id', $userId)
                      ->orWhereHas('assignees', function ($query) use ($userId) {
                          $query->where('users.id', $userId);
                      });
                })
                ->where('status', '!=', 'archived')
                ->where('status', '!=', 'done');
            }])
            ->orderByRaw('LOWER(tag_name)')
            ->get();

        return view('tags.index', compact('tags'));
    }

    public function create()
    {
        return view('tags.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tag_name' => 'required|string|max:255|unique:tags,tag_name',
            'color' => 'required|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $tag = Tag::create($validated);

        $this->logChange($tag, 'created tag');

        return redirect()->route('tags.show', $tag)
            ->with('success', 'Tag created successfully.');
    }

    public function show(Request $request, Tag $tag)
    {
        $sort     = $request->input('sort', 'date');
        $reversed = $request->boolean('reversed');

        $tasksQuery = $tag->tasks()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done')
            ->with(['creator', 'project', 'assignees', 'attachments', 'comments', 'completionLog.user']);
        match ($sort) {
            'created'  => $tasksQuery->orderBy('created_at', $reversed ? 'asc' : 'desc'),
            'name'     => $tasksQuery->orderByRaw($reversed ? 'LOWER(name) DESC' : 'LOWER(name) ASC'),
            'custom'   => $tasksQuery->orderByRaw('CASE WHEN tag_task.sort_order IS NULL THEN 1 ELSE 0 END, tag_task.sort_order ASC, tasks.date IS NULL, tasks.date ASC, tasks.time IS NULL, tasks.time ASC'),
            'location' => $tasksQuery->orderByRaw($reversed ? '(location IS NULL OR location = \'\') ASC, LOWER(location) DESC' : '(location IS NULL OR location = \'\') ASC, LOWER(location) ASC'),
            default    => $tasksQuery->orderByRaw($reversed ? 'tasks.date IS NULL, tasks.date DESC, tasks.time IS NULL, tasks.time DESC, tasks.created_at DESC' : 'tasks.date IS NULL, tasks.date ASC, tasks.time IS NULL, tasks.time ASC, tasks.created_at ASC'),
        };
        $tasks = $tasksQuery->get();

        $perPage = (int) env('PAGINATION_PER_PAGE', 100);

        $completedTasksTotal = $tag->tasks()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', 'done')
            ->count();

        $completedTasksRaw = $tag->tasks()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', 'done')
            ->with(['creator', 'project', 'assignees', 'attachments', 'comments', 'completionLog.user'])
            ->orderBy('datetime')
            ->take($perPage + 1)
            ->get();

        $completedTasksHasMore = $completedTasksRaw->count() > $perPage;
        $completedTasks = $completedTasksHasMore ? $completedTasksRaw->slice(0, $perPage) : $completedTasksRaw;

        $archivedTasksTotal = $tag->tasks()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', 'archived')
            ->count();

        $archivedTasksRaw = $tag->tasks()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', 'archived')
            ->with(['creator', 'project', 'assignees', 'attachments', 'comments', 'completionLog.user'])
            ->orderBy('datetime')
            ->take($perPage + 1)
            ->get();

        $archivedTasksHasMore = $archivedTasksRaw->count() > $perPage;
        $archivedTasks = $archivedTasksHasMore ? $archivedTasksRaw->slice(0, $perPage) : $archivedTasksRaw;

        $tag->load('changeLogs.user');

        $projects = Project::where(function ($q) {
                $q->where('user_id', Auth::id())
                  ->orWhereHas('assignees', fn($q2) => $q2->where('users.id', Auth::id()));
            })
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get(['id', 'name']);

        $allTags = Tag::orderByRaw('LOWER(tag_name)')->get(['id', 'tag_name', 'color']);

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

        $breakdown = $tasks
            ->groupBy(fn($t) => optional($t->project)->name ?? 'No Project')
            ->map(fn($g, $name) => ['name' => $name, 'count' => $g->count()])
            ->sortByDesc('count')
            ->values()
            ->toArray();

        return view('tags.show', compact(
            'tag', 'tasks', 'breakdown',
            'completedTasks', 'completedTasksHasMore', 'completedTasksTotal',
            'archivedTasks', 'archivedTasksHasMore', 'archivedTasksTotal',
            'projects', 'allTags', 'users', 'locations', 'sort'
        ));
    }

    public function completedTasks(Request $request, Tag $tag)
    {
        $perPage = (int) env('PAGINATION_PER_PAGE', 100);
        $page    = max(1, (int) $request->get('page', 1));
        $offset  = ($page - 1) * $perPage;

        $tasks = $tag->tasks()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', 'done')
            ->with(['creator', 'project', 'assignees', 'attachments', 'comments', 'completionLog.user'])
            ->orderBy('datetime')
            ->skip($offset)->take($perPage + 1)
            ->get();

        $hasMore = $tasks->count() > $perPage;
        if ($hasMore) {
            $tasks = $tasks->slice(0, $perPage);
        }

        $readOnly = false;

        return response()->json([
            'html'     => view('tasks.partials.completed-list', compact('tasks', 'readOnly'))->render(),
            'hasMore'  => $hasMore,
            'nextPage' => $page + 1,
        ]);
    }

    public function archivedTasks(Request $request, Tag $tag)
    {
        $perPage = (int) env('PAGINATION_PER_PAGE', 100);
        $page    = max(1, (int) $request->get('page', 1));
        $offset  = ($page - 1) * $perPage;

        $tasks = $tag->tasks()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', 'archived')
            ->with(['creator', 'project', 'assignees', 'attachments', 'comments', 'completionLog.user'])
            ->orderBy('datetime')
            ->skip($offset)->take($perPage + 1)
            ->get();

        $hasMore = $tasks->count() > $perPage;
        if ($hasMore) {
            $tasks = $tasks->slice(0, $perPage);
        }

        $readOnly = true;
        $showAsArchived = true;

        return response()->json([
            'html'     => view('tasks.partials.completed-list', compact('tasks', 'readOnly', 'showAsArchived'))->render(),
            'hasMore'  => $hasMore,
            'nextPage' => $page + 1,
        ]);
    }

    public function updateField(Request $request, Tag $tag)
    {
        $field = $request->input('field');
        $allowedFields = ['tag_name', 'color'];

        if (!in_array($field, $allowedFields)) {
            return response()->json(['success' => false, 'message' => 'Invalid field'], 400);
        }

        $value = $request->input('value');

        if ($field === 'tag_name') {
            if (empty(trim($value))) {
                return response()->json(['success' => false, 'message' => 'Name cannot be empty'], 400);
            }
            if (Tag::where('tag_name', $value)->where('id', '!=', $tag->id)->exists()) {
                return response()->json(['success' => false, 'message' => 'A tag with that name already exists'], 422);
            }
        }

        if ($field === 'color' && !preg_match('/^#[0-9A-Fa-f]{6}$/', $value)) {
            return response()->json(['success' => false, 'message' => 'Invalid color format'], 400);
        }

        $old = $tag->$field;
        $tag->$field = $value;
        $tag->save();

        $this->logChange($tag, "changed {$field} from {$old} to {$value}");

        return response()->json(['success' => true]);
    }

    public function quickStore(Request $request)
    {
        $validated = $request->validate([
            'tag_name' => 'required|string|max:255|unique:tags,tag_name',
        ]);

        $colors = ['#EF4444', '#F59E0B', '#10B981', '#3B82F6', '#8B5CF6', '#EC4899', '#06B6D4', '#F97316'];
        $color = $colors[array_rand($colors)];

        $tag = Tag::create([
            'tag_name' => $validated['tag_name'],
            'color' => $color,
        ]);

        $this->logChange($tag, 'created tag');

        return response()->json([
            'id' => $tag->id,
            'tag_name' => $tag->tag_name,
            'color' => $tag->color,
        ]);
    }

    public function reorderTasks(Request $request, Tag $tag)
    {
        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $userId = Auth::id();

        // Only update pivot rows for tasks the user can access that belong to this tag
        $accessibleIds = Task::whereHas('tags', fn($q) => $q->where('tags.id', $tag->id))
            ->where(function ($q) use ($userId) {
                $q->where('creator_id', $userId)
                  ->orWhereHas('assignees', fn($q2) => $q2->where('users.id', $userId));
            })
            ->whereIn('id', $request->ids)
            ->pluck('id')
            ->flip();

        foreach ($request->ids as $position => $id) {
            if ($accessibleIds->has($id)) {
                DB::table('tag_task')
                    ->where('tag_id', $tag->id)
                    ->where('task_id', $id)
                    ->update(['sort_order' => $position]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Tag $tag)
    {
        $tag->tasks()->detach();

        $this->logChange($tag, 'deleted tag');

        $tag->delete();

        return redirect()->route('tags.index')
            ->with('success', 'Tag deleted successfully.');
    }

    protected function logChange(Tag $tag, string $description)
    {
        $tag->changeLogs()->create([
            'date' => now(),
            'user_id' => Auth::id(),
            'entity_type' => 'tags',
            'entity_id' => $tag->id,
            'description' => $description,
        ]);
    }
}
