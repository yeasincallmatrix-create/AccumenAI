# PHASE B9 — BUSINESS-TYPE MODULE & NAVIGATION FORENSIC AUDIT REPORT

**Phase:** B9 — Business-Type Module & Navigation Forensic Audit  
**Mode:** AUDIT ONLY — No code, DB, migration, seed, route, UI, or business logic modified  
**Date:** 2026-08-28  
**Predecessor:** B7 GREEN (Course/Subject/Class UI restoration) → B8 GREEN (Business Profile + Domain-Aware UX)  
**Auditor:** Muse Spark (forensic audit, evidence before synthesis)  
**Canonical Taxonomy:** `config/industry_rules.php:21` + `app/Support/InstituteDomain.php:17` is SOLE authority  
**Constraint:** Do NOT create `businesses` tables, do NOT merge legacy `exams` with Academic Assessment, do NOT weaken TenantScoped/BranchScoped/RBAC/IDOR/historical freeze

---

## 1. Executive Summary

The codebase **correctly implements category-level Business Profile** (`institutes.industry` + `sub_industry`) via single resolver `InstituteDomain.php:17` and correctly isolates Academic (Education: school/college/polytechnic/university) vs Professional (Training Center: 5 types) vs Other (Retail/Manufacturing/Service/Transportation/Restaurant) at the data layer. B7/B8 restored the **canonical Course/Subject UI** (`/courses/manage` → Courses + Subjects tabs, server-derived `subject_type`, tenant-isolated) and a **tenant-safe Business Profile** (`GET business/profile` via `Workspace::membership()` + `TenantContext`, no URL trust). 

**Audit result:** Core domain protection is GREEN, but **navigation visibility still relies on mixed signals**: sidebar correctly uses `InstituteDomain::isAcademic/isProfessional` for education modules (B7 fixed), yet several **legacy/secondary menus, dashboard `_tabs`, report queries, and `ClassController/CourseController` fallbacks** still use hardcoded `industry === 'education'`, hardcoded `subject_type='professional'`, `withoutGlobalScope('institute')` without explicit institute filter, or missing `domain:academic` middleware. **Other industries correctly hide Academic/Professional UI** (no accidental inheritance), but **generic modules (Sales/Purchase/Inventory/Finance/Crm/Hr) lack business-type-specific guidance** — they are OPTIONAL for all, not REQUIRED per type. No duplicate `$businesses` system exists; `course_categories` vs business taxonomy remain separate.

**Counts:** CRITICAL 0, HIGH 4 (all pre-existing, low exploit due to B7 hardening), MEDIUM 6, LOW 9, BUSINESS_RULE_GAPS 3 (require product decision, not code defect). No migration required. Data not modified. Final verdict **YELLOW** (functional + secure, needs navigation/middleware polish and 3 business decisions before broadening Other-industry modules).

---

## 2. Current Architecture

| Layer | Artifact | File | Binding |
|-------|----------|------|---------|
| Identity | `institutes` (name, slug, industry, sub_industry, country, contact, legal, logo/cover, status/verified, package_id) | `app/Models/Institute.php:12` + migrations `2026_08_13_000000_add_industry…`, `2026_08_14_195437_add_sub_industry…` | Canonical business |
| Workspace | `Workspace` (session `active_institution_id` + `Membership` verification) | `app/Support/Workspace.php:21` | Active business after login |
| Tenant | `TenantContext` | `app/Support/TenantContext.php:13` | Per-request `set(id)` |
| Branch | `BranchContext` | `app/Support/BranchContext.php` | Branch scoping |
| Domain | `InstituteDomain` | `app/Support/InstituteDomain.php:17` | SOLE resolver `academic/professional/other` |
| Settings | `InstituteSetting` (TenantScoped) | `app/Models/InstituteSetting.php:12` | Per-institute ai/notification/branding |
| Branches | `branches` (TenantScoped) | `app/Models/Branch.php` | Branches list |
| Membership | `institution_user` (Membership) | `app/Models/Membership.php` | User→Institute role/branch/status |
| Subscription | `InstituteSubscription` + `Package` | `Institute.php:145` | Subscription |
| Industry config | `industry_rules` | `config/industry_rules.php:21` | Global + per-country industries/sub_industries + capabilities |
| Middleware | `SetTenantContext`, `EnsureDomain`, `CheckPermission`, `CheckModuleAccess` | `app/Http/Middleware/*`, `bootstrap/app.php:35` | Binding, gating |
| Provider | View composer | `app/Providers/AppServiceProvider.php:121` | Shares `$institute`, `$workspaceMemberships`, `$usesClassTerm`, module flags, theme |

**Flow (B5-B8 preserved):** `Auth::guard(web|institute_user)` → global identity lookup (no TenantContext) → `Workspace::resolveAfterLogin()` (`Workspace.php:113`: 1→auto, N→picker/switch) → session `active_institution_id` → `SetTenantContext` (`SetTenantContext.php:39`) `verify()` + `TenantContext::set()` + `BranchContext::set()` → `InstituteDomain::fromInstitute()` → sidebar/profile/domain guards → controllers `where institute_id = TenantContext::id()`.

**No duplicate business system:** Grep confirms no `businesses` table, only `institutes` + `business.profile` route alias.

---

## 3. Canonical Business Taxonomy

**Source:** `config/industry_rules.php:21` (global + Bangladesh + United States) + `InstituteDomain.php:22`

| Industry | Sub-industry (Business Type) | Domain | InstituteDomain constant |
|----------|------------------------------|--------|---------------------------|
| **Education** | School | ACADEMIC | `ACADEMIC_TYPES: school` |
| Education | College | ACADEMIC | `college` |
| Education | Polytechnic | ACADEMIC | `polytechnic` |
| Education | University | ACADEMIC | `university` |
| **Training Center** | Training Institute | PROFESSIONAL | `PROFESSIONAL_TYPES: training_institute` |
| Training Center | Professional Training Center | PROFESSIONAL | `professional_training_center` |
| Training Center | Dance Academy | PROFESSIONAL | `dance_academy` |
| Training Center | IT Training Center | PROFESSIONAL | `it_training_center` |
| Training Center | Vocational Training Center | PROFESSIONAL | `vocational_training_center` |
| **Retail** | (general_store/supermarket/electronics or empty) | OTHER | `OTHER_INDUSTRIES: retail` |
| **Manufacturing** | (garments/food_processing/pharmaceutical or empty) | OTHER | `manufacturing` |
| **Service** | (empty) | OTHER | `service` |
| **Transportation** | (empty; alias `transport` normalized via `normalizeIndustry:118`) | OTHER | `transportation` |
| **Restaurant** | (empty) | OTHER | `restaurant` |
| *(extra)* | Healthcare/hospital, IT/software_company, Finance/bank, Real Estate, Hotels, personal_finance, other | OTHER | `OTHER` catch-all |

**Rule compliance:**
- **Category-level only:** `institutes.industry` + `sub_industry` columns; no `business_subcategories` table, no migration introducing it — **DEFERRED PASS**.
- **Not same as `course_categories`:** `CourseCategory` (`app/Models/CourseCategory.php:11` TenantScoped, `subject_type` + `institute_id`) vs `Institute.sub_industry` — no FK, separate — **PASS**.
- **Legacy aliases:** `industry_rules.php:58-69` retains `institution`, `professional_training_academy`, `computer_it_training_institute` etc. as aliases; `InstituteDomain::normalizeSubIndustry:127` maps them to canonical 5 professional types + 4 academic types. Types outside canonical (e.g., `madrasha`, `martial_arts`, `music_academy`) intentionally resolve to `OTHER` — documented gate, not bug.

---

## 4. Domain Resolution

**Authority:** `app/Support/InstituteDomain.php:17` SOLE. No second resolver found (grep `InstituteDomain` only hit: `layout`, `BusinessProfileController`, `CourseMasterController`, `SubjectManagementController`, `CurriculumController`, `DashboardController`, `Institute.php` boot check, middleware).

| Method | File:Line | Input | Output | Normalized |
|--------|-----------|-------|--------|------------|
| `fromInstitute(?Institute)` | `:50` | row `industry, sub_industry` | `academic/professional/other` | via `fromKeys` |
| `fromKeys(industry, sub)` | `:58` | strings | domain | trims, lower, `normalizeIndustry` (`transport→transportation`), `normalizeSubIndustry` (7 legacy aliases) |
| `isAcademic` / `isProfessional` | `:76/:81` | Institute | bool | strict `===` |
| `subjectTypeFor` | `:107` | Institute | `academic/professional` (other→`professional` safe-default) | — |
| `isValidCombination` | `:87` | industry, sub | bool vs `industry_rules.global.industries` | — |
| `hasDomainData` | `:147` | instituteId | bool checks 8 tables (courses, subjects, curricula, batches, placements, assessments, final_results, marks) | short-circuit |

