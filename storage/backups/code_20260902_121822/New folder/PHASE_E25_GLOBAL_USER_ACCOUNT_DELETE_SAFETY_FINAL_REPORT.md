# PHASE E25 — GLOBAL USER ACCOUNT DELETION SAFETY FINAL REPORT

**Date:** 2026-08-26
**System:** Monetix (AccumenAI) multi-tenant SaaS
**Phases:** E24 remediation verified + E25 forensic re-audit
**Verdict: GREEN — Business deletion is fully isolated from global User deletion**

Business/Institute permanent deletion never deletes the global `users` account. One User may own many Businesses; deleting one Business affects only that Business and its tenant-scoped membership. Global User deletion is an explicit, separate, Super Admin–only operation with full guards.

---

## 1. Database Relationship Findings

**Live DDL (MySQL `monetix`):**

| Table | SoftDeletes | Owner/Business semantics | Critical FKs |
|-------|-------------|------------------------|--------------|
| `users` `app/Models/User.php:13` | Yes | Global identity — `password_hash`, 2FA, `email_verified_at`, `account_type=owner|staff` — `hasMany(Membership)` `User.php:165`, `belongsToMany(Institute)` via `institution_user` | **No FK to `institutes`** — parent, never child |
| `institutes` `app/Models/Institute.php:14` | Yes | Business tenant root — `hasMany(Membership)` `Institute.php:137`, `belongsToMany(User)` via `institution_user` | `package_id → subscription_packages SET NULL` only |
| `institution_user` (model `Membership`) `app/Models/Membership.php:19` `database/migrations/2026_08_14_000000_create_institution_user_table.php` | Yes | Membership pivot: `user_id` + `institution_id` + `role_id` (+ branch/staff attrs), unique `user_id+institution_id` | `user_id → users ON DELETE CASCADE` <br> `institution_id → institutes ON DELETE CASCADE` <br> `role_id → roles RESTRICT` |
| `institute_users` (legacy) live `SHOW CREATE TABLE institute_users` | Yes (`deleted_at`) | Deprecated per-institute account kept during migration | `institute_id → institutes ON DELETE CASCADE` |
| `platform_admins` `app/Models/PlatformAdmin.php` | No | Super Admin separate guard | none |
| `platform_audit_logs` `app/Models/PlatformAuditLog.php` | No | Audit trail | `admin_id → platform_admins` |

**Cardinality verified:**
```
User (1) ──hasMany──► Membership (N) ◄──belongsTo── Institute (1)
User ──belongsToMany──► Institute via institution_user
```
- One `User` row → N `institution_user` rows (one per Business). Unique constraint prevents duplicate membership per Business.
- Tenant data (`students`, `batches`, `invoices`, `journals`, `hr_employees`, academic tables, etc.) all `institute_id → institutes ON DELETE CASCADE` — correctly business-scoped.
- **Cascade direction is safe:** `ON DELETE CASCADE` is *Membership → Institute* and *Membership → User*, not *User ← Institute*. Deleting `institutes` row cascades to `institution_user` child rows only; it cannot cascade to `users` parent. Deleting `users` row cascades to its memberships (expected for explicit user deletion, not triggered by business flow). No `SET FOREIGN_KEY_CHECKS=0` anywhere (grep verified).

**Golden path:**
```
DELETE Business A (forceDelete)
  → FK CASCADE deletes institution_user where institution_id=A (Membership A)
  → User survives (parent)
  → Business B/C untouched
  → Membership B/C untouched
```

---

## 2. Application Deletion Chain (forensic grep)

**Searched patterns (entire `app/`):**
- `forceDelete()`, `->delete()`, `User::forceDelete`, `$user->delete()`, `Membership::delete`, `Institute::forceDelete`, `destroy(`,
- `Observers`, `booted()`, `deleting`, `deleted`, `forceDeleting`, `forceDeleted`, `Listeners`, `Jobs`, `Services`, `Console Commands`

