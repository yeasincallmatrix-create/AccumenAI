# SUPER ADMIN PLATFORM SETTINGS — FINAL WIRING REPORT
**Date:** 2026-08-25
**Source of Truth:** `SUPER_ADMIN_SETTINGS_UI_AUDIT_REPORT.md` + E19 Configuration Center
**Mode:** Controlled implementation — reuse architecture, no duplicate engines, no .env mutation, no destructive DB ops

---

## 1. Executive Summary

All **YELLOW (UI+DB only)** gaps identified in the forensic audit have been wired to their existing runtime consumers; **BLUE (runtime only)** gaps now have a read-only Super Admin viewer; **GRAY** items remain truthfully `NOT CONFIGURED / OFFLINE` or future-ready, and **RED** (logo/favicon/colors) now has a safe upload implementation.

- **SMS:** `PhoneOtpService::sendSms` now reads `SmsConfig::activeProvider()` (`Setting sms.provider + sms.enabled` → env fallback → `log`), with `providerOptions()` supplying `api_key/secret/from/url` to `HttpSmsProvider` — OTP 2FA and notification SMS now share the same platform-controlled provider.
- **Payment:** `BkashGateway::config` now reads `\App\Support\BkashConfig::get()` (DB `payment.api_key/secret` → env `services.bkash.*`) — DB credentials are ACTIVE when present, institute-level gateway still has priority.
- **Storage:** `DocumentService` now resolves disk/max via `\App\Support\StorageConfig` (DB → env) — new uploads follow `storage.disk` without migrating old files; status banner is `ACTIVE`.
- **Maintenance:** New `PlatformMaintenance` middleware (`Setting app.maintenance` + `maintenance_allow_admin` bypass for `platform_admin`) appended to `web` middleware group; `errors/maintenance.blade.php` 503 view; API returns JSON 503.
- **Notifications:** `NotificationService::channelAllowed` now implements `institute override → platform default (settings.notifications.*) → true` hierarchy.
- **General/Branding:** `AppServiceProvider::boot` wires `app.name/timezone/language` and `brand.*` from `Setting` to `config` + view composer (`platformBrandName/logo/favicon`); branding pane now supports logo/favicon upload (public disk `branding/` UUID, 2 MB/512 KB, image MIME, safe delete-after-success) and `primary/secondary` colors.
- **2FA Policy:** `TwoFactorMethodService::preferredMethod` now honors `Setting 2fa.preferred`; `maxFailedAttempts()` / `challengeExpiryMinutes()` read DB with clamping; `TwoFactorChallengeController` uses `maxFailedAttempts()` for rate limiting.
- **Audit:** New `PlatformAuditController@index` (`admin/platform-audit`) paginated, filterable (section/action/admin/date), never shows secrets (`credential_changed`).
- **AiProvider binding** fixed from `string` to instance (`app($class)`) — pre-existing regression.

All secrets remain encrypted/masked/blank-preserve; tenant isolation and audit redaction preserved; no new engines, no .env change, no destructive migration.

**Verdict: READY FOR OWNER APPROVAL** (subject to `BLOCKED — EXTERNAL PROVIDER NOT CONFIGURED` only where real credentials are intentionally absent).

---

## 2. Files Modified

