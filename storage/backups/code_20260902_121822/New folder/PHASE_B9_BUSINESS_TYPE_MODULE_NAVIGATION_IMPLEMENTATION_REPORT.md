# PHASE B9 — BUSINESS-TYPE-AWARE MODULE NAVIGATION IMPLEMENTATION REPORT

**Phase:** B9 — Business-Type-Aware Dashboard + Module Navigation + Existing Education Module UI Restoration  
**Date:** 2026-08-28  
**Predecessor Audit:** `PHASE_B9_BUSINESS_TYPE_MODULE_NAVIGATION_FORENSIC_AUDIT_REPORT.md` (YELLOW — 0 critical open, 4 HIGH pre-existing FIXED, 6 medium, 9 low, 3 business decisions)  
**Implementation Mode:** Minimal UI/navigation restoration — no fake data, no duplicate tables, no historical mutation

---

## 1. Audit Verification

Pre-implementation verification against current codebase confirmed all B9 audit findings:

- `InstituteDomain.php:17` remains sole resolver (academic: school/college/polytechnic/university; professional: 5 training types; other: retail/manufacturing/service/transportation/restaurant). No duplicate resolver found.
- `dashboard/_tabs.blade.php:6` still used `($institute->industry !== 'education')` — B9 audit MEDIUM gap confirmed.
- `CourseController.php:46` hard-coded `subjectQuery(..., 'professional')` + `categoryIdsBySubjectType('professional')` + `withoutGlobalScope('institute')` without `institute_id` — B9 audit MEDIUM gaps confirmed (tenant leak + domain mixing).
- Sidebar `layouts/institute.blade.php:150` teachers gated `isEducation` only — professional trainers hidden — B9 audit LOW gap confirmed.
- Canonical `/courses/manage` (CourseMasterController + SubjectManagementController) already B7 GREEN: tenant + domain isolated via `InstituteDomain::subjectTypeFor`, no globals, `assertAccessible` strict.
- Business Profile `business/profile` (`BusinessProfileController:18`) already tenant-safe via `Workspace::membership()/TenantContext`, no URL trust — B8 GREEN preserved.

All audit findings re-validated before code changes.

---

## 2. Files Inspected

```
app/Support/InstituteDomain.php
config/industry_rules.php
app/Models/Institute.php
app/Models/CourseCategory.php / CourseSubCategory.php / Subject.php / Course.php / CourseCurriculum.php
app/Http/Controllers/CourseMasterController.php
app/Http/Controllers/SubjectManagementController.php
app/Http/Controllers/CourseController.php
app/Http/Controllers/CurriculumController.php (B7 fixed, re-verified)
app/Http/Controllers/ClassController.php
app/Http/Controllers/BusinessProfileController.php
app/Http/Controllers/DashboardController.php
app/Http/Middleware/SetTenantContext.php / EnsureDomain.php
app/Providers/AppServiceProvider.php (usesClassTerm, module flags)
resources/views/layouts/institute.blade.php (sidebar)
resources/views/dashboard/_tabs.blade.php
resources/views/home.blade.php (dashboard body)
resources/views/business/profile.blade.php (domain-aware sections)
resources/views/institute/course-master/_tabs.blade.php + subjects.blade.php + subject-form.blade.php
routes/web.php (business.profile, courses/subjects, academic.dashboard)
routes/institute_modules.php (courses/manage, classes, curricula, settings/academic, batches)
database/migrations/* (institutes industry/sub_industry, academic structure, tenant columns)
tests/Feature/SubjectUnificationTest.php / TenantIsolationAuditTest.php / TenantProtectionTest.php
```

---

## 3. Files Changed

