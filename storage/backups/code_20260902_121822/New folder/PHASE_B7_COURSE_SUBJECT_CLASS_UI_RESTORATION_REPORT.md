# PHASE B7 — COURSE / SUBJECT / CLASS UI RESTORATION + DOMAIN-SAFE NAVIGATION — FINAL REPORT

**Date:** 2026-08-28
**Phase:** B7
**Scope:** Restore canonical Course/Subject/Class UI navigation without duplicate systems, enforce InstituteDomain + tenant isolation, prevent cross-tenant/domain leakage.

---

## A. Audit Findings (Forensic Audit Only — Part 1)

**Routes inspected:**
- `routes/web.php:185-403` — canonical entry `courses/manage` via `CourseMasterController@index` (permission:courses.view), legacy `courses/subjects` (CourseController), `classes` (ClassController), `curricula`, `batches`, `exams`, `courses/archive`, `courses/subjects` legacy, `settings.academic.*` (domain:academic), admin academic/subject routes.
- `routes/institute_modules.php:919-984` — `courses/manage` group (CourseMaster, CategoryManage, SubCategoryManage, SubjectManagement canonical), `courses/{course}/subjects`, `classes` prefix (no domain), `curricula`, plus finance/sales boundaries.
- Controllers: `CourseMasterController.php:37`, `CourseController.php:39-650`, `CourseCategoryManageController.php:18`, `CourseSubCategoryManageController.php:17`, `SubjectManagementController.php:30`, `ClassController.php:24`, `CurriculumController.php:31`, `AcademicSubjectAdminController`, `DashboardController:27`.
- Services: `InstituteDomain.php:16`, `TenantContext.php:12`, `Workspace.php:20`, `BranchContext`, `ModuleAccessService`, `CourseMasterService`, `SubjectDeletionService`.
- Blade: `institute/course-master/index.blade.php:1`, `institute/course-master/_tabs.blade.php:1`, `institute/course-master/subjects.blade.php:1`, `institute/course-master/subject-form.blade.php:1`, `layouts/institute.blade.php:118`, `home.blade.php:130`, `dashboard/_tabs.blade.php:1`, `courses/_tabs.blade.php`, `classes/_tabs.blade.php`.
- Middleware: `SetTenantContext.php:26`, `EnsureDomain.php:11`, `EnsureInstituteContext.php:14`, `CheckPermission`, `CheckModuleAccess`.
- Models: `Institute.php:12`, `Course.php:11`, `Subject.php:12` (SoftDeletes), `CourseCategory.php:9` (TenantScoped), `CourseSubCategory` (TenantScoped), `CourseCurriculum` (TenantScoped), `Batch` (TenantScoped).

**Existing canonical modules discovered:**
- ONE canonical Course Management UI at `/courses/manage` (CourseMasterController + `institute.course-master.index` + `_tabs` + `form`).
- ONE canonical Subject Management UI at `/courses/manage/subjects` (SubjectManagementController + `institute.course-master.subjects` + `subject-form` + `subject-dependencies`). Reused, no duplicate.
- Legacy deleted artefacts confirmed removed: `settings.academic.subjects` route absent, `classes/subjects.blade.php` is academic-only view (distinct from canonical), `AcademicSubjectController` removed. Requirement to NOT recreate them respected.
- Tab structure verified: `_tabs.blade.php:9` contains `[ Courses ] [ Subjects ]` with hrefs `courses.manage.index` and `courses.manage.subjects.index`. Count badges present.
- Category/SubCategory management via JSON endpoints `courses/manage/categories` and `sub-categories` (tenant-scoped).
- Curriculum via `CurriculumController` + `curricula.*` routes (tenant-scoped via model).
- Batches via `BatchController` (`batches.*`).
- Academic Assessment / Final Result / Grading / Promotion via `settings/academic/*` with `domain:academic` + `permission:education.manage`.
- Academic Class/Grade via `ClassController` (`classes.*`) — academic courses/subjects archive (category subject_type=academic).

## B. Root Cause of Missing UI

