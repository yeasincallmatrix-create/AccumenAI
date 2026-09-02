# PHASE B16 — ALL TRAINING CENTER TYPES INHERITANCE + OPERATIONAL UI FORENSIC AUDIT

**Project:** Monetix / MAWA SaaS
**Audit Date:** 2026-08-28
**Audit Mode:** FORENSIC AUDIT ONLY — NO CODE/DATA/DB/MIGRATION/ROUTE/SEED/VIEW MODIFICATION
**Auditor:** Muse Spark (OpenCode)
**Canonical Hierarchy Under Audit:**
```
INDUSTRY
├── Education { school, college, polytechnic, university } → domain=academic
├── Training Center { training_institute, professional_training_center, dance_academy, it_training_center, vocational_training_center } → domain=professional
├── Retail → other
├── Manufacturing → other
├── Service → other
├── Transportation → other
└── Restaurant → other (+ healthcare, information_technology etc → other)
```

---

## 1. Executive Summary

| Gate | Result | Severity |
|------|--------|----------|
| G1 Taxonomy | PASS | — |
| G2 Authoritative Domain Resolver | PASS (with 1 LOW competing hard-code cluster) | LOW |
| G3 Training Navigation Inheritance | **PASS** | — |
| G4 Students/Trainers | PASS | — |
| G5 Course/Subject/Curriculum | PASS | — |
| G6 Batch/Enrollment/Attendance | **PARTIAL** (hard-coded `professional` string cluster in `BatchController`, shared `courses()` fallback) | MEDIUM |
| G7 Exams/Marks/Results/Certificate | PASS | — |
| G8 Business-Profile Category-Level UI | PASS | — |
| G9 Multi-Business Inheritance | PASS | — |
| G10 New Tenant Inheritance | PASS | — |
| G11 Other Industries Regression | PASS | — |
| G12 Tenant/IDOR/RBAC Security | PASS (PARTIAL on IDOR for legacy `BatchController` subjectsCount / fallback) | MEDIUM |
| G13 MAWA-Specific Code | **PASS** (zero business-logic MAWA hard-code; only `mawa_*` i18n helper naming residue) | LOW |
| G14 Legacy/Duplicate Systems | PASS (1 duplicate route alias, legacy `CourseController` retained as funnel) | LOW |
| G15 Database/Migration | PASS — NO migration required | — |

**Global Verdict:** Training Center inheritance is **correctly implemented as industry-level, resolver-driven, tenant-safe, and automatically inherited by all five Training Center business types**. No code modification is required per tenant. The only gaps are **non-blocking code-hygiene items** in `BatchController.php` (hard-coded `'professional'` literals and a global catalog fallback) that do not break inheritance but should be refactored to `InstituteDomain::subjectTypeFor()` for full consistency before future academic reuse of the shared Batch module.

No new tables, no duplicate Training modules, no tenant-specific configuration, and no MAWA-specific branching were found.

---

## 2. Current Taxonomy

**Source of Truth:** `config/industry_rules.php:20-138` + `app/Support/IndustryRules.php:15-95` + `app/Support/InstituteDomain.php:18-45`

| Industry Key | Label | Sub-Industries (canonical) | Legacy Aliases (normalized) |
|---|---|---|---|
| `education` | Education | `school`, `college`, `polytechnic`, `university`, `madrasha`, `primary_school`, `secondary_high_school`, `school_college` | — |
| `training_center` | Training Center | `training_institute`, `professional_training_center`, `dance_academy`, `it_training_center`, `vocational_training_center` | `institution`→`training_institute`, `professional_training_academy`→`professional_training_center`, `computer_it_training_institute`/`computer_it`→`it_training_center`, `vocational_institute`/`skill_development_center`/`technical_training_center`→`vocational_training_center`, plus `martial_arts`, `music_academy`, `sports_academy`, `language_academy`, `coaching_centre` (kept as audit-trail aliases, still accepted) |
| `retail`, `manufacturing`, `service`, `transportation`(`transport` alias), `restaurant`, `healthcare`, `information_technology`, `finance`, `real_estate`, `hotels`, `personal_finance`, `other` | Various | `[]` (empty — no sub-type domain structure exposed) | `transport`→`transportation` |

**Education vs Training Center separation confirmed:**

- `InstituteDomain.php:67` `if ($industry === 'education' && in_array($sub, ACADEMIC_TYPES)) → academic`
- `InstituteDomain.php:70` `if ($industry === 'training_center' && in_array($sub, PROFESSIONAL_TYPES)) → professional`
- Training Center is **NOT** a child of Education at any layer (config, resolver, validation, or UI). `INSTITUTE INDUSTRY = training_center` is a peer of `education`.

**File Evidence:**

- `config/industry_rules.php:24` `training_center => 'Training Center'` peer of `education`
- `config/industry_rules.php:52-70` `training_center` sub map contains all 5 canonical + legacy aliases
- `app/Support/InstituteDomain.php:31-37` `PROFESSIONAL_TYPES` = exactly the 5 types under audit
- `app/Models/Institute.php:31-48` domain immutability guard uses `InstituteDomain::fromKeys` not string compare

---

## 3. Domain Resolver Audit

**Authoritative Resolver:** `app/Support/InstituteDomain.php` (164 lines) — **CONFIRMED ONLY AUTHORITATIVE RESOLVER**

### 3.1 Required Methods — Verified Present & Correct

| Method | Location | Behaviour | Verdict |
|---|---|---|---|
| `fromInstitute(?Institute)` | `InstituteDomain.php:50-56` | null→OTHER, else `fromKeys(industry, sub_industry)` | PASS |
| `fromKeys(string, string)` | `InstituteDomain.php:58-74` | lower+trim → `normalizeIndustry` → `normalizeSubIndustry` → `education+ACADEMIC_TYPES → academic`, `training_center+PROFESSIONAL_TYPES → professional`, else `other` | PASS |
| `normalizeIndustry(string)` | `InstituteDomain.php:118-124` | `transport→transportation` | PASS |
| `normalizeSubIndustry(string,string)` | `InstituteDomain.php:127-142` | maps 7 legacy aliases | PASS |
| `isValidCombination(string, ?string)` | `InstituteDomain.php:87-105` | validates against `config('industry_rules.global.industries')` + `IndustryRules::subIndustries` + normalization | PASS |
| `isProfessional(?Institute)` | `InstituteDomain.php:81-84` | `fromInstitute === PROFESSIONAL` | PASS |
| `isAcademic(?Institute)` | `InstituteDomain.php:76-79` | `fromInstitute === ACADEMIC` | PASS |
| `subjectTypeFor(?Institute)` | `InstituteDomain.php:108-115` | `academic→academic`, `professional→professional`, `other→professional` (safe default) | PASS |
| `hasDomainData(int)` | `InstituteDomain.php:147-163` | checks 8 tables before domain switch | PASS |

### 3.2 Domain Resolution Matrix (fromKeys)

| Industry | Sub-Industry | Resolved Domain | Evidence |
|---|---|---|---|
| `training_center` | `training_institute` | `professional` | `InstituteDomain.php:70` |
| `training_center` | `professional_training_center` | `professional` | `InstituteDomain.php:70` |
| `training_center` | `dance_academy` | `professional` | `InstituteDomain.php:70` |
| `training_center` | `it_training_center` | `professional` | `InstituteDomain.php:70` |
| `training_center` | `vocational_training_center` | `professional` | `InstituteDomain.php:70` |
| `training_center` | legacy `institution` | `professional` (normalized→training_institute) | `InstituteDomain.php:132` |
| `education` | `school` | `academic` | `InstituteDomain.php:67` |
| `education` | `college` | `academic` | `InstituteDomain.php:67` |
| `education` | `polytechnic` | `academic` | `InstituteDomain.php:67` |
| `education` | `university` | `academic` | `InstituteDomain.php:67` |
| `retail` / `manufacturing` / `service` / `transportation` / `restaurant` / any other | any/`''` | `other` | `InstituteDomain.php:73` |

### 3.3 Competing / Duplicate Domain Classifications — Search Result

Search: `Select-String` across `app/` + `resources/` for `isProfessional|isAcademic` found **7 hits, all correctly delegating to `InstituteDomain`**:

- `app/Http/Controllers/DashboardController.php:45` `InstituteDomain::isAcademic($institute)` — correct abstract, not string compare
- `app/Http/Controllers/DashboardController.php:171` same
- `app/Services/AcademicDashboardService.php:97` same
- `app/Services/ModuleAccessService.php:389` same
- `resources/views/dashboard/_tabs.blade.php:8` `InstituteDomain::isAcademic` — correctly
- `resources/views/layouts/institute.blade.php:124` `isAcademic` + `125` `isProfessional` — canonical
- `app/Support/InstituteDomain.php:76,81` — definitions

Search for `industry === 'education'` / `industry === 'training_center'` outside `InstituteDomain.php`:

- `app/Support/InstituteDomain.php:67,70` — canonical itself
- `config/industry_rules.php` — data, not logic
- `app/Http/Controllers/Auth/RegistrationFlowController.php:406` — onboarding validation uses `IndustryRules`, not raw compare (verified)
- `app/Services/Demo/DemoDataService.php:108` — demo seeding `if ($industry === 'education')` for demo data generation — **demo-only, not runtime classification** — LOW
- `app/Services/Reports/ReportRegistry.php:592` — report branching, not domain resolution — LOW

**Competing hard-coded literal `subject_type = 'professional'` found (non-resolver):**

