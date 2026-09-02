# REAL SMS OTP END-TO-END PRODUCTION AUDIT

**Date:** 2026-08-26
**Laravel:** 12.66.0
**Environment:** `local` (`APP_ENV=local`, `.env` untouched, `QUEUE_CONNECTION=database`)
**Mode:** READ-ONLY — no source files modified, no .env changed, no migrations, no jobs dispatched, no queue workers started, no real SMS sent, no external provider calls. All checks via static inspection, route/config/database read-only SELECT, and existing tests. Secrets masked.
**Project Root:** `C:\xampp\htdocs\monetix` (local XAMPP; shared/cPanel host path `/home/USER/monetix` to be discovered)

---

## A. Overall Verdict

### `BLOCKED — REAL SMS OTP NOT PRODUCTION READY`

**Application integration READY, but real provider delivery NOT ready.**

The complete OTP generation → validation → security pipeline is correctly implemented (secure random, hashed storage, expiration, rate limits, tenant-safe provider selection, encrypted secrets, audit). However **no real SMS provider is configured**: `sms.enabled NOT CONFIGURED`, `sms.provider NOT CONFIGURED`, `sms.api_url NOT CONFIGURED`, `SMS_HTTP_URL env empty` → `SmsConfig::activeProvider()` returns `log` (LogSmsProvider) which only writes to `laravel.log` and never reaches a phone. Real OTP **will not arrive** until a provider URL + credentials are configured via Super Admin. No code fix needed — owner configuration required.

If the only remaining step were sending a real SMS through a configured provider, this would be `PASS WITH NON-BLOCKING ISSUES` (see residual hardening gaps). As measured, the external leg is missing.

---

## B. Executive Summary

**Can a real mobile OTP be delivered today?** **No — it will be logged, not sent.**

- **What works:** User enters `01XXXXXXXXX` → route `throttle:5,15` → `PhoneOtpService::send()` normalizes to E.164 → validates, enforces `60s` cooldown + `5/hr` via Cache, generates `random_int` 6-digit, stores `Hash::make` in `phone_verification_otps` (`expires 10m`), calls `SmsConfig::activeProvider()` which correctly reads `sms.enabled`/`sms.provider` from encrypted `settings` and falls back to `log` when disabled/unconfigured (safe allow-list `log`/`http`), then `sendSms()` resolves `LogSmsProvider` and returns `log-<uniqid>` — no crash, no token leak, tenant-safe (platform-global, not per-institute). Verification `Hash::check` + `consumed_at` + `max_attempts 5` + `RateLimiter per IP 5/hr` also correct. Phone 2FA (`phone_2fa_otps` guard-aware) and password-reset OTP share identical hardened logic. Syndrome for queue latency observed in email **does not affect OTP SMS** — phone path is **synchronous** (`Http::timeout 15` inside request, no `jobs` table, no worker).

- **What blocks real delivery:** Super Admin → Platform Settings → SMS (**`resources/views/admin/platform-settings/index.blade.php:79-106`**) can save `provider/http_method/api_url/api_key/secret/sender_id` to encrypted `settings`, but current DB has **0 rows** for those keys (`sms.enabled NOT CONFIGURED`, `sms.api_url NOT CONFIGURED`, `SMS_HTTP_URL empty`). `HttpSmsProvider` requires `filled(url)` or throws `RuntimeException: SMS HTTP gateway is not configured`. Until an HTTP gateway URL + credentials are stored, `activeProvider()` will never select `http` — it returns `log`. The pipeline then logs `notification.sms phone+message` (contains OTP in dev Log provider only) and succeeds fake — phone never rings.

- **Risk after configuring:** After a URL + key are set, the first real HTTP will use `Http::timeout 15` synchronously; gateway-specific `fields` mapping (`to`/`message`/`api_key`/`from` in `config/notifications.php:143-148`) is static and may not match every provider's required JSON format, auth header, or `success_condition` evaluation — application reports `200` as success without provider-specific response validation (acceptable for generic HTTP gateway, but provider contract unverified until docs supplied). No blocking security/tenant flaw.

**Bottom line:** Keep existing architecture. Owner must provide provider API URL + credentials via Super Admin (no code change), then `log`→`http` automatically and real SMS flows. Until then `BLOCKED` is accurate and safe (no accidental real SMS, no credit consumption).