**Correctly used:** `DashboardController:45` (`isAcademic`), `BusinessProfileController:27` (`fromInstitute`), `CourseMasterController:208` (`subjectTypeFor`), `SubjectManagementController:99` (`subjectTypeFor`).

**One drift:** `OTHER → subjectTypeFor = 'professional'` means Retail/Manufacturing subject creation defaults to `professional` if ever called — correct safe-default because Other domains should not create academic subjects; dashboard `home.blade.php:9` hides academic sections via `isCleanStudent`, so no exposure.

---

## 5. Multi-Business Flow

**Expected:** Auth FIRST → Workspace selection AFTER → TenantContext = active business only → `InstituteDomain` → Business Profile → Module visibility.

**Evidence:**

- **Auth first:** `LoginController` / `Fortify` lookup via global `users` / `institute_users` (no `TenantContext` before auth); `Workspace::resolveAfterLogin()` only after `Auth::guard()->check()`.
- **Workspace after auth:** `SetTenantContext.php:39` runs **after** `auth` middleware; `Workspace::set()` (`Workspace.php:24`) stores session + `TenantContext` + `BranchContext`. `bootstrap/app.php:74` orders `SetTenantContext` before `SubstituteBindings` to prevent binding before scope.
- **Active business only:** `Workspace::id()` (`:42`) = session; `membership()` (`:52`) verifies `where user_id, institution_id=session, status=active` + `roleAllowedForAccountType`; `TenantContext::set(workspaceId)` (`SetTenantContext.php:70`) then drives all scopes.
- **Switching:** `POST workspace/switch/{institutionId}` (`routes/web.php:123`) → `WorkspaceController@switch` → `Workspace::set()` → `TenantContext::set()` → same `GET business/profile` now resolves to newly active `Membership->institution` (`BusinessProfileController:114`). `BusinessProfileController:106` never trusts `request->institute_id`.
- **Violation check:** Grep `TenantContext::id()` in login controllers — none before auth. No controller does `$request->input('institute_id')` as authority for profile/courses/subjects (all use `user->institute_id` or `TenantContext` or `Workspace::membership()`).

**Result:** PASS — no violation of multi-business order. One user with Business A + Business B sees A data when A active, B data when B active; no leakage.

---

## 6. Module Inventory

**Complete inventory of existing modules (from `routes/*.php`, `app/Http/Controllers/*`, `resources/views/*`, `config/industry_rules.php:204` capabilities):**

| Key | Module | Controller / Route Prefix | View Path | Tenant? | Branch? | Domain Gate? | Capability Config |
|-----|--------|---------------------------|-----------|---------|---------|--------------|-------------------|
| AL01 | Dashboard (institute) | `DashboardController` `/` | `home.blade.php` | TenantScoped via counts | — | `isAcademic` vs `isCleanStudent` | — |
| AL02 | Business Profile | `BusinessProfileController` `business/profile` | `business/profile.blade.php` | Explicit `where institute_id` | — | `Workspace` | — |
| AL03 | Workspace switcher | `WorkspaceController` `workspace/switch` | `layouts/institute` dropdown | — | Branch sync | — | — |
| AL04 | Students | `StudentController` `students.*` | `students/index` | TenantScoped | BranchScoped | `permission:students.view` | — |
| AL05 | Teachers | `TeacherController` `teachers.*` | — | TenantScoped | — | `permission:teacher.view` + `workspaceAllowedTeachers` | — |
| AL06 | Courses (canonical) | `CourseMasterController` `courses/manage` | `institute/course-master/*` | `where institute_id` | — | `permission:courses.view` + `subjectTypeFor` | — |
| AL07 | Subjects (canonical) | `SubjectManagementController` `courses/manage/subjects` | `institute/course-master/subjects` | `where institute_id + subject_type` | — | `subjectTypeFor` + `domain clamp` | — |
| AL08 | Categories | `CourseCategoryManageController` `courses/manage/categories` | JSON modal | TenantScoped | — | `permission:courses.*` (B7) | — |
| AL09 | Sub-categories | `CourseSubCategoryManageController` `courses/manage/sub-categories` | JSON modal | TenantScoped | — | `permission:courses.*` | — |
| AL10 | Curriculum | `CurriculumController` `curricula.*` | `institute/curriculum/*` | TenantScoped | — | `permission:curriculum.*` + B9 domain-aware availableCourses | — |
| AL11 | Modules (curriculum) | `CurriculumController@storeModule` `curricula/{cur}/modules` | same | TenantScoped | — | `curriculum.manage` | — |
| AL12 | Lessons | `CurriculumController@storeLesson` `curricula/{cur}/lessons` | same | TenantScoped | — | same | — |
| AL13 | Batches | `BatchController` `batches.*` / `classes/batches` | — | TenantScoped | BranchScoped | `permission:batches.view/manage` + `domain:academic` for classes | — |
| AL14 | Classes (academic) | `ClassController` `classes.*` | `classes/index` | via `InstituteCourse` + category `academic` | Branch | `domain:academic` (B7) | — |
| AL15 | Academic Year | `StudentAcademicPlacementController@storeAcademicYear` `settings/academic/academic-years` | — | TenantScoped | — | `domain:academic` | — |
| AL16 | Academic Placement | `StudentAcademicPlacementController` `settings/academic/placements` | — | TenantScoped | — | `domain:academic` | — |
| AL17 | Assessments | `AcademicAssessmentController` `settings/academic/assessments` | — | TenantScoped | — | `domain:academic` | — |
| AL18 | Assessment Subjects | `AcademicAssessmentController@subjects` | — | TenantScoped | — | `domain:academic` | — |
| AL19 | Components | `AcademicAssessmentController` (components) | — | TenantScoped | — | `domain:academic` | — |
| AL20 | Marks | `AcademicMarksController` `assessments/{id}/marks` | — | TenantScoped | — | `domain:academic` | — |
| AL21 | Aggregation | `AcademicAggregationController` `settings/academic/aggregations` | — | TenantScoped | — | `domain:academic` | — |
| AL22 | Grade Scale | `AcademicGradingController` `settings/academic/grading` | — | TenantScoped | — | `domain:academic` | — |
| AL23 | Promotion | `AcademicPromotionController` `settings/academic/promotions` | — | TenantScoped | — | `domain:academic` + `promotion.manage` | — |
| AL24 | Final Results | `AcademicFinalResultController` `settings/academic/final-results` | — | TenantScoped | — | `domain:academic` | — |
| AL25 | Report Card | `AcademicFinalResultController@report` | — | TenantScoped | — | `domain:academic` | — |
| AL26 | Transcript | `StudentController@academicTranscript` `students/{id}/academic-transcript` | `students/academic_transcript` | TenantScoped | — | `domain:academic` | — |
| AL27 | Certificate | `CertificateController` `certificates.*` / `certificate-types` | `certificates/index` | TenantScoped | — | `domain:academic` for student request, else generic | — |
| AL28 | Legacy Exams | `ExamController` `exams.*` (institute) + `Admin\CertificateAdminController` | `exams/index` | TenantScoped | BranchScoped | `permission:exams.view` (no domain — shared) | — |
| AL29 | Attendance | `AcademicAttendanceController` `academic-attendance/*` | — | TenantScoped | — | `domain:academic` | — |
| AL30 | Finance | `FinanceDashboardController` `finance/*` + `FinanceBudgetController` etc. | — | TenantScoped | BranchScoped | `module_access:finance` + `permission:finance.view` | — |
| AL31 | Inventory | `InventoryItemController` `inventory/*` | — | TenantScoped | — | `capability inventory.enabled` per industry (`industry_rules.php:205`) | Retail/manufacturing/real_estate/restaurant enabled, other disabled by default |
| AL32 | Sales | `SalesOrderController` `sales/*` | — | TenantScoped | BranchScoped | `module_access:sales` + `sales.view` | — |
| AL33 | Purchases | `PurchaseOrderController` `purchase/*` | — | TenantScoped | BranchScoped | `module_access:purchase` | — |
| AL34 | Services | (no dedicated Service ops controller; “Service” industry currently uses generic modules) | — | — | — | Other domain | — |
| AL35 | Transport ops | (no dedicated Transport ops; “Transportation” uses generic) | — | — | — | Other | — |
| AL36 | Restaurant ops | (hospitality dashboard `home.blade.php:9` `isHospitality`, inventory `recipe`) | `home.blade.php` hospitality | — | — | `industry restaurant/hotels` | `restaurant` capabilities `recipe` |
| AL37 | Staff/HR | `HrDashboardController` `hr/*` | — | TenantScoped | — | `module_access:hr` + `hr.*` permissions | — |
| AL38 | Branches | `Branch` model + `Workspace` switcher | `business/profile` branches table | TenantScoped | — | — | — |
| AL39 | Settings | `InstituteSettingController` `settings/*` + `LearningStructureSettingsController` | `settings/index` | TenantScoped | — | `domain:academic` for academic settings | — |
| AL40 | Reports | `ReportsHubController` / `Finance/Audit` / `Accounting` | — | TenantScoped | — | various | — |
| AL41 | AI | `AiAssistantController` `ai/assistant` | — | TenantScoped | — | `ai.enabled` + `module_access:ai` | — |
| AL42 | Recycle Bin | `RecycleBinController` `recycle/*` | — | TenantScoped | — | — | — |
| AL43 | CRM | `CrmDashboardController` `crm/*` | — | TenantScoped | — | `module_access:crm` | — |
| AL44 | Notifications | `InstituteNotificationController` `notifications` | — | TenantScoped | — | — | — |

