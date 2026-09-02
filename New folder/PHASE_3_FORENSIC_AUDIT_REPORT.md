# PHASE 3 — PENDING REGISTRATION LIFECYCLE FORENSIC AUDIT REPORT

**Date:** 2026-08-27  
**Scope:** PendingRegistration lifecycle (creation → OTP → onboarding → atomic creation → cleanup)  
**Baseline:** GREEN registration + OTP invariants retained

---

## 1. Complete Registration Lifecycle Map

```
Email+Password (POST /register/account, throttle 10/hr IP + 5/hr email, EmailNormalizer, EmailDomainPolicy, User/InstituteUser/PlatformAdmin duplicate check)
  ↓
PendingRegistration::create {email unique, password_hash=PasswordService::hash, otp_hash=Hash, otp_expires_at +10m, expires_at +24h, attempts0, resend0}
  ↓ session[pending_id,email,verified=false,step1] + regenerate()
  ↓ Mail::queue EmailOtpMail(ShouldBeEncrypted) → jobs(encrypted) → worker → SMTP
  ↓ GET /register/verify-otp (resolvePending: isGraceExpired/isAbandonedExpired check, email mismatch clear)
OTP verify POST /register/verify-otp (10/min per pending+IP + 5/OTP attempts, lockForUpdate, single-use, verified_at+expires_at+48h, regenerate)
  ↓ session verified=true
GET/POST /register/organization (verified check, IndustryRules, Phone E164, duplicate phone check, organization_data JSON)
  ↓ GET/POST /register/address (verified+organization required, GeoHierarchy lock, address_data JSON)
  ↓ POST /register/address → finalizeRegistration: DB::transaction + lockForUpdate pending + User unique check → User.create(verified) + Institute.create(slug) + Membership.assign(owner) + pending.delete → post-commit AcademicSetup/Demo (non-blocking) → Auth::login + regenerate + Workspace::set + clear session → education? placeholder : dashboard

Alternate/legacy: POST /register → alias to storeAccount, POST /register/selection + GET /register/form → redirect to new flow, GET /workspace/create requires auth:web (tenant), institute/register (staff) separate.
No API/CLI/seed path creates PendingRegistration (grep artisan/seed/factory only creates User directly for admin).
```

**Duplicate handling (Case C):** unique email prevents 2 rows; storeAccount: if existing pending not verified && not graceExpired → resume (update password_hash, reset expires_at, reuse, resend OTP invalidates old); else delete expired then create new. No multiple valid OTP identities.

**Normalization:** `EmailNormalizer::normalize` (lowercase/trim) before unique check and key, prevents case/whitespace bypass (`User@Example.COM` == `user@example.com`). Phone via `PhoneNormalizer::toE164`.

**Session:** `pending_id` alone insufficient; `resolvePending` validates `session email == DB email` else clear. `pending_id` tamper → 302. Multi-browser same email → distinct pending rows impossible due to unique email; second browser's POST reuses same row (resume) but session email still bound, cross-session A cannot access B's pending because session stores different pending_id/email.

---

## 2. PendingRegistration State Machine

Authoritative timestamps: `created_at`, `expires_at` (24h unverified, pushed to +48h on verify), `verified_at`, `otp_expires_at`, `organization_data`, `address_data`, `deleted_at via delete`.

States derived:

- **CREATED / OTP_SENT:** `verified_at null && !isGraceExpired()` → can only access verify-otp, resend.
- **OTP_VERIFIED:** `verified_at not null && !isAbandonedExpired() && organization_data null` → can access organization.
- **ONBOARDING:** `verified_at not null && !isAbandonedExpired() && organization_data not null` → can access address.
- **COMPLETED:** pending deleted inside final transaction (no row).
- **EXPIRED:** `verified_at null && isGraceExpired()` → resolvePending deletes + 302 to account, cannot continue.
- **ABANDONED:** `verified_at not null && isAbandonedExpired()` (verified_at+48h) → delete, cannot continue.
- **CLEANED:** row deleted.

