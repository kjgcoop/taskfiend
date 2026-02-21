# For Developers

## Local Setup

```bash
cp .env.example .env
composer setup        # installs deps, generates key, runs migrations, builds assets
php artisan user:create admin@example.com "Admin User" password123
php artisan serve
```

## Docker Setup

```bash
cp .env.example .env
# Edit .env: set APP_KEY, APP_ENV=production, APP_DEBUG=false
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan user:create admin@example.com "Admin User" password123
```

---

## CLI Commands

### User Management

```bash
php artisan user:create {email} {name} {password}   # Create a user
php artisan user:toggle {email}                      # Enable or disable a user
php artisan user:purge {email} --force               # Delete all data for a user (irreversible)
```

Disabled users cannot log in, but their tasks remain. Tasks assigned to other users remain
accessible to those users. An invalidated or disabled account can be re-enabled.

### API Keys

```bash
php artisan apikey:create {email}    # Generate a key — printed once, note it immediately
php artisan apikey:invalidate {key}  # Permanently invalidate a key (cannot be re-enabled)
```

Keys are stored hashed; there is no way to retrieve a key after creation.

### Maintenance

```bash
php artisan tasks:move-overdue    # Reschedule all overdue incomplete tasks to today
```

---

## Database Management

There are two databases:

| | Path | Used by |
|---|---|---|
| **Production** | `database/database.sqlite` | `.env` |
| **Test** | `database/test-database.sqlite` | `.env.testing` |

**Always specify the environment when running artisan commands manually:**

```bash
# Production
php artisan migrate:fresh --force

# Test database
php artisan migrate:fresh --force --env=testing
php artisan user:create test@example.com "Test" password123 --env=testing
```

The `testing` connection is defined in `config/database.php` and always points to the test
database file regardless of `.env`.

---

## Running Tests

The E2E test suite uses Playwright and covers authorization/privacy (task, project, and tag
access control). Tests run against the test database and auto-start a Laravel dev server.

```bash
npm run test:e2e              # Run all tests (headless)
npm run test:e2e:headed       # Watch tests run in Firefox
npm run test:e2e:ui           # Playwright interactive UI
npm run test:e2e:debug        # Playwright Inspector (step-by-step)
npm run test:e2e:report       # Open last test report

# Run a specific suite
npx playwright test task-authorization
npx playwright test project-authorization
npx playwright test tag-visibility
```

Tests create three users automatically: `user1@test.com`, `user2@test.com`, `user3@test.com`
(password: `password123`). The database is reset before each suite.

See `tests/e2e/README.md` for a full guide to the test structure and writing new tests.

---

## API

All endpoints require a bearer token from `php artisan apikey:create`.

```
Authorization: Bearer tfk_xxxxxxxxxxxxxxxxxxxxxxxx
```

### POST /api/tasks — Create a task

Natural language is parsed from the `name` field if `date` or `recurrence_pattern` are
not explicitly provided (same parser as the web quick-add bar).

**Request body (JSON):**

```json
{
  "name": "Team sync every Tuesday",
  "description": "Optional markdown description",
  "date": "2026-02-24",
  "time": "14:30",
  "project_id": 1,
  "recurrence_pattern": "Tuesday",
  "recurrence_floating": false,
  "tag_ids": [1, 2],
  "assignee_ids": [1]
}
```

All fields except `name` are optional. If `assignee_ids` is omitted, the task is assigned
to the key owner. If `project_id` is omitted, the task goes into the key owner's inbox project.

**Response (201):**

```json
{
  "success": true,
  "task": {
    "id": 42,
    "name": "Team sync",
    "date": "2026-02-24",
    "time": "14:30",
    "status": "incomplete",
    "recurrence_pattern": "Tuesday",
    "recurrence_floating": false,
    "creator": { "id": 1, "name": "..." },
    "project": { "id": 1, "name": "..." },
    "tags": [...],
    "assignees": [...]
  }
}
```

**Error response (422)** when an unrecognized recurrence keyword is detected in the name:

```json
{
  "success": false,
  "message": "The recurrence pattern in '...' was not recognized. ..."
}
```

---

### GET /api/tasks/on/{date} — Tasks due on a date

Returns all non-archived tasks dated `YYYY-MM-DD` visible to the key owner (created by or
assigned to them).

```
GET /api/tasks/on/2026-02-24
```

```json
{
  "success": true,
  "date": "2026-02-24",
  "tasks": [...]
}
```

