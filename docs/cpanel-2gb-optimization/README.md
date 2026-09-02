# cPanel 2GB Optimization — 50-70 Concurrent Users (Apache 2.4.68 + PHP-FPM + MariaDB 10.11)

**Date:** 2026-09-02 | **Project:** AccumenAI Laravel 12 | **Server:** 2GB RAM, cPanel 134, Apache 2.4.68 + PHP-FPM, MariaDB 10.11.18

## Baseline Captured (Phase 0, local XAMPP dev mirror)
- **PHP:** 8.5.8 XAMPP / 8.5.0 Herd Lite, `zend opcache v8.5.0`, `memory_limit 512M`, `max_execution_time 120`, `redis` available, `opcache.memory_consumption 128` (CLI), XAMPP `php.ini:1679` `opcache.enable` commented (needs enable on cPanel)
- **Apache:** 2.4.58 WinNT `ThreadsPerChild 150` (`httpd-mpm.conf:106`) — cPanel prod should use `mpm_event` not `winnt`
- **MariaDB:** 10.4.32 `innodb_buffer_pool_size 16M` / `innodb_log_file_size 5M` (dev default) — prod target 512M/128M
- **Laravel:** 12.68.0 `APP_ENV=local`→`production`, `QUEUE_CONNECTION=database` (`config/queue.php:16`), `CACHE_STORE=file` (local) vs prod `database`, duplicate route `admin.certificates.requests-columns` prevents `route:cache` (fallback to `route:clear` verified)
- **Bootstrap cache:** `config.php 90354 bytes`, `events.php 327`, `views 605 compiled`, `routes-v7.php` not cached (expected)

## Execution Order (CORRECTED)
Original snippet `config:cache -> route:cache -> optimize:clear` is destructive. Correct:
```bash
php artisan down --render="errors::503"
composer install --no-dev --optimize-autoloader
php artisan optimize:clear   # clear STALE first
php artisan config:cache
php artisan view:cache
php artisan event:cache
php artisan route:cache || php artisan route:clear  # duplicate name fallback
php artisan up
```

## Files in this folder
1. `01-php-fpm-pool.conf` — WHM > MultiPHP Manager > PHP-FPM pool for 2GB (ondemand 10-12 children)
2. `02-apache-mpm-event.conf` — WHM > Apache Configuration > Global Configuration (MaxRequestWorkers 100-120)
3. `03-mariadb-my.cnf` — WHM > SQL Services or `/etc/my.cnf` (buffer_pool 512M)
4. `04-opcache.ini` — WHM > MultiPHP INI Editor > OPcache
5. `05-cron-queue-setup.sh` — cPanel > Cron Jobs (schedule:run + queue:work --stop-when-empty)
6. `optimize-cpanel.sh` (in `scripts/`) — one-shot deploy helper for cPanel Terminal

## Quick Apply (cPanel Terminal, project root)
```bash
bash scripts/optimize-cpanel.sh
```
Then apply WHM configs via UI and set crons.

## Rollback
```bash
cp .env.bak.20260902_* .env
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan up
```
See `../_VERIFY_AND_ROLLBACK.md` for full checklist.

## Swap Warning
Current Swap 11.93% + Memory 40% — if `pm.max_children` too high, swap will spike >50% and OOM. Formula: `(2048 - 600 MariaDB - 200 Apache - 300 system) / 60 ≈ 10-12`.
