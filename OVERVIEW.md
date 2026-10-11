# AccumenAI — Project Overview

> Purpose: give a human or an AI agent a complete, self-contained understanding of
> what this product is and how it is organised — SaaS model, industries, modules,
> packages, permissions — without needing to open any other file.
>
> Everything below is verified against the codebase / live database (measured
> 2026-10-09). Evidence paths are given inline. Secrets are never reproduced; use
> `[REDACTED]`.
>
> Companion docs: `docs/AI_CONTEXT.md` (forensic architecture audit),
> `docs/AI_DEVELOPMENT_RULES.md` (hard editing rules), `docs/MODULE_REGISTRY.md`
> (per-module inventory). This file is the entry point; those go deeper.

---

## 1. What AccumenAI is

**AccumenAI** (formerly **Monetix** — old name still appears in DB names, `mawa_*`
helpers, CSS vars, and some docs) is a **multi-tenant, multi-industry business
management SaaS platform** sold to SMBs by subscription.

The core idea:

1. A customer picks an **industry** (education, healthcare, training center,
   retail, restaurant, manufacturing, real estate, …).
2. The platform **enables the matching module set** for that industry (students &
   fees for a school, OPD/IPD/pharmacy for a clinic, POS & stock for a shop, …).
3. The customer subscribes to a **package tier** (Starter / Growth / Enterprise),
   which further limits which modules and features are usable.
4. Staff get **role-based access**; everything is isolated per tenant
   ("institute") and optionally per branch.

**Not** a microservice, **not** API-only: a single Laravel monolith serving Blade
server-rendered pages, Livewire components, a handful of React pages, and a JSON
API.

### Tech stack

| Layer | Choice |
|---|---|
| Framework | Laravel 12, PHP 8.2 |
| DB | MySQL (XAMPP locally, cPanel in prod) — authoritative schema `database/schema/mysql-schema.sql` (427 tables) |
| Auth | Fortify + Sanctum, 5 guards, 2FA/OTP, reCAPTCHA |
| UI | Blade + Tailwind/Bootstrap theme, Livewire 4 (21 components), React 19 **only** for 4 medical pages (`resources/js/medical/*.jsx`) |
| Build | Vite 7 (`npm run build`; from PowerShell use `cmd /c "npm run build"`) |
| Queue | database driver — **queue worker must be running** (`queue:listen database --queue=default,notifications`) or async work silently does nothing |
| Tests | PHPUnit 11, `DatabaseTransactions` against pre-seeded `monetix_test` DB |
| Deploy | one 2 GB cPanel box; no Docker, no CI (`.github/` absent), no Redis/Horizon |

Two standalone Node packages live in-tree but are **not** part of the Laravel app:
`gateway/` (on-prem lab analyzer bridge) and `simulator/` (virtual analyzer).

---

## 2. The three portals

| Portal | Guard | Who | Entry |
|---|---|---|---|
| Platform admin | `platform_admin` | Platform operator (your company) | `/admin/login` |
| Institute (tenant) | `web` (owner/staff) + legacy `institute_user` | Tenant owner & employees | `/login` |
| Guardian | `guardian` | Parents/guardians of students | `/guardian/login` |

- `platform_staff` guard is **defined but unwired** (0 routes) — do not use.
- **One role per session**: `SetFortifyGuard` pins the Fortify guard per request.
- **Owner vs staff invariant**: `users.account_type enum('owner','staff')`.
  - Owner ⇒ membership role must be `institute-owner`; **owner bypasses the whole
    permission matrix** (`Membership::hasPermission()` returns true).
  - Staff ⇒ any other role; evaluated against `role_permissions`.
  - Enforced by `Membership::assertRoleAllowedForAccountType()` (throws
    `AccountTypeMismatchException`) and on `account_type` change by
    `User::assertAccountTypeConsistentWithMemberships()`.

---

## 3. The SaaS model

### 3.1 Tenancy objects

