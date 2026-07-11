<?php

namespace Tests\Feature;

use App\Models\ChangeLog;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Characterization tests for the task status lifecycle — completing,
 * archiving, and re-opening tasks via both update() (full form) and
 * updateField() (inline AJAX), including descendant cascades and
 * recurring-task rollover.
 */
class TaskLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->project = Project::create([
            'name'    => 'Lifecycle Project',
            'user_id' => $this->user->id,
            'status'  => 'incomplete',
        ]);
    }

    private function makeTask(array $attributes = []): Task
    {
        $task = Task::create(array_merge([
            'name'       => 'Lifecycle task',
            'creator_id' => $this->user->id,
            'project_id' => $this->project->id,
            'status'     => 'incomplete',
        ], $attributes));

        $task->assignments()->create([
            'assignee_id'    => $this->user->id,
            'assigned_by_id' => $this->user->id,
        ]);

        return $task;
    }

    private function updateTask(Task $task, array $data)
    {
        return $this->actingAs($this->user)
            ->put(route('tasks.update', $task), array_merge(['name' => $task->name], $data));
    }

    private function updateField(Task $task, string $field, $value, array $extra = [])
    {
        return $this->actingAs($this->user)
            ->post(route('tasks.updateField', $task), array_merge([
                'field' => $field,
                'value' => $value,
            ], $extra));
    }

    // ── update(): completing ────────────────────────────────────────────────

    public function test_update_marking_done_sets_completed_at_and_logs_completed_verb(): void
    {
        $task = $this->makeTask();

        $this->updateTask($task, ['status' => 'done'])->assertRedirect();

        $task->refresh();
        $this->assertSame('done', $task->status);
        $this->assertNotNull($task->completed_at);
        $this->assertTrue(
            ChangeLog::where('entity_type', 'tasks')
                ->where('entity_id', $task->id)
                ->where('verb', 'completed')
                ->exists()
        );
    }

    public function test_update_marking_done_completes_incomplete_descendants(): void
    {
        $parent = $this->makeTask(['name' => 'Parent']);
        $child  = $this->makeTask(['name' => 'Child', 'parent_id' => $parent->id]);

        $this->updateTask($parent, ['status' => 'done']);

        $this->assertSame('done', $parent->refresh()->status);
        $this->assertSame('done', $child->refresh()->status);
        $this->assertNotNull($child->completed_at);
    }

    public function test_update_marking_incomplete_clears_completed_at(): void
    {
        $task = $this->makeTask(['status' => 'done', 'completed_at' => now()]);

        $this->updateTask($task, ['status' => 'incomplete']);

        $task->refresh();
        $this->assertSame('incomplete', $task->status);
        $this->assertNull($task->completed_at);
    }

    // ── update(): archiving ─────────────────────────────────────────────────

    public function test_update_archiving_cascades_to_descendants(): void
    {
        $parent = $this->makeTask(['name' => 'Parent']);
        $child  = $this->makeTask(['name' => 'Child', 'parent_id' => $parent->id]);

        $this->updateTask($parent, ['status' => 'archived']);

        $this->assertSame('archived', $parent->refresh()->status);
        $this->assertSame('archived', $child->refresh()->status);
        $this->assertNotNull($parent->completed_at);
    }

    // ── update(): recurring rollover ────────────────────────────────────────

    public function test_update_marking_recurring_done_creates_next_instance(): void
    {
        $tag  = Tag::create(['tag_name' => 'recurring', 'color' => '#ff0000']);
        $task = $this->makeTask([
            'name'               => 'Daily standup',
            'date'               => today()->format('Y-m-d'),
            'recurrence_pattern' => 'daily',
        ]);
        $task->tags()->sync([$tag->id]);

        $this->updateTask($task, ['status' => 'done']);

        $next = Task::where('name', 'Daily standup')
            ->where('status', 'incomplete')
            ->first();

        $this->assertNotNull($next, 'A next occurrence should have been created.');
        $this->assertSame(today()->addDay()->format('Y-m-d'), $next->date);
        $this->assertSame('daily', $next->recurrence_pattern);
        $this->assertSame([$tag->id], $next->tags->pluck('id')->all());
        $this->assertSame([$this->user->id], $next->assignments->pluck('assignee_id')->all());
    }

    public function test_update_recurring_does_not_duplicate_existing_next_instance(): void
    {
        $task = $this->makeTask([
            'name'               => 'Daily standup',
            'date'               => today()->format('Y-m-d'),
            'recurrence_pattern' => 'daily',
        ]);
        $this->makeTask([
            'name'               => 'Daily standup',
            'date'               => today()->addDay()->format('Y-m-d'),
            'recurrence_pattern' => 'daily',
        ]);

        $this->updateTask($task, ['status' => 'done']);

        $this->assertSame(1, Task::where('name', 'Daily standup')
            ->where('date', today()->addDay()->format('Y-m-d'))
            ->count());
    }

    public function test_update_recurring_stops_at_end_date(): void
    {
        $task = $this->makeTask([
            'name'                => 'Ends today',
            'date'                => today()->format('Y-m-d'),
            'recurrence_pattern'  => 'daily',
            'recurrence_end_date' => today()->format('Y-m-d'),
        ]);

        $this->updateTask($task, ['status' => 'done']);

        $this->assertSame(0, Task::where('name', 'Ends today')
            ->where('status', 'incomplete')
            ->count());
    }

    // ── update(): quick-complete ────────────────────────────────────────────

    public function test_quick_complete_only_changes_status_ignoring_stale_fields(): void
    {
        $task = $this->makeTask([
            'name' => 'Real name',
            'date' => today()->format('Y-m-d'),
        ]);

        // Quick-complete resubmits a snapshot of the row; those hidden fields
        // may be stale and must not overwrite the task's current values.
        $this->actingAs($this->user)->put(route('tasks.update', $task), [
            'name'           => 'Stale name',
            'date'           => today()->subDays(3)->format('Y-m-d'),
            'status'         => 'done',
            'quick_complete' => 1,
        ]);

        $task->refresh();
        $this->assertSame('done', $task->status);
        $this->assertSame('Real name', $task->name);
        $this->assertSame(today()->format('Y-m-d'), $task->date);
    }

    // ── updateField(): status changes ───────────────────────────────────────

    public function test_update_field_done_sets_completed_at_and_returns_next_recurring_id(): void
    {
        $task = $this->makeTask([
            'name'               => 'Daily standup',
            'date'               => today()->format('Y-m-d'),
            'recurrence_pattern' => 'daily',
        ]);

        $response = $this->updateField($task, 'status', 'done');

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertNotNull($response->json('next_task_id'));

        $task->refresh();
        $this->assertSame('done', $task->status);
        $this->assertNotNull($task->completed_at);

        $next = Task::find($response->json('next_task_id'));
        $this->assertSame(today()->addDay()->format('Y-m-d'), $next->date);
    }

    public function test_update_field_done_with_descendants_completes_all_and_flags_reload(): void
    {
        $parent = $this->makeTask(['name' => 'Parent']);
        $child  = $this->makeTask(['name' => 'Child', 'parent_id' => $parent->id]);

        $response = $this->updateField($parent, 'status', 'done');

        $response->assertOk()->assertJson(['success' => true, 'reload' => true]);
        $this->assertSame('done', $parent->refresh()->status);
        $this->assertSame('done', $child->refresh()->status);
    }

    public function test_update_field_archived_cascades_to_descendants(): void
    {
        $parent = $this->makeTask(['name' => 'Parent']);
        $child  = $this->makeTask(['name' => 'Child', 'parent_id' => $parent->id]);

        $this->updateField($parent, 'status', 'archived')->assertOk();

        $this->assertSame('archived', $parent->refresh()->status);
        $this->assertSame('archived', $child->refresh()->status);
    }

    public function test_update_field_reopen_can_archive_next_occurrence(): void
    {
        $done = $this->makeTask([
            'name'               => 'Daily standup',
            'date'               => today()->format('Y-m-d'),
            'recurrence_pattern' => 'daily',
            'status'             => 'done',
            'completed_at'       => now(),
        ]);
        $next = $this->makeTask([
            'name'               => 'Daily standup',
            'date'               => today()->addDay()->format('Y-m-d'),
            'recurrence_pattern' => 'daily',
        ]);

        $this->updateField($done, 'status', 'incomplete', [
            'next_occurrence_action' => 'archive',
        ])->assertOk();

        $this->assertSame('incomplete', $done->refresh()->status);
        $this->assertNull($done->completed_at);
        $this->assertSame('archived', $next->refresh()->status);
    }

    public function test_update_field_rejects_invalid_status(): void
    {
        $task = $this->makeTask();

        $this->updateField($task, 'status', 'bogus')->assertStatus(400);
        $this->assertSame('incomplete', $task->refresh()->status);
    }
}
