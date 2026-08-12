<?php

namespace App\Services;

use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * The task status state machine — single source of truth for what a status
 * change implies, shared by the full-form update, the inline field editor,
 * and anything else that completes, archives, or re-opens a task:
 *
 *  - completing a task with incomplete subtasks completes the whole subtree
 *  - archiving cascades to all descendants
 *  - completed_at is stamped on done/archived and cleared on re-open
 *  - every transition is change-logged with the right verb
 *  - completing/archiving a recurring task creates the next occurrence
 *  - re-opening a recurring task can archive the already-created next occurrence
 */
class TaskLifecycle
{
    public function __construct(private DateParser $dateParser = new DateParser())
    {
    }

    /**
     * Apply a status change and all of its side effects.
     *
     * @param string      $newStatus            One of incomplete|done|archived.
     * @param string|null $nextOccurrenceAction 'archive' to archive the next
     *        occurrence when re-opening a recurring task.
     */
    public function changeStatus(Task $task, string $newStatus, ?string $nextOccurrenceAction = null): TaskStatusChange
    {
        $previousStatus = $task->status;
        if ($previousStatus === $newStatus) {
            return new TaskStatusChange(changed: false);
        }

        // Completing a task with incomplete subtasks completes the whole subtree.
        if ($newStatus === 'done' && $task->hasIncompleteDescendants()) {
            $this->completeTaskAndDescendants($task);
            $this->logChange($task, 'marked done with all subtasks', 'completed');

            $next = $task->recurrence_pattern ? $this->createNextOccurrence($task) : null;

            return new TaskStatusChange(changed: true, completedDescendants: true, nextRecurringTask: $next);
        }

        // Archiving cascades to all descendants.
        if ($newStatus === 'archived') {
            foreach ($task->getAllDescendants() as $descendant) {
                if ($descendant->status !== 'archived') {
                    $descendant->status = 'archived';
                    $descendant->save();
                    $this->logChange($descendant, 'auto-archived (parent archived)', 'archived');
                }
            }
        }

        $task->status = $newStatus;
        $task->completed_at = in_array($newStatus, ['done', 'archived']) ? now() : null;
        $task->save();

        $verb = match ($newStatus) {
            'done'     => 'completed',
            'archived' => 'archived',
            default    => 'edited',
        };
        $this->logChange($task, "changed status from {$previousStatus} to {$newStatus}", $verb, 'status', $previousStatus, $newStatus);

        $next = null;
        if (in_array($newStatus, ['done', 'archived']) && $task->recurrence_pattern) {
            $next = $this->createNextOccurrence($task);
        }

        if ($newStatus === 'incomplete' && $task->recurrence_pattern && $nextOccurrenceAction === 'archive') {
            $this->archiveNextOccurrence($task);
        }

        return new TaskStatusChange(changed: true, nextRecurringTask: $next);
    }

    /**
     * Mark task and all descendant subtasks as done (bottom-up).
     */
    private function completeTaskAndDescendants(Task $task): void
    {
        foreach ($task->getAllDescendants() as $descendant) {
            if ($descendant->status !== 'done') {
                $descendant->status = 'done';
                $descendant->completed_at = now();
                $descendant->save();
                $this->logChange($descendant, 'auto-completed (parent marked done)', 'completed');
            }
        }

        if ($task->status !== 'done') {
            $task->status = 'done';
            $task->completed_at = now();
            $task->save();
        }
    }

    /**
     * Compute the date of the next occurrence of a recurring task that was
     * just completed or re-opened.
     *
     * Floating recurrence advances from today (the completion date); fixed
     * recurrence advances from the task's scheduled date.
     */
    private function nextOccurrenceDate(Task $task): ?Carbon
    {
        $pattern  = $task->recurrence_pattern;
        $baseDate = $task->recurrence_floating
            ? Carbon::today()
            : ($task->date ? Carbon::parse($task->date) : Carbon::today());

        $next = $this->dateParser->getNextOccurrence($pattern, $baseDate);
        if (!$next) {
            return null;
        }

        // Advance past-due occurrences forward through the pattern until we reach today
        // at the earliest. E.g. a daily task last due Monday, completed on Wednesday,
        // lands on Wednesday (today) — not Tuesday.
        $today = Carbon::today();
        while ($next->lt($today)) {
            $advanced = $this->dateParser->getNextOccurrence($pattern, $next);
            if (!$advanced) {
                break;
            }
            $next = $advanced;
        }

        // Guard: next occurrence must be strictly after the scheduled date of the task
        // just completed. This prevents re-creating the same instance when a task is
        // completed before its due date (e.g. completing Thursday's Mon/Thu task on
        // Wednesday — the "next" occurrence from today is Thursday itself, not Monday).
        if ($task->date) {
            $scheduledDate = Carbon::parse($task->date);
            while ($next->lte($scheduledDate)) {
                $advanced = $this->dateParser->getNextOccurrence($pattern, $next);
                if (!$advanced) {
                    break;
                }
                $next = $advanced;
            }
        }

        return $next;
    }

