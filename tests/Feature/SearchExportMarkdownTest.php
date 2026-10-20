<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /search?export=markdown: the whole result set by default, narrowed to the 'ids[]' the page
 * sends while its on-page filter box is active (TaskMarkdownExport::visibleIds()).
 */
class SearchExportMarkdownTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::create(['name' => 'P', 'user_id' => $this->user->id, 'status' => 'incomplete']);
    }

    private function task(string $name): Task
    {
        return Task::create([
            'name' => $name, 'creator_id' => $this->user->id,
            'project_id' => $this->project->id, 'status' => 'incomplete',
        ]);
    }

    private function export(array $extra = []): string
    {
        return $this->actingAs($this->user)->get(route('search', array_merge([
            'q'               => 'widget',
            'search_title'    => '1',
            'show_incomplete' => '1',
            'export'          => 'markdown',
        ], $extra)))->assertOk()->getContent();
    }

    public function test_without_ids_every_match_is_exported(): void
    {
        $this->task('Blue widget');
        $this->task('Red widget');

        $md = $this->export();

        $this->assertStringContainsString('* Blue widget', $md);
        $this->assertStringContainsString('* Red widget', $md);
    }

    public function test_ids_narrow_to_the_rows_left_by_the_page_filter(): void
    {
        $blue = $this->task('Blue widget');
        $this->task('Red widget');

        $md = $this->export(['ids' => [$blue->id]]);

        $this->assertStringContainsString('* Blue widget', $md);
        $this->assertStringNotContainsString('Red widget', $md);
    }

    public function test_empty_ids_exports_nothing_and_cannot_widen_the_search(): void
    {
        $this->task('Blue widget');
        $other = $this->task('Unrelated gadget');

        $this->assertStringNotContainsString('Blue widget', $this->export(['ids' => '']));
        $this->assertStringNotContainsString('Unrelated gadget', $this->export(['ids' => [$other->id]]));
    }
}
