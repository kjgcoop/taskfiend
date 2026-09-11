<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Search page used to have its own hand-rolled JS regex tokenizer for
 * #project/@tag tokens, which only matched a project/tag whose slug had no
 * hyphens removed from a multi-word name in exactly the way the JS expected
 * — and drifted from the canonical QuickAddParser matching used everywhere
 * else. Token resolution now happens exactly once, server-side, via
 * SearchController::parseTokens() (backed by QuickAddParser), and the
 * client just applies whatever the server resolved.
 */
class SearchParseTokensTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_matches_multi_word_project_name_via_hyphenated_slug(): void
    {
        $project = Project::create([
            'name'    => 'Home Renovation',
            'user_id' => $this->user->id,
            'status'  => 'incomplete',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('search.parseTokens', ['input' => 'fix the roof #home-renovation']));

        $response->assertOk()->assertJson([
            'query'        => 'fix the roof',
            'project_id'   => (string) $project->id,
            'project_name' => 'Home Renovation',
        ]);
    }

    public function test_matches_multi_word_tag_name_via_hyphenated_slug(): void
    {
        $tag = Tag::create([
            'tag_name' => 'Long Tag Name',
            'color'    => '#ff0000',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson(route('search.parseTokens', ['input' => 'call mom @long-tag-name']));

        $response->assertOk()->assertJson([
            'query' => 'call mom',
        ]);
        $this->assertSame([$tag->id], $response->json('tag_ids'));
    }

    public function test_combines_project_and_tag_tokens_with_plain_text(): void
    {
        $project = Project::create([
            'name'    => 'Home Renovation',
            'user_id' => $this->user->id,
            'status'  => 'incomplete',
        ]);
        $tag = Tag::create(['tag_name' => 'Urgent', 'color' => '#ff0000']);

        $response = $this->actingAs($this->user)
            ->getJson(route('search.parseTokens', ['input' => 'Buy milk #home-renovation @urgent']));

        $response->assertOk()->assertJson([
            'query'      => 'Buy milk',
            'project_id' => (string) $project->id,
        ]);
        $this->assertSame([$tag->id], $response->json('tag_ids'));
    }

    public function test_inbox_token_resolves_to_inbox_sentinel(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('search.parseTokens', ['input' => 'errands #inbox']));

        $response->assertOk()->assertJson([
            'query'      => 'errands',
            'project_id' => 'inbox',
        ]);
    }

    public function test_unmatched_project_token_stays_in_query_text(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('search.parseTokens', ['input' => 'foo #notaproject']));

        $response->assertOk()->assertJson([
            'query'      => 'foo #notaproject',
            'project_id' => 'none',
        ]);
    }

    public function test_empty_input_returns_empty_result(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson(route('search.parseTokens', ['input' => '']));

        $response->assertOk()->assertJson([
            'query'      => '',
            'project_id' => 'none',
            'tag_ids'    => [],
        ]);
    }
}
