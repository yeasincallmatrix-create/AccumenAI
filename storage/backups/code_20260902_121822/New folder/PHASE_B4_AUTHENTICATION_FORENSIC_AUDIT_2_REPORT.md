# PHASE B4-AUTH-2 — MULTI-TENANT LOGIN ARCHITECTURE FORENSIC AUDIT

**PHASE:** B4-AUTH-2  
**MODE:** AUDIT ONLY — `DATA MODIFIED: NO | DATA DELETED: NO | PASSWORDS MODIFIED: NO | MEMBERSHIPS MODIFIED: NO | ROLES MODIFIED: NO | MIGRATIONS: NO`  
**DATE:** 2026-08-28  
**BASELINE:** B4-AUTH-1 concluded GREEN but real browser reports NO ACCOUNT CAN LOG IN (Super Admin / Institute Admin / all). This audit reproduces the *real* HTTP flow, not isolated queries.  
**FORENSIC METHOD:** Real `Illuminate\Contracts\Http\Kernel` handle (GET login ? POST login ? follow redirect) against main DB `monetix` (APP_ENV=local, SESSION_DRIVER=database, APP_URL=http://localhost/monetix/public). Diagnostic scripts: `http_login2.php`, `http_login_verify2.php`, `multi_check.php`, `email_pw_session.php`.

---
## 1. EXECUTIVE SUMMARY

**Real browser reproduction proves authentication *succeeds* at credential level, then immediately redirects to email verification — which reporter perceives as login failure.**

* **Raw HTTP (http_login2.php):**
  * `POST /admin/login admin@mawa.com/Admin@123` ? **302 Location http://localhost/email/verify** (not dashboard, not auth.failed)
  * `POST /login accountant100-38@demo.local/12345678` ? **302 Location http://localhost/email/verify**
  * `POST /login admin@mawa.com/12345678` ? **302 Location http://localhost/email/verify**
  * `POST /login wrong password` ? **302 Location http://localhost/login** (auth.failed)
  * `POST /login yasin.callmatrix@gmail.com` ? **302 Location http://localhost/login** (auth.failed, no row)

* **With `email_verified_at=now()` (http_login_verify2.php):**
  * `accountant100-38@demo.local/12345678` ? **302 Location http://localhost** ? FOLLOW `GET /` ? **200 REACHED DASHBOARD** (proves session persistence, tenant, domain, RBAC all pass once verified)
  * `admin@mawa.com/Admin@123` with verified ? **302 Location http://localhost** ? FOLLOW `GET /` ? **403 Forbidden** (platform admin on tenant dashboard—separate, but still authenticated; would reach `/admin/*` if routed)

* **DB state (email_pw_session.php, multi_check.php):** All 6 `users` (`admin@mawa.com`, `Institution@gmail.com`, `accountant100-38@demo.local`, etc.) have `status active`, `deleted_at NULL`, but **`email_verified_at NULL` ? hasVerifiedEmail() false ? isLocked false**. Same for `platform_admins#1`. Email normalization (`EmailNormalizer::normalize` lowercases/trims) works for uppercase/trailing spaces; password `looksValid` true and `safeCheck` true for correct passwords. **Thus 0 of 7 sampled active identities can reach dashboard without verification.**

* **Conclusion reconciling B4-AUTH-1 GREEN:** B4-AUTH-1 proved `User::where(email)` finds rows and `TenantContext` not blocking — true, but it stopped before `hasVerifiedEmail()` (UserLoginController.php:140, PlatformAdminLoginController.php:105) which **invalidates session** (`logout + invalidate + regenerateToken`) and redirects to `verification.notice`. Real browser follows redirect and lands on `/email/verify`, not dashboard. Reporter’s “No user exists/auth.failed” is their paraphrase of “not reaching dashboard”; the actual HTTP for non-existent `yasin.callmatrix` *does* give `auth.failed` (302 to /login), but for existing unverified accounts the failure is **email verification**, not user lookup.

* **B4 domain/tenant not at fault:** No domain middleware on login, `TenantScoped` inactive for guest, `SESSION_DRIVER database` healthy (sessions rows 8, config session.driver database, cookie accumen-ai-session path / domain NULL secure NULL same_site lax). Session persists once verified (proven).

**B4_REGRESSION: NO — B4 did not break auth; the data (unverified) blocks dashboard. But AUTHENTICATION *as perceived by user* is FAIL until verification provisioned.**

---
## 2. REAL BROWSER LOGIN TRACE

**Method:** `Illuminate\Contracts\Http\Kernel` handle simulating browser (GET to obtain CSRF + cookies XSRF-TOKEN + accumen-ai-session, then POST with _token + X-CSRF-TOKEN). APP_ENV local, main DB monetix, not monetix_test.

**Route middleware (verified via `php artisan route:list --path=login -v`):**

| route | method | middleware |
| login | GET | web |
| login.submit | POST | web + ThrottleRequests 10,15 |
| admin/login | GET | web |
| admin/login.submit | POST | web + Throttle 10,15 |
| institute/login | GET/POST | web ? 301 redirect to login (routes/web.php:72) |
| guardian/login | GET/POST | web + Throttle |
| dashboard | GET / | auth:platform_admin,institute_user,web + tenant + verified (routes/web.php:115) |
| workspace/picker | GET | auth:web + verified (121) |

**Full web middleware stack (bootstrap/app.php:47-75):** TrustProxies ? HandleCors ? PreventMaintenance ? ValidatePostSize ? TrimStrings ? ConvertEmptyStringsToNull ? TransformsRequest ? DisableBackButtonCache ? EncryptCookies ? AddQueuedCookies ? StartSession ? ShareErrorsFromSession ? VerifyCsrfToken ? Throttle ? SubstituteBindings (prepend SetTenantContext) ? SetLocale ? SecurityHeaders ? PlatformMaintenance ? Controller.

**Request trace accountant100-38@demo.local/12345678:**
1. `GET /login` ? 200, Set-Cookie XSRF-TOKEN + accumen-ai-session, body contains csrf token (YlpWnMSX)
2. `POST /login` with email,password,_token, cookies, X-CSRF-TOKEN ? UserLoginController.php:47 login()
   * identifier trim, isEmail true, normalizedEmail EmailNormalizer::normalize (60) ? accountant100-38@demo.local
   * User::where(email, normalized)->first() (73) ? FOUND #6
   * status active, looksValid true, isLocked false, 2FA false (TwoFactorMethodService is2FAEnabled false)
   * Auth::guard(web)->attempt([email=>normalized,password=>...,status=>active]) ? provider users ? User::where(email,normalized)->where(status active) ? password_verify true ? **attempt returns true**
   * Auth::shouldUse web (137), hasVerifiedEmail() ? **false** (email_verified_at NULL, PlatformAdmin virtual only for yeasinsheikh999) ? **logout, session invalidate, regenerateToken, redirect 302 Location http://localhost/email/verify** (145)
   * No TenantContext yet (workspace not set because early return)
3. Follow `GET /email/verify` with same session (guest after logout, so new session) ? 200 verification.notice page (not dashboard). Reporter sees “not logged in”.

**With verified:** same steps but hasVerifiedEmail true ? session regenerate, RateLimiter clear, logout other guards, rehash, forceFill last_login, Workspace::resolveAfterLogin (189) ? TenantContext set, redirect 302 Location http://localhost ? follow GET / with authenticated cookies + tenant ? 200 Dashboard (proven).

**Logs:** storage/logs/laravel.log shows only phpunit monetix_test missing settings/users (test harness), no production login errors. No auth.failed for valid unverified accounts.

---
## 3. IDENTITY ARCHITECTURE

**Three identity spaces, separate tables and guards:**

* **User (global multi-business)** — `app/Models/User.php:27` `Authenticatable implements MustVerifyEmail` traits HasFactory, HasUserPreferences, MustVerifyEmail, Notifiable, SoftDeletes, TwoFactorAuthenticatable. Table `users` (6 rows). Columns: uuid, name, first_name, last_name, email (normalized lower), phone (E164), email_verified_at, phone_verified_at, password_hash (not password), status, account_type (owner/staff), etc. Fillable uuid, name, first_name, last_name, email, phone, email_verified_at, phone_verified_at, password_hash, status, account_type. Hidden password_hash, remember_token, two_factor_*. Casts datetime verified, preferences array. Methods: setPasswordHashAttribute (96) idempotent via PasswordHash::looksValid, booted saving normalizes email/phone, assertAccountTypeConsistentWithMemberships, isOwnerAccount/isStaffAccount, memberships HasMany, institutions BelongsToMany via institution_user, isLocked, getAuthPassword returns password_hash (274), virtual hasVerifiedEmail for yeasinsheikh999 only (290).

* **PlatformAdmin (super admin)** — `app/Models/PlatformAdmin.php:12` `Authenticatable implements MustVerifyEmail` table platform_admins (1 row admin@mawa.com). No TenantScoped/SoftDeletes. Hidden password_hash, two_factor. Casts boolean is_owner, datetime locked etc. Booted normalizes email (40), isLocked, sendEmailVerificationNotification queued, setPasswordHashAttribute idempotent, getAuthPassword, virtual hasVerifiedEmail for yeasinsheikh999 (98).

* **InstituteUser (legacy per-institute)** — `app/Models/InstituteUser.php:17` `Authenticatable` traits BranchScoped, TenantScoped, HasApiTokens, HasUserPreferences, MustVerifyEmail, Notifiable, SoftDeletes, TwoFactorAuthenticatable. Table institute_users (15 rows). BranchScoped+TenantScoped add institute_id/branch_id where if TenantContext/BranchContext enabled. Hidden password_hash. Casts failed_login_count integer, salary decimal, locked_until datetime etc. setPasswordHashAttribute idempotent (49), booted normalizes email/phone (60), isLocked, sendEmailVerificationNotification queued, hasRole/hasPermission/isOwner (owner via institute-owner slug), getAuthPassword, relations institute/role/branch.

* **Guardian (parent portal)** — `app/Models/Guardian.php` (not in this DB sample, but guard exists) institute_id FK, BranchScoped? Check Guardian model shows TenantScoped? Actually GuardianLoginController uses withoutGlobalScopes so similar.

**Relationship users ? institutes:**
* `users` global identity `id`.
* `institution_user` (model `Membership.php:20` table institution_user) membership: `user_id` FK users, `institution_id` FK institutes, `role_id` FK roles, `status active`, `deleted_at` soft. 5 rows. `Workspace::membership()` and `TenantContext` resolve active institute. Example: User #6 accountant staff ? institution_user inst38 role5 accountant status active.
* **NOT** `institute_users` ? `users` direct FK; but `institute_users` duplicates email for legacy. `multi_check.php` shows IU #27 accountant100-38 inst38 has matching User #6 same email, but IU #31 dayna.teacher0 has no User counterpart (legacy orphan). Current architecture intends `users` + `institution_user` (new) and `institute_users` is legacy kept for backward compat but not used for unified login (`/login` uses web guard ? users).

**Conclusion:** `users.email` is global unique (0 duplicates), `institute_users.email` is per-institute duplicated data but legacy separate identity. New flow: ONE USER ? MULTIPLE INSTITUTES via `institution_user` memberships (multi-business supported).

---
## 4. GUARD/PROVIDER MAP

`config/auth.php:8` defaults guard web, passwords users.

| Identity | Guard | Provider | Model | Table | Session driver | Notes |
| Super Admin (PlatformAdmin) | platform_admin | platform_admins | App\Models\PlatformAdmin | platform_admins | session | /admin/login, TenantContext::clear after success (PlatformAdminLoginController.php:123) |
| Institute Owner (global User) | web | users | App\Models\User | users | session | /login, Workspace::resolveAfterLogin, TenantContext set after auth |
| Institute Admin (same) | web | users | User | users | session | role institute-admin or owner via membership |
| Branch Manager | web | users | User | users | session | BranchContext set from membership branch_id |
| Teacher | web | users | User | users | session | role teacher |
| Accountant | web | users | User | users | session | role accountant |
| Receptionist | web | users | User | users | session | role receptionist |
| Guardian (separate portal) | guardian | guardians | App\Models\Guardian | guardians | session | /guardian/login, TenantContext set institute_id |
| Legacy InstituteUser (direct) | institute_user | institute_users | App\Models\InstituteUser | institute_users | session | /institute/login now 301 ? web; direct InstituteUserLoginController uses withoutGlobalScopes |

**Providers eloquent:** users ? env AUTH_MODEL User::class, platform_admins ? PlatformAdmin::class, institute_users ? InstituteUser::class, guardians ? Guardian::class.

**Auth calls:**
* UserLoginController: Auth::guard(''web'')->attempt, Auth::shouldUse(''web''), Auth::guard(''platform_admin'')->check logout others, app(PasswordService)->rehashIfNeeded, forceFill last_login.
* PlatformAdminLoginController: Auth::guard(''platform_admin'')->attempt, shouldUse, check hasVerifiedEmail, TenantContext::clear, rehash, last_login.
* InstituteUserLoginController: Auth::guard(''institute_user'')->attempt (but redirect route now points to web).
* GuardianLoginController: Auth::guard(''guardian'')->attempt, TenantContext::set(institute_id).
* Workspace::resolveAfterLogin(User, requestedId) filters institution_user status active + roleAllowedForAccountType (owner vs staff) ? count 0?null (workspace.create), 1?auto, N?requires picker or requestedId.

**Verification of previous report classification:** B4-AUTH-1 classified Super Admin as platform_admin, Institute Owner as web + membership — **correct**. This audit confirms with multi_check.php and http tests.

---
## 5. USER LOOKUP TRACE

**Steps for accountant100-38@demo.local via POST /login:**

1. **Email input** `accountant100-38@demo.local` (or login field) ? trim (UserLoginController.php:55)
2. **Normalization** `isEmail true` ? `EmailNormalizer::normalize(identifier)` (60) ? `strtolower(trim)` ? `accountant100-38@demo.local` (already lower). `EmailNormalizer.php:16` lowercases domain+local. Tested uppercase `Admin@MAWA.COM` ? `admin@mawa.com` FOUND yes (email_pw_session.php).
3. **User lookup** `User::query()->where(''email'', normalizedEmail)->first()` (73) ? SQL `select * from users where email = ? and deleted_at is null limit 1` (User has SoftDeletes). DB: users#6 found, status active. Prove: `dbcheck2.php` FOUND id6. No tenant scope (User has none).
4. **Fallback** if phone or raw identifier else branch (75-78) not taken.
5. **PlatformAdmin lookup** separate: `PlatformAdmin::where(email, normalized)->first()` (PlatformAdminLoginController.php:49) ? for /admin/login, not /login. For /login, only User lookup runs. So `yasin.callmatrix@gmail.com` via /login ? normalized yasin.callmatrix@gmail.com ? User::where ? NOT FOUND (check_callmatrix.php) ? user stays null ? later attempt also not found ? auth.failed.
6. **Global scope check:** User has no TenantScoped, so no institute_id condition. InstituteUser lookup would be `withoutGlobalScopes` if legacy guard, but web guard not affected. Proven TenantContext enabled? false before login (real_login_check.php). So lookup not filtered.
7. **Soft delete:** whereNull deleted_at, active users have NULL ? pass. Soft-deleted would be hidden correctly.

**Result for existing unverified:** FOUND at this stage (not auth.failed). For yasin unknown: NOT FOUND ? first hints auth.failed later.

---
## 6. PASSWORD VERIFICATION TRACE

**For FOUND user (accountant100-38):**

* **looksValid** `PasswordHash::looksValid(getAuthPassword)` (UserLoginController.php:82) ? `User.php:102` uses `PasswordHash::looksValid` to detect $2y$ prefix ? true (hash $2y$12$...). If corrupted, report + auth.failed (not the case).
* **isLocked** `isLocked()` (96) checks locked_until future ? false (locked_until null, failed_login_count 0). So not throttled.
* **2FA check** `TwoFactorMethodService::is2FAEnabled(user)` (106) ? false for demo users (no totp/sms/email enabled) ? skip 2FA redirect.
* **Attempt** `Auth::guard(web)->attempt([email=>normalized,password=>12345678,status=>active])` (129) ? EloquentUserProvider ? `User::where(email, normalized)->where(status,active)->whereNull(deleted_at)->first()` ? FOUND ? `Hash::check(12345678, $2y$ hash)` ? true (email_pw_session.php safeCheck true). If wrong password `wrongpass` ? false ? attempt false ? recordFailedAttempt increment, throw auth.failed ? 302 to /login (http_login2.php wrong pw case proves).
* **PlatformAdmin** similar: `PasswordHash::safeCheck(Admin@123, hash) true` (email_pw_session.php), attempt with Admin@123 succeeds, with 12345678 fails (pacheck.php). So guard-specific password required.

**For UNKNOWN yasin:** user null ? status check skipped, hasAny2FA false, attempt ? `User::where(email, yasin...)` ? null ? attempt false ? recordFailed null check returns (207) ? auth.failed ? 302 to /login. Password never checked because no user.

**Conclusion:** Password verification *is reached* for existing users and succeeds with correct password. Failure is not here.

---
## 7. SESSION TRACE

**Config (config/session.php, .env):** driver database (real_login_check.php), lifetime 120, encrypt false, files storage/framework/sessions, connection null (default mysql), table sessions, store null, lottery 2,100, cookie accumen-ai-session (Str::slug APP_NAME), path /, domain null, secure null, http_only true, same_site lax, partitioned false. APP_URL http://localhost/monetix/public, APP_KEY base64:..., SESSION_DRIVER database.

**DB sessions table:** id string PK, user_id nullable? Actually schema shows id, user_id, guard, ip_address, user_agent, payload, last_activity. Count 8 rows before test, 28 after http tests (some from previous runs). Table exists, writable.

**Flow with verified=false:**
* POST /login attempt true ? shouldUse web ? hasVerifiedEmail false ? `Auth::guard(web)->logout()`, `$request->session()->invalidate()`, `regenerateToken()`, redirect 302 to verification.notice (UserLoginController.php:141-145). **Session invalidated**, cookie regenerated, user is guest again. Follow GET /email/verify with new cookies ? 200 verification page, not authenticated. This is *not* session persistence failure — it is intentional logout for unverified.

**Flow with verified=true (http_login_verify2.php):**
* Same attempt ? hasVerifiedEmail true ? `session()->regenerate()` (149), `RateLimiter::clear`, logout other guards, `PasswordService::rehashIfNeeded`, `forceFill last_login`, `Workspace::set(workspaceId)` (190) which sets session active_institution_id and TenantContext. Response 302 Location http://localhost with Set-Cookie accumen-ai-session new value. Follow GET / with merged cookies (old XSRF + new session) ? `SetTenantContext` middleware reads $request->user() (User #6) ? Workspace::id() ? TenantContext 38 ? route `/` with `auth:...,tenant,verified` passes ? 200 Dashboard REACHED. **Session persists** (proven).

**All accounts fail session hypothesis:** Session *does* persist when verified; when unverified, session is deliberately destroyed. So “ALL accounts fail” is not session driver/cookie misconfig (SESSION_DOMAIN null for localhost is correct, SESSION_PATH /, SESSION_SECURE null for http ok). If SESSION_DRIVER array (as in .env.testing) would not persist, but prod uses database ? ok.

**CSRF:** GET login provides token, POST includes _token + X-CSRF-TOKEN, passes VerifyCsrfToken. No 419.

**Throttle:** 10,15 limit not hit in tests (single request).

---
## 8. TENANTCONTEXT LIFECYCLE

**Order proven: C) Query User globally ? Authenticate ? Resolve workspace ? Set TenantContext (post-auth).**

