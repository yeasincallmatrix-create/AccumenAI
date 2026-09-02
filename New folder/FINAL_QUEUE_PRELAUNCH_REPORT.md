# FINAL QUEUE PRE-LAUNCH CHECK — READ-ONLY

**Date:** 2026-08-26 04:26 UTC (fresh check)
**Laravel:** 12.66.0
**Environment:** `local` (`APP_ENV=local`, `PROJECT C:\xampp\htdocs\monetix`, `PHP C:\Users\Fast\.config\herd-lite\bin\php.exe 8.5.0`)
**Mode:** READ-ONLY — no queue:work, no jobs processed, no emails sent, no .env/db/config modified beyond prior `after_commit` fix. This is pre-launch gate.

**Prior State Confirmed:**
`QUEUE_CONNECTION=database`, `after_commit true` (was `false`), `config/queue.php.bak exists`, 5 `QueuedVerifyEmail` pending, `attempts 0 reserved_at null`, no worker, 0 failed, SMTP healthy, `schedule:run` + `notifications:retry everyFiveMinutes` present.

---

## 1. Configuration Verification (READ-ONLY)

```bash
php artisan config:show queue
# queue default database, connections.database.table jobs queue default retry_after 90 after_commit true
cat .env | grep QUEUE_CONNECTION  # QUEUE_CONNECTION=database
Test-Path bootstrap/cache/config.php  # False (no stale cache)
```

| Check | Value | File:Line | Result |
|-------|-------|-----------|--------|
| `queue.default` | `database` | `config/queue.php:16` | **PASS** |
| `database.after_commit` | `true` (was `false` 2026-08-26) | `config/queue.php:44` (changed, backup `config/queue.php.bak 4199 bytes` 2026-03-14 09:54) | **PASS** (fixed) |
| `database.retry_after` | `90` | `config/queue.php:43` | PASS |
| `database.queue` | `default` | `config/queue.php:42` | PASS |
| `queue.failed` | `database-uuids → failed_jobs` | `config/queue.php:123-127` | PASS |
| `.env QUEUE_CONNECTION` | `database` (masked secrets not shown) | `.env:38` | **PASS** (not sync) |
| `bootstrap/cache/config.php` | **NOT EXISTS** | filesystem | PASS (env live) |

No secrets exposed. `QUEUE_CONNECTION` still `database` per Phase 3 requirement.

---

## 2. Worker Command Verification (READ-ONLY)

```bash
php artisan queue:work --help
# Laravel 12.66.0 — options verified
```

| Flag | Available in 12.66.0? | Intended Value | Verified |
|------|------------------------|-----------------|----------|
| `queue:work` | YES | `database` (connection) or default | YES |
| `--stop-when-empty` | YES | YES | YES (`Stop when the queue is empty`) |
| `--queue` | YES | `default,notifications` | YES (`The names of the queues to work`) |
| `--tries` | YES | `3` | YES |
| `--timeout` | YES | `30` | YES |
| `--sleep` | YES | `3` | YES |
| `--max-time` | YES | `60` | YES |

**Intended production command (not executed):**

```bash
php artisan queue:work --stop-when-empty --queue=default,notifications --tries=3 --timeout=30 --sleep=3 --max-time=60
```

All flags exist. Command covers both `default` (verification) + `notifications` (OTP/business). Leaves `failed_jobs` observable (`--tries 3`).

**Not started** per §8.

---

## 3. Queue State (READ-ONLY)

```sql
SELECT * FROM jobs ORDER BY available_at;
SELECT * FROM failed_jobs;
```

