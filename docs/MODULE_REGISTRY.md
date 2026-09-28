# ACCUMENAI — MODULE REGISTRY

Per-module inventory derived from actual routes, controllers, services, models, tables, seeders, and tests.
Route counts come from `php artisan route:list --json` (2156 total). Status vocabulary: **IMPLEMENTED / PARTIAL / PLANNED / DEPRECATED / UNUSED**.

---

## 1. Authentication & Accounts

**Status: IMPLEMENTED**

- **Purpose**: multi-guard login, 2FA, OTP, verification, lockout, workspace selection.
- **Dependencies**: Fortify, Sanctum (API), reCAPTCHA, `Support\Workspace`, `Support\PasswordHash`.
- **Routes**: `routes/auth.php` (forgot/reset password email+phone, 2FA challenge, email verify, `account/security/*`), `routes/web.php` (`/login`, `/admin/login`, `/guardian/login`, `/logout`), `workspace/*` (6).
- **Controllers**: `Auth/UserLoginController`, `PlatformAdminLoginController`, `InstituteUserLoginController`, `InstituteUserRegisterController`, `OwnerRegisterController`, `GuardianLoginController`, `TwoFactorChallengeController`, `ForgotPasswordController`, `ResetPasswordController`, `PhonePasswordResetController`, `VerificationPromptController`, `SecurityController`, `LogoutController`, `RegistrationFlowController`, `WorkspaceController`, `InstituteOnboardingController`.
- **Services**: `Auth/PasswordService`, `UserAccountService`, `AccountDeletionService`, `AccountDeletionGovernance`, `AccountInactivityService`, `Identity/*` (9), `Platform/PlatformSettingsService`.
- **Models/Tables**: `users`, `institution_user` (Membership), `institute_users`, `platform_admins`, `platform_staffs`, `guardians`, `email_otps`, `phone_2fa_otps`, `phone_verification_otps`, `phone_password_reset_otps`, `login_attempts`, `sessions`, `password_reset_tokens`, `identity_audit_logs`.
- **Permissions**: n/a (guard-based); reCAPTCHA/throttle gate login.
- **Events/Jobs**: `QueuedVerifyEmail` notification; scheduled `auth:audit-hashes`.
- **Tests**: `AuthFlowTest`, `UnifiedLoginTest`, `RegistrationFlowTest`, `OwnerRegistrationTest`, `OwnerStaffAccountFlowTest`, `AccountTypeEnforcementTest`, `PasswordPolicyTest`, `PasswordRecoveryTest`, `PasswordResetTest`, `EmailVerificationAndLockoutTest`, `E18UserFriendlyOtp2FaTest`, `E19_1EmailOtpUiTest`, `LoginCaptchaTest`, `WorkspaceContextTest`, `AccountDeletionGovernanceTest`, `E24/E25/E26/E27/E28*`.
- **Rules**: owner/staff invariant; owner = permission superuser; one-role-per-session (`SetFortifyGuard`); `password_hash` column.
- **Known issues**: `platform_staff` guard has 0 routes (UNUSED realm); `Membership::roleAllowedForAccountType()` fails open on null user/role.

---

## 2. Organization (Institute) & Branch

**Status: IMPLEMENTED**

- **Purpose**: tenant registration/onboarding, profile, branches, settings, module entitlements.
- **Routes**: `institute/*` (6), `settings/*` (181 incl. business profile/branches/users-modules), `admin/institutes/*` (platform), `super-admin/*` (20), `medical/branches/*` (9).
- **Controllers**: `InstituteCreationController`, `InstituteOnboardingController`, `InstituteSettingController`, `BusinessProfileController`, `BusinessController`, `InstituteLogoController`, `Admin/InstituteAdminController`, `SuperAdmin/*`, `Medical/BranchController`.
- **Services**: `MembershipService`, `ModuleAccessService`, `IndustryService`, `IndustrySubcategoryService`, `InstituteTaxonomyBackfill`, `RoleTemplateService`, `TerminologyService`, `RuleEngineService`, `SuperAdminOverrideService`, `Platform/*`.
- **Models/Tables**: `institutes`, `branches`, `institute_settings`, `industries`, `sub_industries`, `industry_subcategories`, `industry_settings`, `module_registry`, `module_terminology`, `module_rules`, `institute_module_entitlements`, `institute_module_overrides(+_archive)`, `institute_feature_overrides`, `super_admin_overrides`, `institute_subscriptions`.
- **Permissions**: `settings.manage`, `institute-settings.*` (observed in permission seeders).
- **Tests**: `InstituteCreationTest`, `InstituteOnboardingTest`, `InstituteSettingsTest`, `IndustryAdminTaxonomyTest`, `IndustryInstitutionDomainTest`, `TerminologyServiceTest`, `ModuleSettingsModuleAccessTest`, `SuperAdminInstituteManagementTest`, `SuperAdminOverrideTest`, `TenantProtectionTest`.
- **Rules**: `branches.code` globally unique; `institutes.status` gates access; `advanced_accounting_enabled` and `business_entity_type` affect finance.
- **Known issues**: org table is `institutes` but membership FK column is `institution_id` (naming inconsistency).

---

## 3. Membership / Roles / Permissions (RBAC)

**Status: IMPLEMENTED (matrix partially seeded; enforcement partial)**

