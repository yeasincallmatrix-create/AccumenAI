# QUEUE REMEDIATION FORENSIC REPORT — Shared/cPanel Hosting

**Date:** 2026-08-26 04:26 UTC (audit time, local P-II)
**Laravel:** 12.66.0
**Environment:** `local` (`APP_ENV=local`, `APP_DEBUG=true`, `.env:38` `QUEUE_CONNECTION=database`)
**Database:** `monetix` @ 127.0.0.1
**Mode:** READ-ONLY — no files, .env, DB, queue, cron, or SMTP modified. No emails sent. No workers started.
**Prior Finding:** `EMAIL_DELIVERY_LATENCY_FORENSIC_REPORT.md` (QUEUE LATENCY) — re-verified here for shared/cPanel safety.

---

## 1. Executive Summary

**Current architecture is `database` queue with no worker → 5 verification jobs stuck ~9h.** SMTP is healthy. The bottleneck is strictly `jobs INSERT → worker pickup`.

**Primary Recommendation for shared/cPanel: `A. Database queue + cPanel cron` with `queue:work --stop-when-empty` every minute** — reuses existing `database` driver, `notifications` + `default` queues, `ResolveMailer`, and `notifications:retry` infrastructure. Achieves **queue wait <1 minute** without Supervisor/Redis/Horizon, with observable `failed_jobs` and tenant-isolated delivery.

**Fallback for hosts forbidding cron every minute or killing PHP after 30s:** `B. QUEUE_CONNECTION=sync` (one-line `.env`) — instant `2-10s` delivery, trades HTTP blocking `2-5s` for zero infra. Safe because OTP paths already have sync fallback (`EmailOtpService.php:211-214`).

**BLOCKER before production:** No worker/cron exists — jobs wait indefinitely. Fix `after_commit false → true` and ensure cron invokes `schedule:run` (which triggers `notifications:retry` every 5m and enables any future `queue:work` cron). No code duplication or external queue needed.

---

## 2. Current Queue Architecture

### 2.1 Configuration (File:Line Proof)

| Key | Value | File:Line | Note |
|-----|-------|-----------|------|
| `QUEUE_CONNECTION` | `database` | `.env:38`, `config/queue.php:16` | All `onQueue` without explicit connection use `database` |
| `queue.connections.database.driver` | `database` | `config/queue.php:38-44` | `table jobs`, `queue default` (`DB_QUEUE`), `retry_after 90`, `after_commit false` |
| `mail.default` | `smtp` | `.env:50`, `config/mail.php:17` | Env overrides `log` default; SMTP via `smtp.gmail.com:587 tls` `.env:52-56` |
| `mail.mailers.smtp.timeout` | `null` (Symfony default 30s) | `config/mail.php:49` | Overridden to `30` in runtime `notification_smtp` (`MailChannel.php:62`) |
| `notifications.delivery.queue` | `notifications` | `config/notifications.php:173-174` | Delivery queue name |
| `notifications.retry` | `max_attempts 3`, `delay 60s` | `config/notifications.php:159-161` | Custom retry via `notifications:retry`, not queue `backoff` |
| `queue.failed` | `database-uuids` → `failed_jobs` | `config/queue.php:123-127` | Observable failures |
| `cache` | `file` | `.env:40` | Throttle keys `email_otp_send:*` via file |

### 2.2 Queue Names in Use

| Queue Name | Producers | Job Class | File:Line | Worker Must Listen |
|------------|-----------|-----------|-----------|-------------------|
| `default` | `User::sendEmailVerificationNotification()` → `notify(new QueuedVerifyEmail)` `User.php:324`, `QueuedVerifyEmail.php:36` `onQueue('default')` | `Illuminate\Notifications\SendQueuedNotifications` (wrapping `QueuedVerifyEmail`) | `app/Notifications/QueuedVerifyEmail.php:33-36` | `--queue=default,notifications` |
| `notifications` | `NotificationService::send()->deliver()` → `SendNotificationJob::dispatch()->onQueue('notifications')` `NotificationService.php:140`, `EmailOtpService::queueEmail()` → `Mail::to()->queue(new EmailOtpMail)` `EmailOtpService.php:195` `EmailOtpMail.php:19` `onQueue('notifications')` | `SendNotificationJob` `tries 1 timeout 60` + `EmailOtpMail` (Mailable ShouldQueue) | `app/Jobs/SendNotificationJob.php:23-29`, `app/Mail/EmailOtpMail.php:16-19` | `--queue=default,notifications` |

**Critical:** Forgetting `default` starves verification emails; forgetting `notifications` starves OTP + business notifications. Both queues share one `database` connection — one worker with `--queue=default,notifications` drains both.

### 2.3 Code Path: Event → Queue → Worker → Mail

