# ACCUMENAI — AI DEVELOPMENT RULES

Working rules for an AI agent modifying this codebase. Every rule is grounded in an existing file (evidence in parentheses). Where a rule is aspirational rather than enforced by existing code it is marked **[CONVENTION — not enforced]**.

---

## 0. Hard constraints

1. **Never write secrets.** `.env` / `.env.testing` contain real API keys (Gemini, reCAPTCHA, SMTP, AWS, bKash). Reference config keys (`config/ai.php`, `config/services.php`), never literal values. Use `[REDACTED]` in docs. `.env`, `opencode.json`, `/demo/`, `*.sql` are gitignored — keep them that way.
2. **Never bypass tenancy.** Every institute-owned row must carry `institute_id`. Never add a global scope that skips it.
3. **Never expose data across institutes or roles.** Tests asserting this exist (`TenantProtectionTest`, `TenantIsolationAuditTest`, `AccountingOwnerStaffTenantSafetyTest`) — they must stay green.
4. **Do not break the double-entry invariant.** All money movement goes through `JournalPostingService` (post / reverse / void only; posted journals are immutable).
5. **Do not write stock directly.** `inventory_movements` is written only by `InventoryStockService`.
6. **Do not commit unless asked.** Do not add packages, change DB schema casually, or refactor unrelated code.

---

## 1. Where code goes

| Kind | Location | Example |
|---|---|---|
| HTTP layer | `app/Http/Controllers/**` | `app/Http/Controllers/Sales/SalesInvoiceController.php` |
| Domain logic | `app/Services/<Area>/` | `app/Services/Accounting/JournalPostingService.php` |
| Query/API orchestration | `app/Http/Controllers/Api/` | `Api/InventoryApiController` |
| Request validation | `app/Http/Requests/` (FormRequest) | `app/Http/Requests/Settings/*` |
| Responses (API) | `app/Http/Resources/` | 22 resources |
| Persistence | `app/Models/<Area>/` | `app/Models/Medical/Patient.php` |
| Model concerns (cross-cut) | `app/Models/Concerns/` | `TenantScoped`, `BranchScoped`, `BranchScopedOrShared` |
| Jobs | `app/Jobs/` | `SendNotificationJob`, `DepreciationRunJob`, `ProcessAnalyzerMessage` |
| Events/Listeners | `app/Events/`, `app/Listeners/` | `JournalPosted` → `LogJournalPosted` |
| Notifications | `app/Notifications/` | `QueuedVerifyEmail`, `EmailOtpMail` |
| Console | `app/Console/Commands/` (74) | `notifications:retry` |
| Routes | `routes/*.php` | `routes/web.php`, `routes/institute_modules.php`, `routes/medical.php`, `routes/api.php`, `routes/auth.php`, `routes/guardian.php` |
| Views | `resources/views/**` Blade | 974 views; layouts `institute`, `admin`, `standalone`, `app` |
| Livewire | `app/Livewire/` (21) | 4 React pages only under `resources/js/pages` |
| Migrations | `database/migrations/` | single module feature = one migration + one seeder |
| Config | `config/<feature>.php` | `config/pos.php`, `config/manufacturing.php` |

**There is no `routes/modules/*.php` split** — module routes live in `routes/web.php` + `routes/institute_modules.php` + `routes/medical.php`.

---

## 2. Routing rules

1. Group routes by module prefix already used by that module (e.g. `sales/*`, `purchase/*`, `hr/*`, `training/*`, `medical/*`, `settings/*`). Do not invent new top-level prefixes.
2. Web routes: append `->middleware([...])` per route; group-level middleware is declared in `bootstrap/app.php` route groups or route files (`routes/medical.php` wraps everything in `medical` + `medical.module`).
3. API routes only in `routes/api.php`, already wrapped in `auth:sanctum`, `ensure.institute.context`, `throttle:60,1`, plus `ForceJsonResponse`.
4. Adding a route requires deciding its authorization row: permission middleware (`CheckPermission`/`permission:slug`), module gate (`CheckModuleAccess`), feature gate (`CheckFeatureAccess`), or explicit `authorize()` in the controller. **At least one** — 706 routes currently have none (see audit report).
5. Controller-namespace route names use `Route::resource(...)->names('sales.invoices')` style already present; keep dot-names unique (route:list showed duplicates only because of alias prefixes — do not add more).

