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
 * Feature tests for POST /tasks  (TaskController::store)
 *
 * Covers: validation, project fallback, inactive-project blocking,
 * parent-task rules, #project/@tag token parsing (quick-add),
 * recurrence pattern handling, assignee inheritance, and change logging.
 */
class TaskStoreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->project = Project::create([
            'name'       => 'My Project',
            'user_id'    => $this->user->id,
            'status'     => 'incomplete',
            'is_default' => true,
        ]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** POST /tasks as the authenticated user with sensible defaults merged in. */
    private function storeTask(array $data = [])
    {
        return $this->actingAs($this->user)->post('/tasks', array_merge([
            'name'       => 'Test Task',
            'project_id' => $this->project->id,
        ], $data));
    }

    /** Create a tag owned globally (tags are global). */
    private function createTag(string $name = 'urgent'): Tag
    {
        return Tag::create(['tag_name' => $name, 'color' => '#ff0000']);
    }

    /** Create a second user. */
    private function createOtherUser(): User
    {
        return User::factory()->create();
    }

    // =========================================================================
    // Authentication
    // =========================================================================

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->post('/tasks', ['name' => 'Test']);

        $response->assertRedirect('/login');
    }

    // =========================================================================
    // Validation
    // =========================================================================

    public function test_name_is_required(): void
    {
        $response = $this->storeTask(['name' => '']);

        $response->assertSessionHasErrors('name');
    }

    public function test_name_cannot_exceed_255_characters(): void
    {
        $response = $this->storeTask(['name' => str_repeat('a', 256)]);

        $response->assertSessionHasErrors('name');
    }

    public function test_project_id_must_exist_in_database(): void
    {
        $response = $this->storeTask(['project_id' => 99999]);

        $response->assertSessionHasErrors('project_id');
    }

    public function test_tag_ids_must_exist_in_database(): void
    {
        $response = $this->storeTask(['tag_ids' => [99999]]);

        $response->assertSessionHasErrors('tag_ids.0');
    }

    public function test_assignee_ids_must_exist_in_database(): void
    {
        $response = $this->storeTask(['assignee_ids' => [99999]]);

        $response->assertSessionHasErrors('assignee_ids.0');
    }

    public function test_time_must_be_valid_h_i_format(): void
    {
        $response = $this->storeTask(['time' => 'not-a-time']);

        $response->assertSessionHasErrors('time');
    }

    // =========================================================================
    // Successful creation — basic fields
    // =========================================================================

    public function test_minimal_task_is_created_and_redirects_to_show(): void
    {
        $response = $this->storeTask(['name' => 'My Task']);

        $response->assertRedirect();
        $this->assertDatabaseHas('tasks', [
            'name'       => 'My Task',
            'creator_id' => $this->user->id,
            'status'     => 'incomplete',
        ]);
    }

    public function test_created_task_has_correct_project(): void
    {
        $this->storeTask(['name' => 'Proj Task', 'project_id' => $this->project->id]);

        $this->assertDatabaseHas('tasks', [
            'name'       => 'Proj Task',
            'project_id' => $this->project->id,
        ]);
    }

    public function test_description_is_stored(): void
    {
        $this->storeTask(['description' => 'Some details here.']);

        $this->assertDatabaseHas('tasks', ['description' => 'Some details here.']);
    }

    public function test_iso_date_is_stored(): void
    {
        $this->storeTask(['date' => '2026-06-15']);

        $this->assertDatabaseHas('tasks', ['date' => '2026-06-15']);
    }

    public function test_tags_are_synced(): void
    {
        $tag = $this->createTag();

        $this->storeTask(['tag_ids' => [$tag->id]]);

        $task = Task::where('name', 'Test Task')->firstOrFail();
        $this->assertTrue($task->tags->contains($tag));
    }

    public function test_explicit_assignees_are_saved(): void
    {
        $other = $this->createOtherUser();

        $this->storeTask(['assignee_ids' => [$other->id]]);

        $task = Task::where('name', 'Test Task')->firstOrFail();
        $this->assertTrue($task->assignees->contains($other));
    }

    public function test_recurrence_pattern_is_stored(): void
    {
        $this->storeTask(['recurrence_pattern' => 'weekly']);

        $this->assertDatabaseHas('tasks', ['recurrence_pattern' => 'weekly']);
    }

    public function test_recurrence_floating_flag_is_stored(): void
    {
        $this->storeTask(['recurrence_pattern' => 'daily', 'recurrence_floating' => true]);

        $this->assertDatabaseHas('tasks', ['recurrence_floating' => true]);
    }

    // =========================================================================
    // Default assignee
    // =========================================================================

    public function test_creator_is_auto_assigned_when_no_assignees_given(): void
    {
        $this->storeTask(['name' => 'Solo Task']);

        $task = Task::where('name', 'Solo Task')->firstOrFail();
        $this->assertTrue($task->assignees->contains($this->user));
    }

    // =========================================================================
    // Project fallback logic
    // =========================================================================

    public function test_uses_default_project_when_no_project_id_given(): void
    {
        $defaultProject = Project::create([
            'name'       => 'Default',
            'user_id'    => $this->user->id,
            'status'     => 'incomplete',
            'is_default' => true,
        ]);

        $this->actingAs($this->user)->post('/tasks', ['name' => 'No Project Task']);

        $this->assertDatabaseHas('tasks', [
            'name'       => 'No Project Task',
            'project_id' => $defaultProject->id,
        ]);
    }

    // =========================================================================
    // Inactive project blocking
    // =========================================================================

    public function test_cannot_create_task_in_done_project(): void
    {
        $doneProject = Project::create([
            'name'    => 'Done Project',
            'user_id' => $this->user->id,
            'status'  => 'done',
        ]);

        $response = $this->storeTask(['project_id' => $doneProject->id]);

        $response->assertSessionHasErrors('project_id');
        $this->assertDatabaseMissing('tasks', ['project_id' => $doneProject->id]);
    }

    public function test_cannot_create_task_in_archived_project(): void
    {
        $archivedProject = Project::create([
            'name'    => 'Archived Project',
            'user_id' => $this->user->id,
            'status'  => 'archived',
        ]);

        $response = $this->storeTask(['project_id' => $archivedProject->id]);

        $response->assertSessionHasErrors('project_id');
        $this->assertDatabaseMissing('tasks', ['project_id' => $archivedProject->id]);
    }

    // =========================================================================
    // Parent task rules
    // =========================================================================

    public function test_parent_task_is_set_correctly(): void
    {
        $parent = Task::create([
            'name'       => 'Parent',
            'creator_id' => $this->user->id,
            'project_id' => $this->project->id,
            'status'     => 'incomplete',
        ]);

        $this->storeTask(['name' => 'Child', 'parent_id' => $parent->id]);

        $this->assertDatabaseHas('tasks', [
            'name'      => 'Child',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_subtask_inherits_parent_assignees_when_none_specified(): void
    {
        $other = $this->createOtherUser();

        $parent = Task::create([
            'name'       => 'Parent Task',
            'creator_id' => $this->user->id,
            'project_id' => $this->project->id,
            'status'     => 'incomplete',
        ]);
        $parent->assignments()->create([
            'assignee_id'    => $other->id,
            'assigned_by_id' => $this->user->id,
        ]);

        $this->storeTask(['name' => 'Child Task', 'parent_id' => $parent->id]);

        $child = Task::where('name', 'Child Task')->firstOrFail();
        $this->assertTrue($child->assignees->contains($other));
    }

    public function test_subtask_uses_explicit_assignees_over_parent_inheritance(): void
    {
        $other = $this->createOtherUser();
        $third = $this->createOtherUser();

        $parent = Task::create([
            'name'       => 'Parent Task',
            'creator_id' => $this->user->id,
            'project_id' => $this->project->id,
            'status'     => 'incomplete',
        ]);
        $parent->assignments()->create([
            'assignee_id'    => $other->id,
            'assigned_by_id' => $this->user->id,
        ]);

        $this->storeTask([
            'name'         => 'Child Task',
            'parent_id'    => $parent->id,
            'assignee_ids' => [$third->id],
        ]);

        $child = Task::where('name', 'Child Task')->firstOrFail();
        $this->assertTrue($child->assignees->contains($third));
        $this->assertFalse($child->assignees->contains($other));
    }

    public function test_cannot_create_subtask_under_archived_parent(): void
    {
        $archivedParent = Task::create([
            'name'       => 'Archived Parent',
            'creator_id' => $this->user->id,
            'project_id' => $this->project->id,
            'status'     => 'archived',
        ]);

        $response = $this->storeTask(['parent_id' => $archivedParent->id]);

        $response->assertSessionHasErrors('parent_id');
        $this->assertDatabaseMissing('tasks', ['parent_id' => $archivedParent->id]);
    }

    public function test_cannot_set_parent_task_user_has_no_access_to(): void
    {
        $other = $this->createOtherUser();
        $foreignProject = Project::create([
            'name'    => 'Foreign',
            'user_id' => $other->id,
            'status'  => 'incomplete',
        ]);
        $foreignParent = Task::create([
            'name'       => 'Foreign Parent',
            'creator_id' => $other->id,
            'project_id' => $foreignProject->id,
            'status'     => 'incomplete',
        ]);
        $foreignParent->assignments()->create([
            'assignee_id'    => $other->id,
            'assigned_by_id' => $other->id,
        ]);

        $response = $this->storeTask(['parent_id' => $foreignParent->id]);

        $response->assertForbidden();
    }

    // =========================================================================
    // Recurrence
    // =========================================================================

    public function test_invalid_recurrence_pattern_is_rejected(): void
    {
        $response = $this->storeTask(['recurrence_pattern' => 'every fortnight']);

        $response->assertSessionHasErrors('recurrence_pattern');
        $this->assertDatabaseMissing('tasks', ['recurrence_pattern' => 'every fortnight']);
    }

    public function test_recurrence_without_date_auto_populates_date(): void
    {
        $this->storeTask(['recurrence_pattern' => 'daily']);

        $task = Task::where('name', 'Test Task')->firstOrFail();
        $this->assertNotNull($task->date, 'Date should be auto-set when recurrence_pattern is provided without a date');
    }

    public function test_recurrence_with_explicit_date_keeps_that_date(): void
    {
        $this->storeTask([
            'recurrence_pattern' => 'weekly',
            'date'               => '2026-07-04',
        ]);

        $this->assertDatabaseHas('tasks', [
            'name'               => 'Test Task',
            'recurrence_pattern' => 'weekly',
            'date'               => '2026-07-04',
        ]);
    }

    // =========================================================================
    // Quick-add: #project and @tag token parsing
    // =========================================================================

    public function test_hash_project_token_assigns_project_and_strips_token(): void
    {
        // Use a single-word project name so the slug matches exactly — the controller
        // compares the token slug against LOWER(name) without converting hyphens to spaces.
        $project = Project::create([
            'name'    => 'work',
            'user_id' => $this->user->id,
            'status'  => 'incomplete',
        ]);

        $response = $this->actingAs($this->user)->post('/tasks', [
            'name'      => 'Fix bug #work',
            'quick_add' => true,
        ]);

        $response->assertRedirect();
        $task = Task::where('creator_id', $this->user->id)->latest()->first();
        $this->assertSame('Fix bug', $task->name);
        $this->assertSame($project->id, $task->project_id);
    }

    public function test_at_tag_token_assigns_tag_and_strips_token(): void
    {
        $tag = $this->createTag('urgent');

        $this->actingAs($this->user)->post('/tasks', [
            'name'      => 'Do something @urgent',
            'quick_add' => true,
        ]);

        $task = Task::where('creator_id', $this->user->id)->latest()->first();
        $this->assertSame('Do something', $task->name);
        $this->assertTrue($task->tags->contains($tag));
    }

    public function test_quick_add_parses_date_keyword_from_name(): void
    {
        $this->actingAs($this->user)->post('/tasks', [
            'name'       => 'Stand-up daily',
            'project_id' => $this->project->id,
            'quick_add'  => true,
        ]);

        $task = Task::where('creator_id', $this->user->id)->latest()->first();
        $this->assertSame('Stand-up', $task->name);
        $this->assertSame('daily', $task->recurrence_pattern);
        $this->assertNotNull($task->date);
    }

    public function test_quick_add_rejects_unrecognized_recurrence_in_name(): void
    {
        $response = $this->actingAs($this->user)->post('/tasks', [
            'name'       => 'Gym every weeks',
            'project_id' => $this->project->id,
            'quick_add'  => true,
        ]);

        $response->assertSessionHasErrors('name');
        $this->assertDatabaseMissing('tasks', ['creator_id' => $this->user->id]);
    }

    public function test_quick_add_redirects_back_not_to_show(): void
    {
        $response = $this->actingAs($this->user)->post('/tasks', [
            'name'       => 'Quick task',
            'project_id' => $this->project->id,
            'quick_add'  => true,
        ]);

        // quick_add redirects back (302) rather than to the task show page
        $response->assertRedirect();
        $response->assertSessionHas('success');
    }

    public function test_normal_form_name_is_not_parsed_for_dates(): void
    {
        // Without quick_add, "daily" in the name should NOT be stripped or parsed.
        $this->storeTask(['name' => 'Check email daily']);

        $this->assertDatabaseHas('tasks', ['name' => 'Check email daily']);
    }

    // =========================================================================
    // Change log
    // =========================================================================

    public function test_creating_a_task_writes_a_change_log_entry(): void
    {
        $this->storeTask(['name' => 'Logged Task']);

        $task = Task::where('name', 'Logged Task')->firstOrFail();
        $this->assertDatabaseHas('change_logs', [
            'entity_type' => 'tasks',
            'entity_id'   => $task->id,
            'user_id'     => $this->user->id,
            'verb'        => 'created',
        ]);
    }
}
