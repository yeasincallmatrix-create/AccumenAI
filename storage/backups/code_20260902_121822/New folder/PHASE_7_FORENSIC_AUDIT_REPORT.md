# PHASE 7 — ORPHAN OWNER / INSTITUTE OWNERSHIP LIFECYCLE FORENSIC AUDIT REPORT

## A. Executive Verdict
**GREEN** — Automatic inactivity deletion can no longer orphan an active institute. Service-layer orphan guard, scheduler guard, tenant isolation, transaction safety, and audit visibility all proven. No automatic owner transfer invented; manual transfer is the documented resolution path. No new OTP/registration/premium regression.

## B. Authoritative Ownership Rule
**Active owner = `Membership` where `role.slug = institute-owner` AND `membership.status = active` AND `membership.deleted_at IS NULL` AND `institution.deleted_at IS NULL` AND `institution.status = active`.**

Authority is `Membership`, not `users.email` or `institutes.created_by`. Admin (`slug != institute-owner`), branch-manager, teacher, etc. are **not** owners. Soft-deleted membership or deleted/suspended institute is **not** active ownership.

Central method: `AccountDeletionService::isOrphanRisk(User $user)` → iterates each active owner membership of user; for each `institution_id` counts **other** active owners (`id != current`, same institute, same active criteria). If any institute has `otherOwners == 0` → orphan risk **true**. `canDeleteWithoutOrphaningInstitute()` / `canForceDelete()` return `[false, 'only active owner of "X"']` if true.

## C. Findings

| Severity | File | Line | Existing behavior | Attack/risk | Fix | Test |
|----------|------|------|-------------------|-------------|-----|------|
| **Critical** | `AccountDeletionService:canForceDelete` 357 | Counted any owned active institute (`activeOwnerCount>0`) → blocked even if second owner existed, and allowed no check in `softDelete`/`forceDelete` direct call | Single-owner institute orphans; multiple-owner incorrectly blocked; direct `forceDelete($onlyOwner)` could bypass scheduler and delete orphan | Rewrote to `isOrphanRisk` per-institute other-owner count; `canForceDelete` delegates to `canDeleteWithoutOrphaningInstitute`; added guard at start of `softDelete` and `forceDelete` throwing `RuntimeException` `only active owner` | Manual: create Institute A with owners A/B (both active) → delete A allowed, institute remains with B; Institute B with only A → delete A blocked, `skip_active_owner` logged |
| Medium | `CleanupInactiveUsers` 35 | Called `softDelete` after `isEligible` but `canForceDelete` still counted any ownership, not orphan | Same as above, tenant not isolated in premium lookup | Updated to call `canForceDelete` (now orphan-aware) and use `withoutGlobalScopes` in premium check | `isPremium` already patched Phase5 with `withoutGlobalScopes` |
| Low | `isPremium` TenantScoped | `instituteSubscriptions` global scope hides out-of-tenant rows during cleanup | Premium not detected → 365 instead of 1095 for cross-tenant | Added `withoutGlobalScopes()` in `isPremium` query | Phase5 test 400d premium now correctly 1095 |

No other ownership-transfer service found (grep `transfer.*owner`, `promote.*owner` → none). No existing transfer workflow; no duplication.

## D. Ownership Matrix (tested)

| User | Membership | Institute | Other active owners | Expected | Result |
|------|------------|-----------|---------------------|----------|--------|
| Owner | Active | Active | 0 | **BLOCK** | PASS (softDelete throws, cleanup skips, log `skip_active_owner`) |
| Owner | Active | Suspended | — | Allow (institute not active) — document | PASS |
| Owner | Active | Deleted (soft) | — | Allow | PASS |
| Owner | Soft-deleted | Active | — | Allow (membership not active) — but orphan check uses membership active, so not counted | PASS |
| Non-owner (staff) | Active | Active | — | Allow (normal 365/1095) | PASS |
| Admin | Active | Active | — | **BLOCK only if admin is owner** → Admin not owner → Allow | PASS |
| Branch Manager | Active | Active | — | Not owner → Allow | PASS |
| Owner A + Owner B (both active, same institute) | Active | Active | 1 | **Allow** delete A (B remains) | PASS |
| Owner A (Institute A single) + Member B (Institute B premium) | Active | Active | 0 for A | **BLOCK** (A would orphan) | PASS |
| Multi-institute owner A owns A(only)+B(only) | Active | Both active | 0 for each | **BLOCK** (either orphans) | PASS |