---

## 3. Tenancy & scoping rules

1. **Instinct:** column is `institute_id` (never `tenant_id`, never `organization_id`).
2. Context holders: `Support\TenantContext::instituteId()`, `Support\BranchContext::branchId()`, session workspace key `active_institution_id` (`Support\Workspace`).
3. `SetTenantContext` middleware runs **before** `SubstituteBindings` (bootstrap/app.php:101) so route-model binding already sees scoped models. Never reorder.
4. Two scoping regimes — pick the one the module uses:
   - **Trait-scoped models** (most of the app, 223 models): `use TenantScoped;` (+ `BranchScoped` or `BranchScopedOrShared`). Eloquent auto-filters; the trait's `creating/saving` hooks force `institute_id` and revert dirty `institute_id`/`created_by`.
   - **Medical models (76 files, `app/Models/Medical/*`)**: **NO trait, NO global scope.** Every query must manually add `->where('institute_id', MedicalScope::instituteId())` (pattern: `PatientController.php:68,207,302,463`). 52 files already use `MedicalScope`.
5. Global reference data is intentionally NOT scoped (`Subject`, `AcademicLevel`, `AcademicGroup`, `AssessmentType`, `ClassGrade`) — do not "fix" that.
6. Guardian portal clears branch context deliberately (`SetTenantContext.php:31-35`) — branch-restricted queries must not assume `BranchContext` there.
7. Writing a row: set `institute_id` explicitly in mass assignment if the model is not trait-scoped; for trait models rely on hooks but still pass the value when creating via `DB::table()` (bypasses hooks).

---

## 4. Authorization rules

1. Guards (`config/auth.php`): `web` (owner/staff), `platform_admin`, `institute_user` (legacy), `guardian`. **Never** authenticate institute UI with `platform_admin`, and vice versa. `SetFortifyGuard` picks the guard for login.
2. Owner = permission superuser: `Membership::hasPermission()` returns true for the `institute-owner` role. Staff are evaluated against `role_permissions` + `user_module_access`.
3. For new institute-facing endpoints add **all applicable** of:
   - `CheckModuleAccess` (`module_access:module`) — module enabled for institute,
   - `CheckFeatureAccess` (`feature:...`) — feature enabled,
   - `CheckPermission` (`permission:slug`) — RBAC, **and/or**
   - controller `$this->authorize(...)` against a Policy in `app/Policies/`.
4. Policy registration is manual: `AppServiceProvider::registerPolicies()` binds ~9 policies (Invoice, Payment, ChartOfAccount, Partner, Shareholder, ShareCapitalTransaction, Dividend, TdsDeduction, CorporateTaxComputation). **New policy ⇒ add a binding there.**
5. Beware documented fail-open paths:
   - `FinanceWriteGate` and `DenyTeacherFromFinance` are deny-lists with an empty/absent matrix — they do NOT grant protection by themselves.
   - `Membership::roleAllowedForAccountType()` returns true when user/role is null.
   - `CheckPermission` may fall back to the user's first active membership (`CheckPermission.php:44`).
   Treat these as gaps to close, not as permission grants to rely on.
6. Finance/HR/Medical writes should also be logged: `module_access_logs` + `AccountingAuditService` / `AccountingAuditTrail` patterns exist.

---

## 5. Business rules you must preserve