| Metric | Value | Evidence |
|--------|-------|----------|
| **Pending `jobs`** | **5** | `DB::table('jobs')->count()` |
| **Failed `failed_jobs`** | **0** | `DB::table('failed_jobs')->count()` → `queue:failed` shows `No failed jobs found.` |
| **By queue** | `default:5`, `notifications:0` | `GROUP BY queue` |
| **Oldest** | `id=2` `2026-08-25 19:01:01` age **44651s (12:24:11)** | `jobs` |
| **Newest** | `id=6` `2026-08-25 19:17:04` age **43688s (12:08:08)** | `jobs` |
| **Attempts** | All `attempts=0`, `reserved_at=null` | Never fetched — proves **no worker** |
| **Next oldest:** | `id=3 19:01:43 44609s`, `id=4 19:09:10 44162s`, `id=5 19:10:51 44061s` | Consistent with forensic `5 pending 9h+` now `12h+` — grew 3h, same count → no new jobs leaked, none drained |
| **Notification logs** | Not re-queried but prior `queued 0 failed 0` — queue is verification, not business | — |
| **Expected** | `5 pending 0 failed` | **MATCHES** |

**If count differed:** Would STOP — but it matches forensic (5) plus age grew exactly wall-clock (≈3h between audits) → no deletion or injection.

---

## 4. Audit of the 5 Pending Emails (READ-ONLY, Tokens Redacted)

**Method:** `json_decode(payload) → unserialize(data.command) → SendQueuedNotifications.notifiables` (Collection) → `App\Models\User id` → `users.id` lookup for `getEmailForVerification()` (masked). Never prints token/password.

| Job | Queue | Created / Available | Age | Notifiable | Recipient (masked) | Exists Now | Job Class | Notification Class | Purpose | Token |
|-----|-------|---------------------|-----|------------|---------------------|------------|-----------|--------------------|---------|-------|
| 2 | `default` | `2026-08-25 19:01:01` | 12.40h | `App\Models\User#489` | `x***@kolsea.com` | **yes** (`users` row exists, `email_verified_at null`, `name Yasin Sheikh`, `created 19:01:01`) | `SendQueuedNotifications` | `QueuedVerifyEmail` | Email verification link | **PRESENT — REDACTED** |
| 3 | `default` | `2026-08-25 19:01:43` | 12.39h | `App\Models\User#489` | `x***@kolsea.com` | yes | `SendQueuedNotifications` | `QueuedVerifyEmail` | Email verification link | **PRESENT — REDACTED** |
| 4 | `default` | `2026-08-25 19:09:10` | 12.27h | `App\Models\User#490` | `y***@gmail.com` | yes | `SendQueuedNotifications` | `QueuedVerifyEmail` | Email verification link | **PRESENT — REDACTED** |
| 5 | `default` | `2026-08-25 19:10:51` | 12.24h | `App\Models\User#490` | `y***@gmail.com` | yes | `SendQueuedNotifications` | `QueuedVerifyEmail` | Email verification link | **PRESENT — REDACTED** |
| 6 | `default` | `2026-08-25 19:17:04` | 12.14h | `App\Models\User#490` | `y***@gmail.com` | yes | `SendQueuedNotifications` | `QueuedVerifyEmail` | Email verification link | **PRESENT — REDACTED** |

**Details per gate requirement:**

- **Job class:** `Illuminate\Notifications\SendQueuedNotifications` wrapping `App\Notifications\QueuedVerifyEmail` (verified via `displayName` + `commandName` decode).
- **Mail class:** Not `EmailOtpMail` — these are **verification emails**, not OTP. `EmailOtpMail` / `SendNotificationJob` would be on `notifications` queue — this queue is `default` only for `QueuedVerifyEmail`.
- **Recipient email:** Extracted via `users.id` → `getEmailForVerification()` (the address verification will be sent to). Masked `x***@kolsea.com` (User 489) appeared twice, `y***@gmail.com` (User 490) three times. Likely two test users created `2026-08-25` within minutes of jobs.
- **Verification token/hash:** Present in notification (signed URL generated at send) — **REDACTED** per rule.
- **Expiration:** Not in payload (generated at process time, see §5).
- **Sensitive tokens:** Never printed.