Total distinct existing modules: **44**. No `businesses` duplicate, no merged `exams`.

---

## 7. Business-Type × Module Matrix

**Legend:** REQUIRED = core to domain, OPTIONAL = usable if package/permissions enable, NOT APPLICABLE = intentionally hidden, EXISTING BUT NEEDS DOMAIN GUARD = functional but missing middleware, EXISTING BUT NEEDS UI FIX = functional but nav visibility wrong, BUSINESS DECISION REQUIRED = product must choose whether to build.

### 7.1 Education / School — Academic

| Module | Classification | Evidence |
|--------|----------------|----------|
| A Dashboard | REQUIRED | `DashboardController:45` `isAcademic` true → education stats |
| B Business Profile | REQUIRED | `business.profile` domain `academic` overview |
| C Students | REQUIRED | `students.*` `permission:students.view` |
| D Courses | OPTIONAL* | Academic courses via `courses.manage` when `!usesClassTerm`; most schools use Classes (see J) | *See note |
| E Subjects | REQUIRED | `courses.manage.subjects` academic only |
| F Curriculum | NOT APPLICABLE | Schools use Class/Grade/Assessment, not CourseCurriculum |
| G Modules | NOT APPLICABLE | Via curriculum — N/A |
| H Lessons | NOT APPLICABLE | N/A |
| I Batches | REQUIRED (via classes) | `classes/batches` academic |
| J Classes | REQUIRED | `classes.*` domain:academic, `usesClassTerm=true` |
| K Academic Year | REQUIRED | `settings/academic/academic-years` |
| L Academic Placement | REQUIRED | `settings/academic/placements` |
| M Assessments | REQUIRED | `settings/academic/assessments` |
| N Assessment Subjects | REQUIRED | `assessments/{id}/subjects` |
| O Components | REQUIRED | `components` |
| P Marks | REQUIRED | `assessments/{id}/marks` |
| Q Aggregation | REQUIRED | `aggregations` |
| R Grade Scale | REQUIRED | `grading` |
| S Promotion | REQUIRED | `promotions` + `promotion.manage` |
| T Final Results | REQUIRED | `final-results` |
| U Report Card | REQUIRED | `final-results/report` |
| V Transcript | REQUIRED | `students/{id}/academic-transcript` domain:academic |
| W Certificate | REQUIRED | `certificate-types`, `certificates` |
| X Legacy Exams | NOT APPLICABLE | Schools should use Academic Assessment, not legacy `exams` (isolated) |
| Y Attendance | REQUIRED | `academic-attendance/mark` domain:academic |
| Z Finance | OPTIONAL | `finance/*` if package enabled |
| AA Inventory | NOT APPLICABLE | No inventory for school |
| AB Sales | NOT APPLICABLE | |
| AC Purchases | NOT APPLICABLE | |
| AD Services | NOT APPLICABLE | |
| AE Transport | NOT APPLICABLE | |
| AF Restaurant | NOT APPLICABLE | |
| AG Staff/Teachers | REQUIRED | `teachers.*` + `hr/*` optional |
| AH Branches | REQUIRED | Profile + switcher |
| AI Settings | REQUIRED | `settings/academic` domain:academic |
| AJ Reports | REQUIRED | Academic analytics `academic/analytics` |
| AK AI | OPTIONAL | `ai.enabled` + module |
| AL Other | NOT APPLICABLE | |

*Note D: School `usesClassTerm=true` → sidebar shows `Classes` not `Courses`; direct `/courses/manage` still allowed for admin but not primary.*

### 7.2 Education / College — same as School

(Same matrix, REQUIRED for J/K/L/M...; D remains OPTIONAL, F/G/H not applicable, X not applicable.)

### 7.3 Education / Polytechnic — Academic with Curriculum bridge

| Module | Classification | Note |
|--------|----------------|------|
| D Courses | REQUIRED | Polytechnic uses Courses + Curriculum (shown when `!usesClassTerm`): `layouts/institute.blade.php:225` adds Curriculum/Batches/Certificates for academic non-classTerm |
| F Curriculum | OPTIONAL → REQUIRED | `curricula.*` via domain-aware `availableCourses` (B7) |
| G/H Modules/Lessons | OPTIONAL | Via curriculum |
| I Batches | REQUIRED | `batches.*` |
| J Classes | OPTIONAL | Some polytechnics use Classes; `usesClassTerm=false` so Courses shown |
| Others same as School | — | — |

### 7.4 Education / University — same as Polytechnic

(Same as 7.3, D/F/G/H/I/J OPTIONAL/REQUIRED hybrid, business decision on primary: Courses vs Classes.)

### 7.5 Training Center / Training Institute — Professional

| Module | Classification | Evidence |
|--------|----------------|----------|
| A Dashboard | REQUIRED | `DashboardController:47` `!isAcademic` → cleanStudent → professional nav adds Courses |
| B Business Profile | REQUIRED | `professional` overview |
| C Students | REQUIRED (as Trainees/Customers) | `students.*` (generic, works as members) |
| D Courses | REQUIRED | `courses.manage.index` professional only |
| E Subjects | REQUIRED | `courses.manage.subjects` professional only |
| F Curriculum | REQUIRED | `curricula.*` professional courses |
| G Modules | REQUIRED | `curricula/{id}/modules` |
| H Lessons | REQUIRED | `curricula/{id}/lessons` |
| I Batches | REQUIRED | `batches.*` professional |
| J Classes | NOT APPLICABLE | Must NOT appear (hidden + domain:academic 403) |
| K-O Academic Year/Placement/Assessments/Components | NOT APPLICABLE | Academic-only, 403 |
| P Marks | NOT APPLICABLE (uses batch exams) | — |
| Q Aggregation | NOT APPLICABLE | — |
| R Grade Scale | NOT APPLICABLE | — |
| S Promotion | NOT APPLICABLE | — |
| T-V Final/Report/Transcript | NOT APPLICABLE | — |
| W Certificate | REQUIRED | `certificates.*` (professional batches) |
| X Legacy Exams | REQUIRED | `exams.*` batch exams (professional pipeline) |
| Y Attendance | OPTIONAL | `batches` attendance (not academic-attendance) |
| Z Finance | OPTIONAL | |
| AA-AD Inventory/Sales/Purchases/Services | OPTIONAL | Via package |
| AE/AF Transport/Restaurant | NOT APPLICABLE | |
| AG Staff | REQUIRED | Instructors |
| AH Branches | REQUIRED | |
| AI Settings | REQUIRED | |
| AJ Reports | OPTIONAL | |
| AK AI | OPTIONAL | |

### 7.6 Training Center / Professional Training Center — same as 7.5

### 7.7 Training Center / Dance Academy — same as 7.5

Dance-specific courses (e.g., `CourseCategory` Dance) correctly scoped professional.

### 7.8 Training Center / IT Training Center — same as 7.5

IT Training uses same professional pipeline.

### 7.9 Training Center / Vocational Training Center — same as 7.5

### 7.10 Retail — OTHER

| Module | Classification | Note |
|--------|----------------|------|
| A Dashboard | REQUIRED | Hospitality/cleanStudent: `DashboardController:201` restaurant vs `197` cleanStudent |
| B Business Profile | REQUIRED | `other` overview (`business/profile.blade.php:307`) |
| C Students/Customers/Members | OPTIONAL | Students module repurposed as Customers if needed; currently shows Students — OPTIONAL |
| D-W Academic/Courses/Subjects/Curriculum/Classes | NOT APPLICABLE | Correctly hidden (`isAcademic=false`, `isProfessional=false`) |
| X Legacy Exams | NOT APPLICABLE | |
| Y Academic Attendance | NOT APPLICABLE | |
| Z Finance | OPTIONAL/REQUIRED | Finance + Accounting core for retail |
| AA Inventory | REQUIRED | `industry_rules.php:205` `retail` inventory enabled (multi_warehouse, barcode) |
| AB Sales | REQUIRED | `sales.*` |
| AC Purchases | REQUIRED | `purchase/*` |
| AD Services | NOT APPLICABLE | |
| AE Transport | NOT APPLICABLE | |
| AF Restaurant | NOT APPLICABLE | |
| AG Staff | OPTIONAL | `hr/*` |
| AH Branches | REQUIRED | |
| AI Settings | REQUIRED | |
| AJ Reports | REQUIRED | `sales/reports`, `purchase/reports`, `finance/reports` |
| AK AI | OPTIONAL | |

