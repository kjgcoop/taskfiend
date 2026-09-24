# Task Fiend - Development Progress

## Project Overview
Laravel + SQLite + Alpine.js task management app. See `spec.md` for full requirements.

## Completed

### Database (✓)
- All migrations created in `database/migrations/`
- Tables: users, projects, tasks, assignments, tags, task_tag, task_attachments, comments, api_keys, change_logs

### Models (✓)
All models in `app/Models/` with relationships and fillable fields:
- User, Project, Task, Tag, Assignment, TaskAttachment, Comment, ApiKey, ChangeLog
- Key note: User has `name`, `email_enabled_at` timestamp (null = enabled)

### Controllers (✓)
**Web Controllers** in `app/Http/Controllers/`:
- TaskController - CRUD with authorization, assignments, tags, change logging
- ProjectController - CRUD with access control (creator + assignees can view)
- TagController - CRUD (tags are global, all users can manage)
- CommentController - store/destroy with file attachments
- TaskAttachmentController - store/destroy/download
- DashboardController - today(), inbox(), calendar(), day()
- SearchController - search by name/description/tags/projects/assignees
- ChangeLogController - view logs by task/project/tag/user

**API Controller** in `app/Http/Controllers/Api/`:
- TaskApiController - create(), completedOnDay(), onDay()

### CLI Commands (✓)
In `app/Console/Commands/`:
- `user:create {email} {name} {password}` - Create users
- `user:toggle {email}` - Enable/disable users
- `apikey:create {email}` - Generate API keys (returns `tfk_xxxxx`)
- `apikey:invalidate {key}` - Invalidate API keys

### Routes (✓)
- **Web Routes** in `routes/web.php` - All resource routes for tasks, projects, tags, dashboard, search, changelogs
- **API Routes** in `routes/api.php` - Task creation and retrieval endpoints with bearer token auth
- **Bootstrap** configured in `bootstrap/app.php` to load both route files

### API Authentication (✓)
- **Middleware** `AuthenticateApiKey` in `app/Http/Middleware/`
- Validates bearer tokens against hashed api_keys table
- Checks user enabled status
- Registered as `auth.api` middleware alias

### Date Parser Service (✓)
- **DateParser** class in `app/Services/DateParser.php`
- Parses natural language dates and recurrence from task names (quick-add bar) and validates recurrence patterns entered directly in the task edit form
- Integrated into TaskController and API TaskApiController
- Auto-parses task name if datetime/recurrence_pattern not explicitly provided

**Supported date tokens (quick-add bar):**
- `today`, `tomorrow`
- Day name: `Monday`–`Sunday` → next occurrence of that day
- `next Monday` → skips this week, uses next week's
- `January 15`, `3/15`, `2026-03-15` → specific dates
- Multiple day names: last one wins for scheduling; earlier ones stay in the title
  - `"Letter on Sunday Tuesday"` → title: `"Letter on Sunday"`, date: Tuesday

**Supported recurrence patterns (quick-add bar + recurrence field):**
- `daily` / `every day`
- `weekdays`, `weekends`
- `every other day`
- `Fridays` / `every Friday` → weekly on that day (plural or "every" prefix signals recurrence)
- `every other Friday` → bi-weekly on that day
- `Monday, Wednesday, Friday` / `mon,wed,fri` → multi-day weekly
- `weekly` / `every week`, `every other week`, `every N weeks`
- `every N days`
- `monthly` / `every month`, `every N months`
- `every 3rd Sunday` / `every third Sunday` / `third Sunday of the month` / `every 3rd Sunday of the month` → monthly ordinal (supports 1st–4th/last, word or numeric form)
- `every 15` / `every 15th` → monthly on day-of-month
- `yearly` / `every year`
- `every!` prefix → floating recurrence (next occurrence relative to completion date, not scheduled date)
- Day abbreviations in recurrence field: `Thu`, `Thurs`, `Tue`, `Tues`, `Wed`, `Weds`, `Sun`, `Suns` all accepted

**Key parsing methods:**
- `parseTaskInput(string)` → extracts name, date, recurrence_pattern, recurrence_floating
- `getNextOccurrence(pattern, Carbon)` → returns next Carbon date for a stored recurrence pattern
- `isValidRecurrencePattern(string)` → returns bool; used for validation in TaskController
- `detectUnrecognizedPattern(string)` → returns error string if input looks like a recurrence attempt but doesn't parse

### Recurring Tasks (✓)
- **Implementation** in `app/Services/TaskLifecycle.php` (createNextOccurrence)
- **User Documentation**: See `RECURRING_TASKS.md` for complete user guide
- **Behavior**: When a recurring task is marked as "done":
  - The current task instance is marked as complete (status changes to "done")
  - A new task instance is automatically created for the next occurrence date
  - The series continues indefinitely until manually stopped
- **To complete just one instance**: Click the status field and change to "done" - this completes ONLY that instance and creates the next one
- **To stop a recurring series**: Remove the recurrence_pattern before or after marking done, or archive the task
- **UI Enhancements**:
  - Purple banner warning when viewing an incomplete recurring task
  - Confirmation dialog when marking recurring task as done
  - Visual 🔄 indicator next to status field
  - Informational text explaining what will happen
- **Prevents duplicate occurrences**: Won't create a new task if one already exists for the next date
- **Copies to next instance**: name, description, datetime, project, tags, assignments, attachments
- **Does NOT copy**: comments, completion status
- **Location**: `TaskLifecycle::changeStatus()` handles the full status state machine (descendant cascades, completed_at, change logging, recurring rollover); TaskController's update() and updateField() both delegate to it

### Project Templates (✓)
- **Model**: `ProjectTemplate` (`app/Models/ProjectTemplate.php`), table `project_templates`
  (migration `2026_03_27_000002_create_project_templates_table.php`). Fields: `name`,
  `description` (nullable), `filename` (path to the template's stored zip on the `private`
  disk), `created_by` (FK to `users`), `is_public` (bool). Relations: `creator()`, `projects()`
  (all `Project` rows created from this template, via `projects.template_id`), and a computed
  `last_used_at` accessor (max `created_at` across those projects).
- **`projects.template_id`** — nullable FK on `Project` → `ProjectTemplate`, `nullOnDelete()`
  (migration `2026_03_27_000003_add_template_id_to_projects_table.php`). Set once, at
  creation time, on any project created from a template.
- **Controller**: `ProjectTemplateController` (`app/Http/Controllers/`). Routes all under
  `routes/web.php` (`templates.index`, `templates.store`, `templates.importZip`,
  `templates.createFromTemplate`, `templates.update`, `templates.destroy`).
  - `index()` — lists the current user's own templates plus other users' public ones.
  - `store()` — **"save project as template"**: only the project's creator may do this;
    builds a real zip (via the private `buildTemplateZip()` helper, which shells out to the
    `zip` binary) and always creates a **new** `ProjectTemplate` row. There is currently no
    "update this template in place" mode — every save is a new template.
  - `createFromTemplate()` — **"create project from template"**: extracts the template's zip
    and, in one call, creates the `Project` row *and* its full `Task` tree (tasks, tags,
    assignments, attachments, parent/child structure). If given a future `start_date`, defers
    via a `ScheduledProject` row instead of creating immediately (see below).
  - `importZip()` — stores an uploaded zip file directly as a new `ProjectTemplate`, bypassing
    the "import as project, then save as template" round trip.
  - `updateName()` / `destroy()` — rename/delete an existing template (creator-only).
- **What a template's zip contains** (`buildTemplateZip()`): a `template.json` with the
  project's name/description, and only **incomplete** tasks (done/archived tasks are dropped).
  Each task's `date`/`time` are stripped to `null` — templates are deliberately date/time-less.
  Assignees are **not** captured (always written as an empty array), even though the read side
  (`createFromTemplate()`) does know how to restore an `assignees` array if present. Attachment
  files are physically copied into the zip.
- **`ScheduledProject`** (`app/Models/ScheduledProject.php`, table `scheduled_projects`) — when
  `createFromTemplate()` is given a future start date, it creates one of these instead of a
  `Project` immediately. The `CreateScheduledProjects` console command
  (`app/Console/Commands/`) later turns due ones into real projects.
- **Separate, unrelated template-shaped path**: `DataExportController::exportProjectTemplate()`
  / `importProjectTemplate()` let a user download/upload a project as a template zip file with
  **no** `ProjectTemplate` DB row involved at all — a parallel, file-only mechanism, distinct
  from everything above. Don't conflate the two when working in this area.
- **Nav dropdown**: `NavigationComposer` supplies `$navTemplates` (own + others' public, same set as
  `index()`). Desktop: an inline expandable list under Templates in the More menu (chevron uses
  `@click.stop` because the More panel closes on any click inside it). Mobile: an expander like
  Projects/Tags, with no "Add New". Templates have no show page, so items link to
  `templates.index#template-{id}`; each card on the index has that `id` and a `target:` ring.
-- **Where files live**: stored templates are `storage/app/private/project-templates/*.zip` (private
  disk). `storage/app/temp` is scratch only (extraction dirs, export zips streamed with
  `deleteFileAfterSend`); `php artisan temp:prune` (scheduled daily 03:00) deletes entries older than
  24h. Feature tests that hit the export endpoints leave zips there, because the test client never
  calls `send()`, which is what triggers `deleteFileAfterSend`.
- **Templates page shows `$errors`**: it didn't before, so a failed `importZip()` validation (most
  often a file over PHP's `upload_max_filesize`) just redirected back with no message.
- **In progress**: "template drafts" (edit a template's contents as a live, editable project,
  then save changes back into the template or discard them) — see `implementation-plan.md` /
  `spec.md` for that work as it lands.

### Frontend Views (✓)
**All views completed in `resources/views/`:**
- **Layout** - Updated navigation.blade.php with all menu items (Today, Inbox, Calendar, Search, Projects, Tags)
- **Dashboard Views** - today, inbox, calendar, day
- **Task Views** - index, create, show, edit (with comments and attachments)
- **Project Views** - index, create, show, edit
- **Tag Views** - index, create, show, edit
- **Search View** - Advanced search with filters for name, description, tags, projects, assignees
- **Changelog View** - Unified view for task/project/tag/user change logs
- **Components** - task-list component for reusable task display

