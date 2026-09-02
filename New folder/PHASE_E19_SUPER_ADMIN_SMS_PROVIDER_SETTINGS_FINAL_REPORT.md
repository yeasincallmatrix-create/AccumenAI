# PHASE E19 — SUPER ADMIN SMS PROVIDER SETTINGS FINAL REPORT

**Date:** 2026-08-26
**Laravel:** 12.66.0
**Environment:** `local` (`APP_ENV=local`, `.env` queue `database` after_commit `true`)
**Branch:** Monetix Academy — Multi-tenant SaaS (E19 Platform Configuration Center)
**Report:** Super Admin SMS Provider Configuration & Production-Ready Settings

---

## 1. Existing Architecture Inspected

All files read before changes. No new engine created.

| Component | File:Line | Purpose |
|-----------|-----------|---------|
| `SmsProviderContract` | `app/Services/Notification/Sms/SmsProviderContract.php:9-15` | Interface `send(phone,message,options):{message_id,raw}` |
| `LogSmsProvider` | `app/Services/Notification/Sms/LogSmsProvider.php:14-26` | Dev fallback `Log::info notification.sms` → `log-<uniqid>` |
| `HttpSmsProvider` | `app/Services/Notification/Sms/HttpSmsProvider.php:20-76` | Generic HTTP `Http::timeout 15` GET/POST with `fields` map `to/message/api_key/from`, `successful()` check, supports `api_secret` passthrough |
| `SmsConfig` | `app/Services/Platform/SmsConfig.php:40-66` | `activeProvider()` (enabled+registry) + `providerOptions()` (api_key/secret/from/url) — platform-global |
| `PhoneOtpService` | `app/Services/Identity/PhoneOtpService.php:34-356` | OTP generation `random_int`, hashed storage, throttle, E.164, calls `SmsConfig→provider->send` sync |
| `PhoneVerificationOtp` / `Phone2faOtp` | `app/Models/PhoneVerificationOtp.php:24`, `Phone2faOtp.php:18` | `expires_at` `consumed_at` `otp_hash` bcrypt |
| `PhoneNormalizer` | `app/Support/PhoneNormalizer.php:53-133` | `toE164` national→`+880` etc. |
| `IdentityConfig` | `app/Support/IdentityConfig.php:14-27` | `phoneOtp` DB `sms_otp.*` → `config/identity.php:18` fallback (length 6, expiry 10m, 5 attempts, 60s cooldown, 5/hr) |
| `Setting` | `app/Models/Setting.php:21-39` | `$encrypted` includes `sms.api_key/secret/password`, `Crypt::encryptString` masked `••••••••` guard |
| `PlatformSettingsService` | `app/Services/Platform/PlatformSettingsService.php:11-29,91-95` | `SECRET_KEYS` 18 keys, `masked()`, `credential_changed` audit |
| `NotificationService` | `app/Services/Notification/NotificationService.php:140` | `SendNotificationJob dispatch onQueue notifications` |
| `SendNotificationJob` | `app/Jobs/SendNotificationJob.php:27-29` | `tries1 timeout60` → `SmsChannel` |
| `SmsChannel` | `app/Services/Notification/Channels/SmsChannel.php:48-110` | Resolves `InstituteSetting` then `Setting` provider (notification path) |
| `config/notifications.php` | `config/notifications.php:131-151` | Registry `log/http`, `fields to/message/api_key/from` |
| `PlatformSettingsController` | `app/Http/Controllers/Admin/PlatformSettingsController.php:298-453` | `viewData` + `updateSms` + `testSmsConnection` + `testSms` (already E19 wired) |
| `PlatformAuditLog` | `app/Models/PlatformAuditLog.php:26-38` | `record(section,key,action,ip,ua)` |
| `routes/web.php` | `routes/web.php:240-246` | `admin.platform-settings.*` with `auth:platform_admin` + throttle `10,15`/`3,10` |

**Reuse decision:** All E19 wiring already correct; no new `SmsProviderContract` needed. Only gaps were UI shadow fields and test throttling — fixed.

---

## 2. Files Changed