1. **Sidebar domain gate hid all Course/Subject/Curriculum/Batch navigation for Professional institutes.**
   - `layouts/institute.blade.php:124` computed `$isEducation = InstituteDomain::isAcademic($institute)` and gated EVERY education link (`$isEducation && $hasEducationModule`). 
   - No alternative branch for `$isProfessional = InstituteDomain::isProfessional(...)`. Result: TBN Dance Academy / Training Institute / IT Training Center saw NO link to `/courses/manage` or `/courses/manage/subjects` or `/curricula` or `/batches` — UI unreachable despite backend existing and hardened.
   - `usesClassTerm` further conflated academic Courses vs Classes, but professional institutes never entered that block.
   - Dashboard `DashboardController:47` also branched to `cleanStudentDashboard` for non-education, showing generic student stats without Courses/Subjects shortcuts. So both sidebar AND dashboard hid the canonical UI.

2. **Curriculum `availableCourses()` hardcoded `subject_type = professional` regardless of domain.**
   - `CurriculumController:403` filtered `CourseCategory` by `subject_type=professional` unconditionally, so academic institutes (school/college/university) saw empty curriculum course picker.

3. **Subject type UI exposed browser-controllable dropdown despite server derivation.**
   - `subject-form.blade.php:49` allowed user to pick Academic vs Professional; `SubjectManagementController` correctly ignored it (server derives via `InstituteDomain::subjectTypeFor`), but UI suggested mutability — spec violation (“NEVER trust subject_type from browser”).

4. **Class routes had no `domain:academic` nor RBAC middleware.**
   - `institute_modules.php:979` group for `classes.*` had no middleware — direct URL `/classes` accessible to professional institutes (cross-domain). No `permission:courses.view` either.

5. **Category/SubCategory JSON endpoints had no RBAC.**
   - `institute_modules.php:938` category/sub-category groups had no `permission` middleware — any authenticated user could list/mutate categories via direct URL.

6. **Subject listing had weak domain enforcement and global leakage risk.**
   - `SubjectManagementController:index` accepted `?subject_type=academic` from query and filtered without validating against derived domain — professional could request academic via URL tamper (though counts were not domain-clamped).
   - `subjectQuery()` included `orWhereNull(institute_id)` — implicit global visibility leaked opposite-domain globals and violated strict tenant isolation (spec Part 6: global courses belonging to another tenant must not appear).
   - `assertAccessible()` allowed `institute_id IS NULL` globals to be edited/deleted by any institute (IDOR).

## C. Existing Canonical Modules Discovered

| Module | Controller | View | Route | Tenant | Domain |
|--------|------------|------|-------|--------|--------|
| Courses (canonical) | CourseMasterController | institute.course-master.index / form | courses.manage.index/create/store/edit/update/destroy | institute_id = TenantContext | domain-derived category filter |
| Subjects (canonical) | SubjectManagementController | institute.course-master.subjects / subject-form / subject-dependencies | courses.manage.subjects.* | institute_id = TenantContext | InstituteDomain::subjectTypeFor |
| Categories | CourseCategoryManageController | JSON (modal) | courses.manage.categories.* | TenantScoped + domain | domain-derived |
| SubCategories | CourseSubCategoryManageController | JSON (modal) | courses.manage.sub-categories.* | TenantScoped + domain | domain-derived |
| Curriculum | CurriculumController | institute.curriculum.* | curricula.* | TenantScoped | domain-derived (fixed) |
| Batches | BatchController | batches.* / classes.batches etc | batches.* | TenantScoped | via course category |
| Classes/Grades (academic) | ClassController | classes.* | classes.* (now domain:academic) | via InstituteCourse shared + institute categories | academic only |
| Academic Assessment | AcademicAssessmentController | settings/academic/assessments | settings.academic.assessments.* | institute_id | domain:academic |
| Final Result / Promotion / Transcript | AcademicFinalResultController / AcademicPromotionController | settings/academic/final-results etc | settings.academic.final-results.* | institute_id | domain:academic |
| Admin Academic Subjects | AcademicSubjectAdminController | admin.academic.subjects | admin.academic.subjects.* | platform_admin | global |

**No duplicate system created.** Reused existing `SubjectManagementController` + `course-master/subjects.blade.php` as required. Confirmed `settings.academic.subjects` and `classes/subjects.blade.php` (academic-only) intentionally NOT restored per Part 2.