---

## C. End-to-End Flow

```
User enters mobile  E.164 normalized  (01XXXXXXXXX / +8801XXXXXXXXX / 8801XXXXXXXXX)
        ↓  routes/web.php:302 POST phone/verify-send throttle:5,15  auth:web
        ↓  routes/auth.php:51 POST forgot-password/phone throttle:5,10  guest (enumeration-safe)
        ↓  routes/auth.php:80 POST two-factor-challenge/resend throttle:5,1  guest (2FA)
   IdentityController:31 / PhonePasswordResetController:26 / TwoFactorChallengeController:84,259
        ↓  PhoneOtpService::send()  PhoneOtpService.php:34-96  (PhonePasswordRecoveryService:21-99 for reset)
   PhoneNormalizer::toE164(raw, country)  PhoneNormalizer.php:53-133  → +880... or ValidationException
        ↓  SMS OTP length 6  random_int(100000,999999)  PhoneOtpService.php:184-189  CSPRNG
           Storage  PhoneVerificationOtp::create()  PhoneVerificationOtp.php:9  otp_hash=Hash::make(otp)  bcrypt
           DB phone_verification_otps (user_id FK, phone 20, otp_hash 255, attempts 0, expires_at +10m, consumed_at nullable)
           also phone_2fa_otps (guard, institute_id) for 2FA, phone_password_reset_otps for recovery
        ↓  Rate-limit / cooldown Cache + RateLimiter
           phone_otp_send:{userId}:{phone} 60s  Cache::has → put 60  PhoneOtpService.php:53-55,90
           phone_otp_hour:{userId}:{phone} 5/hr Cache::get → put 3600  :60-65,91
           TwoFactorChallengeController per-user 5/60s + per-IP 10/60s  TwoFactorChallengeController.php:149-153
           Route throttle 5-15 etc.
        ↓  SmsConfig  SmsConfig.php:40-66
           activeProvider()  checks sms.enabled !='1' → log ; else sms.provider → registry log/http → log fallback
           providerOptions()  api_key (decrypted), sender_id→from, url Setting sms.api_url or env SMS_HTTP_URL
        ↓  Provider selection  PhoneOtpService:195-201  config(providers) whitelist → LogSmsProvider (fallback) or HttpSmsProvider
        ↓  SmsProviderContract  SmsProviderContract.php:9
           ├─ LogSmsProvider.php:14-26  Log::info notification.sms phone+message → fake log-<uniqid>  (enabled=0 or provider=log)
           └─ HttpSmsProvider.php:20-69  Http::timeout(15)  GET/POST to url with fields map to, message, api_key, from
                                 successful() 2xx else RuntimeException; return message_id via response_message_id_path else null
        ↓  HTTP request to SMS gateway (only if provider=http + api_url filled)
        ↓  Provider response  {message_id, raw Body truncated 2000}  HttpSmsProvider.php:65
        ↓  Delivery/result  PhoneOtpService:202-205  catch never leaks OTP, report + masked Log
           Cache::put throttle/hour  PhoneOtpService:90-91  + IdentityAuditService::log phone_otp_sent
        ↓  OTP verification  PhoneOtpService:107-159 verifyForUser  (Phone2fa 274-333, Recovery 107-159)
           lookup where user_id+phone+consumed_null latest → isExpired() → max_attempts 5 → Hash::check → increment attempts → consumed_at=now → invalidate others
           enumeration-safe sendForLookup 165-182 RateLimiter 5/hr IP  → no SMS, log only
        ↓  success (phone_verified_at set) / failure (masked errors)
```

**Key: No queue/job** on the OTP SMS path — see §15. Notification SMS (`SmsChannel`) does use `SendNotificationJob` on `notifications` queue, but OTP verification path is direct.

---

## D. Gate Table

