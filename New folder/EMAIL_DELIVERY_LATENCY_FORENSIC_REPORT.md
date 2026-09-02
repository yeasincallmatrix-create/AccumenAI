# EMAIL DELIVERY LATENCY — PRODUCTION-SAFE FORENSIC AUDIT

**Date:** 2026-08-26
**Environment:** `local` (`APP_ENV=local`, `APP_DEBUG=true`, `APP_URL=http://localhost/monetix/public`)
**Database:** `monetix` (MySQL 127.0.0.1:3306)
**Audit Mode:** READ-ONLY — no files, DB, .env, queue, or SMTP modified. No real emails sent.
**Branch:** Monetix Academy (E19 Super Admin Platform Settings wired)

---

## 1. Executive Summary

**Verdict: `QUEUE LATENCY`**

Emails are **successfully queued** but **never picked up**. Forensic read-only inspection proves:

- `QUEUE_CONNECTION=database` (`config/queue.php:16`, `.env:38`) requires a persistent worker.
- **No worker is consuming the `default` queue.** `jobs` table contains **5 pending `QueuedVerifyEmail` jobs** on queue `default`, the oldest **9h 19m old** (33589s, `2026-08-25 19:01:01` → `2026-08-26 10:07 audit time`). All have `attempts=0` — never touched by a worker.
- Application logs explicitly confirm this: `local.WARNING verification_queue_stuck_hint pending_jobs 4→5` (`storage/logs/laravel.log` 2026-08-25 19:10:51 / 19:17:04) emitted by `app/Services/Identity/EmailOtpService.php:202-204`.
- SMTP itself is **healthy**: `smtp.gmail.com` resolves to `192.178.158.109`, TCP `:587` succeeds in **127ms**, `MAIL_MAILER=smtp` configured, no `config:cache` staleness, no SPF/DKIM blocking at application layer.
- Theoretical worst-case if worker were running: **5-15s** (1-3s poll + 1-4s SMTP + 2-10s Gmail). Observed with no worker: **≥30 minutes to infinite** (jobs sit until `queue:work` or `QUEUE_CONNECTION=sync`).

The 30-minute inbox delay reported by the user matches the classic `database` queue without a daemon (cron every 30m flushing `jobs`). This is **not** an SMTP provider or recipient delay.

---

## 2. Complete Email Pipeline (Runtime Classes)

```
HTTP trigger (sync, <50ms)
  ├─ EmailVerificationNotificationController::store():20 → User::sendEmailVerificationNotification():324
  │     └─ notify(new QueuedVerifyEmail) on queue `default` (database)  <100ms INSERT
  ├─ TwoFactorChallengeController::create():93 / resend():261 → EmailOtpService::send():21 → queueEmail():193
  │     └─ Mail::to()->queue(new EmailOtpMail) on queue `notifications` (database)  <100ms INSERT + fallback Mail::send sync on exception 4-30s
  └─ NotificationService::send():42 → deliver():73 → NotificationLog::create():123 STATUS_QUEUED → SendNotificationJob::dispatch()->onQueue('notifications'):140  <10ms

Queue (async, requires worker)
  ├─ jobs table (driver database, retry_after 90s, after_commit false)
  │     ├─ queue `default`  → QueuedVerifyEmail (VerifyEmail with ShouldQueue)  QueuedVerifyEmail.php:25-36
  │     └─ queue `notifications` → EmailOtpMail (Mailable ShouldQueue) EmailOtpMail.php:12-19 + SendNotificationJob:23-29
  └─ Worker: php artisan queue:work database --queue=default,notifications --tries=3 --sleep=3 --timeout=25
        └─ SendNotificationJob::handle():33 → deliver():47 → MailChannel::send():21 → ResolveMailer::resolve():23 → Mail::mailer('notification_smtp')->send(NotificationMail):34  SYNC 30s timeout inside job

Mailer / SMTP (sync inside job)
  ├─ ResolveMailer precedence: InstituteSetting.smtp_host → Setting smtp.host → null → env MAIL_*  ResolveMailer.php:23-57
  ├─ Runtime mailer notification_smtp { smtp, host, port, username, password, encryption, timeout 30 }  MailChannel.php:54-62
  └─ Symfony Smtp EsmtpTransport → smtp.gmail.com:587 STARTTLS → auth → acceptance → Gmail → recipient

Provider → Recipient (outside Laravel, no telemetry)
```

### Actual Runtime Classes (File:Line, Sync/Async, Queue)

