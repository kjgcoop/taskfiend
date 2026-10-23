# Changes Since the Last Release

These changes have been implemented since the last release. This file is not cumulative: when a release ships, its contents are used to write the release notes and then cleared, so it only ever describes what's new since the previous release.

## Upgrade Steps

Run these after deploying:

```bash
npm run build
php artisan migrate
php artisan templates:convert-to-projects
php artisan tasks:backfill-creator-assignments --dry-run   # preview
php artisan tasks:backfill-creator-assignments             # apply
```

It assigns each existing task to its creator when the creator isn't already an assignee (see "The Creator Is Always Assigned" below). It's safe to run more than once.

## Markdown Exports Match What's on the Page

Export MD on a project or tag page now exports only what's showing: folded Done and Archived sections and collapsed subtasks are left out, as on the Today page. On Overdue and Search, typing in the page's filter box now narrows the export to the rows that match it; with no filter, they still export every result.

## Watching Project Tasks on Your Day

New under Day Preferences on your profile: **Also show dated tasks from projects I'm assigned to**. When it's on, tasks dated for a day appear on that day's page even if they're assigned to someone else in a project you belong to, so you can follow along without being added to every task. It's off by default, so nothing changes until you opt in.

- Applies to the Day page (including its Done and Archived sections and past days), the day exports (Markdown, PDF, PNG), the morning digest and PNG emails, the day API and the date suggestions while typing a task.
- Doesn't apply to Overdue, Undated, All Tasks or the Calendar, which still show only tasks assigned to you. A project-mate's backlog never lands on your Overdue page.
- Tasks that are there only because of this setting carry a small **Not Assigned to You** label (an eye icon in the agenda grid), with a tooltip naming who they're assigned to, and their title, project name and tags are shown in muted gray, so they're easy to skim past.
- Opening such a task in the side panel shows a notice above its name explaining that it isn't assigned to you directly, and linking to the setting on your profile.
- Only dated tasks are shown. Undated tasks in your projects stay on the project page.
- Run `php artisan migrate` after deploying; it adds one column to users.

## Project Members Can See Each Other's Tasks

Anyone who owns a project, or is assigned to it, can now find every task in it, not just on the project page. Before, a task showed up in search, on tag pages, in the change log, in parent-task pickers and in bulk actions only if you had created it or been assigned to it.

- Search, tag pages and tag counts, the change log, parent-task pickers, location suggestions, reordering, bulk actions and the full data export now include tasks from projects you belong to.
- Being assigned to a single task still lets you see that one task, and nothing else in the project.
- Your own lists are unchanged: Overdue, Undated, All Tasks and the Calendar still show only tasks you created or are assigned to. A project-mate's overdue tasks will not pile up on your Overdue page. The Day page, morning emails and day API work the same way unless you opt in to the setting above.

## Fixes

- **Template tasks in search.** Tasks that belong to a template no longer show up in search results.
- **Project page for members.** Opening a project you're assigned to but don't own showed a stuck-open status dialog and dozens of console errors, because the page's scripts only loaded for the project's owner. The page now works the same for every member.

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
