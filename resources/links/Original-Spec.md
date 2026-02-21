## Background
This is the spec I fed to Claude to get the first draft of this software created. After some back and forth, there were features added/changed, but I include this here because I thought it might be interesting.

## Summary

Task Fiend is a to-do list software written in Laravel to run on a Debian variant of Linux. Because I'm only expecting two or three users, Sqlite is fine. Please use Alpine as the frontend.




## Properties

I don't think dates are necessary on these tables as we'll have a change log (described at the bottom). Please let me know if you disagree. I'm open to discussion on this point.

### Tasks

*   Name
*   Description
*   Status: incomplete, done or archived. Because there are only three statuses, I think these values can be hard-coded rather than referencing another table.
*   Creator
*   Datetime - this is the day on which it should appear on my list. I may treat it as a due date or simply informational. A later version may implment these as separate properties, but right now, there should just be one date and the user can use it however they want.
*   Project ID - can be null
*   Recurrence pattern - null or "daily" or "every second Tuesday" or whatever terms the user used.

### Task attachments

*   User ID
*   Task ID
*   Path to file

### Comments

Tasks can have comments which can have attachments. Comments are as follows:

*   User ID
*   Task ID
*   Comment
*   Path to file - I realize this means there can only be one attachment per comment. I can live with this.

### Assignments

*   Task ID
*   Assignee - the person expected to do the work
*   Assigned by

### Tag properties

Tags can be assigned to multiple tasks. Tasks can have multiple tags, although a given tag can only be applied once to a specific task. Tags are global - they have no owner. All users can CRUD all tags.

*   Tag name
*   Color

### User properties

Please use standard Laravel methods for password encryption and session management.

*   Email address (this will also function as the username)
*   Human name
*   Enabled timestamp - null if enabled; if there's a value, the user is disabled

### Projects

A user can see a project if they created it or if they're assigned a task within it. If there are no tasks, the creator should still be able to see it, but no one else can until tasks are added.

*   Name
*   Description
*   User Id
*   Status: like tasks, the the possible statuses are incomplete, done or archived. Because there are only three statuses, I think these values can be hard-coded rather than referencing another table.

### Change Log

*   Date
*   User ID
*   Type of altered thing - this should correspond to the name of the table where the thing is stored (users, projects, tasks, etc)
*   Human description formatted something like "\[User's human name\] changed title from a to b" or "\[User's human name\] marked b done."

### API Keys

*   Key (hashed)
*   User ID
*   Created date
*   Valid - null if valid, datetime if invalid

## Ways it Should Differ from Existing Software

### Can Assign Tasks to Multiple Users

*   Any user assigned to a task can alter its title, description or add attachments or comments.
*   The task creator can add and remove assignees users. I cannot unassign myself from a task I created.
*   If I'm assigned to a task someone else created, I can remove myself but no one else.
*   If not itemized above, only a task's creator can perform an operation, such as deleting comments or attachments.
*   In the portion of the task add/edit view where you can change assignments, list all users with checkboxes next to their names. Because we're only expecting a few users, I think a combo-box would be excessive.
*   Unless otherwise specified, new tasks get assigned to the user who created them.
*   Tasks should be private by default. Only I can see a task I create unless I assign an it to someone else. Tasks can only be seen by their creator and any assignees.

### Command Line Scripts

*   It needs a command line script to create a new user. A browser-based admin panel is not necessary. It should require username, human name and password as parameter. Fail with a human-friendly message if there is already a user associated with that address.
*   It needs a command line script to toggle a user as enable or disabled. Again, a UI isn't necessary. All it requires is an email address. Fail with a human-friendly message if there is no user associated with that address.
    *   If disabled, the only change should be toggling the enabled/disabled and last updated field in the users table. Nothing should be deleted. Tasks they've created and assigned to other users should still be visible to those other users. They should no longer be able to log in.
    *   If re-enabled, it should just alter enabled/disabled and last updated in the users table. Because we didn't delete or change their tasks or projects, no other changes should be necessary.
*   It needs a command line script to create API keys. Takes an email address as a parameter and returns an API key
*   It needs a command line script to invalidate API keys. Takes the API key as a parameter.

### Additional Details