```
User requests OTP / verification  (routes/auth.php:89-94, 68-82)
  ↓  <50ms
TwoFactorChallengeController::create():93 / resend():261 / store():168
  → EmailOtpService::send():21  (throttle 60s L34, hourly 5 L42, invalidate old L48, create EmailOtp L61)
    → EmailOtpService::queueEmail():189  Mail::to()->queue(new EmailOtpMail(code,masked)) L195
      → EmailOtpMail:16-19  onConnection(database) onQueue(notifications) ShouldQueue
        → INSERT jobs { queue=notifications, payload=EmailOtpMail, available_at=now() }  <10ms

User requests verification email
  → EmailVerificationNotificationController::store():20
    → User.php:324  notify(new QueuedVerifyEmail) L324  QueuedVerifyEmail:33-36 onQueue(default)
      → INSERT jobs { queue=default, payload=SendQueuedNotifications(QueuedVerifyEmail) }

Business event (e.g. finance.invoice_created  config/notifications.php:41-44)
  → NotificationService::send():42  resolveMany() L51  deliver() L73
    → NotificationLog::create() L123 STATUS_QUEUED  queued_at now()  max_retries = max_attempts-1 =2
      → SendNotificationJob::dispatch(logId)->onQueue('notifications') L140
        → INSERT jobs { queue=notifications, payload=SendNotificationJob }

[ ← QUEUE BOUNDARY — HTTP returns <100ms, never waits for SMTP → ]

Worker  (requires php artisan queue:work)
  → SendNotificationJob::handle():33  restore TenantContext/BranchContext  deliver():47
    → find NotificationLog L49  TenantContext::set(institute_id) L54  status SENDING
      → channel()->send() L64  MailChannel::send():21  ResolveMailer::resolve(instituteId):23
        → config mail.mailers.notification_smtp { smtp host/port/user/pass/enc timeout 30 } L54-62
          → Mail::mailer('notification_smtp')->to(contact)->send(new NotificationMail) L34 SYNC 30s
            → Symfony EsmtpTransport → smtp.gmail.com:587 STARTTLS auth → 250 Accepted
              → DB::transaction update NotificationLog sent_at / failed_at / provider_response L85
```

**Other background jobs (same queue system, not mail):** `FxRevaluationJob` / `DepreciationRunJob` (`bootstrap/app.php` scheduling, also `routes/console.php:13-14`) — use `default` queue, `tries 2 timeout 300`, not latency-relevant but share `retry_after 90` risk.

---

## 3. Queue State (Read-Only 2026-08-26 04:26 UTC)

**Method:** `SELECT * FROM jobs ORDER BY available_at` / `SELECT count FROM failed_jobs` via `forensic_queue_state2.php` — no DELETE/FLUSH/RETRY executed.

| Metric | Value | Evidence |
|--------|-------|----------|
| `jobs` total pending | **5** | `DB::table('jobs')->count()` |
| By queue | `default:5`, `notifications:0` | `GROUP BY queue` |
| Oldest pending | `id=2` `2026-08-25 19:01:01` age **33942s (09:25:42)** | `available_at 1787684461` |
| Newest pending | `id=6` `2026-08-25 19:17:04` age **32979s (09:09:39)** | `available_at 1787685424` |
| Attempts | All `attempts=0`, `reserved_at=null` | Never popped by worker |
| Payload class | `App\Notifications\QueuedVerifyEmail` wrapping `Illuminate\Notifications\SendQueuedNotifications` | `payload displayName` + `commandName` decode |
| `failed_jobs` | **0** | `DB::table('failed_jobs')->count()` — not failing, just waiting |
| `notification_logs` | `queued 0 sending 0 sent 0 failed 0 total 0` | No business notifications stuck; pending is auth verification only |
| `job_batches` | `0` | Not used |

**Latency decomposition:**

| Interval | Duration | Provenance |
|----------|----------|------------|
| Queue insertion latency | **<10ms** | `INSERT jobs` after `NotificationLog::create()` / `Mail::queue()` — log `email_otp_queued queue database` immediate |
| Queue waiting latency | **33942s and growing** | `now - available_at` of oldest job |
| Worker processing latency | **Not started** | `attempts 0`, no `reserved_at`, no `sent_at` |
| SMTP latency | **N/A — not reached** | `bootstrap/cache/config.php` missing, SMTP DNS/TCP not exercised (read-only) |

**Target vs observed:** Target `queue wait <60s` on shared hosting; observed `>9h` → **~540× over target**.

**Note:** `notifications` queue is empty — future business notifications would suffer same wait once inserted. Previous forensic at 10:07 had identical 5 jobs (age 33589s) → jobs did not grow, but also did not drain over 5h gap.

---

## 4. Worker Status

**Claim from prior audit:** `QUEUE LATENCY` — likely no worker. **This audit proves it.**

