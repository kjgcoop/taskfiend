<?php

namespace App\Console\Commands;

use App\Models\ChangeLog;
use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAttachment;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PurgeUser extends Command
{
    protected $signature = 'user:purge {email} {--force : Skip confirmation prompt}';

    protected $description = 'Delete all tasks, projects, and change logs for a user (preserves user account, API keys, and tags)';

    public function handle()
    {
        $email = $this->argument('email');

        $user = User::where('email', $email)->first();

        if (!$user) {
            $this->error("User not found: {$email}");
            return 1;
        }

        // Gather counts for preview and summary
        $taskIds = Task::where('creator_id', $user->id)->pluck('id');
        $taskCount = $taskIds->count();
        $projectCount = Project::where('user_id', $user->id)->count();
        $changeLogCount = ChangeLog::where('user_id', $user->id)->count();

        // Count related records that will cascade-delete with tasks
        $attachmentCount = TaskAttachment::whereIn('task_id', $taskIds)->count();
        $commentCount = Comment::whereIn('task_id', $taskIds)->count();

        if ($taskCount === 0 && $projectCount === 0 && $changeLogCount === 0) {
            $this->info("No data to purge for {$email}.");
            return 0;
        }

        // Show what will be deleted
        $this->warn("Will purge all data for: {$email} (ID: {$user->id})");
        $this->line("  Tasks:            {$taskCount}");
        $this->line("  Task attachments: {$attachmentCount}");
        $this->line("  Comments:         {$commentCount}");
        $this->line("  Projects:         {$projectCount}");
        $this->line("  Change logs:      {$changeLogCount}");
        $this->line('');
        $this->line('  Preserved: user account, API keys, tags');

        if (!$this->option('force') && !$this->confirm('Proceed with purge?')) {
            $this->info('Purge cancelled.');
            return 1;
        }

        DB::transaction(function () use ($user, $taskIds, $taskCount, $projectCount, $changeLogCount, $attachmentCount, $commentCount) {
            // 1. Clean up stored files before deleting database records
            $this->deleteStoredFiles($taskIds);

            // 2. Delete tasks (cascades: assignments, comments, task_attachments, tag_task)
            if ($taskCount > 0) {
                Task::where('creator_id', $user->id)->delete();
                $this->info("Deleted {$taskCount} tasks (with {$attachmentCount} attachments, {$commentCount} comments)");
            }

            // 3. Delete projects
            if ($projectCount > 0) {
                Project::where('user_id', $user->id)->delete();
                $this->info("Deleted {$projectCount} projects");
            }

            // 4. Delete change logs
            if ($changeLogCount > 0) {
                ChangeLog::where('user_id', $user->id)->delete();
                $this->info("Deleted {$changeLogCount} change logs");
            }
        });

        $this->info('');
        $this->info("Purge complete for {$email}.");

        return 0;
    }

    private function deleteStoredFiles($taskIds): void
    {
        if ($taskIds->isEmpty()) {
            return;
        }

        // Delete task attachment files
        $attachmentPaths = TaskAttachment::whereIn('task_id', $taskIds)
            ->whereNotNull('file_path')
            ->pluck('file_path');

        foreach ($attachmentPaths as $path) {
            Storage::disk('private')->delete($path);
        }

        // Delete comment attachment files
        $commentPaths = Comment::whereIn('task_id', $taskIds)
            ->whereNotNull('file_path')
            ->pluck('file_path');

        foreach ($commentPaths as $path) {
            Storage::disk('private')->delete($path);
        }

        $fileCount = $attachmentPaths->count() + $commentPaths->count();
        if ($fileCount > 0) {
            $this->info("Deleted {$fileCount} stored files");
        }
    }
}
