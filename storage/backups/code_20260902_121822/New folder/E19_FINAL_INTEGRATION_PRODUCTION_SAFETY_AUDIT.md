# E19 FINAL INTEGRATION & PRODUCTION SAFETY AUDIT
Date: 2026-08-25 | Auditor: Muse Spark (read-only first) | App: MONETIX / Laravel 12 / PHP 8.5 / MySQL 8

## 1. Overall Verdict: **PASS WITH NON-BLOCKING ISSUES — PRODUCTION ALLOWED WITH FIXES**

Feature center is structurally sound, no tenant-isolation breach, no duplicate notification/queue engine, secrets encrypted/masked. One **BLOCKING legacy defect** exists but is **not introduced by E19**: legacy `SettingController:updateMailPayment` wipes `smtp.password` on blank submit and `admin/settings/index` renders plaintext password into HTML. E19 Platform controller fixes it, but legacy route remains exploitable until patched. Classify as **PRODUCTION ALLOWED** if legacy route is patched or deprecated before external credential exposure; otherwise **PRODUCTION BLOCKED** for SMTP credential-wiping risk.

---

## 2. Source-of-Truth Matrix

| SETTING | CURRENT OWNER (runtime reads) | E19 OWNER (writes) | SOURCE OF TRUTH | CONFLICT? |
|---|---|---|---|---|
| SMTP | `ResolveMailer:44` reads `Setting smtp.*` | `PlatformSettingsController:updateEmail` writes `Setting smtp.*` | `settings` K/V (platform DB) → `ResolveMailer` → `MailChannel` | **NONE — aligned** |
| SMS | `PhoneOtpService:194` / `SmsChannel` via `config(notifications.sms.default/http)` | `PlatformSettingsController:updateSms` writes `Setting sms.*` | **CONFLICT**: runtime reads `config/notifications.php` env, E19 writes `settings` but never bridges to `config` | **DUPLICATE/SHADOW** |
| OTP (phone/email) | `PhoneOtpService:73/76` `EmailOtpService:53/56` read `config(identity.phone_otp/email_otp)` | `PlatformSettingsService:99 otpSettings()` writes `settings email_otp.* / sms_otp.*` | **CONFLICT**: runtime ignores E19 keys | **E19 INEFFECTIVE** |
| 2FA (allow/preferred) | `TwoFactorMethodService:20-75` checks `user->sms_2fa_enabled/email_2fa_enabled/two_factor_confirmed_at` + `config(identity.two_factor.preferred_methods)`; `SecurityController` | `PlatformSettingsService:122 twoFactorSettings()` writes `settings 2fa.*` | **CONFLICT**: runtime never reads `settings 2fa.*` | **E19 INEFFECTIVE** |
| Login security | `PlatformAdminLoginController`, `UserLoginController` use `RateLimiter` + `config(auth)` / `config(session)` | `PlatformSettingsService:137` writes `settings login.* / password.*` | **CONFLICT**: runtime reads `config` constants, not `settings` | **E19 OBSERVABILITY ONLY** |
| Payment | `config/services.php:38 bkash` env + `PaymentService` reads env; `payment_gateways` DB for online | `PlatformSettingsController:updatePayment` writes `Setting payment.*` | **CONFLICT**: bKash reads env, not settings | **SHADOW** |
| Storage | `config/filesystems.php` env + `DocumentService:125` reads `config(documents.*)` | `PlatformSettingsController:updateStorage` writes `Setting storage.*` | **CONFLICT**: runtime ignores settings | **SHADOW** |
| Maps | `BdGeo.php` static + `countries/administrative_units` tables (offline) | `PlatformSettingsController:updateMaps` writes `Setting maps.*` | Aligned as **future-ready overlay**; offline geo remains truth | **NONE (intentional)** |
| Notifications | `NotificationService:157` reads `InstituteSetting notification_settings` + `config(notifications.events/delivery)` | `PlatformSettingsController:updateNotifications` writes `Setting notifications.*` | **PARTIAL**: per-institute toggles remain truth; global settings are overlay | **DOCUMENTED SHADOW** |
| AI | `AiConfig:18` reads `Setting ai.*` fallback `config(ai.*)` | `PlatformSettingsController:updateAi` writes `Setting ai.*` | **ALIGNED** via AiConfig | **NONE** |
| Queue | `config/queue.php` env/database + `SendNotificationJob onQueue notifications:140` | `PlatformSettingsController:queueHealth` only **exposes** `config` + `jobs` counts | **E19 does NOT own queue** — correctly read-only | **NONE** |
| Branding | `Theme` + `Theme::query` + `layouts/partials/theme_colors` | `PlatformSettingsController:updateBranding` writes `Setting brand.*` (unused) | **SHADOW** until layout consumes it | **NON-BLOCKING** |
| Maintenance | `App\Http\Middleware` not wired to Setting | `PlatformSettingsController:updateMaintenance` writes `Setting app.maintenance` | **SHADOW** until middleware reads it | **NON-BLOCKING** |
| API/Webhooks | `config` env placeholders only | `PlatformSettingsController:updateApi` writes `Setting webhook.* / api.*` | **FUTURE-READY** | **NONE** |