- **Routes**: `staff/invite` (2), `staff/members/*` (2), `staff/roles/*` (6), `institute/users/modules` (2), `admin/users/*` (7).
- **Controllers**: `StaffInvitationController`, `Staff/RoleController`, `InstituteUserModuleAccessController`, `Admin/PlatformStaffController`, `Admin/UserAdminController`-style (`admin/users`).
- **Services**: `RoleTemplateService`, `UserModuleAccessService`, `ModuleAccessService`.
- **Models/Tables**: `roles`, `permissions`, `role_permissions`, `institution_user`, `user_module_access`, `module_access_logs`.
- **Permissions**: slugs `module.action`; modules observed: accounting, admin, crm, dashboard, education, finance, institute-settings, medical.*, purchase, restaurant, sales, settings, staff, tax, training*.
- **Events/Jobs**: `module_access_logs` written with actor type (migration `2026_09_18_000006`, `2026_09_20_000001`).
- **Tests**: `AuthorizationTest`, `ModuleAccessMiddlewareTest`, `CheckFeatureAccessMiddlewareTest`, `AccessDecisionLoggingTest`, `ModuleAccessLogActorTypeTest`, `TenantIsolationAuditTest`.
- **Rules**: `institute-owner` = superuser; system roles have `institute_id NULL`.
- **Known issues**: 706 authenticated routes have no permission/module middleware; `RolePermissionSeeder` only grants 4 global roles; `RoleTemplateService` skips unknown slugs.

---

## 4. Sales

**Status: IMPLEMENTED**

- **Purpose**: quotation → order → delivery → invoice → payment → return/credit-memo, plus reports.
- **Dependencies**: Parties, Inventory (`InventoryStockService`), Accounting (`Accounting\InvoiceService`, `JournalPostingService`), `SalesSequence`.
- **Routes**: **119** under `sales/*` (quotations/estimates, orders, deliveries, invoices, payments, returns, credit-memos, receipts, customers, items, leads, reports ×12, settings).
- **Controllers**: `Sales/{QuotationController, SalesOrderController, DeliveryController, SalesInvoiceController, ReceivePaymentController, SalesReturnController, SalesReceiptController, CustomerController, SalesLookupController, LeadController, SalesReportController, SalesSettingsController}`.
- **Services**: `Sales/*` (13) — `QuotationService`, `SalesOrderService`, `DeliveryService`, `SalesInvoiceService`, `SalesReturnService`, `SalesCustomerResolver`, `SalesCatalogService`, `SalesSettingsService`, `SalesInventoryIntegration`(**orphaned**), …
- **Models/Tables**: `sales_sequences`, `sales_quotations(+_lines)`, `sales_orders(+_lines)`, `sales_deliveries(+_lines)`, `sales_returns(+_items, _refunds)`, `invoices`, `invoice_items`, `payments`, `parties`, `customer_groups`, `cash_memos`.
- **Permissions**: `sales.view`, `sales.create`, `sales.update`, `sales.manage` (observed `CheckPermission:sales.view` ×76, `module_access:sales` ×123).
- **Tests**: `SalesFoundationTest`, `SalesQuotationTest`, `SalesOrderTest`, `SalesDeliveryTest`, `SalesInvoiceTest`, `SalesReturnTest`, `SalesCycleCompleteTest`, `SalesLifecycleHardeningTest`, `SalesCustomerProductTest`, `SalesReportTest`, `SalesCrmModuleTest`, `SalesQuotationFkTest`, `NumberSequenceTest`.
- **Rules**: order lifecycle states (draft/submitted/approved/processing/ready/completed/cancelled/rejected); doc numbers from `sales_sequences`; stock issued at delivery confirmation; invoice posting creates journal; returns reverse stock + journal.
- **Known issues**: `SalesInventoryIntegration` has no callers (dead helper); duplicate route prefixes (`sales/quotations` and `sales/estimates` alias the same controller).

---

## 5. Purchase

**Status: IMPLEMENTED**

- **Purpose**: PR → quotation → PO → goods receipt → invoice → supplier payment → return/vendor credit.
- **Dependencies**: Parties, Inventory (`receivePurchase`), Accounting (`PurchaseAccountingService`), `PurchaseSequence`.
- **Routes**: **116** under `purchase/*` (requests, quotations, orders, goods-receipts, invoices, bills, payments, returns, vendor-credits, suppliers, reports, settings).
- **Controllers**: `Purchase/{PurchaseRequestController, PurchaseQuotationController, PurchaseOrderController, GoodsReceiptController, GoodsReceiptWebController, PurchaseInvoiceController, BillPaymentController?, PurchaseReturnController, PurchaseSupplierController?, PurchaseReportController, PurchaseSettingsController}`.
- **Services**: `Purchase/*` (10) — `PurchaseRequestService?`, `PurchaseQuotationService`, `PurchaseOrderService`, `GoodsReceiptService`, `PurchaseInvoiceService`, `PurchasePaymentService`, `PurchaseReturnService`, `PurchaseReportService`, `PurchaseSettingsService`, `PurchaseAccountingService` (in `Services/Accounting`).
- **Models/Tables**: `purchase_sequences`, `purchase_requests(+_items)`, `purchase_quotations(+_lines)`, `purchase_orders(+_lines)`, `goods_receipts(+_items)`, `purchase_invoices(+_items)`, `purchase_returns(+_items)`, `purchase_supplier_payments`, `supplier_credit_balances`, `supplier_refunds`.
- **Permissions**: `module_access:purchase` ×128, `purchase.view/create/update/manage`.
- **Tests**: `ProcurementModuleTest`, `PurchaseFoundationTest`, `PurchaseOrderTest`, `PurchaseCycleCompleteTest`, `PurchaseP4Test`, `PurchaseReturnTest`, `PurchaseFinanceTest`, `PurchaseAccountingTest`, `PurchaseReportsTest`, `PurchaseLifecycleHardeningTest`, `GoodsReceiptTest`, `GoodsReceiptP5AdditionalTest`.
- **Rules**: GRN drives weighted-average stock receipt + `Dr Inventory / Cr AP` journal; invoice `post` action; returns reverse stock.

