<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Services\QuickAddParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickAddParserTest extends TestCase
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

    private function parse(string $input, ...$args)
    {
        return (new QuickAddParser($this->user->id))->parse($input, ...$args);
    }

    // ── #project ────────────────────────────────────────────────────────────

    public function test_project_token_matches_exact_name(): void
    {
        $tokens = $this->parse('Buy milk #myproject');

        $this->assertSame('Buy milk', $tokens->name);
        $this->assertSame($this->project->id, $tokens->project?->id);
        $this->assertTrue($tokens->projectResolved);
    }

    public function test_project_token_matches_hyphenated_slug(): void
    {
        $tokens = $this->parse('Buy milk #my-project');

        $this->assertSame($this->project->id, $tokens->project?->id);
        $this->assertSame('Buy milk', $tokens->name);
    }

    public function test_project_token_matches_name_with_apostrophe(): void
    {
        Project::create(['name' => "KJ's Inbox", 'user_id' => $this->user->id, 'status' => 'incomplete']);

        $tokens = $this->parse('Buy milk #kjsinbox');

        $this->assertSame("KJ's Inbox", $tokens->project?->name);
    }

    public function test_unmatched_project_token_stays_in_name(): void
    {
        $tokens = $this->parse('Buy milk #nonexistent');

        $this->assertSame('Buy milk #nonexistent', $tokens->name);
        $this->assertNull($tokens->project);
        $this->assertFalse($tokens->projectResolved);
    }

    public function test_inactive_project_does_not_match(): void
    {
        $this->project->update(['status' => 'archived']);

        $tokens = $this->parse('Buy milk #myproject');

        $this->assertNull($tokens->project);
        $this->assertSame('Buy milk #myproject', $tokens->name);
    }

    public function test_inaccessible_project_does_not_match(): void
    {
        $other = User::factory()->create();
        Project::create(['name' => 'Secret', 'user_id' => $other->id, 'status' => 'incomplete']);

        $tokens = $this->parse('Buy milk #secret');

        $this->assertNull($tokens->project);
    }

    public function test_pre_resolved_project_skips_lookup_but_strips_token(): void
    {
        $tokens = $this->parse('Buy milk #whatever', projectPreResolved: true);

        $this->assertSame('Buy milk', $tokens->name);
        $this->assertNull($tokens->project);
        $this->assertTrue($tokens->projectResolved);
    }

    // ── @tag ────────────────────────────────────────────────────────────────

    public function test_tag_tokens_match_and_strip(): void
    {
        $tag1 = Tag::create(['tag_name' => 'errands', 'color' => '#ff0000']);
        $tag2 = Tag::create(['tag_name' => '5 minutes', 'color' => '#00ff00']);

        $tokens = $this->parse('Buy milk @errands @5minutes');

        $this->assertSame('Buy milk', $tokens->name);
        $this->assertEqualsCanonicalizing([$tag1->id, $tag2->id], $tokens->tagIds);
        $this->assertSame(['errands', '5 minutes'], $tokens->tagNames);
    }

    public function test_tag_token_matches_hyphenated_multiword_name(): void
    {
        $tag = Tag::create(['tag_name' => 'Long Tag', 'color' => '#0000ff']);

        $tokens = $this->parse('Buy milk @long-tag');

        $this->assertSame([$tag->id], $tokens->tagIds);
        $this->assertSame('Buy milk', $tokens->name);
    }

    public function test_unmatched_tag_token_stays_in_name(): void
    {
        $tokens = $this->parse('Email @bob about the thing');

        $this->assertSame('Email @bob about the thing', $tokens->name);
        $this->assertSame([], $tokens->tagIds);
    }

    // ── +location ───────────────────────────────────────────────────────────

    public function test_plain_location_token(): void
    {
        $tokens = $this->parse('Buy milk +store');

        $this->assertSame('Buy milk', $tokens->name);
        $this->assertSame('store', $tokens->location);
        $this->assertFalse($tokens->showMap);
    }

    public function test_quoted_double_plus_location_sets_show_map(): void
    {
        $tokens = $this->parse('Dentist ++"123 Main St, Town"');

        $this->assertSame('Dentist', $tokens->name);
        $this->assertSame('123 Main St, Town', $tokens->location);
        $this->assertTrue($tokens->showMap);
    }

    public function test_location_resolves_to_existing_canonical_value(): void
    {
        Task::create([
            'name'       => 'Existing',
            'creator_id' => $this->user->id,
            'project_id' => $this->project->id,
            'status'     => 'incomplete',
            'location'   => 'Coffee Shop',
        ]);

        $tokens = $this->parse('Meet Ana +coffee-shop');

        $this->assertSame('Coffee Shop', $tokens->location);
    }

    public function test_location_does_not_resolve_against_other_users_tasks(): void
    {
        $other = User::factory()->create();
        $otherProject = Project::create(['name' => 'Other', 'user_id' => $other->id, 'status' => 'incomplete']);
        Task::create([
            'name'       => 'Foreign',
            'creator_id' => $other->id,
            'project_id' => $otherProject->id,
            'status'     => 'incomplete',
            'location'   => 'Secret Lair',
        ]);

        $tokens = $this->parse('Meet Ana +secret-lair');

        $this->assertSame('secret-lair', $tokens->location);
    }

    public function test_mid_word_plus_is_not_a_location(): void
    {
        $tokens = $this->parse('Solve 5+3 worksheet');

        $this->assertNull($tokens->location);
        $this->assertSame('Solve 5+3 worksheet', $tokens->name);
    }

    public function test_location_not_parsed_when_disabled(): void
    {
        $tokens = $this->parse('Buy milk +store', parseLocation: false);

        $this->assertNull($tokens->location);
        $this->assertSame('Buy milk +store', $tokens->name);
    }

    // ── &user ───────────────────────────────────────────────────────────────

    public function test_assignee_token_matches_user_and_strips_typed_token(): void
    {
        $ana = User::factory()->create(['name' => 'Ana Torres']);

        // "&ana" is a prefix match for "Ana Torres" — the typed token (not a
        // slug re-derived from the full name) must be stripped from the title.
        $tokens = $this->parse('Review doc &ana');

        $this->assertSame('Review doc', $tokens->name);
        $this->assertSame([$ana->id], $tokens->assigneeIds);
        $this->assertSame(['Ana Torres'], $tokens->assigneeNames);
        $this->assertSame([], $tokens->unknownAssignees);
    }

    public function test_unknown_assignee_token_stays_in_name_and_is_reported(): void
    {
        $tokens = $this->parse('Review doc &nobody');

        $this->assertSame('Review doc &nobody', $tokens->name);
        $this->assertSame([], $tokens->assigneeIds);
        $this->assertSame(['&nobody'], $tokens->unknownAssignees);
    }

    public function test_disabled_users_do_not_match_assignee_tokens(): void
    {
        User::factory()->create(['name' => 'Gone User', 'email_enabled_at' => now()]);

        $tokens = $this->parse('Review doc &goneuser');

        $this->assertSame([], $tokens->assigneeIds);
        $this->assertSame(['&goneuser'], $tokens->unknownAssignees);
    }

    public function test_assignees_not_parsed_when_disabled(): void
    {
        User::factory()->create(['name' => 'Ana Torres']);

        $tokens = $this->parse('Review doc &ana', parseAssignees: false);

        $this->assertSame('Review doc &ana', $tokens->name);
        $this->assertSame([], $tokens->assigneeIds);
    }

    // ── combined ────────────────────────────────────────────────────────────

    public function test_all_token_types_combined(): void
    {
        $tag = Tag::create(['tag_name' => 'errands', 'color' => '#ff0000']);
        $ana = User::factory()->create(['name' => 'Ana Torres']);

        $tokens = $this->parse('Buy milk #myproject @errands +store &ana');

        $this->assertSame('Buy milk', $tokens->name);
        $this->assertSame($this->project->id, $tokens->project?->id);
        $this->assertSame([$tag->id], $tokens->tagIds);
        $this->assertSame('store', $tokens->location);
        $this->assertSame([$ana->id], $tokens->assigneeIds);
    }

    public function test_whitespace_collapsed_after_token_removal(): void
    {
        Tag::create(['tag_name' => 'errands', 'color' => '#ff0000']);

        $tokens = $this->parse('Buy @errands milk');

        $this->assertSame('Buy milk', $tokens->name);
    }
}