**Are they still valid recipients?** Both users `email_verified_at NULL` → `NOT VERIFIED` → still need verification. User rows not deleted (unlike forensic-deleted `yasin.callmatrix@gmail.com` user 488). So emails would be deliverable if processed.

---

## 5. Old-Job Safety Determination

**Question:** If these 12h-old jobs are processed now, will verification links be expired or stale?

**Source investigation (READ-ONLY):**

- `app/Notifications/QueuedVerifyEmail.php:1-38` extends `Illuminate\Auth\Notifications\VerifyEmail` and **does not override** `verificationUrl()`. Calls parent implementation.
- `vendor/laravel/framework/src/Illuminate/Auth/Notifications/VerifyEmail.php:80-91`:

```php
return URL::temporarySignedRoute(
    'verification.verify',
    Carbon::now()->addMinutes(Config::get('auth.verification.expire', 60)),
    ['id' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())]
);
```

- `=>` Signed URL is **generated when `toMail()` runs, i.e., when worker processes the job** (`SendQueuedNotifications → toMail → verificationUrl`). **Not generated at queue time.** `Config::get('auth.verification.expire', 60)` defaults to **60 minutes** (`config/auth.php` has no `verification.expire` key — falls back to 60).
- The `hash` is `sha1(email)` — recomputed at process time from current `users.email` (if email changed, old hash invalid — but both users still same email).
- Laravel's `VerifyEmail` does not store token in DB — signature + expiry embedded in URL and verified via `signed` middleware `routes/auth.php:89` `throttle:6,1`.

**Therefore:**

- **Generated at queued vs processed?** **Processed.** So age at queue time does **not** affect expiry. Fresh URL will be `Carbon::now() +60m` when worker runs.
- **Expiration handling?** `URL::temporarySignedRoute` checks `expires` query param on verification request — `60m` from now will be valid. No DB token to invalidate old.
- **Invalidation?** No stored token to compare — only signature. If job were 12h old but user verified in interim, controller `EmailVerificationNotificationController:15 hasVerifiedEmail()` would redirect — but these users are still `NOT VERIFIED`.
- **Safe to process?** **YES** — will produce fresh, valid 60-minute links. Duplicate jobs for same user (2×489, 3×490) will produce 2-3 identical emails with same link (harmless, idempotent — verification is `GET ... {id}/{hash}` signed).

**If source could not determine:** Would be `NOT DETERMINABLE` — but source **does** determine as above.

**Withholding `NOT DETERMINABLE` flag:** Not needed. Evidence proves `SAFE TO PROCESS` (with fresh expiry).

**IP note:** No passwords/SMTP/API secrets in payload — only model identifiers.

---

## 6. Mail Configuration (READ-ONLY, Credentials Masked)

| Check | Value | Source |
|-------|-------|--------|
| `mail.default` | `smtp` | `config/mail.php:17` / `.env:50` |
| `mail.mailers.smtp.host` | `smtp.gmail.com` | `config/mail.php:44` / `.env:52` |
| `mail.mailers.smtp.port` | `587` | `config/mail.php:45` / `.env:53` |
| `mail.mailers.smtp.encryption` | `tls` | `.env:56` / `ResolveMailer::normalizeEncryption` |
| `mail.from` | `yeasin.callmatrix@gmail.com / MAWA Academy` | `.env:57-58` / `config/mail:114` |
| `ResolveMailer → configured SMTP → MailChannel → SMTP transport` | `ResolveMailer::resolve(null)` → `NULL → env fallback smtp.gmail.com` (no `InstituteSetting smtp_host` in 158 rows), `MailChannel::send():29 registerMailer` → runtime `notification_smtp {timeout 30}` | `ResolveMailer.php:23-57`, `MailChannel.php:29-62` |
| `MAIL_USERNAME` | **CONFIGURED** `y***@gmail.com` | `.env:54` masked |
| `MAIL_PASSWORD` | **CONFIGURED** (both `env MAIL_PASSWORD` and `Setting smtp.password`) | masked, not printed |
| **SMTP credentials overall** | **CONFIGURED** | Both env + Setting present |

