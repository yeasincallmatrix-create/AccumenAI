# Phase 12 — Security & Tenant Forensic Audit

**Date:** 2026-09-20
**Scope:** Full read-only adversarial audit of AccumenAI multi-tenant security boundaries
**Tests:** `tests/Feature/SecurityForensicTest.php` (22 tests, 34 assertions, all green)

---

## Executive Summary

The AccumenAI platform has a **solid multi-tenant isolation foundation** for non-medical modules (education, HR, accounting, CRM, sales, purchase). The `TenantScoped` global scope, `SetTenantContext` middleware, `Workspace::verify()` forgery detection, and `BlockPlatformAdminEscalation` middleware provide defense-in-depth.

The **medical module is the primary risk surface**: 78 medical models lack the `TenantScoped` global scope, legacy flat routes lack `medical.module` middleware, and 6 controllers have a branch-scoping gap. The finance/accounting web routes also lack route-level `permission:` middleware.

No CRITICAL findings require an immediate STOP -- all gaps are mitigable through the remediation plan below.

---

## Findings by Severity

### HIGH-1: Medical Models Lack TenantScoped (78 of 80 models)

- **Location:** `app/Models/Medical/*.php`
- **Impact:** PHI/clinical data isolation depends entirely on controller-level scoping. A single missed `scopeForInstitute()` call would expose cross-tenant patient data.
- **Mitigated by:** All Medical controllers extend `MedicalController` which enforces `instituteId()`. Models have `scopeForInstitute()` query scope.
- **Evidence:** `Patient`, `Encounter`, `Prescription`, `Admission`, `Doctor`, `Invoice`, `ClinicalNote`, `VitalSign`, `DischargeSummary`, `MedicalDocument`, `RadiologyOrder`, `BloodDonor`, `BloodUnit`, `EmergencyVisit` -- none use `TenantScoped`. Only `LabTest` and `LabResult` have it.
- **Test:** `medical_models_lack_tenantscoped_trait`, `labtest_model_has_tenantscoped_trait`, `labresult_model_has_tenantscoped_trait`

### HIGH-2: Legacy Medical Flat Routes Lack medical.module Middleware

- **Location:** `routes/medical.php:75-219`
- **Impact:** Legacy flat routes (patients, appointments, prescriptions, admissions, TPA claims, lab, billing) use only `auth + tenant + medical` middleware. They lack the `medical.module:medical.*` sub-module gate that new grouped routes (lines 228+) have.
- **Mitigated by:** The `medical` module-level middleware still checks the medical module is enabled for the institute. The legacy routes share the same controllers as the new grouped routes.
- **Test:** `legacy_medical_flat_routes_lack_medical_module_middleware`

### HIGH-3: Finance/Accounting Web Routes Lack permission: Middleware

- **Location:** `routes/web.php:548-585`
- **Impact:** Finance dashboard, chart of accounts, invoices, payments, parties, periods, and accounting reports (P&L, balance sheet, cash flow) are accessible to any authenticated institute user without role-based permission checks.
- **Mitigated by:** `DenyTeacherFromFinance` middleware blocks teacher role. `finance.write` middleware gate exists on the route group. The routes are read-only (GET) index pages. API routes have proper `permission:` middleware.
- **Test:** `finance_web_routes_lack_permission_middleware`

### MEDIUM-1: 6 Medical Controllers Use branchContextId() Instead of resolveBranchId()

- **Location:** `BloodDonorController`, `BloodUnitController`, `BloodRequestController`, `EmergencyController`, `PhysiotherapyPlanController`, `RadiologyController`
- **Impact:** When `$request->branch_id` is provided, `branchContextId()` validates the branch belongs to the institute but does NOT call `ensureBranchAccess()`. A user assigned to Branch A could create records in Branch B of the same institute.
- **Mitigated by:** `MedicalController::instituteId()` still enforces institute-level isolation. `TenantScoped` (where present) and `BranchContext` provide defense-in-depth.
- **Test:** `branch_context_id_validates_institute_not_branch_access`

### MEDIUM-2: Session Auto-Resolve Fallback

- **Location:** `app/Http/Middleware/SetTenantContext.php:60-78`
- **Impact:** After session regeneration (cache:clear, new device), the user is silently placed into their first active membership (alphabetical by institution_id). A user who was working in Institute B might see Institute A's data on next page load.
- **Mitigated by:** `Workspace::verify()` validates explicit workspace switching. Auto-resolve only triggers for single-membership users or as a fallback when workspace is null.

