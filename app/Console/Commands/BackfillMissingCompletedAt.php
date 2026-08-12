<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Services\TaskLifecycle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Historical fix-up for a bug where TaskController::bulkUpdate() (the
 * multi-select "Archive"/"Mark done" action) set a task's status directly
 * via mass assignment instead of going through TaskLifecycle::changeStatus().
 * That skipped two things: stamping completed_at, and — for recurring tasks
 * — creating the next occurrence. This command repairs both, for tasks
 * whose status is done/archived but completed_at was never set.
 */
class BackfillMissingCompletedAt extends Command
{
    protected $signature = 'tasks:backfill-completed-at
        {--dry-run : Show what would change without writing anything}';

    protected $description = 'Backfill completed_at for done/archived tasks that are missing it, and catch up any recurring series that missed their next occurrence as a result';

    public function handle(TaskLifecycle $lifecycle)
    {
        $dryRun = $this->option('dry-run');

        $tasks = Task::whereIn('status', ['done', 'archived'])
            ->whereNull('completed_at')
            ->get();

        if ($tasks->isEmpty()) {
            $this->info('No tasks found with a missing completed_at.');
            return 0;
        }

        $this->info("Found {$tasks->count()} task(s) with status done/archived but no completed_at.");

        $stamped = 0;
        $recurred = 0;

        foreach ($tasks as $task) {
            // Best-effort completion time: updated_at reflects when the
            // status column was last written, which for these rows is the
            // moment the buggy bulk-update request saved the status change.
            $completedAt = $task->updated_at ?? $task->created_at;

            $this->line("  #{$task->id} \"{$task->name}\" ({$task->status}) -> completed_at = {$completedAt}"
                . ($task->recurrence_pattern ? " [recurring: {$task->recurrence_pattern}]" : ''));

            if ($dryRun) {
                continue;
            }

            $task->completed_at = $completedAt;
            $task->save();
            $stamped++;

            if ($task->recurrence_pattern) {
                // createNextOccurrence() attributes its change-log entries to
                // Auth::id(), which is null in this CLI context — attribute
                // them to the task's creator instead, same as other
                // system-initiated writes (e.g. BackfillTaskLogs).
                Auth::login($task->creator);
                $next = $lifecycle->createNextOccurrence($task);
                Auth::logout();

                if ($next) {
                    $recurred++;
                    $this->line("    -> next occurrence: #{$next->id} on {$next->date}");
                }
            }
        }

        if ($dryRun) {
            $this->info('Dry run complete, no changes written.');
            return 0;
        }

        $this->info("Backfilled completed_at on {$stamped} task(s); created/found {$recurred} missing recurring occurrence(s).");

        return 0;
    }
}