```
PlatformAdmin ──manages──> Institute (tenant = "institute", table `institutes`)
User ── Membership (table `institution_user`!) ──> Institute ──> Branch (`branches`)
Membership ──role_id──> Role ──<role_permissions>── Permission
Institute ──package_id──> SubscriptionPackage ──<package_modules / package_industries>
Institute ──< institute_module_overrides / _entitlements / super_admin_overrides
```

- **Organization = `institute`**. There is no `organizations` table. The tenancy
  FK column is always **`institute_id`** (never `tenant_id`/`organization_id`) —
  the single exception is `institution_id` inside `institution_user`.
- `Membership` model → table **`institution_user`** (singular), a *different*
  table from `institute_users` (legacy realm). Both exist and are load-bearing.
- Passwords live in **`password_hash`**, never `password`.
- Workspace switching: session key `active_institution_id`
  (`app/Support/Workspace.php`, `workspace/*` routes).

### 3.2 Packages (how the product is sold)

Table `subscription_packages` (24 active tiers, BDT pricing):

| Family | Tiers | Price/month |
|---|---|---|
| Generic | `free`, `basic`, `advanced`, `premium` | 0 / 1,500 / 4,000 / 9,000 |
| Per-industry | `*_starter`, `*_growth`, `*_enterprise` for restaurant, real_estate, manufacturing, medical, training_center, education, retail | 1,500 / 4,000 / 9,000 |

Package → module mapping lives in `package_modules`, `package_industries`,
`package_industry_modules`, `package_scoped_modules/features`, `package_features`,
`package_country_prices` (country-specific pricing for EU/SAARC expansion).
Checkout: bKash (`SaasSubscriptionService`, `saas/*` routes).

### 3.3 Entitlement resolution — the heart of the SaaS logic

`ModuleAccessService::resolveEnabled($institute)`
(`app/Services/ModuleAccessService.php:279`) computes the effective module set by
layering, in order:

1. **Core modules** — always on (`config/industry-modules.php` → `core`).
2. **Industry defaults** — `industry-modules.<industry>.default`.
3. **Sub-category defaults** — mandatory + default from the industry subcategory
   (`IndustrySubcategoryService`).
4. **Package modules** — what the tenant's subscription includes.
5. **Tenant overrides** — `institute_module_overrides` (optional modules toggled
   on/off); the package × industry matrix wins over a manual enable.
6. **Entitlements** — `institute_module_entitlements` grant/deny rows.
7. **Industry-disabled removal** — `industry-modules.<industry>.disabled` is
   removed unconditionally.
8. **Super-admin emergency overrides** — `super_admin_overrides` (time-limited),
   added after every denial, bypasses hard boundaries.
9. **Hard boundaries** — industry compatibility + country tax allow-list
   (cannot be overridden except by super admin), then dependency closure and
   parent gating (e.g. `medical.pharmacy` requires `medical`).

Enforcement: route middleware **`module_access:key`** → `CheckModuleAccess` →
`isEnabled()`. Feature granularity: **`feature:key`** → `CheckFeatureAccess` →
`feature_registry` (41 features) + package feature scope + overrides.

> `institute_module_entitlements` currently has 0 rows in dev — layers 1–5 + 9
> do the real work today; entitlements/super-admin are wired but unused.

---

## 4. Industries

### 4.1 Industry taxonomy

Tables: `industries` → `sub_industries` → `industry_subcategories`, seeded
idempotently from `config/industry_rules.php` by `IndustryTaxonomySeeder`.

**14 active industries** (DB-verified):

`education`, `training_center`, `healthcare`, `information_technology`,
`finance`, `retail`, `manufacturing`, `real_estate`, `transportation`,
`service`, `restaurant`, `hotels`, `personal_finance`, `other`

Each carries country-specific sub-industries (e.g. Bangladesh: school, college,
madrasha, coaching centre, IT training, music/sports academy, …).