---

## 6. Inventory

**Status: IMPLEMENTED**

- **Purpose**: items, categories, warehouses, stock levels/movements, adjustments, transfers, counts, batches, serials, barcode.
- **Dependencies**: `InventoryCapabilityService` (feature gate), `InventoryAccountingService` → `JournalPostingService`.
- **Routes**: **25** under `inventory/*` (items CRUD, warehouses CRUD, adjustments, transfers, batches, stock-ledger, barcode-search).
- **Controllers**: `Inventory/{InventoryItemController, InventoryWarehouseController, InventoryAdjustmentController, InventoryTransferController}`, API: `Api/InventoryApiController`.
- **Services**: `Inventory/*` (6): `InventoryStockService`, `InventoryItemService`, `InventoryAccountingService`, `InventoryReportService`, `InventoryReconciliationService`, `InventoryCapabilityService`; plus `System/InventoryIntegrityAuditService`, `Accounting/InventoryAccountingReportService`.
- **Models/Tables**: `inventory_items`, `inventory_categories`, `inventory_warehouses`, `inventory_stock_levels`, `inventory_movements`, `inventory_adjustments(+_items)`, `inventory_transfers(+_items)`, `inventory_counts(+_items)`, `inventory_batches`, `inventory_serial_numbers`.
- **Permissions**: `inventory.*` (gated through `InventoryCapabilityService::assert(..., 'inventory.purchase_receipt', ...)`).
- **Events/Jobs**: n/a (synchronous in transactions).
- **Tests**: `InventoryStockEngineTest`, `InventoryManagementTest`, `InventoryMasterDataTest`, `InventoryAccountingTest`, `InventoryAccountingReportTest`, `InventoryIntegrityAuditTest`, `AdvancedInventoryTest`.
- **Rules**: **`inventory_movements` = source of truth**; `inventory_stock_levels` = cached balance with row locks; **weighted-average costing only**; single write path `InventoryStockService`; negative stock epsilon `0.00005`.
- **Verified**: `InventoryItem` and `InventoryStockLevel` both use `TenantScoped` + `BranchScopedOrShared` (app/Models/InventoryItem.php:20-22, InventoryStockLevel.php:17-18) — Eloquent queries auto-scope; movement writes still pass explicit institute/branch columns.

---

## 7. Accounting / Finance (double-entry)

**Status: IMPLEMENTED (two UIs: `finance/*` CRUD + `accounting/*` reports/ops)**

- **Purpose**: COA, journals, invoices, payments, parties, periods, budgets, opening balances, bank feed/reconciliation, expenses, recurring, progressive contracts, aging, executive dashboards, approvals, audit.
- **Dependencies**: `JournalPostingService` (core), `AccountingPeriodService`, `AccountingSetupService`, `AccountingAuditService`, `Party`, `Tax`, `Currency/ExchangeRate`.
- **Routes**: **132** `finance/*` + **66** `accounting/*` = 198. Also `settings/corporate-tax`, `settings/tds*`, `settings/tax-reports/*`, `settings/tax-reconciliation/*`.
- **Controllers**: root `Finance*Controller` (13: Audit, Budget, ChartOfAccount, Dashboard, ExchangeRate, FxRevaluation, Invoice, Journal, OnlinePayment, OpeningBalance, Party, Payment, PaymentMethod, Period, Report), `Accounting/*` (reports, aging, bank-feed, bank-reconciliation, approvals, executive, fiscal years, security), `ExpenseController`, `RecurringTemplateController`, `ProgressiveContractController`.
- **Services**: `Accounting/*` (60) — incl. `JournalPostingService`, `InvoiceService`, `PaymentService`, `ChartOfAccountService`, `TenantCoaSeederService`, `AccountingPeriodService`, `BankFeedMatchingService`, `RecurringTransactionService`, `ProfitDistributionService`, `ProgressiveInvoiceService`, `PurchaseAccountingService`, `ExecutiveDashboardService`, `RatioAnalysisService`, `AgingCalculatorService`, `BillableExpenseBillingService`, `TaxAccountingService`, `PartnerService`, `TdsReceivableService`.
- **Models/Tables**: `chart_of_accounts`, `account_groups`, `account_heads`, `fiscal_years`, `accounting_periods`, `accounting_settings`, `journals`, `journal_entries`, `opening_balances`, `statement_snapshots`, `budgets(+_versions,_lines)`, `bank_statements(+_lines)`, `bank_reconciliations`, `bank_rules`, `expenses`, `payments`, `payment_methods`, `parties`, `progressive_contracts`, `recurring_templates(+_generations)`, `accounting_audit_trails`, `exchange_rates`.
- **Permissions**: `finance.view/manage`, `settings.manage`, `advanced.accounting` gate; policies on 9 models (`InvoicePolicy`, `PaymentPolicy`, `ChartOfAccountPolicy`, `PartnerPolicy`, `ShareholderPolicy`, `ShareCapitalTransactionPolicy`, `DividendPolicy`, `TdsDeductionPolicy`, `CorporateTaxComputationPolicy`).
- **Events/Jobs**: **`JournalPosted` dispatched** (`JournalPostingService.php:103`) → `LogJournalPosted` (queued). **`InvoicePaid` defined but never dispatched (UNUSED).**
- **Tests**: `AccountingEngineTest`, `AccountingIntegrityAuditTest`, `AccountingPeriodClosingTest`, `AccountingReportsTest`, `AccountingMultiCurrencyTest`, `AccountingOwnerStaffTenantSafetyTest`, `FinanceCoreTest`, `ChartOfAccount*`, `BankReconciliationTest`, `BudgetingTest`, `CashFlowStatementTest`, `LedgerReconciliationTest`, `ExecutiveDashboardTest`, `ArApManagementUiTest`, `PayableAgingTest`, `ReceivableAgingTest`, `Phase08FinancialIntegrityTest`, `AuditActivityLogTest`.
- **Rules**: balance invariant; period open/closed; posted journals immutable (reverse/void only); hybrid global COA (`institute_id NULL AND is_system=1`); `advanced_accounting_enabled` gates advanced routes; `AuditActivityLog` middleware NOT registered (audit gap).
- **Known issues**: overlapping `finance/*` vs `accounting/*` report endpoints; 706 ungated routes include most `accounting/*`; `FinanceWriteGate`/`DenyTeacherFromFinance` fail open.

