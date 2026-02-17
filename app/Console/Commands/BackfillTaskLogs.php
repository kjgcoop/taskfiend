<?php

namespace App\Console\Commands;

use App\Models\ChangeLog;
use App\Models\Task;
use Illuminate\Console\Command;

class BackfillTaskLogs extends Command
{
    protected $signature = 'changelog:backfill';

    protected $description = 'Backfill missing "created task" change log entries for existing tasks';

    public function handle()
    {
        // Find tasks that have no "created" change log entry
        $tasksWithoutCreationLog = Task::whereDoesntHave('changeLogs', function ($query) {
            $query->where('description', 'like', '%created task%')
                  ->orWhere('description', 'like', '%created recurring task%');
        })->with('creator')->get();

        if ($tasksWithoutCreationLog->isEmpty()) {
            $this->info('All tasks already have creation log entries.');
            return 0;
        }

        $this->info("Found {$tasksWithoutCreationLog->count()} tasks missing creation log entries.");

        $count = 0;
        foreach ($tasksWithoutCreationLog as $task) {
            ChangeLog::create([
                'date' => $task->created_at,
                'user_id' => $task->creator_id,
                'entity_type' => 'tasks',
                'entity_id' => $task->id,
                'description' => 'created task',
            ]);
            $count++;
        }

        $this->info("Backfilled {$count} creation log entries.");

        return 0;
    }
}
