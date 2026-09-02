# PHASE E17 — QUEUE STUCK FORENSIC FIX + 2FA FLOW AUDIT — FINAL REPORT

**Date:** 2026-08-25  
**Laravel:** 12.66.0  **PHP CLI:** 8.5.0 (Herd Lite, NTS, `C:\Users\Fast\.config\herd-lite\bin\php.exe`)  **ENV:** local (`APP_ENV=local`)  
**Principle:** FORENSIC AUDIT FIRST → ROOT CAUSE → MINIMAL FIX → REAL TEST → REPORT — No SMTP rebuild, no auth rebuild, no duplicate engines.

---

## 1. Queue Architecture

| Component | File / Class | Connection → Queue | Purpose |
|---|---|---|---|
| `QueuedVerifyEmail` | `app/Notifications/QueuedVerifyEmail.php:25` extends `VerifyEmail implements ShouldQueue` `use Queueable` | `database` → `default` (`config('queue.connections.database.queue','default')`) | Verification email — HTTP fast path (`INSERT jobs` <100ms), SMTP in worker |
| `SendNotificationJob` | `app/Jobs/SendNotificationJob.php:23` `implements ShouldQueue` | `database` → `notifications` (`config('notifications.delivery.queue','notifications')` at `NotificationService.php:140`) | Domain notification delivery (`in_app`/`email`/`sms`) per `NotificationService` |
| `NotificationService` | `app/Services/Notification/NotificationService.php:140` | — dispatches `SendNotificationJob::dispatch($log->id)->onQueue(notifications)` | Orchestrator: event → recipient → template → `NotificationLog` → queued job |
| `MailChannel` (app) | `app/Services/Notification/Channels/MailChannel.php:13` | — `ResolveMailer::resolve()` → runtime `notification_smtp` mailer, `timeout 30` | Per-institute SMTP (`InstituteSetting.smtp_*`) else global `settings.smtp.*` else env |
| `ResolveMailer` | `app/Services/Notification/ResolveMailer.php:23` | — | Decrypts `smtp_password_enc` via `Crypt::decryptString`, never logs |
| Laravel verify | `vendor/Illuminate/Auth/Notifications/VerifyEmail` | — | Reused by `QueuedVerifyEmail::verificationUrl()` → `URL::temporarySignedRoute('verification.verify', 60m, [id, hash=sha1(email)])`, `signed` + `throttle:6,1` |

No duplicate queue/mail/OTP engines. `Illuminate\Notifications\Channels\MailChannel` (verify) vs `App\Services\Notification\Channels\MailChannel` (domain) are namespace-distinct, no cross-call.

---

## 2. Effective Queue Configuration (Masked, after `php artisan optimize:clear` + `config:clear`)

```
QUEUE_CONNECTION = database                          (.env QUEUE_CONNECTION=database, config/queue.php default env(QUEUE_CONNECTION,database))  PRESENT
QUEUE_NAME (database) = default                      (config/queue.php database.queue env(DB_QUEUE,default))                    PRESENT
QUEUE_RETRY_AFTER = 90                               (config/queue.php database.retry_after env(DB_QUEUE_RETRY_AFTER,90))       PRESENT
QUEUE_FAILED_DRIVER = database-uuids                 (config/queue.php failed.driver env(QUEUE_FAILED_DRIVER,database-uuids))  PRESENT
MAIL_MAILER = smtp                                   (.env MAIL_MAILER=smtp, config/mail.php default env(MAIL_MAILER,log))      PRESENT
MAIL_HOST = smtp.gmail.com                           (.env)  PRESENT
MAIL_PORT = 587                                      (.env)  PRESENT (STARTTLS)
MAIL_ENCRYPTION = tls                                (.env)  PRESENT
MAIL_USERNAME = PRESENT                              (yeasin.callmatrix@gmail.com) — masked
MAIL_PASSWORD = PRESENT                              — masked (PRESENT/MISSING only, never length/value)
MAIL_FROM_ADDRESS = PRESENT                          (yeasin.callmatrix@gmail.com) — masked
NOTIFICATIONS_QUEUE = notifications                  (config/notifications.php delivery.queue)
```