---

## 8. Tax / TDS / VAT

**Status: IMPLEMENTED**

- **Routes**: `settings/tax-reports/*` (3), `settings/tds*` (12), `settings/corporate-tax` (3), `settings/tax-reconciliation` (4), `accounting/reports/tax/*` (5).
- **Controllers**: `Settings/*Tax*`, `TdsCertificateReceivedController`, `TdsReceivableController`, `TaxReconciliationController`, `AdvancedAccountingSettingController`, `AdvanceTaxPaymentController?`.
- **Services**: `Tax/*` (4): `TaxAccountingService`, `TaxComplianceService`, + 2; models: `TaxRate`, `TaxGroup`, `TaxRule`, `TaxJurisdiction`, `TaxRateHistory`, `TaxAuditLog`, `TaxReturnPeriod/Line/Reconciliation`, `TdsDeduction`, `TdsReceivable`, `TdsCertificate(+Received)`, `AdvanceTaxPayment`, `CorporateTaxComputation`, `CountryTaxConfig`, `CountryTaxModule`, `TaxDeductionRule`.
- **Seeders**: `TaxPermissionSeeder`, `TaxGroupSeeder`, `TaxRateSeeder`, `CountryTaxConfigSeeder`, `CountryTaxModuleSeeder`, `TaxDeductionRuleSeeder`.
- **Tests**: `TaxEngineTest`, `TaxReportTest`, `AccountingAgingModulesTest`.

---

## 9. Fixed Assets

**Status: IMPLEMENTED**

- **Routes**: **16** `fixed-assets/*` (assets CRUD, capitalize, categories, locations, depreciation, disposal, QR).
- **Controllers**: `FixedAsset/*`.
- **Services**: `FixedAsset/*` (7) incl. `FixedAssetAccountingService`, `FixedAssetQrService`, `Depreciation/UnitsOfProductionDepreciation`; Job `DepreciationRunJob`.
- **Tables**: `fixed_assets`, `asset_categories`, `asset_locations`, `asset_cost_components`, `asset_depreciation_runs/entries`, `asset_disposals`, `asset_impairments`, `asset_revaluations`, `asset_transfers`, `asset_method_changes`, `asset_qr_codes`, `asset_audit_logs`.
- **Tests**: `FixedAssetAccountingTest`, `FixedAssetDepreciationTest`, `FixedAssetLifecycleTest`, `FixedAssetModuleTest`, `FixedAssetQrTest`.

---

## 10. HR & Payroll

**Status: IMPLEMENTED**

