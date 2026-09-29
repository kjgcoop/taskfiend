<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectIndexAssigneesTest extends TestCase
{
    use RefreshDatabase;

    public function test_card_shows_project_assignees_but_not_the_viewer(): void
    {
        $me = User::factory()->create(['name' => 'Owner Person']);
        $spouse = User::factory()->create(['name' => 'Spouse Person']);

        $project = Project::create(['name' => 'Shared', 'user_id' => $me->id, 'status' => 'incomplete']);
        $project->assignees()->attach($spouse->id);

        $response = $this->actingAs($me)->get(route('projects.index'));

        $response->assertOk();
        $response->assertSee('title="Spouse Person"', false);
        $response->assertDontSee('title="Owner Person"', false);
    }

    public function test_assignee_viewer_sees_the_owner(): void
    {
        $owner = User::factory()->create(['name' => 'Owner Person']);
        $me = User::factory()->create(['name' => 'Assignee Person']);

        $project = Project::create(['name' => 'Shared', 'user_id' => $owner->id, 'status' => 'incomplete']);
        $project->assignees()->attach($me->id);

        $response = $this->actingAs($me)->get(route('projects.index'));

        $response->assertSee('title="Owner Person"', false);
        $response->assertDontSee('title="Assignee Person"', false);
    }

    public function test_extra_members_collapse_into_a_plus_badge(): void
    {
        $me = User::factory()->create();
        $project = Project::create(['name' => 'Crowded', 'user_id' => $me->id, 'status' => 'incomplete']);
        $project->assignees()->attach(User::factory()->count(5)->create()->pluck('id'));

        $this->actingAs($me)->get(route('projects.index'))->assertSee('+2');
    }
}
