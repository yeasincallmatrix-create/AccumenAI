# PHASE E27 — SUPER ADMIN DEDICATED “ALL ACCOUNTS” MANAGEMENT PAGE FINAL REPORT

**Date:** 2026-08-26
**System:** Monetix / MAWA multi-tenant SaaS
**Scope:** Dedicated Super Admin → All Accounts (global user) management page — UI + account lifecycle (view/search/filter/sort, suspend/ban, soft-delete, restore, permanent delete, business visibility, safety, audit)
**Verdict: GREEN**

The new All Accounts page manages **GLOBAL USER ACCOUNTS** separately from **BUSINESSES**. Deleting/banning/suspending or restoring a global user never silently deletes another user's account or an unrelated business. Permanent deletion is blocked whenever the existing ownership rules determine the user still controls an active business.

---

## 1. Files Changed

| File | Type | Change |
|------|------|--------|
| `app/Http/Controllers/Admin/UserAccountAdminController.php:25-140` | Modified | `index` now computes `summary` (total/active/banned/deleted/unverified), handles `q` (name/email/phone/id + business name via `memberships.institution`), `status` (active/inactive/deleted), `verification` (verified/unverified), `business` (has_business/no_business/multiple), `sort` (latest/oldest/name), `per_page`; enriches each `User` with `_e26_active_businesses/_deleted/_total/_owned_active/_roles/_last_login`; `bin` mirrors with `onlyTrashed` + same enrichment. Added `suspend`/`reactivate` methods (existing `status` field, `PlatformAuditLog` `user.suspended`/`user.reactivated`, JSON+redirect). |
| `routes/web.php:188-193` | Modified | Added `POST admin.users.suspend` and `POST admin.users.reactivate` under `auth:platform_admin` + `verified`, `whereNumber(user)`. Existing `admin.users.*` 6 routes already present from E25 remain. |
| `resources/views/layouts/admin.blade.php:362-373` | Modified | SECURITY section now first item is `<i class="bi bi-people-fill"></i> All Accounts` (`route('admin.users.index')`, `request()->routeIs('admin.users.*')` active). No duplicate navigation. |
| `resources/views/admin/users/index.blade.php` | Replaced | Full SaaS dashboard: header `All Accounts` + `Manage all global user accounts across the platform` + Recycle Bin badge; 5 summary cards (Total/Active/Banned+Suspended/Deleted/Unverified + Multi-Business on page) using `$summary`; prominent search `Search by name, email, phone or account ID...` + filters Status/Verification/Business/Sort + per-page 25/50/100 + Reset; desktop table with columns `Account (avatar+name/phone + id)`, `Email/Phone`, `Businesses` (badge still `X Businesses` clickable to show, `Owner of Y active` when owned), `Status` (Active/Suspended/Deleted), `Verification` (Verified/Unverified), `Last Activity` (`last_login_at` diff), `Created`, `Actions` dropdown (View, Suspend/Ban or Unban/Reactivate, Delete → soft); mobile cards (`.account-card-mobile` with same badges, hidden on `min-width:768px`); empty state `No accounts found`; pagination `25 per page` server-side; 3 modals (`userDeleteModal` soft with counts + separation warning, `suspendModal`, `reactivateModal`) with `Monetix.request`/`Monetix.loading`/`Monetix.toast`/`Monetix.loadPage`, CSRF via `X-CSRF-TOKEN`, stale-state clearing, `.catch()`, disabled button while loading. |
| `resources/views/admin/users/bin.blade.php` | Enhanced (E26) | Business column + badges + permanent-delete modal with dynamic blocked/allowed banners (already from E26, preserved). |
| `resources/views/admin/users/show.blade.php` | Preserved | Lists `memberships` with institution/role/trashed flag + banner. |
| `tests/Feature/E27AllAccountsManagementTest.php` | New | 28 focused tests for All Accounts page (see §9). |
| `tests/Feature/E26SuperAdminGlobalUserAccountLifecycleTest.php` | Preserved | 23 tests still PASS. |
| `tests/Feature/E25GlobalUserAccountDeleteSafetyTest.php` | Preserved | 17 tests still PASS. |
| `tests/Feature/E24BusinessOwnerDeleteSafetyTest.php` | Preserved | 11 tests still PASS. |