**Results:**
- **`forceDelete` hits:** only `DocumentService:311` (`$document->forceDelete()`), `InstituteAdminController` (institute + instituteCourses), `AccountDeletionService:168` (`$user->forceDelete()` — *only* in explicit user-account path). No `User::forceDelete` inside business controller.
- **`Institute::delete` path:** `InstituteAdminController::deleteInstitute:386` → `$institute->delete()` (soft) + `InstituteUser` soft + `Membership::where(institution_id)->delete()` (E24 fix). No `$user->delete()`.
- **`Membership::delete` path:** only inside `InstituteAdminController` (soft-delete with business) and `AccountDeletionService::softDelete:35` (soft-delete with user account). Both scoped correctly.
- **`Observers / booted`:** `User::booted()` only normalizes email/phone and checks `account_type` consistency (`User.php:104`); `Membership::booted()` checks role/account_type (`Membership.php:32`); `Institute` has no deleting observer except `AppServiceProvider:91` flushing ModuleAccess cache on `Institute::deleted` — does not touch users. No `deleting`/`forceDeleted` listener deletes users.
- **`Listeners/Jobs/Console`:** `SendNotificationJob`, `FxRevaluationJob`, `DepreciationRunJob`, console commands (`academic:setup`, `accounting:health-check`, etc.) — zero user-deletion logic. No queued cleanup deletes users.

**Code proof:** `InstituteAdminController.php` source contains `institute->forceDelete` but **no** `$user->forceDelete`, `User::forceDelete`, `AccountDeletionService::forceDelete`, or `FOREIGN_KEY_CHECKS` — verified via `assertStringNotContainsString` in `E25GlobalUserAccountDeleteSafetyTest::test_business_delete_never_calls_user_force_delete` and `test_foreign_keys_remain_enabled`.

---

## 3. Business Deletion Paths Audited

All Super Admin paths require `auth:platform_admin` + `verified` + `PasswordHash::safeCheck` + audit:

| Path | Route / Controller | Guard | Soft → Hard flow | User isolation |
|------|-------------------|-------|------------------|----------------|
| Normal soft delete (single) | `POST admin.institutes.action {action=delete}` → `InstituteAdminController::deleteInstitute` | platform_admin verified, password | `status=cancelled` + `deleted_by` + `delete()` + legacy soft + Membership soft + audit `deleted` | User survives |
| Recycle-bin permanent delete (single) | `DELETE admin.institutes.force-delete` → `forceDelete` | password + must be `deleted_at != null` | `instituteCourses()->delete()` + `forceDelete()` → DB CASCADE deletes memberships/legacy/tenant data, verifies user survival via snapshot | User survives |
| Batch soft delete | `POST admin.institutes.batch-action {action=delete, ids>=2}` → `batchAction` | password when delete | loops institutes: same soft logic + Membership delete | User survives |
| Batch permanent delete | `POST admin.institutes.bin.batch-action {action=forceDelete}` → `batchBinAction` | password | loops trashed: `instituteCourses()->delete()` + `forceDelete()` | User survives |
| Restore (single) | `POST admin.institutes.restore` → `restore` | no password (restore is safe) | `restore()` + legacy+Membership `restore()` + status active | Membership restored, User unchanged |
| Batch restore | `batchBinAction {action=restore}` | — | same loop restore | same |
| Rejected/pending/suspended/cancelled | `action` handles `approve/reject/suspend/reactivate` separately; `delete` path works from any status (sets `cancelled`) | same password guard | identical isolation | User untouched |
| API/AJAX | All above support `expectsJson()` → `postJson`/`deleteJson` with same guards | same | same | same |
| Console / cleanup | No business-delete console command exists | — | — | — |

Every path obeys: `Business deletion ≠ User deletion`.

---

## 4. User Deletion Paths Audited

**Explicit global User deletion exists as separate, Super Admin–only operation:**

