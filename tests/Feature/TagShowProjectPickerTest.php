<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test for GET /tags/{tag}  (TagController::show)
 *
 * Covers the `$projects` list rendered into `data-projects` on that page,
 * which feeds the quick-add bar's `#project` matching and the bulk-edit
 * "move to project" dropdown — both project *pickers*, not just a filter.
 * Part of the "Audit project pickers for archived/done projects" plan item:
 * this list previously included done projects (only archived ones were
 * excluded), unlike every sibling page sharing the same component.
 */
class TagShowProjectPickerTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_picker_data_excludes_done_and_archived_projects(): void
    {
        $user = User::factory()->create();
        $tag  = Tag::create(['tag_name' => 'urgent', 'color' => '#ff0000']);

        $activeProject = Project::create([
            'name'    => 'Active Project',
            'user_id' => $user->id,
            'status'  => 'incomplete',
        ]);
        $doneProject = Project::create([
            'name'    => 'Done Project',
            'user_id' => $user->id,
            'status'  => 'done',
        ]);
        $archivedProject = Project::create([
            'name'    => 'Archived Project',
            'user_id' => $user->id,
            'status'  => 'archived',
        ]);

        $response = $this->actingAs($user)->get("/tags/{$tag->id}");

        $response->assertOk();
        $projects = $response->viewData('projects');
        $projectIds = $projects->pluck('id')->all();

        $this->assertContains($activeProject->id, $projectIds);
        $this->assertNotContains($doneProject->id, $projectIds);
        $this->assertNotContains($archivedProject->id, $projectIds);
    }
}