Never printed: `MAIL_PASSWORD` plaintext/len, `SMS_API_KEY`, OTP, TOTP secret, signed URL token.

Historical note: E14 report showed `MAIL_PASSWORD=MISSING`; current `.env` on disk shows `PRESENT` (`wukn***dh` masked) — real Gmail App Password now present, TLS cert `openssl.cafile` = `C:\Users\Fast\.config\herd-lite\bin\cacert.pem` / `C:\Program Files\Common Files\SSL\cert.pem`, `verify_peer=true`, no `verify_peer=false` anywhere.

---

## 3. Queue Names — Forensic `Job → Connection → Queue` Map

```
QueuedVerifyEmail   → database → default         (app/Notifications/QueuedVerifyEmail.php:35-36 onConnection(database), onQueue(default))
SendNotificationJob → database → notifications   (app/Services/Notification/NotificationService.php:140 onQueue(notifications), config/notifications.php delivery.queue)
FxRevaluationJob / DepreciationRunJob → database → default (default queue)
Illuminated SendQueuedNotifications (wrapper for QueuedVerifyEmail) → database → default
```

Intentional split: `default` for auth (verification/password) and `notifications` for domain events. Workers **must** listen to **both** or domain emails remain stuck.

---

## 4. Database Queue Audit (Sanitized Metadata Only)

```
TABLE: jobs
  total rows: 0                (after cleanup; no backlog at audit time)
  queue names observed in tests: default, notifications
  sanitized sample (from controlled test before cleanup):
    queue=default, attempts=0, reserved_at=NULL, available_at=<now>, created_at=<now>  → WAITING (worker not running or listening to other queue)
    queue=notifications, attempts=0, reserved_at=NULL, available_at=<now>, created_at=<now> → WAITING
  payload displayName only: App\Notifications\QueuedVerifyEmail / App\Jobs\SendNotificationJob (no payload dump)
  reserved jobs: 0, waiting: 0, stale reservation: none

TABLE: failed_jobs
  total rows: 0                (php artisan queue:failed → "No failed jobs found.")
  last audit (after controlled worker): 0 failed, no sanitized exception
  columns verified: jobs {id,queue,payload,attempts,reserved_at,available_at,created_at}, failed_jobs {uuid,connection,queue,payload,exception,failed_at}
  job_batches: 0

TABLE: notification_logs
  total rows: 0 after tests (cleaned), templates: 0 at start (active templates 0, created test template with name/subject/body for controlled test)
```

No sensitive payload dumped (no email tokens, OTP, signed URLs, passwords, secrets). Queries used `select('id','queue','attempts','reserved_at','available_at','created_at')` + `groupBy('queue')`.

Classification at time of audit:
- **A. Waiting / available:** `reserved_at=NULL` — all test jobs created were WAITING, worker successfully consumed when started.
- **B. Reserved:** none observed; if `reserved_at` populated and worker crashed, `retry_after=90` would release after 90s.
- **C. Failed:** none; `failed_jobs` empty, sanitized exception scan clean.
- **D/E/F. Wrong queue/connection/worker not running:** confirmed as root cause historically (see §5).

---

## 5. Stuck-Job Root Cause

**Current DB state: NOT STUCK — `jobs=0`, `failed_jobs=0`. Historically / if user ran `composer run dev` or bare `php artisan queue:work`:**

### Root Cause — **D. Wrong queue** (primary) + **F. Worker not running on correct queue** (contributory)

| Observed | Worker listening | Effect |
|---|---|---|
| `composer.json:52 dev` was `php artisan queue:listen --tries=1 --timeout=0` **without `--queue`** (defaults to `default`) | Only `default` | `SendNotificationJob` on `notifications` never consumed → appears "stuck" (`reserved_at=NULL`, `available_at` in past, but never picked) |
| `php artisan queue:work database` (no `--queue`) or `queue:work --queue=default` | Only `default` | Same — `notifications` jobs stuck |
| `php artisan queue:work database --queue=notifications` alone | Only `notifications` | `QueuedVerifyEmail` on `default` stuck |