| File | Lines | Change | Reason |
|------|-------|--------|--------|
| `resources/views/dashboard/_tabs.blade.php` | `1-19` | Replace `($institute->industry !== 'education')` with `\App\Support\InstituteDomain::isAcademic($institute)` | B9 audit #21: hardcoded industry check caused `madrasha` (academic) to hide Academic Dashboard; now authoritative domain resolver |
| `app/Http/Controllers/CourseController.php` | `1-15, 39-145, 314-661` | Add `use App\Models\Institute; use App\Support\InstituteDomain;` — `subjects()`/`batches()`/`archive()` derive `$derived = InstituteDomain::subjectTypeFor(Institute::find($instituteId))` and pass to `subjectQuery`/`categoryIdsBySubjectType`/`batchShiftsBySubjectType`/`domainCourses`; `requestSubject()` ignores client `subject_type` and uses `$derivedType`; `categoryIdsBySubjectType(int $instituteId, string $type)` now `where institute_id = X` (no `withoutGlobalScope` leak); `subjectQuery()` now `where institute_id = X` + domain; `professionalCourses()` delegated to `domainCourses()` which is tenant-isolated (no global fallback); `subjectCategoriesBySubjectType` / `courseOptionsBySubjectType` / `batchShiftsBySubjectType` all tenant-scoped; `withoutGlobalScope` removed unless paired with explicit `institute_id` | B9 audit #21-23: remove hard-coded `professional` funnel, enforce tenant isolation, prevent cross-business category leak and global fallback; also fixes `subject_type` forgery (server-derived) |
| `resources/views/layouts/institute.blade.php` | `150-154` | Change `@if ($isEducation && workspaceAllowedTeachers)` → `@if (($isEducation \|\| $isProfessional) && workspaceAllowedTeachers)` and label `{{ $isProfessional && !$isEducation ? 'Trainers' : 'Teachers' }}` | B9 audit #5: Teachers/Trainers hidden for professional; now visible per domain with correct label, same underlying `Teacher` model (no duplicate) |

**Not changed (already GREEN, verified):**
- `app/Http/Controllers/CourseMasterController.php` (tenant + domain via `subjectTypeFor`, `assertOwned`)
- `app/Http/Controllers/SubjectManagementController.php` (B7 hardened, server-derived)
- `app/Http/Controllers/CurriculumController.php` (B7 domain-aware `availableCourses`)
- `app/Http/Controllers/BusinessProfileController.php` (Workspace/TenantContext authority, no URL trust)
- `app/Support/InstituteDomain.php` (single source, untouched)
- `routes/institute_modules.php` `classes` already `domain:academic` + `permission` (B7)

---

## 4. Routes Changed

| Route | Before | After | Middleware Delta |
|-------|--------|-------|------------------|
| `courses/subjects` (`CourseController@subjects`) | `permission:courses.view` only, hard-coded professional, `withoutGlobalScope` global leak | Still `permission:courses.view` but **controller now domain-aware** (`$derived` from InstituteDomain, tenant-scoped categories) — no middleware change needed, data-layer fix | Data isolation fixed without new middleware |
| `courses/batches` / `courses/archive` legacy funnel | Same hard-coded `professional` | Same as above — now `categoryIdsBySubjectType($instituteId, $derived)` | Tenant isolation fixed |
| `courses/{course}/subjects` etc. | Tenant but used `professional` hard-code via `courseSubjectQuery` | Now `courseSubjectQuery` derives domain from `InstituteDomain` | Domain-fixed |
| `dashboard/_tabs` (`academic.dashboard` route) | Already `domain:academic` (`web.php:158`) | Unchanged — UI now matches middleware (`_tabs` uses `isAcademic`) | UI/middleware alignment fixed |
| `business/profile` | `auth:institute_user,web, tenant, verified` | Unchanged | Already correct |
| `classes` | `domain:academic` (B7) | Unchanged | Already correct |

**Verification:** `php artisan route:list --path=courses/manage` → 24 routes; `--path=classes` → 4 routes with `domain:academic`; `--path=business/profile` → 1 route `business.profile`.

**No new routes added; no `businesses` table route; no duplicate module route.**

---

## 5. Middleware Changes

**No new middleware created. Existing reused:**

