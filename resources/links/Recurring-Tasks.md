# Recurring Tasks - User Guide

## Overview
Task Fiend supports recurring tasks that automatically create new instances when completed. This allows you to manage repeating work without manually recreating tasks.

## How Recurring Tasks Work

### Creating a Recurring Task
1. Create a new task or edit an existing one
2. Set the **Recurrence** field with a pattern like:
   - `daily` - repeats every day
   - `weekdays` - repeats Monday-Friday
   - `every Monday` - repeats every Monday
   - `every other Wednesday` - repeats every 2 weeks on Wednesday (bi-weekly)
   - `every 3 weeks` - repeats every 3 weeks
   - `monthly` - repeats on the same day each month
   - `yearly` - repeats annually

**Important:** For recurring tasks to work properly:
- The task MUST have a date set
- The task MUST have a recurrence pattern set
- Both are required for automatic recreation

### Completing a Recurring Task Instance

**When you mark a recurring task as "done":**
1. ✅ The current task instance is marked as complete
2. 🔄 A new task instance is automatically created for the next occurrence
3. ♾️ The series continues indefinitely

**What gets copied to the next instance:**
- ✅ Task name
- ✅ Description
- ✅ Date/time (calculated for next occurrence)
- ✅ Project assignment
- ✅ Tags
- ✅ Assignees
- ✅ File attachments

**What does NOT get copied:**
- ❌ Comments (each instance has its own discussion)
- ❌ Completion status (new instance starts as "incomplete")

### Stopping a Recurring Series

There are three ways to stop a recurring task from creating new instances:

#### Option 1: Remove Recurrence Pattern First
1. Open the task
2. Click on the **Recurrence** field
3. Clear the recurrence pattern (delete the text)
4. Save
5. Now mark the task as "done" - no new instance will be created

#### Option 2: Archive Instead of Complete
1. Open the task
2. Change **Status** to "Archived" instead of "Done"
3. The series stops immediately

#### Option 3: Remove Recurrence After Completing
1. Mark the task as done (creates next instance)
2. Open the newly created instance
3. Remove its recurrence pattern
4. Future completions won't create more instances

## Visual Indicators

When viewing a recurring task, you'll see:

1. **🔄 Purple banner** at the top of task detail page explaining the behavior
2. **🔄 Icon** next to the status field in task detail view
3. **Purple text** in the Recurrence field showing the pattern
4. **Purple circle** (quick complete button) in task lists - hover to see what will happen
5. **Confirmation dialog** when marking as done from detail view (reminding you what will happen)

## Examples

### Example 1: Weekly Team Meeting
- **Task Name:** Team sync
- **Recurrence:** every Tuesday
- **Date:** 2026-01-07 (next Tuesday)
- **Behavior:** When marked done on Tuesday, creates new instance for January 14

### Example 2: Bi-Weekly Report
- **Task Name:** Submit status report
- **Recurrence:** every other Friday
- **Date:** 2026-01-10
- **Behavior:** When marked done, creates next instance for January 24 (2 weeks later)

### Example 3: Daily Standup
- **Task Name:** Daily standup
- **Recurrence:** weekdays
- **Date:** 2026-01-06
- **Behavior:** When marked done on Monday, creates instance for Tuesday; skips weekends

## Tips

- **Overdue recurring tasks** are left in the overdue pile - no duplicates are created
- **Same-day recurrence** (like daily tasks) won't create a new instance if one already exists for that date
- **Comments are instance-specific** - use them for notes about that particular occurrence
- **Attachments are copied** - if you need different files for each instance, remove/replace them after creation
- **Assignees are persistent** - all instances will have the same people assigned

## Troubleshooting

**Q: I marked a recurring task done but don't see the new instance**
- Check if a task with the same name and date already exists
- Verify the recurrence pattern is still set (it might have been cleared)
- Check if the task was archived instead of marked done

**Q: How do I complete just one instance without creating the next?**
- Remove the recurrence pattern before marking done, OR
- Mark as archived instead of done

**Q: Can I change the recurrence pattern for future instances?**
- Yes! Edit the most recent instance and update its recurrence pattern
- This affects future instances but not past ones

**Q: What happens if I edit a completed recurring task?**
- Changes only affect that specific instance
- The next instance is already created and won't be updated
- Edit the next instance if you need to make changes

## Related Documentation

- See `CLAUDE.md` for technical implementation details
- See `spec.md` for full application specifications
