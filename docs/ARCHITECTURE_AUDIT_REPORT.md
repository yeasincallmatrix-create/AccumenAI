# ACCUMENAI — ARCHITECTURE AUDIT REPORT

Read-only forensic audit, 2026-09-28. No application code, config, DB, or tests were modified (only `docs/*` files created). Every finding cites file evidence; speculation is marked UNKNOWN.

**Method**: static scan of all PHP/Blade/config/routes (grep + file reads), `php artisan route:list --json` (2156 routes), `database/schema/mysql-schema.sql` (427 tables), `composer.json`/`package.json`, `.gitignore`/git status, and safe test execution (`php artisan test --filter=...`).

**Severity key**: CRITICAL (exploitable now / cross-tenant or privilege break) · HIGH (material gap, likely exploit or data loss) · MEDIUM (weakness needing planned fix) · LOW (hygiene) · INFO (observation).

---

## Executive summary

The platform has a **strong tenancy foundation for non-medical modules** (middleware priority reorder, `TenantScoped` traits with write-protection hooks, workspace forgery detection, single journal writer, single stock writer) and a **clear weakness in authorization breadth and audit coverage**: most authenticated routes rely on authentication + tenant scope only, two finance deny-middleware fail open by design, two security/audit middleware are documented as active but are never registered, and five debug scripts that force-login a platform admin sit in the webroot and are git-tracked. The test suite is currently red (18/30 failures in a 3-class sample), which limits regression safety.

---

## A. Findings

### CRITICAL

**C-1 · Debug scripts in `public/` perform platform-admin impersonation and are publicly routable**
- Evidence: `public/_probe_admin.php` (`Auth::shouldUse('platform_admin'); Auth::loginUsingId(1);` then reads `PlatformAdmin` preferences and renders views), `public/_dash_theme_test.php` (same impersonation + full `DashboardController` HTML render), `public/_pagination_verify.php` (impersonation + `Course`/`Subject` dumps), `public/step127_safety.php` (row counts for ~23 tables), `public/step127_verify.php` (schema/column dump).
- All five are **git-tracked** (`git ls-files public` returns them) and **directly executable**: `public/.htaccess` only rewrites non-existent paths (`RewriteCond %{REQUEST_FILENAME} !-f`), so `GET /_probe_admin.php` runs outside the Laravel middleware stack (no `SecurityHeaders`, no auth, no throttling).
- Impact: unauthenticated privileged-code execution inside a request (impersonates platform admin id 1), admin-page rendering and DB/schema disclosure. In any environment where `public/` is the docroot and these files ship, this is an immediate breach.
- Recommendation: delete the five files (or move to `scripts/` outside docroot), remove them from git history before any public deploy, and add a CI check that fails on `public/*.php` other than `index.php`.

### HIGH

**H-1 · 706 of 2102 authenticated routes have no permission/module/feature gate**
- Evidence: route:list analysis — 2102 routes carry `Illuminate\Auth\*`; 1396 carry `CheckPermission`/`CheckModuleAccess`/`CheckFeatureAccess`/`MedicalModuleAccess`; **706 carry none**. Controller-level authorization exists only in ~17 controllers: `Gate::authorize()` 50 call sites in 12 controllers (finance/settings/accounting only), `requirePermission()` 19 call sites in 5 controllers (`Purchase/ExpenseController`, `Purchase/BillPaymentController`, `Sales/ReceivePaymentController`, `Sales/SalesInvoiceController`, `Sales/SalesReceiptController`), plus per-controller `can()` helpers in `Hr/*` and `AcademicAnalyticsController`.
- Ungated areas observed: most `accounting/*` reports/bank-feed, `academic/*` structure routes, `account/security/*`, `documents/*`.
- Impact: any authenticated user of the institute (including low-privilege roles) can reach these endpoints; protection is authentication + tenant scope only (cross-institute safe, cross-role unsafe).
- Recommendation: default-deny — add `permission:`/`module_access:` middleware per route group, or controller `authorize()`; start with `accounting/*`, `academic/*`, `documents/*`.

