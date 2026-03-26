<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for Task model hierarchy methods:
 *   getAllDescendants(), getAllAncestors(), getRoot(), getDepth(),
 *   hasIncompleteDescendants(), isAncestorOf(), formatDuration()
 *
 * Uses RefreshDatabase because the hierarchy methods rely on Eloquent
 * relationships that require persisted records.
 */
class TaskModelTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();

        $this->project = Project::create([
            'name'    => 'Test Project',
            'user_id' => $this->user->id,
            'status'  => 'incomplete',
        ]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function createTask(array $overrides = []): Task
    {
        return Task::create(array_merge([
            'name'       => 'Task',
            'creator_id' => $this->user->id,
            'project_id' => $this->project->id,
            'status'     => 'incomplete',
        ], $overrides));
    }

    // =========================================================================
    // getAllDescendants()
    // =========================================================================

    public function test_get_all_descendants_returns_empty_collection_for_leaf(): void
    {
        $task = $this->createTask(['name' => 'Leaf']);

        $descendants = $task->getAllDescendants();

        $this->assertCount(0, $descendants);
    }

    public function test_get_all_descendants_returns_direct_children(): void
    {
        $parent = $this->createTask(['name' => 'Parent']);
        $child1 = $this->createTask(['name' => 'Child 1', 'parent_id' => $parent->id]);
        $child2 = $this->createTask(['name' => 'Child 2', 'parent_id' => $parent->id]);

        $descendants = $parent->getAllDescendants();

        $this->assertCount(2, $descendants);
        $this->assertTrue($descendants->contains('id', $child1->id));
        $this->assertTrue($descendants->contains('id', $child2->id));
    }

    public function test_get_all_descendants_returns_grandchildren(): void
    {
        $grandparent = $this->createTask(['name' => 'Grandparent']);
        $parent      = $this->createTask(['name' => 'Parent',     'parent_id' => $grandparent->id]);
        $child       = $this->createTask(['name' => 'Child',      'parent_id' => $parent->id]);

        $descendants = $grandparent->getAllDescendants();

        $this->assertCount(2, $descendants);
        $this->assertTrue($descendants->contains('id', $parent->id));
        $this->assertTrue($descendants->contains('id', $child->id));
    }

    public function test_get_all_descendants_returns_three_levels_deep(): void
    {
        $root        = $this->createTask(['name' => 'Root']);
        $level1      = $this->createTask(['name' => 'L1', 'parent_id' => $root->id]);
        $level2      = $this->createTask(['name' => 'L2', 'parent_id' => $level1->id]);
        $level3      = $this->createTask(['name' => 'L3', 'parent_id' => $level2->id]);

        $descendants = $root->getAllDescendants();

        $this->assertCount(3, $descendants);
        $this->assertTrue($descendants->contains('id', $level3->id));
    }

    // =========================================================================
    // getAllAncestors()
    // =========================================================================

    public function test_get_all_ancestors_returns_empty_for_root_task(): void
    {
        $root = $this->createTask(['name' => 'Root']);

        $ancestors = $root->getAllAncestors();

        $this->assertCount(0, $ancestors);
    }

    public function test_get_all_ancestors_returns_immediate_parent(): void
    {
        $parent = $this->createTask(['name' => 'Parent']);
        $child  = $this->createTask(['name' => 'Child', 'parent_id' => $parent->id]);

        $ancestors = $child->getAllAncestors();

        $this->assertCount(1, $ancestors);
        $this->assertSame($parent->id, $ancestors->first()->id);
    }

    public function test_get_all_ancestors_returns_full_chain_in_order(): void
    {
        $root   = $this->createTask(['name' => 'Root']);
        $middle = $this->createTask(['name' => 'Middle', 'parent_id' => $root->id]);
        $leaf   = $this->createTask(['name' => 'Leaf',   'parent_id' => $middle->id]);

        $ancestors = $leaf->getAllAncestors();

        $this->assertCount(2, $ancestors);
        // Order: immediate parent first, then grandparent
        $this->assertSame($middle->id, $ancestors->get(0)->id);
        $this->assertSame($root->id,   $ancestors->get(1)->id);
    }

    // =========================================================================
    // getRoot()
    // =========================================================================

    public function test_get_root_returns_self_for_root_task(): void
    {
        $root = $this->createTask(['name' => 'Root']);

        $this->assertSame($root->id, $root->getRoot()->id);
    }

    public function test_get_root_returns_top_level_ancestor(): void
    {
        $root   = $this->createTask(['name' => 'Root']);
        $middle = $this->createTask(['name' => 'Middle', 'parent_id' => $root->id]);
        $leaf   = $this->createTask(['name' => 'Leaf',   'parent_id' => $middle->id]);

        $this->assertSame($root->id, $leaf->getRoot()->id);
    }

    // =========================================================================
    // getDepth()
    // =========================================================================

    public function test_get_depth_returns_zero_for_root(): void
    {
        $root = $this->createTask(['name' => 'Root']);

        $this->assertSame(0, $root->getDepth());
    }

    public function test_get_depth_returns_one_for_direct_child(): void
    {
        $parent = $this->createTask(['name' => 'Parent']);
        $child  = $this->createTask(['name' => 'Child', 'parent_id' => $parent->id]);

        $this->assertSame(1, $child->getDepth());
    }

    public function test_get_depth_returns_correct_depth_for_deep_nesting(): void
    {
        $root   = $this->createTask(['name' => 'Root']);
        $l1     = $this->createTask(['name' => 'L1', 'parent_id' => $root->id]);
        $l2     = $this->createTask(['name' => 'L2', 'parent_id' => $l1->id]);
        $l3     = $this->createTask(['name' => 'L3', 'parent_id' => $l2->id]);

        $this->assertSame(3, $l3->getDepth());
    }

    // =========================================================================
    // hasIncompleteDescendants()
    // =========================================================================

    public function test_has_incomplete_descendants_false_when_no_children(): void
    {
        $task = $this->createTask();

        $this->assertFalse($task->hasIncompleteDescendants());
    }

    public function test_has_incomplete_descendants_true_when_child_is_incomplete(): void
    {
        $parent = $this->createTask(['name' => 'Parent']);
        $this->createTask(['name' => 'Child', 'parent_id' => $parent->id, 'status' => 'incomplete']);

        $this->assertTrue($parent->hasIncompleteDescendants());
    }

    public function test_has_incomplete_descendants_false_when_all_children_done(): void
    {
        $parent = $this->createTask(['name' => 'Parent']);
        $this->createTask(['name' => 'Child', 'parent_id' => $parent->id, 'status' => 'done']);

        $this->assertFalse($parent->hasIncompleteDescendants());
    }

    public function test_has_incomplete_descendants_true_when_grandchild_is_incomplete(): void
    {
        $grandparent = $this->createTask(['name' => 'Grandparent']);
        $parent      = $this->createTask(['name' => 'Parent', 'parent_id' => $grandparent->id, 'status' => 'done']);
        $this->createTask(['name' => 'Child', 'parent_id' => $parent->id, 'status' => 'incomplete']);

        $this->assertTrue($grandparent->hasIncompleteDescendants());
    }

    // =========================================================================
    // isAncestorOf()
    // =========================================================================

    public function test_is_ancestor_of_returns_true_for_direct_parent(): void
    {
        $parent = $this->createTask(['name' => 'Parent']);
        $child  = $this->createTask(['name' => 'Child', 'parent_id' => $parent->id]);

        $this->assertTrue($parent->isAncestorOf($child));
    }

    public function test_is_ancestor_of_returns_true_for_grandparent(): void
    {
        $grandparent = $this->createTask(['name' => 'Grandparent']);
        $parent      = $this->createTask(['name' => 'Parent', 'parent_id' => $grandparent->id]);
        $child       = $this->createTask(['name' => 'Child',  'parent_id' => $parent->id]);

        $this->assertTrue($grandparent->isAncestorOf($child));
    }

    public function test_is_ancestor_of_returns_false_for_unrelated_task(): void
    {
        $taskA = $this->createTask(['name' => 'Task A']);
        $taskB = $this->createTask(['name' => 'Task B']);

        $this->assertFalse($taskA->isAncestorOf($taskB));
    }

    public function test_is_ancestor_of_returns_false_for_child_checking_parent(): void
    {
        $parent = $this->createTask(['name' => 'Parent']);
        $child  = $this->createTask(['name' => 'Child', 'parent_id' => $parent->id]);

        // Child is NOT an ancestor of parent.
        $this->assertFalse($child->isAncestorOf($parent));
    }

    public function test_is_ancestor_of_prevents_circular_reference_detection(): void
    {
        $taskA = $this->createTask(['name' => 'Task A']);
        $taskB = $this->createTask(['name' => 'Task B', 'parent_id' => $taskA->id]);

        // taskA is ancestor of taskB — so taskB cannot be made parent of taskA.
        $this->assertTrue($taskA->isAncestorOf($taskB));
        // Therefore this check correctly prevents $taskB from becoming $taskA's parent:
        $this->assertFalse($taskB->isAncestorOf($taskA));
    }

    // =========================================================================
    // formatDuration() — static helper
    // =========================================================================

    public function test_format_duration_returns_null_for_null(): void
    {
        $this->assertNull(Task::formatDuration(null));
    }

    public function test_format_duration_returns_null_for_zero(): void
    {
        $this->assertNull(Task::formatDuration(0));
    }

    public function test_format_duration_minutes_only(): void
    {
        $this->assertSame('45m', Task::formatDuration(45));
    }

    public function test_format_duration_hours_only(): void
    {
        $this->assertSame('2h', Task::formatDuration(120));
    }

    public function test_format_duration_hours_and_minutes(): void
    {
        $this->assertSame('2h 20m', Task::formatDuration(140));
    }

    public function test_format_duration_one_minute(): void
    {
        $this->assertSame('1m', Task::formatDuration(1));
    }

    public function test_format_duration_one_hour(): void
    {
        $this->assertSame('1h', Task::formatDuration(60));
    }
}