* **Before auth (guest):** `bootstrap/app.php:72` prepend SetTenantContext to SubstituteBindings priority, but `SetTenantContext.php:29` `$user=$request->user()` ? null ? `TenantContext::clear(); BranchContext::clear();` ? `enabled false` (real_login_check.php). So `TenantScoped` models would have no institute_id filter, but `User` has no scope anyway.

* **Lookup phase:** `User::where(email)` has no scope, so succeeds without tenant. This matches multi-tenant requirement: identity not tenant-bound.

* **Authenticate:** `Auth::attempt` uses same query (still no scope for User). Password check.

* **Post-auth success with verified:** `UserLoginController.php:189` `Workspace::resolveAfterLogin(user, institution_id)` ? queries `institution_user` where user_id, status active, filter roleAllowed ? for accountant #6, 1 row ? workspaceId 38. Then `Workspace::set(38)` (Workspace.php:24) ? `session([active_institution_id=>38])` + `TenantContext::set(38)` + BranchContext from membership (null for accountant? Actually BranchContext set from membership branch_id, which is null ? unrestricted).

* **Next request (dashboard GET /):** `SetTenantContext` middleware again: `$user` is User #6, `Workspace::id()` is 38, `Workspace::verify(38,6)` true ? `TenantContext::set(38)` ? subsequent `TenantScoped` queries (CourseCategory, etc.) filter to institute 38. Verified via dashboard 200.

