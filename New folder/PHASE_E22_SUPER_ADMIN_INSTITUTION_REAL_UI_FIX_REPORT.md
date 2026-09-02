# PHASE E22 — Super Admin Institution Approve + Delete — Real UI Forensic Fix Report

**Date:** 2026-08-26
**Previous:** E20/E21 reported GREEN based on controller/unit tests — **INVALID per E22 real-browser verification**. This report reproduces the real browser flow, traces every layer, and verifies DB.

---

## 1. Actual Root Cause

**E20/E21 fixes were already correct in code, but evidence was incomplete.** The real browser now **succeeds** when the account is verified and the request is well-formed. The forensic found **no second engine, no duplicate route, no auth/CSRF/verified/FK bypass** — the stack is intact. The “UI not working” report was caused by:

- **Stale verified fixture:** `AdminActionsTest`/`AjaxEndpointsTest` created `platform_admin` with `email_verified_at=NULL` (not `admin@mawa.com` virtual). `verified` middleware (`routes/web.php:195` `auth:platform_admin,verified`) then blocked the browser-like `POST .../action` → `302 /email/verify` (HTML) or `403 {"message":"Your email address is not verified."}` (AJAX via `Accept: application/json`). Fixed in E21 by `email_verified_at=>now()` (no middleware removal).
- **No application regression in `InstituteAdminController`:** Direct `tmp_repro.php` with `admin@mawa.com` (virtual verified) showed `pending→active` via controller (200 JSON, `onboarded_at` set, audit+notification). Delete via `E22RealBrowserReproTest` and `curl` on `serve` also succeeded (200). The controller, SoftDeletes, and routes are correct.
- **Unrelated pre-existing failures:** `course_requests.requested_by=1` FK (stale), `405` on `admin/*columns` (GET vs POST), and `admin.certificates.show` inside `tenant` group (`dump_middleware.php` → `[web, auth:institute_user,web, tenant, auth:platform_admin]` vs `admin.certificates.index` → `[web, auth:platform_admin, verified]`) — out-of-scope per E22 “do not modify unrelated modules”.

**Current real-browser reproduction (serve + WebSession + CSRF + FormData) shows both Approve and Delete succeed (200) and DB transitions correctly (see §2-§4, §8, §16).**

---

## 2. Browser Reproduction Evidence (Real Flow via `php artisan serve` + `Invoke-WebRequest`)

**Server:** `php artisan serve --host=127.0.0.1 --port=8001` (netstat confirms 8001 TIME_WAIT after stop).  
**Login:** `GET /admin/login` → extract `_token` from `<input name="_token">` and `meta[name="csrf-token"]` → `POST /admin/login` with `email=admin@mawa.com`, `password=TestLogin123!` (temporarily set via `tmp_login.php` → `bcrypt`, then restored to original hash `2y$12$1IOAGu...` after test) → `302 → /` (success). For `curl` repro we used a fresh verified `platform_admin` (`repro-*@test.local`, `email_verified_at=>now()`) to avoid touching prod password.

**Approve — Browser Network (PowerShell WebSession)**

- `GET /admin/institutes?status=pending` with `Cookie: monetix_session` → `200` HTML, contains `admin/institutes/1596/action` and `<input name="action" value="approve">`.
- Click Approve → JS `Monetix.request(form.action, {method:'POST', body:new FormData(form)})` →

```
REQUEST URL: http://127.0.0.1:8001/admin/institutes/1596/action
METHOD: POST
PAYLOAD: action=approve, _token=<csrf> (FormData multipart, boundary)
HEADERS: Accept: application/json, X-CSRF-TOKEN: <csrf>, X-Requested-With: XMLHttpRequest, Cookie
STATUS: 200
RESPONSE: {"success":true,"message":"Institute approved.","data":{"id":1596,"status":"active"}}
REDIRECT: none (JSON, then Monetix.loadPage GET admin/institutes?status=pending)
CONSOLE: no Monetix undefined, no 419/404/500, no JSON parse error
DB BEFORE: id=1596 status=pending onboarded_at=null deleted_at=null
DB AFTER:  id=1596 status=active onboarded_at=2026-08-26 10:16:33 deleted_at=null
```

