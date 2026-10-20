<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /tags/{tag}/export-markdown (TagController::exportMarkdown): 'ids[]' narrows the export to
 * the task rows visible on the tag page, same pattern as the Today and project exports.
 */
class TagExportMarkdownTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tag $tag;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::create(['name' => 'P', 'user_id' => $this->user->id, 'status' => 'incomplete']);
        $this->tag = Tag::create(['tag_name' => 'errands', 'color' => '#112233']);
    }

    private function task(string $name, string $status = 'incomplete'): Task
    {
        $task = Task::create([
            'name' => $name, 'creator_id' => $this->user->id,
            'project_id' => $this->project->id, 'status' => $status,
        ]);
        $task->tags()->attach($this->tag->id);

        return $task;
    }

    private function export(array $query = []): string
    {
        return $this->actingAs($this->user)
            ->get(route('tags.export-markdown', $this->tag) . ($query ? '?' . http_build_query($query) : ''))
            ->assertOk()
            ->getContent();
    }

    public function test_without_ids_everything_is_exported(): void
    {
        $this->task('Open task');
        $this->task('Finished task', 'done');

        $md = $this->export();

        $this->assertStringContainsString('* Open task', $md);
        $this->assertStringContainsString('* Finished task', $md);
    }

    public function test_ids_narrow_to_visible_tasks(): void
    {
        $open = $this->task('Open task');
        $this->task('Finished task', 'done');

        $md = $this->export(['ids' => [$open->id]]);

        $this->assertStringContainsString('* Open task', $md);
        $this->assertStringNotContainsString('Finished task', $md);
    }

    public function test_empty_ids_exports_no_tasks(): void
    {
        $this->task('Open task');

        $this->assertStringNotContainsString('Open task', $this->export(['ids' => '']));
    }
}
