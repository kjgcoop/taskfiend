<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Round-trip behavior of App\Services\ProjectTemplateArchive that only shows
 * up when a template is imported somewhere its ids don't line up.
 */
class ProjectTemplateArchiveTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        $this->user = User::factory()->create();
        $this->project = Project::create(['name' => 'Kitchen', 'user_id' => $this->user->id]);
        $this->task = Task::create([
            'name' => 'Buy tile', 'creator_id' => $this->user->id,
            'project_id' => $this->project->id, 'status' => 'incomplete',
        ]);
    }

    private function exportZip(): string
    {
        $response = $this->actingAs($this->user)->get(route('projects.export-template', $this->project));
        $response->assertOk();

        $copy = tempnam(sys_get_temp_dir(), 'tpl') . '.zip';
        copy($response->getFile()->getPathname(), $copy);

        return $copy;
    }

    private function import(string $zip): Task
    {
        $this->actingAs($this->user)->post(route('projects.import-template'), [
            'template_file' => new UploadedFile($zip, 't.zip', 'application/zip', null, true),
            'project_name'  => 'Imported',
        ])->assertRedirect();

        $project = Project::where('name', 'Imported')->firstOrFail();

        return Task::where('project_id', $project->id)->where('name', 'Buy tile')->firstOrFail();
    }

    public function test_tags_are_matched_by_name_not_by_stale_id(): void
    {
        $urgent = Tag::create(['tag_name' => 'urgent', 'color' => '#ff0000']);
        $this->task->tags()->attach($urgent->id);
        $zip = $this->exportZip();

        // As on another instance: the manifest's tag id now belongs to an
        // unrelated tag, and "urgent" exists under a different id and case.
        $oldId = $urgent->id;
        $this->task->tags()->detach();
        $urgent->delete();
        $groceries = Tag::create(['tag_name' => 'groceries', 'color' => '#00ff00']);
        DB::table('tags')->where('id', $groceries->id)->update(['id' => $oldId]);
        Tag::create(['tag_name' => 'URGENT', 'color' => '#ff0000']);

        $imported = $this->import($zip);

        $this->assertSame(['URGENT'], $imported->tags->pluck('tag_name')->all());
        $this->assertSame(1, Tag::whereRaw('LOWER(tag_name) = ?', ['urgent'])->count());
    }

    public function test_missing_tag_is_created(): void
    {
        $tag = Tag::create(['tag_name' => 'only-here', 'color' => '#abcdef']);
        $this->task->tags()->attach($tag->id);
        $zip = $this->exportZip();

        $this->task->tags()->detach();
        $tag->delete();

        $imported = $this->import($zip);

        $this->assertSame(['only-here'], $imported->tags->pluck('tag_name')->all());
    }

    public function test_attachments_sharing_a_filename_keep_their_own_contents(): void
    {
        foreach (['one', 'two'] as $body) {
            Storage::disk('private')->put("dir{$body}/same.txt", $body);
            TaskAttachment::create([
                'task_id' => $this->task->id, 'user_id' => $this->user->id,
                'original_filename' => 'same.txt', 'file_path' => "dir{$body}/same.txt", 'file_size' => 3,
            ]);
        }

        $imported = $this->import($this->exportZip());

        $bodies = $imported->attachments
            ->map(fn ($a) => Storage::disk('private')->get($a->file_path))
            ->sort()->values()->all();
        $this->assertSame(['one', 'two'], $bodies);
    }
}
