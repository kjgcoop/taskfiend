<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Feature tests for POST /projects/import-template
 * (DataExportController::importProjectTemplate)
 *
 * "Uploading a template and creating a project" — a user uploads a zip
 * (their own previous export, or one shared with them) and a brand new
 * project is created from it.
 */
class ProjectTemplateImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
    }

    /**
     * Build a real template zip by exercising the actual export endpoint,
     * so these tests exercise genuine app-produced zips rather than a
     * hand-rolled approximation of the schema.
     */
    private function exportTemplateZipPath(User $owner, Project $project): string
    {
        $response = $this->actingAs($owner)->get(route('projects.export-template', $project));
        $response->assertOk();

        return $response->getFile()->getPathname();
    }

    private function uploadedZip(string $path, string $name = 'template.zip'): UploadedFile
    {
        return new UploadedFile($path, $name, 'application/zip', null, true);
    }

    /** Build a zip with an entry whose name escapes the extraction directory. */
    private function buildTraversalZip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'trav') . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('template.json', json_encode([
            'template_type' => 'project',
            'project'       => ['name' => 'x', 'description' => ''],
            'tasks'         => [],
            'tags'          => [],
        ]));
        $zip->addFromString('../../evil.txt', 'pwned');
        $zip->close();

        return $path;
    }

    /** Build a zip containing a unix symlink entry (classic unzip-symlink escape). */
    private function buildSymlinkZip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'symlink') . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('template.json', json_encode([
            'template_type' => 'project',
            'project'       => ['name' => 'x', 'description' => ''],
            'tasks'         => [],
            'tags'          => [],
        ]));
        $zip->addFromString('evil_link', sys_get_temp_dir());
        $zip->setExternalAttributesName('evil_link', ZipArchive::OPSYS_UNIX, 0120777 << 16);
        $zip->close();

        return $path;
    }

    /** Build a valid zip padded past the upload size limit with incompressible data. */
    private function buildOversizedZip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'big') . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('template.json', json_encode([
            'template_type' => 'project',
            'project'       => ['name' => 'x', 'description' => ''],
            'tasks'         => [],
            'tags'          => [],
        ]));
        // Random bytes don't compress, so this pushes the zip's on-disk
        // size past the 10MB upload cap.
        $zip->addFromString('padding.bin', random_bytes(11 * 1024 * 1024));
        $zip->setCompressionName('padding.bin', ZipArchive::CM_STORE);
        $zip->close();

        return $path;
    }

    // =========================================================================
    // Validation
    // =========================================================================

    public function test_template_file_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.import-template'), [
            'project_name' => 'New Project',
        ]);

        $response->assertSessionHasErrors('template_file');
    }

    public function test_template_file_must_be_a_zip(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.import-template'), [
            'project_name'  => 'New Project',
            'template_file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ]);

        $response->assertSessionHasErrors('template_file');
    }

    public function test_project_name_is_required(): void
    {
        $user = User::factory()->create();
        $zipPath = $this->exportTemplateZipPath($user, Project::create([
            'name' => 'Source', 'user_id' => $user->id, 'status' => 'incomplete',
        ]));

        $response = $this->actingAs($user)->post(route('projects.import-template'), [
            'template_file' => $this->uploadedZip($zipPath),
        ]);

        $response->assertSessionHasErrors('project_name');
    }

    public function test_oversized_template_file_is_rejected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.import-template'), [
            'project_name'  => 'New Project',
            'template_file' => $this->uploadedZip($this->buildOversizedZip()),
        ]);

        $response->assertSessionHasErrors('template_file');
        $this->assertDatabaseMissing('projects', ['name' => 'New Project']);
    }

    // =========================================================================
    // Successful import
    // =========================================================================

    public function test_uploading_a_valid_template_creates_a_project_with_tasks_and_tags(): void
    {
        $owner = User::factory()->create();
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

        $zipPath = $this->exportTemplateZipPath($owner, $sourceProject);

        $importer = User::factory()->create();
        $response = $this->actingAs($importer)->post(route('projects.import-template'), [
            'project_name'  => 'Imported Project',
            'template_file' => $this->uploadedZip($zipPath),
        ]);

        $project = Project::where('name', 'Imported Project')->first();
        $response->assertRedirect(route('projects.show', $project));

        $this->assertNotNull($project);
        $this->assertSame($importer->id, $project->user_id);

        $importedTask = Task::where('project_id', $project->id)->where('name', 'Buy tile')->first();
        $this->assertNotNull($importedTask);
        $this->assertSame('incomplete', $importedTask->status);
        $this->assertTrue($importedTask->tags->pluck('tag_name')->contains('urgent'));
    }

    public function test_importer_is_auto_assigned_to_every_imported_task(): void
    {
        $owner = User::factory()->create();
        $sourceProject = Project::create([
            'name' => 'Source Project', 'user_id' => $owner->id, 'status' => 'incomplete',
        ]);
        $task = Task::create([
            'name' => 'Solo task', 'description' => '',
            'creator_id' => $owner->id, 'project_id' => $sourceProject->id, 'status' => 'incomplete',
        ]);
        Assignment::create(['task_id' => $task->id, 'assignee_id' => $owner->id, 'assigned_by_id' => $owner->id]);

        $zipPath = $this->exportTemplateZipPath($owner, $sourceProject);

        $importer = User::factory()->create();
        $this->actingAs($importer)->post(route('projects.import-template'), [
            'project_name'  => 'Imported Project',
            'template_file' => $this->uploadedZip($zipPath),
        ]);

        $importedTask = Task::where('name', 'Solo task')
            ->whereHas('project', fn ($q) => $q->where('name', 'Imported Project'))
            ->firstOrFail();

        $this->assertTrue($importedTask->assignees->contains($importer));
    }

    public function test_imported_task_attachment_is_copied_with_sanitized_filename(): void
    {
        $owner = User::factory()->create();
        $sourceProject = Project::create([
            'name' => 'Source Project', 'user_id' => $owner->id, 'status' => 'incomplete',
        ]);
        $task = Task::create([
            'name' => 'Task with file', 'description' => '',
            'creator_id' => $owner->id, 'project_id' => $sourceProject->id, 'status' => 'incomplete',
        ]);
        Assignment::create(['task_id' => $task->id, 'assignee_id' => $owner->id, 'assigned_by_id' => $owner->id]);

        Storage::disk('private')->put('task_attachments/original.txt', 'attachment contents');
        TaskAttachment::create([
            'task_id' => $task->id, 'user_id' => $owner->id,
            'original_filename' => 'original.txt',
            'file_path' => 'task_attachments/original.txt',
            'file_size' => 20,
        ]);

        $zipPath = $this->exportTemplateZipPath($owner, $sourceProject);

        $importer = User::factory()->create();
        $this->actingAs($importer)->post(route('projects.import-template'), [
            'project_name'  => 'Imported Project',
            'template_file' => $this->uploadedZip($zipPath),
        ]);

        $importedTask = Task::where('name', 'Task with file')
            ->whereHas('project', fn ($q) => $q->where('name', 'Imported Project'))
            ->firstOrFail();

        $attachment = $importedTask->attachments()->first();
        $this->assertNotNull($attachment);
        $this->assertSame('original.txt', $attachment->original_filename);
        $this->assertTrue(Storage::disk('private')->exists($attachment->file_path));
        $this->assertSame('attachment contents', Storage::disk('private')->get($attachment->file_path));
    }

    // =========================================================================
    // Security regression: malicious zip handling
    // =========================================================================

    public function test_zip_with_path_traversal_entry_is_rejected_and_creates_no_project(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.import-template'), [
            'project_name'  => 'Should Not Exist',
            'template_file' => $this->uploadedZip($this->buildTraversalZip()),
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('projects', ['name' => 'Should Not Exist']);
    }

    public function test_zip_with_symlink_entry_is_rejected_and_creates_no_project(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.import-template'), [
            'project_name'  => 'Should Not Exist',
            'template_file' => $this->uploadedZip($this->buildSymlinkZip()),
        ]);

        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('projects', ['name' => 'Should Not Exist']);
    }
}
