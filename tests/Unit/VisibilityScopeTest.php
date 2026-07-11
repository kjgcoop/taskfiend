<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the shared visibility query scopes:
 *   Task::visibleTo($userId)   — tasks the user created or is assigned to
 *   Project::forMember($userId) — projects the user owns or is assigned to
 */
class VisibilityScopeTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $assignee;
    private User $stranger;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner    = User::factory()->create();
        $this->assignee = User::factory()->create();
        $this->stranger = User::factory()->create();

        $this->project = Project::create([
            'name'    => 'Scope Test Project',
            'user_id' => $this->owner->id,
            'status'  => 'incomplete',
        ]);
    }

    private function makeTask(string $name): Task
    {
        return Task::create([
            'name'       => $name,
            'creator_id' => $this->owner->id,
            'project_id' => $this->project->id,
            'status'     => 'incomplete',
        ]);
    }

    public function test_visible_to_includes_tasks_created_by_user(): void
    {
        $task = $this->makeTask('Created by owner');

        $this->assertTrue(Task::visibleTo($this->owner->id)->pluck('id')->contains($task->id));
    }

    public function test_visible_to_includes_tasks_assigned_to_user(): void
    {
        $task = $this->makeTask('Assigned to assignee');
        $task->assignments()->create([
            'assignee_id'    => $this->assignee->id,
            'assigned_by_id' => $this->owner->id,
        ]);

        $this->assertTrue(Task::visibleTo($this->assignee->id)->pluck('id')->contains($task->id));
    }

    public function test_visible_to_excludes_unrelated_tasks(): void
    {
        $task = $this->makeTask('Private to owner');

        $this->assertFalse(Task::visibleTo($this->stranger->id)->pluck('id')->contains($task->id));
    }

    public function test_visible_to_groups_conditions_so_other_filters_still_apply(): void
    {
        // A done task belonging to someone else must not leak through when the
        // scope is combined with an OR-prone status filter — the scope's
        // creator/assignee pair has to stay inside its own WHERE group.
        $foreign = Task::create([
            'name'       => 'Foreign done task',
            'creator_id' => $this->stranger->id,
            'project_id' => $this->project->id,
            'status'     => 'done',
        ]);

        $visible = Task::visibleTo($this->owner->id)
            ->where('status', 'done')
            ->pluck('id');

        $this->assertFalse($visible->contains($foreign->id));
    }

    public function test_visible_to_works_through_belongs_to_many_relations(): void
    {
        // TagController and ChangeLogController call the scope on
        // $tag->tasks(), which joins tag_task — creator_id must not be
        // ambiguous there.
        $tag  = \App\Models\Tag::create(['tag_name' => 'scope-test', 'color' => '#ff0000']);
        $task = $this->makeTask('Tagged task');
        $task->tags()->sync([$tag->id]);

        $this->assertTrue($tag->tasks()->visibleTo($this->owner->id)->pluck('tasks.id')->contains($task->id));
        $this->assertFalse($tag->tasks()->visibleTo($this->stranger->id)->pluck('tasks.id')->contains($task->id));
    }

    public function test_for_member_includes_owned_projects(): void
    {
        $this->assertTrue(Project::forMember($this->owner->id)->pluck('id')->contains($this->project->id));
    }

    public function test_for_member_includes_assigned_projects(): void
    {
        $this->project->assignees()->sync([$this->assignee->id]);

        $this->assertTrue(Project::forMember($this->assignee->id)->pluck('id')->contains($this->project->id));
    }

    public function test_for_member_excludes_task_level_assignees(): void
    {
        // Being assigned a task inside a project grants visibility
        // (activeForUser) but NOT membership — forMember is the stricter
        // rule used when creating/moving tasks into a project.
        $task = $this->makeTask('Task for stranger');
        $task->assignments()->create([
            'assignee_id'    => $this->stranger->id,
            'assigned_by_id' => $this->owner->id,
        ]);

        $this->assertFalse(Project::forMember($this->stranger->id)->pluck('id')->contains($this->project->id));
        $this->assertTrue(Project::activeForUser($this->stranger->id)->pluck('id')->contains($this->project->id));
    }
}