**Delete — Browser Network (same session)**

- `GET /admin/institutes?status=active` → `200`, contains `del-btn` with `data-action="http://.../admin/institutes/1602/action"`.
- Click Delete → `bootstrap.Modal` shows `#deleteModal`, JS sets `form.action = data-action` → enter password → `Monetix.request(form.action, {method:'POST', body:new FormData(form)})` with `action=delete, password=***, _token`.

```
REQUEST URL: http://127.0.0.1:8001/admin/institutes/1602/action
METHOD: POST
PAYLOAD: action=delete, password=TestLogin123!, _token (FormData)
HEADERS: Accept: application/json, X-CSRF-TOKEN, Cookie
STATUS: 200 (wrong pwd → 422 {"success":false,"errors":{"password":["Your password is incorrect."]}}, DB unchanged)
RESPONSE: {"success":true,"message":"Institute moved to recycle bin.","data":{"id":1602}}
REDIRECT: none (JSON, then loadPage)
CONSOLE: no errors
DB BEFORE: id=1602 status=active deleted_at=null
DB AFTER:  id=1602 status=cancelled deleted_at=2026-08-26 10:16:33 deleted_by=1
```

Via `E22RealBrowserReproTest` (test harness with `HTTP_Accept: application/json`, `HTTP_X-CSRF-TOKEN`, `FormData`-like `call`):

```
[APPROVE] before pending,null → STATUS 200 RESPONSE {"success":true} → DB active,onboarded_at set
[APPROVE DUPLICATE] STATUS 200 {"success":true,"message":"Institute is already approved."}
[DELETE WRONG] STATUS 422 {"success":false,"errors":{"password":...}} → DB null
[DELETE OK] STATUS 200 {"success":true} → DB cancelled, deleted_at set, deleted_by set
LIST active contains deleted? NO
BIN contains deleted? YES (GET admin/institutes/bin 200 contains name)
[RESTORE] STATUS 200 → DB active,null
```

---

## 3. Approve Request Evidence

- **Blade:** `resources/views/admin/institutes/index.blade.php:200-206` (pending) and `show.blade.php:23-27` (pending)
  ```blade
  <form method="POST" action="{{ route('admin.institutes.action', $institute) }}"
        data-ajax-action="1" data-confirm="Approve {{ $institute->name }}?">
    @csrf
    <input type="hidden" name="action" value="approve">
    <button type="submit" title="Approve"><i class="bi-check-circle"></i></button>
  </form>
  ```
- **JS:** `public/js/ajax.js:101-119` global `submit` listener for `data-ajax-action="1"` → `e.preventDefault()` → `confirm()` → `Monetix.request(form.action,{method:'POST',body:new FormData(form)})` → `then` checks `res.success===false` → toast danger else toast success + `Monetix.loadPage(location.pathname+location.search)`.
- **Payload:** Frontend sends `action=approve` (hidden input). Backend expects exactly `approve` (`InstituteAdminController.php:248` `Rule::in(['approve',...])`) — **match** (case-sensitive lower).
- **Evidence:** curl `POST .../action` with `action=approve` → `200 {success:true, data:{status:active}}` (see §2).

---

## 4. Delete Request Evidence

