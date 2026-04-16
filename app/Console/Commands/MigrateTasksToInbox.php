<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Console\Command;

class MigrateTasksToInbox extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tasks:migrate-to-inbox';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate tasks with no project (project_id = null) to their creator\'s Inbox';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tasks = Task::whereNull('project_id')->get();
        $migrated = 0;

        foreach ($tasks as $task) {
            // Find the creator's Inbox project
            $inbox = Project::where('user_id', $task->creator_id)
                ->where('is_default', true)
                ->first();

            if ($inbox) {
                $task->project_id = $inbox->id;
                $task->save();
                $migrated++;
                $this->info("Migrated task #{$task->id} ({$task->name}) to {$inbox->name}");
            } else {
                $this->warn("No Inbox found for user #{$task->creator_id} - task #{$task->id} not migrated");
            }
        }

        $this->info("Migrated {$migrated} task(s) to Inbox projects.");
        return 0;
    }
}
