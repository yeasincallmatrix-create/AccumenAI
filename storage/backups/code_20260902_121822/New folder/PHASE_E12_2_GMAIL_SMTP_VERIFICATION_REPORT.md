# PHASE_E12_2_GMAIL_SMTP_VERIFICATION_REPORT

## 1. Environment
- `APP_NAME MONETIX Academy`, `APP_ENV local`, `APP_DEBUG true`, `APP_URL http://localhost/monetix/public`
- `DB_CONNECTION mysql 127.0.0.1:3306 monetix/monetix_test`, `BCRYPT_ROUNDS 12/4`, `SESSION database`, `CACHE file`, `LOG stack`
- `QUEUE_CONNECTION database` (was `sync`, now `database` – verified `jobs`/`failed_jobs` exist, 0 rows)
- `MAIL_MAILER smtp` (was `log`), `MAIL_HOST smtp.gmail.com`, `MAIL_PORT 587`, `MAIL_ENCRYPTION tls`, `MAIL_USERNAME yeasin.callmatrix@gmail.com` **PRESENT**, `MAIL_PASSWORD MISSING`, `MAIL_FROM_ADDRESS yeasin.callmatrix@gmail.com`
- PHP 8.5.0 NTS (Herd Lite `C:\Users\Fast\.config\herd-lite\bin\php.exe` + XAMPP Apache `C:\xampp\php\php.ini`), `php --ini` / `php -i` verified, `openssl` extension loaded, CA bundle now `C:\Users\Fast\.config\herd-lite\bin\cacert.pem` and `C:\xampp\php\extras\ssl\cacert.pem` (223k, valid), `verify_peer true`.

## 2. Active PHP Executable
- `where php` → `C:\Users\Fast\.config\herd-lite\bin\php.exe` (CLI, Herd Lite, NTS Visual C++ 2022 x64, Zend OPcache 8.5.0)
- `php -v` → `PHP 8.5.0 (cli) (built: Nov 21 2025 13:38:22)`
- Apache PHP: `C:\xampp\php\php.exe` / `C:\xampp\apache\bin\php.ini` (XAMPP) – separate INI, also verified.

## 3. Active php.ini
- **CLI:** `Loaded Configuration File: C:\Users\Fast\.config\herd-lite\bin\php.ini` (Herd Lite, previously truncated 64 bytes, now restored minimal with `[openssl] openssl.cafile="C:\Users\Fast\.config\herd-lite\bin\cacert.pem"`).
- **Apache:** `C:\xampp\php\php.ini` (XAMPP) with `openssl.cafile="C:\xampp\php\extras\ssl\cacert.pem"` (verified `Select-String`).
- Scan: `php --ini` shows only Herd ini, no additional; `php -i | findstr openssl.cafile` now shows correct path (previously empty).

## 4. OpenSSL Configuration
- `php -i | findstr /I "openssl.cafile openssl.capath"` → CLI now `openssl.cafile => C:\Users\Fast\.config\herd-lite\bin\cacert.pem => C:\Users\Fast\.config\herd-lite\bin\cacert.pem` (was empty), `openssl.capath => no value` (default, not needed).
- `openssl_get_cert_locations()['default_cert_file'] = C:\Program Files\Common Files\SSL\cert.pem` (Windows default, missing) – now overridden by `openssl.cafile`.
- `verify_peer = true`, `verify_peer_name = true` (kept, never set `false` as per rule).
- `HttpSmsProvider` timeout 15s, `MAIL_ENCRYPTION tls` → STARTTLS.

## 5. CA Bundle Path
- **Source:** `C:\xampp\perl\vendor\lib\Mozilla\CA\cacert.pem` (223,687 bytes, Mozilla CA, 2020) – legitimate.
- **Destination CLI:** `C:\Users\Fast\.config\herd-lite\bin\cacert.pem` **EXISTS** (copied, verified `Test-Path True`).
- **Destination Apache:** `C:\xampp\php\extras\ssl\cacert.pem` **EXISTS** (copied, 223k, verified).
- **Validation:** `file_exists` true, `php -i` shows correct `openssl.cafile`, no blind path creation.

## 6. TLS Verification
- **Before fix:** `MAIL_FAIL: Unable to connect with STARTTLS: stream_socket_enable_crypto(): SSL operation failed with code 1. error:0A000086:SSL routines::certificate verify failed` (CA missing).
- **After fix:** `php artisan optimize:clear` + `test_smtp_simple.php` `Mail::raw` now passes STARTTLS: `TLS certificate verification = PASS`, `STARTTLS = PASS` (no verify warnings). Next failure is SMTP auth (`535 BadCredentials` due to missing password), not TLS.
- **Result:** `TLS certificate verification = PASS` with `verify_peer true`.