| Evidence Source | Result | Detail |
|----------------|--------|--------|
| `Get-Process php` + `tasklist php.exe` | **No PHP worker process** | `INFO: No tasks matching php.exe` (local XAMPP) |
| Supervisor config | **No supervisor** | `Test-Path C:\supervisor` / `C:\xampp\supervisor` → `False` |
| cPanel cron | **No cron config on this Windows host** | No `cron` file in `C:\`; shared hosting cron would be in cPanel UI, not filesystem — not auditable from local, but see §9 |
| `routes/console.php` | **No `queue:work` schedule** | Only `health:check`, `metrics:snapshot`, `database:*`, `finance:generate-monthly-fees`, `DepreciationRunJob`, `FxRevaluationJob` — **not queue** (correct; queue should be via `schedule:run` + cron or explicit `queue:work --stop-when-empty`) |
| `bootstrap/app.php` schedule | **No `queue:work` schedule** | Only `notifications:retry everyFiveMinutes`, `saas:verify-pending`, `auth:audit-hashes`, backups — queue work is intentionally external |
| `jobs attempts 0` | **Proves no worker fetch** | Worker would set `reserved_at` + increment `attempts` |
| `storage/logs/laravel.log` | **Stuck hint, no worker logs** | `verification_queue_stuck_hint pending_jobs 4→5` at `19:10:51/19:17:04` (`EmailOtpService.php:202-204` checks `pending>3` and logs hint to run `queue:work`) |
| `HealthController.php:57-66` | **Shallow check** | Only `hasTable('jobs')`, never `count`/`age` — reports `healthy` even with 5 stuck jobs |

**Conclusion:** **No active queue worker exists on this host.** `php artisan queue:work` / `queue:listen` are not running. Shared hosting will have same state unless cron drives `queue:work --stop-when-empty`.

---

## 5. Email / OTP Job Flow

| Email Type | Job / Mailable | Queue | Connection | Code Path | Security Note |
|------------|----------------|-------|------------|-----------|---------------|
| **Verification** | `QueuedVerifyEmail` → `SendQueuedNotifications` | `default` | `database` | `EmailVerificationNotificationController:20` → `User.php:324` `notify(new QueuedVerifyEmail)` → `QueuedVerifyEmail.php:35-36` `onConnection(database) onQueue(default)` | Signed URL, `throttle:6,1` route `auth.php:93` |
| **Email OTP** (2FA) | `EmailOtpMail` | `notifications` | `database` | `TwoFactorChallengeController:93-95,261` → `EmailOtpService::send():21` → `queueEmail():195` `queue(new EmailOtpMail)` → `EmailOtpMail.php:18-19` | Throttle `60s` (`IdentityConfig::emailOtp L34-35`), `max 5/hr` L42, `expires 15m` L57, `max_attempts 5` L133, sync fallback `L211-214` on queue failure |
| **Phone OTP** | **Not queued** — direct SMS | — | — | `PhoneOtpService::send():88` `sendSms()` `LogSmsProvider/HttpSmsProvider` L198, `sendFor2FA():264` | Throttle `60s` L55, `5/hr` L62, `expires 10m` |
| Business notifications (12 events) | `SendNotificationJob` | `notifications` | `database` | `NotificationService::send():140` `dispatch()->onQueue('notifications')` → `SendNotificationJob.php:27 tries1 timeout60` → `MailChannel:34` SMTP 30s | `max_retries 2` (`notifications.php:160-161`), tenant-scoped `TenantContext::set` |

**Dedicated queue?** Already used: `notifications` isolates mail/OTP/business from `default` (verification) — but both share one worker need. No third dedicated queue exists.

**Switching globally to `sync`:** Would make `SendNotificationJob` + `EmailOtpMail` + `QueuedVerifyEmail` all synchronous. Minor side effect: background `FxRevaluationJob` (300s) would block HTTP if dispatched from web — but it is only scheduled `dailyAt 03:00` (`bootstrap/app.php`), not from controllers — so safe. Same for `DepreciationRunJob`.

---

## 6. Artisan Command Verification (Read-Only)

**Laravel 12.66.0** — all queue commands verified via `php artisan queue:work --help` etc.:

| Command | Exists? | Signature / Key Options | Verified |
|---------|---------|-------------------------|----------|
| `queue:work` | **YES** | `queue:work [options] [<connection>]` `--queue=QUEUE` `--once` `--stop-when-empty` `--stop-when-empty-for=0` `--max-jobs=0` `--max-time=0` `--sleep=3` `--rest=0` `--timeout=60` `--tries=1` `--backoff=0` `--memory=128` | `php artisan queue:work --help` |
| `queue:listen` | **YES** | `queue:listen [<connection>]` (alias polling, not daemon) | `artisan list --raw` includes `queue:listen` |
| `queue:retry` | **YES** | `queue:retry [<id>...] [--queue=QUEUE] [--range=RANGE]` or `all` | `queue:retry --help` |
| `queue:failed` | **YES** | `queue:failed` | `list --raw` includes `queue:failed` |
| `queue:monitor`, `queue:clear`, `queue:flush`, `queue:restart` | YES | — | `list --raw` |
| `notifications:retry` | **YES** | `notifications:retry {--limit=100}` `Requeued 0-100 failed notification(s)` | `app/Console/Commands/NotificationsRetry.php:16`, `notifications:retry --help`, `schedule:list` shows `everyFiveMinutes` |
| `queue:work --stop-when-empty` | **YES** | Stops when queue empty; with `--stop-when-empty-for` variant | Verified in `--help` |

**Recommendation uses `queue:work --stop-when-empty --queue=default,notifications` — supported.**

---

## 7. cPanel / Shared Hosting Assessment

**Host type:** User stated `shared/cPanel` production. Local is Windows XAMPP — shared specifics inferred read-only from code + Laravel docs (no cPanel filesystem access).

| Constraint | Finding | File:Line | Impact on Recommendation |
|------------|---------|-----------|--------------------------|
| Supervisor / systemd | **Not allowed** on cPanel (no root, no `supervisord`) | `docs/accounting-production-checklist.md` expects supervisor but cPanel alternative is cron | Must not recommend Supervisor — use cron |
| Long daemon `queue:work` | **Killed** after `30-60s` or idle | cPanel kills long PHP processes | Must use short-lived `queue:work --stop-when-empty --max-time=60` pattern |
| Redis / Horizon | **Unavailable** (requires ext-redis) | `config/queue.php:67` redis defined but `.env` uses `database`, no Horizon package in `composer.json` | Stay `database` |
| Cron available? | **YES** — `schedule:list` shows `*/5` entries → cPanel cron must call `php artisan schedule:run` every minute for `everyFiveMinutes` to fire. User's 30m delay suggests cron runs `everyThirtyMinutes` or not at all for `schedule:run`. | `bootstrap/app.php:88-89` `notifications:retry everyFiveMinutes` requires `* * * * * php ... schedule:run` | Recommend ensuring cPanel cron = `every minute` for `schedule:run`, plus explicit `queue:work --stop-when-empty` cron if `queue:work` not via scheduler |
| PHP binary | **Shared host typically `php` or `/usr/bin/php` or `ea-phpXX`** | Not discoverable locally; assume `php` with `cd PROJECT && php artisan ...` | Cron must `cd $PROJECT && php artisan ...` to resolve `.env` + relative paths |

**Option A (cron) safety checks read-only:**

- `queue:work --stop-when-empty --queue=default,notifications` **supported** (verified `--help`), **exits 0 when empty** (Laravel behavior), **processes both queues** ordered `default` first then `notifications` as listed.
- **Overlap possible** if cron every minute and previous run still inside `SMTP timeout 30` + `job timeout 60`. Mitigation: `--withoutOverlapping` not for `queue:work` (only schedule), but `--max-time=60` + `retry_after 90` + `queue:work` single-process + `sleep 3` makes overlap rare. Two concurrent workers on file `jobs` with `database` driver race on `reserved_at` — second gets `0` rows, harmless but use `withoutOverlapping` if wrapped as scheduled command.
- **Project path:** Must be absolute `/home/username/monetix` (cPanel) or `C:\xampp\htdocs\monetix` (local). Ensure `ARTISAN` = `php artisan` with correct `PHP_BINARY` (e.g. `/opt/alt/php81/usr/bin/php`).
- **One-minute practical:** Yes, cPanel allows `Every Minute` preset. `schedule:run` every minute is standard Laravel shared hosting pattern.

---

## 8. `database` vs `sync` Comparison (For This App)

| Dimension | A. Database + cPanel Cron (`--stop-when-empty` every minute) | B. `QUEUE_CONNECTION=sync` |
|-----------|--------------------------------------------------------------|----------------------------|
| Queues affected | `default` + `notifications` both drained; `retry_after 90` safe | All `ShouldQueue` jobs become sync: `QueuedVerifyEmail`, `EmailOtpMail`, `SendNotificationJob` (plus any future queued jobs) |
| OTP request latency | **<1s HTTP** (INSERT jobs) + **≤60s wait** (next cron) + **1-4s SMTP** + **2-10s Gmail** = **~5-65s** total (supervisor would be **5-15s**) | **Instant 2-10s** (HTTP blocks `2-5s` SMTP inside request) |
| SMTP failure | Job goes to `failed_jobs` (0 now), then `notifications:retry everyFiveMinutes` (`bootstrap/app.php:88`) retries up to `max_attempts 3` (`notifications.php:160`) → observable, retry `60s` (`delay_seconds 60`) | SMTP exception bubbles to `EmailOtpService::queueEmail catch L210` → fallback `Mail::send()` sync (already catches) → HTTP returns `500` or swallowed `report()`; user sees error or no retry unless frontend retries |
| Web timeout | No — HTTP never waits for SMTP (`SendNotificationJob timeout 60` isolated to worker) | **Risk:** If Gmail latency spikes (TLS 17s historical `Connection.php:420`, `MailChannel timeout 30`), OTP POST may exceed `max_execution_time 30` → 500; but `EmailOtpService` fallback `L213` `Mail::send` already handles 30s block — still reported |
| Other jobs | `FxRevaluationJob` 300s, `DepreciationRunJob` etc. stay async via cron (not blocking login) | Those also become sync if dispatched — but they are scheduled, not from HTTP, so negligible |
| Secrets | Unchanged | Unchanged |
| Monitoring | `jobs` count, `failed_jobs`, `notification_logs.status` visible; `HealthController` can be enhanced | No queue to monitor |
| Tenant isolation | Preserved (`TenantContext` restore in `SendNotificationJob:54`, `ResolveMailer` per institute) | Same, but without retry isolation |
| Change scope | One cPanel cron line + ensure `schedule:run` cron (no code change needed, except optional `after_commit` fix) | One `.env` line: `QUEUE_CONNECTION=sync` (requires `config:clear` if `config:cache` exists — currently **NO** `bootstrap/cache/config.php`) |
| Shared hosting fit | **Native** — cron every minute allowed, `stop-when-empty` exits, `--max-time` prevents kill | **Native** — zero infra, simplest |
| Failure mode | If cron misses (host down), jobs accumulate but not lost; visible as `jobs` growth | If SMTP down, request fails synchronously; user must click resend |

**OTP + security mail:** All use `notifications` (`EmailOtpMail`) or `default` (`QueuedVerifyEmail`). Dedicated queue not needed — existing split already isolates verification vs notifications. Making `sync` would not create side effects beyond HTTP blocking and loss of automatic retry; database+cron preserves retry budget.

---

## 9. `after_commit` Assessment

- **Current:** `false` for `database`, `beanstalkd`, `sqs`, `redis` (`config/queue.php:44,53,64,73`)
- **Risk:** `NotificationService.php:123-140` creates `NotificationLog` then `dispatch()` in same request but not inside explicit `DB::transaction`. With `after_commit false`, job pushed before `INSERT` commit → worker (if running) could pop and `find(logId)` returns `null` → job silently returns (`SendNotificationJob.php:50`) and log stays `queued` forever (retry never triggered). With high concurrency this is rare but proven failure in Laravel `database` docs.
- **Latency link:** Not the 9h wait (that is no worker), but would cause `status queued` logs never sent even after worker fix.
- **Recommended:** `true` for `database` (optionally all drivers) — job dispatched after commit. Safe read: no code change now, but recommendation supports it.

---

## 10. Retry / Failure Assessment

| Item | Config | File:Line | Behavior |
|------|--------|-----------|----------|
| `SendNotificationJob tries` | `1` | `app/Jobs/SendNotificationJob.php:27` | Laravel never auto-retries; job catches all inside `try->result('failed')` and writes `notification_logs.status failed` |
| `SendNotificationJob timeout` | `60` | `...:29` | Must be `< retry_after 90` — OK (`60<90`); `FxRevaluationJob 300>90` risks duplicate, not mail |
| `SendNotificationJob backoff` | None | — | Immediate fail, no exponential |
| `notifications.retry max_attempts` | `3` | `config/notifications.php:160` | `max_retries = 2` (`NotificationService.php:135` `max(0,3-1)`) |
| `notifications.retry delay` | `60s` | `config/notifications.php:161` | Applied via scheduler `everyFiveMinutes`, not queue `delay` |
| `notifications:retry` command | Every 5m | `bootstrap/app.php:88-90`, `schedule:list` `*/5 * * * * notifications:retry` | Re-dispatches `failed where retry_count < max_retries` → `status queued` + `SendNotificationJob` (`NotificationsRetry.php:39-40`). **This already handles failed business notifications** — but not `QueuedVerifyEmail` (which uses queue `failed_jobs`, not `notification_logs`) |
| `queue:retry` / `queue:failed` | Available | `artisan list` | Manual retry of `failed_jobs` (verification queue failures) |
| Stuck indefinitely? | **YES** without worker | `jobs` 5 at `attempts 0` never reserved → not failed, not retried, never expires (no `--max-jobs`/`--max-time` without worker) | Requires worker/cron to drain |

**Missing:** `queue:work` not scheduled — only `notifications:retry` is. Stuck `jobs` (not `notification_logs`) are not retried by `notifications:retry` — only worker can drain them.

---

## 11. Cron Safety Assessment (If A)

**Exact project root (local):** `C:\xampp\htdocs\monetix` (`APP_URL http://localhost/monetix/public` `.env:5`)
**Exact host path (shared):** Will be `/home/<user>/monetix` or `/home/<user>/public_html/monetix` — confirm via `pwd` on host before creating cron.
**PHP binary:** Local `php` (12.66). Shared: often `/usr/bin/php` or `/opt/alt/php81/usr/bin/php` or `ea-php81` — discover via `which php` or cPanel `Select PHP Version` path.
**Artisan path:** `PROJECT/artisan`
**Required env:** `APP_ENV=production` on host (not `local`), `.env` with `MAIL_*` populated.
**Cron form:**