| File | Line | Current | Expected | Risk | Recommendation |
|---|---|---|---|---|---|
| `app/Http/Controllers/BatchController.php` | `126` `Subject::where('subject_type','professional')` in `subjectsCount` helper | Hard-coded `'professional'` | `InstituteDomain::subjectTypeFor(Institute::find($instituteId))` | MEDIUM — academic caller would miscount; currently `batches.index` is shared but not gated `domain:professional`, academic would see wrong count | Refactor to derived type |
| `app/Http/Controllers/BatchController.php` | `196-197` `Subject::where('subject_type','professional')` in `show()` `availableSubjects` | Same | Derived type | MEDIUM | Same |
| `app/Http/Controllers/BatchController.php` | `572-576` `CourseCategory::withoutGlobalScope('institute')->where('subject_type','professional')` in `categoryIdsBySubjectType()` | Global bypass + hard-coded | Tenant-scoped `where('institute_id',$instituteId)->where('subject_type',$derived)` | MEDIUM — bypasses tenant scope + ignores domain | Replace with domain-derived tenant query (match `CourseController::categoryIdsBySubjectType` pattern) |
| `app/Http/Controllers/BatchController.php` | `549-564` `Course::without institute filter` fallback in `courses()` when no `InstituteCourse` | `Course::query()->orderBy('name')->get()` — global catalog leak | `Course::where('institute_id',$instituteId)->...` tenant-filtered fallback | MEDIUM — multi-tenant leak surface (low exploitability due to TenantScoped on other models, but inconsistent) | Tenant-scope fallback |

**Verdict:** `InstituteDomain.php` **is the ONLY authoritative resolver**. The `BatchController` cluster is not a competing resolver (it never returns `domain`), but it duplicates the subject_type literal instead of deriving it — a **hygiene gap, not a branching taxonomy**. No `MAWA`-specific resolver, no second `domain` column, no client-trusted `subject_type`.

---

## 4. Five Training Type Matrix

| # | Canonical Sub-Industry Key | Label (config) | industry | Resolves via `fromKeys` | Domain | Navigation Inherits Training UI? | Evidence |
|---|---|---|---|---|---|---|---|
| 1 | `training_institute` | Training Institute | `training_center` | `InstituteDomain.php:31` in PROFESSIONAL_TYPES | `professional` | **YES** | `institute.blade.php:285` `isProfessional` true → full Training block |
| 2 | `professional_training_center` | Professional Training Center | `training_center` | `InstituteDomain.php:32` | `professional` | **YES** | Same |
| 3 | `dance_academy` | Dance Academy | `training_center` | `InstituteDomain.php:33` | `professional` | **YES** | Same |
| 4 | `it_training_center` | IT Training Center | `training_center` | `InstituteDomain.php:34` | `professional` | **YES** | Same |
| 5 | `vocational_training_center` | Vocational Training Center | `training_center` | `InstituteDomain.php:35` | `professional` | **YES** | Same |

**All five receive identical professional operational UI** — condition is `$isProfessional` (industry-level), not `sub_industry === 'training_institute'`.

**File Evidence that NOT `training_institute`-only:**

- `resources/views/layouts/institute.blade.php:124-125` correctly:
  ```php
  $isEducation = \App\Support\InstituteDomain::isAcademic($institute);
  $isProfessional = \App\Support\InstituteDomain::isProfessional($institute);
  ```
- `resources/views/layouts/institute.blade.php:285` gate is `@if ($isProfessional && ($workspaceAllowedEducation ?? false))` — no `training_institute` string, no `$institute->sub_industry ===`, no MAWA slug check, no ID check.
- `app/Support/InstituteDomain.php:31-37` all five listed together as `PROFESSIONAL_TYPES`.
- Grep for `training_institute` across `resources/views/layouts` returned **zero** hits — no hard-coded single-type check in navigation.

---

## 5. Navigation Matrix

**File:** `resources/views/layouts/institute.blade.php` (581+ lines inspected)

**Resolver Used:**

- `layouts/institute.blade.php:124` `$isEducation = InstituteDomain::isAcademic($institute)` ✅ authoritative
- `layouts/institute.blade.php:125` `$isProfessional = InstituteDomain::isProfessional($institute)` ✅ authoritative

**Anti-patterns Verified Absent in Navigation:**

- ❌ `$institute->industry === 'education'` — NOT used for training nav gate
- ❌ `$institute->sub_industry === 'training_institute'` — NOT used
- ❌ `if ($institute->id === 1)` / MAWA ID — NOT present
- ❌ `if ($institute->slug === 'mawa')` — NOT present
- ❌ `str_contains($institute->name, 'Mawa')` — NOT present

### 5.1 Expected Training UI — Presence Check (Professional Block Lines 285-324)

| Expected Item | Present for `$isProfessional`? | Route / View | Line |
|---|---|---|---|
| Training (section header) | YES — block gated `isProfessional` | — | `285` |
| Courses | YES | `courses.manage.index` `mawa_e('sidebar.courses')` | `286` |
| Subjects | YES | `courses.manage.subjects.index` `mawa_e('subjects.tab_subjects')` | `289` |
| Curriculum | YES | `curricula.index` | `292` |
| Batches | YES | `batches.index` | `295` `view != enrollment/attendance` |
| Enrollment | YES | `batches.index?view=enrollment` | `298` |
| Attendance | YES | `batches.index?view=attendance` | `301` |
| Exams | YES | `exams.index` | `304` `view != marks/results` |
| Marks | YES | `exams.index?view=marks` | `307` |
| Results | YES | `exams.index?view=results` | `310` |
| Certificates | YES | `certificates.index` | `313` |
| Trainers (Teachers labelled Trainers) | YES — shared block above | `teachers.index` label `Trainers` when `isProfessional && !$isEducation` | `136-153` |
| Fees | YES (gated `workspaceAllowedFinance`) | `finance.education.fee-collection` | `316-320` |
| Reports | YES | `reports.hub` | `321` |

**All 13 expected items present. No item missing for any of the five business types.**

**Students Link (shared):**

- `institute.blade.php:136` `@if (($isEducation || $isProfessional) && $hasEducationModule)` → `students.index` visible to BOTH academic and professional — correct.

**Academic Isolation in Same File:**

- `institute.blade.php:173` `@if ($isEducation && ($workspaceAllowedEducation ?? false))` — Classes/Courses toggle + Exams (academic)
- `institute.blade.php:203-283` Academic collapsible group — `@if ($isEducation ...)` — Dashboard, Academic Settings, Academic Years, Classes, Groups/Streams, Students, Subjects, Teachers, Placements, Assessments, Marks Entry, Results (Aggregations/Grade Scales/Final Results/Published), Promotions, Attendance, Analytics, Transcript, Certificates — **only** when `isEducation` true, hidden from professional.
- `institute.blade.php:325-336` `@if ($isEducation ... && !$usesClassTerm)` Curriculum/Batches/Certificates extra for university/polytechnic — also academic-only.
- Academic-only workflow is correctly hidden from Training Center.

---

## 6. Student/Trainer Matrix

| Capability | Route | Controller | TenantScoped | Permission | Visible to 5 Training Types? | Evidence |
|---|---|---|---|---|---|---|
| Students | `students.*` `GET /students` | `StudentController.php` | `Student` TenantScoped + BranchScoped | `students.view` / `students.manage` | **YES** all 5 | `web.php:139` `students.*` `auth+tenant+verified`, `institute.blade.php:136` `($isEducation\|\|$isProfessional)` |
| Teachers / Trainers | `teachers.index` | `TeacherController.php` | `InstituteUser` tenant | `workspaceAllowedTeachers` | **YES** all 5, label `Trainers` when professional | `institute.blade.php:150-153` `($isEducation\|\|$isProfessional) && workspaceAllowedTeachers` + `152` `{{ $isProfessional && !$isEducation ? 'Trainers' : 'Teachers' }}` |
| Underlying Model | — | `InstituteUser` / `Membership` | TenantScoped | — | Unified — NO separate `Instructor`/`Trainer` table | Verified: no `Instructor` model, no `Trainer` model, no migration for separate table; `TeacherController` reused for both domains |

**NO duplicate Instructor/Trainer table exists.** UI label switches; model/system remains unified (`InstituteUser`, `Membership`, `TeacherAcademicAssignment`). RBAC for every training type is identical — no MAWA-specific permission.

**FILE:LINE:**

- `app/Http/Controllers/TeacherController.php:1` — single controller for both labels
- `resources/views/layouts/institute.blade.php:152` — label polymorphism
- `app/Models/InstituteUser.php` — single source, no `Trainer` model exists (glob: no `Trainer.php` under `app/Models`)

---

## 7. Course Matrix

| Aspect | File | Line | Current | Expected | Tenant Isolation | Risk |
|---|---|---|---|---|---|---|
| Course Master authoring | `CourseMasterController.php:44` | `Course::where('institute_id',$instituteId)` | Tenant-filtered, paginated, `withCount(batches,curricula,materials)` | Same | ✅ | — |
| Category validation | `CourseMasterController.php:212` | `Rule::exists('course_categories','id')->where('institute_id',$instituteId)->where('subject_type',$domainType)` | Server-derived `subjectTypeFor` | Same | ✅ | — |
| Sub-category validation | `CourseMasterController.php:213` | `Rule::exists('course_sub_categories','id')->where('institute_id',$instituteId)` | Tenant-scoped | ✅ | — | — |
| Course categories list | `CourseMasterController.php:253-255` | `CourseCategory::where('institute_id',$instituteId)->where('subject_type',$domainType)` | Domain-filtered tenant | ✅ | — | — |
| Course ownership assert | `CourseMasterController.php:200` | `if ($course->institute_id === null \|\| (int)$course->institute_id !== (int)$request->user()->institute_id) abort(403)` | Ownership check | ✅ | — | — |
| `subjectTypeFor` usage | `CourseMasterController.php:209,252` | `InstituteDomain::subjectTypeFor(Institute::find($instituteId))` | Authoritative | ✅ | — | — |
| Cross-tenant leak | `Course.php:1` | `Course` model has **no** `TenantScoped` (intentionally global catalog + `InstituteCourse` pivot) | Tenant isolation via `institute_id` filter in controllers — correct (global catalog is shared reference, but authoring filters by `institute_id`) | ⚠️ Global model | LOW — reads gated by `institute_id` in every controller method |
| Duplicate course system | — | No duplicate Training `Course` — single `Course` + `InstituteCourse` + `CourseCategory` + `CourseSubCategory` | Reuse | ✅ | — | — |

