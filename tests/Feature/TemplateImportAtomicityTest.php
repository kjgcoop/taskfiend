<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectTemplate;
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
 * Both "create a project from a template zip" paths are all-or-nothing:
 * if the import blows up part-way, no project/tasks/tags are left behind
 * and any attachment files already copied to the private disk are removed.
 */
class TemplateImportAtomicityTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->user = User::factory()->create();
    }

    /**
     * A zip whose first task (with a tag and an attachment) imports fine, and
     * whose second task is missing required keys, so the import throws after
     * rows and files have already been written.
     */
    private function buildHalfBrokenZip(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'broken') . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('template.json', json_encode([
            'template_type'    => 'project',
            'project'          => ['name' => 'Broken', 'description' => ''],
            'tags'             => [['id' => 987654, 'name' => 'brand-new-tag', 'color' => '#123456']],
            'tasks'            => [
                [
                    'name' => 'Good task', 'description' => '', 'recurrence_pattern' => null,
                    'parent_index' => null, 'tags' => [987654], 'assignees' => [],
                ],
                ['parent_index' => null], // no name/description/recurrence_pattern
            ],
            'task_attachments' => [
                ['task_index' => 0, 'filename' => 'notes.txt', 'path' => 'task_attachments/notes.txt'],
            ],
        ]));
        $zip->addFromString('attachments/notes.txt', 'hello');
        $zip->close();

        return $path;
    }

    private function assertNothingWasCreated(): void
    {
        $this->assertSame(0, Project::where('name', 'Imported')->count());
        $this->assertSame(0, Task::count());
        $this->assertSame(0, TaskAttachment::count());
        $this->assertFalse(Tag::where('tag_name', 'brand-new-tag')->exists());
        $this->assertSame([], Storage::disk('private')->allFiles('task_attachments'));
    }

    public function test_failed_one_off_import_leaves_nothing_behind(): void
    {
        $zipPath = $this->buildHalfBrokenZip();

        $response = $this->actingAs($this->user)
            ->from(route('projects.index'))
            ->post(route('projects.import-template'), [
                'template_file' => new UploadedFile($zipPath, 'broken.zip', 'application/zip', null, true),
                'project_name'  => 'Imported',
            ]);

        $response->assertRedirect(route('projects.index'));
        $response->assertSessionHas('error');
        $this->assertNothingWasCreated();
    }

    public function test_failed_create_from_stored_template_leaves_nothing_behind(): void
    {
        $stored = 'project-templates/broken.zip';
        Storage::disk('private')->put($stored, file_get_contents($this->buildHalfBrokenZip()));
        $template = ProjectTemplate::create([
            'name'       => 'Broken template',
            'filename'   => $stored,
            'created_by' => $this->user->id,
            'is_public'  => false,
        ]);

        $response = $this->actingAs($this->user)
            ->from(route('templates.index'))
            ->post(route('templates.createFromTemplate', $template), ['project_name' => 'Imported']);

        $response->assertRedirect(route('templates.index'));
        $response->assertSessionHas('error');
        $this->assertNothingWasCreated();
        // The stored template itself must survive a failed use.
        Storage::disk('private')->assertExists($stored);
    }
}