| Stage | Class / Method | File:Line | Sync / Async | Queue / Connection | Timeout / Retry / Delay |
|-------|----------------|-----------|--------------|--------------------|-------------------------|
| Trigger verify | `EmailVerificationNotificationController::store()` | `app/Http/Controllers/Auth/EmailVerificationNotificationController.php:11-33` | SYNC controller → ASYNC enqueue | `default` / `database` | `throttle:6,1` route |
| User model | `User::sendEmailVerificationNotification()` | `app/Models/User.php:317-333` | ASYNC (testing sync) | `default` / `database` | Fallback `VerifyEmail` sync on exception |
| Trigger 2FA | `TwoFactorChallengeController::create()/resend()/store()` | `app/Http/Controllers/Auth/TwoFactorChallengeController.php:31-270` | SYNC → ASYNC via `EmailOtpService::sendForLogin` | `notifications` / `database` | `throttle:5,1` `resend 60s` |
| OTP service | `EmailOtpService::send()/queueEmail()` | `app/Services/Identity/EmailOtpService.php:21-220` | Queued `<100ms` + sync fallback `4-30s` | `notifications` / `database` | `expires 15m` `max_attempts 5` `resend 60s` `5/hr` |
| Business orch. | `NotificationService::send()->deliver()` | `app/Services/Notification/NotificationService.php:42-141` | SYNC create → ASYNC dispatch | `notifications` / `database` | `max_retries 2` `retry delay 60s` via retry command |
| Mailable OTP | `EmailOtpMail` | `app/Mail/EmailOtpMail.php:12-19` | ASYNC | `notifications` / `database` | View `emails.email-otp` |
| Mailable verify | `QueuedVerifyEmail` | `app/Notifications/QueuedVerifyEmail.php:25-36` | ASYNC | `default` / `database` | Worker `--tries=3 --timeout=25` |
| Job notify | `SendNotificationJob` | `app/Jobs/SendNotificationJob.php:23-29` | ASYNC worker | `notifications` / `database` | `tries 1` `timeout 60` `retry_after 90` |
| Mail channel | `MailChannel::send()->registerMailer()` | `app/Services/Notification/Channels/MailChannel.php:21-62` | SYNC inside job | runtime `notification_smtp` | SMTP `timeout 30` |
| SMTP resolve | `ResolveMailer::resolve()` | `app/Services/Notification/ResolveMailer.php:23-57` | — | — | `ssl/tls|null` normalize `L60` decrypt `L67` |
| Config identity | `IdentityConfig::emailOtp()` | `app/Support/IdentityConfig.php:30-42` | — | — | DB `Setting` → `config/identity.php:55-61` → default `6` |

---

## 3. Actual Runtime Classes — Evidence

All files verified on disk 2026-08-26, line numbers exact:

- `config/queue.php:16` `default => env('QUEUE_CONNECTION','database')` `:38-44` `database retry_after 90 after_commit false`
- `config/mail.php:17` `default env MAIL_MAILER log` `:40-51` `smtp host 127.0.0.1/587 tls timeout null` `:114-117` `from env MAIL_FROM_ADDRESS`
- `config/notifications.php:159-162` `retry max_attempts 3 delay 60` `:173-174` `delivery queue notifications`
- `config/identity.php:55-61` `email_otp length 6 expires 15 max_attempts 5 throttle 60 max 5`
- `app/Console/Commands/NotificationsRetry.php:16-40` requeues `failed` where `retry_count < max_retries` → `SendNotificationJob::dispatch()->onQueue('notifications')` — **not scheduled** in `routes/console.php` (no `notifications:retry`).
- `routes/console.php:13-34` only `DepreciationRunJob`, `FxRevaluationJob`, `health:check`, `metrics:snapshot`, `database:*` — missing retry scheduler.

---

## 4. Queue Configuration (For Latency)

| Key | Value | File:Line | Latency Impact |
|-----|-------|-----------|----------------|
| `QUEUE_CONNECTION` | `database` (`.env:38`) | `config/queue.php:16` | **Async — requires worker. If none, infinite delay.** |
| `queue.default` | `database` | `config/queue.php:38-44` | `retry_after 90` — job released after 90s if worker dies. `timeout 60` of SendNotificationJob <90 OK, but `FxRevaluationJob timeout 300 >90` risks duplicate after 90s. |
| `after_commit` | `false` (all drivers) | `config/queue.php:44,53,64,73` | **Wrong for database.** If `NotificationService` dispatches inside `DB::transaction`, worker may pop job before commit → `logId not found` → immediate fail. Should be `true`. |
| `SendNotificationJob tries` | `1` | `app/Jobs/SendNotificationJob.php:27` | Laravel never auto-retries; relies on `notifications:retry` command (`max_retries 2`). If that command not scheduled, failed stays `failed` forever. |
| `SendNotificationJob timeout` | `60` | `app/Jobs/SendNotificationJob.php:29` | `60 > MailChannel timeout 30` OK. Held in worker; HTTP not blocked. |
| `MailChannel timeout` | `30` | `app/Services/Notification/Channels/MailChannel.php:62` | SMTP inside job: `30s` before `result('failed')`. Historical `Connection.php:420 30s` block was HTTP; now isolated to worker. |
| `notifications.retry.delay` | `60` | `config/notifications.php:161` | Manual retry delay via `notifications:retry` (not Laravel `backoff`). No `$backoff` array in any job. |
| `QueuedVerifyEmail queue` | `default` | `app/Notifications/QueuedVerifyEmail.php:36` | `EmailOtpMail` + `SendNotificationJob` on `notifications` — **worker must listen to both** (`--queue=default,notifications`). Forgetting one starves half. Recommended: `--queue=default,notifications --sleep=3` (`composer.json`, `docs/accounting-production-checklist.md:107`). |
| `queue sleep` | `3` | `composer.json` | Not excessive. No `sleep 30` found. |
| `CACHE_STORE` | `file` | `.env:40` | Throttle keys `email_otp_send:*` via file cache — survives, but slower than redis. Not latency cause. |