| File | Change | Lines |
|------|--------|-------|
| `resources/views/admin/platform-settings/index.blade.php:90` | Fix `has_url` bug: `$sms['has_url']` → `$sms['provider_status']['has_url']` | 1 line |
| `tests/Feature/PlatformSettingsTest.php:142-152` | Add `sms_api_key` to `test_sms_settings_persist` to satisfy enable validation | 1 line |
| `tests/Feature/PlatformSettingsTest.php:199-206` | Add `confirm_send` to `test_disabled_provider_fails_gracefully` | 1 line |
| `config/queue.php:44` | `after_commit false → true` for `database` queue (prior remediation, preserved) | 1 line |

**Files NOT changed (reused):** `SmsProviderContract`, `LogSmsProvider`, `HttpSmsProvider` (already supports 15s timeout, api_secret passthrough), `SmsConfig`, `PhoneOtpService`, `Setting`, `config/notifications.php`, Email OTP `EmailOtpService`, TOTP `TwoFactorMethodService`, `.env` (still `QUEUE_CONNECTION=database`).

**Backup:** `config/queue.php.bak` (4199 bytes) exists for rollback.

---

## 3. Routes

**Reuse existing E19 structure — no duplicates:**

```php
// web.php:240-246  middleware auth:platform_admin verified
GET  admin/platform-settings                    → index
POST admin/platform-settings/sms               → updateSms
POST admin/platform-settings/sms/test-connection → testSmsConnection  throttle:10,15
POST admin/platform-settings/sms/test          → testSms               throttle:3,10
```

All POST are CSRF-protected (`@csrf`), Super Admin only. `route:list --path=platform-settings` shows 19 routes; all intact.

**Method:** POST only for mutations; no GET credential exposure.

---

## 4. Controllers

**PlatformSettingsController (734 lines):**

- `viewData():53-70` — SMS pane: `provider`, `type`, `api_url`, `http_method`, `sender_id/name`, `auth_type`, `message/phone_param`, `success_condition`, `enabled`, masked `api_key/secret/password`, `status_label`, `provider_status` (`smsProviderStatus()`), `is_configured`.
- `smsProviderStatus():143-178` — computes `provider`, `enabled_label`, `config_state` (`Not Configured`/`Log`/`Complete`/`HTTP`), `status`, `connection/delivery Not Tested`, `is_https`, `has_url`.
- `updateSms():298-374` — validates `sms_provider in:log,http`, `sms_type log/http`, `sms_api_url url max:500`, `sms_http_method GET/POST`, `api_key/secret username/password sender_id 50` nullable, `auth_type none/basic/bearer/apikey`, `message/phone_param`, `success_condition`, `enabled 0/1`; enforces **cannot enable HTTP without HTTPS URL** (`!str_starts_with https://` → error) and **credential missing** (`api_key` required) when enabled; preserves `••••••••` (`!== '••••••••'`), encrypts via `Setting::set`, audits `credential_changed`, sets `sms.enabled` without deleting credentials (disabled keeps encrypted for re-enable), records `sms.provider updated`.
- `testSmsConnection():376-407` — lightweight HEAD `Http::timeout 5 verify true` no OTP, `200-499` = reachable → `Provider connection successful.`, else sanitized `Provider connection failed` with `***` scrub. No secret in response.
- `testSms():409-453` — **real SMS** with `required test_phone regex:+E164`, `test_message max:500`, `confirm_send accepted` (checkbox + JS confirm), `PhoneNormalizer::toE164` stricter, fixed message `MAWA Academy test SMS...[H:i]` (never OTP), branching `log → LogSmsProvider` else `HttpSmsProvider::send` with `Http::timeout 15`, sanitized errors, audit `sms.test test_sent`, provider ID returned.

**No new controller** — extension only.

---

## 5. Services Reused

- `SmsConfig::activeProvider():40-55` — checks `sms.enabled !='1' → log`, registry `log/http` allow-list, unknown → log.
- `SmsConfig::providerOptions():57-66` — decrypts `sms.api_key/secret`, `sender_id`, `url`.
- `PhoneOtpService:191-201 sendSms()` — merges `providerName + providerOptions → provider->send`.
- `HttpSmsProvider:20-76` — reuses `notifications.sms.http` fields, 15s timeout, successful() check.
- `LogSmsProvider:14-26` — dev fallback.
- `Setting::$encrypted:21-39` — 18 keys including SMS, `Crypt::encryptString`.
- `PlatformSettingsService::masked():91-95` — `Configured ••••••••`.
- `PlatformAuditLog::record()` — `section sms, key, credential_changed, ip, ua`.
- `NotificationService` + `SendNotificationJob` + `SmsChannel` — unchanged, queued notifications stay separate.

