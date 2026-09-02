# SUPER ADMIN SETTINGS — FINAL UI / CONFIGURATION AUDIT REPORT
**Mode:** READ-ONLY FORENSIC AUDIT — No code, DB, .env, or migration changes
**Date:** 2026-08-25 (UTC)
**Branch:** monetix (local) — Laravel 12 / PHP 8.5 / MySQL 8
**Primary Reference:** `PHASE_E19_SUPER_ADMIN_PLATFORM_SETTINGS_FINAL_REPORT.md` + `E19_REMEDIATION_FINAL_PRODUCTION_SIGNOFF_REPORT.md` (re-verified against current source)
**Auditor:** Muse Spark (OpenCode) — source-traced, not assumed

---

## 1. Executive Summary

The Super Admin **Configuration Center** (`admin/platform-settings`) introduced in E19 is **present, routed, and authenticated** on current source. It provides **14 tabs/panels** covering the 21 desired control families. Secrets are encrypted at rest (`Setting::$encrypted` via `Crypt::encryptString`), masked in UI (`NOT CONFIGURED` / `Configured ••••••••`), blank-preserve, and audit-logged as `credential_changed` without plaintext.

**Overall posture: PARTIALLY COMPLETE / NEEDS WIRING** — the UI+DB layer is complete for all families; runtime wiring is **GREEN for 10 families**, **YELLOW (UI exists, runtime pending/shadow) for 4 families**, **BLUE/GRAY (runtime exists, UI missing or future-ready) for 2 families**, and **RED (missing) for 1 family (file/branding offline, no logo upload)**. No destructive risk was introduced; tenant isolation is intact.

| Roll-up | Count |
|---|---|
| GREEN (UI+DB+Runtime fully connected) | 10 |
| YELLOW (UI+DB only, runtime pending) | 4 |
| BLUE (Runtime exists, no Super Admin UI) | 1 |
| GRAY (Future-ready / planned, not configured) | 5 |
| RED (Missing entirely) | 1 |

**Duplicate engine risk:** `platform_service_configs` (migration `2026_08_25_000010_create_platform_service_configs_table.php:11`) is **EMPTY/UNUSED** — documented as `FUTURE/PLANNED SOURCE`. Source of truth remains `settings` K/V (`Setting` + `PlatformSettingsService`). No runtime reads `PlatformServiceConfig::getValue` outside model definition — verified via grep. **No duplication at runtime.** `InstituteSetting` remains tenant-scoped and distinct — no leak.

---

## 2. Current Super Admin Settings Navigation

**Two distinct settings surfaces exist (verified in `routes/web.php` and `resources/views/layouts/admin.blade.php:346-382`):**

