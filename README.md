## Task Fiend

A self-hosted to-do list for small groups. Vibe-coded because no existing tool handled multi-user task assignment quite right, and watching an AI write software is fun.

**[Full documentation at taskfiend.online](https://taskfiend.online)**

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

## Frontend assets

Third-party assets are vendored so the app works without external CDN requests.

| Asset | Source | License |
|-------|--------|---------|
| **Figtree** font | [Fontsource](https://fontsource.org/fonts/figtree) — Erik Kennedy | SIL OFL 1.1 (`public/fonts/OFL.txt`) |
| **Instrument Sans** font | [Fontsource](https://fontsource.org/fonts/instrument-sans) — Rodrigo Fuenzalida | SIL OFL 1.1 (`public/fonts/OFL.txt`) |
| **marked.js** v15.0.12 | [github.com/markedjs/marked](https://github.com/markedjs/marked) | MIT |