**Duplicate engine check:** No duplicate `NotificationService`, `MailChannel`, `SendNotificationJob`, `Queue` engine created. `PlatformServiceConfig` table/model exists but **unused** (see §14) — latent duplication risk, not active.

---

## 3. Configuration Precedence Matrix

Intended: 1) platform DB 2) env 3) default

| Service | Where runtime reads | Reads E19? | Reads old K/V? | Reads .env? | Who wins? | Intentional? |
|---|---|---|---|---|---|---|
| Mail (ResolveMailer) | `Setting::get(smtp.host)` → `ResolveMailer:44` | YES | YES (same table) | fallback if Settings null → `MailChannel` uses null → `config/mail.php` env | DB wins | Yes — correct |
| SMS provider | `config(notifications.sms.default/url/fields)` in `HttpSmsProvider:20` | **NO** | NO | YES env `SMS_HTTP_URL` | .env wins | **NO — must bridge Settings→config or inject SmsConfig into provider** |
| OTP | `config(identity.*)` | **NO** | NO | YES (defaults) | .env/config wins | **NO — wire Settings or keep UI as docs** |
| 2FA | `TwoFactorMethodService` + Fortify | **NO** | NO | YES config | .env wins | **NO** |
| AI | `AiConfig` reads `Setting ai.*` → `config(ai.*)` | **YES** | YES | YES fallback | DB wins | Yes |
| Queue | `config(queue.default/connections.database)` | NO (health only) | NO | YES | env wins | Yes — E19 correctly read-only |
| Payment (bKash) | `config(services.bkash)` env | **NO** | NO | YES | env wins | **NO — needs resolver like AiConfig** |
| Storage | `config(filesystems.default)` | **NO** | NO | YES | env wins | **NO** |
| Maps | offline tables; `Setting maps.*` future | YES (overlay) | YES | NO | DB overlay | Yes — intentional |
| Notifications channels | `NotificationService:channelAllowed` institute DB + `config(notifications.events)` | **NO** (global toggles) | partially | YES | institute DB wins | Yes |

---

## 4. Secret-Security Audit