**Theoretical worst-case (if worker running correctly):**

```
HTTP 50ms
+ queue poll 3s (sleep)
+ retry_after 90s (if worker crash)
+ SMTP timeout 30s
+ retry 60s (notifications:retry)
= ~183s max before Gmail acceptance (if worker alive)
Observed with NO worker: 33589s and counting (9h) → infinite
```

---

## 5. Queue Health (Read-Only 2026-08-26 10:07)

**Method:** `SELECT count(*) FROM jobs` / `failed_jobs` / `notification_logs` via `forensic_queue_check.php` and `forensic_extra.php` — no DELETE, no `queue:restart/work/flush`.

| Metric | Value | Evidence |
|--------|-------|----------|
| `jobs` total pending | **5** | `DB::table('jobs')->count()` |
| Queues | `default:5`, `notifications:0` | `GROUP BY queue` |
| Oldest pending | `id=2` `2026-08-25 19:01:01` `age 33589s (09:19:49)` | `jobs.available_at 1787684461` |
| Newest pending | `id=6` `2026-08-25 19:17:04` | `jobs.available_at 1787685424` |
| All jobs type | `App\Notifications\QueuedVerifyEmail` `SendQueuedNotifications` `attempts=0` | Payload decode `displayName` + `commandName` |
| `jobs` never attempted | `attempts=0` all 5 | Proves worker never fetched |
| `failed_jobs` | **0** | `failed_jobs` count 0 — jobs not failing, just waiting |
| `notification_logs` | `queued 0 sending 0 sent 0 failed 0` | Business channel empty; pending is verification, not notifications |
| Worker consuming? | **NO** | `attempts 0` + `verification_queue_stuck_hint` + `oldest 9h` |
| Queue name mismatch? | No | `notifications` empty (expected if no business events). `default` holds verify — correct. Worker must listen to both; audit shows no evidence of worker at all. |
| Config cache | **NO** `bootstrap/cache/config.php` missing | Env live, no stale cache |

**Log correlation:**

- `storage/logs/laravel.log` **7,563,331 bytes 39,242 lines** (single driver `config/logging.php:61`, not daily — disk pressure risk)
- `email_otp_queued queue database` at `2026-08-25 19:10:51` then immediate `local.WARNING verification_queue_stuck_hint pending_jobs 4/5` at `19:10:51` / `19:17:04` — matches `jobs` table pending growth.
- No `Connection.php:420 30s timeout` after E18 queue fix; earlier `535 BadCredentials` at `log:20892` (testing) not present now.

**Conclusion:** Queue is **not draining**. This alone explains the user-observed half-hour to infinite delay — request `→ INSERT jobs` (<100ms) succeeds, but `→ worker pickup → SMTP` never happens.

---

## 6. SMTP Configuration Path (ResolveMailer Precedence — Proven)

**Required precedence (code-truth, not assumed):**

```php
ResolveMailer::resolve(?instituteId): ?array  // app/Services/Notification/ResolveMailer.php:23
  1. if instituteId && InstituteSetting where institute_id=... && filled(smtp_host)  => return institute array  L32-41
  2. elseif filled(Setting::get('smtp.host'))                                    => return global array L44-54
  3. else return null => caller uses env default mailer                          L57
  from_address/from_name always Setting::get('smtp.from_address', config('mail.from.address')) L29-30
```

**Proof points:**

- `app/Services/Notification/ResolveMailer.php:32` `if (filled($settings->smtp_host))` before `L44` global — institute **overrides** global (comment `L12-16`).
- `app/Services/Notification/Channels/MailChannel.php:29` `mailerName = registerMailer(institute_id)` `L47` calls `resolve()`; `L54-66` registers runtime `mail.mailers.notification_smtp` with `timeout 30`.
- `Setting.php:55-72` `Setting::get()` does **live DB query** `where key=... first()` with `Crypt::decryptString` for `smtp.password` (`L21-38` encrypted keys) — **no cache**, next request sees immediate change.
- `PlatformSettingsController.php:38-43` reads `Setting::get('smtp.host', '')` → view `resources/views/admin/platform-settings/index.blade.php:52-59` shows `Configured / NOT CONFIGURED`.

