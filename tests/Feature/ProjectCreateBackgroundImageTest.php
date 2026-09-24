<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Feature test for POST /projects (ProjectController::store) with an optional
 * background image, added per implementation-plan.md's "Background Image on
 * Project Create" task. Reuses the same validation/storage logic as the
 * existing per-project uploadBackground() endpoint.
 */
class ProjectCreateBackgroundImageTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_a_project_with_a_background_image_stores_it(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();

        // avif bypasses the GD-based resize branch in storeBackgroundImage(), which
        // needs the gd extension (not installed in this sandbox) to exercise safely.
        $file = UploadedFile::fake()->create('cover.avif', 10, 'image/avif');

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'New Project',
            'background_image' => $file,
        ]);

        $response->assertRedirect();

        $project = \App\Models\Project::where('name', 'New Project')->firstOrFail();

        $this->assertNotNull($project->background_image);
        Storage::disk('private')->assertExists($project->background_image);
    }

    public function test_creating_a_project_without_a_background_image_still_works(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'No Image Project',
        ]);

        $response->assertRedirect();

        $project = \App\Models\Project::where('name', 'No Image Project')->firstOrFail();
        $this->assertNull($project->background_image);
    }

    public function test_invalid_background_image_file_type_is_rejected(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

        $response = $this->actingAs($user)->post(route('projects.store'), [
            'name' => 'Bad Image Project',
            'background_image' => $file,
        ]);

        $response->assertSessionHasErrors('background_image');
        $this->assertDatabaseMissing('projects', ['name' => 'Bad Image Project']);
    }
}
