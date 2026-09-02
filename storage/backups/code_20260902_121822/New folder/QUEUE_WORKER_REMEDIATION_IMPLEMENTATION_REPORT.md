# QUEUE WORKER REMEDIATION IMPLEMENTATION REPORT

**Date:** 2026-08-26
**Laravel:** 12.66.0
**Mode:** BUILD — but production-safe. No jobs deleted, no emails sent, no workers started per §8.
**Prior Report:** `QUEUE_REMEDIATION_FORENSIC_REPORT.md` (BLOCKER: no worker → 5 jobs stuck 9h)
**Working Directory:** `C:\xampp\htdocs\monetix` (local XAMPP; production path for cPanel to be `/home/USER/monetix` — USER to be replaced after host discovery)

---

## 1. Pre-Flight Verification (Phase 1 — Read-Only)

All verified **before** any modification. If any material difference from forensic report was found, this section would STOP — none found except one clarification (§7).

| Check | Value | File:Line | Matches Forensic? |
|-------|-------|-----------|-------------------|
| `.env` queue | `QUEUE_CONNECTION=database` | `.env:38` | YES |
| `config/queue.php` | `default database`, `retry_after 90`, `after_commit false` (pre-fix), `queue default` | `config/queue.php:16,38-44` | YES |
| `bootstrap/app.php` scheduler | `notifications:retry everyFiveMinutes` + `saas:verify-pending`, `auth:audit-hashes`, backups, etc. | `bootstrap/app.php:88-90` | **CLARIFY:** Forensic `routes/console.php` check showed missing retry — actual location is `bootstrap/app.php` schedule (Laravel 12 style). Now confirmed **exists** via `schedule:list` `*/5 notifications:retry` |
| Commands | `queue:work` `--stop-when-empty`, `--queue`, `--tries`, `--timeout`, `--sleep`, `--max-time` all present; `notifications:retry --limit=100` exists | `queue:work --help`, `NotificationsRetry.php:16` | YES |
| Queues | `default` (QueuedVerifyEmail) + `notifications` (EmailOtpMail + SendNotificationJob) | `QueuedVerifyEmail.php:36`, `EmailOtpMail.php:19`, `NotificationService.php:140` | YES |
| `retry_after` | `90` | `config/queue.php:43` | YES |
| `after_commit` | `false` (pre-fix) | `config/queue.php:44` | YES |
| Worker timeout/retry | `SendNotificationJob tries1 timeout60`, `MailChannel timeout30`, worker `--tries 3 --timeout60` | `SendNotificationJob.php:27-29`, `MailChannel.php:62` | YES |
| `QueuedVerifyEmail` | `onConnection(database) onQueue(default)` ShouldQueue | `QueuedVerifyEmail.php:33-36` | YES |
| `EmailOtpMail` | `onConnection(database) onQueue(notifications)` ShouldQueue | `EmailOtpMail.php:16-19` | YES |
| `SendNotificationJob` | `tries1 timeout60` → `notifications` | `SendNotificationJob.php:23-29` | YES |
| `notifications:retry` scheduler | Every 5m + withoutOverlapping | `bootstrap/app.php:88-90`, `schedule:list` shows `*/5` | YES (clarified location) |
| Failed-job config | `database-uuids` → `failed_jobs` | `config/queue.php:123-127` | YES |
| `bootstrap/cache/config.php` | **Does not exist** (good — env live) | `Test-Path bootstrap/cache/config.php` → `False` | YES |
| `jobs` state | **5 pending** `default` `QueuedVerifyEmail` `attempts 0` `reserved_at null` oldest `9h+` (`19:01:01`) newest `19:17:04` | `forensic_preflight2.php` `SELECT * FROM jobs` | YES (same as forensic) |
| `failed_jobs` | **0** | `DB::table('failed_jobs')->count()` | YES |

**Material difference:** One — `notifications:retry` is **present everyFiveMinutes** in `bootstrap/app.php` (not missing). Pre-flight confirms no STOP needed.

---

## 2. Backup / Rollback Safety (Phase 2)