**Accounting**
- Journal lines must balance; posting is `JournalPostingService::post()`; corrections = `reverse()`/`void()` + new entry. `JournalPosted` event must fire for anything that should be logged (already fired at `JournalPostingService.php:103`).
- Chart of accounts is hybrid: global rows `institute_id NULL AND is_system=1` + tenant rows. Seed with `TenantCoaSeederService`.
- Fiscal periods: closed periods reject postings (`AccountingPeriodService`).
- Amount convention: `DECIMAL(19,4)`; money fields cast `decimal:4`.
- Advanced finance features are gated by `institute_settings.advanced_accounting_enabled` (`config/finance.php`, `advanced.*` middleware). Check before adding advanced routes.

**Inventory**
- `InventoryStockService` is the ONLY writer of `inventory_movements`: methods `receivePurchase`, `saleIssue`, `transfer`, `postAdjustment`, `postCount`, `returnStock`, `returnForReference`.
- `inventory_stock_levels` is a cache only — rebuildable; weighted-average cost lives there; row locks during updates; epsilon `0.00005` for float noise.
- Stock movements carry the accounting effect (via `InventoryAccountingService` → journal).

**Sales / Purchase lifecycles**
- Sales: quotation → order → delivery (stock issue) → invoice (journal) → payment (journal) → return/credit-memo (reverse stock + journal).
- Purchase: request → quotation → PO → GRN (stock + `Dr Inventory / Cr AP`) → invoice → payment → return.
- Document numbers come from `<area>_sequences` tables (`sales_sequences`, `purchase_sequences`, `hr_*_code_sequences`, medical `number_sequences`) — never hand-generate.

**Accounts / roles**
- `users.account_type enum('owner','staff')`; invariant enforced by `Membership::assertRoleAllowedForAccountType()` (`AccountTypeMismatchException`).
- One role per session; `SetFortifyGuard` re-resolves after role switch.

**Medical**
- Device/channel auth is separate from user auth (`lab.device` credentials tables); lab analyzer results broadcast through Reverb; `ProcessAnalyzerMessage` queued.
- Sensitive medical routes are dual-gated (`MedicalModuleAccess` + `CheckFeatureAccess`).

**Notifications**
- Anything user-visible async → `SendNotificationJob` / `NotificationCenter`; templates in `notification_templates`; retry via `notifications:retry`.

---

## 6. Naming & code style

1. **PHP 8.2+, Laravel 12 style**: constructor property promotion, `casts(): array` method (not `$casts` property — see `InventoryItem.php:28`), typed properties, enums where they already exist (`app/Enums/`).
2. Table names: snake_case, plural, often area-prefixed (`sales_invoices`? no — `invoices`, `purchase_orders`, `hr_employees`, `training_*`, `medical_*`). **Check `database/schema/mysql-schema.sql` before assuming.**
3. Traits/imports: use `App\Models\Concerns\*` via `use Concerns\TenantScoped;` (models inside `App\Models`) or full import elsewhere.
4. Blade:
   - Directives available: `@moduleEnabled`, `@featureEnabled`, `@featureLocked`, `@featureHidden`, `@term`, `@mawa_e()`/`mawa_e()`.
   - Layouts: `x-layouts.institute`, `x-layouts.admin`, `x-layouts.standalone`.
   - Translations: `lang/mawa/{en,bn}.php`; helpers `mawa_e()`, `mawa_translate()`; never hard-code user-facing English in blades that already have `@term`.
5. JS: Vite 7 + Tailwind 4 (`resources/css`), React 19 only for the 4 medical pages; Blade + Alpine/Livewire elsewhere. Do not add a new SPA framework.
6. Tests: PHPUnit 11 (`phpunit.xml`), `tests/Feature/*` with `DatabaseTransactions` (no migrations run — test DB is pre-seeded from `database/schema/full_data.sql`). Follow existing naming `SalesInvoiceTest`, `Phase08FinancialIntegrityTest`.
7. Comments: repo style is sparse docblocks on services/models, none in controllers. Match it.

