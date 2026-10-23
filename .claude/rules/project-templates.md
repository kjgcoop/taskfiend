---
paths:
  - "app/Services/ProjectTemplateArchive.php"
  - "app/Services/TemplateReadme.php"
  - "app/Http/Controllers/ProjectTemplateController.php"
  - "app/Http/Controllers/DataExportController.php"
  - "app/Http/Controllers/ScheduledProjectController.php"
  - "app/Models/ProjectTemplate.php"
  - "app/Console/Commands/ConvertTemplatesToProjects.php"
  - "app/Models/ScheduledProject.php"
  - "app/Console/Commands/CreateScheduledProjects.php"
  - "tests/**/*Template*"
---

# Project Templates

## Data Model

- A template is a `Project` with `project_type = 'template'` (`Project::TYPE_TEMPLATE`, `isTemplate()`) plus `is_public`. Its tasks are ordinary tasks in that project. Templates are archived, never deleted.
- `projects.template_id`: nullable, points at the template *project* a project was created from.
- `Project::createFromTemplate($template, $name, $user)` is the one path behind create-from-template, the scheduled command and "create now": it calls `duplicate()` (which strips dates when copying from a template) and change-logs the result.
- `ScheduledProject` (table `scheduled_projects`): when `createFromTemplate()` is given a future start date it creates one of these instead of a project; the `CreateScheduledProjects` command later turns due ones into real projects, copying the template's contents as they are on that day.
- `ProjectTemplate` (table `project_templates`, old zip-backed rows) survives only for `php artisan templates:convert-to-projects`, the one-time upgrade step that turns old rows into template projects and repoints `projects.template_id` / `scheduled_projects.template_id`. It is idempotent via a `converted` change-log entry.

## Controller Actions (`ProjectTemplateController`)

Routes: `templates.index`, `.create`, `.storeNew`, `.store`, `.importZip`, `.download`, `.createFromTemplate`, `.toggleVisibility`, `.destroy`. Route-bound `{template}` is a `Project`.

- `index()`: own templates plus other users' public ones (the nav's `$navTemplates` uses the same set).
- `create()` / `storeNew()`: start an empty template from scratch (creator as sole assignee), then redirect to its project page.
- `store()`: "save project as template". Only the project's creator may. Always creates a **new** template project (no update-in-place mode).
- `createFromTemplate()`: creates the project and its task tree via `Project::createFromTemplate()`, or a `ScheduledProject` for a future date.
- `importZip()`: reads an uploaded zip into a new template project. The page must render `$errors` (a file over `upload_max_filesize` otherwise fails silently).
- `download()`: builds a zip with `ProjectTemplateArchive::build()`.
- `destroy()` (archives): creator-only. There is no rename endpoint: the template list links titles to the template's project page, where the title is click-to-edit via `projects.updateField`.

## `ProjectTemplateArchive` Is the Only Zip Reader/Writer

`build(Project)` returns a temp zip path (shells out to the `zip` binary) or `false`. `readManifest(zipPath)` validates without importing. `createProject(zipPath, name, user, ?templateId, ?templateName)` returns a `Project`. Callers: `ProjectTemplateController`, `DataExportController`, `ScheduledProjectController::createNow()`, `CreateScheduledProjects`. A bad zip throws `InvalidTemplateException` with a user-facing message; callers also catch `\Throwable` (report plus generic message).

- **Zip contents**: `template.json` (project name/description), only **incomplete** tasks, each task's date/time stripped to null (templates are deliberately date-less), assignees **not** captured (written as `[]`) and ignored on import even if a manifest lists some, since ids from another instance point at the wrong users (every imported task is assigned to the importer only), attachment files physically copied, and a human-readable `README.md` from `TemplateReadme::build($data)`. Import ignores the README; older zips without it still import. "Task Fiend" is hardcoded there rather than `APP_NAME`.
- **Tags are matched by name, case-insensitively**, created only if missing. Manifest tag ids only link tasks to that manifest's own `tags` list, because ids from another instance point at the wrong tag.
- **Duplicate attachment filenames** get `_N`-suffixed names in `attachments/`, and the manifest `path` records the suffixed name.
- **Imports are all-or-nothing**: one DB transaction; every copied file is recorded, and on any exception the project/tag/task/attachment writes roll back, files and extraction dir are deleted, and the exception is rethrown. The project creation is change-logged on every path. Tests: `TemplateImportAtomicityTest`, `ProjectTemplateArchiveTest`.

## Two Separate Mechanisms: Don't Conflate

`DataExportController::exportProjectTemplate()` / `importProjectTemplate()` let a user download/upload a project as a template zip with no template project involved. The browser-based "Import All Data" (`DataExportController::importAll`) is disabled (`abort(503)`) and hidden from the UI; re-enabling it would need ID remapping. `php artisan todoist:import` is a separate, working CLI import.

## Files

- Templates no longer live on disk; the old `storage/app/private/project-templates/*.zip` files are read only by `templates:convert-to-projects`.
- `storage/app/temp` is scratch only (extraction dirs, export zips streamed with `deleteFileAfterSend`). `php artisan temp:prune` runs daily at 03:00 and deletes entries older than 24h. Feature tests hitting export endpoints leave zips there because the test client never calls `send()`.

## Nav Dropdown

Desktop: an inline expandable list under Templates in the More menu (chevron uses `@click.stop` because the More panel closes on any click inside it). Mobile: an expander like Projects/Tags, no "Add New". Templates open on their project page (`projects.show`), as do the titles on `/templates`.

## Template Task Trees Stay Inside the Template

Unlike normal projects, a template's tasks may only be nested under tasks of the *same* template, and no outside task may be nested under a template's task. `Task::parentLinkAllowed()` is the rule; it's enforced in `TaskController::update()` and `updateField()`, and the parent pickers are filtered with `Task::scopeParentCandidatesIn()`. A template task that has a parent or subtasks can't be moved out (`Task::isTiedToTemplateTree()`, checked in `update()`, `updateField()` and `bulkUpdate()`). Project moves mirror this: a template's task can only move to another of the creator's templates, and any other task can never move into one (`Project::scopeMoveTargetFor()` validates in `update()`, `updateField()` and `bulkUpdate()`; `scopePickableFor()` feeds the task page/panel/edit dropdowns).

## Templates Stay Out of Work Lists

`assignedTo()` and `visibleTo()` are template-agnostic on purpose (an owner must still find a template's tasks to edit them). Any list of the user's *real work* chains `Task::outsideTemplates()` after them: undated, all, calendar counts, the day's done/archived groups (and the API's completed-on-day), tag pages/counts/change log, and search. Templates also can't be the default project or hearted. New work lists must add the scope.