## 7. STARTTLS
- **Test:** `fsockopen smtp.gmail.com 587` + `stream_socket_enable_crypto(..., STREAM_CRYPTO_METHOD_TLS_CLIENT)` via Symfony Mailer now succeeds (no cert error). **PASS**.

## 8. SMTP Authentication
- **Username:** `yeasin.callmatrix@gmail.com` **PRESENT** (masked check).
- **Password:** `env(MAIL_PASSWORD)` → `null` – **MISSING** (Gmail 16-digit App Password not in local `.env`; user has it but not pasted, per rule never ask user to paste in chat, never hard-code, not in `.env.example` placeholder `YOUR_GMAIL_APP_PASSWORD`).
- **Test:** `Mail::raw` with `smtp` now returns `Failed to authenticate on SMTP server with username "yeasin.callmatrix@gmail.com" using LOGIN/PLAIN/XOAUTH2. Authenticator "LOGIN" returned "535-5.7.8 Username and Password not accepted."` – expected due to **MISSING** password, not invalid App Password (would be same 535 but with correct password would be 235). No password printed (only `PRESENT/MISSING`).
- **Result:** `SMTP authentication = FAIL — BLOCKED` (missing App Password), not Gmail restriction/TLS/port/DNS. Configuration correct (`smtp.gmail.com:587 tls`), password source correct (`env` only), no OAuth needed.

## 9. Direct Laravel Mail Test
- **Command:** `Mail::raw('E12 Test A', fn($m)=>$m->to('yeasin.callmatrix@gmail.com')->subject('E12 Test A'))` via configured `smtp` mailer (not second engine).
- **Result:** `MAIL_FAIL` 535 as above, not `MAIL_SENT`. No `MAIL_PASSWORD` in exception (masked). **Not** real inbox delivery.

## 10. Real Gmail Delivery
**Overall: BLOCKED** – No email delivered to `yeasin.callmatrix@gmail.com` inbox via `MAWA Academy → ResolveMailer → MailChannel → SendNotificationJob → smtp.gmail.com:587` because `MAIL_PASSWORD MISSING`. Previous `log` channel would write to `storage/logs/laravel.log`, now `smtp` attempts real network and fails at AUTH (after TLS PASS). **Not claimed as GREEN.** Final proof `Mail::raw succeeded` **NOT** met.

## 11. Email Verification
**Logic:** `OwnerRegisterController` → `User::sendEmailVerificationNotification` → `MustVerifyEmail` signed `email/verify/{id}/{hash}` `throttle:6,1` → `email_verified_at` + `verified` middleware blocks workspace. **Tests:** `OwnerRegistrationTest` 14 passed (with `Secret123!`), `EmailPhoneIdentityTest` verification matrices passed. **Real Gmail:** **BLOCKED** (needs SMTP auth).

## 12. Password Recovery
**Flow:** `POST /forgot-password` (normalized, generic `auth.reset_link_sent`, `throttle:5,10`) → `Password::broker` `Hash::make` token `expire 60` → `smtp` **BLOCKED** → `POST /reset-password` `PasswordPolicy` + single-use delete + session revoke + `remember_token` rotation, no token logging. `PasswordRecoveryTest` 10 email cases **PASS** via `log` in testing env. **Real Gmail BLOCKED**.

## 13. Email Change
**Flow:** `POST account/email/change-request` stores `pending_email` + `Hash::make` 64-char `expires 60m`, old active, `Mail::raw` verification via `smtp` **BLOCKED** → `GET account/email/verify?token&email` `Hash::check` promotes. **Logic PASS** (`EmailPhoneIdentityTest`), **real delivery BLOCKED**.

## 14. Notification Queue
- **Config:** `QUEUE_CONNECTION database` **CONFIGURED** (verified `config('queue.default')=database`), `jobs` 0, `failed_jobs` 0, `job_batches` exist, `retry 3/60` `retry_after 90`.
- **Worker:** `php artisan queue:work --tries=3` available in separate terminal (not left running permanently). With `database`, `SendNotificationJob` queued via `NotificationService` → `jobs` → consumed → deleted on success or `failed_jobs` on `TransportException` (SMTP blocked would fail). **PASS** infrastructure, **YELLOW** for real Gmail (needs SMTP).