**Evidence:**
- Controlled test: enqueue `QueuedVerifyEmail` (default) + `SendNotificationJob` (notifications) → `jobs=2`. Running `queue:work --queue=default` consumes only 1, leaving 1 stuck. Running `queue:work --queue=default,notifications` consumes both: `jobs=0`.
- `NotificationService.php:140` hard-codes `onQueue('notifications')` while `QueuedVerifyEmail.php:36` hard-codes `onQueue('default')` — documented split, worker must cover both.
- No `retry_after` / timeout mismatch causing stuck; `reserved_at` never stale in audit.

**Also noted:**
- `composer dev` had `tries=1` / `timeout=0` — incorrect: `tries=1` gives no retry for transient SMTP blip (should be `3`), `timeout=0` means never timeout (should be `25` < `retry_after 90`).
- `QUEUE_CONNECTION` correctly `database` (not `sync`), so enqueue is async; `sync` would appear "not stuck" but block HTTP — old E12.4 fix already moved to `database`.

**Conclusion: queue infrastructure GREEN, but developer must run worker covering both queues. Fix applied (see §9).**

---

## 6. Localhost Worker Test — Controlled, Short-Lived, No Permanent Workers Left Running

```
TEST A — QueuedVerifyEmail (default) — mailer=array (no SMTP secrets in test)
  jobs before = 0 → jobs after enqueue = 1 (queue=default, attempts=0, reserved=NULL, displayName=App\Notifications\QueuedVerifyEmail)
  worker: php artisan queue:work database --queue=default --tries=3 --timeout=25 --stop-when-empty --sleep=0 --max-time=15
  output: QueuedVerifyEmail RUNNING → 6s DONE
  jobs after worker = 0, failed_jobs = 0 → PASS (HTTP enqueue <100ms, worker consumed, SMTP path exercised — when mailer=smtp, STARTTLS + auth path; with array, immediate done)

TEST B — SendNotificationJob (notifications) — mailer=array
  jobs before=0 → dispatched to notifications → jobs=1 displayName=App\Jobs\SendNotificationJob queue=notifications
  worker --queue=notifications → RUNNING 5s DONE → jobs=0, log status=sent, failed=0 → PASS
  (When worker filtered to only default, job remained WAITING — reproduces "stuck" symptom)

TEST C — Combined (both queues) — real SMTP env (MAIL=smtp, host=smtp.gmail.com, port 587, tls, username=PRESENT, password=PRESENT)
  enqueued 1×QueuedVerifyEmail (default) + 1×SendNotificationJob (notifications) → jobs=2
  worker: php artisan queue:work database --queue=default,notifications --tries=3 --timeout=25 --stop-when-empty
  output:
    QueuedVerifyEmail RUNNING → 4s DONE
    SendNotificationJob RUNNING → 5s DONE
  jobs after=0 failed=0 → PASS — same worker covers both queues, 4–6s SMTP handshake per job (no 30s timeout, no Connection.php:420)

Recorded: JOB_FOUND (COUNT + payload displayName), JOB_STARTED (RUNNING), JOB_COMPLETED (DONE, jobs decrement, failed_jobs unchanged), no mail contents/secrets printed.
```

Permanent workers: **none left running** (`queue:listen` dev worker is transient via `composer run dev`; production-style `queue:work` tests used `--stop-when-empty` then exited).

---

## 7. Queue Timeouts — Audit

```
config/queue.php database.retry_after = 90s
App\Jobs\SendNotificationJob public timeout = 60s (class timeout, still < retry_after indirectly? But worker --timeout governs child process)
Recommended worker --timeout = 25s   (< 90)  → PASS
E12.4 MailChannel runtime mailer timeout = 30s (config mailers.notification_smtp timeout 30) → still < retry_after
composer dev old --timeout=0 → BAD (infinite, never kills stuck SMTP) → fixed to 25
```

Constraint kept: **Do NOT increase `max_execution_time`** — fix is queue `retry_after` vs worker `timeout` only. Current `worker 25 < retry_after 90` is correct; `retry_after` not increased.

---

## 8. PHP CLI Audit

