# Accounting · HR · Inventory — Current Platform Features

> Updated: 2026-08-22 — reflects codebase `C:\xampp\htdocs\monetix` after Academic Dashboard + HR 4-tab + Payroll 5-tab + module-cache fix. No dummy pages; all routes/controllers/services audited via `Glob/Grep/Read`.

## 1. Module Registry & Packages

`database/migrations/2026_09_02_000100_create_saa_s_module_tables` seeds `module_registry` (11 keys):

| Key | Type | Used by routes | Navbar |
|---|---|---|---|
| `crm` | core | `module_access:crm` | `workspaceAllowedCrm` |
| `accounting` | core | (package only) | — |
| `finance` | core | `module_access:finance` | `workspaceAllowedFinance` |
| `inventory` | core | **0** `module_access:inventory` (engine via `purchase`/`sales`) | **none** — headless engine |
| `hr` | core | `module_access:hr` | `workspaceAllowedHr` |
| `sales` | core | `module_access:sales` | `workspaceAllowedSales` |
| `purchase` | core | `module_access:purchase` | `workspaceAllowedPurchase` |
| `reports` | core | package only | — |
| `notifications` | core | — | — |
| `ai` | core | `ai.enabled:assistant` | `aiEnabled` |
| `education` | industry | `module_access:education` (students/batches/exams, now **not** `academic-dashboard`/`teachers`/`admissions`/`settings`) | `workspaceAllowedEducation` |

Packages (canonical `FREE/BASIC/ADVANCED/PREMIUM` after `2026_09_15_000100`):

| Package | Modules enabled |
|---|---|
| **FREE** | `crm,notifications` (2) |
| **BASIC** | `crm,finance,reports,notifications,education` (5) |
| **ADVANCED** | `crm,finance,accounting,reports,notifications,ai,education,sales` (8) |
| **PREMIUM** | all 11 (`crm,finance,accounting,inventory,hr,reports,notifications,ai,education,sales,purchase`) |

Entitlement chain: `package_modules` → `institute_module_overrides` → `institute_module_entitlements` (active/trialing, `is_grant`, `starts/ends_at`, latest `updated_at` wins) → `industry` gate (only `education` gated) → `checkDependencies`. Single source `ModuleAccessService::isEnabled()` + `CheckModuleAccess` middleware (403, never 404). Cache `module_access:{institute_id}` 3600s, now auto-flushed on `Institute::created/updated(package_id/status)/deleted` + `DB::listen` for bulk `DB::table` updates (fix for Supermarket `id=17` stale).

---

## 2. Accounting (Finance) — Implemented

**Migrations:** `2026_08_19_010100_create_accounting_coa_tables`, `2026_08_19_010400_create_accounting_journal_tables`, `2026_08_25_000100_create_inventory_tables` (inventory CoA), plus fiscal year/period, tax, asset, budget, FX migrations. **No** `finance_transactions` or `cash_book` tables — replaced by `journals/journal_entries`.

**Chart of Accounts:** 32 template accounts via `AccountingSetupService::setupForInstitute()` — required codes `1001 Cash, 1002 Bank, 1100 AR, 1200 Inventory, 1300 Fixed Assets, 2001 AP, 2100 VAT Payable, 3002 Retained Earnings, 4901 FX Gain, 5901 FX Loss`. Read-only `accountByCode`, overrides per `inventory_categories/items`.

**Journals:** `journals` + `journal_entries` (`journal_id` FK). All postings via `JournalPostingService::create` (balanced, period-lock, immutability, `type` enum `sale/purchase/receipt/payment/journal/contra/opening/adjustment`).

**Fiscal & Periods:** `fiscal_years` (`is_current`), `accounting_periods` monthly, `closing` via `AccountingPeriodController`/`FiscalYearController` (`settings.accounting.manage`).

**AR/AP:** `parties` unified `customer|supplier|both` + `branch_id`, `ReceivablesPayablesService::totals()` derived from GL `is_receivable/is_payable`.

**Inventory valuation:** `inventory_stock_levels` weighted-avg `avg_cost` via `InventoryStockService::move()` (`lockForUpdate`, `EPSILON 0.00005`), `inventory_movements` signed ledger is source of truth, `InventoryReconciliationService::reconcile()` rebuilds drift.

**Tax/VAT:** `tax_groups`/`tax_rates`/`tax_jurisdictions`, `TaxComplianceService`.

**Assets:** `fixed_assets`, `asset_categories`, `DepreciationRunJob` daily 02:00.

**Budget:** `budgets` workflow draft→submitted→approved→locked, `BudgetCalculationService` actuals from posted journals.

**FX:** `exchange_rates`, `fx_revaluations`, `FxRevaluationJob` daily 03:00.

**Reports:** `FinancialReportService::trialBalance/balanceSheet`, `AccountingReconciliationService`.

**Routes:** `module_access:finance` group `finance/*` (chart-of-accounts, journals, invoices, payments, parties, payment-methods, periods, opening-balances, exchange-rates, fx-revaluations, audit, education fees, budgets, online-payments) + `accounting/*` (dashboard, reports, periods, fiscal-years). All `auth:institute_user,web` + `tenant` + `permission:*`.

---

## 3. HR — Implemented

**Migrations:** `2026_09_03*` HR core, `2026_09_04*` attendance/leave/payroll, `2026_09_05*` recruitment/performance/training, `2026_08_25` inventory-adjacent.

**Tables:** `hr_employees` (tenant+branch, `employee_code` unique, `institute_user_id` nullable link to `institute_users`, `reporting_manager_id`, `employment_status`), `hr_departments`, `hr_designations`, `hr_documents` (via `documents` polymorphic), `hr_attendances`/`hr_attendance_corrections`/`hr_shifts`/`hr_holidays`, `hr_leave_types/balances/applications`, `hr_salary_structures`, `hr_payrolls`/`hr_payroll_periods`, `hr_requisitions`/`hr_vacancies`/`hr_applications`/`hr_interviews`/`hr_offers`, `hr_performance_reviews`/`hr_kpis`, `hr_training_programs`/`hr_enrollments`, `hr_documents`.