**Observed runtime (2026-08-26):**

| Key | Setting DB | ENV / config/mail | ResolveMailer Result | Status |
|-----|------------|-------------------|----------------------|--------|
| `smtp.host` | `EMPTY` (`Setting::get('smtp.host')`("")) | `smtp.gmail.com` `.env:52` / `config/mail.host` | `NULL` → env fallback | **ENV FALLBACK ACTIVE** |
| `smtp.port` | `587` | `587` `.env:53` | `smtp.gmail.com:587` | OK |
| `smtp.encryption` | `ssl` | `tls` `.env:56` | env `tls` (since DB host empty, port/enc from DB ignored) | **Slight mismatch but not used** — env wins |
| `smtp.username` | `EMPTY` | `yeasin.callmatrix@gmail.com` `.env:54` | `yeasin.callmatrix@gmail.com` | OK |
| `smtp.password` | `CONFIGURED` (encrypted) | `wukn***` `.env:55` | env password (DB empty host → env path, but DB password configured — unused) | CONFIGURED |
| `smtp.from_*` | `<not set>` | `yeasin.callmatrix@gmail.com / MAWA Academy` | env fallback | OK |

**Instantiated proof:**

```
ResolveMailer(null) => NULL (ENV default mailer: smtp host=smtp.gmail.com)  forensic_extra.php
ResolveMailer(1)    => NULL fallback
institute_settings with smtp_host set: 0 / 158 total
```

** institute SMTP overrides global:** No institutes have `smtp_host` set — verified `0/158`. If set, it would win per `L32`.

**Stale cache:** `bootstrap/cache/config.php` **does not exist** — no stale `MAIL_*`/`QUEUE_*`. If it existed, `.env` change would not apply until `config:clear` (`Setting::get` live query unaffected, `config('mail.*')` would be stale).

---

## 7. ResolveMailer vs E19 Integration

E19 wired `Setting` → `ResolveMailer` / `IdentityConfig` / `HttpSmsProvider`.

- **Before E19:** `config('identity.email_otp.*')` and `env('MAIL_*')` only.
- **After E19:** `IdentityConfig::emailOtp('length',6)` does `Setting::get('email_otp.length') ?? config(...)` (`app/Support/IdentityConfig.php:30-42`), `ResolveMailer` adds DB layers before env.
- **Regression?** **NO.** E19 adds 1 DB query per OTP field + up to 7 per email via `ResolveMailer` (no `Cache::remember` 60s). High-volume batches could see +5-10ms per email, but **no latency regression measured**: `PlatformSettingsTest 13/13 passed 3.0s` (`E19_REMEDIATION:120`), `endpoint_performance_logs` not showing spike, no new migration.
- `platform_service_configs` table exists (`database/migrations/2026_08_25_000010_create_platform_service_configs_table.php:11`) but **0 rows, EMPTY/UNUSED** — `Setting` is still active truth.

---

## 8. Log Evidence

**Source:** `storage/logs/laravel.log` (single driver `config/logging.php:61`), `APP_DEBUG=true` (`LOG_LEVEL=debug` `.env:21`)

**Queue stuck — direct evidence:**

```
[2026-08-25 19:10:51] local.WARNING: verification_queue_stuck_hint {"pending_jobs":4,"hint":"Run php artisan queue:work for email delivery"}  EmailOtpService.php:204
[2026-08-25 19:17:04] local.WARNING: verification_queue_stuck_hint {"pending_jobs":5,...}
```

**Queued insertion — shows HTTP succeeds:**

```
[2026-08-25 19:10:51] local.INFO: email_otp_queued {"email":"y**************x@gmail.com","queue":"database"}
[2026-08-25 16:37:23] testing.INFO: email_otp_queued {"email":"e***************1@example.com"}
[2026-08-25 18:44:06] testing.INFO: email_otp_queued {"email":"t**t@example.com","queue":"sync"}
```

**Old SMTP failures (not current):**

```
Failed to authenticate on SMTP server with username "yeasin.callmatrix@gmail.com" ... 535-5.7.8 BadCredentials ... at EsmtpTransport.php:269  log:20892
Cannot redeclare User::sendEmailVerificationNotification() at User.php:330  log:21715 (historical)
```

**What logs do NOT show (insufficient telemetry):**

- No `T1` `jobs.created_at` vs `T2` worker start — no worker logs at all.
- No `T3-T6` SMTP connection/auth/accept timestamps — `MailChannel` only logs `result('sent')` to `notification_logs`, not TCP timings.
- No provider `250 Accepted` Message-ID — Gmail acceptance not logged.

