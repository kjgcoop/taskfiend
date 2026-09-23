<?php

namespace Tests\Feature;

use App\Models\ActivityNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for the client-side heartbeat endpoint (GET /auth/check), which reports session validity
 * and the current unread notification count for the bell badge.
 */
class AuthCheckHeartbeatTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $recipient, User $actor, bool $seen = false): void
    {
        ActivityNotification::create([
            'user_id'     => $recipient->id,
            'actor_id'    => $actor->id,
            'actor_name'  => $actor->name,
            'entity_type' => 'tasks',
            'entity_id'   => 1,
            'entity_name' => 'Some task',
            'description' => 'updated status to done',
            'seen'        => $seen,
        ]);
    }

    public function test_guest_gets_401(): void
    {
        $this->getJson('/auth/check')
            ->assertStatus(401)
            ->assertExactJson(['ok' => false]);
    }

    public function test_authenticated_user_gets_unread_count(): void
    {
        $user  = User::factory()->create();
        $actor = User::factory()->create();

        $this->notify($user, $actor);
        $this->notify($user, $actor);
        $this->notify($user, $actor, seen: true);
        $this->notify($actor, $user); // someone else's — not counted

        $this->actingAs($user)
            ->getJson('/auth/check')
            ->assertOk()
            ->assertExactJson(['ok' => true, 'unread' => 2]);
    }

    public function test_polling_does_not_mark_notifications_seen(): void
    {
        $user  = User::factory()->create();
        $actor = User::factory()->create();
        $this->notify($user, $actor);

        $this->actingAs($user)->getJson('/auth/check')->assertJson(['unread' => 1]);
        $this->actingAs($user)->getJson('/auth/check')->assertJson(['unread' => 1]);

        $this->assertSame(1, ActivityNotification::where('user_id', $user->id)->where('seen', false)->count());
    }

    public function test_opening_the_feed_clears_the_count(): void
    {
        $user  = User::factory()->create();
        $actor = User::factory()->create();
        $this->notify($user, $actor);

        $this->actingAs($user)->getJson(route('notifications.feed'))->assertOk();

        $this->actingAs($user)->getJson('/auth/check')->assertJson(['unread' => 0]);
    }
}
