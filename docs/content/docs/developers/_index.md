---
title: "For Developers"
---

## Local setup

```bash
cp .env.example .env
composer setup        # installs deps, generates app key, runs migrations, builds assets
php artisan user:create admin@example.com "Admin User" password123
php artisan serve
```

## Docker setup

```bash
cp .env.example .env
# Edit .env: set APP_KEY, APP_ENV=production, APP_DEBUG=false
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan user:create admin@example.com "Admin User" password123
```

---

## CLI commands

### User management

```bash
php artisan user:create {email} {name} {password}   # Create a user
php artisan user:toggle {email}                      # Enable or disable a user
php artisan user:purge {email} --force               # Delete all data for a user (irreversible)
php artisan user:password {email}                    # Reset password; outputs new password
```

Disabled users cannot log in, but their tasks remain. An account can be re-enabled. API keys for disabled users are rejected by the API middleware.

### API keys

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

## Database management

| | Path | Used by |
|---|---|---|
| **Production** | `database/database.sqlite` | `.env` |
| **Test** | `database/test-database.sqlite` | `.env.testing` |

Always specify the environment when running artisan commands against a specific database:

```bash
# Production
php artisan migrate:fresh --force

# Test database
php artisan migrate:fresh --force --env=testing
php artisan user:create test@example.com "Test" password123 --env=testing
```

The `testing` connection is defined in `config/database.php` and always points to the test database file regardless of `.env`.

---

## Architecture notes

**No deletion** — Tasks and projects are never deleted, only archived. Use `status = archived` to hide things.

**Status values** — `incomplete`, `done`, `archived`. These are the only valid values; enforced at the migration level.

**Authorization** — Tasks and projects are private by default. Visibility is limited to the creator and explicitly assigned users. Look for the `->where('creator_id')` / `->orWhereHas('assignments')` pattern in `TaskController` and `ProjectController`.

**Change logging** — All creates and updates write a record to `change_logs`. The Activity view (`/changelogs`) surfaces this per-task, per-project, per-tag, or per-user.

**File storage** — Attachments use the `private` disk. Downloads are proxied through `TaskAttachmentController::download()` to enforce authorization.

**Auth split** — Session-based auth for web routes, hashed-token auth for API routes. The `auth.api` middleware (`app/Http/Middleware/AuthenticateApiKey.php`) validates bearer tokens against bcrypt hashes in `api_keys` and checks the user's enabled status.

---

## Adding pages to Other Links

I added the ability to add other links because I didn't want to put words in your mouth with respect to a privacy policy or Terms of Service or such nonsense. Now it's your problem to add. 

Drop any Markdown file into `storage/app/other-links/`. It will:

- Appear as a link in the **Other Links** nav dropdown (desktop) and collapsible section (mobile)
- Be accessible at `/other-links/{filename}` (filename including extension)
- Use the filename (minus extension, with `-` and `_` replaced by spaces) as the page title

The nav is populated by `app/View/Composers/NavigationComposer.php`, which shares `$otherLinksFiles` to all views using the navigation layout. Symlinks are supported.

---

## Contributing to documentation

The docs live in `docs/` and are built with [MkDocs Material](https://squidfunk.github.io/mkdocs-material/).

To preview changes locally:

```bash
pip install mkdocs-material
cd docs
mkdocs serve
```

Then open `http://localhost:8000`. The nav is defined in `docs/mkdocs.yml` — update it when adding new pages.

If you document something that others running Task Fiend would find useful, please consider contributing it back.
