# AccumenAI — Consolidated Roadmap V1

**Project:** `C:\xampp\htdocs\AccumenAI`
**Document type:** Implementation Roadmap
**Status:** Phases 1–5 COMPLETE; Phase 6 (Feature Registry) LANDED; Phase 7+ in progress
**Last updated:** 2026-09-18 (v3 — feature registry + feature gating + hardening sweep)
**Test baseline:** 577+ tests / 2268+ assertions (all green)
**Uncommitted changes:** 39 files + 20 untracked, +987 / -273 lines (feature registry + hardening)
**Related docs:**
- `docs/audit/00-overview.md` through `docs/audit/04-routes-views-config.md` (Forensic Audit)
- `docs/memory.md` (Long-term project memory)
- `docs/standard-list-page.md` (SLP spec)
- `docs/customcommand.md` (Natural-language command mapping)

---

## Table of Contents

1. [Big Picture](#1-big-picture)
2. [Phase 0 — Architecture Contract](#2-phase-0--architecture-contract)
3. [Phase 1 — Existing System Hardening](#3-phase-1--existing-system-hardening)
4. [Phase 2 — Taxonomy Cutover](#4-phase-2--taxonomy-cutover)
5. [Phase 3 — Feature Registry Foundation](#5-phase-3--feature-registry-foundation)
6. [Phase 4 — Package Feature Entitlement](#6-phase-4--package-feature-entitlement)
7. [Phase 5 — Feature Runtime Authorization](#7-phase-5--feature-runtime-authorization)
8. [Phase 6 — Generic Module Configuration](#8-phase-6--generic-module-configuration)
9. [Phase 7 — Institute Configuration Override](#9-phase-7--institute-configuration-override)
10. [Phase 8 — Unified Effective Resolution Engine](#10-phase-8--unified-effective-resolution-engine)
11. [Phase 9 — Remove Country Hardcoding](#11-phase-9--remove-country-hardcoding)
12. [Phase 10 — Observability & Audit](#12-phase-10--observability--audit)
13. [Phase 11 — Seeder / Migration / Data Governance](#13-phase-11--seeder--migration--data-governance)
14. [Phase 12 — Full Security & Tenant Forensic Audit](#14-phase-12--full-security--tenant-forensic-audit)
15. [Phase 13 — Full Test & Acceptance](#15-phase-13--full-test--acceptance)
16. [Critical Sequencing](#16-critical-sequencing)
17. [Immediate Next Steps](#17-immediate-next-steps)
18. [Phase Exit Criteria](#18-phase-exit-criteria)
19. [Surfaced Bugs Registry](#19-surfaced-bugs-registry)
20. [Rules & Constraints](#20-rules--constraints)

---

## 1. Big Picture

AccumenAI is a **multi-tenant education management platform** (Laravel 12, PHP 8.2+, MySQL) with three portals: Platform Admin, Institute Staff, and Global accounts. The roadmap builds a **data-driven, industry-aware, RBAC-gated SaaS** on top of the existing monolith.

**Architecture layers (bottom-up):**

```
┌─────────────────────────────────────────────────┐
│  Phase 13: Full Test & Acceptance               │
├─────────────────────────────────────────────────┤
│  Phase 12: Security & Tenant Forensic Audit     │
├─────────────────────────────────────────────────┤
│  Phase 11: Seeder / Migration / Data Governance │
├─────────────────────────────────────────────────┤
│  Phase 10: Observability & Audit                │
├─────────────────────────────────────────────────┤
│  Phase 9:  Remove Country Hardcoding            │
├─────────────────────────────────────────────────┤
│  Phase 8:  Unified Effective Resolution Engine  │
├─────────────────────────────────────────────────┤
│  Phase 7:  Institute Configuration Override     │
├─────────────────────────────────────────────────┤
│  Phase 6:  Generic Module Configuration         │
├─────────────────────────────────────────────────┤
│  Phase 5:  Feature Runtime Authorization        │
├─────────────────────────────────────────────────┤
│  Phase 4:  Package Feature Entitlement          │
├─────────────────────────────────────────────────┤
│  Phase 3:  Feature Registry Foundation          │
├─────────────────────────────────────────────────┤
│  Phase 2:  Taxonomy Cutover                     │
├─────────────────────────────────────────────────┤
│  Phase 1:  Existing System Hardening            │
├─────────────────────────────────────────────────┤
│  Phase 0:  Architecture Contract                │
└─────────────────────────────────────────────────┘
```

**Resolution chain (future state):**
Platform Default → Industry Default → Package Default → Institute Override → Branch Override → User Override

---

## 2. Phase 0 — Architecture Contract

**Status: COMPLETE (audit deliverables exist)**

### Deliverables
- `docs/audit/00-overview.md` — Consolidated read-only architecture overview (2026-08-17)
- `docs/audit/01-models.md` — Complete model inventory (60+ models, 3 concern traits)
- `docs/audit/02-controllers.md` — Controller audit (45 controllers, 5 FormRequests)
- `docs/audit/03-support.md` — Support layer audit (concerns, services, middleware, helpers)
- `docs/audit/04-routes-views-config.md` — Routing, views, and config audit

### Key Findings (from audit)
- TenantScoped/BranchScoped are no-ops when context is null (deliberate for platform-admin)
- Static TenantContext/BranchContext have no queue/job context restoration
- Three parallel auth realms (web, platform_admin, institute_user) duplicate visibility logic
- `$guarded = []` is the norm across most models (mitigated by scope hooks)
- Mixed styling stack (Bootstrap 5.3 + Tailwind v4)

---

## 3. Phase 1 — Existing System Hardening

**Status: COMPLETE (14/14 items done — includes hardening sweep)**

### Deliverables

| # | Item | Status | Key Files |
|---|------|--------|-----------|
| 1 | Forensic audit (4-part) | Done | `docs/audit/00-04` |
| 2 | TenantScoped hardened | Done | `app/Models/Concerns/TenantScoped.php` |
| 3 | BranchScoped hardened | Done | `app/Models/Concerns/BranchScoped.php` |
| 4 | BranchScopedOrShared | Done | `app/Models/Concerns/BranchScopedOrShared.php` |
| 5 | SecurityHeaders middleware | Done | `app/Http/Middleware/SecurityHeaders.php` |
| 6 | BlockPlatformAdminEscalation | Done | `app/Http/Middleware/BlockPlatformAdminEscalation.php` |
| 7 | AuditActivityLog (SEC-05) | Done | `app/Http/Middleware/AuditActivityLog.php` |
| 8 | SchemaVersionCheck | Done | `app/Http/Middleware/SchemaVersionCheck.php` |
| 9 | Security config | Done | `config/security.php` |
| 10 | Super Admin singleton | Done | `SingleImmutableSuperAdminTest.php` |
| 11 | System services (40+) | Done | `app/Services/System/` |
| 12 | Route throttling + guards | Done | `routes/web.php` |
| 13 | Mass-assignment hardening ($fillable) | Done | 8 models: Institute, InstituteModuleEntitlement, InstituteModuleOverride, ModuleRegistry, ModuleAccessLog, SubscriptionPackage, PackageModule, IndustrySetting, AccountingSetting |
| 14 | AuditActivityLog SEC-05 rewrite | Done | `app/Http/Middleware/AuditActivityLog.php` — never reads `institute_id` from request input |

### Hardening Summary
- **TenantScoped** (40+ models): creating hook forces `institute_id`, updating hook blocks `institute_id`/`created_by` tampering
- **BranchScoped** (10+ models): same pattern for `branch_id`
- **BranchScopedOrShared** (accounting models): allows institute-wide rows (branch_id NULL)
- **17 middleware classes** including SecurityHeaders (CSP/HSTS), BlockPlatformAdminEscalation, AuditActivityLog
- **Mass-assignment hardening**: 8 models switched from `$guarded = []` to explicit `$fillable` arrays (SEC-02)
- **Audit log integrity**: `AuditActivityLog` now resolves institute ID only from `TenantContext`/`Workspace` — never from `$request->input()` (SEC-05)
- **Test coverage**: TenantSecurityTest, SecurityHardeningTest, SecurityAuditTest, MassAssignmentTest, etc.

---

## 4. Phase 2 — Taxonomy Cutover

**Status: COMPLETE**

### Deliverables

| # | Item | Status | Key Files |
|---|------|--------|-----------|
| 1 | Industries table | Done | `migration 000001` |
| 2 | Sub-industries table | Done | `migration 000002` |
| 3 | Institute FK columns | Done | `migration 000003` |
| 4 | Backfill service | Done | `InstituteTaxonomyBackfill.php` |
| 5 | Remediation migration | Done | `migration 000005` |
| 6 | Industry model | Done | `app/Models/Industry.php` |
| 7 | SubIndustry model | Done | `app/Models/SubIndustry.php` |
| 8 | IndustryService (DB-backed) | Done | `app/Services/IndustryService.php` |
| 9 | IndustryRules (DB-first) | Done | `app/Support/IndustryRules.php` |
| 10 | InstituteDomain resolver | Done | `app/Support/InstituteDomain.php` |
| 11 | IndustryTaxonomySeeder | Done | `database/seeders/IndustryTaxonomySeeder.php` |
| 12 | config/industry_rules.php | Done | 14 industries, country-specific sub-industries |
| 13 | Test coverage | Done | 3 test files |

### Resolution
`IndustryRules` reads DB first (via `IndustryService`, 1-hour cache), falls back to `config/industry_rules.php`. `InstituteDomain` maps (industry, sub_industry) → domain: `academic`, `professional`, `medical`, `other`.

---

## 5. Phase 3 — Feature Registry Foundation

**Status: COMPLETE (expanded with feature-level registry)**

### Deliverables

| # | Item | Status | Key Files |
|---|------|--------|-----------|
| 1 | ModuleRegistry model | Done | `app/Models/ModuleRegistry.php` |
| 2 | parent_key migration | Done | `migration 2026_09_17_000001` |
| 3 | ModuleRegistrySeeder | Done | 14 modules seeded |
| 4 | MedicalSubModuleSeeder | Done | Medical sub-modules (SEC-03: no mass-grant) |
| 5 | ModuleAccessService | Done | `app/Services/ModuleAccessService.php` (850+ lines) |
| 6 | feature_registry table | Done | `migration 2026_09_18_040000` — 12 medical features |
| 7 | package_features table | Done | `migration 2026_09_18_040100` — package→feature mapping |
| 8 | FeatureRegistrySeeder | Done | 12 medical features (pharmacy, laboratory, billing, etc.) |
| 9 | PackageFeatureSeeder | Done | free=0, basic=0, advanced=12, premium=12 |
| 10 | Legacy institute backfill | Done | `migration 2026_09_18_161315` — assigns FREE package |
| 11 | Coming_soon deactivation | Done | `migration 2026_09_18_215540` + `223446` |
| 12 | Test coverage | Done | 3 original + `FeatureRegistryPilotTest` |

### Modules Seeded
**Core (11):** crm, accounting, finance, inventory, hr, sales, purchase, reports, notifications, ai, vat
**Industry (3):** education, training_center, medical (with sub-modules)

### Feature Registry Schema
```
feature_registry: feature_key, module_key, name, parent_feature_key, sort_order, status
package_features: package_id, feature_key, enabled
```
Resolution: `isFeatureEnabled(institute, featureKey)` → parent module enabled → feature exists (active) → package_features row enabled. Fail-closed.

---

## 6. Phase 4 — Package Feature Entitlement

**Status: COMPLETE (hardened with hardening sweep)**

### Deliverables

| # | Item | Status | Key Files |
|---|------|--------|-----------|
| 1 | SubscriptionPackage model | Done | `app/Models/SubscriptionPackage.php` |
| 2 | PackageModule model | Done | `app/Models/PackageModule.php` |
| 3 | InstituteModuleEntitlement model | Done | `app/Models/InstituteModuleEntitlement.php` |
| 4 | InstituteModuleOverride model | Done | `app/Models/InstituteModuleOverride.php` |
| 5 | InstituteSubscription model | Done | `app/Models/InstituteSubscription.php` |
| 6 | ModuleAccessLog model | Done | `app/Models/ModuleAccessLog.php` |
| 7 | ModuleAccessService (6-step pipeline) | Done | `app/Services/ModuleAccessService.php` |
| 8 | SaasSubscriptionService (bKash) | Done | `app/Services/SaasSubscriptionService.php` |
| 9 | EntitlementsExpire command | Done | `app/Console/Commands/EntitlementsExpire.php` |
| 10 | Admin controller | Done | `InstituteModuleEntitlementController.php` |
| 11 | config/country_modules.php | Done | 12 country defaults |
| 12 | Test coverage | Done | 11 test files |
| 13 | Override archival (SEC-03) | Done | `institute_module_overrides_archive` table |
| 14 | Fail-closed subscription check (SEC-01) | Done | `isSubscriptionActive()` rewrite |
| 15 | Service-layer industry compatibility | Done | `isIndustryCompatible()` public method |
| 16 | Audit log actor-type attribution | Done | `actor_type` column + `resolveActorType()` |

### Resolution Pipeline (6-step)
1. **Package base**: modules from `SubscriptionPackage` → `PackageModule`
2. **Education filter**: remove sales/purchase/hr/crm for education industry
3. **Legacy override**: `InstituteModuleOverride` force-enable/disable
4. **Individual entitlement**: `InstituteModuleEntitlement` grants/denials (latest wins; deny on tie)
5. **Industry compatibility**: entitlements cannot bypass industry rules
6. **Dependency closure**: missing dependencies/parent disables child

---

## 7. Phase 5 — Feature Runtime Authorization

**Status: COMPLETE (expanded with feature-level gating)**

### Deliverables

| # | Item | Status | Key Files |
|---|------|--------|-----------|
| 1 | Permission model | Done | `app/Models/Permission.php` |
| 2 | Role model | Done | `app/Models/Role.php` |
| 3 | RolePermission pivot | Done | `app/Models/RolePermission.php` |
| 4 | CheckPermission middleware | Done | `app/Http/Middleware/CheckPermission.php` |
| 5 | CheckModuleAccess middleware | Done | `app/Http/Middleware/CheckModuleAccess.php` |
| 6 | MedicalModuleAccess middleware | Done | `app/Http/Middleware/MedicalModuleAccess.php` |
| 7 | EnsureDomain middleware | Done | `app/Http/Middleware/EnsureDomain.php` |
| 8 | EnsureAiEnabled middleware | Done | `app/Http/Middleware/EnsureAiEnabled.php` |
| 9 | CheckFeatureAccess middleware | Done | `app/Http/Middleware/CheckFeatureAccess.php` — `feature:medical.pharmacy` |
| 10 | Permission seeders (4) | Done | Staff, Admin, Medical, MedicalAlias |
| 11 | Blade directives | Done | `@featureEnabled`, `@featureLocked`, `@featureHidden` in AppServiceProvider |
| 12 | Locked menu UX | Done | `menu-item-locked` CSS + `upgrade/show.blade.php` + `upgrade.show` route |
| 13 | Test coverage | Done | 2+ original + 13 new test files (~76 assertions) |

### Route Pattern (Module-Level)
```php
->middleware('permission:students.view', 'module_access:education')
```

### Route Pattern (Feature-Level — Dual Gate)
```php
->middleware('medical.module:medical.pharmacy', 'feature:medical.pharmacy')
```
Module must be enabled AND feature must be enabled. 403 if either fails.

### Feature-Gated Medical Routes
| Sub-Module | Middleware | Feature Gate |
|------------|-----------|--------------|
| pharmacy | `medical.module:medical.pharmacy` | `feature:medical.pharmacy` |
| laboratory | `medical.module:medical.laboratory` | `feature:medical.laboratory` |
| billing | `medical.module:medical.billing` | `feature:medical.billing` |
| emergency | `medical.module:medical.emergency` | `feature:medical.emergency` |
| radiology | `medical.module:medical.radiology` | `feature:medical.radiology` |
| bloodbank | `medical.module:medical.bloodbank` | `feature:medical.bloodbank` |
| ambulance | `medical.module:medical.ambulance` | `feature:medical.ambulance` |
| physiotherapy | `medical.module:medical.physiotherapy` | — |
| dental | `medical.module:medical.dental` | — |
| vaccination | `medical.module:medical.vaccination` | — |
| records | `medical.module:medical.records` | — |
| diet | `medical.module:medical.diet` | — |

### Blade Directives
- `@featureEnabled('medical.pharmacy')` — renders if feature enabled
- `@featureLocked('medical.pharmacy')` — renders if parent module enabled + industry compatible + feature NOT enabled (shows upgrade CTA)
- `@featureHidden('medical.pharmacy')` — renders nothing if industry incompatible
- Shared institute query cache (single `Institute::find()` per request)

---

## 8. Phase 6 — Generic Module Configuration

**Status: SUBSTANTIALLY IMPLEMENTED — feature registry + service centralization landed**

### What Exists
- `ModuleRegistry` with `key`, `parent_key`, `type`, `dependencies` (JSON)
- `feature_registry` table — 12 medical features with `feature_key`, `module_key`, `status`
- `package_features` table — package→feature mapping
- `ModuleAccessService` — 6-step module resolution + `isFeatureEnabled()` (850+ lines)
- `UserModuleAccessService` — per-user module override layer
- `CheckModuleAccess` + `CheckFeatureAccess` middleware
- `ModuleAdminController` — admin CRUD for modules
- `config/country_modules.php` — 12 country defaults
- `config/industry_rules.php` — industry capabilities
- `app/Models/Setting.php` — platform-wide key-value (encrypted secrets, 60s cache)
- `app/Models/InstituteSetting.php` — per-institute JSON settings
- `app/Models/IndustrySetting.php` — industry-level settings
- `app/Models/AccountingSetting.php` — branch-scoped accounting settings
- `ModuleSettingsController` — routes through service layer (SEC-04, no raw DB writes)

### Gaps
- **No unified `ConfigResolutionEngine`** — settings scattered across 5+ models
- **Duplicate merge pattern** — `array_replace_recursive(DEFAULTS, db_config)` copied across Purchase/Sales services
- **No shared `ConfigMerger` utility** — each domain implements its own merge logic

### Remaining Work
1. Create `app/Support/ConfigResolutionEngine.php` — generic multi-layer resolver
2. Create `app/Support/ConfigMerger.php` — standardized merge utility
3. Refactor Purchase/Sales/Accounting services to use shared utility
4. Add config resolution tests

---

## 9. Phase 7 — Institute Configuration Override

**Status: WELL IMPLEMENTED — gaps in generic override stack**

### What Exists
- `InstituteSetting` model — JSON-cast columns for ai_config, notification_settings, sales_config, purchase_config, training_config, theme colors, etc.
- `InstituteSettingController` — full settings management (index/account/appearance/notifications/security)
- `LearningStructureResolver` — 6-step priority chain for academic templates
- `CertificateApprovalModeService` — institute-level certificate approval
- `InventoryCapabilityService` — industry defaults + tenant overrides
- `PurchaseSettingsService` / `SalesSettingsService` — `DEFAULTS` + merge + audit trail
- `AppServiceProvider::syncIndustryModule()` — auto-assigns industry module on institute create/update

### Gaps
- **No generic override stack** — each domain implements its own resolution independently
- **Duplicate merge code** — Purchase/Sales use identical `array_replace_recursive()` pattern
- **No branch-level override** — overrides stop at institute level

### Remaining Work
1. Create `app/Support/ConfigResolutionEngine.php` (shared with Phase 6)
2. Add branch-level override support to `InstituteSetting`
3. Refactor domain services to use shared resolution engine
4. Add override precedence tests

---

## 10. Phase 8 — Unified Effective Resolution Engine

**Status: PARTIALLY IMPLEMENTED — per-domain engines exist, no unified engine**

### What Exists (Per-Domain)
- `LearningStructureResolver` — 6-step priority chain (most complete)
- `ModuleAccessService::resolveEnabled()` — 6-step module resolution
- `InventoryCapabilityService` — industry defaults + tenant overrides
- `PurchaseSettingsService` / `SalesSettingsService` — DEFAULTS + merge
- `AcademicFinalResultService` — institute override wins over global
- `AcademicGradingService` — fallback to hardcoded defaults

### What Does NOT Exist
- **No `ConfigResolutionEngine`** — generic multi-layer resolver
- **No `ConfigMerger`** — standardized merge utility
- **No branch-level resolution** — chain stops at institute

### Remaining Work
1. Create `app/Support/ConfigResolutionEngine.php`
2. Define resolution chain: Platform → Industry → Package → Institute → Branch → User
3. Migrate domain-specific resolvers to use unified engine
4. Add resolution audit logging (which layer won?)
5. Add resolution tests for each layer

---

## 11. Phase 9 — Remove Country Hardcoding

**Status: SUBSTANTIALLY IMPLEMENTED — transport alias fixed in hardening sweep**

### What Exists
- `GeoHierarchy` — country-neutral 3-level address system (DB-driven)
- `CountryCodes` — 195 countries, phone formats, mobile digit lengths
- `PhoneNormalizer` — country-aware phone normalization
- `mawa_lang()` — custom localization with 2400+ keys (en + bn)
- `SetLocale` middleware — locale resolution chain
- `IndustryRules` — DB-first industry lookups
- `config/geo-labels.php` — country-specific address level names
- **Transport alias normalization** (hardening sweep): `InstituteDomain::normalizeIndustry()` resolves legacy `transport` → `transportation` transparently

### Remaining Hardcoding
1. `CountryCodes::codeFor()` — defaults to `'880'` (Bangladesh) when unknown
2. `AccountingSetupService::COUNTRY_TO_CURRENCY` — hardcoded 5-country map (BD/US/IN/PK)
3. `AcademicGradingService::DISPLAY_PRECISION` — hardcoded 2 decimal places

### Remaining Work
1. `CountryCodes::codeFor()` → return null/throw on unknown country
2. `AccountingSetupService` → DB-backed currency resolution (from `countries` table)
3. `AcademicGradingService` → configurable precision per institute
4. Add missing country entries to `config/industry_rules.php`

---

## 12. Phase 10 — Observability & Audit

**Status: WELL IMPLEMENTED — actor-type attribution added in hardening sweep**

### What Exists
- **3 audit tables**: `audit_logs` (institute-scoped), `accounting_audit_trails` (financial), `platform_audit_logs` (platform-wide)
- **AuditActivityLog middleware** — logs auth events, permission denials, module access
- **BlockPlatformAdminEscalation middleware** — logs escalation attempts
- **SecurityAuditService** — aggregates security metrics (score-based)
- **DatabaseMonitoringService** — 20+ sub-services (health, consistency, FK, duplicates, tenant isolation, etc.)
- **Event listeners**: `logJournalPosted`, `LogInvoicePaid`
- **SecurityAuditController** — security dashboard
- **Actor-type attribution** (hardening sweep): `ModuleAccessLog` now has `actor_type` column populated by `resolveActorType()` — distinguishes platform_admin, institute_user, web, guardian, system

### Gaps
1. **3 separate audit tables** with different schemas — no unified query interface
2. **Limited event listeners** — only journal posting and invoice payment
3. **No centralized audit dashboard** aggregating all 3 sources
4. **No alerting** on critical events (privilege escalation, cross-tenant access)

### Remaining Work
1. Create `AuditLogSearchService` — unified query across all 3 tables
2. Add event listeners for: user creation, role changes, module access changes, entitlement changes
3. Create unified audit dashboard view
4. Add alerting rules for critical events
5. Add audit log retention/archival policy

---

## 13. Phase 11 — Seeder / Migration / Data Governance

**Status: WELL IMPLEMENTED — dry-run + safeguard patterns + feature registry seeders**

### What Exists
- **Master seeder**: `DatabaseSeeder` calls 9 seeders (added FeatureRegistrySeeder, PackageFeatureSeeder)
- **17+ seeders**: ModuleRegistry, IndustryTaxonomy, AcademicStructure, GradeScale, AdditionalCountry, AcademicAssessment, Certificate, RoleTemplate, StaffPermission, AdminPermission, Medical*, PassMarkDefaults, LearningStructure, Exam, CdsRule, **FeatureRegistry**, **PackageFeature**
- **SeedVersionService** — SHA-256 checksums for 7 seed categories
- **SeedIntegrityService** — validates required system data exists
- **SchemaVersionService** — tracks migration status
- **AccountDeletionGovernance** — full lifecycle governance
- **DataSafetyGuard** — prevents destructive operations
- **Console commands**: `system:verify-seeds`, `system:audit-db`, `taxonomy:backfill-institutes`, `entitlements:expire`
- **Dry-run + safeguard flag** (hardening sweep): `entitlements:expire --dry-run` previews without DB writes; scheduler gated behind `ENTITLEMENTS_EXPIRE_SCHEDULED=false` by default
- **MedicalSubModuleSeeder cleanup** (hardening sweep): removed mass-enable bypass that wrote directly to `institute_module_overrides`, bypassing the entitlement lifecycle
- **Legacy institute backfill** (feature registry): assigns FREE package to all institutes with `package_id = NULL`

### Gaps
1. **No seeder rollback** — only `seedDefaults()` for repair
2. **Limited seed version tracking** — only 7 categories (missing medical, CRM, notifications)
3. **No data governance dashboard** — only CLI commands
4. **No automated data quality checks** on schedule

### Remaining Work
1. Add seed version tracking for medical, CRM, notification templates
2. Create data governance dashboard (admin view)
3. Add scheduled data quality checks (artisan scheduler)
4. Document rollback procedures for each seeder

---

## 14. Phase 12 — Full Security & Tenant Forensic Audit

**Status: SUBSTANTIALLY IMPLEMENTED — major fixes in hardening sweep**

### What Exists
- **TenantIsolationAuditService** — creates 3 tenant contexts, tests cross-access
- **SecurityHeaders** — CSP, HSTS, X-Frame-Options on every response
- **BlockPlatformAdminEscalation** — blocks privilege escalation
- **SecurityAuditService** — aggregates security metrics
- **EnterpriseDatabaseCertificationService** — 13-check certification with weighted scoring
- **ProductionDatabaseAuditService** — integrity scoring across 9 categories
- **BackupService** + **DisasterRecoveryService** — backup/restore/verify
- **10+ security test files** covering tenant isolation, mass assignment, auth flows

### Hardening Sweep Security Fixes
| SEC | Fix | Impact |
|-----|-----|--------|
| SEC-01 | `isSubscriptionActive()` fail-closed | Prevents subscription bypass via missing row, non-active status, past date, or DB exception |
| SEC-02 | 8 models `$fillable` arrays | Prevents mass-assignment IDOR on Institute, entitlement, override, package, module models |
| SEC-03 | Override archival + seeder cleanup | Override lifecycle now auditable; MedicalSubModuleSeeder no longer bypasses entitlement flow |
| SEC-04 | Service-layer module toggles | `ModuleSettingsController` routes through `enableModule()`/`disableModule()` — industry validation + audit + cache invalidation |
| SEC-05 | Audit log institute ID from authoritative sources | `AuditActivityLog` never reads `institute_id` from `$request->input()` — prevents log poisoning |

### Known Risks
1. **TenantScoped no-op when context is null** — any unguarded code path loses isolation
2. **Static TenantContext/BranchContext** — no queue/job context restoration
3. **No automated penetration testing**
4. **No formal vulnerability scanning**

### Remaining Work
1. Add queue/job context restoration for TenantContext/BranchContext
2. Add automated security scanning (e.g., `laravel/security-checker`)
3. Document manual penetration testing procedures
4. Add cross-tenant access alerts (real-time monitoring)

---

## 15. Phase 13 — Full Test & Acceptance

**Status: EXTENSIVE — expanded with feature registry + hardening sweep tests**

### What Exists
- **577+ tests / 2268+ assertions** (all green)
- **7 unit tests**: PhoneRule, PhoneNormalizer, IndustryRules, IndustryRulesFallback, CountryCodes, AiLanguage, Example
- **113+ feature tests**: Security, Academic, Accounting, AI, Medical, CRM, Inventory, Module Access, Feature Registry, etc.
- **PHPUnit 11.5.56**, PHP 8.5.8

### Hardening Sweep Test Additions (~276 new assertions)
| Test File | New Coverage |
|-----------|-------------|
| `EntitlementsExpireTest.php` | Dry-run no-op, dry-run output, live transition + audit logs, exit codes, safeguard config flag |
| `InstituteModuleEntitlementAdminTest.php` | Service-level industry compatibility (medical/education/training_center), controller rejection |
| `IndustryInstitutionDomainTest.php` | 14 tests: training_center independence, domain mapping, legacy aliases, transport normalization |
| `SaaSModuleAccessTest.php` | Multi-module bulk update API, package slug renames, nonexistent module rejection |
| `FeatureRegistryPilotTest.php` | Table exists, 12 medical features, unique feature_key, package mapping, seeder idempotency |
| `CheckFeatureAccessMiddlewareTest.php` | Allow/block, PlatformAdmin bypass, no-tenant block, parent-disabled block, fail-closed |
| `OverrideArchiveTest.php` | Archive on changePackage, empty case, no shadow, metadata preserved |
| `AuditActivityLogTest.php` | SEC-05: injected `?institute_id` ignored, poisoned request never reaches audit table |
| `ModuleAccessFeatureGateTest.php` | isFeatureEnabled chain: package, parent, registry, status, malformed key, null package fallback |
| `ModuleAccessIndustryCompatibilityTest.php` | Education↔education, medical↔healthcare, training_center↔training_center, non-industry always pass |
| `ModuleAccessLogActorTypeTest.php` | PlatformAdmin→platform_admin, InstituteUser→institute_user, CLI→system, legacy null |
| `ModuleAccessSubscriptionActiveTest.php` | SEC-01: past end_date inactive, future active, cancelled inactive, expired inactive |
| `ModuleSettingsModuleAccessTest.php` | SEC-04: self-service toggle through service layer, override row, audit log |
| `LockedMenuUpgradeTest.php` | Pharmacy normal/locked/hidden, upgrade page, 404 unknown, query performance |
| `MedicalBatch1FeatureGateTest.php` | Laboratory/BloodBank/Radiology: normal/locked/403, pharmacy unaffected |
| `MedicalBillingModuleGateTest.php` | All billing routes have medical.module middleware |
| `MedicalPharmacyFeatureGateTest.php` | Pharmacy accessible/blocked/parent-disabled, dual middleware on all routes |
| `MedicalSubModuleSeederTest.php` | SEC-03: idempotent, no mass-grant, existing overrides preserved |

### Gaps
1. **Minimal unit tests** (7) vs feature tests (100+)
2. **No code coverage reports** in phpunit.xml
3. **No dedicated tests** for: LearningStructureResolver, ModuleAccessService::resolveEnabled(), settings merge patterns
4. **No acceptance criteria tests** (user-story-level)
5. **No performance/load tests**

### Remaining Work
1. Add unit tests for ConfigResolutionEngine (when built)
2. Add unit tests for ModuleAccessService resolution pipeline
3. Add unit tests for settings merge patterns
4. Configure code coverage in phpunit.xml
5. Add acceptance criteria tests for critical user stories
6. Add performance benchmarks for key operations

---

## 16. Critical Sequencing

```
Phase 0 (Audit) ──→ Phase 1 (Hardening) ──→ Phase 2 (Taxonomy)
                                              │
Phase 3 (Feature Registry) ←──────────────────┘
       │
Phase 4 (Package Entitlement) ←── Phase 3
       │
Phase 5 (Runtime Auth) ←── Phase 4
       │
Phase 6 (Module Config) ←── Phase 5
       │
Phase 7 (Institute Override) ←── Phase 6
       │
Phase 8 (Resolution Engine) ←── Phase 6 + Phase 7
       │
Phase 9 (Remove Hardcoding) ←── Phase 8
       │
Phase 10 (Observability) ←── Phase 5 + Phase 8
       │
Phase 11 (Data Governance) ←── Phase 2 + Phase 3
       │
Phase 12 (Security Audit) ←── Phase 1 + Phase 5 + Phase 10
       │
Phase 13 (Test & Acceptance) ←── ALL PHASES
```

**Critical path:** Phase 0 → 1 → 2 → 3 → 4 → 5 → 6 → 7 → 8

**Parallel tracks:**
- Phase 9 (Remove Hardcoding) can run alongside Phase 6-8
- Phase 10 (Observability) can run alongside Phase 6-8
- Phase 11 (Data Governance) can run alongside Phase 6-8
- Phase 12 (Security Audit) requires Phase 1 + Phase 5 + Phase 10
- Phase 13 (Test & Acceptance) is continuous throughout

---

## 17. Immediate Next Steps

### Priority 1: Complete Phase 6-8 (Unified Resolution)
1. Create `app/Support/ConfigResolutionEngine.php`
2. Create `app/Support/ConfigMerger.php`
3. Refactor Purchase/Sales/Accounting services to use shared utility
4. Add branch-level override to `InstituteSetting`
5. Add resolution audit logging
6. Add tests for each resolution layer

### Priority 2: Feature Registry Admin UI (Phase 5e completion)
1. Admin CRUD for `feature_registry` (manage features per module)
2. Admin UI for `package_features` (assign features to packages)
3. `entitlements:expire` update for feature-level expiry
4. End-to-end upgrade flow testing

### Priority 3: Clean Up Hardcoding (Phase 9)
1. Fix `CountryCodes::codeFor()` fallback
2. DB-back currency resolution in `AccountingSetupService`
3. Configurable grading precision

### Priority 4: Unified Audit (Phase 10)
1. Create `AuditLogSearchService`
2. Add missing event listeners
3. Create unified audit dashboard

### Priority 5: Cleanup
1. Delete `audit_tmp*.php` files (4 files at project root)
2. Delete `PageMarker` (when development is done)
3. Move `react`/`react-dom` to devDependencies if not needed
4. Commit all changes (39 files + 20 untracked, +987/-273)

---

## 18. Phase Exit Criteria

### Phase 5e Exit (Feature-Level Entitlements)
- [x] `feature_registry` table exists with 12 medical features
- [x] `package_features` table maps features to packages
- [x] `CheckFeatureAccess` middleware gates routes
- [x] Blade directives (`@featureEnabled`, `@featureLocked`, `@featureHidden`) work
- [x] Locked menu UX with upgrade CTA
- [x] Legacy institutes backfilled to FREE package
- [x] Coming_soon flags cleared for active modules
- [ ] Admin CRUD for `feature_registry`
- [ ] Admin UI for `package_features`
- [ ] Feature-level entitlement extension via Super Admin

### Phase 6 Exit
- [ ] `ConfigResolutionEngine` exists and is tested
- [ ] `ConfigMerger` utility exists and is tested
- [ ] Purchase/Sales/Accounting services use shared utility
- [ ] No duplicate merge code across domain services

### Phase 7 Exit
- [ ] Branch-level override support added to `InstituteSetting`
- [ ] All domain services use `ConfigResolutionEngine`
- [ ] Override precedence tests pass
- [ ] Documentation updated

### Phase 8 Exit
- [ ] `ConfigResolutionEngine` handles full chain: Platform → Industry → Package → Institute → Branch → User
- [ ] Resolution audit logging shows which layer won
- [ ] All domain resolvers migrated to unified engine
- [ ] Resolution tests for each layer pass

### Phase 9 Exit
- [ ] `CountryCodes::codeFor()` returns null on unknown
- [ ] Currency resolution is DB-backed
- [ ] Grading precision is configurable
- [ ] No hardcoded country values in business logic

### Phase 10 Exit
- [ ] `AuditLogSearchService` queries all 3 audit tables
- [ ] Event listeners exist for critical events
- [ ] Unified audit dashboard view exists
- [ ] Alerting rules for critical events

### Phase 11 Exit
- [ ] Seed version tracking covers all categories
- [ ] Data governance dashboard exists
- [ ] Scheduled data quality checks run
- [ ] Rollback procedures documented

### Phase 12 Exit
- [ ] Queue/job context restoration for TenantContext/BranchContext
- [ ] Automated security scanning configured
- [ ] Penetration testing procedures documented
- [ ] Cross-tenant access alerts working

### Phase 13 Exit
- [ ] Unit test coverage ≥ 80% for core services
- [ ] Code coverage reports configured
- [ ] Acceptance criteria tests for critical user stories
- [ ] Performance benchmarks documented

---

## 19. Surfaced Bugs Registry

| # | Bug | Source | Status | Fix |
|---|-----|--------|--------|-----|
| 1 | `StudentsTool` empty names (full_name accessor shadows DB column) | Branch auth tests | Fixed | Select `first_name`/`last_name` instead |
| 2 | `BatchesTool` start_date uncast string | Branch auth tests | Fixed | Parse with Carbon |
| 3 | Duplicate flash "Certificate revoked." on admin pages | Hardening | Fixed | Removed per-page duplicates |
| 4 | Taxonomy backfill ran before seeder populated data | Phase 2 | Fixed | Remediation migration `000005` |
| 5 | `$guarded = []` on most models | Audit | Fixed | 8 models switched to `$fillable` in hardening sweep |
| 6 | `AuditActivityLog` reads `institute_id` from request input | SEC-05 audit | Fixed | Resolves from TenantContext/Workspace only |
| 7 | `isSubscriptionActive()` permissive on exceptions | SEC-01 audit | Fixed | Fail-closed: returns false on any exception |
| 8 | MedicalSubModuleSeeder mass-enabled all sub-modules | SEC-03 audit | Fixed | Removed bypass; access flows through entitlement lifecycle |
| 9 | `ModuleSettingsController` raw DB writes bypass service | SEC-04 audit | Fixed | Routes through `enableModule()`/`disableModule()` |
| 10 | `EntitlementsExpire` runs unconditionally on schedule | Safety audit | Fixed | Gated behind `ENTITLEMENTS_EXPIRE_SCHEDULED=false` + `--dry-run` |
| 11 | Legacy `transport` slug has no DB row, breaks resolution | Taxonomy audit | Fixed | `InstituteDomain::normalizeIndustry()` resolves transparently |

---

## 20. Rules & Constraints

1. **Pagination box must be centered on every page.** Count text goes below the pagination box.
2. **Institute staff must NOT manage their own SMTP/payment config** — admin-only.
3. **All blue Bootstrap elements follow the active theme.** Buttons, dropdowns, nav-pills, pagination, links, focus rings.
4. **Institute identity comes ONLY from authenticated user** — never from request input.
5. **TenantScoped/BranchScoped hooks are the primary security net** — never trust `$guarded`.
6. **Write-mode AI tools require explicit user confirmation** — no auto-execution.
7. **Students must select `first_name`/`last_name`** — never `full_name` (accessor shadow).
8. **batches.start_date is uncast** — always parse with Carbon.
9. **Schema changes: migration → --pretend → dev DB → test DB → run tests.**
10. **Keep migrations idempotent** — guard with `Schema::hasColumn()`/`Schema::hasTable()`.
11. **Git commit before risky commands** — power outage recovery.
12. **After Blade edits: `artisan view:clear`.**
13. **Page Marker is temporary** — delete when development is done.
14. **Feature-gated routes use dual middleware** — `medical.module:X` + `feature:X` for pharmacy, laboratory, billing, emergency, radiology, bloodbank, ambulance.
15. **Feature directives share institute cache** — single `Institute::find()` per request for `@featureEnabled`/`@featureLocked`/`@featureHidden`.
16. **MedicalSubModuleSeeder is idempotent** — no mass-grant of `medical.*` overrides; access flows through entitlement lifecycle only.
17. **Override archival preserves lifecycle** — `changePackage()` archives overrides to `institute_module_overrides_archive` before deleting.
18. **EntitlementsExpire scheduler is off by default** — `ENTITLEMENTS_EXPIRE_SCHEDULED=false` until explicitly enabled.
14. **Tests: `DatabaseTransactions` + `vendor\bin\phpunit`** — rolls back automatically.