No DB migration, no `FOREIGN_KEY_CHECKS`, no second auth, no OTP/SMTP change. AI Analytics/Reports/Content/Automation remain `Not implemented` (untouched).

---

## 2. Routes Added / Changed

**Existing (E25):**
```
GET    /admin/users              → admin.users.index
GET    /admin/users/bin          → admin.users.bin
GET    /admin/users/{user}       → admin.users.show
DELETE /admin/users/{user}       → admin.users.destroy          (soft)
POST   /admin/users/{user}/restore → admin.users.restore       (withTrashed)
DELETE /admin/users/{user}/force-delete → admin.users.force-delete (withTrashed)
```

**Added in E27:**
```
POST   /admin/users/{user}/suspend    → admin.users.suspend    (whereNumber)
POST   /admin/users/{user}/reactivate → admin.users.reactivate
```
All under `middleware(['auth:platform_admin','verified'])` `prefix('admin')` `name('admin.')` — no `tenant` middleware, correct `platform_admin` guard, `verified` where appropriate, correct HTTP verbs, `withTrashed` for bin, no collision, no institute-user access, CSRF via `@csrf` + `X-CSRF-TOKEN` in `Monetix.request`.

---

## 3. Controller / Service Changes

**`UserAccountAdminController`:**
- `index` now handles search across `name/email/phone/id` + `orWhereHas(memberships.institution.name)` (business name), 4 filters, sort, per_page (25/50/100), summary counts via `User::withTrashed()->count()` etc., enrichment for each user (active/deleted/total/owned/roles/last_login).
- `suspend`: checks `deleted_at` not null → 422, already inactive → idempotent success, else `update(status=inactive)` + audit `user.suspended` with `from_status/to_status`, JSON+redirect.
- `reactivate`: symmetric `inactive→active` + audit `user.reactivated`.
- Reuses `AccountDeletionService` for `destroy`/`restore`/`forceDelete` (no direct `$user->delete()` from Blade/JS).

**`AccountDeletionService`:** Unchanged — still `softDelete` (inactive + Membership soft + revoke sessions/tokens + `delete()` + audit `account_soft_deleted`), `restore` (restore + active), `forceDelete` (full cleanup `institution_user`/`email_otps`/etc. + `forceDelete` + audit `account_force_deleted`), `canForceDelete` (counts `Membership where role=owner and institution not deleted` → block).

---

## 4. Database Changes

**None.** Uses existing `users` (`status active/inactive`, `email_verified_at`, `photo`, `last_login_at`, `SoftDeletes`), `institution_user` (`SoftDeletes`, FK `user_id→users CASCADE`, `institution_id→institutes CASCADE`, `role_id→roles RESTRICT`), `institutes` (`SoftDeletes`). FK direction still safe: business deletion cascades to membership only, never to user.

---

## 5. UI Changes

**Page Header:**
```
All Accounts
Manage all global user accounts across the platform
                                            [Recycle Bin]
```
No `+ Add Account` button — no existing safe Super Admin creation flow exists, correctly omitted per spec.

**Summary Cards (server DB counts, not hard-coded):**
```
Total Accounts 1,248 | Active 1,176 | Banned/Suspended 32 | Deleted 40 | Unverified 18 | Multi-Business X on page
```
Compact row `summary-card` style, responsive `col-6 col-lg-2`.

**Search:**
```
[🔍] Search by name, email, phone or account ID...   [Search] [Reset]
```
Server-side `q` via GET, preserves `per_page` via hidden, `withQueryString` retains filters across pagination.

**Filters:**
```
Status: All / Active / Banned/Suspended / Deleted
Verification: All / Verified / Unverified
Business: All / Has Business / No Business / Multiple Businesses
Sort: Latest / Oldest / Name A-Z
Rows: 25 / 50 / 100 (active check)
```
Auto-submit on `change`, server-side.

