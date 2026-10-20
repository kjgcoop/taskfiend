<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Starting a template from scratch, opening templates from the list, and
 * keeping a template's task tree inside the template.
 */
class TemplateAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->other = User::factory()->create();

        // TaskController::edit()/create() throw without a default project.
        $default = Project::create(['name' => 'Inbox', 'user_id' => $this->owner->id, 'is_default' => true]);
        $default->assignees()->sync([$this->owner->id]);
    }

    private function makeProject(string $name, string $type = Project::TYPE_NORMAL, ?User $user = null): Project
    {
        $user ??= $this->owner;
        $project = Project::create(['name' => $name, 'user_id' => $user->id, 'project_type' => $type]);
        $project->assignees()->sync([$user->id]);

        return $project;
    }

    private function makeTask(Project $project, string $name = 'Task', ?Task $parent = null): Task
    {
        $task = Task::create([
            'name' => $name, 'creator_id' => $project->user_id, 'project_id' => $project->id,
            'parent_id' => $parent?->id, 'status' => 'incomplete',
        ]);
        $task->assignees()->sync([$project->user_id => ['assigned_by_id' => $project->user_id]]);

        return $task;
    }

    // --- Create from scratch -------------------------------------------------

    public function test_create_form_renders_and_index_links_to_it(): void
    {
        $this->actingAs($this->owner)->get(route('templates.index'))
            ->assertOk()->assertSee(route('templates.create'), false);
        $this->actingAs($this->owner)->get(route('templates.create'))
            ->assertOk()->assertSee('Create Template');
    }

    public function test_store_new_creates_empty_template_and_redirects_to_it(): void
    {
        $response = $this->actingAs($this->owner)->post(route('templates.storeNew'), [
            'name' => 'Onboarding', 'description' => 'New hire steps', 'is_public' => '1',
        ]);

        $template = Project::where('name', 'Onboarding')->firstOrFail();
        $response->assertRedirect(route('projects.show', $template));
        $this->assertTrue($template->isTemplate());
        $this->assertTrue($template->is_public);
        $this->assertSame($this->owner->id, $template->user_id);
        $this->assertSame('New hire steps', $template->description);
        $this->assertSame(0, $template->tasks()->count());
        $this->assertSame([$this->owner->id], $template->assignees()->pluck('users.id')->all());

        $this->actingAs($this->owner)->get(route('projects.show', $template))
            ->assertOk()->assertSee('data-template-banner', false);
        $this->assertDatabaseHas('change_logs', [
            'entity_type' => 'projects', 'entity_id' => $template->id, 'description' => 'created template',
        ]);
    }

    public function test_store_new_defaults_to_private_and_requires_a_name(): void
    {
        $this->actingAs($this->owner)->post(route('templates.storeNew'), ['name' => 'Quiet'])->assertRedirect();
        $this->assertFalse(Project::where('name', 'Quiet')->first()->is_public);

        $before = Project::count();
        $this->actingAs($this->owner)->post(route('templates.storeNew'), ['name' => ''])
            ->assertSessionHasErrors('name');
        $this->assertSame($before, Project::count());
    }

    public function test_new_template_appears_in_my_templates_not_in_project_list(): void
    {
        $this->actingAs($this->owner)->post(route('templates.storeNew'), ['name' => 'Fresh Template']);

        $this->actingAs($this->owner)->get(route('templates.index'))->assertSee('Fresh Template');

        // (Not assertDontSee on the page: the nav's Templates dropdown lists it everywhere.)
        $response = $this->actingAs($this->owner)->get(route('projects.index'))->assertOk();
        $this->assertNotContains('Fresh Template', collect($response->viewData('projects'))->pluck('name')->all());
    }

    // --- Tasks created in a template ------------------------------------------

    public function test_tasks_created_in_a_template_belong_to_it_and_the_panel_names_it(): void
    {
        $template = $this->makeProject('Template Home', Project::TYPE_TEMPLATE);

        $this->actingAs($this->owner)->post(route('tasks.store'), ['name' => 'Form task', 'project_id' => $template->id]);
        $this->actingAs($this->owner)->postJson(route('tasks.store'), ['name' => 'Quick task', 'project_id' => $template->id, 'quick_add' => 1]);

        $task = Task::where('name', 'Form task')->firstOrFail();
        $this->assertSame($template->id, $task->project_id);
        $this->assertSame($template->id, Task::where('name', 'Quick task')->firstOrFail()->project_id);

        // The panel can't find a template in its project list (templates are not
        // pickable), so it must be given the project name rather than defaulting to "Inbox".
        $html = $this->actingAs($this->owner)->get(route('tasks.panel', $task))->assertOk()->getContent();
        $this->assertStringContainsString('&quot;projectName&quot;:&quot;Template Home&quot;', $html);
    }

    // --- Moving tasks: templates mirror normal projects -------------------------

    public function test_template_task_can_move_only_to_another_own_template(): void
    {
        $a = $this->makeProject('A', Project::TYPE_TEMPLATE);
        $b = $this->makeProject('B', Project::TYPE_TEMPLATE);
        $normal = $this->makeProject('Normal');
        $others = $this->makeProject('Theirs', Project::TYPE_TEMPLATE, $this->other);
        $others->update(['is_public' => true]);
        $task = $this->makeTask($a);

        foreach ([$normal, $others] as $bad) {
            $this->actingAs($this->owner)->postJson(route('tasks.updateField', $task), ['field' => 'project_id', 'value' => $bad->id])
                ->assertStatus(422);
        }
        $this->assertSame($a->id, $task->fresh()->project_id);

        $this->actingAs($this->owner)->postJson(route('tasks.updateField', $task), ['field' => 'project_id', 'value' => $b->id])
            ->assertOk();
        $this->assertSame($b->id, $task->fresh()->project_id);
    }

    public function test_normal_task_cannot_move_into_a_template(): void
    {
        $normal = $this->makeProject('Normal');
        $template = $this->makeProject('Tpl', Project::TYPE_TEMPLATE);
        $task = $this->makeTask($normal);

        $this->actingAs($this->owner)->postJson(route('tasks.updateField', $task), ['field' => 'project_id', 'value' => $template->id])
            ->assertStatus(422);
        $this->actingAs($this->owner)->postJson(route('tasks.bulkUpdate'), ['task_ids' => [$task->id], 'project_id' => $template->id])
            ->assertStatus(422);
    }

    public function test_bulk_move_keeps_templates_and_normal_projects_apart(): void
    {
        $a = $this->makeProject('A', Project::TYPE_TEMPLATE);
        $b = $this->makeProject('B', Project::TYPE_TEMPLATE);
        $normal = $this->makeProject('Normal');
        $tplTask = $this->makeTask($a);
        $normalTask = $this->makeTask($normal);

        $this->actingAs($this->owner)->postJson(route('tasks.bulkUpdate'), ['task_ids' => [$tplTask->id], 'project_id' => $normal->id])
            ->assertStatus(422);
        $this->actingAs($this->owner)->postJson(route('tasks.bulkUpdate'), ['task_ids' => [$tplTask->id, $normalTask->id], 'project_id' => $b->id])
            ->assertStatus(422);
        $this->actingAs($this->owner)->postJson(route('tasks.bulkUpdate'), ['task_ids' => [$tplTask->id], 'project_id' => $b->id])
            ->assertOk();
        $this->assertSame($b->id, $tplTask->fresh()->project_id);
        $this->assertSame($normal->id, $normalTask->fresh()->project_id);
    }

    public function test_project_dropdown_lists_templates_for_template_tasks_and_projects_for_the_rest(): void
    {
        $a = $this->makeProject('Alpha Template', Project::TYPE_TEMPLATE);
        $this->makeProject('Beta Template', Project::TYPE_TEMPLATE);
        $this->makeProject('Gamma Normal');
        $tplTask = $this->makeTask($a);
        $normalTask = $this->makeTask($this->makeProject('Delta Normal'));

        $panel = $this->actingAs($this->owner)->get(route('tasks.panel', $tplTask))->getContent();
        $this->assertStringContainsString('Beta Template', $panel);
        $this->assertStringNotContainsString('Gamma Normal', $panel);

        $panel = $this->actingAs($this->owner)->get(route('tasks.panel', $normalTask))->getContent();
        $this->assertStringContainsString('Gamma Normal', $panel);
        $this->assertStringNotContainsString('Beta Template', $panel);
    }

    // --- Opening from the list -----------------------------------------------

    public function test_template_titles_link_to_the_template_page(): void
    {
        $mine = $this->makeProject('Mine', Project::TYPE_TEMPLATE);
        $theirs = $this->makeProject('Theirs', Project::TYPE_TEMPLATE, $this->other);
        $theirs->update(['is_public' => true]);

        $html = $this->actingAs($this->owner)->get(route('templates.index'))->assertOk()->getContent();

        $this->assertStringContainsString('href="' . route('projects.show', $mine) . '"', $html);
        $this->assertStringContainsString('href="' . route('projects.show', $theirs) . '"', $html);
        $this->assertStringNotContainsString('templateNameEditor', $html);
    }

    public function test_rename_endpoint_is_gone(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('templates.update'));
    }

    // --- No cross-project parent/child in templates ---------------------------

    public function test_parent_pickers_are_scoped_to_the_template(): void
    {
        $template = $this->makeProject('Tpl', Project::TYPE_TEMPLATE);
        $normal = $this->makeProject('Normal');
        $inTemplate = $this->makeTask($template, 'Template Parent Candidate');
        $other = $this->makeTask($template, 'Template Child');
        $outside = $this->makeTask($normal, 'Normal Parent Candidate');

        // Editing a template task offers only that template's tasks...
        $edit = $this->actingAs($this->owner)->get(route('tasks.edit', $other))->getContent();
        $this->assertStringContainsString('Template Parent Candidate', $edit);
        $this->assertStringNotContainsString('Normal Parent Candidate', $edit);

        // ...and editing a normal task never offers a template's tasks.
        $edit = $this->actingAs($this->owner)->get(route('tasks.edit', $outside))->getContent();
        $this->assertStringContainsString('Normal Parent Candidate', $edit);
        $this->assertStringNotContainsString('Template Parent Candidate', $edit);
    }

    public function test_cannot_nest_a_template_task_under_a_normal_task_or_vice_versa(): void
    {
        $template = $this->makeProject('Tpl', Project::TYPE_TEMPLATE);
        $normal = $this->makeProject('Normal');
        $tplTask = $this->makeTask($template, 'T');
        $normalTask = $this->makeTask($normal, 'N');

        $this->actingAs($this->owner)->postJson(route('tasks.updateField', $tplTask), ['field' => 'parent_id', 'value' => $normalTask->id])
            ->assertStatus(422);
        $this->actingAs($this->owner)->postJson(route('tasks.updateField', $normalTask), ['field' => 'parent_id', 'value' => $tplTask->id])
            ->assertStatus(422);
        $this->assertNull($tplTask->fresh()->parent_id);
        $this->assertNull($normalTask->fresh()->parent_id);
    }

    public function test_cannot_nest_across_two_templates(): void
    {
        $a = $this->makeProject('A', Project::TYPE_TEMPLATE);
        $b = $this->makeProject('B', Project::TYPE_TEMPLATE);
        $taskA = $this->makeTask($a);
        $taskB = $this->makeTask($b);

        $this->actingAs($this->owner)->postJson(route('tasks.updateField', $taskA), ['field' => 'parent_id', 'value' => $taskB->id])
            ->assertStatus(422);
    }

    public function test_edit_form_rejects_cross_boundary_parent(): void
    {
        $template = $this->makeProject('Tpl', Project::TYPE_TEMPLATE);
        $normal = $this->makeProject('Normal');
        $tplTask = $this->makeTask($template, 'T');
        $normalTask = $this->makeTask($normal, 'N');

        $this->actingAs($this->owner)->put(route('tasks.update', $tplTask), [
            'name' => 'T', 'project_id' => $template->id, 'parent_id' => $normalTask->id,
        ])->assertSessionHasErrors('parent_id');
        $this->assertNull($tplTask->fresh()->parent_id);
    }

    public function test_can_nest_within_the_same_template(): void
    {
        $template = $this->makeProject('Tpl', Project::TYPE_TEMPLATE);
        $parent = $this->makeTask($template, 'P');
        $child = $this->makeTask($template, 'C');

        $this->actingAs($this->owner)->postJson(route('tasks.updateField', $child), ['field' => 'parent_id', 'value' => $parent->id])
            ->assertOk();
        $this->assertSame($parent->id, $child->fresh()->parent_id);
    }

    public function test_normal_projects_may_still_mix_parents(): void
    {
        $a = $this->makeProject('A');
        $b = $this->makeProject('B');
        $taskA = $this->makeTask($a);
        $taskB = $this->makeTask($b);

        $this->actingAs($this->owner)->postJson(route('tasks.updateField', $taskA), ['field' => 'parent_id', 'value' => $taskB->id])
            ->assertOk();
    }

    public function test_nested_template_task_cannot_be_moved_out(): void
    {
        $template = $this->makeProject('Tpl', Project::TYPE_TEMPLATE);
        $normal = $this->makeProject('Normal');
        $parent = $this->makeTask($template, 'P');
        $child = $this->makeTask($template, 'C', $parent);

        $this->actingAs($this->owner)->postJson(route('tasks.updateField', $child), ['field' => 'project_id', 'value' => $normal->id])
            ->assertStatus(422);
        $this->actingAs($this->owner)->postJson(route('tasks.updateField', $parent), ['field' => 'project_id', 'value' => $normal->id])
            ->assertStatus(422);
        $this->actingAs($this->owner)->postJson(route('tasks.bulkUpdate'), ['task_ids' => [$child->id], 'project_id' => $normal->id])
            ->assertStatus(422);

        $this->assertSame($template->id, $child->fresh()->project_id);
        $this->assertSame($template->id, $parent->fresh()->project_id);
    }
}