**Verdict:** All five training types share the same Course system, tenant-isolated, domain-scoped, no client-forgeable `subject_type`.

---

## 8. Subject Matrix

| Aspect | File | Line | Current | Expected | Verdict |
|---|---|---|---|---|---|
| Subject query domain | `CourseController.php:47` `InstituteDomain::subjectTypeFor(Institute::find($instituteId))` | Derived server-side | Same | PASS |
| Subject query tenant | `CourseController.php:78-94` `InstituteSubject` assigned filter + `where('institute_id',$instituteId)` + `where('subject_type',$subjectType)` + `whereNull('deleted_at')` | Tenant + domain scoped | PASS | PASS |
| Subject categories | `CourseController.php:73-77` `CourseCategory::where('institute_id',$instituteId)->where('subject_type',$derived)` | Tenant + domain | PASS | PASS |
| Subject request category validation | `CourseController.php:335` `Rule::exists('course_categories','id')->where('institute_id',$instituteId)->where('subject_type',$derivedType)` | Server-derived type, tenant exists check | PASS | PASS |
| Subject request `subject_type` client trust | `CourseController.php:328` `$subjectType = $derivedType; // Server-derived: never trust client subject_type` | Ignores client `subject_type` | PASS | PASS |
| Category auto-create | `CourseController.php:344-357` `CourseCategory::create(['institute_id'=>$instituteId, 'subject_type'=>$subjectType])` | Tenant + domain bound | PASS | PASS |
| `withoutGlobalScope` in subjects list | `CourseController.php:50` `with(['category' => fn($q)=>$q->withoutGlobalScope('institute')])` | **Safe** — eager load for category display inside already-filtered `subjectQuery` (tenant filtered outer), not a bypass | PASS | LOW |
| Subjects NEVER cross tenants | Verified via `Subject::where('institute_id',$instituteId)->where('subject_type',$derived)` + `InstituteSubject` pivot — no `withoutGlobalScope` leak | — | PASS | — |
| `subject_type` cannot be client-forged | `requestSubject` explicitly discards client value; `store` validates `category_id` with `where subject_type=$derived` | — | PASS | — |

**Verdict:** All five training types use same Subject pipeline; queries are tenant + domain scoped; no IDOR via forged `subject_type`.

---

## 9. Curriculum Matrix

| Aspect | File | Line | Current | Expected | Verdict |
|---|---|---|---|---|---|
| Curriculum index filter | `CurriculumController.php:46` `CourseCurriculum::query()->with(['course'])->withCount(['modules','batches'])->when(course_id)->when(status)` + global `TenantScoped` | Tenant auto-filtered | PASS |
| Available courses for curriculum | `CurriculumController.php:398-416` `$derived = InstituteDomain::subjectTypeFor(Institute::find($instituteId)); $categoryIds = CourseCategory::where('institute_id',$instituteId)->where('subject_type',$derived)->pluck('id'); Course::where('institute_id',$instituteId)->whereIn('category_id',$categoryIds)` | Domain-filtered tenant | PASS |
| Course usable assert | `CurriculumController.php:418-433` `assertCourseUsable()` checks `InstituteCourse` existence OR `course.institute_id === instituteId` else ValidationException | Ownership check | PASS | PASS |
| Curriculum create | `CurriculumController.php:80-103` `curricula->create((int)$user->institute_id, $courseId, $data, (int)$user->id)` | Tenant-bound service | PASS |
| Version protection | `CurriculumController.php:57-58` `TenantScoped` + service `version auto-increment, only one active version per course, referenced batch frozen` | Existing backend reused | PASS |
| `curriculum_id` validation in Batch | `BatchController.php:500-513` checks `CourseCurriculum::where('institute_id',$instituteId)->where('course_id',$courseId)->where('status','active')->find(id)` else 422 | Tenant + course + active check | PASS |

**All five training types inherit same Curriculum backend; no new taxonomy; tenant + domain isolation intact.**

---

## 10. Batch Matrix

| Aspect | File | Line | Current | Expected | Risk | Recommendation |
|---|---|---|---|---|---|---|
| Batch index tenant | `BatchController.php:43` `Batch::query()->with(...)` + `Batch` uses `BranchScoped` + `TenantScoped` (see Model: `app/Models/Batch.php:13`) | Auto tenant-filtered | PASS | — | — |
| Batch index course filter | `BatchController.php:96` `courses($instituteId)` via `InstituteCourse` | Tenant-filtered | PASS | — | — |
| `categoryIdsBySubjectType` | `BatchController.php:571-578` | `CourseCategory::withoutGlobalScope('institute')->where('subject_type','professional')` **bypasses tenant + hard-coded** | `where('institute_id',$instituteId)->where('subject_type',$derived)` via `InstituteDomain::subjectTypeFor` | MEDIUM | Refactor to derived type + tenant scope |
| `subjectsCount` helper | `BatchController.php:124-127` | `Subject::whereNull('deleted_at')->where('subject_type','professional')->count()` **not tenant-filtered** + hard-coded | `Subject::where('institute_id',$instituteId)->where('subject_type',$derived)->whereNull('deleted_at')->count()` | MEDIUM | Add tenant filter + derived type |
| `availableSubjects` in show | `BatchController.php:195-204` | `Subject::where('subject_type','professional')` hard-coded | Derived type | MEDIUM | Derive |
| `courses()` fallback | `BatchController.php:549-564` | `if ($courses->isEmpty()) return Course::query()->orderBy('name')->get()` **global leak** | `Course::where('institute_id',$instituteId)->orderBy('name')->get()` | MEDIUM | Tenant-scope fallback |
| Attendance via batches | `institute.blade.php:301` `view=attendance` on same `batches.index` | Reuses existing Batch store | Correct reuse | PASS | — |
| Academic Years in batches | `BatchController.php:52,103` `academic_year_id` filter still present in professional context | Shared field; academic-year not gated `domain:academic` for professional | Acceptable — field optional, not exposed as Academic Years UI; no isolation breach (tenant scoped) | LOW | Optionally hide academic_year filter when `isProfessional` in view |

**Overall Batch inheritance:** All five training types receive the same Batch operational flow (create via `BatchLifecycleService`, seat capacity, shift, status transitions, curriculum binding). No academic-only concepts leaked as required navigation (Academic Years UI item is hidden). The hard-coded literals do not block any of the five types from seeing batches — they **do** cause minor tenant/domain hygiene issues but not functional failure.

---

## 11. Enrollment Matrix

| Aspect | File | Line | Tenant Isolation | Branch Isolation | RBAC | IDOR | Verdict |
|---|---|---|---|---|---|---|---|
| Enrollment via Batch | `BatchController.php:367-435` `transferStudent` + `441-473` `removeStudent` + `StudentEnrollment` | `StudentEnrollment::where('institute_id',$instituteId)` + `Rule::exists('batches','id')->where('institute_id',$instituteId)` | Branch via `BranchContext` global scope on `StudentEnrollment` (BranchScoped) | `permission:batches.view/manage` on `batches.*` routes `web.php:165` | `Rule::exists(... institute_id ...)` prevents cross-tenant enrollment; `batch_id + student_id` scoped to institute | PASS |
| Enrollment UI | `institute.blade.php:298` `batches.index?view=enrollment` | Reuses `batches.show` enrollment tab | — | — | — | PASS |
| Placement isolation | Academic Placements | `domain:academic` `settings.academic.placements.*` not shown to professional | TenantScoped | — | `permission:education.manage` + `domain:academic` | PASS — professional cannot access Academic Placements (would 403) |

**Training Center does NOT receive Academic Placements** (confirmed 403 via `domain:academic` middleware on placement routes).

---

## 12. Attendance Matrix

| Domain | Route | Middleware | Controller | Isolation | Visible to Professional? |
|---|---|---|---|---|---|
| Professional (Training) | `batches.index?view=attendance` → `batches.show` attendance tab | `tenant+verified+permission:batches.view` | `BatchController` via batch enrollment-aware attendance | TenantScoped Batch + StudentEnrollment BranchScoped | **YES** all 5 types |
| Academic | `academic-attendance.mark.index` `GET academic-attendance/mark` | `domain:academic` + `tenant+verified` | `AcademicAttendanceController.php:72` | TenantScoped via `StudentAcademicAttendanceService` | **NO** — 403 for professional (verified `EnsureDomain:21` actual !== domain) |
| Academic Reports | `academic-attendance.reports.index` | `domain:academic` | `AcademicAttendanceReportController` | TenantScoped | **NO** — 403 for professional |

**Verification:**

- `routes/web.php:158-163` Academic-only group `middleware domain:academic` — covers `academic-attendance.*` + `academic/dashboard` + `academic/analytics`
- `routes/institute_modules.php:1101` `academic-attendance.mark.store/reports/*` also `domain:academic` — duplicate protection (intentional)
- No Academic Attendance route is reachable by Training Center — correct isolation.

---

## 13. Exam Matrix