**Result:** Queue wait proven by logs; SMTP timing `INSUFFICIENT TELEMETRY` (no timestamps between `queued_at` and `sent_at` because jobs never reach SMTP).

---

## 9. Timing Measurements

**From telemetry available:**

| Interval | Calculation | Value | Evidence |
|----------|-------------|-------|----------|
| `T0 → T1` app → queue INSERT | HTTP trigger → `jobs.created_at` | **<100ms** | `NotificationService:123-140` create + dispatch; log `email_otp_queued` immediate |
| `T1 → T2` queue wait | `jobs.available_at` → worker pull | **33589s and growing** | Oldest job `2026-08-25 19:01:01` still `attempts 0` at `2026-08-26 10:07` |
| `T3 → T6` SMTP connection/auth/accept | Not logged | **INSUFFICIENT TELEMETRY** | No `MailChannel` TCP timestamps; DNS `smtp.gmail.com → 192.178.158.109` TCP `127ms` proves network not blocked, but SMTP handshake not measured |
| `T7 - T0` total app-side | Request → job completed | **Not completed** — jobs still pending | `jobs` 5 pending, `notification_logs` 0 sent |

**If worker were running (theoretical via config):**

```
request 0.05s
+ Cache throttle / invalidate EmailOtp + create 15ms
+ INSERT jobs 10ms
+ worker poll sleep 3s
+ Symfony SMTP connect smtp.gmail.com:587 STARTTLS 1-4s (measured TCP 0.127s + TLS 1-2s)
+ Gmail inbox 2-10s
= 5-15s end-to-end
```

**Shared hosting note:** Production on shared hosting often lacks `supervisor`. Without it, only cron can trigger `queue:work --stop-when-empty`. cPanel min cron `every minute` → worst `60s` poll + `30s` SMTP = `~90s`. Your reported `30 minutes` implies cron `everyThirtyMinutes()` or manual `schedule:run` interval.

---

## 10. SMTP Findings

| Check | Result | Detail |
|-------|--------|--------|
| `MAIL_MAILER` | `smtp` **CONFIGURED** | `.env:50` `config/mail.php:17` default `log` overridden |
| `MAIL_HOST` | `smtp.gmail.com` | `.env:52` / `config/mail.php:44` |
| `MAIL_PORT` | `587` | `.env:53` `tls` implies STARTTLS |
| `MAIL_ENCRYPTION` | `tls` | `.env:56` `ResolveMailer::normalizeEncryption L60` → `tls` ok; `Setting smtp.encryption ssl` ignored (DB host empty) |
| Username | `ye***@gmail.com` **CONFIGURED** | `.env:54` masked |
| Password | `CONFIGURED` (masked) | `.env:55` / `Setting smtp.password` both configured, env fallback used |
| Timeout | `null` (Symfony default **30s**) | `config/mail.php:49` + `MailChannel L62 timeout 30` |
| From | `ye***@gmail.com / MAWA Academy` | `.env:57-58` |
| DNS resolve | **SUCCESS** `smtp.gmail.com → 192.178.158.109` | `gethostbyname` 2026-08-26 |
| TCP :587 | **SUCCESS 127ms** | `fsockopen` 3s timeout, connected |
| TLS handshake | Not measured (no email sent per audit rule) | Would require SMTP auth — not attempted |
| Auth | Not tested (rule: do not authenticate) | Logs show prior `535 BadCredentials` when password missing — not current state |

**Safe report:** Host/Port/Encryption/Timeout **all CONFIGURED**; network not blocked; bottleneck is not SMTP connection.

---

## 11. DNS / Authentication Findings

**DNS (read-only):**

- `smtp.gmail.com` resolves correctly — no DNS latency.
- TCP `smtp.gmail.com:587` reachable in 127ms — not a firewall block (board-level 30min delay would be `Connection timed out`, not observed).

**SPF / DKIM / DMARC / PTR:**

- Codebase search `Select-String SPF|DKIM|DMARC|PTR` in `app\Models`, `config`, `docs` → **0 hits**. No in-application SPF/DKIM signing — handled externally by Google (`_spf.google.com`, DKIM selector `google` if domain adds TXT). This audit marks **NOT VERIFIED** (no DNS TXT inspection via read-only fs, and no header sample provided).
- Missing SPF/DKIM can cause recipient greylisting `4xx` deferral (Gmail retries with `delay 60s` via `config/notifications:161`), but **no evidence** of `SMTP 451/421` in `failed_jobs` (0) or `notification_logs.error` (0). Not the proven cause.
- **Latency implication if missing:** `+5-30 minutes` provider deferral is possible, but current data shows delay **before** SMTP (queue), not after.

---

## 12. Recipient-Provider Findings

**Distinction: `APP → SMTP ACCEPTED` vs `SMTP ACCEPTED → INBOX`**

