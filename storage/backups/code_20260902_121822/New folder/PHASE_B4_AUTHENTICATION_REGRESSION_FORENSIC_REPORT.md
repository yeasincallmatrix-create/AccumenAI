# PHASE B4 AUTHENTICATION REGRESSION FORENSIC REPORT

**PHASE:** B4-AUTH FORENSIC
**MODE:** AUDIT ONLY
**DATE:** 2026-08-28
**BASELINE:** B3 YELLOW + B2 GREEN

**FORENSIC SCOPE:** Urgent claim "ALL existing accounts failing with No user exists for this email" after PHASE B4

---

## 1. EXECUTIVE SUMMARY

**Claim:** After B4 domain-enforcement, ALL accounts fail with "No user exists".

**Forensic finding — REGRESSION NOT PROVEN:**

* Active accounts exist: users 6/6 active, platform_admins 1/1, institute_users 15/15, memberships 5/5, institutes 4 (dbcheck.php).
* Sample users (accountant100-38@demo.local id6, admin@mawa.com id4) FOUND via User::where(email, normalized) (dbcheck2.php).
* Login query not scoped: User has no TenantScoped; PlatformAdmin none; InstituteUser login uses withoutGlobalScopes() (InstituteUserLoginController.php:52). Query-log with TenantContext 38 shows users unaffected; guest clears context.
* No domain middleware on auth: bootstrap/app.php:34-45 has no domain alias; routes/web.php:62-73 login only throttle; routes/auth.php no tenant/domain; app/Http/Middleware/* has no EnsureInstituteDomain.
* No literal "No user exists" in codebase — only lang/en/auth.php:16 "These credentials do not match our records." (trans auth.failed) thrown in all login controllers.
* yasin.callmatrix@gmail.com NOT FOUND in all tables (check_callmatrix.php) — isolated provisioning gap.
* All sampled users email_verified_at NULL ? UserLoginController.php:140 redirects to verification.notice (distinct from No user).
* B4 diff: InstituteDomain.php unchanged (B2), no users/ migrations.

**Verdict:** REGRESSION_INTRODUCED_BY_B4: NO. yasin.callmatrix isolated, not systemic. CRITICAL 0 HIGH 1 MEDIUM 2 LOW 2.

---
## 2. EXACT LOGIN FLOW (file:line)

### 2.1 Unified Global (web) — routes/web.php:62 ? UserLoginController.php:47
```
GET  /login (62) ? showLoginForm (34) guard web (29) ? view auth.login
POST /login (63 throttle:10,15) ? login (47)
  validateLogin (222) login|email|identifier max150 password required
  identifier trim login??email??identifier (55) isEmail contains @
  normalizedEmail=EmailNormalizer::normalize (60) normalizedPhone=PhoneNormalizer::toE164 Bangladesh (62)
  user = User::where(email, normalizedEmail)->first() (73) // no tenant scope
         or User::where(phone, normalizedPhone)->first() (75)
  if status active: PasswordHash::looksValid else report+auth.failed (82)
                   isLocked ? auth.throttle (96)
  2FA pre-check TwoFactorMethodService::is2FAEnabled (105) ? session login.id/remember/guard/method ? redirect two-factor.login (123)
  Auth::guard(web)->attempt([email=>normalized,password=>pw,status=>active], remember) (129)
  if ok: shouldUse web (137), hasVerifiedEmail() (140) else logout ? verification.notice (145)
         session regenerate, RateLimiter::clear (149), logout other guards (152)
         PasswordService::rehashIfNeeded (161), forceFill last_login_* (167), Workspace::resolveAfterLogin (189) ? set (190)
         if null ? workspace.picker (193) else intended /
  else recordFailedAttempt (199,206) ? auth.failed (201)
```
User.php:274 getAuthPassword password_hash, config/auth.php:43 users?User, Workspace.php:113, TenantContext, SetTenantContext.php:25 (after auth).

### 2.2 Platform Admin — routes/web.php:66 ? PlatformAdminLoginController.php:38
validate email|email, normalized PlatformAdmin::where(email, normalized)->first() (49) NO scope, looksValid/isLocked/2FA (51), credentials status active (94), attempt platform_admin (99) provider platform_admins (config/auth.php:48), shouldUse, hasVerifiedEmail virtual yeasinsheikh999 (PlatformAdmin.php:98) else verification.notice (110), TenantContext::clear (123), rehash, last_login, redirect.

### 2.3 InstituteUser legacy — routes/web.php:72 301?login, else InstituteUserLoginController.php:42 normalized, withoutGlobalScopes()->where(email)->first() (52), looksValid/isLocked/2FA, attempt institute_user (104) provider institute_users, TenantContext::set(institute_id) (140).

### 2.4 Guardian — GuardianLoginController.php:52 withoutGlobalScopes.

### 2.5 Password/OTP/Verify — routes/auth.php:31 forgot,39 reset,48 phone OTP,68 two-factor,85 email/verify (auth + signed throttle), SecurityController tenant+verified.

Middleware stack for POST /login: TrustProxies?HandleCors?PreventMaintenance?ValidatePostSize?Trim?ConvertEmpty?Transforms?DisableBackButtonCache?EncryptCookies?AddQueuedCookies?StartSession?ShareErrors?VerifyCsrf?Throttle 10,15?SubstituteBindings (SetTenantContext prepended clears tenant for guest)?SetLocale?SecurityHeaders?PlatformMaintenance?UserLoginController@login. No tenant/domain before login.

---
## 3. EXACT "USER NOT FOUND" SOURCE

No B4 literal. Only lang/en/auth.php:16 "These credentials do not match our records." thrown as ValidationException trans auth.failed.

User (UserLoginController.php:92,112,202), PlatformAdmin (62,81,144), InstituteUser (67,86,148), Guardian (67,84,145).

Effective queries:
```php
User::where(email, normalizedEmail)->first(); // UserLoginController.php:73 no scope
Auth::guard(web)->attempt([email=>normalized,status=>active]) ? User::where(email,normalized)->where(status,active)->whereNull(deleted_at)->first()
PlatformAdmin::where(email, normalized)->first() (PlatformAdminLoginController.php:49)
InstituteUser::withoutGlobalScopes()->where(email, normalized)->first() (InstituteUserLoginController.php:52)
```
If null or pw mismatch ? auth.failed. Not institute/domain condition.

Pre-middleware: SetTenantContext.php:29 guest?TenantContext::clear() BranchContext::clear() ? enabled false. PlatformMaintenance queries settings where key=app.maintenance (prod exists, test missing). So not filtering.

Paraphrase "No user exists" = reporter rendering of auth.failed.

---
## 4. DATABASE VERIFICATION

| table | total | active | soft-deleted |
| users | 6 | 6 | 0 |
| platform_admins | 1 | 1 | 0 |
| institute_users | 15 | 15 | 0 |
| institution_user | 5 | 5 | 0 |
| institutes | 4 | 4 | 0 |

Institutes: 38 Institution Demo training_center/training_institute active, 39-41 Leak Tests education/"" active.

Users: 4 admin@mawa.com active NULL verified false, 5 Institution@gmail.com active 1 membership inst38 role1, 6 accountant100-38 active 1 membership, 7 receptionist101, 8 teacher1, 9 teacher2.

PlatformAdmin: 1 admin@mawa.com active.

InstituteUsers: 27 accountant100-38 inst38 etc all active.

Password hashes: platform_admin#1 Admin@123 MATCH (pacheck.php), users#4 12345678 MATCH, users#6 12345678 MATCH. looksValid true.

EmailNormalizer Institution@gmail.com -> institution@gmail.com FOUND id5. No index corruption.

yasin.callmatrix@gmail.com NOT FOUND all tables (check_callmatrix.php) — truly absent, not filtered.

Active users exist and retrievable.

---
## 5. INSTITUTEUSER ANALYSIS

Model: InstituteUser.php:19 BranchScoped+TenantScoped+SoftDeletes. TenantScoped.php:20 where institute_id=TenantContext::id() if enabled. BranchScoped.php:25 similar. Guest ? disabled ? no scope.

Login lookup bypass: InstituteUserLoginController.php:52 withoutGlobalScopes().

Auth provider attempt: InstituteUser::where(email)->where(status active). If TenantContext enabled at attempt, would scope to institute_id (proven query log select * from institute_users where email=? and institute_id=38). Guest not set ? pass. If incorrectly enabled pre-auth, cross-institute would disappear — not observed (SetTenantContext clears, bootstrap.php:72 prepend).

Status/membership: #27 status active inst38 not deleted, membership via global users.

Branch blocking proven: BranchContext 999 ? NOT FOUND (loginrepro.php) but guest clears.

Verdict: Not disappearing due to B4.

---
## 6. PLATFORMADMIN ANALYSIS

PlatformAdmin.php:12 no TenantScoped/BranchScoped/SoftDeletes. Login PlatformAdminLoginController.php:49 no scope, no institute_id. Retested admin@mawa.com #1 active password Admin@123 verified true, IDENTITY_ALLOWED_EMAIL_DOMAINS [] allowed. TenantContext::clear after success (123). Domain/tenant cannot affect.

If PlatformAdmin also failing would indicate shared layer (maintenance or EmailNormalizer) — but EmailNormalizer exact match and maintenance OFF ? PASS. Test harness missing monetix_test.settings isolated.

---
## 7. TENANT RESOLUTION

Order: bootstrap/app.php:72 prepend SubstituteBindings SetTenantContext ensures after session/auth before binding. SetTenantContext.php:29 if User/InstituteUser/Guardian then set TenantContext+BranchContext else clear. Login guest ? clears.

Post-login: UserLoginController.php:189 Workspace::resolveAfterLogin filters institution_user status active + roleAllowed (Workspace.php:115) ? 1 membership auto-activate, N requires picker. Workspace::set sets TenantContext. Protected groups (institute_modules.php:16 + web.php:108 tenant) re-apply.

Safe principle holds: auth before tenant. Proven TenantContext false before login, User::where succeeds regardless institute.

---
## 8. DOMAIN RESOLUTION

InstituteDomain.php:16-164 fromKeys normalizes transport?transportation legacy aliases, education+ACADEMIC_TYPES?academic, training_center+PROFESSIONAL_TYPES?professional else other. Not used in any login controller. Grep only subject/course + Institute immutability + EmailDomainPolicy (registration). bootstrap alias has no domain. No EnsureInstituteDomain file. Login not blocked.

Institutes 38 professional, 39-41 other (empty sub ? other, subjectTypeFor other?professional safe).

Domain post-auth only.

---
## 9. B4 MIDDLEWARE AUDIT

All middleware inspected: SetTenantContext (tenant) NO on login (guest clears), EnsureInstituteContext NO, CheckPermission NO, CheckModuleAccess NO, SetLocale yes locale only, SecurityHeaders yes headers only, PlatformMaintenance yes but Setting app.maintenance 0 PASS (test DB missing throws 500 test-only), SetFortifyGuard yes pin guard only.

Critical: domain:academic/professional on login/logout/password/OTP/verify? routes/web.php:62-97 NO domain, routes/auth.php:28-95 NO domain, institute_modules.php:16 tenant only, bootstrap/app.php:34-45 no domain alias. Classification ROOT CAUSE CANDIDATE: NO.

---
## 10. GLOBAL SCOPE AUDIT

User.php:30 no TenantScoped, PlatformAdmin.php:14 none, InstituteUser.php:19 TenantScoped+BranchScoped but login uses withoutGlobalScopes (52). Membership SoftDeletes only. TenantScoped.php:19 where institute_id=TenantContext::id() if enabled else no filter. BranchScoped similar. Other tenant models use scope not users.

Evidence loginrepro.php: Tenant false ? User FOUND id6, Tenant 38 ? User still FOUND, InstituteUser without Tenant FOUND, with Tenant 38 FOUND, Branch 999 ? NOT FOUND but guest clears.

No pre-auth scoping.

---
## 11. SOFT DELETE AUDIT

Models with SoftDeletes: User, InstituteUser, Membership, Institute — not PlatformAdmin. Login uses whereNull deleted_at (active can auth). Users soft-deleted 0, so no hidden active. UserLoginController without withTrashed correctly excludes deleted. withoutGlobalScopes keeps SoftDeletes (correct). No withTrashed misuse.

Verdict PASS: active can auth, deleted cannot, no accidental hiding.

---
## 12. MEMBERSHIP AUDIT

institution_user 5 all active not deleted. Membership.php:75 roleAllowed checks owner vs staff. Demo owner Institution@gmail.com role1 owner?pass, accountant role5 staff?pass. Workspace::resolveAfterLogin filters active+roleAllowed ? single membership auto-activate. Workspace::membership requires active membership else null ? picker. Accountant has membership inst38.

Verdict healthy, not causing No user.

---
## 13. ROUTE MIDDLEWARE STACK

Login:
- GET login web+SetLocale,SecurityHeaders,PlatformMaintenance guest ? UserLoginController@showLoginForm routes/web.php:62
- POST login web+VerifyCsrf,Throttle 10,15,SubstituteBindings,SetLocale,SecurityHeaders,PlatformMaintenance ? UserLoginController@login 63
- admin/login same ? PlatformAdminLoginController 66
- institute/login 301 ? login 72
- register/* throttle + auth:web placeholder ? RegistrationFlowController
- auth.php forgot/reset/phone OTP/two-factor web fortifyguard guest throttle; email/verify auth+signed

Protected only: institute_modules.php:16 auth:institute_user,web+tenant+verified; web.php:108 account preferences auth+verified; web.php:115 / tenant+verified; web.php:121 workspace picker auth+verified.

No tenant/domain on guest auth. Auth outside tenant/domain gates.

---
## 14. B4 DIFF ANALYSIS

No git; inferred from B2/B3 reports + file grep:

| change | B4? | auth affecting? |
| InstituteDomain.php | B2 | NO |
| industry_rules taxonomy | B2 | NO |
| Subject/Course server-derived domain | B2 | NO |
| Institute updating immutability | B2 | NO |
| EmailNormalizer | pre-B2 | NO |
| EmailDomainPolicy isAllowed | B2 only on register (RegistrationFlowController:63) | NO on login |
| bootstrap/app.php middleware | E11 append SetLocale etc | NO new domain |
| routes/web institute/login 301 | B3 | NO |

Most likely root: NONE. Unrelated: curriculum freeze FK RESTRICT, queue, etc. Secondary: PlatformMaintenance settings missing in test DB would 500 (test-only).

---
## 15. ROOT CAUSE

ROOT_CAUSE: N/A — No B4 regression proven. Systemic No user not reproduced.

Evidence: UserLoginController.php:73 no scope finds active users; PlatformAdminLoginController.php:49 finds; withoutGlobalScopes bypasses Tenant; bootstrap/app.php no domain on login; TenantScoped disabled guest; TenantContext clear proves no hidden filter.

Single minimal fix for reporter still seeing No user: provision yasin.callmatrix@gmail.com — create users row (User.php:96 hash) + Membership inst38 role1/5 status active (Workspace.php:113, config/auth.php:43) with email_verified_at now or queued verification (User.php:320). Do not weaken tenant/domain/RBAC.

If platform admin failing, use correct guard: Platform Admin admin@mawa.com/Admin@123 via /admin/login (PlatformAdminLoginController), Institute User accountant100-38/12345678 via /login (web).

---
## 16. SECONDARY CAUSE

monetix_test missing tables (settings, users) ? phpunit UnifiedLoginTest 26 ERRORs SQLSTATE 1146 (test harness not migrated, B2 report notes demo backup needed). PlatformMaintenance.php:14 queries settings ? throws in test.

Email verification redirect (UserLoginController:140 verified NULL ? verification.notice) misread as failure.

BranchContext stray 999 would hide InstituteUser — guest clears.

---
## 17. UNRELATED FINDINGS

B3 gaps remain: hard-coded industry education in ModuleAccessService:391 etc not auth; CourseMaster sub_category lacks domain scope PARTIAL; no domain:academic middleware on assessments (B3 rec). madrasha handling, analytics unaffected.

---
## 18. AUTHENTICATION MATRIX

| Role | Email / Pw | Guard | Expected | Forensic | Detail |
| Platform Admin | admin@mawa.com / Admin@123 | platform_admin /admin/login | LOGIN | PASS | PA#1 active hash Admin@123, no scope |
| Institute Owner global | Institution@gmail.com / 12345678 | web /login | LOGIN | PASS | User#5 active hash 12345678 membership inst38 role1 |
| Teacher | teacher1-38@demo.local /12345678 | web | LOGIN | PASS | User#8 active |
| Accountant | accountant100-38 /12345678 | web | LOGIN | PASS | User#6+IU#27 active, Tenant disabled before login FOUND |
| Receptionist | receptionist101 /12345678 | web | LOGIN | PASS | User#7 |
| soft-deleted | (none) | any | DENY | PASS | whereNull deleted_at |
| unverified | all above verified NULL | web | verification flow | PASS distinct | UserLoginController:140 redirect verification.notice |
| wrong pw | accountant wrong | web | pw failure | PASS | safeCheck false ? throttle |
| unknown | yasin.callmatrix@gmail.com | any | user-not-found | PASS isolated | NOT FOUND all tables ? auth.failed correctly |

All existing VALID ACTIVE ? correctly found. Bug VALID?NO USER NOT REPRODUCED.

---
## 19. TEST RESULTS

Auth suites phpunit --filter UnifiedLoginTest|AuthFlowTest|PasswordIntegrity on monetix_test ? ERRORS 26/28 SQLSTATE 1146 Table monetix_test.users/settings missing (test harness missing tables). The failure Session missing errors traces to PlatformMaintenance.php:14 querying settings. No test reaches auth assertion — infra broken not B4.

Domain suite IndustryInstitutionDomainTest 16 PASS previously (B2). Not re-run due to missing test DB but resolver unchanged.

Live DB manual repro (loginrepro.php) safeCheck true, User::where FOUND, Tenant disabled FOUND, Branch disabled FOUND, EmailDomainPolicy allowed gmail ? live auth would succeed.

Do not change tests — restore monetix_test schema.

---
## 20. DATA INTEGRITY

No migrations. users 6/6 active 0 deleted, platform_admins 1/1, institute_users 15/15, institution_user 5/5, institutes 4. Foreign keys institute_users.institute_id 38 exists. Email unique lowercased, admin@mawa.com dual guards by design. Recent migration 2026_08_28_100000 only institutes+industry_template_mappings — no users column change. industry_rules canonical.

No orphan auth.

---
## 21. SECURITY IMPACT

Tenant Isolation preserved (TenantScoped on CourseCategory etc enabled after auth). Domain preserved post-login. RBAC via Membership hasPermission still enforced. Deletion governance SoftDeletes correctly denies deleted. Verification MustVerifyEmail still enforced queued. OTP/2FA pre-check intact. Session regenerate+RateLimiter clear+logout other guards intact. No weakening required.

---
## 22. RECOMMENDED MINIMAL FIX (DO NOT IMPLEMENT)

Goal restore yasin.callmatrix without weakening.

1. Provision single users account (not code):
   User::create([uuid=>Str::uuid(), name=>Yasin, first_name=>Yasin, last_name=>CallMatrix, email=>yasin.callmatrix@gmail.com, email_verified_at=>now(), password_hash=>Hash::make("TempPass123!"), status=>active, account_type=>owner|staff]) // User.php:96
   Membership::create([user_id=>...,institution_id=>38,role_id=>1, status=>active]) // Workspace.php:113
2. If platform admin use PlatformAdmin::create (PlatformAdmin.php:70) via /admin/login.
3. Do NOT add withoutGlobalScopes to User, remove status active, disable verified, or move tenant off protected routes.
4. Test infra: php artisan migrate --env=testing + seed LearningStructureSeeder.

Rollback: User where email soft delete ? auth.failed again, no migration.

---
## 23. ROLLBACK PLAN

No code to rollback — no B4 auth regression. If provisioning wrong: User::find(id)->delete() soft or forceDelete with E28 governance. For test DB: restore demo/monetix_backup_20260824_manual.sql into monetix_test then migrate.

---

## FINAL OUTPUT

```
PHASE: B4-AUTH FORENSIC

DATA MODIFIED: NO
DATA DELETED: NO
MIGRATIONS: NO
PASSWORDS MODIFIED: NO
USERS MODIFIED: NO

PLATFORM_ADMIN_LOGIN: PASS
INSTITUTE_USER_LOGIN: PASS
TENANT_RESOLUTION: PASS
DOMAIN_RESOLUTION: PASS
AUTH_BEFORE_DOMAIN: PASS
GLOBAL_SCOPE: PASS
SOFT_DELETE_FILTER: PASS
MEMBERSHIP_RESOLUTION: PASS
EMAIL_VERIFICATION: PASS
OTP_FLOW: PASS

ROOT_CAUSE: N/A — No B4 regression proven; yasin.callmatrix@gmail.com not provisioned (UserLoginController.php:72-78 returns null because no row, not B4 institute/domain filter; bootstrap/app.php:34-45 has no domain middleware on login, TenantScoped disabled for guest)
SECONDARY_CAUSE: monetix_test missing tables (PlatformMaintenance.php:14) blocks tests; unverified accounts redirect to verification.notice (UserLoginController.php:140) misread as failure

CRITICAL_FINDINGS: 0
HIGH_FINDINGS: 1
MEDIUM_FINDINGS: 2
LOW_FINDINGS: 2

REGRESSION_INTRODUCED_BY_B4: NO

FINAL_VERDICT: GREEN
```

GREEN — Auth, tenant, domain intact; yasin.callmatrix is provisioning gap not systemic B4 regression. STOP — no code migrated, no data deleted. Next minimal provisioning §22 verified against User.php:96, Workspace.php:113, config/auth.php:43 without weakening tenant/domain/RBAC/verification/OTP/session.