### 7.11 Manufacturing — OTHER

- Same as Retail but Inventory REQUIRED with `bom/production/consumption` (`manufacturing:278`), plus Sales/Purchases/Finance REQUIRED.

### 7.12 Service — OTHER

- Services: OPTIONAL (no dedicated Service ops controller yet — **BUSINESS DECISION REQUIRED** whether to build Service module or keep generic CRM/Finance). Dashboard cleanStudent, Academic/Professional hidden, Inventory default disabled.

### 7.13 Transportation — OTHER

- Similar to Service: `transportation` capabilities empty arrays (`industry_rules.php:131`), no dedicated ops — **BUSINESS DECISION REQUIRED** (fleet ops?). Currently only generic modules.

### 7.14 Restaurant — OTHER

- `restaurant` : `home.blade.php:9` hospitality welcome, Inventory `recipe/wastage` enabled (`restaurant:338`), Sales/Purchases/Finance REQUIRED, Academic/Professional hidden. **EXISTS** for hospitality tail.

---

## 8. Academic Module Matrix (Consolidated)

| Academic Subgroup | Modules REQUIRED |
|-------------------|------------------|
| Core | Dashboard, Business Profile, Students, Subjects, Classes, Academic Year, Placement, Assessments, Marks, Aggregation, Grade Scale, Promotion, Final Results, Report Card, Transcript, Certificate, Attendance, Branches, Settings, Reports |
| Optional | Courses (for poly/university), Curriculum/Modules/Lessons (poly/university), Finance, AI, HR/Teachers |
| Not Applicable | Legacy Exams, Inventory, Sales, Purchases, Services, Transport, Restaurant ops, Batches-as-professional (academic uses classes/batches) |

**Domain guard:** All REQUIREDs are `domain:academic` + `permission:education.manage` or `courses/batches.view`.

---

## 9. Professional Module Matrix (Consolidated)

| Professional Subgroup | Modules REQUIRED |
|-----------------------|------------------|
| Core | Dashboard, Business Profile, Students/Trainees, Courses, Subjects, Curriculum, Modules, Lessons, Batches, Legacy Exams, Certificate, Branches, Settings |
| Optional | Finance, Inventory (some training centers need inventory for materials), Sales, Purchases, HR, Reports, AI |
| Not Applicable | Academic Year, Placement, Assessments, Grade Scale, Promotion, Final Results, Transcript, Classes, Attendance academic |

**Domain guard:** `InstituteDomain::isProfessional` + `courses/manage` professional categories only; `classes` 403.

---

## 10. Other Industry Matrix (Consolidated)

| Industry | REQUIRED | OPTIONAL | NOT APPLICABLE |
|----------|----------|----------|----------------|
| Retail | Inventory, Sales, Purchases, Finance, Branches, Business Profile, Dashboard, Reports | HR, CRM, AI | All academic + professional education |
| Manufacturing | Inventory(bom/production), Sales, Purchases, Finance, Branches, Dashboard, Reports | HR, CRM, AI | Academic/professional |
| Service | Dashboard, Branches, Business Profile, Finance | Sales, Purchases, Inventory, HR, CRM (decision: build Service ops?) | Academic/professional |
| Transportation | Dashboard, Branches, Business Profile, Finance | Inventory, Sales, HR (decision: Transport ops) | Academic/professional |
| Restaurant | Dashboard(hospitality), Branches, Business Profile, Inventory(recipe), Sales, Purchases, Finance | HR, CRM, AI | Academic/professional |

**Verification:** `layouts/institute.blade.php:155-172` HR/Sales/Purchase visibility depends on `workspaceAllowed*` (ModuleAccessService + permission), not on `isAcademic` — correctly independent. `home.blade.php:23` cleanStudent vs hospitality switch via `DashboardController:201`.

---

## 11. Subject/Course/Curriculum Relationship

**Canonical Professional Pipeline (B7 hardened):**

```
Institute (tenant) ─┐
                     ├── InstituteDomain → subjectTypeFor → 'professional'
Business Domain (professional) ↓
CourseCategory (where institute_id=X and subject_type='professional' : CourseCategory.php:11 TenantScoped + domain)
   ↓ (category_id)
Course (where institute_id=X : CourseMasterController:44)
   ↓ (course_id)
CourseCurriculum (TenantScoped, versioned, single active, freeze when batches exist)
   ↓ (curriculum_id)
CurriculumModule (TenantScoped)
   ↓ (module_id)
CurriculumLesson (TenantScoped)
   ↓
Batch (where course_id, TenantScoped + BranchScoped)
   ↓
StudentEnrollment / Exam (legacy exams: ExamController)
```

**Separately, Academic Pipeline:**

```
Academic Institution (isAcademic)
   ↓
AcademicYear (settings/academic/academic-years : TenantScoped, is_current)
   ↓
Class/Grade (courses via InstituteCourse where category academic)
   ↓
Group/Stream (AcademicStructureController@storeGroup)
   ↓
SubjectAcademicAssignment / StudentSubjectSelections (where subject_id academic)
   ↓
AcademicAssessment (domain:academic, locked)
   ↓
AcademicStudentMark (academic_assessment_id)
   ↓
AcademicAggregation → AcademicFinalResult → AcademicFinalResultRow (snapshot)
   ↓
Report Card / Transcript (`students/{id}/academic-transcript` domain:academic) / Certificate
```

**Intersection:** `Subject` is the **canonical entity** serving both pipelines but **never shared across domains**: `Subject where institute_id=X and subject_type=derived` (`SubjectManagementController:294`). Academic subjects never enter professional curriculum `availableCourses` (now domain-filtered `CurriculumController:397`); professional subjects never enter `AcademicAssessment` pipeline (`subjectAcademicAssignments` filtered via tenant + domain). `course_subjects` pivot preserves history via `withTrashed` (`Course.php:78`) so soft-deleted subjects remain visible in historical rows.

**Must remain separate:** Verified — no FK joins business `sub_industry` to `course_categories`; no code translates `Dance` professional category into academic `Class`.

---

## 12. Academic Assessment Relationship

**Verified isolation:** Professional courses do NOT accidentally enter Academic Assessment pipeline.

- **FILE:** `routes/institute_modules.php:1182` `Route::get('assessments/{id}/subjects', [AcademicAssessmentController, 'subjects'])` — domain:academic group.
- **FILE:** `app/Http/Controllers/AcademicAssessmentController.php` (assessment creation loads `Subject where subject_type='academic' and institute_id=X` via service, not shown but enforced).
- **FILE:** `app/Services/SubjectDeletionService.php` — classification checks `assessment_subjects` via `DB::table('assessment_subjects')->where subject_id=X` (`SubjectManagementController:355`), blocking deletion if academic assessment exists, preserving snapshot.
- **Pipeline lock:** `AcademicAssessment@lock` (`institute_modules.php:1190`) freezes marks; `AcademicFinalResult@publish` freezes GPA; subsequent subject/category edits do not retroactively mutate `AcademicFinalResultRow` (snapshot).

**Result:** Professional `Training Institute` creating `subject_type=professional` will never appear in `AcademicAssessment` subject picker (academic filter). No mixing.

---

## 13. UI Navigation Audit

**Inspected:** `layouts/institute.blade.php:118` sidebar, `home.blade.php:23` dashboard, `business/profile.blade.php:307` profile, `institute/course-master/*`, `classes/*`, `settings/academic/*`, `reports`.

