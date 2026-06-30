---
title: "Getting Started"
---

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


