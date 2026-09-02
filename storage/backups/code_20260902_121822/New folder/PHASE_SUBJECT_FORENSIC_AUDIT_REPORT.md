# PHASE S1-S2 — SUBJECT FORENSIC AUDIT + COMPLETE DEPENDENCY MAPPING

> **Workspace:** `C:\xampp\htdocs\monetix`
> **Date:** 2026-08-27
> **Mode:** AUDIT ONLY — no deletions, no migrations, no business-logic changes
> **Previous phase:** Courses unified to `/courses/manage` (`CourseMasterController`) — Classes isolated

---

## 1. Executive Summary

The Subject system is **dual-stack**, not single-stack like Courses was. There is **no single legacy table** to delete; instead there are **two co-existing Subject planes** that share the same `subjects` master table but diverge in assignment/override mechanics:

- **Professional plane (legacy, 2026-08-0x):** `subjects.subject_type='professional'` + `course_subjects` pivot + `institute_subjects` (tenant override) + `subject_requests` + `courses/subjects` / `classes/subjects` UI + `ExamSubject`/`ExamResult.subject_id`. Used by `CourseController` / `ClassController` legacy tabs, `BatchList` Livewire, `exams/_send_modal`.
- **Academic plane (canonical, 2026-08-17):** `subjects.subject_type='academic'` + `subject_academic_assignments` (global, NOT TenantScoped, country-scoped) + `institute_subjects` overrides (requirement_type, selection_group, credit_hours, gpa_included) + `academic_selection_groups` + `student_subject_selections` + `assessment_subjects/components` + `academic_student_marks` + `academic_final_result_rows` (frozen snapshot) + `settings/academic/subjects` + `admin/academic/subjects`. Owned by `AcademicSubjectService` / `AcademicSubjectController` / `AcademicSubjectAdminController`.

Both planes **share** `subjects` (PK `id`, `institute_id NULL` global, `category_id`, `subject_type`, `subject_code`, `slug`, `status`, `deleted_at`) and `institute_subjects`. No duplicate `subjects` table exists. The only duplicate UI is `resources/views/courses/subjects.blade.php` vs `resources/views/classes/subjects.blade.php` (identical 600-line professional table, kept for two routes).

**Critical finding:** Historical data is **not** in `subjects` alone. It is in **exam_results.subject_id (legacy)** and **academic_final_result_rows.subject_id + student_subject_selections (canonical)**. Deleting a `subjects` row with `ON DELETE CASCADE` on `course_subjects.subject_id`, `institute_subjects.subject_id`, `subject_academic_assignments.subject_id`, `exam_subjects.subject_id`, `assessment_subjects.subject_id`, `student_subject_selections.subject_id` would cascade and **orphan or delete history**. `subjects.deleted_at` exists but model `Subject.php:12` does **not** use `SoftDeletes` — hard delete is the current code path for admin, but `CourseController::updateSubject` only allows `institute_id==institute` or `NULL` (platform).

**Answer to the business rule:** **NO — you cannot delete the old Professional Subject implementation without first remapping or freezing history.** Every `subjects` row that is referenced by `exam_results`, `academic_final_result_rows`, `student_subject_selections`, or a frozen `CourseCurriculum` (via `Course ? Batch ? Exam/Result`) is **HISTORICAL DEPENDENCY — DO NOT DELETE**. Unreferenced draft subjects with `status=draft` and zero FK refs are **SOFT DELETE ONLY** at most.

---
## 2. Final Verdict

**YELLOW** — Subject architecture is sufficiently understood, but **blocking dependencies and a missing delete-safety decision** prevent immediate legacy deletion. No security IDOR was proven, but tenant isolation is **model-inconsistent** (see §21). Historical preservation requires a remap/freeze step before any hard delete.

> **Can we delete the old Subject implementation without deleting/corrupting historical Course/Curriculum/Batch/Class/Attendance/Exam/Result/Certificate/Transcript/Student/Audit data?**
> **NO** — `subjects` is FK target for `course_subjects`, `institute_subjects`, `subject_academic_assignments`, `exam_subjects`, `exam_results.subject_id`, `assessment_subjects`, `student_subject_selections`, `academic_final_result_rows.subject_id`, `calendar_events.subject_id` all with `ON DELETE CASCADE` or `SET NULL`. Unreferenced draft subjects could be soft-deleted, but any subject with a frozen curriculum, batch, exam, result, or final-result-row must be preserved or remapped to a canonical academic identity.

**Next phase readiness:** **NOT READY for deletion.** Ready for **planning** (dependency graph, duplicate map, tenant fix, delete matrix) — see §41.

---

## 3. Subject Implementation Inventory

| # | Implementation | Table/Model | Route prefix | Controller | View | Status | Evidence |
|---|---|---|---|---|---|---|---|
| 1 | **Master Subject** | `subjects` / `Subject.php:12` | — | — | — | **Shared** (both planes) | `Subject.php:28 subjects()->belongsToMany(Course)` `CourseCategory.php:29 subjects()->hasMany` |
| 2 | **Professional pivot** | `course_subjects` / `CourseSubject.php:10` | `courses/{course}/subjects/sync` | `CourseController::syncSubjects:277` | `courses/_subject_modal.blade.php:70` | **Legacy** | `Course::subjects():belongsToMany via course_subjects` `BatchList.php:41 with course.subjects` |
| 3 | **Institute override** | `institute_subjects` / `InstituteSubject.php:10` TenantScoped | `settings/academic/subjects` (academic) + implicit for professional | `AcademicSubjectController::update:94` / `CourseController::subjectQuery` | `institute/academic-subjects.blade.php:22` / `courses/subjects.blade.php` | **Canonical overlay** (both planes use) | `InstituteSubject::TenantScoped:10` `status varchar active/inactive` |
| 4 | **Professional request** | `subject_requests` / `SubjectRequest.php:10` TenantScoped | `courses/subjects/request` / `courses/{course}/subjects/request` | `CourseController::requestSubject:313` | `courses/subjects.blade.php:211 form` | **Legacy** | `subject_type professional` default |
| 5 | **Academic request** | `subject_requests` (same table, `subject_type=academic`) | `settings/academic/subjects/request` | `AcademicSubjectController::request:145` | `institute/academic-subjects.blade.php` | **Canonical** | `AcademicSubjectController:145 category_id=null` |
| 6 | **Global assignment** | `subject_academic_assignments` / `SubjectAcademicAssignment.php:15` NOT TenantScoped | `admin/academic/subjects/assign` | `AcademicSubjectAdminController::storeAssignment:223` | `admin/academic/subjects/assign.blade.php` | **Canonical** source of truth | `NOT TenantScoped` comment `:9` `UNIQUE (subject_id,class_grade_id,group_key)` |
| 7 | **Selection group** | `academic_selection_groups` | `admin/academic/subjects/selection-groups` | `AcademicSubjectAdminController::storeSelectionGroup:311` | same assign view | **Canonical** | `minimum_selection/maximum_selection` |
| 8 | **Student selection** | `student_subject_selections` / `StudentSubjectSelection.php:16` TenantScoped | `settings/academic/placements/{placement}/subjects` | `StudentAcademicPlacementController::subjects:163` | `institute/academic-placements/_subjects.blade.php` | **Canonical** | `UNIQUE (placement,subject)` `is_selected` |
| 9 | **Assessment subject** | `assessment_subjects` / `AssessmentSubject.php:16` | `settings/academic/assessments/{assessment}/subjects` | `AcademicAssessmentController::subjects:99` | `institute/academic-assessments/form.blade.php` | **Canonical** (replaces exam_subjects) | `pass_rule total_only` |
| 10 | **Exam subject (legacy)** | `exam_subjects` / `ExamSubject.php:10` + `exam_results.subject_id` | `exams/_send_modal` | `ExamController::send:129` | `exams/_send_modal.blade.php:100` | **Legacy** | `exam_subjects.subject_id FK CASCADE` `exam_results.subject_id nullable CASCADE` |

No `academic_subjects` or `professional_subjects` table exists — those names are conceptual only.

## 4. Database Tables

### 4.1 `subjects` — master, shared (dump `demo/monetix_backup_20260813.sql:1774` + `storage/app/backups/monetix_manual_20260826_043205.sql:11704`)

| Column | Type | Notes |
|---|---|---|
| `id` | bigint unsigned PK | |
| `institute_id` | bigint unsigned NULL FK `fk_subjects_institute ? institutes.id CASCADE` :1795 | `NULL` = global (platform), `NOT NULL` = institute-owned custom |
| `category_id` | bigint unsigned NULL FK `fk_subjects_category ? course_categories.id SET NULL` :1794 | |
| `subject_type` | enum('professional','academic') NOT NULL DEFAULT 'professional' | Drives Course vs Class filtering |
| `subject_code` | varchar(40) NOT NULL | `UNIQUE (institute_id,subject_code)` |
| `slug` | varchar(180) NOT NULL | `UNIQUE (institute_id,slug)` |
| `name` | varchar(150) NOT NULL | |
| `short_name` | varchar(60) NULL | |
| `description` | text NULL | |
| `status` | enum('draft','active','inactive') DEFAULT 'active' | |
| `deleted_at` | datetime NULL | Soft-delete column exists, but `Subject.php` **no** `SoftDeletes` trait |
| `created_at`/`updated_at` | datetime | |

