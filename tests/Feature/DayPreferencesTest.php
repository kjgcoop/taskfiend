<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskDigestMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DayPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $member;
    private Task $watched;
    private string $today;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner  = User::factory()->create();
        $this->member = User::factory()->create();
        foreach ([$this->owner, $this->member] as $user) {
            Project::create(['name' => 'Default', 'user_id' => $user->id, 'status' => 'incomplete', 'is_default' => true]);
        }

        $project = Project::create(['name' => 'Shared', 'user_id' => $this->owner->id, 'status' => 'incomplete']);
        $project->assignees()->sync([$this->member->id]);

        $this->today = today()->format('Y-m-d');
        $this->watched = Task::create([
            'name'       => 'Watched Zebra Task',
            'creator_id' => $this->owner->id,
            'project_id' => $project->id,
            'status'     => 'incomplete',
            'date'       => $this->today,
        ]);
        $this->watched->assignWithCreator([]);
    }

    public function test_profile_page_shows_the_day_preferences_section(): void
    {
        $this->actingAs($this->member)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Day Preferences')
            ->assertSee('show_project_tasks_on_day', false);
    }

    public function test_guests_cannot_change_day_preferences(): void
    {
        $this->patch(route('profile.day-preferences.update'), ['show_project_tasks_on_day' => '1'])
            ->assertRedirect('/login');
    }

    public function test_checking_the_box_turns_the_setting_on_and_unchecking_turns_it_off(): void
    {
        $this->actingAs($this->member)
            ->patch(route('profile.day-preferences.update'), ['show_project_tasks_on_day' => '1'])
            ->assertRedirect(route('profile.edit'));
        $this->assertTrue($this->member->fresh()->show_project_tasks_on_day);

        // An unchecked checkbox submits nothing at all.
        $this->actingAs($this->member)->patch(route('profile.day-preferences.update'), []);
        $this->assertFalse($this->member->fresh()->show_project_tasks_on_day);
    }

    public function test_setting_only_affects_the_user_who_changed_it(): void
    {
        $this->actingAs($this->member)
            ->patch(route('profile.day-preferences.update'), ['show_project_tasks_on_day' => '1']);

        $this->assertFalse($this->owner->fresh()->show_project_tasks_on_day);
    }

    public function test_day_page_hides_project_tasks_by_default_and_shows_them_when_on(): void
    {
        $this->actingAs($this->member)->get(route('day', ['date' => $this->today]))
            ->assertOk()
            ->assertDontSee('Watched Zebra Task');

        $this->member->update(['show_project_tasks_on_day' => true]);

        $this->actingAs($this->member)->get(route('day', ['date' => $this->today]))
            ->assertOk()
            ->assertSee('Watched Zebra Task');
    }

    public function test_overdue_page_ignores_the_setting(): void
    {
        $this->watched->update(['date' => today()->subDay()->format('Y-m-d')]);
        $this->member->update(['show_project_tasks_on_day' => true]);

        $this->actingAs($this->member)->get(route('overdue'))
            ->assertOk()
            ->assertDontSee('Watched Zebra Task');
    }

    public function test_digest_follows_the_same_setting_as_the_day_page(): void
    {
        $mailer = app(TaskDigestMailer::class);

        $this->assertFalse($mailer->tasksFor($this->member, today())->contains('id', $this->watched->id));

        $this->member->update(['show_project_tasks_on_day' => true]);

        $this->assertTrue($mailer->tasksFor($this->member->fresh(), today())->contains('id', $this->watched->id));
    }
}