---

### GET /api/tasks/completed/{date} — Tasks completed on a date

Returns tasks with `status = done` whose `updated_at` falls on `YYYY-MM-DD`.

```
GET /api/tasks/completed/2026-02-24
```

Response shape is the same as above.

---

## Natural Language Date & Recurrence Reference

The date parser runs on task names in the quick-add bar, the task create form, and the API.
It extracts a date and/or recurrence pattern and strips the matched text from the name.

### One-Time Dates

| Input | Result |
|---|---|
| `today` | today's date |
| `tomorrow` | tomorrow |
| `next Monday` (or any weekday) | the following Monday |
| `Monday` (or any weekday, no "next") | next occurrence of that day; **also sets recurrence** |
| `February 14` | Feb 14 this year (next year if past) |
| `2/14` | Feb 14 this year (next year if past) |
| `2026-02-14` | exact ISO date |

Note: bare day names like "Monday" are treated as recurring (pattern = `Monday`). Use
"next Monday" if you want a one-time date.

### Recurring Patterns

| Input | Recurrence pattern stored | Initial date set to |
|---|---|---|
| `daily` or `every day` | `daily` | today |
| `every other day` | `every other day` | today |
| `weekdays` | `weekdays` | next weekday |
| `weekends` | `weekends` | next Saturday |
| `weekly` or `every week` | `weekly` | 1 week from today |
| `every other week` | `every other week` | today |
| `every 2 weeks` (any number) | `every 2 weeks` | today |
| `Monday` / `every Monday` | `Monday` | next Monday |
| `every other Wednesday` | `every other Wednesday` | next Wednesday |
| `mon,tue,fri` (comma-separated 3-letter abbreviations) | `mon,tue,fri` | next matching day |
| `every first Monday` | `every first Monday` | next first Monday of a month |
| `every last Friday` | `every last Friday` | next last Friday of a month |
| `every 15th` (any day number) | `every 15` | next 15th of a month |
| `monthly` or `every month` | `monthly` | 1 month from today |
| `every 3 months` (any number) | `every 3 months` | 3 months from today |
| `yearly` or `every year` | `yearly` | 1 year from today |

### Floating Recurrence (`every!`)

Inspired by Todoist's floating recurrence: `every!` means the next occurrence is calculated
relative to when you *complete* the task, not from the original due date.

```
Team sync every! Tuesday
```

This sets `recurrence_floating = true`. A non-floating task due last Tuesday that you complete
today schedules next for *this* Tuesday. A floating task schedules next for *next* Tuesday
from today.

---

## Architecture Notes

**No deletion** — Tasks and projects are never deleted, only archived. This is intentional per
the spec; use `status = archived` to hide things.

**Authorization** — Tasks and projects are private by default. Visibility is limited to the
creator and explicitly assigned users. Check the `TaskController` and `ProjectController` for
the `->where('creator_id')` / `->orWhereHas('assignments')` pattern used throughout.

**Change logging** — All creates and updates write a record to `change_logs`. The Activity
view (`/changelogs`) surfaces this per-task, per-project, per-tag, or per-user.

**Status values** — `incomplete`, `done`, `archived`. These are the only valid values; the
enum is enforced at the migration level.

**File storage** — Attachments (task and comment) use the `private` disk. Downloads are
proxied through `TaskAttachmentController::download()` to enforce authorization.

**Session-based auth for web, hashed-token auth for API** — The `auth.api` middleware
(`app/Http/Middleware/AuthenticateApiKey.php`) validates bearer tokens against bcrypt hashes
in the `api_keys` table and checks the user's enabled status.

---

## Adding Pages to Other Links

Drop any Markdown file into `storage/app/other-links/`. It will:

- Appear as a link in the **Other Links** nav dropdown (desktop) and collapsible section
  (mobile hamburger menu) — the dropdown only renders if the directory is non-empty
- Be accessible at `/other-links/{filename}` (filename including extension)
- Use the filename (minus extension, with `-` and `_` replaced by spaces) as the page title

Subdirectories are not listed in the nav, but files in them are accessible directly by path
if you link to them manually.

Symlinks are supported — the directory listing uses `glob()` rather than Laravel's Storage
facade, which does not follow symlinks.

The nav is populated via a view composer at `app/View/Composers/NavigationComposer.php`,
which shares `$otherLinksFiles` to all views that use the navigation layout.
