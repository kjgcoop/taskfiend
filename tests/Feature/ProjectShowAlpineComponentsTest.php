<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The project page's header and modals are rendered for everyone who can open
 * the project, so the Alpine components they use must be registered for
 * everyone too. They used to be registered only for the owner, which left
 * project members with "Undefined variable: projectHeaderControls" errors and
 * a status dialog stuck open. (Console errors aren't visible to PHPUnit, so
 * this asserts the registering script is on the page.)
 */
class ProjectShowAlpineComponentsTest extends TestCase
{
    use RefreshDatabase;

    private const COMPONENTS = ['projectHeaderControls', 'projectHeaderEditor'];

    private function projectFor(User $owner, array $memberIds = []): Project
    {
        $project = Project::create([
            'name'    => 'Shared Project',
            'user_id' => $owner->id,
            'status'  => 'incomplete',
        ]);
        $project->assignees()->sync($memberIds);

        return $project;
    }

    public function test_owner_gets_the_header_components_registered(): void
    {
        $owner   = User::factory()->create();
        $project = $this->projectFor($owner);

        $response = $this->actingAs($owner)->get(route('projects.show', $project));

        foreach (self::COMPONENTS as $component) {
            $response->assertSee("Alpine.data('{$component}'", false);
        }
    }

    public function test_project_member_who_is_not_the_owner_gets_the_header_components_registered(): void
    {
        $owner   = User::factory()->create();
        $member  = User::factory()->create();
        $project = $this->projectFor($owner, [$member->id]);

        $response = $this->actingAs($member)->get(route('projects.show', $project));

        $response->assertOk();
        foreach (self::COMPONENTS as $component) {
            $response->assertSee("x-data=\"{$component}\"", false);
            $response->assertSee("Alpine.data('{$component}'", false);
        }
    }
}
