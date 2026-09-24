<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for GET /tasks/{task}/panel (TaskController::panel), covering the new
 * Parent Task field. The full task page already renders/edits a task's parent
 * (tasks/show.blade.php); the sidebar panel (tasks/_panel.blade.php) did not.
 */
class TaskPanelParentTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->project = Project::create([
            'name'       => 'Panel Project',
            'user_id'    => $this->user->id,
            'status'     => 'incomplete',
            'is_default' => true, // TaskController::panel() calls Auth::user()->defaultProject().
        ]);
    }

    private function makeTask(array $attributes = []): Task
    {
        $task = Task::create(array_merge([
            'name'       => 'Task',
            'creator_id' => $this->user->id,
            'project_id' => $this->project->id,
            'status'     => 'incomplete',
        ], $attributes));

        return $task->refresh();
    }

    public function test_panel_shows_current_parent_name_when_set(): void
    {
        $parent = $this->makeTask(['name' => 'Parent Task Name']);
        $child  = $this->makeTask(['name' => 'Child Task', 'parent_id' => $parent->id]);

        $response = $this->actingAs($this->user)->get("/tasks/{$child->id}/panel");

        $response->assertOk();
        $response->assertSee('Parent Task', false);
        $response->assertSee('Parent Task Name', false);
    }

    public function test_panel_shows_no_parent_placeholder_when_unset(): void
    {
        $task = $this->makeTask(['name' => 'Standalone Task']);

        $response = $this->actingAs($this->user)->get("/tasks/{$task->id}/panel");

        $response->assertOk();
        $response->assertSee('None (Top-level task)', false);
    }

    public function test_panel_offers_visible_incomplete_task_as_candidate_parent(): void
    {
        $task      = $this->makeTask(['name' => 'Task Needing A Parent']);
        $candidate = $this->makeTask(['name' => 'Candidate Parent Task']);

        $response = $this->actingAs($this->user)->get("/tasks/{$task->id}/panel");

        $response->assertOk();
        $response->assertSee('Candidate Parent Task', false);
    }
}
