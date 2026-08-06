<?php

namespace Tests\Unit;

use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskTextFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TaskTextFilter is a server-side port of the on-page filter box in
 * task-list.blade.php's Alpine `filterTasks()`. These tests exercise the
 * same token forms the JS supports, so the two stay in sync — see
 * DashboardController::exportDayPdf(), which uses this to make the PDF
 * export mirror whatever's currently filtered on screen.
 */
class TaskTextFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $creator;
    private Project $projectA;
    private Project $projectB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = User::factory()->create();
        $this->projectA = Project::create(['name' => 'Website Redesign', 'user_id' => $this->creator->id, 'status' => 'incomplete']);
        $this->projectB = Project::create(['name' => 'Errands', 'user_id' => $this->creator->id, 'status' => 'incomplete']);
    }

    private function task(string $name, ?Project $project = null, array $tags = [], array $assignees = [], ?string $location = null): Task
    {
        $task = Task::create([
            'name'       => $name,
            'creator_id' => $this->creator->id,
            'project_id' => $project?->id ?? $this->projectA->id,
            'status'     => 'incomplete',
            'location'   => $location,
        ]);

        foreach ($tags as $tagName) {
            $tag = Tag::firstOrCreate(['tag_name' => $tagName]);
            $task->tags()->attach($tag->id);
        }

        foreach ($assignees as $user) {
            $task->assignments()->create(['assignee_id' => $user->id, 'assigned_by_id' => $this->creator->id]);
        }

        return $task->fresh(['project', 'tags', 'assignees']);
    }

    public function test_empty_query_returns_all_tasks_unchanged(): void
    {
        $tasks = collect([$this->task('Buy milk'), $this->task('Call dentist')]);

        $result = TaskTextFilter::apply($tasks, '');

        $this->assertCount(2, $result);
    }

    public function test_plain_word_matches_task_name_case_insensitively(): void
    {
        $tasks = collect([$this->task('Buy Milk'), $this->task('Call dentist')]);

        $result = TaskTextFilter::apply($tasks, 'milk');

        $this->assertCount(1, $result);
        $this->assertSame('Buy Milk', $result->first()->name);
    }

    public function test_hash_token_filters_by_project_with_hyphen_normalization(): void
    {
        $a = $this->task('Design mockups', $this->projectA);
        $b = $this->task('Buy stamps', $this->projectB);

        $result = TaskTextFilter::apply(collect([$a, $b]), '#website-redesign');

        $this->assertCount(1, $result);
        $this->assertSame($a->id, $result->first()->id);
    }

    public function test_at_token_filters_by_tag(): void
    {
        $bug = $this->task('Fix login', tags: ['bug']);
        $chore = $this->task('Tidy inbox', tags: ['chore']);

        $result = TaskTextFilter::apply(collect([$bug, $chore]), '@bug');

        $this->assertCount(1, $result);
        $this->assertSame($bug->id, $result->first()->id);
    }

    public function test_not_prefix_excludes_matches(): void
    {
        $bug = $this->task('Fix login', tags: ['bug']);
        $chore = $this->task('Tidy inbox', tags: ['chore']);

        $result = TaskTextFilter::apply(collect([$bug, $chore]), 'not:@bug');

        $this->assertCount(1, $result);
        $this->assertSame($chore->id, $result->first()->id);
    }

    public function test_plus_token_filters_by_location(): void
    {
        $home = $this->task('Water plants', location: 'Home Office');
        $office = $this->task('Print badge', location: 'Downtown Office');

        $result = TaskTextFilter::apply(collect([$home, $office]), '+home-office');

        $this->assertCount(1, $result);
        $this->assertSame($home->id, $result->first()->id);
    }

    public function test_ampersand_token_filters_by_assignee(): void
    {
        $alice = User::factory()->create(['name' => 'Alice Smith']);
        $bob = User::factory()->create(['name' => 'Bob Jones']);

        $aliceTask = $this->task('Review PR', assignees: [$alice]);
        $bobTask = $this->task('Deploy', assignees: [$bob]);

        $result = TaskTextFilter::apply(collect([$aliceTask, $bobTask]), '&alice');

        $this->assertCount(1, $result);
        $this->assertSame($aliceTask->id, $result->first()->id);
    }

    public function test_quoted_phrase_is_matched_as_a_single_name_token(): void
    {
        $match = $this->task('Call the dentist office');
        $noMatch = $this->task('Call mom');

        $result = TaskTextFilter::apply(collect([$match, $noMatch]), '"dentist office"');

        $this->assertCount(1, $result);
        $this->assertSame($match->id, $result->first()->id);
    }

    public function test_multiple_tokens_are_combined_with_and(): void
    {
        $a = $this->task('Fix login bug', $this->projectA, tags: ['bug']);
        $b = $this->task('Fix login bug', $this->projectB, tags: ['bug']);

        $result = TaskTextFilter::apply(collect([$a, $b]), '#website-redesign @bug');

        $this->assertCount(1, $result);
        $this->assertSame($a->id, $result->first()->id);
    }
}
