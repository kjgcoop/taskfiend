<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Feature tests for GET /projects/{project}/export-template
 * (DataExportController::exportProjectTemplate)
 *
 * "Saving a file as a template" — downloads the project as a
 * self-contained zip (template.json + attachments) the user keeps locally.
 */
class ProjectTemplateExportTest extends TestCase
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
            'name'        => 'Kitchen Remodel',
            'description' => 'Redo the kitchen',
            'user_id'     => $this->owner->id,
            'status'      => 'incomplete',
        ]);
    }

    private function makeTask(array $overrides = []): Task
    {
        $task = Task::create(array_merge([
            'name'        => 'Task',
            'description' => '',
            'creator_id'  => $this->owner->id,
            'project_id'  => $this->project->id,
            'status'      => 'incomplete',
        ], $overrides));

        Assignment::create([
            'task_id'        => $task->id,
            'assignee_id'    => $this->owner->id,
            'assigned_by_id' => $this->owner->id,
        ]);

        return $task;
    }

    // =========================================================================
    // Authorization
    // =========================================================================

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('projects.export-template', $this->project));

        $response->assertRedirect('/login');
    }

    public function test_user_with_no_access_gets_403(): void
    {
        $outsider = User::factory()->create();

        $response = $this->actingAs($outsider)->get(route('projects.export-template', $this->project));

        $response->assertForbidden();
    }

    public function test_assignee_who_is_not_creator_can_export(): void
    {
        $assignee = User::factory()->create();
        $task = $this->makeTask();
        Assignment::create([
            'task_id'        => $task->id,
            'assignee_id'    => $assignee->id,
            'assigned_by_id' => $this->owner->id,
        ]);

        $response = $this->actingAs($assignee)->get(route('projects.export-template', $this->project));

        $response->assertOk();
    }

    // =========================================================================
    // Successful export
    // =========================================================================

    public function test_creator_can_download_a_template_zip(): void
    {
        $this->makeTask(['name' => 'Buy tile']);

        $response = $this->actingAs($this->owner)->get(route('projects.export-template', $this->project));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/zip');
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
    }

    public function test_zip_contains_expected_template_json(): void
    {
        $tag = Tag::create(['tag_name' => 'urgent', 'color' => '#ff0000']);
        $task = $this->makeTask(['name' => 'Buy tile', 'description' => 'Get the good kind']);
        $task->tags()->attach($tag->id);

        // Excluded because it's not "incomplete".
        $this->makeTask(['name' => 'Finished task', 'status' => 'done']);
        $this->makeTask(['name' => 'Archived task', 'status' => 'archived']);

        $response = $this->actingAs($this->owner)->get(route('projects.export-template', $this->project));
        $response->assertOk();

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($response->getFile()->getPathname()) === true);

        $data = json_decode($zip->getFromName('template.json'), true);
        $zip->close();

        $this->assertSame('project', $data['template_type']);
        $this->assertSame('Kitchen Remodel', $data['project']['name']);

        // Only the one incomplete task should be present.
        $this->assertCount(1, $data['tasks']);
        $this->assertSame('Buy tile', $data['tasks'][0]['name']);
        $this->assertSame('Get the good kind', $data['tasks'][0]['description']);
        $this->assertSame([$tag->id], $data['tasks'][0]['tags']);

        $this->assertCount(1, $data['tags']);
        $this->assertSame('urgent', $data['tags'][0]['name']);
    }

    public function test_zip_includes_task_attachment_file(): void
    {
        $task = $this->makeTask(['name' => 'Task with file']);

        Storage::disk('private')->put('task_attachments/original.txt', 'attachment contents');
        TaskAttachment::create([
            'task_id'           => $task->id,
            'user_id'           => $this->owner->id,
            'original_filename' => 'original.txt',
            'file_path'         => 'task_attachments/original.txt',
            'file_size'         => 20,
        ]);

        $response = $this->actingAs($this->owner)->get(route('projects.export-template', $this->project));
        $response->assertOk();

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($response->getFile()->getPathname()) === true);

        $data = json_decode($zip->getFromName('template.json'), true);
        $this->assertCount(1, $data['task_attachments']);
        $this->assertSame('original.txt', $data['task_attachments'][0]['filename']);

        $this->assertNotFalse($zip->locateName('attachments/original.txt'));
        $this->assertSame('attachment contents', $zip->getFromName('attachments/original.txt'));

        $zip->close();
    }
}
