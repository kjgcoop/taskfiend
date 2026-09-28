<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A task can have a parent whose own project differs from the task's project (the parent picker
 * doesn't force the two to match). Before this fix, ProjectController::show() (and its
 * completed/archived counterparts) only queried tasks with a null parent_id as "top-level for
 * this project" — a task belonging to this project whose parent belongs to a *different* project
 * was excluded from that query (it has a parent_id), and never appears nested either (its parent
 * isn't part of this project's tree), so it silently vanished from the project entirely.
 *
 * The fix: a task is "top-level for this project" if it has no parent, OR its parent belongs to
 * a different project. It then renders with a "Subtask of: <parent> in <parent's project>"
 * banner (task-list.blade.php) so the cross-project relationship is clear.
 */
class CrossProjectChildVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $projectA;
    private Project $projectB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->projectA = Project::create([
            'name' => 'Project A', 'user_id' => $this->user->id, 'status' => 'incomplete',
        ]);
        $this->projectB = Project::create([
            'name' => 'Project B', 'user_id' => $this->user->id, 'status' => 'incomplete',
        ]);
    }

    private function makeTask(Project $project, array $overrides = []): Task
    {
        $task = Task::create(array_merge([
            'name'       => 'Task',
            'creator_id' => $this->user->id,
            'project_id' => $project->id,
            'status'     => 'incomplete',
        ], $overrides));

        Assignment::create([
            'task_id' => $task->id, 'assignee_id' => $this->user->id, 'assigned_by_id' => $this->user->id,
        ]);

        return $task;
    }

    public function test_child_in_a_different_project_than_its_parent_appears_on_its_own_projects_page()
    {
        $parent = $this->makeTask($this->projectA, ['name' => 'Parent In A']);
        $child  = $this->makeTask($this->projectB, ['name' => 'Child In B', 'parent_id' => $parent->id]);

        $response = $this->actingAs($this->user)->get(route('projects.show', $this->projectB));

        $response->assertOk();
        $response->assertSee('Child In B');
        // The cross-project banner names both the parent and its project.
        $response->assertSee('Parent In A');
        $response->assertSee('Project A');
    }

    public function test_child_in_same_project_as_parent_is_still_nested_not_duplicated_at_top_level()
    {
        $parent = $this->makeTask($this->projectA, ['name' => 'Parent Same Project']);
        $child  = $this->makeTask($this->projectA, ['name' => 'Child Same Project', 'parent_id' => $parent->id]);

        $response = $this->actingAs($this->user)->get(route('projects.show', $this->projectA));

        $response->assertOk();
        $tasks = $response->viewData('tasks');

        $this->assertTrue($tasks->contains('id', $parent->id));
        $this->assertFalse($tasks->contains('id', $child->id), 'same-project child should only be nested, not also top-level');
    }

    public function test_cross_project_top_level_scope_does_not_bypass_task_visibility()
    {
        $other = User::factory()->create();
        $otherProject = Project::create([
            'name' => 'Other Project', 'user_id' => $other->id, 'status' => 'incomplete',
        ]);

        // Grants $this->user view access to $otherProject via a single assigned task, without
        // making them a project-level member (so per-task visibility still applies).
        $ownTask = Task::create([
            'name' => 'My Task In Other Project', 'creator_id' => $other->id,
            'project_id' => $otherProject->id, 'status' => 'incomplete',
        ]);
        Assignment::create([
            'task_id' => $ownTask->id, 'assignee_id' => $this->user->id, 'assigned_by_id' => $other->id,
        ]);

        // A private task belonging entirely to the other user, parented under a task in a
        // different project — qualifies as "top-level" under the new cross-project scope, but
        // must still be excluded because $this->user cannot see it via Task::visibleTo().
        $parent = $this->makeTask($this->projectA, ['name' => 'Parent A']);
        $private = Task::create([
            'name' => 'Private Task', 'creator_id' => $other->id, 'project_id' => $otherProject->id,
            'status' => 'incomplete', 'parent_id' => $parent->id,
        ]);
        Assignment::create([
            'task_id' => $private->id, 'assignee_id' => $other->id, 'assigned_by_id' => $other->id,
        ]);

        $response = $this->actingAs($this->user)->get(route('projects.show', $otherProject));

        $response->assertOk();
        $tasks = $response->viewData('tasks');

        $this->assertTrue($tasks->contains('id', $ownTask->id));
        $this->assertFalse($tasks->contains('id', $private->id));
    }
}
