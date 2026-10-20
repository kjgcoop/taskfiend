<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The task side panel explains, above the task name, why a task the viewer
 * isn't assigned to is in front of them, and links to the setting.
 */
class NotAssignedNoticeTest extends TestCase
{
    use RefreshDatabase;

    private const MARK = 'data-not-assigned-notice';

    private User $owner;
    private User $member;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner  = User::factory()->create();
        $this->member = User::factory()->create();
        foreach ([$this->owner, $this->member] as $user) {
            Project::create(['name' => 'Default', 'user_id' => $user->id, 'status' => 'incomplete', 'is_default' => true]);
        }

        $this->project = Project::create(['name' => 'Shared', 'user_id' => $this->owner->id, 'status' => 'incomplete']);
        $this->project->assignees()->sync([$this->member->id]);
    }

    private function makeTask(array $assigneeIds = []): Task
    {
        $task = Task::create([
            'name'       => 'Panel Zebra Task',
            'creator_id' => $this->owner->id,
            'project_id' => $this->project->id,
            'status'     => 'incomplete',
        ]);
        $task->assignWithCreator($assigneeIds);

        return $task;
    }

    public function test_notice_is_shown_for_a_task_visible_only_through_the_project(): void
    {
        $task = $this->makeTask();

        $this->actingAs($this->member)->get(route('tasks.panel', $task))
            ->assertOk()
            ->assertSee(self::MARK, false)
            ->assertSee('This task is not assigned to you directly')
            ->assertSee(route('profile.edit') . '#day-preferences', false);
    }

    public function test_wording_follows_the_users_day_preference(): void
    {
        $task = $this->makeTask();

        $this->actingAs($this->member)->get(route('tasks.panel', $task))
            ->assertSee('To show tasks like this on your day')
            ->assertDontSee('To hide tasks that');

        $this->member->update(['show_project_tasks_on_day' => true]);

        $this->actingAs($this->member)->get(route('tasks.panel', $task))
            ->assertSee('To hide tasks that aren')
            ->assertDontSee('To show tasks like this on your day');
    }

    public function test_notice_is_absent_for_a_task_assigned_to_the_viewer(): void
    {
        $task = $this->makeTask([$this->member->id]);

        $this->actingAs($this->member)->get(route('tasks.panel', $task))
            ->assertOk()
            ->assertDontSee(self::MARK, false);
    }

    public function test_notice_is_absent_for_the_creator(): void
    {
        $task = $this->makeTask();

        $this->actingAs($this->owner)->get(route('tasks.panel', $task))
            ->assertOk()
            ->assertDontSee(self::MARK, false);
    }

    public function test_profile_page_has_the_anchor_the_notice_links_to(): void
    {
        $this->actingAs($this->member)->get(route('profile.edit'))
            ->assertSee('id="day-preferences"', false);
    }
}