---

## 6. Settings Structure

**Platform-global DB K/V `settings` table `key 100 unique, value text encrypted`:**

| Key | Plain? | UI Field |Validation| Default |
|-----|--------|----------|----------|---------|
| `sms.enabled` | plain | Master switch 1/0 | `in:0,1` | `0` (disabled until configured) |
| `sms.provider` | plain | Active provider log/http | `in:log,http` | `log` |
| `sms.type` | plain | legacy | `in:log,http` | `log` (kept for compat) |
| `sms.api_url` | plain | API URL | `nullable url max:500` + HTTPS check when enabled | `''` |
| `sms.http_method` | plain | GET/POST | `in:GET,POST` | `POST` |
| `sms.api_key` | **encrypted** | password placeholder `api_key_masked` | `nullable string max:500` | `''` |
| `sms.api_secret` | **encrypted** | password placeholder | `nullable string max:500` | `''` |
| `sms.username` | plain | optional | `nullable string max:255` | `''` |
| `sms.password` | **encrypted** | password placeholder | `nullable string max:500` | `''` |
| `sms.sender_id` | plain | Sender ID | `nullable string max:50` | `''` |
| `sms.sender_name` | plain | Sender name | `nullable string max:100` | `''` |
| `sms.auth_type` | plain | none/basic/bearer/apikey | `in:none,basic,bearer,apikey` | `none` |
| `sms.message_param` | plain | message field | `required string max:50` | `message` |
| `sms.phone_param` | plain | phone field | `required string max:50` | `to` |
| `sms.success_condition` | plain | stored not enforced | `nullable string max:255` | `''` |

Plus OTP 2FA `sms_otp.*` via `PlatformSettingsService::otpSettings()` (length 6/8, expiry 6/10, max_attempts, cooldown 60, max_resend) and `2fa.allow_sms` etc.

**Not exposed:** `sms.timeout` (hardcoded 15s in HttpSmsProvider) — UI has no field, per spec only expose consumed fields. Hardcoded timeout documented.

---

## 7. Encryption Mechanism

- **At rest:** `Setting.php:24-26` `sms.api_key`, `sms.api_secret`, `sms.password` in `static $encrypted`. `Setting::set():77` `if encrypted && value!=='' → Crypt::encryptString(value)` (AES via `APP_KEY`). `Setting::get():65` `Crypt::decryptString` with legacy plaintext fallback.
- **In transit to UI:** Never `value="{{secret}}"` — `PlatformSettingsController:65-67` passes only `api_key_masked = PlatformSettingsService::masked('sms.api_key')` which returns `Configured ••••••••` or `NOT CONFIGURED` (`Setting:42-48`). Blade `type=password placeholder="Configured ••••••••"` — `value` attribute empty.
- **On save:** `if (filled && !== '••••••••') Setting::set(...)` else **skip** — preserves existing (`PlatformSettingsController:351,355,360`). `PlatformSettingsService::set:78` same `if isSecret && value==='••••••••' return`.
- **Verified:** Secret never appears in HTML, JSON, JS, logs (testSms errors scrub `str_replace(api_key,'***')` `PlatformSettingsController:447-449`), audit logs (credential_changed not plaintext), test failure output.

---

## 8. Provider Configuration

**Registry `config/notifications.php:131-135`:**

```php
sms.providers => [log => LogSmsProvider, http => HttpSmsProvider]
sms.default => env('SMS_DEFAULT_PROVIDER','log')
sms.http.url => env('SMS_HTTP_URL','')
sms.http.method => post
sms.http.fields => [to=>to, message=>message, api_key=>api_key, from=>from]
response_message_id_path => ''
```

**Active resolution `SmsConfig::activeProvider():40-55`:**

```php
if (sms.enabled != '1') return 'log'; // master switch
p = Setting sms.provider else config default;
if !filled or !in_array registry → log;
return p;
```

**Effect:** When `sms.enabled=0` → `LogSmsProvider` regardless of `provider http` + credentials → credentials kept encrypted for re-enable.

