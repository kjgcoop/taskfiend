<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A comment can carry any number of attachments, each stored as its own
 * comment_attachments row with its own physical file.
 */
class CommentAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    private function makeTask(User $creator): Task
    {
        $project = Project::create([
            'name' => 'Project ' . uniqid(),
            'user_id' => $creator->id,
            'status' => 'incomplete',
        ]);

        return Task::create([
            'name' => 'Task',
            'creator_id' => $creator->id,
            'project_id' => $project->id,
            'status' => 'incomplete',
        ]);
    }

    public function test_comment_accepts_multiple_attachments(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();
        $task = $this->makeTask($user);

        $this->actingAs($user)->post(route('comments.store', $task), [
            'comment' => 'Two files',
            'attachments' => [
                UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'),
                UploadedFile::fake()->create('b.txt', 1, 'text/plain'),
            ],
        ])->assertRedirect();

        $comment = $task->comments()->first();
        $this->assertSame(['a.pdf', 'b.txt'], $comment->attachments()->orderBy('id')->pluck('original_filename')->all());
        foreach ($comment->attachments as $attachment) {
            Storage::disk('private')->assertExists($attachment->file_path);
        }
    }

    public function test_comment_without_attachments_still_works(): void
    {
        $user = User::factory()->create();
        $task = $this->makeTask($user);

        $this->actingAs($user)->post(route('comments.store', $task), ['comment' => 'Hi'])->assertRedirect();

        $this->assertSame(0, $task->comments()->first()->attachments()->count());
    }

    public function test_download_requires_access_and_matching_comment(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();
        $other = User::factory()->create();
        $task = $this->makeTask($user);

        $this->actingAs($user)->post(route('comments.store', $task), [
            'comment' => 'File',
            'attachments' => [UploadedFile::fake()->create('a.txt', 1, 'text/plain')],
        ]);
        $comment = $task->comments()->first();
        $attachment = $comment->attachments()->first();

        $this->actingAs($user)->get(route('comments.download', [$task, $comment, $attachment]))->assertOk();
        $this->actingAs($other)->get(route('comments.download', [$task, $comment, $attachment]))->assertForbidden();
    }

    public function test_deleting_a_comment_removes_all_its_files(): void
    {
        Storage::fake('private');
        $user = User::factory()->create();
        $task = $this->makeTask($user);

        $this->actingAs($user)->post(route('comments.store', $task), [
            'comment' => 'Files',
            'attachments' => [
                UploadedFile::fake()->create('a.txt', 1, 'text/plain'),
                UploadedFile::fake()->create('b.txt', 1, 'text/plain'),
            ],
        ]);
        $comment = $task->comments()->first();
        $paths = $comment->attachments->pluck('file_path');

        $this->actingAs($user)->delete(route('comments.destroy', [$task, $comment]));

        foreach ($paths as $path) {
            Storage::disk('private')->assertMissing($path);
        }
    }
}