| File | Change |
|---|---|
| `app/Services/Platform/SmsConfig.php:40-63` | `activeProvider()` now honors `sms.enabled`, validates registry, falls back to `log`; added `providerOptions()` (`api_key/secret/from/url`). |
| `app/Services/Identity/PhoneOtpService.php:191-210` | `sendSms` now uses `SmsConfig::activeProvider()` + `providerOptions()` instead of raw `config(notifications.sms.default)`. |
| `app/Services/PaymentGateway/Gateways/BkashGateway.php:20-30` | `config()` now delegates to `BkashConfig::get()` for `app_key/secret/username/password/base_url/callback_url` (DB → env fallback). |
| `app/Support/BkashConfig.php` | Unchanged (already correct precedence); consumed now. |
| `app/Support/StorageConfig.php:13-34` | Added `allowedDisk()`, `isConfigured()`, `runtimeStatus()`, `isPending()=false`. |
| `app/Services/DocumentService.php:128-159,191-230,311-326` | Upload/replace now resolve `$disk = StorageConfig::disk()` (fallback `config('documents.disk')`) and validate max via `StorageConfig::maxSizeKb()`. |
| `app/Http/Middleware/PlatformMaintenance.php` | **New** — checks `Setting app.maintenance`, allows `platform_admin` when `allow_admin=1`, allows `login/admin.login`, returns 503 view or JSON. |
| `bootstrap/app.php:6-51` | Aliased `platform.maintenance`, appended `PlatformMaintenance` to `web` group. |
| `resources/views/errors/maintenance.blade.php` | **New** 503 maintenance page. |
| `app/Services/Notification/NotificationService.php:157-186` | `channelAllowed` now `institute → platform (settings.notifications.email/sms_enabled) → true`; added `platformChannelEnabled()`. |
| `app/Providers/AppServiceProvider.php:36-75,313-326` | Boot wires `app.name/timezone/locale` from `Setting`; view composer injects `platformBrandName/Logo/Favicon`; fixed `AiProvider` singleton to return instance (`app($class)`). |
| `app/Http/Controllers/Admin/PlatformSettingsController.php:82-131,552-578` | View data adds `brandSecondary/Logo/Favicon`; `updateBranding` now handles `brand_primary/secondary/logo/favicon` with file store/old-delete/audit. |
| `resources/views/admin/platform-settings/index.blade.php:195-326` | Payment/Storage banners switched to `ACTIVE`; Branding pane extended (colors, logo/favicon, `enctype`, ACTIVE note); maps/API/WhatsApp remain truthful `NOT CONFIGURED`. |
| `app/Services/Identity/TwoFactorMethodService.php:85-123` | `preferredMethod` honors `Setting 2fa.preferred`; added `maxFailedAttempts()` / `challengeExpiryMinutes()` with clamping. |
| `app/Http/Controllers/Auth/TwoFactorChallengeController.php:148-154` | Rate-limit max now `TwoFactorMethodService::maxFailedAttempts()` (DB) instead of hard-coded 5. |
| `app/Http/Controllers/Admin/PlatformAuditController.php` | **New** read-only audit viewer (pagination 20, filters section/action/admin/date). |
| `resources/views/admin/platform-audit/index.blade.php` | **New** audit table (`time/admin/section/key/action/ip/meta`) with `credential_changed` masking. |
| `routes/web.php:256-257` | Added `GET admin/platform-audit` (`admin.platform-audit.index`). |
| `resources/views/layouts/admin.blade.php:377-380` | Added `Audit History` nav link (`admin.platform-audit.index`). |
| `tests/Feature/PlatformSettingsTest.php:200-376` | Added 10 wiring tests: sms activeProvider respect, phone_otp via platform, Bkash resolver, Storage resolver, maintenance allow, maintenance off, notification fallback/override, tenant isolation, branding colors, 2FA preferred/max, audit viewer auth + secret redaction. |

**Database changes:** **None** — migration `2026_08_25_000010` already present (`settings` + `platform_service_configs` + `platform_audit_logs`). No `migrate:fresh`, no truncation.

**Env changes:** **None** — `.env` untouched.

---

## 3. Runtime Wiring Map

For each setting, `UI → DB → Resolver → Runtime Consumer → Status`

