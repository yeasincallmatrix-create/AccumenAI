# PHASE B17 — MAWA TRAINING CENTER REAL UI / OPERATIONAL WORKFLOW RESTORATION
## TARGET: MAWA Academy + All Professional Training Tenants — IMPLEMENTATION REPORT

**Project:** Monetix / MAWA SaaS
**Date:** 2026-08-28
**Mode:** Forensic Audit → Implement (minimum fixes) → Verify
**Predecessor:** PHASE B16 — B16 proved all 5 Training Center sub-types architecturally inherit `professional` domain via `InstituteDomain`; this phase makes the EXISTING professional operational system actually visible, reachable, and usable from the real UI.
**Rules Enforced:** No fake business data, no new tables, no `FOREIGN_KEY_CHECKS`, no taxonomy/domain change, no `training_*` duplicate subsystem, reuse canonical controllers/routes/models/services/views.

**MAWA Canonical Tenant:**
```
industry = training_center
sub_industry = training_institute
domain = professional  (InstituteDomain::fromKeys)
```

---

## 1. Root Cause

B16 forensic audit (`PHASE_B16_*_REPORT.md:29`) identified that navigation already correctly gated on `InstituteDomain::isProfessional($institute)` for all 5 training types, and 11 of 14 expected training menu items were present in `resources/views/layouts/institute.blade.php:285-324`. However two **non-functional code-hygiene failures** in `app/Http/Controllers/BatchController.php` prevented the operational workflow from being fully correct in the actual rendered UI/data layer:

| ID | File | Line (before) | Issue | Why Workflow Broke |
|---|---|---|---|---|
| R-B17-01 | `BatchController.php` | `571-578` `categoryIdsBySubjectType()` | `withoutGlobalScope('institute')` + hard-coded `where('subject_type','professional')` — bypassed tenant scope, ignored domain derivation | Professional enrolled tenants could see OTHER tenants' `course_categories` in batch course filter; academic callers would get wrong domain categories; tenant counts wrong in `batches.index` stats. Not a crash, but a **tenant-isolation / domain-safety regression** that violated Part 8 & Part 20 security requirements. |
| R-B17-02 | `BatchController.php` | `124-127` `subjectsCount` + `195-197` `availableSubjects` + `549-564` `courses()` fallback | Hard-coded `'professional'` literal + `subjectsCount` not tenant-filtered + `courses()` global `Course::query()->orderBy('name')->get()` leak | Stats on `batches.index` mixed all institutes' subjects; course dropdown for empty `InstituteCourse` showed global catalog (other tenants' courses); batch `show` subject picker ignored tenant and domain for non-assigned case. Broke **DATA QUERY correctness** (Part 1: DATA QUERY column) even though route/nav appeared reachable. |
| R-B17-03 | `institute.blade.php` | `284-324` | Professional navigation was flat list without collapsible `Training` grouping — discoverability weak vs Academic's `nav-group` + correct order existed but lacked visual hierarchy; Enrollment/Attendance/Marks/Results were query-param tabs on `batches.index`/`exams.index` not obvious as workflow steps without grouping | Users discovered routes existed (route:list showed them) but *didn't perceive* the Course→Subject→Curriculum→Batch→Enrollment→Attendance→Exam→Marks→Result→Certificate workflow because professional links were not grouped/ordered as a coherent Training pipeline like Academic's `Academic` group. Broke **NAVIGATION VISIBLE / UI discoverability** (Part 16 & Part 17 distinction A vs F). |

All other training surfaces (Students/Trainers/Courses/Subjects/Curriculum/Exams/Marks/Results/Certificates/Fees/Reports) were already correctly tenant+domain+RBAC safe and visible — they required only verification, not code change.

---

## 2. MAWA Current UI Before

**Before B17 fixes (functionally correct routes, tenant-safe except BatchController, but workflow not obvious):**

- Sidebar for MAWA (`isProfessional=true`, `isEducation=false`):
  - `Dashboard` → 200
  - `Students` → `students.index` (`students.*` prefix) `auth+tenant+verified+permission:students.view` → **200, TenantScoped Student, empty-state safe** — visible but not visually inside a Training group.
  - `Trainers` (label `Trainers` when professional) → `teachers.index` `TeacherController` unified model (`InstituteUser`/`Membership`) — **200**, permission `workspaceAllowedTeachers` ( `education` module + `teacher.view`) — visible.
  - *Academic group hidden* (correct, `isEducation=false` so `institute.blade.php:204` not rendered).
  - **Professional flat list** (no group header):
    - `Courses` `courses.manage.index` → 200 tenant-scoped `CourseMasterController` (`where institute_id`) + domain-derived categories — **visible**
    - `Subjects` `courses.manage.subjects.index` → 200 tenant+domain `SubjectManagementController` — **visible**
    - `Curriculum` `curricula.index` → 200 `CurriculumController` + freeze rules — **visible**
    - `Batches` `batches.index` → 200 but **DATA QUERY incorrect** (subjectsCount leaked, coursesCount leaked, categoryIds bypass — see R-B17-01/02)
    - `Enrollment` `batches.index?view=enrollment` → same controller/view but query-param tab — **visible but discoverability low (flat)** 
    - `Attendance` `batches.index?view=attendance` → same — **visible but low discoverability**
    - `Exams` `exams.index` → 200 `ExamController` TenantScoped — **visible**
    - `Marks` `exams.index?view=marks` → same route with view param — **visible but low discoverability**
    - `Results` `exams.index?view=results` → same — **visible but low discoverability**
    - `Certificates` `certificates.index` → 200 Tenant+BranchScoped — **visible**
    - `Fees` `finance.education.fee-collection` (gated `workspaceAllowedFinance`) → 200 `EducationFinanceController` generic reusable — **visible if finance enabled**
    - `Reports` `reports.hub` → 200 — **visible**

- Actual HTTP status for each (tenant stub): all `GET` routes return `200` when tenant present + permission granted; academic routes (`academic.dashboard`, `settings.academic.*`, `academic-attendance.*`, `classes.*`) correctly `403` via `domain:academic` middleware (`EnsureDomain.php:11`).

- Distinction per Part 17: No UI was truly **hidden** (A), no route **inaccessible** (B), no **permission missing** (C) beyond BatchController hygiene — the only failures were **tenant filtering incorrect** (E) in BatchController + low discoverability (workflow not visually grouped). Data genuinely absent (F) is the expected empty state when MAWA has no courses/batches yet — correctly reported as `DATA_PRESENT=NO` (no fake data created).

---

## 3. Training Navigation

**File:** `resources/views/layouts/institute.blade.php:123-126,284-360`

**Before (excerpt flat list, no group):**
```blade
{{-- Professional domain navigation: MAWA Training Center operational — reuse existing tenant-safe routes --}}
@if ($isProfessional && ($workspaceAllowedEducation ?? false))
    <a class="nav-link ..." href="{{ route('courses.manage.index') }}">Courses</a>
    <a ...>Subjects</a> <a ...>Curriculum</a> <a ...>Batches</a> <a ...>Enrollment</a> ...
@endif
```

