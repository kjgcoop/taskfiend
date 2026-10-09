<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TemplateDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private function makeTemplate(User $owner, User $other): Project
    {
        $template = Project::create([
            'name' => 'Tpl', 'user_id' => $owner->id, 'status' => 'incomplete',
            'project_type' => Project::TYPE_TEMPLATE, 'is_public' => true,
        ]);
        $template->assignees()->sync([$owner->id, $other->id]);
        $parent = Task::create([
            'name' => 'Parent', 'creator_id' => $owner->id, 'project_id' => $template->id,
            'status' => 'incomplete', 'date' => '2026-01-05', 'time' => '09:00',
        ]);
        $parent->assignments()->create(['assignee_id' => $owner->id, 'assigned_by_id' => $owner->id]);
        $child = Task::create([
            'name' => 'Child', 'creator_id' => $owner->id, 'project_id' => $template->id,
            'parent_id' => $parent->id, 'status' => 'incomplete', 'date' => '2026-01-06', 'time' => '10:00',
        ]);
        $child->assignments()->create(['assignee_id' => $other->id, 'assigned_by_id' => $owner->id]);

        return $template;
    }

    public function test_explicit_name_and_acting_user_with_no_auth(): void
    {
        Storage::fake('private');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $template = $this->makeTemplate($owner, $other);

        $copy = $template->duplicate(name: 'My Project', actingUserId: $other->id);

        $this->assertSame('My Project', $copy->name);
        $this->assertSame($other->id, $copy->user_id);
        $this->assertSame(Project::TYPE_NORMAL, $copy->project_type);
        $this->assertFalse($copy->is_public);
        $this->assertSame($template->id, $copy->template_id);
        $this->assertSame([$other->id], $copy->assignees()->pluck('users.id')->all());

        $tasks = $copy->tasks()->get();
        $this->assertCount(2, $tasks);
        foreach ($tasks as $t) {
            $this->assertNull($t->getRawOriginal('date'));
            $this->assertNull($t->time);
            $this->assertSame($other->id, $t->creator_id);
            $this->assertSame([$other->id], $t->assignments()->pluck('assignee_id')->all());
            $this->assertSame($other->id, $t->assignments()->first()->assigned_by_id);
        }
        $this->assertNotNull($tasks->firstWhere('name', 'Child')->parent_id);
    }

    public function test_copy_of_prefix_follows_config(): void
    {
        $u = User::factory()->create();
        $p = Project::create(['name' => 'Plain', 'user_id' => $u->id, 'status' => 'incomplete']);

        $this->assertSame('Copy of Plain', $p->duplicate(actingUserId: $u->id)->name);

        config(['app.duplicate_name_prefix' => false]);
        $this->assertSame('Plain', $p->duplicate(actingUserId: $u->id)->name);
    }

    public function test_project_duplicate_regression_uses_auth_user(): void
    {
        $u = User::factory()->create();
        $p = Project::create(['name' => 'Plain', 'user_id' => $u->id, 'status' => 'incomplete']);
        Task::create(['name' => 'T', 'creator_id' => $u->id, 'project_id' => $p->id, 'status' => 'incomplete', 'date' => '2026-02-02']);
        $this->actingAs($u);

        $copy = $p->duplicate();

        $this->assertSame('Copy of Plain', $copy->name);
        $this->assertSame($u->id, $copy->user_id);
        $this->assertSame('2026-02-02', $copy->tasks()->first()->getRawOriginal('date'));
    }
}
