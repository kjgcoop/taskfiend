<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for GET /overdue/export-markdown (DashboardController::exportOverdueMarkdown)
 *
 * Covers: authentication, base overdue scoping (mirrors the Overdue page's own query), and the
 * 'ids[]' narrowing mechanism that lets the export mirror whatever the on-page text filter
 * currently shows — same pattern as the Today page's exports (see
 * DashboardController::dayExportTaskGroups()).
 */
class OverdueExportMarkdownTest extends TestCase
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

    private function createOverdueTask(array $overrides = []): Task
    {
        return Task::create(array_merge([
            'name'       => 'Overdue Task',
            'creator_id' => $this->user->id,
            'project_id' => $this->project->id,
            'status'     => 'incomplete',
            'date'       => today()->subDays(2)->format('Y-m-d'),
        ], $overrides));
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get('/overdue/export-markdown');

        $response->assertRedirect('/login');
    }

    /** Catches Blade syntax errors in the Export MD button's markup/script. */
    public function test_overdue_page_renders_with_export_button(): void
    {
        $response = $this->actingAs($this->user)->get('/overdue');

        $response->assertOk();
        $response->assertSee('Export MD', false);
    }

    public function test_export_without_ids_includes_all_overdue_tasks(): void
    {
        $one = $this->createOverdueTask(['name' => 'Pay rent']);
        $two = $this->createOverdueTask(['name' => 'File taxes']);

        $response = $this->actingAs($this->user)->get('/overdue/export-markdown');

        $response->assertOk();
        $response->assertSee('Pay rent', false);
        $response->assertSee('File taxes', false);
    }

    public function test_export_excludes_other_users_tasks(): void
    {
        $other = User::factory()->create();
        $otherProject = Project::create([
            'name'    => 'Other Project',
            'user_id' => $other->id,
            'status'  => 'incomplete',
        ]);
        Task::create([
            'name'       => 'Not mine',
            'creator_id' => $other->id,
            'project_id' => $otherProject->id,
            'status'     => 'incomplete',
            'date'       => today()->subDays(1)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->user)->get('/overdue/export-markdown');

        $response->assertOk();
        $response->assertDontSee('Not mine', false);
    }

    public function test_ids_param_narrows_export_to_only_those_tasks(): void
    {
        $keep = $this->createOverdueTask(['name' => 'Keep me']);
        $drop = $this->createOverdueTask(['name' => 'Drop me']);

        $response = $this->actingAs($this->user)->get('/overdue/export-markdown?' . http_build_query(['ids' => [$keep->id]]));

        $response->assertOk();
        $response->assertSee('Keep me', false);
        $response->assertDontSee('Drop me', false);
    }

    public function test_ids_param_cannot_widen_export_beyond_authorized_scope(): void
    {
        $other = User::factory()->create();
        $otherProject = Project::create([
            'name'    => 'Other Project',
            'user_id' => $other->id,
            'status'  => 'incomplete',
        ]);
        $othersTask = Task::create([
            'name'       => 'Not mine',
            'creator_id' => $other->id,
            'project_id' => $otherProject->id,
            'status'     => 'incomplete',
            'date'       => today()->subDays(1)->format('Y-m-d'),
        ]);
        $notOverdue = $this->createOverdueTask(['name' => 'Future task', 'date' => today()->addDays(3)->format('Y-m-d')]);

        $response = $this->actingAs($this->user)->get('/overdue/export-markdown?' . http_build_query([
            'ids' => [$othersTask->id, $notOverdue->id],
        ]));

        $response->assertOk();
        $response->assertDontSee('Not mine', false);
        $response->assertDontSee('Future task', false);
    }

    public function test_empty_ids_param_exports_nothing(): void
    {
        $this->createOverdueTask(['name' => 'Should be excluded']);

        $response = $this->actingAs($this->user)->get('/overdue/export-markdown?ids=');

        $response->assertOk();
        $response->assertDontSee('Should be excluded', false);
    }
}
