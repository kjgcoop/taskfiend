## Task Fiend

A self-hosted to-do list for small groups. Vibe-coded because no existing tool handled multi-user task assignment quite right, and watching an AI write software is fun.

Built with Laravel, SQLite and Alpine.js. **[Full documentation at taskfiend.online](https://taskfiend.online/docs/)** (source in `docs/content/docs/`).

## Dependencies

- PHP 8.2 or newer, with the SQLite, GD (image scaling and PNG export), mbstring, and zip extensions
- Composer
- Node.js and npm (to build frontend assets; Playwright for browser tests)
- SQLite (no separate database server needed)
- Optional: Docker and Docker Compose for production deployment
- Optional: a Mailgun account for the morning email digest


## Quick start

### Local development

```bash
cp .env.example .env
composer setup
php artisan user:create admin@example.com "Admin User" password123
php artisan serve
```

### Docker

```bash
cp .env.example .env
# Edit .env: set APP_KEY, APP_ENV=production, APP_DEBUG=false
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan user:create admin@example.com "Admin User" password123
```

The nginx container serves HTTPS on `APP_PORT` (default 8118) using Let's Encrypt certificates for `APP_DOMAIN` from `/etc/letsencrypt`.

## Upgrading

After pulling a new release, run `npm run build` and `php artisan migrate` (Docker: `docker compose exec app php artisan migrate --force`). Release notes list anything else required.

## Configuration

All configuration is in `.env` (see `.env.example` for every option and its comments).

| Variable | Purpose |
|---|---|
| `APP_URL`, `APP_DOMAIN`, `APP_PORT` | Public address of the instance |
| `APP_TIMEZONE` | Timezone used for "today" and all date logic |
| `DB_CONNECTION`, `DB_DATABASE` | SQLite database (default `database/database.sqlite`) |
| `DISABLE_REGISTRATION` | Set `true` to block public sign-ups |
| `MAILGUN_API_KEY`, `MAILGUN_BASE`, `MAIL_*` | Outgoing email (optional) |
| `TODOIST_KEY` | Used by `todoist:import` |
| `MAX_FILE_SIZE`, `SCALE_LARGEST_TO` | Attachment size limit and image downscaling |
| `PAGINATION_PER_PAGE`, `BULK_INPUT_MAX_*`, `LONG_TEXT_MAX_CHARS` | List and input limits |
| `HUMAN_DATE_FORMAT`, `MAPS_URL_TEMPLATE`, `LOCATION_TRUNCATE_LENGTH` | Display preferences |
| `DAY_EXPORT_PNG_WIDTH` | Width of the day view's PNG export |

### Scheduled Jobs and Morning Email

Scheduled project creation and the morning email need a cron entry running every minute. Users opt in to the email (a daily digest or PNG) under Email Preferences on their profile, which also needs `MAILGUN_API_KEY` and `MAILGUN_BASE`. See the [developer docs](https://taskfiend.online/docs/developers/).

```
* * * * * cd /path/to/taskfiend && php artisan schedule:run >> /dev/null 2>&1
```

## Running

```bash
php artisan serve                              # development server
php artisan apikey:create admin@example.com    # API key (tfk_xxxxx)
```

Other CLI commands (in `app/Console/Commands/`): `user:create`, `user:toggle`, `apikey:create`, `apikey:invalidate`, `email:task-digest`, `email:task-png`, `temp:prune`, `tasks:backfill-completed-at`, `tasks:backfill-creator-assignments`, `templates:convert-to-projects`, `todoist:import`.

## Testing

```bash
php artisan migrate:fresh --force --env=testing   # prepare the test database (never omit --env)
npm test                                          # PHPUnit and Playwright
php artisan test                                  # PHPUnit only
npm run test:e2e                                  # Playwright only
```

Test settings come from `.env.testing`, which uses `database/test-database.sqlite`.

## Frontend assets

Third-party assets are vendored so the app works without external CDN requests.

| Asset | Source | License |
|-------|--------|---------|
| **Figtree** font | [Fontsource](https://fontsource.org/fonts/figtree) — Erik Kennedy | SIL OFL 1.1 (`public/fonts/OFL.txt`) |
| **Instrument Sans** font | [Fontsource](https://fontsource.org/fonts/instrument-sans) — Rodrigo Fuenzalida | SIL OFL 1.1 (`public/fonts/OFL.txt`) |
| **marked.js** v15.0.12 | [github.com/markedjs/marked](https://github.com/markedjs/marked) | MIT |
