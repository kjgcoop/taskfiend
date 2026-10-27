<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectIndexInactivePaginationTest extends TestCase
{
    use RefreshDatabase;

    private function makeProjects(User $user, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            Project::create([
                'name' => sprintf('Old %03d', $i),
                'user_id' => $user->id,
                'status' => 'archived',
            ]);
        }
    }

    public function test_index_shows_first_page_and_total(): void
    {
        config(['taskfiend.pagination_per_page' => 5]);
        $user = User::factory()->create();
        $this->makeProjects($user, 7);

        $this->actingAs($user)->get(route('projects.index'))
            ->assertOk()
            ->assertSee('(7)', false)
            ->assertSee('Old 005')
            ->assertDontSee('Old 006');
    }

    public function test_inactive_endpoint_returns_next_page(): void
    {
        config(['taskfiend.pagination_per_page' => 5]);
        $user = User::factory()->create();
        $this->makeProjects($user, 7);

        $this->actingAs($user)->getJson(route('projects.inactive', ['page' => 2]))
            ->assertOk()
            ->assertJson(['hasMore' => false, 'nextPage' => 3])
            ->assertJsonPath('html', fn ($html) => str_contains($html, 'Old 006') && str_contains($html, 'Old 007') && !str_contains($html, 'Old 005'));
    }
}