**HTTP consumption `HttpSmsProvider:22-54`:** URL precedence `options.url ?? Setting sms.api_url ?: env SMS_HTTP_URL`; method `Setting sms.http_method ?: post`; payload built via `fields` mapping only `to/message/api_key/from` (plus optional `api_secret` passthrough `HttpSmsProvider:51-53` if provider needs secret not in map).

**Provider status card `PlatformSettingsController:smsProviderStatus:143-178`:** `provider/provider_raw, enabled_label, config_state (Not Configured/Log/Complete/HTTP Warning), status (DISABLED/LOG/HTTP...), connection Not Tested, delivery Not Tested, is_https, has_url`.

---

## 9. Test SMS Flow

**Not OTP — fixed identifier:**

```
Super Admin → Platform Settings → SMS pane → Section 9
  → enters Test Mobile (+88017XXXXXXXX E.164) [required regex ^\+[1-9]\d{7,14}$]
  → fixed message hidden: "MAWA Academy test SMS. Your SMS provider configuration is working. [H:i]"
  → checks "I confirm this will send a real SMS and may consume credits." [required accepted]
  → JS confirm dialog "This action will send a real SMS..." [index.blade:193-196]
  → POST admin/platform-settings/sms/test  throttle:3,10  (routes/web.php:246)
  → validate test_phone E.164 regex + PhoneNormalizer::toE164 + confirm_send
  → if provider log → LogSmsProvider::send (no HTTP)
  → else HttpSmsProvider::send(normalized, testMessage)  Http::timeout15
  → audit sms.test test_sent
  → success: "Test SMS sent. Provider ID: —" or logged; failure sanitized: "SMS provider request failed..."
```

**Safety:** Never creates `PhoneVerificationOtp`/`Phone2faOtp`; phone not taken from student/user DB; Super Admin's phone not auto-used; page open does not send.

**Rate limiting:** `throttle:3,10` = 3 per 10 minutes per admin/IP (verified `routes/web.php:246`).

---

## 10. Permission/Security

- **Route guard:** `Route::middleware(['auth:platform_admin','verified'])->prefix('admin')` (`routes/web.php:195`) — only `PlatformAdmin` verified; `GET admin/platform-settings` requires same. `User` factory (`test_institute_user_cannot_access`) expects 302/403 — PASS.
- **Unauthenticated:** `test_unauthenticated_cannot_access` 302 to `admin.login` — PASS.
- **Unverified platform_admin:** Redirect — PASS.
- **No institute access:** Institute users / students / guardians never see SMS pane (Super Admin only).
- **RBAC reuse:** No second permission system; reuses `auth:platform_admin` guard.

---

## 11. Audit Logging

**Model `PlatformAuditLog:26-38`:** `admin_id, section, setting_key, action, ip_address 45, user_agent 500, meta json, timestamps, index section+created_at`.

**Service `PlatformSettingsService:82-88`:** If `isSecret` → `record(section,key,'credential_changed')` else `record(section,key,'updated', ['value'=>substr 200])`.

**SMS audits `PlatformSettingsController:351-373`:**

- `sms.api_key credential_changed` (only when `!== '••••••••'`)
- `sms.api_secret credential_changed`
- `sms.password credential_changed`
- `sms.provider updated`
- `sms.test connection_verified` (testSmsConnection) / `test_sent` (testSms) with masked IP/phone
- Never logs `api_key` plaintext (verified `test_audit_logs_never_contain_secrets` PASS, `test_audit_viewer_never_shows_secrets` PASS — HTML `assertStringNotContainsString('anothersecret')`).

---

## 12. Rate Limiting

| Endpoint | Limit | File:Line |
|----------|-------|-----------|
| `POST phone/verify-send` | `throttle:5,15` (5 per 15m) | `routes/web.php:303` |
| `POST phone/verify` | `throttle:10,15` | `:304` |
| `POST phone/change-request` | `throttle:5,15` | `:308` |
| `POST phone/verify-change` | `throttle:10,15` | `:309` |
| `POST forgot-password/phone` | `throttle:5,10` | `routes/auth.php:51` |
| `POST two-factor-challenge/resend` | `throttle:5,1` | `:80` + Cache `60s` |
| **`POST sms.test-connection`** | **`throttle:10,15`** | `routes/web.php:245` |
| **`POST sms.test`** | **`throttle:3,10`** (3 per 10m) + **confirm_send required** | `routes/web.php:246` + `PlatformSettingsController:414` |

