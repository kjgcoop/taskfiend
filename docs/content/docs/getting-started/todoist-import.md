---
title: "Migrating from Todoist"
---

[Here](https://taskfiend.online/about/todoist-differences/)'s a brief overview of the differences between Task Fiend and Todoist. This is just the stuff I thought of off the top of my head; it is by no means exhaustive.

Task Fiend includes a one-time import command that pulls your active tasks, projects, labels, comments, and attachments from Todoist. 

> This is a command-line import, run once from the server. It's unrelated to (and unaffected by) any
> data import options you may see — or not see — in the web UI.

## Before you start

You'll need:

1. Your **Todoist API token** — find it at Todoist Settings → Integrations → Developer
2. A **Task Fiend user account and API key** for the account that will own the imported data:
   ```bash
   php artisan user:create your@email.com "Your Name" password123
   php artisan apikey:create your@email.com
   ```
   Note the API key — it's only shown once.

## Running the import

```bash
php artisan todoist:import --taskfiend-api-key=tfk_xxxxx --todoist-api-key=YOUR_TODOIST_KEY
```

The Todoist key can also be set as an environment variable instead of a CLI option:

```bash
export TODOIST_KEY=YOUR_TODOIST_KEY
php artisan todoist:import --taskfiend-api-key=tfk_xxxxx
```

Progress is printed to the console as it runs. Warnings and errors are written to Laravel's log files. A summary is shown when it finishes.

## What gets imported

| Todoist | Task Fiend | Notes |
|---------|-----------|-------|
| Projects | Projects | Flat; nested projects become top-level |
| Labels | Tags | Created if they don't exist; reused if they do |
| Active tasks | Tasks | Assigned to the importing user |
| Subtasks | Tasks | Imported after parent tasks |
| Comments | Comments | Attributed to the importing user |
| Comment attachments | Attachments | Downloaded and re-uploaded |
| Recurring tasks | Recurring tasks | Pattern converted; logged if unrecognizable |
| Due dates and times | Dates and times | Pacific timezone assumed |

## What doesn't get imported

- Completed tasks
- Todoist priority levels (no equivalent)
- Sections (no equivalent)
- Collaborator information (everything is assigned to the importing user)
- Task attachments (Todoist's API doesn't expose these separately from comments)

## Duplicate handling

Running the import more than once is safe. Items that already exist are skipped, not overwritten:

- **Projects**: skipped if a project with the same name already exists
- **Tasks**: skipped if a task with the same name exists in the same project
- **Tags**: existing tags are reused silently

## After the import

Check in the Task Fiend UI that:

- Projects were created
- Tasks are in the right projects
- Tags are applied
- Comments are visible
- Attachments are downloadable
- Recurrence patterns are working as expected

Any recurrence patterns that couldn't be converted are logged as warnings — search the logs for those task names and set the pattern manually.

## Known limitations

- Subprojects are flattened to top-level projects (Task Fiend doesn't support nested projects)
- Some complex Todoist recurrence patterns may not convert cleanly — these are imported without recurrence and logged
- All imported content is owned by the user identified by the API key; collaborator relationships are not preserved