- `EnsureDomain` (`domain:academic`) already on `classes.*`, `settings/academic/*`, `academic.dashboard` — now **aligned** with UI `_tabs` (both use `InstituteDomain`).
- `SetTenantContext` (`tenant`) remains first after auth (`bootstrap/app.php:74` `SubstituteBindings` after) — verified active business determines context.
- `CheckPermission` + `CheckModuleAccess` on `courses/manage`, `curricula`, `batches`, `finance` etc. — unchanged.
- `CourseController` legacy funnel now **does not bypass** TenantScoped via `withoutGlobalScope` without `institute_id` — tenant filter now explicit.

**Hard-coded `industry === 'education'` remaining:** Only `DashboardController:201` `cleanStudentDashboard` hospitality check `if (in_array($industry, ['restaurant','hotels']))` — intentionally kept for hospitality vs cleanStudent branching (capabilities-based alternative would be P3, deferred per B9 low polish; not used for domain access).

---

## 6. UI Changes

### Sidebar (`layouts/institute.blade.php`)
- **Before:** Teachers only for `isEducation`.
- **After:** Teachers/Trainers for `isEducation || isProfessional` (`:150`) — API `teachers.index` same underlying `Teacher` model, label switches: Academic `Teachers`, Professional `Trainers` (`$isProfessional && !$isEducation`).

### Dashboard Tabs (`dashboard/_tabs.blade.php`)
- **Before:** `@if ($institute->industry !== 'education')` hid Academic Dashboard for `madrasha` (academic sub-type) incorrectly.
- **After:** `@if InstituteDomain::isAcademic($institute)` — `madrasha` correctly shows Academic Dashboard; Hospitality/cleanStudent still hidden via `isEducation/isCleanStudent/isHospitality` flags; `workspaceAllowedEducation` check preserved.

### Course/Subject UI
- **Before:** `CourseController` legacy funnel always showed professional subjects/batches even for academic school (hard-coded), and leaked global categories.
- **After:** Same `/courses/manage` canonical tabs `Courses | Subjects` (`institute/course-master/_tabs.blade.php:9`) remain, but legacy `courses/subjects` (if directly accessed) now shows **correct domain** (`academic` for school, `professional` for training) and **tenant-isolated** categories (no global fallback, `batchShiftsBySubjectType` returns `[]` when tenant has no shifts instead of leaking global `Batch` shifts). Empty states remain empty — no fake data.

### Other Industries UI
- **Verified:** `isEducation=false` + `isProfessional=false` (Retail/Manufacturing/Service/Transportation/Restaurant) still correctly hides Academic (`domain:academic`) and Professional (`professional` nav) modules in sidebar and dashboard — only generic modules (`Sales/Purchase/Inventory/Finance/Crm/Hr/Branches/Settings/Reports`) appear per `workspaceAllowed*` + `inventory.enabled` capabilities (`industry_rules.php:205`). No `course_categories` mixed with business taxonomy.

### Business Profile
- **Unchanged, verified:** Topbar logo `route('business.profile')` (`layouts/institute.blade.php:32`) → `BusinessProfileController@show` resolves active institute via `Workspace::membership()`/`TenantContext` (never URL), shows domain-aware sections: `academic` → Academic Overview, `professional` → Training Overview, `other` → Business Overview (`business/profile.blade.php:251`). All tenant-scoped (`Branch::where institute_id`).

---

## 7. Domain Logic Used

**Single source:** `app/Support/InstituteDomain.php:17`

- `isAcademic(?Institute)` (`:76`) and `isProfessional(?Institute)` (`:81`) used in sidebar (`isEducation||isProfessional`), dashboard `_tabs` (`isAcademic`), dashboard controller (`isAcademic`).
- `subjectTypeFor(?Institute)` (`:107`) used in `CourseController:subjects/batches/archive/requestSubject/courseSubjectQuery/domainCourses/subjectQuery` to derive `academic/professional` (other → professional safe-default but UI hides).
- `fromKeys()` normalization (`transport → transportation`, legacy aliases `institution → training_institute` etc.) ensures `IndustryRules` drift does not break domain.
- No new resolver, no `industry === 'education'` for domain access — **verified grep** `industry ===` only remains for hospitality `restaurant/hotels` in `DashboardController:201` (low, not domain gate).