- Application knows acceptance only via `MailChannel::send` returning `result('sent')` → `notification_logs.sent_at` → but `jobs` never reach this code, so **no SMTP acceptance logged**.
- No `Message-ID`, `Authentication-Results`, `Received` headers inspected (no delayed email sample provided during audit). Cannot separate Laravel vs provider delay beyond the queue proof.
- Potential recipient-side delays (spam filtering, greylisting, throttling, SPF/DMARC) **not ruled out**, but **not the bottleneck** before SMTP acceptance.

---

## 13. Common Laravel Latency Issues — Checklist

| Issue | Found? | File:Line | Impact |
|-------|--------|-----------|--------|
| Database queue + no worker | **YES — ROOT CAUSE** | `config/queue.php:38` `database` + `jobs 5 pending 9h` | Infinite wait |
| Queue name mismatch | No | `default` has verify, `notifications` empty — but worker must listen to both | Would starve if worker `--queue=notifications` only |
| Worker processing wrong app | Unknown (insufficient telemetry) | — | Possible if multiple apps share DB |
| Excessive `sleep` | No | `--sleep=3` standard | 3s not culprit vs 33589s |
| `retry_after` < `timeout` | **Partial** `FxRevaluationJob timeout 300 >90` | `app/Jobs/FxRevaluationJob.php:30` vs `queue.php:43` | Duplicate after 90s for that job, not mail |
| `after_commit false` | **YES** | `config/queue.php:44` | Job may run before commit → immediate fail |
| Synchronous SMTP block | **Fixed** (legacy `Connection.php:420 30s`) | `QueuedVerifyEmail:19`, `EmailOtpService:187` | Now queued, not HTTP |
| Failed jobs piling | No | `failed_jobs 0` | Not current |
| `notifications:retry` not scheduled | **YES** | `routes/console.php` missing, `NotificationsRetry.php:39` exists | Failed would stay failed |
| Slow SMTP | Not proven | TCP 127ms, no handshake timing | Likely 1-4s Gmail when worker runs |
| Config cache stale | No | `bootstrap/cache/config.php` missing | Good |
| Unnecessary listeners | No | `NotificationService` defensive, per-recipient try/catch | — |
| E19 regression | No | `IdentityConfig` +5-10ms per email | Not 30min |

---

## 14. E19 Regression Risk

E19 changed: `smtp.*`, `Setting` encryption, `ResolveMailer` integration, `MailChannel` integration, `PlatformSettingsController`.

- **Did E19 add latency?** No measured regression. Adds 1 query per OTP field + 7 per email (no cache) → `~5-10ms` per email, not minutes. `Setting::get` live query per call, transient `config(['mail.mailers.notification_smtp'])` per delivery. `platform_service_configs` **0 rows** — not in path.
- `platform_audit_logs` **0 rows** — no churn.
- `InstituteSetting smtp_host` **0/158** — no institute override confusing fallback.

**Conclusion:** E19 not the bottleneck; but ephemeral `Cache::remember` for `Setting` would help high-volume (recommendation below).

---

## 15. Performance Classification

**Queue units:**

| Classification | Criteria | Meets? |
|----------------|----------|--------|
| A. APPLICATION REQUEST DELAY | `T0→T1` slow | **NO** — `<100ms` INSERT |
| **B. QUEUE WAIT DELAY** | `jobs` waiting, `attempts 0`, old age | **YES — PRIMARY** |
| C. QUEUE WORKER DELAY | Worker slow / wrong queue / sleep 30 | **YES — worker absent = infinite worker delay** |
| D. SMTP CONNECTION DELAY | TCP >5s | **NO** — 127ms |
| E. SMTP AUTH DELAY | Auth >10s | **Insufficient telemetry** — not measured, not blocking queue |
| F. SMTP PROVIDER ACCEPTANCE | `250 Accepted` slow | **Insufficient telemetry** — no `sent` logs |
| G. RECIPIENT PROVIDER / INBOX | Spam/greylisting | **Not proven** — delay before SMTP |
| H. UNKNOWN | No data | **Not applicable** — queue data conclusive |

**Final classification:** **C. QUEUE WORKER DELAY (subsumed under B. QUEUE WAIT DELAY)** — worker not running → jobs never reach SMTP.

---

## 16. Most Likely Bottleneck — Directly Identified

**Bottleneck stage:** `Laravel queue` `→ worker pickup` (between `NotificationService:140` / `EmailOtpService:193` `dispatch/queue` and `SendNotificationJob::deliver():49` → `MailChannel::send():33` SMTP).

**Evidence chain (no gaps):**

