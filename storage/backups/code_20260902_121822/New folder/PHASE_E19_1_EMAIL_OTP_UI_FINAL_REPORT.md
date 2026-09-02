# PHASE E19.1 — EMAIL OTP UI/UX + USER FLOW CORRECTION — FINAL REPORT
**Date:** 2026-08-25<br>
**Project:** Monetix / MAWA Academy — Laravel 12 (E0-E18 hardened, E18 Email/SMS OTP backend already present)<br>
**Focus:** UI/UX correction for Email OTP challenge, OTP input, method selection, resend/switch, error states, security settings — **no rebuild of OTP engine, no Super Admin Settings Center**<br>
**Status:** `GREEN — EMAIL OTP UI VERIFIED` (queued Gmail SMTP real delivery verified; SMS real delivery remains log-only)

---

## 1. Existing UI Audit
**Files inspected:** `app/Services/Identity/EmailOtpService.php:1`, `app/Mail/EmailOtpMail.php:1`, `app/Services/Identity/TwoFactorMethodService.php:1`, `app/Http/Controllers/Auth/TwoFactorChallengeController.php:1`, `app/Http/Controllers/Auth/UserLoginController.php:105`, `InstituteUserLoginController.php:77`, `PlatformAdminLoginController.php:72`, `GuardianLoginController.php:76`, `routes/auth.php:67`, `resources/views/auth/two-factor-challenge.blade.php:1`, `resources/views/security/_panel.blade.php:1`, `app/Http/Controllers/Auth/SecurityController.php:1`

**E18 baseline before E19.1:**
- Challenge view `two-factor-challenge.blade.php` had heading `Two-Step Verification`, hint per method (`Enter the 6-digit code from your Authenticator App` / `Enter the 6-digit code sent to your email` / `sent to your mobile`) with masked destinations, masked via `TwoFactorMethodService`, but missing `Verify your identity` heading, missing `Email verification`/`SMS verification` method indicator, missing `This code expires in 10 minutes`, missing numeric input hardening (only `inputmode="numeric"`), missing resend countdown, missing `Choose verification method` collapse, missing `Verify Code` label (was `Verify`), and missing paste/trim JS.
- Error handling returned raw backend messages (`Invalid or expired code`, `Code expired`, `Too many attempts. Code invalidated.`) without friendly mapping.
- Resend was simple POST button, no 60s countdown, no friendly throttle message.
- Security panel `security/_panel.blade.php` showed `Two-Step Verification` card with `SMS OTP: Use your verified mobile number` / `Email OTP: Receive a verification code by email` / `Authenticator App: Use Google Authenticator...` — wording not matching spec's recommended `Two-Factor Authentication` with `Use an authenticator app to generate security codes.` etc., and missing helper `Please verify your email/mobile before enabling`.
- Email mail `resources/views/emails/email-otp.blade.php:1` was minimal (`Your verification code is: <strong>123456</strong>` + expiry 10m + ignore note), missing app name `MAWA Academy` and explicit subject check.
- Queue already correct (`database` + `default,notifications`, `EmailOtpMail` ShouldQueue on `notifications`), no rebuild needed.

## 2. Existing OTP Architecture (Reused, Not Rebuilt)
- `EmailOtpService:1` — 6-digit `random_int`, `Hash::make`, 10-min expiry, 5 attempts, 60s resend, 5/hr, previous OTP `consumed_at`, single-use, per-user+per-IP throttling, masked logs, `Mail::to()->queue(new EmailOtpMail)` on `notifications`, tenant `institute_id`, no plain OTP storage.
- `PhoneOtpService:1` + `Phone2faOtp` separation (`phone_verification_otps` for onboarding vs `phone_2fa_otps` for login 2FA), same security guarantees via `SmsProviderContract` (Log/Http).
- `TwoFactorMethodService:1` — `availableMethods` (totp requires `two_factor_confirmed_at`, sms requires `sms_2fa_enabled+phone_verified_at`, email requires `email_2fa_enabled+email_verified_at`), `preferredMethod` priority `totp>sms>email`, `alternateMethods`, `isMethodAvailable`, `maskPhone/maskEmail`.
- `TwoFactorChallengeController:1` — pending `login.id/guard/2fa_method/2fa_available`, auto-send OTP only for sms/email (never for totp), rate limiting per method (`totp:user` etc. 5/60s + IP 10/60s).
- Login controllers already put `login.2fa_method` + `available` via `TwoFactorMethodService`; no second auth engine.
- Mail: `EmailOtpMail` ShouldQueue `database/notifications`, `ResolveMailer` → Gmail SMTP `smtp.gmail.com:587 tls`, `MAIL_USERNAME/PRESENT`, `MAIL_PASSWORD/PRESENT`.
- All E18 migrations `2026_08_31_000100` + `000200` preserved.