No credentials printed. Mail channel path intact.

---

## 7. SMTP Connectivity — No Email (READ-ONLY TCP/DNS)

```php
gethostbyname('smtp.gmail.com') → 192.178.158.108  // RESOLVES
fsockopen('smtp.gmail.com', 587, 3s) → SUCCESS 113ms
```

- Did **not** authenticate, did not send `EHLO`/`MAIL FROM`/`RCPT TO` requiring credentials.
- Pure DNS + TCP check — safe on shared hosting (cPanel firewall typically allows outbound 587).
- SMTP server reachable — not the bottleneck.

---

## 8. cPanel Cron Requirements

**Project path verified (local):** `C:\xampp\htdocs\monetix` (`__DIR__`), `artisan` exists, `PHP_BINARY C:\Users\Fast\.config\herd-lite\bin\php.exe 8.5.0`. `which php` in PowerShell returns same herd binary.

**Production host path:** **NOT inventable** from Windows XAMPP. Shared host will be `/home/<USER>/monetix` where `<USER>` is hosting account username (discover via `pwd` + `ls` on host or cPanel File Manager). **Do not hardcode `USER`.** Owner must replace.

**PHP binary verified (local):** `php` works (`php -v` 8.5.0). Shared host: usually `php` or `/opt/alt/php81/usr/bin/php` / `ea-php81` — verify via `which php` / cPanel `Select PHP Version`.

**Exact cron commands (owner to enter in cPanel → Cron Jobs → Every Minute `* * * * *`):**

```bash
# 1) Laravel scheduler — MUST be every minute for everyFiveMinutes to work
cd /home/USER/monetix && php artisan schedule:run >> /dev/null 2>&1

# 2) Queue worker — short-lived, drains both queues
cd /home/USER/monetix && php artisan queue:work --stop-when-empty --queue=default,notifications --tries=3 --timeout=30 --sleep=3 --max-time=60 >> /dev/null 2>&1
```

Replace `USER` + `php` path after host discovery. Use `cd PROJECT &&` so `.env`/`storage` resolved.

**Do not create** until owner approves.

---

## 9. Cron Architecture Verification

- **Scheduler:** `* * * * * schedule:run` required — `bootstrap/app.php:88-90` defines `notifications:retry everyFiveMinutes` (confirmed `schedule:list` shows `*/5 notifications:retry`). Without every-minute cron, it never fires (not via `routes/console.php`).
- **Queue worker:** `* * * * * queue:work --stop-when-empty ...` as above. Not a scheduled command in `bootstrap/app.php` — intentionally explicit cron for `database` driver short-lived pattern.
- **Duplication?** No duplicate scheduler for `notifications:retry`. Two crons are complementary: scheduler vs worker. Not overlapping responsibilities. `withoutOverlapping()` only for scheduled commands, not needed for raw `queue:work` every minute (it exits).

---

## 10. Worker Overlap Risk

| Parameter | Value | File:Line |
|-----------|-------|-----------|
| `retry_after` | `90` | `config/queue.php:43` |
| `job timeout` (SendNotificationJob) | `60` | `SendNotificationJob.php:29` |
| `mail timeout` (MailChannel) | `30` | `MailChannel.php:62` |
| `worker timeout` | `30` | Intended `--timeout=30` in cron |
| `worker max-time` | `60` | Intended `--max-time=60` |
| `cron interval` | `60s` (`* * * * *`) | Cron |

**Reasonable relationship?**