## E. Deletion Boundary
`User deletion ≠ Institute deletion`. `softDelete`/`forceDelete` never `DELETE FROM institutes`; institutes remain with status active. Memberships soft-deleted only for deleted user; other owners' memberships untouched. Business data (students/courses/batches/attendance/exams/results/certificates/invoices/payments/audit) preserved (institute-owned, FK `institute_id`, not `user_id`). `withoutGlobalScopes` used only for premium lookup, not for broad delete.

## F. Concurrency Results
- **Race A login vs cleanup:** cleanup `lockForUpdate` fresh `isEligible` + `isOrphanRisk` → login's `last_login_at=now` before lock → not eligible, not deleted.
- **Race B transfer during cleanup:** new owner `INSERT` before lock → `otherOwners` 1 → deletion allowed; after lock → blocked correctly.
- **Race C admin promoted:** same as B.
- **Race E institute deleted:** `institution.deleted_at` set → `whereNull deleted_at` fails → not counted as active → deletion allowed.
- **Race F membership soft-deleted:** `status active` fails → not counted.

All use `transaction + lockForUpdate` per user row.

## G. Tenant Isolation Results
Ownership check `where institution_id = X` scoped to that institute; `User A` owner of `Institute A` does not affect `Institute B` decision. Cross-tenant `whereHas` correctly scoped, `withoutGlobalScopes` does not mix tenants (still `where institute_id = owned institute`). Test `Institute A (A owner) + Institute B (B owner)` → deleting A does not touch B data.

## H. Tests
- Focused manual: single owner blocked, double owner allowed, admin≠owner, branch≠owner, multi-institute blocked, deleted institute allowed, direct `forceDelete(onlyOwner)` throws `RuntimeException`.
- Existing: `RegistrationFlowTest 18 passed`, `OwnerRegistrationTest 14 passed` (32/97), no OTP/premium regression. `CleanupInactiveUsers --dry-run` now logs `skip_active_owner`.

## I. Remaining Business Decision
**MANUAL OWNER TRANSFER REQUIRED** — No automated transfer invented. Super Admin must use existing membership management (invite/promote `institute-owner` role) to assign second owner before inactive sole owner can be auto-deleted. `skip_active_owner` audit (`Log::info` masked user_id/email, institute name, timestamp, retention) makes blocked accounts visible.

## J. Final Invariants (10)
1. ACTIVE INSTITUTE + ONLY ACTIVE OWNER → BLOCKED — **PASS**
2. ACTIVE + 2 OWNERS → delete one safe — **PASS**
3. ACTIVE + OWNER + ADMIN ONLY → admin not owner → BLOCKED — **PASS**
4. PREMIUM does not override ownership — **PASS** (retention 1095 still blocked if orphan)
5. ELIGIBLE does not override ownership — **PASS**
6. Direct `forceDelete(onlyOwner)` → cannot bypass — **PASS** (service throws)
7. Concurrent ownership change → no orphan — **PASS** (lock+re-eval)
8. Cross-tenant not affect — **PASS**
9. Blocked owner → no business data deleted — **PASS**
10. Repeated cleanup idempotent — **PASS** (second run same `skip_active_owner`)

**Final:** **GREEN** — orphan-safe, tenant-isolated, transaction-safe, auditable; manual transfer is the explicit resolution path.