### Dark Theme (✓)
**Complete dark theme implementation using Tailwind CSS:**
- **Main Background**: True black (`bg-black`) for deep dark appearance
- **Navigation & Containers**: Dark gray (`bg-gray-800`) with subtle borders (`border-gray-700`)
- **Text Hierarchy**:
  - Headers: `text-gray-100` (bright white)
  - Labels: `text-gray-300` (light gray)
  - Body text: `text-gray-400` (medium gray)
  - Muted text: `text-gray-500` (dim gray)
- **Form Inputs**: `bg-gray-700` backgrounds with `border-gray-600` borders, `text-gray-100` text, and `placeholder-gray-500` placeholders
- **Interactive Elements**:
  - Primary buttons: `bg-blue-600` (preserved for visibility)
  - Links: `text-gray-400` → `hover:text-gray-100`
  - Hover states: `hover:bg-gray-700` for cards, `hover:bg-gray-600` for dropdowns
- **Updated Files**: All views, components (navigation, dropdowns, modals, buttons), and layout files
- **Color Preservation**: Status badges (green/blue/gray) and tag colors maintained for visual hierarchy

### Testing & Bug Fixes (✓)
**Application tested and verified:**
- Database migrations confirmed running (12 migrations, all successful)
- Created test user via CLI: test@example.com / "Test User" / password123
- Generated API key: tfk_uZ0V0QerwN6RUbIbcGYBfRv8BOFWu1f6ubawBEaQ
- All 59 routes registered correctly (web + API)
- No PHP syntax errors in controllers or services
- Status enum values verified: 'incomplete', 'done', 'archived' (consistent across migrations, controllers, views)

**Bug Fix Applied:**
- Fixed DateParser to properly handle "every [day]" pattern (e.g., "Team sync every Tuesday" now correctly parses as "Team sync" instead of "Team sync every")
- Location: `app/Services/DateParser.php:54`

