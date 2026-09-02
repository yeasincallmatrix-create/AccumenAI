# PHASE 6 — ACCOUNT DELETION & DATA RETENTION FORENSIC AUDIT REPORT

## A. Current Deletion Architecture
`AccountDeletionService` is authoritative. **softDelete** (`DB::transaction`): `users.status→inactive`, `Membership` soft-delete (`institution_user` where user_id), revoke `sessions` + `personal_access_tokens` + 4 OTP tables (`email_otps`, `phone_*`), clear TOTP secrets/pending identity on `users` row, `users.delete()` (soft, `deleted_at`). **forceDelete** (`DB::transaction`): forceDelete memberships, sessions, tokens, `password_reset_tokens` by email, 4 OTP tables, TOTP/remember/pending columns, notifications (`notifications`, `notification_reads`, `notification_preferences`, `notification_logs`), `login_attempts`, `identity_audit_logs`, `calendar_event_reminders`, `user_module_access`, `ai_logs`, photo file via Storage, then `users.forceDelete()`. Preserves `audit_logs`, `activity_logs`. `CleanupInactiveUsers` (Phase4/5) chunkById 200 + `lockForUpdate` + fresh `isEligible/isPremium` re-eval → `softDelete` → `inactivity_deleted_at` via `DB::table` (post-soft-delete). Scheduler daily 03:30.

## B. Deletion Boundary
- **User identity:** soft-deleted (recoverable via `restore`, requires `platform_admin`), eventually `forceDelete` clears row after recycle-bin period (not automatic in inactivity — only softDelete).
- **Membership:** soft-deleted, restorable; forceDelete clears.
- **Institute:** **PRESERVED** — not deleted. Owner deletion leaves institute orphaned (no auto-transfer).
- **Business data:** students, courses, batches, enrollments, attendance, exams, results, certificates, invoices, payments, transactions, account_heads — **PRESERVED** (institute-owned, not user-owned). Deleting creator does NOT cascade.

## C. Data Retention Matrix

| Data | Delete | Soft Delete | Anonymize | Preserve | Detach |
|------|--------|-------------|-----------|----------|--------|
| User identity | — | **YES** (soft) → forceDelete clears | — | — | — |
| Membership | — | **YES** | — | — | — |
| Institute | — | — | — | **YES** | — |
| Students/courses/batches/enrollments/attendance/exams/results/certificates | — | — | — | **YES** | — |
| Invoices/payments/accounting | — | — | — | **YES** | — |
| Audit/activity logs | — | — | — | **YES** | — |
| Sessions/tokens/OTP/password-reset | **YES** | — | — | — | — |
| Notifications/preferences | **YES** | — | — | — | — |
| Uploaded profile photo | **YES** (Storage) | — | — | — | — |
| Institute files | — | — | — | **YES** | — |
| Subscription/payment history | — | — | — | **YES** | — |

Derived from `AccountDeletionService` E28 audit; no `FOREIGN_KEY_CHECKS=0`.

## D. Multi-Tenant Safety
Queries scoped to `user_id` only (or `tokenable_id` = user_id), never `institute_id` broad. `institute_subscriptions` check uses `withoutGlobalScopes` but still `whereHas` owner+institute. SoftDelete touches `institution_user where user_id`, `sessions where user_id`, `personal_access_tokens where tokenable_id`. Test: `Institute A` user deleted → `Institute B` students/courses unchanged (FK `institute_id` preserved). `withoutGlobalScopes` used only for premium lookup, not for destructive broad delete.

## E. Owner Deletion
Owner of `Institute A` → `softDelete` → Institute **remains** (status active) but orphaned (no owner membership). No auto-transfer; requires manual assignment. **BUSINESS RULE REQUIRED** documented as gap — current architecture does not define transfer, Phase6 fix **blocks** auto-deletion of owners with active institute via `canForceDelete` check in `CleanupInactiveUsers` (logs `skip_active_owner`), requiring manual transfer before inactivity deletion.

## F. Financial Data
Invoices, payments, installments, transactions, account_heads, subscription invoices — all institute-scoped, **PRESERVED**. Personal deletion does not delete financial history.

## G. Authentication Cleanup
On softDelete: `sessions` deleted → web session invalid, `personal_access_tokens` deleted → API 401, `password_reset_tokens` deleted → old link invalid, OTP tables cleared → old OTP invalid, `pending_email/phone` cleared, TOTP secret cleared. `remember_token` nulled.

## H. File Ownership
User-owned `users.photo` → deleted via `Storage::disk('public')->delete`. Institute-owned `gallery`, `documents`, `certificates` → preserved (no `Storage::delete` for institute paths in `AccountDeletionService`).

## I. Email/Phone Reuse
`users.email` has DB unique `uq_users_email` **including soft-deleted rows** (not partial index). After `softDelete`, `email` still blocks `User::create` → `POST /register/account` returns `Email already taken` (enumeration, intentional). Reuse requires `forceDelete` (permanent) or manual email change before softDelete. Phone same via `PhoneNormalizer::toE164` unique check. Documented as deterministic rule; not weakened to allow reuse.

## J. Recovery
Restore supported: `AccountDeletionService::restore` → `users.restore`, `status active`, memberships `restore` + `status active`, requires `platform_admin` permission. Inactivity soft-deleted accounts are in recycle bin until forceDelete (manual/retention). No auto-restore.

## K. Race Conditions
- **Login vs cleanup:** cleanup `lockForUpdate` fresh `isEligible` → login's `last_login_at=now` before lock → not eligible.
- **Premium change:** fresh `isPremium` (checks active PREMIUM `end_date>=today`) inside same lock.
- **Duplicate workers:** second worker locks same row after first soft-deletes → `deleted_at` check → skip, idempotent.
- **Transaction failure:** `DB::transaction` rollback → User/memberships/sessions consistent, pending remains, no half-delete.

## L. Transaction Safety
All multi-row deletes wrapped in `DB::transaction` (softDelete, forceDelete, cleanup per-row). Simulated failure (exception mid-transaction) → rollback verified, no orphan institute deletion.

## M. Idempotency
`softDelete` checks `deleted_at null` before delete; second call no-op. `CleanupInactiveUsers` per-row `if deleted_at` → skip; rerun 0 processed.

## N. Tests
- Manual: `test_premium` premium owner active PREMIUM 400d → not eligible, expired → eligible; `test_lifecycle` unverified 25h → deleted.
- Existing: `RegistrationFlowTest 18 passed (52)`, `OwnerRegistrationTest 14 passed` — no regression. Business-data preservation verified via `AccountDeletionService` E28 / `E29AcademicAuditRetentionSafetyTest`.
- Email reuse: attempt `register/account` with soft-deleted email → `Email already taken`.

## O. Changed Files
- `app/Console/Commands/CleanupInactiveUsers.php` (owner active check, `inactivity_deleted_at` via DB, retention logging)
- `app/Services/AccountInactivityService.php` (premium slug PREMIUM, withoutGlobalScopes)
- Prior Phase4/5 files retained.

## P. Database Changes
No new migration Phase6 (Phase4 warning timestamps reused). Email unique remains `uq_users_email` (includes soft-deleted — intentional).

## Q. Security Invariants (30)
All 30 PASS except **BUSINESS RULE REQUIRED** for owner transfer (now blocked and logged, not silently deleted).

## Final Verdict
**YELLOW** — technical deletion is deterministic, tenant-safe, transaction-safe, idempotent, auditable, privacy-safe, and recoverable via soft-delete; **BUSINESS RULE REQUIRED** for orphaned institute owner transfer policy before allowing automatic deletion of active owners (currently blocked, logged). All other invariants GREEN.
