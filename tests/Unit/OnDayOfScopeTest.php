<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Task::onDayOf($user): the plate (assignedTo), plus dated tasks in the user's
 * projects when they've turned on show_project_tasks_on_day.
 */
class OnDayOfScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $member;
    private User $stranger;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner    = User::factory()->create();
        $this->member   = User::factory()->create();
        $this->stranger = User::factory()->create();

        $this->project = Project::create([
            'name'    => 'Shared',
            'user_id' => $this->owner->id,
            'status'  => 'incomplete',
        ]);
        $this->project->assignees()->sync([$this->member->id]);
    }

    private function makeTask(string $name, ?string $date, array $assigneeIds = [], ?Project $project = null): Task
    {
        $task = Task::create([
            'name'       => $name,
            'creator_id' => $this->owner->id,
            'project_id' => ($project ?? $this->project)->id,
            'status'     => 'incomplete',
            'date'       => $date,
        ]);
        $task->assignWithCreator($assigneeIds);

        return $task;
    }

    private function ids(User $user)
    {
        return Task::onDayOf($user->fresh())->pluck('id');
    }

    public function test_setting_defaults_to_off(): void
    {
        $this->assertFalse($this->member->fresh()->show_project_tasks_on_day);
    }

    public function test_off_returns_only_the_plate(): void
    {
        $watched  = $this->makeTask('Watched', '2026-10-20');
        $assigned = $this->makeTask('Assigned', '2026-10-20', [$this->member->id]);

        $ids = $this->ids($this->member);

        $this->assertTrue($ids->contains($assigned->id));
        $this->assertFalse($ids->contains($watched->id));
    }

    public function test_on_adds_dated_tasks_from_projects_the_user_belongs_to(): void
    {
        $this->member->update(['show_project_tasks_on_day' => true]);
        $watched  = $this->makeTask('Watched', '2026-10-20');
        $assigned = $this->makeTask('Assigned', '2026-10-20', [$this->member->id]);

        $ids = $this->ids($this->member);

        $this->assertTrue($ids->contains($watched->id));
        $this->assertTrue($ids->contains($assigned->id));
    }

    public function test_on_still_excludes_undated_project_tasks(): void
    {
        $this->member->update(['show_project_tasks_on_day' => true]);
        $undated = $this->makeTask('Undated', null);

        $this->assertFalse($this->ids($this->member)->contains($undated->id));
    }

    public function test_on_does_not_reach_projects_the_user_does_not_belong_to(): void
    {
        $this->member->update(['show_project_tasks_on_day' => true]);
        $private = Project::create(['name' => 'Private', 'user_id' => $this->owner->id, 'status' => 'incomplete']);
        $hidden  = $this->makeTask('Hidden', '2026-10-20', [], $private);

        $this->assertFalse($this->ids($this->member)->contains($hidden->id));
    }

    public function test_on_does_not_help_a_stranger(): void
    {
        $this->stranger->update(['show_project_tasks_on_day' => true]);
        $task = $this->makeTask('Not theirs', '2026-10-20');

        $this->assertFalse($this->ids($this->stranger)->contains($task->id));
    }

    public function test_conditions_stay_grouped_so_other_filters_still_apply(): void
    {
        $this->member->update(['show_project_tasks_on_day' => true]);
        $watched = $this->makeTask('Watched', '2026-10-20');
        $other   = $this->makeTask('Other day', '2026-10-21');

        $ids = Task::onDayOf($this->member->fresh())->where('date', '2026-10-20')->pluck('id');

        $this->assertTrue($ids->contains($watched->id));
        $this->assertFalse($ids->contains($other->id));
    }
}