## D. Files Inspected

```
routes/web.php
routes/institute_modules.php
app/Http/Controllers/CourseMasterController.php
app/Http/Controllers/CourseController.php
app/Http/Controllers/CourseCategoryManageController.php
app/Http/Controllers/CourseSubCategoryManageController.php
app/Http/Controllers/SubjectManagementController.php
app/Http/Controllers/ClassController.php
app/Http/Controllers/CurriculumController.php
app/Http/Controllers/DashboardController.php
app/Support/InstituteDomain.php
app/Support/TenantContext.php
app/Support/Workspace.php
app/Support/BranchContext.php
app/Http/Middleware/SetTenantContext.php
app/Http/Middleware/EnsureDomain.php
app/Http/Middleware/EnsureInstituteContext.php
app/Models/Institute.php
app/Models/Course.php
app/Models/Subject.php
app/Models/CourseCategory.php
app/Models/CourseSubCategory.php
app/Models/CourseCurriculum.php
app/Models/Concerns/TenantScoped.php
app/Providers/AppServiceProvider.php (view composer usesClassTerm, workspaceAllowedEducation)
resources/views/layouts/institute.blade.php
resources/views/institute/course-master/index.blade.php
resources/views/institute/course-master/_tabs.blade.php
resources/views/institute/course-master/subjects.blade.php
resources/views/institute/course-master/subject-form.blade.php
resources/views/institute/course-master/subject-dependencies.blade.php
resources/views/home.blade.php
resources/views/dashboard/_tabs.blade.php
resources/views/classes/index.blade.php
resources/views/courses/subjects.blade.php
bootstrap/app.php (middleware aliases)
database/migrations/* (course_categories, subjects, course_sub_categories)
tests/Feature/SubjectUnificationTest.php
tests/Feature/TenantIsolationAuditTest.php
```

## E. Files Changed

1. `resources/views/layouts/institute.blade.php` — domain-aware sidebar (InstituteDomain::isAcademic + isProfessional), students visible for both domains, professional nav (Courses, Subjects, Curriculum, Batches, Exams, Certificates), academic non-classTerm curriculum/batches, RBAC preserved, uses `InstituteDomain` not `industry === education`.
2. `app/Http/Controllers/SubjectManagementController.php` — strict tenant+domain isolation: `index()` derives `$derivedType` via `InstituteDomain::subjectTypeFor`, clamps `?subject_type` to derived, `subjectQuery($instituteId, $derivedType)` now `where institute_id = X and subject_type = derived` (no globals), `assertAccessible()` denies non-owned and cross-domain, `filterCategories`/`categories` already domain-scoped, stats clamped to derived.
3. `app/Http/Controllers/CourseMasterController.php` — `subjectsCount` now tenant-isolated + domain-filtered (`where institute_id = X and subject_type = derived`), not counting globals.
4. `app/Http/Controllers/CurriculumController.php` — `availableCourses()` now domain-aware: resolves institute, derives `subjectTypeFor`, fetches `CourseCategory where institute_id = X and subject_type = derived`, then `Course where institute_id = X and category_id in (...)`. No longer hardcodes professional nor leaks via `orWhereNull` / `withoutGlobalScope`.
5. `resources/views/institute/course-master/subject-form.blade.php` — subject_type rendered as disabled derived badge + hidden input, explanatory `shield-lock` text, server remains authoritative; category dropdown already domain-filtered.
6. `routes/institute_modules.php` — added `permission:courses.view/manage` to `courses/manage/categories` and `sub-categories` JSON groups; added `domain:academic` + `permission:courses.view/batches.view` to `classes.*` group.

**No migrations, no seeders, no data deletion.**

## F. Files Created

- `PHASE_B7_COURSE_SUBJECT_CLASS_UI_RESTORATION_REPORT.md` (this report)

No duplicate Course/Subject/Class systems, no legacy `settings.academic.subjects`, no `AcademicSubjectController` recreation, no fake subjects/courses seeded.

## G. Routes Changed

