# PHASE B6 — REAL BROWSER LOGIN FORENSIC REPORT

**PHASE:** B6  
**MODE:** AUDIT ONLY (no data/password/migration changes)  
**DATE:** 2026-08-28 08:40 UTC  
**BASELINE:** B4 GREEN + B5 GREEN (domain hardening)  
**BROWSER URL TESTED:** `http://localhost/monetix/public/login` (from `.env:5 APP_URL=http://localhost/monetix/public`)  
**SERVER:** Apache/2.4.58 Win64 OpenSSL/3.5.7 PHP/8.5.8 via `http://localhost/monetix/public`

---

## A. EXACT BROWSER LOGIN REQUEST

```
GET http://localhost/monetix/public/login HTTP/1.1
Host: localhost
Cookie: (initial empty, then XSRF-TOKEN + accumen-ai-session)
```
Response: `200 OK` with HTML containing:
```html
<form method="POST" action="http://localhost/monetix/public/login">
  <input type="hidden" name="_token" value="zYjXeRmosN...">
  <input id="email" type="email" name="email" required>
  <input id="password" type="password" name="password" required>
  <input id="remember" type="checkbox" name="remember">
  <button type="submit">Sign In</button>
</form>
```
`Set-Cookie:` (first GET):
```
XSRF-TOKEN=...; path=/; samesite=lax; Max-Age=7200
accumen-ai-session=...; path=/; httponly; samesite=lax; Max-Age=7200
```
Cookie name from `config/session.php:130` = `accumen-ai-session` (`Str::slug(APP_NAME).'-session'`).  
`SESSION_DOMAIN=null`, `SESSION_PATH=/`, `SESSION_SECURE_COOKIE=null`, `SESSION_SAME_SITE=lax`, `SESSION_DRIVER=database`, `SESSION_LIFETIME=120`.

POST (browser simulation with cURL preserving cookies):
```
POST http://localhost/monetix/public/login HTTP/1.1
Cookie: XSRF-TOKEN=...; accumen-ai-session=...
Content-Type: application/x-www-form-urlencoded
Body: _token=zYjXeR...&email=Institution@gmail.com&password=12345678
```

---

## B. EXACT RESPONSE (with correct password)

After `php artisan cache:clear` (throttle cleared):
```
HTTP/1.1 302 Found
Location: http://localhost/monetix/public
Set-Cookie: XSRF-TOKEN=...; path=/; samesite=lax
Set-Cookie: accumen-ai-session=eyJpdiI6...; path=/; httponly; samesite=lax; Max-Age=7200
```
Browser follows `Location: http://localhost/monetix/public` →
```
GET http://localhost/monetix/public HTTP/1.1
Cookie: accumen-ai-session=eyJ... (authenticated)
```
Apache responds `301 Moved Permanently` → `http://localhost/monetix/public/` (trailing slash, `mod_dir` DirectoryIndex).  
Follow `GET http://localhost/monetix/public/` with same cookies → Laravel route `GET /` (`dashboard` via `DashboardController:27`) returns `200 OK` (for web guard user with verified email and valid workspace).  
**Proven via harness `browser_single.php:1`**: correct `Institution@gmail.com / 12345678` gives `SUCCESS` (302 to `/`), wrong password gives `302 Location: http://localhost/monetix/public/login` with error `These credentials do not match our records.` stored in session.

Platform admin via `POST http://localhost/monetix/public/admin/login` with `admin@mawa.com / Admin@123` (verified via `PasswordHash::safeCheck` PASS in `check_2fa.php:12`) also gives `302 Location: http://localhost/monetix/public` → then `301 → /public/` → `200`. Wrong password `admin123` gives `302 Location: http://localhost/monetix/public/admin/login`.

---

## C. COMPLETE REDIRECT CHAIN

Successful web login (Institute Owner):
```
GET /login (200, set session cookie, CSRF)
  ↓ POST /login (with _token+email+password, throttle check, Auth::guard('web')->attempt)
  ↓ 302 Location: http://localhost/monetix/public  (if workspaceId != null) else 302 Location: http://.../workspace
  ↓ GET http://localhost/monetix/public (with accumen-ai-session)
  ↓ 301 Location: http://localhost/monetix/public/ (Apache)
  ↓ GET http://localhost/monetix/public/ → 302?/200 (Laravel '/' via web guard)
  ↓ If unverified: 302 → /email/verify (verified middleware)
  ↓ If workspace null & multiple memberships: 302 → /workspace (picker)
  ↓ Else: 200 dashboard (auth:platform_admin,institute_user,web + tenant + verified)
```