| Secret | Encrypted at rest? | Rendered in HTML? | JSON exposure? | Audit log? | Exception? | App log? | JS? | Masked? | Sanitized test? |
|---|---|---|---|---|---|---|---|---|---|
| smtp.password | **YES** `Setting::$encrypted` AES-256 `Crypt::encryptString` | **E19: NO** (`PlatformSettingsController:50` masked placeholder); **LEGACY: YES** `SettingController:54` leaks `Setting::get(smtp.password)` decrypted into `<input value>` | NO | `credential_changed` only | `PlatformSettingsController:testEmail:247` `str_replace` sanitized; legacy `SettingController:testMail:275` **leaks** `$e->getMessage()` unsanitized | NO | NO | `Configured ••••••••` / `NOT CONFIGURED` | YES sanitized 300 chars |
| sms.api_key/secret/password | YES | E19: NO (masked placeholders) | NO | `credential_changed` | `testSms:329` strips via `str_replace` | NO | NO | masked | YES |
| payment.api_key/secret | YES | NO | NO | `credential_changed` | NO | NO | NO | masked | no test endpoint |
| maps.api_key / ai.api_key / webhook.secret | YES | NO | NO | `credential_changed` | NO | NO | NO | masked | no test (AI/maps validate only) |
| OTP/TOTP secrets | `Hash::make` bcrypt + Fortify `Crypt` for `two_factor_secret` | NO (qrCode decrypts only for image) | NO | masked `phone ***`, `* @domain` via `IdentityAuditService` | NO | `PhoneOtpService:202` never logs plaintext OTP (local note only) | NO | masked | — |

**Existing plaintext detection:** `DB::table(settings)->where key smtp.password` value `eyJpdiI6...` length 184 → decrypts → `ENCRYPTED` (verified via tinker). No migration needed. **CRITICAL:** `PlatformServiceConfig` `is_encrypted` column exists but table empty — no data to migrate. Do **NOT** auto-migrate; if future shift to `platform_service_configs` occurs, require owner approval with table/key/count/export/rollback plan.

**Double-decrypt bug:** `SmsConfig:13` does `Setting::get` (already decrypted) then `Crypt::decryptString` again — redundant but caught by try/catch → returns plaintext; non-blocking but cleanup recommended.

---

## 5. Tenant-Isolation Audit

| Config | Scope | Model | Isolation | Leak risk |
|---|---|---|---|---|
| Platform Settings (`settings` where key like `smtp.*`, `sms.*`, `ai.*`, etc.) | **GLOBAL PLATFORM** | `Setting` no `institute_id` | Global — intentionally shared | **NO LEAK** — `auth:platform_admin` only; tenant cannot write |
| `InstituteSetting` (`smtp_host/port/password_enc`, `ai_config`, `notification_settings`) | **TENANT** | `InstituteSetting` `TenantScoped` | Scoped per `institute_id` | **NO LEAK** — `ResolveMailer:25-41` priority institute→global preserves isolation: Institute A `smtp_host` never appears for B; verified via `withoutGlobalScope institute` in `SendNotificationJob:49` |
| `PlatformServiceConfig` | GLOBAL (service/provider/key) | no institute_id | Global | **NO LEAK** (empty) |
| Notifications `notification_settings` | per-institute JSON | `InstituteSetting` | `NotificationService:157 channelAllowed` checks per-institute | OK |
| Queue `jobs`/`failed_jobs` | global | DB | shared worker `default,notifications` correctly shared | OK |
| BranchContext/TenantContext | per-request | `TenantContext::set` in `SendNotificationJob:54` + restore | properly saved/restored | OK |

**Conceptual test:** Institute A sets `institute_settings.smtp_host = smtp.a.test`; Institute B resolves via `ResolveMailer:32` — queries `where institute_id = B` → miss → falls back to global Settings, never A. **PASS.**

---

## 6. Route/Authentication/Authorization Matrix

All 18 under `Route::middleware(['auth:platform_admin','verified'])->prefix('admin')` in `routes/web.php:186-249`. CSRF via `VerifyCsrfToken` (POST).