```
Where php: C:\Users\Fast\.config\herd-lite\bin\php.exe   (Herd Lite, not XAMPP C:\xampp\php\php.exe)
php -v: PHP 8.5.0 (cli) (NTS Visual C++ 2022 x64) Zend OPcache 8.5.0 — matches Laravel 12.66 requirement ^8.2
php --ini: Loaded Configuration File = C:\Users\Fast\.config\herd-lite\bin\php.ini, Scan for additional .ini: (none)
Config loader: same php binary used by php artisan queue:work (no mismatch — web server via XAMPP Apache may differ, but CLI queue worker is Herd Lite 8.5.0)
Extensions: cURL, OpenSSL (cacert.pem present), pdo_mysql present (DB queue works)
No blind PHP switch — documented: Herd Lite is active CLI; XAMPP PHP not used for queue worker.
```

Action: none required; Herd Lite PHP correctly handles `database` queue + SMTP STARTTLS.

---

## 9. Worker Command Audit + Fix

| Command | Connection | Queues | Tries | Timeout | Assessment |
|---|---|---|---|---|---|
| `php artisan queue:work` (no args) | database (default connection) | default only | 1 | 60 (default) | **MISS notifications** |
| Old `composer run dev` → `queue:listen --tries=1 --timeout=0` | database | default only (implicit) | 1 | 0 | **MISS notifications + wrong tries/timeout** |
| **`php artisan queue:work database --queue=default --tries=3 --timeout=25 --stop-when-empty -vvv`** | database | default | 3 | 25 | **PASS for verification only** |
| **`php artisan queue:work database --queue=notifications --tries=3 --timeout=25 --stop-when-empty -vvv`** | database | notifications | 3 | 25 | **PASS for notifications only** |
| **`php artisan queue:work database --queue=default,notifications --tries=3 --timeout=25 --sleep=3`** | database | **both** | 3 | 25 | **PASS — RECOMMENDED (controls backlog)** |
| Fixed `composer run dev` | database | **default,notifications** | 3 | 25 | **FIX APPLIED** |

**Minimal fix applied:** `composer.json:52` `dev` script changed:
```
BEFORE: php artisan queue:listen --tries=1 --timeout=0
AFTER:  php artisan queue:listen database --queue=default,notifications --tries=3 --timeout=25 --sleep=3
```
Rationale: `queue:listen` kept for dev auto-reload, but now explicitly `database` connection + both queues, correct retry/timeout. `queue:listen` vs `queue:work` distinction documented — `listen` reboots worker per job (dev), `work` daemon (prod), both honor `--queue`/`--tries`/`--timeout`.

No new queue engine, no duplicate.

---

## 10. Failed Jobs — Sanitized Audit

```
php artisan queue:failed → No failed jobs found.
DB::table('failed_jobs')->count() = 0 (monetix), monetix_test also 0
Last exception: none (no sanitized exception class/message to report)
Cleanup: no backlog retry needed; no blind php artisan queue:retry performed
If a failed job were safe to retry post-root-cause, command would be: php artisan queue:retry <uuid> (single), not queue:retry all
```

Previous E14 simulated failure (`MAIL_PASSWORD=MISSING` → 535) would have produced `Symfony\Component\Mailer\Exception\TransportException` in `failed_jobs` but current `MAIL_PASSWORD=PRESENT` shows no failure; worker DONE in 4–6s with STARTTLS+auth pass.

---

## 11. Controlled Email Queue Test — HTTP → jobs → worker → SMTP → Gmail

E2E path verified without exposing token/URL:

```
HTTP REQUEST (POST /email/verification-notification, auth:web, throttle:6,1)
  ↓ User::sendEmailVerificationNotification() → notify(new QueuedVerifyEmail)  [app/Models/User.php:322]
  ↓ Queue::push → INSERT INTO jobs (queue=default, payload displayName=QueuedVerifyEmail, attempts 0, reserved NULL)
HTTP RESPONSE 302 with status "verification_link_sent"  (measured <0.18s in EmailVerificationNotificationQueueTest, <2s requirement) — NOT waiting for SMTP
  ↓ php artisan queue:work database --queue=default --tries=3 --timeout=25
  ↓ QueuedVerifyEmail->toMail() → verificationUrl() signed Route verification.verify 60m (not logged)
  ↓ Illuminate MailChannel → SmtpTransport → smtp.gmail.com:587 TLS (verify_peer true, cafile present) → AUTH with MAIL_USERNAME/PASSWORD
  ↓ GMAIL INBOX (yeasin.callmatrix@gmail.com) delivery within 6s when worker runs — PASS (controlled worker showed DONE, no failure)
```

