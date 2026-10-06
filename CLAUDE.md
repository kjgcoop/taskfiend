# Task Fiend

Laravel + SQLite + Alpine.js task management app. `spec.md` (gitignored, local only) has the full requirements.

Area-specific notes live in `.claude/rules/` and load automatically when you touch matching files:

| File | Covers |
|---|---|
| `alpine-csp.md` | Alpine.js CSP-safe parser pitfalls, shared frontend helpers, sortable lists, task panel |
| `date-parser.md` | `DateParser` tokens, recurrence patterns, relative dates |
| `recurring-tasks.md` | Recurring-task rollover behavior and `TaskLifecycle` |
| `project-templates.md` | Templates, template zips, scheduled projects |
| `tags-and-search.md` | Tag archiving, search, `QuickAddParser`, project pickers |
| `exports.md` | Day PDF/PNG/Markdown exports |
| `other-links.md` | Instance-owned Markdown pages |
| `testing.md` | PHPUnit, Playwright, test database and sandbox limits |

## Canonical Helpers (Don't Hand-Roll These)

These replaced drifted copy-pasted logic. Use them for any new code:

- **`Task::visibleTo($userId)`**: the creator-or-assignee visibility rule. Always use it for task list queries.
- **`Project::forMember($userId)`**: owner or project-level assignee; the rule for *acting on* a project (creating or moving tasks into it). Stricter than `Project::activeForUser()`, which also grants visibility via assigned tasks. Reject `done`/`archived` target projects with a 422/flash error.
- **`TaskLifecycle::changeStatus()`** (`app/Services/TaskLifecycle.php`): the task status state machine (descendant cascades, `completed_at`, change logging, recurring rollover). Route *every* status change through it, including bulk actions. Bypassing it once left `completed_at` null and broke recurrence.
- **`QuickAddParser`** (`app/Services/QuickAddParser.php`): single source of truth for inline tokens (`#project`, `@tag`, `+location`/`++location`, `&user`). Used by single store, bulk store, the live preview and search.
- **`Task::duplicate()`**: the single implementation behind the Duplicate button, project duplication and recurring rollover. Every duplicated attachment gets its own physical file copy (`TaskAttachmentController::destroy()` has no reference counting).
- **`ProjectTemplateArchive`**: the only code that reads or writes template zips.

## Key Patterns

- **Authorization**: tasks/projects are private by default, visible to creator + assignees only.
- **Change logging**: all CRUD operations log to `change_logs`.
- **No deletion**: tasks/projects are archived, never deleted (per spec).
- **File storage**: `private` disk for task and comment attachments.
- **Mass assignment**: Laravel silently drops non-`$fillable` attributes. A new column that "does nothing" on `update()` is usually missing from `$fillable`.
- **Null = active/enabled** convention for timestamps (`User::email_enabled_at`, `Tag::archived_at`).
- **Task assignment**: new tasks auto-assign to the creator unless specified; the creator can add/remove any assignee; an assignee can remove only themselves; only creator and assignees see a task.
- **Subtasks always inherit their parent's project**, through every input path (including inline `#project` tokens). A parent may be in a different project than its child, so "top-level in this project" means "no parent, or parent belongs elsewhere" (`ProjectController::topLevelForProjectScope()`).
- **Alpine.js runs in CSP-safe mode**: directive expressions must be a single expression, not statements. See `.claude/rules/alpine-csp.md`.

## Dates

- Display: "Weekday, Month number day, four digit year" (e.g., "Monday, November 10, 2025")
- API/storage: `YYYY-MM-DD`
- Timezone: Pacific (`APP_TIMEZONE`). A "Today is a day behind" report is usually a reviewer in another timezone, not a bug.
- `ProjectReminder::date` and similar model accessors return a *formatted string*, not Carbon. Use `getRawOriginal('date')` when you need a real date.

## Database: Always Specify the Environment

- **Production DB**: `database/database.sqlite` (from `.env`)
- **Test DB**: `database/test-database.sqlite` (from `.env.testing`)

```bash
php artisan migrate:fresh --force                    # PRODUCTION (.env)
php artisan migrate:fresh --force --env=testing      # TEST (.env.testing); never omit the flag
php artisan user:create test@example.com "Test User" password123 --env=testing
```

Connections in `config/database.php`: `sqlite` (default, `DB_DATABASE` from `.env`) and `testing` (always `database/test-database.sqlite`). `.env.testing` uses array session/cache drivers and a sync queue.

## Quick Start

```bash
php artisan user:create admin@example.com "Admin User" password123
php artisan apikey:create admin@example.com     # returns tfk_xxxxx
php artisan migrate
php artisan serve                               # http://localhost:8000
```

CLI commands live in `app/Console/Commands/`: `user:create`, `user:toggle`, `apikey:create`, `apikey:invalidate`, `email:task-digest`, `email:task-png`, `temp:prune`, `tasks:backfill-completed-at`, `todoist:import`, and the scheduled `CreateScheduledProjects`.

## File Locations

- Models: `app/Models/` · Controllers: `app/Http/Controllers/` (API in `Api/`) · Services: `app/Services/`
- Migrations: `database/migrations/` · Views: `resources/views/` · Routes: `routes/web.php`, `routes/api.php`
- User/developer docs: `docs/content/docs/` (Hugo, public-facing). Don't log per-session changes there.

## Working Style

- Test commands: `php artisan test` (PHPUnit), `npm run test:e2e` (Playwright), `npm test` (both). Add `--env=testing` to any artisan command that touches the DB.
- Verify before claiming done: run `php artisan view:cache` (compiles every Blade template) and the relevant PHPUnit tests. See `.claude/rules/testing.md` for what the cloud sandbox can and can't run.
- Runtime Alpine/browser errors are console-only and won't be caught by Blade compilation. Say so when you couldn't click-test.

## Linguistic Norms

Headlines should be in title case: almost every word should be capitalized. Exceptions (according to [this](https://sellertoolkit.org/is-is-capitalized-in-a-title/what-words-are-not-capitalized-in-a-title)) include articles (a, an, the), coordinating conjunctions (and, but, or, nor, for, so, yet), and short prepositions. It is insufficient to capitalize just the first word.