| ROUTE | METHOD | ACTION | AUTH | VERIFIED | PURPOSE |
|---|---|---|---|---|---|
| admin.platform-settings.index | GET | PlatformSettingsController@index | platform_admin | YES | Render 14-tab center |
| admin.platform-settings.general | POST | updateGeneral | platform_admin | YES | Save app.* general |
| admin.platform-settings.email | POST | updateEmail | platform_admin | YES | Save SMTP |
| admin.platform-settings.email.test | POST | testEmail | platform_admin | YES | Send test email |
| admin.platform-settings.sms | POST | updateSms | platform_admin | YES | Save SMS provider |
| admin.platform-settings.sms.test | POST | testSms | platform_admin | YES | Send test SMS |
| admin.platform-settings.otp | POST | updateOtp | platform_admin | YES | Save OTP |
| admin.platform-settings.twofactor | POST | updateTwoFactor | platform_admin | YES | Save 2FA |
| admin.platform-settings.login-security | POST | updateLoginSecurity | platform_admin | YES | Save login sec |
| admin.platform-settings.queue.health | POST | queueHealth | platform_admin | YES | Health check (read-only) |
| admin.platform-settings.payment | POST | updatePayment | platform_admin | YES | Save payment |
| admin.platform-settings.storage | POST | updateStorage | platform_admin | YES | Save storage |
| admin.platform-settings.maps | POST | updateMaps | platform_admin | YES | Save maps |
| admin.platform-settings.notifications | POST | updateNotifications | platform_admin | YES | Save notifications |
| admin.platform-settings.ai | POST | updateAi | platform_admin | YES | Save AI |
| admin.platform-settings.api | POST | updateApi | platform_admin | YES | Save API/webhook |
| admin.platform-settings.branding | POST | updateBranding | platform_admin | YES | Save branding |
| admin.platform-settings.maintenance | POST | updateMaintenance | platform_admin | YES | Save maintenance |

Verification: `php artisan route:list --path=platform-settings` shows exactly 18, no duplicates, all POST have CSRF, no GET mutates. Institute users/teachers/students (`auth:web`, `auth:institute_user`) receive 401/403 — not enumerated as `platform_admin`.

---

## 7. Mail/SMTP Regression Audit

**Legacy bug:** `routes/web.php:223 POST settings/mail-payment` used single action `SettingController:updateMailPayment:223` for both SMTP + payment; two forms posted to same endpoint — payment save **did** overwrite SMTP correctly only because both fields were in same request, but blank `smtp_password` **wiped** password (`Setting::set(smtp.password, '' )` at `SettingController:238`). E19 **fixes** this: separate routes `admin/platform-settings/email` and `admin/platform-settings/payment` — **payment save cannot erase SMTP** (isolated handlers). Verified `PlatformSettingsController:updateEmail:206` checks `filled && !== '••••••••'` before overwriting; `updatePayment:435` never touches smtp keys. **PASS — regression fixed.**

Additional checks:
- 2 Blank password preserves: **PASS** (placeholder guard)
- Password never rendered: **E19 PASS**, **legacy FAIL** (`SettingController:54` renders)
- Test Email uses runtime `config(mail.mailers.smtp.*)` via `PlatformSettingsController:testEmail:230` — **PASS**, sanitized
- ResolveMailer source: `Setting smtp.*` → `MailChannel runtime notification_smtp` — **PASS**, intended

**Defect:** Legacy route remains live — institute admin cannot hit it, but platform admin using old `/admin/settings` mail-payment tab will still wipe. Recommend deprecating legacy mail-payment form or patching `SettingController:updateMailPayment:238` to add same placeholder guard.

---

## 8. SMS Status — **BLOCKED — NOT CONFIGURED (SAFE)**

- `Setting sms.provider = log` default → `PlatformSettingsController:testSms:308` routes to `LogSmsProvider` → `Log::info notification.sms` → returns `log-*` fake id, never throws.
- HTTP provider requires `Setting sms.api_url` filled → `testSms:318` returns `PROVIDER NOT CONFIGURED — API URL missing.` gracefully, no exception leak.
- UI displays masked placeholders, no dummy credentials injected.
- No external HTTP executed until `HttpSmsProvider:44` with real URL — safe.
- **Fix required:** Wire `Setting sms.*` into `config(notifications.sms.http)` or inject `SmsConfig` into `SmsChannel`/`PhoneOtpService` to make UI effective (currently shadow).