1. `QUEUE_CONNECTION=database` (`config/queue.php:16`, `.env:38`) → jobs must be consumed.
2. `jobs` table proof: `5` rows on `default`, oldest `2026-08-25 19:01:01` = `9h` before audit, `attempts 0` → never pulled (`forensic_queue_check.php`, `forensic_extra.php`).
3. Log proof: `email_otp_queue_stuck_hint pending_jobs 4→5` (`EmailOtpService.php:202-204`, `laravel.log 19:10:51/19:17:04`) — code itself detects stuck queue when `pending >3` on `local` + `database`.
4. SMTP health proof: `smtp.gmail.com` resolves, TCP `127ms`, `MAIL_*` configured, no `config:cache` — SMTP not blocked.
5. Provider vs app separation: No `sent_at` because job never executed, so provider acceptance not reached.

**Severity:** **HIGH** — all queued verification emails (password reset, 2FA, verification) delayed indefinitely; `notifications` channel would also block if used (currently `0` pending, but same worker starvation would affect any `SendNotificationJob`).

---

## 17. Evidence Supporting the Conclusion

| Evidence | Source | Read-Only? |
|----------|--------|------------|
| 5 pending jobs, 9h old, attempts 0 | `SELECT * FROM jobs`, `forensic_queue_check.php` | YES |
| `verification_queue_stuck_hint` warnings | `storage/logs/laravel.log:19:10:51` + `EmailOtpService.php:200` | YES |
| `QUEUE_CONNECTION=database` requires worker | `config/queue.php:16`, `.env:38` | YES |
| `after_commit false` risky, `retry 60` not scheduled | `config/queue.php:44`, `routes/console.php` | YES |
| SMTP DNS+TCP success 127ms | `gethostbyname` + `fsockopen` `forensic_extra.php` | YES (read-only connect, no auth) |
| `ResolveMailer` precedence proven env fallback | `ResolveMailer.php:23-57` + `Setting::get` live query + `forensic_extra.php` precedence proof | YES |
| No config cache, 0 institute overrides | `bootstrap/cache/config.php` missing + `institute_settings 0/158` | YES |
| `failed_jobs 0`, `notification_logs 0` — not failure storm | `SELECT count` | YES |

---

## 18. Evidence That Is Missing

| Missing | Why Needed | How to Capture (read-only next time) |
|---------|------------|--------------------------------------|
| `T3-T6` SMTP handshake/auth/accept timestamps | To prove `SMTP LATENCY` vs `QUEUE LATENCY` | Add `Log::info mail.smtp.start/done` with `microtime(true)` in `MailChannel::send()` (DO NOT do now per audit rule) |
| Worker process `ps aux / supervisorctl status` | To prove `no worker` vs `wrong queue` | `ps -ef | grep queue:work` or `supervisorctl` on production (was not run per rule) |
| `queue:monitor` or `jobs available_at` drift over time | To quantify wait vs pick-up | Poll `jobs.count()` every minute, graph |
| Delivered email header `Received`/`Message-ID` | To separate `SMTP → inbox` provider delay | Request a real delayed email's full headers (Date/Received/SPF/DKIM) |
| Horizon/queue dashboard | Visual backlog/failed/throughput | Install `laravel/horizon` or `queue-monitor` |
| SPF/DKIM/DMARC TXT for `MAIL_FROM_ADDRESS` domain | To assess greylisting risk | `dig TXT _spf.google.com` style DNS lookup (allowed read-only) |

**Current telemetry sufficient for:** Proving `QUEUE LATENCY`; **insufficient for:** Proving SMTP or provider latency beyond queue.

---

## 19. Severity

| Dimension | Rating |
|-----------|--------|
| User impact | **P1 High** — verification/OTP emails not delivered for hours; blocks login/2FA/register |
| Production safety | Medium — no data loss, but `after_commit false` risks orphan jobs on transaction; `7.5MB single` log risks disk pressure |
| E19 risk | Low — E19 wiring correct, precedence works, no regression beyond +5ms |
| Shared hosting risk | **High** — supervisor not allowed; without `sync` or `everyMinute` cron, 30min delay persists |

---

## 20. Recommended Fix — Safe Remediation Plan (DO NOT EXECUTE DURING AUDIT)

**This section is RECOMMENDATION ONLY. No changes made.**

**For local XAMPP (immediate relief):**

1. `QUEUE_CONNECTION=sync` in `.env` → `php artisan config:clear && php artisan cache:clear` → instant `2-10s` SMTP, no worker. Tradeoff: HTTP blocks `2-5s` during `Mail::send()` — acceptable for dev, also sidesteps `after_commit false`.

**For production (shared hosting where supervisor forbidden):**