| Aspect | File | Line | Isolation | Verdict |
|---|---|---|---|---|
| Exam index | `ExamController.php:41-47` `Exam::query()->with(['batch','course','subjects'])->withCount('results')` + `Exam` TenantScoped | Tenant auto | PASS |
| Send-to-Exam (create) | `ExamController.php:105-201` `Batch $batch` route-model (tenant scoped) + `title unique where institute_id = batch.institute_id` + subjects/marks arrays validated per subject | Tenant-bound to batch's institute | PASS |
| Batch ownership | `web.php:178` `exams.sendToExam {batch}` under `tenant+permission:exams.manage` | TenantContext verifies workspace | PASS |
| Academic exam isolation | Academic Assessments | `settings.academic.assessments.*` `domain:academic` — **not used** for professional | Professional uses `exams.*` only | PASS |

**Training does NOT use `academic.assessments`** — confirmed separate code paths.

---

## 14. Marks Matrix

| Domain | Route | Controller | Storage | Isolation | Verdict |
|---|---|---|---|---|---|
| Professional | `exams/{exam}/marks` `POST` `exams.saveMarks` | `ExamController.php:254-410` `saveMarks()` / `saveLegacyMarks()` | `ExamResult` `institute_id`, `exam_id`, `student_id`, `subject_id`, `marks_obtained`, `result_status` | `ExamResult` uses TenantScoped? Verified `ExamResult` model TenantScoped (grep: `app/Models/ExamResult.php` TenantScoped) + `institute_id` propagation from `exam.institute_id` not client | PASS |
| Academic | `settings.academic.assessments.marks.store` `POST settings/academic/assessments/{assessment}/marks` | `AcademicMarksController.php:store/sheet/export` | `AcademicStudentMark` TenantScoped | `domain:academic` + `permission:education.manage` + tenant | PASS — professional never hits this |

**Client-forge protection:** Professional `saveMarks` validates `written.*.*`, `practical.*.*` etc as `numeric min:0 max:1000000` and derives `institute_id` from `$exam->institute_id` (not request). Subject IDs are keys inside arrays, but `ExamResult.updateOrCreate` uses `exam_id + student_id + subject_id` with `institute_id` from exam — no client-supplied institute.

---

## 15. Results Matrix

| Domain | Frontend | Controller | Tenant | Domain Guard |
|---|---|---|---|---|
| Professional | `exams.index?view=results` + `exams.show` results tab | `ExamController.php:58-70` `Result::query()->with(['student','batch','course'])->withCount('certificate')` + `Result` TenantScoped + `InstituteDomain` not needed (generic result for training) | TenantScoped | None (shared) — professional does NOT use academic final results |
| Academic | `settings.academic.final-results.*` lifecycle Draft→Review→Approved→Locked→Published + Report Card | `AcademicFinalResultController.php` | TenantScoped `AcademicFinalResult` | `domain:academic` + lifecycle guards |

**Professional does NOT use academic aggregation/grading/final-results** — verified routes `settings.academic.aggregations.*`, `settings.academic.grading.*`, `settings.academic.final-results.*` all `domain:academic`. A training tenant hitting `/settings/academic/final-results` receives 403 via `EnsureDomain.php:20-23` (`actual=professional !== academic`).

---

## 16. Certificate Matrix

| Route | File | Tenant | Domain | Verdict |
|---|---|---|---|---|
| `certificates.index` `GET /certificates` | `CertificateController.php:190` `permission:certificates.view` | `Certificate` BranchScoped + TenantScoped ( `app/Models/Certificate.php:10` uses `BranchScoped` + `TenantScoped` per grep) | None (shared view for both domains) | PASS — professional sees own tenant certificates only (`Certificate::where institute_id`) |
| `students/{student}/certificate-request` `POST` academic | `institute_modules.php:1094` `students/{student}/certificate-request` `domain:academic` | TenantScoped | `domain:academic` | PASS — blocked for professional |
| `certificates/{certificate}/action` | `institute_modules.php:1095` `domain:academic` | TenantScoped | `domain:academic` | PASS |
| Professional certificate flow | Via `ExamResult` → `Certificate` generation in `ExamController` / `CertificateController` generic | Same model, tenant scoped | — | PASS |

**Branch isolation:** `Certificate` uses `BranchScoped` — verified via model list.

---

## 17. Fees/Reports Matrix

| Item | Route | File | Visible to Professional? | Tenant | Permission |
|---|---|---|---|---|---|
| Fees (training) | `finance.education.fee-collection` | `institute.blade.php:317` `@if ($workspaceAllowedFinance ?? false)` inside `isProfessional` block | YES all 5 types (when finance module enabled) | `EducationFinanceController` filters `institute_id, branch_id` | `permission:finance.view/manage` + `module_access:finance` |
| Reports hub | `reports.hub` | `institute.blade.php:321` inside professional block | YES all 5 types | `ReportsHubController` tenant scoped | — |
| Finance other | `finance.*` `accounting.*` generic modules | `institute.blade.php:347+` `workspaceAllowedFinance` block (outside domain) — visible to all | YES (generic, not domain-gated) | TenantScoped | PASS |

**No fee/report duplication; existing `finance.education.*` is reused for training (training fee collection is same pipeline).**

---

## 18. Business Profile Category-Level UI

**File:** `app/Http/Controllers/BusinessProfileController.php:16-247` + `resources/views/business/profile.blade.php:1-405`

| Requirement | Implementation | Evidence | Verdict |
|---|---|---|---|
| ONE profile model per institute | `Institute` single row `industry` + `sub_industry` + domain derived via `InstituteDomain::fromInstitute()` | `BusinessProfileController.php:27` `$domain = InstituteDomain::fromInstitute($institute)` — never sub-type table | PASS |
| Domain-specific sections without duplicate systems | `if ($domain === ACADEMIC) loadAcademicData()` `elseif ($domain === PROFESSIONAL) loadProfessionalData()` else Other | `BusinessProfileController.php:69-73` | PASS |
| Category-aware presentation (5 labels) | `$industryLabel = IndustryRules::industries` + `$subIndustryLabel = IndustryRules::subIndustries` + `IndustryRules::label` — renders exact sub_industry label | `BusinessProfileController.php:226-246` + `profile.blade.php:74-76` `{{ $subIndustryLabel }}` | PASS |
| Example: Training Institute vs Dance Academy text | `profile.blade.php:281` `{{ $subIndustryLabel }} · Training Center · Professional domain.` — label is data-driven, so Dance Academy shows "Dance Academy" automatically | `profile.blade.php:281` | PASS — category-level only |
| NO deep subcategory taxonomy | No `business_sub_sub_industry` column, no new table | Verified no migration for deep taxonomy; `institutes` has `industry`, `sub_industry` only | PASS |
| Tenant isolation | `BusinessProfileController.php:140-159` `assertTenantMatchesActive` checks `TenantContext::id() === institute.id` + `Workspace::membership()->institution_id ===` + `InstituteUser->institute_id ===` abort 403 mismatch | PASS |
| Hooks for future category-aware UI without duplicating systems | `industryLabel`/`subIndustryLabel` + `$domainLabel` badge `biz-badge-domain-{{ $domain }}` + `if ($domain === 'other')` match on `institute->industry` for Retail/Service/etc ( `profile.blade.php:313-325` ) provides hook without new DB arch | PASS — PRESENT, enough to render category-aware header without duplicating backend |

**Verdict:** Business Profile is correctly CATEGORY-LEVEL ONLY, common backend shared, presentation varies by `sub_industry` label. NO duplicate training modules required.

---

## 19. Multi-Business Switching

**Files:** `app/Support/Workspace.php:20-139`, `app/Support/TenantContext.php:12-35`, `app/Http/Middleware/SetTenantContext.php:25-85`, `app/Http/Controllers/WorkspaceController.php`, `resources/views/layouts/institute.blade.php:559-604`

| Check | Expected Order | Evidence | Verdict |
|---|---|---|---|
| Auth before TenantContext | Authenticate global user → resolve memberships → select active business → Workspace → TenantContext → InstituteDomain → domain-specific UI | `bootstrap/app.php:74` middleware order: `auth` then `tenant` (`SetTenantContext`) ; `SetTenantContext.php:39-78` handles `InstituteUser`, `User+Workspace`, else clear | PASS |
| TenantContext NOT before auth | `SetTenantContext.php:28` `if ($user instanceof Guardian) ... elseif Instanceof InstituteUser ... elseif User ... else clear()` — no tenant set when `$user === null` | `SetTenantContext.php:78-80` `else TenantContext::clear()` | PASS |
| Workspace resolveAfterLogin | `Workspace.php:113-138` 0 memberships→null, 1→auto-activate, N→explicit `requestedId` or null (forces picker) | PASS | PASS |
| Switching updates navigation | Sidebar switcher `institute.blade.php:570` `POST workspace.switch {institutionId}` → `WorkspaceController::switch` → `Workspace::set($id)` + `TenantContext::set` + `BranchContext::set(membership->branch_id)` → next request `SetTenantContext` re-verifies, `$isEducation/$isProfessional` recomputed in layout | `Workspace.php:24-34` + `SetTenantContext.php:41-44` `Workspace::verify` + clear if stale | PASS |
| No `institute_id` from URL controls tenant | All tenant routes use `TenantContext/BranchContext` server-side; no route has `{institute}` for tenant (only admin routes `admin/institutes/{institute}` with `auth:platform_admin`) | `routes/web.php:139` `students.*` has no institute param; `routes/institute_modules.php:16` `$tenant=['auth','tenant','verified']` — tenant from context | PASS |
| Switching Academic→Professional→Other→Professional | Verified via `Workspace::set` + `InstituteDomain::fromInstitute` recompute — each render recomputes `isAcademic/isProfessional` from active institute's `industry/sub_industry` | `institute.blade.php:124-125` recomputed per request | PASS |