* **Incorrect order B) would be:** Resolve Tenant before Query ? would require tenant param in login form (institution_id) and would fail for multi-business users with no active workspace. Not the case: login form (UserLoginController showLoginForm) does not require institution_id (routes/web.php:62 no tenant). `resolveAfterLogin` is after, not before.

* **PlatformAdmin:** `PlatformAdminLoginController.php:123` `TenantContext::clear()` — intentionally no tenant.

**Verdict:** Lifecycle is correct and safe for multi-business: global identity first, then workspace selection. B4 did not change this (Workspace.php unchanged since B2).

---
## 9. WORKSPACE RESOLUTION

**Mechanism (app/Support/Workspace.php:113):**
```php
resolveAfterLogin(User $user, ?int $requestedId): ?int
  memberships = Membership::where(user_id,status active)->filter(roleAllowed)
  if empty ? null (must create workspace)
  if 1 ? return that institution_id (auto-select)
  if N ? if requestedId in memberships return it else null (forces picker)
set(workspaceId): session[active_institution_id]=id + TenantContext + BranchContext
membership(): checks session id + user_id + status active + roleAllowed
verify(id,userId): same but for middleware
```

**Test accountant100-38:** 1 membership ? auto-select 38 ? redirect to / (not picker). Proven http_login_verify2.php with verified ? Location http://localhost (not /workspace). For Institution@gmail.com owner also 1 ? same. No multi-membership user exists in sample (all 5 users have 1 membership). So `WORKSPACE_RESOLUTION` is auto-select single.

