# PHASE_E11_PRODUCTION_CONFIG_CHECKLIST

## SMTP
- **MAIL_MAILER:** `env(MAIL_MAILER, log)` → `.env` currently `log` (dev). Production must set `smtp`. `.env.example` documents `MAIL_MAILER=smtp` as commented template. No hard-coded value.
- **MAIL_HOST:** `env(MAIL_HOST, 127.0.0.1)` → `.env` `127.0.0.1`. Production `smtp.gmail.com` via env. `.env.example` placeholder `# MAIL_HOST=smtp.gmail.com`. Not committed.
- **MAIL_PORT:** `env(MAIL_PORT, 2525)` → `.env` `2525`. Production `587`. Env-only.
- **MAIL_ENCRYPTION:** `env(MAIL_ENCRYPTION, tls)` → `.env` not set (config defaults `tls`). Production `tls`. Env-only.
- **MAIL_USERNAME:** `env(MAIL_USERNAME)` → `.env` `null` (empty). Production Gmail address via env. Not logged.
- **MAIL_PASSWORD:** `env(MAIL_PASSWORD)` → `.env` `null`. Production Gmail App Password via env. Never printed, never committed, `ResolveMailer` decrypts `smtp_password_enc` via `Crypt`.
- **MAIL_FROM_ADDRESS:** `env(MAIL_FROM_ADDRESS, hello@example.com)` → `.env` `hello@example.com`. Production must be verified Gmail address.
- **MAIL_FROM_NAME:** `env(MAIL_FROM_NAME, APP_NAME)` → `.env` `${APP_NAME}`.
- **Queue:** `QUEUE_CONNECTION=sync` (`.env`), `config/queue.php` `default env(QUEUE_CONNECTION,database)` with `retry_after 90`. `config/notifications.php` `retry max_attempts 3 delay 60`. Notifications via `SendNotificationJob` queue `notifications` – sync means immediate, database would be async with worker. No secret leakage.

## SMS
- **Provider:** `config/notifications.php sms.default env(SMS_DEFAULT_PROVIDER, log)` → `.env` not set → `log`. Production should set `http` + `SMS_HTTP_URL`.
- **Endpoint presence:** `config/notifications.php sms.http.url env(SMS_HTTP_URL,'')` → `.env` empty. Production must set via env only.
- **Credential presence:** `sms.http.fields` maps `api_key`/`from` from `options` (institute/platform settings decrypted). No hard-coded key. `.env` no `SMS_API_KEY` committed.
- **Sender configuration:** `from` field via `SMS_FROM` or per-institute setting, env-only.
- **Timeout/Retry:** `HttpSmsProvider` timeout 15s, `config/notifications.retry max_attempts 3 delay 60`, `LogSmsProvider` fallback. No hard-coded.
- **Verification:** `PhoneOtpService`/`PhonePasswordRecoveryService` masked phone in logs, hashed OTP (`$2y$`), `expires 10m`, `attempts 5`, `resend 60s`.

## Identity
- **Allowed email domains:** `config/identity.php allowed_email_domains env(IDENTITY_ALLOWED_EMAIL_DOMAINS)` → empty (all allowed). `.env` not set. Env-driven, case-insensitive via `EmailDomainPolicy::isAllowed` (lowercase). Ownership still requires verification.
- **Phone OTP settings:** `phone_otp length 6 expires 10m max_attempts 5 resend 60s max_per_hour 5` – env-free but not secret, tunable via config.
- **Email verification:** `config/auth.php passwords expire 60 throttle 60`, `MustVerifyEmail` with `signed` + `throttle:6,1`, `email_verified_at` nullable, pending fields `pending_email/token/expires`.
- **Password recovery:** `phone_password_reset length 6 expires 10m verified_ttl 10m`, hashed tokens/OTPs, single-use, generic responses.

## 2FA
- **Enabled guards:** `web` (`User`), `institute_user`, `platform_admin`, `guardian` (now `TwoFactorAuthenticatable` via `2026_08_26_000005_add_two_factor_to_guardians_table`). Fortify `twoFactorAuthentication confirm true confirmPassword true`.
- **TOTP throttling:** Per-user `totp:user:{guard}:{id} 5/60s` + IP `totp:ip 10/60s` via `RateLimiter`, `clear` on success, `hit` on failure, no secret logging.
- **Recovery code behavior:** `two_factor_recovery_codes` encrypted via `Fortify::currentEncrypter()`, `replaceRecoveryCode` removes used code (single-use), `regenerateRecoveryCodes` rotates, shown only via `qrCode`/`recoveryCodes` JSON (audited `2fa_qr_viewed`), never logged.