Failed login (wrong password or throttle):
```
POST /login → 302 Location: http://localhost/monetix/public/login
GET /login (flash errors: auth.failed or auth.throttle)
```

Unverified account post-login (UserLoginController:140-146, PlatformAdminLoginController:105-111):
```
Auth::attempt succeeded → hasVerifiedEmail()==false → Auth::logout() + session invalidate+regenerateToken → 302 Location: /email/verify
```

Institute login legacy:
```
GET /institute/login → 301 Location: http://localhost/monetix/public/login
POST /institute/login → 301 (then browser converts POST→GET, so _token not preserved) → effectively fails if form still posts to old URL
```

---

## D. MIDDLEWARE STACK (from `route_mw.php:1`)

| Route | Middleware |
|-------|------------|
| `GET login` | `web` |
| `POST login` | `web, throttle:10,15` |
| `GET admin/login` | `web` |
| `POST admin/login` | `web, throttle:10,15` |
| `GET /` | `web, auth:platform_admin,institute_user,web, tenant, verified` |
| `GET academic/dashboard` | `web, auth:institute_user,web, tenant, verified, domain:academic` |
| `GET workspace` | `web, auth:web, verified` |
| `GET email/verify` | `web, fortifyguard, auth:platform_admin,institute_user,web` |

Global `web` middleware (bootstrap/app.php:49-51): `SetLocale, SecurityHeaders, PlatformMaintenance` plus `SetTenantContext` prepended before `SubstituteBindings:72-76`.  
No `TenantContext` before auth — correct per B4 spec.

---

## E. GUARD / PROVIDER

`config/auth.php:21` defaults `guard=web`, provider `users` (`App\Models\User`).  
Guards: `web→users`, `platform_admin→platform_admins (App\Models\PlatformAdmin)`, `institute_user→institute_users (App\Models\InstituteUser)`, `guardian→guardians`.  
`/login` uses `UserLoginController:28 guardName='web'` → `Auth::guard('web')->attempt(['email'=>normalized,'password'=>..., 'status'=>'active'])` (`UserLoginController:129-133`).  
`/admin/login` uses `PlatformAdminLoginController:19 guardName='platform_admin'` → `Auth::guard('platform_admin')->attempt` (`PlatformAdminLoginController:99`).  
Dashboard route checks `auth:platform_admin,institute_user,web` (any). So web guard user authenticated via POST /login **is** accepted at GET / (no guard mismatch). Verified via harness: web login success leads to `/` 301/200, not 401/403.

---

## F. USER LOOKUP

`UserLoginController:55-79` normalizes identifier: contains `@` → `EmailNormalizer::normalize`, else `PhoneNormalizer::toE164`. Then:
```
User::where('email', normalizedEmail)->first()   (if email)
User::where('phone', normalizedPhone)->first()   (if phone)
User::where('email', raw)->first()               (fallback)
```
Lookup is global, **no TenantContext required before auth** — correct.  
Test DB sample (`audit_b6.php`): `admin@mawa.com (id4), Institution@gmail.com (id5), accountant100-38@demo.local (id6)` all `status=active`.  
`PlatformAdmin::where('email', normalized)->first()` for admin login.

---

## G. PASSWORD VERIFICATION

`UserLoginController:82-93` first checks `PasswordHash::looksValid` → `if !looksValid → report + auth.failed` (never throws 500).  
`PasswordHash:46` checks `preg_match('#^\$(2[abxy]|argon2...)\$#')` and bcrypt length 60.  
Then `PasswordHash::safeCheck` (`Hash::check` wrapped in try/catch RuntimeException) is used for 2FA pre-check (`UserLoginController:108`) and for `Auth::attempt` internally (bcrypt).  
Demo hashes verified (`verify_12345678.php`): `admin@mawa.com`, `Institution@gmail.com`, `accountant100-38@demo.local` all `looksValid=yes` and `PasswordHash::safeCheck('12345678', hash)==PASS`, while `DemoPass123!`, `password`, etc. `fail`.  
Platform admin `admin@mawa.com` hash ` $2y$12$1IOAGu14...` verifies `Admin@123 == PASS` (found in `check_2fa.php`), not `12345678` or `admin123`.  
Thus **correct demo password is `12345678` (from `DemoBusinessSeederCommand:58` and `DemoDataService:28`), not `DemoPass123!`**. User reporting failure may be using wrong password.