- **Purpose**: employees, org structure, attendance/shifts/holidays, leave, recruitment, performance/KPI, training, payroll, self-service, documents, reports.
- **Dependencies**: Accounting (`HrPayrollFinanceService` → `JournalPostingService`), `hr_*_code_sequences`.
- **Routes**: **145** `hr/*` (employees, departments, designations, attendance, leave, payroll, performance, recruitment, training, self-service, documents, reports, salary-structures, manager).
- **Controllers**: `Hr/*` (15+): `HrEmployeeController`, `HrAttendanceController`, `HrLeaveController`, `HrPayrollController`, `HrPerformanceController`, `HrRecruitmentController`, `HrTrainingController`, `HrSelfServiceController`, `HrDepartmentController`, `HrDesignationController`, `HrDocumentController`, `HrReportController`, `HrSalaryStructureController`, `HrManagerController`; API `Api/HrApiController`.
- **Services**: `Hr*Service` (16 in root) + `HrPayrollFinanceService`, `HrReportExportService`.
- **Models/Tables**: 40 `hr_*` tables (employees, departments, designations, attendances+corrections, leave_types/balances/applications, payroll_periods/items/adjustments, salary_structures(+components), applications/history, interviews, offers, vacancies, requisitions, kpis, performance_*, trainings(+enrollments), work_shifts, holidays, employment_histories/periods, employee_skills, employee_code_sequences, payroll_no_sequences).
- **Permissions**: `module_access:hr` ×148; HR-related permission slugs in `StaffPermissionSeeder`/`AccountingPermissionSeeder`.
- **Tests**: `HrCoreTest`, `HrAttendanceLeaveTest`, `HrPayrollTest`, `HrPayrollCompleteTest`, `HrLifecycleTest`, `HrPerformanceTrainingTest`, `HrRecruitmentTest`, `HrSelfServiceTest`, `HrReportsAiTest`, `HrDocumentManagementTest`, `HrFinanceIntegrationTest`.

---

## 11. CRM

**Status: IMPLEMENTED**

- **Routes**: **34** `crm/*` (contacts, leads, organizations, activities, notes, tasks, dashboard) + `sales/leads/*` (5) + `sales/customers-crm/search`.
- **Controllers**: `CrmContactController`, `CrmLeadController`, `CrmOrganizationController`, `CrmActivityController`, `CrmNoteController`, `CrmTaskController`, `CrmDashboardController`, `Sales/LeadController`.
- **Services**: `CrmContactService`, `CrmLeadService`, `CrmOrganizationService`, `CrmActivityService`, `CrmNoteService`, `CrmTaskService`, `CrmAuditService`, `EducationCrmIntegrationService`, `EducationAdmissionPipelineService`.
- **Models/Tables**: `crm_contacts(+types)`, `crm_leads(+sources, _statuses)`, `crm_organizations`, `crm_activities`, `crm_notes`, `crm_tasks`.
- **Permissions**: `crm.view/manage`; `module_access:crm` ×38.
- **Tests**: `CrmCrudTest`, `CrmSecurityTest`, `CrmTimelineTest`, `SalesCrmModuleTest`, `EducationCrmIntegrationTest`, `AdmissionPipelineTest`.

---

## 12. Education / Academic

**Status: IMPLEMENTED**

- **Purpose**: students, guardians, courses/classes/subjects, batches, enrollment, attendance, exams/marks/results/grading/promotion, certificates, admissions pipeline, fee structures/fee collection, curriculum, notices, alumni.
- **Routes**: **~152** across `students/*` (15), `courses/*` (33), `classes/*` (4), `batches/*` (10), `exams/*` (7), `certificates/*` (2), `certificate-types/*` (6), `admissions/*` (17), `teachers/*` (10), `academic/*` (31), `academic-attendance/*` (9), `curricula/*` (14), `finance/education/*` (~30), `alumni/*` (11), `documents/*` (13), `settings/notifications/*`.
- **Controllers**: `StudentController`, `BatchController`, `CourseController`, `ClassController`, `ExamController`, `CertificateController`, `AdmissionController`, `AdmissionPipelineController`, `TeacherController`, `FeeStructureController`, `EducationFinanceController`, `Academic*Controller` (15 root academic controllers), `Alumni/*`, `Admin/*AdminController`.
- **Services**: `Academic*Service` (20+ in root), `Education/*` (5: `FeeStructureService`, `MonthlyFeeGenerationService`, `StudentFinanceService`, `BatchLifecycleService`, …), `StudentAcademic*Service` (8), `LearningStructureService/Resolver`, `CourseMasterService`, `CourseCurriculumService`.
- **Models/Tables**: `students`, `guardians`, `guardian_student`, `student_enrollments`, `batches`, `courses(+categories/sub_categories/subjects)`, `subjects`, `exams(+subjects/components)`, `exam_results`, `results`, `attendance`, `certificates(+types)`, `fee_heads`, `fee_structures(+items)`, `monthly_fee_periods`, `installments`, `academic_*` (20), `structure_templates/nodes/labels/levels`, `curriculum_modules/lessons`, `course_curricula`, `alumni`.
- **Permissions**: `education.manage` (×94 CheckPermission), `module_access:education` (×90) and `education.classes`.
- **Tests**: 60+ education/academic tests (`Academic*Test` ×~30, `Education*Test` ×~15, `Student*Test`, `Certificate*Test`, `BatchModuleTest`, `ExamModuleTest`, `TeacherManagementTest`, `Admission*Test`).

---

## 13. Medical / HMS

**Status: IMPLEMENTED (largest module)**

