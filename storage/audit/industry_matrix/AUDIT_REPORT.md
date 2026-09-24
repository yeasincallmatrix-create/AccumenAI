# AUDIT REPORT — Industry-Module Matrix & Package System
# Date: 2026-09-23
# Auditor: MimoV2Flash
# Context: Prerequisite for Sub-Industry Package Build
# Mode: READ-ONLY (no code changes, no migrations, SELECT-only)

---

## 1. Executive Summary

| Metric | Value |
|--------|-------|
| Industry config exists (`config/industry-modules.php`) | **YES** (6 industries + core) |
| Sub-industry config (`config/sub-industries.php`) | **NO — MISSING** |
| Sub-industry config (actual: `config/industry_rules.php`) | **YES** (Bangladesh/US + global) |
| `sub_industry` column on institutes | **YES** — `varchar(60)`, nullable, default NULL |
| `sub_industry_id` / `industry_id` columns | **YES** — both nullable, FK to taxonomy tables |
| Index on `(industry, sub_industry)` composite | **NO** (only single-col `industry_id` + `sub_industry_id` indexes) |
| Medical sub-modules | **14** (all `active`, none `coming_soon`) |
| `medical.pharmacy` present | **YES** (registry + routes + permissions + features) |
| Existing healthcare tenants | **4** (2 active hospital/diagnostic with package 4; 1 cancelled; 1 test NULL) |
| Package system | **4 packages** (FREE/BASIC/ADVANCED/PREMIUM) |
| `package_scopes` | **EXISTS** — 3 rows, all `sub_industry_id=NULL` (no pharmacy scope) |
| Pharmacy-specific package | **NO** |
| `ModuleAccessService` sub-industry support (module resolution) | **PARTIAL** — used only for PackageScope lookup, NOT for `industry-modules` config key |
| Sidebar sub-industry awareness | **PARTIAL** — `$subIndustry` read for `diagnostic_center` only; medical gated by module+feature, not sub-industry |
| config `sub-industries` references | **0** |
| Pharmacy tests | **PASS** (`MedicalPharmacyFeatureGateTest` passes) |
| Overall Pharmacy readiness | **PARTIAL** |

**Verdict:** Core pharmacy *module* infrastructure (registry, routes, permissions, features, package_modules on ADVANCED/PREMIUM, sidebar UI) is largely built. What is **missing** for a true *sub-industry package* (Pharmacy tenant) is: `config/sub-industries.php`, sub-industry-aware package resolution in `resolveEnabled()`, pharmacy-scoped `package_scopes`/`package_scoped_modules`, pharmacy-specific package rows, composite index, and backfill of `industry_id`/`sub_industry_id` (currently NULL on healthcare tenants).

---

## 2. Industry-Module Config

- File: `config/industry-modules.php` — **EXISTS** (130 lines)
- Industries defined: **6** (+ `core` key present)
- Total distinct module keys mentioned across all industries: **~18** (purchase, medical, medical.billing, sales, education, education.fees, training_center, training_center.courses, inventory, pos, manufacturing, crm, accounting, finance, reports, notifications, ai, vat)

| Industry | Default | Optional | Disabled |
|----------|---------|----------|----------|
| healthcare | purchase, medical, medical.billing | sales | education, training_center |
| education | education, education.fees | sales, purchase | medical, medical.billing, training_center |
| training_center | training_center, training_center.courses | sales, purchase | medical, medical.billing, education |
| retail | sales, purchase, inventory, pos | (none) | medical, medical.billing, education, training_center |
| manufacturing | sales, purchase, inventory, manufacturing | (none) | medical, medical.billing, education, training_center |
| real_estate | (none) | sales, purchase | medical, medical.billing, education, training_center |

**Core modules (always available in ALL industries):** crm, accounting, finance, reports, notifications, ai, vat

**Note:** No sub-industry keys anywhere in this file. Healthcare treats `medical` + `medical.billing` as defaults; `medical.pharmacy` is NOT in industry config at all (comes via package_modules / parent-gate).

---

## 3. Sub-Industry Config

- File: `config/sub-industries.php` — **MISSING (NO)**
- Actual sub-industry source of truth: **`config/industry_rules.php`** (371 lines)
  - Structure: `global.industries`, `global.sub_industries`, per-country keys (`Bangladesh`, `United States`), `capabilities`
  - Not the `mandatory/default/optional` shape assumed by the audit brief — it is `country → industry → slug => label`
