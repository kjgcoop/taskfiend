<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Services\QuickAddParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Template projects (project_type = 'template') must never be offered where
 * a normal project would be: lists, pickers, quick-add tokens, move targets.
 */
class TemplateExcludedFromPickersTest extends TestCase
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
            'name' => 'Normal One', 'user_id' => $this->user->id, 'is_default' => true,
        ]);
        $this->template = Project::create([
            'name' => 'Template One', 'user_id' => $this->user->id,
            'project_type' => Project::TYPE_TEMPLATE,
        ]);
    }

    public function test_scopes_exclude_templates(): void
    {
        $active = Project::activeForUser($this->user->id)->pluck('id');
        $this->assertTrue($active->contains($this->normal->id));
        $this->assertFalse($active->contains($this->template->id));
        $this->assertTrue(Project::forMember($this->user->id)->pluck('id')->contains($this->normal->id));
        $this->assertFalse(Project::forMember($this->user->id)->pluck('id')->contains($this->template->id));
    }

    public function test_project_index_excludes_templates(): void
    {
        $r = $this->actingAs($this->user)->get('/projects')->assertOk();
        $ids = $r->viewData('projects')->pluck('id')->all();
        $this->assertContains($this->normal->id, $ids);
        $this->assertNotContains($this->template->id, $ids);
    }

    public function test_tag_show_picker_excludes_templates(): void
    {
        $tag = Tag::create(['tag_name' => 'x', 'color' => '#ff0000']);
        $r = $this->actingAs($this->user)->get("/tags/{$tag->id}")->assertOk();
        $this->assertNotContains($this->template->id, $r->viewData('projects')->pluck('id')->all());
    }

    public function test_quick_add_hash_token_does_not_match_template(): void
    {
        $tokens = (new QuickAddParser($this->user->id))->parse('I like #template-one');
        $this->assertNull($tokens->project);
        $this->assertSame('I like #template-one', $tokens->name);
    }

    public function test_moving_task_into_template_is_rejected(): void
    {
        $task = Task::create([
            'name' => 'T', 'creator_id' => $this->user->id,
            'project_id' => $this->normal->id, 'status' => 'incomplete',
        ]);
        Assignment::create([
            'task_id' => $task->id, 'assignee_id' => $this->user->id, 'assigned_by_id' => $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->postJson("/tasks/{$task->id}/update-field", ['field' => 'project_id', 'value' => $this->template->id])
            ->assertStatus(422);
        $this->assertSame($this->normal->id, $task->fresh()->project_id);
    }
}