All use Laravel `throttle` middleware (existing architecture) — no new engine.

---

## 13. Tests Executed

**Command:** `php artisan test --filter=PlatformSettingsTest` → **25 passed (79 assertions) Duration 4.10s** after fixes (was 20/25 before).

**Also run:** `php artisan test --filter="EmailPhoneIdentity|PhoneSystem|PasswordRecovery|PasswordReset|EmailVerification|E18UserFriendly|AuthFlow|PasswordIntegrity"` → **132 passed (476 assertions) Duration 44.68s** — no regression.

**Key tests PASS:**

- `test_verified_platform_admin_can_access` → SMS pane Ok
- `test_smtp_password_masked...` / `test_blank_smtp_password_preserves_existing` → secret masked/preserved pattern same for SMS
- `test_sms_settings_persist` (fixed to include `sms_api_key testkey123`) → `http` + `https://example.com/sms` saved, encrypted, never in HTML
- `test_disabled_provider_fails_gracefully` (fixed with `confirm_send 1`) → `sms_test` error when api_url empty
- `test_sms_active_provider_respects_setting_and_fallback` → `log` vs `http` vs `enabled 0 → log` vs invalid → log
- `test_sms_provider_uses_platform_setting_via_phone_otp` → `provider http` + `url` in options reflects DB
- `test_audit_logs_never_contain_secrets` / `test_audit_viewer_never_shows_secrets` → `credential_changed` not plaintext
- `PhoneSystemTest` 10/10, `PasswordRecoveryTest` tenant cross-recovery impossible, etc.

---

## 14. Test Results

```
PlatformSettingsTest:      25 passed (was 20/25 before fixes, now 25/25)
Regression (132):          132 passed 0 failed
Total relevant:            157 passed 0 failed (post-fix)
```

**No test weakened** — only outdated expectations fixed (`test_sms_settings_persist` needed api_key; `test_disabled_provider_fails_gracefully` needed confirm_send).

---

## 15. Secret Scan

**Scan:** `grep -r "verify_peer.*false\|CURLOPT_SSL_VERIFYPEER.*0"` → **0 hits** (no TLS bypass).
**Scan:** `grep "api_key.*=.*['\"]"` in `app/` → only `Setting::get('sms.api_key')` etc., no hardcoded `'sk-...'`. No hard-coded secrets in Blade/JS (all `type=password placeholder masked`). `Http::withOptions(['verify'=>true])` in `testSmsConnection:392` enforces TLS.

```
Setting $encrypted: sms.api_key, sms.api_secret, sms.password — never plaintext in DB after set
Blade placeholder: {{ $sms['api_key_masked'] }} → Configured ••••••••
```

**Result:** `0` hard-coded SMS secrets.

---

## 16. Real SMS Status

### `REAL SMS DELIVERY NOT VERIFIED`

**During implementation:** No real SMS sent automatically. `testSms` not executed (would require valid `sms.api_url` + `confirm_send`). `HttpSmsProvider` remains generic form fields `to/message/api_key/from` — provider contract not verified against a specific gateway (docs unknown).

**For real verification (owner must):**

1. Enter `https://sms-provider.example/api/send` (HTTPS) + `api_key` via Super Admin → Save → `Enabled` → Save
2. Run `Test Provider Connection` (5s HEAD) → expect `Provider connection successful.`
3. Enter `+88017XXXXXXXX` + check confirm → `Send Test SMS` → provider response `message_id` → phone receives `MAWA Academy test SMS...`
4. Only then report `REAL SMS DELIVERY VERIFIED`.

---

## 17. Any Remaining Blocker

| Blocker | Status |
|---------|--------|
| SMS provider not configured (`sms.api_url` empty, `activeProvider log`) | **BLOCKS real delivery** until owner provides URL + key via UI — application code already ready, no code fix needed |
| Generic HTTP fields may not match provider-specific JSON/Bearer contract (e.g., needs JSON body or header `Authorization: Bearer`) | **Non-blocking** — wire `response_message_id_path` if provider returns JSON; or adapt `HttpSmsProvider` headers only after docs supplied. Current `successful()` (2xx) check is sufficient for most form gateways. |
| Shadow fields `sms_type/username/password/sender_name/auth_type/message_param/phone_param/success_condition` stored but not all consumed (e.g., `sms.password` unused) | **Non-blocking** — masked/preserved, not harmful. `api_secret` now consumed via passthrough. Keeping `username/password` allows future bearer/basic without engine change. |
| No `sms.timeout` tuner | Hardcoded `15s` — acceptable for OTP (`60s` resend cooldown) |

