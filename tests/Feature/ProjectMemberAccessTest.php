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
 * End-to-end coverage for the access/plate split: project members can SEE every
 * task in their project (Task::visibleTo) wherever tasks are looked up, but only
 * the user's own plate (Task::assignedTo / onDayOf) fills their lists.
 */
class ProjectMemberAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $member;
    private User $stranger;
    private Project $project;
    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner    = User::factory()->create();
        $this->member   = User::factory()->create();
        $this->stranger = User::factory()->create();
        foreach ([$this->owner, $this->member, $this->stranger] as $user) {
            Project::create(['name' => 'Default', 'user_id' => $user->id, 'status' => 'incomplete', 'is_default' => true]);
        }

        $this->project = Project::create(['name' => 'Shared', 'user_id' => $this->owner->id, 'status' => 'incomplete']);
        $this->project->assignees()->sync([$this->member->id]);
        $this->today = today()->format('Y-m-d');
    }

    private function makeTask(string $name, ?string $date = null, array $extra = []): Task
    {
        $task = Task::create(array_merge([
            'name'       => $name,
            'creator_id' => $this->owner->id,
            'project_id' => $this->project->id,
            'status'     => 'incomplete',
            'date'       => $date,
        ], $extra));
        $task->assignWithCreator([]);

        return $task;
    }

    // ── Access: project members can see and act on every task in the project ─────

    public function test_search_finds_an_unassigned_task_in_a_project_the_user_belongs_to(): void
    {
        $this->makeTask('Findable Zebra Task', null, ['description' => 'needlephrase']);
        $query = ['q' => 'needlephrase', 'search_title' => '1', 'search_description' => '1', 'show_incomplete' => '1'];

        $this->actingAs($this->member)->get(route('search', $query))
            ->assertOk()->assertSee('Findable Zebra Task');

        $this->actingAs($this->stranger)->get(route('search', $query))
            ->assertOk()->assertDontSee('Findable Zebra Task');
    }

    public function test_tag_page_lists_tagged_tasks_in_a_project_the_user_belongs_to(): void
    {
        $tag  = Tag::create(['tag_name' => 'zebra-tag', 'color' => '#ff5722']);
        $task = $this->makeTask('Tagged Zebra Task');
        $task->tags()->sync([$tag->id]);

        $this->actingAs($this->member)->get(route('tags.show', $tag))
            ->assertOk()->assertSee('Tagged Zebra Task');

        $this->actingAs($this->stranger)->get(route('tags.show', $tag))
            ->assertOk()->assertDontSee('Tagged Zebra Task');
    }

    public function test_project_member_can_reorder_tasks_they_are_not_assigned_to_but_a_stranger_cannot(): void
    {
        $task = $this->makeTask('Reorder Zebra Task');

        $this->actingAs($this->stranger)->postJson(route('tasks.reorder'), ['ids' => [$task->id]])->assertOk();
        $this->assertNull($task->fresh()->sort_order);

        $this->actingAs($this->member)->postJson(route('tasks.reorder'), ['ids' => [$task->id]])->assertOk();
        $this->assertSame(0, (int) $task->fresh()->sort_order);
    }

    public function test_project_member_can_bulk_update_a_task_they_are_not_assigned_to_but_a_stranger_cannot(): void
    {
        $task = $this->makeTask('Bulk Zebra Task', $this->today);
        $newDate = today()->addDays(3)->format('Y-m-d');

        $this->actingAs($this->stranger)->postJson('/tasks/bulk-update', ['task_ids' => [$task->id], 'date' => $newDate]);
        $this->assertSame($this->today, $task->fresh()->getRawOriginal('date'));

        $this->actingAs($this->member)->postJson('/tasks/bulk-update', ['task_ids' => [$task->id], 'date' => $newDate])->assertOk();
        $this->assertSame($newDate, $task->fresh()->getRawOriginal('date'));
    }

    // ── Plate: a member's own lists never fill up with the rest of the project ────

    public function test_overdue_undated_all_and_calendar_ignore_project_membership_even_with_the_day_setting_on(): void
    {
        $this->member->update(['show_project_tasks_on_day' => true]);
        $this->makeTask('Overdue Zebra Task', today()->subDays(2)->format('Y-m-d'));
        $this->makeTask('Undated Zebra Task');
        $this->makeTask('Dated Zebra Task', $this->today);

        foreach (['overdue', 'undated', 'all-tasks', 'calendar'] as $route) {
            $html = $this->actingAs($this->member)->get(route($route))->assertOk()->getContent();
            foreach (['Overdue Zebra Task', 'Undated Zebra Task', 'Dated Zebra Task'] as $name) {
                $this->assertStringNotContainsString($name, $html, "{$route} must not list {$name} for a non-assignee");
            }
        }
    }

    // ── The day setting reaches the day exports and the day API ─────────────────

    public function test_day_markdown_export_follows_the_day_setting(): void
    {
        $this->makeTask('Exported Zebra Task', $this->today);
        $url = route('day.export-markdown', ['date' => $this->today]);

        $this->actingAs($this->member)->get($url)->assertOk()->assertDontSee('Exported Zebra Task', false);

        $this->member->update(['show_project_tasks_on_day' => true]);

        $this->actingAs($this->member)->get($url)->assertOk()->assertSee('Exported Zebra Task', false);
    }

    public function test_day_api_follows_the_day_setting_for_open_and_completed_tasks(): void
    {
        $plainKey = 'tfk_' . Str::random(40);
        ApiKey::create([
            'user_id'        => $this->member->id,
            'key_hash'       => Hash::make($plainKey),
            'key_prefix'     => substr($plainKey, 0, 12),
            'invalidated_at' => null,
        ]);
        $open = $this->makeTask('Api Open Zebra Task', $this->today);
        $done = $this->makeTask('Api Done Zebra Task', $this->today, ['status' => 'done', 'completed_at' => now()]);

        $onDay     = fn () => $this->withToken($plainKey)->getJson("/api/tasks/on/{$this->today}")->assertOk()->getContent();
        $completed = fn () => $this->withToken($plainKey)->getJson("/api/tasks/completed/{$this->today}")->assertOk()->getContent();

        $this->assertStringNotContainsString('Api Open Zebra Task', $onDay());
        $this->assertStringNotContainsString('Api Done Zebra Task', $completed());

        $this->member->update(['show_project_tasks_on_day' => true]);

        $this->assertStringContainsString('Api Open Zebra Task', $onDay());
        $this->assertStringContainsString('Api Done Zebra Task', $completed());
    }
}