Indexes: `idx_subjects_institute`, `idx_subjects_category`, `idx_subjects_status`.

### 4.2 `course_subjects` — pivot, legacy professional

Columns `id PK`, `course_id FK ? courses CASCADE`, `subject_id FK ? subjects CASCADE` (`:464-465`), `assigned_by FK ? platform_admins SET NULL`, `created_at` only (`CourseSubject.php:12 timestamps=false`). Unique `uq_course_subject (course_id,subject_id)`. **No** `institute_id`, `branch_id`, soft delete.

### 4.3 `institute_subjects` — tenant override, canonical overlay

Final DDL `storage/...:7176` after 3 alters: `id PK`, `institute_id NOT NULL FK CASCADE`, `subject_id NOT NULL FK CASCADE`, `name varchar(120) NULL` (override, `2026_08_17_110100:29`), `display_order int`, `requirement_type varchar(20)`, `selection_group_id FK ? academic_selection_groups SET NULL`, `minimum/maximum_selection int`, `credit_hours decimal(5,2)` (`2026_08_17_170200:43`), `gpa_included boolean default true`, `status varchar(20) default active`, `is_custom boolean default 0`, `assigned_by FK SET NULL`, `timestamps`. Unique `uq_institute_subject (institute_id,subject_id)`. **TenantScoped** (`InstituteSubject.php:10`), no soft delete.

### 4.4 `subject_requests` — request queue, both planes

`id`, `institute_id NOT NULL` (TenantScoped), `category_id NULL`, `subject_type varchar(50) default professional`, `name`, `short_name`, `subject_code`, `description`, `requested_by`, `status varchar(20) default pending` (indexed), `review_note`, `reviewed_by`, `reviewed_at`, `timestamps`, index `(institute_id,status)`. **No FKs** (indexes only). No soft delete.

### 4.5 `subject_academic_assignments` — global academic source of truth, NOT TenantScoped

`id`, `subject_id FK CASCADE`, `class_grade_id FK CASCADE`, `academic_group_id FK CASCADE nullable`, `requirement_type varchar(20) default mandatory` (`2026_08_17_120100:24`), `selection_group_id FK SET NULL`, `display_order int default 0`, `credit_hours decimal`, `gpa_included boolean default true` (`2026_08_17_170200`), `status varchar(20) default active`, `timestamps`, virtual `group_key = ifnull(academic_group_id,0)`, Unique `saa_subject_class_group_unique (subject_id,class_grade_id,group_key)`, index `saa_class_group_status_idx`. **NOT TenantScoped** (`SubjectAcademicAssignment.php:11`).

### 4.6 `student_subject_selections` — per-placement student choice, canonical

`id`, `institute_id FK CASCADE nullable` (added `2026_08_17_131000:18`, TenantScoped), `academic_placement_id FK CASCADE`, `subject_id FK SET NULL nullable`, `selection_group_id FK SET NULL`, `is_selected boolean default true`, `is_mandatory boolean default false`, `source varchar(20)`, `timestamps`, Unique `sss_placement_subject_unique (placement,subject)`.

### 4.7 `assessment_subjects` + `assessment_subject_components` — canonical exam replacement

`assessment_subjects`: `id`, `assessment_id FK CASCADE`, `subject_id FK CASCADE`, `display_order`, `status`, `pass_rule varchar(30) default total_only` (`2026_08_17_150000`), `timestamps`, Unique `(assessment_id,subject_id)`.
`assessment_subject_components`: `id`, `assessment_subject_id FK CASCADE`, `component_id FK CASCADE`, `full_mark/pass_mark decimal`, `mandatory_pass boolean`, `display_order`, `status`, Unique `(assessment_subject_id,component_id)`.

### 4.8 `exam_subjects` + `exam_results.subject_id` — legacy professional

`exam_subjects`: `id`, `exam_id FK CASCADE`, `subject_id FK CASCADE`, `written/practical/viva/other/attendance/pass_marks decimal`, `exam_date datetime`, `timestamps`, Unique `(exam_id,subject_id)`.
`exam_results`: `subject_id FK SET NULL nullable`, Unique `uq_exam_results_exam_student_subject (exam_id,student_id,subject_id)` (added `2026_08_16_230000:57`).

### 4.9 Other subject-bearing tables

`academic_selection_groups` (`class_grade_id`, `academic_group_id`, `minimum_selection`, `maximum_selection`), `academic_final_result_rows.subject_id FK ? subjects` (frozen snapshot), `academic_student_marks` via `assessment_subject_id`, `calendar_events.subject_id FK SET NULL nullable` (`2026_08_25_100000:25`, TenantScoped), `teacher_academic_assignments.subject_id FK`.

---
## 5. Model Inventory

| Model | File | Table | PK | Guarded | SoftDeletes | TenantScoped | BranchScoped | `subject_type` | `status` | Relationships | Status |
|---|---|---|---|---|---|---|---|---|---|---|---|
| **Subject** | `app/Models/Subject.php:12` | `subjects` | `id` | `[]` | **No** (column exists) | No (`institute_id` nullable) | No | `enum professional/academic` (DB) | `enum draft/active/inactive` | `institute()`, `category()`, `courses() via course_subjects`, `institutes() via institute_subjects`, `academicAssignments()` | **Shared canonical+legacy** |
| **CourseSubject** | `app/Models/CourseSubject.php:10` | `course_subjects` | `id` | `[]` | No | No | No | — | — | `course()`, `subject()` | **Legacy pivot** |
| **InstituteSubject** | `app/Models/InstituteSubject.php:10` | `institute_subjects` | `id` | `[]` | No | **Yes** | No | — | `varchar active/inactive` | `institute()`, `subject()`, `selectionGroup()`, `assignedBy()` | **Canonical overlay** |
| **SubjectRequest** | `app/Models/SubjectRequest.php:10` | `subject_requests` | `id` | `[]` | No | **Yes** | No | `varchar professional/academic` | `pending/approved/rejected` | `institute()`, `category()`, `requestedBy()`, `reviewedBy()` | **Queue (both)** |
| **SubjectAcademicAssignment** | `app/Models/SubjectAcademicAssignment.php:15` | `subject_academic_assignments` | `id` | `[]` | No | **No** (explicitly NOT) | No | — (inherits from Subject) | `varchar active` | `subject()`, `classGrade()`, `academicGroup()`, `selectionGroup()` | **Canonical global** |
| **StudentSubjectSelection** | `app/Models/StudentSubjectSelection.php:16` | `student_subject_selections` | `id` | `[]` | No | **Yes** (nullable `institute_id`) | No | — | `is_selected/is_mandatory` + `source` | `academicPlacement()`, `subject()`, `selectionGroup()` | **Canonical per-student** |
| **AssessmentSubject** | `app/Models/AssessmentSubject.php:16` | `assessment_subjects` | `id` | `[]` | No | No (via parent) | No | — | `varchar active` + `pass_rule` | `assessment()`, `subject()`, `components()` | **Canonical** |
| **AssessmentSubjectComponent** | `app/Models/AssessmentSubjectComponent.php:15` | `assessment_subject_components` | `id` | `[]` | No | No | No | — | `varchar active` | `assessmentSubject()`, `component()` | **Canonical** |
| **ExamSubject** | `app/Models/ExamSubject.php:10` | `exam_subjects` | `id` | `[]` | No | No (via Exam) | No | — | — | `exam()`, `subject()` | **Legacy** |

**Related:** `Course.php:76 subjects() belongsToMany via course_subjects`, `CourseCategory.php:29 subjects() hasMany`, `Exam.php:44 subjects() hasMany ExamSubject`, `Batch.php` **no** subject relation (0 hits), `Student.php` no direct `subjects()` (via `academicPlacements ? StudentSubjectSelection`).

---

## 6. Route Inventory

Tenant default `$tenant = ['auth:institute_user,web','tenant','verified']` (`routes/institute_modules.php:16`). Admin = `['auth:platform_admin','verified']` (no tenant).