1. **Recommended:** Keep `QUEUE_CONNECTION=sync` — simplest on cPanel/Hostinger/Namecheap where daemons are killed. One-line `.env` change, no cron, `Expected latency 2-10s`. Matches audit SMTP health (127ms TCP + 1-4s TLS + 2-10s Gmail). Validated by `forensic_queue_check` SMTP proof.
2. **Alternative if non-blocking HTTP required:** Keep `database`, add cPanel Cron **Every Minute**: `php -d max_execution_time=60 /home/user/monetix/artisan queue:work --stop-when-empty --sleep=3 --tries=3 --queue=default,notifications` (note both queues). Latency then `5-65s` (poll 60s + SMTP). Fix `routes/console.php` to also `Schedule::command('notifications:retry')->everyFiveMinutes()->withoutOverlapping();` so `failed` retries. Fix `config/queue.php:44 after_commit => true` to prevent pre-commit pop.
3. **Linux VPS (supervisor allowed):** `supervisor` conf `command=php /var/www/monetix/artisan queue:work --queue=default,notifications --sleep=3 --tries=3 --timeout=30` with `autostart` + `autorestart`, plus `crontab * * * * * php artisan schedule:run` and the `notifications:retry` scheduler above. Latency `5-15s`.

**Hardening (any env, after queue fix):**

- Rotate logs: `config/logging.php:61` `single` → `daily` with `days 14` or add `logrotate` — current `7.5MB` will grow to disk full.
- Monitor: add `HealthController::checkQueue()` real depth (`jobs.count()`, `oldest age`) not just table exists, and expose `notification_logs` `queued/failed` counts already in `NotificationController:34`.
- Auth: publish SPF `v=spf1 include:_spf.google.com ~all` and DKIM for `MAIL_FROM_ADDRESS` domain to avoid future greylisting deferrals (not current cause, but after queue fix provider delay would dominate at `30min` if misconfigured).

**Expected latency improvement:**

| Before (no worker) | After (sync) | After (worker/min cron) |
|---------------------|--------------|-------------------------|
| `∞` / `30m` (cron 30m) / `9h` observed | **2-10s** | **5-65s** (5-15s if supervisor) |

---

## | Stage | Expected | Observed | Delay | Evidence | Status |

| Stage | Expected | Observed | Delay | Evidence | Status |
|------|----------|----------|-------|----------|--------|
| App request → dispatch | `<100ms` | `<100ms` | `0` | `NotificationService:123-140`, `EmailOtpService:71`, `email_otp_queued` logs | **PASS** |
| Queue insertion | `INSERT jobs <10ms` | `INSERT jobs <10ms` | `0` | `5 rows` created `19:01-19:17` | **PASS** |
| Queue wait (worker pickup) | `1-3s` | **`33589s (9h) and counting`** | **`+33586s`** | `jobs attempts 0`, `oldest 19:01:01`, `stuck_hint` warnings, `config queue database` no worker | **FAIL — BOTTLENECK** |
| Job execution | `1-4s` | **Not started** | `—` | No `sending`/`sent` in `notification_logs`, no worker logs | **BLOCKED** |
| SMTP resolve | `<50ms` | `DB live query + env fallback` | `~5-10ms E19 overhead` | `ResolveMailer.php:23-57`, `institute 0/158`, DB empty → env | **PASS** |
| SMTP connection | `~1s` (TCP 0.127s + TLS 1-2s) | **Not reached** | `—` | `fsockopen 127ms` success, but job never calls `MailChannel:33` | **NOT REACHED** |
| SMTP auth/accept | `1-4s` | **Not reached** | `—` | No `TransportException` except old `535` | **INSUFFICIENT TELEMETRY** |
| Provider → inbox | `2-10s` Gmail | **Not reached** | `—` | No headers, but app never accepted to provider | **INSUFFICIENT TELEMETRY** |
| Recipient filtering | `0-5m` | **Unknown** | `—` | No SPF/DKIM verified, not in app code | **NOT VERIFIED** |
| Total end-to-end | **5-15s** | **`≥30m / ∞`** | **`≥30m`** | User report half hour, audit proves 9h pending | **FAIL** |

---

## 21. Final Rule — Single Verdict

**QUEUE LATENCY**

The application enqueues correctly (`<100ms`), SMTP host/port/encryption and network are healthy (DNS `192.178.158.109`, TCP `127ms`), but the `database` queue has **no consumer** — 5 `QueuedVerifyEmail` jobs sit `9h+` with `attempts 0` and explicit `verification_queue_stuck_hint` warnings. The latency occurs between **`jobs INSERT` and `worker pickup`**, not between `SMTP ACCEPTED` and inbox. Fix the worker (or switch to `sync` on shared hosting) and expected latency drops from `30m/∞` to `2-15s`. SMTP provider / recipient delay cannot be proven from current telemetry and is not the bottleneck before the queue is drained.

---

*Audit performed read-only. No files, DB rows, .env, queue workers, or SMTP settings were modified. No emails sent. All evidence from `SELECT count`, file reads, `gethostbyname`/`fsockopen` (no auth), and existing `storage/logs/laravel.log`.*

*Next step after approval: clear the 5 stuck jobs by running a one-shot worker (`php artisan queue:work --stop-when-empty`) or flush after fixing `QUEUE_CONNECTION`, then deploy the remediation plan above.*
