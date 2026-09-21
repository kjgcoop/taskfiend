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

**Outbound email goes through Mailgun's HTTP API, not SMTP** — many VPS providers (including
DigitalOcean) block outgoing SMTP, so `MAIL_MAILER=smtp`/PHP's `mail()` aren't usable in production.
`App\Services\Mailgun\MailgunClient` calls Mailgun's HTTP API directly via Laravel's `Http` facade
(Guzzle, already a framework dependency) rather than requiring the `mailgun/mailgun-php` SDK or
Symfony's `mailgun-mailer` transport — neither is installable in every environment this app runs in.
Configure `MAILGUN_API_KEY` and `MAILGUN_BASE` in `.env`. `MAILGUN_BASE` is the **domain name**
Mailgun gave you for the account — for a free/trial account, the auto-generated
`sandboxXXXX.mailgun.org` shown on the dashboard; for a paid account, whatever custom domain you
verified there. Not a URL — just the domain, no `https://` and no path. It's used for two things at
once: building the API request (`https://api.mailgun.net/v3/<MAILGUN_BASE>/messages`), and as the
domain of the emails' "from" address (`MailgunClient::defaultFrom()`, which keeps
`MAIL_FROM_NAME`'s display name and `MAIL_FROM_ADDRESS`'s local part but forces the domain to
`MAILGUN_BASE`). These two roles are deliberately not split into separate env vars — in every setup
this account can have, the domain you authenticate as and the domain you're allowed to send *as* are
the same one, so a second knob would only be one more way to get it wrong. `MailgunClient::configurationErrors()`
catches the mistake of pasting a full URL into `MAILGUN_BASE` (which otherwise fails opaquely, as a
404 from Mailgun's API on the mangled path) with a message that says what's actually wrong instead.
Separately: a *free/trial* account's sandbox domain can only send to recipients you've explicitly
added to the account's authorized-recipients list (see the dashboard) — sending to anyone else 403s
with "Free accounts are for test purposes only...", regardless of `MAILGUN_BASE` being otherwise
correct. `domain`/`secret`/`endpoint` (`config('services.mailgun.*')`) use the same key names
Laravel's own built-in `mailgun` mail transport expects, so switching to `MAIL_MAILER=mailgun` later
is a drop-in if `symfony/mailgun-mailer` ever becomes installable — except that transport doesn't
force the "from" domain the way `defaultFrom()` does, so `MAIL_FROM_ADDRESS` would need to already
be on `MAILGUN_BASE`'s domain at that point. `App\Services\TaskDigestMailer` builds a user's "tasks
for the day" digest (used today by `php artisan email:task-digest`) without touching `Auth::id()` or
`request()`, so it can be called the same way from a queued/scheduled job later.

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