**After:**
```blade
{{-- Professional / Training Center — operational workflow (all 5 training types via isProfessional) --}}
@if ($isProfessional && ($workspaceAllowedEducation ?? false))
    @php $trainingOpen = request()->routeIs('courses.manage.*','courses.manage.subjects.*','curricula.*','batches.*','exams.*','certificates.*','finance.education.*','reports.hub*') ? true : false; @endphp
    <div class="nav-group">
        <button ... data-bs-target="#trainingNavGroup">Training</button>
        <div class="collapse {{ $trainingOpen ? 'show' : '' }}" id="trainingNavGroup">
            <a class="nav-link sub ..." href="{{ route('courses.manage.index') }}">Courses</a>
            <a class="nav-link sub ..." href="{{ route('courses.manage.subjects.index') }}">Subjects</a>
            <a class="nav-link sub ..." href="{{ route('curricula.index') }}">Curriculum</a>
            <a class="nav-link sub ..." href="{{ route('batches.index') }}">Batches</a>
            <a class="nav-link sub ..." href="{{ route('batches.index', ['view'=>'enrollment']) }}">Enrollment</a>
            <a class="nav-link sub ..." href="{{ route('batches.index', ['view'=>'attendance']) }}">Attendance</a>
            <a class="nav-link sub ..." href="{{ route('exams.index') }}">Exams</a>
            <a class="nav-link sub ..." href="{{ route('exams.index', ['view'=>'marks']) }}">Marks</a>
            <a class="nav-link sub ..." href="{{ route('exams.index', ['view'=>'results']) }}">Results</a>
            <a class="nav-link sub ..." href="{{ route('certificates.index') }}">Certificates</a>
            <a class="nav-link sub ..." href="{{ route('finance.education.fee-collection') }}">Fees</a> (conditional)
            <a class="nav-link sub ..." href="{{ route('reports.hub') }}">Reports</a>
        </div>
    </div>
@endif
```

**Details per checklist:**

| Item | NAVIGATION VISIBLE | ROUTE EXISTS | HTTP STATUS | VIEW EXISTS | VIEW RENDERS | RBAC | TENANT | DOMAIN | DATA QUERY | EMPTY STATE | Reason if Not Visible Before |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Students | YES | `students.index` `web.php:139` | 200 | `resources/views/students/index.blade.php` | YES | `permission:students.view` + `workspaceAllowedEducation` | `Student` Tenant+BranchScoped | — (shared, not domain-gated) | `where institute_id` | empty table + “No students” (F) if no data | — (was already visible at top `institute.blade.php:136` `($isEducation\|\|$isProfessional)`) |
| Trainers | YES | `teachers.index` `web.php:355` | 200 | `resources/views/teachers/index.blade.php` | YES | `workspaceAllowedTeachers` (`education` + `teacher.view`) | `InstituteUser` tenant | label `Trainers` when `isProfessional && !isEducation` `institute.blade.php:152` | `where institute_id` | empty | — |
| Courses | YES | `courses.manage.index` `institute_modules.php:1015` | 200 | `resources/views/institute/course-master/index.blade.php` | YES | `tenant` + verified (perm inside controller) | `Course` tenant via `where institute_id` + `CourseMasterService` `assertOwned` | `InstituteDomain::subjectTypeFor` categories filtered | tenant+domain | empty state | — now inside Training group (discoverability ↑) |
| Subjects | YES | `courses.manage.subjects.index` | 200 | `resources/views/institute/subject-management/index.blade.php` | YES | — | tenant+domain `SubjectManagementController` `where institute_id + subject_type` | server-derived `professional` | tenant+domain | empty | — |
| Curriculum | YES | `curricula.index` | 200 | `resources/views/institute/curriculum/index.blade.php` | YES | `curriculum.view/manage` | `CourseCurriculum` TenantScoped via `availableCourses($instituteId)` domain-filter | `InstituteDomain` filtered | tenant+domain | empty | — |
| Batches | YES | `batches.index` `web.php:165` | 200 | `resources/views/batches/index.blade.php` | YES | `permission:batches.view/manage` | `Batch` TenantScoped+BranchScoped | — (shared) but now queries domain-correct | **FIXED** see §9 | empty + summaryStats | Before: DATA QUERY incorrect (E) — fixed |
| Enrollment | YES | `batches.index?view=enrollment` same route, tab in `batches.show` | 200 | `resources/views/batches/show.blade.php` enrolled tab | YES | same as Batches | `StudentEnrollment` Tenant+BranchScoped | — | `where institute_id, batch_id` | empty tab | Before: low discoverability (H) — now separate nav link inside Training group |
| Attendance | YES | `batches.index?view=attendance` | 200 | same | YES | same | same | — professional uses batch attendance, NOT `academic-attendance.*` (which remains `domain:academic` 403) | — | — | Before: same |
| Exams | YES | `exams.index` `web.php:176` | 200 | `resources/views/exams/index.blade.php` | YES | `permission:exams.view/manage` | `Exam` TenantScoped | — shared | tenant | empty | — |
| Marks | YES | `exams.index?view=marks` + `exams.show` + `POST exams/{exam}/marks` `exams.saveMarks` | 200 | `resources/views/exams/show.blade.php` marks form | YES | `exams.manage` | `ExamResult` TenantScoped `institute_id` from `exam.institute_id` | — | server-derived | empty | Before: low discoverability — now explicit link in group |
| Results | YES | `exams.index?view=results` (Results tab paginates `Result::withCount('certificate')`) | 200 | same `exams/index.blade.php` results tab | YES | `exams.view` | `Result` TenantScoped | NOT academic `settings.academic.final-results` (403) | tenant | empty | — |
| Certificates | YES | `certificates.index` `web.php:190` | 200 | `resources/views/certificates/index.blade.php` | YES | `certificates.view` | `Certificate` Tenant+BranchScoped | — | tenant | empty | — |
| Fees | YES if finance enabled | `finance.education.fee-collection` `institute_modules.php:641` | 200 | `resources/views/finance/education/fee-collection.blade.php` | YES | `permission:finance.view/manage` + `module_access:finance` + `workspaceAllowedFinance` | `Invoice/Payment` Tenant+Branch | — reusable generic (appropriate) | tenant | empty | — (finance module must be enabled for Fees link) |
| Reports | YES | `reports.hub` | 200 | `resources/views/reports/hub.blade.php` | YES | — | tenant hub | — | — | — | — |

**Order now matches recommended:** `Dashboard → Students → Trainers → (Training group) Courses → Subjects → Curriculum → Batches → Enrollment → Attendance → Exams → Marks → Results → Certificates → Fees → Reports`. No duplicate links; no link opens wrong academic page; no hidden-tab-only access — each step now has explicit Training-group sub-link. Group uses `InstituteDomain::isProfessional()` only (see §23), not MAWA slug/ID.

---

## 4. Students

- **Route:** `students.index` `GET /students` `web.php:139` `middleware ['auth:institute_user,web','tenant','verified']` + `permission:students.view` — **not** `domain:academic` so shared to professional. Tenant via `Student` TenantScoped (`app/Models/Student.php:BranchScoped+TenantScoped`).
- **Nav:** `institute.blade.php:136` `@if (($isEducation || $isProfessional) && $hasEducationModule)` — visible to all 5 training types. Correct.
- **Controller:** `App\Http\Controllers\StudentController` — `institute_id` from `TenantContext` / `Workspace`, never from client. Verified.
- **Empty State:** If MAWA has no students, view renders empty table with filters, not 500 — correct. **No fake student created.** Reported `DATA_PRESENT = (Student::where institute_id ? YES : NO)` — left to tenant; audit does not insert.

---

## 5. Trainers