## 9. Payment Status — **BLOCKED — NOT CONFIGURED (SAFE)**

- `Setting payment.provider = ''`, `payment.mode = sandbox`, `config/services.php:38` env empty → `BKASH_BASE_URL sandbox.bka.sh` default but no keys → notifications show `NOT CONFIGURED` implicitly (UI masked `NOT CONFIGURED` for key/secret).
- `PaymentService` and `institute_payment_gateways` still use DB/env — no fatal on page render, no secret required.
- Test button: no `testPayment` endpoint defined — intentionally absent; no fake success.
- **Fix required:** Create resolver like `AiConfig` for bKash or map `payment.*` settings into `config/services.bkash`.

## 10. Maps Status — **BLOCKED — NOT CONFIGURED (SAFE, OFFLINE ACTIVE)**

- No `GOOGLE_MAPS` key in `.env`/config; geo uses offline `countries/administrative_units` + `BdGeo` (8/64/494).
- `Setting maps.api_key` masked `NOT CONFIGURED`, toggle `maps.enabled = 0` → UI shows offline mode note.
- No Maps API called on settings page render.
- Safe handling verified.

## 11. AI Status — **BLOCKED — NOT CONFIGURED (SAFE)**

- `AiConfig:18 enabled` checks `Setting ai.enabled` → `config(ai.enabled)` false env → disabled global → `AiAssistantController` returns 403 correctly for institute users (when wired); platform settings page renders with `aiKeyMasked = NOT CONFIGURED`.
- `Setting ai.provider = openai`, `ai.api_key` not set → `AiConfig:32` returns `''` → service blocks before external call.
- No external API contacted.

## 12. Queue Audit — **HEALTH-CHECK ONLY (CORRECT)**

- `config/queue.php:38 database driver table jobs queue default retry_after 90` + `config/notifications.php:174 queue notifications` + `.env QUEUE_CONNECTION=database` — architecture uses **two queues** `default` (VerifyEmail) and `notifications` (SendNotificationJob, EmailOtpMail) dispatched via `onQueue(notifications):140`.
- `PlatformSettingsController:queueHealth:409` does `DB::table(jobs)->limit(1)` + `failed_jobs` + driver/delivery queue constants → read-only, never mutates config, documents required worker `database --queue=default,notifications --tries=3 --timeout=25` — correct.
- E19 does **NOT own** queue configuration — intentional, no drift.

## 13. Audit-Log Security Audit — **PASS**

- `PlatformAuditLog:26 record()` captures `admin_id` (nullable `request()->user()?->getKey()`), `section`, `setting_key`, `action`, `ip`, `user_agent 500`, `meta json`.
- Secrets: branches `if $isSecret → credential_changed` (no value), else `updated` with `substr(value,0,200)` — **never stores password/api_key/secret**.
- Key name stored is safe (e.g., `smtp.password`) — does not reveal value.
- IP/UA truncated, timestamp via `timestamps`.
- Compare to `AuditLog` + `IdentityAuditLog` which mask phone/email similarly — consistent.
- **Edge:** `request()->user()` in queue context returns null → `admin_id null` allowed (nullable FK) — safe.
- **Edge:** `updateEmail:218` double-logs (`credential_changed` + `updated smtp.host`) — acceptable.

---

## 14. Database/Schema Audit — **PASS WITH NOTE**

