# ACCUMENAI — AI CONTEXT DOCUMENT

> Purpose: give another AI coding agent a complete, evidence-based understanding of this codebase without rediscovery.
> Method: read-only forensic audit. Evidence references are `path` or `path:line`.
> Status vocabulary: **IMPLEMENTED / PARTIAL / PLANNED / UNUSED / DEPRECATED / UNKNOWN**.
> Secrets are never reproduced; use `[REDACTED]`.

---

## 1. Project identity

- **Product name**: AccumenAI (`APP_NAME="Accumen AI"` in `.env`).
- **Former codename**: **Monetix** — still present in `docs/audit/00-overview.md`, `docs/structure.md` (`C:\xampp\htdocs\monetix`), SQL dump headers (`demo/monetix_backup_*.sql`), test DB names (`monetix_test`), CSS variable `--monetixSidebar*`, and helper `mawa_*` naming (`lang/mawa/{en,bn}.php`).
- **Repo**: git repo at project root. Recent commits are conventional-commit style (`feat(accounting):`, `fix(seeder):`, `chore(auth):`, `display(industries):`).
- **README**: `README.md` contains only the title; `README.txt` only "AccumenAI". Real docs live in `docs/`.
- **Type**: single-tenant-per-row multi-tenant SaaS monolith (not a microservice, not API-only).

## 2. Product purpose

A **multi-industry business management platform** sold to SMBs by subscription:

- Select an **industry** (education, healthcare/medical, training center, retail/POS, manufacturing, real estate, restaurant, generic business) → the platform enables the matching module set.
- Tenants ("institutes") get branches, staff, role-based access, and domain modules.
- Domain coverage evidenced by routes/controllers/services: sales, purchase, inventory, accounting, tax, fixed assets, HR/payroll, CRM, education/academic, medical/HMS, training center, documents, notifications, reports, approvals, AI assistant.
- Platform layer: packages (`subscription_packages`, `package_modules`, `package_scoped_*`, `package_features`, `package_industries`), entitlement expiry, bKash checkout, super-admin console.

## 3. Technology stack

See `docs/AI_HANDOFF.md` §"Current stack" for the table. Summary: PHP 8.2 / Laravel 12 / Fortify / Sanctum / Livewire 4 / Blade + Tailwind 4 + React 19 (4 pages) / Vite 7 / MySQL / database queue / PHPUnit 11.

Notable **absences** (verified by composer.json/package.json): no Spatie packages, no Horizon, no Redis usage (config only), no Docker, no CI config, no Vue, no GraphQL.

## 4. Architecture

**Pattern: MVC + Service Layer with global-scope multi-tenancy.**

Implemented building blocks (with counts):

| Construct | Count | Evidence |
|---|---|---|
| Controllers | 358 | `app/Http/Controllers/**` |
| Services (business logic) | 392 | `app/Services/**` (root 119, Accounting 60, System 40, Medical 36, Sales 13, Ai 8, …) |
| Eloquent models | 421 `.php` files | `app/Models/**` (incl. `Medical/` 76, `Concerns/` 6) |
| Form Requests | 50 | `app/Http/Requests/**` (Medical 30+, Settings 10, root) |
| API Resources | 22 | `app/Http/Resources/*` |
| Policies | 9 | `app/Policies/*` (accounting/corporate-finance only) |
| Middleware | 24 | `app/Http/Middleware/*`, aliases in `bootstrap/app.php:44-67` |
| Events | 3 | `app/Events/*` — only `JournalPosted` is dispatched |
| Listeners | 2 | `app/Listeners/*` (queued) |
| Jobs | 4 | `app/Jobs/*` |
| Notifications | 1 class + 3 Mail | `app/Notifications/QueuedVerifyEmail`, `app/Mail/*` |
| Form Request–driven validation | partial | many controllers validate inline |
| Livewire components | 21 | `app/Livewire/*` |
| Artisan commands | 74 | `app/Console/Commands/*` |
| Helpers | `app/helpers.php` (mawa_*, qr_svg), `app/Support/helpers.php` (`platform_logo_url`) | composer autoload `files` |

**NOT present**: repositories, DTOs, action classes, domain events beyond 3, state machines, query objects, specifications, observers, interfaces/contracts directory (`app/Contracts/` has 2 files).

**Request lifecycle (as implemented):**

```
HTTP
 → web group middleware: AssignRequestId, NormalizePersonNames, SetLocale, SecurityHeaders, PlatformMaintenance
 → SetTenantContext (prepended BEFORE SubstituteBindings — bootstrap/app.php:101)
 → route middleware: auth:<guard(s)> [→ guest] → verified → tenant → module_access:* → feature:* → permission:* 
                    → deny.teacher.finance → finance.write → advanced.accounting → medical* → throttle
 Controller (authorize()/Gate::authorize in 12 controllers only; requirePermission() trait used by 5)
 → Service (transactions, business invariants)
 → Model (TenantScoped/BranchScoped global scopes + create/update hooks)
 → MySQL
 → (optional) JournalPosted event → LogJournalPosted (queued)
 → Response (Blade view | JSON via ApiResponse concern | API Resource)
```

## 5. Directory structure

