<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\ChangeLog;
use App\Models\Project;
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
     * A template is a Project row with project_type = 'template'.
     */
    private function createSavedTemplate(User $owner, bool $isPublic = false): Project
    {
        $template = Project::create([
            'name' => 'Kitchen Template', 'user_id' => $owner->id, 'status' => 'incomplete',
            'project_type' => Project::TYPE_TEMPLATE, 'is_public' => $isPublic,
        ]);
        $tag = Tag::create(['tag_name' => 'urgent', 'color' => '#ff0000']);
        $task = Task::create([
            'name' => 'Buy tile', 'description' => 'Get the good kind',
            'creator_id' => $owner->id, 'project_id' => $template->id, 'status' => 'incomplete',
        ]);
        $task->tags()->attach($tag->id);
        Assignment::create(['task_id' => $task->id, 'assignee_id' => $owner->id, 'assigned_by_id' => $owner->id]);

        return $template;
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

    // =========================================================================
    // Template list, duplicate-based creation, scheduled paths
    // =========================================================================

    public function test_index_lists_only_template_projects_own_and_public(): void
    {
        $mine = $this->createSavedTemplate($this->owner);
        $other = User::factory()->create();
        $pub = Project::create(['name' => 'Their Public', 'user_id' => $other->id, 'project_type' => 'template', 'is_public' => true]);
        Project::create(['name' => 'Their Private', 'user_id' => $other->id, 'project_type' => 'template', 'is_public' => false]);
        Project::create(['name' => 'Plain Project', 'user_id' => $this->owner->id, 'is_public' => true]);

        $r = $this->actingAs($this->owner)->get(route('templates.index'));

        $r->assertOk();
        $this->assertSame([$mine->id], $r->viewData('myTemplates')->pluck('id')->all());
        $this->assertSame([$pub->id], $r->viewData('publicTemplates')->pluck('id')->all());
    }

    public function test_archived_tasks_do_not_come_along(): void
    {
        $template = $this->createSavedTemplate($this->owner);
        Task::create(['name' => 'Old task', 'creator_id' => $this->owner->id, 'project_id' => $template->id, 'status' => 'archived']);

        $this->actingAs($this->owner)->post(route('templates.createFromTemplate', $template), ['project_name' => 'Fresh']);

        $project = Project::where('name', 'Fresh')->firstOrFail();
        $this->assertSame(['Buy tile'], Task::where('project_id', $project->id)->pluck('name')->all());
        $this->assertSame('normal', $project->project_type);
    }

    public function test_create_now_uses_the_templates_current_state(): void
    {
        $template = $this->createSavedTemplate($this->owner);
        $scheduled = ScheduledProject::create([
            'template_id' => $template->id, 'user_id' => $this->owner->id,
            'project_name' => 'Later', 'start_date' => Carbon::now()->addWeek()->toDateString(),
        ]);
        Task::create(['name' => 'Added later', 'creator_id' => $this->owner->id, 'project_id' => $template->id, 'status' => 'incomplete']);

        $this->actingAs($this->owner)->post(route('scheduled-projects.create-now', $scheduled));

        $project = Project::where('name', 'Later')->firstOrFail();
        $this->assertEqualsCanonicalizing(['Buy tile', 'Added later'], Task::where('project_id', $project->id)->pluck('name')->all());
        $this->assertSame($template->id, $project->template_id);
        $this->assertDatabaseMissing('scheduled_projects', ['id' => $scheduled->id]);
    }

    public function test_scheduled_command_reflects_template_state_on_the_day(): void
    {
        $template = $this->createSavedTemplate($this->owner);
        ScheduledProject::create([
            'template_id' => $template->id, 'user_id' => $this->owner->id,
            'project_name' => 'Due Today', 'start_date' => now()->toDateString(),
        ]);
        Task::where('project_id', $template->id)->update(['status' => 'archived']);
        Task::create(['name' => 'Only this', 'creator_id' => $this->owner->id, 'project_id' => $template->id, 'status' => 'incomplete']);

        $this->artisan('projects:create-scheduled')->assertSuccessful();

        $project = Project::where('name', 'Due Today')->firstOrFail();
        $this->assertSame($this->owner->id, $project->user_id);
        $this->assertSame(['Only this'], Task::where('project_id', $project->id)->pluck('name')->all());
        $this->assertDatabaseHas('scheduled_projects', ['project_name' => 'Due Today', 'is_created' => true]);
    }

    public function test_project_page_links_template_only_when_viewer_can_edit(): void
    {
        $template = $this->createSavedTemplate($this->owner, isPublic: true);
        $project = $template->duplicate('From Tpl', $this->owner->id);

        $r = $this->actingAs($this->owner)->get(route('projects.show', $project));
        $r->assertSee('Created from template', false);
        $r->assertSee('data-template-source-link', false);

        $other = User::factory()->create();
        $project->assignees()->sync([$this->owner->id, $other->id]);
        $r = $this->actingAs($other)->get(route('projects.show', $project));
        $r->assertSee('Created from template', false);
        $r->assertSee('Kitchen Template');
        $r->assertDontSee('data-template-source-link', false);

        $template->update(['status' => 'archived']);
        $r = $this->actingAs($this->owner)->get(route('projects.show', $project));
        $r->assertSee('(archived)', false);
        $r->assertDontSee('data-template-source-link', false);
    }
}
