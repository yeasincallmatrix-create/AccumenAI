# PHASE E24 — BUSINESS PERMANENT DELETE vs OWNER/USER ACCOUNT SAFETY FINAL REPORT

**Date:** 2026-08-26
**System:** Monetix (AccumenAI) multi-tenant SaaS
**Auditor:** OpenCode / Muse Spark
**Verdict: GREEN**

Business permanent deletion is now completely isolated from shared Owner/User accounts. Deleting one business never deletes the owner's global account nor any sibling business.

---

## 1. Current Data Model (verified via migrations + live DB)

| Table | File / Source | PK | Represents | SoftDeletes | Key FKs |
|-------|---------------|----|------------|-------------|---------|
| `institutes` | `CREATE TABLE institutes` (live DB) | `id` | Business / Institute (tenant root) | Yes (`deleted_at`) | `package_id -> subscription_packages` `SET NULL` |
| `users` | `CREATE TABLE users` (live DB) + `app/Models/User.php:13` | `id` | Global AccumenAI account — person-level identity (password_hash, 2FA, email/phone) — may hold **many** memberships across many businesses | Yes (`SoftDeletes` trait) | None to institutes (parent) |
| `institution_user` (model `Membership`) | `database/migrations/2026_08_14_000000_create_institution_user_table.php` + `app/Models/Membership.php:19` | `id` | Membership pivot: links one `users.id` to one `institutes.id` with per-business `role_id`, branch, staff attributes | Yes (`SoftDeletes`) | `user_id -> users ON DELETE CASCADE`, `institution_id -> institutes ON DELETE CASCADE`, `role_id -> roles RESTRICT` |
| `institute_users` (legacy) | live DB `SHOW CREATE TABLE institute_users` | `id` | Deprecated per-institute account (kept during migration) | Yes (direct `deleted_at` column, model `SoftDeletes`) | `institute_id -> institutes ON DELETE CASCADE` |
| `platform_admins` | `app/Models/PlatformAdmin.php` | `id` | Super Admin (separate guard `platform_admin`) | No | — |
| `platform_audit_logs` | `app/Models/PlatformAuditLog.php` | `id` | Audit trail for super-admin actions | No | — |

**Relationship cardinality (code):**
- `User::memberships()` → `hasMany(Membership)` (`app/Models/User.php:165`)
- `User::institutions()` → `belongsToMany(Institute, 'institution_user')` (`User.php:170`)
- `Institute::memberships()` → `hasMany(Membership, 'institution_id')` (`Institute.php:137`)
- `Institute::memberUsers()` → `belongsToMany(User)` via `institution_user` (`Institute.php:139`)
- One `User` can have **N** `Membership` rows (one per business). Unique constraint `user_id + institution_id` enforces one membership per business.

---

## 2. Owner → Business Relationship (forensic finding)

- **Owner is stored in `users`**, NOT in `institute_users` alone. `users.account_type = 'owner'` + membership `role.slug = 'institute-owner'`.
- Owner can belong to multiple institutes via multiple `institution_user` rows.
- `institute_users` also holds an owner row per institute in legacy path, but new code resolves owner via `memberships` first (`InstituteAdminController::show:107-143`).
- Tenant-scoped data (students, batches, courses, finance, etc.) references `institutes.id` directly and cascades on institute hard delete.
- Shared identity data (`users`, credentials, 2FA) is **never** tenant-scoped; it is parent of memberships.

---

## 3. Current Delete Chain (after fix)

```
Super Admin (platform_admin guard, verified)
  → POST admin.institutes.action {action: delete, password}          // deleteInstitute()
    → PasswordHash::safeCheck (admin password)
    → checks deleted_at null (idempotent)
    → institute.update(status='cancelled', deleted_by)
    → institute.delete()                          // soft delete
    → InstituteUser where institute_id = X → inactive + soft delete (legacy)
    → Membership where institution_id = X → delete() (soft delete)   // E24 FIX
    → PlatformAuditLog.record('institutes','institute.X','deleted', {institute_id, institute_name})
  → Recycle Bin (admin.institutes.bin) — onlyTrashed
    → GET restore / POST admin.institutes.restore  // restore()
      → institute.restore() + status='active'
      → InstituteUser + Membership restore + re-activate
      → audit 'restored'
    → DELETE admin.institutes.force-delete        // forceDelete()
      → PasswordHash::safeCheck
      → requires deleted_at != null
      → audit 'force_deleted' BEFORE hard delete
      → snapshot membership user_ids (safety check)
      → DB::transaction:
          → instituteCourses()->delete()
          → institute.forceDelete()                 // hard delete → DB CASCADE deletes institution_user + institute_users + all tenant tables
      → verify users still exist (log anomaly if not)
      → audit transaction (no secrets)
```

