<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectReminder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the optional note on a project reminder
 * (ProjectController::storeReminder() / dismissReminder()), and its
 * appearance on the /day reminder bar and the reminders-index page.
 */
class ProjectReminderNoteTest extends TestCase
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

    public function test_storing_a_reminder_saves_the_note(): void
    {
        $response = $this->actingAs($this->user)->post(route('projects.reminders.store', $this->project), [
            'date' => now()->addDay()->toDateString(),
            'note' => 'Bring the signed contract',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('project_reminders', [
            'project_id' => $this->project->id,
            'note'       => 'Bring the signed contract',
        ]);
    }

    public function test_note_is_optional(): void
    {
        $response = $this->actingAs($this->user)->post(route('projects.reminders.store', $this->project), [
            'date' => now()->addDay()->toDateString(),
        ]);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('project_reminders', [
            'project_id' => $this->project->id,
            'note'       => null,
        ]);
    }

    public function test_dismissing_a_recurring_reminder_copies_the_note_to_the_next_occurrence(): void
    {
        $reminder = $this->project->reminders()->create([
            'user_id'             => $this->user->id,
            'date'                => now()->toDateString(),
            'recurrence_pattern'  => 'weekly',
            'recurrence_floating' => false,
            'note'                => 'Check the invoice',
        ]);

        $this->actingAs($this->user)->post(route('projects.reminders.dismiss', [$this->project, $reminder]));

        $next = ProjectReminder::where('project_id', $this->project->id)
            ->where('dismissed', false)
            ->first();

        $this->assertNotNull($next);
        $this->assertSame('Check the invoice', $next->note);
    }

    public function test_day_view_shows_the_reminder_note(): void
    {
        $this->project->reminders()->create([
            'user_id' => $this->user->id,
            'date'    => now()->toDateString(),
            'note'    => 'Don\'t forget the deposit',
        ]);

        $response = $this->actingAs($this->user)->get(route('day'));

        $response->assertOk();
        $response->assertSee('Don&#039;t forget the deposit', false);
    }
}