- Global industries: **15** (incl. legacy `transport` alias)
- Global sub-industries defined: education (8), training_center (17) only
- Bangladesh sub-industries: education (8), training_center (17), healthcare (5: hospital, clinic, **pharmacy**, diagnostic_center, nursing_home), IT (3), finance (3), retail (3), manufacturing (3)
- United States sub-industries: education (7), training_center (17), healthcare (3: hospital, clinic, **pharmacy**), IT (3), finance (2)
- DB-backed taxonomy: `industries` table **14 rows**, `sub_industries` table **65 rows**; healthcare (industry_id=3) has **5** subs including pharmacy (id=51, country_id=21)

---

## 4. Institutes Schema

| Column | Type | Nullable | Default |
|--------|------|----------|---------|
| industry | varchar(60) | YES | education |
| industry_id | bigint(20) unsigned | YES | NULL |
| **sub_industry** | **varchar(60)** | **YES** | **NULL** |
| **sub_industry_id** | **bigint(20) unsigned** | **YES** | **NULL** |
| package_id | bigint(20) unsigned | YES | NULL |
| status | enum(pending,active,suspended,expired,cancelled) | NO | pending |

Indexes (relevant): `institutes_industry_id_index (industry_id)`, `institutes_sub_industry_id_index (sub_industry_id)`, `idx_institutes_package (package_id)`.
**No composite index on `(industry, sub_industry)` or `(industry_id, sub_industry_id)`.**
No standalone index on the string `industry` / `sub_industry` columns either.

---

## 5. Institute Distribution

**By industry:**

| Industry | Count |
|----------|-------|
| education | 7 |
| healthcare | 4 |
| training_center | 2 |
| retail | 1 |
| **TOTAL** | **14** |

**By industry + sub_industry:**

| Pair | Count |
|------|-------|
| education.NULL | 7 |
| healthcare.hospital | 2 |
| healthcare.diagnostic_center | 1 |
| healthcare.NULL | 1 |
| retail.supermarket | 1 |
| training_center.professional_training_center | 2 |
| **sub_industry SET** | **6** |
| **sub_industry NULL** | **8** |

**No tenant has `sub_industry = pharmacy` today.**

---

## 6. Module Registry (Medical + Industry Parents)

Industry-matched query total: **33** rows.

**Root industry modules (type=industry, status=active):** education, manufacturing, medical, training_center
*(Note: `healthcare`, `retail`, `real_estate` as root keys do NOT exist — industry key is `medical` for healthcare.)*

**Medical sub-modules (14 — full list):**

| Key | Name | Status | Coming Soon |
|-----|------|--------|-------------|
| medical.opd | OPD | active | NO |
| medical.ipd | IPD | active | NO |
| **medical.pharmacy** | **Pharmacy** | **active** | **NO** |
| medical.laboratory | Laboratory | active | NO |
| medical.billing | Billing | active | NO |
| medical.emergency | Emergency | active | NO |
| medical.radiology | Radiology | active | NO |
| medical.bloodbank | Blood Bank | active | NO |
| medical.physiotherapy | Physiotherapy | active | NO |
| medical.dental | Dental | active | NO |
| medical.vaccination | Vaccination | active | NO |
| medical.ambulance | Ambulance | active | NO |
| medical.diet | Diet & Nutrition | active | NO |
| medical.records | Medical Records | active | NO |

Also present: education (7 subs), training_center (8 subs), root medical/education/training_center/manufacturing.
**module_registry total rows: 59.**

---

## 7. Package System

| ID | Name | Slug | Status | Medical modules in package_modules |
|----|------|------|--------|-------------------------------------|
| 1 | FREE | free | active | 0 |
| 2 | BASIC | basic | active | 0 |
| 3 | ADVANCED | advanced | active | 15 (medical + 14 subs, all ON) |
| 4 | PREMIUM | premium | active | 15 (medical + 14 subs, all ON) |

- **Total packages: 4**
- **Healthcare/Pharmacy-specific packages: NONE** (no pharmacy slug, no industry-scoped package)
- `package_features` medical.*: present only for **package 3 (ADVANCED)** and **package 4 (PREMIUM)** — 12 medical.* feature keys each, including `medical.pharmacy` ON
- FREE/BASIC: 0 medical package_modules and 0 medical package_features

---

## 8. Package-Module Mapping (Medical)

| Package | Module | Enabled |
|---------|--------|---------|
| ADVANCED | medical + medical.ambulance/billing/bloodbank/dental/diet/emergency/ipd/laboratory/opd/**pharmacy**/physiotherapy/radiology/records/vaccination | all ON |
| PREMIUM | medical + same 14 subs incl. **medical.pharmacy** | all ON |