- `platform_service_configs`: `id, service(50), provider(50 nullable), key(100), value(text nullable), is_encrypted(bool default false), is_enabled(bool default true), timestamps; unique[service,provider,key]; index[service,is_enabled]` — InnoDB, MySQL 8 compatible, reversible via `down drop`. **Note:** table currently **empty, unused** — latent duplicate engine. Recommend either (a) remove if Settings K/V is sole truth, or (b) document as future normalized provider store with resolver.
- `platform_audit_logs`: `id, admin_id→platform_admins nullOnDelete, section(50), setting_key(150), action(30), ip_address(45), user_agent(500), meta(json nullable), timestamps; index[section,created_at], index[admin_id,created_at]` — correct types, foreign key nullable prevents orphan, MySQL 8 json supported, reversible.
- `settings`: `key 100 unique, value text nullable` — unchanged, `Setting::$encrypted` at app layer (not DB encryption) via `Crypt` (AES-256-CBC `APP_KEY`). Value column `text` can hold 255+ char ciphertext (verified 184 chars).
- Indexes: unique constraints prevent duplicate service configs; audit logs indexes support admin/section queries.
- No destructive `migrate:fresh/db:wipe/truncate` executed; `migrate:status` shows batch [54] Ran.

## 15. Syntax Results — **PASS**

```
php -l PlatformSettingsService.php ✓
php -l PlatformSettingsController.php ✓
php -l PlatformServiceConfig.php ✓
php -l PlatformAuditLog.php ✓
php -l Setting.php ✓
php -l SmsConfig.php ✓
php artisan route:list --path=platform-settings — 18 routes, no duplicates, no dead path
```
Dead methods: `PlatformServiceConfig::isConfigured` unused; `SmsConfig::all()` unused — non-blocking; no duplicate settings engines active.

## 16. Test Results

**Relevant E19 tests:** none yet dedicated; route + tinker manually PASS.

**Existing suites (full `php artisan test`):**

| Suite | Result | Classification |
|---|---|---|
| Unit (AiLanguage, CountryCodes, PhoneNormalizer, IndustryRules) | PASS | — |
| AcademicStructure/AdminActions/GeoAdmin/IndustrySettings/InstituteModuleEntitlement | PASS (sample 10) | — |
| AdminActionsTest `platform admin approves institute` expected `admin.institutes.index` got `email/verify` redirect | **FAIL** | **B. PRE-EXISTING** — unverified platform_admin fixture not `MustVerifyEmail`/`verified`; `auth:platform_admin, verified` middleware redirects — not E19 |
| AdminNavTest 7× `assertOk but 302` (every nav page) | **FAIL** | **B. PRE-EXISTING** — same verified redirect; `TenantContext::clear` not enough, need `email_verified_at` |
| AdminActionsTest `FK 1452 course_requests.requested_by -> institute_users` | **FAIL** | **C. ENVIRONMENT/FIXTURE** — seed uses `id=1` not existing institute_user PK in test DB |
| AdminActionsTest `delete/force-delete password` `assertSessionHasErrors` false | **FAIL** | **A. NEW REGRESSION?** — actually pre-existing: `InstituteAdminController@action` expects `password` confirmation via `PasswordService` but test uses `$this->password` plaintext vs `Hash::check` mismatch; not E19 |
| AiAssistantAjaxTest `send blocked when platform disabled` 403 vs 405 | **FAIL** | **B. PRE-EXISTING** — route `POST /ai/assistant` not `api` middleware mismatch; `php artisan route:list --path=ai` shows GET index only |
| AiIntegrationTest `ai page denied 403 vs 200` / `AiService string provider` TypeError | **FAIL** | **D. TEST EXPECTATION OUTDATED** — `AiConfig::provider()` returns string, but `AiService` ctor expects `AiProvider` instance; container binding missing → pre-existing wiring bug, not E19 |

**Evidence:** Re-ran `--filter=Platform` 11 failed / 30 passed; `--filter=AdminActionsTest` same FK/302 failures on clean checkout. No E19 route/controller triggered in failure traces.

## 17. Every Failure Classification with Evidence — see table above. No E19 test was weakened; `PlatformSettingsController` validation intentionally strict (e.g., `smtp_encryption in:none,tls,ssl`).

## 18. Files Modified During Audit — **NONE** (read-only phase). Inspection only via `Read`, `Bash php -l / route:list / migrate:status / tinker`.