```
app/
├── Console/Commands/      74  artisan commands (backup, audit, backfill, sync, cleanup, entitlements)
├── Contracts/              2
├── Enums/                  1
├── Events/                 3  (InvoicePaid[unused], JournalPosted, LabAnalyzerResultStored)
├── Exceptions/             2  (AccountTypeMismatchException, …)
├── Geo/                    2
├── Http/
│   ├── Controllers/      358 (root 108 + Accounting, Admin, Ai, Api, Auth, Concerns,
│   │                          FixedAsset, Guardian, Hr, Institute, Inventory, Medical,
│   │                          Purchase, Saas, Sales, Settings, Staff, SuperAdmin, Training)
│   ├── Middleware/        24
│   ├── Requests/          50 (Medical/, Settings/, root)
│   └── Resources/         22
├── Jobs/                   4
├── Listeners/              2
├── Livewire/              21
├── Mail/                   3
├── Models/               421  + Concerns/ (TenantScoped, BranchScoped, BranchScopedOrShared,
│                               DeletesFiles, HasUserPreferences, NormalizesPersonNames)
│                          + Medical/ (76), Accounting/?, LabIntegration/? (subdirs)
├── Notifications/          1
├── Policies/               9
├── Providers/              1  (AppServiceProvider — blade directives, policy binding, model events)
├── Rules/                  1  (PhoneRule)
├── Services/             392  root 119 + Accounting 60, System 40, Medical 36, Sales 13,
│                               Purchase 10, Identity 9, Ai 8, FixedAsset 7, Inventory 6,
│                               Education 5, Notification 5, Tax 4, LabIntegration 11, …
└── Support/               31  (TenantContext, BranchContext, Workspace, MedicalScope, …)
bootstrap/app.php           middleware aliases, priority, schedules, exception rendering
config/                    45 files incl. domain configs (accounting, ai, manufacturing, pos,
                           medical, real-estate, restaurant, country_modules, industry-modules,
                           workflows, tax, security, backup, identity, …)
database/
├── migrations/           240  (all timestamped 2026-09-13 → 2026-09-27; deltas + registry seeds)
├── schema/                  mysql-schema.sql (427 tables, authoritative), schema.sql,
│                            full_data*.sql, seed_data.sql, migrations_data.sql
├── seeders/               75  (permissions, roles, modules, COA, medical, geo, packages…)
├── factories/              7
├── geo/                    bangladesh.json
└── backups/                geo fix SQL
routes/                     web(726 ln), institute_modules(1938), medical(719), api(163),
                            auth(177), guardian(101), console(58), channels(33)  → 2156 routes
resources/
├── views/                974 blade files (institute 193, medical 233, admin 97, hr 61,
│                          settings 45, sales 44, purchase 47, training 40, auth 23, …)
├── css/app.css, js/ (app.js, bootstrap.js, medical/*.jsx ×7)
└── templates/
public/css (base,layout,components,pages), public/js (ajax, geo-select, …), public/build
tests/                    504 files (Feature 424, Unit 12, Fixtures, shards, Concerns)
docs/                     existing human docs + this package
demo/                     legacy HTML mockups + SQL backups (gitignored)
backups/                  ~100 dated SQL dumps (gitignored)
scripts/                  cPanel ops shell scripts
gateway/, simulator/      Node side projects (lab analyzer gateway, analyzer simulator)
storage/, lang/, reports/, Documentation/, .opencode/, .commandcode/
```

**Actively used**: `app/`, `routes/`, `resources/views/`, `database/`, `config/`, `tests/`, `public/`.
**Reference/legacy**: `demo/`, `Documentation/`, `docs/audit/` (dated), `backups/`, `reports/`, `simulator/`, `gateway/`.

## 6. Database architecture

### Authoritative sources
1. `database/schema/mysql-schema.sql` — 427 tables (schema dump; includes `schema_migrations`).
2. `database/migrations/*.php` — 240 incremental migrations (many are pure `DB::table('module_registry')` seeders).
3. `database/schema/full_data.sql` / `full_data_safe.sql` — test/fixture provisioning for `monetix_test`.

### Core tenancy tables (verified DDL)

```
users            id PK, uuid, uid(10), name/first/last, email(uq), phone(uq), password_hash,
                 status enum(active|inactive), account_type enum(owner|staff),
                 two_factor_*, failed_login_count, locked_until, last_login_at, deleted_at
institutes       id PK, uuid, uid, name, slug(uq), industry, industry_id→industries,
                 sub_industry_id→sub_industries, country_id→countries, package_id→subscription_packages,
                 status enum(pending|active|suspended|expired|cancelled), verified,
                 advanced_accounting_enabled, business_entity_type, share-capital columns,
                 deleted_at, deletion_requested_*
institution_user (= Membership) id PK, uuid, user_id→users(CASCADE), institution_id→institutes(CASCADE),
                 role_id→roles, branch_id→branches(SET NULL), employee_id, designation, department,
                 salary, joining_date, status enum(active|inactive|suspended),
                 legacy_institute_user_id(uq), deleted_at
                 UNIQUE(user_id, institution_id)
institute_users  (= legacy InstituteUser) id PK, uuid, institute_id, branch_id, role_id,
                 first/last_name (+generated full_name/name), email(uq global + uq per institute),
                 phone(uq), password_hash, 2FA cols, status, deleted_at
                 UNIQUE(institute_id, employee_id)
branches         id PK, code(uq, varchar(4)), institute_id(CASCADE), name, manager_user_id→institute_users,
                 status enum(active|inactive), is_principal, deleted_at
roles            id PK, institute_id NULLABLE (NULL = system role), name, slug,
                 UNIQUE(institute_id, slug), is_system, status
permissions      id PK, module, name, slug(uq)
role_permissions id PK, role_id(CASCADE), permission_id(CASCADE), UNIQUE(role_id, permission_id)
```

### Business tables (representative; 427 total)

