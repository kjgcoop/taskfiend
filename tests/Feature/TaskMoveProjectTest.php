<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for moving an existing task into a different project via:
 *   PUT  /tasks/{task}                    TaskController::update()
 *   POST /tasks/{task}/update-field       TaskController::updateField() (field=project_id)
 *
 * store() already blocks creating a task directly inside a done/archived
 * project (see TaskStoreTest); these cover the matching "move an existing
 * task into" rule (part of the "Audit project pickers for archived/done
 * projects" plan item), which the same two endpoints did not previously
 * enforce.
 */
class TaskMoveProjectTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->project = Project::create([
            'name'    => 'My Project',
            'user_id' => $this->user->id,
            'status'  => 'incomplete',
        ]);
    }

    /** Create a task owned by (and assigned to) $this->user in $this->project. */
    private function createOwnedTask(array $overrides = []): Task
    {
        $task = Task::create(array_merge([
            'name'       => 'Test Task',
            'creator_id' => $this->user->id,
            'project_id' => $this->project->id,
            'status'     => 'incomplete',
        ], $overrides));

        Assignment::create([
            'task_id'        => $task->id,
            'assignee_id'    => $this->user->id,
            'assigned_by_id' => $this->user->id,
        ]);

        return $task;
    }

    // =========================================================================
    // PUT /tasks/{task}
    // =========================================================================

    public function test_cannot_move_task_to_done_project_via_update(): void
    {
        $task = $this->createOwnedTask();
        $doneProject = Project::create([
            'name'    => 'Done Project',
            'user_id' => $this->user->id,
            'status'  => 'done',
        ]);

        $response = $this->actingAs($this->user)->put("/tasks/{$task->id}", [
            'name'       => $task->name,
            'project_id' => $doneProject->id,
        ]);

        $response->assertSessionHasErrors('project_id');
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'project_id' => $this->project->id]);
    }

    public function test_cannot_move_task_to_archived_project_via_update(): void
    {
        $task = $this->createOwnedTask();
        $archivedProject = Project::create([
            'name'    => 'Archived Project',
            'user_id' => $this->user->id,
            'status'  => 'archived',
        ]);

        $response = $this->actingAs($this->user)->put("/tasks/{$task->id}", [
            'name'       => $task->name,
            'project_id' => $archivedProject->id,
        ]);

        $response->assertSessionHasErrors('project_id');
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'project_id' => $this->project->id]);
    }

    // =========================================================================
    // POST /tasks/{task}/update-field  (field=project_id)
    // =========================================================================

    private function updateField(Task $task, string $field, $value)
    {
        return $this->actingAs($this->user)->postJson("/tasks/{$task->id}/update-field", [
            'field' => $field,
            'value' => $value,
        ]);
    }

    public function test_cannot_move_task_to_done_project_via_update_field(): void
    {
        $task = $this->createOwnedTask();
        $doneProject = Project::create([
            'name'    => 'Done Project',
            'user_id' => $this->user->id,
            'status'  => 'done',
        ]);

        $response = $this->updateField($task, 'project_id', $doneProject->id);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'project_id' => $this->project->id]);
    }

    public function test_cannot_move_task_to_archived_project_via_update_field(): void
    {
        $task = $this->createOwnedTask();
        $archivedProject = Project::create([
            'name'    => 'Archived Project',
            'user_id' => $this->user->id,
            'status'  => 'archived',
        ]);

        $response = $this->updateField($task, 'project_id', $archivedProject->id);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'project_id' => $this->project->id]);
    }

    public function test_cannot_move_task_via_update_field_to_project_owned_by_another_user(): void
    {
        $task = $this->createOwnedTask();
        $other = User::factory()->create();
        $foreignProject = Project::create([
            'name'    => 'Foreign Project',
            'user_id' => $other->id,
            'status'  => 'incomplete',
        ]);

        $response = $this->updateField($task, 'project_id', $foreignProject->id);

        $response->assertStatus(422)->assertJson(['success' => false]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'project_id' => $this->project->id]);
    }

    public function test_can_move_task_via_update_field_to_active_project_user_is_member_of(): void
    {
        $task = $this->createOwnedTask();
        $newProject = Project::create([
            'name'    => 'New Project',
            'user_id' => $this->user->id,
            'status'  => 'incomplete',
        ]);

        $response = $this->updateField($task, 'project_id', $newProject->id);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'project_id' => $newProject->id]);
    }
}