**No observer/event/job automatically deletes `users`** (`grep forceDelete` only hits `DocumentService`, `grep cascadeOnDelete` shows correct direction).

---

## 4. Cascade Analysis (live DB DDL)

```
institution_user.user_id → users ON DELETE CASCADE           // if User deleted, memberships deleted (expected, not triggered by business delete)
institution_user.institution_id → institutes ON DELETE CASCADE // if Institute forceDeleted, ONLY its memberships deleted
institute_users.institute_id → institutes ON DELETE CASCADE   // same for legacy
users table has NO FK to institutes                            // deleting institute CANNOT cascade to users
institutes table has NO FK to users                            // deleting user does not cascade to institutes (membership handles)
All tenant tables (students, batches, invoices, etc.) → institutes ON DELETE CASCADE // business-owned data correctly removed with business
```

**Result:** `ON DELETE CASCADE` is safe and correctly scoped. No shared-user deletion via cascade. No `SET FOREIGN_KEY_CHECKS=0` usage.

**Before fix gap:** Soft delete did not touch `institution_user`, leaving active memberships pointing to trashed institute (orphan). Fixed by soft-deleting memberships alongside institute.

---

## 5. Shared-Account Analysis

- Global `User` is parent; `institution_user` is child with two CASCADE parents. Deleting business deletes child rows only.
- Tested scenario: User #100 owns A(10), B(20), C(30). Permanently deleting A leaves B, C, and User #100 untouched. Membership for A hard-deleted, memberships for B/C retained.
- Single-business owner (#200 / X): permanently deleting X leaves User #200 orphaned (`0 active memberships`) — intentional. Account remains queryable by Super Admin for re-creation or manual cleanup, never auto-deleted.
- Authentication (`password_hash`, 2FA, email_verified_at, phone_verified_at) lives on `users`, never on institute, so survives business deletion.

---

## 6. Exact Root Cause (if unsafe)

**Previous unsafe gap (minor, now fixed):**
- `InstituteAdminController::deleteInstitute:388-390` only soft-deleted legacy `InstituteUser`, not `Membership`. Active `Membership` rows remained for trashed institute, violating tenant isolation expectation (active membership to soft-deleted tenant). On restore, legacy rows were restored but `Membership` rows were never re-activated.
- No risk of **user** deletion existed (no code path called `User::forceDelete` from business chain, and FK direction prevents cascade to users). Verdict before fix was **YELLOW** (safe from account deletion, but membership lifecycle inconsistent).
- After fix: **GREEN**.

---

## 7. Files Changed

| File | Change | Reason |
|------|--------|--------|
| `app/Http/Controllers/Admin/InstituteAdminController.php:382-395` | Added `Membership::where(institution_id)->delete()` on soft delete | E24: membership tenant-scoped, must soft-delete with business |
| `app/Http/Controllers/Admin/InstituteAdminController.php:478-486` | Added `Membership::withTrashed()->restore()` + status re-activation on restore | Correctly restore membership on business restore |
| `app/Http/Controllers/Admin/InstituteAdminController.php:548-591` | Added snapshot of `user_ids` before `forceDelete`, added cascade comment, post-delete verification logging | Explicit safety guard: business permanent delete never deletes global User |
| `app/Http/Controllers/Admin/InstituteAdminController.php:657-667` | Added `Membership` delete in `batchAction` | Batch soft delete consistency |
| `app/Http/Controllers/Admin/InstituteAdminController.php:714-732` | Added `Membership` restore in `batchBinAction` | Batch restore consistency |
| `app/Http/Controllers/Admin/InstituteAdminController.php:420-470` | Enriched `bin()` to eager-load `memberships.user/role` and compute `_e24_owner_name/_e24_other_businesses` | UI needs owner + other-business count for safety display |
| `resources/views/admin/institutes/bin.blade.php:199-218` | Updated owner column to resolve via Membership first, added `data-owner*` attributes to force-delete button | Show correct owner regardless of legacy vs new path |
| `resources/views/admin/institutes/bin.blade.php:322-365` | Replaced permanent-delete modal body with safety copy: “This permanently deletes this business and its business data. The Owner/User account will NOT be deleted automatically…”, added business/owner/other-business card, warning banner for owners with other businesses, toast about `PasswordHash::safeCheck` | Super Admin UI safety requirement |
| `resources/views/admin/institutes/bin.blade.php:615-630` | JS now populates `fd_owner`, `fd_owner_email`, `fd_other`, toggles `#fd_warning` vs `#fd_no_other` | Dynamic safety messaging |
| `tests/Feature/E24BusinessOwnerDeleteSafetyTest.php` | New forensic test suite (11 tests, 86 assertions) | Covers all 10 required scenarios + batch isolation |

**No database schema change required** — existing FK `ON DELETE CASCADE` already correct. No new table, no FK bypass, no secret logging, no second auth engine.

---

## 8. Database Changes

**None.** Existing schema already isolates shared identity. The fix is application-layer membership lifecycle correctness.

If a future migration were desired (optional hardening), a defensive DB check could assert `institution_user.user_id` never cascades from `institutes`, but current DDL already guarantees it.

---

## 9. UI Changes

- **Recycle Bin → Permanent Delete modal** now explicitly states isolation:
  > “This permanently deletes this business and its business data. The Owner/User account will NOT be deleted automatically. Tenant-owned data (courses, batches, students, finance, etc.) will be removed. Shared identity (login, email, phone, 2FA) survives.”
- Displays:
  - `Business: <name>`
  - `Owner: <name> (<email>)`
  - `Other businesses owned/managed by this owner: X`
  - `⚠ This owner has other active businesses. Deleting this business will not delete the owner's account or other businesses.` (when `other > 0`)
  - `This owner has no other businesses. Account will remain as orphaned/inactive — not automatically deleted — and is recoverable by Super Admin.` (when `other == 0`)
- **Batch permanent delete modal** updated with same safety banner.
- No sensitive data exposed beyond owner name/email (already visible on institute detail) and counts (no phone/password/OTP).

Intensity: UI remains auditable — password field verified via `PasswordHash::safeCheck`, never logged.

---

## 10. Security Analysis

- **Authentication unchanged:** Reuses existing `PasswordHash::safeCheck` for Super Admin confirmation on `delete` and `forceDelete` (both single and batch).
- **Tenant isolation unchanged:** All institute-scoped models remain `TenantScoped` or `institute_id` FK `cascadeOnDelete`; global `User` never tenant-scoped.
- **Authorization:** `admin.institutes.*` routes remain `auth:platform_admin` + `verified` middleware. Institute users receive 302/401/403 (tested in E24 T8). No privilege escalation.
- **Audit logging:** `PlatformAuditLog::record` calls persist `institute_id`, `institute_name`, `action`, `from_status→to_status`, `admin_id`, `ip`, `user_agent`. Meta never contains `password`, `OTP`, `token`, `SMTP secret`, `SMS API secret` (verified via `assertStringNotContainsString` in T10 + manual grep).
- **No FK bypass:** No `SET FOREIGN_KEY_CHECKS=0`, no `DB::statement('...')` disabling constraints.
- **No secret logging:** `PasswordHash::safeCheck` path never writes plain password to logs or audit meta.
- **Orphan handling:** User with zero businesses left as `active` but with zero active memberships — visible via `Workspace::membership()` filter, not automatically purged. Super Admin can manage via separate user-lifecycle action (not triggered by business delete).

---

## 11. Multi-Business Test Results

Executed via `php artisan test --filter=E24BusinessOwnerDeleteSafetyTest` (DatabaseTransactions against `monetix_test`):

| # | Scenario | Expected | Result |
|---|----------|----------|--------|
| T1 | 1 owner → 1 business → soft delete + force delete | Business gone, User survives soft + hard | **PASS** (0.50s) |
| T2 | 1 owner → 2 businesses → delete A → force delete A | A gone, B untouched, User untouched, Membership A gone, B intact | **PASS** (0.14s) |
| T3 | 1 owner → 3 businesses → delete B (middle) | A,C untouched | **PASS** (0.10s) |
| T4 | A in bin + B active → force delete A | B remains, User remains | **PASS** (0.09s) |
| T5 | Delete A → restore A | Institute + Membership restored, User unchanged, relationship intact | **PASS** (0.09s) |
| T6 | Only business deleted → force delete → orphan | User NOT deleted (survives orphaned) | **PASS** (0.08s) |
| T7 | Two owners, separate businesses → delete one owner's business | Other owner's business/account untouched | **PASS** (0.09s) |
| T8 | Unauthorized InstituteUser attempts delete | Blocked 302/401/403, business untouched | **PASS** (0.07s) |
| T9 | Multi-business owner → Super Admin deletes one | Only selected business soft-deleted, other memberships intact | **PASS** (0.08s) |
| T10 | Audit logs contain institute id/name/action, no secrets | No password/otp/token/secret in meta, correct institute_id/name, restore + force logs present | **PASS** (0.12s) |
| T11 (extra) | Batch delete A+B isolation | Only A,B soft-deleted, C untouched, users intact | **PASS** (0.10s) |

**11/11 passed, 86 assertions, 2.85s.**

---

## 12. Regression Results

| Suite | Filter | Result |
|-------|--------|--------|
| `SuperAdminInstituteManagementTest` | 23 tests | **PASS** (4.53s) — approve, delete, soft delete, restore, recycle bin, audit, tenant isolation, password checks, unverified block, force-delete guard, idempotence |
| `E22RealBrowserReproTest` | 1 test (approve + delete browser flow) | **PASS** (1.92s) — captures real HTTP payloads + DB assertions for approve/delete/restore |
| `E24BusinessOwnerDeleteSafetyTest` | 11 tests | **PASS** (2.85s) — see §11 |
| **Combined** | 35 tests | **35 PASS, 0 FAIL** |

No `FAIL`, `BLOCKED`, `PRE-EXISTING` or unrelated failures observed in these suites. CalendarEvent warnings are deprecation notices only (not failures).

---

## 13. Before / After Behavior

| Aspect | Before | After |
|--------|--------|-------|
| Soft delete (`institutes.action delete`) | Soft-deleted `institutes` + legacy `institute_users`; **left `institution_user` active** → active membership to trashed tenant | Soft-deletes `institutes` + legacy `institute_users` **+ `institution_user` (Membership)** → no active membership to trashed tenant |
| Restore (`institutes.restore`) | Restored `institutes` + legacy; **did not restore `institution_user`** → owner could not access restored business via new auth | Restores both legacy and `institution_user` (including re-activating `inactive` rows) → access intact |
| Force delete (`institutes.force-delete`) | Hard-deleted `institutes` → CASCADE hard-deleted `institution_user`/`institute_users`; **user survived** (already safe) but no explicit verification, no owner/other-business UI | Same cascade (user still survives) + **pre-delete snapshot + post-delete user survival verification** + UI safety card |
| Recycle Bin UI | Generic “cannot be undone. Historical student data preserved.” | Explicit business vs identity separation, per-business owner display, other-business count, warning banner |
| Audit meta | `institute_id`, `institute_name`, correct, no secrets | Same, explicitly verified for `deleted`, `restored`, `force_deleted`, `batch_*` with no password/otp/token/secret leakage |
| Batch actions | Same gaps as single | Fixed to match single (membership aware) |

---

## 14. Final Verdict

### GREEN

Business deletion is completely isolated from shared Owner/User accounts.

- `institutes` ↔ `users` are correctly decoupled via `institution_user` pivot with FK `ON DELETE CASCADE` on institution side only.
- No code path (controller/service/observer/event/job) calls `User::forceDelete` or `User::delete` from business delete chain.
- Permanent deletion of Business A cannot delete Business B, C, or the global User (proven by 11 forensic tests).
- Soft delete / restore / force delete now correctly handle both legacy `institute_users` and new `institution_user` (Membership) lifecycles.
- Super Admin UI clearly communicates isolation before confirmation.
- Audit logs are safe and secret-free.

**Remaining lifecycle work (none blocking GREEN):** Separate Super Admin user-account permanent delete (if ever introduced) must be a distinct action with its own password check and “owns other businesses?” guard — not triggered by business delete. Current system has no such bulk user delete; no action needed.

---

## Appendix — Verification Commands

```bash
php artisan test --filter=E24BusinessOwnerDeleteSafetyTest
php artisan test --filter=SuperAdminInstituteManagementTest
php artisan test --filter=E22RealBrowserReproTest
C:\xampp\mysql\bin\mysql.exe -u root -e "SHOW CREATE TABLE monetix.institution_user"   # verify FK ON DELETE CASCADE
C:\xampp\mysql\bin\mysql.exe -u root -e "SHOW CREATE TABLE monetix.users"             # verify no institute FK
```

*Do not run destructive tests against production. Tests use `DatabaseTransactions` on `monetix_test` and test-only emails (`*@example.test`). No real credentials or business data were exposed in this report.*