- **`medical.pharmacy` in packages:** YES — ADVANCED + PREMIUM (module level AND feature level)
- FREE/BASIC: no medical modules at all

---

## 9. Existing Healthcare Tenants

| ID | Name | Sub-Industry | industry_id | sub_industry_id | Package | Status |
|----|------|--------------|-------------|-----------------|---------|--------|
| 189 | Central Hospital | hospital | NULL | NULL | 4 (PREMIUM) | active |
| 190 | decent Hospital | hospital | — | — | 4 | cancelled |
| 191 | CENTRAL DIAGNOSTIC CENTER | diagnostic_center | NULL | NULL | 4 (PREMIUM) | active |
| 193 | Smoke 6ab3e4759c6f2 | NULL | — | — | NULL | active |

**Critical gap:** String `sub_industry` is set on real tenants, but **`industry_id` and `sub_industry_id` are NULL** — so `resolveScopedPackage()` (which keys on IDs) falls back to GLOBAL scope for these tenants. Backfill command exists (`BackfillInstituteTaxonomy`) but data not fully applied for healthcare.

**Pharmacy tenants: 0.**

---

## 10. ModuleAccessService

- File: `app/Services/ModuleAccessService.php` (1700+ lines)
- **Sub-industry support in module resolution (`resolveEnabled`): NO**
  - Reads only `$institute->industry` → `getIndustryConfig($industry)` → `config("industry-modules.{$industry}")`
  - Never reads `sub_industry` or `sub_industry_id` for default/optional/disabled module lists
- **Sub-industry support in PackageScope resolution: YES (partial)**
  - `resolveScopedPackage()` / `resolveScopedPackageForPackage()` use `$institute->sub_industry_id` in the 8-step candidate fallback chain (B101 sub-only step included)
  - `getScopedModuleKeys` / `getScopedFeatureKeys` filter `package_scoped_modules` / `package_scoped_features` by `sub_industry_id`
  - BUT both scoped tables are **empty (0 rows)** — dead path today
- Resolution order: core → industry defaults → package modules → optional+overrides → entitlements → remove industry-disabled → industry compatibility → deps → parent gate
- `isIndustryCompatible()` hardcodes map: `education→education`, `medical→healthcare`, `training_center→training_center`; no sub-industry dimension
- **Where sub-industry would fit:**
  1. `resolveEnabled()` — optional: consult `sub_industry`-aware module matrix (new config layer) after industry defaults
  2. `resolveScopedPackage()` — already wired via `sub_industry_id` (needs data backfill + scoped seed rows)
  3. `isIndustryCompatible()` — could veto modules by sub-industry (e.g. pharmacy tenant without OPD)

---

## 11. Sidebar Gating

- File: `resources/views/layouts/institute.blade.php`
- Industry-aware: **PARTIAL**
  - Medical section: `@if ($workspaceAllowedMedical ?? false)` where `$workspaceAllowedMedical = $institute !== null && $moduleService->isEnabled($institute, 'medical')` (AppServiceProvider:591)
  - `$subIndustry = $institute->sub_industry ?? 'hospital'` (line 139) — used only for `$isDiagnostic = $subIndustry === 'diagnostic_center'`
  - Sub-modules iterated via `getMedicalSubModules()`; each gated by `@featureHidden/@featureEnabled/@featureLocked('medical.<key>')` + permission checks
  - **Pharmacy appears at** `@case('medical.pharmacy')` (line 220): Dispense, Stock, Medicines, Expiry Alerts — gated by `@featureEnabled('medical.pharmacy')` + `medical_medicines.view` / `medical_pharmacy.view`
- **No `@if(moduleEnabled(...))` blade directive pattern** — uses view composers + feature directives instead
- **No sub-industry-based sidebar mutation** (except diagnostic_center flag)

---

## 12. Sub-Industry References (Code Search)

| Scope | Count |
|-------|-------|
| Code references (`sub_industry`/`sub-industry` in app/config/database/routes/resources/tests) | **795** across **202 PHP files** |
| Config references (`config('sub-industries...')`) | **0** |
| Config files containing sub_industry | 1 (`config/industry_rules.php` only) |
| Key consumers | InstituteDomain, IndustryRules, InstituteOnboardingController, SubIndustryAdminController, PackageScopeAdminController, ModuleAccessService (scope only), LearningStructureResolver, seeders, migrations, blade onboarding/views |