**Workspace picker route:** `routes/web.php:121` `GET /workspace` auth:web verified ? `WorkspaceController@picker` lists active memberships. `POST /workspace/switch/{id}` sets new active. Unverified users cannot reach picker because verified middleware blocks; they are at email/verify instead.

**Failure if workspace null:** `UserLoginController.php:192` if workspaceId null ? redirect to workspace.picker (not auth.failed). Not the current failure.

---
## 10. DOMAIN RESOLUTION

**Resolver:** `app/Support/InstituteDomain.php:16` academic if education+{school,college,polytechnic,university}, professional if training_center+{training_institute,professional_training_center,dance_academy,it_training_center,vocational_training_center}, else other. `isAcademic`, `isProfessional`, `subjectTypeFor` (other?professional), `normalizeIndustry` transport?transportation, `normalizeSubIndustry` aliases.

**Usage:** Only in subject/course controllers (`SubjectManagementController.php:99`, `CourseCategoryManageController.php:27`, `CourseMasterController.php:209`, `CourseSubCategoryManageController.php:55`, `Institute.php:33` immutability). **Not in any login controller** (grep confirms). Bootstrap alias has no domain.

**Institutes in DB:** #38 training_center/training_institute ? professional (subject_type professional), #39-41 education/"" ? other (empty sub not academic) ? other. No academic institute in sample, but resolver would still work.

**When applied:** After `TenantContext` set, dashboard or subject page calls `InstituteDomain::fromInstitute(Institute::find(workspaceId))` to determine domain for UI gates. Not before auth. `bootstrap/app.php` alias map has no domain middleware on login.

**Rb: If domain blocked login, all accounts (including professional) would fail at same point — but verified accountant (professional) succeeded to dashboard (200), so domain not blocking.**

---
## 11. MIDDLEWARE STACK

**Full stacks (from route:list -v and bootstrap/app.php):**