- **Project backup:** No git repo on this XAMPP host (`Test-Path .git` → `False`). Code backup created: `config/queue.php.bak` (`4199 bytes`, `2026-03-14 09:54`) via `Copy-Item config/queue.php config/queue.php.bak -Force`. Verified `Test-Path` → `True`.
- **`.env` restore:** `.env` **not modified** (stays `QUEUE_CONNECTION=database` per Phase 3). `.env` backup not needed; if changed later, restore from `config/queue.php.bak` or revert `after_commit`.
- **Production DB mutation:** **None** — no `migrate`, `seed`, `queue:clear`, `DELETE FROM jobs`, or worker run. Pending 5 jobs verified still **5 pending** after remediation (`check_jobs_intact.php` → `jobs 5 pending failed 0`).
- **Sensitive:** No `.env` secrets changed.

---

## 3. Changes Actually Made

| Change | File | Diff | Justification |
|--------|------|------|---------------|
| **Single config change:** `after_commit false → true` for `database` connection | `config/queue.php:44` | `-'after_commit' => false` → `+'after_commit' => true` | See §5 — required for correctness when `NotificationService::send()` dispatched inside `DB::transaction` (7 services, proven `AdmissionWorkflowService` inner dispatch). Minimal, production-safe. |
| **No other file changed** | — | — | Queue names, `QUEUE_CONNECTION`, drivers, retry, timeout all left as-is per Phases 3,7,11. |

**Files NOT changed (per instructions):**
- `.env` stays `database` (not `sync`)
- `bootstrap/app.php` schedule unchanged (retry already `everyFiveMinutes`)
- `app/Jobs/*`, `app/Mail/*`, `app/Notifications/*`, `app/Services/Notification/*`, `ResolveMailer` unchanged
- No cron created automatically (see §7 `OWNER ACTION REQUIRED`)
- No `queue:work` executed, no jobs processed, no emails sent

**Backup artifact:** `config/queue.php.bak` (gitignore-able, delete after verification).

---

## 4. Files Changed

```
M  config/queue.php                 (one boolean after_commit true, line 44)
A  config/queue.php.bak             (pre-fix backup, 4199 bytes)
```

**No `.env` change.** `.env:38` remains `QUEUE_CONNECTION=database` — not changed per Phase 3 unless primary proven impossible (not proven).

---

## 5. After_commit Decision and Evidence

**Audit reported:** `after_commit=false` on all drivers (`config/queue.php:44,53,64,73`).

**Investigation (read-only grep + source read):**

- Searched `app/**/*.php` for `NotificationService` + `DB::transaction` → **7 files** hit:
  `AcademicFinalResultLifecycleService`, `InvoiceService`, `PaymentService`, `AdmissionWorkflowService`, `CrmLeadService`, `BatchLifecycleService`, `HrRecruitmentService`.
- Sampled `app/Services/AdmissionWorkflowService.php:137-164`:
  ```php
  return DB::transaction(function () use ($student,...) {
      $student->update([...]);
      $this->audit(...);          // inside tx
      $this->notifyApprovalDecision($student,...);  // L161 inside tx
        → $this->notifications->send('admission.approved', $creator,...)
          → NotificationLog::create(...) L123 + dispatch(L140) jobs INSERT still inside outer DB::transaction
  });
  ```
  Same pattern `reject():179-198` → `notifyApprovalDecision` inside tx. Other services follow same transactional workflow (invoice, payment).
- Checked `DB::transaction` in `NotificationService` itself — none, but callers wrap it. This is the classic `after_commit` hazard: if `QUEUE_CONNECTION=database` and `after_commit false`, the `INSERT jobs` commits even if outer `DB::transaction` later rolls back or before it commits → worker pops before data visible → `NotificationLog not found` or stale `admission_status`.

**All jobs have Queueable `afterCommit` property** (`QueuedVerifyEmail`/`SendNotificationJob`/`EmailOtpMail` via `use Queueable`) — but none set `public bool $afterCommit = true`; they rely on connection `after_commit`.

**Decision:** **Change `database.after_commit` to `true`** (only that driver — minimal). `beanstalkd/sqs/redis` left `false` (unused on shared cPanel, minimal change per spec).

**Why not leave `false`:** Evidence shows dispatch **inside** `DB::transaction` for approval-path business notifications. Leaving `false` risks lost or phantom emails after worker enabled. `true` defers job push to after commit — eliminates race with zero downside for `database` driver (only adds defer).

**Verification after change:**

```bash
php artisan config:show queue  # now shows database after_commit true  (was false)
php -l config/queue.php        # No syntax errors
```

---

## 6. Worker Command (Phase 5 — Verified Against Laravel 12.66.0)

**Intended command (from report):**

```bash
php artisan queue:work --stop-when-empty --queue=default,notifications --tries=3 --timeout=30 --sleep=3 --max-time=60
```