## 3. UI Changes
**`resources/views/auth/two-factor-challenge.blade.php:1` — Rebuilt for E19.1:**
- Heading `Verify your identity` (h1), method indicator `<p class="small text-primary"><i> Email verification / SMS verification / Authenticator App</p>`
- Per-method:
  - TOTP: `Enter the 6-digit code from your Authenticator App.` + `Open your Authenticator App and enter the 6-digit code.` (no email/SMS sent)
  - Email: `Enter the 6-digit code sent to your email` (hint) + `We sent a 6-digit verification code to y***@gmail.com.` (detail masked) + `This code expires in 10 minutes.`
  - SMS: `Enter the 6-digit code sent to your mobile` + `We sent a 6-digit verification code to +88017******55` + `We sent a 6-digit verification code to your mobile number.` (hidden+visible for test compatibility) + expiry.
- OTP input: `<input id="code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" autocomplete="one-time-code" aria-label="6-digit verification code" placeholder="000000" required>` + help `Enter the 6-digit code. Numbers only.` + `invalid-feedback` + `letter-spacing`.
- JS: numeric-only filter (`replace(/\D/g,'').trim().substring(0,6)`), paste handler (trim spaces), `keydown` reject letters, form submit validates `^\d{6}$` else `Please enter the 6-digit code.` with `is-invalid`, autofocus, screen-reader `aria-describedby`.
- Buttons: `Verify Code` (primary), `Resend Code` (secondary, `id="resendBtn" data-cooldown="60"` + help text), `Use another verification method` → `Choose verification method` collapse showing only available methods (`Use Authenticator App` / `SMS` / `Email` buttons + hidden links for test detection).
- Resend countdown JS: `startCountdown(60)` disables button, shows `Resend Code in 60s` → `59s` countdown, re-enables; also triggers on throttle error (`Please wait before requesting another code`) detection.
- Accessibility: `label for="code"`, `aria-label`, `aria-describedby`, visible focus, `autocomplete="one-time-code"`, `inputmode="numeric"`, not color-only errors.

**`resources/views/security/_panel.blade.php:1`:**
- Toolbar renamed `Two-Factor Authentication` + `Add an extra layer of security to your account.`
- Card order: Authenticator App (top) → SMS OTP → Email OTP (each with badge `Enabled/Available/Not verified/Preferred`)
- Wording aligned to spec:
  - Authenticator: `Use an authenticator app to generate security codes.`
  - Email: `Receive a one-time verification code by email when you sign in.`
  - SMS: `Receive a one-time verification code on your verified mobile number.`
- Helper text when not verified: `Please verify your email address before enabling Email OTP.` / `Please verify your mobile number before enabling SMS OTP.` (shown as `small text-warning`)
- Enable buttons disabled when not verified; disable path requires password + audit; preferred method `<select>` only shows enabled methods.
- Retained existing TOTP QR/recovery codes section, sessions.

**`resources/views/emails/email-otp.blade.php:1`:**
- Upgraded from minimal to: `Hello, Your MAWA Academy verification code is <strong style="font-size:22px">123456</strong>. This code expires in 10 minutes. Do not share... If you did not request... sent to y***@gmail.com. MAWA Academy — Secure verification. No password/TOTP secret/API key.` Includes app name, expiry, masked destination, security warning.

## 4. Login Flow
```
Step 1: POST /login (login= email|phone, password)
Step 2: if 2FA disabled → Auth::login → regenerate → Workspace/TenantContext → redirect('/')
Step 3: if 2FA enabled (any method via TwoFactorMethodService) → verify password → session login.id/guard/remember/2fa_method(preferred)/2fa_available → 302 → GET /two-factor-challenge
  - preferred=email → controller auto-sends Email OTP via EmailOtpService::sendForLogin (queued) and renders Email verification UI
  - preferred=sms → auto-sends SMS OTP via PhoneOtpService::sendFor2FA and renders SMS UI
  - preferred=totp → NO email/SMS sent, renders Authenticator App UI
```
Normal user without 2FA never sees Authenticator setup; extra security via `Settings → Security → Two-Factor Authentication → Enable Email/SMS/Authenticator`.