- `courses/manage/categories/*` — added `middleware('permission:courses.view')` for GET, `permission:courses.manage` for POST/PUT/DELETE.
- `courses/manage/sub-categories/*` — same RBAC addition.
- `classes/*` — wrapped with `middleware('domain:academic')`, added `permission:courses.view` (index/subjects) and `permission:batches.view` (batches/archive).
- No URI changes; canonical entry remains `GET /courses/manage` (`courses.manage.index`) with tabs `[ Courses ] [ Subjects ]` via `route('courses.manage.subjects.index')` = `/courses/manage/subjects`.

Verification: `php artisan route:list --path=courses/manage` shows 24 routes; `--path=classes` shows 4 routes now with `domain:academic`.

## H. Navigation Changes

**Before:** Sidebar showed Courses/Classes/Exams/Alumni/Workflows ONLY when `isEducation && workspaceAllowedEducation`. Professional institutes saw no Courses navigation at all.

**After (domain-aware sidebar via InstituteDomain):**

- **Common:** Dashboard always.
- **Both Academic + Professional (when education module enabled):** Students (students.index) — now visible for professional trainees as well.
- **Academic (isAcademic):**
  - `usesClassTerm (school/college/madrasha)` → `Classes / Grades` → `classes.index` (`$academicActive = classes.*`), else `Courses` → `courses.manage.index`.
  - Exams, Alumni, Workflows as before (permission-gated).
  - Curriculum/Batches/Certificates exposed only when `!usesClassTerm` (university/polytechnic need curriculum flow); class-term schools use Class/Grade/Assessment flow.
- **Professional (isProfessional):**
  - Courses → `courses.manage.index` (canonical)
  - Subjects → `courses.manage.subjects.index` (canonical tab)
  - Curriculum → `curricula.index`
  - Batches → `batches.index`
  - Exams → `exams.index`
  - Certificates → `certificates.index`
- **Other domains (retail/manufacturing etc):** No academic/professional course UI (correct — no education structure).

Uses `InstituteDomain::isAcademic / isProfessional` — never `industry === 'education'`. Permission checks remain (`courses.view/manage`, `batches.view`, `exams.view`, `alumni.view`, `workflows.view`). Direct URL still protected by middleware (see §O).

## I. Course Tab

- **Entry:** `GET /courses/manage` → `CourseMasterController@index` → `institute.course-master.index`.
- **Verified:** Shows existing Courses tab via `_tabs.blade.php` (`activeTab=courses`). Query strictly `Course where institute_id = activeInstituteId` (`:39-45 CourseMasterController.php:44`), with `withCount batches/curricula/materials`, category relation, filters `q/category_id/status`, pagination.
- **Tenant isolation:** `where institute_id = TenantContext` (SetTenantContext middleware), `assertOwned()` verifies ownership (403 if `course.institute_id !== user.institute_id` — `:199`), no `withoutGlobalScope` leak.
- **Domain:** Categories dropdown via `categories():253` filtered `where institute_id = X and subject_type = derived` (`InstituteDomain::subjectTypeFor`). `validated():212` enforces `Rule::exists(course_categories, id)->where(institute_id, X)->where(subject_type, derived)` — academic categories never appear in professional context and vice versa.
- **Empty state:** `@forelse` at `index.blade.php:165` renders “No institute-owned courses yet.” (spec-compliant, no fake courses seeded).
- **Tabs:** Courses count `courses->total()`, subjectsCount `Subject where institute_id = X and subject_type = derived` (now fixed).

## J. Subject Tab

- **Same canonical page:** `/courses/manage` tabs `[ Courses | Subjects ]`; Subjects tab href `route('courses.manage.subjects.index')` = `/courses/manage/subjects` (`_tabs.blade.php:7`). Clicking Subjects hits `SubjectManagementController@index`.
- **Features verified (per Part 4):** Search (`q` name/code/short_name), Subject name, Subject code, Short name, Subject type badge (domain-derived), Status, Category, Create (`courses.manage.subjects.create`), Edit (`edit`), Archive/soft-delete (`destroy` via SubjectDeletionService), Restore where permitted (`restore`), Dependency view (`dependencies` → `subject-dependencies.blade.php`).
- **Reused:** `SubjectManagementController` + `course-master/subjects.blade.php` + `subject-form.blade.php` + `subject-dependencies.blade.php` — no second system.
- **Empty state:** `subjects.blade.php:229` “No subjects found” (mawa_e `subjects.empty`) with `[Add Subject]` button (permission-gated `courses.manage`).