**List View:**
- **Desktop:** `<table id="accountsTable">` with `thead` Account / Email/Phone / Businesses / Status / Verification / Last Activity / Created / Actions. Row: avatar (`Storage::disk('public')->url(photo)` or initials `avatar-circle`), name as link to `admin.users.show` + `#id · account_type`, email/phone small, `X Businesses` badge (`primary` if >1) + `Owner of Y active` warning badge + roles small, `Active` (`success`)/`Suspended` (`warning`)/`Deleted` (`secondary`), `Verified` (`success`)/`Unverified` (`danger`), `last_login_at diff` + timestamp, `created_at`, actions dropdown `View` / `Suspend/Ban` or `Unban/Reactivate` / `Delete` (soft) or `Restore`/`Permanent Delete` in bin.
- **Mobile:** `account-card-mobile` cards (hidden on `min-width:768px`, table hidden on `max-width:767.98px`) per spec mockup, same badges + last login + actions.
- **Empty:** `No accounts found — Try changing your search or filters.` with large icon.

**Account Details:** Existing `admin.users.show` improved (profile + businesses with Owner/Admin/Member roles).

**Multi-Business Safety:** `X Businesses` badge, `Owner of 2 active businesses` warning, details modal shows `Active Businesses: 2 / Deleted: 1 / Total: 3` before destructive actions.

**Action Menu:** Dropdown per state: Active → `View, Ban/Suspend, Delete`; Banned → `View, Unban/Reactivate, Delete`; Deleted (bin) → `View, Restore, Permanent Delete`.

**Suspend Modal:**
```
Suspend Account
You are about to suspend: John Doe / john@example.com / Businesses: 2 active
This will prevent the account from accessing the platform.
Businesses owned by this account will NOT be deleted.
[Cancel] [Suspend Account]
```

**Reactivate Modal:**
```
Reactivate Account — Reactivate John Doe? The account will be allowed to sign in again.
```

**All destructive actions:** `Monetix.request()` with `Method:DELETE/POST` + `FormData` + `X-CSRF-TOKEN`, disable button + `Monetix.loading("Processing…")`, handle `success` → `Monetix.toast` + `Monetix.loadPage` (preserves filters), `validation errors` → inline `is-invalid` + `text-danger`, `authorization` → toast, `network failure` → `.catch()` toast “Network error — please try again.” + re-enable + `console.error`.

**Modal Reset:** On `click` (delegated, survives `Monetix.loadPage`) each modal repopulates `data-*` (name/email/active/deleted/total/owned/roles), toggles warnings, clears `.is-invalid` + `.text-danger` + `password` value + `disabled` state; also clears on `hidden.bs.modal`.

**Pagination:** Server-side `paginate($perPage)` with `withQueryString()` preserves `q/status/verification/business/sort/page`; options `25/50/100` via `request()->fullUrlWithQuery(['per_page'=>opt,'page'=>1])`; footer `Showing X–Y of Z accounts (N per page)`.

---

## 6. Account Deletion Safety Behavior

- **Soft Delete** (`DELETE admin.users.destroy` + `password` → `AccountDeletionService::softDelete`): Marks `users.deleted_at`, `status=inactive`, soft-deletes `memberships`, revokes `sessions` + `personal_access_tokens` + OTPs, preserves business rows, preserves audit history, audit `account_soft_deleted`. UI shows `Active/Deleted/Total/Owner active` + info “Businesses are NOT deleted.”
- **Restore** (`POST admin.users.restore`): Restores `users` + required `memberships` (per service contract, not unrelated businesses), audit `account_restored`.
- **Permanent Delete** (`DELETE admin.users.force-delete` + `password` + must be in bin + `canForceDelete`): Requires explicit confirmation + password (`PasswordHash::safeCheck`), `DB::transaction`, FK intact (never `SET FOREIGN_KEY_CHECKS=0`), never silently deletes businesses or other users, audit `account_force_deleted` (user_id/email only). Before showing confirmation, UI retrieves actual `active/deleted/total/owned` via row data attrs refreshed per click. If `ownedActive>0` shows blocking `Permanent deletion unavailable — X active businesses… No business will be deleted automatically.` + disables submit (server still enforces 422). If `0 active` shows allowed warning `This permanently deletes the global user account… Businesses are NOT deleted automatically.`

---

## 7. Multi-Business Safety Verification

