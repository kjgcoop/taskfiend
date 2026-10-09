<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Auth edge cases for PATCH /templates/{template}/visibility
 * (the main toggle cases live in TemplateManagementActionsTest).
 */
class ProjectTemplateVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $owner = User::factory()->create();
        $template = Project::create([
            'name' => 'My Template', 'user_id' => $owner->id,
            'project_type' => Project::TYPE_TEMPLATE, 'is_public' => false,
        ]);

        $this->patch(route('templates.toggleVisibility', $template))->assertRedirect('/login');
        $this->assertFalse($template->fresh()->is_public);
    }

    public function test_non_template_project_is_not_found(): void
    {
        $owner = User::factory()->create();
        $project = Project::create(['name' => 'Plain', 'user_id' => $owner->id]);

        $this->actingAs($owner)->patchJson(route('templates.toggleVisibility', $project))->assertNotFound();
    }
}
