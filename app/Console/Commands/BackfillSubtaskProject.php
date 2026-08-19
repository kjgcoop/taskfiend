<?php

namespace App\Console\Commands;

use App\Models\ChangeLog;
use App\Models\Task;
use Illuminate\Console\Command;

/**
 * Historical fix-up for a bug in TaskController::create(): when opening the
 * create form via "+ Add Subtask" (which preselects parent_id but not
 * project_id), the Project field defaulted to the user's default project
 * instead of the parent task's project. A subtask created without manually
 * correcting that dropdown ended up with a project_id that didn't match its
 * parent's — it still rendered nested under the parent (the `children`
 * relation isn't scoped by project), but silently dropped out of that
 * project's task counts and task list, since those ARE scoped by project_id.
 *
 * This repairs existing data by setting every such subtask's project_id to
 * match its parent's. Runs breadth-first (top-down) so that fixing a
 * subtask's project also corrects what its own children get matched against.
 */
class BackfillSubtaskProject extends Command
{
    protected $signature = 'tasks:backfill-subtask-project
        {--dry-run : Show what would change without writing anything}';

    protected $description = "Backfill subtasks whose project_id doesn't match their parent task's project_id";

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $fixed = 0;
        $pass = 0;

        // Loop until a pass makes no changes, so multi-level subtask chains
        // (grandchildren etc.) get corrected once their immediate parent is.
        do {
            $pass++;
            $mismatched = Task::whereNotNull('parent_id')
                ->with('parent:id,project_id')
                ->get()
                ->filter(fn (Task $t) => $t->parent && $t->parent->project_id !== $t->project_id);

            if ($mismatched->isEmpty()) {
                break;
            }

            $this->info("Pass {$pass}: found {$mismatched->count()} mismatched subtask(s).");

            foreach ($mismatched as $task) {
                $oldProjectId = $task->project_id;
                $newProjectId = $task->parent->project_id;

                $this->line("  #{$task->id} \"{$task->name}\" project_id {$oldProjectId} -> {$newProjectId}");

                if ($dryRun) {
                    continue;
                }

                $task->project_id = $newProjectId;
                $task->save();

                ChangeLog::create([
                    'date'        => now(),
                    'user_id'     => $task->creator_id,
                    'entity_type' => 'tasks',
                    'entity_id'   => $task->id,
                    'description' => 'updated project_id',
                    'verb'        => 'edited',
                    'field'       => 'project_id',
                    'old_value'   => (string) $oldProjectId,
                    'new_value'   => (string) $newProjectId,
                ]);

                $fixed++;
            }

            if ($dryRun) {
                // Nothing was written, so re-querying would find the same rows forever.
                break;
            }
        } while (true);

        if ($dryRun) {
            $this->info('Dry run complete, no changes written.');
            return 0;
        }

        $this->info("Backfilled project_id on {$fixed} subtask(s).");

        return 0;
    }
}