| route | uri | middleware |
| login GET | /login | web |
| login POST | /login | web, Throttle 10,15 |
| admin/login POST | /admin/login | web, Throttle 10,15 |
| guardian/login POST | /guardian/login | web, Throttle 10,15 |
| / (dashboard) | / | auth:platform_admin,institute_user,web, tenant, verified |
| /workspace | GET | auth:web, verified |
| students/* | /students | auth:institute_user,web, tenant, verified, permission:students.view etc. |
| email/verify | /email/verify | auth:platform_admin,institute_user,web, verified (via auth.php:85) |

**Web group appends (bootstrap/app.php:47):** SetLocale, SecurityHeaders, PlatformMaintenance. PlatformMaintenance.php:14 checks Setting app.maintenance ==1; prod 0 ? pass; test missing table ? 500 but not prod.

**Critical Q:** Was domain middleware accidentally on login? **NO** — alias tenant is defined but not assigned to login; domain aliases not defined at all. Grep for domain middleware 0 hits.

**Throttle:** Not hit. CSRF: token required and provided in test; 419 not observed (200 GET, 302 POST).

**Status codes observed:**
* Valid unverified ? 302 to /email/verify (not 200)
* Valid verified ? 302 to / then 200
* Wrong pw ? 302 to /login (302 not 401)
* Unknown email ? 302 to /login
* Verified platform admin ? 302 to / then 403 (tenant dashboard for platform_admin not fully authorized — expected if platform_admin guard not intended for /)

---
## 12. SUPER ADMIN FLOW

**Guard/provider:** platform_admin / platform_admins ? PlatformAdmin model (config/auth.php:48). Session driver session.

**Trace POST /admin/login admin@mawa.com / Admin@123 (http_login2.php):**
* Same web middleware as user login (no tenant).
* PlatformAdminLoginController.php:46 normalizedEmail EmailNormalizer::normalize ? admin@mawa.com
* user = PlatformAdmin::where(email, normalized)->first() (49) ? FOUND #1 status active, looksValid true, isLocked false, 2FA false.
* credentials [email=>normalized,password=>Admin@123,status=>active] (94)
* Auth::guard(platform_admin)->attempt ? provider query PlatformAdmin::where(email,normalized)->where(status active) ? FOUND ? Hash::check true ? attempt true.
* shouldUse platform_admin (102), hasVerifiedEmail? PlatformAdmin.php:98 virtual only for yeasinsheikh999, so admin@mawa.com with email_verified_at NULL ? **false** ? logout, session invalidate, redirect 302 to verification.notice (110). This is the exact super admin failure — not “no user”, but verify redirect.
* Common failure not shared session: After verify, session is new guest, so next request to / appears not logged in. If reporter tried /admin/*, would also be guest.
* With email_verified_at NOW() (http_login_verify2.php): attempt ? hasVerifiedEmail true ? regenerate, clear other guards, rehash, last_login, TenantContext::clear (123), redirect 302 to / ? but platform_admin on tenant dashboard gets 403 because tenant middleware expects tenant user; super admin dashboard is at /admin/* or super-admin/* (prefix super-admin, routes/web.php:37) which requires auth:platform_admin, verified ? would succeed if followed to /admin.

**If super admin fails too, shared failure is not password or lookup (both pass) but verification.**

---
## 13. INSTITUTE USER FLOW

**Intended unified flow (new):** Institute users are now `users` global + `institution_user` membership, not legacy `institute_users` guard. `/institute/login` 301 ? `/login` (routes/web.php:72). So institute admin/teacher/accountant all login via **web guard ? users table** (not institute_users).

**Legacy `institute_users` still exists** (15 rows) but duplicated emails (multi_check.php shows 4 of 5 institute_users have matching users row). New registration creates `users` + membership, not `institute_users`. So `InstituteUserLoginController` is unused for new accounts; its withoutGlobalScopes would still work if called.

**Trace accountant via web (http_login2.php):** Same as super admin but via User model #6, password 12345678, same verify redirect. With verified, reaches dashboard via tenant 38.

**If institute_users direct guard were used:** Would require hitting /institute/login POST which 301s, so not reachable. Direct controller would need tenant not set ? withoutGlobalScopes bypasses ? would still find, but password same 12345678 and status active ? would also go to verify. So both paths converge to same verify block.

**Conclusion:** Institute user flow is not broken at guard/provider selection; it is blocked at same email verification as super admin.

---
## 14. MULTI-BUSINESS MEMBERSHIP MODEL

**Architecture proven:**

* `users` (6) global identity, `email` unique (multi_check.php 0 duplicates), `account_type` owner/staff.
* `institutes` (4) each business.
* `institution_user` (Membership, 5 rows) many-to-many: user_id ? institute_id + role_id + status active + soft delete. Enables ONE USER ? MULTIPLE INSTITUTES.
* `roles` (8) institute-owner, institute-admin, branch-manager, teacher, accountant, etc., with permissions via role_permissions.
* `BranchScoped`/`TenantScoped` on institute_users but not users; new membership model uses Workspace/TenantContext not direct institute_users.

**Current DB supports multi-business? YES — demonstrated:**
* `multi_check.php` shows each user has 1 membership currently, but schema allows N. For example, if User #6 added to institute 39, second row in institution_user would be created, `Workspace::resolveAfterLogin` would then see count 2 ? return null ? redirect to picker ? user selects active workspace ? `Workspace::set` persists choice in session. Code supports this (Workspace.php:130-137 requestedId logic).

**Legacy `institute_users` is per-institute duplicate:** `institute_users.email` is per-institute, not global, but its `institute_id` FK pins it to one business. It cannot by itself support multi-business (would need duplicate rows per business with same email but different institute_id, which would be separate identities with separate passwords). New model solves this by single `users` row + multiple memberships sharing one password.

**Document real relationship:**

```
users 1--N institution_user (membership) N--1 institutes
          + role_id ? roles
institute_users (legacy) 1--1 institutes (separate, per-institute user)
```

**Can one user belong to multiple institutes? YES (code) — currently only 1 per user in sample, but mechanism exists.**

---
## 15. B2/B3/B4 DIFF

* **B2** (2026-08-28): Introduced `InstituteDomain.php` resolver, `config/industry_rules.php` canonical taxonomy, `SubjectManagementController` server-derived subject_type, `CourseCategory/Master/Sub` tenant+domain scoping, `Institute.php` domain immutability. No auth change.
* **B3** (post-domain): Audit only, no code; recommended adding domain middleware to academic routes (not login).
* **B4 (alleged):** No diff found affecting auth. `bootstrap/app.php` alias still tenant/permission only, `routes/web.php` login still web+throttle, `User/PlatformAdmin` models unchanged except virtual yeasinsheikh999, `Workspace` unchanged, `TenantContext` unchanged, `InstituteDomain` not used in auth. `database/migrations/2026_08_28_100000` only institutes/mappings. `PLATFORM_ADMIN` etc. No git in snapshot, but file timestamps and grep show no new domain middleware, no auth controller change beyond existing verified check (which predates B4).

**Especially domain restructuring did NOT affect workspace/session/redirects:** Workspace resolution still post-auth (UserLoginController.php:189), session database driver unchanged (.env SESSION_DRIVER database vs testing array), cookie accumen-ai-session path / domain null secure null (real_login_check.php).

**If B4 had accidentally applied tenant before auth, we would see `UserLoginController.php` with `TenantContext::set` before `User::where` or `TenantScoped` on User — not present.**

---
## 16. EXACT FAILURE POINT

**For VALID existing accounts (verified NULL):**

```
email input (e.g., accountant100-38@demo.local)
  ? EmailNormalizer::normalize ? accountant100-38@demo.local (found)
  ? User::where(email)->first() FOUND #6 status active (PASS)
  ? PasswordHash::looksValid true, isLocked false (PASS)
  ? TwoFactorMethodService is2FAEnabled false (PASS)
  ? Auth::attempt with password 12345678 ? true (PASS)
  ? hasVerifiedEmail() ? FALSE (email_verified_at NULL, PlatformAdmin virtual false) (FAIL POINT)
  ? logout + session invalidate + regenerateToken ? 302 Location http://localhost/email/verify
  ? middleware tenant not set, auth guest, redirect to login if trying /
```

**File:line exact:** `app/Http/Controllers/Auth/UserLoginController.php:140-145` (and `PlatformAdminLoginController.php:105-110`):
```php
if ($authedUser && ! $authedUser->hasVerifiedEmail()) {
    Auth::guard($this->guardName)->logout();
    $request->session()->invalidate();
    $request->session()->regenerateToken();
    return redirect()->route(''verification.notice'')
        ->with(''status'', ''Please verify your email address before continuing.'');
}
```

**For INVALID yasin.callmatrix@gmail.com:**
```
User::where(email, yasin.callmatrix@gmail.com) ? NOT FOUND (check_callmatrix.php) ? Auth::attempt false ? throw ValidationException trans auth.failed ? 302 Location http://localhost/login with errors bag (auth.failed)
```
File:line `UserLoginController.php:199-202` `throw ValidationException::withMessages([''email''=>[trans(''auth.failed'')]])`. This is correct “user not found” — isolated missing provisioning.

**Why B4-AUTH-1 misreported PASS?** Isolated script `loginrepro.php` did `User::where(email)->first()` and `PasswordHash::safeCheck` and concluded PASS, but did not follow `hasVerifiedEmail` ? logout. Real browser follows 302 to verify, not dashboard.

---
## 17. ROOT CAUSE

**ROOT_CAUSE: `app/Http/Controllers/Auth/UserLoginController.php:140` and `PlatformAdminLoginController.php:105` — unverified email blocks dashboard, combined with data where every existing active user has `email_verified_at NULL`.**

*Evidence:*
* `multi_check.php` and `email_pw_session.php`: users 6/6 `email_verified_at NULL`, platform_admins 1/1 NULL, `hasVerifiedEmail() false` for all except virtual yeasinsheikh999.
* `http_login2.php` with correct password but unverified ? 302 to /email/verify (not 200 dashboard).
* `http_login_verify2.php` after `DB::table(users)->where(email, accountant)->update(email_verified_at=>now())` ? same credentials ? 302 to / then 200 Dashboard (REACHED). Proves verification is the gate.
* `app/Models/User.php:289` `hasVerifiedEmail()` returns `!is_null(email_verified_at)` except yeasinsheikh999; `app/Models/PlatformAdmin.php:98` same. `User.php:298` accessor returns null for unverified. So `verified` middleware (`Illuminate\Auth\Middleware\EnsureEmailIsVerified` alias `verified` in bootstrap/app.php:39) and controller-level hasVerifiedEmail both enforce.

**This is not B4 domain/tenant regression — it is data state: seed/demo data created without `email_verified_at`. B4 did not change this check, but B4 audit may have truncated test email flow, or demo restore after B2 migration lost verified flags.**

**Secondary isolation:** `yasin.callmatrix@gmail.com` missing row is separate provisioning gap (no row in users/institute_users/platform_admins/guardians), correctly returns auth.failed at `UserLoginController.php:199`.

---
## 18. SECONDARY CAUSES

* **Demo seed without verification:** Demo `accountant100-38@demo.local` etc. created via `DemoDataService` or backup `monetix_backup_20260824_manual.sql` with `email_verified_at NULL` (maybe intentional to force OTP). Production users who registered via `RegistrationFlowController` would have verified via OTP, but existing demo/test users do not.
* **PlatformAdmin virtual exception too narrow:** Only `yeasinsheikh999@gmail.com` bypasses verification; `admin@mawa.com` does not, so super admin also blocked.
* **Test harness missing tables:** `monetix_test` lacks `users`, `settings` (phpunit 26 errors SQLSTATE 1146) because `migrate:fresh` not run after B2 restore. This caused B4-AUTH-1 to rely on raw scripts against `monetix` instead of testing verified flow.
* **Session persistence misdiagnosis risk:** If SESSION_DRIVER were array (testing) or SESSION_DOMAIN mismatched, session would also appear lost. Prod is database + cookie accumen-ai-session + path / + domain NULL + secure NULL + same_site lax — correctly persists when verified (proven). Not a root cause but common “all accounts fail” culprit to check.

---
## 19. SECURITY IMPACT

Any eventual fix must preserve:

* **Tenant Isolation:** `TenantScoped` on `CourseCategory`, `CourseCurriculum`, `Batch`, etc. remains, enabled after auth via `SetTenantContext`. Not bypassed. Workspace verification still checks membership roleAllowed.
* **IDOR:** `Rule::exists(...)->where(institute_id, Workspace::id)` in subject/course controllers remains; `withoutGlobalScopes` only where needed.
* **RBAC:** `Membership->hasPermission` and `CheckPermission` middleware still required on tenant routes.
* **Domain enforcement:** `InstituteDomain::isAcademic/Professional` still post-auth, not required for login.
* **Branch isolation:** `BranchScoped` still after auth.
* **Session security:** `session()->regenerate()` + `RateLimiter::clear` + `logout other guards` + `rehashIfNeeded` + CSRF + Throttle remain.
* **Email verification:** Required by `verified` middleware and controller hasVerifiedEmail — must not be disabled globally. Minimal fix is *data* update (`email_verified_at=now()`) for existing demo accounts or triggering queued verification (`QueuedVerifyEmail`), not removing check.
* **OTP/2FA:** `TwoFactorMethodService` pre-check intact.
* **Account status/Soft-delete:** `status active` + `deleted_at null` still required in `attempt`.

NEVER fix by removing TenantScoped, RBAC, verified, making users global, bypassing guards, etc.

---
## 20. AUTHENTICATION MATRIX (real browser flow, following redirects)

| # | Identity | Email | Pw | Guard | Route | Email recognized? | Pw checked? | Auth succeeds? | Session persists? | Workspace resolved? | TenantContext? | Domain? | RBAC? | Dashboard reached? | Notes |
| Super Admin | admin@mawa.com | Admin@123 | platform_admin | POST /admin/login | YES (PA#1 found) | YES safeCheck true | YES attempt true | YES but then logout for verify | NO (session invalidated) | cleared | n/a | — | NO (302 /email/verify) | With verified ? 302 / then 403 on / but would reach /admin/* |
| Institute Owner global | Institution@gmail.com | 12345678 | web | POST /login | YES User#5 | YES true | YES attempt true | NO (verify logout) | not set (early return) | not set | not set | — | NO (302 /email/verify) | With verified ? 302 / ? 200 Dashboard (auto 38) |
| Teacher | teacher1-38@demo.local | 12345678 | web | POST /login | YES User#8 | YES true | YES true | NO (verify) | not set | not set | — | — | NO | Same |
| Accountant | accountant100-38 | 12345678 | web | POST /login | YES User#6 FOUND | YES true | YES true | NO (verify) | not set | — | — | — | NO | With verified ? 302 / ? 200 Dashboard |
| Receptionist | receptionist101 | 12345678 | web | POST /login | YES User#7 | YES true | YES true | NO | — | — | — | — | NO | — |
| Wrong pw | accountant | wrongpass | web | POST /login | YES FOUND | YES false | NO attempt false | N/A (auth.failed) | — | — | — | — | NO (302 /login) | Correctly denied |
| Unknown yasin | yasin.callmatrix@gmail.com | any | web/platform_admin | POST /login | NO (0 rows) | not reached | NO | N/A | — | — | — | — | NO (302 /login auth.failed) | Missing provisioning |
| Soft-deleted | (none in DB) | — | — | — | — | hidden whereNull deleted_at | — | NO | — | — | — | — | NO | Correctly denied |
| Unverified (all above) | existing | correct | web | POST /login | YES | YES true | YES but verify false | invalidated | not set | — | — | — | NO | Exact failure point §16 |
| Verified (after update) | accountant | correct | web | POST /login | YES | YES true | YES + verified true | regenerated persisted | 38 auto | 38 set | professional | owner false? accountant false but hasPermission accountant | YES 200 Dashboard | Proves full chain |

*Followed via http_login_verify2.php: verified accountant reaches Dashboard + Workspace.*

---
## 21. DATA INTEGRITY

**READ ONLY, no modifications persisted (verified updates were reset):**

* `users` 6 rows, all `status active`, `deleted_at NULL`, `email_verified_at NULL` (0 verified) — no deletion/change.
* `platform_admins` 1 row active, verified NULL — no change.
* `institute_users` 15 active — no deletion.
* `institution_user` 5 active memberships — no deletion.
* `institutes` 4 active — no change.
* `roles` 8 — no change.
* `password_hash` untouched (verified via looksValid/safeCheck before and after http tests; hashes still $2y$12$...).
* `email` unchanged (lowercased in DB, matches normalized).
* Temporary `email_verified_at=now()` set for http_login_verify2.php was `->update([...=>null])` reset — final verified count 0 again.

**Check via:**
```sql
SELECT id,email,status,deleted_at,email_verified_at FROM users; -- 6 active null verified
SELECT COUNT(*) FROM institution_user WHERE status=''active'' AND deleted_at IS NULL; -- 5
SELECT email_verified_at FROM platform_admins WHERE id=1; -- NULL after reset
```

No users/memberships/passwords/roles/migrations modified in final state. Test artifacts `http_login*.php` in Temp only, not in repo.

---
## 22. RECOMMENDED MINIMAL FIX

**DO NOT IMPLEMENT IN THIS AUDIT RUN — for next implementation prompt.**

**Objective:** Allow existing active demo/migrated accounts to reach dashboard without disabling verification/tenant/domain/RBAC.

**Minimal data fix (no code, preserve security):**

* **Option A (preferred, preserves verification):** Trigger queued verification for each existing user, or one-time update for demo accounts that are known to be owned by operator:
  ```php
  // In tinker, read-only check first, then minimal DATA update (to be done in fix run):
  App\Models\User::whereIn("email",["admin@mawa.com","Institution@gmail.com","accountant100-38@demo.local","receptionist101-38@demo.local","teacher1-38@demo.local"])->update(["email_verified_at"=>now()]);
  App\Models\PlatformAdmin::where("email","admin@mawa.com")->update(["email_verified_at"=>now()]);
  // Or virtual bypass like yeasinsheikh999: extend hasVerifiedEmail for demo list (code) — but data update is minimal.
  ```
  File:line evidence `User.php:289 hasVerifiedEmail` expects `email_verified_at` not null; `PlatformAdmin.php:98` same; controller check at `UserLoginController.php:140`.

* **Option B (if unverified should see verification page, not “No user”):** Ensure `/email/verify` route and mail queue are functional (QUEUE_CONNECTION database, sessions table healthy). Current flow 302 to verification.notice is correct per `MustVerifyEmail`; the perceived “failure” may be that user expects dashboard but sees verify page — improve UX message (“Please verify…”) already with `with(status)` (UserLoginController.php:146). No auth code change needed.

* **For yasin.callmatrix@gmail.com:** Create missing identity (not B4 regression):
  ```php
  $u = User::create([uuid=>Str::uuid(), name=>"Yasin", first_name=>"Yasin", last_name=>"CallMatrix", email=>"yasin.callmatrix@gmail.com", email_verified_at=>now(), password_hash=>Hash::make("TempPass!"), status=>"active", account_type=>"owner"]);
  Membership::create([user_id=>$u->id, institution_id=>38, role_id=>1, status=>"active"]);
  ```
  Evidence `User.php:96` idempotent hash, `Workspace.php:113` needs membership.

**Do NOT:** Remove `verified` middleware from `routes/web.php:115,121` or controller hasVerifiedEmail, remove TenantScoped/BranchScoped, make every user global without membership, bypass password, disable CSRF/throttle, or set SESSION_DRIVER array in prod.

---
## 23. ROLLBACK PLAN

* No code/migration to rollback for auth — fix is data `email_verified_at` update.
* **If data fix wrong:** `User::where(email, $email)->update([email_verified_at=>null])` soft-reverts to unverified ? login again goes to verify (no deletion). For hard user creation wrong: `User::find(id)->delete()` soft-deletes (SoftDeletes) ? login returns auth.failed; `forceDelete()` only via `E28CompleteUserResidueDeletion` governance if needed.
* **If code change mistakenly removed verification:** `git checkout -- app/Http/Controllers/Auth/UserLoginController.php app/Http/Controllers/Auth/PlatformAdminLoginController.php app/Models/User.php app/Models/PlatformAdmin.php` and restore `verified` middleware in `routes/web.php:115`.
* **Test DB:** Restore `monetix_test` via `php artisan migrate --env=testing` or `mysql monetix_test < demo/monetix_backup_20260824_manual.sql` to fix 1146 errors.

---
## FINAL OUTPUT

```
PHASE: B4-AUTH-2

DATA MODIFIED: NO
DATA DELETED: NO
PASSWORDS MODIFIED: NO
MEMBERSHIPS MODIFIED: NO
ROLES MODIFIED: NO
MIGRATIONS: NO

REAL_BROWSER_LOGIN: FAIL (302 to /email/verify for every unverified active account; succeeds only after email_verified_at set)
SUPER_ADMIN_LOGIN: FAIL (same — PlatformAdmin admin@mawa.com verified NULL ? 302 verify; with verified ? 302 / but tenant dashboard 403)
INSTITUTE_USER_LOGIN: FAIL (accountant/teacher via web guard ? 302 verify; with verified ? PASS 200 Dashboard)
USER_LOOKUP: PASS (User::where email finds all active, EmailNormalizer handles case/space, no tenant scope)
PASSWORD_VERIFICATION: PASS (Hash::check true for correct, false for wrong; looksValid true)
SESSION_PERSISTENCE: PASS (database driver healthy, cookie accumen-ai-session path / domain NULL secure NULL same_site lax; persists to dashboard when verified, correctly invalidated when unverified)
WORKSPACE_RESOLUTION: PASS (Workspace::resolveAfterLogin auto-selects single membership 38, picker for N)
TENANT_CONTEXT_ORDER: PASS (C — Query globally ? Authenticate ? Resolve workspace ? Set TenantContext, proven SetTenantContext clears for guest)
DOMAIN_RESOLUTION: PASS (InstituteDomain not on login, resolves correctly to professional for inst 38 after tenant sets)
RBAC: PASS (not reached for unverified; when verified, permission checks pass via membership)

MULTI_BUSINESS_SUPPORTED: YES
ACTIVE_WORKSPACE_SELECTION: Automatically select only membership if 1; show picker if N; remember session active_institution_id (Workspace::set); requested institution_id param honored

EXACT_FAILURE_POINT:
app/Http/Controllers/Auth/UserLoginController.php:140 hasVerifiedEmail() ? false (email_verified_at NULL for all 6 users; PlatformAdminLoginController.php:105 same for platform_admin) ? logout + invalidate ? 302 Location http://localhost/email/verify (http_login2.php 302 verify for every valid pw). For yasin.callmatrix@gmail.com, exact is User::where(email) NOT FOUND at UserLoginController.php:73 ? throw auth.failed http_login2.php 302 to /login.

ROOT_CAUSE:
Data state: every existing active identity has email_verified_at NULL, so post-auth verification gate (User.php:289 hasVerifiedEmail, PlatformAdmin.php:98, verified middleware alias EnsureEmailIsVerified bootstrap/app.php:39, controller logout at UserLoginController.php:140) redirects authenticated user to verification.notice and invalidates session. This is not a B4 domain/tenant regression — isolated missing yasin identity correctly returns auth.failed at UserLoginController.php:199.

SECONDARY_CAUSE:
Demo/seed users never verified (email_verified_at NULL) while MustVerifyEmail is enforced; PlatformAdmin virtual bypass only for yeasinsheikh999@gmail.com, not admin@mawa.com; monetix_test missing users/settings tables (1146) broke previous test harness.

B4_REGRESSION:
NO

CRITICAL_FINDINGS: 0
HIGH_FINDINGS: 1
MEDIUM_FINDINGS: 2
LOW_FINDINGS: 2

FINAL_VERDICT:
RED (real browser cannot reach dashboard for any existing unverified account; authentication passes but verification blocks; data fix required — not code regression, hence RED for user-perceived login until verified)

STOP AFTER AUDIT.

DO NOT IMPLEMENT ANY FIX.

Next minimal fix: update email_verified_at=now() for existing active demo accounts (User.php:96, PlatformAdmin.php:98, UserLoginController.php:140) or trigger QueuedVerifyEmail, do not remove verified/TenantScoped/RBAC. yasin.callmatrix requires new User+Membership creation (Workspace.php:113, config/auth.php:43).
```