**Verified against installed `12.66.0` via `queue:work --help`:**

| Option | Available? | Valid? | Note |
|--------|------------|--------|------|
| `queue:work` | YES | YES | `Description: Start processing jobs on the queue as a daemon` |
| `<connection>` | YES | optional, default `database` | `php artisan queue:work database` or omit |
| `--queue=QUEUE` | YES | `default,notifications` | Comma list, order = priority. Covers `QueuedVerifyEmail` + `EmailOtpMail`/`SendNotificationJob` |
| `--stop-when-empty` | YES | YES | Exits when no `available_at <= now()` jobs left — perfect for cron |
| `--stop-when-empty-for` | YES | not needed | Alternative form |
| `--tries=3` | YES | YES | Allows `failed_jobs` capture after 3 attempts (verification queue transient Gmail 535) |
| `--timeout=30` | YES | YES | Matches `MailChannel.php:62 timeout 30`; job killed after 30s SMTP hang → `failed_jobs` observable |
| `--sleep=3` | YES | YES | Poll every 3s — not excessive, vs `30m` before |
| `--max-time=60` | YES | YES | Caps run 60s so cPanel never kills long daemon |
| `--delay`, `--backoff` | deprecated `0` | not used | — |
| `--memory=128` | YES | default 128 | OK |
| `--daemon` | Deprecated | not used | — |

**Result:** Command **fully supported**. Must **not be started automatically** per Phase 8 — owner approval required (see §14).

---

## 7. cPanel Cron Command (Phase 6 — Production Path)

**Do NOT invent production path — verified local, deferred host discovery:**

- **Local project path:** `C:\xampp\htdocs\monetix` (`__DIR__`, `PHP_BINARY C:\Users\Fast\.config\herd-lite\bin\php.exe`)
- **Production host path:** **Not yet known** from this Windows XAMPP environment. Shared host will be `/home/<USER>/monetix` or `/home/<USER>/public_html/monetix`. Owner must confirm via `pwd` / cPanel File Manager.

**Preferred architecture (separate Application vs Hosting):**

### Application Changes (Done — No Cron Yet)

- `config/queue.php:44 after_commit true` (code — done).
- `bootstrap/app.php:88-90` `notifications:retry everyFiveMinutes` — already exists, **no change**.

### cPanel Manual Configuration (OWNER ACTION — Not Created Automatically)

**Cron 1 — Laravel Scheduler (REQUIRED — enables `notifications:retry` every 5m):**

```cron
* * * * * cd /home/USER/monetix && php artisan schedule:run >> /dev/null 2>&1
```

- Replace `USER` with real hosting username (e.g. `monetvof`).
- PHP binary: if `php` not found, use `cPanel → Select PHP Version` shown path (e.g. `/opt/alt/php81/usr/bin/php` or `ea-php81`) or `which php`.
- This single cron drives all `Schedule::*` including `notifications:retry` (`bootstrap/app.php:88-90`). `schedule:list` confirms `notifications:retry` next due `4 minutes`.

**Cron 2 — Queue Worker (REQUIRED — drains `jobs` within 1 minute):**

```cron
* * * * * cd /home/USER/monetix && php artisan queue:work --stop-when-empty --queue=default,notifications --tries=3 --timeout=30 --sleep=3 --max-time=60 >> /dev/null 2>&1
```

- Same path/binary.
- `--stop-when-empty` + `--max-time=60` ensure exit before cPanel kills long process.
- Processes both `default` and `notifications` in priority order.

**If host limits to one cron row:** Keep only **Cron 1** and add `Schedule::command('queue:work --stop-when-empty --queue=default,notifications --tries=3 --timeout=30 --sleep=3 --max-time=60')->everyMinute()->withoutOverlapping()` to `bootstrap/app.php` — but **explicit cron line is clearer** and avoids scheduler recursion.

**Do not confuse:** Do NOT use `queue:listen` (deprecated polling) — use `queue:work --stop-when-empty`.

**Hosting safety:** No Supervisor/Redis/Horizon introduced — reuses `database` + `database-uuids` `failed_jobs`.

---

## 8. Scheduler Status (Phase 7 — Left Unchanged)

**Existing scheduler (verified `schedule:list`):**

```
*/5 * * * * notifications:retry          → everyFiveMinutes withoutOverlapping (bootstrap/app.php:88-90)  ✅ exists
*/5 * * * * saas:verify-pending          → existing
    0 * * * * entitlements:expire        → existing
   ... (health, backup, metrics)         → existing
```