Verified: `CREATED+expired → 302`, `CREATED not verified → organization 302`, `VERIFIED → organization OK`, `VERIFIED+abandoned → 302 + cleanup`, `COMPLETED → row absent`.

Code: `PendingRegistration::state()` + `isGraceExpired()` + `isAbandonedExpired()` (verified_at+48h). Verify now extends `expires_at` to +48h.

---

## 3. Every Bypass Found

| Route | Attack | Expected | Evidence | Fix | Severity |
|-------|--------|----------|----------|-----|----------|
| `GET /register/organization` without verify | direct navigation | 302 to verify-otp | `showOrganization` checks `!isVerified()` → redirect | already enforced | - |
| `POST /register/organization` before verify | curl bypass | 302 | same check | enforced | - |
| `GET/POST /register/address` without verify/org | direct POST | 302 | `showAddress` checks verified + organization_data | enforced | - |
| `POST /register/education` / `workspace/create` guest | guest creation | 302 login | `auth:web` middleware | enforced | - |
| `pending_id A + email B` session tamper | IDOR | clear session 302 | `resolvePending` email mismatch → `forget` | enforced | - |
| `expired pending` (24h) reuse | stale session replay | 302 + delete | `isGraceExpired()` + `isAbandonedExpired()` synchronous check | **hardened** (verified grace 48h) | High |
| `concurrent finalize` double User | 2x POST /register/address same pending | only one User | `User::where email unique` + now `lockForUpdate pending` inside transaction → second throws ValidationException, rollback | **hardened** | Critical |
| `concurrent verify` double success | 2x verify same OTP | only one | `lockForUpdate` in `PendingRegistrationOtpService::verify` | already enforced | - |
| `concurrent resend` 2 OTPs valid | race | old invalid | `transaction+lockForUpdate` + overwrite `otp_hash` | enforced | - |
| legacy `POST /register` alias | bypass | alias to new flow | `Route::post('register', fn()=>storeAccount)` | enforced | - |
| API `User::create` | hidden API | none found | grep `routes/api.php` no register | - | - |

All direct POST bypasses **blocked**.

---

## 4. Unverified Expiration Rule

- **Synchronous boundary:** `resolvePending` checks `isGraceExpired()` / `isAbandonedExpired()` before any step, deletes row, clears session, 302 to account — even if scheduler not run for 30 days.
- **Cleanup housekeeping:** `registrations:cleanup` chunked delete of `whereNull verified_at && expires_at < now()` (24h) — idempotent, never deletes User/Institute/Membership (only `pending_registrations` table, checked `User::where email existence` before abandoned delete).
- **Test:** `test_lifecycle.php` created unverified `expires_at -25h` → state EXPIRED → `Artisan::call cleanup` → deleted, `after=0`.

---

## 5. Verified-But-Abandoned Rule

- OTP verified ≠ account created. `verified_at` set + `expires_at` pushed to +48h (new). Grace: `verified_at+48h` → `ABANDONED` → `isAbandonedExpired()` true → synchronous block + cleanup delete.
- Cleanup: `whereNotNull verified_at` chunked, lockForUpdate, `isAbandonedExpired()` check, per-row DB transaction, safe if User now exists (pending still deleted as orphan, no business data affected).
- Verified: lifecycle test `verified_at -49h` → `ABANDONED` → cleanup deletes.

---

## 6. Partial Failure / Transaction Safety

- Final `DB::transaction` groups `User.create + Institute.create + syncLegacy + Membership.assign + pending.delete`; on any exception (slug conflict, FK, validation) → rollback → `User0 Institute0 Membership0`, pending remains recoverable (not deleted). Post-commit `AcademicSetup`/`Demo` outside transaction, non-blocking (log only). Tested via concurrent finalize (unique email constraint).

---

