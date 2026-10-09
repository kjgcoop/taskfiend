<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class TemplateZipPortabilityTest extends TestCase
{
    use RefreshDatabase;

    private function makeTemplate(User $owner, bool $public = false): Project
    {
        $t = Project::create([
            'name' => 'Portable', 'description' => 'desc', 'user_id' => $owner->id,
            'project_type' => Project::TYPE_TEMPLATE, 'is_public' => $public,
        ]);
        $parent = Task::create(['name' => 'Parent', 'creator_id' => $owner->id, 'project_id' => $t->id, 'status' => 'incomplete']);
        Task::create(['name' => 'Child', 'creator_id' => $owner->id, 'project_id' => $t->id, 'parent_id' => $parent->id, 'status' => 'incomplete']);
        return $t;
    }

    public function test_download_then_reupload_creates_independent_template(): void
    {
        $owner = User::factory()->create();
        $t = $this->makeTemplate($owner);

        $response = $this->actingAs($owner)->get(route('templates.download', $t));
        $response->assertOk();
        $path = $response->baseResponse->getFile()->getPathname();
        $copy = tempnam(sys_get_temp_dir(), 'tz') . '.zip';
        copy($path, $copy);

        $this->actingAs($owner)->post(route('templates.importZip'), [
            'template_file' => new UploadedFile($copy, 'x.zip', 'application/zip', null, true),
            'template_name' => 'Uploaded',
            'is_public' => '1',
        ])->assertSessionHasNoErrors();

        $new = Project::where('project_type', 'template')->where('name', 'Uploaded')->firstOrFail();
        $this->assertNotSame($t->id, $new->id);
        $this->assertTrue($new->is_public);
        $this->assertSame($owner->id, $new->user_id);
        $this->assertSame(2, Task::where('project_id', $new->id)->count());
        $this->assertSame(1, Task::where('project_id', $new->id)->whereNotNull('parent_id')->count());
        $this->assertSame(2, Project::where('project_type', 'template')->count());
        $this->assertSame('Portable', $t->fresh()->name);
        $this->assertSame(2, Task::where('project_id', $t->id)->count());
    }

    public function test_private_template_download_forbidden_to_others_but_public_allowed(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $t = $this->makeTemplate($owner);

        $this->actingAs($other)->get(route('templates.download', $t))->assertForbidden();
        $t->update(['is_public' => true]);
        $this->actingAs($other)->get(route('templates.download', $t))->assertOk();
    }
}