**H-2 · Finance deny-middleware fail open by design**
- Evidence: `app/Http/Middleware/FinanceWriteGate.php` docblock: *"Everyone else fails OPEN … because the role→permission grant matrix is empty in seeded data"*; only `receptionist`/`branch-manager` denied. `DenyTeacherFromFinance.php` docblock: *"Unknown user types / missing methods fail OPEN (current behaviour)"*. Both are applied on 1057 routes each, yet they only block a named deny-list.
- Impact: they are documented as finance write protection but provide no positive authorization; combined with H-1 they can be mistaken for a control that does not exist.
- Recommendation: treat as compensating controls only; backfill the role→permission matrix and switch to positive `permission:` checks.

**H-3 · Security middleware documented as active are never registered**
- Evidence: `app/Http/Middleware/AuditActivityLog.php` — no reference anywhere except its own file and `tests/Feature/AuditActivityLogTest.php` (which unit-tests it via reflection). `app/Http/Middleware/BlockPlatformAdminEscalation.php` — referenced only by tests and docs (`docs/PHASE_12_SECURITY_AUDIT.md:11,75` claims "defense-in-depth"; `docs/ROADMAP_CONSOLIDATED_V1.md:118` marks it "Done"); **not present** in `bootstrap/app.php` aliases, global web append (lines 69-75), or route middleware.
- Documentation ≠ Implementation: audit/roadmap docs describe protections the running app does not have.
- Impact: no generic mutation audit trail; request-field privilege-escalation blocking (`is_owner`, `guard`, `role` injection) is absent in production paths.
- Recommendation: either register both middleware or correct the docs; decide deliberately (SEC-002 in `memory.md:290` already tracks the incomplete `in_array` check).

**H-4 · Medical/HMS: 77 models with no uniform tenant scope**
- Evidence: `app/Models/Medical/*.php` — `addGlobalScope` appears 0 times; `LabOrder.php` comment: *"intentionally NOT TenantScoped"*; only `LabResult.php`/`LabTest.php` use `TenantScoped`. Isolation depends on manual `->where('institute_id', MedicalScope::instituteId())` (pattern at `PatientController.php:68,207,302,463`; `MedicalScope` used across 52 files).
- Impact: a single omitted `where()` in a new medical query leaks across institutes. This is the largest module (490 routes, 65 controllers) and the least mechanically protected.
- Recommendation: extend `TenantScoped` to medical models where safe, or add an automated test that fails any medical controller/service query without `MedicalScope`/`institute_id`.

**H-5 · Mass assignment: 255 of 421 model files use `$guarded = []`**
- Evidence: count of `protected $guarded = [];` in `app/Models/**` = 255; `protected $fillable` = 162.
- Impact: any unvalidated `Model::create($request->all())` path lets clients set `institute_id`, `role`, `status`, etc. Mitigated (not eliminated) by `TenantScoped` write hooks that force/revert `institute_id` and `created_by`.
- Recommendation: prefer explicit `$fillable` for models with mutable money/role/status fields; enforce FormRequest validation on all store/update routes.

**H-6 · Test suite currently red — regression safety compromised**
- Evidence (executed): `php artisan test --filter="CertificateIndexAuthorizationTest|TenantProtectionTest|AccountingOwnerStaffTenantSafetyTest"` → **18 failed, 12 passed (39 assertions)**. Failure types: `CertificateIndexAuthorizationTest` expects 200 gets 403 (3 tests); `TenantProtectionTest` missing `audit_logs` rows (4 tests) and a `QueryException` on `owner deletion is protected`; `AccountingOwnerStaffTenantSafetyTest` — mostly `ValidationException` where `ModelNotFoundException` expected, plus FK violation `tenant_recovery_archives_institute_id_foreign` (root cause UNKNOWN: possible test-fixture/schema drift in `monetix_test`, or a real regression in `TenantProtectionService`). `tests/Unit/IndustryRulesTest.php` → 5 failed / 3 passed (reproduced).
- Impact: cannot trust green/red signals; tenant-safety tests themselves failing.
- Recommendation: fix or quarantine with tickets before any refactor; document the known-failing baseline in `phpunit.xml` or a `tests/KNOWN_FAILURES.md`.