**No `config/sub-industries.php` and no `config('sub-industries')` usage anywhere.**

---

## 13. Tests

Filter run: `php artisan test --filter="Industry|SubIndustry|Pharmacy"` → **Tests: 12 failed, 135 passed (631 assertions)** (run timed out after completing this summary line).

| Category | Test files (approx) |
|----------|---------------------|
| Files matching industry | **203** |
| Files matching sub_industry / SubIndustry | **131** |
| Files matching pharmacy | **46** |

Notable results:
- **PASS:** `IndustryModuleMatrixTest`, `MedicalPharmacyFeatureGateTest`, `SubIndustryAdminTaxonomyTest`, `ModuleAccessIndustryCompatibilityTest`, `ScopedPackageResolutionTest`, `PackageScopeModelTest`, `IndustryInstitutionDomainTest`
- **FAIL (pre-existing, not audit-caused):** `IndustryRulesTest` (3 asserts — country-scoped industries array drift), `AdminActionsTest`, `AdminNavTest`, `IndustryAdminTaxonomyTest` (DB-backed sub industries empty in test DB), `IndustrySettingsTest`, `MedicalSubModuleAccessTest` (AccountTypeMismatchException), `ModuleSettingsModuleAccessTest`, `Phase10MedicineArchitectureTest` (ModelNotFoundException), + others
- **Pharmacy-specific tests:** `MedicalPharmacyFeatureGateTest` **PASS**; pharmacy flows referenced in DeletionSafety, DgdaToggle, Phase10Medicine (latter fails for unrelated model issue)

---

## 14. Package / Scope Tables

| Table | Exists | Rows |
|-------|--------|------|
| subscription_packages | YES | 4 |
| package_modules | YES | 120 |
| package_features | YES | 85 |
| package_scopes | YES | **3** |
| package_scoped_modules | YES | **0** |
| package_scoped_features | YES | **0** |
| institute_module_overrides | YES | 75 |
| institute_module_entitlements | YES | 0 |
| module_registry | YES | 59 |
| feature_registry | YES | 42 |
| permissions | YES | 343 |
| industries (taxonomy) | YES | 14 |
| sub_industries (taxonomy) | YES | 65 |

**package_scopes content (3 rows):** all `inherit_from_parent=1`, `status=active`, `sub_industry_id=NULL`, `industry_id=NULL`:
1. package FREE × country 21 (Bangladesh)
2. package PREMIUM × country 21
3. package FREE × global

**No pharmacy-specific or industry-scoped package scope exists.**

---

## 15. Feature Registry (Industry Features)

**12 medical.* features (module_key=medical), all active:** ambulance, billing, bloodbank, dental, diet, emergency, laboratory, **pharmacy**, physiotherapy, radiology, records, vaccination
**7 education.* features, 9 training_center.* features** — all active
**pharmacy-specific feature keys beyond medical.pharmacy:** none (only `medical.pharmacy`)

---

## 16. Permissions (Medical)

- **Total `medical.*` permissions: 130**
- **Pharmacy permissions: 9** under `medical.pharmacy.*`:
  - medical.pharmacy.dispense
  - medical.pharmacy.medicines.{create,delete,edit,view}
  - medical.pharmacy.stock.{create,delete,edit,view}
- **Legacy/duplicate underscore style: 5** — `medical_pharmacy.{create,delete,dispense,edit,view}` (also present)
- Plus `medical_medicines.*` used by sidebar check
- **Pharmacy-specific permission coverage: EXISTS (with naming dualism: dot vs underscore)**

---

## 17. Routes (Medical)

- Total routes matching `--name=medical`: **490**
- **Pharmacy routes: 36** (prefix `medical/pharmacy/*`: index, dispense queue/item/batch, medicines CRUD + import/migrate/DGDA, stock CRUD + adjust, expiry-alerts, reports/pharmacy)
- OPD routes: 9 lines matching; IPD: 5 (plus many more nested under appointments/prescriptions/encounters — full medical set is 490)
- Pharmacy route middleware (routes/medical.php:254): `['medical.module:medical.pharmacy', 'feature:medical.pharmacy']`

**Pharmacy routes: EXIST and are feature-gated.**

---

## 18. Gap Analysis