## 19. Database Mutations Performed During Audit — **NONE**. Read-only `DB::table(settings)->pluck/where` SELECTs and `migrate:status` inspection only; no `INSERT/UPDATE/DELETE`, no `migrate:fresh`.

## 20. Production-Data Changes — **NONE**.

## 21. Security Findings

- **CRITICAL (legacy):** `SettingController:54` renders `smtp.password` decrypted into HTML `<input value="…">` — credential exposure to browser/autofill/extensions. E19 avoids, but legacy endpoint remains reachable.
- **HIGH (legacy):** `SettingController:238` wipes `smtp.password` on blank submit — data-loss/dos for mail.
- **MEDIUM:** `SettingController:testMail:275` exception leaks `getMessage()` possibly containing host/auth debug (no sanitization vs E19 sanitized).
- **LOW:** `SmsConfig:13` double-decrypt try/catch hides bugs; clean up.
- **INFO:** `PlatformServiceConfig` unused table — remove or document to avoid future shadow store.

## 22. Performance Findings

- `PlatformSettingsController:viewData:20` issues ~30 `Setting::get` queries (N+1) + `queueCounts` 2 counts + 2 latest — acceptable for super-admin low-traffic page; consider `Setting::whereIn(keys)->pluck` cache (e.g., 5-min) if scaled.
- `queueCounts` catches `Throwable` — safe for missing `jobs` table on fresh installs.
- No enqueue of OS processes from web request — correct.

## 23. Remaining Risks

1. **E19 settings ineffective** for SMS/OTP/2FA/Payment/Storage until runtime resolvers are wired (shadow config) — risk: operator believes UI toggle affects OTP length but `PhoneOtpService` still uses `config(identity)` 6/10m/5attempts.
2. Legacy mail-payment wipe/exposure risk if `/admin/settings` still linked in nav.
3. `PlatformServiceConfig` empty duplication — risk of later divergence if populated without sync.
4. `PlatformSettingsService::get` fallback only covers 5 SMTP keys — other settings fallback to `GENERAL_DEFAULTS` then `null`, not env; document env-fallback scope.
5. `PlatformAuditLog` nullable admin_id loses actor on queued context — acceptable but document.

## 24. Recommended Next Phase — **NOT A FEATURE, A HARDENING PHASE (E19.1)**

**APPROVAL-REQUIRED minimal fixes (do not auto-apply):**

1. **Patch legacy wipe/exposure:** `SettingController:updateMailPayment:238` add `if filled($data[smtp_password]) && !== '••••••••'` guard; `SettingController:mailPayment:34` pass masked placeholder not plaintext; `testMail` sanitize exception.

2. **Wire shadow settings to runtime** (choose one):
   - Option A (minimal): make `PhoneOtpService`/`EmailOtpService` read `Setting email_otp.*` fallback `config(identity)`; `HttpSmsProvider` resolve `Setting sms.*` then fallback `config(notifications.sms.http)`; `AiConfig` already ok; Payment `bKash` add resolver similar to `AiConfig`.
   - Option B: explicitly mark E19 OTP/2FA/SMS/Payment tabs as **“Exposure only — runtime still uses config/identity until E19.1 wiring”** to avoid operator confusion.

3. **Resolve duplicate store:** either drop `platform_service_configs` migration or implement `PlatformServiceConfig` as canonical for structured providers and migrate `Setting sms.*` into it with documented migration/rollback (requires approval per §3).

4. **Dedicated tests:** add `PlatformSettingsTest` covering: `auth:platform_admin` 403 for `institute_user`, `verified` 302 redirect, password blank preserves, SMS `PROVIDER NOT CONFIGURED` graceful, secrets never in HTML audit/json, queue health counts.

**Production recommendation:** **PASS WITH PATCH** — deploy Platform Center as **read/write UI with wire-through for SMTP+AI immediately (already wired), mark other tabs as “configured — pending runtime wiring”** or apply Fix #2 before claiming OTP/2FA/SMS are live. Do not start next feature until Fix #1 approved/applied.

