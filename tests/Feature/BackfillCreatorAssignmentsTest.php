<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillCreatorAssignmentsTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;
    private User $other;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = User::factory()->create();
        $this->other   = User::factory()->create();
        $this->project = Project::create([
            'name'    => 'Backfill',
            'user_id' => $this->creator->id,
            'status'  => 'incomplete',
        ]);
    }

    private function makeTask(string $name, array $assigneeIds): Task
    {
        $task = Task::create([
            'name'       => $name,
            'creator_id' => $this->creator->id,
            'project_id' => $this->project->id,
            'status'     => 'incomplete',
        ]);
        foreach ($assigneeIds as $id) {
            $task->assignments()->create(['assignee_id' => $id, 'assigned_by_id' => $this->creator->id]);
        }

        return $task;
    }

    public function test_assigns_the_creator_to_tasks_that_lack_them(): void
    {
        $task = $this->makeTask('Only the other user', [$this->other->id]);

        $this->artisan('tasks:backfill-creator-assignments')->assertSuccessful();

        $this->assertEqualsCanonicalizing(
            [$this->creator->id, $this->other->id],
            $task->assignments()->pluck('assignee_id')->all()
        );
        $this->assertDatabaseHas('assignments', [
            'task_id'        => $task->id,
            'assignee_id'    => $this->creator->id,
            'assigned_by_id' => $this->creator->id,
        ]);
        $this->assertDatabaseHas('change_logs', [
            'entity_type' => 'tasks',
            'entity_id'   => $task->id,
            'description' => 'updated assignees',
        ]);
    }

    public function test_assigns_tasks_that_have_no_assignments_at_all(): void
    {
        $task = $this->makeTask('Nobody', []);

        $this->artisan('tasks:backfill-creator-assignments')->assertSuccessful();

        $this->assertSame([$this->creator->id], $task->assignments()->pluck('assignee_id')->all());
    }

    public function test_leaves_tasks_that_already_include_their_creator_alone(): void
    {
        $task = $this->makeTask('Already fine', [$this->creator->id, $this->other->id]);

        $this->artisan('tasks:backfill-creator-assignments')->assertSuccessful();

        $this->assertSame(2, $task->assignments()->count());
        $this->assertDatabaseMissing('change_logs', ['entity_id' => $task->id, 'description' => 'updated assignees']);
    }

    public function test_is_idempotent(): void
    {
        $task = $this->makeTask('Only the other user', [$this->other->id]);

        $this->artisan('tasks:backfill-creator-assignments')->assertSuccessful();
        $this->artisan('tasks:backfill-creator-assignments')->assertSuccessful();

        $this->assertSame(2, $task->assignments()->count());
    }

    public function test_dry_run_writes_nothing(): void
    {
        $task = $this->makeTask('Only the other user', [$this->other->id]);

        $this->artisan('tasks:backfill-creator-assignments', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame([$this->other->id], $task->assignments()->pluck('assignee_id')->all());
        $this->assertDatabaseMissing('change_logs', ['entity_id' => $task->id, 'description' => 'updated assignees']);
    }
}