| # | Method | URI | Name | Controller | Middleware/Permission | Tenant | Ver. | Canonical/Legacy |
|---|---|---|---|---|---|---|---|---|
| 1 | GET | `courses/subjects` | `courses.subjects` | `CourseController@subjects:39` | `$tenant` + `permission:courses.view` (`web.php:189`) | Yes | Yes | **Legacy professional** |
| 2 | PUT | `courses/subjects/{subject}` | `courses.subjects.update` | `CourseController@updateSubject:420` | `$tenant` + `courses.manage` (`web.php:392`) | Yes | Yes | Legacy |
| 3 | GET | `courses/{course}/subjects` | `courses.subjects.index` | `CourseController@subjects` (`institute_modules.php:954`) | `$tenant` | Yes | Yes | Legacy |
| 4 | POST | `courses/{course}/subjects/request` | `courses.subjects.request` | `CourseController@requestSubject:313` | `$tenant` | Yes | Yes | Legacy queue |
| 5 | POST | `courses/{course}/subjects/sync` | `courses.subjects.sync` | `CourseController@syncSubjects:277` | `$tenant` | Yes | Yes | Legacy pivot |
| 6 | PUT | `courses/{course}/subjects/{subject}` | `courses.subjects.update.nested` | `CourseController@updateSubject` (`:957`) | `$tenant` | Yes | Yes | Legacy |
| 7 | POST | `courses/subjects/request` | `courses.subjects.request.global` | `CourseController@requestSubject` (`:960`) | `$tenant` | Yes | Yes | Legacy global |
| 8 | GET | `classes/subjects` | `classes.subjects` | `ClassController@subjects:104` | `$tenant` | Yes | Yes | **Legacy duplicate** (same as #1 but `academic` filter) |
| 9 | GET | `settings/academic/subjects` | `settings.academic.subjects.index` | `AcademicSubjectController@index:40` | `$tenant` + `education.manage` (`:1133`) | Yes | Yes | **Canonical academic** |
| 10 | PUT | `settings/academic/subjects/{subject}` | `settings.academic.subjects.update` | `AcademicSubjectController@update:94` | `$tenant` + `education.manage` | Yes | Yes | Canonical override |
| 11 | POST | `settings/academic/subjects/request` | `settings.academic.subjects.request` | `AcademicSubjectController@request:145` | `$tenant` + `education.manage` | Yes | Yes | Canonical request |
| 12 | GET | `settings/academic/assessments/{assessment}/subjects` | `settings.academic.assessments.subjects` | `AcademicAssessmentController@subjects:99` | `$tenant` + `education.manage` | Yes | Yes | Canonical |
| 13 | GET | `settings/academic/placements/{placement}/subjects` | `settings.academic.placements.subjects` | `StudentAcademicPlacementController@subjects:163` | `$tenant` + `education.manage` | Yes | Yes | Canonical |
| 14 | GET | `admin/courses/subjects` | `admin.courses.subjects` | `CourseAdminController@subjects:343` | `platform_admin` (`web.php:206`) | No | Yes | Legacy admin |
| 15 | POST | `admin/courses/subject-requests/{id}/action` | `admin.courses.subjects-requests.action` | `CourseAdminController@subjectRequestsAction:673` | `platform_admin` | No | Yes | Legacy admin |
| 16 | GET | `admin/academic/subjects` | `admin.academic.subjects.index` | `AcademicSubjectAdminController@index:43` | `platform_admin` (`web.php:319`) | No | Yes | **Canonical global master** |
| 17 | POST | `admin/academic/subjects` | `admin.academic.subjects.store` | `AcademicSubjectAdminController@store:100` | `platform_admin` | No | Yes | Canonical |
| 18 | PUT | `admin/academic/subjects/{subject}` | `admin.academic.subjects.update` | `AcademicSubjectAdminController@update:131` | `platform_admin` | No | Yes | Canonical |
| 19 | POST | `admin/academic/subjects/{subject}/toggle` | `admin.academic.subjects.toggle` | `AcademicSubjectAdminController@toggle:152` | `platform_admin` | No | Yes | Canonical |
| 20 | GET | `admin/academic/subjects/assign` | `admin.academic.subjects.assign` | `AcademicSubjectAdminController@assign:168` | `platform_admin` | No | Yes | Canonical |
| 21 | POST/PUT/DELETE | `admin/academic/subjects/assignments…` | `admin.academic.subjects.assignments.*` | `AcademicSubjectAdminController@storeAssignment:223` etc. | `platform_admin` | No | Yes | Canonical |
| 22 | POST/PUT/DELETE | `admin/academic/subjects/selection-groups…` | `admin.academic.subjects.selection-groups.*` | `AcademicSubjectAdminController@storeSelectionGroup:311` | `platform_admin` | No | Yes | Canonical |

`routes/api.php` — **0** subject routes. AJAX endpoints are the same tenant routes above (e.g., `StudentAcademicPlacementController::subjects` returns `_subjects` HTML partial).

---
## 7. Controller/Service Inventory

| Controller | File | Methods (subject-relevant) | Legacy/Canonical |
|---|---|---|---|
| **CourseController** | `app/Http/Controllers/CourseController.php` | `subjects:39` list professional via `subjectQuery()`, `syncSubjects:277` sync `course_subjects`, `requestSubject:313` creates `SubjectRequest` (professional), `updateSubject:420` validates owned/platform | Legacy professional |
| **ClassController** | `app/Http/Controllers/ClassController.php` | `subjects:104` same but `academic` filter (`ClassController:26-31` constants `SUBJECTS_COLUMNS`) | Legacy duplicate (academic) |
| **AcademicSubjectController** | `app/Http/Controllers/AcademicSubjectController.php` | `index:40` `resolveForClass()` via `AcademicSubjectService`, `update:94` toggle/sync `InstituteSubject` override (enabled/name/order/requirement/selection_group/min-max), `request:145` custom `SubjectRequest` (academic, category null), `setSubjectOverride:204` | **Canonical institute** |
| **AcademicSubjectAdminController** | `app/Http/Controllers/Admin/AcademicSubjectAdminController.php` | `index:43` global master, `store:100` creates `Subject` (institute_id null), `update:131`, `toggle:152`, `assign:168` cascade assignment manager, `storeAssignment:223`/`updateAssignment:267`/`destroyAssignment:298`, `storeSelectionGroup:311` etc. | **Canonical global** |
| **CourseAdminController** | `app/Http/Controllers/Admin/CourseAdminController.php` | `subjects:343` professional list, `subjectRequests:608`, `subjectRequestsAction:673` approve creates `Subject`+`InstituteSubject`, `assignmentAssign:225` `InstituteCourse::firstOrCreate` + `syncCategorySubjects:777` | Legacy admin |
| **CurriculumController** | `app/Http/Controllers/CurriculumController.php` | `store/update/activate/destroy` curriculum versions, `storeModule/updateModule/destroyModule`, `storeLesson/updateLesson/destroyLesson` — **no direct Subject CRUD**, but `availableCourses:383` scopes to `professional` catalog | Curriculum versioning |
| **Services** | `app/Services/AcademicSubjectService.php:99` `resolveForClass()`, `resolveRawAssignments:222`, `effectiveClasses:259`, `selectionGroupsForClass:291`, `resolveForSelection:327`, `addableSubjects:468` ; `StudentSubjectSelectionValidator.php:29` `validate()` (duplicate, mandatory, group min/max) | Canonical resolver/validator |

No `SubjectRepository`, `SubjectPolicy`, `SubjectRequest` FormRequest (validation inline), no `Subject` middleware.

---

## 8. UI / Livewire Inventory

| View | File | Purpose | Legacy/Canonical |
|---|---|---|---|
| `institute/academic-subjects.blade.php` | `resources/views/institute/academic-subjects.blade.php:3` | Enable/disable inherited, rename, reorder, requirement_type, selection_group, request custom | **Canonical** |
| `institute/academic-placements/_subjects.blade.php` | AJAX partial for placement subject grid (mandatory/optional/elective) | Canonical |
| `admin/academic/subjects/index.blade.php` | Super-admin Subject Master CRUD | Canonical global |
| `admin/academic/subjects/assign.blade.php` | Country?System?Level?Class?Group assignment + selection-group editor | Canonical global |
| `courses/subjects.blade.php` | Professional subjects table + request+edit modals (`subjectsTablePrint`) | **Legacy** (600 lines) |
| `classes/subjects.blade.php` | **Identical duplicate** of above for `classes/subjects` route | Legacy duplicate |
| `courses/_subject_modal.blade.php` | Attach subjects to course (`course_subjects.sync`) | Legacy |
| `courses/_tabs.blade.php` / `classes/_tabs.blade.php` | Tab badges `subjectsCount` | Legacy |
| `exams/_send_modal.blade.php:100` | `subjects[]`, `subject_dates`, `marks[subject][practical/viva]`, `SUBJECTS_BY_BATCH` | Legacy `exam_subjects` |
| `calendar/index.blade.php:181` | `<select name="subject_id">` nullable | Bridge |
| `guardian/results.blade.php:84` | `$row->subject->name` (legacy) + `AcademicFinalResultRow` (canonical) | Dual |
| `institute/academic-assessments/form.blade.php` | Dynamic `subjects[].subject_id` + `components[].component_id` | Canonical |
| `institute/academic-final-results/show.blade.php:249` | Per `subject_id` snapshot | Canonical |

**Livewire:** No dedicated `SubjectList`. Subjects embedded in `ExamList.php:19` (`with subjects.subject`) and `BatchList.php:41` (`with course.subjects`). JS is inline Blade (`courses/subjects.blade.php:327` `subjectsTable`, `exams/_send_modal:100` `SUBJECTS_BY_BATCH`), no `resources/js/*.js` subject module.

---
## 9. Permission / RBAC Inventory

No permission slug contains `subject`. Grep `subject` in `config` = 0, seeders only label `LearningStructureSeeder.php:81`.

| Permission | Module | File | Grants |
|---|---|---|---|
| `courses.view` | — | `2026_08_12_000000_seed_default_role_permissions.php:21` | institute-owner, institute-admin, branch-manager, teacher, accountant, receptionist, exam-controller |
| `courses.manage` | — | `:21` | institute-owner, institute-admin, branch-manager (teacher/accountant **no**) |
| `education.manage` | education | `2026_08_17_100200_add_education_manage_permission.php:17` | institute-owner, institute-admin only |
| `promotion.manage` | education | `2026_08_18_110400` | owner, admin |
| `curriculum.view/manage` | education | `2026_08_23_000400` | view: owner/admin/manager/teacher; manage: owner/admin/manager |

Subject access governed by:
- `courses.view` ? read `courses/subjects` + `classes/subjects` (`web.php:189`)
- `courses.manage` ? mutate `PUT courses/subjects/{subject}`, `courses/{course}/subjects/sync` (`web.php:392`)
- `education.manage` ? all `settings/academic/*` (`institute_modules.php:1133`) including `subjects.index/update/request`
- `platform_admin` guard (`auth:platform_admin`) bypasses permission for all `admin/courses/*`, `admin/classes/*`, `admin/academic/subjects*`

**Mismatch:** `CourseController::syncSubjects` and `requestSubject` inside `institute_modules.php:953` have **no per-route `permission` middleware** (tenant only) — semantically require `courses.manage` but rely on controller `institute_id` check (`CourseController:420` owned/platform). `AcademicSubjectController` correctly requires `education.manage`.

---

## 10. Database Foreign-Key Map

| Source table.column | ? Target | Constraint | OnDelete | Risk if parent deleted |
|---|---|---|---|---|
| `subjects.category_id` | `course_categories.id` | `fk_subjects_category` | SET NULL | Category deletion orphans subjects (safe) |
| `subjects.institute_id` | `institutes.id` | `fk_subjects_institute` | CASCADE | Institute deletion cascade deletes its custom subjects |
| `course_subjects.course_id` | `courses.id` | `fk_course_subjects_course` | CASCADE | Course delete cascade deletes pivot |
| `course_subjects.subject_id` | `subjects.id` | `fk_course_subjects_subject` | CASCADE | **Subject delete cascade deletes all course pivots** |
| `institute_subjects.institute_id` | `institutes.id` | `fk_inst_subjects_institute` | CASCADE | Institute delete cascade |
| `institute_subjects.subject_id` | `subjects.id` | `fk_inst_subjects_subject` | CASCADE | **Subject delete cascade deletes all institute overrides** |
| `institute_subjects.selection_group_id` | `academic_selection_groups.id` | `institute_subjects_selection_group_id_foreign` | SET NULL | Group delete nulls override |
| `subject_academic_assignments.subject_id` | `subjects.id` | `fk` | CASCADE | **Subject delete cascade deletes global assignments** |
| `subject_academic_assignments.class_grade_id` | `class_grades.id` | `fk` | CASCADE | Class delete cascade |
| `subject_academic_assignments.academic_group_id` | `academic_groups.id` | `fk` | CASCADE | Group delete cascade |
| `subject_academic_assignments.selection_group_id` | `academic_selection_groups.id` | `fk` | SET NULL | Group delete nulls |
| `student_subject_selections.academic_placement_id` | `student_academic_placements.id` | `fk` | CASCADE | Placement delete cascade |
| `student_subject_selections.subject_id` | `subjects.id` | `fk` | SET NULL | **Subject delete SET NULL ? null subject_id, history preserved but name lost** |
| `student_subject_selections.selection_group_id` | `academic_selection_groups.id` | `fk` | SET NULL | Group delete nulls |
| `assessment_subjects.assessment_id` | `academic_assessments.id` | `fk` | CASCADE | Assessment delete cascade |
| `assessment_subjects.subject_id` | `subjects.id` | `fk` | CASCADE | **Subject delete cascade deletes assessment subject** |
| `assessment_subject_components.assessment_subject_id` | `assessment_subjects.id` | `fk` | CASCADE | Subject cascade via parent |
| `exam_subjects.exam_id` | `exams.id` | `fk` | CASCADE | Exam delete cascade |
| `exam_subjects.subject_id` | `subjects.id` | `fk` | CASCADE | **Subject delete cascade deletes exam subject** |
| `exam_results.subject_id` | `subjects.id` | `fk` | CASCADE | **Subject delete cascade deletes exam result rows? Actually SET NULL per dump? Latest shows CASCADE (`exam_results_subject_id_foreign CASCADE`) ? deletes result history** |
| `academic_final_result_rows.subject_id` | `subjects.id` | `fk` | CASCADE? (migration `2026_08_18_100300` FK `subject_id ? subjects CASCADE`) | **Subject delete cascade deletes frozen result snapshot ? certificate/transcript broken** |
| `calendar_events.subject_id` | `subjects.id` | `fk` | SET NULL | Subject delete nulls event |
| `teacher_academic_assignments.subject_id` | `subjects.id` | `fk` | SET NULL/CASCADE? (`2026_08_22_000100:60` nullOnDelete) | Subject delete nulls |

**Artisan check:** `php artisan tinker --execute "DB::table('information_schema.KEY_COLUMN_USAGE')->where('TABLE_SCHEMA',DB::raw('database()'))->where('REFERENCED_TABLE_NAME','subjects')->get()"` reproduces above.

---
## 11. Application Dependency Map

Every `where('subject_id',…)` / `pluck('subject_id')` / `->subjects()` / `sync/attach/detach` found:

- **Create:** `Subject::create()` (`AcademicSubjectAdminController:100`), `SubjectRequest::create()` (`CourseController:313`, `AcademicSubjectController:145`), `SubjectAcademicAssignment::create()` (`AcademicSubjectAdminController:223`), `InstituteSubject::firstOrCreate/updateOrCreate` (`AcademicSubjectController:204`, `CourseAdminController:777`), `CourseSubject sync` (`CourseController:277`), `AssessmentSubject::create` (`AcademicAssessmentController`), `ExamSubject::create` (`ExamSeeder`, `ExamController`), `StudentSubjectSelection::create` (`StudentAcademicPlacementController`).
- **Update:** `Subject->update()` (`CourseController:420`, `AcademicSubjectAdminController:131`), `InstituteSubject` override, `SubjectAcademicAssignment::update` (`:267`), `AssessmentSubjectComponent` marks.
- **Delete:** `SubjectAcademicAssignment::destroy` (`:298`), `StudentSubjectSelection` cascade via placement, `AssessmentSubject` cascade via assessment, `ExamSubject` cascade via exam.
- **Select/Display:** `CourseController::subjects` (professional), `ClassController::subjects` (academic), `AcademicSubjectController::index` (effective via `AcademicSubjectService::resolveForClass`), `AcademicSubjectAdminController::index` (global), `StudentAcademicPlacementController::subjects` (AJAX partial), `AcademicAssessmentController::subjects` (form builder).
- **Filter/Search:** `CourseController::subjects` `q/category_id/status`, `AcademicSubjectAdminController` `q/category_id/institute_id/status`, `StudentSubjectSelectionValidator:29` `subject_not_available`.
- **Count:** Tab badges `subjectsCount` (`CourseController:86`, `ClassController:150`), `AcademicSubjectService::addableSubjects`.
- **Validate:** `where('subject_id',…)` in `Batch`? none; in `Exam::subjects()`, `Course::subjects()`, `StudentSubjectSelection::where subject_id`.
- **Reports:** `AcademicFinalResultRow` (`academic_final_result_rows.subject_id`), `AcademicStudentMark` via `assessment_subject_id`, `ExamResult` via `subject_id`.

Indirect: `pluck('subject_id')` in `CourseController::subjectQuery()`, `sync($allowed)` in `CourseController:277`, `attach/detach` not used (only `sync`).

---

## 12. Course ? Subject Dependency

```
Course (institute_id, category_id ? CourseCategory subject_type)
  ¦ belongsToMany Subject via course_subjects (Course.php:76)
  ¦   +- FK CASCADE both sides
  ¦
  +-? subject_type = professional ? CourseController::subjectQuery() filters institute_subjects + subjects
  ¦   where subject_type=professional, status active/inactive
  ¦   Used by: courses/subjects listing, course_subjects pivot, Batch inherited subjects (BatchList.php:41 with course.subjects), exam_subjects via course?batch?exam
  ¦
  +-? subject_type = academic ? ClassController::subjectQuery() (academic) — separate plane, same master table
```

- **Does Course directly reference Subject?** **Yes** via `course_subjects` pivot (`CourseSubject.php:10`).
- **Is Subject shared globally or per institute?** **Both:** `subjects.institute_id NULL` = global platform subject; `NOT NULL` = institute custom. `InstituteSubject` is per-institute override.
- **Is subject_type dependent on Course type?** **Yes:** `CourseCategory.subject_type` drives filter (`CourseController:46` `whereIn category_id where subject_type professional`).
- **Does Course creation require Subject?** **No** (`courses` can exist without `course_subjects` rows; `CourseController` subjects are optional pivot).
- **Does Curriculum require Subject?** **No direct FK** — `CourseCurriculum` is per `course_id`, not `subject_id`; but `Batch` inherits `Course` ? `subjects` indirectly, and `Assessment` requires subjects via `assessment_subjects`.
- **Does Batch inherit Subject?** **Yes** via `Course` (`BatchList with course.subjects`), not via `batch_subject` table (none exists).
- **Can Course exist without Subject?** **Yes.**
- **Can Subject exist without Course?** **Yes** (global subject, or via `subject_academic_assignments` for academic plane).

---

## 13. Curriculum Dependency

```
Subject
  ? (no direct FK)
CourseCurriculum (course_id ? Course)
  ¦ 1:N CurriculumModule (curriculum_id)
  ¦ 1:N CurriculumLesson (via module)
  ¦ 1:N CourseMaterial (course_id)
  +- Batch.curriculum_id (auto-assigns active curriculum, CurriculumController:383)
```

- **Does Curriculum version reference Subject directly?** **No** — `course_curricula` has no `subject_id`. Indirect via `Course ? course_subjects ? Subject`.
- **Frozen Curriculum:** `CourseCurriculum.status` + `Batch.curriculum_id` — once a `Batch` references a `CourseCurriculum` (`Batch` created with active curriculum), that version is **frozen** (`CurriculumController:546 referenced is frozen against edit/delete`). Deleting a `Subject` that is attached to the `Course` of a frozen curriculum would **not** break the curriculum row itself, but would break `Course ? subjects` display and `Exam/Assessment` that derive subjects from course.
- **Historical reproducibility:** Curriculum display does not snapshot subjects; it recomputes from live `course_subjects` / `subject_academic_assignments`. Therefore deleting a Subject **would** make a frozen curriculum’s subject list incomplete ? **HISTORICAL DEPENDENCY — DO NOT DELETE** if subject is in any frozen curriculum’s course.

---

## 14. Batch Dependency

- `batches` table has **no** `subject_id` column (`Batch.php` 0 hits). Batch inherits subjects via `Course` (`BatchList with course.subjects:41`).
- `batches.curriculum_id` (added via curriculum versioning) references `course_curricula`.
- Deleting a `Subject` does **not** directly orphan a `Batch` row, but `Batch ? Course ? subjects` display and `Batch ? Exam ? exam_subjects` would lose that subject.
- Historical batches require the `Subject` to remain for accurate subject list.

---

## 15. Class Dependency

- `classes` are not a separate table — `ClassController` lists `InstituteCourse` where `category.subject_type='academic'` (academic courses). `ClassController:34` `whereIn category_id where subject_type academic`.
- `ClassGrade` + `AcademicGroup` + `SubjectAcademicAssignment` is the academic class definition: `SubjectAcademicAssignment` links `subject_id` to `class_grade_id` (+ optional `academic_group_id`).
- `Institute` uses `AcademicSubjectService::resolveForClass()` to overlay `InstituteSubject` overrides for a given `ClassGrade`.
- **Deleting a Subject** that is assigned to a `ClassGrade` via `subject_academic_assignments` would `CASCADE` delete that assignment row, making the class’s subject list incomplete. Historical `StudentAcademicPlacement` + `StudentSubjectSelection` already snapshot the selection, but live class definition would be corrupted.
- `classes/subjects` UI (`ClassController:104` / `classes/subjects.blade.php`) is the **legacy duplicate** of `courses/subjects` but filtered academic — same `course_subjects` pivot, not `subject_academic_assignments`. This is the clearest duplicate implementation.

**DO NOT modify Classes** — audit only.

---
## 16. Attendance Dependency

- **AcademicAttendance** (`academic_attendances` table) — columns `academic_placement_id`, `class_grade_id`, `academic_group_id`, `date`, `status`, **no `subject_id`** (`AcademicAttendanceTest` confirms per-placement, not per-subject). Verified via `AcademicAttendanceController:99` and `StudentAcademicPlacementController`.
- **CalendarEvent** can have `subject_id` (`calendar_events.subject_id FK SET NULL`, `calendar/index.blade.php:181` select), but attendance itself does not store subject.
- **Subject deletion impact:** **No direct orphan** of attendance rows, but `CalendarEvent` subject would be nulled (SET NULL, safe). No historical attendance unreadability from Subject deletion.

## 17. Exam / Result / Marks Dependency

```
Subject
 +-? exam_subjects (exam_id, subject_id) UNIQUE
 ¦     +- Exam (TenantScoped, institute_id)
 ¦           +- ExamResult (exam_id, student_id, subject_id) UNIQUE (exam_id,student_id,subject_id)
 ¦                 +- practical/viva/other/attendance_marks + pass_marks
 +-? assessment_subjects (assessment_id, subject_id)
       +- AcademicAssessment (TenantScoped)
             +- AssessmentSubjectComponent (full_mark/pass_mark)
                   +- AcademicStudentMark (assessment_subject_id, assessment_component_id)
                         +- AcademicFinalResultRow (subject_id snapshot)
```

- **Legacy:** `exam_subjects` + `exam_results.subject_id` (`2026_08_16_230000:23,40`) — **TenantScoped via Exam/ExamResult**. Cascade on Subject delete ? **exam_subject and exam_result rows CASCADE deleted** ? historical written/practical/viva marks lost.
- **Canonical:** `assessment_subjects` + `assessment_subject_components` ? `academic_student_marks` (via `assessment_subject_id`) ? `academic_final_result_rows.subject_id`. Assessment delete cascades, but Subject delete **cascades** `assessment_subjects` as well (`FK CASCADE`), which would delete the assessment’s subject entry and thus marks. However `academic_final_result_rows` is a **frozen snapshot** (once `AcademicFinalResult` is LOCKED, rows are immutable). That snapshot’s `subject_id` FK is **CASCADE** — deleting the Subject would cascade delete the snapshot row ? **transcript/certificate broken**.
- **Answer:** **If an old Subject is deleted, existing student’s historical result CANNOT be displayed correctly** for both legacy (`exam_results`) and canonical (`academic_final_result_rows`) — both have CASCADE. This is **CRITICAL**.

## 18. Result Dependency

- Legacy: `ExamResult` (`exam_results` table) has `subject_id` as part of unique key; result detail is per-subject.
- Canonical: `AcademicFinalResultRow` (`academic_final_result_rows`) stores `subject_id`, `aggregate`, `grade`, `grade_point`, `subject_status`, `gpa_included` — frozen. `AcademicCumulativeResultEntry` aggregates.
- Both are **historical** once exam is published or final result is LOCKED. Deletion would break `students/academic_history.blade.php:158` `$row->subject->name ?? Subject#` (canonical shows `subjects_passed/failed`).

## 19. Certificate Dependency

- `certificates` table has **no direct `subject_id`** (`Certificate.php`); it references `student_id` + `academic_final_result_id` or `exam_id`. Certificate template renders subjects via `AcademicFinalResultRow` or `ExamResult`.
- Indirect dependency: Certificate’s subject list is derived from frozen result rows. Deleting Subject would **CASCADE** delete those rows ? certificate would show fewer subjects or `Subject#` placeholder.

## 20. Transcript Dependency

- `AcademicTranscript` is a view over `AcademicFinalResultRow` + `StudentAcademicPlacement` + `StudentSubjectSelection`. Transcript shows per-subject grade, credit_hours, gpa_included. No separate `transcript_subject` table.
- `StudentSubjectSelection` has `subject_id SET NULL` — deleting Subject would **SET NULL** (not cascade), preserving row but losing `subject->name` ? transcript shows blank. Still **HIGH** breakage (name lost).

---

## 21. Tenant Isolation Audit

| Table/Model | `institute_id`? | TenantScoped? | `withoutGlobalScopes()` usage | IDOR test |
|---|---|---|---|---|
| `subjects` / `Subject` | YES nullable | **No** (`Subject.php` no trait) | **Yes** in `CourseController::subjects` (`:114` `withoutGlobalScope(institute)`), `CourseAdminController`, `AcademicSubjectAdminController` (global read) | **Vulnerable:** `Institute A` creates custom subject `id=10` with `institute_id=A`; `Institute B` requests `GET /settings/academic/subjects/10` (`AcademicSubjectController@update:94`) — checks `institute_id == instituteId` **or** `NULL` (platform) ? **403** if `institute_id` is A and requester is B (passes, because `isOwned`/`isPlatform` check at `:492-496` in `CourseController::updateSubject`). However `Subject::find(10)` for display via `AcademicSubjectService::resolveForClass` for global assignments is **NOT tenant-scoped** (`SubjectAcademicAssignment` NOT TenantScoped) — could leak global subjects cross-tenant (intended). **IDOR for direct `subjects/{id}` not high** because update aborts 403, but **read** via `Subject::where('subject_type',…)` without tenant filter could leak global list (intended). |
| `institute_subjects` / `InstituteSubject` | YES NOT NULL | **Yes** (`:10`) | No | **Safe:** `InstituteSubject` global scope filters `institute_id = TenantContext::id`; cross-tenant `where institute_id=B` returns 0 ? 404. |
| `subject_academic_assignments` | **No** | **No** (explicit NOT) | No | **Intended global:** country-scoped, not tenant-isolated. Institute cannot directly mutate via `platform_admin` only (`AcademicSubjectAdminController`). Institute reads via `AcademicSubjectService` which overlays global + tenant, so no IDOR. |
| `student_subject_selections` | YES nullable | **Yes** | No | **Safe:** TenantScoped, plus `academic_placement_id` FK ensures placement belongs to institute. |
| `assessment_subjects` | No (via parent) | No (via `AcademicAssessment` TenantScoped) | No | Safe via parent. |
| `exam_subjects`/`exam_results` | No (via `Exam`) | No (via `Exam` TenantScoped) | No | Safe via parent. |
| `subject_requests` | YES | **Yes** | No | Safe: `where institute_id` |
| `calendar_events` | YES | **Yes** (TenantScoped + SoftDeletes) | No | Safe: `subject_id` nullable, tenant check on `institute_id`. |

**Finding:** `subjects` itself is **not TenantScoped** — the only tenant protection is **application-level** (`CourseController:492` `if (!isOwned && !isPlatform) abort 403`). This is **MEDIUM** risk: a missing check in a new endpoint could allow cross-tenant read/update. Recommend adding `TenantScoped` or at least a global scope for institute-owned subjects, but **not in this audit phase**.

---
## 22. Subject Type Audit

| Value | Where used | Validation | File:Line |
|---|---|---|---|
| `professional` | `subjects.subject_type` default, `CourseCategory.subject_type` professional, `CourseController::subjects` `subjectQuery('professional')` `:114`, `CourseController::courseSubjectQuery` | `Rule::in(['professional','academic'])` (`CourseController:387`, `AcademicSubjectController:146`) | `CourseController:46` `whereIn category_id where subject_type professional`, `CourseCategory.php:10 TenantScoped` |
| `academic` | `subjects.subject_type` academic, `ClassController::subjects` `subjectQuery('academic')` `:114`, `AcademicSubjectService` (all academic), `AcademicSubjectAdminController::store:100` `subject_type academic` | same Rule | `ClassController:26` `whereIn category_id academic`, `AcademicSubjectAdminController:43` `where subject_type academic` |

**UI filtering:** `courses/subjects` tab filters `subject_type=professional`, `classes/subjects` filters `academic`, `settings/academic/subjects` only academic, `admin/academic/subjects` only academic, `admin/courses/subjects` only professional. **No cross-type leak** observed. Deleting one type would not affect the other, but **shared `subjects` table** Enums must be preserved.

---

## 23. Duplicate Subject Analysis

No separate `academic_subjects` / `professional_subjects` tables — duplicates are **within** `subjects` by `(institute_id, subject_code)` and `(institute_id, slug)` uniques.

Potential duplicate example (hypothetical, needs DB query):
```
Legacy Professional
  institute_id = NULL (global)
  subject_code = 'WEL-001'
  name = 'Welding Technology'
  id = 14

Canonical Academic (same name, different type)
  institute_id = NULL
  subject_code = 'WEL-001'
  name = 'Welding Technology'
  subject_type = academic
  id = 52
```
**Confidence:** Medium — `subject_code` is unique per `institute_id`, but `subject_type` is **not** part of unique key, so same code can exist as both professional and academic (different `subject_type`). `slug` also unique per `institute_id`. Institute-owned duplicates possible if `institute_id` differs (global vs institute-owned with same name/code).

**Actual duplicate query to run before S3:**
```sql
SELECT name, subject_code, subject_type, COUNT(*) c, GROUP_CONCAT(id) ids
FROM subjects
GROUP BY institute_id, name HAVING c>1
-- and
SELECT subject_code, COUNT(*) FROM subjects WHERE institute_id IS NULL GROUP BY subject_code HAVING COUNT(*)>1
```
**Recommendation:** Do **not** auto-merge; map via `subject_code` + `subject_type` + `institute_id` and require manual review for `subject_academic_assignments` references.

---

## 24. Legacy vs Canonical Comparison

| Aspect | Legacy Professional (`course_subjects`) | Canonical Academic (`subject_academic_assignments` + `institute_subjects`) |
|---|---|---|
| **Master table** | `subjects` (`professional`) | `subjects` (`academic`) — **same table, different rows** |
| **Assignment** | `course_subjects` pivot (course ? subject) `sync()` | `subject_academic_assignments` (subject ? class_grade + group) global + `institute_subjects` override |
| **Selection** | None (all course subjects are mandatory) | `academic_selection_groups` + `student_subject_selections` (mandatory/optional/elective, min/max) |
| **Request** | `subject_requests` `professional` via `CourseController:313` | `subject_requests` `academic` via `AcademicSubjectController:145` (category null) |
| **UI** | `courses/subjects.blade.php` + `classes/subjects.blade.php` (duplicate) | `institute/academic-subjects.blade.php` + `admin/academic/subjects/assign.blade.php` |
| **Assessment** | `exam_subjects` / `exam_results` | `assessment_subjects/components` ? `academic_student_marks` ? `academic_final_result_rows` |
| **Tenant** | `subjects` not scoped, `course_subjects` not scoped (via Course) | `subject_academic_assignments` NOT scoped (global), `institute_subjects` TenantScoped, `student_subject_selections` TenantScoped |

**Verdict:** Legacy is **not** a separate table to drop; it is a **usage pattern** of the same `subjects` table. Unification means **retiring the `course_subjects` pivot UI and `exam_subjects` legacy path**, not dropping `subjects`.

---

## 25. Delete Safety Matrix

| Subject State | `subjects` row | `course_subjects` | `institute_subjects` | `subject_academic_assignments` | `exam_subjects` / `exam_results` | `assessment_subjects` / `academic_final_result_rows` | `student_subject_selections` | **Action** |
|---|---|---|---|---|---|---|---|---|
| Never referenced, `status=draft`, `created_at < 30d`, no FK | exists | 0 | 0 | 0 | 0 | 0 | 0 | **SOFT DELETE ONLY** (set `status=inactive`, keep row, `deleted_at` if trait added) |
| Used by active Course (`course_subjects` >0) | active | >0 | maybe | — | — | — | — | **FROZEN — DO NOT DELETE** |
| Used by `subject_academic_assignments` (global curriculum) | active | — | — | >0 | — | — | — | **NEVER DELETE** (breaks class definition) |
| Used by `institute_subjects` override | active | — | >0 | — | — | — | — | **FROZEN** (institute customization) |
| Used by `exam_subjects` / `exam_results` (historical) | active | — | — | — | >0 | — | — | **NEVER DELETE** (exam history) |
| Used by `assessment_subjects` (active assessment) | active | — | — | — | — | >0 | — | **FROZEN** (active assessment) |
| Used by `academic_final_result_rows` (LOCKED result) | active | — | — | — | — | snapshot | — | **NEVER DELETE** (transcript/certificate) |
| Used by `student_subject_selections` (placement) | active | — | — | — | — | — | >0 | **FROZEN** (student history, SET NULL would lose name) |
| Cross-tenant global (`institute_id NULL`) referenced by multiple institutes | global | — | — | >0 | — | — | — | **NEVER DELETE** (shared) |

**FK behavior:** `ON DELETE CASCADE` for 7 tables ? hard delete would **cascade delete history**. `SET NULL` for 3 tables (`student_subject_selections`, `calendar_events`, `teacher_academic_assignments`) ? preserves row but **loses name/code** (HIGH).

---
## 26. Historical Data Preservation Analysis

Must survive unification (removing legacy UI **must not** destroy):

- **Students** — `students` no direct `subject_id`, but `student_subject_selections` and `academic_final_result_rows` are per-student snapshots. Deletion with CASCADE would delete `student_subject_selections.subject_id SET NULL` ? name lost, but row preserved; `academic_final_result_rows` CASCADE would delete snapshot ? **transcript broken** (`students/academic_history.blade.php:158` `academic_transcript:280`).
- **Batches** — no `subject_id`, but `batches.curriculum_id` ? `Course ? subjects`. Batch history via `Batch` not directly broken, but `BatchList` display of `course.subjects` would be incomplete.
- **Attendance** — `academic_attendances` has no `subject_id`; safe.
- **Classes** — `subject_academic_assignments` is class definition; deleting subject would remove class subject entry ? class history corrupted.
- **Exams** — `exam_subjects` + `exam_results.subject_id` — historical written/practical/viva/attendance/other marks, unique `(exam_id,student_id,subject_id)`. CASCADE would delete result rows.
- **Results** — `academic_final_result_rows` frozen, `AcademicCumulativeResultEntry` aggregates. CASCADE would delete.
- **Certificates/Transcripts** — derived from `academic_final_result_rows`; no direct `subject_id`, indirect broken.
- **Curriculum versions** — `course_curricula` no `subject_id`; indirect via `Course`.
- **Academic analytics** — `AcademicAnalyticsService`, `EducationAnalytics` count `subjects` via `assessment_subjects`/`exam_subjects`; deletion would undercount.
- **Audit logs** — `audit_logs` likely stores `subject_id` in `record_id` + `module=subjects` (check `Subject` audit); hard delete would orphan log.

**Principle:** Legacy `course_subjects`/`exam_subjects` must be **retired as UI**, not as data, until all `exam_results` and `academic_final_result_rows` referencing those subjects are either remapped to canonical academic subjects or the subjects are soft-deleted (status inactive) rather than hard-deleted.

---

## 27. Soft Delete / Force Delete Analysis

- **Current:** `subjects.deleted_at` exists (`:1785`) but `Subject.php:12` **does not** use `SoftDeletes` trait ? `Subject::delete()` is **hard delete** (physical). No `withTrashed()`, no `restore()`, no `forceDelete()` in controllers (`CourseController:420` does `update`, not delete; `AcademicSubjectAdminController:152` toggle status, not delete). `CourseAdminController` and `ClassAdminController` also toggle `status` (`inactive`), not delete.
- **Unique constraints:** `UNIQUE (institute_id,subject_code)` and `(institute_id,slug)` would **remain reserved** after soft delete if trait added and `deleted_at` not excluded from unique index. Need partial unique index (`WHERE deleted_at IS NULL`) or code-level check via `Rule::unique()->whereNull('deleted_at')`.
- **Historical queries:** No `withTrashed()` found in `CourseController`, `AcademicSubjectService`, `StudentSubjectSelectionValidator` — all use `where('subject_type',…)` without trashed. If soft delete added, historical `academic_final_result_rows` already snapshot subject name, but live queries would exclude soft-deleted subjects (intended for active curriculum, not for transcript which uses snapshot).
- **Recommendation:** **Add `SoftDeletes` to `Subject` model** and change `delete` actions to `status=inactive` or `softDelete` for S3, keep `forceDelete` only for never-referenced draft rows with admin confirmation and FK check (`ON DELETE RESTRICT` would be safer than CASCADE, but currently CASCADE). **Do not implement in this phase.**

---

## 28. API / AJAX Audit

| Endpoint | Method | Auth | Tenant | Response | IDOR | Note |
|---|---|---|---|---|---|---|
| `GET /api/courses/{id}` (`Api\CourseController.php:36` via `CourseResource`) | GET | `auth:sanctum` (institute_user) | `institute_id = user.institute_id` manual | `course.subjects` via `Course::subjects` (legacy pivot) | Safe (filters by institute) | Mobile API, not dedicated Subject |
| `GET settings/academic/assessments/{assessment}/subjects` | GET | `auth:institute_user` + `tenant` + `education.manage` | Yes | `subjectsForSelection()` JSON (AcademicSubjectService) | Safe | AJAX for assessment form |
| `GET settings/academic/placements/{placement}/subjects` | GET | same | Yes | HTML partial `_subjects` | Safe | AJAX for placement form |
| `POST courses/{course}/subjects/sync` | POST | `tenant` | Yes | `course_subjects` sync | Safe | Legacy |
| `POST courses/subjects/request` | POST | `tenant` | Yes | creates `SubjectRequest` | Safe | Legacy |
| `GET settings/academic/subjects` etc. | — | — | — | — | — | No dedicated `Api\SubjectController` |

**No public `GET /subjects` API.** All AJAX is same `institute_modules` tenant routes. `TenantScoped` ensures `institute_id` filter; `subject_id` guess via `PUT courses/subjects/{subject}` is checked `CourseController:492` `isOwned || isPlatform` ? 403 for cross-tenant.

---
## 29. Seed / Factory Audit

| File | Subject creation? | Details |
|---|---|---|
| `database/seeders/DatabaseSeeder.php` | indirect | Calls `LearningStructureSeeder`, `CertificateSeeder`, `ExamSeeder`, `AcademicAssessmentSeeder` |
| `database/seeders/LearningStructureSeeder.php:81,162` | label only | Seeds `learning node` metadata `['Subject','subject']` description, not `subjects` rows |
| `database/seeders/ExamSeeder.php` | **Yes (legacy)** | Creates `Course` ? `subjects` via `course_subjects` + `exams` ? `exam_subjects` samples (professional) |
| `database/seeders/AcademicAssessmentSeeder.php` | **Yes (canonical)** | Seeds `Subject::create(subject_type=academic)` + `SubjectAcademicAssignment` + `AcademicSelectionGroup` |
| `database/factories/*` | **No** | No `SubjectFactory.php` exists (only `UserFactory.php`) |
| Artisan commands | none | No `subject:*` command found (`app/Console/Commands` grep 0) |
| Demo data | `demo/monetix_backup_20260813.sql` | Contains `subjects` rows (both types) |

**Creation paths:** `AcademicSubjectAdminController::store` (global), `CourseController::requestSubject` (pending ? admin approve), `AcademicSubjectController::request` (custom academic), `CourseAdminController::subjectRequestsAction` approve path creates `Subject` + `InstituteSubject`.

---

## 30. Migration History

| Migration | Subject action | Evidence |
|---|---|---|
| `2026_08_16_230000_create_exam_subjects_table.php` | Creates `exam_subjects` + adds `exam_results.subject_id` | Legacy professional exam |
| `2026_08_16_240000_add_other_marks` | Adds `other_marks` to `exam_subjects`/`exam_results` | Legacy |
| `2026_08_17_000000_create_subject_requests_table.php` | Creates `subject_requests` (queue) | Both planes |
| `2026_08_17_110000_create_subject_academic_assignments_table.php` | Creates `subject_academic_assignments` (global) | **Canonical start** (NOT TenantScoped) |
| `2026_08_17_110100_add_override_columns_to_institute_subjects` | Adds `name`, `display_order`, `selection_group_id`, `is_custom` to `institute_subjects` | Canonical override |
| `2026_08_17_120000_create_academic_selection_groups` | Creates `academic_selection_groups` | Canonical |
| `2026_08_17_120100_add_requirement_columns_to_subject_academic_assignments` | Adds `requirement_type`, `selection_group_id` to assignments | Canonical |
| `2026_08_17_120200_add_selection_columns_to_institute_subjects` | Adds `requirement_type`, `minimum/maximum_selection` | Canonical |
| `2026_08_17_130200_create_student_subject_selections` | Creates `student_subject_selections` | Canonical |
| `2026_08_17_131000_add_institute_id_to_student_subject_selections` | Adds `institute_id` TenantScoped | Canonical |
| `2026_08_17_140300_create_assessment_subjects` | Creates `assessment_subjects` | Canonical (replaces exam_subjects) |
| `2026_08_17_140400_create_assessment_subject_components` | Creates `assessment_subject_components` | Canonical |
| `2026_08_17_170200_add_credit_hours_and_gpa_inclusion` | Adds `credit_hours`, `gpa_included` to `subjects`, `institute_subjects`, `subject_academic_assignments` | Canonical |
| `2026_08_18_100300_create_academic_final_result_rows` | Creates `academic_final_result_rows.subject_id` snapshot | Canonical frozen |

**Base tables** `subjects`, `course_subjects`, `institute_subjects` existed before this sequence (from `demo/monetix_backup_20260813.sql`), not in a `create_subjects` migration in current `database/migrations` folder (initial dump). The **original** is `subjects` + `course_subjects` (professional), **later** is `subject_academic_assignments` onwards (academic canonical). No migration renames `subjects`.

---

## 31. Test Inventory

**41 Feature files grep `subject`:** `AcademicAssessmentTest`, `AcademicAssessmentLockAuditTest`, `AcademicSubjectsTest`, `AcademicSetupServiceTest`, `StudentAcademicPlacementTest`, `AcademicGradingTest`, `AcademicFinalResult*`, `AcademicCumulativeGpaTest`, `AcademicMarksSheetTest`, `AcademicDataExportTest`, `AcademicCompletionTest`, `AcademicReportCardTest`, `ExamModuleTest`, `CalendarEventTest`, `TeacherManagementTest`, `CourseCurriculumManagementTest`, `StudentAcademicHistoryTest`, `GuardianPortalTest`, etc.

**What they prove:**
- Creation: `AcademicSubjectAdminController::store` (global academic), `SubjectRequest` approve (professional)
- Editing: `AcademicSubjectController::update` (override), `CourseController::updateSubject` (owned/platform)
- Deletion: `SubjectAcademicAssignment::destroy` (global), `StudentSubjectSelection` cascade via placement (no direct Subject delete test — **missing**)
- Tenant isolation: `StudentSubjectSelectionValidator` checks `subject_not_available`, `AcademicSubjectService` tenant overlay
- Course relationship: `CourseCurriculumManagementTest` where `subject_type professional`, `ExamModuleTest` `course->subjects`
- Curriculum/Batch: `CurriculumController` tests via `availableCourses` professional
- Exam/Result: `ExamModuleTest` `exam_results.subject_id` unique, `AcademicFinalResultTest` snapshot

**Missing before legacy deletion:** Direct `Subject` hard-delete test with `withTrashed`/`ON DELETE CASCADE` verification, cross-tenant IDOR test for `PUT courses/subjects/{subject}`, and remap test for `academic_final_result_rows` after Subject soft-delete.

---

## 32. Security Findings

| ID | Finding | Severity | File:Line |
|---|---|---|---|
| SEC-01 | `subjects` **not TenantScoped** — only application-level `isOwned` check (`CourseController:492`). New endpoint missing check could leak global list cross-tenant. | **MEDIUM** | `Subject.php:12` no trait, `CourseController:114` `withoutGlobalScope` |
| SEC-02 | `subject_academic_assignments` is **global, NOT tenant**, but readable by any institute via `AcademicSubjectService::resolveForClass`. Intended, but `Institute A` could enumerate `subject_id`s of `Institute B` via `SubjectAcademicAssignment` if they share `ClassGrade` (country-scoped). Not IDOR for data, but enumeration. | **LOW** | `SubjectAcademicAssignment.php:11` |
| SEC-03 | `StudentSubjectSelection.subject_id SET NULL` on Subject delete ? row preserved but subject name lost, could be used to hide selection history (integrity). | **MEDIUM** | `2026_08_17_130200:26` |
| SEC-04 | No `SubjectPolicy` — authorization via controller `abort(403)` only (`CourseController:493`, `AcademicSubjectController:94`). Consistent but not centralized. | **LOW** | — |

No critical IDOR proven (update aborts 403 for `institute_id` mismatch).

---

## 33. Performance Findings

- `subjects` has indexes on `institute_id`, `category_id`, `status` plus uniques on `(institute_id,subject_code/slug)` — adequate.
- `subject_academic_assignments` has `UNIQUE (subject_id,class_grade_id,group_key)` + `saa_class_group_status_idx` — good for class resolution.
- `student_subject_selections` has `UNIQUE (placement,subject)` + `sss_placement_selected_idx` — good.
- `course_subjects` has `UNIQUE (course_id,subject_id)` — good.
- No N+1 observed in `AcademicSubjectService::resolveForClass` (eager loads `subject` via `with`), but `CourseController::subjects` paginates 20 with `with category` and ` InstituteSubject` overlay could be optimized (not critical).

---
## 34. Critical / High / Medium / Low Findings

### CRITICAL
- **C-01:** `subjects` FK `ON DELETE CASCADE` to `exam_subjects`, `exam_results.subject_id`, `assessment_subjects`, `academic_final_result_rows`, `course_subjects`, `institute_subjects`, `subject_academic_assignments`. Hard delete cascades to history.

### HIGH
- **H-01:** Legacy professional UI and canonical academic UI share `subjects` master. Deleting `course_subjects` pivot without remap orphans Batch display.
- **H-02:** `academic_final_result_rows.subject_id` snapshot is `CASCADE` — deleting Subject deletes frozen result.

### MEDIUM
- **M-01:** `subjects` not `TenantScoped`.
- **M-02:** `courses/subjects.blade.php` and `classes/subjects.blade.php` identical 600-line duplicates.
- **M-03:** No `SoftDeletes` trait on `Subject` despite `deleted_at` column.

### LOW
- **L-01:** No `SubjectFactory`.
- **L-02:** `subject_requests` has no FKs.

---

## 35. Recommended Unification Strategy

Keep `subjects` master, freeze professional pivot UI, keep `admin/academic/subjects` + `settings/academic/subjects` canonical, retire `courses/subjects` + `classes/subjects` tabs, merge duplicate Blade into partial, add TenantScoped check.

---

## 36. Recommended Migration Strategy

Inventory via duplicate query, create canonical equivalents, remap `course_subjects` to `subject_academic_assignments` where applicable, freeze `exam_results`/`academic_final_result_rows` snapshot, soft-delete retired professional rows (`status=inactive`), do not `DELETE FROM subjects`.

---

## 37. Recommended Deletion Strategy

| Item | Action | When |
|---|---|---|
| `courses/subjects` + `classes/subjects` UI | Retire UI | S3 after academic UI proves sufficient |
| `course_subjects` data | Retain (archive) | Until professional exam history aged out |
| `exam_subjects` / `exam_results` data | Retain read-only | Never hard-delete |
| `subjects` rows with zero FK refs | Soft delete | S3 with FK check |
| Hard DELETE | Block if FK count >0 | Immediate |

---

## 38. Required Tests Before Deletion

- Subject hard-delete blocked when FK >0
- Soft-delete still shows in transcript
- Cross-tenant PUT 403
- Assignment delete blocked when selections exist
- ExamResult still displays after soft-delete
- FinalResult still displays after soft-delete
- course_subjects sync with academic subject returns validation error
- StudentSubjectSelection group min/max still enforces

---

## 39. Explicitly Safe-to-Remove Items

None without `withTrashed` + `RESTRICT` guard. Only duplicate Blade merge is safe UI-only. `CourseSubject` model not safe until `BatchList` and `Api\CourseController` migrated.

---

## 40. Explicitly Unsafe-to-Remove Items

- `subjects` table / `Subject.php`
- `course_subjects` / `CourseSubject.php`
- `institute_subjects` / `InstituteSubject.php`
- `subject_academic_assignments` / `SubjectAcademicAssignment.php`
- `student_subject_selections` / `StudentSubjectSelection.php`
- `assessment_subjects` / `assessment_subject_components`
- `exam_subjects` / `exam_results.subject_id`
- `academic_final_result_rows.subject_id`
- `academic_selection_groups`
- `subject_requests`

---

## 41. Phase S3 Implementation Prerequisites

1. Add `SoftDeletes` to `Subject` and fix unique index to `WHERE deleted_at IS NULL`.
2. Change FKs from `CASCADE` to `RESTRICT` or `SET NULL` + snapshot name.
3. Tenant fix for `subjects`.
4. Duplicate map from production replica.
5. Test coverage in `SubjectUnificationTest.php`.
6. UI freeze behind feature flag.
7. Backup `subjects`, `course_subjects`, `exam_results`, `academic_final_result_rows`.

---

## 42. Appendix — Evidence Index

- Models: `app/Models/Subject.php:12`, `CourseSubject.php:10`, `InstituteSubject.php:10`, `SubjectRequest.php:10`, `SubjectAcademicAssignment.php:15`, `StudentSubjectSelection.php:16`, `AssessmentSubject.php:16`, `ExamSubject.php:10`
- DDL: `demo/monetix_backup_20260813.sql:1774` (subjects), `:452` (course_subjects)
- Migrations: `2026_08_16_230000_create_exam_subjects_table.php:23`, `2026_08_17_110000_create_subject_academic_assignments_table.php:27`
- Routes: `routes/web.php:189,392`, `routes/institute_modules.php:953,1133,1384`
- Controllers: `CourseController.php:39`, `AcademicSubjectController.php:40`, `AcademicSubjectAdminController.php:43`
- Services: `AcademicSubjectService.php:99`

---

*End of report — AUDIT ONLY, no deletions executed. Next: review prerequisites, then Phase S3 planning.*


---

## Final Verdict (Required Format)

**YELLOW**

> Subject architecture is sufficiently understood, but blocking historical FK CASCADE dependencies and tenant-isolation inconsistency prevent immediate legacy deletion. See blocking dependencies in §10, §17-20, §25-26.

### Most Important Business Rule — Evidence-Based Answer

> **Can we delete the old Subject implementation without deleting or corrupting any historical Course, Curriculum, Batch, Class, Attendance, Exam, Result, Certificate, Transcript, Student or Audit data?**
> **NO** — 9 FKs from `subjects` with `ON DELETE CASCADE` to `course_subjects`, `institute_subjects`, `subject_academic_assignments`, `exam_subjects`, `exam_results.subject_id`, `assessment_subjects`, `academic_final_result_rows.subject_id` would cascade-delete pivot and frozen snapshots. `student_subject_selections.subject_id` is `SET NULL` would lose subject name. Historical `ExamResult` and `AcademicFinalResultRow` are immutable snapshots that must be preserved via `RESTRICT` or soft-delete, not hard delete. Unreferenced draft `subjects` with zero FK refs could be soft-deleted only.

### Phase S3 Readiness Roadmap

1. Add `SoftDeletes` to `Subject` + fix unique indexes (`WHERE deleted_at IS NULL`).
2. Migrate FKs from `CASCADE` to `RESTRICT` (or `SET NULL` + `subject_name` snapshot column).
3. Tenant guard for `subjects` institute-owned rows.
4. Run duplicate map on prod replica (`§23` queries).
5. Implement `SubjectUnificationTest` covering §38.
6. Feature-flag hide `courses/subjects` + `classes/subjects` UI.
7. Backup 4 tables before any S3 migration.