### ✅ What EXISTS
- `config/industry-modules.php` with 6 industries + core
- `config/industry_rules.php` as sub-industry taxonomy (incl. healthcare pharmacy for BD/US)
- `institutes.sub_industry` + `sub_industry_id` + `industry_id` columns
- DB tables `industries` (14) + `sub_industries` (65) with pharmacy row (id=51)
- `medical.pharmacy` in module_registry (active), feature_registry, package_modules (ADV/PREMIUM), package_features (ADV/PREMIUM)
- 36 pharmacy routes, 9+ pharmacy permissions, sidebar pharmacy case with feature+permission gates
- `package_scopes` schema with `sub_industry_id` column + fallback resolver in ModuleAccessService
- Admin CRUD for sub-industries + package scope admin
- Onboarding/registration sub-industry selection
- Tests: IndustryModuleMatrix, SubIndustryAdminTaxonomy, MedicalPharmacyFeatureGate (PASS)

### ⚠️ What's PARTIAL
- Sub-industry config lives in `industry_rules.php`, not `sub-industries.php` as planned
- ModuleAccessService: sub_industry only in PackageScope path, not in industry-modules matrix resolution
- Sidebar: reads `sub_industry` but only for diagnostic_center; no pharmacy-as-primary-tenant UX
- Healthcare tenants: string sub_industry set, **numeric taxonomy IDs NULL** → scope resolver misses industry/sub scopes
- `package_scoped_*` tables empty → scoped package path inert
- Pharmacy permissions dual naming (`medical.pharmacy.*` vs `medical_pharmacy.*`)
- No composite `(industry_id, sub_industry_id)` index
- Some industry/pharmacy-related tests failing (pre-existing)

### ❌ What's MISSING
- `config/sub-industries.php` (file + `config('sub-industries')` usage)
- Sub-industry-keyed entries in `config/industry-modules.php` (or a parallel sub-industry matrix)
- Pharmacy-specific package (slug/name) or pharmacy-scoped `package_scopes` row (`sub_industry_id=51`)
- Seeded `package_scoped_modules` / `package_scoped_features` for pharmacy scope
- Any tenant with `sub_industry=pharmacy` or `sub_industry_id=51`
- Module-level resolution that narrows healthcare modules by sub-industry (e.g. Pharmacy tenant: pharmacy+sales+purchase, no OPD/IPD)
- `industry_id`/`sub_industry_id` backfill completed for healthcare tenants
- Tests asserting Pharmacy-as-sub-industry package behavior (only feature-gate tests exist)

---

## 19. Pharmacy Readiness

| Requirement | Status |
|-------------|--------|
| medical.pharmacy module (registry) | ✅ |
| medical.pharmacy package_modules (ADV/PREMIUM) | ✅ |
| medical.pharmacy package_features (ADV/PREMIUM) | ✅ |
| Pharmacy routes | ✅ (36) |
| Pharmacy permissions | ✅ (9 + legacy 5) |
| Pharmacy feature_registry | ✅ |
| Sidebar pharmacy menu | ✅ |
| sub_industry column | ✅ |
| sub_industries.taxonomy pharmacy row | ✅ (id=51) |
| config/industry_rules healthcare.pharmacy | ✅ (BD + US) |
| sub_industry_id populated on tenants | ❌ (NULL) |
| config/sub-industries.php | ❌ |
| Sub-industry module matrix in industry-modules | ❌ |
| ModuleAccessService sub-industry module resolution | ❌ |
| Pharmacy package / pharmacy package_scope | ❌ |
| package_scoped_modules/features data | ❌ (tables empty) |
| Existing pharmacy tenant | ❌ |
| Pharmacy-specific tests (package/scope) | ❌ partial (feature gate only) |
| **Overall** | **PARTIAL** |

---

## 20. Recommendations for Sub-Industry Build