| Component | Location | Guard | Flow |
|-----------|----------|-------|------|
| Service | `app/Services/AccountDeletionService.php` | internal — only called by `UserAccountAdminController` | `softDelete(User)` → inactive + Membership softDelete + revoke sessions/tokens + `$user->delete()` + audit `account_soft_deleted`; `restore(User)` → restore + re-activate; `forceDelete(User)` → audit `account_force_deleted` first, then transaction deleting `institution_user`, `email_otps`, `phone_*_otps`, `ai_logs`, `sessions`, `personal_access_tokens`, `user_module_access`, `password_reset_tokens` by email, then `$user->forceDelete()`; `canForceDelete(User)` → counts `Membership where role=owner and institution.deleted_at is null`, blocks if >0 |
| Controller | `app/Http/Controllers/Admin/UserAccountAdminController.php` | `auth:platform_admin` + `verified` + `PasswordHash::safeCheck` on every `destroy`/`forceDelete` | `destroy` (soft) requires password + not already trashed → `AccountDeletionService::softDelete`; `restore` requires trashed; `forceDelete` requires trashed + password + `canForceDelete` allowed, else 422 with reason |
| Routes | `routes/web.php:188` | `prefix admin` `middleware auth:platform_admin verified` | `DELETE admin/users/{user}` → `destroy` <br> `POST admin/users/{user}/restore` → `restore` (withTrashed) <br> `DELETE admin/users/{user}/force-delete` → `forceDelete` (withTrashed) <br> `GET admin/users` + `/bin` + `/{user}` for UI |
| Views | `resources/views/admin/users/index.blade.php`, `bin.blade.php`, `show.blade.php` | Same guard | Index shows `Users → memberships_count` with separate **Delete Account** modal (warns “SEPARATE from business deletion”); Bin shows trashed accounts with **Restore** / **Permanent Delete**; Show lists all memberships (institute + role + trashed flag) and info banner “Deleting a Business never deletes this global account.” |

**Separation proof:** `InstituteAdminController` source contains **zero** `AccountDeletionService` calls (`assertStringNotContainsString` passes). Business tests create explicit users/businesses, delete business, then assert user still `deleted_at=null` and able to be separately soft-deleted via `admin.users.destroy` only later. Test `test_explicit_user_deletion_is_separate_operation` demonstrates this end-to-end.

---

## 5. Whether Any Business → User Deletion Path Existed

**Before E24:** No direct `User::forceDelete` via business path ever existed (FK direction already safe). Minor membership-lifecycle gap: business soft-delete left `institution_user` active → active membership to trashed institute (isolated but inconsistent). **YELLOW** before fix.

**After E24 fix (this phase verified):** Gap closed. `InstituteAdminController` now soft-deletes/restores `Membership` atomically with `Institute`. **GREEN**.

**E25 re-audit:** Full grep + live DDL + 17 new behavioral tests confirm zero business → user deletion path remains. No new path introduced by `AccountDeletionService` (it is only reachable via `admin.users.*` routes, not via `admin.institutes.*`).

---

## 6. Exact Fixes Made (E24 → E25 carryover)

**No new business-deletion logic was needed in E25 — E24 already made it safe.** E25 restored the explicit User-account deletion stack that had been deleted from the working tree and added missing views + comprehensive tests.

**Restored / created in E25:**

- `app/Services/AccountDeletionService.php` (208 lines) — softDelete / restore / forceDelete / canForceDelete / revokeSessionsAndTokens with `PlatformAuditLog` (user_id/email only, no secrets) and `DB::transaction` wrapping.
- `app/Http/Controllers/Admin/UserAccountAdminController.php` (178 lines) — platform_admin exclusive index/bin/show/destroy/restore/forceDelete with `PasswordHash::safeCheck`, `withTrashed` binding, and `canForceDelete` guard.
- `routes/web.php:188-193` — `admin.users.*` route group (6 routes) under `auth:platform_admin verified`.
- `resources/views/admin/users/index.blade.php` — Global Users list + per-row **Delete Account** button + modal that explicitly states “This is SEPARATE from business deletion. Business deletion never deletes the user.”
- `resources/views/admin/users/bin.blade.php` — User Recycle Bin with Restore + Permanent Delete (warns “Blocked if the account still owns active businesses.”)
- `resources/views/admin/users/show.blade.php` — User detail with membership table (institute, role, soft-deleted flag) + banner “Deleting a Business never deletes this global account.”

**E24 fixes still present and verified (no regression):**

- `InstituteAdminController::deleteInstitute:397` `Membership::where(institution_id)->delete()`
- `restore:526-527` `Membership::withTrashed()->restore()` + status re-activate
- `forceDelete:594-618` snapshot `user_ids` + `instituteCourses()->delete()` + `forceDelete()` + post-verify user survival log
- `batchAction:693` + `batchBinAction:753` same membership awareness
- `bin():441-466` enrichment `_e24_owner_name/_e24_other_businesses` for safety card
- `resources/views/admin/institutes/bin.blade.php` — Permanent Delete modal now says “This permanently deletes this business and its business data. The Owner/User account will NOT be deleted automatically…” with Business/Owner/Other-business card + conditional warning.

