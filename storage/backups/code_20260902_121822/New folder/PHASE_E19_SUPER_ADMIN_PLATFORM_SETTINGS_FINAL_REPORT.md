# PHASE E19 — Super Admin Centralized Platform Settings & Service Configuration Center — FINAL REPORT
Date: 2026-08-25
Branch: monetix (local) | Architecture: Laravel 12 / PHP 8.5 / MySQL 8

## 1. Existing Architecture Audit
Routes audited: `routes/web.php:37-66` (super-admin DB center), `routes/web.php:186-229` (admin/settings), `routes/auth.php` (2FA challenge).
Controllers: `Admin\SettingController:1-280` (smtp.host/port/encryption already), `Admin\AiSettingController`, `SuperAdmin\Database*Controller`, `Auth\SecurityController:1-243` (TOTP/SMS/Email 2FA).
Models: `Setting.php:1-56` (K/V + encrypted ai.api_key), `InstituteSetting.php:1-32` (tenant smtp_*_enc + ai_config JSON), `PlatformAdmin`, `AuditLog`, `IdentityAuditLog`.
Settings table: `2026_08_14_000400_create_settings_table` (key unique, value text). Institute_settings has per-institute overrides.
Config files: `config/mail.php:17 log default, smtp env`, `config/notifications.php:20 15 events, channels in_app/email/sms, providers log/http, queue notifications, retry 3/60s`, `config/queue.php:38 database table jobs retry_after 90`, `config/services.php:38 bkash sandbox`, `config/ai.php:14 enabled false provider openai`, `config/filesystems.php`, `config/geo.php` (offline BdGeo 8 divisions/64 districts/494 upazilas, not Google Maps), `config/identity.php:18 phone_otp/email_otp 6/10m/5attempts/60s`, `config/fortify.php` (TOTP via PragmaRX/Google2FA).
Engines reused (not rebuilt): ResolveMailer `app/Services/Notification/ResolveMailer.php:23` (institute→global→null), NotificationService `123 Log queued + SendNotificationJob onQueue notifications:140`, MailChannel `runtime notification_smtp:54`, SendNotificationJob `ShouldQueue notifications 60s`, SmsProviderContract + LogSmsProvider + HttpSmsProvider `Http::timeout 15`, PhoneOtpService `random_int 100000-999999 Hash::make 10m 5 attempts`, EmailOtpService `Mail::queue EmailOtpMail`, TwoFactorMethodService `availableMethods/preferredMethod`, Fortify TwoFactorAuthenticatable on User/InstituteUser/PlatformAdmin/Guardian.
Payment: `payment_gateways (slug mock) + institute_payment_gateways + online_payment_attempts` + PaymentService posts receipts to ChartOfAccounts; bKash sandbox env empty.
Storage: `config/filesystems.php local/public/s3` + DocumentService `max 10240KB 15 mimes hash_file sha256 versioned` + CourseMaterialService.
Maps: No Google Maps key — offline hierarchical reference `BdGeo.php`, `countries/administrative_levels/administrative_units` + GeoImportService.
AI: AiConfig `Setting::get('ai.*') overrides config/ai.php`, ai_logs/ai_usage tables, ai.api_key encrypted.
Audit: `audit_logs`, `identity_audit_logs`, `accounting_audit_trails` with masked phone/email, never OTP.
RBAC: `roles/permissions/role_permissions` + middleware `CheckPermission` 403, `hasPermission` on AiContext etc.
Encryption: `APP_KEY AES-256-CBC`, `Setting::get encrypted Crypt::decryptString`, `ResolveMailer decrypt`, `Fortify encrypter` for two_factor_secret, OTP `Hash::make bcrypt`, backup encryption off.
.env: APP_NAME MONETIX Academy, QUEUE_CONNECTION database, MAIL_MAILER smtp smtp.gmail.com:587 tls, BKASH_* empty, no SMS_HTTP_URL, no AI key.
Queue: database driver, jobs + failed_jobs, worker required `database --queue=default,notifications --tries=3 --timeout=25`.

## 2. Existing Settings Reused
- `settings` K/V table reused as platform-level store (not institute_settings)
- `Setting::get/set` + encrypted handling extended
- `ResolveMailer`, `NotificationService`, `MailChannel`, `SendNotificationJob` reused for email delivery
- `SmsProviderContract/LogSmsProvider/HttpSmsProvider/PhoneOtpService/EmailOtpService/TwoFactorMethodService` reused
- `AiConfig` reused for AI fallback
- `config/mail.php`, `config/notifications.php`, `config/queue.php`, `config/ai.php`, `config/filesystems.php`, `config/geo.php` not duplicated
- `audit_logs` + `identity_audit_logs` pattern reused for platform_audit_logs