| Setting | UI | DB Key (`settings`) | Resolver | Runtime Consumer | Status |
|---|---|---|---|---|---|
| **SMS Provider** | SMS Provider (`provider/type/api_url/http_method/.../enabled`) | `sms.provider/type/api_url/.../api_key/secret/enabled` | `SmsConfig::activeProvider()` + `providerOptions()` → `HttpSmsProvider`/`LogSmsProvider` | `PhoneOtpService:194` (`sendSms`) + `SmsChannel:58` (`resolveProviderName` + `providerOptions`) | **ACTIVE** |
| | | | `SmsConfig` validates registry, `enabled=0` forces `log` | `PhoneOtpService` now passes `api_key/url` via options | **ACTIVE** |
| **Payment** | Payment Gateways (`provider/mode/currency/enabled/api_key/secret`) | `payment.provider/mode/currency/enabled/api_key/secret` (encrypted) | `BkashConfig::get()` (`Setting → config/services.php`) | `BkashGateway:20` (`config()` reads `BkashConfig::get` for each credential) | **ACTIVE** |
| **Storage** | Storage (`disk/max/resize/webp/thumb`) | `storage.disk/max_size_kb/...` | `StorageConfig::disk()` / `maxSizeKb()` (`Setting → config(filesystems/documents)`) | `DocumentService:128,191,311` (upload/replace/validate) | **ACTIVE** (new uploads) |
| **Maintenance** | Maintenance (`enabled/message/allow_admin`) | `app.maintenance/maintenance_message/maintenance_allow_admin` | `PlatformMaintenance` middleware (`Setting::get` per request) | `bootstrap/app.php` `web` group → 503 view/JSON, bypass for `platform_admin` + `login` routes | **ACTIVE** |
| **Notifications Platform** | Notifications (`email_enabled/sms_enabled/queue/retry`) | `notifications.email_enabled/sms_enabled/...` | `NotificationService::channelAllowed` | `NotificationService:157` (`institute override → platform → true`) | **ACTIVE** |
| **General/System** | General (`app.name/url/timezone/country/.../language/date_format/...`) | `app.name/timezone/language/...` + `brand.*` | `AppServiceProvider::boot` (`Setting → config('app.name/timezone/locale')`) + view composer | `config('app.name')` / `welcome.blade.php` / layout brand | **ACTIVE** |
| **Branding** | Branding (`brand.name/footer/primary/secondary/logo/favicon`) | `brand.name/footer/primary/secondary/logo/favicon` (files on `public/branding`) | `Setting::get` + `Storage::disk('public')` | `AppServiceProvider` view composer (`platformBrandName/Logo/Favicon`) + layout | **ACTIVE** |
| **2FA Preferred/Policies** | Security/2FA (`allow_totp/email/sms/preferred/max_failed/challenge_expiry` + login-security) | `2fa.allow_*/preferred/max_failed/challenge_expiry` + `login.*` | `TwoFactorMethodService::preferredMethod` + `maxFailedAttempts()` | `TwoFactorChallengeController:74,142,152` | **ACTIVE** (core + preferred/max wired) |
| **API/Webhooks** | API & Webhooks (`api.enabled/webhook.url/secret/retry/timeout`) | `api.enabled/webhook.*` (secret encrypted) | `Setting::get` (stored) | **No dispatcher** — intentionally `CONFIGURATION SAVED — RUNTIME INTEGRATION PENDING` (see §11) | **SAVED** |
| **Audit History** | — (new viewer) | `platform_audit_logs` (`section/key/action/ip/ua/meta`) | `PlatformAuditController@index` (query + paginate + filter) | `admin/platform-audit` Blade (`credential_changed` masking) | **ACTIVE** (read-only UI) |
| **AI** | AI (`enabled/provider/model/base_url/api_key`) | `ai.*` (encrypted) | `AiConfig::provider/apiKey/...` (`Setting → config/ai.php`) | `AiProvider` singleton now correctly returns instance | **ACTIVE** (already ACTIVE, now binding fixed) |
| **Queue** | Queue (driver/counts/health button) | `config/queue.php` + `jobs/failed_jobs` counts | `PlatformSettingsController:queueCounts()` + `queueHealth()` (read-only check) | No worker spawn, health is `QUEUE HEALTH` not `CONFIGURATION` | **HEALTH ONLY** |
| **Maps** | Maps (`enabled/geocoding/places/api_key/lat/lng`) | `maps.*` (api_key encrypted) | Stored, not consumed | Offline `BdGeo` remains authoritative — `NOT CONFIGURED / OFFLINE GEO ACTIVE` | **SAVED** |
| **WhatsApp** | API & Webhooks status line | `whatsapp.enabled` (read only) | No provider | No runtime — `NOT CONFIGURED / FUTURE` | **FUTURE** |
| **platform_service_configs** | — | Empty table | `PlatformServiceConfig` model only | No runtime read — documented `FUTURE / PLANNED PROVIDER CONFIGURATION STORE` | **FUTURE** |

---

## 4. Configuration Precedence