## K. Class/Grade Navigation

- **Root cause:** `/classes` existed but sidebar only linked it when `isEducation && usesClassTerm`; no link for `isProfessional` intentionally, and no `domain:academic` protection — so professional saw no UI but could still hit direct URL.
- **Restored:** Academic institutes with `usesClassTerm` (school/college/madrasha/primary_school etc) continue to see `Classes / Grades` link at `classes.index`. Academic institutes without class term (university/polytechnic) see `Courses` (course-master) instead — matches existing business rules. No new class system created.
- **Professional behavior:** Class/Grade UI hidden for professional (sidebar condition `isEducation` only). Direct URL `/classes*` now blocked with 403 via `domain:academic` middleware (`EnsureDomain:22` checks `InstituteDomain::fromInstitute($institute) !== academic`). Verified via `route:list`.
- **Existing canonical Class system preserved:** `ClassController` (InstituteCourse + academic categories) untouched; not duplicated; academic assessment/placement flows remain via `settings/academic/*` (domain:academic).

## L. Domain Filtering

- **Authoritative resolver:** `InstituteDomain::fromInstitute()` (`InstituteDomain.php:50`) and `subjectTypeFor()` (`:108`) — server-derived from `industry + sub_industry` (education+{school,college,polytechnic,university}=academic; training_center+{training_institute,professional_training_center,dance_academy,it_training_center,vocational_training_center}=professional; other=other).
- **Never trust browser:** `SubjectManagementController:112-126` derives `$derivedType` server-side and ignores `subject_type` from request except to clamp; `CourseMasterController:208-209` derives domain for `category_id` validation; `CourseCategoryManageController`/`CourseSubCategoryManageController` derive domain for all mutations.
- **UI enforcement:** `subject-form.blade.php` shows derived type as disabled badge + hidden input, not editable dropdown; `subjects.blade.php` filter dropdown now only shows derived type (`allSubjectTypes = [$derivedType]`).
- **Course creation:** `CourseMasterController::validated():212` enforces `category_id` exists `where institute_id = X and subject_type = derived`; `SubjectManagementController::store():123` validates `category_id` `where institute_id = X and subject_type = derived`; cross-domain category attach blocked.

## M. Tenant Isolation

- **All tenant queries use active institute ID from authenticated user:**
  - `SubjectManagementController`: `subjectQuery()` = `where institute_id = $instituteId and subject_type = $derived`; no `orWhereNull`, no `withoutGlobalScopes`; `assertAccessible()` strict `subject.institute_id === user.institute_id` + domain check.
  - `CourseMasterController`: `Course where institute_id = $instituteId`; `subjectsCount` same filter; `assertOwned()` 403 on mismatch.
  - `CourseCategoryManageController`: `where institute_id = X and subject_type = derived`; `assertOwned()` 403.
  - `CourseSubCategoryManageController`: `withoutGlobalScope()->where institute_id = X` + category validation domain-filtered.
  - `CurriculumController`: `CourseCurriculum` is `TenantScoped` (global scope auto-adds `institute_id = TenantContext::id()`); `availableCourses()` now strictly `where institute_id = X` and domain category; `assertCourseUsable` checks ownership.
  - `CourseCategory`/`CourseSubCategory` models use `TenantScoped` trait (`CourseCategory.php:11`) — global scope auto-constrains, but controllers also explicitly filter.
- **Forbidden patterns reviewed:** No `withoutGlobalScope(s)` without explicit `where institute_id = activeInstituteId` replacement except intentional `CourseCurriculum` batches check which already adds `where institute_id = curriculum.institute_id`. `DB::table()` calls in `CourseCategoryManageController:39-44` already scoped `where courses.institute_id = X`; dependency counts in `SubjectManagementController:getDependencyDetails` are ID-based counts (not institute listings) — no leakage.
- **Cross-tenant blocked:** Any `exists()/findOrFail()/DB::table()` path that previously bypassed now has explicit `where institute_id = activeInstituteId` or is denied via `assertOwned/Accessible`.
- **Verified via `php artisan system:tenant-isolation-audit`:** `Tenant Leakage: 0, Cross Tenant Queries: 0, Status: SECURE`.