*   A task in detail view will have an option under the menu that shows more options to be archived. This will dismiss it like marking a task as done, except that when a list of colored tasks is generated, it shouldn't include archived tasks. Anyone assigned to a task can change its status.
*   Projects can be marked as done or archived.
*   Tasks and projects cannot be deleted, only completed or archived.
*   Some task software supports a kanban boards. Task Fiend doesn't need that functionality at this time.
*   Dates humans see should be in the format Weekday, Month number day, four digit year. For example, Monday, November 10, 2025.

## Some of the Ways in Which it Should Be the Same:

### Dates

I like software that is very good at interpreting inline human dates.

#### Date Formats

Tasks are always in the future. "Dentist May 28" should create a task titled "Dentist" that will appear on the next May 28. If I create it on May 27, 2026, it should be in 2026. If I create it on May 29, 2026, it should be in 2027.

A date in the format \[number\]/\[number\] should be assumed to be month/day. For example, 11/10 would be November 10th.

A date in the format \[four digits\]-\[two digits\]-\[two digits\] should be assumed to be year-month-day. 2026-11-10 would be November 10, 2026.

#### Recurring Tasks

*   Date formats it should recognize for recurring tasks:
    *   Daily
    *   Weekly (like "Wednesdays" or "every two weeks")
    *   Monthly (like "every 23rd" or "every third Monday")
    *   Yearly
    *   Multiple days per week (Sun,Mon - no space between - for Sundays and Mondays)
    *   The term "weekdays" for Monday through Friday
    *   The term "weekends" for Saturday and Sunday.
*   When a recurring task is completed, mark the status as completed and create a new one on the next date with matching title, description, attachments and assignee(s). Don't duplicate comments or comment attachments onto the new version.
*   Don't let more than one occurrence exist: if I have a daily task to brush my teeth, and I don't mark it done today, don't create a new one tomorrow. Leave the task in the overdue pile.
*   Store the recurrence pattern (e.g., "every Monday") in the database so it can be displayed and edited.
*   It should have an API which will authenticate users with a bearer token created by the script described above. The API doesn't need to be comprehensive, but it should have the following endpoints:
    *   Create tasks - it should support all the recurring date formats listed above
    *   List all tasks completed on a given day which will be supplied in the format YYYY-MM-DD. 2025-11-10 would be November 10, 2025.
    *   Retrieve all tasks on the supplied day. Same format as above.
*   If it's necessary to specify a time zone, please use Pacific Standard Time. I think that's the correct name. It's the same time zone as Los Angeles, CA and Portland, OR.

### Other Task Properties

*   Tasks can have tags - see table description toward the top of this document.
*   Tasks can be in up to one project. Task Fiend should support however many projects Sqlite's integer type will support. A task should only be in one project, but can be moved between projects.

### Para-Task Funtionality

#### Change log

See the table description above. A user can see changes about any task or project they have access to, regardless of who made the change. There should be a way to see changes by:

*   Task
*   Project - this should show both the project details itself and the tasks in the project
*   Tag - similarly, show details about both the tag and the tasks in the tag.
*   User - users can only see their own change log

#### Search

If multiple types of terms are supplied, they should be cumulative such that for a task to be returned, all of these must be true.

*   By task name
*   By task description
*   By tag(s) - if multiple tags are specified, return tasks with all of those tags assigned.
*   Project(s)
*   Assignee

#### Inbox

Show all tasks with no project assigned in a list called Inbox.

## UI

*   All pages should work on both desktop and smaller screens like phones.
*   When I first log in, I should see a list of tasks assigned to today.
*   Please sort tasks by the datetime.
    *   It should look like Google Calendar
    *   If there are more tasks than fit in the box, please hide overflow rather than making the day very tall.
    *   When they click on a day, they should see the complete list of all tasks on that day. I'm thinking modal for this, but I'm open to other ideas.
*   When a user clicks a task (whether they're in the today view or the calendar view or elsewhere), a panel should pop out from the right with the task details.
*   Menu items:
    *   Today
    *   Inbox
    *   Calendar
    *   Search
    *   Projects
    *   Tags

## Potential Features for Later Versions: 

*   Sync with Google calendar. I tried to implement this myself and found it not simple.
*   Kanban board
*   Task statuses beyond archived/done
*   User able to specify the formats in which they want to see dates.
*   Multiple time zones
*   Gamification
*   Allow watchers distinct from assignees
*   Let users decide what they want to see on login.
*   Separate view as and due date properties
*   Other calendar intervals
*   Task duration
*   Hourly task view
*   Other task sorts including manual
