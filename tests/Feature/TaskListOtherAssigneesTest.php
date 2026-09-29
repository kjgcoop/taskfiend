<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaskListOtherAssigneesTest extends TestCase
{
    use RefreshDatabase;

    public function test_task_list_shows_only_assignees_other_than_the_viewer(): void
    {
        $me = User::factory()->create(['name' => 'Viewer Zed']);
        $spouse = User::factory()->create(['name' => 'Spouse Quinn']);

        $project = Project::create(['name' => 'Shared', 'user_id' => $me->id, 'status' => 'incomplete']);
        $task = Task::create([
            'name' => 'Shared task',
            'creator_id' => $me->id,
            'project_id' => $project->id,
            'status' => 'incomplete',
        ]);
        $task->assignees()->attach([$me->id, $spouse->id]);

        $response = $this->actingAs($me)->get(route('projects.show', $project));

        $response->assertOk();
        $response->assertSee('title="Spouse Quinn"', false);
        $response->assertDontSee('title="Viewer Zed"', false);
    }
}
