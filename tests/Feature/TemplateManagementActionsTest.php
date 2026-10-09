<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rename / visibility / delete (archive) actions on template Projects.
 */
class TemplateManagementActionsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->other = User::factory()->create();
    }

    private function makeTemplate(bool $public = false, string $name = 'My Template'): Project
    {
        return Project::create([
            'name' => $name, 'user_id' => $this->owner->id,
            'project_type' => Project::TYPE_TEMPLATE, 'is_public' => $public,
        ]);
    }

    public function test_creator_can_rename(): void
    {
        $t = $this->makeTemplate();
        $this->actingAs($this->owner)->patchJson(route('templates.update', $t), ['name' => ' Renamed '])
            ->assertOk()->assertJson(['success' => true, 'name' => 'Renamed']);
        $this->assertSame('Renamed', $t->fresh()->name);
    }

    public function test_rename_rejects_blank_name(): void
    {
        $t = $this->makeTemplate();
        $this->actingAs($this->owner)->patchJson(route('templates.update', $t), ['name' => '  '])->assertStatus(400);
        $this->assertSame('My Template', $t->fresh()->name);
    }

    public function test_non_creator_cannot_rename_even_if_public(): void
    {
        $t = $this->makeTemplate(true);
        $this->actingAs($this->other)->patchJson(route('templates.update', $t), ['name' => 'X'])->assertForbidden();
        $this->assertSame('My Template', $t->fresh()->name);
    }

    public function test_creator_can_toggle_visibility_both_ways(): void
    {
        $t = $this->makeTemplate(false);
        $this->actingAs($this->owner)->patchJson(route('templates.toggleVisibility', $t))
            ->assertOk()->assertJson(['is_public' => true]);
        $this->assertTrue($t->fresh()->is_public);
        $this->actingAs($this->owner)->patchJson(route('templates.toggleVisibility', $t))
            ->assertOk()->assertJson(['is_public' => false]);
        $this->assertFalse($t->fresh()->is_public);
    }

    public function test_non_creator_cannot_toggle_visibility(): void
    {
        $t = $this->makeTemplate(true);
        $this->actingAs($this->other)->patchJson(route('templates.toggleVisibility', $t))->assertForbidden();
        $this->assertTrue($t->fresh()->is_public);
    }

    public function test_destroy_archives_instead_of_deleting(): void
    {
        $t = $this->makeTemplate();
        $this->actingAs($this->owner)->delete(route('templates.destroy', $t))->assertRedirect();
        $this->assertSame('archived', $t->fresh()->status);
    }

    public function test_non_creator_cannot_destroy(): void
    {
        $t = $this->makeTemplate(true);
        $this->actingAs($this->other)->delete(route('templates.destroy', $t))->assertForbidden();
        $this->assertNotSame('archived', $t->fresh()->status);
    }

    public function test_archived_template_hidden_from_list_nav_and_create(): void
    {
        $t = $this->makeTemplate(true, 'Gone Template');
        $this->makeTemplate(true, 'Kept Template');
        $this->actingAs($this->owner)->delete(route('templates.destroy', $t));

        foreach ([$this->owner, $this->other] as $u) {
            $html = $this->actingAs($u)->get(route('templates.index'))->assertOk()->getContent();
            $this->assertStringNotContainsString('Gone Template', $html);
            $this->assertStringContainsString('Kept Template', $html);
        }

        $this->actingAs($this->owner)->post(route('templates.createFromTemplate', $t), ['project_name' => 'P'])
            ->assertForbidden();
        $this->assertSame(0, Project::where('template_id', $t->id)->count());
    }

    public function test_projects_created_from_template_keep_template_id_after_archive(): void
    {
        $t = $this->makeTemplate();
        $p = Project::createFromTemplate($t, 'Inst', $this->owner);
        $this->actingAs($this->owner)->delete(route('templates.destroy', $t));
        $this->assertSame($t->id, $p->fresh()->template_id);
    }
}