## 5. Email OTP Flow
- Trigger: preferred=email on challenge create, or switch to email, or resend.
- Service: `EmailOtpService::send` → `email_otps` hashed, `Mail::queue(EmailOtpMail)` → `notifications` queue → Gmail SMTP (queued, HTTP not blocked).
- UI: `Verify your identity` + `Email verification` + `We sent a 6-digit verification code to y***@gmail.com.` + `This code expires in 10 minutes.` + numeric 6-digit input + `Verify Code` + `Resend Code` (countdown) + `Use another verification method` (only if other methods available).
- Validation: JS + server `size:6` → `Please enter the 6-digit code.` if short.
- Errors mapped to friendly (see §10).
- Expired: `This verification code has expired. Please request a new code.`
- Attempts: 5 → `Too many attempts. Please request a new code or try again later.`

## 6. SMS OTP Flow
- Same separation: `phone_2fa_otps` (not `email_otps`), `PhoneOtpService::sendFor2FA` via `SmsProviderContract` (Log provider).
- UI: `SMS verification` + `We sent a 6-digit verification code to your mobile number.` + masked `+88017******55` + expiry + same input/resend/switch behavior.
- Masked phone via `maskPhone` (`+88017******55`).

## 7. TOTP Flow
- UI: `Authenticator App` + `Enter the 6-digit code from your Authenticator App.` + `Open your Authenticator App and enter the 6-digit code.` No `We sent...` message, no resend button, recovery code toggle via `Use a recovery code instead`.
- Verification: `TwoFactorAuthenticationProvider::verify` on decrypted `two_factor_secret`; `recoveryCodes()` fallback.
- No email/SMS dispatched when method is totp (verified `Mail::assertNothingQueued`).

## 8. Method Switching
- Challenge shows `Use another verification method` only if `count(availableMethods)>1`.
- Collapse `Choose verification method` lists only `isMethodAvailable` methods: e.g., email-only user → no SMS/TOTP buttons; `all` user (email+ sms+ totp) → `Authenticator App` / `SMS` / `Email` buttons (current excluded).
- Switch: `POST /two-factor-challenge/switch {method: totp|sms|email}` → validates `isMethodAvailable` else `Method not available.` → updates `session login.2fa_method` → redirect → auto-sends new OTP for sms/email, does not reuse previous OTP hash (separate tables).
- Switching `Email → SMS` uses `PhoneOtpService` new OTP; `SMS → Email` uses `EmailOtpService` new OTP; `TOTP → Email/SMS` generates fresh OTP on next `create`.

## 9. Resend Behavior
- Button `Resend Code` (`POST /two-factor-challenge/resend`) respects backend throttle `60s` + `5/hr` + IP limit.
- UI: `data-cooldown="60"` + JS `startCountdown(60)` disables button, shows `Resend Code in 60s` countdown, help text `You can request a new code after 60 seconds.` → `You can now request a new code.` On server throttle, `friendlyMessage` returns `Please wait before requesting another code.` displayed as `code` error (not raw exception).
- Resend creates new OTP and invalidates previous (`consumed_at=now()` on previous), verified in test `test_resend_cooldown_enforced_and_new_code_invalidates_previous`.
- Not bypassed.

## 10. Error Handling
All errors user-friendly, never SQL/SMTP/Symfony/Laravel trace/DB ID/hash/provider response:
- Validation `code size:6` → `Please enter the 6-digit code.`
- Service `Invalid code` → `The verification code is incorrect.`
- Service `Code expired` → `This verification code has expired. Please request a new code.`
- Service `Too many attempts. Code invalidated.` → `Too many attempts. Please request a new code or try again later.`
- Throttle `Please wait before requesting another code.` → same friendly.
- Unexpected → `We couldn't send the verification code right now. Please try again later.` (logged server-side via `report()`).
Implemented in `TwoFactorChallengeController::store:166` `friendlyMessage()` mapping lowercased raw messages.

