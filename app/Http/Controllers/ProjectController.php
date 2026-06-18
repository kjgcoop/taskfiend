<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectReminder;
use App\Models\ProjectStatusLog;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Services\DateParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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
                'tasks as open_tasks_count'      => fn ($q) => $q->where('status', 'incomplete')->whereNull('parent_id'),
                'tasks as done_tasks_count'       => fn ($q) => $q->whereIn('status', ['done', 'archived'])->whereNull('parent_id'),
            ])
            ->with([
                'creator',
                'latestStatusLog',
            ])
            ->get();

        $natSort = fn ($a, $b) => strnatcasecmp($a->name, $b->name);
        $projects         = $all->where('status', 'incomplete')->sort($natSort)->values();
        $inactiveProjects = $all->whereIn('status', ['done', 'archived'])->sort($natSort)->values();

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
        $longTextMax = (int) env('LONG_TEXT_MAX_CHARS', 10000);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => "nullable|string|max:{$longTextMax}",
            'end_date' => 'nullable|date',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
        ]);

        $project = Project::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'end_date' => $validated['end_date'] ?? null,
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

        $sort     = $request->input('sort', 'date');
        $reversed = $request->boolean('reversed');

        $isDirectMember = $project->user_id === Auth::id()
            || $project->assignees()->where('users.id', Auth::id())->exists();

        $visibleToUser = function ($q) use ($isDirectMember) {
            if (!$isDirectMember) {
                $q->where('creator_id', Auth::id())
                    ->orWhereHas('assignees', function ($query) {
                        $query->where('users.id', Auth::id());
                    });
            }
        };


        /*
                $visibleToUser = function ($q) {
                    $q->where('creator_id', Auth::id())
                      ->orWhereHas('assignees', function ($query) {
                          $query->where('users.id', Auth::id());
                      });
                };*/

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
            ->when($sort === 'created',  fn($q) => $q->orderBy('created_at', $reversed ? 'asc' : 'desc'))
            ->when($sort === 'name',     fn($q) => $q->orderByRaw($reversed ? 'LOWER(name) DESC' : 'LOWER(name) ASC'))
            ->when($sort === 'custom',   fn($q) => $q->orderByRaw('CASE WHEN project_sort_order IS NULL THEN 1 ELSE 0 END, project_sort_order ASC, date IS NULL, date ASC, time IS NULL, time ASC, created_at ASC'))
            ->when($sort === 'location', fn($q) => $q->orderByRaw($reversed ? '(location IS NULL OR location = \'\') ASC, LOWER(location) DESC' : '(location IS NULL OR location = \'\') ASC, LOWER(location) ASC'))
            ->when($sort === 'duration', fn($q) => $q->orderByRaw($reversed ? 'CASE WHEN duration_minutes IS NULL THEN 1 ELSE 0 END, duration_minutes DESC' : 'CASE WHEN duration_minutes IS NULL THEN 1 ELSE 0 END, duration_minutes ASC'))
            ->when(!in_array($sort, ['created', 'name', 'custom', 'location', 'duration']), fn($q) => $q->orderByRaw($reversed ? 'date IS NULL, date DESC, time IS NULL, time DESC, created_at DESC' : 'date IS NULL, date ASC, time IS NULL, time ASC, created_at ASC'))