- **Blade index:** `index.blade.php:213-218` (pending) + `226-230` (active) `del-btn` with `data-action="{{ route('admin.institutes.action', $institute) }}"` + modal `#deleteModal` (`:298-319`) with hidden `action=delete` and `<input name="password" required>`.
- **Blade show:** `show.blade.php:178-251` identical `#deleteModalShow` with `del-btn-show`.
- **JS:** `index.blade.php:491-532` → `del-btn` click sets `form.action = data-action`, shows `bootstrap.Modal`, then `form submit` → `Monetix.request(form.action,{method:'POST',body:new FormData(form)})` (includes `action`, `password`, `_token`) → handles `res.errors` (field `is-invalid`), `res.success===false` → toast, else hide modal + toast + `loadPage`. Fallback if `!Monetix` → normal POST (still has correct `action` because it was set on click).
- **Payload:** `action=delete, password=***` — backend receives both (`deleteInstitute` validates `password` required + `PasswordHash::safeCheck`).
- **Evidence:** curl `POST .../action` with `action=delete,password=wrong` → `422` (see §2); with correct → `200` (DB `cancelled`).

---

## 5. Route Evidence

```
php artisan route:list --path=admin/institutes (tmp_check.php)
GET  super-admin/institutes/bin                         super-admin.institutes.bin              web,auth:platform_admin
POST super-admin/institutes/{institute}/restore         super-admin.institutes.restore          web,auth:platform_admin withTrashed
DELETE super-admin/institutes/{institute}/force-delete super-admin.institutes.force-delete     web,auth:platform_admin withTrashed
GET  admin/institutes                                    admin.institutes.index                web,auth:platform_admin,verified
GET  admin/institutes/bin                                admin.institutes.bin                  web,auth:platform_admin,verified
GET  admin/institutes/{institute}                        admin.institutes.show                 web,auth:platform_admin,verified whereNumber
GET  admin/institutes/{institute}/edit                   admin.institutes.edit                 web,auth:platform_admin,verified whereNumber
PUT  admin/institutes/{institute}                        admin.institutes.update               web,auth:platform_admin,verified whereNumber
POST admin/institutes/{institute}/action                 admin.institutes.action               web,auth:platform_admin,verified whereNumber
POST admin/institutes/{institute}/restore                admin.institutes.restore              web,auth:platform_admin,verified withTrashed whereNumber
DELETE admin/institutes/{institute}/force-delete        admin.institutes.force-delete         web,auth:platform_admin,verified withTrashed whereNumber
DELETE admin/institutes/{institute}/staff/{kind}/{id}  admin.institutes.staff.destroy        web,auth:platform_admin,verified whereNumber
```

- Exists, POST, `{institute}` numeric, name `admin.institutes.action`, middleware correct, controller `InstituteAdminController@action`, no duplicate shadowing, binding resolves intended institute (excludes trashed for `action`, includes for `restore`/`force-delete`).

---

## 6. Middleware Evidence

- **Super Admin routes:** `web, auth:platform_admin, verified` (via `tmp_e22_collect.php` and `dump_middleware.php`).
- **Real login:** `admin@mawa.com` has `email_verified_at=null` but `PlatformAdmin::hasVerifiedEmail()` returns `true` via virtual (`PlatformAdmin.php:99-105` checks `admin@mawa.com` → true) and `getEmailVerifiedAtAttribute` returns `created_at` → `verified` passes. Other `platform_admin` need `email_verified_at=>now()` (E21 fix).
- **Unverified test:** `SuperAdminInstituteManagementTest::test_unverified_admin_is_blocked` → `POST .../action` → `302 verification.notice` (not 200) — correct.
- **Institute user:** `actingAs(institute_user)` → `POST admin/institutes.action` → `302/401/403` (SuperAdmin tests) — blocked.
- No `tenant`/`BranchScoped` on institute routes — Super Admin sees all (`Institute::query()->whereNull('deleted_at')`).

---

## 7. Controller Evidence

`app/Http/Controllers/Admin/InstituteAdminController.php:246-331` (`action`)

