<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Feature tests for POST /projects/{project}/save-as-template
 * (ProjectTemplateController::store)
 *
 * "Save a project as a template" — not downloaded; stored server-side in
 * the user's internal template repository for later reuse via
 * templates.createFromTemplate.
 */
class ProjectTemplateSaveTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->owner = User::factory()->create();
        $this->project = Project::create([
            'name'    => 'Kitchen Remodel',
            'user_id' => $this->owner->id,
            'status'  => 'incomplete',
        ]);
    }

    private function saveAsTemplate(array $data = [])
    {
        return $this->actingAs($this->owner)->post(
            route('templates.store', $this->project),
            array_merge(['template_name' => 'My Template'], $data)
        );
    }

    // =========================================================================
    // Authorization
    // =========================================================================

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->post(route('templates.store', $this->project), ['template_name' => 'X']);

        $response->assertRedirect('/login');
    }

    public function test_only_the_project_creator_can_save_it_as_a_template(): void
    {
        $assignee = User::factory()->create();
        $task = Task::create([
            'name' => 'Task', 'description' => '', 'creator_id' => $this->owner->id,
            'project_id' => $this->project->id, 'status' => 'incomplete',
        ]);
        Assignment::create(['task_id' => $task->id, 'assignee_id' => $assignee->id, 'assigned_by_id' => $this->owner->id]);

        $response = $this->actingAs($assignee)->post(route('templates.store', $this->project), [
            'template_name' => 'X',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseCount('project_templates', 0);
    }

    // =========================================================================
    // Validation
    // =========================================================================

    public function test_template_name_is_required(): void
    {
        $response = $this->saveAsTemplate(['template_name' => '']);

        $response->assertSessionHasErrors('template_name');
        $this->assertDatabaseCount('project_templates', 0);
    }

    // =========================================================================
    // Successful save
    // =========================================================================

    public function test_saving_creates_a_project_template_record(): void
    {
        $response = $this->saveAsTemplate([
            'template_name'        => 'Kitchen Template',
            'template_description' => 'Reusable kitchen remodel checklist',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('project_templates', [
            'name'        => 'Kitchen Template',
            'description' => 'Reusable kitchen remodel checklist',
            'created_by'  => $this->owner->id,
            'is_public'   => false,
        ]);
    }

    public function test_is_public_flag_is_stored(): void
    {
        $this->saveAsTemplate(['template_name' => 'Public Template', 'is_public' => true]);

        $this->assertDatabaseHas('project_templates', [
            'name'      => 'Public Template',
            'is_public' => true,
        ]);
    }

    public function test_saved_template_zip_is_stored_on_the_private_disk(): void
    {
        $this->saveAsTemplate(['template_name' => 'Kitchen Template']);

        $template = ProjectTemplate::where('name', 'Kitchen Template')->firstOrFail();

        $this->assertStringStartsWith('project-templates/', $template->filename);
        Storage::disk('private')->assertExists($template->filename);
    }

    public function test_saved_template_zip_contains_project_tasks(): void
    {
        $tag = Tag::create(['tag_name' => 'urgent', 'color' => '#ff0000']);
        $task = Task::create([
            'name' => 'Buy tile', 'description' => 'Get the good kind',
            'creator_id' => $this->owner->id, 'project_id' => $this->project->id, 'status' => 'incomplete',
        ]);
        $task->tags()->attach($tag->id);
        Assignment::create(['task_id' => $task->id, 'assignee_id' => $this->owner->id, 'assigned_by_id' => $this->owner->id]);

        $this->saveAsTemplate(['template_name' => 'Kitchen Template']);

        $template = ProjectTemplate::where('name', 'Kitchen Template')->firstOrFail();
        $zipPath = Storage::disk('private')->path($template->filename);

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath) === true);
        $data = json_decode($zip->getFromName('template.json'), true);
        $zip->close();

        $this->assertSame('project', $data['template_type']);
        $this->assertCount(1, $data['tasks']);
        $this->assertSame('Buy tile', $data['tasks'][0]['name']);
    }

    public function test_saving_a_second_template_does_not_overwrite_the_first(): void
    {
        $this->saveAsTemplate(['template_name' => 'Template One']);
        $this->saveAsTemplate(['template_name' => 'Template Two']);

        $this->assertDatabaseCount('project_templates', 2);

        $templates = ProjectTemplate::all();
        $this->assertNotSame($templates[0]->filename, $templates[1]->filename);
    }
}
