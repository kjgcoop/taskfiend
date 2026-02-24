<?php

namespace App\Console\Commands;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Console\Command;

class MoveOverdueTasks extends Command
{
    protected $signature = 'tasks:move-overdue';

    protected $description = 'Move all overdue incomplete tasks to today (dev utility)';

    public function handle()
    {
        $today = Carbon::today(config('app.timezone'))->toDateString();

        $tasks = Task::where('status', 'incomplete')
            ->whereNotNull('date')
            ->where('date', '<', $today)
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('No overdue tasks found.');
            return 0;
        }

        foreach ($tasks as $task) {
            $this->info("Moving task #{$task->id} ({$task->name}) from {$task->date} to {$today}");
            $task->date = $today;
            $task->save();
        }

        $this->info("Moved {$tasks->count()} overdue task(s) to today ({$today}).");
        return 0;
    }
}