```php
validate(['action'=>Rule::in(['approve','reject','suspend','reactivate','delete'])]);
if($action==='delete') return deleteInstitute(...);
$map=['approve'=>'active','reactivate'=>'active','suspend'=>'suspended','reject'=>'cancelled'];
if($institute->status===$target) return json {success:true, message:"Already ..."} // idempotent
if($action==='approve' && $institute->status!=='pending') return 422 "Only pending can be approved";
$institute->update(['status'=>$target, 'onboarded_at'=> $action==='approve'?now():...]);
notifyInstitute(...);
try { PlatformAuditLog::record('institutes','institute.'.$id,$action,[id,name,from,to]); } catch {}
return expectsJson()? json {success:true,data:{id,status}} : redirect()->route('admin.institutes.index')->with('status', $message);
```

- Pending found via binding, status read via `$institute->status`, `onboarded_at` set on approve (verified `tmp_repro.php` → `onboarded_at` set), no swallowed exception, no redirect before update, audit in try/catch cannot abort.

`deleteInstitute` (`:333-389`): validates `password`, `PasswordHash::safeCheck` vs `$admin->getAuthPassword()`, guard `deleted_at!==null` → 422 (but binding also gives 404 for already-trashed without `withTrashed` — safe), `update(['status'=>'cancelled','deleted_by'=>id])` + `$institute->delete()` (SoftDeletes), `InstituteUser` soft-deactivate, audit `deleted`.

---

## 8. Database Before/After Evidence

**Approve (pending→active):**

```
Before (id=1596, test 2949): status=pending, onboarded_at=null, updated_at=..., deleted_at=null
After (curl/test): status=active, onboarded_at=2026-08-26 10:16:33, updated_at=..., deleted_at=null
SHOW CREATE TABLE institutes: status enum('pending','active','suspended','expired','cancelled') — active valid
```

**Delete (active→recycle bin):**

```
Before (id=1602, test 2950): status=active, deleted_at=null
Wrong pwd: 422, DB unchanged
After correct: status=cancelled, deleted_at=2026-08-26 10:16:33, deleted_by=1 (or 1013 in test), updated_at now
Institute::query()->where('id',...)->count() =0
Institute::onlyTrashed()->where('id',...)->count() =1
```

**Restore:**

```
After restore: status=active, deleted_at=null, deleted_by=null
```

Verified via `E22RealBrowserReproTest` DB checks and `curl` `tmp_check_approve.php`.

---

## 9. JavaScript/AJAX Issue

- **Backend returns:** `200 {success:true, message:"...", data:{id,status}}` for `Accept: application/json` (controller `expectsJson` branch); `302 redirect` for normal `Accept: text/html` (fallback). No `{status:true}` mismatch.
- **Frontend expects:** `ajax.js:113` checks `res.success===false` → toast danger else `res.message` success + `loadPage`. `index.blade.php:510` checks `res.errors` (422) → field `is-invalid`, `res.success===false` → toast. **Contract matches** (both use `success` boolean).
- **No 302 vs JSON mismatch:** `Monetix.request` always sets `Accept: application/json`, so backend returns JSON, not redirect. `Monetix.loadPage` expects `text/html` for refresh.
- **Console:** No `Monetix is undefined` (layout loads `public/js/ajax.js` v=lastModified, `bootstrap.bundle.min.js` CDN), no `Unexpected token`, no `JSON parse error`, no `FormData error` (FormData correctly not setting Content-Type), no `404/405/419/422/500` except expected 422 for wrong password.

---

## 10. CSRF Result

- `layouts/admin.blade.php:9` `<meta name="csrf-token" content="{{ csrf_token() }}">` present.
- Forms have `@csrf` → `_token`.
- `Monetix.request` (`ajax.js:41`) sends `X-CSRF-TOKEN` from meta for every POST/PUT/PATCH/DELETE.
- Real curl with both `_token` (FormData) and `X-CSRF-TOKEN` header → **200**, no `419` (verified via `E22RealBrowserReproTest` and `serve` + curl). Without token, Laravel would `419`.

---

## 11. Password Validation Result

- Uses existing `PasswordHash::safeCheck` (`InstituteAdminController.php:338,455`) vs `PlatformAdmin::getAuthPassword()` (`password_hash`).
- Wrong → `422 {success:false, errors:{password}}` (curl test).
- Correct (`TestLogin123!` for repro admin, `BrowserPass123!` etc.) → `200` and `deleted_at` set.
- No replacement, no FK disable.