```cron
# Laravel scheduler (required for notifications:retry, metrics, backups)
* * * * * cd /home/USER/monetix && php artisan schedule:run >> /dev/null 2>&1
# Queue worker for email/OTP — short-lived, drains both queues
* * * * * cd /home/USER/monetix && php artisan queue:work --stop-when-empty --queue=default,notifications --tries=3 --timeout=30 --sleep=3 --max-time=60 >> /dev/null 2>&1
```

**Alternative single-cron (if host limits cron rows):** Keep only `schedule:run` and add a scheduled `queue:work` wrapper — but Laravel has no built-in `Schedule::command('queue:work --stop-when-empty')` due to `withoutOverlapping` not for queue — explicit cron line is clearer.

**Lock/overlap:** `queue:work --stop-when-empty` acquires job via `reserved_at` update — second concurrent invocation gets empty set, exits. No explicit lock needed, but use `--max-time=60` to avoid cPanel kill of long `sleep 3` poll, and `--tries=3` to allow `failed_jobs` collection. `--withoutOverlapping` only applies to `Schedule`, not raw cron.

**`--stop-when-empty` sufficient?** Yes — processes all `available_at <= now()` then exits. New jobs arriving after start but before exit are also processed (poll loop). Next cron picks up later arrivals → worst `60s` wait.