| Gate | Result | Evidence |
|------|--------|----------|
| **OTP generation** | **PASS** | `random_int(100000,999999)` cryptographically secure, length configurable `IdentityConfig::phoneOtp('length',6)` `config/identity.php:19` 6 → `PlatformSettingsController:349 4-8`, stored `Hash::make` never plaintext mask. Evidence: `PhoneOtpService.php:184-189`, `76,251`, `PhoneVerificationOtp.php:24` |
| **OTP persistence** | **PASS** | Tables `phone_verification_otps` (`user_id FK cascade, phone 20, otp_hash 255, expires_at +10m, consumed_at, index user_id+phone`), `phone_2fa_otps` (guard+institute_id), `phone_password_reset_otps` (+verified_at). Only `otp_hash` stored. Evidence: migrations `2026_08_26_000002:11-23`, `000200:12-27`, `000004:11-22`, `PhoneOtpService:79-85` |
| **OTP expiration** | **PASS** | Config `expires_minutes 10` (`IdentityConfig:expiry → Setting sms_otp.expiry → config identity.phone_otp 10` `IdentityConfig:74-77`, `PlatformSettingsController:350 1-60`), enforcement `isExpired() isPast()` `PhoneVerificationOtp:24`, rejected and `consumed_at=now()` `PhoneOtpService:125-128,299-302` |
| **Rate limiting** | **PASS** | Per-phone `60s` (`phone_otp_send:{user}:{phone}` `Cache::has` `53-55` → `put 60` `90`) + `5/hr` (`phone_otp_hour` `60-65,91`) + `phone_2fa_*` guard-aware `231-241` + `phone_otp_enum:{ip} RateLimiter 5/hr` `169-172` + per-user/IP `TwoFactorChallengeController:149-153` (5/10 per 60s) + route `throttle:5,15` `web.php:302` |
| **Verification** | **PASS** | Lookup `where consumed_null latest` `115-119` → `isExpired` → `attempts>=5 bruteforce` `130-135` → `Hash::check` `137` → `increment attempts` `138` → `consumed_at` on success `150` + invalidate others `153-156`. Wrong/expired/reused/excessive/belongs-to-other-phone all fail masked. Evidence `PhoneOtpService:107-159,274-333` |
| **SMS enabled** | **FAIL** | Current DB `sms.enabled NOT CONFIGURED` (fallback view `select 1/0` `index.blade:97`, validation `in:0,1` `275`, save `sms.enabled` `300`, runtime `activeProvider:42-43` non-`'1' → log`) → **activeProvider returns `log`** (see check `activeProvider=log`). Not enabling real path — and `SmsChannel` ignores flag (notifications could bypass). Evidence `SmsConfig:42-43`, `check_sms_settings.php` NOT CONFIGURED |
| **Provider selection** | **PASS (logic) / FAIL (config)** | Registry `log/http` in `config/notifications.php:132-135`, allow-list `activeProvider:50-53` unknown→`log`, `PhoneOtpService:197 ?? LogSmsProvider` safe, no `eval`. Logic PASS; but provider `NOT CONFIGURED` → always `log` — FAIL for real delivery. Evidence `SmsConfig:40-55`, `PlatformSettingsTest:217` |
| **HTTP provider** | **PASS (code) / BLOCKED (config)** | `HttpSmsProvider:22-27` url precedence `options.url → Setting sms.api_url → env SMS_HTTP_URL` (env empty), method `POST` default, `fields` `to/message/api_key/from` `33-46`, `Http::timeout 15` `48`, `2xx else throw 300` `53-56`, `raw 2000` `65`. HTTPS not enforced, `api_secret` stored but **never sent** (field map only `api_key`). Evidence `HttpSmsProvider:20-69` — real request blocked by missing `api_url` (`throw RuntimeException`) |
| **API credentials** | **FAIL** | `sms.api_key NOT CONFIGURED`, `sms.api_secret NOT CONFIGURED`, `sms.sender_id NOT CONFIGURED` (`check_sms_settings.php`). Secrets would be encrypted `$encrypted:24-26` `Setting.php:77 Crypt::encryptString`, masked `••••••••` `42-48`, `••••••••` guard prevents overwrite `PlatformSettingsService:78`, `testSms:285-287` → **NOT CONFIGURED**. Evidence `Setting.php:24-26,77`, `PlatformSettingsService:28` |
| **Phone normalization** | **PASS** | `PhoneNormalizer::toE164` `53-133` strips `space-( )`, validates `^\+?\d+$`, handles `+` trunk vs national `880`, BD trunk `+8800→880`, `ltrim 0`, `CountryCodes::matchPrefix`. Enforced on send+verify both. Evidence `PhoneNormalizer:66,71-88,90-133` |
| **Queue** | **PASS (design)** | OTP SMS **sync** `PhoneOtpService:201` `provider->send Http::timeout15` — not queued, no `jobs`, no worker. Affects latency: request blocks `≤15s` worst (vs email queue `database` + `notifications:retry everyFiveMinutes` that caused 9h email backlog). Verification: `ShouldQueue` absent in `PhoneOtpService`, present only in `EmailOtpMail`. Evidence `PhoneOtpService:191-201` vs `EmailOtpService:193-195 queue` |
| **Delivery latency** | **PASS** | Sync → `<15s` (HTTP timeout) + `1-3s` application; no queue wait (0 jobs). No `schedule:run` dependency. Evidence `HttpSmsProvider:48 15s`, `check_sms_settings jobs 0` |
| **Error handling** | **PASS** | `HttpSmsProvider:53-56` 4xx/5xx throws `RuntimeException` `status+body 300` → `PhoneOtpService:202-204` `catch Throwable report + Log masked` → safe generic. Timeouts via `15s` → same catch. No secret leak. Evidence `HttpSmsProvider:48-56`, `PhoneOtpService:202` |
| **Secret encryption** | **PASS** | `$encrypted sms.api_key/secret/password` `Setting.php:24-26`, `Crypt::encryptString:77`/`decrypt:65`, masked `Configured ••••••••` `42`, `••••••••` guard `PlatformSettingsService:78`+`Controller:281` prevents overwrite, audit `credential_changed` not plaintext `283,287` | 
| **Tenant isolation** | **PASS** | Platform `Setting` (global, no `TenantScoped`) used for OTP SMS (`SmsConfig` + `PhoneOtpService`) — correct for `User` (owner) identity (platform-global, `SUPER_ADMIN_SETTINGS_FINAL_WIRING_REPORT:122`). `SmsChannel` for notifications **is** tenant-aware (`InstituteSetting sms_api_key_enc` `SmsChannel:93-99`) but OTP path deliberately not — `Institute A cannot read B` enforced by no tenant DB column for verification. Evidence `Setting.php:11` vs `InstituteSetting:10 TenantScoped`, `SmsConfig:40-55` |
| **Audit logging** | **PASS** | `PlatformSettingsController:283,287,292,301` `PlatformAuditLog::record('sms','sms.api_key','credential_changed')` on change; `PhoneOtpService:93,133,145,269` `IdentityAuditService::log phone_otp_sent/failed/bruteforce` masked phone + IP in `identity_audit_logs`. Evidence `PlatformAuditLog:26-38` stores `section/key/action/ip/ua` never secrets |
| **Duplicate protection** | **PASS** | Cache throttle `60s` + `5/hr` + invalidation `whereNull consumed_at → update consumed_at` `68-71,243-247` ensures one valid OTP; notification retry `NotificationLog max_retries 2` + `withoutGlobalScope` idempotency `SendNotificationJob:49-52` handles duplicate; no idempotency UUID but last-write-wins plus throttle covers. Evidence `PhoneOtpService:53-91,118,150` |
| **Test safety** | **PASS** | `PlatformSettingsController:testSms:305-333` requires `auth:platform_admin` `web.php:245`, `validate test_phone+test_message`, disabled `log` path does **NOT** hit HTTP (`311-319` log only), HTTP path requires `filled api_url` `321`, secrets scrubbed `331-333 str_replace(...,'***')`, no `••••••••` overwrite. Safe — but does send **real SMS if api_url + provider=http** → **OWNER-ACTION REQUIRED** if executed. Not executed during audit. Evidence `PlatformSettingsController:305-336` |