---

## 7. Testing rules

1. Run targeted tests before claiming done: `php artisan test --filter=SomeTest`.
2. Known baseline failures (do not confuse with your change):
   - `tests/Unit/IndustryRulesTest.php` — 5 failures (unit suite 73 passed / 5 failed).
   - `CertificateIndexAuthorizationTest` — 200→403 (feature run 49 passed / 8 failed).
3. New endpoint ⇒ feature test covering: happy path, cross-institute 403, permission-denied 403, and (for writes) audit/journal side effects.
4. No live network in tests (`Http::fake`), no real email (`Notification::fake`), no queue dispatch surprises (`Bus::fake` where needed).
5. Feature tests assume seeded roles/permissions — see `database/seeders/RoleSeeder.php`, `RolePermissionSeeder.php`, `TaxPermissionSeeder.php`, `TrainingCenterPermissionSeeder.php`, `AiToolPermissionSeeder.php`.

---

## 8. Migration / config rules

1. One migration per cohesive change; never edit an applied migration — add a new one. Existing style: `2026_09_XX_XXXXXX_add_<module>_<thing>.php` with `Schema::create`/`table` and guarded `hasTable`/`hasColumn` checks in later waves.
2. Entitlement/module changes go through `module_registry` + `institute_module_entitlements` (`ModuleAccessService`), not through hardcoded booleans.
3. New config file: `config/<name>.php` + read via `config('<name>.<key>')`; register defaults in the file (see `config/pos.php`, `config/manufacturing.php` as templates).
4. `database/schema/mysql-schema.sql` (427 tables) is the authoritative schema snapshot — if you add tables, note that it is regenerated, not hand-edited.
5. Seeders are idempotent (`updateOrInsert` / `firstOrCreate`) — keep that.

---

## 9. Security checklist for every change

- [ ] `institute_id` enforced (trait or explicit where) on every new query touching tenant data.
- [ ] Branch scoping decided (`BranchScoped` vs `BranchScopedOrShared` vs cleared).
- [ ] Route has permission/module/feature gate **or** controller calls `authorize()`.
- [ ] Mass assignment: either `$fillable` explicitly or deliberate `$guarded=[]` (255 models are `$guarded=[]` — do not widen this to request input without validation).
- [ ] Upload validation (documents/scan paths use `Concerns\DeletesFiles` + validated mime/size).
- [ ] No secrets in code/logs; API responses use Resources (no raw model dumps).
- [ ] Rate limiting: login/OTP endpoints are throttled; keep any new auth-adjacent endpoint throttled.
- [ ] `SecurityHeaders` middleware is global — do not strip headers.
- [ ] reCAPTCHA on public forms (`LoginCaptchaTest` covers login).

---

## 10. Things that look broken but are intentional (do not "fix") — DOCUMENTATION ≠ IMPLEMENTATION notes

1. `Membership` model → table `institution_user` (singular) with `institution_id` FK. Correct as-is.
2. Medical models lack `TenantScoped` by design (manual `MedicalScope`) — see §3.4.
3. Global reference models lack tenant scope by design (comments say so, e.g. `AcademicLevel.php:16`).
4. `routes/medical.php` + `routes/institute_modules.php` + `routes/web.php` overlap in prefixes — duplication of route *names* is avoided by module-specific name prefixes; verify `route:list` before merging.
5. Stale human docs (`docs/audit/00-overview.md` claims "no Livewire/API/Sanctum"; `docs/structure.md` points at `C:\xampp\htdocs\monetix`; `phpunit.xml` comment says "17 migrations" while there are 240) — trust code over docs.
6. `.bak` files (`app/Models/Attendance.php.bak`, `config/queue.php.bak`) are leftovers, not sources.
7. `platform_staff` guard and `AuditActivityLog` middleware exist but are wired to nothing (see audit report) — if you touch them, either wire them up or delete via a deliberate decision, not incidentally.