**Services:** `HrEmployeeService`, `HrAttendanceService`, `HrLeaveService`, `HrPayrollService`, `HrSalaryStructureService`, `HrRecruitmentService`, `HrPerformanceService`, `HrTrainingService`, `HrSelfService::resolveEmployee(?int)` (now nullable, fixes `hr/manager/dashboard` TypeError for `web` Owner without employee row), `HrAuditService`.

**Controllers & Routes (`module_access:hr` group `hr/*`):**
- `HrDashboardController@index` (`hr.dashboard`), `HrEmployeeController` (CRUD + transfer/promote/resign/terminate), `HrDepartment/DesignationController`, `HrAttendanceController` (dashboard/daily/mark/corrections/shifts/holidays), `HrLeaveController` (dashboard/types/balances/applications), `HrPayrollController` (periods/generate/approve/pay/reconciliation/payslip), `HrSalaryStructureController`, `HrRecruitmentController`, `HrPerformanceController`, `HrTrainingController`, `HrSelfServiceController`, `HrManagerController` (now 403 not 500 when no employee), `HrReportController`.

**UI:**
- Sidebar: `Payroll` (above `HR`, active for `hr.payroll.*|hr.performance.*|hr.salary-structures.*|hr.training.*`) + `HR` (`hr.dashboard`). Previous 11-item HR menu trimmed to 2 top-level for clarity; sub-links removed from navbar per request — 5 payroll sub-items now **inside** `hr/_payroll_tabs.blade.php` (Payroll | Performance | Salary Structure | Reconciliation | Training) with `data-tab-switch` seamless, and HR’s 4 tabs (`hr/_tabs.blade.php`: HR | Employees | Attendance | Leave) on `hr/dashboard`, `hr/employees/index`, `hr/attendance/dashboard`, `hr/leave/dashboard` (seamless-fade opacity-only, no shake).

**Permissions:** `hr.employee.view/create/update/delete`, `hr.department.*`, `hr.designation.*`, `hr.attendance.view/manage`, `hr.leave.*`, `hr.salary.*`, `hr.payroll.*`, `hr.performance.*`, `hr.training.*`, `hr.team.view`, `hr.self.*`, `hr.reports.view` seeded to `institute-owner/admin`.

---

## 4. Inventory — Engine Complete, UI Headless

**Migrations:** `2026_08_25_000100_create_inventory_tables` (13 tables).

**Tables:** `inventory_categories` (self `parent_id`, CoA overrides), `inventory_warehouses` (`branch_id` nullable = shared), `inventory_items` (`sku` unique `institute+branch`, `barcode` unique `institute`, `item_type` 7 values, `reorder_level`), `inventory_batches` (`lot_number`, `expiry_date`), `inventory_serial_numbers`, `inventory_movements` (signed `quantity`, `movement_no` unique, `journal_id`), `inventory_stock_levels` (cached `quantity+avg_cost` per `warehouse+item+batch`), `inventory_transfers`/`transfer_items`, `inventory_adjustments`/`adjustment_items`, `inventory_counts`/`count_items`.

**Services:** `InventoryItemService` (master CRUD, sku/barcode unique, delete guards), `InventoryStockService::move()` **single write path** (transaction + `lockForUpdate`, weighted-avg, `allow_negative_stock` gate), `InventoryCapabilityService` (17 toggles from `config/industry_rules.capabilities` per industry + tenant override), `InventoryAccountingService` (purchaseReceipt `Dr Inventory/Cr AP 2001`, saleIssue `Dr COGS/Cr Inventory`, adjustment `4005/5008`/`5009` wastage), `InventoryReportService` (`lowStock`, `stockOnHand`), `InventoryReconciliationService` (rebuilds `stock_levels` from ledger).

**No `inventory.*` web/API routes** — intentionally headless; stock is mutated via `purchase/receipts` (`receivePurchase`) and `sales/deliveries` (`saleIssue`). Inventory master not exposed; permissions `inventory.view/create/update/adjust/transfer/count/approve/post/reports.view` exist but unused in routes.

**Finance integration:** Every `receipt/issue/adjustment/return` creates a `journal_id` on `inventory_movements` (except `transfer` which is qty-only by design).

---

## 5. Navbar & Access

`app/Providers/AppServiceProvider View::composer` shares `workspaceAllowed*` = `isEnabled(module) && hasPermission` (single source). `layouts/admin.blade.php` uses it:

- HR/Payroll above HR, Finance, Sales, Purchase (`purchase.orders.index`), CRM, Education (`academic.dashboard` now `isEducation` only, no module gate; `academic.analytics` now `permission` only), Settings moved out of `module_access:education` to `auth+tenant` (so `FREE` retail sees Settings).

Direct URL bypass → `CheckModuleAccess` 403, never 404 (cleared `config:cache`/`route:cache` stale `APP_URL=http://localhost/monetix/public` handled via Symfony `baseUrl=/monetix/public`).

---

## 6. Where We Are Going

- Inventory Master UI (`inventory/*` CRUD for categories/warehouses/items) — decide if `inventory` remains `PREMIUM`-bundled with `purchase` or separately entitlable, then add `module_access:inventory` routes.
- HR: keep 4-tab HR + 5-tab Payroll seamless strips; consider exposing Departments/Designations via HR tabs if needed.
- Accounting: queue `sync` for production (`QUEUE_CONNECTION=database`), scheduler cron, health-check per tenant.

