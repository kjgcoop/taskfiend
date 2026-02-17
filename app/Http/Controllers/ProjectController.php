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
        $query = Project::query()
            ->where(function ($q) {
                $q->where('user_id', Auth::id())
                  ->orWhereHas('assignees', function ($query) {
                      $query->where('users.id', Auth::id());
                  });
            });

        if ($request->has('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', '!=', 'archived');
        }

        $projects = $query->withCount('tasks')
            ->with('creator')
            ->orderBy('name')
            ->get();

        return view('projects.index', compact('projects'));
    }

    public function create()
    {
        $users = User::where('email_enabled_at', null)
            ->orderBy('name')
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

        return view('projects.show', compact('project', 'tasks'));
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
            ->orderBy('name')
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