**H-7 · `InvoicePaid` event never dispatched (payment side-effects missing)**
- Evidence: 0 dispatch sites for `new InvoicePaid` / `InvoicePaid::dispatch` under `app/`; `app/Events/InvoicePaid.php` + `app/Listeners/LogInvoicePaid.php` exist. Contrast: `JournalPosted` **is** dispatched at `app/Services/Accounting/JournalPostingService.php:103`.
- Impact: any listener logic (audit, notifications, analytics) for invoice payment silently never runs — PLANNED/UNUSED code that reads as implemented.
- Recommendation: dispatch from `Accounting/InvoiceService` payment path or delete the event+listener pair.

### MEDIUM

**M-1 · Unauthenticated surface = 54 routes** (all public/entry points, verified): 27 auth entry (`login`, `register*`, `logout`, `institute/register`, `guardian/login`, `admin/login`), 9 Livewire asset endpoints (`livewire-e025ea8a/*`), 6 framework/static (`up`, `storage/{path}` ×2, `broadcasting/auth`, `sanctum/csrf-cookie`, `dev/page-marker`), 6 geo/verification APIs (`geo/units`, `geo/levels/{country}`, `verify/certificate*`, `api/login`, `api/verify/certificate/{number}` — the `api/*` pair throttled `10,1`), 5 lab-device endpoints (`api/lab-gateway/*`, `AuthenticateLabDevice` + `throttle:30,1`), home page `/`.
- Concern: `dev/page-marker` (dev tool) and `storage/{path}` are public with no throttle; `broadcasting/auth` relies on `routes/channels.php` checks (review when adding channels).
- Recommendation: remove `dev/page-marker`, throttle `storage/{path}`, confirm certificate verification rate limits under load.

**M-2 · `AccountingOwnerStaffTenantSafetyTest` semantics changed** — cross-institute/branch probes now throw `ValidationException` where `ModelNotFoundException` was expected (`AccountingOwnerStaffTenantSafetyTest`). Either validation short-circuits 404s (behaviour change) or route binding now 422/redirects. UNKNOWN which is correct; needs a product decision documented in tests.

**M-3 · Secrets and debug config in local environment** — `.env` contains live credentials (Gemini API key, reCAPTCHA, SMTP, AWS, bKash) — reported as `[REDACTED]` everywhere; `APP_ENV=local`, `APP_DEBUG=true` (`.env`) — must not ship to production; `.env.testing.tmp` exists on disk (gitignored). `.gitignore` correctly covers `.env*`, `opencode.json`, `/demo/`, `*.sql`, `tmp_*.php`.
- Recommendation: add a deploy check asserting `APP_DEBUG=false` + no default keys.

**M-4 · `redirectGuestsTo` sends accounting dashboard guests to platform admin login** — `bootstrap/app.php:91-93`: `if ($routeIs('accounting.dashboard')) return route('admin.login');` — a staff user hitting `/accounting` unauthenticated is redirected to the **platform** admin login, not the institute login (UI confusion; possible user-type confusion attack surface).
- Recommendation: redirect to `login` (institute) instead.

**M-5 · Stale human documentation contradicts code (DOCUMENTATION ≠ IMPLEMENTATION)** — `docs/audit/00-overview.md` claims no Livewire/API/Sanctum (all present); `docs/structure.md` points at root `C:\xampp\htdocs\monetix`; `phpunit.xml` comment "17 migrations cover HMS deltas only" vs **240 migrations**; `docs/ROADMAP_CONSOLIDATED_V1.md` marks `BlockPlatformAdminEscalation`/`AuditActivityLog` as done (see H-3).
- Recommendation: mark those files deprecated at the top; trust this AI package + code.

