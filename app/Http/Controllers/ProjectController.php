<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $all = Project::query()
            ->where(function ($q) {
                $q->where('user_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->withCount([
                'tasks as open_tasks_count'      => fn ($q) => $q->where('status', 'incomplete'),
                'tasks as done_tasks_count'       => fn ($q) => $q->where('status', 'done'),
            ])
            ->with('creator')
            ->orderByRaw('LOWER(name)')
            ->get();

        $projects         = $all->where('status', 'incomplete');
        $inactiveProjects = $all->whereIn('status', ['done', 'archived']);

        return view('projects.index', compact('projects', 'inactiveProjects'));
    }

    public function create()
    {
        $users = User::where('email_enabled_at', null)
            ->orderByRaw('LOWER(name)')
            ->get();

        return view('projects.create', compact('users'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
        ]);

        $project = Project::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'user_id' => Auth::id(),
            'status' => 'incomplete',
        ]);

        // Sync assignees (include creator if no assignees specified)
        $assigneeIds = $validated['assignee_ids'] ?? [Auth::id()];
        $project->assignees()->sync($assigneeIds);

        $this->logChange($project, 'created project');

        return redirect()->route('projects.show', $project)
            ->with('success', 'Project created successfully.');
    }

    public function show(Request $request, Project $project)
    {
        $this->authorizeProjectAccess($project);

        $sort = $request->input('sort', 'date');

        $visibleToUser = function ($q) {
            $q->where('creator_id', Auth::id())
              ->orWhereHas('assignees', function ($query) {
                  $query->where('users.id', Auth::id());
              });
        };

        $taskEagerLoad = [
            'creator',
            'tags',
            'assignees',
            'attachments',
            'comments',
            'completionLog.user',
        ];

        $tasks = $project->tasks()
            ->where($visibleToUser)
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done')
            ->whereNull('parent_id')
            ->with(array_merge($taskEagerLoad, [
                'children' => function ($query) {
                    $query->where('status', '!=', 'archived')
                          ->where('status', '!=', 'done')
                          ->with([
                              'tags',
                              'assignees',
                              'attachments',
                              'creator',
                              'children' => function ($q) {
                                  $q->where('status', '!=', 'archived')
                                    ->where('status', '!=', 'done')
                                    ->with(['tags', 'assignees', 'attachments', 'creator', 'children']);
                              }
                          ]);
                }
            ]))
            ->when($sort === 'created', fn($q) => $q->orderBy('created_at', 'desc'))
            ->when($sort === 'name',    fn($q) => $q->orderByRaw('LOWER(name) ASC'))
            ->when($sort === 'date',    fn($q) => $q->orderByRaw('date IS NULL, date ASC, time IS NULL, time ASC'))
            ->get();

        $perPage = (int) env('PAGINATION_PER_PAGE', 100);

        $completedTasksTotal = $project->tasks()
            ->where($visibleToUser)
            ->where('status', 'done')
            ->whereNull('parent_id')
            ->count();

        $completedTasksRaw = $project->tasks()
            ->where($visibleToUser)
            ->where('status', 'done')
            ->whereNull('parent_id')
            ->with(array_merge($taskEagerLoad, [
                'children' => function ($query) {
                    $query->where('status', 'done')
                          ->with([
                              'tags',
                              'assignees',
                              'attachments',
                              'creator',
                              'children' => function ($q) {
                                  $q->where('status', 'done')
                                    ->with(['tags', 'assignees', 'attachments', 'creator', 'children']);
                              }
                          ]);
                }
            ]))
            ->orderByDesc('updated_at')
            ->take($perPage + 1)
            ->get();

        $completedTasksHasMore = $completedTasksRaw->count() > $perPage;
        $completedTasks = $completedTasksHasMore ? $completedTasksRaw->slice(0, $perPage) : $completedTasksRaw;

        $project->load(['creator', 'assignees', 'changeLogs.user']);

        $users = User::where('email_enabled_at', null)
            ->orderByRaw('LOWER(name)')
            ->get();

        $projects = Project::where(function ($q) {
                $q->where('user_id', Auth::id())
                  ->orWhereHas('assignees', fn($q2) => $q2->where('users.id', Auth::id()));
            })
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get(['id', 'name']);

        $tags = Tag::orderBy('tag_name')->get(['id', 'tag_name', 'color']);

        return view('projects.show', compact(
            'project', 'tasks', 'completedTasks', 'completedTasksHasMore', 'completedTasksTotal',
            'users', 'projects', 'tags', 'sort'
        ));
    }

    public function completedTasks(Request $request, Project $project)
    {
        $this->authorizeProjectAccess($project);

        $perPage = (int) env('PAGINATION_PER_PAGE', 100);
        $page    = max(1, (int) $request->get('page', 1));
        $offset  = ($page - 1) * $perPage;

        $readOnly = in_array($project->status, ['done', 'archived']);

        $visibleToUser = function ($q) {
            $q->where('creator_id', Auth::id())
              ->orWhereHas('assignees', function ($query) {
                  $query->where('users.id', Auth::id());
              });
        };

        $tasks = $project->tasks()
            ->where($visibleToUser)
            ->where('status', 'done')
            ->whereNull('parent_id')
            ->with([
                'creator', 'tags', 'assignees', 'attachments', 'comments', 'completionLog.user',
                'children' => function ($query) {
                    $query->where('status', 'done')
                          ->with([
                              'tags', 'assignees', 'attachments', 'creator',
                              'children' => function ($q) {
                                  $q->where('status', 'done')
                                    ->with(['tags', 'assignees', 'attachments', 'creator', 'children']);
                              }
                          ]);
                },
            ])
            ->orderByDesc('updated_at')
            ->skip($offset)->take($perPage + 1)
            ->get();

        $hasMore = $tasks->count() > $perPage;
        if ($hasMore) {
            $tasks = $tasks->slice(0, $perPage);
        }

        return response()->json([
            'html'     => view('tasks.partials.completed-list', compact('tasks', 'readOnly'))->render(),
            'hasMore'  => $hasMore,
            'nextPage' => $page + 1,
        ]);
    }

    public function updateField(Request $request, Project $project)
    {
        if ($project->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Only the project creator can edit it.'], 403);
        }

        if ($project->is_inbox) {
            return response()->json(['success' => false, 'message' => 'Inbox projects cannot be edited.'], 403);
        }

        $field = $request->input('field');
        $allowedFields = ['name', 'description', 'status', 'assignee_ids'];

        if (!in_array($field, $allowedFields)) {
            return response()->json(['success' => false, 'message' => 'Invalid field'], 400);
        }

        if (in_array($project->status, ['done', 'archived']) && $field !== 'status') {
            return response()->json(['success' => false, 'message' => 'This project is inactive. Only the status can be changed.'], 403);
        }

        try {
            if ($field === 'assignee_ids') {
                $assigneeIds = $request->input('assignee_ids', []);
                $project->assignees()->sync($assigneeIds);
                $this->logChange($project, 'updated assignees');
            } else {
                $value = $request->input('value');

                if ($field === 'status' && !in_array($value, ['incomplete', 'done', 'archived'])) {
                    return response()->json(['success' => false, 'message' => 'Invalid status'], 400);
                }

                if ($field === 'name' && empty(trim($value))) {
                    return response()->json(['success' => false, 'message' => 'Name cannot be empty'], 400);
                }

                $old = $project->$field;
                $project->$field = $value;
                $project->save();
                $this->logChange($project, "changed {$field} from {$old} to {$value}");
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'An error occurred'], 500);
        }
    }

    public function destroy(Project $project)
    {
        if ($project->is_inbox) {
            abort(403, 'Inbox projects cannot be deleted or archived.');
        }

        abort(403, 'Projects cannot be deleted. Please archive instead.');
    }

    public function uploadBackground(Request $request, Project $project)
    {
        if ($project->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'background_image' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,image/avif,image/heic,image/heif',
                'max:20480',
            ],
        ]);

        if ($project->background_image) {
            Storage::disk('private')->delete($project->background_image);
        }

        $path = $request->file('background_image')->store("project-backgrounds/{$project->id}", 'private');
        $project->background_image = $path;
        $project->save();

        $this->logChange($project, 'updated background image');

        return back()->with('success', 'Background image updated.');
    }

    public function removeBackground(Project $project)
    {
        if ($project->user_id !== Auth::id()) {
            abort(403);
        }

        if ($project->background_image) {
            Storage::disk('private')->delete($project->background_image);
            $project->background_image = null;
            $project->save();
            $this->logChange($project, 'removed background image');
        }

        return back()->with('success', 'Background image removed.');
    }

    public function showBackground(Project $project)
    {
        if (!$project->background_image) {
            abort(404);
        }

        $disk = Storage::disk('private');

        if (!$disk->exists($project->background_image)) {
            abort(404);
        }

        return response($disk->get($project->background_image))
            ->header('Content-Type', $disk->mimeType($project->background_image))
            ->header('Cache-Control', 'private, max-age=86400');
    }

    protected function authorizeProjectAccess(Project $project)
    {
        $isCreator = $project->user_id === Auth::id();
        $isAssignee = $project->assignees()->where('users.id', Auth::id())->exists();
        $hasTaskInProject = $project->tasks()
            ->whereHas('assignees', function ($query) {
                $query->where('users.id', Auth::id());
            })
            ->exists();

        if (!$isCreator && !$isAssignee && !$hasTaskInProject) {
            abort(403, 'You do not have access to this project.');
        }
    }

    protected function logChange(Project $project, string $description)
    {
        $project->changeLogs()->create([
            'date' => now(),
            'user_id' => Auth::id(),
            'entity_type' => 'projects',
            'entity_id' => $project->id,
            'description' => $description,
        ]);
    }
}