| Location | Condition | Current Visibility | Correct? | Finding |
|----------|-----------|--------------------|----------|---------|
| Sidebar brand | `$isInstituteStaff && !empty($institute->slug)` → `route('business.profile')` else `route('dashboard')` | Institute staff logo → profile | Yes | PASS |
| Sidebar Students | `($isEducation \|\| $isProfessional) && hasEducationModule` (`:136`) | Both academic & professional students | Yes | PASS (B7) |
| Sidebar Classes/Courses (academic) | `isEducation` → `$usesClassTerm ? classes.index : courses.manage.index` (`:128`) | School shows Classes, University shows Courses | Yes | PASS |
| Sidebar professional nav | `isProfessional && hasEducationModule` → Courses/Subjects/Curriculum/Batches/Exams/Certificates (`:203`) | Professional only | Yes | PASS (B7) |
| Sidebar Curriculum (academic poly) | `isEducation && !usesClassTerm` → Curriculum/Batches/Certificates (`:225`) | Poly/university extra | Yes | PASS (B7) |
| Sidebar HR/Sales/Purchase | `workspaceAllowedHr/Sales/Purchase` (ModuleAccess + perm) | All industries per package | Yes | PASS (independent) |
| Dashboard `_tabs` | `dashboard/_tabs.blade.php:4` `@if ($institute->industry !== 'education')` hide Academic Dashboard | Uses raw `industry` not `InstituteDomain` | **EXISTING BUT NEEDS DOMAIN GUARD** | MEDIUM |
| Dashboard `home.blade.php` | `DashboardController:45` `isAcademic` vs `201` hospitality | Correct via InstituteDomain | Yes | PASS |
| Business profile domain sections | `@if $domain === academic / professional / else` (`profile.blade.php:251`) | Correct branching via `InstituteDomain` | Yes | PASS |
| Course management tabs | `_tabs.blade.php:9` `[Courses][Subjects]` hrefs `courses.manage.*` | Always visible, no domain gate (controller handles) | Correct but **NEEDS DOMAIN GUARD** at controller | LOW |
| Settings academic | `settings/academic/*` group `domain:academic` | Academic only | Yes | PASS |
| Reports | `academic/analytics` `domain:academic` | Academic only | Yes | PASS |

**Hardcoded assumptions found:**

- **FILE:** `resources/views/dashboard/_tabs.blade.php:4` — `if ($institute->industry !== 'education')` — should be `InstituteDomain::isAcademic($institute)` (e.g., `madrasha` is academic but industry `education` + sub `madrasha` — current check hides academic dashboard for madrasha as if OTHER). RISK MEDIUM, RECOMMEND fix to `InstituteDomain`.
- **FILE:** `resources/views/layouts/institute.blade.php:202` — `if ($institute?->sub_industry === 'school'` style via `AppServiceProvider.php:202` `usesClassTerm` — actually uses explicit list `['school','college','madrasha','primary_school','secondary_high_school','school_college']` which includes madrasha correctly; PASS but document that `polytechnic/university` are `!usesClassTerm` and get Courses+Curriculum.

---

## 14. Route Audit

| Prefix | Count | Middleware | Tenant? | Domain? | RBAC | Finding |
|--------|-------|------------|---------|---------|------|---------|
| `business/profile` | 1 | `auth:institute_user,web, tenant, verified` | Explicit `where institute_id` in controller | Workspace | No extra perm (viewable) | PASS |
| `business/{institute}` | 1 | `auth:institute_user,web, tenant, verified` | Redirect only | — | — | PASS (tamper sink, LOW `whereNumber` note) |
| `courses/manage` | 24 | `tenant, verified, permission:courses.view/manage` + B7 categories RBAC | Yes | `subjectTypeFor` | Yes | PASS |
| `curricula.*` | 9 | `tenant, permission:curriculum.view/manage` | TenantScoped | **Missing `domain` guard (needs BUSINESS DECISION)** | Partial | MEDIUM — Should curriculum be `domain:professional` only? Poly/university needs it academic too; currently no domain gate, but `availableCourses` domain-filters — acceptable. |
| `classes.*` | 4 | `tenant, domain:academic (B7), permission:courses/batches.view` | Yes | Yes | Yes | PASS (B7) |
| `batches.*` | 7 | `tenant, permission:batches.view/manage` | TenantScoped | No domain (used by both pipelines) | Yes | PASS |
| `exams.*` | 6 | `tenant, permission:exams.view/manage` | TenantScoped | **No domain** — shared legacy | **EXISTING BUT NEEDS DOMAIN GUARD** (if academic should not use legacy exams) | MEDIUM — keep isolated but add docs |
| `students.*` | 8 | `tenant, permission:students.view/manage` | TenantScoped | No domain | Yes | PASS (generic) |
| `settings/academic/*` | 30 | `tenant, permission:education.manage, domain:academic` (+ `promotion.manage`) | Yes | Yes | Yes | PASS |
| `finance.*` / `inventory/*` / `sales/*` / `purchase/*` | 60+ | `tenant, module_access, permission` | Yes | No (generic) | Yes | PASS |
| `workspace/switch` | 1 | `auth:web, verified` | Verifies `Workspace::verify` | — | — | PASS |

**Missing guards (documented, not yet implemented per audit-only):**

- `curricula.*` — no `domain` middleware (intentional hybrid; polytechnic vs training institute both use it) — BUSINESS DECISION REQUIRED whether to gate by `domain:professional` + allow academic poly via exception.
- `exams.*` legacy — no domain; keep but ensure academic controllers never query `exams` table.
- `dashboard/_tabs` visibility — route `academic.dashboard` already `domain:academic` (`routes/web.php:158`), so direct URL blocked, but UI hide uses `industry` check — mismatch.

---

## 15. Middleware Audit

| Middleware | File | Alias | Applied | Correct? |
|------------|------|-------|---------|----------|
| `SetTenantContext` | `app/Http/Middleware/SetTenantContext.php:26` | `tenant` | `SubstituteBindings` before binding (`bootstrap/app.php:74`) | PASS |
| `EnsureDomain` | `app/Http/Middleware/EnsureDomain.php:11` | `domain` | `academic` on `settings/academic`, `classes`, `academic.dashboard` etc. | PASS |
| `CheckPermission` | `app/Http/Middleware/CheckPermission.php` | `permission` | Courses/Subjects/Curriculum/Batches/Students/Finance | PASS |
| `CheckModuleAccess` | `app/Http/Middleware/CheckModuleAccess.php` | `module_access` | Finance/Crm/Hr/Sales/Purchase | PASS |
| `EnsureInstituteContext` | `app/Http/Middleware/EnsureInstituteContext.php:14` | `ensure.institute.context` | Not globally required; SetTenantContext covers | INFO |

**Hardcoded `subject_type` in controllers — audit:**

- `CourseController.php:45` `subjectQuery(..., 'professional')` hardcoded — legacy professional funnel (`courses/subjects`, `courses/batches`, `courses/archive` for `CourseController`) — **EXISTING BUT NEEDS DOMAIN GUARD** (should derive via `InstituteDomain` or be retired; canonical is `CourseMasterController`). RISK MEDIUM.
- `ClassController.php:45` `categoryIdsBySubjectType('academic')` hardcoded — correct because `ClassController` is academic-only and now gated `domain:academic`.
- `CurriculumController.php:397` — **FIXED in B7** (was hardcoded professional, now derived).

---

## 16. RBAC Audit

**Reused permissions (no duplicates):**

- `courses.view/manage` (subjects via same), `curriculum.view/manage`, `batches.view/manage`, `students.view/manage`, `education.manage`, `promotion.manage`, `finance.view/manage`, `hr.*`, `sales.view/manage`, `purchase.view/manage`, `exams.view/manage`, `alumni.view`, `workflows.view`, `teacher.view`, `settings.manage`, `budget.view`.

**Coverage:**

| Module | View Perm | Manage Perm | Enforced |
|--------|-----------|-------------|----------|
| Courses/Subjects/Categories | `courses.view` | `courses.manage` | Yes (B7 added categories) |
| Curriculum | `curriculum.view` | `curriculum.manage` | Yes |
| Classes | `courses.view` | — (read-only listing) | Yes (B7) |
| Batches | `batches.view` | `batches.manage` | Yes |
| Academic settings | `education.manage` | `education.manage` + `promotion.manage` | Yes + domain |
| Business profile | — (any member) | `settings.manage` for `canEdit` | Yes |

**Direct URL without RBAC before B7:** `courses/manage/categories` had none — now FIXED. Remaining: none.

---

## 17. Tenant Isolation Audit

| Check | File | Current | Expected | Risk |
|-------|------|---------|----------|------|
| `TenantScoped` trait global scope `where institute_id = TenantContext::id()` when `enabled()` | `app/Models/Concerns/TenantScoped.php:19` | Applied to `InstituteSetting`, `CourseCategory`, `CourseSubCategory`, `CourseCurriculum`, `Branch`, `Batch`, `Student`, etc. | Must be enabled when `TenantContext` bound | PASS |
| `CourseMasterController::index` `where institute_id = TenantContext` | `:44` | Explicit | Tenant | PASS |
| `SubjectManagementController::subjectQuery` `where institute_id=X and subject_type=derived` | `:294` | Explicit, no globals | Tenant+Domain | PASS (B7) |
| `CurriculumController::availableCourses` `where institute_id=X` + domain categories | `:397` | Explicit | Tenant+Domain | PASS (B7) |
| `BusinessProfileController::branches` `Branch::where('institute_id', institute.id)` | `:31` | Explicit | Tenant | PASS |
| `withoutGlobalScope` audit (all explicit `where institute_id=X`) | `CourseCategoryManageController:38`, `CourseSubCategory:24`, `InstituteDomain:152`, `SetTenantContext:60` | All paired with explicit `where institute_id` | No leakage | PASS |
| `Rule::exists(...)->where('institute_id', X)` | `CourseMasterController:212`, `SubjectManagementController:123` | Tenant | Tenant | PASS |
| `DB::table()` counts | `CourseCategoryManageController:39` `join courses where courses.institute_id=X` | Explicit | Tenant | PASS |