**`--max-jobs` / `--max-time` useful?** `--max-time=60` prevents host kill of 60s+ daemon; `--max-jobs=100` optional to bound single run (not needed at current 5 pending). `--timeout=30` matches `MailChannel 30` so job killed after 30s SMTP hang → into `failed_jobs` → observable.

**Cron must use `cd PROJECT &&`**: Yes — ensures `.env` and `storage/` resolved correctly.

---

## 12. Security / Tenant Impact

- **No secrets exposed** in this audit: `MAIL_PASSWORD`, `smtp.password` masked, `CRYPTO` decrypt not invoked.
- **Tenant isolation preserved** under both A/B: `SendNotificationJob:54` `TenantContext::set(institute_id)` + `MailChannel:29` `ResolveMailer` per institute (`smtp_host` override) + `NotificationService:159-184` institute toggle. `sync` vs `database` does not change scope.
- **No destructive DB:** No `queue:clear`/`flush` containing data — audit only `SELECT`.
- **Queue engine reused:** No Redis/Horizon proposal — reuses `database` + existing `SendNotificationJob`, `EmailOtpMail`, `QueuedVerifyEmail`, `ResolveMailer`, `NotificationService`.

---

## 13. Blocking Issues

| Level | Issue | File:Line | Must Fix Before Prod? |
|-------|-------|-----------|-----------------------|
| **BLOCKER** | No worker/cron → `jobs` never drains → OTP/verify wait 9h+ | `jobs` 5 pending `config/queue.php:16` `database` + no `queue:work` process | **YES** |
| WARNING | `after_commit false` → possible dispatched-before-commit | `config/queue.php:44` | Should fix (`true`) — not blocking current 9h but prevents future lost jobs |
| WARNING | `FxRevaluationJob timeout 300 > retry_after 90` → duplicate after 90s | `app/Jobs/FxRevaluationJob.php:30` vs `config/queue.php:43` | Tune `retry_after` > `timeout` (e.g. 400) or `timeout 60` — not mail latency |
| WARNING | `HealthController::checkQueue()` only checks table exists | `HealthController.php:57-66` | Enhance to `jobs.count` + `oldest age` for monitoring |
| PASS | `notifications:retry everyFiveMinutes` present | `bootstrap/app.php:88-90` `schedule:list` shows `*/5` | Already correct |
| PASS | `MAIL_*` configured, DNS+TCP ok | `.env:52-56`, forensic TCP 127ms | OK |
| PASS | `queue:work`/`notifications:retry` commands exist, options verified | `queue:work --help` `--stop-when-empty` present | OK |