1. **Do not create `config/sub-industries.php` as a second source of truth** — either rename/document `industry_rules.php` as canonical, or add a thin `config/sub-industries.php` that delegates to `industry_rules` to avoid drift (IndustryRulesFallbackTest already guards config fallback).
2. **Extend `config/industry-modules.php`** (or add `sub-industry-modules` layer) with optional keys like `healthcare.pharmacy` → default/optional/disabled overrides; wire into `resolveEnabled()` after industry defaults (step 2.5), keyed by `$institute->sub_industry`.
3. **Backfill `industry_id`/`sub_industry_id`** via existing `BackfillInstituteTaxonomy` before relying on `resolveScopedPackage()` for healthcare/pharmacy tenants.
4. **Seed pharmacy PackageScope:** `package_scopes (package_id=?, country_id=21, industry_id=3, sub_industry_id=51)` + corresponding `package_scoped_modules`/`package_scoped_features` (or accept GLOBAL fallback if product decision is tier-based only).
5. **Decide package shape:** (a) new subscription package `pharmacy` / `pharmacy_starter`, vs (b) scoped modules on existing ADV/PREMIUM vs (c) industry-modules matrix only. Recommend (b)+(c) first — zero new billing entities, reuses PackageScope chain.
6. **Add composite index** `(industry_id, sub_industry_id)` on institutes if filtering/scoping by pair becomes hot.
7. **Normalize pharmacy permissions** (`medical.pharmacy.*` canonical; deprecate `medical_pharmacy.*`) before role seeding for pharmacy packages.
8. **Define Pharmacy tenant module profile** explicitly, e.g. default: medical, medical.pharmacy, medical.billing?, purchase, inventory, sales; optional: medical.laboratory; disabled: medical.opd, medical.ipd, medical.emergency, education… (product decision).
9. **Sidebar:** today a Pharmacy tenant with only `medical` + `medical.pharmacy` would still show OPD/IPD/etc. gated only by feature flags — either force-lock hospital-only features for `sub_industry=pharmacy` or seed feature denials per sub-industry.
10. **Fix/follow failing industry tests before build** (IndustryRulesTest drift, IndustryAdminTaxonomyTest empty DB, MedicalSubModuleAccessTest) so regressions are detectable.

---

## 21. Files to Create/Modify (list only)

**Create (candidates):**
- `config/sub-industries.php` (or document decision to skip)
- Pharmacy scoped seed: seeder/command for `package_scopes` + `package_scoped_modules` + `package_scoped_features`
- `tests/Feature/PharmacySubIndustryPackageTest.php` (package resolution + sidebar + resolveEnabled by sub_industry)
- Optional migration: composite index `institutes (industry_id, sub_industry_id)`

**Modify (candidates):**
- `config/industry-modules.php` — sub-industry overlay keys
- `app/Services/ModuleAccessService.php` — `resolveEnabled()` / `resolveEnabledWithReasons()` / `isIndustryCompatible()` sub-industry awareness
- `resources/views/layouts/institute.blade.php` — pharmacy-primary sidebar profile
- `app/Providers/AppServiceProvider.php` — if new workspaceAllowed* flags needed
- `database/seeders/MedicalPermissionSeeder.php` — permission naming cleanup if required
- Package admin UI/controllers if new package or scoped modules surface

---

## 22. Raw Evidence (saved under `storage/audit/industry_matrix/`)

| File | Content |
|------|---------|
| `01_industry-modules.php.txt` | Copy of config/industry-modules.php |
| `02_industry_rules.php.txt` | Copy of config/industry_rules.php |
| `03_institutes_columns.txt` | SHOW COLUMNS + indexes institutes |
| `03b_subindustry_index.txt` | hasColumn + index probe |
| `04_industry_distribution.txt` | Industry counts |
| `05_subindustry_distribution.txt` | Industry×sub_industry counts |
| `06_module_registry_industry.txt` | Industry parent + sub modules |
| `07_medical_submodules.txt` | 14 medical sub-modules |
| `08_packages.txt` | 4 packages |
| `09_package_modules_medical.txt` | Medical package_modules |
| `10_healthcare_tenants.txt` | Healthcare institutes |
| `11_ModuleAccessService.php.txt` | Copy of service |
| `15_package_tables.txt` | Table existence + row counts |
| `16_package_scopes.txt` | package_scopes rows |
| `17_features.txt` | feature_registry industry features |
| `18_permissions.txt` | medical.* + pharmacy permissions |
| `19_medical_routes.txt` | route:list --name=medical (490) |
| `21_industry_tables.txt` | industries + sub_industries taxonomy |
| `22_package_detail.txt` | Package feature/scoped detail + healthcare IDs |
| `scripts/*.php` | Read-only SELECT scripts used |

---

## 23. Next Steps

1. Product decision: Pharmacy as (A) sub-industry overlay on healthcare matrix, (B) standalone scoped package, or (C) both.
2. Confirm whether `config/sub-industries.php` is introduced as facade over `industry_rules.php` or abandoned.
3. Backfill `industry_id`/`sub_industry_id` for existing tenants (SELECT verified NULLs above).
4. Seed pharmacy `package_scopes` + scoped modules/features once shape (A/B/C) is chosen.
5. Implement `resolveEnabled()` sub-industry step + tests (`PharmacySubIndustryPackageTest`).
6. Sidebar/UX pass for pharmacy-primary tenant (hide OPD/IPD or lock features).
7. Re-run failing industry tests; add pharmacy package seed to demo/staging only after review.
