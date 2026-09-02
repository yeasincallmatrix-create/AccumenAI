#!/bin/bash
# optimize-cpanel.sh — Run from project root via cPanel Terminal or SSH
# For 2GB cPanel + Apache 2.4.68 + PHP-FPM + MariaDB 10.11 — Laravel 12
# Idempotent: safe to re-run after each deploy. Handles route:cache duplicate fallback.
set -e
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_ROOT"
echo "[$(date)] Starting cPanel optimization in $PROJECT_ROOT"

# 0. Pre-checks
php -v | head -n1
php -m | grep -i opcache || echo "WARN: opcache not loaded"
php artisan --version
if [ ! -f .env ]; then echo "ERROR: .env missing"; exit 1; fi
cp .env ".env.bak.$(date +%Y%m%d_%H%M%S)"
echo "Backup: .env.bak.* created"

# 1. Maintenance down (allow bypass via secret if needed: php artisan down --secret=xxxx)
php artisan down --render="errors::503" || true

# 2. Composer optimize (use --no-dev on prod, skip if offline)
if command -v composer >/dev/null 2>&1; then
  echo "[Composer] install --no-dev --optimize-autoloader"
  composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction || echo "WARN: composer install failed (offline?) — continuing"
else
  echo "WARN: composer not found — skipping install"
fi

# 3. Clear stale cache FIRST (critical order)
php artisan optimize:clear

# 4. Ensure prod flags (do not overwrite APP_KEY)
if grep -q "^APP_ENV=" .env; then sed -i 's/^APP_ENV=.*/APP_ENV=production/' .env; else echo "APP_ENV=production" >> .env; fi
if grep -q "^APP_DEBUG=" .env; then sed -i 's/^APP_DEBUG=.*/APP_DEBUG=false/' .env; else echo "APP_DEBUG=false" >> .env; fi
if grep -q "^LOG_LEVEL=" .env; then sed -i 's/^LOG_LEVEL=.*/LOG_LEVEL=warning/' .env; fi
echo "Flags: APP_ENV=production APP_DEBUG=false LOG_LEVEL=warning"

# 5. Cache (route has fallback)
php artisan config:cache
php artisan view:cache
php artisan event:cache || php artisan event:clear

# Route cache — duplicate name admin.certificates.requests-columns will fail; fallback to clear
if ! php artisan route:cache; then
  echo "[Fallback] route:cache failed — clearing"
  php artisan route:clear
fi

# 6. Migrate + storage link (safe)
php artisan migrate --force || echo "WARN: migrate failed"
php artisan storage:link || true

# 7. Permissions (cPanel)
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# 8. Up
php artisan up || php artisan up
php artisan about --only=environment || php artisan env
echo "[$(date)] Done. Verify: ls -lh bootstrap/cache/config.php storage/framework/views | head"
ls -lh bootstrap/cache/config.php 2>&1 | head -n5
echo "Next: apply WHM configs docs/cpanel-2gb-optimization/01..04 and set crons 05-cron-queue-setup.sh"