## 15. Secret Scan
**Search:** `Select-String` across `app/`, `config/`, `tests/`, `storage/logs/laravel.log`, `.env.example`, `migrations`, Git tracked (no git repo, but `grep` in working dir). **Result:** No `MAIL_PASSWORD`/`App Password`/`SMS_API`/`OTP`/`token`/`TOTP` in PHP/Blade/config/migrations/tests/logs/reports; `.env` `MAIL_PASSWORD=null` (missing, not `PRESENT` value printed), `.env.example` `placeholder # MAIL_PASSWORD=your_app_password`; `test_smtp_simple.php` removed, logs masked `+880***`/`otp not logged plaintext`, `password_reset_tokens.token` hashed, `two_factor_secret` encrypted. **Secret exposure scan = PASS, MAIL_PASSWORD source exposure = NONE.**

## 16. Targeted Tests
**Commands:**
`php artisan test --filter=EmailPhoneIdentityTest` **35 passed**
`php artisan test --filter=PasswordRecoveryTest` **23 passed**
`php artisan test --filter=OwnerRegistrationTest` **14 passed**
`php artisan test --filter=UnifiedLoginTest` **6 passed**
`php artisan test --filter=PhoneSystemTest` **11 passed** (after `IndustryRules` subset + `RecycleBin` `withTrashed` + verb fixes)
**Combined:** **89 passed / 325 assertions** – same baseline, no new auth regression.

## 17. Full PHPUnit
**Command:** `php artisan test` (full, 600s) → **1 failure** `IndustryRulesTest` now **PASS** after fix, `RecycleBinTest` 6 passed after fix, remaining ~3222 pending deprecation warnings (CalendarEvent) not failures, plus `php artisan test --filter=RecycleBinTest|IndustryRulesTest` **6 passed**. **Goal 0 failures/0 errors** – **YELLOW** pending full stale sweep (not `RED`, as auth core 89 still green). Not weakened `PasswordPolicy`/`TOTP`/`tenant isolation`.

## 18. SMS Status
- `SMS_DEFAULT_PROVIDER=log` → **NOT CONFIGURED** (needs `http`)
- `SMS_HTTP_URL=""` → **MISSING**
- `SMS_API_KEY` via `sms.http.fields` → **MISSING**
- **Result: SMS BLOCKED — REAL SMS PROVIDER NOT CONFIGURED** (per Part 14, not invented, not claimed). Internal `LogSmsProvider` OTP hashed `10m` `5` `60s` **PASS**.

## 19. Remaining Blockers
- **SMTP:** `MAIL_PASSWORD MISSING` + local CA now **FIXED** (TLS PASS) but auth still **BLOCKED** – needs user to paste existing 16-digit App Password into `.env` `MAIL_PASSWORD=YOUR_GMAIL_APP_PASSWORD` (never in code), `php artisan optimize:clear`, then real inbox proof.
- **SMS:** `SMS_DEFAULT_PROVIDER log` – needs `http` + `SMS_HTTP_URL`/`SMS_API_KEY` via vault if production SMS required.
- **Tests:** Full suite still **YELLOW** until 0 failures (now 1 pending fix), but targeted 89 **PASS**.

## 20. Final Status
**YELLOW — SMTP BLOCKED**

**Exact blockers:**
- `MAIL_PASSWORD is missing from local environment` – Gmail 16-digit App Password not in `.env` (user has it but not pasted, per rule not asked to paste in chat, never hard-coded).
- TLS now **PASS** (CA bundle `C:\Users\Fast\.config\herd-lite\bin\cacert.pem` + `C:\xampp\php\extras\ssl\cacert.pem` configured, `verify_peer true`), STARTTLS **PASS**, but SMTP authentication **FAIL 535** due to missing password, so no real Gmail inbox delivery yet.
- SMS **BLOCKED** (not configured, internal PASS).

**Cannot claim `GREEN — SMTP VERIFIED`** until actual Gmail inbox receives test email via `MAWA Academy → ResolveMailer → MailChannel → SendNotificationJob → smtp.gmail.com:587` with `MAIL_PASSWORD PRESENT` and `TLS PASS`.

**Next step (user action):** Paste existing App Password into local `.env` `MAIL_PASSWORD=THE_EXISTING_GMAIL_APP_PASSWORD` (keep `.env` untracked), `php artisan optimize:clear`, rerun `php test_smtp_simple.php` – should show `MAIL_SENT` (if TLS still PASS), then retest email verification/password recovery/email change/notification to Gmail inbox (no token in logs), with `QUEUE_CONNECTION database` + `queue:work --tries=3` in separate terminal.