- **Sales**: `sales_sequences`, `sales_quotations(+_lines)`, `sales_orders(+_lines)`, `sales_deliveries(+_lines)`, `sales_returns(+_items, _refunds)`, `invoices`, `invoice_items`, `payments`, `cash_memos`, `parties`, `customer_groups`, `medical_invoices`.
- **Purchase**: `purchase_sequences`, `purchase_requests(+_items)`, `purchase_quotations(+_lines)`, `purchase_orders(+_lines)`, `goods_receipts(+_items)`, `purchase_invoices(+_items)`, `purchase_returns(+_items)`, `purchase_supplier_payments`, `supplier_credit_balances`, `supplier_refunds`.
- **Inventory**: `inventory_items`, `inventory_categories`, `inventory_warehouses`, `inventory_stock_levels`, `inventory_movements`, `inventory_adjustments(+_items)`, `inventory_transfers(+_items)`, `inventory_counts(+_items)`, `inventory_batches`, `inventory_serial_numbers`.
- **Accounting**: `account_groups`, `account_heads`, `chart_of_accounts` (hybrid global/tenant: `institute_id` NULL + `is_system`), `fiscal_years`, `accounting_periods`, `accounting_settings`, `journals`, `journal_entries`, `opening_balances`, `statement_snapshots`, `budgets(+_versions,_lines)`, `bank_statements(+_lines)`, `bank_reconciliations`, `bank_rules`, `expenses`, `progressive_contracts`, `recurring_templates(+_generations)`, `partners`, `shareholders`, `share_capital_transactions`, `share_certificates`, `dividends(+_payouts)`, `corporate_tax_computations`, `tds_*`, `advance_tax_payments`, `tax_*`.
- **Fixed assets**: `asset_categories`, `asset_locations`, `fixed_assets`, `asset_cost_components`, `asset_depreciation_runs/entries`, `asset_disposals/impairments/revaluations/transfers/method_changes`, `asset_qr_codes`, `asset_audit_logs`.
- **HR**: `hr_employees`, `hr_departments`, `hr_designations`, `hr_attendances(+corrections)`, `hr_leave_types/balances/applications`, `hr_payroll_periods/items/adjustments`, `hr_salary_structures(+components)`, `hr_applications/history`, `hr_interviews/offers/vacancies/requisitions`, `hr_kpis`, `hr_performance_*`, `hr_trainings(+enrollments)`, `hr_work_shifts`, `hr_holidays`, `hr_employee_code_sequences`.
- **Education**: `students`, `student_enrollments`, `batches`, `courses/categories/sub_categories/subjects`, `exams(+subjects/components)`, `exam_results`, `results`, `certificates(+types)`, `attendance`, `fee_heads`, `fee_structures(+items)`, `monthly_fee_periods`, `installments`, `guardians`, `guardian_student`, `student_academic_*`, `academic_*` (20 tables), `structure_templates/nodes/labels`.
- **Medical/HMS**: `patients`, `appointments`, `medical_encounters`, `prescriptions(+items)`, `lab_orders/results/tests/samples/analyzers/messages/worklists`, `pharmacy_stock/dispenses`, `medicines` (+ `medicine_*` dictionary, `dgda_*`, `rxnorm_*`), `wards/beds`, `blood_*`, `dental_*`, `radiology_*`, `vaccination_*`, `physiotherapy_*`, `diet_*`, `ambulance_*`, `emergency_visits`, `clinical_notes`, `tpa_claims`, `vital_signs`, `discharge_summaries`.
- **CRM**: `crm_contacts(+types)`, `crm_leads(+sources, _statuses)`, `crm_organizations`, `crm_activities`, `crm_notes`, `crm_tasks`.
- **Training**: `training_courses/categories/sub_categories/subjects`, `training_students`, `training_batches`, `training_enrollments`, `training_exams(+results)`, `training_schedules`, `training_attendance`, `training_certificates`, `training_batch_results`, `training_classes`.
- **Platform/SaaS**: `subscription_packages`, `package_modules`, `package_scopes`, `package_scoped_modules/features`, `package_features`, `package_industries(+modules)`, `package_country_prices`, `institute_module_entitlements/overrides(+archive)`, `institute_feature_overrides`, `institute_subscriptions`, `feature_registry`, `module_registry`, `module_terminology`, `module_rules`, `industries`, `sub_industries`, `industry_subcategories`, `subcategory_default_modules`, `super_admin_overrides`, `tenant_access_grants/denials`, `platform_admins`, `platform_staffs`, `platform_audit_logs`, `platform_service_configs`.
- **Ops/observability**: `activity_logs(+archive)`, `audit_logs(+archive)`, `module_access_logs`, `command_logs`, `login_attempts`, `identity_audit_logs`, `system_backups`, `system_health_audits`, `system_schema_versions`, `system_seed_versions`, `database_query_logs`, `endpoint_performance_logs`, `queue_audit_logs`, `deployment_logs`, `backup_verification_logs`, `jobs/failed_jobs/job_batches/cache/sessions`.

### Keys & conventions
- PK: `bigint unsigned AUTO_INCREMENT`. `uuid char(36) DEFAULT uuid()` on most tenant tables. `uid varchar(10)` on users/institutes.
- Tenant FK: **`institute_id`** on tenant tables; **`institution_id`** only in `institution_user` (naming inconsistency).
- Branch FK: `branch_id` (nullable; SET NULL).
- Timestamps: `datetime`/`timestamp` with DB-side `DEFAULT current_timestamp()` in the dump; Eloquent `timestamps = true` on models.
- Soft deletes: `deleted_at` on identity/tenant/academic tables; NOT on most ledger/journal tables (immutability by workflow instead).
- Money: `decimal(15,2)` / `decimal(10,2)` / `decimal(8,2)` depending on table; quantities often `decimal(12,4)` in inventory.
- Generated columns: `institute_users.full_name`/`name` are `GENERATED ALWAYS AS ... STORED`.

### Relationship overview

```
PlatformAdmin ──manages──> Institutes
User ──(institution_user / Membership)──> Institute ──has──> Branch
User ──account_type──> owner | staff
Membership ──role_id──> Role ──<role_permissions>── Permission
Institute ──package_id──> SubscriptionPackage ──<package_modules/scopes/features>── module_registry/feature_registry
Institute ──<entitlements/overrides>──> effective module+feature set (ModuleAccessService)

Institute ──< Students / Batches / Courses(assigned) / Exams / Certificates
Institute ──< Parties ──< SalesQuotation → SalesOrder → SalesDelivery → Invoice → Payment
Institute ──< PurchaseRequest → PurchaseOrder → GoodsReceipt → PurchaseInvoice → SupplierPayment
Institute ──< InventoryItem → InventoryMovement → InventoryStockLevel (cache)
Institute ──< Journal → JournalEntry (double-entry, COA leaf accounts)
Institute ──< HrEmployee → HrPayroll* / HrAttendance / HrLeave*
Institute ──< Patient → Encounter → Prescription / LabOrder / Invoice
```

## 7. Authentication

- **Engine**: Laravel Fortify (`config/fortify.php`), but login controllers are custom (`App\Http\Controllers\Auth\*`).
- **Guards**: `web`, `platform_admin`, `institute_user`, `guardian`, `platform_staff` (`config/auth.php:44-69`) — 5 providers, 5 password brokers (all `password_reset_tokens`).
- **Password column**: `password_hash` on all auth models via `getAuthPassword()`/`getAuthPasswordName()`; idempotent setter avoids double-hash (`User.php:103`).
- **Login flow (global user, `web`)**: `POST /login` (throttle 30,15, reCAPTCHA) → `UserLoginController::login` → `Auth::guard('web')->attempt([...,'status'=>'active'], remember)` → optional rehash (`PasswordService::rehashIfNeeded`) → email verification gate → `Workspace::resolveAfterLogin()` → 0 memberships = force picker, 1 = auto-set, N = explicit → redirect `workspace.picker` or intended.
- **Legacy institute login**: `institute_user` guard remains fully functional; `institute/login` now 301-redirects to unified `/login` (`routes/web.php:67-71`), but `auth:institute_user,web` still guards module routes.
- **Platform admin**: `POST /admin/login` (throttle 10,15), guard `platform_admin`.
- **Guardian**: `routes/guardian.php`, guard `guardian`, `POST /guardian/login` (throttle 30,15).
- **2FA**: Fortify `TwoFactorAuthenticatable` on `User`, `InstituteUser`; challenge routes in `routes/auth.php:68-81` (switch method, resend); `SetFortifyGuard` pins `config('fortify.guard')` per request to maintain **one-role-per-session**.
- **OTP channels**: `email_otps`, `phone_2fa_otps`, `phone_verification_otps`, `phone_password_reset_otps` models + phone password-reset routes (`routes/auth.php:48-66`).
- **Password reset**: email (`forgot-password`/`reset-password`, throttle 10,10) + phone OTP variant.
- **Email verification**: `MustVerifyEmail` on User/InstituteUser; queued notification (`QueuedVerifyEmail`) with sync fallback; `verified` middleware on 1487 routes.
- **Lockout**: `failed_login_count` + `locked_until` columns, thresholds from `PlatformSettingsService::effectiveLoginThreshold()`.
- **Account status**: `users.status enum(active|inactive)`; `EnsureInstituteContext` rejects non-active `InstituteUser` even with a valid token (`EnsureInstituteContext.php:27`).
- **Sessions**: `database` driver, 120 min lifetime, no encryption.

