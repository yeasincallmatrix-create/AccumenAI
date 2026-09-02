# Verify & Rollback — 2GB cPanel Optimization

## Verify (cPanel Terminal, project root)

```bash
# 1. Env
grep -E "^APP_ENV|^APP_DEBUG|^LOG_LEVEL" .env
# Expect: APP_ENV=production, APP_DEBUG=false, LOG_LEVEL=warning

# 2. Cache files
ls -lh bootstrap/cache/config.php bootstrap/cache/events.php
# Expect: config.php ~90KB, events.php exists, routes-v7.php ABSENT (duplicate fallback is intentional)
ls -lh storage/framework/views | wc -l
# Expect: 600+ compiled

# 3. OPcache
php -i | grep -E "opcache.enable|opcache.memory_consumption|opcache.max_accelerated"
# Expect: enable On, memory 128, max 10000, validate_timestamps Off (prod)

# 4. FPM
cat /opt/alt/php82/etc/php-fpm.d/yourdomain.conf | grep -E "pm\.|memory_limit"
# Expect: pm=ondemand, max_children 12, memory_limit 256M

# 5. MariaDB
mysql -e "SHOW VARIABLES LIKE 'innodb_buffer_pool_size'; SHOW VARIABLES LIKE 'max_connections'"
# Expect: 536870912 (512M), 100

# 6. Cron
crontab -l | grep artisan
# Expect: schedule:run * * * * * + queue:work --stop-when-empty * * * * *

# 7. Load test (from local or server)
ab -n 500 -c 50 https://yourdomain/
# Or k6: 50 VUs, p95 <800ms, error <1%, swap <20%
```

## Local XAMPP Verify (done 2026-09-02)

- `php artisan about --only=environment`: Laravel 12.68.0, PHP 8.5.0, Env production, Debug OFF, Timezone Asia/Dhaka — PASS
- `bootstrap/cache/config.php 90354 bytes`, `events.php 327`, `views 605` — PASS
- `route:cache` fails with `LogicException Unable to prepare route [admin/certificates/requests-columns] already assigned` → fallback `route:clear` — PASS (documented)
- `php -i OPcache`: CLI On, 128M, 10000 files, save_comments On — PASS (XAMPP php.ini commented, needs WHM enable)
- `DeploymentGitService.php:278` and `DeploymentZipService.php:178` fixed: clear-before-cache, no destructive cache:clear after, event:cache added, route fallback — PASS
- `scripts/optimize-cpanel.sh` created — cPanel Terminal entry point

## Rollback

**Immediate (Laravel only):**
```bash
# List backups
ls -lt .env.bak.*
# Restore
cp .env.bak.20260902_142148 .env   # adjust timestamp
php artisan optimize:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear
php artisan up
php artisan about --only=environment
```

**Full server rollback (WHM):**
- PHP-FPM: revert `/opt/alt/phpXX/etc/php-fpm.d/yourdomain.conf` from backup
- Apache: WHM > Apache Configuration > Global Configuration > revert MaxRequestWorkers 400, KeepAliveTimeout
- MariaDB: restore `/etc/my.cnf` backup, `systemctl restart mariadb`
- OPcache: `opcache.validate_timestamps=1`, `revalidate_freq=2`

**Deployment service rollback:**
```bash
git diff app/Services/DeploymentGitService.php app/Services/DeploymentZipService.php
git checkout HEAD -- app/Services/DeploymentGitService.php app/Services/DeploymentZipService.php
```

## Monitoring (cPanel > Resource Usage)
- Memory Used should stay <80% at 50 concurrent, Swap <20%
- If Swap >50%: reduce `pm.max_children` 12→10, reduce `innodb_buffer_pool_size` 512M→384M
- Check `~/logs/php-fpm-slow.log`, `~/logs/laravel-cron.log`, `~/logs/queue.log`, `failed_jobs` table
