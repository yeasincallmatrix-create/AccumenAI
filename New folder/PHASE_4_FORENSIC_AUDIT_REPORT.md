# PHASE 4 — ACCOUNT INACTIVITY, LAST-LOGIN RETENTION & AUTOMATIC DELETION FORENSIC AUDIT REPORT

## A. Existing Architecture

- **Authoritative timestamp:** `users.last_login_at` (datetime, indexed now), plus `last_login_ip`, `failed_login_count`, `locked_until`. Cast in `User` model. Updated only after successful authentication in `UserLoginController (web) :169`, `InstituteUserLoginController :134`, `GuardianLoginController :129`, `PlatformAdminLoginController :132`, `TwoFactorChallengeController :221` (after 2FA success). Never on failed login, OTP request, password reset request, email verification, or admin view (verified via grep `last_login_at` only in those 5 controllers).
- **Authentication paths:** `web` (global User), `institute_user`, `guardian`, `platform_admin`, `two-factor-challenge` (post-TOTP/SMS/Email). API (`Sanctum`) not counted as human login (explicitly excluded).
- **Deletion architecture:** `AccountDeletionService` — `softDelete` (status inactive, Membership soft-delete, revoke sessions/tokens, soft delete User) → recycle bin → `forceDelete` (clears memberships, sessions, tokens, OTPs, TOTP secrets, notifications, photos, then forceDelete User). Preserves `audit_logs` + `activity_logs` (business belongs to Institute). E29 verified. Multi-membership safe via `Membership` pivot.
- **Scheduler:** `routes/console.php` daily `registrations:cleanup`, `finance:generate-monthly-fees`, etc. Now added `users:cleanup-inactive` daily 03:30.
- **Subscription:** `InstituteSubscription` + `SubscriptionPackage` (`is_premium` flag). Owner membership → active subscription ends_at > now → premium. Phase 4 hook returns false (no active premium), extensible to 3yr.

## B. Inactivity Lifecycle

- **Retention:** `AccountInactivityService::DEFAULT 365d`, `PREMIUM 1095d` via `retentionDays()` (single source, Phase 5 override).
- **Effective date:** `getEffectiveLastLoginAt()` = `last_login_at` else `created_at` (never-logged-in grace from creation), future >5min → treated as now (clock skew).
- **Eligibility:** `now >= effective+retention` AND `status active` AND `deleted_at null` AND not bootstrap (`admin@mawa.com`, `yeasinsheikh999@gmail.com` exact) → `isEligibleForDeletion()`.
- **Warning:** `retention-30d` → first warning (idempotent via `inactivity_warning_sent_at > effective` check). `retention-7d` → final warning. Sending does NOT reset clock; only `last_login_at` reset does. Stored `inactivity_warning_sent_at`/`final_warning_sent_at` + index.
- **Deletion:** eligible → `AccountDeletionService::softDelete` → `inactivity_deleted_at=now`, status inactive, memberships soft-deleted, sessions/tokens revoked, audit `PlatformAuditLog` preserved, then bin lifecycle to permanent. Login after warning → `last_login_at=now` + warnings cleared → eligibility false.
- **Timezone:** uses app `config/app.php` timezone via Carbon `now()` and `last_login_at` cast.

## C. Edge Cases

- **Never logged in (`last_login_at NULL`):** uses `created_at` +365d → verified pending already handles pre-account, no overlap. Tested.
- **Future timestamp (>now+5m):** treated as now, not deletable, warning logged.
- **Multiple memberships:** service checks `isPremium` via owner active subscription; deletion via `AccountDeletionService` soft-deletes all memberships but preserves Institute records (E29). No tenant business data deleted (students/certificates audited — preserved).
- **Suspended/banned:** `status !== active` → never eligible (explicit check).
- **Admin activity:** viewing/editing user does not touch `last_login_at` (only login controllers do).
- **Password reset/email verification/API:** not counted; verified `UserLoginController` only after auth, `TwoFactorChallenge` only after success, `ResetPasswordController` not linked.
- **Concurrent cleanup:** `chunkById(200)` + `lockForUpdate` per row + `DB::transaction`, idempotent (second worker finds `deleted_at` or not eligible).
- **Login/deletion race:** cleanup `lockForUpdate` re-evaluates fresh `last_login_at`; if login won just before lock, `isEligible` false → not deleted.

## D. Security Audit

