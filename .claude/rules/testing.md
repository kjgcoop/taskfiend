---
paths:
  - "tests/**"
  - "phpunit.xml"
  - "playwright.config.js"
  - ".env.testing"
---

# Testing

Developer docs: `docs/content/docs/developers/testing.md` and `tests/e2e/README.md`.

## Commands

```bash
php artisan test                  # PHPUnit (Feature + Unit)
npm run test:e2e                  # Playwright, all tests
npm run test:e2e:headed           # watch in the browser
npm run test:e2e:ui               # interactive UI mode
npm test                          # both
```

Always pass `--env=testing` (or `--database=testing`) to any artisan command that touches the test DB; see `CLAUDE.md`.

## PHPUnit

- Feature tests that need `TaskController::create()` must give the user a project flagged `is_default` (`Auth::user()->defaultProject()` throws otherwise).
- Use `UploadedFile::fake()->create(...)`, not `->image()`, when `gd` may be absent (the GD-based resize branch in `storeBackgroundImage()` throws).
- For rendered-output behavior with unchanged server-side matching, assert on the rendered HTML (as `TaskCreateSlugifyTest` does).
- Feature tests that hit export endpoints leave zips in `storage/app/temp`.
- A view can't redirect with `withErrors()`; `SearchController::index()` flashes a `ViewErrorBag` manually so `assertSessionHasErrors()` works.

## Playwright E2E (`tests/e2e/`)

- Specs cover authorization and privacy: `task-authorization`, `project-authorization`, `tag-visibility`, plus panel and other specs. Tags are visible to everyone but never bypass task/project privacy.
- Helpers: `helpers/db.js` (reset/seed/cleanup, all with `--env=testing`) and `helpers/auth.js`.
- `playwright.config.js` auto-starts the dev server against `database/test-database.sqlite` and uses three seeded users, `user1@`, `user2@`, `user3@` (domain from `TEST_USER_DOMAIN` in `.env.testing`).
- A single failure like `net::ERR_ABORTED` on `page.goto` in a parallel run, which passes with `--workers=1`, has been SQLite dev-server contention rather than a regression.

## Cloud Sandbox Limits (Vary by Session; Check Before Assuming)

- Egress is restricted: `packagist.org` and `registry.npmjs.org` may be blocked, so `composer install` / `npm install` can fail. The session-start hook (`.claude/hooks/session-start.sh`) restores `vendor/` from the `vendor-cache` branch. Check whether `vendor/bin/phpunit` exists before concluding tests can't run.
- Chromium may be pre-installed at `/opt/pw-browsers`. Don't run `playwright install` if it is.
- `php artisan view:cache` compiles every Blade template but cannot catch runtime Alpine/CSP parser errors; click-testing needs a built front end (`npm run build`, or a dummy `public/hot` for pages that don't need Vite/Alpine).
- If something couldn't be run or click-tested, say so plainly in the summary.
