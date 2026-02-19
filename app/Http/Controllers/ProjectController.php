<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            ->withCount('tasks')
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

    public function show(Project $project)
    {
        $this->authorizeProjectAccess($project);

        $tasks = $project->tasks()
            ->where(function ($q) {
                $q->where('creator_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            })
            ->where('status', '!=', 'archived')
            ->where('status', '!=', 'done')
            ->whereNull('parent_id') // Only get root-level tasks
            ->with([
                'creator',
                'tags',
                'assignees',
                'attachments',
                'comments',
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
                                    ->with(['tags', 'assignees', 'attachments', 'creator']);
                              }
                          ]);
                }
            ])
            ->orderBy('date')
            ->orderBy('time')
            ->get();

        $project->load(['creator', 'assignees', 'changeLogs.user']);

        $users = User::where('email_enabled_at', null)
            ->orderByRaw('LOWER(name)')
            ->get();

        return view('projects.show', compact('project', 'tasks', 'users'));
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

    public function edit(Project $project)
    {
        if ($project->is_inbox) {
            abort(403, 'Inbox projects cannot be edited.');
        }

        if ($project->user_id !== Auth::id()) {
            abort(403, 'Only the project creator can edit it.');
        }

        $users = User::where('email_enabled_at', null)
            ->orderByRaw('LOWER(name)')
            ->get();

        return view('projects.edit', compact('project', 'users'));
    }

    public function update(Request $request, Project $project)
    {
        if ($project->is_inbox) {
            abort(403, 'Inbox projects cannot be edited.');
        }

        if ($project->user_id !== Auth::id()) {
            abort(403, 'Only the project creator can edit it.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'in:incomplete,done,archived',
            'assignee_ids' => 'nullable|array',
            'assignee_ids.*' => 'exists:users,id',
        ]);

        $changes = [];
        foreach (['name', 'description', 'status'] as $field) {
            if (isset($validated[$field]) && $project->$field != $validated[$field]) {
                $changes[$field] = ['old' => $project->$field, 'new' => $validated[$field]];
                $project->$field = $validated[$field];
            }
        }

        $project->save();

        foreach ($changes as $field => $change) {
            $this->logChange($project, "changed {$field} from {$change['old']} to {$change['new']}");
        }

        // Sync assignees if provided
        if (isset($validated['assignee_ids'])) {
            $project->assignees()->sync($validated['assignee_ids']);
        }

        return redirect()->route('projects.show', $project)
            ->with('success', 'Project updated successfully.');
    }

    public function destroy(Project $project)
    {
        if ($project->is_inbox) {
            abort(403, 'Inbox projects cannot be deleted or archived.');
        }

        abort(403, 'Projects cannot be deleted. Please archive instead.');
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
