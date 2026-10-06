---
paths:
  - "app/Services/TaskLifecycle.php"
  - "app/Console/Commands/BackfillMissingCompletedAt.php"
  - "app/Http/Controllers/TaskController.php"
  - "docs/content/docs/features/recurring-tasks.md"
---

# Recurring Tasks and Task Lifecycle

User guide: `docs/content/docs/features/recurring-tasks.md`. Pattern syntax: see `date-parser.md`.

## Behavior

- Marking a recurring task done completes that instance and creates the next one (`TaskLifecycle::createNextOccurrence()`). The series continues until the `recurrence_pattern` is removed or the task is archived.
- No duplicate occurrences: nothing is created if one already exists for the next date.
- **Copied** to the next instance: name, description, datetime, project, tags, assignments, attachments (each with its own file copy). **Not copied**: comments, completion status. Rollover uses `Task::duplicate()` with ownership preserved, so completing someone else's recurring task doesn't reassign it.
- Project reminders carry `recurrence_pattern`, `recurrence_floating` and `note` forward explicitly in `ProjectController::dismissReminder()`. Add any new reminder field there too. The non-floating branch must use the *raw* date (`getRawOriginal('date')`), not the formatted accessor.
- UI cues: purple banner on incomplete recurring tasks, confirmation dialog on completion, 🔄 next to status, purple border/tooltip on the quick-complete button.

## `TaskLifecycle::changeStatus()`

The only place that should change a task's status. It handles descendant cascades (complete/archive), `completed_at`, change logging, recurring rollover, and archiving the next occurrence on re-open. `TaskController::update()`, `updateField()`, `bulkUpdate()` and quick-complete all delegate to it. `createNextOccurrence()` is public for backfill use.

Quick-complete forms resubmit the task's full tag set (`tag_ids[]` from `$task->tags`, deliberately unfiltered for archived tags) so completing never detaches a tag.

## Backfill

`php artisan tasks:backfill-completed-at [--dry-run]` stamps `completed_at` (from `updated_at`) on done/archived tasks that lack it and catches still-recurring ones up. It runs outside a request, so it logs in as the task's creator for change-log attribution (same convention as `BackfillTaskLogs`).

## Known Limitation

A future day's recurring tasks that haven't been generated yet (the prior occurrence isn't completed) won't appear in that day's export or view. Projecting them would need read-only "what would `getNextOccurrence()` chain forward to" logic kept strictly separate from the real completion-triggered rollover, so a virtual task is never mistaken for a real one.