## 7. Duplicate Registration Rules

- **Case A (verified User exists):** `storeAccount` `User::where email exists → 422 Email already taken` (safe, intentional enumeration for registration UX).
- **Case B (unverified User):** Should not exist under new flow (User only created after verify). Legacy unverified Users (if any) → same Case A handling (reject), no coexistence with pending.
- **Case C (pending exists):** resume if `!verified && !graceExpired` (update hash, reset expiry, resend invalidates old) else delete expired/abandoned then create new — deterministic, no duplicate valid OTP.

---

## 8. Multi-Device / Multi-Browser

- Same email from Browser B reuses row (resume) — old OTP invalidated by new send (overwrite + lock). Session A cannot access B's pending because `session pending_id` differs and email mismatch check clears. Concurrent account/verify/resend/organization/address all protected by `lockForUpdate` + Cache throttle. Cross-identity `organization_data` cannot leak (pending row scoped).

---

## 9. Session Security

- Keys: `pending_registration_id`, `registration_flow {email,verified,step}` — created on storeAccount with `regenerate()`, updated on verify (`regenerate()` again), cleared on finalize (`forget` + `regenerate` via login) and on `isGraceExpired`/`isAbandonedExpired`/`resolvePending` miss. `pending_id` tamper → email mismatch → clear. Old browser session after completion → `find` returns null → 302 to account. Logout → `Auth` logout invalidates session (Laravel), pending session cleared. No fixation.

---

## 10. Organization Data Retention

- `organization_data` {organization_name, first/last, phone E164, country, industry, sub_industry} and `address_data` {country_id, admin_* , zip, address} — only resume data, no OTP plaintext (`otp_hash` hashed), `password_hash` is `PasswordService::hash` (bcrypt), no tokens. Cleared on `pending.delete()` after transaction.

---

## 11. Cleanup Command Forensic Audit

- **Before:** mass `delete()` without chunk/lock, unsafe for millions, not idempotent under concurrent workers, no per-row exception.
- **After:** `chunkById(200)` + `DB::transaction + lockForUpdate` per row, per-row try/catch, `User::where email` existence check before abandoned delete, masked logging `cleanup_pending_registrations {expired,abandoned}`, idempotent (second run 0), concurrent-safe (second worker lock waits then finds row gone).

---

## 12. Schedule Failure Safety

- Runtime `resolvePending` blocks expired/abandoned even if scheduler not run for 1/7/30 days — cleanup is housekeeping, not auth.

---

## 13. Cross-Tenant / Cross-User Isolation

- Pending has no `institute_id` until final creation; verify scoped to pending row, not tenant. Malicious `pending_id A + email B` → mismatch clear. `institute` only created inside verified transaction with `country_id` from geo.

---

## 14. Route Bypass Audit

All 7 register routes + 2 legacy aliases + `workspace/create` audited — all enforce verified via `resolvePending` or `auth:web`. No API registration.

---

## 15. Abandonment / Re-Registration

- Expired pending deleted → new `POST /register/account` with same email → fresh pending, old OTP/pending_id/session invalid. Test: create pending `expires_at -25h` → cleanup → re-register same email → new pending created, old session 302.

---

## 16. Rate Limit / Resource Abuse

- `storeAccount`: `register_account_ip:10/hr` + `register_account_email:5/hr` (normalized email md5, layered IP+email). OTP: `pending_otp_send:60s` + 5/hr per pending, `pending_otp_verify:10/min per pending+IP` + 5/OTP attempts. Flood DB via unlimited pending → blocked by unique email + per-email limit.

---

## 17. Error Message Security

- User-safe: `Email already taken`, `Invalid verification code`, `This verification code has expired`, `Too many incorrect attempts`, `Please wait…`, `Failed to resend…` — no OTP/hash/IDs/stack/tenant leaked. Server logs masked.

---

## 18. Email / Queue Failure