### 4.2 Industry → module matrix

`config/industry-modules.php` defines, per industry, three buckets:

| Bucket | Meaning |
|---|---|
| `default` | enabled automatically, tenant cannot disable |
| `optional` | tenant may toggle (subject to package matrix) |
| `disabled` | stripped even if something else enabled it |

Configured industries: **healthcare, education, training_center, retail,
manufacturing, real_estate, restaurant**. Examples:

- **healthcare** default: `purchase`, `medical`, `medical.billing`; optional:
  `sales`; disabled: education, training_center, real_estate, restaurant,
  manufacturing, retail.
- **education** default: `education`, `education.fees`; optional: sales,
  purchase; disabled: medical, medical.billing, training_center.
- **retail** default: sales, purchase, inventory + full `pos.*` family.
- **restaurant / manufacturing / real_estate**: their domain modules plus
  sales/purchase/inventory/pos integration keys.

**Core modules (all industries)**: `crm`, `accounting`, `finance`, `reports`,
`notifications`, `ai`, `vat`.

Industries without a matrix entry (IT, finance, hotels, transport, …) get core
modules only — they ride on the generic sales/purchase/accounting stack.

> `dealership` is a **module group, not an industry** (`config/dealership.php`
> header comment): brands/products/SR management usable by any tenant.

---

## 5. Modules and implementation status

### 5.1 Registries

- **`module_registry`** — 299 module keys (18 top-level families: accounting, ai,
  crm, education, finance, hr, inventory, manufacturing, medical, notifications,
  pos, purchase, reports, sales, tds, training_center, vat). Many are
  forward-looking placeholders that the UI simply skips.
- **`feature_registry`** — 41 fine-grained features, gated by `feature:` middleware.
- **`config/module_groups.php`** — display groups for the platform module matrix
  (`/admin/module-config`): core, medical, education, training_center, sales, pos,
  purchase, inventory, manufacturing, real_estate, restaurant, dealership.

Module keys are **dot-namespaced** (`medical.pharmacy`,
`training_center.courses`, `pos.checkout`).

### 5.2 Status matrix

| Area | Status | Evidence |
|---|---|---|
| Sales (quotation→order→delivery→invoice→payment→return) | **IMPLEMENTED** | `Services/Sales/*`, `sales/*` routes |
| Purchase (request→PO→GRN→invoice→payment→return) | **IMPLEMENTED** | `Services/Purchase/*` |
| Inventory (single writer: `InventoryStockService`) | **IMPLEMENTED** | `inventory/*` routes, weighted-avg costing |
| Accounting (double-entry, `JournalPostingService`) | **IMPLEMENTED** | `finance/*` + `accounting/*` routes, reports |
| Tax / TDS / VAT | **IMPLEMENTED** | `Services/Tax/*` |
| Fixed assets & depreciation | **IMPLEMENTED** | `Services/FixedAsset/*` |
| HR + payroll | **IMPLEMENTED** | `hr/*`, `HrPayrollFinanceService` |
| Education / academic (students, exams, fees) | **IMPLEMENTED** | 152 education routes |
| **Medical / HMS** (OPD, IPD, lab, pharmacy, radiology…) | **IMPLEMENTED** | 490 routes, 65 controllers, 76 models, 233 views, 4 React pages |
| Lab analyzer integration (device auth, Reverb live) | **IMPLEMENTED** | `LabIntegration/*`, `lab.device` middleware |
| Training center | **IMPLEMENTED** | `training_center/*` |
| CRM | **IMPLEMENTED** | `Services/Crm*` |
| Documents / notifications / reports / workflow / AI assistant | **IMPLEMENTED** | respective services |
| SaaS packaging & bKash checkout | **IMPLEMENTED** | `SaasSubscriptionService` |
| Guardians portal | **IMPLEMENTED** | `routes/guardian.php` |
| **POS** | **PLANNED** | `config/pos.php` + registry rows only — 0 routes/models |
| **Manufacturing** | **PLANNED** | `config/manufacturing.php` + registry — 0 routes/models |
| **Real Estate** | **PLANNED** | migrations/registry — 0 routes/controllers |
| **Restaurant** | **PLANNED** | migrations + industry row + packages — 0 routes/controllers |
| **Dealership** | **PARTIAL** | phases 1–5 permission seeders exist; feature set incomplete |

