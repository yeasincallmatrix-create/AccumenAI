# PHASE_E11_PRECHECK_REPORT — Forensic Pre-Check (No Code Changes)

> Date: 2026-08-25 | Env: local | DB: monetix (MySQL) | Branch: E11-A

## 1. Current `.env` mail configuration
```
MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
MAIL_ENCRYPTION=null (not set, config defaults tls)
QUEUE_CONNECTION=sync
CACHE_STORE=file
SESSION_DRIVER=database
```
**Finding:** Production Gmail SMTP not configured. `log` driver writes to log only, never reaches `smtp.gmail.com:587`. `MAIL_FROM_ADDRESS` is placeholder `hello@example.com` not verified Gmail. **BLOCKS production** — real verification/reset emails never delivered.

## 2. `config/mail.php`
`default env(MAIL_MAILER, log)`; `mailers.smtp` reads `MAIL_HOST/PORT/USERNAME/PASSWORD/ENCRYPTION` via env, timeout null, `local_domain` from `APP_URL`. `from` from `MAIL_FROM_ADDRESS/NAME`. Correctly env-only, supports `smtp`/`log`/`array`/`ses` etc. No hard-coded secret. Requires `MAIL_MAILER=smtp` + Gmail app password to verify.

## 3. `config/queue.php`
`default env(QUEUE_CONNECTION, database)` → current `sync` (synchronous). `connections.database` uses `jobs` table, `retry_after 90`. For notifications queued (`notifications` queue, `retry 3/delay 60` in `config/notifications.php`) sync means immediate execution, not async. Production should use `database` with worker. Not blocking but queue retry verified via `sync` immediate.

## 4. `config/notifications.php`
`sms.default env(SMS_DEFAULT_PROVIDER, log)` → currently `log` (fallback). `sms.http.url env(SMS_HTTP_URL,'')` empty, `method post`, `fields` mapping. `providers log/http` via `SmsProviderContract`. `retry max_attempts 3 delay 60`. Correctly env-driven, no secret committed. **BLOCKS** real SMS — provider not configured.

## 5. `config/identity.php`
`allowed_email_domains env(IDENTITY_ALLOWED_EMAIL_DOMAINS)` array filtered, case-insensitive via `EmailDomainPolicy`. `phone_otp` (6/10m/5 attempts/60s throttle), `email_change` (60m), `phone_password_reset` (6/10m/5 attempts/60s/10m verified TTL). No hard-coded domains, verification still required. PASS.

## 6. Existing SMTP resolution path
`App\Services\Notification\ResolveMailer::resolve(?instituteId)` → `institute_settings.smtp_*` (encrypted `smtp_password_enc` via `Crypt::decryptString`) → `settings.smtp.*` → `null` (env default). `MailChannel` uses `ResolveMailer` to build transient mailer, `NotificationService` → `MailChannel` → `SendNotificationJob` (queue `notifications`). No hard-coded Gmail; runtime uses env/institute settings. Path exists and is correct, but env `log` means log channel only.

## 7. Existing SMS provider resolution path
`config/notifications.php:sms.default` → `SmsProviderContract` → `LogSmsProvider` (logs) or `HttpSmsProvider` (generic HTTP gateway `SMS_HTTP_URL`). `SmsChannel` resolves via `provider(string $name)`. `PhoneOtpService`/`PhonePasswordRecoveryService` call `app($providerClass)->send(phone,message)`. No duplicate abstraction. **BLOCKS** real delivery — `SMS_HTTP_URL` empty, default `log`.

## 8. Email verification routes/controllers
`routes/auth.php` Fortify group: `GET email/verify`, `GET email/verify/{id}/{hash} signed throttle:6,1`, `POST email/verification-notification throttle:6,1` via `VerificationPromptController`/`VerifyEmailController`/`EmailVerificationNotificationController` (Laravel `MustVerifyEmail`). `users`/`institute_users`/`platform_admins` use `MustVerifyEmail` + `email_verified_at`. Workspace `'/'` middleware `verified` blocks unverified. Throttle + signed URL + expiration via `config/auth.php passwords.expire 60`. PASS (needs real SMTP).

## 9. Phone verification routes/controllers
`routes/web.php: account/phone/verify-send|verify` + `account/phone/change-request|verify-change` via `IdentityController` (`auth:web` `throttle:5,15`/`10,15`), `PhoneOtpService` hashed 6-digit `expires 10m` `max_attempts 5` `resend 60s` `hour 5`, masked logs, tenant isolation (user_id). `users` `phone_verified_at`. PASS (needs real SMS for delivery, LogSmsProvider only logs).

## 10. Email password recovery
`routes/auth.php POST forgot-password / reset-password` via `ForgotPasswordController`/`ResetPasswordController` reusing `Password` brokers `users/institute_users/platform_admins/guardians` sharing `password_reset_tokens` (email PK, token hashed via `Hash::make` / `DatabaseTokenRepository`, `expire 60` `throttle 60`). Generic `auth.reset_link_sent` response (no enumeration), token single-use (`PasswordService::setForUser` deletes `WHERE email`), no token logging (only `status`). **Potential cross-broker email collision** if same email exists in 2 tables (now mitigated at registration via cross-table uniqueness check added E9). PASS subject to SMTP.