- `PendingRegistrationOtpService::send` `try { queue } catch { report; try send sync }` — pending remains, verification based on DB `otp_hash` not queue success, user can resend (invalidates old), no false verified.

---

## 19. Database Constraint Audit

- `pending_registrations.email` unique → no duplicate OTP identities, supports concurrent-safe resume. Indexes `otp_expires_at, expires_at, verified_at` for cleanup efficiency. Nullable timestamps allow states. No FK checks disabled, no global deletes.

---

## 20. Test Matrix

| Group | Tests |
|-------|-------|
| Creation | pending created, User0, Institute0 — `RegistrationFlowTest` |
| OTP | valid, invalid, expired, reused, 5 attempts lock, resend old invalid, leading-zero `004271` — `RegistrationFlowTest` (18) |
| Lifecycle | unverified expiration, verified abandoned 48h, cleanup deletes only pending, idempotency (run twice 1→0), re-registration after cleanup — `test_lifecycle.php` manual + `RegistrationFlowTest` |
| Concurrency | concurrent verify (lock), concurrent resend, concurrent finalize (lock+unique) |
| Security | session tamper (mismatch clear), pending_id tamper, email mismatch, cross-user, legacy bypass, direct POST |
| Failure | User transaction rollback, slug conflict, queue failure (no verify) |
| Abuse | IP + email rate limit, OTP attempt limit |

**Existing suites:** `RegistrationFlowTest 18 passed (52)`, `OwnerRegistrationTest 14 passed`, `InstituteOnboardingTest 9 passed`, `InstituteCreationTest 3 passed`.

---

## 21. Regression Safety

- No new failures introduced; `WorkspaceContextTest` missing seed `MAWA ACADEMY` is pre-existing.

---

## 22. Final Security Invariants (20)

1. OTP NOT VERIFIED → User 0 — **PASS**
2. OTP NOT VERIFIED → Institute 0 — **PASS**
3. OTP NOT VERIFIED → Membership 0 — **PASS**
4. EXPIRED PENDING → cannot continue — **PASS**
5. ABANDONED VERIFIED → eventually cleaned — **PASS** (48h)
6. CLEANUP → never deletes business data — **PASS**
7. OLD OTP → invalid — **PASS**
8. USED OTP → invalid — **PASS**
9. CONCURRENT VERIFY → one success — **PASS**
10. CONCURRENT RESEND → one current OTP — **PASS**
11. SESSION TAMPERING → blocked — **PASS**
12. IDENTITY MISMATCH → blocked — **PASS**
13. CROSS-TENANT → blocked — **PASS**
14. LEGACY BYPASS → blocked — **PASS**
15. QUEUE FAILURE → no false verification — **PASS**
16. FINAL TX FAILURE → no orphan — **PASS**
17. SCHEDULE FAILURE → still blocked — **PASS**
18. RE-REGISTRATION → clean isolated — **PASS**
19. OTP → never plaintext — **PASS** (Hash + encrypted queue)
20. CLEANUP → idempotent concurrency-safe — **PASS**

---

## Files Changed
- `app/Models/PendingRegistration.php` (isAbandonedExpired, state)
- `app/Services/Identity/PendingRegistrationOtpService.php` (verified extends expires_at +48h)
- `app/Http/Controllers/Auth/RegistrationFlowController.php` (per-email throttle, isAbandonedExpired check, lockForUpdate finalize)
- `app/Console/Commands/CleanupPendingRegistrations.php` (chunked tx+lock idempotent)

Database impact: no new migration; pending table already has `expires_at` (reused, now set +48h on verify), `verified_at`, indexes.

Security impact: verified abandon lifecycle deterministic, race-safe, abuse-resistant.

## Remaining Risks
None material; super-admin `yeasinsheikh999@gmail.com` virtual verified remains intentional.

**Verdict: GREEN — Pending lifecycle deterministic, abuse-resistant, cleanup-safe, concurrency-safe, production-ready.**