> **Do not scaffold POS/Manufacturing/Real-Estate/Restaurant/Dealership code
> unless explicitly asked** — the registry entries are product roadmap
> placeholders, not half-built features to "finish".

### 5.3 Module dependency spine

```
tenancy + auth/RBAC + registries (foundation)
        │
Accounting (COA, fiscal periods, JournalPostingService)
   ▲         ▲          ▲          ▲          ▲
Inventory  Sales/Purchase  HR payroll  Education fees  Fixed assets  Tax
   │            │
InventoryStockService ──► InventoryAccountingService ──► JournalPostingService
```

Medical is largely **self-contained** (own `Medical\Invoice`, own pharmacy
stock), gated by `medical` + `medical.module` middleware.

---

## 6. Permissions & RBAC

### 6.1 Model

```
roles            institute_id NULL = system role; unique (institute_id, slug)
permissions      slug (uq), module, name        — 493 rows
role_permissions role_id ↔ permission_id
```

**9 system roles** (DB-verified): `institute-owner` (superuser), `institute-admin`,
`branch-manager`, `accountant`, `teacher`, `trainer`, `receptionist`,
`exam-controller`, `viewer`.

Tenant roles are created per institute by `RoleTemplateService::seedForInstitute()`
(industry-specific templates, e.g. `*-accountant`, `*-manager`; unknown slugs are
skipped).

### 6.2 Permission slugs

Convention: **`module.action`** (e.g. `sales.create`, `accounting.view`,
`settings.manage`), plus fine-grained domain families. Distribution by module
(DB-verified, 493 total):

| module | count | | module | count |
|---|---|---|---|---|
| restaurant | 79 | | medical_pharmacy | 10 |
| dealership | 51 | | medical_bloodbank | 10 |
| sales | 25 | | medical_encounters | 10 |
| training | 24 | | medical_lab / medicines / patients / vitals / wards | 8 each |
| purchase | 22 | | tax | 5 |
| accounting | 19 | | education_* family | 5/2 each |
| medical_radiology / emergency / tpa | 12–14 | | settings, staff, finance, hr, admin | 1–2 each |

Medical mixes two naming styles (`medical.opd` dot and `medical_opd` underscore) —
`MedicalPermissionAliasSeeder` bridges them. **Check the DB/seeder before
assuming a slug exists.**

### 6.3 Enforcement layers

1. **Route middleware** (primary): aliases in `bootstrap/app.php:62-69`
   - `permission:slug[,slug]` → `CheckPermission`
   - `module_access:key[,key]` → `CheckModuleAccess` → `ModuleAccessService`
   - `feature:key` → `CheckFeatureAccess`
   - `medical` / `medical.module`, `advanced.accounting`, `deny.teacher.finance`,
     `finance.write`
2. **Controller**: `$this->authorize()` / `Gate::authorize()` (≈12 finance &
   settings controllers), `AuthorizesPermission::requirePermission()` (5 controllers).
3. **Policies**: 9 accounting policies, bound manually in
   `AppServiceProvider::registerPolicies()`. **New policy ⇒ register there.**

Blade side: `@moduleEnabled`, `@featureEnabled`, `@featureLocked`,
`@featureHidden`, `@term` directives control UI visibility (hiding UI is *not*
authorization — the route gate is).

### 6.4 Coverage & known gaps (measured 2026-10-09)

- **2,245 total routes; 2,192 authenticated; 1,138 carry a
  `permission:`/`module_access:`/`feature:` gate.**