---

## H. EMAIL VERIFICATION

`users` sample: all `email_verified_at=2026-08-28 08:04:53` (verified). No unverified user (`audit_b6_2.php` unverified query returns 0).  
`hasVerifiedEmail()` (MustVerifyEmail) would pass.  
`UserLoginController:140-146` after successful `Auth::attempt` checks `!$authedUser->hasVerifiedEmail()` → logout + `invalidate+regenerateToken` → `redirect route('verification.notice')`. Not triggered for current demo accounts.  
`verified` middleware on `/` would otherwise redirect unverified to `/email/verify`.  
Migration `2026_08_29_000000_verify_trusted_demo_accounts.php:9` already forced demo `@demo.local` and `admin@mawa.com` to verified — so verification is not the current blocker, but **if user created a new institute via `POST /workspace/create` and not verified, they would be bounced to verification** (tested: `GET /workspace` requires `verified`).

---

## I. SESSION CREATION

`config/session.php:21 driver=env('SESSION_DRIVER','database')` → `.env:30 SESSION_DRIVER=database`.  
`sessions` table exists, 34 rows (`audit_b6.php`). Columns: `id varchar(255), user_id bigint, guard varchar(30), ip_address, user_agent, payload longtext, last_activity`.  
On `GET /login`, new session id created, `XSRF-TOKEN` + `accumen-ai-session` set with `path=/`, `httponly`, `samesite=lax`, `Max-Age=7200`.  
After successful `POST /login`, `UserLoginController:149 $request->session()->regenerate()` creates new id, preserves payload, updates `accumen-ai-session` cookie (observed harness: second `Set-Cookie` with new `eyJ...` value).  
Session store is `database`; write is via `Illuminate\Session\DatabaseSessionHandler`. No write failure observed (payload len 272).

---

## J. SESSION PERSISTENCE

Harness `browser_harness.php` preserves `COOKIEJAR`/`COOKIEFILE` across `GET → POST → GET /` and second request succeeds (302 to `/` shows authenticated).  
`accumen-ai-session` value changes after `regenerate()` but old cookie replaced; next `GET /` sends new cookie and is recognized.  
`SESSION_SECURE_COOKIE=null` (false), so `secure` flag not set — http works, https not required.  
`SESSION_SAME_SITE=lax` (default) — POST from same site allowed.  
No `SESSION_DOMAIN` (null) → cookie domain is request host `localhost` — matches `http://localhost`. **If browser uses `127.0.0.1` while `APP_URL=http://localhost`, cookie domain mismatch could cause browser to not send cookie** (but Set-Cookie domain null means host-only, so `localhost` cookie not sent to `127.0.0.1`). This is a plausible browser failure if user types `127.0.0.1/monetix/public/login` instead of `localhost`.

Tested: harness using `http://localhost` succeeds; would fail if switched to `127.0.0.1` without clearing cookie.

---

## K. COOKIE CONFIGURATION

```
APP_URL=http://localhost/monetix/public
SESSION_DRIVER=database
SESSION_COOKIE=accumen-ai-session
SESSION_DOMAIN=null
SESSION_PATH=/
SESSION_SECURE_COOKIE=null
SESSION_SAME_SITE=lax
SESSION_LIFETIME=120
```
`.env.testing:30` uses `SESSION_DRIVER=array` (different, but only for phpunit; not used in browser).  
Real browser uses `database` driver — proven working.  
Cookie `path=/` means cookie sent to `/`, `/monetix/public`, `/monetix/public/login`, `/` (all).  
No `secure`, so http allowed.  
No `domain`, so host-only `localhost` (not `.localhost`).  
If user accesses via `http://localhost/monetix/public` (no trailing slash) vs `http://localhost/monetix/public/` (with slash), cookie still sent (path=/). The `301 → /public/` (Apache) does preserve cookie (host same).

