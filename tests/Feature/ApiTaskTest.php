<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for the three API task endpoints:
 *   POST   /api/tasks                      TaskApiController::create()
 *   GET    /api/tasks/completed/{date}     TaskApiController::completedOnDay()
 *   GET    /api/tasks/on/{date}            TaskApiController::onDay()
 *
 * Authentication is handled by the AuthenticateApiKey middleware (Bearer token).
 */
class ApiTaskTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    /** The raw (unhashed) API key — sent as Bearer token in requests. */
    private string $plainKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(); // email_enabled_at = null → enabled

        $this->plainKey = 'tfk_' . Str::random(40);

        ApiKey::create([
            'user_id'        => $this->user->id,
            'key_hash'       => Hash::make($this->plainKey),
            'key_prefix'     => substr($this->plainKey, 0, 12),
            'invalidated_at' => null,
        ]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Authenticated POST /api/tasks. */
    private function apiPost(array $data = [])
    {
        return $this->withToken($this->plainKey)->postJson('/api/tasks', $data);
    }

    /** Authenticated GET /api/tasks/on/{date}. */
    private function apiOnDay(string $date)
    {
        return $this->withToken($this->plainKey)->getJson("/api/tasks/on/{$date}");
    }

    /** Authenticated GET /api/tasks/completed/{date}. */
    private function apiCompletedOnDay(string $date)
    {
        return $this->withToken($this->plainKey)->getJson("/api/tasks/completed/{$date}");
    }

    /** Create an inbox project for the test user. */
    private function createInbox(): Project
    {
        return Project::create([
            'name'     => 'Inbox',
            'user_id'  => $this->user->id,
            'is_inbox' => true,
            'status'   => 'incomplete',
        ]);
    }

    /** Create a task owned by (and assigned to) $this->user. */
    private function createOwnedTask(array $overrides = []): Task
    {
        $project = Project::create([
            'name'    => 'Test Project',
            'user_id' => $this->user->id,
            'status'  => 'incomplete',
        ]);

        $task = Task::create(array_merge([
            'name'       => 'Test Task',
            'creator_id' => $this->user->id,
            'project_id' => $project->id,
            'status'     => 'incomplete',
        ], $overrides));

        $task->assignments()->create([
            'assignee_id'    => $this->user->id,
            'assigned_by_id' => $this->user->id,
        ]);

        return $task;
    }

    // =========================================================================
    // Authentication middleware
    // =========================================================================

    public function test_request_without_token_is_rejected(): void
    {
        $response = $this->postJson('/api/tasks', ['name' => 'Test']);

        $response->assertUnauthorized()
                 ->assertJson(['success' => false]);
    }

    public function test_request_with_invalid_token_is_rejected(): void
    {
        $response = $this->withToken('tfk_badtoken')->postJson('/api/tasks', ['name' => 'Test']);

        $response->assertUnauthorized()
                 ->assertJson(['success' => false]);
    }

    public function test_request_with_invalidated_key_is_rejected(): void
    {
        ApiKey::where('user_id', $this->user->id)->update(['invalidated_at' => now()]);

        $response = $this->apiPost(['name' => 'Test']);

        $response->assertUnauthorized();
    }

    public function test_request_from_disabled_user_is_rejected(): void
    {
        $this->user->update(['email_enabled_at' => now()]);

        $response = $this->apiPost(['name' => 'Test']);

        $response->assertForbidden()
                 ->assertJson(['success' => false]);
    }

    // =========================================================================
    // POST /api/tasks — validation
    // =========================================================================

    public function test_create_requires_name(): void
    {
        $response = $this->apiPost([]);

        $response->assertUnprocessable();
    }

    public function test_create_rejects_invalid_date_format(): void
    {
        $response = $this->apiPost(['name' => 'Task', 'date' => '26/03/2026']);

        $response->assertUnprocessable();
    }

    public function test_create_rejects_invalid_recurrence_pattern(): void
    {
        $response = $this->apiPost([
            'name'               => 'Task',
            'recurrence_pattern' => 'every fortnight',
        ]);

        $response->assertStatus(422)
                 ->assertJson(['success' => false]);
    }

    public function test_create_rejects_unrecognized_recurrence_in_name(): void
    {
        $response = $this->apiPost(['name' => 'Gym every biweekly']);

        $response->assertStatus(422)
                 ->assertJson(['success' => false]);
    }

    // =========================================================================
    // POST /api/tasks — successful creation
    // =========================================================================

    public function test_create_returns_201_with_task(): void
    {
        $this->createInbox();

        $response = $this->apiPost(['name' => 'My API Task']);

        $response->assertCreated()
                 ->assertJson(['success' => true])
                 ->assertJsonPath('task.name', 'My API Task');
    }

    public function test_create_stores_task_in_database(): void
    {
        $this->createInbox();

        $this->apiPost(['name' => 'Stored Task']);

        $this->assertDatabaseHas('tasks', [
            'name'       => 'Stored Task',
            'creator_id' => $this->user->id,
            'status'     => 'incomplete',
        ]);
    }

    public function test_create_auto_assigns_creator_when_no_assignees_given(): void
    {
        $this->createInbox();

        $this->apiPost(['name' => 'Self Assigned']);

        $task = Task::where('name', 'Self Assigned')->firstOrFail();
        $this->assertTrue($task->assignees->contains($this->user));
    }

    public function test_create_uses_explicit_project_id(): void
    {
        $project = Project::create([
            'name'    => 'Explicit Project',
            'user_id' => $this->user->id,
            'status'  => 'incomplete',
        ]);

        $this->apiPost(['name' => 'Proj Task', 'project_id' => $project->id]);

        $this->assertDatabaseHas('tasks', [
            'name'       => 'Proj Task',
            'project_id' => $project->id,
        ]);
    }

    public function test_create_falls_back_to_inbox_project(): void
    {
        $inbox = $this->createInbox();

        $this->apiPost(['name' => 'Inbox Task']);

        $this->assertDatabaseHas('tasks', [
            'name'       => 'Inbox Task',
            'project_id' => $inbox->id,
        ]);
    }

    public function test_create_parses_natural_language_date_from_name(): void
    {
        $this->createInbox();

        $response = $this->apiPost(['name' => 'Morning run daily']);

        $response->assertCreated();
        $task = Task::where('creator_id', $this->user->id)->latest()->first();
        $this->assertSame('Morning run', $task->name);
        $this->assertSame('daily', $task->recurrence_pattern);
        $this->assertNotNull($task->date);
    }

    public function test_create_explicit_date_is_not_overridden_by_name_parsing(): void
    {
        $this->createInbox();

        $this->apiPost(['name' => 'Stand-up daily', 'date' => '2026-07-01']);

        $task = Task::where('creator_id', $this->user->id)->latest()->first();
        $this->assertSame('2026-07-01', $task->date);
    }

    public function test_create_stores_tags(): void
    {
        $this->createInbox();
        $tag = Tag::create(['tag_name' => 'work', 'color' => '#0000ff']);

        $this->apiPost(['name' => 'Tagged Task', 'tag_ids' => [$tag->id]]);

        $task = Task::where('name', 'Tagged Task')->firstOrFail();
        $this->assertTrue($task->tags->contains($tag));
    }

    public function test_create_stores_explicit_assignees(): void
    {
        $this->createInbox();
        $other = User::factory()->create();

        $this->apiPost(['name' => 'Assigned Task', 'assignee_ids' => [$other->id]]);

        $task = Task::where('name', 'Assigned Task')->firstOrFail();
        $this->assertTrue($task->assignees->contains($other));
        $this->assertFalse($task->assignees->contains($this->user));
    }

    public function test_create_stores_recurrence_pattern(): void
    {
        $this->createInbox();

        $this->apiPost(['name' => 'Recurring Task', 'recurrence_pattern' => 'weekly']);

        $this->assertDatabaseHas('tasks', [
            'name'               => 'Recurring Task',
            'recurrence_pattern' => 'weekly',
        ]);
    }

    public function test_create_stores_floating_recurrence_flag(): void
    {
        $this->createInbox();

        $this->apiPost([
            'name'                => 'Floating Task',
            'recurrence_pattern'  => 'daily',
            'recurrence_floating' => true,
        ]);

        $this->assertDatabaseHas('tasks', [
            'name'               => 'Floating Task',
            'recurrence_floating' => true,
        ]);
    }

    public function test_create_writes_change_log(): void
    {
        $this->createInbox();

        $this->apiPost(['name' => 'Logged API Task']);

        $task = Task::where('name', 'Logged API Task')->firstOrFail();
        $this->assertDatabaseHas('change_logs', [
            'entity_type' => 'tasks',
            'entity_id'   => $task->id,
            'user_id'     => $this->user->id,
        ]);
    }

    public function test_create_response_includes_related_data(): void
    {
        $this->createInbox();

        $response = $this->apiPost(['name' => 'Rich Task']);

        $response->assertCreated()
                 ->assertJsonStructure([
                     'task' => ['id', 'name', 'status', 'creator', 'assignees', 'tags'],
                 ]);
    }

    // =========================================================================
    // GET /api/tasks/on/{date}
    // =========================================================================

    public function test_on_day_returns_tasks_scheduled_for_date(): void
    {
        $task = $this->createOwnedTask(['date' => '2026-06-15', 'status' => 'incomplete']);

        $response = $this->apiOnDay('2026-06-15');

        $response->assertOk()
                 ->assertJson(['success' => true, 'date' => '2026-06-15'])
                 ->assertJsonPath('tasks.0.id', $task->id);
    }

    public function test_on_day_excludes_tasks_on_other_dates(): void
    {
        $this->createOwnedTask(['date' => '2026-06-20']);

        $response = $this->apiOnDay('2026-06-15');

        $response->assertOk()
                 ->assertJsonCount(0, 'tasks');
    }

    public function test_on_day_excludes_archived_tasks(): void
    {
        $this->createOwnedTask(['date' => '2026-06-15', 'status' => 'archived']);

        $response = $this->apiOnDay('2026-06-15');

        $response->assertOk()
                 ->assertJsonCount(0, 'tasks');
    }

    public function test_on_day_includes_done_tasks(): void
    {
        $this->createOwnedTask(['date' => '2026-06-15', 'status' => 'done']);

        $response = $this->apiOnDay('2026-06-15');

        $response->assertOk()
                 ->assertJsonCount(1, 'tasks');
    }

    public function test_on_day_excludes_tasks_belonging_to_other_users(): void
    {
        $other = User::factory()->create();
        $foreignProject = Project::create([
            'name'    => 'Foreign Project',
            'user_id' => $other->id,
            'status'  => 'incomplete',
        ]);
        Task::create([
            'name'       => 'Other Task',
            'creator_id' => $other->id,
            'project_id' => $foreignProject->id,
            'date'       => '2026-06-15',
            'status'     => 'incomplete',
        ]);

        $response = $this->apiOnDay('2026-06-15');

        $response->assertOk()
                 ->assertJsonCount(0, 'tasks');
    }

    public function test_on_day_returns_400_for_invalid_date_format(): void
    {
        $response = $this->apiOnDay('15-06-2026');

        $response->assertStatus(400)
                 ->assertJson(['success' => false]);
    }

    // =========================================================================
    // GET /api/tasks/completed/{date}
    // =========================================================================

    public function test_completed_on_day_returns_done_and_archived_tasks(): void
    {
        $doneTask     = $this->createOwnedTask(['status' => 'done']);
        $archivedTask = $this->createOwnedTask(['status' => 'archived']);

        // Use a raw query to set updated_at — Eloquent's update() overwrites it with now().
        \DB::table('tasks')->where('id', $doneTask->id)->update(['updated_at' => '2026-06-15 12:00:00']);
        \DB::table('tasks')->where('id', $archivedTask->id)->update(['updated_at' => '2026-06-15 12:00:00']);

        $response = $this->apiCompletedOnDay('2026-06-15');

        $response->assertOk()
                 ->assertJson(['success' => true, 'date' => '2026-06-15'])
                 ->assertJsonCount(1, 'done')
                 ->assertJsonCount(1, 'archived');
    }

    public function test_completed_on_day_excludes_tasks_updated_on_other_dates(): void
    {
        $task = $this->createOwnedTask(['status' => 'done']);
        \DB::table('tasks')->where('id', $task->id)->update(['updated_at' => '2026-06-20 12:00:00']);

        $response = $this->apiCompletedOnDay('2026-06-15');

        $response->assertOk()
                 ->assertJsonCount(0, 'done')
                 ->assertJsonCount(0, 'archived');
    }

    public function test_completed_on_day_excludes_other_users_tasks(): void
    {
        $other = User::factory()->create();
        $foreignProject = Project::create([
            'name'    => 'Foreign',
            'user_id' => $other->id,
            'status'  => 'incomplete',
        ]);
        $task = Task::create([
            'name'       => 'Other Done Task',
            'creator_id' => $other->id,
            'project_id' => $foreignProject->id,
            'status'     => 'done',
        ]);
        \DB::table('tasks')->where('id', $task->id)->update(['updated_at' => '2026-06-15 10:00:00']);

        $response = $this->apiCompletedOnDay('2026-06-15');

        $response->assertOk()
                 ->assertJsonCount(0, 'done');
    }

    public function test_completed_on_day_returns_400_for_invalid_date_format(): void
    {
        $response = $this->apiCompletedOnDay('not-a-date');

        $response->assertStatus(400)
                 ->assertJson(['success' => false]);
    }
}