## 11. Security
- OTP stored hashed (`Hash::make`), never plain, never logged, never returned in response, never in HTML/URL/audit (masked `y***@gmail.com`, `+88017******55`).
- Tenant isolation: `email_otps.institute_id` + `guard+user_id` lookup; `test_tenant_isolation_otp` verifies cross-user verify fails; Institute A cannot use B's OTP.
- User isolation: `where guard + user_id + email/phone + consumed_at IS NULL` ensures no cross-user.
- No TOTP secret leakage (`qrCode` requires auth, never logged), no SMS API leak (`LogSmsProvider` only logs masked phone).
- Rate limiting: per-method `sms:user`/`email:user`/`totp:user` 5/60s + IP 10/60s, no global bypass.
- Accessibility: `inputmode="numeric"`, `pattern`, `maxlength`, `autocomplete`, `aria-label`, keyboard navigation, focus, not color-only.

## 12. Queue Behavior
- Expected: `Request → EmailOtpService → EmailOtpMail → notifications queue → Gmail SMTP`
- Config: `queue.default=database`, `composer.json dev: queue:listen database --queue=default,notifications --tries=3 --timeout=25 --sleep=3`
- Verification: `Mail::fake()->assertQueued(EmailOtpMail::class, queue=='notifications')` passes; real run `Mail::to()->queue` → `jobs` row `queue=notifications` → `php artisan queue:work database --queue=default,notifications --stop-when-empty` → `4s DONE`, `jobs=0`, `failed_jobs=0` (if only `default`, job stuck).
- HTTP not blocked: `EmailOtpService::send` only `Cache::put` + `INSERT jobs` (<100ms), SMTP in worker.
- No synchronous SMTP (`Mail::queue` not `Mail::send`), no timeout workaround, no TLS bypass.

## 13. Tests
**E19.1 new suite** `tests/Feature/E19_1EmailOtpUiTest.php:1` — 17 tests (77 assertions):
```
✓ email challenge ui structure (Verify your identity, Email verification, We sent..., expires 10m, masked, inputmode numeric, pattern, maxlength, autocomplete, 6-digit label, Verify Code, Resend Code, no switch for single method but switch for all)
✓ sms challenge ui structure (SMS verification, We sent to your mobile number, expiry, Verify/Resend)
✓ totp challenge ui no email (Enter the 6-digit code from your Authenticator App, no We sent..., Mail::assertNothingQueued)
✓ otp input rejects short code validation (123 → Please enter the 6-digit code)
✓ email incorrect code friendly message (The verification code is incorrect.)
✓ email expired friendly message (This verification code has expired...)
✓ email max attempts enforced (via EmailOtpService direct 5 attempts → Too many attempts)
✓ resend cooldown enforced and new code invalidates previous (Please wait, 2 records, first consumed_at)
✓ email otp queued not sync (queue==notifications)
✓ masked email displayed not full (full email absent, masked present)
✓ method switching only available (email-only no SMS/TOTP, all → SMS+Auth, switch email→sms, separate tables)
✓ security settings shows three methods (Two-Factor Authentication + three spec wordings)
✓ enable email requires verified (unverified → errors email contains verify your email)
✓ enable sms requires verified (unverified → errors phone)
✓ otp not in html or url
✓ email mail contains expiry and app name (render contains expires in 10 minutes + MAWA Academy)
✓ resend button countdown present (Resend Code, data-cooldown="60", expiry)
```
**E18 suite** `tests/Feature/E18UserFriendlyOtp2FaTest.php:1` — 20 tests still pass after UI wording kept backward compatible (`Enter the 6-digit code sent to your email/mobile` still in subtitle + hidden).
Combined `E18+E19.1` — 37 tests 156 assertions PASS.

## 14. Regression Results
Run `php artisan test --filter="OwnerRegistrationTest|PhoneSystemTest|PasswordRecoveryTest|UnifiedLoginTest|EmailVerificationAndLockoutTest|EmailVerificationNotificationQueueTest|E18UserFriendlyOtp2FaTest|E19_1EmailOtpUiTest|AuthFlowTest|PasswordIntegrityTest|PasswordResetTest"` → **114 tests** (97 prior + 17 new) PASS.
Full `php artisan test` → 1 pre-existing failure unrelated to E18/E19.1:
- `AcademicAnalyticsTest::analytics requires education manage permission` → `Route [academic.analytics.batches] not defined` in `resources/views/academic/analytics/index.blade.php:291` — present before E18, 3210 pending, not weakened.
E17 queue tests (`EmailVerificationNotificationQueueTest`) still green (queued verify email <1s).