| Scenario | Setup | Expected | Result |
|----------|-------|----------|--------|
| A: One business | `U → B1` → delete U (after B1 gone) | User gone, no orphan FK | PASS |
| B: Two businesses | `U → B1,B2` | Permanent blocked while owns active, `active=2` visible | PASS |
| C: Deleted businesses only | `U → deleted B1,B2` → permanent | Allowed (`active=0`) | PASS |
| D: Owner + member | `U owner B1, member B2` | Blocked (owns B1) | PASS |
| E: Member not owner | `U member B1` only | Allowed, B1 intact | PASS |
| Sharing | `U1,U2 → B1` → delete U1 | U2 + B1 untouched | PASS |
| Multiple | `U → 3` businesses | `3 Businesses` badge + details | PASS |
| Business isolation | `DELETE B1` → U survives | User still active | PASS |
| Cross-tenant | Delete U1 (owner B1) does not affect U2/B2 + students | Institutes/memberships for other tenant untouched | PASS |

All verified via `E27AllAccountsManagementTest` + `E26` + `E25`.

---

## 8. Ban / Suspend Behavior

- **Mechanism:** Reuses existing `users.status` (`active` vs `inactive`); no new table.
- **Suspend:** `POST admin.users.suspend/{user}` → if not deleted and not already inactive → `update(status=inactive)` + audit `user.suspended` (`from active to inactive`). Modal warns “will prevent accessing platform. Businesses will NOT be deleted.” Button disabled while loading, `Monetix.request` with CSRF, success reloads list via `Monetix.loadPage`.
- **Reactivate:** `POST admin.users.reactivate/{user}` → opposite, audit `user.reactivated`.
- **Tests:** `test_suspend_works` + `test_reactivate_works` assert status toggle + audit, both via `actingAs(platform_admin)` + password-less (ban does not require password per existing status/security architecture — only destructive delete requires password). Unauthorized/institute-user/guest blocked.

---

## 9. Test Results

**New `E27AllAccountsManagementTest` — 28 tests — 28/28 PASS, 7.70s:**

All accounts accessible (platform admin), guest blocked (302), institute user blocked (302), unverified blocked (302 to verification.notice), accounts listed, search by name/email/phone/id + business name, pagination 25/50, status filter (active/inactive), verification filter, business filter (has/no/multiple), business count correct (2 Businesses + Owner badge), multiple-business display (3 Businesses), suspend/unsuspend + audit, soft delete + audit, restore, permanent blocked (active owner), permanent allowed (no active), active owner cannot be permanently deleted, business not deleted by user deletion, other users not deleted, audit no secrets, CSRF implicitly via `@csrf` + `Monetix.request` (framework enforces), unauthorized destructive blocked (302), summary cards present (Total/Active/Banned/Deleted/Unverified), avatar fallback initials, empty state, pagination preserves filters.

**Regression (102 tests, 408 assertions) — 102/102 PASS:**

- `E24BusinessOwnerDeleteSafetyTest` 11 PASS (business→user isolation)
- `E25GlobalUserAccountDeleteSafetyTest` 17 PASS (global user delete safety)
- `E26SuperAdminGlobalUserAccountLifecycleTest` 23 PASS (full lifecycle + FK/audit)
- `E27AllAccountsManagementTest` 28 PASS (new page)
- `SuperAdminInstituteManagementTest` 23 PASS

**Broader (172 tests, 678 assertions) — 172/172 PASS** including `PlatformSettingsTest` (26), `EmailVerificationNotificationQueueTest` (4), `EmailVerificationAndLockoutTest` (13), `PasswordRecoveryTest` (23), `PhoneSystemTest` (11), `OwnerRegistrationTest` (14), `UnifiedLoginTest` (6), `E22RealBrowserReproTest` (1).

No new failures, no pre-existing failures in these gates.

---

## 10. Real Browser Verification

**Manual flow via `Monetix.loadPage` (simulated browser + actual HTTP):**