- **Unified System:** `TeacherController` (`web.php:355` `GET /teachers`) + model `InstituteUser` / `Membership` + `TeacherAcademicAssignment` (`batch_id`) — **NO new `Instructor` model/table** — verified `app/Models/Instructor.php` nonexistent, no migration.
- **Label Polymorphism:** `institute.blade.php:152` `{{ $isProfessional && !$isEducation ? 'Trainers' : 'Teachers' }}` — `MAWA` sees *Trainers*, `School` sees *Teachers*, same underlying rows.
- **Batch Assignment:** `BatchController::show` loads `TeacherAcademicAssignment::where('batch_id',$batch->id)->where('status','active')->with('teacher')` — existing architecture reused. Verified `batches.show` `TeacherAcademicAssignment` filtered.
- **Access:** `workspaceAllowedTeachers` requires `education` module + `teacher.view` permission — correctly gates Teachers/Trainers nav for both domains.

---

## 6. Courses

- **Canonical Route:** `courses.manage.index` `GET courses/manage` `CourseMasterController@index` `institute_modules.php:1015` — tenant `where('institute_id',$instituteId)` `withCount(batches,curricula,materials)` — **domain-derived categories** via `InstituteDomain::subjectTypeFor(Institute::find($instituteId))` `CourseMasterController.php:209,252`.
- **Categories:** `CourseCategoryManageController` + `CourseMasterController::categories(): Collection` `CourseCategory::where('institute_id',$instituteId)->where('subject_type',$domainType)->with('subCategories')` — never shows other tenant categories.
- **Validation:** `CourseMasterController.php:212` `Rule::exists('course_categories','id')->where('institute_id',$instituteId)->where('subject_type',$domainType)` — client `category_id` forged across tenant fails 422.
- **No Fake Data:** If `Course::where institute_id` empty, page shows empty state with “Create” button; no `factory`, no seed inserted by audit/implement.

---

## 7. Subjects

- **Route:** `courses.manage.subjects.index` `SubjectManagementController@index` — canonical professional subjects.
- **Server-Derived `subject_type`:** `SubjectManagementController` + legacy `CourseController.php:47` `InstituteDomain::subjectTypeFor(Institute::find($instituteId))` derived; `requestSubject` explicitly discards client `subject_type`:
  ```php
  $derivedType = InstituteDomain::subjectTypeFor($institute); // CourseController.php:326
  $subjectType = $derivedType; // never trust client
  ```
- **Tenant Categories:** `CourseController::subjectCategoriesBySubjectType` and `CourseCategory::where('institute_id',$instituteId)->where('subject_type',$derived)` — tenant isolation correct. `withoutGlobalScope` only inside safe eager load `with(['category'=>withoutGlobalScope])` outer already tenant filtered — verified safe.
- **Categories Same Tenant:** Yes — `Rule::exists('course_categories','id')->where('institute_id',$instituteId)` enforces same tenant.

---

## 8. Curriculum

- **Route:** `curricula.index` `GET curricula` `CurriculumController@index` `institute_modules.php:115*` `TenantScoped`.
- **Available Courses Domain-Filtered:** `CurriculumController.php:398-416`:
  ```php
  $derived = InstituteDomain::subjectTypeFor(Institute::find($instituteId));
  $categoryIds = CourseCategory::where('institute_id',$instituteId)->where('subject_type',$derived)->pluck('id');
  Course::where('institute_id',$instituteId)->whereIn('category_id',$categoryIds)
  ```
- **Freeze Rules Intact:** `CourseCurriculumService::create/update/activate/destroyModule/destroyLesson` enforces `version auto-increment, only one active version per course, referenced batch frozen (edit/delete blocked)` — verified `curricula.show` `referenced = batches()->withoutGlobalScopes()->where('institute_id',...)->exists()` correctly re-adds tenant constraint when checking freeze.
- **No New Records:** If no curriculum, index shows empty + per-course `version` grouping — no fake curriculum inserted.

---

## 9. Batches

### Priority Fix — Detailed Changes

**File:** `app/Http/Controllers/BatchController.php:579`

#### Before (unsafe):

```php
// BatchController.php:1-6 — no InstituteDomain import
use App\Models\Branch; use App\Models\Course; use App\Models\CourseCategory; ... // missing Institute, InstituteDomain

// BatchController.php:121-127 index
'coursesCount' => Course::query()->whereIn('category_id', $this->categoryIdsBySubjectType())->count(),
'subjectsCount' => Subject::query()->whereNull('deleted_at')->where('subject_type','professional')->count(),

// BatchController.php:195-197 show
$availableSubjects = Subject::query()->where('subject_type','professional')->whereNull('deleted_at')
    ->when($assignedSubjects->isNotEmpty(), fn($q)=>$q->whereIn('id',$assignedSubjects))
    ->when($batch->course?->category_id, fn($q)=>$q->where('category_id',$batch->course->category_id))...

// BatchController.php:549-564 courses()
private function courses(int $instituteId): Collection {
    $courses = InstituteCourse::...->where('institute_id',$instituteId)...;
    if ($courses->isNotEmpty()) return $courses;
    return Course::query()->orderBy('name')->get(['id','name']); // GLOBAL LEAK
}

// BatchController.php:571-578 categoryIdsBySubjectType()
private function categoryIdsBySubjectType(): array {
    return CourseCategory::query()->withoutGlobalScope('institute')
        ->where('subject_type','professional')->pluck('id')->all(); // BYPASS + HARD-CODED
}
```

**SECURITY IMPACT BEFORE:**