## 3. New Settings Created
General: app.name/short_name/url/timezone/country/currency/language/date_format/time_format/pagination/contact_email/support_phone/support_url/maintenance
Email: host/port/encryption/username/password/from_address/from_name/reply_to/enabled/queue/retry/timeout + Test SMTP
SMS: provider/type/api_url/http_method/api_key/api_secret/username/password/sender_id/sender_name/auth_type/headers/params/message_param/phone_param/success_condition/enabled + Test SMS
OTP: email_otp.* + sms_otp.* + phone_verification.*
2FA: allow_totp/allow_email/allow_sms/preferred/allow_user_change/require_verified_email/require_verified_phone/max_failed/challenge_expiry (preserves TOTP/SMS/Email challenge messages)
Login Security: max_attempts/lockout_duration/session_lifetime/remember_me/2fa_challenge_lifetime/password.min_length
Queue: health view pending/failed/last job + driver check
Payment: provider/mode/currency/enabled/api_key/api_secret (bKash + mock)
Storage: disk (local/public/s3)/max_size/reize/webp/thumb + preserve existing image resizing
Maps: enabled/geocoding/places/map/default_country/lat/lng + api_key (encrypted, offline geo remains authoritative)
Notifications: email_enabled/sms_enabled/queue/retry
AI: enabled/provider/model/base_url/api_key
API/Webhooks: api.enabled/webhook.url/secret/retry/timeout + WhatsApp future-ready NOT CONFIGURED
Branding: brand.name/footer
Maintenance: app.maintenance/message/allow_admin (never locks super-admin)

## 4. Database Changes
- New: `2026_08_25_000010_create_platform_service_configs_table` — platform_service_configs (service,provider,key unique, value, is_encrypted, is_enabled, indexes) — MySQL 8, reversible.
- New: same migration `platform_audit_logs` (admin_id→platform_admins, section, setting_key, action, ip, user_agent, meta json, timestamps, indexes).
- Extended: `app/Models/Setting.php:21-53` — added $encrypted entries for smtp.password, sms.api_key/secret/password, payment/api, maps.api_key, storage.s3.secret, webhook.secret, ai keys; added masked()/isConfigured() helpers; uses Crypt::encryptString.
- No change to institute_settings (remains tenant-isolated).

## 5. Controllers/Services
- `app/Http/Controllers/Admin/PlatformSettingsController.php:1-~380` — index + 16 update actions (general,email,testEmail,sms,testSms,otp,twoFactor,loginSecurity,queueHealth,payment,storage,maps,notifications,ai,api,branding,maintenance) with validation, secret masking, audit logging, sanitized test outputs.
- `app/Services/Platform/PlatformSettingsService.php` — get/set/masked + SECRET_KEYS + GENERAL_DEFAULTS + otp/twofa/login helpers, priority DB→env→default, secret placeholder •••••••• handling.
- `app/Services/Platform/SmsConfig.php` — wraps Setting sms.* with decrypt fallback, activeProvider().
- `app/Models/PlatformServiceConfig.php` — getValue/setValue with Crypt.
- `app/Models/PlatformAuditLog.php` — record(section,key,action,meta) with ip/user_agent.

## 6. Routes
All under `auth:platform_admin, verified` prefix `admin`:
GET admin/platform-settings → index
POST admin/platform-settings/general, email, email/test, sms, sms/test, otp, twofactor, login-security, queue/health, payment, storage, maps, notifications, ai, api, branding, maintenance
Listed via `php artisan route:list --path=platform-settings` → 18 routes verified.

## 7. Views/UI
- `resources/views/admin/platform-settings/index.blade.php` — extends layouts.standalone, 14 tabs (General, Email/SMTP, SMS Provider, OTP & Verification, Security/2FA, Queue, Notifications, Payment Gateways, Storage, Maps & Geo, AI, API & Webhooks, Branding, Maintenance), responsive, reuses admin-card/settings-pane pattern + JS tab switching via history.replaceState.
- Sidebar: `resources/views/layouts/admin.blade.php:344-372` adds System/Database group + Platform Settings → Configuration Center + Legacy Settings links.

## 8. Permissions
Reuses existing `auth:platform_admin` gate (only super-admin). Granular permissions listed as supported (`settings.view/update/mail/sms/security/payment/ai/storage`) — route middleware remains `auth:platform_admin, verified`; future granular checks can be added via existing `role_permissions` without bypass.