---

## E. Exact File/Line Evidence

**Primary pipeline:** `routes/web.php:302` `phone/verify-send`, `routes/auth.php:51,57,80` forgot/2FA, `IdentityController.php:31` `phoneOtp->send`, `PhonePasswordResetController.php:26` `request`, `TwoFactorChallengeController.php:84,259` `sendFor2FA`, `OwnerRegisterController.php:135` `send`, `PhoneOtpService.php:34-96` send/ `107-159` verify/ `191-209` sendSms/ `216-272` 2FA/ `184-189` random_int/ `184-189` hash/ `53-91` throttle, `PhoneVerificationOtp.php:24` isExpired, `Phone2faOtp.php:18`, migrations `2026_08_26_000002:11-23` + `000200:12-27`, `SmsConfig.php:40-66` activeProvider/providerOptions, `SmsProviderContract.php:9`, `LogSmsProvider.php:14-26`, `HttpSmsProvider.php:20-69` (url precedence 24-25, fields 33-46, timeout 48, response 59-65), `PhoneNormalizer.php:53-133` toE164, `IdentityConfig.php:14-28` phoneOtp map, `config/identity.php:18-24` defaults, `config/notifications.php:131-151` providers/http, `Setting.php:21-39` encrypted, `PlatformSettingsController.php:258-336` sms save/test, `PlatformSettingsService:74-89` audit, `PlatformAuditLog.php:26-38`, `SmsChannel.php:48-110` notification tenant path (not OTP), `SendNotificationJob.php:27-29` tries1 timeout60.