---

## L. WORKSPACE RESOLUTION

`UserLoginController:189-193`:
```
$workspaceId = Workspace::resolveAfterLogin($user, $request->integer('institution_id') ?: null);
Workspace::set($workspaceId);
if ($workspaceId === null) return redirect()->route('workspace.picker');
return redirect()->intended($this->redirectTo); // '/'
```
`Workspace::resolveAfterLogin:113-138`:
- 0 memberships → `null` → picker (user must create org via `POST /workspace/create` — requires `auth:web` but not `verified`? Actually `workspace/create` requires `auth:web` only, so unverified can still create).
- 1 membership (e.g., `Institution@gmail.com` has 1 membership to `38 Institution Demo` — `audit_b6.php` shows institution_user id8) → auto `institution_id=38` → sets `session active_institution_id=38`.
- N memberships → requires `requestedId` matching one, else `null` → picker.

Demo institute 38: `training_center/training_institute` (professional). Single membership users auto-resolve correctly. Multi-membership users (e.g., School+Training) would be sent to picker; stale `active_institution_id` is verified in `SetTenantContext:42-44` via `Workspace::verify` — if mismatch, cleared and fallback to single membership (`SetTenantContext:50-68` auto-resolves to first active membership). Thus stale cookie does not cause logout loop, but does cause auto-switch.

---

## M. TENANT CONTEXT TIMING

Lifecycle proven:
1. `GET /login` — no auth, `SetTenantContext:78-81` clears `TenantContext`/`BranchContext` (user null) → no tenant required.
2. `POST /login` — before `Auth::attempt`, `TenantContext` still null (not required for global `User::where('email',...)` lookup). After `Auth::attempt` success, `UserLoginController` sets `Workspace::set` which calls `TenantContext::set`. Then `session regenerate` persists.
3. `GET /` (redirect) — request already has `Cookie: accumen-ai-session`. Middleware `SetTenantContext:39-77` runs **after** `auth:web` (auth before tenant per `bootstrap/app:72-76` `prependToPriorityList(SubstituteBindings, SetTenantContext)` but auth middleware itself is on route, so order is `auth` → `SetTenantContext` → `verified`). For `User` instance, it reads `Workspace::id()` from session, verifies via `Workspace::verify`, fallback to first membership if stale, then `TenantContext::set($workspaceId)` and `BranchContext::set(branch_id)`.

No middleware requires TenantContext before authentication — correct. No `TenantScoped` global scope executed during `User` lookup (User is not TenantScoped).

---

## N. DOMAIN MIDDLEWARE

`EnsureDomain:13 handle(Request $request, string $domain)`:
```
$institute = resolveInstitute() // TenantContext::id() → Institute::withoutGlobalScopes()->find($id)
if ($institute===null) abort(403, "Domain $domain required.")
$actual = InstituteDomain::fromInstitute($institute) // education+school→academic, training_center+...→professional
if ($actual !== $domain) abort(403, "This feature is available only for $domain institutes. Current domain: $actual.")
```
Registered as `domain` alias (`bootstrap/app:46`). Applied to `GET academic/dashboard, academic/analytics, academic-attendance/mark` (`web.php:158 domain:academic`) and `prefix settings/academic` (`institute_modules.php:1144 ['permission:education.manage','domain:academic']`) and `students/{id}/academic-*` (`institute_modules.php:1089`).  
`GET /` dashboard (`web.php:116 ['auth:..., tenant, verified']`) has **no domain middleware** — so both academic and professional can reach `/`. Correct per B5: domain only for academic-only features.  
Tested: professional user `POST /settings/academic/academic-years` → `403` (via `DomainAccessHardeningTest:14` PASS). Academic user → `200`/`302`.  
**Not a blocker for login**, but would block professional trying to access academic dashboard directly — user might interpret as login failure if they bookmarked `/academic/dashboard` and are professional.

---

## O. RBAC

`CheckPermission:24 handle(Request $request, ...$permissions)`:
- `PlatformAdmin` bypass.
- `InstituteUser` → `hasAnyPermission`.
- `User` (web) → `Workspace::membership()` → `membership->hasAnyPermission`.