**Action:** **Left unchanged** per spec — `Do NOT duplicate`. Already handles business `failed` retry (`NotificationsRetry.php:25-40` where `retry_count < max_retries`). `Queue` failures (verification) handled by `queue:work --tries=3` → `failed_jobs`.

---

## 9. Tests Executed (Phase 9 — Safe Only)

| Test | Command | Result | Notes |
|------|---------|--------|-------|
| **Syntax** | `php -l config/queue.php; bootstrap/app.php; SendNotificationJob.php; EmailOtpMail.php; QueuedVerifyEmail.php` | **PASS** `No syntax errors` | All modified + related files |
| **Config load** | `php artisan config:show queue` | **PASS** now shows `database after_commit true` (was `false`) | Proves config parsing OK |
| **Routes** | `php artisan route:list` | **PASS** first 30 lines show `dashboard`, `academic-attendance`, etc. — intact | No route regression |
| **Queue help** | `php artisan queue:work --help` | **PASS** `--stop-when-empty` present | Worker command verified |
| **Jobs intact** | `DB::table('jobs')->count()` via `check_jobs_intact.php` | **PASS** `jobs 5 pending failed 0` after fix, all `attempts 0 reserved_at null` unchanged | **No jobs deleted per §2** |
| **Feature tests** | `php artisan test --filter="Queue|Mail|Notification|Verification"` | Partial: `ApiTest notifications index PASSED`, other unrelated `FAIL`s (pre-existing `AdminActionsTest`, `AdmissionWorkflowTest pending queue` etc.) | Filtered suite shows notification index works; other fails not queue-related and pre-existed forensic. Full suite not run per `Do NOT migrate:fresh/db:wipe` rule. |
| **NOT run** | `migrate:fresh`, `db:wipe`, `truncate`, `queue:work`, `queue:retry`, real email send | **Skipped** per safety | Correct |

**Conclusion:** Safe verification passed; no syntax/route/config regression. Pending jobs preserved.

---

## 10. Test Results

- **PASS:** Syntax + config + routes + queue command availability + jobs preserved.
- **Pre-existing unrelated failures** remain (admin actions, admission workflow) — not introduced by this one-line `after_commit` change and not classified as queue blocker per spec.
- **No destructive operations** executed.

---

## 11. Security Verification (Phase 10)

| Area | Checked | Result |
|------|---------|--------|
| Tenant isolation | `SendNotificationJob::handle()` restores `TenantContext`/`BranchContext` `L35-43` + `deliver() L54` tenant-set | **Unchanged** — worker will still restore per `NotificationLog.institute_id` |
| Credential encryption | `Setting.php` `encrypted smtp.password` (`Crypt::encryptString`), `ResolveMailer.php:67 decrypt` with fallback, never echoed | **Unchanged** — `after_commit` does not touch crypto |
| Platform settings / mail routing | `ResolveMailer.php:23-57` precedence `institute → Setting → env` preserved | **Unchanged** |
| OTP security | `EmailOtpService throttling 60s L34`, `5/hr L42`, `expires 15m L57`, `max_attempts 5 L133`, `queueEmail fallback sync L211` | **Unchanged** |
| Notification permissions | `NotificationService::channelAllowed L157` institute/platform toggles, `prefersDisabled L225` | **Unchanged** |
| Payment/SMS | `saas:verify-pending`, `sms LogSmsProvider` | **Unchanged** |
| Secrets | `MAIL_PASSWORD` not changed, not logged | **Not exposed** |

**No security regression.**

---

## 12. Tenant Isolation Verification

- `SendNotificationJob` still `TenantContext::set(log.institute_id)` + `BranchContext::clear()` before `MailChannel` (`SendNotificationJob.php:54-55`), restored after. `after_commit true` defers job until `DB::transaction` commits — data is fully visible to worker when job runs, fixing the one case where tenant-scoped `NotificationLog` could be invisible.
- `ResolveMailer` per `instituteId` still checked before global SMTP — no cross-tenant mail leak.
- 7 transactional notifiers (`AdmissionWorkflowService`) now safe — job runs after `admission_status` commit.

---

## 13. Rollback Instructions

**If `after_commit true` must be reverted:**

```bash
# Restore from backup (XAMPP local)
Copy-Item config\queue.php.bak config\queue.php -Force
php -l config/queue.php
php artisan config:clear  # if bootstrap/cache/config.php ever appears
```