**M-6 · Naming inconsistencies** — tenant table `institutes` vs FK column `institution_id` vs membership table `institution_user` (singular) vs session key `active_institution_id`; scope names differ per trait (`AccountGroup.php:109` notes scope is `institute`). Nothing broken today, but a frequent source of wrong-join bugs.
- Recommendation: enforce `institute_id` for columns, `institution_*` only where legacy; document in `docs/TENANT_NAMING_CONVENTION.md` (exists).

**M-7 · Route duplication / dual surfaces** — `sales/quotations` and `sales/estimates` alias the same controller; `finance/*` (132) and `accounting/*` (66) provide overlapping report/invoice/payment surfaces; `settings/*` (183) mixes tax, notifications, themes, security. Increases the probability of gate inconsistency (feeds H-1).
- Recommendation: designate canonical prefixes and redirect/alias the rest.

**M-8 · Authorization fail-open helpers in `Membership`** — `roleAllowedForAccountType()` returns true when user/role is null; `hasPermission()` returns true unconditionally for the owner role (intended); `CheckPermission.php:44` may fall back to the user's first active membership. Documented in code, but combined with H-1 gives permissive defaults.
- Recommendation: fail closed on null; require explicit active-membership resolution.

### LOW

**L-1 · Dead/unused artifacts**: `app/Http/Middleware/AuditActivityLog` (unregistered), `InvoicePaid` + listener (never dispatched), `app/Services/Sales/SalesInventoryIntegration` (0 callers), guard `platform_staff` (0 routes; `SetFortifyGuard` resolves only web/platform_admin/institute_user), `.bak` files (`app/Models/Attendance.php.bak`, `config/queue.php.bak`), `LegacyUser` model (usage unverified — UNKNOWN).
**L-2 · Scratch files in repo root**: `tmp_coa_verify.php`, `tmp_scale.php`, `tmp_task_b_report.json`, `test_results.txt`, `.env.testing.tmp` — all gitignored but present on disk.
**L-3 · No CI**: `.github/` does not exist; no automated gate for the H-1/H-6 issues (CHANGELOG defers CI/CD, monitoring).
**L-4 · Planned modules expose config without code**: POS (`config/pos.php` + 5 registry migrations), Manufacturing, Real Estate, Restaurant — 0 routes/controllers/models; registry migrations already flipped `module_registry` rows. Risk: entitlement UIs can show modules that cannot function. (Registry-level tests only: `PosPhase*Test`, `ManufacturingModuleTest`, `RealEstatePhase*Test`, `RestaurantPhase*Test`.)
**L-5 · Infrastructure limits**: queue=`database`, cache=`file`, session=`database`, broadcast=`log` — fine for dev/small prod, no Redis/Reverb configured for real-time (`BROADCAST_CONNECTION=log` while Reverb composer dep exists — Reverb usage UNKNOWN).

### INFO (verified positive controls)

- Middleware priority reorder prevents cross-tenant route-model binding (`bootstrap/app.php:98-104`).
- `TenantScoped` forces/repairs `institute_id` + `created_by` on write (223 models); `BranchScoped`/`BranchScopedOrShared` for 75 branch usages; intentional non-scoped global reference models (`AcademicLevel`, `Subject`, …) are commented as such.
- Single writers: `JournalPostingService` (all double-entry postings, fires `JournalPosted`), `InventoryStockService` (all `inventory_movements` writes, weighted average, row locks).
- `SecurityHeaders` + `AssignRequestId` + `SetLocale` + `PlatformMaintenance` globally appended (`bootstrap/app.php:69-75`).
- API stack: `auth:sanctum` + `ensure.institute.context` + `throttle:60,1` + `ForceJsonResponse`.
- Device auth separated from user auth (`AuthenticateLabDevice`, `lab.device` alias, throttled).
- Bcrypt rounds = 12; login/OTP/registration endpoints individually throttled; reCAPTCHA coverage tested (`LoginCaptchaTest`).
- Workspace forgery → 403 (`Workspace::verify`); guardian branch clearing is explicit (`SetTenantContext.php:31-35`).
- 427-table schema snapshot + 240 migrations + 74 idempotent seeders; test fixture `database/schema/full_data.sql`.