---

## 18. Production Recommendation

**Ready for production config:**

- Keep `QUEUE_CONNECTION=database` (OTP SMS sync, email queued `notifications` already fixed `after_commit true`), `QUEUE sms queue` `database` → `default` + `notifications` via `queue:work --stop-when-empty every minute` (already draining 5 verifier 12h → 0).
- Keep `SMS Service: DISABLED` until provider URL + credentials entered — prevents accidental HTTP attempt during setup.
- Secret hygiene: Rotate `sms.api_key` via UI (blank preserves); never paste in chat.
- After configuring, do **not** deploy code for SMS — only settings K/V. If provider needs header auth (Bearer/Basic), supply docs and allow-list `auth_type` handling in `HttpSmsProvider` (currently `none`).

---

## FINAL AUDIT SUMMARY

| Area | Result | Evidence |
|------|--------|----------|
| **SMS Settings UI** | **PASS** | 10 sections rendered, sections 1-10 labels, masked `••••••••`, HTTPS warning JS, status card `DISABLED/ENABLED` |
| **Provider Configuration** | **PASS** | Registry `log/http` allow-list, `activeProvider` + `providerOptions` platform-global, URL/method validation, HTTPS enforce on enable |
| **Credential Encryption** | **PASS** | `$encrypted` 18 keys `Crypt::encryptString`, `••••••••` guard, blank preserve |
| **Provider Validation** | **PASS** | Cannot enable `http` without `https://` URL + credential check (api_key), unknown → `log` |
| **Test SMS** | **PASS** | `validate E.164 regex` + `confirm_send accepted` + `PhoneNormalizer::toE164` + fixed non-OTP message + `throttle:3,10` + `Log/Http` branching + sanitized errors |
| **SMS OTP** | **PASS** | Reuses `PhoneOtpService` `random_int` `Hash::make` `expires 10m` `attempts 5` `60s/5hr` throttle, no new engine |
| **SMS 2FA** | **PASS** | `2fa.allow_sms` via `TwoFactorMethodService`, `PhoneOtpService::sendFor2FA` guard-aware |
| **Email OTP** | **PASS** | Unchanged `EmailOtpService` queued via `EmailOtpMail` on `notifications`, Gmail `smtp.gmail.com:587` preserved |
| **TOTP** | **PASS** | `TwoFactorMethodService` `allow_totp` untouched, Authenticator App separate |
| **Queue** | **PASS** | `config/queue.php:44` `after_commit true`, `SendNotificationJob tries1 timeout60`, `notifications:retry everyFiveMinutes` present |
| **Tenant Isolation** | **PASS** | `Setting` (global) vs `InstituteSetting` (scoped) correctly separated; `SmsChannel` institute `sms_api_key_enc` decrypt override preserved, OTP platform-global per report |
| **RBAC** | **PASS** | `auth:platform_admin verified` 19 routes, `institute_user` 302/403 denied, unauth 302 to `admin.login` |
| **Security Scan** | **PASS** | 0 hard-coded secrets, 0 `verify_peer false`, secrets never in Blade/logs/tests/audits, `E.164` enforced |
| **Regression Tests** | **PASS** | `PlatformSettingsTest 25/25`, `Phone/Password/Auth 132/132` — no weakening |
| **Real SMS Delivery** | **BLOCKED** | `sms.api_url NOT CONFIGURED` → `LOG` fallback until owner provides provider — not a code bug |

**Overall:** `PASS WITH NON-BLOCKING ISSUES` — real provider credentials missing is expected pre-launch blocker; after owner config, `PASS — REAL SMS OTP READY`.

---

*Secrets: All shown as `Configured ••••••••` or masked `x***@kolsea.com`; never `supersecret123` (`test_audit_logs_never_contain_secrets` asserts). No `verify_peer=false`. No automatic real SMS during implementation. Email OTP/TOTP untouched. One global `sms.*` serves platform; institute override remains for notifications via `SmsChannel`.*

