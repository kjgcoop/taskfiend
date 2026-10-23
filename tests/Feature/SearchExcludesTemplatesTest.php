<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchExcludesTemplatesTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_tasks_are_not_returned_by_search(): void
    {
        $user = User::factory()->create();

        $template = Project::create([
            'name'         => 'Template Project',
            'user_id'      => $user->id,
            'status'       => 'incomplete',
            'project_type' => Project::TYPE_TEMPLATE,
        ]);
        $normal = Project::create([
            'name'    => 'Normal Project',
            'user_id' => $user->id,
            'status'  => 'incomplete',
        ]);

        foreach ([['Zebra Template Task', $template], ['Zebra Real Task', $normal]] as [$name, $project]) {
            Task::create([
                'name'       => $name,
                'creator_id' => $user->id,
                'project_id' => $project->id,
                'status'     => 'incomplete',
            ]);
        }

        $response = $this->actingAs($user)->get(route('search', [
            'q'               => 'Zebra',
            'search_title'    => '1',
            'show_incomplete' => '1',
        ]));

        $response->assertOk();
        $response->assertSee('Zebra Real Task');
        $response->assertDontSee('Zebra Template Task');
    }
}
