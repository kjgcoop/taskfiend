<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\ChangeLog;
use App\Models\Task;
use Illuminate\Console\Command;

/**
 * A task's creator is always one of its assignees (Task::assignWithCreator()),
 * but several creation paths used to copy someone else's assignee list and
 * leave the creator off (a subtask inheriting its parent's assignees, a bulk
 * line with an &user token, a task duplicated by another user, an API call
 * with explicit assignee_ids).
 *
 * This repairs existing data by assigning each such task to its creator.
 * "Assigned by" is the creator, so nobody gets a "newly assigned to you"
 * entry in their past-day review.
 */
class BackfillCreatorAssignments extends Command
{
    protected $signature = 'tasks:backfill-creator-assignments
        {--dry-run : Show what would change without writing anything}';

    protected $description = "Assign each task to its creator when the creator isn't already an assignee";

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $fixed = 0;

        Task::whereDoesntHave('assignments', fn ($q) => $q->whereColumn('assignments.assignee_id', 'tasks.creator_id'))
            ->whereNotNull('creator_id')
            ->orderBy('id')
            ->chunkById(200, function ($tasks) use ($dryRun, &$fixed) {
                foreach ($tasks as $task) {
                    $this->line("  #{$task->id} \"{$task->name}\" + creator {$task->creator_id}");

                    if (!$dryRun) {
                        Assignment::create([
                            'task_id'        => $task->id,
                            'assignee_id'    => $task->creator_id,
                            'assigned_by_id' => $task->creator_id,
                        ]);

                        ChangeLog::create([
                            'date'        => now(),
                            'user_id'     => $task->creator_id,
                            'entity_type' => 'tasks',
                            'entity_id'   => $task->id,
                            'description' => 'updated assignees',
                            'verb'        => 'edited',
                        ]);
                    }

                    $fixed++;
                }
            });

        if ($dryRun) {
            $this->info("Dry run complete: {$fixed} task(s) would be assigned to their creator. No changes written.");
            return 0;
        }

        $this->info("Assigned {$fixed} task(s) to their creator.");

        return 0;
    }
}