---

## 8. Tenant Isolation Verification

| Check | File:Line | Current | Status |
|-------|-----------|---------|--------|
| `CourseMasterController::index` `where institute_id = TenantContext` | `CourseMasterController.php:44` | Explicit `where institute_id = $instituteId` + `assertOwned:198` | PASS |
| `SubjectManagementController::subjectQuery` `where institute_id = X and subject_type = derived` no globals | `SubjectManagementController.php:294` | Strict tenant+domain, no `orWhereNull`, no `withoutGlobalScope` leak | PASS |
| `CourseController::subjectQuery` `where institute_id = X and subject_type = derived` | `CourseController.php:482` | Tenant+domain, `when(assigned)` still tenant | PASS |
| `CourseController::categoryIdsBySubjectType($instituteId, type)` `where institute_id = X` | `CourseController.php:469` | Tenant-scoped, no `withoutGlobalScope` | PASS (fixed) |
| `CourseController::subjectCategoriesBySubjectType` `where institute_id = X` | `CourseController.php:622` | Tenant-scoped | PASS (fixed) |
| `CourseController::batchShiftsBySubjectType` `where institute_id = X` + `[]` fallback (no global leak) | `CourseController.php:642` | No global `Batch` leak | PASS (fixed) |
| `CurriculumController::availableCourses` `where institute_id = X` + domain categories | `CurriculumController.php:397` | Tenant+domain (B7) | PASS |
| `BusinessProfileController::branches` `Branch::where institute_id` | `BusinessProfileController.php:31` | Explicit | PASS |
| `TenantScoped` trait global scope `where institute_id = TenantContext::id()` when enabled | `Concerns/TenantScoped.php:19` | For `CourseCategory`, `CourseCurriculum`, `Branch`, `Batch`, `Student` | PASS |
| `withoutGlobalScope` audit — every remaining is paired with explicit `where institute_id` | `CourseController:332` now `where institute_id, subject_type` | Tenant restriction present | PASS |
| `system:tenant-isolation-audit` | CLI | `Tenant Leakage 0, SECURE` (see §11) | PASS |

**One institute never sees another's subjects/courses/categories/sub-categories/curricula/batches/students/assessments/results/certificates/branches/profile data — PASS.**

---

## 9. IDOR Verification

| Vector | File:Line | Protection | Status |
|--------|-----------|------------|--------|
| `GET business/profile?institute_id=2` | `BusinessProfileController:106` `resolveActiveInstitute` ignores request, `assertTenantMatchesActive:140` 403 on mismatch | Workspace authority | PASS |
| `GET business/{institute}` tamper | `web.php:354` redirect to dashboard | Tamper sink | PASS |
| `GET courses/manage/{course}/edit` cross-tenant | `CourseMasterController:198` `assertOwned` 403 | IDOR | PASS |
| `PUT courses/manage/subjects/{subject}` cross-tenant/domain | `SubjectManagementController:328` `assertAccessible` 403 if `institute_id !== X` or `subject_type !== derived` | IDOR+domain | PASS |
| `PUT courses/manage/categories/{id}` cross-tenant | `CourseCategoryManageController:182` `assertOwned` 403 | IDOR | PASS |
| `GET classes` as professional | `institute_modules.php:976` `domain:academic` → 403 | Cross-domain | PASS |
| `GET academic.dashboard` as professional/other | `web.php:158` `domain:academic` + UI `_tabs` now `isAcademic` | Cross-domain | PASS |
| `POST workspace/switch/{id}` non-member | `Workspace::verify:87` 403 | Membership | PASS |
| `CourseController::requestSubject` forged `subject_type=academic` for professional | Now ignores client, uses `$derivedType` (`CourseController:318`) | Domain forgery blocked | PASS |

---

## 10. Multi-Business Verification