- `retry_after 90` > `worker timeout 30` and > `job timeout 60` → **safe** — worker killed after 30s/60s well before 90s resubmit. No premature duplicate. `FxRevaluationJob timeout 300 >90` still risky for non-mail, but not in queue scope.
- `max-time 60` == `cron 60s` → **sufficient but tight**. Worker runs ≤60s then cron launches new one. If previous still in final 30s SMTP, overlap ≤30s where two workers poll `jobs` — database `reserved_at` locking ensures only one claims job (second gets empty queue and exits). **Not harmful**, but `most` worst is double SMTP only if job took >90s and `retry_after` released it — not here (`30<90`).
- **Overlap flag:** **PASS** — configuration is reasonable for shared host. If host kills at 30s strictly, still safe because `timeout 30 < retry_after`.

No change needed.

---

## 11. Scheduler Verification

```bash
php artisan schedule:list
# */5 * * * * notifications:retry  Next Due: 6 seconds
```

- `bootstrap/app.php:88-90` `notifications:retry everyFiveMinutes withoutOverlapping` — **exists**, **verified everyFiveMinutes**, **not duplicated**.
- `routes/console.php` has no `notifications:retry` — correct single source in `bootstrap/app.php`.
- Do not execute or duplicate.

---

## 12. Tenant & Security Safety

| Area | Changed? | File:Line | After Worker |
|------|----------|-----------|--------------|
| `TenantContext`/`BranchContext` | **NOT changed** | `SendNotificationJob.php:35-55` restores per `NotificationLog.institute_id` | Reused |
| `ResolveMailer` per-institute | **NOT changed** | `ResolveMailer.php:32` institute first | Reused |
| `NotificationService` | **NOT changed** | `NotificationService.php:123-140` | Reused |
| `MailChannel` | **NOT changed** | `MailChannel.php:29-62` | Reused |
| SMTP encryption | **NOT changed** | `config/mail.php:49`, `ResolveMailer:60` | Reused |
| Isolation | **Preserved** | Only `config/queue.php:44` boolean flipped | No new queue engine |

**Only change:** `after_commit true` — actually *improves* tenant safety (job after commit).

---

## 13. Rollback Verification

- **Backup exists:** `config/queue.php.bak` **YES** `4199 bytes` (`Test-Path` → `True`).
- **Restore command:**

```bash
Copy-Item config/queue.php.bak config/queue.php -Force
php -l config/queue.php
php artisan config:show queue  # should show after_commit false again
```

- **Cron rollback:** Delete the two cPanel cron rows — no code revert.
- **Do not rollback now** per audit — verify availability only.

---

## 14. Final Gate Table

| Gate | Result | Evidence |
|------|--------|----------|
| Database queue active | **PASS** | `queue.default database` `QUEUE_CONNECTION=database` `.env:38`, `jobs` table exists |
| `after_commit=true` verified | **PASS** | `config:show queue` `after_commit true`, `config/queue.php:44` edited from `false` |
| Worker command verified | **PASS** | `queue:work --help` shows all flags, Laravel 12.66.0 |
| Worker NOT currently running | **PASS** | `tasklist php.exe` none, `jobs attempts 0 reserved_at null` |
| Pending jobs untouched | **PASS** | `jobs 5 pending` same as pre-remediation, no DELETE executed |
| Pending email recipients audited | **PASS** | 5× `QueuedVerifyEmail` to `User 489 x***@kolsea.com ×2` / `490 y***@gmail.com ×3`, masked, no tokens printed |
| Old-job safety determined | **PASS** | `VerifyEmail::verificationUrl` generates URL **at process time** `now()+60m` (`VerifyEmail.php` via `URL::temporarySignedRoute`) — **SAFE TO PROCESS** (fresh links) |
| SMTP configured | **PASS** | `smtp.gmail.com:587 tls` + `MAIL_USERNAME/PASSWORD CONFIGURED` + `ResolveMailer → env fallback` |
| SMTP connectivity | **PASS** | `DNS 192.178.158.108` `TCP 113ms SUCCESS` |
| cPanel project path verified | **PASS (local) / NOT INVENTED (production)** | Local `C:\xampp\htdocs\monetix`, production requires `pwd` discovery |
| PHP CLI verified | **PASS (local)** | `PHP_BINARY ...herd-lite\bin\php.exe 8.5.0` `php -v` 12.66.0 |
| Cron overlap risk acceptable | **PASS** | `retry_after 90 > timeout 30` and `max-time 60 == cron 60s` → safe, database lock prevents duplicate |
| Scheduler verified | **PASS** | `notifications:retry everyFiveMinutes` present in `bootstrap/app.php:88-90` + `schedule:list` |
| Tenant isolation | **PASS** | `TenantContext`, `ResolveMailer` unchanged, only `after_commit` |
| Rollback available | **PASS** | `config/queue.php.bak` exists |
| No production email sent | **PASS** | No `queue:work` executed, `failed_jobs 0`, log shows no new `sent` beyond queue |

