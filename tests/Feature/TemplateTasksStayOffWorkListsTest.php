<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Template tasks are blueprints, not work: they must stay off the lists of the
 * user's real work (undated, all, calendar counts, day done/archived groups,
 * tag pages, API) and templates can't become the default project.
 */
class TemplateTasksStayOffWorkListsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $normal;
    private Project $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->normal = Project::create([
            'name' => 'Real Project', 'user_id' => $this->user->id,
            'status' => 'incomplete', 'is_default' => true,
        ]);
        $this->template = Project::create([
            'name' => 'Blueprint Project', 'user_id' => $this->user->id,
            'status' => 'incomplete', 'project_type' => Project::TYPE_TEMPLATE,
        ]);
    }

    private function task(Project $project, string $name, array $extra = []): Task
    {
        $task = Task::create(array_merge([
            'name' => $name, 'creator_id' => $this->user->id,
            'project_id' => $project->id, 'status' => 'incomplete',
        ], $extra));
        $task->assignments()->firstOrCreate(['assignee_id' => $this->user->id], ['assigned_by_id' => $this->user->id]);

        return $task;
    }

    public function test_undated_and_all_pages_omit_template_tasks(): void
    {
        $this->task($this->normal, 'Zebra Real Task');
        $this->task($this->template, 'Zebra Template Task');

        foreach (['/undated', '/all-tasks'] as $url) {
            $this->actingAs($this->user)->get($url)
                ->assertOk()
                ->assertSee('Zebra Real Task')
                ->assertDontSee('Zebra Template Task');
        }
    }

    public function test_calendar_counts_omit_template_tasks(): void
    {
        $this->task($this->normal, 'Real Undated');
        $this->task($this->template, 'Template One');
        $this->task($this->template, 'Template Two');

        $response = $this->actingAs($this->user)->get('/calendar');

        $response->assertOk();
        $this->assertSame(1, $response->viewData('undatedCount'));
    }

    public function test_day_done_and_archived_groups_omit_template_tasks(): void
    {
        $today = today()->format('Y-m-d');
        foreach (['done', 'archived'] as $status) {
            $this->task($this->normal, "Real {$status} Task", ['status' => $status, 'completed_at' => now()]);
            $this->task($this->template, "Template {$status} Task", ['status' => $status, 'completed_at' => now()]);
        }

        $this->actingAs($this->user)->get("/day?date={$today}")
            ->assertOk()
            ->assertSee('Real done Task')
            ->assertSee('Real archived Task')
            ->assertDontSee('Template done Task')
            ->assertDontSee('Template archived Task');

        $this->actingAs($this->user)->getJson("/day/completed-tasks?date={$today}")
            ->assertOk()
            ->assertJsonPath('html', fn ($html) => str_contains($html, 'Real done Task') && !str_contains($html, 'Template done Task'));

        $this->actingAs($this->user)->getJson("/day/archived-tasks?date={$today}")
            ->assertOk()
            ->assertJsonPath('html', fn ($html) => str_contains($html, 'Real archived Task') && !str_contains($html, 'Template archived Task'));
    }

    public function test_tag_page_and_counts_omit_template_tasks(): void
    {
        $tag = Tag::create(['tag_name' => 'shared-tag', 'color' => '#ff0000']);
        $this->task($this->normal, 'Real Tagged Task')->tags()->attach($tag->id);
        $this->task($this->template, 'Template Tagged Task')->tags()->attach($tag->id);

        $this->actingAs($this->user)->get(route('tags.show', $tag))
            ->assertOk()
            ->assertSee('Real Tagged Task')
            ->assertDontSee('Template Tagged Task');

        $counted = $this->actingAs($this->user)->get(route('tags.index'))->viewData('tags')->firstWhere('id', $tag->id);
        $this->assertSame(1, $counted->tasks_count);
    }

    public function test_api_completed_on_day_omits_template_tasks(): void
    {
        $this->task($this->normal, 'Real Done Task', ['status' => 'done']);
        $this->task($this->template, 'Template Done Task', ['status' => 'done']);

        $plainKey = 'tfk_' . Str::random(40);
        ApiKey::create([
            'user_id'        => $this->user->id,
            'key_hash'       => Hash::make($plainKey),
            'key_prefix'     => substr($plainKey, 0, 12),
            'invalidated_at' => null,
        ]);

        $names = $this->withToken($plainKey)
            ->getJson('/api/tasks/completed/' . today()->format('Y-m-d'))
            ->assertOk()
            ->json('done.*.name');

        $this->assertSame(['Real Done Task'], $names);
    }

    public function test_template_cannot_be_set_as_default_project(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('projects.setDefault', $this->template))
            ->assertStatus(422);

        $this->assertTrue($this->normal->fresh()->is_default);
        $this->assertFalse($this->template->fresh()->is_default);
    }

    public function test_template_cannot_be_hearted(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('projects.toggleHeart', $this->template))
            ->assertStatus(422);

        $this->assertFalse((bool) $this->template->fresh()->is_hearted);
    }

    public function test_bulk_update_skips_done_and_archived_tasks(): void
    {
        $open     = $this->task($this->normal, 'Open Task');
        $done     = $this->task($this->normal, 'Done Task', ['status' => 'done']);
        $archived = $this->task($this->normal, 'Archived Task', ['status' => 'archived']);

        $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', [
                'task_ids' => [$open->id, $done->id, $archived->id],
                'location' => 'Garage',
            ])
            ->assertOk()
            ->assertJson(['success' => true, 'updated' => 1, 'skipped' => 2]);

        $this->assertSame('Garage', $open->fresh()->location);
        $this->assertNull($done->fresh()->location);
        $this->assertNull($archived->fresh()->location);
    }

    public function test_bulk_update_of_only_closed_tasks_changes_nothing(): void
    {
        $done = $this->task($this->normal, 'Done Task', ['status' => 'done']);

        $this->actingAs($this->user)
            ->postJson('/tasks/bulk-update', ['task_ids' => [$done->id], 'location' => 'Garage'])
            ->assertOk()
            ->assertJson(['success' => true, 'updated' => 0, 'skipped' => 1]);

        $this->assertNull($done->fresh()->location);
    }

    public function test_done_tasks_get_no_bulk_select_checkbox(): void
    {
        $this->task($this->normal, 'Open Task');
        $this->task($this->normal, 'Closed Task', ['status' => 'done']);

        $html = $this->actingAs($this->user)->get('/all-tasks')->assertOk()->getContent();

        // One selector (the open task's); the done task's row is flagged closed.
        $this->assertSame(1, substr_count($html, 'title="Select task"'));
        $this->assertSame(1, substr_count($html, 'data-task-closed'));
    }

    public function test_template_locations_are_not_suggested(): void
    {
        $this->task($this->normal, 'Real Task', ['location' => 'Garage']);
        $this->task($this->template, 'Template Task', ['location' => 'Secret Lair']);

        $this->assertSame(['Garage'], Task::locationSuggestionsFor($this->user->id)->all());

        foreach (['/undated', '/all-tasks', '/search'] as $url) {
            $this->actingAs($this->user)->get($url)
                ->assertOk()
                ->assertDontSee('Secret Lair');
        }
    }

    public function test_quick_add_location_token_ignores_template_locations(): void
    {
        $this->task($this->template, 'Template Task', ['location' => 'Secret Lair']);

        $tokens = (new \App\Services\QuickAddParser($this->user->id))->parse('Buy milk +secretlair');

        $this->assertSame('secretlair', $tokens->location);
    }
}