### LOW-1: withoutGlobalScope() Pattern in Services

- **Location:** Multiple service files (EducationCrmIntegrationService, HrEmployeeService, CourseCurriculumService, CrmLeadService, StudentFinanceService, etc.)
- **Impact:** Over 100 `withoutGlobalScope()` calls across service layer. Each bypasses the TenantScoped safety net. Most re-add manual WHERE clauses, but the pattern is fragile -- a missed WHERE in a future change would silently expose cross-tenant data.
- **Mitigated by:** Most usages include explicit `->where('institute_id', $instituteId)` re-scoping. The pattern is used for cross-tenant queries (e.g., platform admin) or multi-tenant joins.
- **Recommendation:** Create a named query scope (e.g., `scopeForInstitute`) instead of bare `withoutGlobalScope` + manual WHERE.

### LOW-2: PlatformAdmin Singleton Enforcement

- **Location:** `app/Models/PlatformAdmin.php:67-78`
- **Impact:** `PlatformAdmin::creating()` hook blocks creation of a second super admin. This is correct behavior but means tests cannot create fresh PlatformAdmin instances.
- **Mitigated by:** Tests use `PlatformAdmin::first()` to reference the existing singleton. The singleton pattern is a security feature, not a gap.

### INFO-1: Solid Defense-in-Depth (Positive Findings)

- **TenantScoped creating hook:** Forces `institute_id = TenantContext::id()` on every model create, preventing mass-assignment override. (`app/Models/Concerns/TenantScoped.php:47-57`)
- **TenantScoped updating hook:** Reverts any changes to `institute_id`, preventing ownership transfer. (`app/Models/Concerns/TenantScoped.php:49-55`)
- **BranchScoped creating hook:** Forces `branch_id = BranchContext::id()`. (`app/Models/Concerns/BranchScoped.php:33-38`)
- **Workspace forgery detection:** `SetTenantContext` middleware verifies workspace membership on every request and aborts 403 for forged sessions. (`app/Http/Middleware/SetTenantContext.php:42-55`)
- **BlockPlatformAdminEscalation:** Blocks `is_owner`, `singleton_guard`, `super_admin`, `platform_admin` field injection. Logs attempts to `platform_audit_logs`. (`app/Http/Middleware/BlockPlatformAdminEscalation.php`)
- **DB::transaction on grants/denials:** `TenantAccessController` wraps all mutations in transactions. (`app/Http/Controllers/Admin/TenantAccessController.php`)
- **lockForUpdate on registration:** Prevents concurrent registration race conditions. (`app/Http/Controllers/Auth/RegistrationFlowController.php:366`)
- **API routes properly protected:** All API routes use both `permission:` AND `module_access:` middleware. No gaps found.
- **Admin routes properly protected:** All admin routes use `auth:platform_admin` guard.
- **ResolvesInstitute trait:** Resolves institute ONLY from auth/session, never from request input. (`app/Http/Controllers/Concerns/ResolvesInstitute.php`)

---

## Remediation Priority

| Priority | Finding | Effort | Recommended Phase |
|---|---|---|---|
| P1 | HIGH-1: Add TenantScoped to medical models | High (78 models) | Phase 13 |
| P2 | HIGH-2: Add medical.module middleware to legacy routes | Low | Phase 13 |
| P3 | HIGH-3: Add permission: middleware to finance web routes | Low | Phase 13 |
| P4 | MEDIUM-1: Replace branchContextId() with resolveBranchId() | Medium (6 controllers) | Phase 13 |
| P5 | MEDIUM-2: Improve session auto-resolve UX | Low | Phase 14 |
| P6 | LOW-1: Replace withoutGlobalScope pattern | High (100+ calls) | Phase 14+ |

---

## Test Coverage Summary

| Domain | Tests | Status |
|---|---|---|
| Tenant isolation | 3 | PASS |
| Medical model scope | 3 | PASS |
| Route authorization | 5 | PASS |
| Platform admin boundary | 5 | PASS |
| Concurrency safety | 3 | PASS |
| Audit trail | 1 | PASS |
| Context lifecycle | 2 | PASS |
| **Total** | **22** | **ALL PASS** |
