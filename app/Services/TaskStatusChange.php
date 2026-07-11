<?php

namespace App\Services;

use App\Models\Task;

/**
 * Result of TaskLifecycle::changeStatus().
 */
class TaskStatusChange
{
    public function __construct(
        /** False when the task already had the requested status (no-op). */
        public readonly bool $changed,
        /** True when completing cascaded to incomplete descendants. */
        public readonly bool $completedDescendants = false,
        /** The next occurrence created (or found) for a recurring task. */
        public readonly ?Task $nextRecurringTask = null,
    ) {
    }
}
