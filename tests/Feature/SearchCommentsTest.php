<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The search page can additionally match on comment text
 * (search_comments), on top of the existing Title/Description checkboxes.
 * Comment matches must never surface a task the searching user can't
 * otherwise see, and if search text is present with none of the three
 * boxes checked, the search must fail with a validation error rather than
 * silently falling back to searching everything.
 */
class SearchCommentsTest extends TestCase
{
    use RefreshDatabase;

    private User $userA;
    private User $userB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->userA = User::factory()->create();
        $this->userB = User::factory()->create();
    }

    private function makeTask(User $creator, string $name = 'Task'): Task
    {
        $project = Project::create([
            'name'    => 'Project ' . uniqid(),
            'user_id' => $creator->id,
            'status'  => 'incomplete',
        ]);

        return Task::create([
            'name'       => $name,
            'creator_id' => $creator->id,
            'project_id' => $project->id,
            'status'     => 'incomplete',
        ]);
    }

    public function test_comment_text_match_finds_task_when_comments_checked(): void
    {
        $task = $this->makeTask($this->userA, 'Unrelated Name');
        $task->comments()->create([
            'user_id' => $this->userA->id,
            'comment' => 'the secret pineapple codeword',
        ]);

        $response = $this->actingAs($this->userA)->get(route('search', [
            'q'                  => 'pineapple',
            'search_title'       => '0',
            'search_description' => '0',
            'search_comments'    => '1',
            'show_incomplete'    => '1',
        ]));

        $response->assertOk();
        $response->assertSee('Unrelated Name');
    }

    public function test_comment_text_match_does_not_leak_another_users_private_task(): void
    {
        $task = $this->makeTask($this->userB, 'Bs Private Task');
        $task->comments()->create([
            'user_id' => $this->userB->id,
            'comment' => 'the secret pineapple codeword',
        ]);

        $response = $this->actingAs($this->userA)->get(route('search', [
            'q'                  => 'pineapple',
            'search_title'       => '0',
            'search_description' => '0',
            'search_comments'    => '1',
            'show_incomplete'    => '1',
        ]));

        $response->assertOk();
        $response->assertDontSee('Bs Private Task');
    }

    public function test_search_with_text_and_no_scope_checked_returns_validation_error_and_no_results(): void
    {
        $task = $this->makeTask($this->userA, 'Findable By Title Pineapple');

        $response = $this->actingAs($this->userA)->get(route('search', [
            'q'                  => 'pineapple',
            'search_title'       => '0',
            'search_description' => '0',
            'search_comments'    => '0',
            'show_incomplete'    => '1',
        ]));

        $response->assertOk();
        $response->assertSessionHasErrors();
        $response->assertDontSee('Findable By Title Pineapple');
    }

    public function test_no_search_text_requires_no_scope_and_shows_no_error(): void
    {
        $response = $this->actingAs($this->userA)->get(route('search', [
            'search_title'       => '0',
            'search_description' => '0',
            'search_comments'    => '0',
        ]));

        $response->assertOk();
        $response->assertSessionDoesntHaveErrors();
    }

    public function test_load_more_endpoint_also_matches_comments(): void
    {
        $task = $this->makeTask($this->userA, 'Unrelated Name Two');
        $task->comments()->create([
            'user_id' => $this->userA->id,
            'comment' => 'the secret pineapple codeword',
        ]);

        $response = $this->actingAs($this->userA)->getJson(route('search.more', [
            'q'                  => 'pineapple',
            'search_title'       => '0',
            'search_description' => '0',
            'search_comments'    => '1',
            'status'             => 'incomplete',
        ]));

        $response->assertOk();
        $this->assertStringContainsString('Unrelated Name Two', $response->json('html'));
    }

    public function test_markdown_export_also_matches_comments(): void
    {
        $task = $this->makeTask($this->userA, 'Unrelated Name Three');
        $task->comments()->create([
            'user_id' => $this->userA->id,
            'comment' => 'the secret pineapple codeword',
        ]);

        $response = $this->actingAs($this->userA)->get(route('search', [
            'q'                  => 'pineapple',
            'search_title'       => '0',
            'search_description' => '0',
            'search_comments'    => '1',
            'show_incomplete'    => '1',
            'export'             => 'markdown',
        ]));

        $response->assertOk();
        $response->assertSee('Unrelated Name Three', false);
    }
}