- The remaining ~1,054 authenticated routes rely on authentication + tenant
  scope only (some authorize() inside the controller; many do not).
- **Documented fail-open paths — do not rely on them as protection:**
  - `FinanceWriteGate` / `DenyTeacherFromFinance` are deny-lists: empty list ⇒
    everything allowed.
  - `Membership::roleAllowedForAccountType()` returns `true` when user/role is null.
  - `CheckPermission` can fall back to the user's *first* active membership
    (`CheckPermission.php:44`) — possibly a different org than the session's.
  - Owner bypasses everything by design.

**Adding a route? Give it at least one gate** (`permission:` / `module_access:` /
`feature:` / controller `authorize()`), and seed any new permission slug in a
permission seeder.

---

## 7. Tenancy rules (the ones that bite)

1. Column is **`institute_id`** — never `tenant_id`, never `organization_id`.
2. **Two scoping regimes**:
   - Most models: `use Concerns\TenantScoped` — global scope + create/update
     hooks that force `institute_id` and revert tampering (223 models).
   - **`app/Models/Medical/*` (77 models): NO global scope** — every query must
     add `->where('institute_id', MedicalScope::instituteId())` manually.
3. `SetTenantContext` middleware runs **before** `SubstituteBindings`
   (`bootstrap/app.php`) — this ordering is load-bearing; never reorder.
4. Branch scoping: `BranchScoped` / `BranchScopedOrShared`; **NULL branch =
   sees all branches** (owner/admin). Guardian portal clears branch context on
   purpose.
5. Line-item models (`InvoiceItem`, `SalesOrderLine`, …) have no scope — they
   rely on their parent's scoping. Don't query them bare.
6. When `TenantContext` is null (CLI, unscoped routes) the scopes are no-ops.
7. Global reference models (`Subject`, `AcademicLevel`, …) are deliberately
   unscoped — do not "fix" that.
8. Schema is **not** built by `php artisan migrate`: the base is
   `database/schema/mysql-schema.sql`; the 342 migrations are idempotent deltas
   + registry/seed rows. Never edit an applied migration.

---

## 8. Hard rules (never do these)

From `docs/AI_DEVELOPMENT_RULES.md` §0 — violations break tests or corrupt data:

1. **Never write secrets.** `.env` contains real API keys. Reference config keys
   (`config/ai.php`, `config/services.php`), use `[REDACTED]` in docs.
2. **Never bypass tenancy.** Every institute-owned row carries `institute_id`.
3. **Never expose data across institutes or roles.**
4. **Never move money directly.** All postings via `JournalPostingService`
   (post / reverse / void; posted journals are immutable).
5. **Never write stock directly.** `inventory_movements` is written only by
   `InventoryStockService`.
6. **Never commit / add packages / refactor unrelated code unless asked.**
7. Document numbers come only from `*_sequences` tables — never hand-generate.

---

## 9. Directory map

```
app/
├── Http/Controllers/    358 (root + Accounting, Admin, Ai, Auth, FixedAsset,
│                          Guardian, Hr, Institute, Inventory, Medical, Purchase,
│                          Saas, Sales, Settings, Staff, SuperAdmin, Training…)
├── Http/Middleware/      24 (SetTenantContext, CheckPermission, CheckModuleAccess,
│                          CheckFeatureAccess, MedicalDomain, FinanceWriteGate…)
├── Models/              421 (Concerns/ = TenantScoped, BranchScoped…; Medical/ = 77)
├── Services/            392 (Accounting 60, System 40, Medical 36, Sales 13…)
├── Support/             TenantContext, BranchContext, Workspace, MedicalScope
├── Livewire/             21    Policies/ 9    Jobs/ 4    Console/Commands/ 74
bootstrap/app.php         middleware aliases, groups, route wiring
config/                   industry-modules, module_groups, industry_rules, pos,
                          manufacturing, real-estate, restaurant, dealership, tax…
database/
├── schema/mysql-schema.sql   AUTHORITATIVE schema (427 tables)
├── migrations/                idempotent deltas + registry seeds (342)
├── seeders/                   75+ (permissions, roles, modules, packages, COA…)
routes/                    web, institute_modules (~2,200 lines), medical, api,
                           api_v1, auth, guardian, console, channels
resources/views/           974 Blade files (layouts: institute, admin, standalone)
resources/js/medical/      4 React entrypoints (add new ones to vite.config.js)
tests/                     504 files; DatabaseTransactions; shard runner
gateway/, simulator/       standalone Node packages (not part of Laravel app)
docs/                      all deep-dive documentation
```

