<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression test for the /tasks/parse-date preview endpoint.
 *
 * Its visibility query previously filtered the assignees relation on a
 * non-existent `user_id` column (instead of `users.id`), which made the
 * query error at runtime. The endpoint now uses Task::visibleTo().
 */
class ParseDatePreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_parse_date_returns_task_counts_for_visible_tasks(): void
    {
        $user    = User::factory()->create();
        $creator = User::factory()->create();

        $project = Project::create([
            'name'    => 'Preview Project',
            'user_id' => $creator->id,
            'status'  => 'incomplete',
        ]);

        $date = now()->addDay()->format('Y-m-d');

        // A task the user can see only via assignment — this exercises the
        // whereHas('assignees') branch that used to reference a bad column.
        $assigned = Task::create([
            'name'       => 'Assigned task',
            'creator_id' => $creator->id,
            'project_id' => $project->id,
            'status'     => 'incomplete',
            'date'       => $date,
        ]);
        $assigned->assignments()->create([
            'assignee_id'    => $user->id,
            'assigned_by_id' => $creator->id,
        ]);

        // A task the user cannot see at all.
        Task::create([
            'name'       => 'Foreign task',
            'creator_id' => $creator->id,
            'project_id' => $project->id,
            'status'     => 'incomplete',
            'date'       => $date,
        ]);

        $response = $this->actingAs($user)->post(route('tasks.parseDate'), [
            'input' => 'tomorrow',
        ]);

        $response->assertOk()->assertJson(['success' => true, 'date' => $date]);

        $projects = collect($response->json('projects'));
        $this->assertSame(1, $projects->sum('count'), 'Only the assigned task should be counted.');
    }
}
