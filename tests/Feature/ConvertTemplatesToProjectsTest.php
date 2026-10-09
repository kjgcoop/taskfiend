<?php

namespace Tests\Feature;

use App\Models\ChangeLog;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\ScheduledProject;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConvertTemplatesToProjectsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->user = User::factory()->create();
        // Fill the low project ids so legacy template ids also exist as project ids,
        // as they would in a real database (the FKs now point at projects).
        for ($i = 0; $i < 20; $i++) {
            Project::create(['name' => 'Filler ' . $i, 'user_id' => $this->user->id]);
        }
    }

    private function seedTemplate(int $id, string $name = 'Kitchen', bool $public = true, bool $withTasks = true): ProjectTemplate
    {
        // Built with ZipArchive rather than ProjectTemplateArchive::build(), which
        // shells out to the zip binary.
        $manifest = [
            'template_type'    => 'project',
            'project'          => ['name' => $name, 'description' => 'Desc'],
            'tasks'            => $withTasks
                ? [['name' => 'Parent', 'parent_index' => null], ['name' => 'Child', 'parent_index' => 0]]
                : [],
            'tags'             => [],
            'task_attachments' => [],
        ];

        $path = tempnam(sys_get_temp_dir(), 'tpl');
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('template.json', json_encode($manifest));
        $zip->close();

        $stored = 'project-templates/tpl_' . $id . '.zip';
        Storage::disk('private')->put($stored, file_get_contents($path));
        unlink($path);

        $template = new ProjectTemplate([
            'name' => $name, 'description' => 'Desc', 'filename' => $stored,
            'created_by' => $this->user->id, 'is_public' => $public,
        ]);
        $template->id = $id;
        $template->save();

        return $template;
    }

    public function test_converts_template_to_template_project_with_task_tree(): void
    {
        $template = $this->seedTemplate(15);

        $this->artisan('templates:convert-to-projects')->assertExitCode(0);

        $project = Project::where('project_type', Project::TYPE_TEMPLATE)->firstOrFail();
        $this->assertSame('Kitchen', $project->name);
        $this->assertSame('Desc', $project->description);
        $this->assertTrue($project->is_public);
        $this->assertSame($this->user->id, $project->user_id);

        $tasks = Task::where('project_id', $project->id)->get();
        $this->assertCount(2, $tasks);
        $child = $tasks->firstWhere('name', 'Child');
        $this->assertSame($tasks->firstWhere('name', 'Parent')->id, $child->parent_id);

        $log = ChangeLog::where('entity_type', 'projects')->where('entity_id', $project->id)
            ->where('verb', 'converted')->where('field', 'project_template_id')->firstOrFail();
        $this->assertSame((string) $template->id, (string) $log->old_value);
    }

    public function test_repoints_projects_and_scheduled_projects(): void
    {
        $this->seedTemplate(15);
        $made = Project::create(['name' => 'Made', 'user_id' => $this->user->id, 'template_id' => 15]);
        $scheduled = ScheduledProject::create([
            'template_id' => 15, 'user_id' => $this->user->id, 'project_name' => 'Later', 'start_date' => now()->addWeek(),
        ]);

        $this->artisan('templates:convert-to-projects')->assertExitCode(0);

        $newId = Project::where('project_type', Project::TYPE_TEMPLATE)->value('id');
        $this->assertNotSame(15, $newId);
        $this->assertSame($newId, $made->fresh()->template_id);
        $this->assertSame($newId, $scheduled->fresh()->template_id);
    }

    public function test_second_run_duplicates_nothing(): void
    {
        $this->seedTemplate(15);
        $made = Project::create(['name' => 'Made', 'user_id' => $this->user->id, 'template_id' => 15]);

        $this->artisan('templates:convert-to-projects')->assertExitCode(0);
        $newId = $made->fresh()->template_id;
        $taskCount = Task::count();

        $this->artisan('templates:convert-to-projects')->assertExitCode(0);

        $this->assertSame(1, Project::where('project_type', Project::TYPE_TEMPLATE)->count());
        $this->assertSame($taskCount, Task::count());
        $this->assertSame(1, ChangeLog::where('verb', 'converted')->count());
        $this->assertSame($newId, $made->fresh()->template_id);
    }

    public function test_empty_template_converts(): void
    {
        $this->seedTemplate(16, 'Empty', false, false);

        $this->artisan('templates:convert-to-projects')->assertExitCode(0);

        $project = Project::where('project_type', Project::TYPE_TEMPLATE)->firstOrFail();
        $this->assertSame('Empty', $project->name);
        $this->assertFalse($project->is_public);
        $this->assertSame(0, Task::where('project_id', $project->id)->count());
    }

    public function test_bad_zip_is_skipped_without_aborting_the_rest(): void
    {
        $bad = $this->seedTemplate(15, 'Bad');
        Storage::disk('private')->put($bad->filename, 'not a zip');
        $this->seedTemplate(16, 'Good');

        $this->artisan('templates:convert-to-projects')->assertExitCode(1);

        $this->assertSame(['Good'], Project::where('project_type', Project::TYPE_TEMPLATE)->pluck('name')->all());
    }

    public function test_purge_deletes_zip_only_for_converted_templates(): void
    {
        $converted = $this->seedTemplate(15, 'Done');
        $unconverted = $this->seedTemplate(16, 'Pending');
        $this->artisan('templates:convert-to-projects', ['--only' => 15])->assertExitCode(0);

        $this->artisan('templates:convert-to-projects', ['--purge' => true, '--purge-only' => true])->assertExitCode(0);

        Storage::disk('private')->assertMissing($converted->filename);
        Storage::disk('private')->assertExists($unconverted->filename);
        $this->assertSame(2, ProjectTemplate::count());
    }
}
