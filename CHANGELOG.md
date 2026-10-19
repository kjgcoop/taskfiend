# Changes Since the Last Release

These changes have been implemented since the last release. This file is not cumulative: when a release ships, its contents are used to write the release notes and then cleared, so it only ever describes what's new since the previous release.

## Upgrade Steps

Run this once after deploying:

```bash
php artisan tasks:backfill-creator-assignments --dry-run   # preview
php artisan tasks:backfill-creator-assignments             # apply
```

It assigns each existing task to its creator when the creator isn't already an assignee (see "The Creator Is Always Assigned" below). It's safe to run more than once.

## Project Members Can See Each Other's Tasks

Anyone who owns a project, or is assigned to it, can now find every task in it, not just on the project page. Before, a task showed up in search, on tag pages, in the change log, in parent-task pickers and in bulk actions only if you had created it or been assigned to it.

- Search, tag pages and tag counts, the change log, parent-task pickers, location suggestions, reordering, bulk actions and the full data export now include tasks from projects you belong to.
- Being assigned to a single task still lets you see that one task, and nothing else in the project.
- Your own lists are unchanged: Today, Overdue, Undated, All, Calendar, the morning digest emails and the day API still show only tasks you created or are assigned to. A project-mate's overdue tasks will not pile up on your Overdue page.

## Project Exports Require Project Membership

Exporting a project (Markdown or template) now requires owning it or being assigned to it as a project. Two problems are fixed:

- **A privacy leak.** Anyone assigned to just one task in someone else's project could export the whole project, including tasks they couldn't otherwise see. They now get a "no permission" error.
- **A wrongful block.** A project assignee who wasn't assigned to any individual task was refused. They can now export.

## The Creator Is Always Assigned

A task's creator is always one of its assignees and can't be removed. Several ways of creating a task didn't follow that rule:

| Way of Creating a Task | Example | Assignees Before | Assignees Now |
|---|---|---|---|
| Add a subtask | Sam adds a subtask under Alex's task | Alex | Alex and Sam |
| Bulk add with an `&` token | Sam adds a line ending in `&alex` | Alex | Alex and Sam |
| Bulk add under a parent | Sam adds lines under Alex's task | Alex | Alex and Sam |
| Duplicate a task | Sam duplicates Alex's task | Alex | Alex and Sam |
| API: create a task with `assignee_ids` | Sam's API key creates a task for Alex | Alex | Alex and Sam |

All of these now go through one shared routine, so a new way of creating tasks can't forget the creator. Tasks created before this release are fixed by the upgrade step above.

**API note:** if you have an integration that creates tasks for other people through the API, those tasks now also appear on the API key owner's lists.
