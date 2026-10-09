<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProjectTypeSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_10_08_000000_add_project_type_and_is_public_to_projects_table.php';

    public function test_new_projects_default_to_normal_and_not_public(): void
    {
        $user = User::factory()->create();
        $project = Project::create(['name' => 'P', 'user_id' => $user->id])->fresh();

        $this->assertSame('normal', $project->project_type);
        $this->assertFalse($project->is_public);
    }

    public function test_type_constants_are_defined(): void
    {
        $this->assertSame('normal', Project::TYPE_NORMAL);
        $this->assertSame('template', Project::TYPE_TEMPLATE);
    }

    public function test_project_type_and_is_public_are_mass_assignable(): void
    {
        $user = User::factory()->create();
        $project = Project::create([
            'name' => 'T', 'user_id' => $user->id,
            'project_type' => Project::TYPE_TEMPLATE, 'is_public' => true,
        ])->fresh();

        $this->assertSame('template', $project->project_type);
        $this->assertTrue($project->is_public);
    }

    public function test_existing_projects_are_backfilled_when_migrating(): void
    {
        $user = User::factory()->create();

        Artisan::call('migrate:rollback', ['--path' => self::MIGRATION, '--force' => true]);
        $id = DB::table('projects')->insertGetId([
            'name' => 'Old', 'user_id' => $user->id, 'status' => 'incomplete',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Artisan::call('migrate', ['--path' => self::MIGRATION, '--force' => true]);

        $project = Project::find($id);
        $this->assertSame('normal', $project->project_type);
        $this->assertFalse($project->is_public);
        $this->assertSame(0, DB::table('projects')->whereNull('project_type')->orWhere('project_type', '')->count());
    }
}