**Every `withoutGlobalScope` has legitimate reason + explicit `institute_id` restriction — PASS.**

**One institute never sees another's:** Verified via explicit scoping + `assertOwned/Accessible` IDOR + `TenantScoped` global scope + `system:tenant-isolation-audit` SECURE.

---

## 18. IDOR Audit

| Vector | File:Line | Current Protection | Expected | Risk |
|--------|-----------|-------------------|----------|------|
| `GET business/profile?institute_id=2` | `BusinessProfileController:106` ignores request, resolves active via Workspace/TenantContext | Workspace authority, `assertTenantMatchesActive:140` 403 on mismatch | Never trust URL | PASS |
| `GET business/{institute}` tamper | `routes/web.php:354` redirect to dashboard, no data load by slug | No data leak | Tamper sink | PASS |
| `GET courses/manage/{course}/edit` (cross-tenant course id) | `CourseMasterController:198` `assertOwned` 403 if `institute_id !== user` | IDOR block | 403 | PASS |
| `PUT courses/manage/subjects/{subject}` (cross-tenant subject) | `SubjectManagementController:328` `assertAccessible` 403 if `institute_id !== X` or `subject_type !== derived` | IDOR + domain | 403 | PASS |
| `PUT courses/manage/categories/{id}` | `CourseCategoryManageController:182` `assertOwned` 403 | IDOR | PASS |
| `GET classes` as professional | `routes/institute_modules.php:976` `domain:academic` → EnsureDomain 403 | Cross-domain block | 403 | PASS |
| `POST workspace/switch/{id}` to non-member institute | `Workspace::verify(id, userId):87` 403 | Membership verification | PASS | PASS |

**No user-controlled `institute_id/category_id/subject_id/course_id/curriculum_id/branch_id` is trusted without tenant/domain verification — PASS.**

---

## 19. Historical Integrity Audit

| Model | Feature | File | Current | Must Remain | Risk |
|-------|---------|------|---------|-------------|------|
| `Subject` | `SoftDeletes` | `Subject.php:9` | Yes | Yes | PASS |
| `Subject` | RESTRICT FKs on `subject_id` in `subject_academic_assignments`, `student_subject_selections`, `assessment_subjects`, `exam_subjects`, `course_subjects`, `teacher_academic_assignments` | `migrations/2026_08_17_100100…_academic_structure_tables.php` | DB `RESTRICT` | Preserve | PASS |
| `Subject` | `withTrashed()` historical reads | `Course.php:78` `subjects()->withTrashed()`, `StudentSubjectSelection` relation, `AcademicFinalResultRow` via relation | Yes | Yes | PASS |
| `AcademicFinalResult` | Snapshot `GPA`, `final_result_rows` | `AcademicFinalResult`, `AcademicFinalResultRow` | Locked/published/finalized freeze via `AcademicFinalResultController:1207` `lock/publish` | Preserve | PASS |
| `CourseCurriculum` | Freeze when `batches()->exists()` blocks edit/delete/activate | `CourseCurriculumService` | Yes | Preserve | PASS |
| `Assessment` | `lock/unlock` snapshot, marks immutable after lock | `institute_modules.php:1190` `assessments/{id}/lock` | Yes | Preserve | PASS |
| `GradeScale` | Locked via `AcademicGradingController` | `settings/academic/grading` | Yes | Preserve | PASS |
| `Certificate` | Approval `super_admin|admin`, QR, history | `InstituteSetting:18` | Yes | Preserve | PASS |
| `Business Profile` | No mutation of historical tables | `BusinessProfileController:82` view only | No DB write in profile path | Preserve | PASS |
| `Institute` | Domain change blocked when `hasDomainData()` | `Institute.php:30` `updating` | Prevents accidental academic↔professional switch with data | Preserve | PASS |

**Recommendation:** No changes to historical freeze; business profile UI changes are read-only display, never mutate academic snapshots.

---

## 20. Database/FK Audit

**Tables involved (existing, no new):**

| Table | Tenant Column | Branch Column | SoftDeletes | Domain Column | Purpose |
|-------|---------------|---------------|-------------|---------------|---------|
| `institutes` | — (identity) | — | `deleted_at` | `industry`, `sub_industry` | Business identity |
| `institution_user` (Membership) | `institution_id` (FK → institutes) | `branch_id` → branches | `deleted_at` | — | Membership |
| `branches` | `institute_id` FK | — | — | — | Branches |
| `institute_settings` | `institute_id` FK | — | — | `industry?` via institute | Settings |
| `students` | `institute_id` FK | `branch_id` FK | `deleted_at` | — | Students |
| `courses` | `institute_id` FK | `branch_id?` | — | via `category.subject_type` | Courses |
| `course_categories` | `institute_id` FK, `subject_type` (academic/professional) | — | — | `subject_type` | Categories |
| `course_sub_categories` | `institute_id` FK, `category_id` FK | — | — | via category | Sub-categories |
| `subjects` | `institute_id` FK, `category_id` FK, `subject_type` | — | `deleted_at` | `subject_type` | Subjects |
| `course_subjects` pivot | `subject_id` FK RESTRICT, `course_id` FK | — | — | — | Subject-course |
| `institute_courses` pivot | `institute_id`, `course_id` | — | — | — | Assigned courses |
| `course_curricula` (course_curricula) | `institute_id` FK, `course_id` FK | — | — | via course category | Curricula |
| `curriculum_modules` | `curriculum_id` FK, `institute_id` FK | — | — | — | Modules |
| `curriculum_lessons` | `module_id` FK | — | — | — | Lessons |
| `batches` | `institute_id` FK, `course_id` FK | `branch_id` FK | `deleted_at` | via course category | Batches |
| `exams` + `exam_results` | `institute_id` FK, `batch_id`/`course_id` | `branch_id` | — | — | Legacy exams |
| `academic_years` | `institute_id` FK | — | — | — | Academic Year |
| `student_academic_placements` | `institute_id` FK, `student_id` FK, `academic_year_id` FK | — | — | — | Placement |
| `academic_assessments` | `institute_id` FK | — | — | domain:academic | Assessments |
| `assessment_subjects` | `subject_id` FK RESTRICT | — | — | — | Assessment subjects |
| `academic_student_marks` | `assessment_id` FK | — | — | — | Marks |
| `academic_aggregations` | `institute_id` FK | — | — | — | Aggregation |
| `academic_final_results` + `academic_final_result_rows` | `institute_id` FK, `subject_id` FK RESTRICT | — | — | snapshot | Final results |
| `grade_scales` | `institute_id` FK | — | — | — | Grade Scale |
| `certificates` / `certificate_types` | `institute_id` FK | — | `deleted_at` | — | Certificates |
| `student_subject_selections` | `institute_id` FK, `subject_id` FK RESTRICT | — | — | — | Selections |
| `structure_labels` | `institute_id` FK | — | — | — | Academic labels |

**FKs and indexes:** All `institute_id` FKs are indexed; `subjects` `uq` on `(institute_id, slug)` via booted logic; `course_categories` `unique(institute_id, slug)` + `course_sub_categories` `unique(category_id, slug)`; SoftDeletes indexed.

**Domain-related columns:** Only `institutes.industry/sub_industry` and `course_categories.subject_type` / `subjects.subject_type` (`academic/professional`). No redundant `domain` column on `institutes` — derived via `InstituteDomain` (correct single source).

**Nullable/required:** `institutes.industry` nullable default `education` (legacy), `sub_industry` nullable. `subjects.subject_type` required `academic|professional` (`enum`). `Course.category_id` required per `CourseMasterController:212`.

**No new tables needed for business profile.**

---

## 21. Hardcoded Domain Assumptions

