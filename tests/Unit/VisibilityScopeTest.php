<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the shared visibility query scopes:
 *   Task::visibleTo($userId)    — tasks the user can see: their own, assigned to them,
 *                                 or in a project they belong to
 *   Task::assignedTo($userId)   — tasks on the user's plate: created by or assigned to them
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
        // A done task belonging to someone else, in a project the stranger
        // does not share with the owner, must not leak through when the scope
        // is combined with an OR-prone status filter — the scope's
        // creator/assignee/member conditions have to stay inside their own
        // WHERE group.
        $strangerProject = Project::create([
            'name'    => 'Stranger Project',
            'user_id' => $this->stranger->id,
            'status'  => 'incomplete',
        ]);
        $foreign = Task::create([
            'name'       => 'Foreign done task',
            'creator_id' => $this->stranger->id,
            'project_id' => $strangerProject->id,
            'status'     => 'done',
        ]);

        $visible = Task::visibleTo($this->owner->id)
            ->where('status', 'done')
            ->pluck('id');

        $this->assertFalse($visible->contains($foreign->id));
    }

    public function test_visible_to_includes_tasks_in_projects_the_user_owns(): void
    {
        $task = Task::create([
            'name'       => 'Made by someone else, in the owner\'s project',
            'creator_id' => $this->stranger->id,
            'project_id' => $this->project->id,
            'status'     => 'incomplete',
        ]);

        $this->assertTrue(Task::visibleTo($this->owner->id)->pluck('id')->contains($task->id));
    }

    public function test_visible_to_includes_unassigned_tasks_in_projects_the_user_is_assigned_to(): void
    {
        $this->project->assignees()->sync([$this->assignee->id]);
        $task = $this->makeTask('Only the owner is on this task');

        $this->assertTrue(Task::visibleTo($this->assignee->id)->pluck('id')->contains($task->id));
        $this->assertFalse(Task::visibleTo($this->stranger->id)->pluck('id')->contains($task->id));
    }

    public function test_visible_to_does_not_grant_the_rest_of_a_project_via_a_single_assigned_task(): void
    {
        $mine   = $this->makeTask('Assigned to stranger');
        $theirs = $this->makeTask('Not assigned to stranger');
        $mine->assignments()->create([
            'assignee_id'    => $this->stranger->id,
            'assigned_by_id' => $this->owner->id,
        ]);

        $visible = Task::visibleTo($this->stranger->id)->pluck('id');

        $this->assertTrue($visible->contains($mine->id));
        $this->assertFalse($visible->contains($theirs->id));
    }

    public function test_assigned_to_includes_created_and_assigned_tasks_only(): void
    {
        $created  = $this->makeTask('Created by owner');
        $assigned = $this->makeTask('Assigned to assignee');
        $assigned->assignments()->create([
            'assignee_id'    => $this->assignee->id,
            'assigned_by_id' => $this->owner->id,
        ]);

        $this->assertTrue(Task::assignedTo($this->owner->id)->pluck('id')->contains($created->id));
        $this->assertTrue(Task::assignedTo($this->assignee->id)->pluck('id')->contains($assigned->id));
        $this->assertFalse(Task::assignedTo($this->assignee->id)->pluck('id')->contains($created->id));
    }

    public function test_assigned_to_excludes_unassigned_tasks_in_a_project_the_user_belongs_to(): void
    {
        // The whole point of the split: project membership lets you SEE a
        // task (visibleTo) but does not put it on your plate (assignedTo).
        $this->project->assignees()->sync([$this->assignee->id]);
        $task = $this->makeTask('Only the owner is on this task');

        $this->assertTrue(Task::visibleTo($this->assignee->id)->pluck('id')->contains($task->id));
        $this->assertFalse(Task::assignedTo($this->assignee->id)->pluck('id')->contains($task->id));
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