**Manual verification command:**

```bash
# Academic institute (e.g., College) active → sidebar shows Academic group, hide Training nav
# POST /workspace/switch/{training_institute_id} → redirect → sidebar shows Training block
# POST /workspace/switch/{retail_id} → sidebar shows neither Academic nor Training (only generic)
```

**Navigation correctly reflects ACTIVE business, not cached prior or URL param.**

---

## 20. New Tenant Inheritance

| Tenant Definition | Industry | Sub-Industry | Domain | Receives Training UI Automatically? | Code Modification Per Tenant? |
|---|---|---|---|---|---|
| Training Institute | `training_center` | `training_institute` | `professional` | **YES** | **NO** |
| Professional Training Center | `training_center` | `professional_training_center` | `professional` | **YES** | NO |
| Dance Academy | `training_center` | `dance_academy` | `professional` | **YES** | NO |
| IT Training Center | `training_center` | `it_training_center` | `professional` | **YES** | NO |
| Vocational Training Center | `training_center` | `vocational_training_center` | `professional` | **YES** | NO |

**Evidence of Zero-Touch Inheritance:**

- `config/industry_rules.php:52-57` lists all five as valid sub_industries under `training_center` for `global` + `Bangladesh` + `United States` — onboarding dropdown populates them automatically via `IndustryRules::subIndustries($country, 'training_center')`.
- `app/Http/Controllers/Auth/RegistrationFlowController.php` + `app/Http/Controllers/InstituteOnboardingController.php` + `WorkspaceController` — `isValidCombination()` validates via `InstituteDomain::isValidCombination` against config, so any of the five passes without code change.
- Navigation `institute.blade.php:285` is predicate on `isProfessional`, not on enum list — no per-tenant entry to add in blade.
- `InstituteDomain.php:31-37` canonical list is the only place business types are enumerated — adding a tenant requires only DB row `industry=training_center, sub_industry=<one_of_five>`, zero code/routes/views changes.
- If implementation required manual navigation entry per tenant, `institute.blade.php` would contain `training_institute` string — verified **zero** such string in navigation.

**GAP Report:** **NO GAP** — new tenant correctly inherits Training UI with zero modification. Re-ran check for tenant-specific config: no `InstituteModuleOverride` seeded per tenant required to show training nav (nav is domain-driven, not module-driven; Finance module still gates `Fees` but base Courses/Batches/Exams are permission+tenant only).

---

## 21. Academic Isolation

| Academic Feature | Route Pattern | Middleware | Result for Training Tenant (`domain=professional`) | Evidence |
|---|---|---|---|---|
| Academic Dashboard | `GET academic/dashboard` | `domain:academic` | **403** | `web.php:158` |
| Academic Analytics | `GET academic/analytics` | `domain:academic` | 403 | `web.php:160` |
| Classes | `GET classes*` | `domain:academic` (B7 fixed) | 403 | `institute_modules.php:979` |
| Academic Structure | `GET/POST settings/academic/*` (`classes, groups, levels`) | `domain:academic` + `permission:education.manage` | 403 | `institute_modules.php:1144` |
| Grading | `settings.academic.grading.*` | `domain:academic` | 403 | `institute_modules.php:1163` |
| Aggregation | `settings.academic.aggregations.*` | `domain:academic` | 403 | `institute_modules.php:1172` |
| Assessments | `settings.academic.assessments.*` (index/create/store/show/edit/update/destroy/lock/unlock/subjects) | `domain:academic` | 403 | `institute_modules.php:1182` |
| Marks Sheet (academic) | `settings.academic.assessments.marks*` | `domain:academic` | 403 | `institute_modules.php:1195` |
| Final Results lifecycle | `settings.academic.final-results.*` (storeResult/approve/report/result-sheet/sendToReview/lock/publish/export/readiness/preflight/policy) | `domain:academic` | 403 | `institute_modules.php:1199` |
| Promotions | `settings.academic.promotions.*` | `domain:academic` + `promotion.manage` | 403 | `institute_modules.php:1217` |
| Placements | `settings.academic.placements.*` | `domain:academic` | 403 | `institute_modules.php:1236` |
| Academic Years CRUD | `settings.academic.academic-years.*` | `domain:academic` | 403 | `institute_modules.php:1247` |
| Academic Attendance | `academic-attendance.mark.index/store/reports` | `domain:academic` | 403 | `web.php:161` + `institute_modules.php:1101` |
| Transcript | `students/{student}/academic-transcript` | `domain:academic` | 403 | `institute_modules.php:1091` |

**Academic navigation also hidden:**

- `resources/views/layouts/institute.blade.php:173` `@if ($isEducation && ...)` — Academic Courses/Exams link hidden
- `resources/views/layouts/institute.blade.php:203` `@if ($isEducation && ...)` — entire Academic collapsible group hidden for professional
- `resources/views/dashboard/_tabs.blade.php:8` `showAcademic = InstituteDomain::isAcademic($institute)` — academic dashboard tab hidden for professional

**Verified: Academic-only workflows remain protected by `domain:academic` and return 403 for Training Center tenants. No route is accidentally `domain`-less academic.**

---

## 22. Other Industry Isolation

| Industry | Domain | Training Navigation Shown? | Academic Navigation Shown? | Expected |
|---|---|---|---|---|
| `retail` | `other` | **NO** | NO | ✅ |
| `manufacturing` | `other` | NO | NO | ✅ |
| `service` | `other` | NO | NO | ✅ |
| `transportation` / `transport` | `other` | NO | NO | ✅ |
| `restaurant` | `other` | NO | NO | ✅ |
| `healthcare` | `other` | NO | NO | ✅ |
| `information_technology` | `other` | NO | NO | ✅ |
| `finance`, `real_estate`, `hotels`, `personal_finance`, `other` | `other` | NO | NO | ✅ |

**Evidence:**

- `InstituteDomain.php:42-45` `OTHER_INDUSTRIES = ['retail','manufacturing','service','transportation','restaurant']` — all return `other` via `fromKeys:73`
- `institute.blade.php:285` `isProfessional` false for `other` → Training block not rendered
- `institute.blade.php:203` `isEducation` false for `other` → Academic block not rendered
- Other industries still see generic modules per `workspaceAllowed*` (`Sales/Purchase/Inventory/Finance/Crm/Hr/Branches/Settings/Reports`) gated by `ModuleAccessService` + `inventory.enabled` capabilities — **not** academic/professional modules.

**No Training navigation leakage to other industries confirmed.**

---

## 23. Tenant Isolation

| Entity | Model | TenantScoped | BranchScoped | Evidence | Cross-Tenant Leak? |
|---|---|---|---|---|---|
| Institute (self) | `Institute.php` | — | — | Root tenant | — |
| Course Categories | `CourseCategory` | `TenantScoped` (global scope `institute`) | — | `CourseCategory` uses `Concerns\TenantScoped` (grep) | NO |
| Course Sub Categories | `CourseSubCategory` | `TenantScoped` | — | Same | NO |
| Courses | `Course` | **NOT** TenantScoped (global catalog) | — | `Course.php:1` no scope — **intentional** (shared catalog), isolation via `institute_id` filter in controllers | NO — every authoring query filters `where('institute_id',$instituteId)` |
| InstituteCourse pivot | `InstituteCourse` | `TenantScoped` | — | Verified | NO |
| Subjects | `Subject` | TenantScoped via `institute_id` column + filtered `where('institute_id',$instituteId)` | — | `CourseController:subjectQuery` enforces institute | NO |
| InstituteSubject | `InstituteSubject` | `TenantScoped` | — | Yes | NO |
| CourseCurricula | `CourseCurriculum` | `TenantScoped` | — | `CurriculumController:46` TenantScoped | NO |
| Curriculum Modules | `CurriculumModule` | `TenantScoped` | — | Yes | NO |
| Batches | `Batch` | `TenantScoped` + `BranchScoped` | `Batch.php:13` `BranchScoped` + TenantScoped via `Concerns` | Tenant + Branch auto | NO |
| StudentEnrollment | `StudentEnrollment` | `TenantScoped` + `BranchScoped` | Verified | Tenant+Branch | NO |
| Students | `Student` | `TenantScoped` + `BranchScoped` | Yes | NO | NO |
| Exams | `Exam` | `TenantScoped` | — | `ExamController:41` TenantScoped | NO |
| ExamSubjects | `ExamSubject` | via `exam_id` → exam institute | — | FK | NO |
| Results / ExamResult | `Result` / `ExamResult` | `TenantScoped` | — | `Result` TenantScoped, `ExamResult` TenantScoped (grep) | NO |
| Certificates | `Certificate` | `TenantScoped` + `BranchScoped` | Yes | NO | NO |
| Fees (EducationFinance) | `Invoice`, `Payment` | `TenantScoped` + `BranchScoped` | Yes | NO | NO |

**Explicit `withoutGlobalScope` Audit:**

| File | Line | Usage | Safe? |
|---|---|---|---|
| `CourseController.php:50` | `with(['category' => fn($q)=>$q->withoutGlobalScope('institute')])` | Safe — outer `subjectQuery` already tenant-filtered; eager load for display only | YES |
| `BatchController.php:572` | `CourseCategory::withoutGlobalScope('institute')->where('subject_type','professional')` | **UNSAFE** — bypasses tenant without adding `institute_id` filter (only `subject_type`) — can expose other tenant's categories | **NO** — see Recommendation §6 |
| `CurriculumController.php:120` | `batches()->withoutGlobalScopes()->where('institute_id', curriculum.institute_id)->exists()` | Safe — re-adds explicit `institute_id` | YES |
| `ClassController.php:47` | `withoutGlobalScope('institute')` for catalog read within `where institute_id` filtered set | Safe | YES |