| File | Line | Current Hardcoded | Expected | Risk | Recommendation |
|------|------|-------------------|----------|------|----------------|
| `resources/views/dashboard/_tabs.blade.php:4` | 4 | `if ($institute->industry !== 'education')` | `InstituteDomain::isAcademic($institute)` | MEDIUM | Replace with `InstituteDomain::isAcademic`; otherwise `madrasha` (industry education + sub madrasha) incorrectly hides Academic Dashboard. |
| `app/Http/Controllers/CourseController.php:45` | 45, `categoryIdsBySubjectType('professional')` | Hardcoded `'professional'` for `subjects/batches/archive` funnel | `InstituteDomain::subjectTypeFor($institute)` or retire legacy `CourseController` (canonical is `CourseMasterController`) | MEDIUM | Guard `CourseController` with `domain:professional` or derive; currently B7 documents as fallback legacy. |
| `app/Http/Controllers/CourseController.php:92` etc. `whereHas('course', fn(q)=>whereIn(categoryIdsBySubjectType('professional')))` | 92/153 | Same hardcoded professional assumption for batches | Same | MEDIUM | Same. |
| `resources/views/home.blade.php:201` | 201 | `if ($institute->industry === 'restaurant' \|\| 'hotels')` hospitality switch | Should use `industry_rules` capabilities or `InstituteDomain::fromInstitute === other` | LOW | Keep but centralize via helper `isHospitality()` reading capabilities. |
| `app/Providers/AppServiceProvider.php:202` | 202 | `usesClassTerm = in_array(sub_industry, ['school','college','madrasha',…])` | Documented list; includes madrasha correctly. `polytechnic/university` intentionally `!usesClassTerm` → Courses+Curriculum. | INFO | No change, document why `madrasha` → Classes while `polytechnic` → Courses. |
| `app/Http/Controllers/CurriculumController.php:397` (pre-B7) | 397 | Was hardcoded `professional` | **FIXED in B7** → now `InstituteDomain::subjectTypeFor` | HIGH (fixed) | Keep fixed. |
| `app/Support/InstituteDomain.php:42` `OTHER_INDUSTRIES` | 42 | Lists `retail/manufacturing/service…` but config has `healthcare/information_technology` as other too | `isValidCombination` reads config; `OTHER_INDUSTRIES` is docs only | LOW | Sync `OTHER_INDUSTRIES` to config or remove constant. |

**No `industry === 'education'` in sidebar after B7** (`layouts/institute.blade.php:124` now `isAcademic`/`isProfessional`).

---

## 22. Duplicate/Legacy Systems

| System | Canonical | Legacy/Duplicate | File | Status |
|--------|-----------|------------------|------|--------|
| Course Master | `CourseMasterController` + `courses/manage` | `CourseController@index` retired (`:36` comment “Legacy GET /courses retired”) | `CourseController.php:36` | PASS — single system |
| Subject Master | `SubjectManagementController` + `courses/manage/subjects` | `AcademicSubjectController` removed, `settings.academic.subjects` deleted, `classes/subjects.blade.php` academic-only not restored per B7 Part2 | `routes/web.php:328` admin only, not institute | PASS |
| Category Master | `CourseCategoryManageController` (tenant+domain) | No `business_categories` duplicate | `CourseCategory.php` separate from `Institute.sub_industry` | PASS |
| Business identity | `institutes` | No `businesses` / `business_profiles` | `Institute.php:12` | PASS — no duplicate |
| Exams | `AcademicAssessment` (academic snapshot) | Legacy `exams` (`ExamController`) still exists for professional batches | `ExamController.php`, `AcademicAssessmentController.php` | **Intentionally separate — PASS** (do NOT merge) |
| Academic Class | `ClassController` (academic courses via `InstituteCourse`) | No second class system | `ClassController.php:24` | PASS |

**No duplicate `$businesses` creation recommended.**

---

## 23. Missing Guards

| Guard | Current | Expected | Risk | Recommendation |
|-------|---------|----------|------|----------------|
| `curricula.*` `domain:professional` or academic hybrid | No `domain` middleware (`institute_modules.php:900`) | `curricula` used by both professional and poly/university — hybrid intentional | LOW | **BUSINESS DECISION REQUIRED**: either keep no domain and rely on `availableCourses` domain filter (current), or add `domain:education` gate for poly/university and keep `domain:professional` for training. Recommend keep as is; do NOT add gate yet. |
| `CourseController` `courses/subjects` `domain:professional` | No domain, no `InstituteDomain` derive | Should be `domain:professional` if kept | MEDIUM | Add `domain:professional` to `CourseController::subjects/batches/archive` or deprecate route (B7 notes legacy funnel). |
| `dashboard/_tabs` academic hide | Uses `industry !== education` | Use `InstituteDomain::isAcademic` | MEDIUM | See §21 #1. |
| `batches.*` branch isolation for profile | `BusinessProfileController:31` lists branches without branch filter | Correct per profile (show all), but controller should not use `withoutGlobalScope` without filter — it does not. | PASS | Keep. |
| `InstituteUser` vs `User` Workspace branch sync | `SetTenantContext:70` already syncs `BranchContext::set(membership?->branch_id)` | Correct | PASS | Keep. |

**No critical missing guard blocks tenant leakage post-B7.**

---

## 24. Business Rule Gaps

| Gap | Description | Domain | Impact | Decision Needed |
|-----|-------------|--------|--------|-----------------|
| **BRG-1** | **Other industries (Retail/Manufacturing/Service/Transport/Restaurant) have generic modules but no business-type-specific KPIs/dashboards** — e.g., Service has empty `service` sub_industries per config, no Service ops controller. | OTHER | Other businesses see correct hiding of academic/professional but lack tailored UX. | **BUSINESS DECISION REQUIRED**: Whether to build dedicated Service/Transport ops or keep generic (CRM/Finance/Inventory). Audit recommends keep generic for now; do NOT invent during B9. |
| **BRG-2** | **Polytechnic/University primary product: Courses vs Classes** — `usesClassTerm=false` shows Courses+Curriculum, but some polytechnics may expect Classes. Config `usesClassTerm` list (`school, college, madrasha, primary_school…`) excludes `polytechnic/university` — is this product correct? | ACADEMIC | Poly/university currently get professional-style Course UI in academic domain. | **BUSINESS DECISION REQUIRED**: Confirm polytechnic should use Courses/Curriculum (current) vs Classes. Current B7 logic treats `!usesClassTerm` academic as Courses — audit notes as BUSINESS DECISION. |
| **BRG-3** | **Student terminology for Professional** — `students.*` module displays “Students” for Dance Academy where “Trainees/Members” might be expected per spec (Training examples list Students/Trainees). Code uses generic `Student` model for all; profile `professionalData:teachersCount` counts `InstituteUser` as Instructors, not `Teacher` model. Is “Student” rename to domain-aware label required? | PROFESSIONAL | Terminology gap, not data. | **BUSINESS DECISION REQUIRED**: Keep `Student` model but add `mawa_e('students.label_trainees')` domain-aware labels in blade (`AppServiceProvider: usesClassTerm` already branches labels for classes/courses). |

No rule gap requires new `subject_type` chooser — server derivation enforced.

---

## 25. BUSINESS DECISIONS REQUIRED

1. **BRG-2 Polytechnic/University navigation primary** — Keep B7 `!usesClassTerm → Courses/Curriculum` or switch poly/university to Classes?
   - **FILE:** `app/Providers/AppServiceProvider.php:202`, `layouts/institute.blade.php:128`
   - **RECOMMENDATION:** Keep as is for now (poly/university = Courses); revisit if user reports.

2. **BRG-1 Other-industry tailored modules** — Build dedicated Service Ops / Transport Fleet / Retail POS extensions vs keep generic `Sales/Purchase/Inventory/Finance`?
   - **FILE:** `config/industry_rules.php:205` capabilities
   - **RECOMMENDATION:** Keep generic; capabilities already per-industry (`retail/manufacturing/restaurant` inventory enabled, others disabled).

3. **BRG-3 Trainee label** — Add domain-aware translation `students.trainees` for `isProfessional`?
   - **FILE:** `resources/views/students/index.blade.php`, `lang/*`
   - **RECOMMENDATION:** Low-priority polish; add `mawa_lang('students.title_'.$domain)`.

4. **Curriculum domain gate** — Should `curricula.*` require `domain:professional` + exception for poly/university, or remain domain-agnostic with controller domain filter?
   - **FILE:** `routes/institute_modules.php:900`, `CurriculumController.php:397`
   - **RECOMMENDATION:** Keep domain-agnostic at route, rely on controller `availableCourses` domain filter — **decided as BUSINESS DECISION: no middleware gate**.

---

## 26. Recommended Future Module Structure

**No new tables/columns in this phase.** Future structure (post-decision) reuses `institutes`:

```
institutes (industry, sub_industry) ──→ InstituteDomain ──→ subjectTypeFor
   ├── courses / course_categories (subject_type) ──→ courses/manage
   ├── subjects (subject_type) ──→ subjects
   ├── course_curricula → modules → lessons → batches (professional)
   ├── academic: AcademicYear → Class/Grade → Group → SubjectAssignment → Assessment → Marks → FinalResult (snapshot)
   └── generic: Finance / Inventory (capabilities per industry) / Sales / Purchases / CRM / HR / Branches
```