## 11. Phone password recovery
`PhonePasswordRecoveryService` + `PhonePasswordResetController` 6 routes `forgot-password/phone` / `reset-password/phone` (guest `throttle`). Canonical `PhoneNormalizer::toE164`, generic response, hashed OTP (`phone_password_reset_otps.otp_hash`), `expires 10m`, `attempts 5`, `resend 60s`, `verified 10m`, masked logs, no plaintext, tenant-isolated (phone UNIQUE global). 3-step flow `request → verify → reset` with `PasswordPolicy` + `PasswordService::setForUser` session revoke. PASS (needs real SMS).

## 12. Guardian 2FA
Before E11: `guardians` **lacked** `two_factor_*` columns (`DESCRIBE guardians` had no secret) and trait `TwoFactorAuthenticatable`. **BLOCKS** guardian parity. After E8 migration `2026_08_26_000005_add_two_factor_to_guardians_table` added columns and trait, `GuardianLoginController` now defers to `two-factor.login` when enabled, `TwoFactorChallengeController` handles `guardian`, `SecurityController` supports `guardian`. Now PASS but needs verification via real TOTP device.

## 13. Institute-user 2FA
`institute_users` has `two_factor_secret/recovery_codes/confirmed_at` + `TwoFactorAuthenticatable`; `InstituteUserLoginController` already defers to challenge, `TwoFactorChallengeController` handles `institute_user`, `SecurityController` via `fortifyguard` `auth:institute_user,web`. Throttling now per-user+IP (E8.3). PASS.

## 14. Platform-admin 2FA
`platform_admins` has `two_factor_*` + trait; `PlatformAdminLoginController` (not inspected but analogous) + challenge support for `platform_admin`. PASS.

## 15. TOTP throttling
Previously IP-only (`fortify.two-factor`). Now `TwoFactorChallengeController::store` adds per-user `totp:user:{guard}:{id} 5/60s` + `totp:ip 10/60s` via `RateLimiter`, `clear` on success, audit `totp_failed/throttled/success` without code. PASS.

## 16. Password policy
`App\Support\PasswordPolicy` single source: `min 8` + `mixedCase` + `numbers` + `symbols` via `config/security.php` (`require_uppercase/lowercase/number/symbol true`). `PasswordService::hash` calls `validatePlain` via `PasswordPolicy::check`, no plaintext logging, `PasswordHash::looksValid` prevents double-hash. Reused in registration, reset, change. **BLOCKS** 15 tests with weak fixtures `brandnewsecret1` etc.

## 17. Session revocation
`PasswordService::revokeSessionsAfterPasswordChange`: deletes `sessions WHERE user_id && id!=currentId` (keeps current if web request) else all (guest reset), regenerates `remember_token`, clears Sanctum tokens, called from `setForUser` (email/phone reset) and `changePassword`. `LogoutController` `invalidate`+`regenerateToken` clears `login.id`. Challenge `regenerate()` after success. PASS.

## 18. Identity audit logging
`IdentityAuditService::log(user_id,event,identifier_type,meta)` writes `identity_audit_logs` (masked phone/email, no passwords/tokens/OTPs/secrets). `PasswordService::recordSecurityEvent` logs `password security event` without secrets. 2FA events `2fa_enabled/confirmed/disabled/qr_viewed/totp_*`. PASS – no secret exposure.

## 19. Existing `phone:normalize` command
`php artisan phone:normalize --dry-run` exists (`Description: Normalize phone numbers across all phone columns with collision detection`). Supports `--dry-run` report without modifying. Uses `PhoneNormalizer::toE164`. **BLOCKS** production pending dry-run + backfill (scanned/normalized/duplicate/invalid stats not yet reported).

## 20. Existing weak-password test fixtures
Grep `brandnewsecret1`/`newpassword123`/`secret123` in `tests/Feature` → 15 failures in full suite (e.g., `GuardianPortalTest::profile password change`, `InstituteSettingsTest::update password`, `RecycleBinTest` etc) vs `PasswordPolicy` strict. Production policy unchanged is correct; fixtures must be hardened to `Str0ng!Pass123` style. **BLOCKS** `php artisan test` green.

## Blocking Summary (Production Gate)
- **YELLOW – NOT READY** due to:
  1. SMTP `MAIL_MAILER=log` (needs `smtp` + Gmail App Password)
  2. SMS `SMS_DEFAULT_PROVIDER=log` + `SMS_HTTP_URL` empty (needs real gateway)
  3. Phone normalization backfill not executed (dry-run report missing)
  4. 15 weak-password fixture failures (need `PasswordPolicy` compliant passwords)
- Non-blocking but verify: guardian 2FA parity now added, TOTP per-user throttle added, cross-table uniqueness added.

> No code changes made in this pre-check.