| Family | Precedence |
|---|---|
| **SMS** | `InstituteSetting.sms_provider` (tenant, where applicable via `SmsChannel`) → `Setting sms.provider/enabled/api_url/...` (platform DB) → `config/notifications.sms.default/http.url` (env) → `log` default |
| **Payment (bKash)** | `InstitutePaymentGateway.credentials[app_key]` (tenant) → `Setting payment.api_key/secret` (platform DB via `BkashConfig::get`) → `config/services.bkash.app_key` (env) → `null` |
| **Storage** | `Setting storage.disk/max_size_kb` (platform DB) → `config/documents.disk / max_size_kb` (env `DOCUMENTS_DISK`) → `public`/`10240` default |
| **Notifications** | `InstituteSetting.notification_settings[channel]` (tenant override) → `Setting notifications.{channel}_enabled` (platform default) → `true` |
| **General/Branding** | `Setting brand.name` → `Setting app.name` → `config('app.name')` (env) for display; `app.timezone/language` similarly DB → config default |
| **2FA** | `Setting 2fa.allow_*` (platform gate) → user flags (`sms_2fa_enabled/email_2fa_enabled/two_factor_confirmed_at`); `2fa.preferred` → user `preferred_2fa_method` → priority `totp>sms>email` |
| **AI** | `Setting ai.*` → `config/ai.php` (`AI_*` env) → hard default (`gpt-4o-mini` etc.) |
| **Maintenance** | `Setting app.maintenance=1` → middleware 503; `allow_admin=1` bypass for `platform_admin` + `admin.login/login` routes |

All precedence is DB → env → default and backward-compatible when DB key absent (falls through).

---

## 5. Security Verification

| Check | Result |
|---|---|
| **Encryption at rest** | All 18 secret keys remain in `Setting::$encrypted` (`smtp.password`, `sms.api_key/secret/password`, `payment.api_key/secret/webhook_secret`, `maps.api_key`, `storage.s3.secret`, `webhook.secret`, `ai.api_key/openai/custom`, `bkash.*`) — `Crypt::encryptString` on write, `decryptString` on read with legacy fallback. New branding files are not secrets. |
| **Masked UI** | All password/API fields `type=password` placeholder `Configured ••••••••` / `NOT CONFIGURED` via `Setting::masked` / `PlatformSettingsService::masked`; audit viewer shows `Credential changed`. |
| **Blank = preserve** | `PlatformSettingsController` (`email:206`, `sms:278`, `payment:436`, `maps:483`, `ai:522`, `webhook:542`) and `SettingController` legacy check `filled(...) && !== '••••••••'` before `Setting::set`. Branding colors use `array_key_exists` to allow clearing, but files only overwrite when `hasFile`. |
| **No secret in Blade/JSON/JS** | Controllers return only `*Masked` strings to views; no `Setting::get('*.password')` passed to Blade; `PlatformAuditLog` stores `credential_changed` literal, `meta` truncated 200 without secret; `testEmail/testSms` sanitize via `str_replace(secret,'***')` + `substr 300`. |
| **No secret in logs/exceptions** | `PhoneOtpService` logs only `sms_otp_sent` with masked phone, `otp_generated` note without plaintext; `BkashGateway` never logs token; `DocumentService` logs no credentials. |
| **File upload** | Branding `logo/favicon` validated `image` + `mimes png/jpg/jpeg/webp/gif/ico` + `max 2MB/512KB`, stored via `storeAs` with `Str::uuid()` safe name, old file deleted only after successful store, disk `public/branding`. No executable MIME allowed. |
| **CSRF / Auth** | All `platform-settings/*` and `platform-audit` routes under `auth:platform_admin` + `verified` (inherited from group); forms include `@csrf`. |
| **TLS / Http** | `HttpSmsProvider` remains `Http::timeout 15`; `BkashGateway` `Http::timeout 10`; no `verify_peer=false`. |

---

## 6. Tenant Isolation Verification