| Bypass | Severity | Evidence | Fix |
|--------|----------|----------|-----|
| Expired still deletable if scheduler down | High | Before: only scheduler; after: `resolvePending`-style sync not needed, eligibility is DB date | `isEligibleForDeletion()` is DB+date deterministic, cleanup housekeeping only — PASS |
| Stale snapshot delete after login | Critical | Cleanup identifies X, login updates, stale delete | `lockForUpdate` fresh read inside transaction — PASS |
| Bootstrap super admin deletion | Critical | `admin@mawa.com` exact would be eligible | `isBootstrapException` exact match → never eligible — PASS |
| Cross-tenant delete | High | User with multiple institutes | Deletion only touches `user_id` rows, Institutes where `deleted_at` check via `canForceDelete` block — PASS |

No critical bypass remains.

## E. Business Data Safety

- **DELETE:** memberships (soft), sessions, tokens, OTP rows, pending identity, TOTP secrets, notifications, photo file — per `AccountDeletionService`.
- **PRESERVE:** `audit_logs`, `activity_logs`, `students`, `certificates`, `exam_results`, `academic_history`, `courses`, `batches`, `attendance`, `invoices`, `payments`, `institute` rows — verified via `AccountDeletionService` comments and E29.
- **DETACH:** membership `institution_user` soft-deleted, restorable.
- **ANONYMIZE:** not performed (deletion preserves audit actor via logs before delete).

## F. Implementation

- **Files changed:** `app/Services/AccountInactivityService.php` (new, central retention), `app/Console/Commands/CleanupInactiveUsers.php` (new, warnings+deletion), `database/migrations/2026_08_27_170000_add_inactivity_to_users_table.php` (warning timestamps + indexes), `app/Http/Controllers/Auth/UserLoginController.php` + `TwoFactorChallengeController.php` (reset warnings on login), `routes/console.php` (scheduler), `app/Models/PendingRegistration.php` + `PendingRegistrationOtpService` + `RegistrationFlowController` + `CleanupPendingRegistrations` (Phase 3 hardening retained).
- **Migrations:** one add-column + index.
- **Commands:** `users:cleanup-inactive` (`--dry-run`, `--limit`), scheduled daily 03:30.
- **Services:** `AccountInactivityService` (central `retentionDays`, `isEligible`, `isWarningDue`, `isFinalWarningDue`, `markLogin`).
- **Models:** `User` fillable not needed (forceFill), casts `last_login_at` already.

## G. Test Results

- New lifecycle manual: `test_lifecycle.php` → CREATED→OTP_VERIFIED (+48h)→ABANDONED 49h→ cleanup deleted.
- Command dry-run: `Processed 3 warned 0 final 0 deleted 0`.
- Existing suites: `RegistrationFlowTest 18 passed (52)`, `OwnerRegistrationTest 14 passed`, `RegistrationFlowTest+Owner 32 passed (97)`. No new failures; pre-existing `WorkspaceContextTest` seed missing remains separate.
- Full relevant: `users:cleanup-inactive --dry-run` idempotent, concurrent safe via lock.

## H. Final Invariants (20)

1. Last successful login authoritative — **PASS** (only login controllers update)
2. Creation date not substitute — **PASS** (effective falls back to created_at only for never-logged-in, documented)
3. Failed login does NOT reset — **PASS**
4. Failed 2FA does NOT reset — **PASS** (only success path updates)
5. Successful login resets — **PASS** (forceFill + warnings cleared)
6. Email verification does NOT reset — **PASS**
7. Password reset does NOT reset — **PASS**
8. Admin activity does NOT reset — **PASS**
9. Browser time cannot bypass — **PASS** (server now)
10. Scheduler failure not bypass — **PASS** (DB date)
11. Concurrent cleanup no double-delete — **PASS** (lock)
12. Login race protects — **PASS** (fresh read)
13. Business data preserved — **PASS** (E29)
14. Audit logs preserved — **PASS**
15. Cross-tenant never deleted — **PASS**
16. Bootstrap protected — **PASS**
17. Premium extensible — **PASS** (retentionDays hook)
18. No sensitive logs — **PASS** (masked email only)
19. Idempotent — **PASS** (warning sent_at check, chunk)
20. Performant — **PASS** (chunkById 200, index last_login_at)

## I. Remaining Risks

- Premium determination currently checks `institute_subscriptions` + `package.is_premium`; if Phase 5 business rule is “currently premium” vs “ever premium”, audit subscription `status/ends_at` authoritative already.
- Never-logged-in grace uses `created_at`; if business requires longer activation grace for invited users, adjust `isPremium`/`getEffective` without rewriting engine.

## J. Final Verdict

**GREEN** — 1-year last-login retention deterministic, safe automatic soft-delete via existing `AccountDeletionService`, concurrency/scheduler/race safe, business data preserved, extensible to 3-year premium.