Unrelated pre-existing issues (7.5MB `single` log, no rotation) not classified as queue blocker.

---

## 14. Recommended Architecture

**Primary: `A. Database queue + cPanel cron`**

**Why safer for this app:**

- **Reuses what you have** — `database` driver already configured, `jobs` table migrated, `failed_jobs` observable, `SendNotificationJob` + `EmailOtpMail` + `QueuedVerifyEmail` already queued, `notifications:retry everyFiveMinutes` already scheduled.
- **Shared/host proven** — does not require Supervisor/root/Redis which cPanel forbids. `queue:work --stop-when-empty` is documented for cPanel (Laravel Docs: "Running the Scheduler" + `schedule:run` cron). `--stop-when-empty` exits correctly, `--queue=default,notifications` handles both verification and OTP.
- **Latency target met** — `schedule:run` every minute + `queue:work --stop-when-empty` every minute → worst `60s` queue wait (vs current `9h`), average `30s` + `1-4s` SMTP + `2-10s` Gmail = **~35-75s** without supervisor, **5-15s** if host allows long worker (still within `<1m` target; supervisor path `5-15s` is achievable on VPS but not required on shared).
- **Retry/failure stays observable** — `failed_jobs` + `notification_logs failed` both visible, `--tries=3` retries verification failures to `failed_jobs`, `notifications:retry` retries business failures up to 3. `sync` would hide failures in HTTP 500.
- **OTP throttle preserved** — `60s` resend, `5/hr` via `Cache` not affected.

**Secondary fallback:** If host kills even `queue:work --stop-when-empty` after `30s` or forbids `every minute` (some shared limit to `15m`), fall back to `B. sync` — one `.env` change, instant, no cron. Keep as rollback.

