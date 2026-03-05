<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\ChangeLog;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for POST /tasks/bulk-update
 *
 * Covers: authentication, field updates, ownership filtering,
 * validation, change logging, and partial-batch behaviour.
 */
class BulkUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $otherUser;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user      = User::factory()->create();
        $this->otherUser = User::factory()->create();

        $this->project = Project::create([
            'name'    => 'Test Project',
            'user_id' => $this->user->id,
            'status'  => 'incomplete',
        ]);
    }

    /** Create a task owned by (and assigned to) $this->user. */
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

    /** Create a task owned by $this->otherUser that $this->user has no access to. */
    private function createForeignTask(array $overrides = []): Task
    {
        $foreignProject = Project::create([
            'name'    => 'Foreign Project',
            'user_id' => $this->otherUser->id,
            'status'  => 'incomplete',
        ]);

        $task = Task::create(array_merge([
            'name'       => 'Foreign Task',
            'creator_id' => $this->otherUser->id,
            'project_id' => $foreignProject->id,
            'status'     => 'incomplete',
        ], $overrides));

        Assignment::create([
            'task_id'        => $task->id,
            'assignee_id'    => $this->otherUser->id,
            'assigned_by_id' => $this->otherUser->id,
        ]);

        return $task;
    }

    // -------------------------------------------------------------------------
    // Authentication
    // -------------------------------------------------------------------------

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->postJson('/tasks/bulk-update', [
            'task_ids' => [1],
            'status'   => 'done',
        ]);

        $response->assertUnauthorized();
    }

    // -------------------------------------------------------------------------
    // Successful updates
    // -------------------------------------------------------------------------

    public function test_can_bulk_update_status(): void
    {
        $task1 = $this->createOwnedTask(['name' => 'Task A']);
        $task2 = $this->createOwnedTask(['name' => 'Task B']);

        $response = $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids' => [$task1->id, $task2->id],
                'status'   => 'done',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'updated' => 2]);

        $this->assertDatabaseHas('tasks', ['id' => $task1->id, 'status' => 'done']);
        $this->assertDatabaseHas('tasks', ['id' => $task2->id, 'status' => 'done']);
    }

    public function test_can_bulk_update_date(): void
    {
        $task1 = $this->createOwnedTask(['name' => 'Task A']);
        $task2 = $this->createOwnedTask(['name' => 'Task B']);

        $response = $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids' => [$task1->id, $task2->id],
                'date'     => '2026-06-15',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'updated' => 2]);

        $this->assertDatabaseHas('tasks', ['id' => $task1->id, 'date' => '2026-06-15']);
        $this->assertDatabaseHas('tasks', ['id' => $task2->id, 'date' => '2026-06-15']);
    }

    public function test_can_bulk_update_project(): void
    {
        $newProject = Project::create([
            'name'    => 'New Project',
            'user_id' => $this->user->id,
            'status'  => 'incomplete',
        ]);

        $task1 = $this->createOwnedTask(['name' => 'Task A']);
        $task2 = $this->createOwnedTask(['name' => 'Task B']);

        $response = $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids'   => [$task1->id, $task2->id],
                'project_id' => $newProject->id,
            ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'updated' => 2]);

        $this->assertDatabaseHas('tasks', ['id' => $task1->id, 'project_id' => $newProject->id]);
        $this->assertDatabaseHas('tasks', ['id' => $task2->id, 'project_id' => $newProject->id]);
    }

    public function test_can_update_multiple_fields_in_one_request(): void
    {
        $task = $this->createOwnedTask();

        $newProject = Project::create([
            'name'    => 'Another Project',
            'user_id' => $this->user->id,
            'status'  => 'incomplete',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids'   => [$task->id],
                'date'       => '2026-07-04',
                'project_id' => $newProject->id,
                'status'     => 'archived',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'updated' => 1]);

        $this->assertDatabaseHas('tasks', [
            'id'         => $task->id,
            'date'       => '2026-07-04',
            'project_id' => $newProject->id,
            'status'     => 'archived',
        ]);
    }

    // -------------------------------------------------------------------------
    // Ownership / access control
    // -------------------------------------------------------------------------

    public function test_tasks_belonging_to_other_users_are_silently_skipped(): void
    {
        $ownTask     = $this->createOwnedTask(['name' => 'My Task']);
        $foreignTask = $this->createForeignTask(['name' => 'Not Mine']);

        $response = $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids' => [$ownTask->id, $foreignTask->id],
                'status'   => 'done',
            ]);

        // Only the owned task counts; foreign task is ignored silently
        $response->assertOk()
            ->assertJson(['success' => true, 'updated' => 1]);

        $this->assertDatabaseHas('tasks', ['id' => $ownTask->id,     'status' => 'done']);
        $this->assertDatabaseHas('tasks', ['id' => $foreignTask->id, 'status' => 'incomplete']);
    }

    public function test_nonexistent_task_ids_are_silently_skipped(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids' => [99999, 99998],
                'status'   => 'done',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'updated' => 0]);
    }

    public function test_assignees_can_bulk_update_tasks_assigned_to_them(): void
    {
        // otherUser creates a task and assigns it to $this->user
        $sharedProject = Project::create([
            'name'    => 'Shared Project',
            'user_id' => $this->otherUser->id,
            'status'  => 'incomplete',
        ]);

        $task = Task::create([
            'name'       => 'Assigned Task',
            'creator_id' => $this->otherUser->id,
            'project_id' => $sharedProject->id,
            'status'     => 'incomplete',
        ]);

        Assignment::create([
            'task_id'        => $task->id,
            'assignee_id'    => $this->user->id,
            'assigned_by_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids' => [$task->id],
                'status'   => 'done',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'updated' => 1]);

        $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'done']);
    }

    public function test_cannot_bulk_update_project_to_one_owned_by_another_user(): void
    {
        $task           = $this->createOwnedTask();
        $foreignProject = Project::create([
            'name'    => 'Foreign Project',
            'user_id' => $this->otherUser->id,
            'status'  => 'incomplete',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids'   => [$task->id],
                'project_id' => $foreignProject->id,
            ]);

        $response->assertForbidden();

        // Task's project should be unchanged
        $this->assertDatabaseHas('tasks', [
            'id'         => $task->id,
            'project_id' => $this->project->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function test_requires_task_ids(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', ['status' => 'done']);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['task_ids']);
    }

    public function test_rejects_empty_task_ids_array(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids' => [],
                'status'   => 'done',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['task_ids']);
    }

    public function test_rejects_request_with_no_fields_to_change(): void
    {
        $task = $this->createOwnedTask();

        $response = $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids' => [$task->id],
            ]);

        $response->assertUnprocessable();
    }

    public function test_rejects_invalid_status_value(): void
    {
        $task = $this->createOwnedTask();

        $response = $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids' => [$task->id],
                'status'   => 'not-a-real-status',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_rejects_invalid_date_format(): void
    {
        $task = $this->createOwnedTask();

        $response = $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids' => [$task->id],
                'date'     => 'tomorrow',
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['date']);
    }

    public function test_accepts_all_valid_status_values(): void
    {
        foreach (['incomplete', 'done', 'archived'] as $status) {
            $task = $this->createOwnedTask();

            $response = $this->actingAs($this->user)
                ->postJson('/tasks/bulk-update', [
                    'task_ids' => [$task->id],
                    'status'   => $status,
                ]);

            $response->assertOk()
                ->assertJson(['success' => true]);

            $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => $status]);
        }
    }

    // -------------------------------------------------------------------------
    // Change logging
    // -------------------------------------------------------------------------

    public function test_change_logs_are_created_for_each_updated_task(): void
    {
        $task1 = $this->createOwnedTask(['name' => 'Task Alpha']);
        $task2 = $this->createOwnedTask(['name' => 'Task Beta']);

        $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids' => [$task1->id, $task2->id],
                'status'   => 'archived',
            ]);

        foreach ([$task1, $task2] as $task) {
            $this->assertDatabaseHas('change_logs', [
                'entity_type' => 'tasks',
                'entity_id'   => $task->id,
                'user_id'     => $this->user->id,
            ]);
        }
    }

    public function test_change_log_description_mentions_updated_fields(): void
    {
        $task = $this->createOwnedTask();

        $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids' => [$task->id],
                'status'   => 'done',
                'date'     => '2026-08-01',
            ]);

        $log = ChangeLog::where('entity_type', 'tasks')
            ->where('entity_id', $task->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('bulk updated', $log->description);
        $this->assertStringContainsString('status', $log->description);
        $this->assertStringContainsString('date', $log->description);
    }

    public function test_no_change_log_created_for_tasks_not_owned_by_user(): void
    {
        $foreignTask = $this->createForeignTask();

        $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids' => [$foreignTask->id],
                'status'   => 'done',
            ]);

        $this->assertDatabaseMissing('change_logs', [
            'entity_type' => 'tasks',
            'entity_id'   => $foreignTask->id,
            'user_id'     => $this->user->id,
        ]);
    }

    // -------------------------------------------------------------------------
    // Tag bulk-add (append behaviour)
    // -------------------------------------------------------------------------

    public function test_can_bulk_add_tags(): void
    {
        $tag1  = Tag::create(['tag_name' => 'urgent', 'color' => '#ff0000']);
        $tag2  = Tag::create(['tag_name' => 'q2',     'color' => '#00ff00']);
        $task1 = $this->createOwnedTask(['name' => 'Task A']);
        $task2 = $this->createOwnedTask(['name' => 'Task B']);

        $response = $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids' => [$task1->id, $task2->id],
                'tag_ids'  => [$tag1->id, $tag2->id],
            ]);

        $response->assertOk()
            ->assertJson(['success' => true, 'updated' => 2]);

        $this->assertDatabaseHas('tag_task', ['tag_id' => $tag1->id, 'task_id' => $task1->id]);
        $this->assertDatabaseHas('tag_task', ['tag_id' => $tag2->id, 'task_id' => $task1->id]);
        $this->assertDatabaseHas('tag_task', ['tag_id' => $tag1->id, 'task_id' => $task2->id]);
        $this->assertDatabaseHas('tag_task', ['tag_id' => $tag2->id, 'task_id' => $task2->id]);
    }

    public function test_bulk_add_tags_does_not_remove_existing_tags(): void
    {
        $existingTag = Tag::create(['tag_name' => 'existing', 'color' => '#aaaaaa']);
        $newTag      = Tag::create(['tag_name' => 'new',      'color' => '#bbbbbb']);
        $task        = $this->createOwnedTask();
        $task->tags()->attach($existingTag->id);

        $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids' => [$task->id],
                'tag_ids'  => [$newTag->id],
            ]);

        // Both old and new tags should be present
        $this->assertDatabaseHas('tag_task', ['tag_id' => $existingTag->id, 'task_id' => $task->id]);
        $this->assertDatabaseHas('tag_task', ['tag_id' => $newTag->id,      'task_id' => $task->id]);
    }

    public function test_bulk_add_tags_can_combine_with_other_fields(): void
    {
        $tag  = Tag::create(['tag_name' => 'combo', 'color' => '#cccccc']);
        $task = $this->createOwnedTask();

        $response = $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids' => [$task->id],
                'status'   => 'done',
                'tag_ids'  => [$tag->id],
            ]);

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('tasks',    ['id' => $task->id, 'status' => 'done']);
        $this->assertDatabaseHas('tag_task', ['tag_id' => $tag->id, 'task_id' => $task->id]);
    }

    public function test_rejects_invalid_tag_id(): void
    {
        $task = $this->createOwnedTask();

        $response = $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids' => [$task->id],
                'tag_ids'  => [99999],
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['tag_ids.0']);
    }

    public function test_change_log_mentions_tags_when_tags_are_added(): void
    {
        $tag  = Tag::create(['tag_name' => 'logged', 'color' => '#dddddd']);
        $task = $this->createOwnedTask();

        $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids' => [$task->id],
                'tag_ids'  => [$tag->id],
            ]);

        $log = ChangeLog::where('entity_type', 'tasks')
            ->where('entity_id', $task->id)
            ->first();

        $this->assertNotNull($log);
        $this->assertStringContainsString('tags', $log->description);
    }
}