- **Purpose**: patients/OPD/appointments/queue, encounters, prescriptions, orders, wards/beds/admissions, pharmacy & medicines (+ DGDA/RxNorm), laboratory + analyzer integration, radiology, blood bank, dental, diet, physiotherapy, vaccination, ambulance, emergency, clinical notes, TPA claims, discharge, timeline, billing, reports.
- **Routes**: **490** in `routes/medical.php` (wrapped in `MedicalDomain` middleware + `MedicalModuleAccess:*` and `CheckFeatureAccess:*` gates per clinical area).
- **Controllers**: `Medical/*` (65) — full list in §"Evidence"; API `Api/MedicalReactController`, `Api/LabGatewayController`.
- **Services**: `Medical/*` (36) + `LabIntegration/*` (11) + `Medical/DgdaService`; Job `ProcessAnalyzerMessage`; event `LabAnalyzerResultStored`.
- **Models**: `app/Models/Medical/*` (76) + `app/Models/LabIntegration/*`.
- **Tables**: patients, appointments, medical_encounters(+diagnoses), prescriptions(+items), lab_orders/results/tests/samples/analyzers/parameter_maps/messages/worklists/device_credentials/result_parameters, pharmacy_stock/dispenses, medicines + `medicine_*`, `dgda_*`, `rxnorm_*`, wards/beds/admissions, blood_*, dental_*, radiology_*, vaccination_*, physiotherapy_*, diet_*, ambulance_*, emergency_visits, clinical_notes, tpa_claims, vital_signs, discharge_summaries, medical_invoices, number_sequences, queue/prescription/clinical audit logs.
- **Permissions**: `medical.*` slugs (`medical.pharmacy`, `medical.laboratory`, `medical.records`, `medical.bloodbank`, `medical.dental`, `medical.diet`, `medical.ambulance`, `medical.physiotherapy`, `medical_queue`, `medical_vitals`) gated by BOTH `MedicalModuleAccess` and `CheckFeatureAccess` (dual gate).
- **Tests**: `MedicalPhase1..4Test`, `MedicalBatch1..4FeatureGateTest`, `MedicalRecordsTest`, `MedicalRolesTest`, `HmsAuthorizationTest`, `Phase10..18*`, `DgdaSyncTest`, `Medicine*Test` ×8, `RadiologyOrderTest`, `BloodBankTest`, `DentalTest`, `DietNutritionTest`, `PhysiotherapyTest`, `VaccinationTest`, `AmbulanceTest`, `EmergencyVisitTest`, `LabIntegration/*` (Adapters, Admin, Api, Chaos, E2E, Load, Parsers, Simulator).
- **CRITICAL RULES**: **no global tenant scope on these models** — every query must add `where('institute_id', MedicalScope::instituteId())`; device auth is separate (`lab.device`); broadcasting via Reverb for live analyzer results.
- **Known issues**: isolation depends on per-query discipline (52 files use `MedicalScope`; the model layer does not enforce it).

---

## 14. Training Center

**Status: IMPLEMENTED**

- **Routes**: **86** `training/*` (students, batches, classes, courses + categories/sub-categories/subjects/materials, enrollments, attendance, exams, marks, results, certificates, fees, schedules, reports, settings).
- **Controllers**: `Training/*` (`TrainingStudentController`, `TrainingBatchController`, `TrainingClassController`, `TrainingCourseController`, `TrainingCourseCategoryController`, `TrainingCourseSubCategoryController`, `TrainingSubjectController`, `TrainingCourseMaterialController`, `EnrollmentController`, `AttendanceController`, `TrainingExamController`, `MarksController`, `ResultsController`, `TrainingResultController`, `TrainingCertificateController`, `TrainingScheduleController`, `FeesController`, `ReportsController`, `SettingController`).
- **Models/Tables**: `training_*` (20 tables incl. `training_batch_results`, `training_exam_results`, `training_schedules`, `training_certificates`).
- **Permissions**: `module_access:training_center` ×57 + `training_center.*`, `training_*` slugs; `TrainingCenterPermissionSeeder`.
- **Tests**: `tests/Feature/TrainingCenter/*` (11) + `TrainingCenterSubModule*`.

---

## 15. Documents

**Status: IMPLEMENTED**

- **Routes**: **13** `documents/*` (CRUD, categories, archive, download, force-delete).
- **Controllers**: `DocumentController`, `DocumentScanController` (OCR via Tesseract).
- **Services**: `DocumentService`, `DocumentCategoryService?`, `DocumentChecklistService`, `DocumentVerificationService`, `DocumentAuditService`.
- **Models/Tables**: `documents`, `document_categories`, `document_versions`; trait `Concerns\DeletesFiles` (file columns cleanup).
- **Tests**: `DocumentManagementTest`, `ArchiveSystemTest`.

---

## 16. Notifications

**Status: IMPLEMENTED**

- **Routes**: **28** (`notifications/*` 3, `settings/notifications/*` 15, `admin/notifications*` 3, `api/notifications*` 2, `guardian/notifications` 1, platform settings 1, verification 1, `institute/notifications`).
- **Controllers**: `NotificationController`, `NotificationLogController`, `NotificationPreferenceController`, `NotificationTemplateController`, `InstituteNotificationController`, `Admin/NotificationController`.
- **Services**: `Notification/*` (5) + `Support/NotificationCenter`; Job `SendNotificationJob`; command `notifications:retry` (every 5 min); Mail `NotificationMail`, `GuardianPasswordReset`, `EmailOtpMail`.
- **Models/Tables**: `notifications`, `notification_templates`, `notification_preferences`, `notification_reads`, `notification_logs`; queue tables `jobs`, `failed_jobs`.
- **Tests**: `NotificationEngineTest`, `EmailVerificationNotificationQueueTest`.

---

## 17. Reports & Exports

**Status: IMPLEMENTED**

