# Implementation Plan

Please be mindful that Alpine is in CSP-safe mode.

> This plan replaces an earlier draft-based design (a temporary `template_draft`
> project, extracted from a template's zip, edited, then either rebuilt back
> into the zip or discarded). That design was discussed and dropped before
> anything was built, in favor of the simpler design below: a template is a
> `Project` row, permanently, edited directly with no draft/save/discard
> cycle. See `spec.md`'s "Templates — target design" section for the full
> reasoning and the currently-unresolved open questions it lists.

## Core Features

- [x] **1. Templates as projects — schema**
      Add a migration giving `projects` two new columns: `project_type` (string,
      database-level default `'normal'`; app-level validated constants for
      `'normal'` and `'template'` — plain string, no native DB-level enum,
      consistent with how `status` is already handled) and `is_public` (boolean,
      database-level default `false`). Adding columns with a `DEFAULT` in SQLite
      backfills that default into every existing row automatically — no separate
      backfill command needed for the defaults themselves. Verify explicitly
      after migrating that no existing project ends up with a null/empty
      `project_type`. No behavior change yet beyond the columns existing. Add a
      migration/model test confirming the defaults and confirming existing
      projects read back as `project_type = 'normal'`, `is_public = false`.

- [x] **2. Migrate existing templates into `projects`**
      Write a console command (following the pattern of
      `app/Console/Commands/BackfillMissingCompletedAt.php`) that, for every
      existing `project_templates` row not yet converted: builds a new
      template `Project` (`project_type = 'template'`, `is_public`, `name`,
      `description` carried over, `user_id` from the old `created_by`) and
      populates its full `Task` tree from the stored zip. Reuse
      `App\Services\ProjectTemplateArchive` (`readManifest()` / `createProject()`;
      the old controller helpers named in `spec.md` no longer exist) and then
      set the template-specific fields on the result.
      **Idempotent, tracked via the change log (no extra column):** on
      conversion, write one `ChangeLog` entry on the new project using the
      structured fields — `entity_type = 'projects'`, `entity_id` = new project
      id, `verb = 'converted'`, `field = 'project_template_id'`, `old_value` =
      the old `project_templates.id`. Before converting a row, check for an
      existing entry with that `old_value`; if present, skip creating a new
      project. This also works for templates with zero tasks.
      **Repoint references** (also re-run-safe, using the same change-log
      entries as the old-id → new-id map): every `projects.template_id` AND
      every `scheduled_projects.template_id` that referenced an old
      `project_templates.id` must be repointed to the new template project's id.
      `scheduled_projects.template_id` is a FK to `project_templates` with
      `cascadeOnDelete()` — change that FK to `projects` too (and
      `projects.template_id`'s FK likewise; still nullable/`nullOnDelete()` for
      the latter), in a new schema migration. Order matters: the repoint must
      run before the FKs are changed, or the command must handle that ordering
      explicitly — design and test it. A single bad zip must be reported and
      skipped without aborting the rest.
      **Purge is a separate step:** add a second command (or `--purge` flag)
      that deletes a template's zip file only if its change-log entry exists.
      Leave the `project_templates` table and `ProjectTemplate` model in place;
      dropping them is a later decision (see `spec.md`'s open questions).
      Tests: seed a `ProjectTemplate` with a real zip, plus a project and a
      scheduled project pointing at it; run the command; assert the new
      template `Project` has the right type/`is_public`/`user_id`/task tree and
      both references now point at it; run it again and assert nothing is
      duplicated; assert an empty template converts; assert purge deletes the
      zip only for converted templates.

- [x] **3. Template editing — entry point and UI indicator**
      Add an "Edit Template" link on a template (now just a `Project` with
      `project_type = 'template'`) that takes the user straight to that
      project's existing show/edit page — no special route, no draft
      creation, no reuse-vs-create-new branching (there's only ever one
      `Project` per template now). On that page, when `project_type ===
      'template'`, show a clear, persistent visual indicator that this is a
      template, not a regular project — reuse the existing pattern
      established for the recurring-task purple banner (see `CLAUDE.md`'s
      Recurring Tasks section) rather than inventing new banner styling.
      Editing behaves exactly like editing any other project — tasks, tags,
      attachments, assignments all work unchanged, saved immediately, no
      explicit "Save Template" step. Add a test confirming the banner/indicator
      appears only when `project_type === 'template'` and confirming a normal
      task edit on a template project persists immediately (no draft/copy
      involved).
      **Authorization:** a private template (`is_public = false`) is visible
      and editable only by its creator. A public template is **viewable
      read-only** by other users (so they can see what they'd get) but
      editable only by its creator: other users get no edit controls and
      write requests are rejected (403) server-side. `is_public` grants
      visibility only when `project_type = 'template'`; a normal project's
      privacy rules are untouched. Put this check in one place (policy or
      model method) and reuse it. Tests: creator edits; other user views a
      public template read-only and cannot write; other user cannot view a
      private template; `is_public` has no effect on a normal project.
      **Header "Templates" dropdown:** each template in the header dropdown
      should link to that template's edit page (the project show page), not
      scroll to an anchor on the templates index. Add a test.
      A stronger "you are editing a template" indicator is intentionally
      deferred until the UI has been used; keep the banner minimal for now.

- [x] **4. Template tasks are date-less**
      Templates have no dates (the old zip flow nulled them). On a template
      project's tasks, every date/time entry control (task create/edit forms, quick-add, inline
      editing — find them all) must be shown but greyed out/disabled, with a
      short visible explanation (e.g. tooltip or helper text: "Templates don't
      have dates; set dates on projects created from this template"). Do not
      hide the controls. Also enforce server-side: task create/update on a
      project with `project_type = 'template'` ignores (nulls) any submitted
      `date`/`time`. Tests: disabled controls + explanation render only for
      template projects; a request submitting a date for a template task
      stores null; normal projects are unaffected.

- [x] **5. Project pickers and listings exclude templates**
      Audit and fix every place in the app that lists/queries a user's
      projects, to exclude `project_type = 'template'` rows unless that
      specific context is explicitly about templates. At minimum, check and
      fix: the dashboard, `ProjectController::index()`/`projects.index`,
      `Project::scopeActiveForUser()` and `Project::scopeForMember()`
      (`app/Models/Project.php`), the quick-add bar's `#project` token
      matching (`App\Services\QuickAddParser`), the search page's project
      filter (`SearchController`), every task create/edit project dropdown,
      bulk "move to project" actions (`TaskController::bulkUpdate()`), and the
      tag show page's project picker (`TagController::show()`). This is the
      same category of audit `CLAUDE.md`'s Sep 11, 2026 session did for
      archived/done projects leaking into pickers — expect comparable scope.
      Add tests for each site confirming a `project_type = 'template'` project
      never appears where a normal project would be offered (mirror
      `tests/Feature/TaskMoveProjectTest.php` / `tests/Feature/TagShowProjectPickerTest.php`
      in style). Also extend `tests/e2e/project-authorization.spec.js` to
      confirm (building on task 3's authorization rule, e2e level): a public
      template is viewable read-only by other users and not editable, a private
      template is not visible to them, and — critically — `is_public` has zero effect on a
      normal (`project_type = 'normal'`) project's existing privacy rules.
      Templates are reachable only via the templates page / header dropdown,
      never project lists. Explicit quick-add test: typing `I like
      #template-name` (where a template has that name) saves the task text
      exactly as typed, with no project assigned and no suggestion offered.

- [x] **6. Extend `duplicate()` for template instantiation**
      Prerequisite for task 7. The existing `Project::duplicate()` /
      `Task::duplicate()` hardcode `'Copy of '` and `auth()->id()`, which breaks
      for the console-run scheduled path where nobody is logged in. Extend both
      with optional parameters, defaulting to today's behavior so the existing
      `ProjectController` duplicate-project button is unchanged (add a
      regression test for it):
      - `name`: when given, used verbatim as the new project's name. When null,
        the `"Copy of "` prefix is applied only if a config value (backed by an
        `.env` entry, read via `config()` — not `env()` — default true) says so;
        add the entry to `.env.example` and the relevant `config/` file.
        Create-from-template passes the user-supplied name / `project_name`.
      - acting user: the user to record as project owner, task creator,
        assignee and `assigned_by`. Create-from-template passes the logged-in
        user; the scheduled path passes `ScheduledProject->user_id`. Instances
        are assigned **only** to that user, not to the template's assignees,
        even when a public template is instantiated by someone else.
      - Copies are always `project_type = 'normal'`, `is_public = false`, with
        `template_id` set to the template; test this.
      - Templates are date-less: tasks created from a template get `date` and
        `time` set to null (at every depth), as the old zip flow did. Add a
        test with a dated task inside a template.

- [x] **7. Template list page and "create project from template"**
      Rework `templates.index` (`ProjectTemplateController::index()`) to query
      `Project::where('project_type', 'template')` (own + public) instead of
      `ProjectTemplate`. No draft indicator badge is needed (there's no draft
      state to indicate — every template is always live). Reimplement
      "create project from template" (currently `createFromTemplate()`, which
      calls `ProjectTemplateArchive::createProject()`) to duplicate the
      template `Project` via `Project::duplicate()` (extended in task 6)
      instead of extracting a zip — it already filters to `status ===
      'incomplete'` tasks at every depth, which reproduces the current
      "archived/done tasks don't come along" behavior for free. Keep the
      `ScheduledProject` future-dated path working the same way, just backed
      by `Project::duplicate()` instead of the zip at fire time (drop the
      "template file missing" check in `CreateScheduledProjects`; it no longer
      applies) — `CreateScheduledProjects` should still read the template's
      *current* state when it actually runs, not a snapshot from when it was
      scheduled (this is existing, correct behavior — see `spec.md` — don't
      change it). Add tests: template list only shows `project_type =
      'template'` projects (own + others' public); creating a project from a
      template with an archived task excludes that task from the new project;
      a scheduled project reflects the template's state at creation time, not
      whatever it looked like when it was scheduled.
      **Second scheduled path:** `ScheduledProjectController` (the manual
      "create now" action, ~line 79) also builds from the template's zip
      today; switch it to the same `duplicate()` path and test it.
      **"Created from template" line:** a project with a `template_id` shows
      "Created from template <Name>" on its page. The name links to that
      template's edit page only if the viewer can edit it; otherwise plain
      text. If the template is archived, show the name plus "(archived)" with
      no link. Test all three cases.

- [x] **8. Template management actions (rename, visibility, delete)**
      `ProjectTemplateController` has `updateName()`, `toggleVisibility()` and
      `destroy()`, all written against `ProjectTemplate`. Rework them to act on
      the template `Project`: rename edits `name`; visibility toggles
      `is_public`; **delete archives the template** (`status = 'archived'`) —
      no hard delete and no file deletion. Archived templates disappear from
      the template list, the header dropdown and the create-from-template
      picker; projects already created from them keep their `template_id`.
      Only the template's creator may rename, toggle or delete (403
      otherwise). Tests for each action, including the 403s and the archived
      exclusion.

- [x] **9. Zip download/upload as a portability feature**
      Add a "Download as zip" action on a template `Project` that generates a
      zip on demand (reuse `ProjectTemplateArchive::build(Project)`, called against the
      template `Project` directly, not a draft). Keep zip upload
      (`ProjectTemplateController::importZip()`) working, but have it create a
      new `Project` (`project_type = 'template'`, via
      `ProjectTemplateArchive::createProject()`) instead of a
      `ProjectTemplate` row. Uploading a zip always creates a **new** template
      — there is no matching/replacing of an existing template by name or any
      other signal; this matches today's existing behavior and is not being
      changed by this task (see `spec.md`'s open questions if that's revisited
      later). Add a test: downloading a template as a zip and re-uploading it
      produces a second, independent template `Project` with the same task
      tree, not an update to the original.

- [x] **10. Docs: scheduled project creation reflects live template state**
      Add a short note to whichever `/docs` page covers scheduled project
      creation (or add a small new subsection if none currently covers it —
      check `docs/content/docs/features/`) stating explicitly that a project
      scheduled from a template will match the template's contents on the day
      it's actually created, not the day it was scheduled. This is existing,
      correct behavior (see `spec.md`'s verification of `CreateScheduledProjects`)
      that was previously undocumented — this task is doc-only, no code change.

## Wrap-Up

- [x] If you get stuck, write `STUCK.md` describing what was tried and why it failed. (Not stuck; nothing to write.)
- [x] Write `README2.md` with setup instructions, dependencies, configuration, and how to run the project. We should keep the regular README.md as is until we've worked with the new code for a little while.