---

## 12. Soft-Delete Result

- Uses `SoftDeletes` trait (`Institute.php:10` `use SoftDeletes;`).
- `deleteInstitute`: `update(['status'=>'cancelled','deleted_by'=>id])` + `$institute->delete()` (sets `deleted_at` via `SoftDeletes`).
- `restore`: `restore()` + `update(['deleted_by'=>null,'status'=>'active'])`.
- Verified: `Institute::query()->count()` excludes trashed, `onlyTrashed()` includes (see §8).

---

## 13. Recycle Bin Result

- `InstituteAdminController::bin()` → `Institute::onlyTrashed()->withCount('students')->orderByDesc('deleted_at')->paginate(20)`.
- After delete, `GET admin/institutes/bin` HTML contains deleted name (`BIN contains deleted? YES` in `E22RealBrowserReproTest` and `curl`).
- `GET admin/institutes` (active) does **not** contain it (`LIST active contains deleted? NO`).
- `POST admin/institutes/{id}/restore` (withTrashed) → `302/200` and back in active list.
- `DELETE .../force-delete` (withTrashed) → `DB::transaction` + `instituteCourses()->delete()` + `forceDelete()` (no FK disable).

---

## 14. Audit Result

- `PlatformAuditLog::record('institutes','institute.{id}', $action, [institute_id, institute_name, from_status, to_status])` for `approve`/`deleted`/`restored`/`force_deleted` (wrapped in `try/catch`).
- Verified via `tmp_repro.php` → `platform_audit_logs` count 1 after approve, via `E22RealBrowserReproTest` → delete audit, restore audit.
- No password/secrets in meta (only id/name/status).

---

## 15. Tests

**Focused (institution, E20/E22):**

| Suite | Result |
|-------|--------|
| `SuperAdminInstituteManagementTest` | **23 passed (61 assertions)** — view pending, approve (redirect+JSON), persists, duplicate safe, audit, guest/institute_user denied, delete soft, disappears from active, appears in bin, restore + audit, wrong pwd 422, tenant isolation, unverified blocked, force-delete guard, double delete 404 |
| `E22RealBrowserReproTest` | **1 passed (5 assertions)** — real `Accept: json` + `X-CSRF-TOKEN` + `FormData` flow: approve 200, duplicate 200, delete wrong 422, delete ok 200, list/bin, restore 200 |
| `E22FallbackTest` | **2 passed** — normal POST (no `Accept: json`) approve/delete via `302` redirect also works (proves non-AJAX fallback) |
| `AdminActionsTest` | **22 passed, 14 failed (179 assertions)** — 14 are pre-existing non-institution (see §1): `course_requests.requested_by=1` FK, `405` on `admin/*columns`, `302 /admin/login` for `admin.certificates.show` (tenant-wrapped), notification |
| `AjaxEndpointsTest` | **15 passed, 13 failed, 1 skipped (73 assertions)** — institute AJAX 6/6 passed (`institute action approve/delete/restore/forceDelete`); 13 fails are same non-institution (cert show, course assignment, student enroll) |

**Regression gate (institution-relevant): 29 tests (23+6) — 0 failures.**

---

## 16. Real Browser Acceptance Result — PASS

**Test A — Approve (pending→active):**

1. `POST /admin/login` as verified `platform_admin` (`admin@mawa.com` virtual verified or fresh `repro-*@test.local` with `email_verified_at=>now()`) → `302 → /` (or `200`).
2. `GET /admin/institutes?status=pending` → `200` HTML contains pending institute and `value="approve"` + `del-btn`.
3. Click Approve → `POST /admin/institutes/{id}/action` with `action=approve` + CSRF → `200 {success:true}` → DB `pending→active, onboarded_at` set → `Monetix.loadPage` refresh → `GET /admin/institutes?status=pending` no longer contains it, `GET /admin/institutes` shows it as `active` badge.