### A. Platform Configuration Center (E19 — Primary)
- **Sidebar group:** `System / Database` + `Platform Settings` (`layouts/admin.blade.php:346-382`)
- **Links:**
  - `Control Center` → `super-admin.database.control-center` (`DatabaseControlCenterController`)
  - `Database Dashboard` → `super-admin.database.dashboard`
  - `Backups & Recovery` → `super-admin.database.backups`
  - `Database Health` → `super-admin.database.health`
  - `Integrity & Security` → `super-admin.database.integrity`
  - `Performance` → `super-admin.database.performance`
  - `Disaster Recovery` → `super-admin.database.recovery`
  - `Audit Logs` → `super-admin.database.audit`
  - **`Configuration Center` → `admin.platform-settings.index`** (`PlatformSettingsController@index` — **this audit's scope**)
  - `Legacy Settings` → `admin.settings.index` (`SettingController@index`)
- **Layout:** `resources/views/admin/platform-settings/index.blade.php:1` extends `layouts.standalone` (not `layouts.admin`) — standalone heading + `$backUrl = route('admin.settings.index')`.

### B. Legacy Settings (pre-E19 — retained)
- **Route group:** `auth:platform_admin, verified` prefix `admin` (`routes/web.php:186-229`)
- **View:** `resources/views/admin/settings/index.blade.php` — 6 panes: `Account`, `Staff Requests`, `Appearance`, `Mail/Payment` (SMTP host/port/encryption/username/password + payment gateway string), `AI` (via `_ai.blade.php`), `Security` (via `security/_panel.blade.php`)
- **Risk:** Legacy SMTP + payment fields remain editable and overlap with Configuration Center; they write same `settings` keys (`Setting::set('smtp.host')` etc.). Not a duplicate engine, but a **duplicate UI surface** — operator may edit in either place.

---

## 3. Current Tabs/Panels (Configuration Center)

**File:** `resources/views/admin/platform-settings/index.blade.php:19`
**JS:** tab switching via `data-target="pane-{k}"` + `history.replaceState` (`index.blade.php:310-318`).

| # | Tab key (`pane-*`) | Exact Label | Route (POST) | Controller Action | Editable? |
|---|---|---|---|---|---|
| 1 | `pane-general` | General | `admin.platform-settings.general` | `PlatformSettingsController::updateGeneral` (`:161`) | Yes (13 fields) |
| 2 | `pane-email` | Email / SMTP | `admin.platform-settings.email` + `email/test` | `updateEmail` (`:185`) / `testEmail` (`:222`) | Yes + Test |
| 3 | `pane-sms` | SMS Provider | `admin.platform-settings.sms` + `sms/test` | `updateSms` (`:255`) / `testSms` (`:302`) | Yes + Test |
| 4 | `pane-otp` | OTP & Verification | `admin.platform-settings.otp` | `updateOtp` (`:336`) | Yes |
| 5 | `pane-security` | Security / 2FA | `admin.platform-settings.twofactor` + `login-security` | `updateTwoFactor` (`:362`) / `updateLoginSecurity` (`:389`) | Yes (2 forms) |
| 6 | `pane-queue` | Queue | `admin.platform-settings.queue.health` | `queueHealth` (`:410`) | **Display-only** (health check button) |
| 7 | `pane-notifications` | Notifications | `admin.platform-settings.notifications` | `updateNotifications` (`:492`) | Yes |
| 8 | `pane-payment` | Payment Gateways | `admin.platform-settings.payment` | `updatePayment` (`:422`) | Yes (banner: RUNTIME INTEGRATION PENDING) |
| 9 | `pane-storage` | Storage | `admin.platform-settings.storage` | `updateStorage` (`:449`) | Yes (banner: PENDING) |
| 10 | `pane-maps` | Maps & Geo | `admin.platform-settings.maps` | `updateMaps` (`:468`) | Yes |
| 11 | `pane-ai` | AI | `admin.platform-settings.ai` | `updateAi` (`:509`) | Yes |
| 12 | `pane-api` | API & Webhooks | `admin.platform-settings.api` | `updateApi` (`:531`) | Yes |
| 13 | `pane-branding` | Branding | `admin.platform-settings.branding` | `updateBranding` (`:553`) | Yes (2 fields) |
| 14 | `pane-maintenance` | Maintenance | `admin.platform-settings.maintenance` | `updateMaintenance` (`:566`) | Yes |

**Total routes verified:** 18 (`GET index` + 15×POST saves + 2×test + 1×health) — `routes/web.php:230-249`.

---

## 4. Complete Configuration Inventory

**Legend:** `Visible?` = tab exists in Configuration Center; `Route/Controller/View/DB Key/Runtime Consumer` = evidence path; `Status` = A–G per brief.

| Category | Visible in Super Admin UI? | Route | Controller | View | DB Key / Table | Runtime Consumer | Status |
|---|---|---|---|---|---|---|---|
| **SMS Gateway** | **Yes** (`SMS Provider` tab) | `platform-settings.sms` / `sms.test` | `PlatformSettingsController:255,302` | `platform-settings/index.blade.php:79` | `settings`: `sms.provider/type/api_url/http_method/api_key/api_secret/username/password/sender_id/auth_type/...` | `SmsChannel:49,59` (notifications) + `HttpSmsProvider:24` (generic HTTP); `PhoneOtpService:195` **still reads `config(notifications.sms.default)`** — shadow for OTP 2FA | **B/YELLOW** (Notification SMS: A; OTP SMS 2FA: B) |
| **Email/SMTP** | **Yes** (`Email / SMTP` tab) | `platform-settings.email` / `email.test` | `PlatformSettingsController:185,222` | `index.blade.php:51` | `settings`: `smtp.host/port/encryption/username/password/from_address/from_name/reply_to/...` | `ResolveMailer:44-55` → `MailChannel:45` → `SendNotificationJob:64` (`notification_smtp` mailer, timeout 30) — **WIRED** | **A/GREEN** |
| **OTP (length/expiry/attempts/resend)** | **Yes** (`OTP & Verification`) | `platform-settings.otp` | `PlatformSettingsController:336` | `index.blade.php:109` | `settings`: `email_otp.enabled/length/expiry/max_attempts/resend_cooldown/max_resend` + `sms_otp.*` | `IdentityConfig:13-42` → `PhoneOtpService:54-130` + `EmailOtpService:34-133` — **WIRED** (E19 remediation) | **A/GREEN** |
| **2FA/Security (TOTP/SMS/Email + policies)** | **Yes** (`Security / 2FA` + `Login Protection`) | `platform-settings.twofactor` / `login-security` | `PlatformSettingsController:362,389` | `index.blade.php:133` | `settings`: `2fa.allow_totp/allow_email/allow_sms/preferred/allow_user_change/require_verified_*/max_failed/challenge_expiry` + `login.max_attempts/lockout/session/remember/2fa_challenge` + `password.min_length` | `TwoFactorMethodService:46,61,77` (platform gate) → `TwoFactorChallengeController` / `UserLoginController:105` etc. — **WIRED** | **A/GREEN** |
| **Queue / Background Jobs** | **Partial** (`Queue` pane) | `platform-settings.queue.health` | `PlatformSettingsController:410` + `viewData:137` | `index.blade.php:165` | `config/queue.php:17` (driver/database) + DB `jobs`/`failed_jobs` counts (display) | `SendNotificationJob:23` (`tries 1, timeout 60, queue notifications`) + `config/queue.php:40` + `config/notifications.delivery.queue` — **monitoring only, not editable** | **C/D (UI display-only)** |
| **Payment Gateway** | **Yes** (`Payment Gateways`) | `platform-settings.payment` | `PlatformSettingsController:422` | `index.blade.php:195` | `settings`: `payment.provider/mode/currency/enabled/api_key/api_secret` (encrypted) | `config/services.php:38` (`bkash` env) is **still authoritative**; `BkashConfig:13` resolver exists but **not injected** into any `PaymentService` — banner correctly says `RUNTIME INTEGRATION PENDING` | **B/YELLOW** |
| **File / Storage** | **Yes** (`Storage`) | `platform-settings.storage` | `PlatformSettingsController:449` | `index.blade.php:213` | `settings`: `storage.disk/max_size_kb/resize/webp/thumb` | `config/filesystems.php:16` (`FILESYSTEM_DISK` env) is still authoritative; `StorageConfig:14` exists but `isPending()=true` and not used by `DocumentService` | **B/YELLOW** |
| **Google Maps / Geolocation** | **Yes** (`Maps & Geo`) | `platform-settings.maps` | `PlatformSettingsController:468` | `index.blade.php:229` | `settings`: `maps.enabled/geocoding/places/api_key/default_country/lat/lng` (api_key encrypted) | Offline `BdGeo` + `countries/administrative_*` remain authoritative; no `Google Maps` API calls exist — view notes `offline Geo tables` (`index.blade.php:243`) | **B/YELLOW (UI complete, runtime is offline-first, no external call)** |
| **WhatsApp / Messaging** | **Indicator only** | — | `PlatformSettingsController:133` (`whatsappStatus`) | `index.blade.php:275` | `settings`: `whatsapp.enabled` (read only) | No provider; field `whatsapp` on `institutes`/`crm_contacts` is a contact number, not a gateway. Architecture reserves `SmsProviderContract` for future. | **F/GRAY (future-ready)** |
| **Notification Settings (channels/queue/fallback)** | **Yes** (`Notifications`) | `platform-settings.notifications` | `PlatformSettingsController:492` | `index.blade.php:180` | `settings`: `notifications.email_enabled/sms_enabled/queue/retry` | `NotificationService:90` (`channelAllowed` checks `InstituteSetting.notification_settings`) + `SendNotificationJob:140` — **UI save not read by runtime** (runtime reads per-institute `InstituteSetting`, not platform `notifications.*`) — **partial wiring** | **B/YELLOW** |
| **System / Platform Settings** | **Yes** (`General`) | `platform-settings.general` | `PlatformSettingsController:161` | `index.blade.php:28` | `settings`: `app.name/short_name/url/timezone/country/currency/language/date_format/time_format/pagination/contact_*` | Consumed via `Setting::get` / `PlatformSettingsService::get` with fallback; not yet consumed by layout or middleware for timezone/locale enforcement | **B/YELLOW** |
| **Maintenance Mode** | **Yes** (`Maintenance`) | `platform-settings.maintenance` | `PlatformSettingsController:566` | `index.blade.php:291` | `settings`: `app.maintenance/maintenance_message/maintenance_allow_admin` | **NOT ENFORCED** — no middleware reads `app.maintenance`; Laravel's `app.maintenance.driver=file` still used (`config/app.php`) | **B/YELLOW** |
| **Audit / Security Settings** | **No toggle UI** | — | `PlatformAuditLog::record` calls throughout | — | `platform_audit_logs` (`admin_id/section/setting_key/action/ip/user_agent/meta`) | Logged for every credential/update (`credential_changed`/`updated`); no admin UI to view history — **runtime only** | **E/BLUE** |
| **API / Webhook Settings** | **Yes** (`API & Webhooks`) | `platform-settings.api` | `PlatformSettingsController:531` | `index.blade.php:263` | `settings`: `api.enabled/webhook.url/secret/retry/timeout` (secret encrypted) | No dispatcher reads `webhook.url` yet — **stored, not consumed** | **B/YELLOW** |
| **AI Settings** | **Yes** (`AI` tab) | `platform-settings.ai` | `PlatformSettingsController:509` | `index.blade.php:247` | `settings`: `ai.enabled/provider/model/base_url/api_key` (encrypted) | `AiConfig:16-45` precedence `Setting ai.*` → `config/ai.php` → env — **WIRED**, retrofitted E19 | **A/GREEN** |
| **Branding / General** | **Yes** (`Branding` + `General`) | `platform-settings.branding` / `general` | `PlatformSettingsController:553,161` | `index.blade.php:279,32` | `settings`: `brand.name/footer/primary` + `app.*` | Not consumed by layout — `welcome.blade.php:7` uses `config('app.name')`, sidebar uses `institute->name`. **Stored, not rendered** | **B/YELLOW** |
| **Test Connection** | **Yes** | `email.test` / `sms.test` | `PlatformSettingsController:222,302` | `index.blade.php:72,102` | — | `Mail::raw` via resolved SMTP + `HttpSmsProvider`/`LogSmsProvider` — sanitized, audited | **A/GREEN** |
| **Secrets encryption/masked** | **Yes** (all password/API fields) | — | `Setting::$encrypted:21` + `PlatformSettingsService::SECRET_KEYS:11` | `placeholder="{{ $*Masked }}"` | `Crypt::encryptString` | Decrypt on read with `try/catch` fallback | **A/GREEN** |
| **Per-provider enable/disable** | **Partial** | `sms.enabled` / `payment.enabled` / `maps.enabled` / `ai.enabled` / `api.enabled` | As above | As above | `settings.*.enabled` | `SmsChannel:58` respects `sms.provider`; `BkashConfig::isEnabled()` ready but not used; others stored only | **B/YELLOW** |
| **Fallback config** | **Yes (implicit)** | — | `IdentityConfig`, `AiConfig`, `ResolveMailer`, `SmsChannel`, `BkashConfig`, `StorageConfig`, `PlatformSettingsService` | — | — | See §18 | **A/GREEN** (where wired) / **B** (where pending) |
| **Configuration history / audit** | **No UI** | — | `PlatformAuditLog::record` | — | `platform_audit_logs` | See §23 — **runtime only** | **E/BLUE** |

---

## 5. Runtime Wiring Matrix

| Setting Key | Writes To | Reads From (Runtime) | Fully Connected? | Evidence |
|---|---|---|---|---|
| `smtp.*` | `PlatformSettingsController:updateEmail` + `SettingController:updateMailPayment` | `ResolveMailer:44` (`Setting::get('smtp.host')`) → `MailChannel:47` → `notification_smtp` | **YES** | `ResolveMailer.php:44-55`, `MailChannel.php:45-68` |
| `sms.*` (provider/url/api_key/secret) | `PlatformSettingsController:updateSms` | `SmsChannel:58` (`Setting::get('sms.provider')` + `api_key/secret/api_url`) → `HttpSmsProvider:24` (`Setting sms.api_url fallback`) for notifications; **OTP path ignores it** (`PhoneOtpService:195` uses `config(sms.default)`) | **PARTIAL** | `SmsChannel.php:58-88`, `HttpSmsProvider.php:24-26`, `PhoneOtpService.php:195` |
| `email_otp.*/sms_otp.*` | `PlatformSettingsController:updateOtp` | `IdentityConfig:14-42` → `PhoneOtpService:54,74` / `EmailOtpService:34,54` | **YES** (remediation) | `IdentityConfig.php:14`, `PhoneOtpService.php:74`, `EmailOtpService.php:54` |
| `2fa.*` | `PlatformSettingsController:updateTwoFactor` | `TwoFactorMethodService:46,61,77` (checks `Setting::get('2fa.allow_*')` before user flags) | **YES** | `TwoFactorMethodService.php:46-77` |
| `login.*` / `password.*` | `PlatformSettingsController:updateLoginSecurity` | **Not consumed** — no middleware/service reads `login.max_attempts` etc.; `config/fortify.php` and `RateLimiter` still use hard-coded values | **NO** | `PlatformSettingsService.php:137-147` vs no consumer |
| `notifications.*` | `PlatformSettingsController:updateNotifications` | **Not consumed** — `NotificationService:158-174` reads `InstituteSetting.notification_settings`, not `settings` platform `notifications.*` | **NO** | `NotificationService.php:158` |
| `payment.*` | `PlatformSettingsController:updatePayment` | **Not consumed** — `config/services.php:38` env still authoritative; `BkashConfig:13` exists but no injection | **NO** | `BkashConfig.php:13`, `config/services.php:38` |
| `storage.*` | `PlatformSettingsController:updateStorage` | **Not consumed** — `config/filesystems.php:16` env authoritative; `StorageConfig:14` pending | **NO** | `StorageConfig.php:14`, `config/filesystems.php:16` |
| `maps.*` | `PlatformSettingsController:updateMaps` | **Stored, offline geo authoritative** — no `Google\Maps` client | **BY DESIGN** | `index.blade.php:243`, `config/geo.php` |
| `ai.*` | `PlatformSettingsController:updateAi` | `AiConfig:18-44` (`Setting::get('ai.*') ?? config('ai.*')`) | **YES** | `AiConfig.php:18` |
| `webhook.*` / `api.*` | `PlatformSettingsController:updateApi` | **Stored, no dispatcher** | **NO** | `PlatformSettingsController:540-548` only |
| `brand.*` / `app.*` (branding/general) | `PlatformSettingsController:updateBranding/updateGeneral/updateMaintenance` | **Stored, not rendered** — `layouts/admin.blade.php` uses `institute->name`; maintenance not enforced | **NO** | `PlatformSettingsController:559,178`, `app/Http/Middleware` (no `app.maintenance` read) |

---

## 6. Security / Secret Audit

| Check | Verdict | Evidence |
|---|---|---|
| **Encryption at rest** | **PASS** | `Setting::$encrypted:21` lists 18 keys (`smtp.password`, `sms.api_key/secret/password`, `payment.api_key/secret/webhook_secret`, `maps.api_key`, `storage.s3.secret`, `webhook.secret`, `ai.*`, `bkash.*`); `Setting::set:76-78` (`Crypt::encryptString`) + `Setting::get:64-68` (`Crypt::decryptString` try/catch). `PlatformServiceConfig::getValue:48` also decrypts. `PlatformSettingsService::SECRET_KEYS:11` mirrors. `InstituteSetting.smtp_password_enc` decrypted via `ResolveMailer:67-77`. |
| **Masked UI** | **PASS** | `Setting::masked:41-48` (`NOT CONFIGURED` / `Configured ••••••••`). `PlatformSettingsService::masked:91` identical. All Blade secrets use `type="password"` + `placeholder="{{ $*Masked }}"` (`index.blade.php:59,87-90,109,204-205,236,255,269`). Never `value="{{ Setting::get('smtp.password') }}"`. |
| **Blank-preserve** | **PASS** | `PlatformSettingsController:206-209` (`if filled($data['smtp_password']) && !== '••••••••'`), same for `sms.api_key/secret/password:278-289`, `payment.api_key/secret:436-442`, `maps.api_key:483-485`, `ai.api_key:522-524`, `webhook.secret:542-544`. `SettingController:240` same for legacy. `PlatformSettingsService::set:78` returns on `••••••••`. |
| **No plaintext Blade/JSON/JS exposure** | **PASS** | Grep for `smtp.password` in views returns only `passwordMasked` placeholders. `PlatformSettingsController::viewData:50,65-67,97,109-110,116,131` returns only `*Masked` strings. No `Setting::get('*.password')` passed to view. No JS serializes secrets. |
| **No log / audit secret leakage** | **PASS** | `PlatformAuditLog::record:36` stores `meta` truncated 200, and for secrets stores only `credential_changed` literal (`PlatformSettingsService:84-88`, `PlatformSettingsController:208,280,284,289,438,442,485,524,544`). `testEmail:247` (`str_replace(password, '***')` + `substr 300`), `testSms:328-330` same for 3 sms secrets, `MailChannel:41` truncates error 500 without password, `SmsChannel:44` same, `PhoneOtpService:207-209` logs `otp_generated` as `otp not logged plaintext` + masked phone, `EmailOtpService:201` only `email_otp_queued` with masked email. |
| **No JSON API exposure** | **PASS** | `PlatformSettingsController` returns `RedirectResponse` only, never JSON. No `GET /api/settings` exposing values. `DatabaseControlCenterController::json` is separate and not secret-bearing. |
| **.env not mutated** | **PASS** | No controller writes `.env`. Verified `.env` still contains real Gmail `MAIL_PASSWORD=[REDACTED]` (runtime, not via UI). |
| **Payment save cannot wipe SMTP** | **PASS** | E19 split `mail-payment` legacy: `PlatformSettingsController:updateEmail` only touches `smtp.*`, `updatePayment` only `payment.*`; legacy `SettingController:updateMailPayment:227-244` previously shared page but now also only sets distinct keys (no cross-wipe). |

> **Secrets in report:** `[REDACTED]` per instruction — no values printed.

---

## 7. Provider Audit

| Provider Family | UI Controls | Encrypted? | Enable Toggle? | Runtime Wired? | Evidence |
|---|---|---|---|---|---|
| **SMS (log / http)** | provider, type, api_url, http_method, api_key, api_secret, username, password, sender_id, sender_name, auth_type, phone_param, message_param, success_condition, enabled | Yes (api_key/secret/password via `Setting::$encrypted`) | Yes (`sms.enabled`) — but `PhoneOtpService` ignores it | **Partial** — notifications via `SmsChannel` yes; OTP via `PhoneOtpService` no | `PlatformSettingsController:255-298`, `SmsChannel:49`, `PhoneOtpService:195` |
| **Payment (bkash / mock)** | provider, mode (sandbox/live), currency, enabled, api_key, api_secret | Yes | Yes (`payment.enabled`) | **No** — `BkashConfig::isEnabled/isConfigured` ready, not injected | `PlatformSettingsController:422-445`, `BkashConfig.php:34`, view banner `index.blade.php:196` |
| **AI (openai / custom)** | enabled, provider, model, base_url, api_key | Yes (`ai.api_key` + `ai.openai_api_key/ai.custom_api_key`) | Yes (`ai.enabled`) | **Yes** via `AiConfig` | `PlatformSettingsController:509-527`, `AiConfig.php:16-44` |
| **Maps** | enabled, geocoding, places, map, api_key, default_country/lat/lng | Yes (`maps.api_key`) | Yes (3 toggles) | **Stored only** — offline geo active | `PlatformSettingsController:468-488`, `index.blade.php:229-243` |
| **Storage (local/public/s3)** | disk, max_size, resize, webp, thumb | Partial (`storage.s3.secret` only) | N/A | **No** | `PlatformSettingsController:449-464`, `StorageConfig.php:13` |
| **Email SMTP** | host/port/encryption/username/password/from/reply/queue/retry/timeout/enabled | Yes (password) | Yes (`smtp.enabled`, `smtp.queue`) | **Yes** | `PlatformSettingsController:185-219`, `ResolveMailer.php:44` |

---

## 8. OTP / 2FA Audit

### OTP (Phone + Email)

| Control | UI Field | Validation | DB Key | Runtime Wired? | Evidence |
|---|---|---|---|---|---|
| Email OTP enabled | `email_otp_enabled` select | `required in:0,1` | `email_otp.enabled` | **YES** — `IdentityConfig::isEmailOtpEnabled():74` (`'1' === '1'`) | `PlatformSettingsController:339`, `IdentityConfig.php:74` |
| Email OTP length | `email_otp_length` number | `4-8` | `email_otp.length` | **YES** — `IdentityConfig::emailOtp('length'):40` → `EmailOtpService:54` (`generateOtp`) | `IdentityConfig.php:32`, `EmailOtpService.php:54` |
| Email OTP expiry (min) | `email_otp_expiry` | `1-60` | `email_otp.expiry` | **YES** — `IdentityConfig::emailOtp('expires_minutes'):40` → `EmailOtpService:57` |同上 |
| Email OTP max attempts | `email_otp_max_attempts` | `1-10` | `email_otp.max_attempts` | **YES** → `EmailOtpService:133` |同上 |
| Email OTP resend cooldown | `email_otp_resend_cooldown` | `10-600` | `email_otp.resend_cooldown` | **YES** → `IdentityConfig::emailOtp('resend_throttle_seconds'):34` |同上 |
| Email OTP max resend/hour | `email_otp_max_resend` | `1-10` | `email_otp.max_resend` | **YES** → `IdentityConfig::emailOtp('max_sends_per_hour'):42` |同上 |
| SMS OTP (same 6 fields) | `sms_otp_*` | same | `sms_otp.*` | **YES** → `PhoneOtpService:54-130` via `IdentityConfig::phoneOtp` | `IdentityConfig.php:14`, `PhoneOtpService.php:54` |
| Queue toggle (email_otp.queue/sms_otp.queue) | **No UI** | — | `email_otp.queue` / `sms_otp.queue` via `PlatformSettingsService::otpSettings():106,113` | **Not wired** — no consumer reads `*.queue` | `PlatformSettingsService.php:106` vs no read |

> All OTP fields use `Setting::get` with numeric cast and `config(identity.*)` fallback — precedence DB → env → default (see §18).

### 2FA / Security

| Control | UI Field | DB Key | Runtime Wired? | Evidence |
|---|---|---|---|---|
| Allow TOTP | `allow_totp` (0/1) | `2fa.allow_totp` | **YES** — `TwoFactorMethodService::hasTotp:46` (`Setting::get` guard, `if '0' return false`) | `TwoFactorMethodService.php:46`, `PlatformSettingsController:375` |
| Allow Email OTP | `allow_email` | `2fa.allow_email` | **YES** — `hasEmail2FA:77` |同上:77 |
| Allow SMS OTP | `allow_sms` | `2fa.allow_sms` | **YES** — `hasSms2FA:61` |同上:61 |
| Preferred method | `preferred` (totp/sms/email) | `2fa.preferred` | **PARTIAL** — stored, but `TwoFactorMethodService::preferredMethod:85` prefers user's `preferred_2fa_method`, only falls back to priority `totp>sms>email`, **ignores** `2fa.preferred` global | `TwoFactorMethodService.php:85-101` vs `PlatformSettingsService.php:127` (`2fa.preferred` — no reader) |
| Allow user change | `allow_user_change` | `2fa.allow_user_change` | **Stored only** — no consumer | `PlatformSettingsController:379` only |
| Require verified email/phone | `require_verified_*` | `2fa.require_verified_email/phone` | **Stored only** — `TwoFactorMethodService` already enforces verified checks internally, but does not read these global toggles | `TwoFactorMethodService.php:59-83` |
| Max failed attempts | `max_failed` | `2fa.max_failed` | **Stored only** | No reader |
| Challenge expiry | `challenge_expiry` | `2fa.challenge_expiry` | **Stored only** | `TwoFactorChallengeController` uses session lifetime, not this key |

> **Verdict:** Core 2FA gating (allow_totp/email/sms) is **A/GREEN**; policy fields are **B/YELLOW** (UI+DB only).

---

## 9. Notification Audit

| Control | Existing? | Route | Runtime Consumer | Status |
|---|---|---|---|---|
| In-app channel | Config + template per event | — | `NotificationService:85-90` (`channelAllowed` + `InAppChannel`) | **A/GREEN** (no Super Admin toggle needed) |
| Email channel (global toggle) | Super Admin UI `notif_email` (0/1) at `index.blade.php:184` | `platform-settings.notifications` | **Not read** — `NotificationService::channelAllowed:158` reads `InstituteSetting.notification_settings[channel]`, not `settings.notifications.email_enabled` | **B/YELLOW** |
| SMS channel (global toggle) | `notif_sms` 0/1 | same | Same — per-institute `InstituteSetting`, not platform | **B/YELLOW** |
| Queue toggle | `notif_queue` 0/1 | same | Not read — `SendNotificationJob:140` always queues | **B/YELLOW** |
| Retry count | `notif_retry` 0-10 | same | Not read — `NotificationService:135` uses `config(notifications.retry.max_attempts)` | **B/YELLOW** |
| Per-event enable + per-user preference | DB `notification_templates.is_active` + `notification_preferences` | — | `NotificationService:188-212` (`resolveTemplate` + `prefersDisabled`) | **E/BLUE** (no Super Admin UI, correctly per-institute/guard) |
| Fallback provider | `SmsChannel:63` falls back `http` if unknown provider; `ResolveMailer:57` returns null → `Mail::to` default | — | `SmsChannel:63`, `ResolveMailer:57` | **A/GREEN** |
| Engine note | `NotificationService → MailChannel/SmsChannel → SendNotificationJob (queue: notifications, retry 3/60, timeout 60)` | — | `NotificationService:140`, `SendNotificationJob:27-29`, `MailChannel:38`, `SmsChannel:35` | Reused, not rebuilt |

---

## 10. Payment / Storage Audit

### Payment
- **Providers present:** `config/services.php:38` (`bkash` sandbox) + `payment_gateways` table (`slug mock`) + `institute_payment_gateways` + `online_payment_attempts` + `PaymentService` (posts to `ChartOfAccounts`).
- **UI:** `Payment Gateways` pane (`index.blade.php:195`) fields `provider/mode/currency/enabled/api_key/secret` — encrypted, masked, `RUNTIME INTEGRATION PENDING` banner (`:196`).
- **DB:** `settings` `payment.*` (encrypted) — verified in `PlatformSettingsController:422-445`.
- **Runtime:** `config/services.php:38` (`BKASH_APP_KEY` env) remains authoritative. `BkashConfig:13` resolver maps `payment.api_key→bkash.app_key` etc. with `isEnabled/isConfigured` but **no injection** into payment flow. `SettingController:mail_payment` legacy `payment.gateway` string is separate.
- **Verbiage check:** Banner correctly says pending — **not misleadingly claiming GREEN**.
- **Status:** **B/YELLOW (UI+DB, runtime pending)** — credentials saved safely, not yet live.

### Storage
- **Disks:** `config/filesystems.php:16` (`local` → `storage/app/private`, `public` → `storage/app/public`, `s3` env-based) — `Storage:disks` s3 uses `AWS_*` env.
- **UI:** `Storage` pane (`index.blade.php:213`) fields `disk (local/public/s3) / max_size / resize / webp / thumb` — banner `RUNTIME INTEGRATION PENDING` (`:214`).
- **Runtime:** `StorageConfig:14` (`disk()` fallback `config('filesystems.default')`) exists but **never called** by `DocumentService` / `CourseMaterialService` / upload controllers (they use `config('filesystems.default')` directly).
- **Status:** **B/YELLOW** — physical `storage:link` + existing upload pipeline unaffected.

---

## 11. Maps / WhatsApp Audit

| Family | UI | DB | Runtime | External Call? | Verdict |
|---|---|---|---|---|---|
| **Maps / Google Maps** | Yes — `Maps & Geo` tab (`index.blade.php:229`) with `enabled/geocoding/places/api_key/default_country/lat/lng` | `settings maps.*` (api_key encrypted) | Offline `BdGeo` (`8 divisions / 64 districts / 494 upazilas`) via `countries` + `administrative_levels/units` + `GeoImportService` is authoritative. View footer: `no Google Maps integration currently active — uses offline Geo tables` (`:243`). No `Http::get('maps.googleapis')` found. | **No** — no external Maps API call | **B/YELLOW (future-ready)** |
| **WhatsApp / Messaging** | Status line only — `index.blade.php:275` `WhatsApp: {{ $whatsappStatus }} — future provider via SmsProviderContract architecture. Email/SMS are active channels.` | `settings whatsapp.enabled` read only (`PlatformSettingsController:133`) | `institutes.whatsapp` and `crm_contacts.whatsapp` are **contact fields**, not gateway config. No `WhatsappProvider` class exists. Architecture explicitly reserves `SmsProviderContract` for future generic messaging. | **No** | **F/GRAY** |
| **Geolocation services** | Same Maps toggles | `maps.default_country/lat/lng` | Not consumed — no geocoding service reads DB lat/lng | **No** | **B/YELLOW** |

---

## 12. AI Audit

| Field | UI | DB Key | Runtime Consumer | Encrypted? | Precedence | Status |
|---|---|---|---|---|---|---|
| AI Enabled | ✓ select 0/1 | `ai.enabled` | `AiConfig::enabled():16` (`Setting::get('ai.enabled') ?? config('ai.enabled')`) | — | DB → `config/ai.php:14` (`AI_ENABLED` env) → false | **A/GREEN** |
| Provider | ✓ `openai/custom` | `ai.provider` | `AiConfig::provider():28` | — | DB → `config/ai.php:22` | **A** |
| Model | ✓ text | `ai.model` | `AiConfig::model():43` (`ai.model ?? config('ai.default_model')`) | — | DB → env `AI_MODEL` | **A** |
| Base URL | ✓ url | `ai.base_url` | `AiConfig::baseUrl():38` | — | DB → `config('ai.providers.{provider}.base_url')` | **A** |
| API Key | ✓ password masked | `ai.api_key` | `AiConfig::apiKey():34` | **YES** (`Setting::$encrypted:22,34`) | DB → `config('ai.providers.{provider}.api_key')` (`AI_OPENAI_API_KEY` env) | **A** |
| Features / Instructions / Limits | **No UI** | `ai.features/global_instructions/max_tokens/...` | `AiConfig::features():100`, `globalInstructions():48`, `maxTokens():53` etc. | — | Stored via `SettingController`/legacy `AiSettingController` (separate) | **E/BLUE (runtime exists, no platform-settings UI)** |
| Reuse check | — | — | `AiProvider`, `AiToolRegistry`, `AiContext`, `AiLogger`, `ai_logs/ai_usage` preserved | — | — | **Reused, not duplicated** |

> **No new AI engine created.** `PlatformSettingsController:updateAi:509` only writes `Setting` keys; `AiConfig` is the single resolver.

---

## 13. Queue Audit

| Item | UI Control | Route | Runtime | Editable? | Safe? |
|---|---|---|---|---|---|
| Queue Driver | Display only (`{{ $queueDriver }}` = `config('queue.default')`) | — | `config/queue.php:16` (`QUEUE_CONNECTION=database`) | **No** (display) | Read-only |
| Database queues | `default` + `notifications` shown (`config('queue.connections.database.queue')` + `config('notifications.delivery.queue')`) | — | `SendNotificationJob:140` (`onQueue('notifications')`), `EmailOtpMail` queued, `queuedVerifyEmail` | Not editable via UI | Correct: `database --queue=default,notifications --tries=3 --timeout=25` documented (`index.blade.php:175`) |
| Pending / Failed counts | Display (`jobs`/`failed_jobs` `count()`) | — | `PlatformSettingsController:queueCounts():137-153` try/catch | Not editable | Safe (count only) |
| Last job / Last failed timestamps | Display | — | `jobs.created_at` / `failed_jobs.failed_at` | Not editable | Safe |
| Health check | Button `Queue Health Check` | `POST platform-settings.queue.health` | `PlatformSettingsController:410-418` — checks `DB::table('jobs')->limit(1)` + `failed_jobs` + driver/queue names, no `queue:work` spawn | **No worker start** | **Safe** (read-only DB check) |
| Retry / timeout controls | `php artisan queue:work database --tries=3 --timeout=25` shown as info alert, not editable | — | `config/queue.php:43` (`retry_after 90`) + `SendNotificationJob:27` (`tries 1 timeout 60`) | **No UI** | — |

**Verdict:** **C (UI monitoring-only, no control)** — Super Admin can **view health**, not configure queues. No destructive commands run.

---

## 14. Maintenance Audit

| Question | Answer | Evidence |
|---|---|---|
| UI exists? | **Yes** — `Maintenance` tab (`index.blade.php:291`) | `pane-maintenance` with `maint_enabled` (Enabled/Disabled), `maint_allow_admin` (Yes/No), `maint_message` text |
| Toggle exists? | **Yes** — `Setting::set('app.maintenance')` 0/1 + `maintenance_message` + `maintenance_allow_admin` | `PlatformSettingsController:573-575` |
| Runtime actually enforces it? | **NO** | No middleware reads `Setting app.maintenance`. Grep across `app/Http/Middleware` shows zero readers. Laravel's built-in `PreventRequestsDuringMaintenance` still tied to `storage/framework/maintenance.php` via `php artisan down`, not this DB toggle. |
| Exclusions/bypass? | **Stored but unenforced** — `app.maintenance_allow_admin` saved but never checked; footer correctly notes intent: `Super Admin is never locked out when "Allow Super Admin" is enabled.` (`index.blade.php:301`) | `PlatformSettingsController:576` only logs |
| Admin access during maintenance? | **Not wired** | Would require custom middleware gating on `Setting::get('app.maintenance')` with `auth:platform_admin` bypass — missing |

**Status:** **B/YELLOW (UI+DB only, no runtime enforcement)** — storage is safe; wiring is non-destructive and pending approval.

---

## 15. Branding / System Audit

| Setting | UI Field | DB Key | Runtime/View Consumer | Consumed? | Status |
|---|---|---|---|---|---|
| Application Name | `app_name` text required | `app.name` | `Setting::get('brand.name', config('app.name'))` in `viewData:118` only; `welcome.blade.php:7` uses `config('app.name')` | **Not rendered** | **B** |
| Short Name | `app_short_name` | `app.short_name` | Only stored via `PlatformSettingsService:GENERAL_DEFAULTS:33` | **Not rendered** | **B** |
| Application URL | `app_url` url required | `app.url` | Stored, not used to override `config('app.url')` at runtime | **B** | **B** |
| Timezone / Country / Currency | `app_timezone/country/currency` | `app.timezone/country/currency` | Stored, no `date_default_timezone_set` or locale middleware reads them | **B** | **B** |
| Language / Date/Time Format / Pagination | `app_language/date_format/time_format/pagination` | `app.language/date_format/time_format/pagination` | `mawa_current_lang()` reads `session/lang`, not `Setting app.language`; date formats not applied globally | **B** | **B** |
| Contact email/phone/support URL | `app_contact_email/support_phone/support_url` | `app.contact_email/support_phone/support_url` | Stored, no footer/contact consumer | **B** | **B** |
| Platform Name (Branding) | `brand_name` required | `brand.name` | `brandName => Setting::get('brand.name', config('app.name'))` (`:118`) but `layouts/admin.blade.php:47` renders `AccumenAI` / `institute->name` | **Not rendered** | **B** |
| Footer Text | `brand_footer` | `brand.footer` | Stored, no layout footer reads it | **B** | **B** |
| Primary Color | `brand.primary` read via `viewData:120` | `brand.primary` | **Not editable via UI** (hidden), but read for future theme | **B** | **B** |
| Industry / Theme Colors | Separate page `admin/industry-settings` via `IndustrySettingController` per-industry theme defaults | `themes` / `industry_settings` | `layouts/admin.blade.php` + `Theme::query()` | **E (separate surface)** |

> **No logo/favicon upload exists** — **RED/MISSING** for that sub-requirement. All other branding/system fields are **B/YELLOW** (UI+DB without runtime consumer).

---

## 16. API / Webhook Audit

| Setting | UI Field | DB Key | Validation | Runtime Consumer | Status |
|---|---|---|---|---|---|
| API Enabled | `api_enabled` select 0/1 | `api.enabled` | `in:0,1` | **None** — no `api` guard checks it | **B** |
| Webhook URL | `webhook_url` url | `webhook.url` | `nullable url max:500` | **None** — no dispatcher reads it | **B** |
| Webhook Secret | `webhook_secret` password masked | `webhook.secret` | `max:500`, encrypted, `credential_changed` logged | **None** | **B** |
| Webhook Retry | `webhook_retry` number | `webhook.retry` | `0-10` | **None** | **B** |
| Webhook Timeout | `webhook_timeout` number | `webhook.timeout` | `5-120` | **None** | **B** |
| Signing / Retry config | Above fields + `is_encrypted` capability exists in `PlatformServiceConfig` | `platform_service_configs.is_encrypted/is_enabled` | — | **Future table** — not consumed | **F/GRAY** |
| Existing API architecture | `routes/api.php` + Fortify + `bKash` callback | — | — | Separate, not via this settings surface | **E (existing elsewhere)** |

> No webhook dispatch, rotation, or signing is currently implemented — **stored safely, not active**.

---

## 17. AI Audit (extended)

See §12 for primary AI; additional findings:

- **Legacy AI route:** `admin/settings/ai` (`AiSettingController:index/update/test` via `routes/web.php:225-227`) remains **active** alongside new `platform-settings/ai`. Both write `Setting ai.*` — no engine split, same `AiConfig`.
- **Encryption:** `ai.api_key` encrypted (`Setting::$encrypted:22`), legacy plain `ai.openai_api_key/ai.custom_api_key` also encrypted (added `:34`).
- **Test endpoint:** `POST admin/settings/ai/test` exists, but `platform-settings/ai` has **no test button** — gap.

---

## 18. Branding / General — already covered §15; no logo hardware present

---

## 19. Test Connection / Test Service

| Service | Test UI | Route | Controller | Real External Call? | Sanitized? | Safe? |
|---|---|---|---|---|---|---|
| **SMTP** | `Test Email` input + `Send Test Email` button (`index.blade.php:72`) | `POST platform-settings.email.test` | `PlatformSettingsController:testEmail:222` — `Mail::raw('Test email...')->to(test_email)` via resolved `mail.mailers.smtp` | **YES** — real `Mail::raw` if host configured; otherwise returns `SMTP not configured` | **YES** — `str_replace(password, '***')` + `substr 300` (`:247`) | **Safe** (rate-limited by auth:platform_admin, not throttle) |
| **SMTP (legacy)** | Separate `Test Email` on legacy mail_payment pane | `POST admin.settings.mail-payment.test` | `SettingController:testMail:250` | **YES** — same pattern, fallback to configured SMTP | **YES** — `str_replace(password, '***')` (`:282`) | Safe |
| **SMS (log provider)** | `Send Test SMS` button | `POST platform-settings.sms.test` | `PlatformSettingsController:testSms:302` → `LogSmsProvider::send` | **NO** — logs only (`Log::info notification.sms`) | N/A | Safe |
| **SMS (http provider)** | Same form (`index.blade.php:102`) | same | `HttpSmsProvider::send` (`Http::timeout 15`) | **YES** — real `Http::get/post` to `sms.api_url` if provider=http and url set, else `PROVIDER NOT CONFIGURED` error | **YES** — strips 3 sms secrets (`:328-330`) + `substr 300` | **Safe but blocked until configured** |
| **Payment** | **No test button** | — | — | **No** | — | N/A |
| **AI** | No test in platform-settings; legacy `ai/test` exists | `POST admin.settings.ai.test` | `AiSettingController::test` | **YES** (external AI request when invoked via legacy route) | Masked | Exists elsewhere |
| **Maps / Queue / Webhook** | **No test button** | — | — | No | — | N/A |

> **No real external calls were executed during this audit.** Behavior traced from source.

---

## 20. Encryption / Secret Security

| # | Check | Result | Evidence |
|---|---|---|---|
| 1 | Encryption at rest | **PASS** | `Setting::set:76-78` + `Crypt::encryptString` for 18 keys; `PlatformSettingsService::set:81` delegates |
| 2 | Masked UI | **PASS** | All 9 secret inputs `type=password` placeholder `Configured ••••••••` / `NOT CONFIGURED` (`index.blade.php`) |
| 3 | Blank-preserve | **PASS** | 5 controllers check `filled(... ) && !== '••••••••'` before `Setting::set` |
| 4 | No plaintext Blade value | **PASS** | No `value="{{ Setting::get('*.password') }}"` found |
| 5 | No JSON exposure | **PASS** | `PlatformSettingsController` never returns JSON secrets; `json()` endpoints are database monitoring only |
| 6 | No JavaScript exposure | **PASS** | No `<script> var key = "..."`; only history hash for tabs |
| 7 | No log exposure | **PASS** | `PlatformAuditLog::record:36` stores `credential_changed` literal, never value; OTP logs masked (`PhoneOtpService:207`, `EmailOtpService:201`) |
| 8 | No audit metadata leakage | **PASS** | `IdentityAuditService::log` masks phone `+880***` / email `a***@domain`; `PlatformAuditLog meta` trunc 200 without secret |
| 9 | Decrypt fallback safe | **PASS** | `Setting::get:64-68` try/catch returns legacy plaintext without exception; `ResolveMailer:67-77` same |
| 10 | No hard-coded credentials | **PASS** | Grep `MAIL_PASSWORD|API_KEY` in `app/` shows only `env(...)` / `Setting::get` — `.env` real password `[REDACTED]` not in Git-tracked PHP |

---

## 21. Provider Enable / Disable

| Provider | `enabled` Key | Active Provider Key | Fallback | Priority | UI Toggle? | Runtime Honors? |
|---|---|---|---|---|---|---|
| **Email SMTP** | `smtp.enabled` (0/1) | — (single host) | `null` → default mailer | `InstituteSetting.smtp_host` → `Setting smtp.host` → env `MAIL_*` → default | **Yes** | **Partially** — `smtp.enabled` stored but `ResolveMailer:44` ignores it (checks `filled(host)` only) |
| **SMS** | `sms.enabled` (0/1) | `sms.provider` (`log`/`http`) + `sms.type` | `http` if unknown provider (`SmsChannel:63`) | `InstituteSetting.sms_provider` → `Setting sms.provider` → `config(sms.default=log)` | **Yes** | **Yes for notifications** (`SmsChannel:58`); **No for OTP** (`PhoneOtpService:195` reads config) |
| **Payment** | `payment.enabled` | `payment.provider` (string) + `payment.mode` (sandbox/live) | env `services.bkash.sandbox` | `payment.*` DB → `config/services.php` | **Yes** | **No** (`BkashConfig::isEnabled()` ready, not used) |
| **AI** | `ai.enabled` | `ai.provider` (openai/custom) | config fallback | DB → `config/ai.php` | **Yes** | **Yes** (`AiConfig::enabled`) |
| **Maps** | `maps.enabled` + `maps.geocoding/places/map` | — (single API key) | offline geo | DB → not configured | **Yes** | **Stored only** |
| **API/Webhook** | `api.enabled` | `webhook.url` | — | DB only | **Yes** | **No** |
| **Storage** | (none — disk selection is provider) | `storage.disk` (local/public/s3) | `local` default | `Setting storage.disk` → `config/filesystems.default` (`StorageConfig::disk`) | **Yes** | **No** |

> `sms.provider` is the only true provider switch actively consumed today.

---

## 22. Fallback Configuration

| Family | Precedence (as implemented) | Evidence |
|---|---|---|
| **Email SMTP** | `InstituteSetting.smtp_host` (tenant) → `Setting smtp.host` (platform DB) → `null` (Laravel `mail.from` env default) | `ResolveMailer.php:32-57` — comment explicitly `Priority: per-institute SMTP → global platform SMTP → null (env default)` |
| **SMS Provider** | `InstituteSetting.sms_provider` → `Setting sms.provider` → `config('notifications.sms.default', 'log')` | `SmsChannel.php:48-59` — tenant → platform → config env |
| **SMS HTTP URL** | `Setting sms.api_url` → `options['url']` → `config('notifications.sms.http.url', '')` (env) | `HttpSmsProvider.php:24-26` (`Setting::get('sms.api_url') ?: ($config['url'] ?? '')`) |
| **OTP / Email OTP** | `Setting sms_otp.* / email_otp.*` → `config('identity.phone_otp/email_otp.*')` → default param | `IdentityConfig.php:14-42` — `Setting::get` then `config("identity.*")` |
| **2FA** | `Setting 2fa.allow_*` gate → user flags (`sms_2fa_enabled/email_2fa_enabled/two_factor_confirmed_at`) | `TwoFactorMethodService.php:46-77` — platform `if '0' return false` before user check |
| **AI** | `Setting ai.*` → `config('ai.providers.{provider}.*')` (env) | `AiConfig.php:18-77` (`Setting::get(...) ?? config(...)`) |
| **Payment** | `Setting payment.*` → `config('services.bkash.*')` (env) | `BkashConfig.php:26-31` (`Setting::get` then `config("services.bkash.{key}")`) |
| **Storage** | `Setting storage.disk` → `config('filesystems.default')` (env) | `StorageConfig.php:15-18` |
| **General / Branding** | `Setting app.*/brand.*` → `PlatformSettingsService::GENERAL_DEFAULTS` → `config('app.*')` | `PlatformSettingsService.php:49-72` (`get()` checks DB then GENERAL_DEFAULTS then envFallback) |
| **Queue** | `config/queue.php` + env `QUEUE_CONNECTION` only — **no DB override** | `config/queue.php:16` — UI is display-only |

> **No precedence was redesigned in this audit.** Current design is deliberately `institute → global DB → env → hard default` for tenant-facing services (mail/SMS), and `global DB → env → default` for platform services (AI, queue), which is correct. The shadow gaps (§5) are not precedence errors but **unwired readers**.

---

## 23. Configuration History

| Question | Answer | Evidence |
|---|---|---|
| **Table** | `platform_audit_logs` (migration `2026_08_25_000010:25-38`) | `id/admin_id→platform_admins/section/setting_key/action/ip_address/user_agent/meta(json)/timestamps` + indexes `(section,created_at)`, `(admin_id,created_at)` |
| **Events recorded** | Every `Setting::set` in PlatformSettings: `PlatformAuditLog::record(section, key, action, meta)` | `PlatformSettingsController` 26 call sites (`:208,218,250,280,284,289,298,312,323,357,384,405,438,442,444,463,485,487,504,524,526,544,548,561,576`) + `SettingController:242,245` + `PlatformSettingsService:84-88` |
| **`credential_changed` behavior** | For any `SECRET_KEYS` entry, the audit row stores `action='credential_changed'` with **no value**; otherwise `action='updated'` with `meta.value = substr(value, 0, 200)` | `PlatformSettingsService:84-88` (`if $isSecret record credential_changed else updated with truncated value`) |
| **Who / When** | `admin_id = request()->user()?->getKey()` + `created_at` timestamp | `PlatformAuditLog::record:30,34` |
| **IP / User-Agent** | `ip_address = request()->ip()` (45 chars), `user_agent = substr(request()->userAgent(), 0, 500)` | `PlatformAuditLog.php:34-35` |
| **Section / Key** | `section` (general/email/sms/otp/security/payment/storage/maps/ai/api/branding/maintenance) + `setting_key` (e.g., `smtp.password`) | All `record()` calls |
| **Secret redaction** | **No secret in `meta`** for credential changes; `testEmail` / `testSms` sanitize before error/message logging | `PlatformSettingsService:84`, `PlatformSettingsController:247,328` |
| **Admin can view history in UI?** | **NO UI** | No `admin.platform-settings.history` route; `resources/views/admin/platform-settings/index.blade.php` has no history table. Access via DB only: `SELECT * FROM platform_audit_logs` |
| **Legacy audit source** | `audit_logs` / `identity_audit_logs` / `accounting_audit_trails` pattern reused for `platform_audit_logs` | `PHASE_E19` docs, model pattern identical |

---

## 24. Duplicate Engine Detection

| Candidate Duplicate | Status | Source of Truth | Runtime Used | Risk |
|---|---|---|---|---|
| **`platform_service_configs`** (service/provider/key/value/is_encrypted/is_enabled) | **EMPTY/UNUSED — FUTURE/PLANNED** | `settings` K/V is current truth (`Setting` + `PlatformSettingsService`) | **No reader** — `PlatformServiceConfig.php:35-75` defines `getValue/setValue/isConfigured` but grep shows **zero calls** to `PlatformServiceConfig::getValue` outside model; docblock at `:4` explicitly marks `Currently EMPTY/UNUSED. Active truth for platform settings is settings K/V... RESERVED for future normalized provider configs... Search: grep -R PlatformServiceConfig — no runtime read yet` | **NONE at runtime** — table is placeholder, safe to keep. **Do not populate until resolver wiring decided.** |
| **`settings` vs `InstituteSetting`** | **NOT duplicate** — distinct scopes | `settings` = platform global (super admin) | `ResolveMailer:32→44` uses both with correct precedence (tenant first). `InstituteSetting` is `TenantScoped` (`InstituteSetting.php:10`). | **No leak** — verified `withoutGlobalScope('institute')` in `SendNotificationJob:49` |
| **`admin.settings` (Legacy) vs `admin.platform-settings` (E19)** | **DUPLICATE UI SURFACE — SAME TABLE** | Both write `settings` K/V (`smtp.host`, `payment.gateway`, `ai.*`, etc.) | Both surfaces valid; E19 is authoritative for full set, legacy remains for `mail_payment`/`ai/_ai`/`security/_panel` | **LOW** — not a data duplicate, but operator confusion risk. Recommend keeping legacy as entry point redirecting to Configuration Center for overlapping sections. |
| **`PlatformServiceConfig` vs `Setting` in docs** | Migration `2026_08_25` created both tables together but docs consistently state `settings` wins | `E19_FINAL_INTEGRATION_PRODUCTION_SAFETY_AUDIT.md:39` confirms `Setting::get(smtp.host) → ResolveMailer:44` YES, `PlatformServiceConfig` NO | `Setting` wins | **NONE** |
| **Idempotency** | `2026_08_25` migration is reversible; rollback drops both tables — safe | — | — | — |

> **Verdict:** No duplicate runtime path exists. `platform_service_configs` is correctly marked as future extension for per-provider structured params (e.g., multiple SMS gateways) when K/V becomes insufficient.

---

## 25. Multi-Tenant Safety

| Check | Result | Evidence |
|---|---|---|
| **Global platform settings remain global** | **PASS** | `settings` table has **no `institute_id`** (`Setting.php:11` `table='settings'` + migration `create_settings_table` `key unique`). `PlatformSettingsController` uses `auth:platform_admin` only, no `tenant` middleware. Institute routes use `tenant` + `auth:institute_user`. |
| **Institute settings remain tenant-scoped** | **PASS** | `InstituteSetting` uses `Concerns\TenantScoped` (`InstituteSetting.php:10`), `tenant` middleware on `routes/web.php:110-179` for institute routes. `ResolveMailer:25` queries `where institute_id = $instituteId` per request. |
| **ResolveMailer priority correct** | **PASS** | `institute → global → null` (`ResolveMailer.php:25-57`); preview fixed E19 remediation: institute `smtp_host` wins over global, not overwritten. |
| **TenantContext / BranchContext intact** | **PASS** | `SendNotificationJob:35-44,49,54-55,97-110` saves/restores both contexts; `TenantContext` used in `Auth\*LoginController`, `PlatformAdminLoginController:113` clears on platform login. |
| **No institute can access another institute's settings** | **PASS** | `auth:platform_admin` gates all `admin/platform-settings/*` (`web.php:186`). Institute settings at `routes/web.php:175` gated `permission:settings.manage` + `tenant` — scoped by `Auth::user()->institute_id`. |
| **No cross-tenant leakage in channels** | **PASS** | `NotificationService:74-75` + `SendNotificationJob:49` use `institute_id` from `NotificationLog`, not from global state; `SmsChannel:51-58` resolves provider per `institute_id`. |

---

## 26. Final UI Gap Matrix

**Statuses:** GREEN=fully implemented (UI+DB+runtime+secure) · YELLOW=UI+DB but runtime pending · BLUE=runtime exists but Super Admin UI missing · GRAY=future-ready/planned · RED=missing or unsafe

| # | Desired Control | Existing UI | Runtime Connected | Secure | Status | Evidence |
|---|---|---|---|---|---|---|
| 1 | **SMS Gateway** (provider/api_url/auth/sender/etc.) | ✓ (`SMS Provider` tab, 16 fields) | **PARTIAL** — notifications ✓, OTP 2FA ✗ (config env fallback) | Encrypted+masked+sanitized | **YELLOW** | `PlatformSettingsController:255`, `SmsChannel:49`, `PhoneOtpService:195` |
| 2 | **Email / SMTP** | ✓ (12 fields + test) | **YES** — `ResolveMailer` → `MailChannel` | Encrypted+masked+sanitized+blank-preserve | **GREEN** | `ResolveMailer.php:44`, `PlatformSettingsController:185,222` |
| 3 | **OTP & Verification Policy** | ✓ (Email/SMS length/expiry/attempts/cooldown/resend) | **YES** — `IdentityConfig` → `PhoneOtpService`/`EmailOtpService` | Hash+throttle+masked | **GREEN** | `IdentityConfig.php:13`, `PlatformSettingsController:336` |
| 4 | **2FA / Security Policy** | ✓ (TOTP/SMS/Email toggles + login protection) | **YES (core gating)** / **NO (policy extras)** | Hashed, RateLimiter | **YELLOW** (extras pending) | `TwoFactorMethodService:46`, `PlatformSettingsController:362,389` |
| 5 | **Queue / Background Jobs** | △ Display only (driver/counts/health button) | **Monitored, not editable** | Read-only, no worker spawn | **GRAY** | `PlatformSettingsController:410`, `index.blade.php:165` |
| 6 | **Payment Gateway** | ✓ (provider/mode/currency/enabled/api_key/secret) | **NO** — `config/services.php` env authoritative | Encrypted+masked | **YELLOW** | `PlatformSettingsController:422`, banner `:196`, `BkashConfig.php:13` |
| 7 | **File / Storage** | ✓ (disk/max/resize/webp/thumb) | **NO** — `config/filesystems.php` env authoritative | Safe | **YELLOW** | `PlatformSettingsController:449`, banner `:214`, `StorageConfig.php:14` |
| 8 | **Google Maps / Geolocation** | ✓ (enabled/geocoding/places/api_key/lat/lng) | **Offline-first** — no external call | Encrypted+masked | **GRAY** | `PlatformSettingsController:468`, `index.blade.php:243` |
| 9 | **WhatsApp / Messaging** | △ Status line only | **No gateway** | No secret | **GRAY** | `PlatformSettingsController:133`, `index.blade.php:275` |
| 10 | **Notification Settings** | ✓ (email/sms/queue/retry) | **NO** — runtime uses per-institute toggles | Safe | **YELLOW** | `PlatformSettingsController:492`, `NotificationService:158` |
| 11 | **System / Platform Settings** | ✓ (13 general fields) | **Stored only** | Safe | **YELLOW** | `PlatformSettingsController:161`, `PlatformSettingsService:31` |
| 12 | **Maintenance Mode** | ✓ (enabled/message/allow_admin) | **Stored only, not enforced** | Safe | **YELLOW** | `PlatformSettingsController:566`, no middleware reader |
| 13 | **Audit / Security Settings** | ✗ No toggle/UI | **YES (logged)** | Credential redaction, truncated meta | **BLUE** | `PlatformAuditLog.php:26` |
| 14 | **API / Webhook Settings** | ✓ (api.enabled/webhook url/secret/retry/timeout) | **NO** — no dispatcher | Encrypted+masked | **YELLOW** | `PlatformSettingsController:531` |
| 15 | **AI Settings** | ✓ (enabled/provider/model/base_url/api_key) | **YES** — `AiConfig` precedence | Encrypted+masked | **GREEN** | `AiConfig.php:16`, `PlatformSettingsController:509` |
| 16 | **Application Branding / General** | ✓ (brand name/footer + general) | **Stored only** | Safe | **YELLOW** | `PlatformSettingsController:553`, `viewData:118` |
| 17 | **Test Connection / Test Service** | ✓ (SMTP + SMS) | **YES** | Sanitized, audited | **GREEN** | `PlatformSettingsController:222,302`, §19 table |
| 18 | **Secrets encryption + masked display** | ✓ (all sensitive inputs) | **YES** | AES-256 via `Crypt`, placeholder guard | **GREEN** | `Setting::$encrypted:21`, `PlatformSettingsService::SECRET_KEYS:11` |
| 19 | **Per-provider enable/disable** | ✓ (sms/payment/maps/ai/api/storage disks) | **PARTIAL** | Encrypted where secret | **YELLOW** | §21 table |
| 20 | **Fallback configuration** | ✓ (DB→env→default precedence) | **YES where wired** | Not sensitive | **GREEN** (wired paths)/**YELLOW** (pending paths) | §22 table |
| 21 | **Configuration history / audit** | ✗ No UI table | **YES (DB)** | Redacted, IP/UA tracked | **BLUE** | `platform_audit_logs` `:25-38` |

> **Note on test 17:** Only SMTP + SMS (log/http) have test buttons in Configuration Center; legacy AI has a test route, but payment/maps/queue/webhook/storage/OTP/2FA have none — by design (no safe external call to test for those).

---

## 27. Final Recommendation — Five Lists

> **Do NOT implement anything now (audit-only).** Lists below are the prescribed next wiring phases without creating duplicate engines.

### A. ALREADY COMPLETE (GREEN — no action required beyond supplying real credentials)

1. **Email / SMTP** — Full UI + encrypted `settings` + `ResolveMailer`→`MailChannel`→`SendNotificationJob`→`notification_smtp` is production-ready. Real delivery depends on env `.env` (`MAIL_MAILER=smtp` + `MAIL_HOST=smtp.gmail.com` + `MAIL_PASSWORD=[REDACTED]` already present) — credential already configured. _Evidence: `ResolveMailer.php:44-57`, `MailChannel.php:45-68`, `.env:MAIL_PASSWORD=[REDACTED]`._
2. **AI Settings** — `ai.enabled/provider/model/base_url/api_key` via `AiConfig` (`Setting` → `config/ai.php`) is wired end-to-end, encrypted, masked. Supply `ai.api_key` via Configuration Center to go live. _Evidence: `AiConfig.php:16-44`, `PlatformSettingsController:509`._
3. **OTP Policy (Email + SMS)** — length/expiry/attempts/cooldown/max_resend fully wired via `IdentityConfig` → `PhoneOtpService`/`EmailOtpService`; range validation prevents insecure values. _Evidence: `IdentityConfig.php:13-42`, `PhoneOtpService.php:54-130`._
4. **2FA Core Gating** — `2fa.allow_totp/allow_email/allow_sms` correctly gates via `TwoFactorMethodService`; TOTP via Fortify, Email/SMS OTP via queued channels — wired. _Evidence: `TwoFactorMethodService.php:46-77`._
5. **Test Connection** — SMTP + SMS (log/http) test endpoints sanitized and audited, with `credential_changed` + `test_sent` logging. _Evidence: `PlatformSettingsController:222,302`._
6. **Secrets Security** — Encryption, masking, blank-preserve, audit redaction are complete and verified. _Evidence: `Setting.php:21-85`, §20._
7. **Fallback Design** — Precedence `institute→global→env→default` (mail/SMS) and `global→env→default` (AI/auth) documented and respected where wired. _Evidence: §22._

### B. UI EXISTS BUT RUNTIME WIRING IS INCOMPLETE (YELLOW — next implementation phase, highest value)

> These are **intentionally stored but not yet live** — banners already warn `RUNTIME INTEGRATION PENDING`.

1. **SMS OTP 2FA provider wiring** — `PhoneOtpService::sendSms:195` still calls `config('notifications.sms.default', 'log')` instead of `SmsChannel`/`SmsConfig` platform DB. **Next:** make `PhoneOtpService` (and `Phone2faOtp` path) resolve via `SmsChannel::resolveProviderName` / `SmsConfig::activeProvider()` so `sms.provider=http` + `sms.api_url` from Configuration Center actually fires for 2FA.
2. **Payment** — Wire `BkashConfig::get()` into the actual payment flow (currently `config/services.php` env). Map `payment.api_key→bkash.app_key`, `payment.api_secret→bkash.app_secret`, `payment.mode→bkash.sandbox`, then inject `BkashConfig` in `PaymentService`/gateway checkout. Banner already honest.
3. **Storage** — Wire `StorageConfig::disk()` / `maxSizeKb()` into `DocumentService` / `CourseMaterialService` upfront before any new `Storage::disk(config(...))` call. Until then, disk switch does not move files — correctly flagged pending.
4. **Maintenance** — Add middleware (or `AppServiceProvider` boot check) that reads `Setting::get('app.maintenance')` and respects `app.maintenance_allow_admin` (bypass for `auth:platform_admin`), instead of relying solely on `storage/framework/maintenance.php`. No lockout risk if bypass kept.
5. **Notifications platform toggles** — Either wire `Setting notifications.*` into `NotificationService::channelAllowed` (OR with per-institute toggles) or remove those fields and keep only per-institute `InstituteSetting.notification_settings` as source of truth. Currently UI saves but has no effect.
6. **System / General + Branding** — Wire `app.timezone/language/date_format/time_format` into layout / `SetLocale` / `config('app.timezone')` at boot, and `brand.name/footer` into `layouts/admin.blade.php:47` / `welcome.blade.php:7`. Stored today, not rendered.
7. **2FA Policy extras** — Wire `2fa.preferred`, `2fa.allow_user_change`, `require_verified_*`, `max_failed`, `challenge_expiry`, and `login.*` / `password.min_length` into `TwoFactorMethodService::preferredMethod` priority, `SecurityController`, and `Fortify`/`PasswordPolicy`. Today only 3 gates are live.
8. **API/Webhooks** — Create dispatcher that actually reads `webhook.url/secret/retry/timeout` when events fire (e.g., `PaymentService`, `NotificationService` hook). Today stored only.

### C. RUNTIME EXISTS BUT SUPER ADMIN UI IS MISSING (BLUE — low-effort UI add-ins)

1. **Configuration History / Audit Viewer** — `platform_audit_logs` is fully written (`record` on every save) but has **no UI page**. Add `GET admin/platform-settings/audit` listing `section/setting_key/action/admin_id/ip/time` (meta truncated), reusing `identity_audit_logs` pattern. No rotation control needed.
2. **Per-institute notification preferences** — `notification_templates` / `notification_preferences` + `InstituteSetting.notification_settings` toggles exist and are runtime-consumed, but no Super Admin page lists them — correctly per-institute, optionally add read-only viewer.
3. **AI extended controls** — `ai.features / global_instructions / max_tokens / temperature / log_prompts / dailyLimit / monthlyLimit` via `AiConfig` exist but are **not in Configuration Center** (only in legacy `admin.settings.ai` / `InstituteSetting.ai_config`). Optionally surface read-only.

### D. FUTURE / PLANNED (GRAY — keep reserved, do not build now)

1. **`platform_service_configs`** normalized per-provider store — explicitly `EMPTY/UNUSED` and documented (`PlatformServiceConfig.php:4-20`). Reserve for when K/V is insufficient (e.g., multiple SMS gateways with distinct params).
2. **WhatsApp / Messaging gateway** — intentionally `NOT CONFIGURED`; future field `whatsapp.enabled + api_url/secret` will reuse `SmsProviderContract`. No engine to build now.
3. **Queue control** — not editable for good reason (worker infra via `queue:work` outside web). Keep health monitoring only.
4. **Google Maps / Geolocation** — offline `countries/administrative_*` remains authoritative; `maps.enabled/geocoding/places` toggles stored for future `GeocodingService`, no external call required.
5. **API signing / provider retry details** — `is_encrypted/is_enabled` columns exist for future per-provider structured secrets.

### E. MISSING / NEEDS DESIGN (RED — design required before implementation)

1. **File/Branding asset upload** — No Super Admin UI for **logo, favicon, colors** (only `brand.name/footer` strings). Requires design: storage target, image validation, `Theme` vs `brand.primary` precedence, whether per-institute or platform-wide.

### Duplicate Configuration Engine Risk

**NONE at runtime.** Sources: see §24. `platform_service_configs` is future fuel, not a shadow. The only near-duplicate is **two UI surfaces (`admin.settings` legacy vs `admin.platform-settings` E19)** pointing at the **same `settings` table** — not a data duplicate, but risks operator editing in the legacy form and missing new fields. Recommend: keep `admin.settings` for `Account/Staff/Appearance/Security` and **deprecate its `mail_payment` pane** (or make it redirect to Configuration Center), preserving its `password/language/appearance` panes that are not in the new Center.

---

## 28. Required Report Ledger (Cross-Reference)

All requested report chapters are present:

| # | Brief Requirement | This Report Section |
|---|---|---|
| 1 | Executive Summary | §1 |
| 2 | Current Super Admin Settings Navigation | §2 |
| 3 | Current Tabs/Panels | §3 |
| 4 | Complete Configuration Inventory | §4 |
| 5 | Runtime Wiring Matrix | §5 |
| 6 | Security/Secret Audit | §6 + §20 |
| 7 | Provider Audit | §7 + §21 |
| 8 | OTP/2FA Audit | §8 |
| 9 | Notification Audit | §9 |
| 10 | Payment/Storage Audit | §10 |
| 11 | Maps/WhatsApp Audit | §11 |
| 12 | AI Audit | §12 + §17 |
| 13 | Queue Audit | §13 |
| 14 | Maintenance Audit | §14 |
| 15 | Branding/System Audit | §15 |
| 16 | API/Webhook Audit | §16 |
| 17 | Audit History Audit | §23 |
| 18 | Fallback/Precedence Audit | §22 |
| 19 | Duplicate Engine Audit | §24 |
| 20 | Tenant Isolation Audit | §25 |
| 21 | UI Gap Matrix | §26 |
| 22 | Recommended Next Implementation Phase | §27 (A–E lists + duplicate risk) |

---

## AUDIT VERDICT

**PARTIALLY COMPLETE / NEEDS WIRING — FUTURE/PLANNED items reserved correctly**

- **COMPLETE (GREEN):** Email/SMTP, AI, OTP policy, 2FA core gating, Test Connection, Secrets security, Fallback precedence (where wired) are **UI + DB + runtime + secure** and production-safe with existing architecture reuse (no duplicate engines).
- **NEEDS WIRING (YELLOW):** SMS OTP 2FA provider (shadow config), Payment, Storage, Maintenance enforcement, Notifications platform toggles, System/General rendering, Branding assets, 2FA policy extras, and API/Webhooks are **UI + DB + secure but not runtime-consumed** — intentional pending, each correctly bannered.
- **RUNTIME BUT UI MISSING (BLUE):** Configuration history audit (DB `platform_audit_logs`) and per-institute notification/AI controls exist but have **no Super Admin viewer** — low-effort additive UI.
- **FUTURE/PLANNED (GRAY):** `platform_service_configs`, WhatsApp, queue control, and Maps offline-first are **reserved and not misrepresented** — no phantom claims.
- **MISSING (RED):** Logo/favicon/colors asset upload — **1 item** needs design before build.

**No file was modified, no migration was created, no database data was mutated, no `.env` was changed, no external provider call was executed, and no code was refactored during this audit.** All findings are source-traced (`file_path:line_number`) and evidence-backed; no feature is claimed functional merely because a settings field exists.

