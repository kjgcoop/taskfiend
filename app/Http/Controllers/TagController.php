<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        $sort = $request->input('sort', 'date');

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
            'created' => $tasksQuery->orderBy('created_at', 'desc'),
            'name'    => $tasksQuery->orderByRaw('LOWER(name) ASC'),
            default   => $tasksQuery->orderByRaw('date IS NULL, date ASC, time ASC'),
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

        $tag->load('changeLogs.user');

        $projects = Project::where(function ($q) {
                $q->where('user_id', Auth::id())
                  ->orWhereHas('assignees', fn($q2) => $q2->where('users.id', Auth::id()));
            })
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get(['id', 'name']);

        $allTags = Tag::orderBy('tag_name')->get(['id', 'tag_name', 'color']);

        return view('tags.show', compact(
            'tag', 'tasks', 'completedTasks', 'completedTasksHasMore', 'completedTasksTotal',
            'projects', 'allTags', 'sort'
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

    public function edit(Tag $tag)
    {
        return view('tags.edit', compact('tag'));
    }

    public function update(Request $request, Tag $tag)
    {
        $validated = $request->validate([
            'tag_name' => 'required|string|max:255|unique:tags,tag_name,' . $tag->id,
            'color' => 'required|string|size:7|regex:/^#[0-9A-Fa-f]{6}$/',
        ]);

        $changes = [];
        foreach (['tag_name', 'color'] as $field) {
            if ($tag->$field != $validated[$field]) {
                $changes[$field] = ['old' => $tag->$field, 'new' => $validated[$field]];
                $tag->$field = $validated[$field];
            }
        }

        $tag->save();

        foreach ($changes as $field => $change) {
            $this->logChange($tag, "changed {$field} from {$change['old']} to {$change['new']}");
        }

        return redirect()->route('tags.show', $tag)
            ->with('success', 'Tag updated successfully.');
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