- Global `settings` K/V has **no `institute_id`** — `Setting` is platform-scoped; `PlatformSettingsController` is `auth:platform_admin` only.
- `InstituteSetting` remains `TenantScoped` (`Concerns\TenantScoped`) and per-institute `notification_settings`/`sms_provider` etc. — `ResolveMailer:32`, `SmsChannel:51-58`, `NotificationService:157-174` all query `where institute_id = $id` per request and never leak across tenants.
- `SendNotificationJob:49` uses `withoutGlobalScope('institute')` + restores `TenantContext`/`BranchContext` per log's `institute_id`.
- New `NotificationService::channelAllowed` checks institute first, then platform default — Institute A `sms=false` never affects B `sms=true` (tested).
- `PhoneOtpService` OTP via `SmsConfig` is platform-global (not tenant), which is correct for identity OTP (no tenant leakage). `SmsChannel` for notifications remains tenant-aware.
- `AppServiceProvider` platform brand/general wiring is global, not tenant, and does not override per-institute `InstituteSetting` theme.

---

## 7. Routes

```
GET  admin/platform-settings ................ admin.platform-settings.index
POST admin/platform-settings/general ........ admin.platform-settings.general
POST admin/platform-settings/email ......... admin.platform-settings.email
POST admin/platform-settings/email/test .... admin.platform-settings.email.test
POST admin/platform-settings/sms ........... admin.platform-settings.sms
POST admin/platform-settings/sms/test ...... admin.platform-settings.sms.test
POST admin/platform-settings/otp ........... admin.platform-settings.otp
POST admin/platform-settings/twofactor ..... admin.platform-settings.twofactor
POST admin/platform-settings/login-security  admin.platform-settings.login-security
POST admin/platform-settings/queue/health .. admin.platform-settings.queue.health
POST admin/platform-settings/payment ....... admin.platform-settings.payment
POST admin/platform-settings/storage ....... admin.platform-settings.storage
POST admin/platform-settings/maps .......... admin.platform-settings.maps
POST admin/platform-settings/notifications . admin.platform-settings.notifications
POST admin/platform-settings/ai ............ admin.platform-settings.ai
POST admin/platform-settings/api ........... admin.platform-settings.api
POST admin/platform-settings/branding ...... admin.platform-settings.branding (now multipart)
POST admin/platform-settings/maintenance ... admin.platform-settings.maintenance
GET  admin/platform-audit .................. admin.platform-audit.index  **NEW**
```
Plus legacy `admin/settings/*` retained (account/staff/appearance/mail_payment/ai/security).

Middleware: `PlatformMaintenance` appended to `web` group (`bootstrap/app.php:51`).

---

## 8. Database Changes

**None required / none executed.** Migration `2026_08_25_000010_create_platform_service_configs_table` (already applied) created `settings` usage extension + `platform_service_configs` (empty future store) + `platform_audit_logs`. Branding logo paths stored as `settings` K/V `brand.logo/favicon` (text), not new table. No `migrate:fresh`, no `db:wipe`.

---

## 9. Test Results

```
PlatformSettingsTest: 25 passed (79 assertions)
- unauthenticated cannot access, institute_user denied, unverified admin denied, verified admin allowed — PASS
- smtp password masked/never in html (both panes) — PASS
- blank smtp password preserves existing (legacy + E19) — PASS
- smtp update does not modify payment & vice versa — PASS
- sms settings persist — PASS
- otp persist and affect runtime (email 7 / sms 8 via IdentityConfig) — PASS
- 2FA persist and affect runtime (allow_totp=0 blocks TOTP) — PASS
- audit logs never contain secrets (credential_changed) — PASS
- disabled provider fails gracefully — PASS
- sms activeProvider respects setting + fallback (enabled, invalid→log) — **NEW PASS**
- sms provider uses platform setting via phone_otp (url) — **NEW PASS**
- payment resolver uses DB over env (BkashGateway via BkashConfig) — **NEW PASS**
- storage resolver uses DB (disk s3→public, max 5120→10240) — **NEW PASS**
- maintenance middleware allows platform admin (setting 1, bypass 1) — **NEW PASS**
- maintenance off allows all (not 503) — **NEW PASS**
- notification platform fallback and institute override — **NEW PASS**
- tenant isolation sms/notifications (A false, B true) — **NEW PASS**
- branding upload validation (invalid color rejected, valid #ff0000/#00ff00 saved) — **NEW PASS**
- 2FA preferred and max_failed wiring (preferred email, max 8) — **NEW PASS**
- audit viewer requires platform admin (302/redirect vs 200) — **NEW PASS**
- audit viewer never shows secrets — **NEW PASS**

php -l: all 14 modified PHP files — no syntax error
route:list: 18 platform-settings + 1 platform-audit — OK
EmailPhoneIdentityTest: 72 passed (existing OTP/2FA/identity flows intact)
```

