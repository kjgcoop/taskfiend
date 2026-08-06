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
  - Creates 3 test users: user1@test.com, user2@test.com, user3@test.com (all use password: password123)
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

### Session Summary (Aug 6, 2026) — Daily PDF export
- **Feature**: "Export PDF" button on the day view (today only, `dashboard/day.blade.php` header, next to
  "Export .md"). Downloads a printable, foldable checklist of the day's incomplete tasks —
  `taskfiend-day-YYYY-MM-DD.pdf` — designed to reduce phone-checking: an editorial-style two-column
  list (eyebrow "TODAY" label, bold date, rule, time gutter, divider line under each row instead of
  a bullet) on a US Letter page sized to fold down to pocket size, meant to be marked up with a
  highlighter and thrown away at end of day (no checkboxes, no completion state in the PDF itself).
  Scoped to *today only* — supporting arbitrary days runs into recurring tasks anticipated for that
  date that don't exist as rows yet, which was explicitly deferred.
- **Mirrors the on-page view exactly**: same sort/reversed as the day view (already in the URL) plus
  the on-page text filter box. That filter is client-side-only (Alpine, never touches the URL — see
  `task-list.blade.php`'s `filterTasks()`), so it's surfaced via `Alpine.store('taskCount').filterText`
  and passed to the export as a `filter` query param. **`App\Services\TaskTextFilter`** is a
  server-side line-for-line port of that same JS tokenizer/matcher (`#project`, `@tag`, `+location`,
  `&user`, `not:` prefix, quoted phrases) — keep the two in sync if the filter syntax changes.
  Always restricts to incomplete tasks regardless of any on-screen status filter (an intentional
  fixed property of this export, not a bug — see `DashboardController::exportDayPdf()`).
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
  to confirm.
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
