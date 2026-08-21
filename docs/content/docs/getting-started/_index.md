---
title: "Getting Started"
---

## Requirements

- **PHP 8.2+**, with these extensions: BCMath, Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring,
  OpenSSL, PCRE, PDO, Session, Tokenizer, XML — the standard set Laravel 12 needs. If you're using
  the default SQLite database (see below), you'll also need the `pdo_sqlite` and `sqlite3`
  extensions specifically.
- **Composer 2**
- **Node.js 18+** and npm, to build the frontend assets (Vite + Tailwind)
- **A database**: **SQLite** is the default and requires no separate server — `composer setup`
  works out of the box with it. MySQL and PostgreSQL are also supported (see `config/database.php`)
  if you'd rather point `DB_CONNECTION` at an existing server.

If `composer setup` fails partway through, it's almost always a missing PHP extension — check the
error output for which one, install it, and re-run the command.

## Local Development Setup:
1. Create environment file: `cp .env.example .env`
2. Run the all-in-one setup command: `composer setup`
   - This installs PHP and JS dependencies, generates an app key, runs migrations, and builds frontend assets.
3. Create your first user: `php artisan user:create admin@example.com "Admin User" password123`
4. Start the dev server: `php artisan serve`

## Alternative Docker Setup:
1. Create environment file: `cp .env.example .env`
2. Edit .env and set APP_KEY, APP_ENV=production, APP_DEBUG=false
3. Build and start containers: `docker compose up -d --build` (or `docker-compose` for Docker V1)
4. Run migrations and create first user:
   - `docker compose exec app php artisan migrate --force`
   - `docker compose exec app php artisan user:create admin@example.com "Admin User" password123`