    /**
     * Find an already-existing incomplete instance of this recurring series
     * on the given date, to avoid creating duplicate occurrences.
     */
    private function findExistingOccurrence(Task $task, string $date): ?Task
    {
        return Task::where('creator_id', $task->creator_id)
            ->where('name', $task->name)
            ->where('recurrence_pattern', $task->recurrence_pattern)
            ->where('status', 'incomplete')
            ->where('date', $date)
            ->first();
    }

    /**
     * Create the next instance of a recurring task that was just completed
     * or archived. Copies name, description, schedule, project, recurrence,
     * tags, assignments, attachments, and the subtask tree (regardless of
     * subtask status) — not comments or completion state. Ownership
     * (creator, assignments, attachment uploader) is preserved from the
     * original rather than reassigned to whoever completed this instance.
     * Returns the existing instance instead of creating a duplicate, and
     * null when the series has ended (or has no next date).
     *
     * Public (rather than an internal step of changeStatus() alone) so the
     * `recurrence:backfill` command can also call it directly to catch up
     * series whose rollover was missed by a status change that bypassed
     * TaskLifecycle (see that command for context).
     */
    public function createNextOccurrence(Task $originalTask): ?Task
    {
        if (!$originalTask->recurrence_pattern) {
            return null;
        }

        $nextOccurrence = $this->nextOccurrenceDate($originalTask);
        if (!$nextOccurrence) {
            return null;
        }

        $nextDate = $nextOccurrence->format('Y-m-d');

        // Stop the series if the next occurrence falls after the end date
        if ($originalTask->recurrence_end_date && $nextDate > $originalTask->recurrence_end_date) {
            return null;
        }

        $existingTask = $this->findExistingOccurrence($originalTask, $nextDate);
        if ($existingTask) {
            return $existingTask;
        }

        $newTask = $originalTask->duplicate(
            overrides: ['date' => $nextDate, 'parent_id' => null], // recurring tasks are always root-level
            withChildren: true,
            preserveOwnership: true,
            afterChildCreate: fn (Task $original, Task $new) => $new->changeLogs()->create([
                'date' => now(),
                'user_id' => Auth::id(),
                'entity_type' => 'tasks',
                'entity_id' => $new->id,
                'description' => 'created subtask from recurring parent',
            ]),
        );

        $newTask->changeLogs()->create([
            'date' => now(),
            'user_id' => Auth::id(),
            'entity_type' => 'tasks',
            'entity_id' => $newTask->id,
            'description' => 'created recurring task',
        ]);

        return $newTask;
    }

    /**
     * When a completed recurring task is re-opened, its next occurrence (if
     * one was already created) can be archived so the series doesn't fork.
     */
    private function archiveNextOccurrence(Task $originalTask): void
    {
        $nextOccurrence = $this->nextOccurrenceDate($originalTask);
        if (!$nextOccurrence) {
            return;
        }

        $nextTask = $this->findExistingOccurrence($originalTask, $nextOccurrence->format('Y-m-d'));
        if ($nextTask) {
            $nextTask->status = 'archived';
            $nextTask->completed_at = now();
            $nextTask->save();
            $this->logChange($nextTask, 'archived (next occurrence of re-opened recurring task)', 'archived', 'status', 'incomplete', 'archived');
        }
    }

    private function logChange(Task $task, string $description, string $verb = 'edited', ?string $field = null, mixed $oldValue = null, mixed $newValue = null): void
    {
        $task->changeLogs()->create([
            'date'        => now(),
            'user_id'     => Auth::id(),
            'entity_type' => 'tasks',
            'entity_id'   => $task->id,
            'description' => $description,
            'verb'        => $verb,
            'field'       => $field,
            'old_value'   => $oldValue !== null ? (string) $oldValue : null,
            'new_value'   => $newValue !== null ? (string) $newValue : null,
        ]);
    }
}