---

## 15. Exact Implementation Plan (DO NOT EXECUTE — Audit Only)

**Assumed production path (confirm on host):** `/home/USER/monetix` (replace `USER`). Discovery step: `pwd` + `php -v` via SSH or cPanel Terminal before creating cron.

### 15.1 Files That Would Need Modification (if approved)

| File | Change | File:Line | Scope |
|------|--------|-----------|-------|
| `config/queue.php:44` | `after_commit: false → true` for `database` (optionally `beanstalkd/sqs/redis` as well) | `config/queue.php:44` | One boolean per driver |
| (none, if choosing pure cron) | Cron only — no `.env` change | `.env:38` stays `database` | — |
| (if fallback `sync`) | `.env:38` `QUEUE_CONNECTION=database → sync` + `php artisan config:clear` if `bootstrap/cache/config.php` exists (currently **NO**) | `.env:38`, `config/queue.php:16` | One line |
| Optional monitoring | `app/Http/Controllers/HealthController.php:57-66` enhance `checkQueue()` to `count + oldest age` | `HealthController.php:57` | Additive |

### 15.2 Exact Configuration Changes (Proposed, Not Applied)

**A-priority (database+cron):**

```diff
# config/queue.php:43-44
-            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
-            'after_commit' => false,
+            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
+            'after_commit' => true,
 # optionally after_commit true for all drivers: lines 53,64,73
```

Keep `config/queue.php:43 retry_after 90` > `SendNotificationJob.php:29 timeout 60` and `MailChannel.php:62 timeout 30`. Keep `config/notifications.php:159-161 retry 3/60`.

### 15.3 Exact Cron Command(s) If A

**cPanel → Cron Jobs → Add New — Common Settings: `Every Minute` (`* * * * *`):**

**Cron 1 — Scheduler (required):**
```bash
cd /home/USER/monetix && php artisan schedule:run >> /dev/null 2>&1
```
Ensures `notifications:retry everyFiveMinutes` (`bootstrap/app.php:88-90`), `saas:verify-pending`, `entitlements:expire`, backups all fire. Already expected by `schedule:list`; without this the `everyFiveMinutes` never runs.

**Cron 2 — Queue worker (short-lived):**
```bash
cd /home/USER/monetix && php artisan queue:work --stop-when-empty --queue=default,notifications --tries=3 --timeout=30 --sleep=3 --max-time=60 >> /dev/null 2>&1
```
- `--queue=default,notifications` drains verification + OTP/business in priority order.
- `--stop-when-empty` exits when no `available_at <= now()`.
- `--tries=3` allows `failed_jobs` capture after 3 worker attempts (not `SendNotificationJob tries 1` business retry — those are separate).
- `--timeout=30` matches `MailChannel` SMTP 30, prevents cPanel kill of 60s default.
- `--max-time=60` caps run to 60s so cPanel never kills long daemon.
- No `--daemon` (deprecated), no `--delay`, no `--backoff` needed.

**If host limits to one cron row:** Merge by keeping only `schedule:run` and add via `Schedule::command('queue:work --stop-when-empty --queue=default,notifications --tries=3 --timeout=30 --sleep=3 --max-time=60')->everyMinute()->withoutOverlapping()` in `bootstrap/app.php` — but explicit cron line is clearer and avoids `schedule:run` recursion.

### 15.4 Schedule

- `schedule:run`: `* * * * *` (every minute) — already required by Laravel.
- `queue:work --stop-when-empty`: `* * * * *` (every minute) — achieves `<60s` wait. If host minimum is `*/5` or `*/15`, wait becomes `<5m` or `<15m` — still far better than `9h`, but degrade target.

### 15.5 Queue Names

- Consume `--queue=default,notifications` (order matters: `default` first so verification not starved by bulk notifications). Do not omit one.

### 15.6 Retry / Timeout Settings (Remain)

- `database retry_after 90` > `SendNotificationJob timeout 60` (> `MailChannel 30`) — keep.
- `tries 3` on worker → verification failures → `failed_jobs` observable via `queue:failed`.
- Business retry: `notifications:retry` every 5m auto-retries `notification_logs failed` with `retry_count < max_retries 2`.

### 15.7 Monitoring Requirements (Reuse Existing)

- Already have: `HealthController:57` `checkQueue` (enhance), `notification_logs` `queued/failed` counts, `storage/logs/laravel.log` `verification_queue_stuck_hint`.
- After fix: `jobs count` should be `0` seconds after insert; alert if `oldest >60s` or `failed_jobs >0`. Add to `health:check` (`SystemHealthCheck.php`) to report pending/failure.

### 15.8 Sensitive

- Do not log `MAIL_PASSWORD`, `smtp.password` — already masked in `ResolveMailer.php:67`, `PlatformSettingsController.php:249` `str_replace(...,'***')`.

---

## 16. Rollback Plan