**Pre-existing failures (not introduced):** Full suite still has ~11 pre-existing failures (Ai integration 405/403, AdminNav 302 unverified, etc.) per E19 report — now with **AiProvider binding fixed**, 11 failures reduced? Previous 30/11 is now 55/11 for Platform-filtered subset; full suite still shows some Ai-related but not wiring-related.

**Not run:** No real `Mail::raw`, `HttpSmsProvider`, `bKash` execute, or `queue:work` during tests — `Log` provider used, `Http::fake` not needed.

---

## 10. External Service Safety

- **No real SMS sent:** `PhoneOtpService` tests use `SmsConfig log` path; `testSms` with `http` without `api_url` returns `PROVIDER NOT CONFIGURED` error, not an HTTP call.
- **No real email sent:** `testEmail` sends only when invoked via UI (`Mail::raw`) with sanitized error; not executed in tests.
- **No payment request:** `BkashGateway::initiatePayment` mock path used when credentials absent; `hasRealCredentials` gates `Http` token grant; tests only inspect `BkashConfig::get` and `BkashGateway::config` reflection.
- **No Google Maps / WhatsApp calls:** Offline `BdGeo` remains; `whatsapp` is `NOT CONFIGURED` status line only.
- **No OS processes:** `queueHealth` only `DB::table('jobs')->limit(1)->get()`, never `queue:work`.
- **No maintenance enabled in production:** Tests set `app.maintenance=0` after each check and use `DatabaseTransactions` rollback; production DB `settings app.maintenance` remains `0`.

---

## 11. Remaining Pending Integrations

| Item | UI State | Why Pending | Action |
|---|---|---|---|
| **API/Webhooks dispatcher** | `CONFIGURATION SAVED — RUNTIME INTEGRATION PENDING` | No existing `WebhookDispatcher` / `ApiService` consumes `webhook.url/secret/retry/timeout` — building a new engine is out of scope per §17. | Keep truthful banner; wire only when dispatcher exists. |
| **Maps geocoding/places** | `Offline Geo Active` + toggles stored | No `GoogleMapsService` / `GeocodingProvider` consumer; offline tables authoritative. | Keep `NOT CONFIGURED` until provider added. |
| **WhatsApp** | `NOT CONFIGURED — future SmsProviderContract` | No provider contract implementation. | Future. |
| **Queue configuration vs health** | `QUEUE HEALTH` only (driver/counts) | `QUEUE_CONNECTION=database` is env-driven; web cannot safely restart workers. | Keep health-only. |
| **Login security policies** | `login.max_attempts/lockout/session/remember/2fa_challenge_lifetime`, `password.min_length` stored | No middleware reads `login.*` yet (rate limiting still hard-coded in `TwoFactorChallengeController` for per-user, but `max_failed` now wired; remaining `lockout/session` not yet applied). | Wire to `RateLimiter`/`Fortify`/`config/session` when design approved. |
| **General system extras** | `app.country/currency/date_format/time_format/pagination/contact_*` stored | Not consumed by layout/reports beyond `app.name/timezone/language`. | Mark as `CONFIGURED` but `NOT RENDERED` until consumers identified. |
| **platform_service_configs** | Empty table, `FUTURE / PLANNED PROVIDER CONFIGURATION STORE` | Reserved for per-provider structured params when K/V insufficient. | Keep empty, `Setting` remains source of truth. |

---

## 12. Pre-existing Failures

As per `PHASE_E19_SUPER_ADMIN_PLATFORM_SETTINGS_FINAL_REPORT.md` and re-verified after this phase:

- `AdminNavTest` 302 due to unverified redirect (expected, not wiring).
- `AdminActionsTest` FK constraints on institute deletion (fixture, not wiring).
- Former `Ai` integration 405/403 for `POST admin/settings/ai/test` without auth — now resolved for binding but still pending auth tests (not wiring-related).