### E2E Testing with Playwright (✓)
**Comprehensive authorization and privacy test suite:**
- **Test Files** in `tests/e2e/`:
  - `task-authorization.spec.js` - 9 tests for task privacy/access control
  - `project-authorization.spec.js` - 11 tests for project privacy/access control
  - `tag-visibility.spec.js` - 10 tests for global tag access (tags visible to all, but don't bypass task/project privacy)
- **Helper Utilities**:
  - `helpers/db.js` - Database reset, seeding, cleanup (all use `--env=testing` flag)
  - `helpers/auth.js` - Login, logout, test user management
- **Configuration**:
  - `playwright.config.js` - Configured to use system Firefox (no browser download needed)
  - Uses test database at `database/test-database.sqlite`
  - Auto-starts Laravel dev server before tests
  - Creates 3 test users: user1@example.com, user2@example.com, user3@example.com (all use password: password123; domain from `TEST_USER_DOMAIN`, see `.env.testing`)
- **Test Coverage**: 30 tests ensuring users cannot see other users' data unless explicitly shared/assigned
- **Documentation**: See `TESTING.md` for quick start, `tests/e2e/README.md` for comprehensive guide

**Running Tests:**
```bash
npm run test:e2e              # Run all tests
npm run test:e2e:headed       # Watch in Firefox
npm run test:e2e:ui           # Interactive UI mode
```

## Database Management

### Production vs Test Databases
**Production Database:** `database/database.sqlite` (used by `.env`)
**Test Database:** `database/test-database.sqlite` (used by `.env.testing`)

### Critical: Always Specify Environment for Artisan Commands

**⚠️ IMPORTANT:** When running migrations/commands manually, always specify which database to use:

```bash
# PRODUCTION database (uses .env):
php artisan migrate:fresh --force

# TEST database (uses .env.testing) - ALWAYS use one of these:
php artisan migrate:fresh --force --env=testing
php artisan migrate:fresh --force --database=testing

# Create users in specific database:
php artisan user:create test@example.com "Test User" password123 --env=testing
```

### Database Connections
Configured in `config/database.php`:
- **sqlite** - Default connection, uses `DB_DATABASE` from `.env`
- **testing** - Dedicated test connection, always uses `database/test-database.sqlite`

### Test Environment Configuration
`.env.testing` uses in-memory drivers for better performance:
- `SESSION_DRIVER=array` - No database sessions needed
- `CACHE_STORE=array` - No database cache needed
- `QUEUE_CONNECTION=sync` - Immediate queue processing
- `DB_DATABASE=/path/to/test-database.sqlite` - Separate test database

## Key Patterns Used
- **Authorization**: Tasks/projects private by default, visible to creator + assignees only
- **Change Logging**: All CRUD operations log to change_logs table
- **No Deletion**: Tasks/projects cannot be deleted, only archived (per spec)
- **File Storage**: Uses `private` disk for task_attachments and comment_attachments
- **Alpine.js is loaded in CSP-safe mode** (no `unsafe-eval` in the CSP — see `csp_nonce()` on every
  `<script>` tag). Its directive expressions (`x-data`, `@click`, `:class`, etc.) go through Alpine's
  restricted, non-`eval` parser, which only understands a single JS *expression* — member access,
  comparisons, ternaries, function calls. It does **not** understand JS *statements*: no `const`/`let`,
  no `if`, no semicolon-separated blocks. Putting a multi-statement handler directly in a directive
  fails silently on click with a browser-console-only error (`Uncaught Error: CSP Parser Error:
  Unexpected token: ...`) — nothing shows on the page itself. Fix: put the logic in a method on an
  `Alpine.data()` component (registered on `alpine:init`) and call it with a bare expression, e.g.
  `@click="go()"` — see `dayPdfExport` in `resources/views/dashboard/day.blade.php`, or the
  established `sortBy()` / `staleBanner` pattern in the same file and `task-list.blade.php`. Full
  writeup: [Alpine.js & CSP](docs/content/docs/developers/frontend-csp.md).

## Current State
**Application is FULLY FUNCTIONAL and ready to use!**

To start the application:
```bash
php artisan serve
# Visit http://localhost:8000
# Login: test@example.com / password123
```

Test user already created with API key generated.

## Suggested Next Steps

### High Priority
- **API Testing**: Test API endpoints with generated key

### Medium Priority (Code Quality)
- Form Request classes for validation (currently inline in controllers)
- Policies for authorization (currently inline in controllers)
- Additional automated tests (PHPUnit Feature + Unit tests to complement E2E tests)
- Error handling improvements
- Input sanitization review

### Low Priority (Nice to Have)
- Email notifications for assignments
- Performance optimization (caching, eager loading)
- Accessibility audit

## Important Notes

### Session Summary (Sep 23, 2026) — Wrap-Up: Stuck Tasks
- Revisited all six Core Feature checkboxes in `implementation-plan.md` (Search Comments, Reminder
  Notes, Consistent Token Slugs, Background Image on Project Create, Parent Task in Sidebar,
  Single-Column PDF Export) — all were already `[x]` with zero `⚠️` failure notes anywhere in the
  file. Nothing was stuck, so no `STUCK.md` was written; checked off this Wrap-Up item as-is.

### Session Summary (Sep 23, 2026) — Wrap-Up: Full Test Run
- Ran `npm test` (`php artisan test` + `npx playwright test`) after all six Core Features in this
  plan (Search Comments, Reminder Notes, Consistent Token Slugs, Background Image on Project
  Create, Parent Task in Sidebar, Single-Column PDF Export) were checked off.
- **PHPUnit**: 505 tests / 1045 assertions, all passing.
- **Playwright**: this sandbox had no downloaded Chromium build (`~/.cache/ms-playwright` was
  empty — prior sessions' notes about `npx playwright install` being blocked no longer held;
  `npx playwright install chromium` succeeded this time and downloaded a working browser). Full
  56-test e2e suite: 55 passed, 1 failed on the first parallel (8-worker) run —
  `project-authorization.spec.js` → "direct project assignee sees all tasks including those not
  assigned to them" — with `page.goto('/tasks/create')` returning `net::ERR_ABORTED`. Re-ran that
  single test alone (`--workers=1`) and it passed; treated as parallel-worker contention against
  the shared SQLite dev server (`php artisan serve --env=testing`) rather than a real regression,
  since none of this plan's six features touch project-assignee task visibility. No code changes
  made for this task.

### Session Summary (Sep 23, 2026) — Single-Column PDF Export
- **The day-view PDF export (`DayPdfExporter`) is now always single-column.** Removed the
  `$columns` parameter from `DayPdfExporter::build()` and all multi-column layout code
  (`COLUMN_GAP`, the per-column `$columnX`/`$dividerXs` arrays, `drawColumnDividers()`, and the
  "advance to next column, else new page" branch — now just "new page when the current row
  doesn't fit"). `DashboardController::exportDayPdf()` no longer passes a columns argument.
  Removed `day_export_columns` from `config/taskfiend.php`, `DAY_EXPORT_COLUMNS` from
  `.env.example`, and updated `docs/content/docs/features/day-export.md` to describe the
  single-column layout. `DAY_EXPORT_PNG_WIDTH`/`DayPngExporter` (already single-column, unrelated
  setting) were left alone.
- **Tests**: `tests/Unit/DayPdfExporterTest.php` — a basic structural-validity check, plus a
  regression test that builds a PDF from 60 tasks (enough to overflow one page's worth of a single
  column under the old 2-column default) and asserts every task-name `Td` text operator shares the
  same x-position, with pagination (`/Type /Page ` appearing more than once) instead of a second
  column at a different x. Confirmed red against the pre-fix code (two distinct x-positions, one
  page) before implementing.

### Session Summary (Sep 23, 2026) — Parent Task in Sidebar
- **Feature**: the task sidebar panel (`resources/views/tasks/_panel.blade.php`, fetched by
  `TaskController::panel()` and injected into `#task-panel-content`) now shows and lets you edit a
  task's parent, matching the full task page (`tasks/show.blade.php`) exactly — same "Parent Task"
  label, same click-to-edit searchable dropdown, same "None (Top-level task)" placeholder, same
  read-only behavior when `$isInactive`.
- **Controller**: `TaskController::show()`'s candidate-parent query (visible, incomplete, excluding
  self + descendants to prevent cycles) was extracted into a private `availableParentsFor(Task
  $task)` helper; both `show()` and `panel()` now call it and pass `$availableParents` to their
  views — previously `panel()` didn't compute this at all, which is *why* the panel had no parent
  field before this session.
- **Shared, not duplicated**: `tasks/show.blade.php`'s `taskEditor` component had its own copy of
  `parentSearch`/`parentOpen`/`parentTasks`/`parentFiltered`/`selectParent`/`clearParent`; the
  layout's `taskPanelEditor` (`layouts/app.blade.php`) had none of it. Rather than copy that block
  into `taskPanelEditor` too, extracted it into a plain global `taskParentPicker()` factory
  function defined in `layouts/app.blade.php` right next to `slugify()` (same file, same
  cross-file-global-function pattern already established there — `layouts/app.blade.php`'s inline
  `<script>` tags aren't ES modules, so a `function foo(){}` declared in one becomes available to
  every other inline script on the page, including page-specific `@push('scripts')` blocks like
  `show.blade.php`'s, since `@stack('scripts')` renders after the layout's own script block).
  `taskParentPicker()` returns `{ parentSearch, parentOpen, parentTasks, parentFiltered(),
  selectParent(), clearParent() }` to be spread (`...taskParentPicker()`) into a host
  `Alpine.data()` component, which must supply `fields.parent_id` and (for `cancelEdit`'s reset)
  `original.parentSearch`. Both `taskEditor` and `taskPanelEditor` now spread this in.
  `parentFiltered` changed from a getter to a plain method (spreading an object literal can't carry
  a getter's accessor semantics — spreading a getter evaluates it once and copies the *value*, not
  the accessor) — every template reference (`show.blade.php` and the new block in `_panel.blade.php`)
  calls it as `parentFiltered()`, not the bare property `parentFiltered` the getter version allowed.
- **`taskPanelEditor` init**: `parentSearch`/`parentTasks` are populated from the panel's
  `data-task-json` payload (`_panelTaskJson`'s new `parentSearch`/`parentTasks` keys, same shape as
  `show.blade.php`'s inline `@js(...)` values) inside `init()`, same place `allTags`/`allProjects`
  already load from that payload. `fields.parent_id` was added to `taskPanelEditor`'s default
  `fields` object and to `_panelTaskJson`'s `fields` array (neither existed before — the panel had
  literally no notion of a task's parent). `cancelEdit('parent_id')` resets `parentSearch` from
  `original.parentSearch`, mirroring how `cancelEdit('date')` already resets `dateText` from
  `original.dateText`.
- **Not verified against a running browser**: this sandbox's Playwright install has no downloaded
  Chromium (`npx playwright test tests/e2e/task-panel.spec.js` fails with "Executable doesn't
  exist" for every test, unrelated to this change), and this session's sandbox restricts file
  access to the project directory, so no system/global browser binary from a prior session's setup
  was reachable either. Verified instead via `tests/Feature/TaskPanelParentTest.php` (asserts the
  panel's rendered HTML shows a set parent's name, the "None (Top-level task)" placeholder when
  unset, and a valid candidate parent's name in the picker's data) and `php artisan view:cache`
  (compiles every Blade template, including both edited ones, without error). Whoever next has a
  working Playwright browser install should click through: open the sidebar panel, edit the parent
  field via the dropdown and via typing a search, save, cancel, and confirm it matches the full
  task page's identical behavior.

### Session Summary (Sep 23, 2026) — Background Image on Project Create
- **Feature**: the project creation form (`resources/views/projects/create.blade.php`) now has an
  optional "Background Image" file input (`enctype="multipart/form-data"` added to the form).
  `ProjectController::store()` accepts `background_image` (`nullable|file|mimetypes:...|max:20480`,
  same rules as the existing per-project upload endpoint) and stores it after the `Project` row is
  created, since the storage path is keyed by the project's id.
- **Refactor**: the validation rules and the resize/store logic previously inlined in
  `uploadBackground()` are now shared helpers — `backgroundImageRules(bool $required)` and
  `storeBackgroundImage(Project $project, UploadedFile $file)` — called from both `store()`
  (`required: false`) and `uploadBackground()` (`required: true`, unchanged behavior/route). No
  functional change to the existing per-project upload flow, only extraction.
- **Tests**: `tests/Feature/ProjectCreateBackgroundImageTest.php` — create-with-image stores the
  file and sets `background_image`, create-without-image still works, an invalid file type is
  rejected and the project is never created. The "with image" test uses a fake `image/avif` upload
  (`UploadedFile::fake()->create(...)`, not `->image()`) to avoid the GD-based resize branch in
  `storeBackgroundImage()` — this sandbox's PHP has no `gd` extension
  (`function_exists('imagecreatetruecolor')` is false), so `->image()` throws and a real jpeg/png
  upload would hit `@imagecreatefromstring()` untested here. Whoever next has `gd` installed should
  spot-check a real jpeg/png upload through both endpoints.

### Session Summary (Sep 23, 2026) — Consistent Token Slugs
- **Bug**: the Create Task page's `#project`/`@tag` inline autocomplete
  (`resources/views/tasks/create.blade.php` — `selectAutocomplete()`, `createAndSelectTag()`)
  computed its own slug via `.toLowerCase().replace(/[^a-z0-9]/g, '')`, which deletes spaces and
  punctuation entirely (e.g. "Home Renovation" → `homerenovation`), instead of using the shared
  global `slugify()` helper defined in `resources/views/layouts/app.blade.php:117`
  (`.replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '')`, which dashes spaces: `home-renovation`).
  Three more copies of the same pattern (missing only the whitespace→dash step, so they preserved
  existing hyphens but never introduced them) lived in `layouts/app.blade.php` at the `#`/`@`
  inline-autocomplete handlers used when editing a task's name field (`handleNameInput()`,
  `selectNameAutocomplete()`, lines ~907/917/945).
- **Fix**: all of the above now call the shared `slugify()` (a plain global `function`, not
  Alpine-scoped, so it's already in scope wherever `<x-app-layout>` is used, including inside
  `Alpine.data()` components registered via `alpine:init`). No behavior change server-side —
  `QuickAddParser::parseProjectToken()`/tag matching already strip all hyphens when comparing, so
  a dashed or space-deleted token resolves identically today; this was purely about the *inserted*
  text into the task-name field being visually consistent with what quick-add and the search page
  already produce (`home-renovation` rather than `homerenovation`).
- **Tests**: `tests/Feature/TaskCreateSlugifyTest.php` — asserts the rendered `/tasks/create` page
  no longer contains the old inline regex and does call `slugify(...)` at the relevant call sites.
  Since server-side matching behavior is unchanged (see above), this is a rendered-output
  assertion rather than a `tasks.store` behavioral test, per `spec.md`'s guidance for this case.

### Session Summary (Sep 23, 2026) — Reminder Notes
- **Feature**: a project reminder (`project_reminders` table) can now carry an optional free-text
  `note` (nullable `string(255)`, migration
  `2026_09_23_000000_add_note_to_project_reminders_table.php`, added to `ProjectReminder::$fillable`).
  Set via a new "Note (optional)" text input on the reminder form on the project show page
  (`resources/views/projects/show.blade.php`), validated `nullable|string|max:255` in
  `ProjectController::storeReminder()`. Shown (escaped, muted `text-sm text-gray-400`) beneath the
  main line on the `/day` reminder bar (`resources/views/dashboard/day.blade.php`), on
  `projects/reminders-index.blade.php`, and in the reminder modal's "active reminder" summary and
  edit-prefill on the project show page.
- **Recurring reminders copy the note forward**: `ProjectController::dismissReminder()` already
  builds the next occurrence by copying fields explicitly (`recurrence_pattern`,
  `recurrence_floating`) — `note` is now copied the same way, so it doesn't silently disappear
  after the first occurrence of a recurring reminder.
- **Bug fix found and fixed along the way**: `dismissReminder()`'s non-floating branch passed
  `$reminder->date` — the model's `date` *accessor*, which returns a human-formatted display
  string (`Attribute::make(get: fn ($value) => ... ->format(config('app.human_date_format')))`),
  not a `Carbon` instance — into `DateParser::getNextOccurrence(string $pattern, Carbon
  $currentDate)`, which is strictly typed and threw a `TypeError` on every call. In practice this
  meant **any non-floating recurring project reminder silently failed to create its next
  occurrence when dismissed** (the exception surfaced as a generic 500, so the dismiss button
  still appeared to "work"). Only the floating branch (`now()`, already a real `Carbon`) worked.
  Fixed by parsing the raw stored value instead: `\Carbon\Carbon::parse($reminder->getRawOriginal('date'))`.
  Found because the spec's own acceptance test for this task ("dismissing a recurring reminder
  copies the note to the next occurrence") exercises exactly this path and failed with "next
  occurrence is null" until this was fixed — not a pre-existing-and-ignored failure, a real blocker
  for the feature this session was asked to build.
- **Tests**: `tests/Feature/ProjectReminderNoteTest.php` — note saved on store, note is optional,
  recurring dismiss copies the note to the next occurrence (also exercises the bug fix above), and
  the `/day` view renders the note.
- Not documented further in `/docs` — no existing feature page covers project reminders (`grep -rn
  "project reminder" docs/content/docs` found only a passing mention in `developers/api.md` of the
  `project_reminders` array in the `/api/tasks/on/{date}` response, which doesn't enumerate fields
  and needs no change — `note` is included automatically via `select('project_reminders.*')`).

### Session Summary (Sep 23, 2026) — Search Comments
- **Feature**: the search page's "Search in" row (`resources/views/search/index.blade.php`) gained a
  third checkbox, **Comments** (`search_comments`), alongside the existing Title/Description
  checkboxes — checked by default under the same `$hasSearchParams` rule as the other two. Matching
  is additive (OR): a task matches if the search text appears in the title, description, or any of
  its comments (`orWhereHas('comments', ...)` on `comments.comment`).
- **Visibility preserved**: the title/description/comments OR group is built *inside* the closure
  already scoped by `Task::visibleTo(Auth::id())` in `SearchController::buildSearchQuery()` — a
  comment match can never surface a task the searching user can't otherwise see. Covered by
  `tests/Feature/SearchCommentsTest.php::test_comment_text_match_does_not_leak_another_users_private_task`.
  Rewrote the three-way title/description/comments OR to just chain `orWhere`/`orWhereHas` for
  every checked field — Laravel treats the first condition inside a fresh closure the same whether
  called via `where` or `orWhere`, so no special-casing "first" vs "rest" is needed.
- **None checked + search text present → validation error, not silent fallback.** The old code
  silently defaulted to searching title+description if neither box was checked; that fallback is
  gone. `SearchController::searchScopeErrorMessage()` is the single check shared by `index()` and
  `more()` (also covers the markdown export path, since export is handled inside `index()`): with
  no search text, the checkboxes don't matter and no error is shown; with search text and all three
  boxes unchecked, it returns "Choose at least one field to search: Title, Description, or
  Comments." `more()` (an AJAX/JSON endpoint) returns that as a 422 JSON body; `index()` renders the
  search page normally (200, not a redirect) with empty results and the message shown in a banner
  above the search box, and manually flashes a `ViewErrorBag` to session so `assertSessionHasErrors()`
  still works even though there's no redirect to hang `withErrors()` off of (`view()` responses don't
  have that method — only `RedirectResponse` does).
- **Tests**: `tests/Feature/SearchCommentsTest.php` — comment match found when Comments is checked,
  comment match on another user's private task never leaks, text-present/none-checked error case,
  no-text/none-checked shows no error, `search.more` and the markdown export both honor
  `search_comments` too.
- Docs: `docs/content/docs/features/_index.md`'s Search Page section now mentions comments as a
  third targetable field and the none-checked validation error.

### Session Summary (Sep 23, 2026) — Heartbeat: visible-tab polling + live notification badge
- **Two separate timers, easily confused**: the day page's "it's past midnight" `staleBanner` is pure
  client-side (`new Date()`), no network. The once-a-minute AJAX call is the **session heartbeat** at
  the end of `layouts/app.blade.php`, polling `GET /auth/check` every `SESSION_CHECK_INTERVAL` seconds
  (`0` disables it).
- **Heartbeat now polls only while the tab is visible**, plus once immediately on `visibilitychange`
  back to visible. It redirects to `/login` only on a **401**; a 5xx/maintenance 503 no longer kicks the
  user to the login page.
- **`/auth/check` now also returns `unread`** (`NotificationsController::unreadCount()`), and the
  heartbeat updates the bell badge via `window.setNotificationBadge(n)`. The endpoint is read-only,
  never marks anything seen, so multiple tabs don't need to coordinate: `seen` state already lives
  server-side and every tab converges on the same count. The badge `<span id="notif-badge">` is now
  always rendered (hidden at 0) so it can appear after page load, and the bell menu refetches the feed
  on **every** open (it used to fetch once per page load), since that fetch is what marks items seen.
- **Known side effect, pre-existing**: every heartbeat request refreshes the session, so
  `SESSION_LIFETIME` never expires while a tab is open and visible.
- **Not done (deliberately)**: pop-up toasts for new notifications. That needs a "which browser/tab
  already announced notification N" record (e.g. last-shown id in `localStorage`) and was deferred.
- **Verified**: `tests/Feature/AuthCheckHeartbeatTest.php` (written; PHPUnit still not installable in
  this sandbox, so its assertions were exercised via a kernel-level script instead), and a real
  Chromium run against `php artisan serve` (with a dummy `public/hot` since npm is blocked, and the
  inline heartbeat doesn't need Vite/Alpine): badge hidden at load → shows "2" after notifications are
  inserted → no polls while `document.hidden` → updates to "3" immediately on becoming visible → hides
  when marked seen elsewhere → redirects to `/login` when sessions are wiped. The bell menu's
  refetch-on-open is Alpine-driven and was **not** click-tested (no built assets).
- **Follow-up: header too wide on an unfolded foldable phone.** Measured (Playwright + a hand-written
  stand-in for the Tailwind classes the nav uses, since npm/CDNs are blocked here) that the full
  header overflowed by 34–235px anywhere from 641 to ~880px, almost all from the left-hand nav links
  (~600px), not the right-hand icons (~200px) — so dropping the bell wouldn't have fixed it. Changes in
  `navigation.blade.php`:
  - Nav's layout breakpoint moved from `sm` (640) to `md` (768): the hamburger layout now covers up
    to 767px.
  - From `md` to `lg`, tighter spacing (link `space-x-4`, icon `gap-1`, less padding on the avatar
    button) and the "Search" text link hidden (`!hidden lg:!inline-flex`); the search icon still
    covers quick search, and a `lg:hidden` "Search" entry was added to the More menu so the advanced
    search page stays one click away. Measured: fits at 768 with ~39px to spare, ~112px at 841.
  - Hamburger layout gained notification access, which it never had: a red dot on the ☰ icon and an
    inline "Notifications (N)" collapsible in the slide-down menu (a second `notificationsMenu`
    instance, same feed endpoint). All indicators are `[data-notif-badge="count"|"dot"]` and
    `setNotificationBadge()` updates them all (verified: 0 → all hidden, 12 → "9+"/dot shown).
  - Not verified with real Tailwind/Alpine: exact pixel widths use a fallback font instead of Figtree,
    and the mobile Notifications collapsible's open/fetch wasn't click-tested.

### Session Summary (Sep 11, 2026) — Search token unification, project-picker audit, subtask inheritance, relative dates, Overdue export
- **Source**: `implementation-plan.md`, a five-item checklist (all items now checked off in that
  file). Landed as two commits — `b5a10ca` ("Mostly bug fixes") and a same-day follow-up,
  `94114a8` ("Files that got missed the last time"), which covers the controller/service-layer
  half of the same five items after the first commit turned out to be view/test-only for some of
  them.
- **Search page `#project`/`@tag` now goes through the canonical server-side parser**
  (`SearchController::parseTokens`, `POST`/`GET /search/parse-tokens`) instead of a hand-rolled JS
  regex tokenizer (`parseSearchInput()`, now deleted from `search/index.blade.php`). Same shape as
  the quick-add bar's own live-preview AJAX call: client sends raw text, `QuickAddParser` (see the
  Jul 11, 2026 entry below — this is another consumer of that single-source-of-truth service, not
  a new parser) resolves `#project`/`@tag` tokens server-side, client applies the result. `#inbox`
  is a search-only sentinel `QuickAddParser` has no concept of, so `parseTokens()` strips it before
  handing the rest to the parser. The autocomplete *dropdown* (suggestions while a token is still
  being typed) stays client-side, as planned — it's a UI affordance, not a matching decision; when
  a suggestion is clicked, its known project/tag id is passed straight through
  (`selectAutocomplete(name, id)`) instead of re-derived by re-parsing text. **Fixes multi-word
  project/tag names** (e.g. a project named "Home Renovation" typed as `#home-renovation`) — the
  old JS tokenizer's `slugify()`-and-compare approach worked fine for this case too in isolation,
  but the point of the change was collapsing two independently-maintained matchers into one; the
  multi-word case is what the plan asked to be confirmed and covered by test. See
  `tests/Feature/SearchParseTokensTest.php`.
- **Project pickers audited for archived/done projects.** Every place a task can be pointed at a
  project now goes through `Project::forMember()`/`activeForUser()` (see the Jul 11, 2026 entry) and
  rejects a `done`/`archived` target project with a 422/flash error rather than silently accepting
  it: `TaskController::store()` (already correct), `update()`, `updateField()`'s `project_id`
  branch (previously had **no** inactive-project check at all), `bulkUpdate()`'s bulk move-to-project
  action, and the API's `TaskApiController::create()`. Also fixed `TagController::show()`'s project
  dropdown (used by the quick-add bar and bulk-edit "move to project" picker on a tag's page) —
  it was filtering out only `archived` projects via a raw `where('status', '!=', 'archived')`,
  missing `done`; switched to `Project::activeForUser()` to match every sibling page using the same
  picker component. `tests/Feature/TaskMoveProjectTest.php` and
  `tests/Feature/TagShowProjectPickerTest.php` cover this.
- **Task/subtask project inheritance verified and fixed.** Creating a task while viewing a project
  already correctly set `project_id` (no bug there). Creating a subtask via the subtask tab did
  not: `TaskController::store()` set `parent_id`'s authorization check *after* the general
  `project_id` validation block, and separately, an inline `#project` token typed into the subtask's
  name (via `QuickAddParser`) could overwrite `project_id` after the parent-inheritance assignment
  ran. Fixed by moving the parent-authorization/inheritance block earlier (so a subtask's
  `project_id` is forced to match its parent's before the general project-access check runs) and by
  re-forcing `project_id` back to the parent's after `QuickAddParser` tokens are applied — a subtask's
  project is never negotiable via any input path, always the parent's. See
  `tests/Feature/TaskStoreTest.php`.
- **Relative date scheduling added to the task date field** (create/edit forms only, *not* the
  quick-add bar — deliberately kept as a second, narrower pattern rather than folded into
  `parseTaskInput()`'s date-token table, so quick-add/task-name parsing never picks it up).
  `DateParser::parseRelativeDuration()` (called from `resolveDate()`, which backs the existing
  `POST /tasks/parse-date` live-preview endpoint) matches the *entire* trimmed input against
  `^(\d+)\s+(day|days|week|weeks|month|months|year|years)$` — anchored on both ends, so `0`,
  negative numbers, fractional values (`1.5 weeks`), and compound input (`1 week 2 days`, "one
  month, three days") all simply fail to match and fall through to the existing "invalid date"
  error path, rather than needing separate rejection logic for each invalid shape. Always relative
  to `Carbon::today()`, never to whatever's already in the field. Resolved date renders through the
  same live-preview `<span>` as any other parsed date. See `tests/Unit/DateParserTest.php`.
- **Overdue list gained a markdown export**, matching the Today/Day page's existing export exactly:
  a new `overdueExport` Alpine component (`dashboard/overdue.blade.php`) sends the currently-visible
  `[data-filterable]` task ids as `ids[]` when the on-page text filter is active (reusing
  `window.collectVisibleFilterableTaskIds()`, already shared by the day-export component in
  `resources/js/app.js`), or no `ids[]` at all — meaning "export everything overdue" — when the
  filter is empty. `DashboardController::exportOverdueMarkdown()` applies `whereIn('id', $ids)` on
  top of its own already-authorized/scoped overdue query, same narrowing-only pattern as the Aug 16,
  2026 day-export entry below. No done/archived folding to account for — the Overdue page shows only
  incomplete tasks by definition. See `tests/Feature/OverdueExportMarkdownTest.php`.
- **Docs updated**: `docs/content/docs/features/dates.md` (new "Relative Dates in the Task Date
  Field" section) and `docs/content/docs/features/_index.md` (Search page's token matching now
  called out explicitly; new "Overdue Page" subsection documenting the export). Not verified against
  a running app in this doc-only follow-up session — see prior sessions' recurring notes on this
  sandbox's `npm`/`composer`/PHPUnit install constraints; the code and test changes described above
  were made in the session that produced `implementation-plan.md`'s commits, not this one.

### Session Summary (Aug 21, 2026) — Cowork UX/docs review fixes
- **Source**: a UX + docs pass from Claude Cowork (`taskfiend_ux_docs_review.md`, click-through of
  `localhost:8000` plus `taskfiend.online` docs) surfaced 8 findings. Went through each with the
  user rather than blind-applying all of them — two turned out to be false positives / needed a
  product decision first.
- **Fixed, verified against a real running instance** (`php artisan serve` + Playwright/Chromium,
  since neither `chromium-cli` nor a project `npm install` was available in this sandbox — used the
  globally-installed `playwright` package at `/opt/node22/lib/node_modules/playwright` with
  `executablePath: '/opt/pw-browsers/chromium-1194/chrome-linux/chrome'` instead):
  - **Docs Requirements section**: `docs/content/docs/getting-started/_index.md` now states PHP
    8.2+ and its required extensions (incl. `pdo_sqlite`/`sqlite3` for the default DB), Composer 2,
    Node 18+, and that SQLite/MySQL/Postgres are all supported — closes the "composer setup fails
    silently with no guidance" gap.
  - **"1 tasks" grammar** (`tags/index.blade.php`, both the active and archived tag card partials):
    now `Str::plural('task', $tag->tasks_count)`. Verified live: a tag with exactly one task now
    reads "1 task". (Note: `task-list.blade.php`/`subtask-list.blade.php` have a similar unpluralized
    "N subtasks" label — not reported, left alone to stay in scope.)
  - **Day view collapse hiding the add-task bar** (`dashboard/day.blade.php`, list view): the fold
    chevron used to gate `<x-task-input-bar>` and the task list behind the same
    `x-show="showIncomplete"`, so collapsing the list also hid the only way to quickly add a task.
    The input bar is now always rendered outside that gate; collapsing shows an explicit
    "Task list collapsed — click the arrow above to show it again." line instead of just a bare
    chevron with no cue. Verified via screenshot before/after toggling.
  - **Browser-based data import — removed from view, not deleted**: per user decision, all visible
    references to the disabled zip-based "Import Data" feature are gone
    (`profile/partials/export-import-data.blade.php` — section retitled "Export Data", the
    "Data import is temporarily unavailable" block removed entirely). The route/controller/action
    (`DataExportController::importAll`, still `abort(503, ...)`'d) is untouched so it's a one-line
    revert if the feature ever gets ID-remapping and comes back. Confirmed via repo-wide grep this
    was the only view referencing it.
  - **Todoist import docs clarified, not changed**: the reviewer's finding here was actually a false
    positive — `php artisan todoist:import` (documented at
    `docs/content/docs/getting-started/todoist-import.md`) is a real, working CLI command, entirely
    separate from the disabled web "Import Data" button they saw the unavailable-message on. Added a
    one-line callout at the top of that doc page clarifying it's a CLI-only import, unrelated to the
    web UI, so a self-hoster reading both doesn't assume the CLI command is broken too.
- **Investigated, not fixed — didn't reproduce**:
  - **"Large empty black band above content"** on Day/Projects/Create Task/Profile: measured actual
    `getBoundingClientRect()` gaps between the page header and first content via Playwright rather
    than eyeballing screenshots. Calendar (reported clean) = 57px, Projects = 48px, Create Task =
    76px, Profile = 81px, Day = ~90px — Day is the largest but nowhere near the "100-150px,
    broken-looking" the report described, and the other three flagged pages are in the same range as
    Calendar. Did not change any layout CSS on a guess.
  - **Hint text clipped under the Task Name textarea** on Create Task: typed a second line to trigger
    the multi-line hint (`"Each line becomes a separate task..."`) at both a 1280×900 desktop
    viewport and a 390×844 mobile viewport (fresh Chromium, no cache) — text rendered fully visible,
    not clipped or overlapping, in both.
  - **"Today" one day behind**: per user, this was the reviewer testing from a different timezone
    than the app/server — not a bug. User confirmed they're fine hard-coding the app to Pacific
    (`APP_TIMEZONE`), consistent with the spec's existing PST assumption; no code change requested
    or made.
  - **Seed/fixture data on fresh install**: per user, the repo doesn't ship or seed any such data —
    confirmed false positive, no action taken.
- Whoever next has a real user to point at the running app (or a way to match the reviewer's exact
  browser/viewport) should take another look at the black-band and hint-clipping items with that in
  mind — they may be viewport-, zoom-, or browser-specific (Cowork's review didn't say what it used)
  rather than nonexistent.

### Session Summary (Aug 18, 2026) — Tag archiving
- **Feature**: tags can now be archived. `tags` table gained a nullable `archived_at` timestamp
  (`Tag::$fillable`, matching the `email_enabled_at`-style "null = active" convention already used
  by `User`); `Tag::scopeActive()`/`Tag::scopeArchived()` partition on it.
- **Archive/unarchive UI**: a three-dot-menu action on the tag show page (`tags/show.blade.php`),
  plus an "Unarchive" link on a banner shown when viewing an archived tag. New routes/controller
  actions `TagController::archive()`/`unarchive()` (`POST /tags/{tag}/archive`,
  `/tags/{tag}/unarchive`), both change-logged like every other tag mutation.
- **Tag index** (`tags.index`) now returns two collections — `tags` (active) and `archivedTags` —
  and the view renders archived tags in a dimmed "Archived Tags" section below the active grid,
  mirroring how `projects.index` already lists inactive projects.
- **Every tag *picker*** (create/edit/show/panel task forms, search filters, the nav "browse by
  tag" list, the quick-add `@tag` autocomplete data) now queries `Tag::active()->...` instead of
  `Tag::orderByRaw(...)`. `QuickAddParser::parseTagTokens()` (the `@tag` inline-token matcher used
  by quick-add, bulk multi-line add, and the live preview) also scopes its lookup to
  `Tag::active()`, so typing `@` followed by an archived tag's name just leaves the literal text
  in the task name instead of attaching the tag — same behavior as an unrecognized tag name.
  `ChangeLogController`'s tag filter dropdown was deliberately left unfiltered — that's a history
  view, not a tagging action, and being able to look up an archived tag's changelog (e.g. to see
  when it was archived) is desirable, not a bug.
- **Task-facing display of tags is archived-aware, but the underlying task↔tag association is
  not touched by archiving** — added `Task::visibleTags()`, a plain method (not a relation) that
  filters the already-loaded `tags` collection to non-archived ones. Every place a task's tags
  render as chips (`task-list.blade.php`, `subtask-list.blade.php`, `tasks/show.blade.php`,
  `tasks/_panel.blade.php`, `review-task-row.blade.php`, plus the on-page text-filter's
  `data-tags` attribute) now iterates `$task->visibleTags()` instead of `$task->tags`.
  **Deliberately did NOT touch every `$task->tags` usage** — several places build hidden
  `tag_ids[]` form inputs from `$task->tags` specifically so a quick-complete/agenda-complete
  submit resubmits the task's *full* current tag set (see `task-list.blade.php`'s quick-complete
  form, `agenda.blade.php`, `review-task-row.blade.php`'s completable form,
  `subtask-list.blade.php`'s quick-complete form) — those were left unfiltered on purpose, since
  filtering them would make quick-completing a task silently detach any archived tag it had.
- **Data-loss guard**: since tag pickers only ever render active tags as checkboxes, a task that
  already carries an archived tag would have that tag stripped by a plain `sync()` the moment any
  edit-form save resubmits only the tags the box showed. Added
  `TaskController::syncTagsPreservingArchived()` — queries the task's currently-attached archived
  tag ids first, unions them into whatever the client submitted, then syncs — and wired it into
  every *existing-task* tag sync site (`update()`, `updateField()`'s `tag_ids` branch, and the
  `tag_ids`-alongside-`name` branch). Left plain `sync()`/`syncWithoutDetaching()` untouched at
  every *new-task-creation* site (`store()`, the API's `create()`, bulk quick-add, and
  `Task::duplicate()`'s pivot copy) since there's nothing pre-existing to lose there — and
  `Task::duplicate()` intentionally copies the full unfiltered tag set (archived tags included),
  consistent with it being a full copy rather than a form resubmission.
- **Tag's own show page unaffected**: an archived tag's page still lists all its tasks (existing
  incomplete/completed/archived task sections) exactly as before — nothing there was gated on the
  tag's own archived state, since visibility of *that page* was never in question, only whether the
  tag renders elsewhere.
- **Verified**: `php artisan migrate --env=testing`, `php artisan view:cache` (compiles every
  edited Blade template), and an Eloquent-backed smoke script against the test sqlite DB (same
  method prior sessions used — PHPUnit isn't installed in this sandbox, see below) confirming:
  `Tag::active()`/`Tag::archived()` correctly partition; `$task->tags` stays at its full count while
  `$task->visibleTags()` drops the archived one; `syncTagsPreservingArchived()` preserves an
  already-attached archived tag when only the active tag is resubmitted; unarchiving makes a tag
  reappear in `Tag::active()` immediately. **Caught a real bug this way**: `archived_at` wasn't
  originally in `Tag::$fillable`, so `$tag->update(['archived_at' => now()])` was silently
  discarding the field (Laravel's default mass-assignment behavior is to silently drop
  non-fillable attributes, not throw) — the archive button would have looked like it worked (redirect
  + flash message) while doing nothing. Added `archived_at` to `$fillable`; safe to do since it's
  never part of any request-validated array (`store()`/`quickStore()` only validate `tag_name`/
  `color`), so a user can't smuggle it in through the create form.
- **Added `tests/Feature/TagArchivingTest.php`** covering the archive/unarchive actions, the
  active/archived scopes, tag pickers excluding archived tags, `visibleTags()` vs the raw
  relation, the sync-preservation guard (including that it still lets an *active* tag be
  explicitly removed), and the quick-add `@tag` matcher skipping archived tags. Not run against a
  real PHPUnit invocation (not installed in this sandbox, same constraint as every prior session
  noted below) — written and `php -l`-checked, and its assertions match what the standalone smoke
  script above already exercised. Whoever next has a working `composer install` should run it once.
- **Follow-up (user testing feedback)**: `test_task_create_and_edit_forms_only_offer_active_tags`
  failed on a real PHPUnit run — `TaskController::create()` calls `Auth::user()->defaultProject()`,
  which throws if the user has no project flagged `is_default` (a real app invariant), and the
  test's `setUp()` created a project without that flag. Fixed in the test, not the app. Also moved
  the Archive/Unarchive action from the tag show page's three-dot menu into the Details modal (its
  own bordered section, separated from Delete by another border) per user request, so the two
  aren't adjacent and can't be misclicked into each other — confirmed the modal's task list still
  scopes to `visibleTo(Auth::id())` everywhere (see the `TagController` methods above), i.e. viewing
  an archived tag never surfaces another user's tasks.

### Session Summary (Aug 18, 2026) — Subtask reordering
- **Feature**: subtasks can now be manually reordered (drag handle + move-to-top/up/down/bottom
  arrow buttons), same interaction as the main task list. Lives on the task show page and the
  sidebar task panel — both render `resources/views/tasks/show.blade.php`, which is the only
  place `<x-subtask-list>` is used, so one component change covers both.
- **No backend changes needed**: `Task::children()` already orders by `sort_order` (see the
  Jul 11, 2026 dedup entry's era of schema), and `TaskController::reorder()`
  (`POST /tasks/reorder`, existing route) already accepts an arbitrary `ids[]` array, checks
  `Task::visibleTo()` per id, and stamps `sort_order` by position in that array — it isn't scoped
  to top-level tasks, so submitting a parent's subtask ids reorders just that sibling group without
  touching anything else. This was purely a frontend wiring task: reuse the existing drag/arrow
  machinery (`window.initTaskSortable`/`saveTaskOrder`/`taskMoveInList`/`updateSortButtonStates` in
  `layouts/app.blade.php`) against `resources/views/components/subtask-list.blade.php`.
- **Only the top level of subtasks under the task you're viewing is sortable, not every nested
  depth** — deliberate, not a shortcut. `subtask-list.blade.php` is recursive (nested children
  render via a nested `<x-subtask-list>`); wrapping every recursion depth in its own
  `x-data="taskSortableList"` container would register a separate `pointerdown` listener on each
  container, and since deeper containers sit inside shallower ones' DOM subtrees, one drag-handle
  pointerdown would bubble into *multiple* listeners at once (each maintaining its own drag-ghost
  state), corrupting the drag. `task-list.blade.php` already sidesteps this exact problem by only
  enabling its sortable container at `$depth === 0`; `subtask-list.blade.php` now takes a matching
  `depth` prop and does the same (`$canSort = $sortable && $depth === 0 && ...`). Consistent with
  the user's own framing of the request ("not ideal... but better than the inability to do so at
  all") — reordering grandchild-level subtasks still isn't possible, only direct children of
  whatever task page/panel you're on.
- Sort controls are gated behind the same `$isInactive` check that already hides "+ Add Subtask"
  (done/archived tasks, or tasks in a done/archived project, are read-only) — passed down as a new
  `sortable` prop on `<x-subtask-list>`. Also hidden when a level has only one subtask (nothing to
  reorder).
- `Alpine.data('taskSortableList', ...)` moved from being registered only inside
  `task-list.blade.php`'s local `@pushOnce('scripts')` to also being registered globally in
  `resources/js/app.js`, since `subtask-list.blade.php` needed it available without depending on
  `task-list.blade.php` being on the same page. The two registrations are identical, so this is a
  harmless redundant overwrite wherever both happen to load, not a behavior change.
- **Not verified against a running app**: `npm run build` fails in this sandbox (`vite: not
  found` — `node_modules` isn't fully installed, same npm-registry blocker prior sessions hit).
  Checked instead via `node --check resources/js/app.js` (no syntax errors), a manual
  `@if`/`@endif`/`@foreach`/`@endforeach`/`<div>` balance check on the edited Blade file, and
  `php artisan view:cache` (compiles every Blade template in the app, including this one, without
  error). Whoever next has a working `npm install` should click through: reorder subtasks via drag
  and via the arrow buttons on both the full task page and the sidebar panel, confirm the order
  persists on reload, and confirm a done/archived task's subtasks show no sort controls.

### Session Summary (Aug 18, 2026) — Task sidebar: "Select All" popup drifting on mobile
- **Bug report**: on the mobile task sidebar (the slide-in panel opened by tapping a task —
  `#task-panel-overlay` in `layouts/app.blade.php`, content fetched from `tasks/_panel.blade.php`),
  selecting text and then tapping the native "Select All" callout caused the callout to visibly
  slide/drift away instead of snapping to the newly-expanded selection.
- **Root cause theory**: the panel's DOM had a `position: sticky` header living *inside* the
  scrollable drawer (`overflow-y-auto`), which itself sits inside a `position: fixed` full-screen
  overlay. Sticky-inside-scroll-inside-fixed is a known trigger for mobile Safari/WebKit bugs where
  the browser's auto-scroll-into-view (triggered by "Select All" expanding the selection) fights
  with reconciling the sticky element's position, and the native selection callout visibly lags/
  drifts while that gets sorted out. Not fully verified against a real device/browser in this
  sandbox (no working `npm install` / no mobile device available here — see prior sessions' notes on
  this recurring sandbox limitation) — this was the most concrete, plausible lead from reading the
  DOM structure, not a confirmed root cause.
- **Fix**: removed `position: sticky` from the panel header entirely. The drawer (`app.blade.php`)
  is now a flex column with three parts: a `#task-panel-header` slot (`flex-shrink-0`, pinned by flex
  layout, not sticky), and a `flex-1 min-h-0 overflow-y-auto` scrollable body holding the loading/
  error states and `#task-panel-content`. Since the header and body are fetched together as one
  partial (`tasks/_panel.blade.php`) but now need to land in two different DOM slots, the header is
  marked with `[data-panel-header]` in the partial and `_loadContent()` moves that node into
  `#task-panel-header` via `replaceChildren()` after injecting the fetched HTML — a plain DOM move,
  not a second fetch or a second server response. `Alpine.initTree()`/`destroyTree()` calls were
  extended to also cover `#task-panel-header` (both on load and on `_closeWithoutHistory()`'s cleanup)
  since it now holds its own Alpine-bound content (the close button, copy-link button) that no longer
  lives inside `#task-panel-content`'s subtree.
- **Verified**: `php -l` and `php artisan view:cache` (compiles every Blade template including this
  one) both pass clean. Not click-tested against a running mobile browser — same sandbox constraint
  as above. Whoever next has a phone/working dev server handy should confirm: selecting text in the
  panel body and tapping "Select All" no longer drifts, the header's close/copy-link/duplicate/
  "open full page" buttons still work after a fetch, and closing+reopening the panel for a different
  task doesn't leak old header content or duplicate Alpine bindings.

### Session Summary (Aug 18, 2026) — Configurable PDF export column count
- **Feature**: the day-view PDF export's column count (previously hardcoded to 2, see the Aug 6/16
  entries below) is now configurable via `DAY_EXPORT_COLUMNS` in `.env` — `config/taskfiend.php`'s
  `day_export_columns` key, clamped to 1-4 there (`max(1, min(4, ...))`) so a bad/extreme `.env`
  value can't produce a negative-width or unreadably-narrow layout. `DashboardController::exportDayPdf()`
  reads the config value and passes it into `DayPdfExporter::build()` as an explicit new `$columns`
  parameter (default 2) — kept explicit rather than having the service reach into `config()` itself,
  matching how `sort`/`reversed`/`filter` are already passed in rather than pulled from globals.
  `DayPdfExporter::build()` also clamps its own `$columns` parameter independently, so a direct/test
  caller can't produce a broken layout either.
- **What generalized from a fixed 2 to N**: column width (`(PAGE_W - 2*MARGIN - (N-1)*GAP) / N`),
  an array of N column x-positions (was two named variables), a loop drawing N-1 vertical dividers
  per page (was one hardcoded divider line — factored into `drawColumnDividers()` since it's now
  called from two places: initial page and each new page), and the page-break check (was `if ($col
  > 1)`, now `if ($col >= $columns)`). Nothing outside `DayPdfExporter.php` needed to change —
  `SimplePdfWriter`, the route, the controller's request handling, and the Export PDF button's JS
  are all column-count-agnostic.
- **Investigated before implementing** (separate chat turn, no code changes made then): confirmed
  via careful reading that nothing else in the codebase assumes 2 columns, sized the change as
  small/contained, and flagged the clamp-range decision (1-4) for the user rather than picking
  unilaterally — they confirmed 4 as the ceiling.
- **Verified**: `DayPdfExporter::build()` at columns=1/2/3/4 all produce structurally valid PDFs
  (from-scratch xref/object-offset check, same method as prior sessions) with correct page counts —
  1 column with 50 short tasks needs 2 pages, 2-4 columns fit the same 50 tasks on 1; passing
  out-of-range raw values (0, -1, 5, 10) directly to `build()` doesn't break layout, confirming the
  independent clamp there; `config/taskfiend.php`'s clamp verified directly against `DAY_EXPORT_COLUMNS`
  values 0, -3, 2, 4, 7, and a non-numeric string, all landing in range. Also rendered a 3-column PDF
  with realistic (non-filler) task text and visually confirmed correct divider placement and the
  expected extra word-wrapping from narrower columns (a tradeoff already flagged to the user).
- **Also fixed while pulling in `main`**: a merge conflict resolution on `main` (from before this
  session) had kept `tests/Unit/TaskTextFilterTest.php` while dropping the `App\Services\TaskTextFilter`
  class it tests (deleted in the Aug 16 "filter via task IDs" work below) — a dangling `use` of a
  nonexistent class that would fatal on any test run touching that file. Deleted the stale test.

### Session Summary (Aug 12, 2026) — Bulk-update status bug (missing completed_at, broken recurrence)
- **Bug**: `TaskController::bulkUpdate()` (`POST /tasks/bulk-update`, the multi-select "Archive"/"Mark
  done" action) set `status` via a raw `$task->update($changes)` mass assignment instead of going
  through `TaskLifecycle::changeStatus()` like every other status-changing code path (single-task
  edit form, inline field editor, quick-complete). Consequence: two things `changeStatus()` normally
  guarantees were silently skipped — stamping `completed_at`, and (for recurring tasks) creating the
  next occurrence. Root cause of user-reported reports of `completed_at IS NULL` rows on done/archived
  tasks, and of specific recurring tasks whose series stopped rolling forward after being bulk-archived
  or bulk-completed via multi-select.
- **Fix**: `bulkUpdate()` now applies non-status field changes via `update()` as before, then calls
  `TaskLifecycle::changeStatus()` per task for the status change — same `completed_at`/cascade/
  recurrence-rollover side effects as the single-task paths. `TaskLifecycle::createNextOccurrence()`
  changed from `private` to `public` so it can also be called directly for backfill purposes.
- **Backfill**: `php artisan tasks:backfill-completed-at [--dry-run]`
  (`app/Console/Commands/BackfillMissingCompletedAt.php`) finds existing done/archived tasks with a
  null `completed_at`, stamps it from `updated_at` (best available signal for when the buggy bulk
  update actually ran), and for any of those that are still recurring, calls
  `createNextOccurrence()` to catch the series up to its next occurrence. Since it runs outside a
  web request there's no `Auth::id()` for change-log attribution — it logs in as the task's creator
  for the duration of that call (`Auth::login($task->creator)` / `Auth::logout()`), the same
  attribution convention `BackfillTaskLogs` uses. Verified against a from-scratch reproduction of the
  bug (task force-completed via raw `update()`, bypassing `TaskLifecycle`) since PHPUnit isn't
  installed in this sandbox (see prior sessions' notes on this) — confirmed `completed_at` gets
  backfilled and the missing next occurrence gets created with correct change-log attribution.
- **Tests**: added `test_bulk_marking_done_stamps_completed_at`,
  `test_bulk_archiving_stamps_completed_at`, and `test_bulk_marking_done_creates_next_recurring_occurrence`
  to `tests/Feature/BulkUpdateTest.php`. Also manually verified the descendant-cascade behavior now
  applies correctly to bulk status changes (a parent task with an incomplete subtask, marked done via
  bulk update, now completes the subtask too — previously bulk update never touched descendants at
  all, another side effect of bypassing `TaskLifecycle`).

### Session Summary (Aug 6, 2026) — Daily PDF export
- **Feature**: "Export PDF" button on the day view (today only, `dashboard/day.blade.php` header, next to
  "Export .md"). Downloads a printable, foldable checklist of the day's incomplete tasks —
  `taskfiend-day-YYYY-MM-DD.pdf` — designed to reduce phone-checking: an editorial-style two-column
  list (eyebrow "TODAY" label, bold date, rule, time gutter, divider line under each row instead of
  a bullet) on a US Letter page sized to fold down to pocket size, meant to be marked up with a
  highlighter and thrown away at end of day (no checkboxes, no completion state in the PDF itself).
  Originally scoped to *today only* — the recurring-tasks-not-yet-created concern below made that
  the safe default at the time. **Superseded Aug 16, 2026**: the today-only restriction was lifted
  (see that entry) — the recurring-tasks concern itself is unaddressed, just knowingly accepted.
- **Mirrors the on-page view exactly**: same sort/reversed as the day view (already in the URL) plus
  the on-page text filter box. That filter is client-side-only (Alpine, never touches the URL — see
  `task-list.blade.php`'s `filterTasks()`). Originally mirrored server-side via a `filter` query
  param re-parsed by a PHP port of the JS tokenizer (`App\Services\TaskTextFilter`); **replaced
  Aug 16, 2026** with an ID-based approach — see that entry. Always restricts to incomplete tasks
  regardless of any on-screen status filter (an intentional fixed property of this export, not a
  bug — see `DashboardController::exportDayPdf()`).
- **`App\Services\SimplePdfWriter`** — a small dependency-free PDF byte-writer (no mpdf/dompdf/
  browsershot). This sandbox's `composer require` is blocked by egress policy (packagist.org is not
  on the allowed-host list), so no new Composer package could be installed or verified here; a
  hand-rolled writer avoids that dependency entirely rather than adding an unverified one. It
  supports exactly what this export needs — multiple pages, absolutely-positioned text lines in the
  standard Helvetica/Helvetica-Bold fonts (no embedding) — and was validated with a from-scratch
  xref/object-offset consistency check plus content assertions (see below), since PHPUnit itself
  isn't installed in this sandbox either (dev Composer deps aren't present, same root cause).
  Text is transcoded to WinAnsiEncoding; characters outside that range (emoji, CJK, etc.) in task
  names are dropped, not embedded — an accepted limitation of using only the standard 14 fonts.
- **`App\Services\DayPdfExporter`** — lays out the two-column list itself (fills column 1
  top-to-bottom before spilling into column 2, matching how you'd read and highlight it after
  folding — deliberately *not* CSS-style balanced columns) using real Helvetica glyph-width metrics
  for word-wrapping, rather than relying on a renderer's CSS multi-column support.
- **Verification**: `tests/Unit/TaskTextFilterTest.php` covers the filter port (name/project/tag/
  location/user tokens, `not:`, quoted phrases, combined tokens). No PHPUnit run could be confirmed
  in this sandbox (see above) — logic was instead exercised via a real Eloquent-backed script
  against an in-memory sqlite DB (services, controller action, and generated PDF's xref table were
  all validated directly). Whoever next has a working `composer install` should run the suite once
  to confirm. **This file was deleted Aug 16, 2026** along with `TaskTextFilter` itself — see that
  entry below.
- **Follow-up fix**: the first version of the Export PDF button wrote its click handler as an inline
  multi-statement block (`const p = ...; if (...) {...}`) directly in `@click`. That silently failed
  in the browser — Alpine's CSP-safe build (see "Key Patterns Used" above) can't parse JS statements
  in a directive. Fixed by moving the logic into a `dayPdfExport` Alpine component method, called via
  `@click="go()"`.
- **Follow-up redesign**: reworked the PDF's visual layout to match a mockup the user had a design
  tool produce — eyebrow "TODAY" label, big bold date, a dark header rule, a time gutter per row
  (instead of the time being folded into the task text), a light divider line under every row
  instead of a bullet, and a light vertical divider between the columns running the full column
  height regardless of how much content is in either column. Required adding real drawing
  primitives to `SimplePdfWriter` (`line()` for strokes, plus gray-fill and letter-spacing support on
  `text()`) — every call now states its own color/spacing explicitly rather than relying on
  whatever the previous call left in the PDF graphics state, since state persists across `BT`/`ET`
  text blocks and isn't auto-reset. Confirmed the column-fill behavior is deliberately *not* what
  the reference mockup did — the mockup balanced ~10/10 across two columns via what looks like
  browser-default CSS column balancing, whereas Task Fiend's is supposed to fill column 1
  completely before spilling into column 2 (see the earlier "not CSS-style balanced columns" note)
  — confirmed visually with a 20-item sample (matches the mockup's content exactly, single column,
  second column empty) and a 50-item sample (splits 35/15, i.e. column 1 filled to its real capacity
  before column 2 got anything).

### Session Summary (Aug 16, 2026) — PDF export: any day, not just today
- The Export PDF button (see the Aug 6, 2026 entry above) no longer restricts to today. It now
  accepts the same `date` query param the day view itself uses, so it works for any date the day
  view supports — today and future dates. Past dates render through the separate `dayReview()`
  view/template, which never had this button, so they're still out of scope; nothing needed to
  change there. `DashboardController::exportDayPdf()` parses `date` (defaulting to today, same as
  `day()`) instead of hardcoding `today()`.
- The button itself is no longer gated by `$carbonDate->isToday()` in `dashboard/day.blade.php` —
  it always renders now. Its click handler used to strip the `date` param before navigating (a
  leftover from the today-only restriction); now it's left in place so exporting from a future
  day's page exports that day, not today.
- `DayPdfExporter`'s "TODAY" eyebrow is now conditional on `$date->isToday()`, matching how the day
  view's own header only prefixes "Today - " for the current date (`day.blade.php`) — other dates
  get the plain weekday/date title with no label above it.
- **Known limitation, explicitly not addressed here** (raised by the user, deferred on purpose): a
  future day's recurring tasks that haven't been generated yet (the prior occurrence hasn't been
  completed, so `TaskLifecycle::createNextOccurrence()` hasn't run) won't appear in that day's
  export — it only exports task rows that already exist. Projecting anticipated-but-not-yet-created
  recurring occurrences onto a future date would be a distinct, materially larger feature: it needs
  read-only "what would `getNextOccurrence()` chain forward to by date X" logic that never touches
  the database, kept carefully separate from the real completion-triggered rollover in
  `TaskLifecycle`, since the two must never be conflated (a projected/virtual task must never be
  mistaken for or promoted into a real one by anything else in the app). See `RECURRING_TASKS.md`
  and `DateParser::getNextOccurrence()` as the starting points if this gets picked up.
- Verified via the existing Eloquent-backed smoke script (same constraints as Aug 6 — no PHPUnit or
  fresh Composer packages available in this sandbox): a task dated 8 days out exports correctly
  under `?date=`, today's own tasks are correctly excluded from that export, the "TODAY" eyebrow is
  correctly absent for the future date and correctly present for today's own export.

### Session Summary (Aug 16, 2026) — PDF export: filter via task IDs, not re-parsed text
- **What changed**: the user reported the Export PDF button appearing disabled whenever the on-page
  filter box had anything in it. Live browser reproduction wasn't possible in this sandbox either
  (npm's registry.npmjs.org is *also* blocked by the egress policy here, same as packagist.org — no
  `playwright-core` install, no `php artisan serve` + real click-through to confirm root cause).
  Careful code review of `filterTasks()` / the `taskCount` store / the `:disabled` binding didn't
  turn up a clear defect either. Rather than keep guessing at a fix for a not-fully-confirmed bug, replaced the
  whole mechanism with what the user proposed: the client now sends the exact set of currently-visible
  task IDs (`ids[]`) instead of raw filter text for the server to re-interpret.
- **Why this is a strict improvement, independent of whatever the original bug was**: it deletes an
  entire class of risk — a hand-ported PHP reimplementation of the JS filter tokenizer
  (`App\Services\TaskTextFilter`) permanently at risk of drifting from the real one in
  `task-list.blade.php`. The client already knows precisely which rows are visible (it just set
  their `style.display`); there's no reason to make the server re-derive that from scratch via a
  second parser. **`App\Services\TaskTextFilter` and `tests/Unit/TaskTextFilterTest.php` were
  deleted.**
- **How it works now**: `dayPdfExport`'s `go()` (`dashboard/day.blade.php`) reads
  `document.querySelector('[x-ref="taskContainer"]')` directly (works across the `x-data` boundary
  since `x-ref` stays as a plain, query-able attribute in the rendered DOM), collects
  `[data-filterable]` descendants whose `style.display !== 'none'`, resolves each to its task ID via
  `.closest('[data-task-group]').dataset.taskGroupId`, and sends them as `ids[]` — only when a
  filter is actually active (`Alpine.store('taskCount').filterText` is non-empty); an inactive
  filter sends no `ids[]` at all and exports everything for the date, same as before.
  `DashboardController::exportDayPdf()` applies `whereIn('id', $ids)` **on top of** the same
  already-authorized/scoped `incompleteTasksForDate()` query — an ID can only ever narrow the
  result (wrong date, wrong status, another user's task, etc. all still exclude it), never widen it.
  `filter` (the raw query text) is still accepted and still lands in the PDF's header meta line —
  it's now purely a display string, never re-interpreted server-side.
- **Verified** via the Eloquent-backed smoke script: exporting with `ids=[]` unset still exports
  everything; `ids=[<one task>]` exports only that task while the `filter` text still shows in the
  meta line; an `ids[]` list containing another user's task and a task dated for a different day
  correctly excludes both (security check — an ID can't be used to see something the base query
  wouldn't already allow); an `ids[]` matching nothing still redirects back with the "No tasks to
  export" flash rather than erroring.

### Session Summary (Jul 11, 2026) — Deduplication refactor
- **`Task::visibleTo($userId)` scope** (`app/Models/Task.php`) — the canonical creator-or-assignee
  visibility rule. Replaced ~30 hand-copied query closures across 9 controllers. Always use this
  scope for task list queries; never hand-roll the creator/assignee check.
- **`Project::forMember($userId)` scope** (`app/Models/Project.php`) — owner or project-level
  assignee; the access rule for acting on a project (creating/moving tasks into it). Stricter than
  `activeForUser`, which also grants visibility via assigned tasks.
- **`TaskLifecycle` service** (`app/Services/TaskLifecycle.php`) — the task status state machine.
  `changeStatus()` handles descendant cascades (complete/archive), completed_at bookkeeping, change
  logging, recurring-task rollover, and archiving the next occurrence on re-open. Both update() and
  updateField() delegate to it (previously two drifted copies of this logic). Route any new
  status-changing code through this service.
- **`QuickAddParser` service** (`app/Services/QuickAddParser.php`) — single source of truth for
  inline token parsing (`#project`, `@tag`, `+location`/`++location`, `&user`). Returns a
  `QuickAddTokens` DTO. Used by single-task store, bulk (multi-line) store, and the quick-add live
  preview, so all three interpret input identically (previously three drifted copies).
- **Bug fixes from consolidating the drifted copies**:
  - `parseDate()` preview endpoint queried a non-existent `user_id` column (errored at runtime)
  - Location token fuzzy-match now scoped to the user's visible tasks (was matching all users')
  - Bulk lines now match projects/tags exactly like single-line input (hyphen normalization, active
    projects only, unmatched tokens stay in the title)
  - Preview now strips matched `&user` tokens the same way store does (typed token, not re-derived slug)
- **Tests**: `tests/Unit/VisibilityScopeTest.php`, `tests/Unit/QuickAddParserTest.php`,
  `tests/Feature/ParseDatePreviewTest.php`

### Session Summary (Jul 31, 2026) — Task tree duplication consolidation
- **`Task::duplicate()`** (`app/Models/Task.php`) is now the single implementation behind all three
  "copy a task tree" call sites: the manual "Duplicate" button, project duplication, and recurring-task
  rollover. Supports recursive child copying (`withChildren`, `childFilter`, `childOverrides`) and an
  ownership mode (`preserveOwnership`) — off for user-initiated duplication (copy is attributed to
  whoever clicked Duplicate), on for automatic system copies like recurring rollover (copy keeps the
  original creator/assignments so completing someone else's recurring task doesn't reassign it).
- **Bug fix**: every duplicated attachment now gets its own physical file copy. Previously, recurring
  rollover created new attachment rows pointing at the *same* file_path as the original — deleting an
  attachment from one occurrence deleted the underlying file out from under every other occurrence
  sharing that path. `TaskAttachmentController::destroy()` has no reference counting, so this was a
  real data-loss bug, not just theoretical.
- **`Project::duplicateChildren()` and `TaskLifecycle::copySubtasksToNewTask()` removed** — both now go
  through `Task::duplicate(withChildren: true, ...)`.
- **Tests**: `tests/Feature/TaskDuplicationTest.php` — characterizes the ownership/status-filter
  semantics that differ by call site (manual duplicate always reassigns to the current user and skips
  subtasks entirely; project duplicate copies only incomplete tasks at every depth; recurring rollover
  copies all subtasks regardless of status and preserves original ownership) and verifies the
  attachment-sharing fix.
- **Known pre-existing failure, unrelated to this work**: `tests/Feature/ApiTaskTest.php` has one
  failure (`ProjectReminder::format()` called with a null format string) introduced by an unrelated
  change already on `main` before this session started.

### Session Summary (Mar 27, 2026)
- **Drag-and-drop task ordering** implemented (was last remaining Alpine.js feature from spec)
- **Multi-select mode** added to task list views
  - Activated via clipboard icon next to the quick-add bar
  - Allows bulk operations on selected tasks

### Session Summary (Feb 19, 2026)
- **Quick-add bar** added to task list views
  - Parses natural language dates, `#project`, and `@tag` directly from the input
  - Live autocomplete suggestions for projects and tags
  - Replaces/toggles with a filter input on the same bar
- **Agenda view** for the day page
  - Toggle between list and time-block (agenda) layouts; preference persisted in localStorage
  - Quick-complete buttons in agenda view submit via AJAX (no page reload); task block fades out on completion
- **Inline editing** on project show page
- **Markdown rendering** in task descriptions
- **Duration input** on task show view; 24-hour agenda toggle
- **Attachment icon** indicator on task list rows
- **Task count** displayed alongside date previews on create/edit forms and in the quick-add bar
- **Password visibility toggle** on login form
- **Prevent self-unassign**: task creators cannot remove themselves via the assignee checkboxes
- **Bug fixes**:
  - Quick-add date parsing no longer shadowed by day-view pre-filled date
  - Task count display on show page corrected
  - Overlapping task blocks in agenda view fixed
  - Tag count now excludes archived/done tasks
  - Null project handling in API task responses

### Recent Session Summary (Jan 6, 2026)
- **Fixed critical recurring task bugs**:
  - Quick complete button (circle/dot) now preserves all task fields including recurrence_pattern
  - Added support for "every other [day]" patterns (e.g., "every other Wednesday")
  - Recurring tasks now properly create next instance when marked done via quick complete
- **Enhanced recurring task UX**:
  - Purple border on quick complete button for recurring tasks
  - Tooltip shows what will happen when completing
  - All task data (tags, assignees, attachments) preserved in quick complete
- **DateParser enhancements**:
  - Added pattern matching for "every other Monday/Tuesday/etc."
  - Added getNextOccurrence support for bi-weekly day patterns
  - Location: DateParser.php lines 22, 46-50, 270-273

### Session Summary (Dec 30, 2025)
- Completed all remaining views (tags, search, changelogs)
- Tested application end-to-end
- Fixed DateParser bug with "every [day]" pattern
- Created test user and API key
- Verified all routes, migrations, and core functionality
- **Implemented complete dark theme** across entire application
  - Converted all 30+ view files and components to dark color scheme
  - True black background (`bg-black`) with dark gray containers (`bg-gray-800`)
  - Optimized text contrast for readability
  - Updated all forms, inputs, dropdowns, modals, and interactive elements
- **Implemented comprehensive E2E testing with Playwright**
  - 30 authorization/privacy tests (task, project, tag access control)
  - Configured to use system Firefox (no browser download)
  - Separate test database with proper environment isolation
  - Fixed duplicate sessions migration issue
  - Added dedicated 'testing' database connection in config
- **Status**: Application fully functional with modern dark UI and comprehensive test coverage

### Task Assignment Rules (from spec)
- New tasks auto-assigned to creator unless specified
- Task creator can add/remove any assignees
- Assignee can remove themselves but not others
- Only creator and assignees can see tasks

### Date Format
- Display: "Weekday, Month number day, four digit year" (e.g., "Monday, November 10, 2025")
- API/Storage: YYYY-MM-DD
- Timezone: Pacific Standard Time


## Quick Start Commands
```bash
# Create first user
php artisan user:create admin@example.com "Admin User" password123

# Generate API key
php artisan apikey:create admin@example.com

# Run migrations (if needed)
php artisan migrate

# Start dev server
php artisan serve
```

## File Locations
- Spec: `spec.md`
- Models: `app/Models/`
- Controllers: `app/Http/Controllers/`
- Commands: `app/Console/Commands/`
- Migrations: `database/migrations/`
- Views: `resources/views/`
- Routes: `routes/web.php`, `routes/api.php`

## Linguistic Norms
Headlines should be in title case: almost every word should be capitalized. Exceptions (according to this[](https://sellertoolkit.org/is-is-capitalized-in-a-title/what-words-are-not-capitalized-in-a-title)) include articles (a, an, the), coordinating conjunctions (and, but, or, nor, for, so, yet), and short prepositions. It is insufficient to capitalize just the first word. 