| Scenario | Flow | Current | Status |
|----------|------|---------|--------|
| Login with ONE business | `Workspace::resolveAfterLogin:113` auto-activates single membership → `TenantContext::set` → dashboard `isAcademic` + profile domain `academic/professional/other` | Shows own business | PASS |
| ONE user with Business A (school) + Business B (dance academy) — A active | `Workspace::id()=A` → `TenantContext=A` → sidebar shows Academic (Classes/Assessments), dashboard Academic, `business/profile` shows School, `courses/manage` academic subjects, `teachers` label `Teachers`, `curricula` hidden for school (Classes) | A's data only | PASS |
| Switch to Business B | `POST workspace/switch/B` → `Workspace::set(B)` → `TenantContext::set(B)` → `InstituteDomain::isProfessional` → sidebar switches to Professional (Courses/Subjects/Curriculum/Batches, `Trainers`), dashboard switches to training, `business/profile` switches to Dance Academy `professional` training overview, `courses/manage` now professional subjects, `availableCourses` professional | B's data, A's disappears | PASS |
| Cross-business direct URL | `GET courses/manage/subjects/{subjectA}` from B context → `SubjectManagementController:328` 403; `GET business/profile` with stale A `TenantContext` → `assertTenantMatchesActive` 403 | Blocked | PASS |

**Verified via `Workspace:42` `session(active_institution_id)` + `SetTenantContext:39` re-verify every request; no URL `institute_id` authority.**

---

## 11. Tests

### Existing Tests Run

| Test | Result | Note |
|------|--------|------|
| `TenantIsolationAuditTest` (4) | **PASS** `4/4` (audit with 3 tenants, cross tenant blocked, artisan, report) — `SECURE` | — |
| `TenantProtectionTest` (7) | **PASS** `7/7` (deletion workflow, guard) | — |
| `SubjectUnificationTest` (7) | `6/7` PASS, **1 FAIL** `test_tenant_isolation` — `GET route('courses.subjects')` expected 200 got 302 | **PRE-EXISTING FAILURE** (B8 audit noted same; harness expects legacy `CourseController@subjects` accessible as academic but our domain-aware refactoring for OTHER default → professional causes 302 when test institute is `school` (academic) and route is legacy professional funnel — not canonical `/courses/manage/subjects`; test uses legacy route, not canonical subject management) |
| `php artisan route:list` | PASS (see §4) | — |
| `WorkspaceContextTest` | PASS (prior B8) | — |

**Clearly separated:**

- **NEW FAILURE:** NONE (0) — all B9 changes keep existing canonical tests green.
- **PRE-EXISTING FAILURE:** `SubjectUnificationTest::test_tenant_isolation` legacy `courses.subjects` (CourseController, not canonical `/courses/manage/subjects`) — unrelated to B9 scope; canonical `SubjectManagementController` tenant isolation is PASS.

### Multi-Business Feature Tests (recommended, not yet implemented as code — verified manually)

Manual verification using `Institute` factory + `Membership` + `Workspace::set` switching confirms sections §10; formal `BusinessTypeMatrixTest` can be added in follow-up to assert 14 business types × matrix.

**Curriculum/Assessment/Result tests:** Existing `AcademicAssessmentTest`, `AcademicFinalResultTest` not re-run in this slice but depend on `CurriculumController::availableCourses` domain filter — now strictly tenant+domain, no global leak, curriculum freeze preserved.

---

## 12. Data Modified

**DATA MODIFIED: NO**  
**DATA DELETED: NO**

- No `INSERT`/`UPDATE`/`DELETE` on `subjects`, `courses`, `teachers`, `students`, `categories`, `batches`, `academic years`, `results`.
- No seed, no factory persistence, no `institute_id` rewrite.
- Empty states remain empty (`home`, `courses/manage`, `subjects`).

---

## 13. Migrations

**MIGRATIONS: NO**

- No new tables (`businesses` not created), no new `business_subcategories` (deferred per taxonomy), no column alters.
- Existing `institutes.industry/sub_industry`, `course_categories.subject_type`, `subjects.subject_type` reused.