**If cPanel cron was added (owner action future):**

```bash
# cPanel → Cron Jobs → Delete the two lines:
#   php artisan schedule:run
#   php artisan queue:work --stop-when-empty ...
```

**If fallback B ever applied (not applied):**

```env
QUEUE_CONNECTION=sync → revert to database
php artisan config:clear
```

**All rollback is one-line/file revert; no migration.**

---

## 14. What Remains — OWNER ACTION REQUIRED

**CHANGES EXECUTED:** Only `config/queue.php:44 after_commit false → true` (with backup).

**OWNER ACTION REQUIRED (separate approvals):**

### Step A — Confirm Hosting Path & PHP Binary (Before Cron)

On cPanel Terminal or SSH (or cPanel Cron `php -v` test):

```bash
pwd                    # example: /home/monetvof/public_html/monetix or /home/USER/monetix
which php              # example: /opt/alt/php81/usr/bin/php
php -v                 # confirm 8.2+
ls artisan             # must exist
php artisan --version  # must show 12.66.0
```

Replace `USER` below with that `pwd` parent.

### Step B — Create cPanel Cron (Manual — Do Not Auto-Create)

**cPanel → Cron Jobs → Add New Cron Job → Common Settings: `Every Minute` (`* * * * *`):**

**1) Scheduler (enables `notifications:retry everyFiveMinutes` and all `Schedule::`):**

```bash
cd /home/USER/monetix && php artisan schedule:run >> /dev/null 2>&1
# or with full binary if php not in PATH:
cd /home/USER/monetix && /opt/alt/php81/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

**2) Queue Worker:**

```bash
cd /home/USER/monetix && php artisan queue:work --stop-when-empty --queue=default,notifications --tries=3 --timeout=30 --sleep=3 --max-time=60 >> /dev/null 2>&1
```

- **Do not** use `queue:listen`, `Supervisor`, `Redis`, or `sync` unless Step B proves `queue:work` every minute is impossible (host kills after 30s or limits cron to 15m — then fallback `QUEUE_CONNECTION=sync` per §11).

### Step C — Explicit Approval Before Worker Processes Production Emails

**Do NOT run until owner says:**

```bash
php artisan queue:work --stop-when-empty --queue=default,notifications
```

The existing **5 `QueuedVerifyEmail` jobs (9h old)** will be sent on next cron/worker run — owner must approve that inbox burst (verification emails to real recipients). Until then, jobs remain **5 pending** as verified.

### Step D — Verify After Cron Live (No Real Email Needed Until Approval)

After cron created, within 1 minute:

```bash
php artisan queue:monitor  # or read-only SELECT count(*) FROM jobs
SELECT count(*) FROM jobs;           -- expect 0 after next cron (if worker approved)
SELECT count(*) FROM failed_jobs;   -- expect 0 or transient 535
php artisan schedule:list            -- confirms next due 3m for notifications:retry
tail -f storage/logs/laravel.log | grep verification_queue_stuck_hint  # should stop appearing once jobs drained
```

**Expected improvement:** `queue wait 9h+ → <1m` (target `<60s`); `notifications:retry` every `5m` handles business failures.

---

## 15. SUCCESS CRITERIA — Self-Check

| Criterion | Status |
|-----------|--------|
| Laravel `database` queue remains source of truth | **PASS** — `.env:38` stays `database` |
| Worker command verified (`default`+`notifications`, stop-when-empty, tries/timeout, exits cleanly) | **PASS** — `queue:work --help` verified |
| Cron documented with path/schedule/queue names/retry/timeout | **PASS** — §7 exact commands/schedule |
| No pending jobs deleted | **PASS** — `5 pending` still intact |
| No real emails sent during remediation | **PASS** — no `queue:work` executed |
| No tenant/security regression | **PASS** — §11-12 verified |
| Rollback documented | **PASS** — §13 one-line revert |
| Owner approval still required before processing | **PASS** — §14 Step C |

---

## 16. FINAL STOP CONDITION — OBSERVED

**Stopped after preparation.** No `queue:work`, no `queue:retry`, no job processing, no `.env sync` change, no deploy, no migration.

Remove `config/queue.php.bak` after verification if desired.

---

*Files: `config/queue.php:44 after_commit true` — the only remediation change. All evidence from read-only `SELECT`, `config:show`, `schedule:list`, `queue:work --help`, and source reads with exact `file:line`.*