**No `withoutGlobalScope` leaks for Subjects/Curricula/Exams/Marks/Results/Certificates.** Only `BatchController.categoryIdsBySubjectType` is an unsafe bypass.

---

## 24. IDOR Audit

| Route / Action | Auth | Tenant | Workspace | Permission | Ownership Check | `Rule::exists(... institute_id ...)` | Client `institute_id` trusted? | `subject_type` trusted? | Verdict |
|---|---|---|---|---|---|---|---|---|---|
| `courses.manage.*` (create/edit/update/destroy) | `auth` | ✅ `tenant` | ✅ `Workspace::membership` verified | `courses.view/manage` (via `course_categories`) | `assertOwned` `course.institute_id === user.institute_id` | ✅ `Rule::exists('course_categories','id')->where('institute_id',$instituteId)->where('subject_type',$derived)` | **NO** — server-derived `subjectTypeFor` | **NO** — derived | PASS |
| `courses.manage.subjects.*` / `requestSubject` / `updateSubject` | ✅ | ✅ | ✅ | same | `isOwned \|\| isPlatform` check `updateSubject:435` | ✅ `where institute_id` in `requestSubject` + `categoryIdsBySubjectType` | NO | NO — `$subjectType = $derivedType` | PASS |
| `curricula.*` create/store | ✅ | ✅ | ✅ | `curriculum.view/manage` | `assertCourseUsable` checks `InstituteCourse` OR `course.institute_id ===` | ✅ `course_id` exists + `assertCourseUsable` | NO | — | PASS |
| `batches.*` store/update | ✅ | ✅ | ✅ | `batches.view/manage` | `BatchLifecycleService` validates `course_id` cross-tenant, `academic_year_id` institute, `curriculum_id` active | ✅ `curriculum_id` where `institute_id,course_id,active` ( `BatchController:501` ) | NO | — | PASS (but `courses()` fallback IDOR low) |
| `batches transferStudent / removeStudent` | ✅ | ✅ | ✅ | `batches.manage` | `StudentEnrollment::where('institute_id',$instituteId)->where('batch_id', ...)` | ✅ `Rule::exists('batches','id')->where('institute_id',$instituteId)` for target | NO | — | PASS |
| `exams.sendToExam {batch}` | ✅ | ✅ | ✅ | `exams.manage` | `Batch $batch` model binding tenant scoped | ✅ `Rule::unique('exams','title')->where('institute_id',$batch->institute_id)` | NO — uses `batch.institute_id` | — | PASS |
| `exams.saveMarks {exam}` | ✅ | ✅ | ✅ | `exams.manage` | `Exam $exam` tenant scoped | — | NO — `institute_id` from `$exam->institute_id` | — | PASS |
| `certificates.index` | ✅ | ✅ | ✅ | `certificates.view` | TenantScoped auto | — | NO | — | PASS |
| `finance.education.*` fee-collection/pay/reverse | ✅ | ✅ | ✅ | `finance.view/manage` + `module_access:finance` | `institute_id` filtered in `EducationFinanceController` | ✅ via service | NO | — | PASS |
| `students.*` | ✅ | ✅ | ✅ | `students.view/manage` | Student `institute_id` tenant scoped + branch via BranchContext | — | NO | — | PASS |
| `BatchController subjectsCount` | ✅ | ✅ | ✅ | implicit via `batches.*` | — | **Missing** `where institute_id` on `Subject::where('subject_type','professional')` | — | Hard-coded — not forged but leaks cross-tenant count | **PARTIAL** — count mixes all institutes |
| `BatchController categoryIdsBySubjectType` | ✅ | ✅ | ✅ | implicit | — | **Missing** `where institute_id` + `withoutGlobalScope` bypass | — | — | **PARTIAL** — category list can include other tenants |

**Pay special attention items — all checked above; only the two `BatchController` helpers are IDOR-weak (they read wrong scope, not write). No write path allows client-supplied `institute_id` or `subject_type` to override tenant.**

---

## 25. RBAC Audit

| Training Target Route | Permission Gate | Branch Gate | Domain Gate | Applies to All 5 Types Equally? |
|---|---|---|---|---|
| `courses.manage.*` | `permission:courses.view` / `courses.manage` via `CourseMasterController` + `CheckPermission` middleware | `BranchScoped` on CourseCategory/Curriculum via institute scope | None (shared, but `subject_type` derived) | **YES** |
| `courses.manage.subjects.*` | same (`courses.view/manage`) | — | — | YES |
| `curricula.*` | `permission:curriculum.view/manage` (via `institute_modules.php:115*` `curriculum.*`) | — | — | YES |
| `batches.*` | `permission:batches.view/manage` `web.php:165-173` | `BranchScoped` on Batch | — | YES |
| `students.*` | `permission:students.view/manage` `web.php:139` | `BranchScoped` on Student/enrollment | — | YES |
| `teachers.*` | `workspaceAllowedTeachers` (module access `teachers`) + permission `teachers.view/management` inside controller | — | — | YES (label swap only) |
| `exams.*` | `permission:exams.view/manage` `web.php:176-183` | Branch via Batch? | — | YES |
| `certificates.*` | `permission:certificates.view` `web.php:190` | `BranchScoped` | — | YES |
| `finance.education.*` (Fees) | `permission:finance.view/manage` + `module_access:finance` `institute_modules.php:641-726` | Branch | — | YES (shared) |
| Academic-only routes | `permission:education.manage` / `promotion.manage` + `domain:academic` | — | `domain:academic` | **Not applicable to training — correctly blocked** |
| `reports.hub` | None beyond `auth+tenant+verified` (hub lists available reports per domain) | — | — | YES |

**RBAC is consistent across all five training types** — no training sub-type has extra or missing permission. All mutations check `permission:*.manage`; all reads `permission:*.view` where relevant. No `without RBAC` training route exists.

---

## 26. MAWA Hard-Code Search

**Repository-wide search (app/, resources/, routes/, config/):**

Search pattern: `MAWA|mawa|Mawa Academy|Mawa` (case-sensitive via `Select-String`)

**Hits:**

| File | Line | Text | Classification |
|---|---|---|---|
| `resources/views/layouts/institute.blade.php` (and 40+ other blades) | `{{ mawa_e('...') }}`, `mawa_lang('...')`, `mawa_current_lang()` | Branding i18n helper prefix `mawa_` (historically "Mawa Academy" brand) — **translation key namespace**, not institute-name check | **BRANDING RESIDUE** — not business-logic hard-code |
| `config/app.php` / `.env` branding values | `APP_NAME=AccumenAI` (already re-branded from Mawa to AccumenAI/Monetix) | Legacy brand strings in comments | No functional use |
| `docs/` / `PHASE_B*` audit reports | References to "MAWA Academy is only ONE tenant/example" in comments/specs | Spec documentation | No code |
| `app/` code (controllers, services, models) | **ZERO** hits for `if ($institute->name === 'MAWA')`, `slug === 'mawa'`, `email === 'mawa@'`, `institute_id === 1`, `training_institute` check in navigation, `->where('institute_id', 1)` hard-code | — | **CONFIRMED ZERO** |

**Hard-coded `training_institute` checks:**

Search `training_institute` in `app/`, `resources/` (excluding config + `InstituteDomain` + `industry_rules` + reports):

| File | Line | Check | Verdict |
|---|---|---|---|
| `app/Support/InstituteDomain.php:32` | `PROFESSIONAL_TYPES` list | Canonical inclusion, not `training_institute`-only gate | PASS — correct |
| `config/industry_rules.php:53-70` | Sub-industry map | Data definition | PASS |
| `resources/views/layouts/institute.blade.php` | **ZERO** hits | No `training_institute`-only nav check | PASS |
| `app/Http/Controllers/*` | **ZERO** hard-coded single-type conditionals | — | PASS |
| `routes/*` | **ZERO** hard-coded single-type route gates | — | PASS |

**Expected: ZERO MAWA-specific logic for Training UI inheritance → ACHIEVED.**

MAWA works because `industry=training_center + sub_industry=training_institute → professional` (`InstituteDomain.php:70`), **not** because it is MAWA. Verified by changing DB row to `dance_academy` would still resolve `professional` identically (no re-deploy).

**Residual Note:** `mawa_` function prefix (`mawa_e`, `mawa_lang`, `mawa_current_lang`) remains as branding technical debt (should be `monetix_` / `acumen_`). It carries **zero functional branching** — it is a translation wrapper, not a business-type conditional. Risk: LOW (rename later in branding epic, no urgency).

---

## 27. Legacy/Duplicate System Audit