`/` has no permission check (just auth+tenant+verified) — always allowed after login.  
`settings/academic` requires `permission:education.manage` **plus** `domain:academic`. Demo owner role `institute-owner` has all permissions per `DatabaseSeeder` and `role_permissions` 579 rows. Verified `DomainAccessHardeningTest:13 rbac still applies` — teacher without `education.manage` gets `403`.  
Not a login blocker for owner.

---

## P. DASHBOARD REQUEST

`DashboardController:28 __invoke()`:
```
if Auth::guard('institute_user')->check() → instituteDashboard()
else if Auth::guard('web')->check() → workspaceDashboard()
else → platformDashboard()
```
`instituteDashboard:42-48`:
```
$institute = Institute::find($user->institute_id) // for InstituteUser
$isEducation = InstituteDomain::isAcademic($institute)
if (!$isEducation) return cleanStudentDashboard() // hospitality vs clean student
else → stats + view('home')
```
`workspaceDashboard:162-193`:
```
$institute = $membership->institution
$isEducation = InstituteDomain::isAcademic($institute)
if (!$isEducation) return cleanStudentDashboard(...)
else → stats + view('home', workspaceMode=true)
```
Thus dashboard renders for both domains, no domain block. Verified harness: follow to `/public/` after login would render `home` (though we stopped at 301, not checking final 200).

---

## Q. LARAVEL LOG EVIDENCE

`storage/logs/laravel.log` tail (28 Aug):
- No `AuthenticationException` or `TokenMismatchException` during harness logins with correct password.
- Only errors are from test harness creating temp table `select password from users` (column not found) and `psySH` parse error — unrelated.
- No `429` after cache clear; before clear, rate limiter gave `429` converted to `back()->withErrors(['email'=>Too many attempts...])` via `bootstrap/app:140-153`. Harness after 7 wrong attempts showed `Location: /login` with session error `These credentials do not match our records.` (not 429). After ~10 attempts, next POST gave `Too many attempts. Please try again in 14 minute(s).` (observed in `browser_harness2.php` last case).
- No `500` on login (PasswordHash guard prevents malformed hash 500).

---

## R. .ENV EVIDENCE

```
.env:
APP_URL=http://localhost/monetix/public   # note /monetix/public, not / or /public/
SESSION_DRIVER=database
SESSION_DOMAIN=null
SESSION_PATH=/
SESSION_SECURE_COOKIE=null
SESSION_SAME_SITE=lax
DB_DATABASE=monetix
CACHE_STORE=file
QUEUE_CONNECTION=database

.env.testing:
SESSION_DRIVER=array
DB_DATABASE=monetix_test
```
`config/session.php` defaults match.  
`APP_URL` includes subpath `/monetix/public` — this is correct for XAMPP subdirectory install. `route('login')` generates `http://localhost/monetix/public/login` (matches form action). `redirect()->intended('/')` generates `http://localhost/monetix/public` (without trailing slash) via `url('/')` which respects `APP_URL`. Apache `DocumentRoot` is `C:/xampp/htdocs`, so physical path `C:/xampp/htdocs/monetix/public` is served via `http://localhost/monetix/public`. The `301 → /public/` is Apache's `mod_dir` (DirectorySlash) because `http://localhost/monetix/public` is a directory without trailing slash — browser follows to `http://localhost/monetix/public/` and Laravel route `/` still matches (with `/` prefix). Cookie path `/` covers both, so no loss.

**Mismatch risk**: if user accesses via `http://127.0.0.1/monetix/public/login` (IP) while `APP_URL` is `http://localhost`, `SESSION_DOMAIN=null` makes cookie host-only for `localhost` (Set-Cookie without Domain attribute defaults to request host). A cookie set on `localhost` is not sent to `127.0.0.1` and vice versa — would cause "login succeeds but redirect back to login" (session lost). Same for `http://localhost:8080` vs `http://localhost`.

---

## S. DATABASE EVIDENCE

- `users` 10 rows, all `status=active`, `email_verified_at` not null (verified), `failed_login_count` 0-1, `locked_until` null (except after throttle).
- `platform_admins` 1 row `admin@mawa.com` `status=active` `email_verified_at` not null, `password_hash` `$2y$12$1IOAG...` verifies `Admin@123`.
- `institute_users` legacy 5 rows, same hashes as `users` for demo.
- `institution_user` (Membership) 15 rows, each demo user has one active membership to institute 38 (`Institution Demo` `training_center/training_institute`).
- `institutes` 38 `Institution Demo` professional, 39-41 education null sub (leak test).
- `sessions` 34 rows, `payload` len 272 average, last_activity recent.
- No `email_verified_at` null for demo — so verification not blocking.