## 8. Account types

- `users.account_type enum('owner','staff')` (DDL) → `isOwnerAccount()` / `isStaffAccount()` (`User.php:182-190`).
- **Owner** = organization proprietor. Membership role slug must be `institute-owner`.
- **Staff** = any non-owner role.
- Cross-invariant enforcement:
  - `Membership::assertRoleAllowedForAccountType()` on create/update (`Membership.php:44-53`) → throws `AccountTypeMismatchException` (`staffCannotOwn`, `ownerCannotBeStaff`).
  - `User::assertAccountTypeConsistentWithMemberships()` on account_type change (`User.php:192-203`) → `staffCannotConvert`, `ownerCannotConvert`.
  - Read-time defense-in-depth: `Membership::roleAllowedForAccountType()` used by `Workspace::membership()/verify()/resolveAfterLogin()`. **Note: returns `true` when user or role is null** (`Membership.php:90-91`) — documented fail-open.
- **Owner privilege**: `Membership::hasPermission()` returns true unconditionally for owner (`Membership.php:140`); `CheckPermission`/`CheckModuleAccess`/`CheckFeatureAccess` additionally short-circuit for `PlatformAdmin`.

## 9. Organization model

- **Organization = `Institute`** (table `institutes`). There is **no `organizations` table**; the word "organization" appears only in docblocks (`Workspace`, `Membership`).
- Columns of note: `industry`/`industry_id`, `sub_industry`, `country_id`, `package_id`, `subscription_expiry`, `status`, `verified`, `onboarded_at`, `advanced_accounting_enabled`, `business_entity_type`, share-capital block, soft-delete + `deletion_requested_*`.
- Relations on `Institute`: students, branches, rooms, batches, exams, results, certificates, notices, gallery, invoices, partners, shareholders, dividends, TDS/TAX set, payments, accountHeads, transactions, attendance, cashMemos, offlineSyncQueue, courseRequests, instituteCourses… (see `app/Models/Institute.php`).
- Lifecycle: created → `AppServiceProvider` model events flush `ModuleAccessService` cache and sync industry modules (`AppServiceProvider.php:304-312`).
- Creation/onboarding: `InstituteCreationController`, `InstituteOnboardingController`, `InstituteAdminController` (platform side).

## 10. Multi-tenancy

Implemented as **shared-schema, column-based isolation with global scopes**.

| Mechanism | Location | Behavior |
|---|---|---|
| Context holder | `app/Support/TenantContext.php` | static `?int $instituteId`; `enabled()` false when null → scopes inactive |
| Branch holder | `app/Support/BranchContext.php` | same pattern |
| Middleware | `app/Http/Middleware/SetTenantContext.php` (alias `tenant`) | Guardian → tenant only; InstituteUser → tenant+branch from user; User → workspace session, forged id = 403, absent = auto-resolve first active membership; else cleared |
| Priority | `bootstrap/app.php:101` | `SubstituteBindings` moved **after** `SetTenantContext` so route-model binding cannot resolve foreign rows |
| Global scope | `Models/Concerns/TenantScoped` | `WHERE institute_id = TenantContext::id()`; hybrid `hasGlobalRows()` variant allows `institute_id IS NULL AND is_system = 1` (COA) |
| Create hook | same | force `institute_id` = context; block tampering |
| Update hook | same | revert dirty `institute_id` and `created_by` |
| Branch scope | `Models/Concerns/BranchScoped` | `WHERE branch_id = BranchContext::id()` when enabled; null branch = unrestricted |
| Shared scope | `Models/Concerns/BranchScopedOrShared` | branch rows OR shared (branch_id NULL) |
| Workspace | `app/Support/Workspace.php` | session key `active_institution_id`; `verify()` re-checks active membership + account-type/role compatibility |
| API context | `EnsureInstituteContext` (alias `ensure.institute.context`) | requires active membership or InstituteUser; sets both contexts |
| Medical | `app/Support/MedicalScope.php` | manual resolution used by 52 files; **no global scope on medical models** |

**Enforced at**: route-model binding, Eloquent queries on 223 scoped models, mass-assignment hooks, API (`ensure.institute.context`), policies (`Accounting` policies call `assertInInstitute`-style checks), scheduled audit command `SystemTenantIsolationAudit`.

**NOT enforced / weaker:**
- Any model without `TenantScoped` when queried without explicit `institute_id` (198 model files; among them all 76 medical models and all line-item models like `InvoiceItem`, `SalesOrderLine`, `PurchaseInvoiceItem` — these rely on parent scoping).
- When `TenantContext` is null (CLI, or a route missing `tenant` middleware), scopes are no-ops.
- Static context in queue workers/jobs (`ProcessAnalyzerMessage`, `SendNotificationJob`, `DepreciationRunJob`, `FxRevaluationJob`) — no explicit tenant binding; inheritance from whatever set the context last is **UNKNOWN** without reading each job's call site.
- `SalesInventoryIntegration::findItem/checkStock/availableItems` use `withoutGlobalScopes()` but re-apply explicit `institute_id`/`branch_id` filters (currently orphaned code anyway).

## 11. Branch model