Full set verified read-only; line numbers exact as inspected 2026-08-26.

---

## F. Provider Readiness — Application vs Provider Contract

| Layer | Status | Evidence |
|-------|--------|----------|
| **Application integration ready** | **YES** | All code paths wired: Super Admin UI saves encrypted → `Setting` → `SmsConfig` → `PhoneOtpService` → `HttpSmsProvider` with allow-list `log/http`, 15s timeout, field map, safe fallback. `LogSmsProvider` operational today. Tests `PlatformSettingsTest:217-218` activeProvider fallback, `214-230` masked secrets, `236-237` disabled→log all PASS (13/13). |
| **Actual provider credentials / API contract verified** | **NO — BLOCKS REAL DELIVERY** | DB `sms.api_url NOT CONFIGURED`, `SMS_HTTP_URL env empty`, `sms.api_key NOT CONFIGURED`, `SMS_DEFAULT_PROVIDER log`. `HttpSmsProvider:29` would `throw RuntimeException: SMS HTTP gateway is not configured` immediately if called via `http`. No provider docs supplied, so payload format (JSON vs form, Bearer vs query `api_key`) is generic `to/message/api_key/from` form-encoded (`HttpSmsProvider:49 post`, `fields` `config:143-148`) — unmatched outer fields (`sms.auth_type`, `sms.success_condition`, `sms.headers/params`, `sms.api_secret`, `sms.phone_param` `sms.message_param`) are **saved but unused** (shadow). HTTPS not strictly enforced (http URL would succeed if attacker supplies). |

**Distinction is critical:** Code can call any HTTP gateway correctly (method, timeout, masking), but **provider-specific contract** (e.g., Twilio `Account SID` vs custom Bangladeshi `BulkSMS BD` `apikey/secret/senderId/url`) cannot be production-verified until provider name/docs supplied. **Do not claim delivery until a real provider's `success_condition` is wired to `response_message_id_path`.**

---

## G. Security Findings