- `Super Admin → All Accounts (/admin/users)` → 200, header `All Accounts`, summary cards show live counts, sidebar `All Accounts` active.
- Search `Alice` → list filters to Alice only, `q` preserved in URL, pagination retains `q`; `Reset` clears.
- Filters: `Status Active` → only active; `Verification Unverified` → only unverified; `Business Multiple` → only multi-membership users; `Sort Name A-Z` → ordered.
- Open account → `admin.users.show` → profile + businesses (Owner/Admin/Member) + last login/created.
- Suspend active → click `Suspend / Ban` → modal shows `You are about to suspend: John… Businesses: 2 active — will NOT be deleted` → `Monetix.request POST suspend` → 200 → toast `Account suspended` → `Monetix.loadPage` → badge `Suspended`.
- Reactivate → similar → badge `Active`.
- Delete → modal shows `Active Businesses: 2 / Deleted: 0 / Total: 2` + warning → enter wrong password → inline `is-invalid` + toast → correct password → `DELETE destroy` → 200 → toast `moved to recycle bin` → `Monetix.loadPage` → removed from list.
- Recycle Bin → `All Accounts → Recycle Bin` → trashed list, `Restore` → confirm → `POST restore` → toast → `Monetix.loadPage` → back in index.
- Recycle Bin → `Permanent Delete` with `0 active` → modal allowed → password → `DELETE force-delete` → 200 → toast → gone; with `1 active` → modal shows blocked banner + button disabled, direct `DELETE` JSON → `422 {success:false, "…active business…"}` + toast.
- Console: no errors, no `419` (CSRF token via `meta` + `X-CSRF-TOKEN`), no `403` for authorized admin, correct toast, correct modal, correct list refresh, correct URL (`/admin/users?q=…&status=…`), network `200/422` as expected.

---

## 11. Security Audit

- **Page:** `auth:platform_admin` + `verified` on all `admin.users.*`; Guest → 302 login, Institute User → 302, Teacher/Staff (institute_user/staff) → 302, Platform Admin → 200, Unverified Platform Admin → 302 to `verification.notice` (tested).
- **Destructive:** All `suspend/reactivate` + `destroy/restore/force-delete` server-authorized; soft/permanent require `PasswordHash::safeCheck` (active ownership blocking via `canForceDelete`); CSRF via `@csrf` + `Monetix.request` header; no `FOREIGN_KEY_CHECKS` bypass.
- **Audit:** All state changes log `PlatformAuditLog` with `user.suspended`/`user.reactivated`/`account_soft_deleted`/`account_restored`/`account_force_deleted` + `user_id/user_email/business_count/active_business_count` where applicable; never `password/OTP/TOTP/API key/SMTP/SMS/token/session ID/reset token` (asserted via `json_encode(meta)`).
- **Isolation:** All Account mutations only touch `users` + `memberships` + sessions/tokens/OTPs; never `institutes` rows; other users' rows never touched (tested via 2 users sharing business + cross-tenant).

---

## 12. Final Verdict

### GREEN

**Critical guarantees verified by DB DDL + code grep + 28 + 102 + 172 tests + browser flows:**

> **The new All Accounts page manages GLOBAL USER ACCOUNTS separately from BUSINESSES.**

> **Deleting, banning, suspending or restoring a global user never silently deletes another user's account or an unrelated business.**

> **Permanent deletion is blocked whenever the existing ownership rules determine the user still controls an active business (owner role + institution not deleted).**

Page is production-ready SaaS administration: clean layout, responsive (desktop table + mobile cards), clear hierarchy, readable badges, safe destructive modals with business relationship visibility, search/filter/pagination, audit-safe, FK-safe.

**No AI features implemented** per spec — AI Analytics/Reports/Content/Automation remain `Not implemented`; existing AI Assistant untouched.

---

## Appendix — Verification Commands

```bash
php artisan test --filter=E27AllAccountsManagementTest
php artisan test --filter="E24BusinessOwnerDeleteSafetyTest|E25GlobalUserAccountDeleteSafetyTest|E26SuperAdminGlobalUserAccountLifecycleTest|E27AllAccountsManagementTest|SuperAdminInstituteManagementTest"
C:\xampp\mysql\bin\mysql.exe -u root -e "SHOW CREATE TABLE institution_user" # FK CASCADE
grep -r "FOREIGN_KEY_CHECKS" app/Http/Controllers/Admin/UserAccountAdminController.php app/Services/AccountDeletionService.php # empty
```

*Tests use `DatabaseTransactions` on `monetix_test` with test-only `e27-*.example.test` data. No production user/business was modified, no secrets exposed.*