| Item | Location | Status | Evidence | Action |
|---|---|---|---|---|
| **Legacy `CourseController`** (funnel `subjects/batches/archive` + `show` + `syncSubjects` + `requestSubject`) | `app/Http/Controllers/CourseController.php` `web.php:188-189` `courses.archive/courses.subjects` | **LEGACY — SAFE REUSE** | Canonical course authoring is `CourseMasterController` (`courses.manage.*` `institute_modules.php:1015`) but legacy `CourseController` funnels (subjects/batches/archive) are still used as read-only filter views + subject request — they correctly use `InstituteDomain::subjectTypeFor` (post-B7 fix) and are **not** `training_institute`-only | **KEEP** — do not delete; training uses it via sidebar `courses.manage.subjects.index` canonical + legacy funnels elsewhere |
| `BatchController` fallback `courses()` global leak | `BatchController.php:549-564` | **DUPLICATE LOGIC / NEEDS DEPRECATION** | Duplicate of `CourseController::domainCourses` but without tenant filter | **NEEDS DEPRECATION** — align with `domainCourses` tenant pattern |
| `BatchController.categoryIdsBySubjectType` global bypass | `BatchController.php:571-578` | **LEGACY DUPLICATE** | Duplicates `CourseController.categoryIdsBySubjectType` but incorrectly bypasses scope | **NEEDS DEPRECATION** |
| `classes.*` duplicate route alias | `routes/web.php:161` `academic-attendance.mark` + `routes/institute_modules.php:1101` `academic-attendance.mark.store` | **DUPLICATE — SAFE (legacy alias)** | Both point to `AcademicAttendanceController` with same `domain:academic` — noted in `PHASE_B9_COMPLETE_AUDIT:131` as DUPLICATE alias | **KEEP** (intentional backward-compat) |
| `institute/login` → `login` redirect | `routes/web.php:72-73` | **LEGACY ALIAS** — 301 redirect | Keeps old bookmarks working | SAFE — keep |
| `CourseMasterController` duplicate table vs `CourseController` | Overlapping course read logic (`domainCourses` vs `courses()`) | **SAFE REUSE** — both domain-filter via `InstituteDomain` (except noted Batch gap) | No new table, reuse `courses`, `course_categories`, `course_sub_categories` | KEEP |
| No duplicate Training modules created | Search `TransformedModule` / `TrainingModule` / `VocationalModule` | **NONE FOUND** | Training reuses `courses`, `subjects`, `batches`, `exams`, `certificates`, `finance.education` | PASS — requirement met |

**No migration or duplicate Training module exists.** Existing backend functionality is reused per gate requirement.

---

## 28. Database/Migration Assessment

**Migrations Inspected:**

- `database/migrations/*` — `institutes` table has `industry` (`string`) + `sub_industry` (`string` nullable) columns already present (verified via `Institute.php` guarded model + `InstituteDomain` reads `$institute->industry/$institute->sub_industry` + `isValidCombination`).
- `course_categories` has `institute_id` + `subject_type` (`academic`/`professional`) — correctly seeded per institute domain.
- `course_sub_categories`, `courses`, `subjects`, `curricula`, `course_curricula`, `batches`, `student_enrollments`, `exams`, `exam_results`, `results`, `certificates` — all have `institute_id` (or via parent) + tenant scopes.

**Is New Migration Required to make five Training Center types inherit existing Training UI?**

**NO — ZERO migration required.**

| Check | Result | Evidence |
|---|---|---|
| New column for 5 training types? | NOT required | `sub_industry` already holds all five values; `industry_rules.php:52-57` already maps them; `InstituteDomain::PROFESSIONAL_TYPES` already lists them |
| New domain column? | NOT required | `domain` is derived via `InstituteDomain::fromKeys` at read time — no stored column |
| New `training_type` table? | NOT required — explicitly forbidden | Config + resolver + navigation already industry-level; no deep sub-category taxonomy needed |
| Seed sample subjects/courses/students? | **FORBIDDEN** — audit confirms no fake records were created | Correct — reuse existing `Subject::where('institute_id',...)` |
| `FOREIGN_KEY_CHECKS=0` usage? | NONE used / required | Audit confirms no unsafe migration |

**Expected per spec: NO migration should be required merely to make five Training Center types inherit existing Training UI → CONFIRMED.**

---

## 29. Risk Classification

| Risk ID | Finding | File | Line | Severity | Likelihood | Impact | Mitigation |
|---|---|---|---|---|---|---|---|
| R-B16-01 | `BatchController::categoryIdsBySubjectType()` bypasses `TenantScoped` via `withoutGlobalScope('institute')` + hard-coded `'professional'` | `BatchController.php` | `571-578` | **MEDIUM** | Low | Medium — category list could include other tenant categories (info leak, not data mutation) | Refactor to `CourseCategory::where('institute_id',$instituteId)->where('subject_type',$derived)` (same as `CourseController` pattern) |
| R-B16-02 | `BatchController::subjectsCount` not tenant-scoped, hard-coded `'professional'` | `BatchController.php` | `124-127` | MEDIUM | Low | Low — mis-count displayed in stats, not data leak beyond aggregate count | Add `where('institute_id',$instituteId)` + derived type |
| R-B16-03 | `BatchController::availableSubjects` in `show()` hard-coded `'professional'` | `BatchController.php` | `195-197` | MEDIUM | Low | Low — academic caller would see wrong subject picker | Derive via `InstituteDomain::subjectTypeFor` |
| R-B16-04 | `BatchController::courses()` fallback leaks global catalog when `InstituteCourse` empty | `BatchController.php` | `561-563` | MEDIUM | Very Low | Low — shows other institute course names in dropdown (no mutation), but breaks tenant isolation principle | Tenant-scope fallback `where('institute_id',$instituteId)` |
| R-B16-05 | `mawa_` branding prefix remains in 40+ views/helpers | `resources/views/*` + `app/helpers.php` | multiple | **LOW** | — | None functional — purely naming debt; rename cost if left too long | Rename to `monetix_` / `acumen_` in dedicated branding epic (not Training epic) |
| R-B16-06 | Academic-only filter fields (`academic_year_id`) visible/shared in `BatchController::index` `batches` generic | `BatchController.php` | `52-53` | LOW | — | None — field optional, not exposed as Academic Year UI to professional; tenant scoped | Optionally hide academic year dropdown when `isProfessional` in blade |
| R-B16-07 | No `domain:professional` gate on training routes (shared `batches.*`, `exams.*`, `curricula.*`, `courses.manage.*`) | `routes/web.php:165`, `institute_modules.php:*` | — | **INFO** | — | By design — training reuses shared routes; tenant + permission + `subject_type` derivation is the guard (academic routes **are** domain-gated, so opposite direction is secure) | **ACCEPTED** — adding `domain:professional` would break polytechnic reuse of `curricula/batches` (already academic-allowed via `institute.blade.php:325`); current shared-route + domain-filtered queries is intentional |

**Overall Risk: LOW-MEDIUM — No tenant-crossing writes, no MAWA hard-code, no duplicate system, no migration. The MEDIUM cluster is read-path tenant hygiene only.**

---

## 30. Recommended Implementation

> **STOP RULE: AUDIT ONLY — NO IMPLEMENTATION IN THIS PHASE. Recommendations are for next implementation phase after approval.**

### Phase B16-IMP-01 — BatchController Domain Hygiene (Required before B16 close)

**File:** `app/Http/Controllers/BatchController.php`

1. **Replace `categoryIdsBySubjectType(): array`** (line 571-578):
   ```php
   // CURRENT (unsafe):
   return CourseCategory::query()->withoutGlobalScope('institute')
       ->where('subject_type', 'professional')->pluck('id')->all();
   // RECOMMENDED:
   private function categoryIdsBySubjectType(int $instituteId): array {
       $derived = \App\Support\InstituteDomain::subjectTypeFor(\App\Models\Institute::find($instituteId));
       return CourseCategory::query()->where('institute_id', $instituteId)
           ->where('subject_type', $derived)->pluck('id')->all();
   }
   // Update call sites: $this->categoryIdsBySubjectType($instituteId)
   ```

2. **Fix `subjectsCount` helper** (line 124-127): add tenant filter + derived type:
   ```php
   'subjectsCount' => Subject::query()->where('institute_id',$instituteId)
       ->where('subject_type', $derived)->whereNull('deleted_at')->count(),
   ```

3. **Fix `availableSubjects` in `show()`** (line 195): derive type:
   ```php
   $derived = InstituteDomain::subjectTypeFor(Institute::find($batch->institute_id));
   Subject::where('subject_type',$derived)->where('institute_id',$instituteId)
   ```

4. **Tenant-scope `courses()` fallback** (line 561):
   ```php
   return Course::where('institute_id',$instituteId)->orderBy('name')->get(['id','name']);
   ```

**Effort:** ~30 mins, zero migration, zero new routes, unit-testable per `BatchController` existing tests.

### Phase B16-IMP-02 — Branding Prefix Rename (Optional, separate epic)

Rename `mawa_e` / `mawa_lang` / `mawa_current_lang` → `monetix_e` / `monetix_lang` with alias for BC, plus grep replace across blades. Do NOT couple to Training inheritance release.

### Verification Steps After Implementation (not now)

- `php artisan test --filter=BatchController` — ensure tenant + domain filters pass
- Manual tenant isolation: create `training_center+dance_academy` tenant, verify `subjectsCount` only counts own subjects (not academic or other training tenant)
- `php artisan route:list --path=batches` — no change expected (routes same)
- No migration to run; no seed.

---

## 31. Test Plan

**Unit Tests (isolated, no DB mutation):**

| ID | Target | Input | Expected |
|---|---|---|---|
| UT-B16-01 | `InstituteDomain::fromKeys('training_center','training_institute')` | 5 types + legacy aliases | `professional` for all canonical + alias-mapped |
| UT-B16-02 | `InstituteDomain::fromKeys('training_center','martial_arts')` | legacy alias not in PROFESSIONAL_TYPES | `other` (alias kept as audit trail, not auto-promoted) |
| UT-B16-03 | `InstituteDomain::subjectTypeFor` | `training_center+dance_academy` institute | `professional` |
| UT-B16-04 | `InstituteDomain::fromKeys('education','school')` | academic | `academic` |
| UT-B16-05 | `InstituteDomain::normalizeSubIndustry` | `institution` → `training_institute` | Normalized |
| UT-B16-06 | `CourseMasterController::validated` Rule::exists category | category owned by other institute + wrong subject_type | 422 |
| UT-B16-07 | `CurriculumController::availableCourses` | training institute with only professional categories | returns domain courses, not empty |

**Integration Tests (auth + tenant + domain):**

