<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateProjectEditingTest extends TestCase
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

    private function makeProject(string $type, bool $public = false): Project
    {
        $project = Project::create([
            'name'         => 'Kickoff Checklist',
            'user_id'      => $this->owner->id,
            'project_type' => $type,
            'is_public'    => $public,
        ]);
        $project->assignees()->sync([$this->owner->id]);
        $task = Task::create([
            'name' => 'Template Task', 'creator_id' => $this->owner->id,
            'project_id' => $project->id, 'status' => 'incomplete',
        ]);
        $task->assignees()->sync([$this->owner->id => ['assigned_by_id' => $this->owner->id]]);

        return $project;
    }

    public function test_banner_appears_only_on_template_projects(): void
    {
        $template = $this->makeProject(Project::TYPE_TEMPLATE);
        $normal   = $this->makeProject(Project::TYPE_NORMAL);

        $this->actingAs($this->owner)->get(route('projects.show', $template))
            ->assertOk()->assertSee('data-template-banner', false);
        $this->actingAs($this->owner)->get(route('projects.show', $normal))
            ->assertOk()->assertDontSee('data-template-banner', false);
    }

    public function test_creator_edit_on_template_persists_immediately(): void
    {
        $template = $this->makeProject(Project::TYPE_TEMPLATE);

        $this->actingAs($this->owner)->postJson(route('projects.updateField', $template), [
            'field' => 'name', 'value' => 'Renamed',
        ])->assertOk();

        $this->assertSame('Renamed', $template->fresh()->name);
        $this->assertSame(1, Project::where('project_type', 'template')->count());
    }

    public function test_other_user_views_public_template_read_only(): void
    {
        $template = $this->makeProject(Project::TYPE_TEMPLATE, true);

        $this->actingAs($this->other)->get(route('projects.show', $template))
            ->assertOk()
            ->assertSee('Template Task')
            ->assertSee('data-template-banner', false);

        $this->actingAs($this->other)->postJson(route('projects.updateField', $template), [
            'field' => 'name', 'value' => 'Hacked',
        ])->assertForbidden();
        $this->actingAs($this->other)->postJson(route('projects.reorderTasks', $template), [
            'ids' => [],
        ])->assertForbidden();
        $this->assertSame('Kickoff Checklist', $template->fresh()->name);
    }

    public function test_other_user_cannot_view_private_template(): void
    {
        $template = $this->makeProject(Project::TYPE_TEMPLATE, false);

        $this->actingAs($this->other)->get(route('projects.show', $template))->assertForbidden();
    }

    public function test_is_public_has_no_effect_on_normal_project(): void
    {
        $normal = $this->makeProject(Project::TYPE_NORMAL, true);

        $this->actingAs($this->other)->get(route('projects.show', $normal))->assertForbidden();
    }

    public function test_nav_dropdown_links_to_template_project_page(): void
    {
        $template = $this->makeProject(Project::TYPE_TEMPLATE);

        $this->actingAs($this->owner)->get(route('projects.index'))
            ->assertOk()
            ->assertSee('data-nav-template href="' . route('projects.show', $template) . '"', false)
            ->assertDontSee('templates#template-', false);
    }
}