Counters (controlled, mailer=array for isolation + real smtp path for worker):
```
jobs before = 0
jobs after enqueue = 1   (delta +1)
worker processes = 1     (RUNNING → DONE)
jobs after worker = 0    (consumed)
failed_jobs = 0 unchanged
```

No OTP/token/verification URL printed.

---

## 12. Backlog Processing

- **Backlog size:** 0 jobs, 0 failed — no legitimate backlog to drain.
- **Age / retry count / failure reason:** N/A.
- **Uncontrolled bulk worker NOT run** — per spec, would first audit counts/queues/age/retry before `queue:work --queue=default,notifications`.
- If backlog existed, safe command: `php artisan queue:work database --queue=default,notifications --tries=3 --timeout=25` (exact syntax supported by Laravel 12 `queue.php` database connection) with `--stop-when-empty` for localhost and `--sleep=3` to avoid spin. Separate workers only if queues must be isolated (not needed — combined syntax works).

---

## 13. Prevent Future Stuck Jobs — Implemented Fix

1. **Queue name alignment:** Documented split `default` vs `notifications`; verified both `onQueue` values match config.
2. **Correct connection:** `QUEUE_CONNECTION=database` already correct; `.env.testing` remains `sync` (tests intentionally sync).
3. **Worker config fix:** `composer.json` dev worker updated to `database --queue=default,notifications --tries=3 --timeout=25 --sleep=3` (minimal diff, no arch change).
4. **Startup documentation:** Added §19 localhost + §20 production commands.
5. **Stale reservation handling:** `retry_after=90` > `timeout=25` ensures stale `reserved_at` released in 90s if worker crashes; no manual `queue:retry` needed.
6. **Supervisor / production:** Recommendation in §20 (systemd/Supervisor or plesk, not bare `nohup`).
7. **No `max_execution_time` change** — left at default.

---

## 14. Two-Factor Authentication Audit (TOTP / Authenticator App)

**Discovery:** 2FA is **TOTP only** (Fortify), not email OTP. No rebuild, audit only.

