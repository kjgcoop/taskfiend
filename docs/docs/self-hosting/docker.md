# Docker Deployment

This guide covers running Task Fiend with Docker, which provides a self-contained environment with Nginx and PHP-FPM.

## Prerequisites

- Docker installed
- Docker Compose (comes with Docker Desktop; install separately on Linux)

## Quick start

### 1. Create environment file

```bash
cp .env.example .env
```

Edit `.env` and set at minimum:

```bash
APP_KEY=base64:your-32-character-random-key-here   # or run: php artisan key:generate
APP_ENV=production
APP_DEBUG=false
APP_PORT=8000
```

### 2. Build and start

```bash
docker compose up -d --build
```

(Use `docker-compose` instead of `docker compose` for Docker V1.)

### 3. Initialize the database and create your first user

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan user:create admin@example.com "Admin User" yourpassword
```

### 4. Open the app

Navigate to `http://localhost:8000` and log in.

## Common Docker commands

```bash
# Start/stop
docker compose up -d
docker compose down

# View logs
docker compose logs -f
docker compose logs -f app       # PHP only
docker compose logs -f nginx     # Nginx only

# Run artisan commands
docker compose exec app php artisan <command>

# Get a shell inside the container
docker compose exec app bash
```

## User and API key management

```bash
docker compose exec app php artisan user:create user@example.com "Name" password123
docker compose exec app php artisan user:toggle user@example.com
docker compose exec app php artisan apikey:create user@example.com
docker compose exec app php artisan apikey:invalidate tfk_xxxxx
```

## Production configuration

Recommended `.env` settings for production:

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your-generated-key-here
APP_URL=https://yourdomain.com
APP_PORT=8000

DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite

SESSION_DRIVER=file
CACHE_STORE=file
```

## Persistent data

The database file lives at `./database/database.sqlite` on your host and is mounted as a Docker volume. It persists when containers are recreated.

To back it up:

```bash
cp database/database.sqlite database/database.sqlite.backup
```

## Using a reverse proxy

If you're putting Task Fiend behind Caddy, Traefik, or another Nginx instance:

1. Bind only to localhost in `docker-compose.yml`:
   ```yaml
   ports:
     - "127.0.0.1:8000:80"
   ```

2. Point your reverse proxy at `http://localhost:8000`.

Example Caddy config:
```
yourdomain.com {
    reverse_proxy localhost:8000
}
```

## Architecture

Two containers:

| Container | Role |
|-----------|------|
| **app** | PHP 8.2 FPM — runs Laravel, handles artisan commands |
| **nginx** | Alpine Nginx — serves static files, proxies PHP requests to app |

Both communicate over a Docker network called `task_fiend`.

## Troubleshooting

### Permission errors

```bash
docker compose exec app chown -R www-data:www-data /var/www/html/storage
docker compose exec app chown -R www-data:www-data /var/www/html/database
docker compose exec app chmod -R 775 /var/www/html/storage
docker compose exec app chmod -R 775 /var/www/html/database
```

### 500 Internal Server Error

1. Check logs: `docker compose logs nginx` and `docker compose logs app`
2. Confirm `APP_KEY` is set in `.env`
3. Clear config cache: `docker compose exec app php artisan config:clear`

### Database issues

```bash
# Verify the file exists
docker compose exec app ls -la /var/www/html/database/

# Run migrations if needed
docker compose exec app php artisan migrate --force
```

### Port conflict

Change `APP_PORT` in `.env` then restart:

```bash
docker compose down && docker compose up -d
```

### Rebuilding after changes

```bash
docker compose down
docker compose build --no-cache
docker compose up -d
```