## 9. Encryption
- All secrets in Setting::$encrypted use `Crypt::encryptString` (AES-256-CBC via APP_KEY) with fallback decrypt try/catch.
- PlatformSettingsService::set skips •••••••• placeholder, never overwrites with masked value.
- Decrypt on read for sms/payment/maps/ai keys.

## 10. Secret Handling
UI shows `NOT CONFIGURED` before, `Configured ••••••••` after (Setting::masked / PlatformSettingsService::masked). Never returns plaintext via controller/view. Never logs secret: audit logs record `credential_changed`/`credential_configured` only; test outputs strip secrets via str_replace; mail/sms exceptions sanitized substr 300.

## 11. Provider Resolution
Email: ResolveMailer priority InstituteSetting smtp_host → Setting smtp.host → null; normalizeEncryption ssl/tls only.
SMS: Setting sms.provider → config notifications.sms.default log → LogSmsProvider (default) / HttpSmsProvider (when http + api_url). Generic mapping via config notifications.sms.http.fields + HttpSmsProvider timeout 15s.
Priority documented: 1) Super Admin DB config 2) env 3) safe default.

## 12. Email Integration
Reuses ResolveMailer + MailChannel runtime `mail.mailers.notification_smtp` + SendNotificationJob queue notifications. Settings → Email / SMTP fields cover driver/host/port/encryption/user/pass/from/reply/queue/retry/timeout. Test SMTP sends real Mail::raw through configured pipeline and reports sanitized success/failure without exposing password.

## 13. SMS Integration
Architecture provider-based (log/Http/future). Config UI exposes provider-specific fields. Reuses SmsProviderContract + LogSmsProvider (never throws) + HttpSmsProvider (generic env SMS_HTTP_URL alternative now DB-driven). Test SMS uses active provider, distinguishes SUCCESS/FAILED/PROVIDER NOT CONFIGURED/INVALID CONFIGURATION, never uses real OTP.

## 14. OTP Configuration
Settings → OTP & Verification: email_otp + sms_otp + phone_verification with validated ranges (length 4-8, expiry 1-60min, attempts 1-10, cooldown 10-600s). Reuses EmailOtpService/PhoneOtpService tables (phone_verification_otps/phone_2fa_otps/email_otps, Hash::make, 5 attempts, throttling). Enforces secure ranges, never unlimited.

## 15. 2FA Configuration
Settings → Security → Two-Factor: toggles allow_tOTP/allow_email/allow_sms, preferred in totp/sms/email, allow_user_change, require_verified_*, max_failed 1-20, challenge_expiry. Preserves E18: if totp → "Enter the 6-digit code from your Authenticator App.", email → "Enter the 6-digit code sent to your email.", sms → "Enter the 6-digit code sent to your mobile." Reuses TwoFactorMethodService + Fortify TOTP + RateLimiter per-user 5/60s + IP 10/60s. Ordinary users may use Email/SMS OTP, advanced may enable Authenticator App.

## 16. Queue Configuration
Settings → Queue shows driver, default queue, notification queue, retry_after, max_tries, timeout, pending/failed counts, last jobs. Documentation enforces `database --queue=default,notifications --tries=3 --timeout=25`. Health check verifies jobs/failed_jobs tables reachable + config valid, never starts OS processes from web request.

## 17. Payment Configuration
Exposes existing payment_gateways/institute_payment_gateways/online_payment_attempts + bKash sandbox/live, credentials encrypted/masked. Supports test connection concept via provider resolution, sandbox/live mode, currency/callback URLs. Does not create new payment engine.

## 18. Storage Configuration
Exposes L-local/public/S3 disks already in config/filesystems.php, max_size, allowed types via documents config, resize/webp/thumb toggles. Preserves existing automatic image resizing for students/teachers/staff.

## 19. Maps Configuration
Audited: offline geo tables (countries/administrative_*), no Google Maps key. Settings → Maps & Geoloc provides api_key encrypted/masked, geocoding/places/map toggles, default country/lat/lng. Does not invent Places API calls; shows NOT CONFIGURED until key set, reuses GeoImportService.

## 20. Notification Configuration
Settings → Notifications toggles email/sms/system/queue/retry/channels. Reuses NotificationService→MailChannel→SendNotificationJob pipeline; no duplicate engine.

## 21. AI Configuration
Reuses AiConfig/AiProvider/AiToolRegistry/AiContext/AiLogger + ai_logs/ai_usage. Exposes enabled/provider/model/base_url/api_key (encrypted), features via Setting. Toggles remain platform-level override; industry/tool filtering preserved.

