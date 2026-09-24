<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature test for GET /tasks/create (TaskController::create)
 *
 * The Create Task page's #project/@tag inline autocomplete used to compute slugs with its
 * own inline copy (`.toLowerCase().replace(/[^a-z0-9]/g, '')`), which deletes spaces and
 * special characters instead of dashing them like the shared global `slugify()` helper in
 * `layouts/app.blade.php`. It should reuse `slugify()` instead of duplicating the logic.
 */
class TaskCreateSlugifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_page_reuses_shared_slugify_helper_instead_of_inline_copy(): void
    {
        $user = User::factory()->create();

        Project::create([
            'name'       => 'Default',
            'user_id'    => $user->id,
            'status'     => 'incomplete',
            'is_default' => true,
        ]);

        $response = $this->actingAs($user)->get('/tasks/create');

        $response->assertOk();

        // The old inline slug regex that deletes spaces/special chars instead of dashing them.
        $response->assertDontSee('.replace(/[^a-z0-9]/g', false);

        // The page should compute its #project/@tag autocomplete slugs via the shared helper.
        $response->assertSee('slugify(name)', false);
        $response->assertSee('slugify(p.name)', false);
        $response->assertSee('slugify(t.tag_name)', false);
    }
}