---

## 15. Final Decision

### READY FOR OWNER APPROVAL

**With one note on old jobs:** They are **safe to process** but will generate **5 verification emails** (2 to `x***@kolsea.com`, 3 to `y***@gmail.com`) with **duplicate** for same recipients (2+3 dupes) — harmless but inbox burst. All links will be fresh 60-minute signed URLs.

No blocker. One config improvement already applied (`after_commit true`), verified.

---

## 16. OWNER APPROVAL GATE

> **The system is ready. No worker has been started. No pending job has been processed. No email has been sent. Owner approval is required before the first `queue:work` execution.**

**EXACT OWNER ACTION — cPanel Cron Configuration (Manual, After Host Path Discovery):**

**Step 0 — Discover host values (SSH/cPanel Terminal):**

```bash
pwd
# → /home/XXXX/monetix  (note the XXXX)
which php
# → /opt/alt/php81/usr/bin/php  or  php  (use what returns)
php -v
# → PHP 8.2+ required (8.5.0 local)
ls artisan
# → artisan must exist
```

**Step 1 — cPanel → Cron Jobs → Add New → Common Settings: Every Minute (`* * * * *`):**

```bash
cd /home/XXXX/monetix && php artisan schedule:run >> /dev/null 2>&1
```

**Step 2 — Add Second Cron → Every Minute:**

```bash
cd /home/XXXX/monetix && php artisan queue:work --stop-when-empty --queue=default,notifications --tries=3 --timeout=30 --sleep=3 --max-time=60 >> /dev/null 2>&1
# If php not found, replace php with full path from Step 0, e.g.:
# cd /home/XXXX/monetix && /opt/alt/php81/usr/bin/php artisan queue:work --stop-when-empty --queue=default,notifications --tries=3 --timeout=30 --sleep=3 --max-time=60 >> /dev/null 2>&1
```

**Step 3 — Do NOT create until you explicitly approve processing the 5 pending emails.** First cron tick will immediately send those 5 verification emails. After approval, verify:

```bash
php artisan queue:monitor  # or read-only: SELECT count(*) FROM jobs;  → expect 0
php artisan queue:failed   # or SELECT count(*) FROM failed_jobs;
tail -f storage/logs/laravel.log | grep email_otp_queued  # should appear then drain
```

**Fallback:** If host forbids `Every Minute`, set to `Every 5 Minutes` (`*/5 * * * *`) — wait becomes `<5m` instead of `<1m`, still far better than `12h`.

**Do NOT execute** until you reply `approve worker`. This report execution stops here.

---

*Evidence: `config:show queue after_commit true`, `.env:38 database`, `queue:work --help` all flags, `jobs 5` `failed 0` `attempts 0`, payloads `QueuedVerifyEmail` to `User 489/490` masked `x***@kolsea.com`/`y***@gmail.com` (2+3 dupes, `NOT VERIFIED`), `VerifyEmail.php` `temporarySignedRoute now()+60m` proves `SAFE TO PROCESS`, `ResolveMailer` env fallback `smtp.gmail.com:587 tls`, `DNS 192.178.158.108 TCP 113ms`, `bootstrap/app.php:88 notifications:retry everyFiveMinutes`, `config/queue.php.bak` rollback exists.*