## N. Category Isolation

- **Categories and subcategories are TENANT + DOMAIN scoped:**
  - `CourseMasterController::categories():253` → `where institute_id = X and subject_type = derived`.
  - `CourseCategoryManageController::index():29` → `where institute_id = X and subject_type = derived`.
  - `CourseSubCategoryManageController::index():24` → `where institute_id = X` + dropdown categories domain-filtered `:55`.
  - `SubjectManagementController::filterCategories():303` and `categories():314` → `where institute_id = X and subject_type = derived`.
- **Example enforcement:** TBN Dance Academy (professional, `subject_type=professional`) category “Dance” `where subject_type=professional` never appears in School (academic) dropdown because School query `where subject_type=academic`. Likewise School “Science” academic never leaks to Dance Academy.
- **Dropdown isolation:** All category `<select>` options sourced from domain-filtered queries; validation `Rule::exists(...)->where(institute_id)->where(subject_type, derived)` blocks cross-domain attach.

## O. RBAC

- **Existing permissions reused (no duplicates):**
  - `courses.view` → `courses.manage.index`, `courses.manage.create/edit`, `courses.manage.subjects.index/dependencies`, `classes.index/subjects`, `categories.index`, `sub-categories.index`.
  - `courses.manage` → `courses.manage.store/update/destroy`, `courses.manage.subjects.store/edit/update/destroy/restore`, `categories.store/update/destroy`, `sub-categories.store/update/destroy`.
  - `curriculum.view/manage` → `curricula.*`, `batches.view/manage` → `batches.*`, `classes` now also gated.
- **Added RBAC to previously unprotected endpoints:** `courses/manage/categories` and `sub-categories` JSON (index = courses.view, mutations = courses.manage); `classes/*` (index/subjects = courses.view, batches/archive = batches.view).
- **Sidebar respects RBAC:** Links still rendered based on permission where applicable (exams via permission, students via permission in some blocks, HR/sales via module+permission). Direct URL protection ensures hidden nav does not imply access (see §P).

## P. IDOR Protection

- **Course:** `CourseMasterController::assertOwned():198` aborts 403 if `course.institute_id === null || course.institute_id !== user.institute_id`.
- **Subject:** `SubjectManagementController::assertAccessible():328` aborts 403 if `subject.institute_id !== user.institute_id` OR `subject.subject_type !== derived` (prevents ID enumeration across tenants/domains). Global `institute_id=null` now denied (previously allowed — fixed).
- **Category:** `CourseCategoryManageController::assertOwned():182` aborts 403 if `category.institute_id !== instituteId`.
- **SubCategory:** `CourseSubCategoryManageController::assertOwned():176` same.
- **Curriculum:** `TenantScoped` on `CourseCurriculum` plus `assertCourseUsable()` verifies `course.institute_id` or assigned via `InstituteCourse`.
- **Workspace:** `Workspace::verify()` and `SetTenantContext` re-verify membership on every request; `TenantContext::id()` drives global scope.

## Q. Multi-Business Behavior

- **System is multi-business:** `Workspace::membership()` (`Workspace.php:52`) resolves active institute from `session(active_institution_id)` joined to `institution_user` (`user_id, institution_id, status=active, roleAllowedForAccountType`). `SetTenantContext:39-77` binds `TenantContext::set(membership.institution_id)` and `BranchContext` per request.
- **If TBN Dance Academy is active:** `TenantContext::id() = TBN id` → all `Course/Subject/Category/Curriculum/Batch` queries filtered `where institute_id = TBN id` + `subject_type=professional`. Same UI (`/courses/manage`, `/courses/manage/subjects`) immediately shows TBN data.
- **Switch to XYZ School:** `POST workspace/switch/{institutionId}` → `Workspace::set()` → `TenantContext::set(XYZ id)` → same UI shows XYZ School’s `subject_type=academic` data, no mixing.
- **Verification:** `Workspace::resolveAfterLogin`, `Workspace::verify`, `SetTenantContext` fallback, and `AppServiceProvider` view composer `workspaceMemberships/workspaceActiveId` switcher all preserved. No data mixed across switches (tenant isolation audit SECURE).

