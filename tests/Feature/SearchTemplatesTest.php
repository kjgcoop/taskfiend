<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Template tasks are excluded from search unless the "Templates" box
 * (search_templates) is checked. It is unchecked on a fresh page load, and
 * the header quick search never sends it.
 */
class SearchTemplatesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();

        $template = Project::create([
            'name'         => 'Template Project',
            'user_id'      => $this->user->id,
            'status'       => 'incomplete',
            'project_type' => Project::TYPE_TEMPLATE,
        ]);
        Task::create([
            'name' => 'Zebra Template Task', 'creator_id' => $this->user->id,
            'project_id' => $template->id, 'status' => 'incomplete',
        ]);

        $project = Project::create([
            'name' => 'Real Project', 'user_id' => $this->user->id, 'status' => 'incomplete',
        ]);
        Task::create([
            'name' => 'Zebra Real Task', 'creator_id' => $this->user->id,
            'project_id' => $project->id, 'status' => 'incomplete',
        ]);
    }

    private function search(array $extra = [])
    {
        return $this->actingAs($this->user)->get(route('search', array_merge([
            'q'                  => 'Zebra',
            'search_title'       => '1',
            'show_incomplete'    => '1',
        ], $extra)));
    }

    public function test_templates_are_excluded_by_default(): void
    {
        $this->search()->assertOk()->assertSee('Zebra Real Task')->assertDontSee('Zebra Template Task');
    }

    public function test_templates_are_included_when_checked(): void
    {
        $this->search(['search_templates' => '1'])
            ->assertOk()->assertSee('Zebra Real Task')->assertSee('Zebra Template Task');
    }

    public function test_checkbox_is_unchecked_on_initial_load_and_remembered_after_search(): void
    {
        $this->actingAs($this->user)->get(route('search'))
            ->assertOk()->assertDontSee('name="search_templates" value="1" checked', false);

        $this->search(['search_templates' => '1'])
            ->assertSee('name="search_templates" value="1" checked', false);
    }

    public function test_templates_only_returns_only_template_tasks(): void
    {
        $this->actingAs($this->user)->get(route('search', [
            'q'                => 'Zebra',
            'search_templates' => '1',
            'show_incomplete'  => '1',
        ]))->assertOk()
            ->assertDontSee('Choose at least one field')
            ->assertSee('Zebra Template Task')
            ->assertDontSee('Zebra Real Task');
    }

    public function test_templates_only_applies_the_search_text_to_template_tasks(): void
    {
        $this->actingAs($this->user)->get(route('search', [
            'q'                => 'Nomatch',
            'search_templates' => '1',
            'show_incomplete'  => '1',
        ]))->assertOk()->assertDontSee('Zebra Template Task');
    }
}
