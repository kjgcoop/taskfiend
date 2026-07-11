<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\ChangeLog;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\ScheduledProject;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature tests for POST /templates/{template}/create-project
 * (ProjectTemplateController::createFromTemplate)
 *
 * "Make an existing (saved) template into a project" — the counterpart to
 * ProjectTemplateSaveTest: takes a template already stored in the user's
 * internal repository and spins up a new project from it.
 */
class ProjectTemplateCreateFromTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->owner = User::factory()->create();
    }

    /**
     * Save a real project as a template via the actual store() endpoint, so
     * these tests exercise a genuine app-produced template rather than a
     * hand-rolled approximation of the schema.
     */
    private function createSavedTemplate(User $owner, bool $isPublic = false): ProjectTemplate
    {
        $sourceProject = Project::create([
            'name' => 'Source Project', 'user_id' => $owner->id, 'status' => 'incomplete',
        ]);
        $tag = Tag::create(['tag_name' => 'urgent', 'color' => '#ff0000']);
        $task = Task::create([
            'name' => 'Buy tile', 'description' => 'Get the good kind',
            'creator_id' => $owner->id, 'project_id' => $sourceProject->id, 'status' => 'incomplete',
        ]);
        $task->tags()->attach($tag->id);
        Assignment::create(['task_id' => $task->id, 'assignee_id' => $owner->id, 'assigned_by_id' => $owner->id]);

        $this->actingAs($owner)->post(route('templates.store', $sourceProject), [
            'template_name' => 'Kitchen Template',
            'is_public'     => $isPublic,
        ]);

        return ProjectTemplate::where('name', 'Kitchen Template')->firstOrFail();
    }

    // =========================================================================
    // Authorization
    // =========================================================================

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $template = $this->createSavedTemplate($this->owner);

        // createSavedTemplate() authenticates as the owner to hit the real
        // store() endpoint; drop that session before making a guest request.
        $this->app['auth']->forgetGuards();

        $response = $this->post(route('templates.createFromTemplate', $template), [
            'project_name' => 'New Project',
        ]);

        $response->assertRedirect('/login');
    }

    public function test_user_cannot_use_a_private_template_they_do_not_own(): void
    {
        $template = $this->createSavedTemplate($this->owner, isPublic: false);
        $other = User::factory()->create();

        $response = $this->actingAs($other)->post(route('templates.createFromTemplate', $template), [
            'project_name' => 'New Project',
        ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('projects', ['name' => 'New Project']);
    }

    public function test_any_user_can_use_a_public_template(): void
    {
        $template = $this->createSavedTemplate($this->owner, isPublic: true);
        $other = User::factory()->create();

        $response = $this->actingAs($other)->post(route('templates.createFromTemplate', $template), [
            'project_name' => 'New Project',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('projects', ['name' => 'New Project', 'user_id' => $other->id]);
    }

    // =========================================================================
    // Validation
    // =========================================================================

    public function test_project_name_is_required(): void
    {
        $template = $this->createSavedTemplate($this->owner);

        $response = $this->actingAs($this->owner)->post(route('templates.createFromTemplate', $template), []);

        $response->assertSessionHasErrors('project_name');
    }

    // =========================================================================
    // Immediate creation
    // =========================================================================

    public function test_creates_project_immediately_when_no_start_date_given(): void
    {
        $template = $this->createSavedTemplate($this->owner);

        $response = $this->actingAs($this->owner)->post(route('templates.createFromTemplate', $template), [
            'project_name' => 'New Kitchen Project',
        ]);

        $project = Project::where('name', 'New Kitchen Project')->first();
        $response->assertRedirect(route('projects.show', $project));

        $this->assertNotNull($project);
        $this->assertSame($this->owner->id, $project->user_id);
        $this->assertSame($template->id, $project->template_id);

        $task = Task::where('project_id', $project->id)->where('name', 'Buy tile')->first();
        $this->assertNotNull($task);
        $this->assertTrue($task->tags->pluck('tag_name')->contains('urgent'));
        $this->assertTrue($task->assignees->contains($this->owner));
    }

    public function test_creating_project_from_template_writes_a_change_log_entry(): void
    {
        $template = $this->createSavedTemplate($this->owner);

        $this->actingAs($this->owner)->post(route('templates.createFromTemplate', $template), [
            'project_name' => 'New Kitchen Project',
        ]);

        $project = Project::where('name', 'New Kitchen Project')->firstOrFail();

        $this->assertDatabaseHas('change_logs', [
            'entity_type' => 'projects',
            'entity_id'   => $project->id,
            'user_id'     => $this->owner->id,
        ]);
    }

    public function test_a_start_date_of_today_creates_the_project_immediately(): void
    {
        $template = $this->createSavedTemplate($this->owner);

        $this->actingAs($this->owner)->post(route('templates.createFromTemplate', $template), [
            'project_name' => 'New Kitchen Project',
            'start_date'   => 'today',
        ]);

        $this->assertDatabaseHas('projects', ['name' => 'New Kitchen Project']);
        $this->assertDatabaseCount('scheduled_projects', 0);
    }

    // =========================================================================
    // Deferred / scheduled creation
    // =========================================================================

    public function test_a_future_start_date_schedules_instead_of_creating_immediately(): void
    {
        $template = $this->createSavedTemplate($this->owner);
        $futureDate = Carbon::now()->addWeek()->toDateString();

        $response = $this->actingAs($this->owner)->post(route('templates.createFromTemplate', $template), [
            'project_name' => 'Future Project',
            'start_date'   => $futureDate,
        ]);

        $response->assertRedirect(route('templates.index'));
        $this->assertDatabaseMissing('projects', ['name' => 'Future Project']);

        $scheduled = ScheduledProject::where('template_id', $template->id)
            ->where('user_id', $this->owner->id)
            ->where('project_name', 'Future Project')
            ->first();

        $this->assertNotNull($scheduled);
        $this->assertSame($futureDate, $scheduled->start_date->toDateString());
    }
}