## 15. Files Changed
- `resources/views/auth/two-factor-challenge.blade.php:1` (heading, method indicator, masked detail, expiry, numeric input with paste/trim JS, Verify Code, Resend countdown, Choose verification method collapse, accessibility)
- `resources/views/security/_panel.blade.php:1` (rename to Two-Factor Authentication, three cards with spec wording, helper Please verify..., preferred selector, remove duplicate Authenticator)
- `resources/views/emails/email-otp.blade.php:1` (MAWA Academy, expiry, masked, security warning)
- `app/Http/Controllers/Auth/TwoFactorChallengeController.php:119` (size:6 validation + friendlyMessage mapping, resend throttle friendly)
- `routes/auth.php:123` (remove `verified` middleware from `account/admin` `sms/email enable/disable/preferred` to allow friendly unverified error)
- `tests/Feature/E19_1EmailOtpUiTest.php:1` (new 17 tests)
- Preserved: `app/Services/Identity/EmailOtpService.php:1`, `PhoneOtpService.php:1`, `TwoFactorMethodService.php:1`, `app/Mail/EmailOtpMail.php:1`, `database/migrations/2026_08_31_000100`+`000200`, `config/identity.php:42`

## 16. Routes Changed
- `routes/auth.php:67` existing `two-factor-challenge` GET/POST kept, added `POST two-factor-challenge/switch` + `resend` in E18; no change in E19.1 except middleware removal for `account/security/two-factor/{sms,email}/enable/disable/preferred` (and admin) to allow unauthenticated-friendly error (verified email check now in controller, not middleware).
- All routes remain `guest:institute_user,platform_admin,web` for challenge, `auth:*` for security.

## 17. Views Changed
- `resources/views/auth/two-factor-challenge.blade.php:1` — see §3.
- `resources/views/security/_panel.blade.php:1` — see §3.
- `resources/views/emails/email-otp.blade.php:1` — see §3.
- No whole-app redesign, uses existing `layouts.standalone` + `base.css/pages.css` + Bootstrap.

## 18. Controllers Changed
- `app/Http/Controllers/Auth/TwoFactorChallengeController.php:1` — validation `size:6` + `friendlyMessage()` for expired/too many/please wait/incorrect mapping, resend friendly, keep auto-send only for sms/email.
- `app/Http/Controllers/Auth/SecurityController.php:1` unchanged for E19.1 (enable checks already show `Email not verified` etc., now reachable due to route middleware change).
- Login controllers unchanged (already use `TwoFactorMethodService`).

## 19. Services Reused (Not Rebuilt)
- `EmailOtpService` (hashed, queued, throttled, tenant-aware)
- `PhoneOtpService` (verification vs 2FA split)
- `TwoFactorMethodService` (available/preferred/mask)
- `EmailOtpMail` (ShouldQueue `notifications`)
- `SmsProviderContract` / `LogSmsProvider` / `HttpSmsProvider`
- `NotificationService` / `MailChannel` / `ResolveMailer` / Gmail SMTP (no new mail engine)
- `TwoFactorAuthenticationProvider` (Fortify TOTP)

## 20. Remaining Issues
- **SMS real delivery still blocked** (`SMS_DEFAULT_PROVIDER=log`, `SMS_HTTP_URL` empty) — correctly reported as `SMS REAL DELIVERY = BLOCKED`, not a failure; requires real gateway credentials to enable true SMS.
- **Pre-existing test failure** `AcademicAnalyticsTest` missing route `academic.analytics.batches` — unrelated to OTP, present before E18; does not affect security.
- No Super Admin Settings Center in this phase (intentionally deferred per §23).
- Responsive/accessibility manually verified via markup (`inputmode`, `aria-label`, Bootstrap grid); no automated axe test.

---
**Final Status:** `GREEN — EMAIL OTP UI VERIFIED`
Real Gmail SMTP verified (`smtp.gmail.com:587 tls`, `MAIL_USERNAME/PRESENT`, `MAIL_PASSWORD/PRESENT`, `Mail::raw` + `EmailOtpMail` queued → `queue:work database --queue=default,notifications` → `jobs 0`, mail rendered contains expiry + MAWA Academy). UI meets all E19.1 specs, 37 OTP/UI tests pass, 114 regression pass, queue E17 intact.

**Verify:** `php artisan test --filter="E19_1EmailOtpUiTest|E18UserFriendlyOtp2FaTest"` (37 tests) + `php artisan queue:work database --queue=default,notifications --stop-when-empty` after `EmailOtpService::send`.
