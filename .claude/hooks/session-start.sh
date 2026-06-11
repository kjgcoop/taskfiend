#!/bin/bash
set -euo pipefail

# Only run in remote (web) environments
if [ "${CLAUDE_CODE_REMOTE:-}" != "true" ]; then
  exit 0
fi

echo '{"async": true, "asyncTimeout": 300000}'

cd "${CLAUDE_PROJECT_DIR:-/home/user/taskfiend}"

# ── 1. PHP dependencies ────────────────────────────────────────────────────────
# The network policy in this environment blocks packagist/github.com, so
# composer install won't work directly. Instead we fetch a pre-built vendor
# archive from the vendor-cache branch (built by .github/workflows/vendor-cache.yml).
if [ ! -f vendor/laravel/framework/src/Illuminate/Foundation/Application.php ]; then
  echo "Installing PHP dependencies from vendor-cache branch..."
  if git fetch origin vendor-cache 2>/dev/null; then
    git checkout origin/vendor-cache -- vendor.tar.gz 2>/dev/null && \
      tar -xzf vendor.tar.gz && rm vendor.tar.gz && \
      echo "✅ vendor extracted from vendor-cache branch" || \
      echo "⚠️  vendor.tar.gz not found on vendor-cache branch"
  else
    echo "⚠️  vendor-cache branch not available, trying composer install..."
    COMPOSER_ALLOW_SUPERUSER=1 composer install --no-interaction --prefer-dist --no-dev --no-scripts 2>/dev/null || true
  fi
  # Regenerate autoloader (works even if only prod packages installed)
  COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-interaction 2>/dev/null || true
fi

# ── 2. App environment ─────────────────────────────────────────────────────────
if [ ! -f .env ]; then
  cp .env.example .env
  sed -i 's|APP_ENV=production|APP_ENV=local|' .env
  sed -i 's|APP_DEBUG=false|APP_DEBUG=true|' .env
  sed -i 's|APP_URL=.*|APP_URL=http://localhost:8000|' .env
  sed -i 's|SESSION_DRIVER=database|SESSION_DRIVER=file|' .env
  sed -i 's|CACHE_STORE=database|CACHE_STORE=file|' .env
  php artisan key:generate --force
fi

# ── 3. Production database ─────────────────────────────────────────────────────
DB_PATH="database/database.sqlite"
if [ ! -f "$DB_PATH" ]; then
  touch "$DB_PATH"
  php artisan migrate --force
  php artisan user:create test@example.com "Test User" password123 2>/dev/null || true
fi

# ── 4. Test environment ────────────────────────────────────────────────────────
if [ ! -f .env.testing ]; then
  cat > .env.testing << 'ENVEOF'
APP_NAME="Task Fiend Test"
APP_ENV=testing
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost:8003
APP_TIMEZONE=UTC
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
LOG_CHANNEL=stderr
LOG_LEVEL=debug
DB_CONNECTION=testing
SESSION_DRIVER=file
CACHE_STORE=array
QUEUE_CONNECTION=sync
FILESYSTEM_DISK=local
MAIL_MAILER=array
DISABLE_REGISTRATION=false
BULK_INPUT_MAX_CHARS=10000
BULK_INPUT_MAX_LINES=100
LONG_TEXT_MAX_CHARS=10000
ENVEOF
  php artisan key:generate --env=testing --force
fi

# ── 5. Test database ───────────────────────────────────────────────────────────
TEST_DB="database/test-database.sqlite"
if [ ! -f "$TEST_DB" ]; then
  touch "$TEST_DB"
fi
php artisan migrate:fresh --force --env=testing
php artisan user:create user1@test.com "User One" password123 --env=testing 2>/dev/null || true
php artisan user:create user2@test.com "User Two" password123 --env=testing 2>/dev/null || true
php artisan user:create user3@test.com "User Three" password123 --env=testing 2>/dev/null || true

# ── 8. Frontend assets ────────────────────────────────────────────────────────
if [ ! -f public/build/manifest.json ]; then
  echo "Restoring frontend build from vendor-cache branch..."
  git checkout origin/vendor-cache -- public-build.tar.gz 2>/dev/null && \
    tar -xzf public-build.tar.gz && rm public-build.tar.gz && \
    echo "✅ public/build extracted from vendor-cache branch" || \
    echo "⚠️  public-build.tar.gz not found on vendor-cache branch"
fi
# Shim @playwright/test → global playwright (no npm install needed)
if [ ! -f node_modules/@playwright/test/index.mjs ]; then
  mkdir -p node_modules/@playwright/test
  cat > node_modules/@playwright/test/package.json << 'PKGJSON'
{"name":"@playwright/test","version":"1.56.1","type":"module","exports":{".":"./index.mjs"}}
PKGJSON
  cat > node_modules/@playwright/test/index.mjs << 'MJSEOF'
export { chromium, firefox, webkit, selectors, devices, errors, request,
  test, expect, defineConfig, mergeTests, mergeExpects } from '/opt/node22/lib/node_modules/playwright/test.mjs';
export { default } from '/opt/node22/lib/node_modules/playwright/test.mjs';
MJSEOF
fi

# ── 7. Tell Playwright where browsers live ────────────────────────────────────
export PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers
echo "export PLAYWRIGHT_BROWSERS_PATH=/opt/pw-browsers" >> "${CLAUDE_ENV_FILE:-/dev/null}"

echo "✅ Session setup complete"