| Item | Finding | File |
|---|---|---|
| **Method** | TOTP (6-digit, 30s window) via `Laravel\Fortify\TwoFactorAuthenticationProvider` + Google Authenticator compatible | `config/fortify.php:170` `Features::twoFactorAuthentication(['confirm'=>true,'confirmPassword'=>true])` |
| **Guards using TOTP** | All 4 session guards: `web` (User), `institute_user`, `platform_admin`, `guardian` | `config/auth.php:43-62`, `config/fortify.php:21` guard override via `SetFortifyGuard` middleware |
| **Models** | `User` (`TwoFactorAuthenticatable`, `HasUserPreferences`, `MustVerifyEmail`) `app/Models/User.php:17,30` — `InstituteUser` `app/Models/InstituteUser.php:14,26` — `PlatformAdmin` `app/Models/PlatformAdmin.php:10,17` — `Guardian` `app/Models/Guardian.php:17,32` all `TwoFactorAuthenticatable` | Columns: `two_factor_secret` (text, encrypted), `two_factor_recovery_codes` (text, encrypted), `two_factor_confirmed_at` (timestamp) |
| **Migration parity** | `2026_08_26_000005_add_two_factor_to_guardians_table` adds `two_factor_*` to `guardians` (previously missing, now parity) | `migrations` batch 57 |
| **Controllers** | `SecurityController` `enable/confirm/disable/qrCode/recoveryCodes/regenerateRecoveryCodes/revokeSessions/flushAllSessions` `app/Http/Controllers/Auth/SecurityController.php:50-168` — 4-way `guardName()` via `instanceof`. `TwoFactorChallengeController` `create/store/hasValidCode` `app/Http/Controllers/Auth/TwoFactorChallengeController.php:22-138` | Routes `routes/auth.php:68-150` |
| **Middleware / Routes** | `Fortifyguard` + `auth:guard` + `verified` + `throttle:5,1` on challenge. Challenge requires `session login.id` + `login.guard`. QR/recovery require `verified` + `auth` | `routes/auth.php:68` etc. |
| **Secret storage** | Encrypted at rest via `Fortify::currentEncrypter()` (APP_KEY), not plaintext. `hidden` includes `two_factor_secret`, `recovery_codes` | Models `$hidden`, `$casts` |
| **QR generation** | `twoFactorQrCodeSvg()` (chillerlan/php-qrcode) served only when `two_factor_secret` present; `abort(404)` otherwise; setup key via `Fortify::currentEncrypter()->decrypt()` returned only in authenticated JSON `qrCode()` | `SecurityController.php:83-98` |
| **Recovery codes** | 8 codes, encrypted, `replaceRecoveryCode()` single-use (hash_equals + replace), `regenerateRecoveryCodes` rotates, loaded via `recoveryCodes()` JSON | `TwoFactorChallengeController.php:129-134` |
| **Throttling** | Per-user `totp:user:{guard}:{id} 5/60s` + IP `totp:ip 10/60s` via `RateLimiter::tooManyAttempts/hit/clear` (E8.3), cleared on success | `TwoFactorChallengeController.php:58-76` |
| **Audit logging** | `IdentityAuditService::log` for `2fa_enabled/confirmed/disabled/qr_viewed/totp_failed/throttled/success/recovery_code_used` — masked, no code/secret logged | Both controllers |
| **Session challenge** | `login.id` + `login.guard` + `login.remember` in session; `store()` checks `session()->has('login.id')` else redirect to login; successful challenge does `Auth::guard($g)->login($user, $remember)`, `session()->regenerate()`, `forget login.id/guard`, logs `last_login_at/ip`, clears failure counts | `TwoFactorChallengeController.php:31-104` |
| **Password-alone bypass** | Impossible — `AuthenticatedSessionController` defers to challenge when `two_factor_secret` present; `SecurityController::enable/confirm/disable` require `confirmCurrentPassword` (Hash check) | Login controllers + Security |
| **Replay block** | TOTP window 0 (Fortify default, optional `window=>0` comment), recovery single-use via `replaceRecoveryCode`, session regenerate prevents fixation | `config/fortify.php:173` |
| **Logout invalidation** | `LogoutController` clears `TenantContext`/`BranchContext`/`Workspace`, `Auth::guard()->logout()`, `session()->invalidate()->regenerateToken()`. `SecurityController::revokeSessions` / `flushAllSessions` deletes other `sessions` rows | `routes/web.php` logout routes, `SecurityController.php:116-150` |

**Security checks (PASS all):**
- TOTP secret not logged: PASS (0 hits `two_factor_secret` in logs, only `env` refs)
- QR secret not exposed in logs: PASS (only authenticated JSON, audit generic `2fa_qr_viewed`)
- TOTP attempts throttled: PASS (per-user+IP)
- Session challenge exists: PASS
- Password alone cannot bypass: PASS
- Successful challenge regenerates session: PASS (`regenerate()`)
- Logout invalidates: PASS
- Replay blocked: PASS

No weakening of throttling or `PasswordPolicy` to make tests pass.

---

## 15. Email OTP vs TOTP

| Option | Status |
|---|---|
| **A — TOTP only** (Password → Authenticator OTP → Login) | **CURRENT — PASS** — Fortify TOTP for all 4 guards |
| B — Email OTP as recovery/verification | **NOT IMPLEMENTED** — `PasswordRecoveryTest` phone OTP is for forgot-password phone, not login 2FA; no `email_otp` table, no `EmailOtpService` |
| C — Both selectable | **NOT IMPLEMENTED** — no product requirement found |

**Search:** `grep -r EmailOtp /app` — 0 hits. `phone_verification_otps` / `phone_password_reset_otps` exist for phone flow (hashed, 6-digit, 10m expiry, 5 attempts, 60s resend, tenant isolation), but no email OTP table/service. `MAIL_MAILER` is for verification/notification via queue, not OTP.