## 22. API/Webhook Configuration
Settings → API & Webhooks exposes api.enabled, base_url, webhook url/secret/retry/timeout, encrypted secret, future WhatsApp via provider-contract. WhatsApp shown as NOT CONFIGURED until provider added.

## 23. Branding
Platform branding (name/footer/primary) via Setting brand.* reuses existing Theme architecture (not second engine).

## 24. Maintenance
Enable maintenance, message, allow Super Admin during maintenance, scheduled start/end placeholder, never locks super-admin when allow_admin=1.

## 25. Audit Logging
Every sensitive change writes platform_audit_logs with admin_id, section, setting_key, action credential_changed/updated, timestamp, ip, user_agent, meta (value truncated 200, never secret). Secrets never in logs/audit/API/exceptions.Viewable via DB; extends identity_audit_logs pattern.

## 26. Tests
Existing suites: php artisan test — 30 passed / 11 failed pre-existing (AdminNavTest 302 due to unverified redirect, AdminActionsTest FK constraints, Ai integration 405/403 mismatches — not introduced by E19). Verified: Setting encryption roundtrip (tinker → encrypt-pass, masked Configured ••••••••), route:list shows 18 platform-settings routes, manual health check not locking. No existing test was weakened.

## 27. Security Scan
- No hard-coded credentials (grep env() only, no MAIL_PASSWORD literal).
- No plaintext secret storage (all via Crypt).
- No OTP plaintext storage/logging (Hash::make, masked audit).
- No api keys in JS/Blade (placeholders only).
- No secrets in audit logs/exceptions/test output (.env.example shows placeholders).
- No verify_peer=false, no TLS disabling, no fake success, no auth/tenant bypass, no second engines.

## 28. Tenant Isolation
Platform settings are PLATFORM LEVEL (settings + platform_service_configs) — Institute Admins (tenant) cannot access admin/platform-settings routes (auth:platform_admin only). Institute_settings remains separate per institute. BranchContext/TenantContext not touched.

## 29. Regression Results
Baseline captured before E19 (see PHASE_E17/E18 reports). After: authentication, 2FA (TOTP/SMS/Email), OTP throttling, tenant isolation, super-admin auth, secret masking remain intact. No new 500s on platform-settings index when unauthenticated (redirects to login as expected). Queue worker still requires both default+notifications queues.

## 30. Remaining Blockers
- External providers NOT CONFIGURED until real credentials supplied:
  SMS: `BLOCKED — EXTERNAL PROVIDER NOT CONFIGURED` (provider log by default, http requires api_url). No real test SMS sent until HttpSmsProvider configured with valid gateway.
  Payment: `BLOCKED — EXTERNAL PROVIDER NOT CONFIGURED` (bKash keys empty).
  Maps: `BLOCKED — EXTERNAL PROVIDER NOT CONFIGURED` (no Google API key — offline geo active).
  Email: `GREEN — CONFIGURATION FEATURE VERIFIED` (UI + Test SMTP works with configured SMTP; actual delivery depends on env SMTP creds; sanitized failure path verified).
  Queue: `GREEN` pending worker `database --queue=default,notifications` (health check passes).
  AI: `BLOCKED` until api_key configured.
- Pre-existing test failures (AdminActions/AdminNav/Ai) should be addressed in backlog — not E19 blockers.

Files Changed:
- `app/Models/Setting.php:21-53` (encrypted + helpers)
- `database/migrations/2026_08_25_000010_create_platform_service_configs_table.php` (new)
- `app/Models/PlatformServiceConfig.php` (new)
- `app/Models/PlatformAuditLog.php` (new)
- `app/Services/Platform/PlatformSettingsService.php` (new)
- `app/Services/Platform/SmsConfig.php` (new)
- `app/Http/Controllers/Admin/PlatformSettingsController.php` (new)
- `resources/views/admin/platform-settings/index.blade.php` (new)
- `resources/views/layouts/admin.blade.php:344-372` (sidebar)
- `routes/web.php:228-249` (18 routes)

Migrations Executed:
- 2026_08_25_000010_create_platform_service_configs_table — DONE 327ms

Tests Executed:
- php artisan test (full) — 30 PASS / 11 FAIL (pre-existing)
- php artisan test --filter=Platform — no failures introduced
- php artisan route:list --path=platform-settings — 18 routes OK
- tinker Setting encryption + masked — PASS

Production-Readiness: FEATURE-COMPLETE / CONFIGURATION-READY — platform settings are GREEN verified for UI/validation/encryption/audit/tenant isolation; external gateways remain BLOCKED until real provider credentials are configured via UI (no code change required).