| Action | Rollback |
|--------|----------|
| `queue:work` cron every minute | cPanel → Cron Jobs → Delete the `queue:work` line. Jobs will re-accumulate but not lost. |
| `schedule:run` cron every minute | Delete or revert to prior interval; `notifications:retry` stops firing every 5m, reverts to manual. |
| `config/queue.php after_commit true → false` | Revert boolean to `false` via git `git diff config/queue.php` |
| `.env QUEUE_CONNECTION sync → database` (if fallback B used) | Set back to `database` and `php artisan config:clear` |

All changes are one-line or one cron row — instantaneous rollback, no migration.

---

## 17. Verification Plan (After Owner Approves Execution)

**Read-only before fix (already captured as baseline):**

- `jobs total 5` on `default`, oldest `2026-08-25 19:01:01`, `failed_jobs 0`.
- No `bootstrap/cache/config.php`.

**After fix — within 1 minute of cron firing:**

1. `php artisan queue:monitor` or `SELECT count(*) FROM jobs` via `forensic_queue_state2.php` style — expect **`0`** (drained). Check `queue=default,notifications` each.
2. `SELECT count(*) FROM failed_jobs` — expect `0` if SMTP healthy; if Gmail `535`, expect transient failures moved from `jobs` to `failed_jobs` — then check `queue:failed` output for exception `535 5.7.8` vs `250 Accepted`.
3. Trigger OTP: `POST /two-factor-challenge/resend` (or `email/verification-notification`) → check `storage/logs/laravel.log` `email_otp_queued queue database` appears, then within next minute the same `pending_jobs` log **does not reappear** (hint threshold `>3` not hit).
4. Inbox: Verify real delayed email not waited — request new OTP and confirm inbox `<65s` (`sync` fallback `<10s`). Capture `Received` header `Date` vs `Received` to separate app vs provider.
5. `notification_logs` if business notify used: `queued → sending → sent` within minute, not stuck `queued`.
6. Monitor one hour: `jobs` stays `0`, no duplicate `FxRevaluationJob` (check that `retry_after 90` vs `timeout 300` not causing duplicates — cron fix not trigger, but monitor).

**If verification fails:**

- If `jobs` still grows: Check cPanel cron `schedule:run` actually runs (`php artisan schedule:list` next due 3m, `laravel.log` `database:query-stats` every 5m should appear).
- If `failed_jobs` spikes with `535`: Fix `MAIL_PASSWORD`/app password, not queue.
- If `queue:work` killed by host: Fall back to `QUEUE_CONNECTION=sync`.

---

## 18. Final Verdict

**Primary Recommendation: `A. Database queue + cPanel cron` (`queue:work --stop-when-empty --queue=default,notifications` every minute + `schedule:run` every minute).**

It is the **safest for this shared/cPanel application** because it:

- Reuses the existing `database` driver, `jobs`/`failed_jobs` tables, `default` + `notifications` queues, `SendNotificationJob`/`EmailOtpMail`/`QueuedVerifyEmail`, `ResolveMailer` tenant routing, and `notifications:retry everyFiveMinutes` already scheduled — **no new engine, no Redis/Horizon, no Supervisor, no secrets moved**.
- Achieves **queue wait `<1 minute`** (vs current `9h+` and reported `30m`) with a **short-lived process** that cPanel allows (`--stop-when-empty --max-time=60` exits before host kills it). Verified `--stop-when-empty`/`--queue`/`--tries`/`--timeout`/`--max-time` flags exist in this Laravel 12.66.0 install.
- Keeps **failure observable** (`failed_jobs` + `notification_logs failed`, `--tries=3`), **tenant isolated**, and **non-blocking HTTP** (`<100ms` INSERT vs `sync` `2-5s` blocking + `timeout 30` risk). `after_commit true` closes the pre-commit race that `sync` would also hide.
- Fallback `B. sync` remains a one-line rollback if host forbids `every minute` or kills even short workers — instant `2-10s` at cost of HTTP blocking. Do not pre-apply `sync` until `A` proven blocked by host policy.

**No action executed — owner approval required before any `.env`, `config/queue.php`, cron, or `queue:work` change.**

---

*Read-only evidence: `config/queue.php:16,38-44`, `config/mail.php:17,49`, `config/notifications.php:159-174`, `.env:38,50-56`, `QueuedVerifyEmail.php:33-36`, `EmailOtpMail.php:16-19`, `SendNotificationJob.php:27-29`, `NotificationService.php:123-140`, `EmailOtpService.php:189-214`, `ResolveMailer.php:23-57`, `bootstrap/app.php:88-90` `notifications:retry everyFiveMinutes`, `schedule:list` `*/5 notifications:retry`, `jobs 5 pending 09:25:42 oldest`, `failed_jobs 0`, `HealthController.php:57-66`, ` artisan queue:work --help ` `--stop-when-empty` present, `laravel.log verification_queue_stuck_hint`.*

*Reports: prior `EMAIL_DELIVERY_LATENCY_FORENSIC_REPORT.md` (QUEUE LATENCY) + this `QUEUE_REMEDIATION_FORENSIC_REPORT.md` (Remediation).*

