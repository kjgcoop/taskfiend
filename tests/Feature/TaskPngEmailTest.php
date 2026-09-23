<?php

namespace Tests\Feature;

use App\Models\EmailSubscription;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskPngMailer;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature tests for the daily task-list-as-PNG email (TaskPngMailer and the
 * `email:task-png` command). Mailgun is faked; assertions inspect the
 * multipart request that would have been sent.
 */
class TaskPngEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.mailgun.domain' => 'mg.example.com',
            'services.mailgun.secret' => 'key-test',
        ]);

        Http::fake(['*' => Http::response(['id' => 'x', 'message' => 'Queued'], 200)]);
    }

    private function userWithTaskOn(Carbon $date, string $taskName = 'Buy milk'): User
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'Inbox', 'user_id' => $user->id, 'status' => 'incomplete']);

        Task::create([
            'name'       => $taskName,
            'creator_id' => $user->id,
            'project_id' => $project->id,
            'status'     => 'incomplete',
            'date'       => $date->format('Y-m-d'),
        ]);

        return $user;
    }

    /** @return array<string, array> multipart parts of the one recorded request, keyed by part name */
    private function sentParts(): array
    {
        Http::assertSentCount(1);
        [$request] = Http::recorded()->first();

        return collect($request->data())->keyBy('name')->all();
    }

    public function test_sends_png_of_the_given_dates_tasks_inline(): void
    {
        $date = Carbon::parse('2026-10-05');
        $user = $this->userWithTaskOn($date);

        $this->assertTrue(app(TaskPngMailer::class)->send($user, $date));

        $parts = $this->sentParts();
        $this->assertSame('Your Task List for Monday, October 5, 2026', $parts['subject']['contents']);
        $this->assertSame('taskfiend-day-2026-10-05.png', $parts['inline']['filename']);
        $this->assertStringStartsWith("\x89PNG", $parts['inline']['contents']);
        $this->assertStringContainsString('src="cid:taskfiend-day-2026-10-05.png"', $parts['html']['contents']);
    }

    public function test_sends_nothing_when_no_tasks_on_that_date(): void
    {
        $user = $this->userWithTaskOn(Carbon::parse('2026-10-05'));

        $this->assertFalse(app(TaskPngMailer::class)->send($user, Carbon::parse('2026-10-06')));
        Http::assertNothingSent();
    }

    public function test_command_requires_email_or_all(): void
    {
        $this->artisan('email:task-png')->assertExitCode(1);
        Http::assertNothingSent();
    }

    public function test_command_all_only_sends_to_png_subscribers(): void
    {
        $subscribed = $this->userWithTaskOn(Carbon::today());
        $digestOnly = $this->userWithTaskOn(Carbon::today());

        EmailSubscription::create(['user_id' => $subscribed->id, 'type' => EmailSubscription::DAILY_PNG]);
        EmailSubscription::create(['user_id' => $digestOnly->id, 'type' => EmailSubscription::DAILY_DIGEST]);

        $this->artisan('email:task-png', ['--all' => true])->assertExitCode(0);

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => collect($request->data())
            ->contains(fn ($part) => $part['name'] === 'to' && str_contains($part['contents'], $subscribed->email)));
    }

    public function test_profile_offers_png_subscription(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.email-preferences.update'), ['subscriptions' => [EmailSubscription::DAILY_PNG]])
            ->assertRedirect(route('profile.edit'));

        $this->assertTrue($user->fresh()->isSubscribedToEmail(EmailSubscription::DAILY_PNG));
    }
}