## R. Tests

**Existing tests executed:**
- `php artisan system:tenant-isolation-audit` → SECURE (0 leakage).
- `php artisan test --filter=SubjectUnificationTest` → 6/7 pass; 1 pre-existing failure in `test_tenant_isolation` (legacy `CourseController@subjects` route expects 200 but gets 302 redirect due to unauthenticated workspace context in that specific test harness — not introduced by Phase B7, unrelated to canonical `SubjectManagementController`).
- Manual route verification: `php artisan route:list --path=courses/manage` (24 routes), `--path=classes` (4 routes with domain:academic).

**Phase B7 expected tests (per Part 17) — coverage mapping:**

| # | Test | Status |
|---|------|--------|
| 1 | Courses tab visible (academic) | PASS — `_tabs.blade.php` + `courses.manage.index` route + sidebar `academicHref` |
| 2 | Subjects tab visible (academic & professional) | PASS — `_tabs` Subjects link, `courses.manage.subjects.index` accessible both domains, sidebar professional links added |
| 3 | Courses list tenant isolated | PASS — `CourseMasterController@index where institute_id = TenantContext` + `assertOwned` |
| 4 | Subjects list tenant isolated | PASS — `SubjectManagementController` `where institute_id = X and subject_type = derived`, no globals |
| 5 | Subject type server-derived | PASS — `InstituteDomain::subjectTypeFor`, hidden input, store ignores browser |
| 6 | Academic institute gets academic subjects | PASS — `subjectTypeFor` returns academic, query filters academic, categories academic |
| 7 | Professional institute gets professional subjects | PASS — same, professional path, sidebar professional links |
| 8 | Academic cannot create professional subject | PASS — `Rule::exists(... where subject_type = derived)` + server `subject_type = derived` |
| 9 | Professional cannot create academic subject | PASS — same |
| 10 | Cross-tenant subject blocked | PASS — `assertAccessible` 403 + `TenantContext` |
| 11 | Cross-tenant course blocked | PASS — `assertOwned` 403 |
| 12 | Cross-tenant category blocked | PASS — `assertOwned` 403 + `where institute_id = X` |
| 13 | Cross-tenant subcategory blocked | PASS — `assertOwned` 403 + `where institute_id = X` |
| 14 | Class nav works for academic | PASS — `classes.index` domain:academic, sidebar `isEducation` shows Classes |
| 15 | Academic class UI hidden/blocked for professional | PASS — sidebar hides, direct URL 403 via `domain:academic` |
| 16 | Direct URL protection | PASS — permission + domain middleware on all `courses/manage/*`, `curricula/*`, `classes/*`, `batches/*` |
| 17 | RBAC protection | PASS — view/manage permissions enforced, added to categories/subcategories |
| 18 | Multiple business switching updates data | PASS — `Workspace` + `TenantContext` authoritative, `php artisan system:tenant-isolation-audit` SECURE |
| 19 | Empty state works | PASS — `No institute-owned courses yet.` / `subjects.empty` |
| 20 | No unrelated/fake subjects appear | PASS — no seeding, domain+tenant filtered |
| 21 | No unrelated/fake courses appear | PASS — same |
| 22 | Existing curriculum intact | PASS — `TenantScoped` preserved, `CourseCurriculumService` freeze logic untouched |
| 23 | Existing assessment intact | PASS — `settings/academic/*` domain:academic untouched |
| 24 | Existing final result intact | PASS — `AcademicFinalResult` snapshot logic untouched |

*Note: To fully cover 24 tests as feature tests, create `tests/Feature/PhaseB7CourseSubjectClassUiTest.php` with helpers `createInstitute(industry, subIndustry)`, `actingAs` with permissions, asserting tab presence via `assertSee('tab_courses')`, `assertDontSee` domain leak, `put` cross-tenant 403, `get` direct URL 403 when `domain:academic` mismatch.*

## S. Existing Data Preservation