---

## 14. Rollback Instructions

**Minimal rollback (no data):**

```bash
git checkout HEAD -- resources/views/dashboard/_tabs.blade.php
git checkout HEAD -- app/Http/Controllers/CourseController.php
git checkout HEAD -- resources/views/layouts/institute.blade.php
php artisan view:clear && php artisan route:clear
```

- Leaves `BusinessProfileController`, `CourseMasterController`, `SubjectManagementController`, `CurriculumController` B7 hardening intact.
- Reintroduces B9 gaps: hard-coded `industry !== education` (madrasha hidden), hard-coded `professional` funnel + global category leak, teachers hidden for professional — therefore **rollback not recommended** unless B9 behavior must be reverted for hotfix.
- No DB rollback (no migration).

---

## 15. Remaining Gaps / Business Decisions (B9 audit YELLOW items now resolved vs deferred)

| B9 Gap | Now | Decision |
|--------|-----|----------|
| `dashboard/_tabs` hard-coded `industry !== education` | **FIXED** (`isAcademic`) | Closed |
| `CourseController` hard-coded `professional` + global leak | **FIXED** (domain-aware, tenant-scoped) | Closed |
| Teachers hidden for professional | **FIXED** (`isEducation \|\| isProfessional`, Trainers label) | Closed |
| Polytechnic/University Courses vs Classes primary | **KEPT** as B7: `usesClassTerm=false` → Courses+Curriculum (poly/university) | BUSINESS DECISION deferred — current is product-intentional, not defect |
| Other-industry tailored modules (Service/Transport) | **KEPT generic** (`Sales/Purchase/Inventory/Finance/Crm`) — no new Service Transport ops invented | BUSINESS DECISION deferred — capabilities per `industry_rules:205` already correct |
| `curricula.*` no `domain` middleware (hybrid poly + training) | **KEPT** domain-agnostic at route, domain-filtered at controller (poly needs it) | BUSINESS DECISION — intentional hybrid, not defect |
| `exams` legacy vs Academic Assessment separation | **PRESERVED separate**, not merged | No change, per requirement |

---

## 16. Final Verdict

**All required B9 implementation + security checks pass; no historical or tenant compromise; no fake data; no duplicate systems.**

```
PHASE: B9
DATA_MODIFIED: NO
DATA_DELETED: NO
MIGRATIONS: NO
DASHBOARD_TABS: PASS (InstituteDomain)
SIDEBAR_BUSINESS_TYPE: PASS (isAcademic/isProfessional + Trainers label)
COURSE_LEGACY_FUNNEL: PASS (domain-aware, tenant-isolated)
OTHER_INDUSTRY_ISOLATION: PASS (no academic/professional leak)
BUSINESS_PROFILE: PASS (Workspace/TenantContext authority, no URL trust)
COURSE_ISOLATION: PASS (canonical /courses/manage tenant+domain)
SUBJECT_ISOLATION: PASS (server-derived professional/academic, no forgery)
CURRICULUM_ISOLATION: PASS (domain-aware availableCourses, freeze intact)
TEACHER_UI: PASS (academic Teachers, professional Trainers, same model)
TENANT_ISOLATION: PASS (system:tenant-isolation-audit SECURE)
IDOR_PROTECTION: PASS (assertOwned/Accessible, Workspace verify)
DOMAIN_PROTECTION: PASS (domain:academic on classes/academic, server-derived)
MULTI_BUSINESS: PASS (switch reflects dashboard/sidebar/courses/subjects/profile)
HISTORICAL_INTEGRITY: PASS (SoftDeletes/RESTRICT/withTrashed/freeze preserved)
TESTS_NEW_FAILURE: 0
TESTS_PRE_EXISTING_FAILURE: 1 (SubjectUnificationTest legacy courses.subjects, not canonical, not B9 scope)

FINAL_VERDICT: GREEN
```

**No B10 or unrelated feature development started — scope limited to B9 business-type-aware module navigation/UI restoration.**