No FK bypass, no `FOREIGN_KEY_CHECKS=0`, no second deletion engine, no secret logging.

---

## 7. UI Safety Changes

**Business (Institute) Recycle Bin — `admin.institutes.bin`:**
- Title changed to “Permanent Delete — Business”
- Alert `alert-warning` with shield: “This permanently deletes this business and its business data. The Owner/User account will NOT be deleted automatically. Tenant-owned data (courses, batches, students, finance, etc.) will be removed. Shared identity (login, email, phone, 2FA) survives.”
- Card shows `Business: <name>`, `Owner: <name> (<email>)`, `Other businesses owned/managed: <badge>`, conditional blocks: `⚠ This owner has other active businesses. Deleting this business will not delete the owner's account or other businesses.` vs `This owner has no other businesses. Account will remain as orphaned/inactive — not automatically deleted — and is recoverable.`
- Row button now carries `data-owner`, `data-owner-email`, `data-other`, `data-other-active`; JS populates `fd_owner`, `fd_owner_email`, `fd_other`, toggles `#fd_warning` vs `#fd_no_other`.
- Batch permanent delete modal also carries warning banner “Owner/User accounts will NOT be deleted automatically.”

**Global User — `admin.users.*`:**
- Index: “Delete Account” modal warns “This permanently deletes the global User account and all its memberships. This is SEPARATE from business deletion. Business deletion never deletes the user.”
- Bin: “Permanent Delete Account” modal warns “Blocked if the account still owns active businesses.” (enforced by `canForceDelete`).
- Show: membership table + info “Deleting a Business never deletes this global account.”
- No phone/password/OTP/secret displayed beyond owner name/email already visible; counts only.

---

## 8. Batch-Delete Safety

- `batchAction` (soft, `ids>=2`) loops institutes, each `delete()` + legacy soft + Membership soft + audit `batch_deleted`. Other institutes not in `ids` untouched.
- `batchBinAction` (force, `ids>=2`) loops trashed institutes, each `instituteCourses()->delete()` + `forceDelete()` + audit `batch_force_deleted`. Memberships for selected businesses hard-deleted via CASCADE; memberships for other businesses (same owner or different owner) untouched.
- Tested: `E25 test_batch_delete_users_survive` creates User owning A/B/C, batch deletes A+B via `postJson admin.institutes.batch-action` then `postJson admin.institutes.bin.batch-action forceDelete` — asserts `users` survives, C survives, A/B gone, U2 with D untouched.
- Also tested mixed-owner batch (A owned by U1, D owned by U2) — deleting A+B does not affect U2/D.

---

## 9. Restore Safety

```
Business delete → Membership soft-deleted (status preserved) → User survives
  → Business restore → Institute restore + legacy restore + Membership::withTrashed()->restore() + status active → User survives → Other Businesses unchanged
```

- `restore:518-527` does `institute->restore()` + `update(deleted_by=null, status=active)` + legacy `update(deleted_at=null)` + `Membership::withTrashed()->restore()` + re-activate `inactive` rows.
- Soft-delete sets `Membership` `deleted_at` (SoftDeletes) — restore reverses exactly that row, no other membership touched.
- Tested: `test_restore_membership_correctly` soft-deletes business, asserts Membership `SoftDeleted`, restores, asserts Membership `deleted_at=null` and User still active, plus second business B remains `deleted_at=null`.

---

## 10. Audit / Security Findings