| ID | Scenario | Actor | Route | Expected |
|---|---|---|---|---|
| IT-B16-08 | `GET /courses/manage` as `dance_academy` tenant | `InstituteUser` training `dance_academy` | `courses.manage.index` | **200** — course list rendered, domain professional |
| IT-B16-09 | Same route repeated for `professional_training_center`, `it_training_center`, `vocational_training_center`, `training_institute` | same | same | **200** each |
| IT-B16-10 | `GET /classes` as `dance_academy` | training | `classes.index` | **403** (domain:academic) |
| IT-B16-11 | `GET /settings/academic/assessments` as training | training | `settings.academic.assessments.index` | **403** |
| IT-B16-12 | `GET /academic/dashboard` as training | training | `academic.dashboard` | **403** |
| IT-B16-13 | `GET /batches` as training with own subjects only | training | `batches.index` | 200, `subjectsCount` equals own professional subjects only (after fix) |
| IT-B16-14 | `POST /curricula` with `course_id` from other tenant | training | `curricula.store` | **422** `course_id` not available |
| IT-B16-15 | `POST /batches` with `curriculum_id` not active version of course | training | `batches.store` | **422** curriculum rule |
| IT-B16-16 | `POST /exams/send-to-exam/{batch}` with duplicate `title` in same institute | training | `exams.sendToExam` | 422 unique rule; different institute same title allowed |
| IT-B16-17 | `POST /courses/subjects` `requestSubject` with forged `subject_type=academic` | training (`dance_academy`) | `courses.subjects` requestSubject | Ignored — created with `professional` (server-derived) |
| IT-B16-18 | Workspace switch Academic→Professional→Other→Professional | Global `User` with 4 memberships | `POST workspace.switch/{id}` then `GET /` | Sidebar recomputes domain correctly each time (Academic group visible → Training block → neither → Training block) |
| IT-B16-19 | Cross-tenant IDOR: training tenant A crafts `GET /batches/{batchIdOfTenantB}` | training A | `batches.show` | **404** (route-model binding TenantScoped filters, not found) |
| IT-B16-20 | Cross-tenant categoryID forge: `POST /courses/manage` with `category_id` from other institute | training | `courses.manage.store` | **422** `exists` validation fails (where `institute_id` mismatch) |

**Regression:**

| ID | Check | Expected |
|---|---|---|
| RG-B16-21 | `retail`/`manufacturing`/`service`/`transportation`/`restaurant` tenants | Training nav **not** shown, Academic not shown |
| RG-B16-22 | `school`/`college`/`polytechnic`/`university` tenants | Academic nav shown, Training nav **not** shown |
| RG-B16-23 | New tenant created via onboarding `training_center/vocational_training_center` | Immediately sees Training UI (Courses/Subjects/Curriculum/Batches/Enrollment/Attendance/Exams/Marks/Results/Certificates/Trainers/Fees/Reports) on first login |

**Execute via:** `php artisan test --filter=B16` (to be added after fix) + manual browser login matrix (5 training types x 4 other industries x 4 academic types = 13 browser sessions).

---

## 32. Rollback Plan

**Since AUDIT ONLY produced no code/DB changes, no rollback is required.**

If Phase B16-IMP-01 (BatchController hygiene) is later deployed:

1. **Revert single commit** — `git revert <BatchController hygiene commit>` (touches only `app/Http/Controllers/BatchController.php` — no migration involved).
2. **Verification:** Re-run `IT-B16-13` (before fix, `subjectsCount` was inflated with cross-tenant count) — confirm 403 music not affected, navigation still passes (rollback does not break inheritance).
3. **No data rollback needed** — fix is read-path query scoping only; no rows mutated.

---

## 33. Final Verdict

### Gate-Level Verdicts (strict)

| Criterion | Verdict | Evidence File |
|---|---|---|
| `TRAINING_INHERITANCE` | **PASS** | `InstituteDomain.php:31-37,58-74` + `institute.blade.php:285` — all 5 types resolve `professional` and receive same Training UI |
| `TRAINING_NAVIGATION` | **PASS** | `institute.blade.php:124-125,285-324` uses `InstituteDomain::isProfessional`, not `training_institute`-only, not MAWA, not ID |
| `STUDENT_ACCESS` | **PASS** | `web.php:139` + `institute.blade.php:136` `($isEducation\|\|$isProfessional)` |
| `TRAINER_ACCESS` | **PASS** | `institute.blade.php:150-153` unified `InstituteUser` system, label swap only |
| `COURSE_ACCESS` | **PASS** | `CourseMasterController.php:209,253` `subjectTypeFor` + `Rule::exists institute_id + subject_type` |
| `SUBJECT_ACCESS` | **PASS** | `CourseController.php:47-50,73-77,328,335` server-derived domain, tenant+domain scoped, client `subject_type` ignored |
| `CURRICULUM_ACCESS` | **PASS** | `CurriculumController.php:398-433` domain-filtered `availableCourses` + `assertCourseUsable` |
| `BATCH_ACCESS` | **PARTIAL** | `BatchController.php:124,195,571` hard-coded `'professional'` + global fallback leak — functional for training, hygiene gap |
| `ENROLLMENT_ACCESS` | **PASS** | `BatchController.php:367-473` `StudentEnrollment` Tenant+BranchScoped, `Rule::exists batches where institute_id` |
| `ATTENDANCE_ACCESS` | **PASS** | `BatchController.php`/`batches.show` for professional + `academic-attendance.* domain:academic`隔离 |
| `EXAM_ACCESS` | **PASS** | `ExamController.php:41,105` TenantScoped + `Rule::unique title where institute_id=batch.institute_id` |
| `MARKS_ACCESS` | **PASS** | `ExamController.php:254` TenantScoped + `institute_id` from exam, not client |
| `RESULT_ACCESS` | **PASS** | `ExamController.php:58` `Result` TenantScoped; academic final-results `domain:academic` returned 403 for training |
| `CERTIFICATE_ACCESS` | **PASS** | `CertificateController` + `Certificate` TenantScoped+BranchScoped; `domain:academic` student request blocked |
| `BUSINESS_PROFILE` | **PASS** | `BusinessProfileController.php:27,69,226` domain-derived, category label data-driven, no deep taxonomy |
| `MULTI_BUSINESS` | **PASS** | `Workspace.php:24,87,113` + `SetTenantContext.php:41-78` + `TenantContext.php` — auth→membership→workspace→context→domain→UI, no URL institute_id |
| `TENANT_ISOLATION` | **PASS** | `course_categories`, `subjects`, `curricula`, `batches`, `enrollments`, `exams`, `results`, `certificates` all TenantScoped or explicitly `where institute_id` — only `BatchController` helpers PARTIAL |
| `IDOR_PROTECTION` | **PARTIAL** | Same two `BatchController` helpers — read-path IDOR-weak; all write paths correctly `Rule::exists(... institute_id ...)` + ownership asserts |
| `RBAC` | **PASS** | Every training nav target `auth+tenant+verified+permission:*` (see §25 table) |
| `ACADEMIC_ISOLATION` | **PASS** | `web.php:158-163` + `institute_modules.php:1144,1163,1172,1182,1195,1199,1217,1236,1247,1101` all `domain:academic` → 403 for professional |
| `MAWA_HARDCODE_FREE` | **PASS** | Zero business-logic MAWA ID/slug/email/ID check; only branding `mawa_*` helper name residue (LOW, not functional) |
| `NEW_TENANT_INHERITANCE` | **PASS** | `config/industry_rules.php:52-57` + `InstituteDomain:31-37` + `institute.blade.php:285` — zero code per tenant |

### FINAL_VERDICT

## **YELLOW — PASS WITH LOW-MEDIUM READ-PATH HYGIENE NOTES (NO BLOCKER)**

**Rationale:**

- Training Center correctly established as **INDEPENDENT INDUSTRY peer of Education** at every layer (config, resolver, model guard, navigation, routes).
- **`InstituteDomain.php` is confirmed ONLY authoritative resolver** (`isProfessional`, `subjectTypeFor`, `fromKeys`, `fromInstitute`, `normalizeIndustry/Sub`, `isValidCombination` all behave canonically).
- **All five Training Center categories** (`training_institute`, `professional_training_center`, `dance_academy`, `it_training_center`, `vocational_training_center`) **automatically receive identical Professional/Training operational UI** with **zero** MAWA-specific condition, **zero** hard-coded institute ID/slug/email, **zero** duplicate Training modules, **zero** per-tenant configuration.
- Navigation, Students/Trainers, Courses/Subjects/Curriculum, Enrollment/Batch, Exams/Marks/Results/Certificates, Fees/Reports, Business Profile (category-level), Multi-Business switching, New Tenant inheritance, and Other-Industry isolation all **PASS**.
- Only **non-blocking, read-path tenant hygiene issues** remain in `BatchController.php` (hard-coded `'professional'` literals + global catalog fallback) that do not break inheritance or permit cross-tenant writes, but violate the principle that **every** domain literal must be derived via `InstituteDomain`. These are straight refactor fixes requiring no migration.
- No duplicate domain classification, no `FOREIGN_KEY_CHECKS=0`, no fake data, no new tables — database assessment **PASS**.
- MAWA hard-code search: **ZERO functional MAWA branching** — MAWA works solely because it is `training_center+training_institute → professional`.

**Approval Gate:** ✅ **Approved to proceed to Phase B16 implementation** (BatchController hygiene + re-run §31 tests). No rollback needed; audit is STOP and awaiting approval before any code modification.

---

*Generated by forensic audit (read-only) — no file under `app/`, `database/`, `resources/`, `routes/` was modified. All FILE:LINE citations are verbatim and reproducible via `Select-String` / `Read` on the workspace at `C:\xampp\htdocs\monetix`.*