---

## B. Findings by area (matrix)

| Area | Status | Worst finding |
|---|---|---|
| Tenancy / isolation (non-medical) | IMPLEMENTED, strong | H-5 (mass assignment) |
| Tenancy (medical) | PARTIAL (convention-based) | H-4 |
| Authentication | IMPLEMENTED | C-1 (webroot impersonation), M-3 |
| Authorization / RBAC | PARTIAL, fail-open edges | H-1, H-2, H-8→M-8 |
| Audit trail | PARTIAL (per-module services only) | H-3 |
| Accounting integrity | IMPLEMENTED | H-1 (report routes), M-4 |
| Inventory integrity | IMPLEMENTED | — |
| Notifications / jobs | IMPLEMENTED | H-7 (missing dispatch) |
| Test suite | RED | H-6 |
| Docs vs code | CONFLICTING | M-5, H-3 |
| Planned modules | PLANNED | L-4 |
| Ops / CI | MISSING | L-3, L-5 |

---

## C. DOCUMENTATION ≠ IMPLEMENTATION checklist

| Claim | Source | Reality |
|---|---|---|
| `BlockPlatformAdminEscalation` active defense | `docs/PHASE_12_SECURITY_AUDIT.md:11,75`, `docs/ROADMAP…:118` "Done" | never registered in `bootstrap/app.php` (H-3) |
| `AuditActivityLog` middleware part of 17-middleware stack | `docs/ROADMAP…:132` | never registered; only reflection-tested (H-3) |
| "No Livewire, no API, no Sanctum" | `docs/audit/00-overview.md` | 21 Livewire components, 58 API routes, Sanctum on all API (M-5) |
| Repo root `C:\xampp\htdocs\monetix` | `docs/structure.md` | actual root `C:\xampp\htdocs\AccumenAI` (M-5) |
| "17 migrations cover HMS deltas" | `phpunit.xml` comment | 240 migrations (M-5) |
| Finance writes gated | `FinanceWriteGate` docblock | deny-list only, fails open (H-2) |
| POS / Manufacturing / Real Estate / Restaurant "phases" | config + registry migrations + tests | 0 routes/controllers/models (L-4) |

---

## D. Priority remediation order (recommendations only — not executed)

1. **C-1**: remove 5 `public/*.php` debug scripts from webroot and git history; add CI guard.
2. **H-6**: restore a green baseline for tenant-safety tests; publish known-failure list.
3. **H-1**: gate `accounting/*`, `academic/*`, `documents/*`, `account/security/*` with `permission:`/`module_access:` (largest exploit surface).
4. **H-3**: register or officially deprecate `AuditActivityLog` + `BlockPlatformAdminEscalation`; fix docs either way.
5. **H-4**: enforce `MedicalScope` mechanically (test or trait) for the 77 medical models.
6. **H-7 / L-1**: dispatch or delete `InvoicePaid`; delete dead middleware/service/guard or wire them up.
7. **M-4, M-8**: redirect fix + fail-closed null handling.
8. **L-3**: add CI running `php artisan test` + `route:list` diff + secret scan.

---

## E. Scope limits / UNKNOWNs

- Read-only: no code, config, DB, or dependency changes; only `docs/` files added.
- Not verified dynamically: HTTP reachability of C-1 (assumed per `.htaccess` semantics), Reverb broadcast usage, legacy `InstituteUser` guard paths, `LegacyUser` usage, OpenAI/Gemini key validity, bKash callback signature validation code path (`PaymentGateway/*` reviewed at file level only).
- Test failures: root causes for `TenantProtectionTest`/`AccountingOwnerStaffTenantSafetyTest` not fully isolated (fixture vs regression) — flagged UNKNOWN in H-6/H-2.
- Counts are from the 2026-09-28 `route:list` snapshot (2156 routes) and disk scan; they drift as code changes.