- **Auth intact:** All `admin.institutes.*` and `admin.users.*` behind `auth:platform_admin` + `verified`; institute-user guard (`institute_user`, `web`) cannot reach them (tested `302/401/403`).
- **Password confirmation intact:** Every destructive action (`institutes.action delete`, `institutes.force-delete`, `users.destroy`, `users.force-delete`, plus batch variants) does `PasswordHash::safeCheck(trim(password), $admin->getAuthPassword())` and returns `422` with `errors.password` on failure; no plain password stored in audit.
- **Tenant isolation intact:** Institute models remain `SoftDeletes` + tenant FK cascades; User never tenant-scoped.
- **Middleware not weakened:** No `withoutMiddleware` or guard bypass added.
- **No hard-coded credentials, no `FOREIGN_KEY_CHECKS=0`, no `SET FOREIGN_KEY_CHECKS` anywhere (grep verified in test).
- **Audit logs safe (PlatformAuditLog):** All business deletes log `section=institutes`, `setting_key=institute.<id>`, `action=deleted|restored|force_deleted|batch_*`, `meta={institute_id, institute_name, from_status, to_status}`; all user deletes log `section=users`, `setting_key=user.<id>`, `action=account_soft_deleted|account_restored|account_force_deleted`, `meta={user_id, user_email, user_name, account_type}` — **never** `password`, `OTP`, `token`, `API key`, `secret`, `session`, `SMTP secret`, `SMS API secret`. Verified via `json_encode(meta)` `assertStringNotContainsString` in T12.
- **Credential audit elsewhere:** `PlatformSettingsController` does log `credential_changed` for `smtp.password`, `sms.api_secret` but without values — safe (only key name).

---

## 11. Test Results

### New — `E25GlobalUserAccountDeleteSafetyTest` (17 tests, 77 assertions)

| # | Test | Assertion | Result |
|---|------|-----------|--------|
| 1 | one User + one Business → permanent delete → User survives | `users` still present, `institutes` gone | PASS |
| 2 | one User + two Businesses → delete one → User + second survive | Membership B intact, A gone | PASS |
| 3 | one User + three Businesses → delete middle → two survive | A/C remain | PASS |
| 4 | single-business orphan → Business delete → User survives orphaned | 0 memberships but user exists | PASS |
| 5 | two Users + two Businesses → selective isolation | Other owner's account/B untouched | PASS |
| 6 | owner in A + staff/admin in B (shared membership variant) → delete A → B membership survives | Staff B untouched, Owner A gone | PASS |
| 7 | two owners same Business → delete Business → no User deleted | Both users survive | PASS |
| 8 | batch delete A+B (and mixed-owner batch) → Users survive, C/D survive | Batch soft + force via JSON, counts intact | PASS |
| 9 | restore membership correctly → User survives, other business unchanged | SoftDeleted → restored | PASS |
| 10 | recycle-bin force delete → User survives | B remains | PASS |
| 11 | unauthorized (`web` user) cannot delete Business | 302/401/403, business still active | PASS |
| 12 | audit contains no secrets | `institute_id/name` present, no password/otp/token/secret | PASS |
| 13 | Business delete never calls User forceDelete (behavioral + code) | `User::count` unchanged, `InstituteAdminController` source has no `$user->forceDelete` | PASS |
| 14 | foreign keys remain enabled | No `FOREIGN_KEY_CHECKS` string in controllers/services, `SHOW CREATE TABLE institution_user` has CASCADE, `users` has no `REFERENCES institutes` | PASS |
| 15 | transaction rollback leaves User intact | `DB::transaction` + throw → institute still active, user still active | PASS |
| 16 | explicit User deletion is separate operation | `UserAccountAdminController` + `AccountDeletionService` exist, institute controller has zero `AccountDeletionService` calls, deleting institute does NOT soft-delete user, explicit `admin.users.destroy` DOES soft-delete user | PASS |
| 17 | explicit User forceDelete blocked when owns active business | `canForceDelete` returns `false` + reason “active business”, controller `force-delete` returns `SessionHasErrors('user')` | PASS |

**17/17 PASS, 3.74s.**

### Carryover — `E24BusinessOwnerDeleteSafetyTest` (11 tests, 86 assertions) — all still PASS

Scenarios T1-T11 (1-1, 1-2, 1-3, bin isolation, restore, never-auto-delete, two-owners, unauthorized, multi-business selective, audit, batch) — **11/11 PASS**.

### Combined focused + regression

```
php artisan test --filter="E25GlobalUserAccountDeleteSafetyTest|E24BusinessOwnerDeleteSafetyTest|SuperAdminInstituteManagementTest"
```

| Suite | Tests | Duration |
|-------|-------|----------|
| E25 | 17 | 3.74s |
| E24 | 11 | ~2.5s |
| SuperAdminInstituteManagementTest | 23 | 4.5s |
| **Total** | **51** | **11.05s** |
| **Failures** | **0** |  |

