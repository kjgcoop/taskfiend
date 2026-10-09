<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateDatelessTasksTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        Project::create(['name' => 'Inbox', 'user_id' => $this->owner->id, 'is_default' => true]);
    }

    private function makeProject(string $type): Project
    {
        $project = Project::create([
            'name' => 'Kickoff', 'user_id' => $this->owner->id, 'project_type' => $type,
        ]);
        $project->assignees()->sync([$this->owner->id]);

        return $project;
    }

    private function makeTask(Project $project): Task
    {
        $task = Task::create([
            'name' => 'A Task', 'creator_id' => $this->owner->id,
            'project_id' => $project->id, 'status' => 'incomplete',
        ]);
        $task->assignees()->sync([$this->owner->id => ['assigned_by_id' => $this->owner->id]]);

        return $task;
    }

    public function test_store_nulls_date_and_time_on_template(): void
    {
        $template = $this->makeProject(Project::TYPE_TEMPLATE);

        $this->actingAs($this->owner)->post(route('tasks.store'), [
            'name' => 'Dated', 'project_id' => $template->id,
            'date' => now()->addDays(3)->format('Y-m-d'), 'time' => '10:30',
        ])->assertSessionHasNoErrors();

        $task = Task::where('name', 'Dated')->firstOrFail();
        $this->assertNull($task->getRawOriginal('date'));
        $this->assertNull($task->time);
    }

    public function test_store_keeps_date_on_normal_project(): void
    {
        $normal = $this->makeProject(Project::TYPE_NORMAL);
        $date = now()->addDays(3)->format('Y-m-d');

        $this->actingAs($this->owner)->post(route('tasks.store'), [
            'name' => 'Dated', 'project_id' => $normal->id, 'date' => $date, 'time' => '10:30',
        ]);

        $task = Task::where('name', 'Dated')->firstOrFail();
        $this->assertSame($date, $task->getRawOriginal('date'));
    }

    public function test_update_and_update_field_null_date_on_template(): void
    {
        $template = $this->makeProject(Project::TYPE_TEMPLATE);
        $task = $this->makeTask($template);
        $date = now()->addDays(3)->format('Y-m-d');

        $this->actingAs($this->owner)->put(route('tasks.update', $task), [
            'name' => 'A Task', 'date' => $date, 'time' => '09:00',
        ]);
        $this->assertNull($task->fresh()->getRawOriginal('date'));
        $this->assertNull($task->fresh()->time);

        $this->actingAs($this->owner)->postJson(route('tasks.updateField', $task), [
            'field' => 'date', 'value' => $date,
        ]);
        $this->assertNull($task->fresh()->getRawOriginal('date'));
    }

    public function test_bulk_update_date_ignored_on_template_tasks(): void
    {
        $template = $this->makeProject(Project::TYPE_TEMPLATE);
        $task = $this->makeTask($template);

        $this->actingAs($this->owner)->postJson(route('tasks.bulkUpdate'), [
            'task_ids' => [$task->id], 'date' => now()->addDay()->format('Y-m-d'),
        ]);
        $this->assertNull($task->fresh()->getRawOriginal('date'));
    }

    public function test_task_pages_show_dateless_note_only_for_templates(): void
    {
        $template = $this->makeProject(Project::TYPE_TEMPLATE);
        $normal = $this->makeProject(Project::TYPE_NORMAL);
        $t = $this->makeTask($template);
        $n = $this->makeTask($normal);

        foreach (['tasks.show', 'tasks.panel', 'tasks.edit'] as $route) {
            $this->actingAs($this->owner)->get(route($route, $t))
                ->assertOk()
                ->assertSee('data-dateless-note', false)
                ->assertSee("Templates don't have dates", false);
            $this->actingAs($this->owner)->get(route($route, $n))
                ->assertOk()->assertDontSee('data-dateless-note', false);
        }
    }

    public function test_create_form_shows_dateless_note_for_preselected_template(): void
    {
        $template = $this->makeProject(Project::TYPE_TEMPLATE);
        $normal = $this->makeProject(Project::TYPE_NORMAL);

        $this->actingAs($this->owner)->get(route('tasks.create', ['project_id' => $template->id]))
            ->assertOk()->assertSee('data-dateless-note', false);
        $this->actingAs($this->owner)->get(route('tasks.create', ['project_id' => $normal->id]))
            ->assertOk()->assertDontSee('data-dateless-note', false);
    }
}
