# Task Fiend - Docker Deployment Guide

This guide explains how to run Task Fiend using Docker, providing a production-ready environment with Nginx and PHP-FPM without needing to install Apache or Nginx system-wide.

## Prerequisites

- Docker installed on your system
- Docker Compose installed (usually comes with Docker Desktop)

## Quick Start

### 1. Initial Setup

First, create your environment file:

```bash
cp .env.example .env
```

Edit `.env` and set your application key and other configuration:

```bash
# Generate app key (you can use any random 32-character string)
# Or run: php artisan key:generate (if you have PHP installed locally)
APP_KEY=base64:your-32-character-random-key-here
APP_ENV=production
APP_DEBUG=false
APP_PORT=8000

# Database is SQLite by default
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite
```

### 2. Build and Start Containers

```bash
docker-compose up -d --build
```

This will:
- Build the PHP-FPM container with all Laravel dependencies
- Start an Nginx container to serve the application
- Map port 8000 (or your custom APP_PORT) to your host machine

### 3. Initialize the Application

Run migrations and create your first user:

```bash
# Run database migrations
docker-compose exec app php artisan migrate --force

# Create your first user
docker-compose exec app php artisan user:create admin@example.com "Admin User" yourpassword

# Generate an API key (optional)
docker-compose exec app php artisan apikey:create admin@example.com
```

### 4. Access the Application

Open your browser and navigate to:
```
http://localhost:8000
```

Login with the credentials you created in step 3.

## Docker Commands

### Start/Stop Containers

```bash
# Start containers (in background)
docker-compose up -d

# Stop containers
docker-compose down

# Stop and remove volumes (caution: removes database!)
docker-compose down -v

# View logs
docker-compose logs -f

# View logs for specific service
docker-compose logs -f app
docker-compose logs -f nginx
```

### Running Artisan Commands

All Laravel artisan commands should be run inside the container:

```bash
# General pattern
docker-compose exec app php artisan <command>

# Examples:
docker-compose exec app php artisan migrate
docker-compose exec app php artisan user:create email@example.com "Name" password
docker-compose exec app php artisan apikey:create email@example.com
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
```

### Accessing the Container Shell

```bash
# Get a bash shell inside the PHP container
docker-compose exec app bash

# Once inside, you can run commands directly:
php artisan migrate
php artisan tinker
```

### Managing Users and API Keys

```bash
# Create a new user
docker-compose exec app php artisan user:create user@example.com "User Name" password123

# Toggle user enabled/disabled
docker-compose exec app php artisan user:toggle user@example.com

# Create API key for user
docker-compose exec app php artisan apikey:create user@example.com

# Invalidate API key
docker-compose exec app php artisan apikey:invalidate tfk_xxxxx
```

## Production Deployment

### Environment Variables

For production, ensure these settings in your `.env`:

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:your-generated-key-here
APP_URL=https://yourdomain.com

# Change default port if needed
APP_PORT=8000

# SQLite database path (inside container)
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/html/database/database.sqlite

# Session and cache settings
SESSION_DRIVER=file
CACHE_STORE=file
```

### File Permissions

The Dockerfile automatically sets correct permissions, but if you encounter issues:

```bash
docker-compose exec app chown -R www-data:www-data /var/www/html/storage
docker-compose exec app chown -R www-data:www-data /var/www/html/database
docker-compose exec app chmod -R 775 /var/www/html/storage
docker-compose exec app chmod -R 775 /var/www/html/database
```

### Persistent Data

The database file is stored in `./database/database.sqlite` on your host machine and is mounted as a volume. This ensures your data persists even if containers are recreated.

To backup your database:

```bash
# Simply copy the database file
cp database/database.sqlite database/database.sqlite.backup

# Or use Docker volume
docker-compose exec app cp /var/www/html/database/database.sqlite /var/www/html/database/backup.sqlite
```

### Using a Reverse Proxy

If you want to run this behind a reverse proxy (like Caddy, Traefik, or another Nginx instance) with HTTPS:

1. Change the port mapping in `docker-compose.yml`:
```yaml
ports:
  - "127.0.0.1:8000:80"  # Only bind to localhost
```

2. Configure your reverse proxy to forward to `http://localhost:8000`

Example Caddy configuration:
```
yourdomain.com {
    reverse_proxy localhost:8000
}
```

## Architecture

This Docker setup consists of two containers:

1. **app** (PHP-FPM)
   - Runs PHP 8.2 with FPM
   - Includes all required PHP extensions (SQLite, GD, etc.)
   - Composer dependencies installed during build
   - Storage and database directories have correct permissions

2. **nginx** (Web Server)
   - Alpine-based Nginx container
   - Configured specifically for Laravel
   - Proxies PHP requests to the app container
   - Serves static files directly

Both containers communicate via a shared Docker network called `task_fiend`.

## Troubleshooting

### Permission Denied Errors

```bash
docker-compose exec app chown -R www-data:www-data /var/www/html/storage
docker-compose exec app chown -R www-data:www-data /var/www/html/database
docker-compose exec app chmod -R 775 /var/www/html/storage
docker-compose exec app chmod -R 775 /var/www/html/database
```

### 500 Internal Server Error

1. Check Nginx logs: `docker-compose logs nginx`
2. Check PHP logs: `docker-compose logs app`
3. Ensure APP_KEY is set in `.env`
4. Clear caches: `docker-compose exec app php artisan config:clear`

### Database Connection Issues

1. Verify database file exists: `docker-compose exec app ls -la /var/www/html/database/`
2. Check permissions: `docker-compose exec app stat /var/www/html/database/database.sqlite`
3. Run migrations: `docker-compose exec app php artisan migrate --force`

### Port Already in Use

If port 8000 is already in use, change APP_PORT in `.env`:

```env
APP_PORT=8080
```

Then rebuild: `docker-compose down && docker-compose up -d`

### Rebuilding After Code Changes

If you modify the Dockerfile or need to update dependencies:

```bash
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

## Development vs Production

### Development Mode

For local development with hot-reloading, you can mount the entire codebase as a volume (already configured in docker-compose.yml):

```bash
# The current docker-compose.yml already mounts ./:/var/www/html
# So code changes are reflected immediately
docker-compose up -d
```

Set in `.env`:
```env
APP_ENV=local
APP_DEBUG=true
```

### Production Mode

For production, you may want to remove the volume mount and bake the code into the image:

1. Comment out the volume in `docker-compose.yml`:
```yaml
# volumes:
#   - ./:/var/www/html
```

2. Rebuild the image: `docker-compose build --no-cache`

## Advanced: Multi-Stage Builds

The current Dockerfile is optimized for simplicity. For even smaller production images, you could implement a multi-stage build that separates the build environment from the runtime environment.

## Support

For issues specific to the application, see `CLAUDE.md` and `spec.md`.
For Docker-related issues, check the official Docker documentation at https://docs.docker.com