---

## 10. Commands

```powershell
composer dev            # artisan serve + queue worker + pail + Vite  ← full stack
composer install/update # ALSO runs npm install && npm run build (slow, offline-hostile)
vendor/bin/pint         # the only style gate (no PHPStan, no cs-fixer)
cmd /c "npm run build"  # from PowerShell (npm via cmd)
php artisan view:clear  # after Blade edits
php artisan route:list --json   # route/gate audits
```

**Tests:**

```powershell
php artisan test --filter=SomeTest          # single class (canonical)
php artisan test tests/Feature/Accounting/  # directory = pass a PATH, not a filter
php tests/shards/run.php                    # full run (6 shards; NEVER run whole suite sequentially)
```

- Test DB `monetix_test` is pre-seeded from `database/schema/full_data.sql`;
  migrations are never run by tests.
- `php artisan test --parallel` / `composer ci:test` are **broken** — use shards.
- Baseline failures exist (e.g. `IndustryRulesTest`) — establish *your* baseline
  with a filtered run before blaming your change.

---

## 11. Known gaps & intentional oddities

**Gaps (known, not fixed):**

- ~1,054 authenticated routes without route-level permission gates.
- Medical models lack global tenant scope (manual filtering).
- 255 of 458 models use `$guarded = []` — compensate with FormRequest validation.
- `AuditActivityLog` middleware exists but is never registered.
- `InvoicePaid` event/listener exist but nothing dispatches the event.
- `platform_staff` guard dead; `SalesInventoryIntegration` orphaned.
- Schema not reproducible by `migrate` alone; no CI/CD.
- Stale docs: `docs/structure.md`, `docs/audit/*`, `phpunit.xml` header —
  **trust code over docs**.

**Intentional — do NOT "fix":**

- `Membership` → table `institution_user` (singular) with `institution_id`.
- Medical models have no `TenantScoped` (manual `MedicalScope` by design).
- Global reference models are unscoped by design.
- POS/Manufacturing/Real-Estate/Restaurant exist only as config + registry rows.
- Guardian portal clears branch context on purpose.
- COA hybrid global rows (`institute_id NULL AND is_system = 1`).
- `mawa_*` helper naming and `monetix_*` DB names are legacy, still in use.

---

## 12. Where to go deeper

| Question | Read |
|---|---|
| Full architecture, request lifecycle, DB schema, workflows, security model | `docs/AI_CONTEXT.md` |
| Rules for *editing* code (where code goes, business invariants, checklists) | `docs/AI_DEVELOPMENT_RULES.md` |
| Per-module inventory: routes/controllers/services/models/tests | `docs/MODULE_REGISTRY.md` |
| What each portal can do (feature list) | `docs/skill.md` |
| UI/style conventions, list-page pattern | `docs/promptRules.md`, `docs/design-conventions.md`, `docs/standard-list-page.md` |
| Environment, golden rules, change log | `docs/memory.md` |
| Agent-specific instructions (commands, test gotchas) | `AGENTS.md` |

**First 5 minutes for a new AI agent:** read this file → `AGENTS.md` →
`docs/AI_DEVELOPMENT_RULES.md` → the relevant section of `docs/AI_CONTEXT.md`.
