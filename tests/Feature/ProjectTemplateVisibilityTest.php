<?php

namespace Tests\Feature;

use App\Models\ProjectTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for PATCH /templates/{template}/visibility
 * (ProjectTemplateController::toggleVisibility)
 */
class ProjectTemplateVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
    }

    private function makeTemplate(bool $isPublic = false): ProjectTemplate
    {
        return ProjectTemplate::create([
            'name'        => 'My Template',
            'filename'    => 'project-templates/does-not-matter.zip',
            'created_by'  => $this->owner->id,
            'is_public'   => $isPublic,
        ]);
    }

    public function test_creator_can_toggle_a_private_template_to_public(): void
    {
        $template = $this->makeTemplate(false);

        $response = $this->actingAs($this->owner)->patchJson(route('templates.toggleVisibility', $template));

        $response->assertOk();
        $response->assertJson(['success' => true, 'is_public' => true]);
        $this->assertTrue($template->fresh()->is_public);
    }

    public function test_creator_can_toggle_a_public_template_back_to_private(): void
    {
        $template = $this->makeTemplate(true);

        $response = $this->actingAs($this->owner)->patchJson(route('templates.toggleVisibility', $template));

        $response->assertOk();
        $response->assertJson(['success' => true, 'is_public' => false]);
        $this->assertFalse($template->fresh()->is_public);
    }

    public function test_non_creator_cannot_toggle_visibility(): void
    {
        $other = User::factory()->create();
        $template = $this->makeTemplate(false);

        $response = $this->actingAs($other)->patchJson(route('templates.toggleVisibility', $template));

        $response->assertForbidden();
        $this->assertFalse($template->fresh()->is_public);
    }

    public function test_unauthenticated_request_is_redirected_to_login(): void
    {
        $template = $this->makeTemplate(false);

        $response = $this->patch(route('templates.toggleVisibility', $template));

        $response->assertRedirect('/login');
        $this->assertFalse($template->fresh()->is_public);
    }
}