//            ->orderBy('date')
//            ->orderBy('time')
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

        $archivedTasksTotal = $project->tasks()
            ->where($visibleToUser)
            ->where('status', 'archived')
            ->whereNull('parent_id')
            ->count();

        $archivedTasksRaw = $project->tasks()
            ->where($visibleToUser)
            ->where('status', 'archived')
            ->whereNull('parent_id')
            ->with(array_merge($taskEagerLoad, [
                'children' => function ($query) {
                    $query->where('status', 'archived')
                          ->with([
                              'tags', 'assignees', 'attachments', 'creator',
                              'children' => function ($q) {
                                  $q->where('status', 'archived')
                                    ->with(['tags', 'assignees', 'attachments', 'creator', 'children']);
                              }
                          ]);
                },
            ]))
            ->orderByDesc('updated_at')
            ->take($perPage + 1)
            ->get();

        $archivedTasksHasMore = $archivedTasksRaw->count() > $perPage;
        $archivedTasks = $archivedTasksHasMore ? $archivedTasksRaw->slice(0, $perPage) : $archivedTasksRaw;

        $project->load(['creator', 'assignees', 'changeLogs.user', 'statusLogs.user']);

        $activeReminder = $project->reminders()
            ->where('user_id', Auth::id())
            ->where('dismissed', false)
            ->orderBy('date')
            ->first();

        $users = User::where('email_enabled_at', null)
            ->orderByRaw('LOWER(name)')
            ->get();

        $projects = Project::activeForUser(Auth::id())->get(['id', 'name', 'is_hearted'])
            ->sort(fn ($a, $b) => strnatcasecmp($a->name, $b->name))
            ->values();

        $tags = Tag::orderByRaw('LOWER(tag_name)')->get(['id', 'tag_name', 'color']);

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

        // All tasks here belong to one project, so no breakdown tooltip is needed.
        $breakdown = [];

        return view('projects.show', compact(
            'project', 'tasks', 'breakdown',
            'completedTasks', 'completedTasksHasMore', 'completedTasksTotal',
            'archivedTasks', 'archivedTasksHasMore', 'archivedTasksTotal',
            'users', 'projects', 'tags', 'locations', 'sort', 'activeReminder'
        ));
    }

    public function completedTasks(Request $request, Project $project)
    {
        $this->authorizeProjectAccess($project);

        $perPage = (int) env('PAGINATION_PER_PAGE', 100);
        $page    = max(1, (int) $request->get('page', 1));
        $offset  = ($page - 1) * $perPage;

        $readOnly = in_array($project->status, ['done', 'archived']);

        $isDirectMember = $project->user_id === Auth::id()
            || $project->assignees()->where('users.id', Auth::id())->exists();

        $visibleToUser = function ($q) use ($isDirectMember) {
            if (!$isDirectMember) {
                $q->where('creator_id', Auth::id())
                    ->orWhereHas('assignees', function ($query) {
                        $query->where('users.id', Auth::id());
                    });
            }
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

    public function archivedTasks(Request $request, Project $project)
    {
        $this->authorizeProjectAccess($project);

        $perPage = (int) env('PAGINATION_PER_PAGE', 100);
        $page    = max(1, (int) $request->get('page', 1));
        $offset  = ($page - 1) * $perPage;

        $isDirectMember = $project->user_id === Auth::id()
            || $project->assignees()->where('users.id', Auth::id())->exists();

        $visibleToUser = function ($q) use ($isDirectMember) {
            if (!$isDirectMember) {
                $q->where('creator_id', Auth::id())
                    ->orWhereHas('assignees', function ($query) {
                        $query->where('users.id', Auth::id());
                    });
            }
        };

        $tasks = $project->tasks()
            ->where($visibleToUser)
            ->where('status', 'archived')
            ->whereNull('parent_id')
            ->with([
                'creator', 'tags', 'assignees', 'attachments', 'comments', 'completionLog.user',
                'children' => function ($query) {
                    $query->where('status', 'archived')
                          ->with([
                              'tags', 'assignees', 'attachments', 'creator',
                              'children' => function ($q) {
                                  $q->where('status', 'archived')
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

        $readOnly = true;
        $showAsArchived = true;

        return response()->json([
            'html'     => view('tasks.partials.completed-list', compact('tasks', 'readOnly', 'showAsArchived'))->render(),
            'hasMore'  => $hasMore,
            'nextPage' => $page + 1,
        ]);
    }

    public function updateField(Request $request, Project $project)
    {
        if ($project->user_id !== Auth::id()) {
            return response()->json(['success' => false, 'message' => 'Only the project creator can edit it.'], 403);
        }

        $field = $request->input('field');
        $allowedFields = ['name', 'description', 'status', 'end_date', 'assignee_ids'];

        if (!in_array($field, $allowedFields)) {
            return response()->json(['success' => false, 'message' => 'Invalid field'], 400);
        }

        if (in_array($project->status, ['done', 'archived']) && $field !== 'status') {
            return response()->json(['success' => false, 'message' => 'This project is inactive. Only the status can be changed.'], 403);
        }

        try {
            if ($field === 'assignee_ids') {
                $assigneeIds = $request->input('assignee_ids', []);
                if (!in_array($project->user_id, $assigneeIds)) {
                    $assigneeIds[] = $project->user_id;
                }
                $project->assignees()->sync($assigneeIds);
                $this->logChange($project, 'updated assignees');
            } else {
                $value = $request->input('value');

                if ($field === 'status' && !in_array($value, ['incomplete', 'done', 'archived'])) {
                    return response()->json(['success' => false, 'message' => 'Invalid status'], 400);
                }

                if ($field === 'status' && in_array($value, ['done', 'archived'])) {
                    if ($project->is_default) {
                        return response()->json(['success' => false, 'message' => 'This is your default project. Pin a different project as default before archiving it.'], 422);
                    }
                }

                if ($field === 'name' && empty(trim($value))) {
                    return response()->json(['success' => false, 'message' => 'Name cannot be empty'], 400);
                }
                if ($field === 'name' && strlen((string) $value) > 255) {
                    return response()->json(['success' => false, 'message' => 'Name cannot exceed 255 characters.'], 422);
                }
                $longTextMax = (int) env('LONG_TEXT_MAX_CHARS', 10000);
                if ($field === 'description' && strlen((string) $value) > $longTextMax) {
                    return response()->json(['success' => false, 'message' => "Description cannot exceed {$longTextMax} characters."], 422);
                }

                if ($field === 'end_date') {
                    $value = $value ? \Carbon\Carbon::parse($value)->toDateString() : null;
                }

                $old = $project->$field;
                $project->$field = $value;
                $project->save();
                $this->logChange($project, "changed {$field} from {$old} to {$value}");
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('ProjectController::updateField failed', [
                'project_id' => $project->id,
                'field'      => $field,
                'error'      => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()], 500);
        }
    }

    public function reorderTasks(Request $request, Project $project)
    {
        $this->authorizeProjectAccess($project);

        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'integer',
        ]);

        $userId = Auth::id();

        // Only update tasks within this project that the user can access
        $accessibleIds = Task::where('project_id', $project->id)
            ->where(function ($q) use ($userId) {
                $q->where('creator_id', $userId)
                  ->orWhereHas('assignees', fn($q2) => $q2->where('users.id', $userId));
            })
            ->whereIn('id', $request->ids)
            ->pluck('id')
            ->flip();

        foreach ($request->ids as $position => $id) {
            if ($accessibleIds->has($id)) {
                Task::where('id', $id)->update(['project_sort_order' => $position]);
            }
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(Project $project)
    {
        if ($project->is_default) {
            abort(403, 'Your default project cannot be deleted or archived. Pin a different project as default first.');
        }

        abort(403, 'Projects cannot be deleted. Please archive instead.');
    }

    public function toggleHeart(Project $project)
    {
        if ($project->user_id !== Auth::id()) {
            abort(403);
        }

        $project->update(['is_hearted' => !$project->is_hearted]);

        return response()->json(['success' => true, 'is_hearted' => $project->is_hearted]);
    }

    public function setDefault(Project $project)
    {
        if ($project->user_id !== Auth::id()) {
            abort(403);
        }

        if ($project->status !== 'incomplete') {
            return response()->json(['success' => false, 'message' => 'Only active projects can be set as default.'], 422);
        }

        // Unpin the current default, then pin this one.
        Project::where('user_id', Auth::id())->where('is_default', true)->update(['is_default' => false]);
        $project->update(['is_default' => true]);

        return response()->json(['success' => true]);
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

        $file      = $request->file('background_image');
        $mime      = $file->getMimeType();
        $directory = "project-backgrounds/{$project->id}";
        $path      = null;

        if (in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif'])) {
            $scaleTo = (int) env('SCALE_LARGEST_TO', 2048);
            $src = @imagecreatefromstring(file_get_contents($file->getRealPath()));

            if ($src) {
                $srcW = imagesx($src);
                $srcH = imagesy($src);

                if (max($srcW, $srcH) > $scaleTo) {
                    $ratio = $scaleTo / max($srcW, $srcH);
                    $newW  = (int) round($srcW * $ratio);
                    $newH  = (int) round($srcH * $ratio);

                    $dst = imagecreatetruecolor($newW, $newH);
                    if ($mime === 'image/png') {
                        imagealphablending($dst, false);
                        imagesavealpha($dst, true);
                        $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                        imagefilledrectangle($dst, 0, 0, $newW, $newH, $transparent);
                    }
                    imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);
                    imagedestroy($src);

                    ob_start();
                    match ($mime) {
                        'image/jpeg' => imagejpeg($dst, null, 90),
                        'image/png'  => imagepng($dst),
                        'image/webp' => imagewebp($dst, null, 90),
                        'image/gif'  => imagegif($dst),
                    };
                    $data = ob_get_clean();
                    imagedestroy($dst);

                    $ext  = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
                    $path = $directory . '/' . uniqid() . '.' . $ext;
                    Storage::disk('private')->put($path, $data);
                } else {
                    imagedestroy($src);
                }
            }
        }

        if ($path === null) {
            $path = $file->store($directory, 'private');
        }

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
        $this->authorizeProjectAccess($project);

        if (!$project->background_image) {
            abort(404);
        }

        $disk = Storage::disk('private');
        $path = $project->background_image;

        if (!$disk->exists($path)) {
            abort(404);
        }

        $contents = $disk->get($path);
        if ($contents === null) {
            abort(404);
        }

        $mimeType = $disk->mimeType($path) ?: match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'avif'        => 'image/avif',
            'heic', 'heif' => 'image/heic',
            default       => 'application/octet-stream',
        };

        return response($contents)
            ->header('Content-Type', $mimeType)
            ->header('Cache-Control', 'private, max-age=86400');
    }

    public function storeReminder(Request $request, Project $project)
    {
        $this->authorizeProjectAccess($project);

        $request->validate([
            'date'                => 'required|date',
            'recurrence_pattern'  => 'nullable|string|max:100',
            'recurrence_floating' => 'nullable|boolean',
        ]);

        $pattern = $request->input('recurrence_pattern') ?: null;

        if ($pattern && !(new DateParser)->isValidRecurrencePattern($pattern)) {
            return back()->withErrors(['recurrence_pattern' => 'Unrecognized recurrence pattern.'])->withInput();
        }

        // Replace any existing undismissed reminder for this user on this project
        ProjectReminder::where('project_id', $project->id)
            ->where('user_id', Auth::id())
            ->where('dismissed', false)
            ->delete();

        $project->reminders()->create([
            'user_id'             => Auth::id(),
            'date'                => $request->input('date'),
            'recurrence_pattern'  => $pattern,
            'recurrence_floating' => $request->boolean('recurrence_floating'),
        ]);

        $this->logChange($project, 'set a project reminder for ' . $request->input('date'));

        return back()->with('success', 'Reminder set.');
    }

    public function dismissReminder(Request $request, Project $project, ProjectReminder $reminder)
    {
        $this->authorizeProjectAccess($project);

        if ($reminder->user_id !== Auth::id()) {
            abort(403);
        }

        $reminder->update(['dismissed' => true]);

        if ($reminder->recurrence_pattern) {
            $dateParser = new DateParser;
            $base = $reminder->recurrence_floating ? now() : $reminder->date;
            $next = $dateParser->getNextOccurrence($reminder->recurrence_pattern, $base);

            if ($next) {
                $project->reminders()->create([
                    'user_id'             => Auth::id(),
                    'date'                => $next->toDateString(),
                    'recurrence_pattern'  => $reminder->recurrence_pattern,
                    'recurrence_floating' => $reminder->recurrence_floating,
                ]);
            }
        }

        return back()->with('success', 'Reminder dismissed.');
    }

    public function destroyReminder(Request $request, Project $project, ProjectReminder $reminder)
    {
        $this->authorizeProjectAccess($project);

        if ($reminder->user_id !== Auth::id()) {
            abort(403);
        }

        $reminder->delete();
        $this->logChange($project, 'removed project reminder');

        return back()->with('success', 'Reminder removed.');
    }

    public function storeStatusLog(Request $request, Project $project)
    {
        $this->authorizeProjectMember($project);

        $request->validate(['body' => 'required|string|max:10000']);

        $log = $project->statusLogs()->create([
            'user_id' => Auth::id(),
            'body'    => $request->input('body'),
        ]);

        $this->logChange($project, 'posted a status update');

        return back()->with('success', 'Status posted.');
    }

    public function destroyStatusLog(Request $request, Project $project, ProjectStatusLog $statusLog)
    {
        $this->authorizeProjectMember($project);

        if ($statusLog->project_id !== $project->id) {
            abort(404);
        }

        if ($statusLog->user_id !== Auth::id() && $project->user_id !== Auth::id()) {
            abort(403, 'You can only delete your own status updates, unless you are the project creator.');
        }

        $statusLog->delete();
        $this->logChange($project, 'deleted a status update');

        return back()->with('success', 'Status update deleted.');
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

    protected function authorizeProjectMember(Project $project)
    {
        $isMember = $project->user_id === Auth::id()
            || $project->assignees()->where('users.id', Auth::id())->exists();

        if (!$isMember) {
            abort(403, 'You must be a project member to do this.');
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