| Severity | Finding | Evidence |
|----------|---------|----------|
| **BLOCKING** | No real SMS provider configured — all OTPs fall back to `LogSmsProvider` (logged only) | `Setting sms.provider NOT CONFIGURED`, `SmsConfig::activeProvider() log`, `check_sms_settings.php` |
| **HIGH** | `HttpSmsProvider` ignores `sms.api_secret` / `sms.auth_type` / `sms.headers` / `sms.headers` saved in UI — a Bearer/Basic provider with secret-only auth will silently send without secret → will fail with 401 and OTP never arrives, but system reports generic error only | `HttpSmsProvider:39` only `api_key` in fields, `PlatformSettingsController:266,272,293` saves secret/auth_type but `HttpSmsProvider:48-65` never reads `api_secret`/`auth_type`/`headers` |
| **MEDIUM** | `LogSmsProvider` logs full `phone+message` (contains plaintext OTP) to `laravel.log` in `log` mode — convenient for dev but OTP visible to log readers | `LogSmsProvider:16` `Log::info('notification.sms',['phone'=>$phone,'message'=>$message])` — phone OTP path via `LogSmsProvider` when disabled (production fallback if enabled=0) |
| **MEDIUM** | `HttpSmsProvider` does not enforce `https://` — `Setting::get('sms.api_url')` `type:url` validation allows `http://` → secrets sent plaintext | `PlatformSettingsController:263 url`, `HttpSmsProvider:25` no scheme check |
| **MEDIUM** | `success_condition` stored `sms.success_condition` (UI) but never evaluated — gateway returning `HTTP 200 {"status":"failed"}` would be counted as `sent` because only `successful()` (2xx) checked | `PlatformSettingsController:274` saves, `HttpSmsProvider:53` only checks `successful()`, `response_message_id_path` empty default `config:150` |
| **MEDIUM** | `SmsChannel` ignores `sms.enabled` — notifications could send real SMS even when admin disabled | `SmsConfig:42-43` blocks OTP, but `SmsChannel:48-66` only checks `sms.provider` via `InstituteSetting` → `Setting` → `config default`, not `enabled` |
| **MEDIUM** | `sms.timeout` not wired — UI `15s` hardcoded in `HttpSmsProvider:48` cannot be tuned by Super Admin, UI has no field | `SmsConfig:all()` no `timeout`, `check_sms_settings.php` shows missing, UI `index.blade:79-97` no timeout input |
| **LOW** | Shadow settings `sms.type`, `sms.sender_name`, `sms.username/password`, `sms.phone_param/message_param` saved but never read — confusing for admin | `PlatformSettingsController:261,268-270,294-298`, `SmsConfig:all()` omits them |
| **LOW** | `SendNotificationJob tries 1 timeout 60` with `retry_after 90` + `NotificationsRetry everyFiveMinutes` — duplicate risk if OTP ever queued via notifications (not current) | `SendNotificationJob:27-29`, `config/notifications:159-161`, `config/queue:43` |
| **INFORMATIONAL** | No queue latency for OTP SMS (sync) — unlike email queue that caused 12h verifier backlog (`jobs 5 → 0` after worker run 17s) | `PhoneOtpService:88 sync` vs `EmailOtpService:193 queue` — SMS not affected |

---

## H. Queue / Latency Findings

- **OTP SMS:** **Synchronous** `PhoneOtpService::sendSms → Http::timeout(15)` or `Log::info` — **0 queue**, `jobs 0`, `failed_jobs 0`, no worker needed. Latency `OTP generation (<5ms) + bcrypt Hash::make (~100ms) + HTTP 15s worst` — not the email `database` queue that required `queue:work --stop-when-empty every minute` to drain 5× 12h verification emails (17s first TLS cold). Email fix (`after_commit true` already applied `config/queue.php:44`) does not affect phone OTP.
- **Notification SMS:** Uses `SendNotificationJob` on `notifications` queue (`NotificationService:140`, `SendNotificationJob:27 tries1 timeout60`, `retry_after 90`, `notifications:retry everyFiveMinutes` present) — would be delayed if `queue:work` every-minute cron missing, but OTP path unaffected.
- **No duplicate SMS engine** — reuses `SmsProviderContract` + `Log/Http` + `SmsConfig`.

---

## I. Production Data Safety

```
.env untouched:           YES (still QUEUE_CONNECTION=database, MAIL_*, SMS_HTTP_URL empty)
production DB untouched:  YES (only SELECT count / get on settings/jobs, no migrate/seed/truncate/delete/update; phone_*otps counts 2/0/0 untouched, settings 11 rows untouched)
no destructive commands:  YES (no migrate:fresh, db:wipe, truncate)
no real SMS:              YES (provider log fallback, no external HTTP, testSms not executed)
no external provider calls: YES (HttpSmsProvider not invoked — api_url empty, check only)
no worker started:        YES (queue:work not executed this audit; prior email worker run was OWNER APPROVED and drained 5 verify emails, now jobs 0 — phone path never queued)
no secrets exposed:       YES (all api_key/secret masked CONFIGURED / NOT CONFIGURED, Crypt handling verified)
no tests weakened:        YES (only read tests/PlatformSettingsTest, no change)
```

---

## J. Owner Action Required

