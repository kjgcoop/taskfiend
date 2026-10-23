<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for GET /projects/{project}/export-markdown (DataExportController::exportMarkdown)
 *
 * Covers the 'ids[]' narrowing mechanism that lets the export mirror what's visible on the
 * project page (folded sections and collapsed subtasks excluded), same pattern as the Today
 * page's exports.
 */
class ProjectExportMarkdownTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::create([
            'name'    => 'My Project',
            'user_id' => $this->user->id,
            'status'  => 'incomplete',
        ]);
    }

    private function task(string $name, string $status = 'incomplete', ?Task $parent = null): Task
    {
        return Task::create([
            'name'       => $name,
            'creator_id' => $this->user->id,
            'project_id' => $this->project->id,
            'parent_id'  => $parent?->id,
            'status'     => $status,
        ]);
    }

    private function export(array $query = []): string
    {
        return $this->actingAs($this->user)
            ->get(route('projects.export-markdown', $this->project) . ($query ? '?' . http_build_query($query) : ''))
            ->assertOk()
            ->getContent();
    }

    public function test_without_ids_everything_is_exported(): void
    {
        $this->task('Open task');
        $this->task('Finished task', 'done');
        $this->task('Old task', 'archived');

        $md = $this->export();

        $this->assertStringContainsString('* Open task', $md);
        $this->assertStringContainsString('* Finished task', $md);
        $this->assertStringContainsString('* Old task', $md);
    }

    public function test_ids_narrow_to_visible_tasks_and_subtasks(): void
    {
        $open = $this->task('Open task');
        $shownChild = $this->task('Shown subtask', 'incomplete', $open);
        $this->task('Folded subtask', 'incomplete', $open);
        $this->task('Finished task', 'done');

        $md = $this->export(['ids' => [$open->id, $shownChild->id]]);

        $this->assertStringContainsString('* Open task', $md);
        $this->assertStringContainsString('    * Shown subtask', $md);
        $this->assertStringNotContainsString('Folded subtask', $md);
        $this->assertStringNotContainsString('Finished task', $md);
    }

    public function test_empty_ids_exports_no_tasks(): void
    {
        $this->task('Open task');

        $md = $this->export(['ids' => '']);

        $this->assertStringContainsString('# My Project', $md);
        $this->assertStringNotContainsString('Open task', $md);
    }

    public function test_ids_cannot_widen_to_other_projects(): void
    {
        $other = Project::create(['name' => 'Other', 'user_id' => $this->user->id, 'status' => 'incomplete']);
        $foreign = Task::create([
            'name' => 'Foreign task', 'creator_id' => $this->user->id,
            'project_id' => $other->id, 'status' => 'incomplete',
        ]);

        $md = $this->export(['ids' => [$foreign->id]]);

        $this->assertStringNotContainsString('Foreign task', $md);
    }
}