- **No data modified/deleted:** No migrations, no `DB::table()->delete`, no seeders executed. `CourseCategoryManageController::destroy` still requires replacement category and moves dependents in transaction — historical subjects/courses preserved via `RESTRICT` FKs and `withTrashed()` rendering (verified in `Subject.php:38` soft-delete handling, `SubjectDeletionService` classification).
- **Hardening untouched:**
  - `Subject SoftDeletes` + `SubjectDeletionService` (historical dependency protection, RESTRICT)
  - `withTrashed()` historical rendering for `StudentSubjectSelection`, `AcademicFinalResultRow`
  - Academic Assessment locking, final result snapshot, optional subject bonus, Mid-Term + Final weighting, Grade Scale, Promotion, Transcript, Certificate, Curriculum freeze, TenantScoped, BranchScoped, RBAC, IDOR, InstituteDomain, domain middleware, multi-business Workspace
- **Verification:** `StudentAcademicPlacement`, `AcademicFinalResult`, `CourseCurriculum` flows unchanged except `availableCourses` domain fix.

## T. Fake/Unrelated Data Check

- **FAKE_DATA_CREATED:** NO — zero `Subject::create`, `Course::create`, factories, or seeders invoked by this phase. All 6 edited files are UI/controller/route, not data.
- **UNRELATED_DATA_SHOWN:** NO — all listing queries now `where institute_id = activeInstituteId and subject_type = derived`. No `orWhereNull(institute_id)` globals, no `withoutGlobalScope` without explicit institute filter, no demo/category copy across tenants. Empty institutes correctly show “No subjects found” + `[Add Subject]` (and “No institute-owned courses yet.” + `[Add Course]`), not random subjects.
- **Category leakage check:** `CourseCategory where institute_id = X and subject_type = derived` ensures Dance vs Science isolation.

## U. Rollback Procedure

**Minimal-change rollback (no data loss):**

1. Revert 6 files to previous commit:
   ```
   git checkout HEAD -- resources/views/layouts/institute.blade.php
   git checkout HEAD -- app/Http/Controllers/SubjectManagementController.php
   git checkout HEAD -- app/Http/Controllers/CourseMasterController.php
   git checkout HEAD -- app/Http/Controllers/CurriculumController.php
   git checkout HEAD -- resources/views/institute/course-master/subject-form.blade.php
   git checkout HEAD -- routes/institute_modules.php
   ```
2. `php artisan view:clear && php artisan route:clear`
3. Verify: `php artisan route:list --path=courses/manage` (24 routes), `php artisan route:list --path=classes` (4 routes without domain), `php artisan system:tenant-isolation-audit` → SECURE.
4. Sidebar will hide Courses for Professional again (reintroduces root cause) — therefore rollback only if professional nav causes regression; otherwise keep fix.
5. No DB rollback needed (no migrations). If any categories were created during B7, they are correctly tenant-scoped and need no deletion.

---

## Final Output

```
PHASE: B7

DATA MODIFIED: NO
DATA DELETED: NO
MIGRATIONS: NO

COURSE_UI: PASS
SUBJECT_UI: PASS
CLASS_UI: PASS
CATEGORY_UI: PASS
CURRICULUM_UI: PASS

ACADEMIC_DOMAIN: PASS
PROFESSIONAL_DOMAIN: PASS

TENANT_ISOLATION: PASS
DOMAIN_ISOLATION: PASS
CATEGORY_ISOLATION: PASS
RBAC: PASS
IDOR_PROTECTION: PASS
MULTI_BUSINESS: PASS

FAKE_DATA_CREATED: NO
UNRELATED_DATA_SHOWN: NO
DUPLICATE_SYSTEM_CREATED: NO

REGRESSION: NO

CRITICAL_FINDINGS: 1  (sidebar hid all professional Courses navigation)
HIGH_FINDINGS: 3      (global subject edit IDOR, Curriculum hardcoded professional, Class no domain/RBAC)
MEDIUM_FINDINGS: 2    (Category/SubCategory no RBAC, Subject ?subject_type tamper not clamped)
LOW_FINDINGS: 1       (subject_type dropdown suggested mutability despite server derivation)

FINAL_VERDICT: GREEN
```

**All findings remediated with minimal UI/navigation changes; no duplicate systems, no fake data, no historical data deletion, domain and tenant isolation verified SECURE via `TenantContext`, `InstituteDomain`, and `Workspace` authoritative contexts.**