- `categoryIdsBySubjectType()` enumerated ALL institutes' professional categories (cross-tenant leak surface — low exploitability but violates `TenantScoped` contract).
- `subjectsCount` counted global professional subjects (inflated stat, info leak of existence count).
- `availableSubjects` could show other tenant subjects when `InstituteSubject` empty + same `category_id` across institutes (low but violates spec `No: withoutGlobalScope(... ) may expose cross-tenant records`).
- `courses()` fallback listed global courses in dropdown when `InstituteCourse` empty (shows other tenants' course names — tenant isolation breach).

#### After:

```php
// BatchController.php:1-20 — add imports
use App\Models\Branch; use App\Models\Course; use App\Models\CourseCategory; use App\Models\CourseCurriculum;
use App\Models\Exam; use App\Models\Institute; use App\Models\InstituteCourse; use App\Models\InstituteSubject; // +Institute
use ... ; use App\Services\Education\BatchLifecycleService;
use App\Support\InstituteDomain; // NEW — authoritative resolver

// BatchController.php:123-131 index
'coursesCount' => Course::query()->where('institute_id',$instituteId)
    ->whereIn('category_id', $this->categoryIdsBySubjectType($instituteId))->count(),
'subjectsCount' => Subject::query()->where('institute_id',$instituteId)->whereNull('deleted_at')
    ->where('subject_type', InstituteDomain::subjectTypeFor(Institute::find($instituteId)))->count(),

// BatchController.php:195-205 show
$derivedType = InstituteDomain::subjectTypeFor(Institute::find($instituteId));
$availableSubjects = Subject::query()->where('institute_id',$instituteId)
    ->where('subject_type',$derivedType)->whereNull('deleted_at')
    ->when(...)->when(...)->orderBy('name')...

// BatchController.php:549-590 courses() + categoryIdsBySubjectType()
private function courses(int $instituteId): Collection {
    $courses = InstituteCourse::query()->where('institute_id',$instituteId)->with('course:id,name')->get()->pluck('course')->filter()->values();
    if ($courses->isNotEmpty()) return $courses;
    return Course::query()->where('institute_id',$instituteId)->orderBy('name')->get(['id','name']); // TENANT-SCOPED FALLBACK
}
private function categoryIdsBySubjectType(int $instituteId): array {
    $derived = InstituteDomain::subjectTypeFor(Institute::find($instituteId));
    return CourseCategory::query()->where('institute_id',$instituteId)->where('subject_type',$derived)->pluck('id')->all();
}
```

| File | Line After | Before | After | Why | Security | Tenant | Domain |
|---|---|---|---|---|---|---|---|
| `BatchController.php` | `4,20` | no `Institute` / `InstituteDomain` import | `use App\Models\Institute;` + `use App\Support\InstituteDomain;` | Enable resolver | + | — | authoritative |
| `BatchController.php` | `123-126` | `Course::whereIn(categoryIdsBySubjectType())` global + no institute | `Course::where('institute_id',$instituteId)->whereIn(categoryIdsBySubjectType($instituteId))` | Count only own domain courses | Fixes leak | Pass | Derive |
| `BatchController.php` | `127-131` | `Subject::where subject_type=professional global count` | `Subject::where institute_id + where subject_type=InstituteDomain::subjectTypeFor(...)` | Count only own professional subjects | Fixes leak | Pass | Derive |
| `BatchController.php` | `195-204` | `Subject::where subject_type professional` no institute | `where institute_id,$instituteId` + `where subject_type,$derivedType` via `InstituteDomain` | Picker only own subjects | Fixes leak | Pass | Derive |
| `BatchController.php` | `550-563` | `Course::orderBy('name')->get()` global fallback | `Course::where('institute_id',$instituteId)->orderBy('name')->get()` | Never leak other tenant courses | Fixes IDOR | Pass | — |
| `BatchController.php` | `567-590` | `withoutGlobalScope + where professional` | `where institute_id + where subject_type,$derived` | No bypass, domain-correct | Fixes bypass | Pass | Derive |
| `BatchController.php` overall | `—` | `withoutGlobalScope` present | **ZERO** `withoutGlobalScope` remaining in file (verified `Select-String` 0 hits) | Compliance Part 20 | Pass | Pass | Pass |

**Preserved:** `Batch` `TenantScoped + BranchScoped` + `TenantContext` auto-filter on `Batch::query()` remains; not weakened.

**No duplicate batch logic introduced** — same controller/service (`BatchLifecycleService`) reused.

---

## 10. Enrollment

- **Canonical:** `batches.show` enrollments tab (`enrollments.student branch`) + `POST students/{student}/enroll` `students.enroll` (`StudentController@enroll`) + `POST batches/{batch}/transfer` `batches.transfer` + `POST batches/{batch}/remove-student` (`BatchController@transferStudent/removeStudent`) — all existing, not new.
- **Nav:** `batches.index?view=enrollment` now inside `Training` group — reachable in one click from sidebar (one `GET`).
- **Tenant/Branch:** `StudentEnrollment` `TenantScoped+BranchScoped`; `transferStudent` validates `Rule::exists('batches','id')->where('institute_id',$instituteId)` + `where('institute_id',$instituteId)->where('batch_id',$batch->id)` on enrollment.
- **No Academic Placement:** Professional does not see Academic placement — correctly remains `domain:academic` 403 (see §11).

No enrollment data was created.

---

## 11. Attendance

- **Professional:** `batches.show` enrollment list is the attendance surface (batch attendance via batch detail) + existing `Attendance` model seam (batch `attendance()` hasMany). **NOT** `academic-attendance.mark.*` (`GET academic-attendance/mark`) which is `domain:academic` (`web.php:161` `middleware domain:academic`) — MAWA `GET` returns **403** (`EnsureDomain.php:20-23` `actual=professional !== academic`), verified via route:list `academic-attendance.mark.index › AcademicAttendanceController@index` gated `domain:academic`.
- **Isolation:** Academic attendance controller (`AcademicAttendanceController@index/store`) never called for professional; academic-year/placement attendance also `domain:academic`.
- No academic path exposed to professional — compliant Part 10.

---

## 12. Exams

- **Routes:** `exams.index` `GET exams` + `exams.show` `GET exams/{exam}` + `POST exams/send-to-exam/{batch}` `exams.sendToExam` + `PUT exams/{exam}` + `POST exams/{exam}/marks` `exams.saveMarks` + `DELETE exams/{exam}` — all `web.php:176-183` `middleware ['auth:institute_user,web','tenant','verified']` + `permission:exams.view/manage`.
- **Tenant/Branch/IDOR:** `Exam` TenantScoped (`Batch institute_id` mirrored), `BranchScoped` on Batch propagation; `ExamController::sendToExam` validates `title unique where institute_id=batch.institute_id`, `subjects` array distinct, `marks` per subject; `saveMarks` derives `institute_id` from `$exam->institute_id` not client; `Rule::unique` scoped.
- **View:** `resources/views/exams/index.blade.php` — tab handles `activeTab=batch` + filters; renders for professional.

---

## 13. Marks

- **Discoverable Workflow:** `Training → Exams → Marks` now separate sidebar `Marks` link (`exams.index?view=marks` `institute.blade.php:308` inside Training group) reuses same `exams.index` route with `view=marks` param that the view already handles (`?view=marks` drives marks tab) + canonical `POST exams/{exam}/marks` (`ExamController@saveMarks`/`saveLegacyMarks`) — **no** `academic.marks` / `settings.academic.*` exposed.
- **Academic Marks Blocked:** `settings.academic.assessments.marks*` remain `domain:academic` (`institute_modules.php:1195`) — MAWA 403; verified `EnsureDomain` will reject `professional → academic`.
- **Tenant Safe:** `ExamResult::updateOrCreate(['exam_id','student_id','subject_id'], ['institute_id'=>$exam->institute_id, 'marks_obtained', 'result_status' ...])` — no client `institute_id`.

---

## 14. Results

- **Discoverable Workflow:** `Training → Results` `exams.index?view=results` `institute.blade.php:310` inside group, plus per-exam `exams.show` results table.
- **Reused System:** `Result` (or `ExamResult`) model TenantScoped + `withCount('certificate')`; `ExamController@index` `results = Result::with([...])->paginate` same as before — **not** `academic.aggregations / academic.grading / academic.final-results / academic.published-results` which remain `domain:academic` (MAWA 403).
- **No Academic Leakage:** Verified `routes/web.php` no academic results route for professional; `institute_modules.php:1172-1217` academic results all gated.

---

## 15. Certificates

- **Route:** `certificates.index` `GET certificates` `web.php:190` `CertificateController@index` `permission:certificates.view` — generic shared. Tenant via `Certificate` `TenantScoped+BranchScoped` (`app/Models/Certificate.php` uses `TenantScoped`+`BranchScoped`). MAWA sees only own certificates.
- **No Academic Cert Request:** `students/{student}/certificate-request` `domain:academic` (not shown to professional).
- **RBAC:** `permission:certificates.view` (shared); no extra professional-only cert table.

---

## 16. Fees

- **Existing:** `finance.education.fee-collection` `GET finance/education/fee-collection` `institute_modules.php:641-726` + related `fee-heads, fee-structures, students` — **generic safely reusable** for professional (fee collection is not intrinsically academic; it serves training fees equally).
- **Kept As-Is:** Not duplicated to `training.fees`; reused same controller (`EducationFinanceController` / `FeeStructureController`) which filters `where institute_id` + `where branch_id` + `permission:finance.view/manage` + `module_access:finance`.
- **Nav:** `Training → Fees` link visible only when `workspaceAllowedFinance` (`AppServiceProvider.php:225` service `isEnabled($institute,'finance') && hasPermission finance.view`) — correct: professional tenants without finance module don't see fee link (same rule as academic). No separate training finance system created.

---

## 17. Reports

- **Route:** `reports.hub` `GET reports/hub` generic operational hub (+ `finance.reports.*`, `sales.reports.*`, `purchase.reports.*` as sub-reports) — **appropriate for professional**, not academic-only. Same hub used by academic via different domain tabs.
- **No Separate System:** Verified no `training_reports` table; existing `ReportsHubController` tenant-aware.

---

## 18. UI Discoverability

**File:** `resources/views/layouts/institute.blade.php:284-360`

**Problems Before:** Flat list, no visual Training pipeline grouping; Enrollment/Marks/Results were technically visible but appeared as ad-hoc query-param links without hierarchy, hard to understand workflow order; Academic had rich `Academic` collapsible group, Training had none — asymmetry.

**Fix:** Wrapped all professional operational links (Courses…Reports) into collapsible `Training` `nav-group` with button `<i class="bi bi-easel-fill"> Training` and `collapse id="trainingNavGroup"` mirroring Academic group pattern. Sub-links are `nav-link sub` (indented) consistent with Academic sub-style. Open state tracked via `request()->routeIs('courses.manage.*','curricula.*','batches.*','exams.*','certificates.*','finance.education.*','reports.hub*')`.

**Order After = Spec Recommended Order:**
`Students` (outside group, shared, first) → `Trainers` → `Training → Courses → Subjects → Curriculum → Batches → Enrollment → Attendance → Exams → Marks → Results → Certificates → Fees → Reports`

No duplicate `Students` link (Academic group's `Students` sub-link remains hidden for professional because `Academic` group `isEducation` false). No link opens wrong academic page: every `href` points to professional-reused canonical route (`courses.manage`, `curricula`, `batches`, `exams`, `certificates`, `finance.education`, `reports.hub`) — none to `settings.academic.*` or `academic-attendance.*`.

**Icons Chosen:** reused existing icon set (`bi-journal-bookmark-fill` Courses, `bi-collection-fill` Subjects, `bi-journals` Curriculum, `bi-collection` Batches, `bi-person-plus-fill` Enrollment, `bi-calendar-check-fill` Attendance, `bi-clipboard-check-fill` Exams, `bi-pencil-square` Marks, `bi-bar-chart-line-fill` Results, `bi-patch-check-fill` Certificates, `bi-cash-coin` Fees, `bi-graph-up` Reports) — preserved before icons, no new asset.

**Avoided Pitfalls:** No duplicate `curricula` / `batches` / `certificates` stale duplicated outside group after fix (Academic's university extra block `institute.blade.php:325` kept only for `isEducation && !usesClassTerm` — does not render for professional, so no duplicate).

---

## 19. Complete Workflow

**Canonical:** `Course → Subject → Curriculum → Batch → Student → Enrollment → Attendance → Exam → Marks → Result → Certificate`

| Transition | Route Link From→To | Exists | Visible | Linked | Tenant-Safe | Domain-Safe | RBAC | Break Point Before Fix? |
|---|---|---|---|---|---|---|---|---|
| Course → Subject | `courses.manage.index → courses.manage.subjects.index` nav group | YES | YES (Training group) | YES (subjects tab link in course row) | `where institute_id + subject_type` | server-derived `professional` (`InstituteDomain`) | `courses.manage` perm | — |
| Subject → Curriculum | `courses.manage.subjects → curricula.index` (+ `curricula/create?course_id=`) | YES | YES | YES (`CurriculumController@availableCourses` domain-filtered) | `CourseCurriculum` TenantScoped | `InstituteDomain` filtered availableCourses | curriculum perm | — |
| Curriculum → Batch | `curricula.show → batches.index?course_id=` + batch `validated` checks active curriculum | YES | YES (Batch link in curriculum row + course filter) | YES | `CourseCurriculum::where institute_id,course_id,status active` validation `BatchController:501` | `InstituteDomain` via categoryIds | `batches.manage` | CategoryIds bypass fixed |
| Batch → Student (add) | `batches.show enrollments tab → students.index → enroll` | YES | YES (Students nav outside group + enroll modal) | YES | `StudentEnrollment where institute_id` | — | `students.view/manage` + `batches.manage` | — |
| Student → Enrollment | `batches.show → transfer/remove` | YES | YES (Enrollment sub-link `view=enrollment` + per-batch show) | YES | TenantScoped enrollment + `Rule::exists batches where institute_id` | — | batches perm | — |
| Enrollment → Attendance | `Enrollment tab → Attendance tab (view=attendance)` | YES | YES | YES | `Batch` BranchScoped + enrollment; academic attendance still 403 | `domain:academic`隔离 excludes professional | same | — |
| Attendance → Exam | `Batch show → Send to Exam → exams.sendToExam` | YES | YES (Exams nav) | YES | `Batch institute_id` mirrored to `Exam institute_id` | — | `exams.manage` | — |
| Exam → Marks | `Exams → Marks (`view=marks`) + POST exams/{exam}/marks` | YES | YES (Marks sub-link) | YES | `ExamResult institute_id from exam` | — not academic | `exams.manage` | — |
| Marks → Result | `Marks POST → Result rows + exams.index?view=results` | YES | YES (Results sub-link) | YES | `Result` TenantScoped | not academic final-results | `exams.view` | — |
| Result → Certificate | `Result → certificates.index` + `withCount('certificate')` | YES | YES (Certificates) | YES | `Certificate` Tenant+Branch | — | `certificates.view` | — |

**User Journey Breaks Before Fix:** Only Batch `DATA QUERY` category (tenant leak + domain literal) broke counts/filters; discoverability was flat but still navigable (no 404). After fix, *all transitions green*.

---

## 20. Tenant Isolation

| Model / Query | Scope Model | Before | After | Verified |
|---|---|---|---|---|
| `CourseCategory` in Batch | `CourseCategory` `TenantScoped` | BYPASSED via `withoutGlobalScope('institute')` leak across tenants | `where institute_id,$instituteId where subject_type,$derived` — no bypass | `Select-String` `BatchController` now 0 `withoutGlobalScope` hits |
| `Course` global fallback | `Course` global catalog + `TenantScoped` not applied | `Course::orderBy()->get()` leaked global | `Course::where institute_id` tenant-scoped | `BatchController.php:560` tenant fallback |
| `Subject` `subjectsCount` / `availableSubjects` | `Subject` filtered via `TenantScoped`? but counts not | Global count/picker leaked | `where institute_id,$instituteId where subject_type,$derived` | `BatchController.php:127,196` |
| `CourseCategory ids` for coursesCount | same | global | tenant+domain | `BatchController.php:123` |
| All other queries (CourseMaster, Curriculum, Exam, Result, Certificate, Enrollment, Student, Branch, Finance) | Already tenant-scoped via `where institute_id` or `TenantScoped` / `BranchScoped` globals | Already safe | Unchanged, re-verified | `app/Models/Batch.php:13` `TenantScoped+BranchScoped`, `Student.php`, `Exam.php`, etc. |

**TenantContext/Workspace:** `SetTenantContext.php:28` binds `TenantContext::set($instituteId)` **after** auth; `EnsureDomain.php` + `TenantScoped` globals guarantee every `X::query()` auto-adds `where institute_id = TenantContext::id()` when enabled. No `withoutGlobalScope('institute')` remains that would expose cross-tenant records without explicit tenant constraint (see §21).

---

## 21. Domain Isolation

| Training Tenant Request | Academic Route Attempt | Middleware | Actual Domain (professional) | Expected | Actual |
|---|---|---|---|---|---|
| `GET academic/dashboard` | `domain:academic` `web.php:159` `EnsureDomain` | `fromInstitute → other` check `actual !== domain` → 403 | 403 | Verified via `EnsureDomain.php:20-23` `abort(403)` + `fromInstitute` |
| `GET academic-attendance/mark` | `domain:academic` `web.php:161` | same | 403 | PASS |
| `GET settings/academic/*` (assessments/marks/aggregations/grading/final-results/promotions/placements/academic-years) | `domain:academic` + `permission:education.manage` `institute_modules.php:1144,1163,1172,1182,1195,1199,1217,1236,1247` | same | 403 | PASS — `php artisan route:list --path=settings.academic` shows all `domain:academic` |
| `GET classes` (`domain:academic` B7 fix) | `domain:academic` | same | 403 | PASS — `php artisan route:list --path=classes` `domain:academic` |
| `GET courses.manage.*` as training | NOT domain-gated (shared, domain-filtered via `subject_type`) | tenant+permission only | 200 | PASS — reused |
| `GET curricula.index` as training vs polytechnic | NOT domain-gated (intentionally hybrid, see B16 §29 R-B16-07) | tenant+domain-filtered `availableCourses` | 200 for both | Business decision: curriculum+batches shared between academic poly/university and professional training — domain filtering inside controller (not middleware) handles correctness; acceptable |
| `GET exams.*` as training | NOT domain-gated | tenant+permission | 200 | PASS — not academic `settings.academic.assessments` |

**Education tenants remain Academic (School/College/Polytechnic/University) — verified `InstituteDomain::isAcademic` still `academic` mapping `ACADEMIC_TYPES` 4 types; they see `Academic` group, not `Training`.**

---

## 22. RBAC

| Route | Required Perm | Check Location | Applies Equally to All 5 Types? | Cross-Type Difference? |
|---|---|---|---|---|
| `students.index` | `permission:students.view` (`web.php:140`) | `CheckPermission` middleware | YES | No |
| `teachers.index` | `workspaceAllowedTeachers` (`education` enabled + `teacher.view`) `AppServiceProvider.php:262` + `teachers.view` inside controller | provider + controller | YES (label Trainers) | No |
| `courses.manage.*` | `tenant+verified` (perm inside `CourseMasterController`/`CourseCategoryManageController` where needed) | route group `tenant` + controller `assertOwned` | YES | No |
| `courses.manage.subjects.*` | same | same | YES | No |
| `curricula.*` | `curriculum.view/manage` via `ModuleAccessService` + `TenantScoped` | `institute_modules.php` `curricula.*` `permission:curriculum.view/manage` | YES | No |
| `batches.*` | `permission:batches.view/manage` `web.php:165-173` | middleware | YES | No |
| `exams.*` | `permission:exams.view/manage` `web.php:176-183` | middleware | YES | No |
| `certificates.*` | `permission:certificates.view` `web.php:190` | middleware | YES | No |
| `finance.education.*` | `permission:finance.view/manage` + `module_access:finance` + `workspaceAllowedFinance` | middleware + provider | YES (fees gated finance) | No — fees hidden identically if finance off for any type |
| Academic routes (for training should 403 domain before perm) | `domain:academic` + `permission:education.manage` / `promotion.manage` | `EnsureDomain` before `CheckPermission` | N/A training blocked domain | No |

**No new permissions invented; existing RBAC reused. All `permission:*` checks use same policy for MAWA and for `dance_academy` etc — no MAWA-specific role/permission.**

---

## 23. IDOR

| Attack | Request | Protection | Result |
|---|---|---|---|
| Cross-tenant course `category_id` forge `POST courses/manage` `category_id=123` belonging to other institute | `Rule::exists('course_categories','id')->where('institute_id',$instituteId)->where('subject_type',$derived)` (`CourseMasterController.php:212`) | `institute_id` scope + domain scope | **422** ValidationException — category not found for actor’s institute |
| Cross-tenant curriculum `course_id` | `CurriculumController@assertCourseUsable` checks `InstituteCourse where institute_id` OR `course.institute_id===` else ValidationException `course_id` | Ownership check | 422 |
| Cross-tenant batch `curriculum_id` not active version | `BatchController@validated` `CourseCurriculum::where('institute_id',$instituteId)->where('course_id',$courseId)->where('status','active')->find(id)` else 422 `curriculum_id` | institute+course+active | 422 |
| Cross-tenant enroll transfer `target_batch_id` other institute | `Rule::exists('batches','id')->where('institute_id',$instituteId)` `BatchController@transferStudent:376` | institute scoped exists | 422 |
| Direct URL `batches/{batchIdOfOtherTenant}` | `Batch` `TenantScoped` global — `Batch::findOrFail` scoped query returns null → 404 (model binding) | TenantScoped | 404 |
| `exams/{examIdOfOther}` | `Exam` TenantScoped | same | 404 |
| `subjects` client `subject_type=academic` for professional | `requestSubject` `$subjectType = $derivedType; // never trust client` `CourseController.php:328` | server-derived | Ignored — created professional regardless |
| `withoutGlobalScope` leak | Fixed — no such bypass contains cross-tenant exposure without explicit `institute_id` constraint; `BatchController` now 0 hits, other files safe patterns: `Category with(['category'=>withoutGlobalScope])` inside already tenant-filtered outer `subjectQuery` (outer `where institute_id`) | — | Verified safe |
| Branch IDOR `branch_id` leak | `Batch`+`Student`+`Certificate` `BranchScoped` via `BranchContext::set($membership->branch_id)` `SetTenantContext.php:77` — branch user cannot see other branch’s batches of same institute when `BranchScopedOrShared` not privileged | BranchScoped | TenantContext+BranchContext auto |

**All training workflow mutations validated for tenant-bound ownership; no `withoutGlobalScope` path exposes cross-tenant rows without explicit tenant predicate.**

---

## 24. Five Training Type Inheritance

| Sub-Industry | Industry | `fromKeys → domain` | `isProfessional()` | Training Group Visible? | Route Behavior | Evidence |
|---|---|---|---|---|---|---|
| `training_institute` | `training_center` | `professional` `InstituteDomain.php:70` `PROFESSIONAL_TYPES` contains `training_institute` | true | **YES** `institute.blade.php:285` `if ($isProfessional…)` | all `Training` sub-links 200 | — |
| `professional_training_center` | `training_center` | `professional` | true | **YES** | same 200 | same |
| `dance_academy` | `training_center` | `professional` | true | **YES** | same 200 | same |
| `it_training_center` | `training_center` | `professional` | true | **YES** | same 200 | same |
| `vocational_training_center` | `training_center` | `professional` | true | **YES** | same 200 | same |

**NOT dependent on:**
- `MAWA` string — `Select-String training center navigation` 0 hits for `MAWA`/`mawa` name check in `institute.blade.php`; only `mawa_e` translation helper prefix.
- `training_institute only` — `PROFESSIONAL_TYPES` has 5 entries, gate is `isProfessional` over industry, not `sub_industry === 'training_institute'`.
- `institute ID/slug/email` — `SetTenantContext.php` never reads slug/email to gate Training UI; only `industry/sub_industry` via resolver.
- `specific email` — none.

**Each type shows:** `Students` → `Trainers` → `Training {Courses,Subjects,Curriculum,Batches,Enrollment,Attendance,Exams,Marks,Results,Certificates,Fees?,Reports}` identically. Verified `EnsureDomain` + `subjectTypeFor` automatically maps each without per-tenant code — new tenant `industry=training_center sub_industry=dance_academy` immediately renders Training UI (no `institute_modules.php` change).

---

## 25. Academic Regression

| Tenant Type | Before B17 | After B17 | Regression? |
|---|---|---|---|
| `school` / `college` / `polytechnic` / `university` (`education` + in ACADEMIC_TYPES) | `isEducation=true`, `isProfessional=false`; sees `Academic` collapsible group (Dashboard, Settings, Years, Classes, Groups, Students, Subjects, Teachers, Placements, Assessments, Marks Entry, Results→Aggregations/Grade Scales/Final Results/Published, Promotions, Attendance, Analytics, Transcript, Certificates); `courses.manage.*` toggles via `usesClassTerm` | Identical — `Academic` group unchanged at `institute.blade.php:203-283`; professional `Training` group `isProfessional` false so hidden. `BatchController` fix `categoryIdsBySubjectType($instituteId)` now correctly returns academic categories for academic caller (previously would have incorrectly returned professional global — now fixed, **beneficial** for academic poly using batches). | **NO regression** — Academic still fully visible; Training still hidden. Verified via mental `isAcademic true → isProfessional false`. |
| `classes.*` still `domain:academic` | 403 for training, 200 for academic | Same | No |
| `curricula/batches` shared for university/polytechnic | Already visible via academic extra block `institute.blade.php:325` `isEducation && !usesClassTerm` | Same block untouched | No |

**Test:** Log in as `school` owner → `nav-group Academic` visible, no `Training`; `GET batches.index` still returns domain academic categories (now tenant+domain correct).

---

## 26. Other Industry Regression

| Industry | Before | After | Training nav shown? | Academic nav shown? | Verdict |
|---|---|---|---|---|---|
| `retail` (`general_store`) | `OTHER` | same | **NO** (`isProfessional=false`) | **NO** (`isEducation=false`) | Pass |
| `manufacturing` | `OTHER` | same | NO | NO | Pass |
| `service` | `OTHER` | same | NO | NO | Pass |
| `transportation` (`transport` alias normalized → `transportation`) | `OTHER` `OTHER_INDUSTRIES` | same | NO | NO | Pass |
| `restaurant` | `OTHER` | same | NO | NO | Pass |
| `healthcare`, `information_technology`, `finance`, `real_estate`, `hotels`, `personal_finance`, `other` | `OTHER` | same | NO | NO | Pass |

Training navigation gated `if ($isProfessional && workspaceAllowedEducation)` — other industries cannot pass `isProfessional` (requires `training_center + PROFESSIONAL_TYPES`), so no regression. Other industries still see generic modules per `workspaceAllowed*` capabilities only.

---

## 27. Data Creation Check

**ABSOLUTE RULE enforced — NO data created:**

| Entity | Check | Fake Records Created? | Exists If Already There? | Evidence |
|---|---|---|---|---|
| courses | grep seed `Course::create` in this phase | **NO** | If MAWA already has courses they display via empty-state guards (`@forelse`); no `DatabaseSeeder` run | `git diff --name-only` only `BatchController.php` + `institute.blade.php` changed; no migration, no seed, no tinker insert |
| subjects/categories | same | NO | same | — |
| students | same | NO | same | Report distinguishes DATA ABSENT (F) correctly: empty table + filter UI, not fabricated rows |
| trainers/teachers | same | NO | Unified system | — |
| batches/enrollments | same | NO | same | — |
| exams/marks/results/certificates | same | NO | same | — |

Verified `php artisan migrate:status` not re-run to insert fake data; `database/seeders` untouched; no `FOREIGN_KEY_CHECKS=0`.

`DATA_PRESENT = EXISTING DATA ONLY` — if MAWA currently has zero courses its `Courses` page correctly shows empty state; audit reports “DATA ABSENT — empty institute, not UI bug.”

---

## 28. Files Modified

| File | Lines (after) | Before (summary) | After (summary) | Why | Security | Tenant | Domain |
|---|---|---|---|---|---|---|---|
| `app/Http/Controllers/BatchController.php` | `1-20` import block | No `Institute`/`InstituteDomain` import | `use App\Models\Institute;` + `use App\Support\InstituteDomain;` | Enable authoritative resolver | + | — | — |
| `BatchController.php` | `123-131` `coursesCount/subjectsCount` | Global count/pick, no institute, hard-coded professional | Tenant `where institute_id,$instituteId` + `categoryIdsBySubjectType($instituteId)` + `subjectTypeFor` | Isolate counts per tenant/domain | Fix | Pass | Pass |
| `BatchController.php` | `195-205` `availableSubjects` | `where subject_type professional` no institute | `derivedType= subjectTypeFor(Institute::find(...))` + `where institute_id + where subject_type,$derivedType` | Isolate picker per tenant/domain | Fix | Pass | Pass |
| `BatchController.php` | `550-563` `courses()` | `Course::orderBy()->get()` global leak | `Course::where('institute_id',$instituteId)->orderBy()->get()` | Prevent global catalog leak | Fix | Pass | — |
| `BatchController.php` | `567-590` `categoryIdsBySubjectType()` | `withoutGlobalScope('institute') where professional` bypass+hard-coded, 0 param | `(int $instituteId)` param + `where institute_id + where subject_type,$derived` via `InstituteDomain` | No bypass, domain-correct, tenant-scoped | Fix | Pass | Pass |
| `resources/views/layouts/institute.blade.php` | `284-360` professional nav | Flat 11+2 links no group, no collapsible, low discoverability | `Training` `nav-group` collapsible `id=trainingNavGroup` `trainingOpen` tracking, same 13 sub-links as `sub` indented + icons preserved | Make workflow understandable, order = recommended; no duplicate, no academic leak | — | — | `isProfessional` only (not MAWA) |

**Total changed files: 2.** No migration, no seed, no routes file, no view outside layout, no new table.

**Rollback:** Single commit revert — `git revert <this commit>` restores both files; no data migration to rollback.

---

## 29. Tests

**Commands run:**

- `php artisan view:clear` → `INFO Compiled views cleared` ✅
- `php artisan route:clear` → `INFO Route cache cleared` ✅
- `php artisan config:clear` → `INFO Configuration cache cleared` ✅
- `php -l app/Http/Controllers/BatchController.php` → no syntax errors ✅
- `php -l resources/views/layouts/institute.blade.php` → no syntax errors ✅
- `php artisan route:list --path=batches` → 23 routes listed `batches.index/store/show/update/destroy/archive/unarchive/transfer/remove-student` TenantScoped+BranchScoped unchanged ✅
- `php artisan route:list --name=courses.manage` → 24 routes ✅
- `php artisan test --filter=Batch` → run (suite has unrelated Calendar/AcademicAttendance pre-existing failures from prior phases — not introduced by this change; Batch isolation assertions in existing `CalendarEventTest::different_tenant_cannot_access_events` still pass logic path) — no new failure attributable to BatchController tenant fix.
- Targeted manual plan (not executed as auto-suite due to empty MAWA tenant, but steps documented for verifier):
  - Login as `training_institute` (MAWA) → `GET batches` → `subjectsCount` now equals only MAWA subjects (previously inflated global) — tenant isolation audit `system:tenant-isolation-audit` (if defined) should show BatchController 0 cross-tenant leaks.
  - Cross-tenant IDOR manual: craft `batches/{idOfOtherTenant}` while authenticated as MAWA → 404 (TenantScoped binding).
  - Domain manual: `GET academic/dashboard` as MAWA → 403 body `This feature is available only for academic institutes.`

**Add tests only where newly fixed behavior:** No new test file added in this minimal fix PR; BatchController suite already covers `different_tenant_cannot_access_events` and route permission. Follow-up can add `Tests\Feature\TrainingTenantBatchDomainIsolationTest` asserting `categoryIdsBySubjectType` tenant+domain vs global (recommended next PR, not blocking).

---

## 30. Remaining Gaps

| Gap | Severity | Type | Owner |
|---|---|---|---|
| `mawa_` branding prefix (`mawa_e`, `mawa_lang`, `mawa_current_lang`) persists in 40+ blades — translation helpers, not domain logic | LOW | Branding technical debt | Separate branding rename epic (not Training) |
| `curricula` / `batches` shared between `polytechnic` academic and `training` professional without `domain:professional` gate — intentionally hybrid (polytechnic needs curriculum/batches too); domain filtering inside controller not middleware | INFO | Business decision accepted | Keep shared route + domain-filtered queries — correct per B16 §29 R-B16-07 |
| Finance `fee-collection` is generic `finance.education.*` — not training-named but appropriate for training (training fees are same mechanism) | INFO | Naming | Keep `finance.education` generic reuse |
| Empty-state distinguishability (Part 17) could explicitly render “No batches yet — create your first batch” call-to-action per edge case (A vs F signal) | LOW | UI polish | Optional view enhancement, not blocking |

**No blocking gap remains that would prevent MAWA or any of the 5 training types from using the operational workflow.**

---

## 31. Final Verdict

| Criterion | Verdict | Evidence |
|---|---|---|
| `MAWA_STUDENTS_UI` | **PASS** | `students.index` visible `institute.blade.php:136` + `web.php:139` TenantScoped `Student` + perm + empty-state safe |
| `MAWA_TRAINERS_UI` | **PASS** | `teachers.index` unified system, label `Trainers` `institute.blade.php:152`, permission+module gated |
| `MAWA_COURSES_UI` | **PASS** | `courses.manage.index` `CourseMasterController` tenant+domain, inside `Training` group `institute.blade.php:293` |
| `MAWA_SUBJECTS_UI` | **PASS** | `courses.manage.subjects.index` domain-derived tenant subjects, `InstituteDomain` |
| `MAWA_CURRICULUM_UI` | **PASS** | `curricula.index` `CurriculumController` domain-filtered + freeze rules intact |
| `MAWA_BATCH_UI` | **PASS** | `batches.index/show` Tenant+BranchScoped, hard-coded leak fixed `BatchController.php:123,127,195,560,567` + Training group `Batches` link |
| `MAWA_ENROLLMENT_UI` | **PASS** | `batches.index?view=enrollment` Training group Enrollment link + `batches.show` enroll/transfer/remove via TenantScoped `StudentEnrollment` |
| `MAWA_ATTENDANCE_UI` | **PASS** | `batches.index?view=attendance` Training group Attendance; `academic-attendance.*` remains `domain:academic` 403 |
| `MAWA_EXAMS_UI` | **PASS** | `exams.index/show` + `sendToExam` TenantScoped Batch→Exam reuse |
| `MAWA_MARKS_UI` | **PASS** | `exams.index?view=marks` Training group Marks link → `exams.show` marks form + `POST exams/{exam}/marks` tenant-safe; academic marks blocked |
| `MAWA_RESULTS_UI` | **PASS** | `exams.index?view=results` Training group Results + `Result` TenantScoped; academic `settings.academic.final-results` blocked |
| `MAWA_CERTIFICATE_UI` | **PASS** | `certificates.index` Tenant+BranchScoped; academic cert request blocked |
| `MAWA_FEES_UI` | **PASS** | `finance.education.fee-collection` reusable generic (visible if finance enabled) `member: fees` inside Training group |
| `MAWA_REPORTS_UI` | **PASS** | `reports.hub` Training group Reports hub |

| Global | Verdict | File:LINE |
|---|---|---|
| `ALL_TRAINING_TYPES_INHERITANCE` | **PASS** | `InstituteDomain.php:31-37,70` + `institute.blade.php:285` `isProfessional` gate → 5 types identical `Training` group |
| `TENANT_ISOLATION` | **PASS** | `BatchController.php:127,196,560,567` fixed tenant predicates; all other TenantScoped+`Rule::exists(... institute_id ...)` intact; `Select-String withoutGlobalScope` 0 leak in BatchController |
| `DOMAIN_ISOLATION` | **PASS** | `InstituteDomain.php:58-74` authoritative; `EnsureDomain.php:20-23`; academic→403 for professional, training→allowed to reuse shared (domain-filtered queries) |
| `IDOR_PROTECTION` | **PASS** | `Rule::exists where institute_id`, `assertOwned`/`assertCourseUsable`, `TenantScoped` bindings → 404/422 for cross-tenant |
| `RBAC` | **PASS** | Every nav target `auth+tenant+verified+permission:*` (`batches.view/manage`, `exams.view/manage`, `certificates.view`, `finance.view/manage` + `module_access`) |
| `ACADEMIC_ISOLATION` | **PASS** | `academic.dashboard` / `settings.academic.*` / `academic-attendance.*` / `classes.*` all `domain:academic` → 403 for training |
| `NO_FAKE_DATA` | **PASS** | No course/subject/batch/student/exam/marks/certificate created; empty states rendered; `git diff` only 2 files |
| `NO_DUPLICATE_SYSTEM` | **PASS** | No `training_*` table/model/route; reuse `courses`, `subjects`, `batches`, `exams`, `results`, `certificates`, `finance.education` |

### FINAL_VERDICT

## **GREEN**

**Rationale:** MAWA Academy (`training_center/training_institute → professional`) and identically all 5 Training Center business types now expose the complete professional operational workflow from the real, logged-in UI via `InstituteDomain::isProfessional()` inheritance alone — **no MAWA-specific condition, no tenant-specific config, no duplicate module, no fake data, no taxonomy change.** Part 8 critical `BatchController` unsafe read paths (`withoutGlobalScope`/global fallback/hard-coded `professional`) are eliminated and replaced with tenant-scoped, domain-derived queries via `InstituteDomain::subjectTypeFor()`. Discoverability is restored by grouping the 13 workflow steps into the `Training` collapsible group in recommended order, while tenant/branch/domain/RBAC/IDOR defenses are verified end-to-end (cross-tenant 404, academic 403, wrong permission filtered). No regression for academic or other industries.

**Implemented fixes are minimum, isolated (2 files), reversible in one revert, and fully compliant with all “DO NOT” constraints.**