- `branches`: `code varchar(4)` **globally unique** (not per-institute — potential cross-tenant code collision is prevented only by uniqueness, so codes must be globally distinct), `institute_id`, `manager_user_id` → `institute_users`, `is_principal`, `status`, `deleted_at`.
- Membership carries `branch_id` (nullable). **NULL branch = owner/institute admin → sees all branches** (`SetTenantContext.php:83-87`).
- `InstituteUser.branch_id` fixed per account.
- Branch-scoped tables: 35+ models carry `branch_id` (students, batches, notices, transactions, inventory items, warehouses, journals…).
- Inherited scoping: rows without their own `branch_id` (attendance, results, invoices…) are scoped through their owning model — documented in `BranchScoped` docblock.
- Guardian explicitly gets `BranchContext::clear()` to follow students across branches of their institute (`SetTenantContext.php:31-35`).

## 12. Roles & permissions

- **Roles**: `roles` with `institute_id NULL` = **system/global role**, non-null = tenant role. Unique `(institute_id, slug)`.
- **System roles**: `SystemRoleSeeder` + `RoleSeeder` (ensures `institute-owner`). Medical roles via `MedicalRoleSeeder`.
- **Tenant roles**: `RoleTemplateService::seedForInstitute()` (invoked by `RoleTemplateSeeder` and institute-creation flow) creates industry-specific roles, e.g. `manager`, `teacher`, `*-accountant`, `*-manager`, `receptionist`, `branch-manager`, `institute-admin`, `accountant`, with explicit permission slug lists; unknown slugs are skipped and reported.
- **Permissions**: `permissions(slug uq, module, name)` seeded by ~15 permission seeders (`AccountingPermissionSeeder`, `SalesPurchasePermissionSeeder`, `StaffPermissionSeeder`, `EducationPermissionSeeder`, `MedicalPermissionSeeder`, `TaxPermissionSeeder`, `TrainingCenterPermissionSeeder`, `AiToolPermissionSeeder`, `ModuleTogglePermissionSeeder`, `RestaurantPermissionSeeder`, `LabAnalyzerPermissionSeeder`, `AccountingAgingPermissionSeeder`, `AdminPermissionSeeder`, `EducationAccounting…`). Observed modules: accounting, admin, crm, dashboard, education, finance, institute-settings, medical.*, purchase, restaurant, sales, settings, staff, tax, training*.
- **Grants**: `role_permissions` seeded by `RolePermissionSeeder` (global roles only: institute-owner, institute-admin, branch-manager, accountant) + per-tenant by `RoleTemplateSeeder`.
- **Enforcement points**:
  1. `permission:slug[,slug]` route middleware → `CheckPermission` (1396 of 2102 authenticated routes have some gate).
  2. `module_access:key[,key]` → `CheckModuleAccess` → `ModuleAccessService::isEnabled()` (subscription/entitlement based).
  3. `feature:key` → `CheckFeatureAccess` → `isFeatureEnabled()` (registry + package + overrides).
  4. `medical`/`medical.module` → `MedicalDomain` / `MedicalModuleAccess`.
  5. `advanced.accounting` → tenant toggle.
  6. `deny.teacher.finance`, `finance.write` → deny-lists (fail-open).
7. Controller-level: `AuthorizesPermission::requirePermission()` (19 call sites, 5 controllers: Expense, BillPayment, ReceivePayment, SalesInvoice, SalesReceipt) and `Gate::authorize()` (50 call sites, 12 controllers - finance/settings/accounting only).
  8. Policies bound explicitly in `AppServiceProvider.php:78-118` for 9 accounting models.
- **NOT ENFORCED / GAP**: 706 authenticated routes carry no permission/module/feature middleware and those controllers mostly don't call `authorize()`/`requirePermission()` (e.g. most `accounting/*` report and bank-feed routes, `academic/*` structure routes, `account/security/*`). Access there is protected only by authentication + tenant scope.

## 13. Core modules

See `docs/MODULE_REGISTRY.md` for the per-module registry (routes/controllers/services/models/tables/permissions/tests). Summary matrix in §21 below.

## 14. Module dependencies

- **Foundation**: tenancy (TenantContext/scopes), auth+RBAC, `module_registry`/`feature_registry`, `ModuleAccessService`, Blade directives.
- **Finance/Accounting** depends on: `ChartOfAccount`, `FiscalYear`/`AccountingPeriod`, `JournalPostingService`, `Party`, `PaymentMethod`, `Tax`.
- **Inventory** depends on: `InventoryCapabilityService` (feature gate) → `InventoryStockService` → `InventoryAccountingService` → `JournalPostingService`.
- **Sales/Purchase** depend on: `InventoryStockService` (stock effects) and `Accounting\InvoiceService`/`PurchaseAccountingService` (journal postings), `SalesSequence`/`PurchaseSequence` (doc numbering), `Party` (customers/suppliers), `SalesSettingsService`/`PurchaseSettingsService`.
- **Education finance** depends on accounting (`StudentFinanceService` → `JournalPostingService`).
- **HR payroll** depends on accounting (`HrPayrollFinanceService` → `JournalPostingService`).
- **Fixed assets** depends on accounting (`FixedAssetAccountingService`).
- **Tax** depends on accounting (`TaxAccountingService`).
- **CRM** is mostly standalone but `EducationCrmIntegrationService` bridges admissions→CRM; sales leads convert to quotations.
- **Medical** is largely self-contained (own invoice model `App\Models\Medical\Invoice`, own pharmacy stock) with `MedicalModuleAccess` gating.
- **AI** (`AiToolRegistry`) exposes read-only tools over finance/HR data; gated by `ai.enabled` middleware + `AiConfig`.

## 15. Major workflows (verified end-to-end)

### Sales
`GET/POST sales/quotations` → `QuotationController` → `QuotationService` → `SalesQuotation(+lines)`
→ `POST sales/orders/convert/{quotation}` → `SalesOrderController@convert` → `SalesOrderService`
→ `approve/submit/processing/ready/complete` state actions on `sales/orders/{order}/*`
→ `POST sales/deliveries` → `DeliveryController@store` → `DeliveryService` (calls `InventoryStockService::saleIssue`)
→ `POST sales/deliveries/{delivery}/invoice` → `SalesInvoiceController@storeForDelivery`
→ `POST sales/payments` → `ReceivePaymentController@store` → payment + accounting
→ returns: `sales/returns|credit-memos` with `approve/post/refund/reverse/credit-note/invoice` actions (`SalesReturnService` uses `InventoryStockService::returnStock` + `JournalPostingService`).
Reports: `sales/reports/*` (12 endpoints) via `SalesReportController`.