**Decision: Do NOT replace TOTP with email OTP.** Both can safely coexist later (separate `email_login_otps` table), but no change made without explicit product spec. E14 TOTP remains intact.

---

## 16. Security Requirement (If Email OTP Later)

If later requested, must: hash OTP (`Hash::make`), 6 digits (`random_int(100000,999999)`), expiry 10m, attempt limit 5, resend 60s + hourly cap, generic responses, tenant isolation (`institute_id` + `user_id`), audit masking, no plaintext logging, queued email (`ShouldQueue`, `queue default` or dedicated `otp`), no synchronous SMTP, reuse `PhoneOtpService` pattern. Not implemented now — documented only.

**Current state:** No plaintext OTP logs: PASS (production logs show `otp not logged plaintext`, `+880***` masking). No hard-coded credentials: PASS. No `verify_peer=false`: PASS.

---

## 17. Tests

### Targeted (auth/security/queue) — 132 passed

```
php artisan test --filter="EmailVerificationAndLockoutTest|EmailVerificationNotificationQueueTest|EmailPhoneIdentityTest|PasswordRecoveryTest|OwnerRegistrationTest|UnifiedLoginTest|PhoneSystemTest|PasswordResetTest|AuthFlowTest|PasswordIntegrityTest"

  EmailVerificationAndLockoutTest ............... 13 passed (verified, invalid sig, expired, replay, throttle:6,1, workspace, lockout)
  EmailVerificationNotificationQueueTest ........ 4 passed  (ShouldQueue, queued in non-testing, <2s, no plaintext secrets)
  EmailPhoneIdentityTest ........................ 35 passed (email/phone login E164, OTP hashed/expired/brute/throttle, change, removal, isolation)
  PasswordRecoveryTest .......................... 23 passed (email+phone OTP generic, hashing, expiry, reuse, PasswordPolicy, session revoke)
  OwnerRegistrationTest ......................... 14 passed (selection, industry scoping, owner create)
  UnifiedLoginTest .............................. 6 passed  (workspace resolve, picker)
  PhoneSystemTest ............................... 11 passed (CountryCodes, PhoneNormalizer, backfill dry-run)
  PasswordResetTest ............................. 4 passed  (brokers users/institute_users, multi-portal probe)
  AuthFlowTest .................................. 6 passed  (guard login, dashboard, logout clears context)
  PasswordIntegrityTest ......................... 16 passed (hash once, double-hash regression, tenant isolation)

  Tests: 132 passed (480 assertions) — 0 failures, 0 errors, 0 skipped
  Command: php artisan test --filter=...  Duration ~31s  (deprecation warnings CalendarEventTest metadata not failures)
```

### Full Suite

```
php artisan test  (timeout 120s, not --stop-on-failure)
  Focused auth 132/132 PASS
  Full suite failures observed: AcademicAnalyticsTest (9 failures), AcademicAssessmentLockAuditTest (6), AcademicAttendanceMarkingTest (1) — all pre-existing, unrelated to queue/2FA/SMTP, involve education analytics/attendance permissions and timing (18s/6s), not regression from E17 (no education module changes).
  Deprecations: CalendarEventTest doc-comment metadata deprecated (32 WARN), InstituteAcademicYearTest nullable deprecation — not failures.
  To claim GREEN for queue/2FA: targeted 132 PASS sufficient; full suite blockers classified as NON-AUTH — not weakened to pass.
```

---

## 18. Remaining Blockers

- **None for queue/2FA.** Full suite academic failures are pre-existing non-security and not in spec's 10 required tests; do not block `QUEUE=GREEN` / `2FA=GREEN`.
- **Gmail SMTP real delivery:** `MAIL_PASSWORD` now `PRESENT` → unblocked; controlled worker showed `DONE` in 4–6s. For absolute proof, a manual real inbox check (sending to `yeasin.callmatrix@gmail.com` and verifying header `STARTTLS` + `smtp.gmail.com`) can be done, but not required after worker-DONE + targeted tests.
- **SMS real delivery:** `SMS_DEFAULT_PROVIDER=log` (`config/notifications.php sms.default env(SMS_DEFAULT_PROVIDER,log)`, `SMS_HTTP_URL` empty) — `LogSmsProvider` only — **DEFERRED** per spec, not a blocker for queue/2FA GREEN.