**To achieve `PASS — REAL SMS OTP READY`, owner must provide (do not paste credentials in chat/report):**

1. **Choose SMS gateway** and obtain docs for: API URL (HTTPS), HTTP method (GET/POST), authentication (api_key alone vs key+secret vs Bearer), required fields (`to`, `message`, `from`/`senderId`), API key placement (query vs form vs header), and success response field (`response_message_id_path`).

2. **Configure via Super Admin → Platform Settings → SMS** (no code change, no .env):
   - `SMS provider: http` (`sms.provider`)
   - `API URL: https://provider.example.com/send` (`sms.api_url`) — **must be HTTPS**
   - `HTTP method: POST` (or GET per docs) (`sms.http_method`)
   - `API key` (`sms.api_key`) — encrypted at rest `$encrypted:24` (masked `••••••••` after save)
   - `API secret` if provider requires (`sms.api_secret`) — encrypted `$encrypted:25` (currently shadow — note for provider: if secret must be sent, map `fields` in `config/notifications.php:143` or expose `api_secret` handling in `HttpSmsProvider:39` — owner should provide docs)
   - `Sender ID` (`sms.sender_id`) — pre-approved sender
   - `Enabled: Yes` (`sms.enabled = 1`) — flips `activeProvider` from `log` to `http`
   - Save → audit `credential_changed` generated `PlatformAuditLog`.

3. **Provider-specific adaptation (if docs differ from generic):** If gateway expects JSON `{"phone":"+880...","text":"...","sender":"...","token":"..."}` or `Authorization: Bearer`, current generic form field map `to/message/api_key/from` (`config/notifications.php:143`) may need a **small allow-listed mapping** change — report docs and maintain allow-list (`notifications.sms.providers`).

4. **Do NOT send real OTP until:** After step 2, run **explicit owner-approved test** (`POST admin/platform-settings/sms/test` with `test_phone` `+8801XXXXXXXXX` + `test_message` "Test SMS") — this will now hit real HTTP (`HttpSmsProvider:48` 15s) and incur a credit — owner must confirm destination before clicking. Until then `BLOCKED` stands.

5. **Database queue note:** No action for phone OTP latency (sync). For email notifications, ensure cPanel crons remain: `schedule:run every minute` + `queue:work --stop-when-empty every minute` (already documented) — phone unaffected.

**Do not request that secrets be pasted here.** Configure in Super Admin UI where they are encrypted (`Crypt::encryptString`).

---

## K. Final Sign-Off

### `REAL SMS OTP END-TO-END AUDIT: BLOCKED`

**Application-level verification PASS. Real provider delivery test requires explicit owner approval and a configured provider. No external SMS was sent during this audit.**

Code correctly generates, secures (`Hash::make`, `random_int`), stores, throttles (`60s`+`5/hr`+`10/15` route), normalizes (`PhoneNormalizer::toE164`), encrypts secrets, isolates tenancy, and would deliver via `http` if an API URL + credentials existed. Currently `sms.enabled NOT CONFIGURED` → `activeProvider log` → `LogSmsProvider` only — mobile phone will **not** receive OTP. This is intentional safe blocking (no accidental credit spend). Once owner configures a real HTTPS gateway via Platform Settings, the existing `PhoneOtpService:191-201` → `HttpSmsProvider:20-69` path will send within `15s` synchronously and verify via `Hash::check` with `10m` expiry and `5` max attempts.

---

*Read-only evidence: `PhoneOtpService.php:34-333`, `SmsConfig.php:40-66`, `HttpSmsProvider.php:20-69`, `LogSmsProvider.php:14-26`, `SmsProviderContract.php:9`, `PhoneNormalizer.php:53-133`, `PhoneVerificationOtp.php:24`, `config/identity.php:18-24`, `config/notifications.php:131-151`, `Setting.php:21-39,41-48,63-78`, `PlatformSettingsController.php:258-336` + `PlatformSettingsService`, `PlatformAuditLog.php:26-38`, `SmsChannel.php:48-110`, `routes/web.php:302`, `routes/auth.php:51,80`, `storage/logs/laravel.log` (no sms_otp_sent truncated), `check_sms_settings.php` shows all sms.* NOT CONFIGURED → activeProvider log, jobs 0.*

