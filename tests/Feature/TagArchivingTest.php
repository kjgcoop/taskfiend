<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for tag archiving.
 *
 * Covers: archive/unarchive actions, active/archived scopes, tag pickers excluding
 * archived tags, task views hiding archived tags, and the archived-tag-on-save
 * data-loss guard in TaskController::syncTagsPreservingArchived().
 */
class TagArchivingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Project $project;
    private Tag $activeTag;
    private Tag $archivedTag;
    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user    = User::factory()->create();
        $this->project = Project::create([
            'name'    => 'Test Project',
            'user_id' => $this->user->id,
            'status'  => 'incomplete',
        ]);

        $this->activeTag   = Tag::create(['tag_name' => 'active-tag', 'color' => '#3B82F6']);
        $this->archivedTag = Tag::create(['tag_name' => 'archived-tag', 'color' => '#EF4444', 'archived_at' => now()]);

        $this->task = Task::create([
            'name'        => 'Tagged task',
            'creator_id'  => $this->user->id,
            'project_id'  => $this->project->id,
            'status'      => 'incomplete',
        ]);
        $this->task->tags()->sync([$this->activeTag->id, $this->archivedTag->id]);
    }

    public function test_archive_action_sets_archived_at()
    {
        $tag = Tag::create(['tag_name' => 'to-archive', 'color' => '#10B981']);

        $this->actingAs($this->user)
            ->post(route('tags.archive', $tag))
            ->assertRedirect(route('tags.show', $tag));

        $this->assertNotNull($tag->fresh()->archived_at);
    }

    public function test_unarchive_action_clears_archived_at()
    {
        $this->actingAs($this->user)
            ->post(route('tags.unarchive', $this->archivedTag))
            ->assertRedirect(route('tags.show', $this->archivedTag));

        $this->assertNull($this->archivedTag->fresh()->archived_at);
    }

    public function test_active_and_archived_scopes_partition_tags()
    {
        $this->assertTrue(Tag::active()->pluck('id')->contains($this->activeTag->id));
        $this->assertFalse(Tag::active()->pluck('id')->contains($this->archivedTag->id));

        $this->assertTrue(Tag::archived()->pluck('id')->contains($this->archivedTag->id));
        $this->assertFalse(Tag::archived()->pluck('id')->contains($this->activeTag->id));
    }

    public function test_tag_index_lists_archived_tags_separately()
    {
        $response = $this->actingAs($this->user)->get(route('tags.index'));

        $response->assertOk();
        $response->assertViewHas('tags', fn ($tags) => !$tags->pluck('id')->contains($this->archivedTag->id));
        $response->assertViewHas('archivedTags', fn ($tags) => $tags->pluck('id')->contains($this->archivedTag->id));
    }

    public function test_task_create_and_edit_forms_only_offer_active_tags()
    {
        $create = $this->actingAs($this->user)->get(route('tasks.create'));
        $create->assertViewHas('tags', fn ($tags) => !$tags->pluck('id')->contains($this->archivedTag->id));

        $edit = $this->actingAs($this->user)->get(route('tasks.edit', $this->task));
        $edit->assertViewHas('tags', fn ($tags) => !$tags->pluck('id')->contains($this->archivedTag->id));
    }

    public function test_task_visible_tags_excludes_archived_but_underlying_relation_keeps_it()
    {
        $this->task->load('tags');

        $this->assertCount(2, $this->task->tags);
        $this->assertCount(1, $this->task->visibleTags());
        $this->assertSame($this->activeTag->id, $this->task->visibleTags()->first()->id);
    }

    public function test_saving_task_without_resubmitting_archived_tag_does_not_detach_it()
    {
        // Simulates the edit form: the archived tag never renders as a checkbox, so the
        // client can only ever submit the active tag it was shown.
        $this->actingAs($this->user)->put(route('tasks.update', $this->task), [
            'name'       => $this->task->name,
            'project_id' => $this->project->id,
            'status'     => 'incomplete',
            'tag_ids'    => [$this->activeTag->id],
        ])->assertRedirect();

        $ids = $this->task->fresh()->tags->pluck('id')->sort()->values()->toArray();
        $this->assertEqualsCanonicalizing([$this->activeTag->id, $this->archivedTag->id], $ids);
    }

    public function test_explicitly_removing_all_tags_still_clears_active_tags()
    {
        // The guard preserves already-attached archived tags, but must not prevent removing
        // active tags the user did uncheck.
        $this->actingAs($this->user)->put(route('tasks.update', $this->task), [
            'name'       => $this->task->name,
            'project_id' => $this->project->id,
            'status'     => 'incomplete',
            'tag_ids'    => [],
        ])->assertRedirect();

        $ids = $this->task->fresh()->tags->pluck('id')->toArray();
        $this->assertEqualsCanonicalizing([$this->archivedTag->id], $ids);
    }

    public function test_quick_add_at_token_does_not_match_archived_tag()
    {
        $tokens = (new \App\Services\QuickAddParser($this->user->id))->parse('Buy milk @archived-tag');

        $this->assertEmpty($tokens->tagIds);
        $this->assertStringContainsString('@archived-tag', $tokens->name);
    }
}
