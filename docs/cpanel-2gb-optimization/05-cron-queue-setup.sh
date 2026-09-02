#!/bin/bash
# cPanel Cron Setup — Laravel 12 queue + scheduler for 2GB (no Supervisor)
# Add via: cPanel > Cron Jobs > Add New Cron Job
# PHP path: check cPanel > Select PHP Version or `which php` => /opt/alt/php82/usr/bin/php or /usr/bin/php or ea-php82
# Project path: /home/yourdomain/public_html or /home/yourdomain/accumenai (adjust)

PHP_BIN="/opt/alt/php82/usr/bin/php"
PROJECT="/home/yourdomain/public_html"
LOG="/home/yourdomain/logs/laravel-cron.log"
mkdir -p "$(dirname "$LOG")"

# 1. Laravel Scheduler — every minute (required for queued jobs, backups, etc.)
# cPanel Cron: * * * * * /opt/alt/php82/usr/bin/php /home/yourdomain/public_html/artisan schedule:run >> /home/yourdomain/logs/laravel-cron.log 2>&1
$PHP_BIN $PROJECT/artisan schedule:run >> "$LOG" 2>&1

# 2. Queue Worker — database driver (QUEUE_CONNECTION=database, config/queue.php:16, after_commit true)
# cPanel has no Supervisor, so use --stop-when-empty every minute (see QUEUE_REMEDIATION_FORENSIC_REPORT.md)
# cPanel Cron: * * * * * /opt/alt/php82/usr/bin/php /home/yourdomain/public_html/artisan queue:work database --queue=default,notifications --sleep=3 --tries=3 --timeout=30 --stop-when-empty >> /home/yourdomain/logs/queue.log 2>&1
# Fallback if host kills after 30s or cron limited to 15m: set .env QUEUE_CONNECTION=sync (one-line, trades 2-5s HTTP block for zero infra)

# 3. Horizon not needed (database driver). If switching to redis, use Supervisor via WHM > Service Manager.

# Verify after adding:
# crontab -l
# tail -f ~/logs/laravel-cron.log ~/logs/queue.log
# php artisan queue:failed  (check failed_jobs table)