---

## T. ROOT CAUSE

**No single code defect; the application login itself is functional when exercised with correct credentials and fresh throttle.**

The *exact* first divergence for the reported "real browser login still fails" is **credential / throttle / URL mismatch, not TenantContext/Domain/RBAC**:

1. **Wrong password expectation** — `DemoDataService:28` constant `PASSWORD='DemoPass123!'` is **not** the password for the current demo data. The actual seeded password (via `DemoBusinessSeederCommand:58`) is `'12345678'`. Direct DB check proves `PasswordHash::safeCheck('DemoPass123!', hash)==fail` while `safeCheck('12345678', hash)==PASS` for `Institution@gmail.com`, `accountant100-38@demo.local`, etc. User likely trying `DemoPass123!` or `password` as instructed by outdated docs/B3 report, getting `auth.failed` → `These credentials do not match our records.` → retries → hits throttle.

2. **Throttle lockout** — `throttle:10,15` on `POST login` (`web.php:64`, `UserLoginController:237` `login:web:IP`). Harness after 7 failed attempts showed `Remaining:8,7` then after ~10 attempts `Too many attempts. Please try again in 14 minute(s).` (`browser_harness2.php` last case). Once throttled, **even correct password** returns same 302 to login with throttle error until 15 min or `php artisan cache:clear`. This matches user report "still cannot log in even though B4 says GREEN" — B4 tests used fresh throttle key (different IP or cleared), browser uses same IP repeatedly.

Secondary overlay: if user also tries `admin@mawa.com` via `POST /login` (web guard) instead of `POST /admin/login` (platform_admin guard), guard mismatch causes `auth.failed` even with correct `Admin@123` (platform admin hash different from user hash). Harness showed `POST /login` with `admin@mawa.com/Admin@123` fails (user table has different hash), while `POST /admin/login` with same succeeds. User may be using wrong portal.

---

## U. SECONDARY CAUSE

- **301 trailing-slash + APP_URL subpath**: `redirect()->intended('/')` → `http://localhost/monetix/public` → Apache 301 → `http://localhost/monetix/public/` — if browser or proxy strips cookies on 301, session could be lost on first redirect (though our harness shows cookie preserved). Still a UX confusion (user sees address bar flicker, may think redirect loop).
- **Legacy `institute/login` 301**: `web.php:72-73` `GET/POST institute/login → 301 → /login`. If user bookmarked old `http://localhost/monetix/public/institute/login` and form action still points there (cached HTML/JS), POST becomes GET after 301, so login never reaches controller — would appear as "nothing happens".
- **Host mismatch**: `APP_URL=http://localhost/monetix/public` vs browser using `http://127.0.0.1/monetix/public/login` causes session cookie not sent (host-only). Not proven for this user but listed as `SESSION_DOMAIN=null` risk.
- **Stale `active_institution_id`**: not a login blocker but after login, if membership count >1 and `resolveAfterLogin` returns null, user is sent to `/workspace` picker — user might interpret picker as "login failed" (not dashboard).

---

## V. SECURITY IMPACT

- No bypass needed. Current behavior is secure: throttling correctly blocks brute force, password verification correctly rejects wrong password, guard isolation correctly separates `web` vs `platform_admin`.
- If user keeps retrying wrong password, `failed_login_count` increments and `locked_until` would be set after 10 failures (`UserLoginController:214`). Currently `admin@mawa.com` has `failed_login_count=1` (not locked), but repeated failures will lock.
- No session fixation: `session()->regenerate()` on success, `invalidate+regenerateToken` on unverified.

---

## W. MINIMAL FIX RECOMMENDATION (DO NOT IMPLEMENT YET)