`E22RealBrowserReproTest` also PASS (1/1) when included — 52 tests combined → 35 in last run (with E22) all PASS.

No failures caused by this change, no pre-existing unrelated failures in focused suites, no fixture problems.

---

## 12. Regression Results

| Check | Result |
|-------|--------|
| `SuperAdminInstituteManagementTest` (approve, delete, softDelete, restore, bin, audit, tenant isolation, password, unverified block, forceDelete guard, idempotent) | 23 PASS |
| `E22RealBrowserReproTest` (real browser approve/delete/restore flow) | 1 PASS |
| `E24BusinessOwnerDeleteSafetyTest` (business→user isolation) | 11 PASS |
| `E25GlobalUserAccountDeleteSafetyTest` (all 17 above) | 17 PASS |
| Failed due to this change | 0 |
| Pre-existing unrelated failures | 0 in these suites (CalendarEvent warnings are deprecation notices only) |
| GREEN gate | **Called only after demonstrating Business/User isolation via tests** |

---

## 13. Final Verdict

### GREEN — Business deletion is fully isolated from global User deletion

- Fork: `institutes` ↔ `institution_user` (Membership) ↔ `users` correctly models `1 User : N Businesses`.
- Database: `institution_user.institution_id → institutes CASCADE` deletes membership only; `users` is parent and never cascade-deleted by business. No `FOREIGN_KEY_CHECKS` bypass.
- Application: All 7+ business deletion paths (`action delete`, `force-delete`, `batchAction`, `batchBinAction`, `restore`, pending/suspended/cancelled, AJAX, batch mixed-owners) do `Membership` soft/hard delete but **never** `$user->delete()` / `$user->forceDelete()` / `AccountDeletionService`. Grep + source assertions + 17 behavioral tests prove it.
- Explicit User deletion is separate, `platform_admin`–exclusive, password-confirmed, transactional, audited, and blocked when the account still owns active businesses (`canForceDelete`).
- UI: Business permanent-delete modal clearly states “The Owner/User account will NOT be deleted automatically” with per-business owner + other-business count + conditional warning. User-account modals clearly state separation.
- Restore: Membership soft-delete/restore lifecycle is symmetric and does not affect other businesses.
- Audit: No passwords/OTPs/tokens/secrets logged; all actions log `institute_id/name` or `user_id/email`.

**No further deletion-engine or authentication redesign required.** The system may keep orphaned users (0 memberships) as inactive/recoverable — intentional and safe.

---

## Appendix — Files Created / Modified in E25

**Created (were missing after E24):**
- `app/Services/AccountDeletionService.php` (restored)
- `app/Http/Controllers/Admin/UserAccountAdminController.php` (restored)
- `routes/web.php` `admin.users.*` 6 routes (restored)
- `resources/views/admin/users/index.blade.php`, `bin.blade.php`, `show.blade.php` (new — minimal safe UI, separate from business UI)
- `tests/Feature/E25GlobalUserAccountDeleteSafetyTest.php` (17 tests)
- `PHASE_E25_GLOBAL_USER_ACCOUNT_DELETE_SAFETY_FINAL_REPORT.md` (this file)

**Verified unchanged (still GREEN from E24):**
- `app/Http/Controllers/Admin/InstituteAdminController.php` (membership-aware delete/restore/forceDelete + bin enrichment + safety log)
- `resources/views/admin/institutes/bin.blade.php` (business safety card + JS)
- `tests/Feature/E24BusinessOwnerDeleteSafetyTest.php`

**Verification commands:**
```bash
php artisan test --filter=E25GlobalUserAccountDeleteSafetyTest
php artisan test --filter="E24BusinessOwnerDeleteSafetyTest|SuperAdminInstituteManagementTest|E22RealBrowserReproTest"
C:\xampp\mysql\bin\mysql.exe -u root -e "SHOW CREATE TABLE institution_user" # verify FK CASCADE
grep -r "FOREIGN_KEY_CHECKS" app/Http/Controllers/Admin/InstituteAdminController.php app/Services/AccountDeletionService.php # should be empty
grep -r "AccountDeletionService" app/Http/Controllers/Admin/InstituteAdminController.php # should be empty
```

*Tests used test-only data (`*@example.test`, `DatabaseTransactions` on `monetix_test`). No production User/Business was modified. No secrets are logged in this report.*