- **Routes**: `reports/hub` + `reports/hub/{report}` (2) plus domain report endpoints: `sales/reports/*` (12), `accounting/reports/*` (17), `finance/reports/*` (6), `hr` reports, `training/reports`, `settings/tax-reports/*`.
- **Controllers**: `ReportsHubController`, `SalesReportController`, `AccountingReportController`, `RatioAnalysisController`, `HrReportController`, `Training/ReportsController`.
- **Services**: `Reports/ReportRegistry` (single file), `*ExportService` (Academic, Hr, Promotion, Sales/Purchase reports), PDF via dompdf.
- **Tests**: `ReportsHubTest`, `ReportExportTest`, `SalesReportTest`, `AccountingReportsTest`, `AdvancedFinancialReportTest`.

---

## 18. Settings / Configuration

**Status: IMPLEMENTED**

- **Routes**: **183** `settings/*` — notifications (15), tax/TDS/corporate-tax, business entity, advanced accounting, module management, themes, language/locale, profile, security, document settings, branches?, etc.
- **Controllers**: `Settings/*` (+ `Http/Requests/Settings/*` FormRequests), `InstituteSettingController`, `ModuleSettingsController`, `ModuleManagementController`, `LearningStructureSettingsController`.
- **Tests**: `tests/Feature/Settings/*` (17), `SettingsHubTest`, `InstituteSettingsTest`.

---

## 19. Workflow / Approvals

**Status: IMPLEMENTED**

- **Routes**: **7** `workflows/*` (index/store/create/show/approve-step/reject-step/transition); plus `accounting/approvals/*` (7).
- **Controllers**: `WorkflowController`, `Accounting/ApprovalController?`.
- **Services**: `WorkflowService` (+ audit); models `Workflow`, `WorkflowStep`, `WorkflowHistory`, `ApprovalWorkflow`, `ApprovalStep`, `ApprovalRequest`, `ApprovalAction`.
- **Seeders**: `ApprovalWorkflowSeeder`.
- **Tests**: `ApprovalWorkflowTest`, `ApprovalWorkflowUiTest`.

---

## 20. Platform / SaaS administration

**Status: IMPLEMENTED**

- **Routes**: `admin/*` **294** (institutes, users, courses, certificates, notifications, settings, packages/scopes, themes, AI keys, platform settings, module matrix, geo), `super-admin/*` **20** (database control center, backups, recovery, monitoring, emergency override), `saas/*` (5), `upgrade` (1).
- **Controllers**: `Admin/*` (15+), `SuperAdmin/*` (5), `SaasCheckoutController`, `UpgradeController`.
- **Services**: `SaasSubscriptionService`, `Platform/*`, `PaymentGateway/{PaymentGatewayManager, GatewayCallbackService}`, `EntitlementsExpire` command, `PackagesGenerateScopes` command, `ProcessExpiredGrants`, `VerifyPendingSaasPayments`.
- **Models/Tables**: `subscription_packages`, `package_modules`, `package_scopes`, `package_scoped_modules`, `package_scoped_features`, `package_features`, `package_industries(+modules)`, `package_country_prices`, `institute_subscriptions`, `tenant_access_grants/denials`, `payment_gateways`, `institute_payment_gateways`, `online_payment_attempts`, `pending_registrations`, `platform_admins`, `platform_staffs`, `platform_audit_logs`, `platform_service_configs`, `themes`, `settings`.
- **Payment**: bKash tokenized (`BKASH_*` env keys, sandbox flag, callback `/saas/callback`, webhook secret) — `BkashConfig` referenced by 17 files.
- **Tests**: `SaasCheckoutTest`, `SaasEnterpriseTest`, `SaaSModuleAccessTest`, `SaasSubscriptionCountryTest`, `EntitlementsExpireTest`, `EntitlementAuditTest`, `GlobalPricingTest`, `PackageCountryPriceAdminTest`, `ScopedPricingTest`, `Wave2RetailPackagesTest`, `SaarcPricingTest`, `EuPricingTest`, `OnlinePaymentTest`.

---

## 21. AI

**Status: IMPLEMENTED**

- **Routes**: `ai/assistant` + `ai/assistant/send` (2); platform `admin/ai-api-keys/*` (6), `admin/settings/ai*` (3), `admin/platform-settings/ai` (1).
- **Controllers**: `Ai/AiAssistantController`.
- **Services**: `Ai/*` (8): `AiService`, `AiToolRegistry`, `AiContext`, `AiLogger`, `AiUsageTracker`, `OpenAiProvider`, `CustomAiProvider`, `AiAccessException`.
- **Models/Tables**: `ai_api_keys`, `ai_logs`, `ai_usage`, `settings` (encrypted key), `institute_settings.ai_config`.
- **Middleware**: `ai.enabled` (`EnsureAiEnabled`); env `AI_ENABLED`, `AI_PROVIDER=gemini`, `AI_MODEL`, `AI_GEMINI_API_KEY` (`[REDACTED]`).
- **Permissions**: `AiToolPermissionSeeder`.
- **Tests**: `AiAssistantAjaxTest`, `AiAssistantCompleteTest`, `AiCoreFinanceTest`, `AiCoreToolingTest`, `AiIntegrationTest`, `AiSecurityTest`, `AiSettingsTest`, `HrReportsAiTest`.

---

## 22. Guardian portal

**Status: IMPLEMENTED**

