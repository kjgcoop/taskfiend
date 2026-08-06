---
title: "For Developers"
---

Start with the [Getting Started](/docs/getting-started/) instructions to install.

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

**Alpine.js runs in CSP-safe mode** — directive expressions (`x-data`, `@click`, `:class`, ...) can only be a single JS expression, not statements like `const`/`if`. Multi-step logic needs to live in an `Alpine.data()` component method instead. See [Alpine.js & CSP](/docs/developers/frontend-csp/) for the failure mode and the fix pattern.

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

The docs live in `docs/` and are built with [Hugo](https://gohugo.io/).

If you don't already have Hugo installed, you can do so on Ubuntu with:
```bash
apt-get install hugo
```

To preview changes locally:

```bash
cd docs
hugo serve -D
```

Then open http://localhost:1313.

If you document something that others running Task Fiend would find useful, please consider contributing it back.
