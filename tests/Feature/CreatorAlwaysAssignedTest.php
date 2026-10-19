<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A task's creator is always one of its assignees. Several creation paths
 * used to copy someone else's assignee list (the parent's, a &user token's,
 * the source task's) and forget to add the creator. (The API's create
 * endpoint is deliberately not covered: ApiTaskTest pins its own behavior.)
 */
class CreatorAlwaysAssignedTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $member;
    private Project $project;
    private Task $parent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner  = User::factory()->create();
        $this->member = User::factory()->create();

        $this->project = Project::create([
            'name'       => 'Shared',
            'user_id'    => $this->owner->id,
            'status'     => 'incomplete',
            'is_default' => true,
        ]);
        $this->project->assignees()->sync([$this->member->id]);

        // The member needs a default project of their own for the create endpoint.
        Project::create([
            'name'       => 'Member Default',
            'user_id'    => $this->member->id,
            'status'     => 'incomplete',
            'is_default' => true,
        ]);

        $this->parent = Task::create([
            'name'       => 'Parent',
            'creator_id' => $this->owner->id,
            'project_id' => $this->project->id,
            'status'     => 'incomplete',
        ]);
        $this->parent->assignments()->create([
            'assignee_id'    => $this->owner->id,
            'assigned_by_id' => $this->owner->id,
        ]);
    }

    private function assigneeIds(Task $task): array
    {
        return $task->assignments()->pluck('assignee_id')->sort()->values()->all();
    }

    public function test_subtask_created_by_a_non_assignee_of_the_parent_is_assigned_to_its_creator(): void
    {
        $this->actingAs($this->member)->post(route('tasks.store'), [
            'name'       => 'Subtask by member',
            'parent_id'  => $this->parent->id,
            'project_id' => $this->project->id,
        ]);

        $subtask = Task::where('name', 'Subtask by member')->firstOrFail();

        $this->assertSame($this->member->id, $subtask->creator_id);
        $this->assertContains($this->member->id, $this->assigneeIds($subtask));
        // Inheriting the parent's assignees still happens.
        $this->assertContains($this->owner->id, $this->assigneeIds($subtask));
    }

    public function test_bulk_added_line_with_an_assignee_token_still_assigns_the_creator(): void
    {
        $token = '&' . str($this->owner->name)->slug('')->toString();

        $this->actingAs($this->member)->post(route('tasks.store'), [
            'name'       => "Bulk one {$token}\nBulk two",
            'project_id' => $this->project->id,
            'quick_add'  => 1,
        ]);

        foreach (['Bulk one', 'Bulk two'] as $name) {
            $task = Task::where('name', 'like', "{$name}%")->firstOrFail();
            $this->assertContains($this->member->id, $this->assigneeIds($task), "{$name} must include its creator");
        }
    }

    public function test_task_duplicated_by_another_user_is_assigned_to_the_duplicator(): void
    {
        $this->actingAs($this->member)->post(route('tasks.duplicate', $this->parent));

        $copy = Task::where('name', 'Copy of Parent')->firstOrFail();

        $this->assertSame($this->member->id, $copy->creator_id);
        $this->assertContains($this->member->id, $this->assigneeIds($copy));
    }
}