### Purchase
`purchase/requests` → `purchase/quotations` → `purchase/orders` (+`GoodsReceipt` via `purchase/goods-receipts`) → `purchase/invoices` (+`post`) → `purchase/supplier-payments` → `purchase/returns|vendor-credits`. Services: `PurchaseRequestService`…`PurchaseReturnService`; `GoodsReceiptService` calls `InventoryStockService::receivePurchase`.

### Inventory
Only `InventoryStockService` writes `inventory_movements` (methods: `receivePurchase`, `saleIssue`, `transfer`, `postAdjustment`, `postCount`, `returnStock`, `returnForReference`). `inventory_stock_levels` updated in the same transaction under row locks, weighted-average costing. Accounting side-effects via `InventoryAccountingService` → `JournalPostingService`. UI: `inventory/items|warehouses|adjustments|transfers|batches|stock-ledger|barcode-search`.

### Accounting
Manual journal: `POST finance/journals` → `JournalPostingService::create(postNow)` → validate balance → `post()` → status `posted` → `JournalPosted` event. Reverse/void only, never delete. Period close/reopen gated by `advanced.accounting`. Reports: `accounting/reports/*` + `finance/reports/*` (trial balance, balance sheet, income statement, cash flow, ledger, payables, receivables, aging, VAT, ratio analysis, executive dashboards).

### Education fee flow
`finance/education/fee-structures` → `MonthlyFeeGenerationService`/`GenerateMonthlyFeeInvoices` → `StudentFinanceService` → `Invoice` + `Payment` → `JournalPostingService`.

### HR payroll
`hr/payroll/periods/{period}/generate` → `HrPayrollService` → `hr_payrolls/items` → `HrPayrollFinanceService` → `JournalPostingService`.

### POS / Manufacturing / Real Estate / Restaurant
**No workflow exists** — see §7 status and §20.

## 16. Important business rules

1. Owner/staff account-type invariant (§8).
2. Owner = superuser over permission matrix.
3. Workspace session id must equal a verified active membership (else 403), except auto-resolve fallback when absent.
4. Journal balance + period-open + immutability rules (`JournalPostingService`).
5. Weighted-average inventory valuation, single write path, negative-stock epsilon `0.00005` (`InventoryStockService::NEGATIVE_STOCK_EPSILON`).
6. Module/feature entitlement chain: package → scoped module/feature → institute overrides → runtime `ModuleAccessService`; platform admin bypasses.
7. Advanced accounting must be enabled for fiscal-year/period/journal-advanced routes.
8. Teachers denied finance URIs; receptionist/branch-manager denied a hard-coded finance write list.
9. Doc-number allocation per tenant+branch: `sales_sequences`, `purchase_sequences`, `number_sequences`, `hr_employee_code_sequences`, `teacher_code_sequences`, `hr_payroll_no_sequences`.
10. Tax: `TaxRate`/`TaxGroup`/`TaxRule` + country configs (`country_tax_configs`, `country_tax_modules`); TDS lifecycle with certificates and receivables.
11. Soft-delete safety: `RecycleBinController`, `AccountDeletionGovernance`, `TenantProtectionService`, `DeletionSafety` tests.

## 17. Important database rules

See §6 plus:
- **Do not assume `migrate` builds the schema.** Base = `database/schema/mysql-schema.sql`.
- Migrations must be idempotent (project convention; all 240 re-runnable over dumps).
- `chart_of_accounts`/`account_groups` support hybrid global rows (`institute_id NULL` + `is_system`) — use `hasGlobalRows()` semantics; migrations `2026_09_20_120947`/`122040`.
- Raw `DB::table('institution_user')` is used in code (e.g. `SetTenantContext.php:61`) — the singular table name is load-bearing.
- `institute_users` and `institution_user` are **different tables** for different auth realms.

## 18. Frontend architecture

See §5 and `docs/AI_HANDOFF.md`. Conventions source of truth: `docs/promptRules.md`, `docs/design-conventions.md`, `docs/standard-list-page.md`.

- Layouts: `layouts/institute.blade.php` (tenant shell, dynamic sidebar from module registry + hardcoded medical nav), `layouts/admin.blade.php` (platform), `standalone`, `app`.
- Reusable: `resources/views/components/` (11), `partials/` (3), Livewire components for heavy lists.
- Permission-aware UI: `@moduleEnabled`, `@featureEnabled`, `@featureLocked`, `@featureHidden`, `@term` (registered in `AppServiceProvider`).
- Organization switching UI: `WorkspaceController` + `workspace/*` routes (7) + `workspace.picker`.
- Branch switching: no standalone UI found — branch comes from membership (**UNKNOWN** whether a switcher exists beyond membership editing).
- Flash messages rendered globally by layout (`session('status')`).
- React islands: `resources/js/medical/{appointments,queue,patients,prescriptions}.jsx` mounted from Blade, served by `MedicalReactController` API.

## 19. API architecture