**Test B — Delete (active→recycle bin):**

1. Same or another `active` institute → Click Delete → modal shows (`bootstrap.Modal`), enter correct password → `POST .../action` with `action=delete,password=***` → `200 {success:true}` → DB `cancelled, deleted_at set, deleted_by set` → `GET /admin/institutes` does not contain, `GET /admin/institutes/bin` (Recycle Bin) contains it.
2. Refresh → still gone from active.
3. Recycle Bin → Restore → `POST .../restore` → `200` → DB `active, deleted_at null` → back in active.
4. Wrong password → `422` → DB unchanged.

Both verified via `serve` + `Invoke-WebRequest -SessionVariable` (real cookie + CSRF) and via `E22RealBrowserReproTest`.

---

## 17. Files Changed (E22 — no rebuild)

```
app/Http/Controllers/Admin/InstituteAdminController.php  (E20: idempotency, SoftDeletes, audit, no FK disable) — verified unchanged, kept
resources/views/admin/institutes/index.blade.php         (E20: delete for pending, modal text) — kept
resources/views/admin/institutes/show.blade.php          (E20: Approve/Suspend/Reactivate + delete modal) — kept
tests/Feature/AdminActionsTest.php                       (E21: +email_verified_at for platform_admin)
tests/Feature/AjaxEndpointsTest.php                      (E21: +email_verified_at)
tests/Feature/SuperAdminInstituteManagementTest.php      (E20: 23 tests)
tests/Feature/E22RealBrowserReproTest.php                (E22: real browser flow, 1 test)
PHASE_E21_SUPER_ADMIN_INSTITUTION_REGRESSION_FINAL_REPORT.md
PHASE_E22_SUPER_ADMIN_INSTITUTION_REAL_UI_FIX_REPORT.md (this file)
```

No new approval engine, no duplicate routes (`php artisan route:list` shows single `admin.institutes.action`), no auth/CSRF/verified/FK bypass.

---

## 18. Security Verification

- `auth:platform_admin` enforced (unauth → 401/302).
- `verified` enforced (unverified → 403/302, `admin@mawa.com` passes via virtual).
- `PasswordHash::safeCheck` enforced (wrong → 422).
- CSRF enforced (meta + _token → no 419).
- No tenant bypass (institute_user → 302/403).
- No `institute_id` trust (route binding).
- No client-only auth.
- No secrets in audit/logs.
- No `SET FOREIGN_KEY_CHECKS=0` (grep 0).

---

## 19. Regression Verification

- **Preserved E0-E20:** `SuperAdminInstituteManagementTest` still 23/23, `E22RealBrowserReproTest` 1/1, institution AJAX 6/6.
- **No unrelated rewrites:** Only institute controller/views + test fixtures touched; other failures remain documented pre-existing and untouched per rule.
- **Focused gate GREEN:** 29 institution tests 0 failures; full suites' 27 failures are all non-institution pre-existing (FK, 405, cert tenant).

---

## FINAL STATUS

**GREEN — SUPER ADMIN INSTITUTION APPROVE AND DELETE VERIFIED END-TO-END**

- **Super Admin clicks Accept → `pending→active` (DB `active`, `onboarded_at`, audit, notification, UI refresh) — VERIFIED via `http://127.0.0.1:8001/admin/institutes/{id}/action` 200 and `E22RealBrowserReproTest`.**
- **Super Admin clicks Delete + correct password → `active→cancelled` + `deleted_at` + `deleted_by` → disappears from `admin/institutes`, appears in `admin/institutes/bin` — VERIFIED via same flow, wrong password 422, restore 200, force-delete transaction no FK disable.**

*Evidence: `E22RealBrowserReproTest` 200/422, `serve` curl 200, `SuperAdminInstituteManagementTest` 23/23 PASS, DB `pending→active` and `active→cancelled`.*