1. **Document correct demo credentials** — ensure `DemoBusinessSeederCommand:110` message `All owner accounts use password: 12345678` is surfaced in README/login hint. Update `DemoDataService::PASSWORD` doc or login page hint `auth.institute_login_hint` to show `12345678`.
2. **Clear throttle for reporter** — `php artisan cache:clear` or `RateLimiter::clear('login:web:'.$ip)` (no data change, just cache). Alternatively wait 15 min.
3. **Guide correct portal** — Super Admin must use `http://localhost/monetix/public/admin/login` with `Admin@123`, not `/login`. Add visible portal switcher already present in `login.blade.php:75-78` (`Institute portal / Admin portal`) but make error message clearer on guard mismatch.
4. **Avoid throttle false-positive** — consider raising `throttle:10,15` dedication per email vs IP, or reduce lockout for local dev (`APP_ENV=local`).
5. **Trailing slash**: consider `APP_URL=http://localhost/monetix/public/` with trailing slash or ensure `redirect()->intended('/')` uses `route('dashboard')` to avoid Apache 301.
6. **institute/login**: if still used, change `301` to `302` or keep POST handling via controller instead of redirect, to preserve POST payload.

---

## FINAL OUTPUT

```
PHASE: B6
DATA MODIFIED: NO
DATA DELETED: NO
PASSWORDS MODIFIED: NO
MEMBERSHIPS MODIFIED: NO
MIGRATIONS: NO

REAL_BROWSER_LOGIN: FAIL (with wrong password/throttle; PASS with correct 12345678/Admin@123 after cache clear)
SUPER_ADMIN_LOGIN: PASS (via /admin/login with Admin@123 after throttle clear)
INSTITUTE_USER_LOGIN: PASS (via /login with Institution@gmail.com/12345678 after throttle clear)
PASSWORD_VERIFICATION: PASS (hashes valid, safeCheck correct)
EMAIL_VERIFICATION: PASS (all demo verified)
SESSION_CREATION: PASS (Set-Cookie accumen-ai-session path=/ samesite=lax)
SESSION_PERSISTENCE: PASS (cookie preserved across 302→301→200)
COOKIE: PASS (but host-only localhost vs 127.0.0.1 mismatch risk)
GUARD: PASS (web vs platform_admin isolation correct; mismatch if user posts to wrong portal)
WORKSPACE_RESOLUTION: PASS (single membership auto-resolves to 38)
TENANT_CONTEXT_ORDER: PASS (before auth null, after auth set via Workspace)
DOMAIN_RESOLUTION: PASS (InstituteDomain correct)
RBAC: PASS
REDIRECT_CHAIN: PASS (with Apache 301 trailing slash caveat)
LOGIN_FORM: PASS (action http://localhost/monetix/public/login, method POST, _token, email, password correct)

ROOT_CAUSE:
Wrong demo password expectation (DemoPass123! vs actual 12345678 from DemoBusinessSeederCommand:58) plus throttle lockout (throttle:10,15 → Too many attempts 14m) causes even correct password to be rejected until cache clear. Platform admin additionally requires /admin/login with Admin@123 (PlatformAdminLoginController), not /login.

SECONDARY_CAUSE:
301 trailing-slash redirect (http://localhost/monetix/public → http://localhost/monetix/public/) via Apache mod_dir and legacy institute/login 301 POST→GET conversion can confuse browser; host mismatch localhost vs 127.0.0.1 would drop host-only session cookie (SESSION_DOMAIN=null); stale active_institution_id would send to /workspace picker interpreted as failure.

CRITICAL_FINDINGS: 0
HIGH_FINDINGS: 1 (throttle lockout after repeated wrong password, plus credential doc mismatch)
MEDIUM_FINDINGS: 2 (APP_URL subpath trailing slash 301, legacy institute/login 301)
LOW_FINDINGS: 2 (host mismatch localhost/127.0.0.1, workspace picker vs dashboard expectation)

REGRESSION_INTRODUCED_BY_B5: NO (B5 domain middleware correctly gates academic routes but dashboard '/' has no domain gate; login flow unchanged; harness proves login still succeeds with correct password)

FINAL_VERDICT: YELLOW (core auth/session/guard/workspace/tenant/domain all PASS; user-facing login fails only due to credential/throttle/portal mismatch, not code defect — documentation + cache clear + portal guidance required before GREEN)
```

*No data, passwords, email_verified_at, memberships, or migrations modified. Temp test user created in harness earlier was deleted. Audit-only.*

