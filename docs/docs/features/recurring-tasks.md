# Recurring Tasks

## How it works

Set a recurrence pattern on any task. When you mark it done, the current instance is completed and a new one is automatically created for the next occurrence date. The series continues until you stop it.

**Requirements:** the task must have both a date and a recurrence pattern set. Without a date, the next occurrence can't be calculated.

## Creating a recurring task

Set the **Recurrence** field on any task to a pattern like:

- `daily` — every day
- `weekdays` — Monday–Friday
- `every Monday` — every Monday
- `every other Wednesday` — every two weeks on Wednesday
- `every 3 weeks` — every three weeks
- `monthly` — same day each month
- `yearly` — annually

See [Dates & Recurrence](dates.md) for the complete syntax.

## What happens when you complete one

1. The current task instance is marked done
2. A new instance is created for the next occurrence
3. The series continues indefinitely

**What's copied to the next instance:** name, description, date/time (recalculated), project, tags, assignees, file attachments.

**What's not copied:** comments (each instance has its own) and completion status (new instance starts as incomplete).

## Stopping the series

**Option 1 — remove the pattern before completing:**
Edit the task, clear the Recurrence field, save, then mark it done. No new instance is created.

**Option 2 — archive instead of complete:**
Change the status to Archived. The series stops immediately.

**Option 3 — remove the pattern from the next instance:**
Mark the current task done (creates one more instance), then open that new instance and clear its recurrence pattern.

## Visual indicators

- **Purple banner** at the top of the task detail page
- **🔄 icon** next to the status field
- **Purple quick-complete button** in task lists — hover to see a tooltip explaining what will happen
- **Confirmation dialog** when marking done from the detail page

## Floating recurrence

Prefix your recurrence pattern with `every!` (with an exclamation mark) to make the next occurrence relative to when you *complete* the task rather than the original due date.

**Example:** `every! 3 days` on a task due Monday that you complete Wednesday → next instance is Saturday (3 days from Wednesday), not Thursday (3 days from Monday).

Useful for habits where slipping a day shouldn't cause permanent catch-up.

## Tips and edge cases

- If you complete a recurring task and a next instance already exists for that date, a duplicate won't be created
- Overdue recurring tasks stay in the overdue pile — no automatic rescheduling
- Attachments are copied to each instance; remove or replace them if each occurrence needs different files
- Assignees are persistent across instances
- Comments are instance-specific — use them for notes about that particular occurrence