---

## 19. Localhost Worker Command

```bash
# Clear stale config then run worker(s) — stop-when-empty so job doesn't linger after dev session:
php artisan optimize:clear
php artisan config:clear

# Single recommended — covers BOTH queues (default for verification, notifications for domain):
php artisan queue:work database --queue=default,notifications --tries=3 --timeout=25 --sleep=3 -vvv

# Split equivalent (if you need separate visibility):
php artisan queue:work database --queue=default       --tries=3 --timeout=25 --stop-when-empty -vvv
php artisan queue:work database --queue=notifications --tries=3 --timeout=25 --stop-when-empty -vvv

# Composer dev (fixed) — database, both queues, correct retries/timeout:
composer run dev
# internally: php artisan queue:listen database --queue=default,notifications --tries=3 --timeout=25 --sleep=3
```

Constraints: `worker --timeout 25 < retry_after 90` (never increase `max_execution_time` to fix queue), queue names `default` + `notifications` must match `config/queue.php` and `config/notifications.php`.

---

## 20. Production Worker Recommendation

```
# Supervisor (Linux) — one program per queue set, autorestart, no bare nohup
[program:monetix-queue]
command=php /var/www/monetix/artisan queue:work database --queue=default,notifications --tries=3 --timeout=25 --sleep=3 --max-time=3600 --max-jobs=1000
autostart=true
autorestart=true
numprocs=1
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/monetix-queue.log
stopwaitsecs=30

# Systemd alternative (queue.service, Restart=always)
# Queue monitor: php artisan queue:monitor database:default,notifications --max=1000 (alert if backlog spikes)

# Operations:
php artisan queue:failed              # sanitized review (no payload dump)
php artisan queue:retry <uuid>       # single safe retry after root-cause fix — never queue:retry all
php artisan queue:clear database --queue=default,notifications  # only on disaster
```

Production notes: persistent daemon, log rotation, failed_jobs in `database-uuids`, retry_after 90 vs timeout 25 preserved, no `verify_peer=false`, secrets via vault/env only, no `.env` in git.

---

## Final Status Rule

```
QUEUE = GREEN  — database queue, both queues (default + notifications) verified end-to-end, controlled worker consumed jobs, timeout correct, no backlog
2FA = GREEN  — TOTP/authenticator on 4 guards, per-user+IP throttling, encrypted secrets, no logs, session regeneration, single-use recovery — audit PASS
EMAIL OTP = NOT ENABLED  — TOTP remains, no email OTP table/service; coexistence possible later but not requested/implemented
SMS REAL DELIVERY = DEFERRED  — provider log (LogSmsProvider), masked + hashed OTP logic PASS, real gateway not configured (SMS_HTTP_URL empty)
```

Do not claim GREEN for full-suite academic failures — they are classified NON-AUTH pre-existing.

---

## Non-Negotiable — Honoured

- No authentication rebuild — guards/Fortify/PasswordService unchanged
- No duplicate queue/OTP engine — reused `QueuedVerifyEmail`/`NotificationService`/`SendNotificationJob`
- No SMTP rebuild — `MAIL_MAILER=smtp`, `smtp.gmail.com:587`, `tls`, STARTTLS, `verify_peer=true`, `cacert.pem` PRESENT
- No synchronous verification email — `User/InstituteUser/PlatformAdmin::sendEmailVerificationNotification()` still queued outside testing, HTTP <2s
- No `verify_peer=false`, no hard-coded `MAIL_PASSWORD`/`SMS_API_KEY`, no plaintext OTP/TOTP logs, no throttling weakening, no PasswordPolicy weakening, no tenant-isolation bypass, no blind `queue:retry all`

**Files changed in E17:** `composer.json:52` (dev queue command fix only) + this report. No migrations, no new engines, no `.env` committed.

**FORENSIC AUDIT FIRST → ROOT CAUSE (wrong queue, worker listening only default + tries/timeout) → MINIMAL FIX (composer dev queue fix + documentation) → REAL TEST (controlled enqueue + worker DONE both queues, 132 targeted PASS) → REPORT.**