- **Routes**: **34** `guardian/*` (login, forgot/reset password, dashboard, students, attendance, results, notifications, logout).
- **Controllers**: `Guardian/*` (dashboard, student, attendance, result), `Auth/GuardianLoginController`.
- **Model**: `guardians`, `guardian_student`, `student_guardians`.
- **Tenant rule**: `TenantContext = guardian.institute_id`, **`BranchContext::clear()`** (cross-branch visibility inside own institute) — `SetTenantContext.php:31-35`.
- **Tests**: `GuardianPortalTest`.

---

## 23. Offline sync

**Status: IMPLEMENTED (legacy/offline capability)**

- **Routes**: 4 `sync/*` (index, upload, approve, reject).
- **Controllers/Services**: `OfflineSyncController`, `OfflineSyncService` (materializes `CashMemo` on approval; writes `CashMemo` + uses `JournalPostingService`).
- **Tables**: `offline_sync_queue`, `cash_memos`.
- **Tests**: `OfflineSyncTest`.

---

## 24. Geo / Localization

**Status: IMPLEMENTED**

- **Routes**: `geo/levels/{country}`, `geo/units` (public, unauthenticated), `admin` geo import UI.
- **Controllers**: `GeoController`, `Admin/GeoAdminController?`, `Admin/GeoImportController?`.
- **Services**: `Support/{GeoHierarchy, CountryCodes, CountryConfigResolver, CountryConfig}`, `GeoImportService`, `GeoDuplicatesService`; commands `ImportGeoNames`, `ImportGeoPackage`.
- **Tables**: `countries`, `administrative_levels`, `administrative_units`, `geo_imports`, `currencies`, `country_currency_map`, `tenant_currency_settings`, `exchange_rates`.
- **Localisation**: `lang/mawa/{en,bn}.php`, `SetLocale` middleware, `mawa_e()`/`mawa_translate()` helpers, `@term` blade directive, `config/locale.php`, `config/geo*.php`.
- **Tests**: `GeoAdminTest`, `GeoImport*Test` (5), `CountryCodesIso2Test`, `LocalizationTest`, `LocaleHelperIso2Test`, `MultiCountryVerificationTest`.

---

## 25. POS

**Status: PLANNED — NOT IMPLEMENTED**

- **Evidence**: `config/pos.php` defines 5 phase "engines" (terminal, sales/cart, payments/sessions, customer/promotions, returns/reports); migrations `2026_09_26_160000…210000_add_pos_phase1..5.php` only `DB::table('module_registry')->updateOrInsert(...)`; migration `2026_09_27_310000_revert_pos_to_module_group.php`.
- **NOT FOUND**: POS routes (0), POS controllers (0), POS models (0), POS views (0). `CashMemo` exists but is used by offline sync/receipts, not a POS terminal.
- **Tests**: `PosPhase1..5Test` — registry/entitlement assertions only (verify before assuming business logic).

## 26. Manufacturing

**Status: PLANNED — NOT IMPLEMENTED**

- **Evidence**: `config/manufacturing.php` (engines: core_manufacturing with `manufacturing.bom|routing|work_centers|production_orders`; quality engine with `quality_control`, `quality_lab`, `sample_management`, `regulatory_compliance`; more); migration `2026_09_25_230000_add_universal_manufacturing_modules.php` seeds `module_registry`.
- **NOT FOUND**: 0 manufacturing routes, 0 `Manufactur|Bom|WorkCenter|Production` app classes (only unrelated `ProductionDashboardController` = accounting dashboard, `ProductionDatabaseAudit*` = DB ops).
- **Tests**: `ManufacturingModuleTest` (registry-level).

## 27. Real Estate

**Status: PLANNED — NOT IMPLEMENTED**

- **Evidence**: migrations `2026_09_26_100000…160000_add_real_estate_phase1..6.php`, `config/real-estate.php`, `config/module_groups.php` forward-looking key `property`.
- **NOT FOUND**: routes, controllers, models, views.
- **Tests**: `RealEstatePhase1..6Test` (registry-level).

## 28. Restaurant

**Status: PLANNED — NOT IMPLEMENTED**

- **Evidence**: migrations `2026_09_26_210000…270000_add_restaurant_phase1..6.php` + `restaurant_main_industry.php`; `config/restaurant.php`; `RestaurantPermissionSeeder`.
- **NOT FOUND**: routes, controllers, models, views (except permission seeds).
- **Tests**: `RestaurantPhase1..6Test`, `RestaurantMainIndustryTest`.

---

## 29. UNUSED / DEAD artifacts

| Artifact | Evidence | Status |
|---|---|---|
| `App\Http\Middleware\AuditActivityLog` | not in `bootstrap/app.php`, 0 route references | UNUSED (audit gap) |
| `App\Events\InvoicePaid` + `Listeners\LogInvoicePaid` | 0 dispatch sites | UNUSED |
| `App\Services\Sales\SalesInventoryIntegration` | 0 callers | UNUSED (planned helper) |
| guard `platform_staff` | 0 routes with that middleware; `SetFortifyGuard` resolves only web/platform_admin/institute_user | UNUSED as auth realm |
| `app/Models/Attendance.php.bak`, `config/queue.php.bak` | `.bak` files in tree | DEPRECATED artifacts |
| `LegacyUser` model | exists alongside `User` | LEGACY (needs verification of use) |
| `docs/audit/*`, `docs/structure.md` | dated 2026-08-17 / stale path | STALE DOCUMENTATION |