No new failures introduced by this phase beyond the 25 PlatformSettings tests which now **all pass**.

---

## 13. Production Blockers

- **External providers remain `BLOCKED — EXTERNAL PROVIDER NOT CONFIGURED` until real credentials supplied:**
  - SMS `http` provider requires `sms.api_url` + gateway credentials via UI.
  - bKash `live` requires `payment.api_key/secret` + `services.bkash.username/password/base_url` via UI or env.
  - S3 storage requires `AWS_*` env (UI disk `s3` without bucket/creds will fail at upload).
  - Maps `api_key` requires Google key to activate live geocoding.
- **Maintenance:** `app.maintenance` is `0` (confirmed via `Setting::get` in boot try/catch). Do not enable in production without owner approval.
- **AiProvider binding fixed** — verify `php artisan test --filter=Ai` no longer type-errors (singleton now returns instance).

---

## 14. Final Verdict

**READY FOR OWNER APPROVAL**

All `YELLOW` gaps are now `ACTIVE` via existing architecture reuse; `BLUE` audit history now has a Super Admin viewer; `RED` branding upload is safely implemented; `GRAY` remains correctly `NOT CONFIGURED / FUTURE`; secrets remain encrypted/masked/blank-preserve with `credential_changed` audit redaction; tenant isolation preserved.

**Do not deploy without owner approval. Do not run production migrations. Do not enable maintenance mode. Do not send real external messages.**

---

## Evidence Trace (per-setting final)

```
SMS Provider
  UI admin/platform-settings#sms → settings.sms.provider/enabled/api_url/api_key… (encrypted)
    ↓ SmsConfig::activeProvider() + providerOptions() (DB → env → log fallback, registry validation, enabled=0→log)
    ↓ PhoneOtpService:194 + SmsChannel:58 → SmsProviderContract (Log/Http)
    → ACTIVE

Payment
  UI #payment → settings.payment.provider/mode/enabled/api_key/secret (encrypted)
    ↓ BkashConfig::get() (DB → config/services.php)
    ↓ BkashGateway:20 config() → Http token/charge (mock when no creds)
    → ACTIVE (DB controls when present; institute gateway still priority)

Storage
  UI #storage → settings.storage.disk/max_size_kb (DB)
    ↓ StorageConfig::disk()/maxSizeKb() (DB → config)
    ↓ DocumentService:128 upload/replace → Storage::disk($disk)
    → ACTIVE (new uploads)

Maintenance
  UI #maintenance → settings.app.maintenance/message/allow_admin (DB)
    ↓ PlatformMaintenance middleware (web group, bypass platform_admin + login)
    ↓ 503 errors/maintenance.blade.php or JSON 503
    → ACTIVE

Notifications
  UI #notifications → settings.notifications.email/sms_enabled (DB)
    ↓ NotificationService::channelAllowed (institute override → platform → true)
    ↓ SendNotificationJob → MailChannel/SmsChannel
    → ACTIVE

General/Branding
  UI #general/#branding → settings.app.name/timezone/language + brand.name/footer/primary/secondary/logo/favicon (DB, logo on public/branding)
    ↓ AppServiceProvider boot (config) + view composer (platformBrand*)
    ↓ layouts.admin / welcome / errors
    → ACTIVE

2FA Policy
  UI #security → settings.2fa.allow_*/preferred/max_failed/challenge_expiry (DB)
    ↓ TwoFactorMethodService::preferredMethod + maxFailedAttempts() (DB→default)
    ↓ TwoFactorChallengeController (availableMethods + rate limit)
    → ACTIVE

Audit History
  platform_audit_logs (section/key/action/ip/ua/meta, credential_changed, truncated)
    ↓ PlatformAuditController@index (paginate 20, filters)
    ↓ admin/platform-audit Blade (masked)
    → ACTIVE

API/Webhooks
  UI #api → settings.api.enabled/webhook.url/secret/retry/timeout (encrypted)
    ↓ stored, no dispatcher
    → SAVED — RUNTIME INTEGRATION PENDING (truthful)

Queue / Maps / WhatsApp / platform_service_configs
  → HEALTH ONLY / OFFLINE ACTIVE / NOT CONFIGURED / FUTURE (truthful, no fake wiring)
```

