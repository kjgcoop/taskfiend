<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "Not Assigned to You" marker: shown on /day only for tasks that are
 * there because of the "show project tasks on my day" setting.
 */
class NotAssignedBadgeTest extends TestCase
{
    use RefreshDatabase;

    private const MARK = 'data-not-assigned-badge';

    private User $owner;
    private User $member;
    private Project $project;
    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner  = User::factory()->create();
        $this->member = User::factory()->create(['show_project_tasks_on_day' => true]);
        foreach ([$this->owner, $this->member] as $user) {
            Project::create(['name' => 'Default', 'user_id' => $user->id, 'status' => 'incomplete', 'is_default' => true]);
        }

        $this->project = Project::create(['name' => 'Shared', 'user_id' => $this->owner->id, 'status' => 'incomplete']);
        $this->project->assignees()->sync([$this->member->id]);
        $this->today = today()->format('Y-m-d');
    }

    private function makeTask(string $name, string $date, array $assigneeIds = [], string $status = 'incomplete'): Task
    {
        $task = Task::create([
            'name'         => $name,
            'creator_id'   => $this->owner->id,
            'project_id'   => $this->project->id,
            'status'       => $status,
            'date'         => $date,
            'completed_at' => $status === 'done' ? now() : null,
        ]);
        $task->assignWithCreator($assigneeIds);

        return $task;
    }

    public function test_is_on_plate_of_covers_creator_assignee_and_neither(): void
    {
        $task = $this->makeTask('Plate', $this->today, [$this->member->id]);
        $other = User::factory()->create();

        $this->assertTrue($task->fresh()->isOnPlateOf($this->owner->id));
        $this->assertTrue($task->fresh()->isOnPlateOf($this->member->id));
        $this->assertFalse($task->fresh()->isOnPlateOf($other->id));
    }

    public function test_badge_marks_a_task_that_is_only_visible_through_the_project(): void
    {
        $this->makeTask('Watched Zebra Task', $this->today);

        $this->actingAs($this->member)->get(route('day', ['date' => $this->today]))
            ->assertOk()
            ->assertSee('Watched Zebra Task')
            ->assertSee(self::MARK, false)
            ->assertSee('Not Assigned to You');
    }

    public function test_badge_is_absent_for_a_task_assigned_to_the_viewer(): void
    {
        $this->makeTask('Mine Zebra Task', $this->today, [$this->member->id]);

        $this->actingAs($this->member)->get(route('day', ['date' => $this->today]))
            ->assertOk()
            ->assertSee('Mine Zebra Task')
            ->assertDontSee(self::MARK, false);
    }

    public function test_badge_is_absent_for_the_owner_of_the_task(): void
    {
        $this->makeTask('Owned Zebra Task', $this->today);

        $this->actingAs($this->owner)->get(route('day', ['date' => $this->today]))
            ->assertOk()
            ->assertSee('Owned Zebra Task')
            ->assertDontSee(self::MARK, false);
    }

    public function test_badge_marks_watched_tasks_in_the_done_group_and_its_paged_loading(): void
    {
        $this->makeTask('Watched Done Task', $this->today, [], 'done');

        $this->actingAs($this->member)->get(route('day', ['date' => $this->today]))
            ->assertSee(self::MARK, false);

        $html = $this->actingAs($this->member)
            ->getJson(route('day.completedTasks', ['date' => $this->today]))
            ->assertOk()
            ->json('html');
        $this->assertStringContainsString(self::MARK, $html);
    }

    public function test_badge_marks_watched_tasks_on_a_past_day(): void
    {
        $yesterday = today()->subDay()->format('Y-m-d');
        $this->makeTask('Watched Past Task', $yesterday);

        $this->actingAs($this->member)->get(route('day', ['date' => $yesterday]))
            ->assertOk()
            ->assertSee('Watched Past Task')
            ->assertSee(self::MARK, false);
    }

    public function test_badge_does_not_appear_outside_the_day_page(): void
    {
        $task = $this->makeTask('Member Visible Task', $this->today, [$this->member->id]);
        $task->update(['date' => null]);

        $this->actingAs($this->member)->get(route('undated'))
            ->assertOk()
            ->assertDontSee(self::MARK, false);

        $this->actingAs($this->member)->get(route('projects.show', $this->project))
            ->assertOk()
            ->assertDontSee(self::MARK, false);
    }
}
