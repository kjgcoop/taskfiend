---
paths:
  - "app/Services/ProjectTemplateArchive.php"
  - "app/Services/TemplateReadme.php"
  - "app/Http/Controllers/ProjectTemplateController.php"
  - "app/Http/Controllers/DataExportController.php"
  - "app/Http/Controllers/ScheduledProjectController.php"
  - "app/Models/ProjectTemplate.php"
  - "app/Models/ScheduledProject.php"
  - "app/Console/Commands/CreateScheduledProjects.php"
  - "tests/**/*Template*"
---

# Project Templates

## Data Model

- `ProjectTemplate` (table `project_templates`): `name`, `description` (nullable), `filename` (path to the stored zip on the `private` disk), `created_by`, `is_public`. Relations: `creator()`, `projects()` (via `projects.template_id`), computed `last_used_at` (max `created_at` of those projects).
- `projects.template_id`: nullable FK, `nullOnDelete()`, set once at creation.
- `ScheduledProject` (table `scheduled_projects`): when `createFromTemplate()` is given a future start date it creates one of these instead of a project; the `CreateScheduledProjects` command later turns due ones into real projects.

## Controller Actions (`ProjectTemplateController`)

Routes: `templates.index`, `.store`, `.importZip`, `.createFromTemplate`, `.update`, `.destroy`.

- `index()`: own templates plus other users' public ones (the nav's `$navTemplates` uses the same set).
- `store()`: "save project as template". Only the project's creator may. Always creates a **new** template row (no update-in-place mode).
- `createFromTemplate()`: extracts the zip and creates the `Project` and its full task tree in one call.
- `importZip()`: stores an uploaded zip directly as a template. The page must render `$errors` (a file over `upload_max_filesize` otherwise fails silently).
- `updateName()` / `destroy()`: creator-only. Visibility can be toggled after creation.

## `ProjectTemplateArchive` Is the Only Zip Reader/Writer

`build(Project)` returns a temp zip path (shells out to the `zip` binary) or `false`. `readManifest(zipPath)` validates without importing. `createProject(zipPath, name, user, ?templateId, ?templateName)` returns a `Project`. Callers: `ProjectTemplateController`, `DataExportController`, `ScheduledProjectController::createNow()`, `CreateScheduledProjects`. A bad zip throws `InvalidTemplateException` with a user-facing message; callers also catch `\Throwable` (report plus generic message).

- **Zip contents**: `template.json` (project name/description), only **incomplete** tasks, each task's date/time stripped to null (templates are deliberately date-less), assignees **not** captured (written as `[]`) and ignored on import even if a manifest lists some, since ids from another instance point at the wrong users (every imported task is assigned to the importer only), attachment files physically copied, and a human-readable `README.md` from `TemplateReadme::build($data)`. Import ignores the README; older zips without it still import. "Task Fiend" is hardcoded there rather than `APP_NAME`.
- **Tags are matched by name, case-insensitively**, created only if missing. Manifest tag ids only link tasks to that manifest's own `tags` list, because ids from another instance point at the wrong tag.
- **Duplicate attachment filenames** get `_N`-suffixed names in `attachments/`, and the manifest `path` records the suffixed name.
- **Imports are all-or-nothing**: one DB transaction; every copied file is recorded, and on any exception the project/tag/task/attachment writes roll back, files and extraction dir are deleted, and the exception is rethrown. The project creation is change-logged on every path. Tests: `TemplateImportAtomicityTest`, `ProjectTemplateArchiveTest`.

## Two Separate Mechanisms: Don't Conflate

`DataExportController::exportProjectTemplate()` / `importProjectTemplate()` let a user download/upload a project as a template zip with **no** `ProjectTemplate` row involved. The browser-based "Import All Data" (`DataExportController::importAll`) is disabled (`abort(503)`) and hidden from the UI; re-enabling it would need ID remapping. `php artisan todoist:import` is a separate, working CLI import.

## Files

- Stored templates: `storage/app/private/project-templates/*.zip` (private disk).
- `storage/app/temp` is scratch only (extraction dirs, export zips streamed with `deleteFileAfterSend`). `php artisan temp:prune` runs daily at 03:00 and deletes entries older than 24h. Feature tests hitting export endpoints leave zips there because the test client never calls `send()`.

## Nav Dropdown

Desktop: an inline expandable list under Templates in the More menu (chevron uses `@click.stop` because the More panel closes on any click inside it). Mobile: an expander like Projects/Tags, no "Add New". Templates have no show page; items link to `templates.index#template-{id}` and each card has that `id` plus a `target:` ring.

## In Progress

"Template drafts": edit a template's contents as a live, editable project, then save back or discard. See `spec.md` / `implementation-plan.md` (both local, gitignored) as it lands.
