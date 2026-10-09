# Task Fiend

A self-hosted to-do list for small groups: Laravel, SQLite and Alpine.js. Full documentation lives at [taskfiend.online/docs](https://taskfiend.online/docs/) (source in `docs/content/docs/`).

## Dependencies

- PHP 8.2 or newer, with the SQLite, GD (image scaling and PNG export), mbstring, and zip extensions
- Composer
- Node.js and npm (to build frontend assets; Playwright for browser tests)
- SQLite (no separate database server needed)
- Optional: Docker and Docker Compose for production deployment
- Optional: a Mailgun account for the morning email digest

## Setup

### Local Development

```bash
cp .env.example .env
composer setup          # installs dependencies, generates APP_KEY, migrates, builds assets
php artisan user:create admin@example.com "Admin User" password123
php artisan serve       # http://localhost:8000
```

For hot reloading, run `composer dev` (server, queue listener, log tail, and Vite together).

### Docker

```bash
cp .env.example .env
# Edit .env: set APP_KEY, APP_ENV=production, APP_DEBUG=false, APP_DOMAIN, APP_PORT
docker compose up -d --build
docker compose exec app php artisan migrate --force
docker compose exec app php artisan user:create admin@example.com "Admin User" password123
```

The nginx container serves HTTPS on `APP_PORT` (default 8118) using Let's Encrypt certificates for `APP_DOMAIN` from `/etc/letsencrypt`.

### Upgrading

After pulling a new release, run `npm run build` and `php artisan migrate` (Docker: `docker compose exec app php artisan migrate --force`).

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

### Scheduled Jobs

Scheduled project creation and the morning email need a cron entry running every minute:

```
* * * * * cd /path/to/taskfiend && php artisan schedule:run >> /dev/null 2>&1
```

## Running

```bash
php artisan serve                              # development server
php artisan apikey:create admin@example.com    # API key (tfk_xxxxx)
```

Other CLI commands (in `app/Console/Commands/`): `user:create`, `user:toggle`, `apikey:create`, `apikey:invalidate`, `email:task-digest`, `email:task-png`, `temp:prune`, `tasks:backfill-completed-at`, `todoist:import`.

## Testing

```bash
php artisan migrate:fresh --force --env=testing   # prepare the test database (never omit --env)
npm test                                          # PHPUnit and Playwright
php artisan test                                  # PHPUnit only
npm run test:e2e                                  # Playwright only
```

Test settings come from `.env.testing`, which uses `database/test-database.sqlite`.