See `docs/AI_HANDOFF.md` §"API architecture". Additional detail:
- Auth: `POST api/login` (email/phone + password) → Sanctum token; `auth:sanctum` group; `ensure.institute.context` re-establishes tenant/branch per request; `ForceJsonResponse` makes all responses JSON.
- Authorization mirrors web: `permission:*` + `module_access:*` on each endpoint.
- Response shape: `{success, message, data...}` (custom), plus 22 Eloquent Resources for list/detail payloads.
- Rate limits: `throttle:10,1` (login/verify), `throttle:60,1` (authenticated).
- Separate device-auth channel: `lab.device` middleware (`AuthenticateLabDevice`) + `routes/api.php` lab gateway endpoints + `LabGatewayController`.
- **Public vs internal**: 54 routes carry no auth middleware: 27 auth entry points (login/register/logout), 9 Livewire assets, 6 framework/static (up, storage/{path}, roadcasting/auth, sanctum/csrf-cookie, dev/page-marker), 6 public geo/verification APIs (geo/units, geo/levels/{country}, erify/certificate*, pi/login, pi/verify/certificate/{number}), 5 lab-device endpoints (pi/lab-gateway/*, guarded by AuthenticateLabDevice), plus the home page.

## 20. Security model

See `docs/ARCHITECTURE_AUDIT_REPORT.md` §Security for the classified list. Architectural summary:

- Auth: multi-guard Fortify, bcrypt, 2FA (app+email+SMS OTP), lockout, reCAPTCHA, throttling, queued verification.
- AuthZ: 3-layer (route middleware, controller, policy) — coverage incomplete (§12 GAP).
- Tenant isolation: global scopes + middleware ordering + create/update hooks (strong where applied).
- Input validation: 50 FormRequests + inline `$request->validate()` (coverage varies).
- Output: Blade `{{ }}` escaping by default; CSP header allows `'unsafe-inline'` and `'unsafe-eval'` for scripts (practical XSS mitigation is weakened — MEDIUM).
- CSRF: framework middleware; 419 handling customized.
- Mass assignment: 255/421 models `$guarded = []` → mitigated by validation + tenant hooks (MEDIUM).
- Secrets: `.env` gitignored (verified); `.opencode/` and `opencode.json` gitignored; `AI_GEMINI_API_KEY`, `RECAPTCHA_*`, SMTP password present locally → `[REDACTED]`.
- Audit: 11 audit models + 30+ `*AuditService` classes; **but** `AuditActivityLog` middleware is unregistered (GAP).

## 21. Testing

- 504 test files; suites Unit + Feature; `DatabaseTransactions` (no migrations); DB `monetix_test` must be pre-provisioned from `database/schema/full_data.sql`.
- Well covered: tenant isolation (`TenantIsolationAuditTest`, `TenantSecurityTest`, `BranchAuthorizationTest`, `Phase18BranchIsolationTest`), sales/purchase lifecycles, accounting integrity, security (`MassAssignmentTest`, `SecurityAuditTest`, `SecurityForensicTest`, `P1HardeningTest`), medical phases 1–18, entitlement/feature gating, geo, HR, education, training.
- Weak: POS/Manufacturing/RealEstate/Restaurant (registry-only tests), `platform_staff` realm, no browser/E2E suite beyond `E22RealBrowserReproTest`, no CI.
- Observed results (this environment): Unit 73/78 pass; filtered feature run 49/57 pass (8 failures, incl. role-dependent 403s).

## 22. Deployment assumptions

- cPanel/Apache + PHP 8.2.12 + MySQL, XAMPP for local (`APP_URL=http://localhost/AccumenAI/public`).
- Root `.htaccess` rewrite to `public/`.
- `composer setup` / `composer dev` scripts; queue worker must run (`queue:listen database`); optional `reverb:start` for lab analyzer live mode.
- Backups: scheduled `database:backup` daily/weekly with verification; `scripts/safe-dump.sh`, `optimize-cpanel.sh`.
- No Docker/CI/CD (explicitly deferred in `CHANGELOG.md`).

## 23. Current implementation status

| Area | Status | Evidence |
|---|---|---|
| Authentication (5-guard Fortify) | IMPLEMENTED | `config/auth.php`, `routes/auth.php`, `app/Http/Controllers/Auth/*` |
| 2FA / OTP / lockout / reCAPTCHA | IMPLEMENTED | `routes/auth.php:68-81`, OTP models, `LoginAttempt` |
| Owner/staff account model | IMPLEMENTED | `users.account_type`, `Membership.php:60-97` |
| Organization (institute) + branches | IMPLEMENTED | `institutes`, `branches` DDL; controllers |
| Workspace switching | IMPLEMENTED | `app/Support/Workspace.php`, `workspace/*` routes |
| RBAC (roles/permissions) | IMPLEMENTED (matrix partially seeded) | seeders + `role_permissions` |
| Permission enforcement | PARTIAL | 1396/2102 gated; deny-lists fail open |
| Multi-tenancy (scoped models) | IMPLEMENTED for 223 models | `TenantScoped` |
| Multi-tenancy (medical) | PARTIAL (manual filters) | 0 global scopes in `Models/Medical` |
| Sales | IMPLEMENTED | 119 routes, `Services/Sales/*` (13) |
| Purchase | IMPLEMENTED | 116 routes, `Services/Purchase/*` (10) |
| Inventory | IMPLEMENTED | 25 routes, `Services/Inventory/*` (6), stock engine |
| Accounting (double-entry) | IMPLEMENTED | `JournalPostingService`, 66 accounting + 132 finance routes |
| Fixed assets | IMPLEMENTED | 16 routes, `Services/FixedAsset/*` (7) |
| Tax/TDS/VAT | IMPLEMENTED | `Services/Tax/*`, tax tables & routes |
| HR + Payroll | IMPLEMENTED | 145 routes, 14 Hr controllers, HR services |
| CRM | IMPLEMENTED | 34 routes, `Services/Crm*` |
| Education/Academic | IMPLEMENTED | 152 education routes, 20 academic controllers |
| Medical/HMS | IMPLEMENTED | 490 routes, 65 controllers, 76 models, 233 views |
| Lab analyzer integration | IMPLEMENTED | `LabIntegration` services, Reverb, `lab.device` |
| Training center | IMPLEMENTED | 86 routes, `Training/*` controllers |
| Documents/Notifications/Reports/Settings/Workflow | IMPLEMENTED | routes + services |
| SaaS packaging & bKash | IMPLEMENTED | `SaasSubscriptionService`, `saas/*` routes |
| AI assistant | IMPLEMENTED | `Services/Ai/*` (8), `ai/*` routes |
| POS | **PLANNED** | `config/pos.php` + registry migrations; 0 routes/models |
| Manufacturing | **PLANNED** | `config/manufacturing.php` + registry; 0 routes/models |
| Real Estate | **PLANNED** | 6 migrations; 0 routes/controllers |
| Restaurant | **PLANNED** | migrations + industry row; 0 routes/controllers |
| Platform staff login realm | **UNUSED** | guard defined, 0 routes |
| `AuditActivityLog` middleware | **UNUSED** (not registered) | absent from `bootstrap/app.php` & routes |
| `InvoicePaid` event | **UNUSED** (never dispatched) | 0 call sites |
| `SalesInventoryIntegration` | **UNUSED** (no callers) | grep = 1 file |

## 24. Known gaps

1. 706 authenticated routes without permission/module/feature middleware.
2. Medical models lack global tenant scope.
3. 255 models with `$guarded = []`.
4. `AuditActivityLog` not wired.
5. `InvoicePaid` event/listener dead.
6. `SalesInventoryIntegration` orphaned.
7. Schema not reproducible by `migrate` alone.
8. Stale docs (`docs/audit/00-overview.md`, `docs/structure.md`, `phpunit.xml` comment).
9. `platform_staff` guard dead.
10. No CI/CD; unit tests currently failing (5) and some feature tests failing (8 in sample run).
11. `Membership::roleAllowedForAccountType()` returns `true` for null user/role (documented fail-open).
12. `Workspace` fallback in `CheckPermission`/`CheckModuleAccess` can fall back to the user's **first** active membership when no workspace is set (`CheckPermission.php:44`) — permission may be evaluated against a different org than the one later bound.

## 25. Known technical debt

- Duplicate/parallel identity realms (`users`+`institution_user` vs `institute_users`) with duplicated `hasRole/hasPermission/isOwner` logic on `User`-path and `InstituteUser`.
- Two finance UIs (`finance/*` and `accounting/*`) with overlapping report endpoints (e.g. balance-sheet/trial-balance exist under both prefixes).
- `withoutGlobalScopes()` used in several services — each must re-apply tenant filters manually.
- Static global contexts (no request-scope container binding) → risky for jobs/CLI/tests.
- Enormous Blade layouts (1832-line institute shell) and a 1938-line route file.
- `.bak` artifacts in source (`app/Models/Attendance.php.bak`, `config/queue.php.bak`).
- Legacy naming (`monetix`, `mawa_*`, `institution_user`) vs current product name.
- ~100 SQL dumps in `backups/` and multiple schema files in `database/schema/` (drift risk).

## 26. Important conventions

- Naming: Services `XxxService`; controllers grouped by domain namespace; permissions slug `module.action`; module keys dot-namespaced (`medical.pharmacy`, `training_center.courses`).
- UID: `uid varchar(10)` (6 alnum + 4 numeric) via `generateUniqueUid()`/fallback in `User::booted`.
- UUID: `uuid char(36) DEFAULT uuid()` — used as a secondary public identifier, never as PK.
- Migrations: idempotent, often `DB::table()` registry seeding rather than schema.
- Pagination: `paginate(20)->withQueryString()` (per `docs/promptRules.md`).
- Localization: `lang/mawa/{en,bn}.php` + `mawa_e()`/`mawa_translate()` helpers + `SetLocale` middleware; `@term` blade directive for per-tenant terminology overrides.
- Page markers: `storage/app/page-markers.json` (`N-` page, `N+` popup) — a project-specific navigation aid.

## 27. Important files/classes

See `docs/AI_HANDOFF.md` §"Important files" and `docs/FILE_INDEX.md` for the full indexed list.

## 28. Things an AI agent MUST NOT assume

1. ~~"There is an `organizations` table"~~ → it's `institutes`; membership FK is `institution_id`.
2. ~~"`password` column"~~ → `password_hash`.
3. ~~"`php artisan migrate` builds the schema"~~ → it doesn't; use the schema dump.
4. ~~"All tenant models have global scopes"~~ → 198 don't, including all medical models.
5. ~~"Routes with `auth` are permission-checked"~~ → 706 aren't.
6. ~~"POS/Manufacturing exist"~~ → config/registry only.
7. ~~"Livewire/API/Sanctum don't exist"~~ → old docs say so; they do exist.
8. ~~"`organization_id`/`tenant_id` columns"~~ → `institute_id`.
9. ~~"The role→permission matrix is fully seeded"~~ → only specific roles/slugs; owner bypasses anyway.
10. ~~"Events/listeners all fire"~~ → `InvoicePaid` never dispatched.
11. ~~"Repository pattern / DTOs"~~ → not used; services + models.
12. ~~"`RefreshDatabase`"~~ → tests use `DatabaseTransactions` against a pre-built DB.
13. ~~"Money in floats"~~ → decimals with explicit rounding.
14. ~~"The audit middleware logs logins"~~ → not registered.

## 29. Safe extension guidelines

1. Read `docs/AI_DEVELOPMENT_RULES.md` first; mirror an existing sibling feature end-to-end (route group → controller → service → model → view → test).
2. New tenant table → add `institute_id` (and `branch_id` if applicable) + `use Concerns\TenantScoped;` + explicit migration with idempotent guards.
3. New route → place inside the correct existing group (`$tenant` group in `institute_modules.php`, `routes/medical.php`, `admin` group) so auth/tenant/verified/permission middleware apply automatically.
4. Gate it: add `permission:` (create the slug in a permission seeder) and `module_access:`/`feature:` if it's a distinct capability.
5. Validate with a FormRequest in the matching namespace; never trust request `institute_id`.
6. Put logic in a Service; keep the controller thin; wrap multi-write operations in `DB::transaction`.
7. For financial effects, call `JournalPostingService` — never write `journal_entries` directly.
8. For stock effects, call `InventoryStockService` — never write `inventory_movements` directly.
9. Blade: extend an existing layout, follow Standard List Page pattern, no inline `<style>`, modals in `@push('modals')`.
10. Add Feature tests using `DatabaseTransactions`; do not migrate; assume seeded roles/permissions exist or seed them in the test.
11. Before finishing: `php artisan test --filter=<yours>`, `php artisan view:clear`, and (for assets) `cmd /c "npm run build"`.

## 30. Current roadmap (as discoverable from the codebase)

- `docs/ROADMAP_CONSOLIDATED_V1.md` (V1): Phase 0 architecture contract → Phase 1 hardening → Phase 2 taxonomy cutover → Phase 3 feature registry → Phase 4 package feature entitlement → Phase 5 feature runtime authorization → Phase 6 generic module configuration → Phase 7 institute configuration override → Phase 8 unified effective resolution engine → Phase 9 remove country hardcoding. **DOCUMENTATION ≠ fully verified against code; phases 3–5 are evidenced by `feature_registry` tables, `CheckFeatureAccess`, and tests (`FeatureRegistryPilotTest`, `CheckFeatureAccessMiddlewareTest`).**
- `docs/memory.md` records Phase 1 acceptance, bugs registry B1–B12, feature registry phases, and a security audit dated 2026-09-22.
- Recent git history shows active work on: **accounting aging reports (phases A/B)**, **country pricing expansion (EU/SAARC)**, **reCAPTCHA deployment doctor**, **Medical→Healthcare display rename**.
- `CHANGELOG.md` "Deferred (Phase 9)": per-shard test DBs, CI/CD pipeline, production monitoring.
- Module registry forward-looking keys (`bom`, `property`, `pos.*`, `manufacturing.*`, `restaurant.*`) are inserted but "skipped, never rendered as broken rows" (`config/module_groups.php` header) → they are product roadmap placeholders.