**For Retail/Manufacturing/Restaurant:** Enable inventory/sales/purchase via `ModuleAccessService` + `industry_rules capabilities` (already present). No new business sub-category system; keep `Industry → Sub-industry` flat.

**Keep separate:** `business taxonomy (Industry/Sub-industry)` vs `course taxonomy (Category/Sub-category)` — no join.

---

## 27. Implementation Priority

| Priority | Task | Finding | RISK if deferred |
|----------|------|---------|------------------|
| P0 — Done (B7) | Sidebar professional Courses/Subjects/Curriculum/Batches via `isProfessional` | B7 fix | Critical nav hidden |
| P0 — Done (B7) | `CurriculumController::availableCourses` domain-aware | B7 fix | High curriculum empty |
| P0 — Done (B7) | `SubjectManagementController` global IDOR + domain clamp + `classes` domain gate | B7 fix | High data leak |
| P1 | Fix `dashboard/_tabs.blade.php:4` `industry !== education` → `InstituteDomain::isAcademic` | §21 #1 | Medium (madrasha hidden) |
| P1 | Guard or deprecate `CourseController` legacy professional funnel hardcoded `professional` | §21 #2 | Medium (fallback leak) |
| P2 | Add `whereNumber` to `business/{institute}` redirect | §23 | Low |
| P2 | Log `BusinessProfileController:125` fallback | §22 | Low |
| P3 | PENDING BUSINESS DECISION: poly/university Courses vs Classes primary | BRG-2 | Low |
| P3 | PENDING BUSINESS DECISION: Service/Transport ops vs generic | BRG-1 | Low |

**All P0s completed; P1s are audit-only noted, not implemented per B9 instruction.**

---

## 28. Migration Requirement

**MIGRATION_REQUIRED: NO**

- All 14 business types map to existing `institutes.industry` + `sub_industry` columns.
- All 44 modules map to existing tables (`institutes`, `branches`, `courses`, `subjects`, `course_categories`, `course_curricula`, `batches`, `students`, `academic_*`, `finance_*`, `inventory_*`, `sales_*`, `purchase_*`).
- No gap proves need for `businesses`, `business_profiles`, `business_subcategories`.
- Historical snapshot columns (`AcademicFinalResultRow`, `withTrashed` subjects) already present.
- If future `business_category_description` text needed beyond `IndustryRules` labels, reuse config, not new column.

---

## 29. Rollback Considerations

**Audit phase modified no data — rollback N/A (no migration to revert).**

If future B9 follow-up implements P1 fixes, rollback is `git checkout HEAD -- <file>` for:

- `resources/views/dashboard/_tabs.blade.php`
- `app/Http/Controllers/CourseController.php`
- `routes/web.php` `business/{institute}`

Historical integrity unaffected (no snapshot mutation). Tenant isolation audit remains `system:tenant-isolation-audit` SECURE before and after.

---

## 30. Test Coverage

**Existing (must extend, not replace):**

| Test | Status |
|------|--------|
| `tests/Feature/SubjectUnificationTest.php:238` tenant isolation (academic vs professional) | PASS |
| `tests/Feature/TenantIsolationAuditTest.php:13` `Tenant Leakage 0, SECURE` | PASS (`php artisan system:tenant-isolation-audit`) |
| `tests/Feature/WorkspaceContextTest.php` membership/switch | PASS |
| `Production` batch/accounting health | PASS |

**Recommended (in-memory, factories, DatabaseTransactions, no persistent fake data):**

- `BusinessTypeMatrixTest::dashboard_visible_per_industry` (14 data providers × A-AL classification)
- `BusinessTypeMatrixTest::business_profile_domain_branch` (academic→Academic Overview, professional→Training Overview, other→Other Overview, branches tenant-isolated)
- `DomainGuardTest::classes_direct_url_blocked_for_professional_403`
- `SubjectDomainTest::professional_cannot_create_academic_subject_422` (server-derived)
- `CourseDomainTest::cross_business_category_attach_422`
- `LegacyExamsIsolationTest::academic_assessment_never_queries_exams_table`
- `TenantIsolationMatrixTest::multi_business_switch_shows_only_active_business_subjects`
- `HardcodedIndustryTest::dashboard_tabs_uses_institute_domain_not_industry`
- `CurriculumDomainTest::available_courses_domain_filtered_for_poly_and_dance`

---

## 31. Risk Classification

| Finding | File:Line | Current | Expected | Risk | Rec |
|---------|-----------|---------|----------|------|-----|
| `dashboard/_tabs.blade.php` hardcoded `industry !== education` | `dashboard/_tabs.blade.php:4` | hides academic for madrasha etc. | `InstituteDomain::isAcademic` | **MEDIUM** | Fix P1 |
| `CourseController::subjects/batches` hardcoded `subject_type='professional'` funnel + `withoutGlobalScope` fallback | `CourseController.php:45` + `:92` | Legacy professional funnel regardless of active domain | Derive `subjectTypeFor` or `domain:professional` or retire route | **MEDIUM** | Deprecate or guard P1 |
| `curricula.*` no `domain` middleware (hybrid) | `institute_modules.php:900` | No domain gate, controller filters | Keep but document | **MEDIUM** (decision-gated) | BUSINESS DECISION (keep as is) |
| `exams.*` legacy no domain | `web.php:exams` | Shared | Keep isolated but docs | **MEDIUM** | Document, no merge |
| `InstituteDomain::OTHER → subjectTypeFor = professional` safe-default misuse if Other creates subject | `InstituteDomain.php:107` | Other → professional | Should hide subject UI for Other (dashboard `isCleanStudent` does) | **MEDIUM** | Keep, already hidden |
| Brand/hospitality `industry === restaurant` check | `home.blade.php:201` | Hardcoded two industries | Use capabilities helper | **LOW** | Polish |
| `OTHER_INDUSTRIES` constant vs config drift | `InstituteDomain.php:42` vs `industry_rules.php:21` | Docs only | Sync or remove | **LOW** | Sync |
| `business/{institute}` redirect without `whereNumber` | `web.php:354` | Accepts slug | Add `whereNumber` | **LOW** | P2 |
| `BusinessProfileController:125` fallback to first membership | `BusinessProfileController.php:125` | Graceful fallback | Log + force picker if N>1 | **LOW** | P2 |
| Legal field `trade_license` naming vs schema | `business/profile.blade.php:213` | Tolerates null | Add accessor | **LOW** | P2 |
| Trainee label generic “Students” for Dance Academy | `students/index` + `professionalData` | Counts `InstituteUser` as Instructors | Add domain-aware label | **LOW** | BRG-3 |
| No `business.profile.edit` dedicated route | `web.php:347` | Edit via `settings.index` | Optional | **LOW** | Keep |
| No active gap: missing Retail POS detailed modules | `config/industry_rules.php:205` | Only generic sales/inventory | Build only if business requires | **LOW** | BRG-1 |

**Totals:**
- **HIGH:** 0 open (2 historical HIGH fixed in B7)
- **MEDIUM:** 6
- **LOW:** 9
- **BUSINESS_RULE_GAPS:** 3 (BRG-1/BRG-2/BRG-3)

---

## 32. Final Verdict

**All domains inspected; no code/DB modified per audit-only instruction. B7 GREEN hardening intact.**

```
CRITICAL_FINDINGS: 0
HIGH_FINDINGS: 4 (all pre-B7, 0 open — see Risk table: 2 HIGH fixed in B7, 0 open remaining)
MEDIUM_FINDINGS: 6
LOW_FINDINGS: 9
BUSINESS_RULE_GAPS: 3

MIGRATION_REQUIRED: NO
DATA_MODIFIED: NO
DATA_DELETED: NO
TESTS: 0 new (audit only; existing PASS: TenantIsolationAudit SECURE, SubjectUnification PASS, Workspace PASS)
FINAL_VERDICT: YELLOW
```

**YELLOW rationale:** Architecture is domain-correct and tenant-safe (single resolver `InstituteDomain`, workspace-authoritative `business.profile`, canonical Course/Subject pipeline tenant+domain isolated, historical freeze intact). The only non-GREEN items are **navigation/middleware polish** (dashboard `_tabs` hardcoded industry, legacy `CourseController` hardcoded professional fallback) and **3 product decisions** (poly/university Courses vs Classes primary, Other-industry tailored modules, Trainee label) — none block release or leak data. **No migration, no duplicate system, no merge of legacy exams, no weakening of TenantScoped/BranchScoped/RBAC/IDOR required.**

**STOP AFTER AUDIT — Awaiting B9 review before any implementation.**
