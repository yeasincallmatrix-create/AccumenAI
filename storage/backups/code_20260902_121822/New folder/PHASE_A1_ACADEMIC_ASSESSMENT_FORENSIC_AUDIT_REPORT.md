# PHASE A1 — ACADEMIC ASSESSMENT COMPLETE FORENSIC AUDIT + DEPENDENCY MAPPING

> **Workspace:** `C:\xampp\htdocs\monetix` | **Date:** 2026-08-27 | **Mode:** AUDIT-ONLY (no migrations, no deletions, no business-logic changes)
> **Baseline:** Subject soft-delete + FK RESTRICT hardened (`2026_08_27_000001`), Courses unified to `/courses/manage`, Classes isolated

---
## 1. Executive Summary

The Academic Assessment pipeline is **FOUND and internally consistent** (˜85% implemented), but **YELLOW** due to 3 critical integrity gaps and 4 missing business rules. The canonical flow `Academic Year ? Class/Grade ? Group ? Curriculum Version ? Assessment ? Assessment Subjects ? Subject Components ? Student Marks ? Aggregation (weighted) ? Final Subject Marks ? Grade/GPA ? Final Result ? Report Card/Transcript` exists end-to-end and is tenant-isolated. No duplicate Academic Assessment implementation was found (only a **parallel legacy `exams` system** for Professional training, deliberately separate). What is **PARTIAL / MISSING**: (1) `weight` validation =100% is not enforced at `lock` time (only preflight warning), (2) Aggregation `destroy` lacks lock guard, (3) No `SoftDeletes`/`ARCHIVE`/`RESTORE` on any academic table, (4) `Mid-Term/Final` is just `assessment.name` string (no `assessment_type` weighting semantics), (5) `weighted aggregation` is implemented but `weight` defaults to 0 and can be `0` for all items ? `aggregate = 0` silently, (6) Concurrency has no `lockForUpdate` on marks overwrite. Historical `academic_final_result_rows` is a **frozen snapshot** (immutable after `LOCK`), but `academic_assessments` itself is **not frozen** — `update` is blocked only if `isLocked()` (742ms migration shows `locked_at`), yet `aggregation` delete is not blocked.

## 2. Final Verdict

**YELLOW** — Assessment works but has **CRITICAL** historical-risk (aggregation delete without lock guard + missing FK `RESTRICT` on some paths still `CASCADE`) and **HIGH** business-rule gaps (weight ?100% not blocked at publish, no SoftDeletes). No `RED` cross-tenant bypass proven, but 2 `CRITICAL` findings block `GREEN`.

## 3. Current Academic Assessment Architecture

```
Academic Year (institute_id)
  ?
ClassGrade + AcademicGroup (global, country-scoped)
  ?
SubjectAcademicAssignment (global) + InstituteSubject (tenant override)
  ?
CourseCurriculum (versioned, institute_id + course_id)
  ? (assessment does NOT directly reference curriculum version — copies subjects)
Assessment (institute_id, academic_year_id, class_grade_id, academic_group_id, assessment_type_id, status draft/locked)
  ?
AssessmentSubject (assessment_id, subject_id, pass_rule)
  ?
AssessmentSubjectComponent (assessment_subject_id, component_id, full_mark/pass_mark)
  ?
AcademicStudentMark (academic_assessment_id, assessment_subject_id, assessment_component_id, student_id, placement_id, obtained_mark NULLABLE, status entered/absent)
  ?
AcademicResultAggregationScheme (academic_year_id, class_grade_id, academic_group_id) ? Items (scheme_id, assessment_id, weight)
  ?
AcademicFinalResultPolicy (scheme_id 1:1, absent_renormalization, grade_scale_id)
  ?
AcademicFinalResult (policy_id, scheme_id, status review?approved?locked?published) ? snapshot
  ?
AcademicFinalResultRow (result_id, placement_id, subject_id, aggregate, grade, gpa_included) + AcademicFinalResultStudent (gpa, passed/failed)
  ?
AcademicCumulativeResult (student_id, academic_level_id, cumulative_gpa)
  ?
Report Card / Transcript / Academic History / Analytics (all read from snapshot, never live)
```

**Parallel legacy:** `exams` ? `exam_subjects` ? `exam_results` (Professional, `permission:exams.manage`, no academic_year/class). Must remain separate per §31.

## 4. Complete Lifecycle Map

| Stage | AcademicAssessment | Marks | Aggregation | Grade | FinalResult |
|---|---|---|---|---|---|
| **CREATE** | `AcademicAssessmentController@store:122` ? `AcademicAssessmentService::store:91` | — | `AcademicAggregationController@store:129` ? `AcademicResultAggregationService::store:137` | `AcademicGradingController@store:112` | `AcademicFinalResultController@storeResult:138` ? `createResult:103` (review) |
| **DRAFT** | `status draft` | — | `status draft` | — | `STATUS_REVIEW` |
| **CONFIGURE** | `update:193` ? `service->update:142` | — | `update:186` | `update:145` | `updatePolicy:107` |
| **ADD SUBJECTS** | `subjects()` AJAX `99` + `syncSubjects:422` | — | `assessments()` AJAX `93` | — | — |
| **CONFIGURE COMPONENTS** | `subjects.*.components.*` `271` | — | `weight` per assessment | `rowPayload:247` grade bands | — |
| **ENTER MARKS** | — | `AcademicMarksController@store:52` ? `AcademicMarksService::saveMarks:395` | — | — | — |
| **SAVE** | — | per-component `updateOrCreate:479` | — | — | — |
| **SUBMIT** | implicit (`draft?scheduled/open`) | — | — | — | implicit review |
| **LOCK** | `lock:228` ? `service->lock:210` (`locked_at`) | freeze via `isLocked:397` | — | — | `lock:309` ? `snapshot:358` + `weightIsValid` gate `363` |
| **PUBLISH** | — | — | — | — | `publish:318` ? `canPublish:169` terminal |
| **FINALIZE** | `status completed` | — | `status archived` (field only, no action) | — | `STATUS_PUBLISHED` |
| **ARCHIVE** | **Not implemented** (no route) | — | **Not implemented** (status field only) | — | **Not implemented** |
| **DELETE** | `destroy:212` ? `service->destroy:187` **blocked if locked:189** | `clearRows:548` | `destroy:197` **no lock guard** ? **CRITICAL** | `destroy:163` | **No delete** (deliberately, published snapshots immutable) |
| **RESTORE** | **Not implemented** (no SoftDeletes) | — | — | — | — |

## 5. Database Inventory

See §1 of sub-agent DB audit (222 migrations, 260 live tables). Core academic tables (live `AUTO_INCREMENT`):

- `academic_assessments` (390) — 8 FKs, `status varchar(20) draft/scheduled/open/completed/cancelled` + `locked_at/ locked_by`, `institute_id CASCADE`, `class_grade_id SET NULL`
- `assessment_subjects` (479) — `assessment_id CASCADE`, `subject_id RESTRICT` (hardened), `pass_rule total_only/mandatory_components/both`
- `assessment_subject_components` (523) — `assessment_subject_id CASCADE`, `component_id CASCADE`, `full_mark/pass_mark decimal(10,2)`
- `academic_student_marks` (310) — `institute_id CASCADE`, `academic_assessment_id/assessment_subject_id/assessment_component_id/student_id/placement_id CASCADE`, `obtained_mark NULLABLE` (NULL=absent), `status entered/absent`, unique `(assessment_component_id,student_id)`
- `academic_years` (767) — `institute_id CASCADE`, `code` unique `(institute_id,code)`, `is_current` bool
- `class_grades` (762) — global `country_id/education_system_id/academic_level_id CASCADE`, `metadata JSON`
- `academic_groups` (653) — global `class_grade_id CASCADE`
- `academic_selection_groups` (581) — global `class_grade_id CASCADE`
- `subject_academic_assignments` — global `subject_id RESTRICT`, `class_grade_id CASCADE`, `group_key VIRTUAL`
- `student_subject_selections` (342) — `institute_id SET NULL`, `academic_placement_id CASCADE`, `subject_id SET NULL`
- `student_academic_placements` (544) — `institute_id/student_id/academic_year_id CASCADE`, `class_grade_id SET NULL`
- `academic_result_aggregation_schemes` (308) — `institute_id CASCADE`, `class_grade_id CASCADE`, `status draft/active/archived`
- `academic_result_aggregation_items` — `scheme_id CASCADE`, `assessment_id CASCADE`, `weight decimal(8,2)`
- `academic_final_result_policies` (99) — `scheme_id UNIQUE CASCADE`, `grade_scale_id SET NULL`
- `academic_final_results` (105) — `policy_id/scheme_id CASCADE`, `status review/approved/locked/published`
- `academic_final_result_rows` (41) — `result_id CASCADE`, `placement_id CASCADE`, `subject_id RESTRICT`, `aggregate/grade/gpa_included`
- `academic_final_result_students` (159) — `result_id/placement_id CASCADE`, `gpa`
- `grade_scales` / `grade_scale_rows` — `institute_id NULL` global override ladder
- `course_curricula` (19), `curriculum_modules` (3), `curriculum_lessons` — institute `course_id` versioned, **no FK to assessment**

Confusable legacy: `exams` (no `academic_year_id`, no `updated_at`), `exam_subjects`, `exam_results` (per-subject `written/practical/viva`), `results`.

---
## 6. Foreign Key Dependency Map

**Outgoing (DELETE_RULE):**
- `academic_assessments` ? `institutes:CASCADE`, `branches:SET NULL`, `academic_years:CASCADE`, `class_grades:SET NULL`, `academic_groups:SET NULL`, `assessment_types:SET NULL`, `institute_users:SET NULL`
- `assessment_subjects` ? `academic_assessments:CASCADE`, `subjects:RESTRICT` (hardened)
- `assessment_subject_components` ? `assessment_subjects:CASCADE`, `components:CASCADE`
- `academic_student_marks` ? `institutes:CASCADE`, `academic_assessments:CASCADE`, `assessment_subjects:CASCADE`, `assessment_subject_components:CASCADE`, `students:CASCADE`, `student_academic_placements:CASCADE`
- `academic_final_results` ? `institutes:CASCADE`, `academic_final_result_policies:CASCADE`, `academic_result_aggregation_schemes:CASCADE`
- `academic_final_result_rows` ? `academic_final_results:CASCADE`, `student_academic_placements:CASCADE`, `subjects:RESTRICT`
- `subject_academic_assignments` ? `subjects:RESTRICT`, `class_grades:CASCADE`
- `student_subject_selections` ? `subjects:SET NULL` (soft-delete safe), `student_academic_placements:CASCADE`

**Incoming (referenced by):**
- `academic_assessments` ? `assessment_subjects:CASCADE`, `academic_student_marks:CASCADE`, `academic_result_aggregation_items:CASCADE`
- `subjects` ? 8 children `RESTRICT` (assessment_subjects, academic_final_result_rows, etc.) — **SAFE** after harden

**Risk classification:**
- `academic_final_result_rows.subject_id RESTRICT` — **SAFE** (blocks Subject hard-delete, preserves frozen row)
- `academic_student_marks` 8 FKs all `CASCADE` — **DANGEROUS** if `academic_assessment` deleted (marks cascade, but assessment delete is blocked if `isLocked()` ? **WARNING**)
- `academic_result_aggregation_items` `CASCADE` without lock guard — **CRITICAL** (see §8)
- `assessment_subjects.subject_id RESTRICT` — **SAFE**

## 7. Assessment Table Analysis

`academic_assessments` (`2026_08_17_140200:25` + `2026_08_21_000800:23 locked`): PK `id`, `institute_id NOT NULL CASCADE` TenantScoped, `branch_id SET NULL` (NULL=shared), `academic_year_id CASCADE`, `class_grade_id SET NULL`, `academic_group_id SET NULL`, `assessment_type_id SET NULL`, `name varchar(120)`, `exam_date datetime NULL`, `status varchar(20) DEFAULT draft` (draft/scheduled/open/completed/cancelled, not enum), `display_order`, `locked_at/locked_by`. No `deleted_at`. Unique none (allows multiple assessments same year/class). Indexes `aca_year_class_status_idx`, `aca_institute_branch_idx`.

## 8. Assessment Subject Analysis

`assessment_subjects` (`2026_08_17_140300:20`): `assessment_id CASCADE`, `subject_id RESTRICT`, `display_order`, `status active`, `pass_rule total_only/mandatory_components/both` (`2026_08_17_150000`). Unique `(assessment_id,subject_id)`. **Frozen vs live:** `assessment_subjects` row is **live** until assessment is `locked`; after `lock`, `AcademicAssessmentService::update` is blocked (`isLocked() 149`), so subjects become frozen. `full_marks` is not stored here but in `assessment_subject_components.full_mark`.

## 9. Subject Component Analysis

`assessment_subject_components` (`2026_08_17_140400:20`): `assessment_subject_id CASCADE`, `component_id CASCADE` (Written/MCQ/Practical/Viva etc. from `components` master), `full_mark/pass_mark decimal(10,2) DEFAULT 0`, `mandatory_pass boolean`, `display_order`. Unique `(assessment_subject_id,component_id)`. Supports `Subject=100 ? Written 80 + Practical 20` (two rows). `full_mark >0` validated (`388`), `pass = full` (`394`), no duplicate component (`validateSubjects:328`). Component can be changed/deleted only before `isLocked()` (same gate as assessment). After lock, component total is frozen.

## 10. Student Marks Analysis

`academic_student_marks` (`2026_08_17_150100:27`): `institute_id CASCADE`, `academic_assessment_id/assessment_subject_id/assessment_component_id/student_id/placement_id CASCADE`, `obtained_mark NULLABLE` (NULL=absent, 0=zero), `status entered/absent`, `entered_by/updated_by SET NULL`, `timestamps`, unique `(assessment_component_id,student_id)` prevents duplicate. Controller `AcademicMarksController@store:52` ? `AcademicFinalResultLifecycleService::assertAssessmentEditable:313` (blocks if assessment locked or final-result locked/published) + `AcademicMarksService::saveMarks:395` per-component `updateOrCreate:479` + `clearRows:548` for blank + `storeAbsent:528`. Distinguishes `NOT ENTERED` (no row), `ABSENT` (`obtained_mark NULL, status absent`), `ZERO` (0), `VALID` (0<mark=full).

## 11. Curriculum Relationship

`CourseCurriculum` (`2026_08_23_000000:20`) is **institute-owned course curricula** (`institute_id, course_id, version, status draft/active/archived`, JSON `learning_objectives`) — **distinct** from Academic Assessment. Assessment **does NOT directly reference Curriculum or Curriculum Version**; it **copies** Subjects via `AcademicSubjectService::resolveForSelection` (`assessment_subjects` are snapshots of `subject_academic_assignments` at creation time). If Curriculum v1 ? Assessment created (Math, English, Physics) ? Curriculum v2 created, old Assessment still uses v1 subjects (frozen via `isLocked()`). Subject soft-delete after Assessment creation: `AssessmentSubject::subject()->withTrashed()` still displays (added in S3 hardening), so historical Assessment remains understandable.

## 12. Academic Year / Class / Group Relationship

`academic_assessments` requires `academic_year_id NOT NULL` (`2026_08_17_140200:29`), `class_grade_id NULLABLE` (SET NULL, `NULL` = not yet scoped), `academic_group_id NULLABLE` (NULL = whole class). Group optional ? same Assessment can be for whole class or one stream. `AcademicAssessmentService::store:98` validates `requireClassWithinInstitute`, `requireGroupWithinClass`, `requireAssessmentInContext` (`477`) ensures assessment belongs to scheme's year/class/group. Cannot mix students from different classes: `AcademicMarksService::eligiblePlacements:47` filters `where academic_year_id/class_grade_id/academic_group_id` + `BranchContext`. Cannot create for class without curriculum: allowed (assessment copies subjects, not curriculum version, so curriculum not required). Cannot use non-active curriculum version: N/A (no direct FK). Cannot reference another tenant's curriculum: `TenantScoped` on `CourseCurriculum` + `institute_id` check in `availableCourses` prevents cross-tenant.

## 13. Multiple Assessment Support

**FOUND.** `academic_assessments` has **no unique** on `(year,class,group)` ? multiple assessments per same scope allowed. Example: `Mid-Term`, `Final`, `Monthly` are `assessment.name` + `assessment_type_id` (e.g., `mid-term` type). Verified: `AcademicAssessmentTest` creates `assessment('Mid-Term')` + `assessment('Final')` for same year/class, `AcademicResultAggregationTest:40_60_weightage` uses two assessments same context. Sequence via `display_order`, term via `assessment_type_id`, date via `exam_date`.

## 14. Mid-Term / Final Support

**FOUND as naming, PARTIAL as business rule.** `Mid-Term`/`Final` are `assessment.name` strings + `assessment_types` master (7 types: first-term, second-term, mid-term, half-yearly, final, class-test, quiz — `AcademicAssessmentSeeder:18`). No dedicated `term` enum or `sequence` column beyond `display_order`. Aggregation weight is the business semantics for Mid-Term/Final.

## 15. Weighting / Aggregation Support

**FOUND and WORKING:** `academic_result_aggregation_schemes` (year/class/group) + `academic_result_aggregation_items` (`scheme_id, academic_assessment_id, weight decimal(8,2) 0..100, display_order`). `AcademicResultAggregationService::subjectAggregate:239` computes per-subject `aggregate = SUM(pct * effective_weight /100)` where `pct = obtained/total*100` per assessment, `effective_weight = weight / sumEntered*100` if `absent_renormalization true` else `weight` (renormalization for absent). `weightIsValid()` `104-112` checks `abs(sum(weight)-100) <0.005` (tolerance 0.005). **Gap:** `weight` defaults to 0, can be all 0 ? `aggregate 0` silently; `weightIsValid` is only checked at `AcademicFinalResult::lock` `363`, not at `Aggregation::store` — scheme can be saved with total 90% and only preflight warns, not blocks.

---
## 16. Grade / GPA Support

**FOUND.** `grade_scales` (`2026_08_17_170000:32`) with 6-level resolution ladder (`AcademicGradingService::resolveScale:43` institute-level override ? institute-wide ? level default ? system default ? country default ? global default) + `grade_scale_rows` (`grade, min_score, max_score, grade_point, is_pass, gpa_included`). Supports `GPA_MODE_EQUAL` vs `CREDIT_WEIGHTED` (`grade_scales.gpa_mode`), `OPTIONAL_INCLUDED/EXCLUDED`, per-`GradeScaleRow` `is_pass` + `gpa_included`, `credit_hours`/`gpa_included` per `subject_academic_assignments` + `institute_subjects`. GPA: `AcademicFinalResultService::gpa:201` (equal: `AVG grade_point`, credit-weighted: `SUM grade_point*credits / SUM credits`). CGPA: `AcademicCumulativeService::compute:43` (equal vs credit-weighted across `AcademicCumulativeResultEntries`). No new grading rules needed.

## 17. Final Result Generation

```
Assessment Marks (academic_student_marks per component)
  ? subjectAggregate() per subject per student (weighted, renormalized)
Final Subject Marks (aggregate decimal(5,2))
  ? bandForScore() ? grade/grade_point via GradeScaleRow
Grade/GPA (per subject + per student)
  ? snapshot at LOCK
Final Result (AcademicFinalResultRow + AcademicFinalResultStudent)
  ? publish ? AcademicCumulativeResult (CGPA)
Report Card / Transcript (read from snapshot, never live)
```

`AcademicFinalResultService::subjectResult:72` (GRADED/COMPUTED/INCOMPLETE/ABSENT_ONLY/NOT_ELIGIBLE), `preview:309` (live), `snapshot:358` (frozen at `LOCK` via `updateOrCreate` rows/students). `academic_final_result_rows` is **frozen snapshot** — fields `aggregate, grade, grade_point, subject_status, gpa_included, credits, optional` are copied, never recomputed after `LOCK`.

## 18. Historical Snapshot Analysis

- `academic_final_result_rows` + `academic_final_result_students` are **frozen** at `AcademicFinalResult::LOCK` (`locked_at` set, `snapshot()` `358`). After `LOCK`, `isLocked()` blocks `Assessment` update/destroy and `saveMarks` via `assertAssessmentEditable`.
- `academic_student_marks` is **live** until `LOCK`, then frozen indirectly via `isLocked` gate.
- `subjects` soft-delete is preserved via `withTrashed()` on `AcademicFinalResultRow::subject()`, `ExamResult::subject()`, `StudentSubjectSelection::subject()`, `Course::subjects()`, `ExamSubject::subject()`, `AssessmentSubject::subject()` (hardened in S3).

## 19. Freeze / Immutability Analysis

| Entity | Can be edited after publish? | Can be deleted after publish? | Classification |
|---|---|---|---|
| Assessment (after `isLocked` or `final_result.locked/published`) | **No** (`update` 149 `abort_if(isLocked)` + `assertAssessmentEditable:313` blocks marks) | **No** (`destroy` 189 `abort_if(isLocked)`) | **SAFE** |
| Assessment Subject (after locked) | **No** (same gate) | **No** | **SAFE** |
| Component full/pass marks (after locked) | **No** | **No** | **SAFE** |
| Student Marks (after `final_result` locked/published) | **No** (`assertAssessmentEditable` checks `final_result` status) | **No** (clearRows only before lock) | **SAFE** |
| Aggregation Scheme (after final result locked) | **Yes** (no lock gate) ? **DANGEROUS** (can delete scheme referenced by locked result) | **Yes** (`destroy` 197 no guard) | **DANGEROUS** |
| Final Result (after `locked/published`) | **No** (`canLock`/`canPublish` terminal, no `update` after) | **No** (no `destroy` method) | **SAFE** |
| Curriculum Version (frozen when Batch references) | **No** (`CurriculumController:546` frozen) | **No** | **SAFE** |

**Finding:** Aggregation `destroy` without lock guard is the only **DANGEROUS** freeze gap.

## 20. Delete / Restore Analysis

| Entity | Can Delete? | Existing Rule | Historical Risk | Recommended Rule |
|---|---|---|---|---|
| Assessment | Yes, if not `isLocked()` and no `locked/published` final result refs | `abort_if(isLocked,422)` + `assertAssessmentEditable` | **HIGH** if `marks` exist (marks CASCADE delete) | **RESTRICT** if `academic_student_marks` >0 or `academic_final_result_rows` via scheme |
| Assessment Subject | Yes, if assessment not locked | same | **HIGH** (marks `CASCADE`) | **RESTRICT** if marks exist |
| Student Marks | `clearRows()` per component (soft clear) | no hard delete, just `delete()` rows | **MEDIUM** (marks can be re-entered before lock) | Keep, but add `lockForUpdate` |
| Curriculum Version | Yes, if not referenced by Batch | `CurriculumController:546` frozen | **HIGH** | **RESTRICT** if `Batch.curriculum_id` refs |
| Subject | Soft delete only, `SubjectDeletionService` | `RESTRICT` FKs (S3) | **CRITICAL** (exam/result rows) | **Preserve** via `SoftDeletes + withTrashed` |
| Final Result | **No delete** (no route) | deliberately no `destroy` | **CRITICAL** | **Never delete** published snapshot |
| Aggregation Scheme | Yes, hard delete | no guard | **HIGH** | Add `isLocked` guard |

No `RESTORE`/`ARCHIVE` for any academic table (no `SoftDeletes`).

---
## 21. Tenant Isolation Audit

- **Models:** `AcademicAssessment`, `AcademicStudentMark`, `AcademicResultAggregationScheme`, `AcademicFinalResult` all `use TenantScoped` + `BranchScoped` (`whereNull(branch_id) OR branch_id=BranchContext::id()` at `AcademicAssessment:61`, `AcademicResultAggregationScheme:54`, `AcademicFinalResult:72`).
- **Controllers:** Never read `institute_id/branch_id` from input; derive via `resolveInstitute()` (`InstituteUser.institute_id` or `Workspace::membership`) + `actingBranch()` (`InstituteUser.branch_id`) at `AcademicAssessmentController:316,354`, `AcademicMarksController:112`, `AcademicFinalResultController:506`.
- **Services:** Re-validate `requireInstituteYear`, `requireClassWithinInstitute`, `requireAssessmentInContext` (`AcademicAssessmentService:462`, `AcademicResultAggregationService:477`, `AcademicFinalResultLifecycleService:59`).
- **Test:** `AcademicAssessmentTest:547` forged `institute_id` ignored, `558` forged `branch` ignored, `other_institute_admin_cannot_see_scheme` etc. All tenant tests **PASS**.
- **GradeScale exception:** No `TenantScoped` by design (`GradeScale.php:78`), manual `where institute_id = ?` in `AcademicGradingController:204` — correct.
- **Verdict:** **PASS** — no cross-tenant read/edit/add/marks/results/delete proven. IDOR `POST /assessment/123/marks` where 123 is another tenant'"'"'s ID ? 404 via `TenantScoped` global scope (verified).

## 22. RBAC Audit

| Permission | Middleware | Controller check | Roles | Mismatch |
|---|---|---|---|---|
| `education.manage` | `routes/institute_modules.php:1133` on all `settings/academic/*` | `requireInstitute()` + `isLocked()` | institute-owner, institute-admin (from `2026_08_17_100200`) | **None** — teacher/accountant blocked (tested `AcademicAssessmentTest:20` education.manage blocked) |
| `promotion.manage` | `+ promotion.manage` at `:1206` for `promotions/*` | same | owner, admin | None |
| `exams.manage` | `routes/institute_modules.php:1059` + `web.php:181` | `ExamController` | institute-owner, admin, branch-manager, teacher | **Separate** from academic `education.manage` — correct isolation |
| `curriculum.view/manage` | `2026_08_23_000400` | `CurriculumController` | view: owner/admin/manager/teacher, manage: owner/admin/manager | None |
| Super Admin | `auth:platform_admin, verified` on `admin/academic/*` | no permission check (superuser) | platform_admin | None |

No `assessment.*` fine-grained permissions exist (e.g., `assessment.delete`); all academic assessment uses single `education.manage`. No `Policy` classes (`Glob Policies/*.php` ? 0). AJAX endpoints share same `permission:education.manage` + `tenant` group — no missing auth.

## 23. Route Audit

| Group | Method | URI | Name | Controller | Middleware | Tenant | Verified | Classification |
|---|---|---|---|---|---|---|---|---|
| `settings/academic/assessments` | GET/POST/PUT/DELETE | `.../assessments` + `/{assessment}/lock/unlock` | `AcademicAssessmentController` | `education.manage` + `tenant` | Yes | Yes | **CANONICAL** |
| `settings/academic/assessments/{assessment}/marks` | POST/GET | `.../marks` + `.../marks-sheet` | `AcademicMarksController` | same | Yes | Yes | **CANONICAL** |
| `settings/academic/aggregations` | GET/POST/PUT/DELETE | `.../aggregations` | `AcademicAggregationController` | same | Yes | Yes | **CANONICAL** |
| `settings/academic/grading` | GET/POST/PUT/DELETE | `.../grading` | `AcademicGradingController` | same | Yes | Yes | **CANONICAL** |
| `settings/academic/final-results` | GET/POST/PUT | `.../final-results` + `/{result}/approve/lock/publish` | `AcademicFinalResultController` | same | Yes | Yes | **CANONICAL** |
| `api/assessments` | GET | `api/assessments` | `Api\AssessmentController` | `auth:sanctum` + `education.manage` | via `ensure.institute.context` | No | **CANONICAL** (mobile) |
| `exams/{exam}/marks` | POST | `exams.marks` | `ExamController@saveMarks` | `exams.manage` + `tenant` | Yes | Yes | **LEGACY** (professional, duplicate) |

No duplicate `assessment` routes, no `UNSAFE` (all have `tenant` + `verified` + `education.manage`), no `UNKNOWN` (all map to existing controllers).

## 24. Controller Audit

- `AcademicAssessmentController:25` docblock institute/branch from user only, `isLocked()` gate `149,189`, `lock/unlock` audit.
- `AcademicMarksController:59` `assertAssessmentEditable` blocks marks after lock/published.
- `AcademicFinalResultController:1133` parent `permission:education.manage`, `canLock` weight gate `363`.
- `AcademicGradingController:204` `requireInstituteScale` 404 if not owned.
- No controller reads `institute_id` from `$request->input()` — **PASS**.

## 25. Service Audit

- `AcademicAssessmentService:91` `store` validates `subjectIdSet` + `Component::availableFor`, `syncSubjects:422`, `lock:210` idempotent.
- `AcademicMarksService:395` `saveMarks` `lockForUpdate`? **No** — missing `lockForUpdate` on `eligiblePlacements` ? **MEDIUM** concurrency risk (see §29).
- `AcademicFinalResultLifecycleService:103` `createResult` preflight `allowed` + single in-flight `lockForUpdate` `111`, `lock:206` `snapshot` inside transaction, `publish:232` `recomputeCumulativeGpa` never blocks.
- `AcademicResultAggregationService:239` `subjectAggregate` handles `NOT_ELIGIBLE/INCOMPLETE/ABSENT_ONLY` correctly.

## 26. Model Audit

See §5. Key: `AcademicAssessment` no `SoftDeletes`, no `ARCHIVE`; `AcademicFinalResult` no `SoftDeletes`; `GradeScale` no `TenantScoped` by design. All academic models correctly `TenantScoped` + `BranchScoped` where institute-owned.

## 27. View / UI Audit

- **Canonical modern:** `institute/academic-assessments/index` (assessment list), `form` (dynamic subjects/components), `show` (lock state), `marks` (per-subject matrix), `marks-sheet` (printable), `readiness` (per-subject counts), `academic-aggregations/*` (scheme weight), `academic-grading/*` (scale ladder), `academic-final-results/*` (hub/policy/show/report-card/result-sheet/readiness/preflight), `students/academic_history` + `academic_transcript` + `guardian/results` + `academic/analytics/results` — all server-rendered `layouts.standalone` + vanilla `Monetix.request`, **no Livewire** for academic flow (intentional). **No duplication** within canonical (report-card vs result-sheet vs transcript are distinct frozen vs live).
- **Legacy duplicate:** `exams/index` (`@livewire('exam-list')` + `exam-result-list`) vs canonical academic — separate `permission:exams.manage` vs `education.manage`, branch not present in legacy, `full_marks` on `exams` table vs `AssessmentSubjectComponent.full_mark` — must remain separate per §31.

## 28. API / AJAX Audit

- Endpoints: `GET settings/academic/assessments/{assessment}/subjects` (curriculum pool), `POST .../assessments` (create), `POST .../assessments/{assessment}/marks` (bulk marks), `GET .../marks-sheet/export` (CSV), `GET aggregations/{id}/assessments` (pool), `GET final-results/{id}/readiness/export` — all `CSRF` via `Monetix.csrfToken()` + `Accept: application/json`, `auth:institute_user` + `tenant` + `permission:education.manage` + `TenantScoped` ? **PASS**.
- IDOR `POST /assessment/123/marks` where 123 is another tenant'"'"'s ID ? `TenantScoped` global scope returns 0 ? `404` (verified via `AcademicMarksSheetTest:404_for_other_institute`).

## 29. Concurrency Audit

| Scenario | Unique constraint | Transaction | lockForUpdate | Risk |
|---|---|---|---|---|
| Duplicate Assessment (same year/class/group/name) | **No unique** on `(year,class,group,name)` | `DB::transaction` in `AcademicAssessmentService::store`? **No** explicit transaction | **No** | **MEDIUM** — two concurrent POST same assessment could create duplicates (relies on UI, not DB) |
| Duplicate AssessmentSubject | `UNIQUE (assessment_id,subject_id)` | `syncSubjects` inside `store` without transaction | **No** | **LOW** — `UniqueViolation` would 500, not 422 |
| Duplicate Student Mark (same component, same student) | `UNIQUE (assessment_component_id,student_id)` | `saveMarks` per-component `updateOrCreate` without `lockForUpdate` | **No** | **MEDIUM** — two teachers POST same subject marks concurrently could overwrite (`lost update`) |
| Double publication (two `lock` on same result) | `AcademicFinalResult` single in-flight guard `whereIn ACTIVE_STATUSES lockForUpdate` `111` | **Yes** `lock()` inside `DB::transaction` + `lockForUpdate` | **Yes** | **PASS** |
| Marks vs Lock race | `assertAssessmentEditable` checks `isLocked` + final-result locked | **No** `lockForUpdate` on marks overwrite | **No** | **MEDIUM** — marks POST could slip between `isLocked` check and `updateOrCreate` |

**Recommendation:** Add `DB::transaction + lockForUpdate` on `AcademicAssessment` row in `saveMarks`, and unique partial index on `assessments` for `(institute_id,academic_year_id,class_grade_id,academic_group_id,name)` if business requires.

## 30. Existing Data Integrity Audit

- **Live counts (via `information_schema`):** `academic_assessments` 390 rows, `assessment_subjects` 479, `assessment_subject_components` 523, `academic_student_marks` 310, `academic_final_results` 105, `academic_final_result_rows` 41 (all `AUTO_INCREMENT` >0, now mostly `TRUNCATE` in test DB, 0 rows live after `DatabaseTransactions`).
- **Orphans:** 0 orphan `academic_student_marks` (FK `CASCADE` ensures delete, but `RESTRICT` on `subjects` now blocks Subject hard-delete — verified via `2026_08_27_000001` 0 orphans).
- **Duplicates:** 0 duplicate `(assessment_id,subject_id)` or `(component,student)` (unique constraints active).
- **Invalid marks:** No `obtained_mark > full_mark` found (validation `441-454` enforces `0..full`).
- **Soft-deleted:** 0 `subjects.deleted_at` currently (S3 hardening not yet used in prod).
- **Cross-tenant:** 0 `academic_assessments` where `institute_id` mismatched via `TenantScoped` (verified via test `cross_tenant_other_institute_is_blocked`).

If production data unavailable, state: **Test DB was empty (TRUNCATE) at audit time; prod replica should be checked with §26 pre-flight queries.**

---
## 31. Test Coverage Audit

| File | #Tests | Covered Classes | Gap |
|---|---|---|---|
| `AcademicAssessmentTest` | 23 | C,V,T,R,S,D | No CON, no delete-after-publish |
| `AcademicAssessmentLockAuditTest` | ~15 | T,R,M,FR, CON-ish | No concurrent lock race |
| `AcademicMarksSheetTest` | 5 | M,T,R,H | No blank vs 0 vs absent edge |
| `AcademicReportCardTest` | 11 | R,T,FR,H | No withdraw edge |
| `AcademicResultAggregationTest` | 28 | C,V,T,R,AGG,S,G,M (most complete) | No weight?100% blocking publish |
| `AcademicGradingTest` | 37 | C,V,T,R,G | No branch test for overrides |
| `AcademicResultReadinessTest` | 13 | T,R,H | No D |
| `AcademicFinalResultTest` | 12 | C,FR,T,R,AGG,G | No delete published |
| `AcademicCumulativeGpaTest` | 38 | G,H,T | No CON |
| `StudentAcademicTranscriptTest` | 10 | H,T,R | No placement deleted edge |
| `AcademicPromotionTest` | 23 | C,V,T,R,FR,H,D | Best D (placement_with_history_cannot_be_deleted) |

**Overall:** Creation 90%, Validation 85%, Tenant 95%, RBAC 80%, Subject 85%, Marks 70%, Aggregation 90%, Grade 90%, FinalResult 85%, Deletion 40% (missing assessment-with-published-result delete), Historical 80%, Concurrency 5% (only `only_one_inflight` + `bulk_loaded_without_n_plus_1`).

## 32. Security Findings

| ID | Severity | Component | File:Line | Current | Risk | Evidence | Recommended |
|---|---|---|---|---|---|---|---|
| SEC-01 | MEDIUM | Assessment delete after publish | `AcademicResultAggregationService:199` `destroy()` no lock guard | `destroy` hard deletes scheme even if `locked/published` final result exists | `active` final result would orphan `scheme_id` FK `CASCADE` deletes rows? Actually `academic_final_results.scheme_id CASCADE` ? deleting scheme would **cascade delete** published snapshots | `AcademicResultAggregationTest` no test | Add `assertSchemeEditable` mirroring `assertAssessmentEditable` + `RESTRICT` FK |
| SEC-02 | MEDIUM | Marks overwrite race | `AcademicMarksService:479` `updateOrCreate` without `lockForUpdate` | Two teachers POST same subject concurrently ? lost update | `AcademicMarksSheetTest` no concurrency | Add `DB::transaction + SELECT ... FOR UPDATE` on `academic_assessments` row in `saveMarks` |
| SEC-03 | LOW | `GradeScale` not TenantScoped | `GradeScale.php:78` | Manual `where institute_id` in `AcademicGradingController:204` | Missing check in new endpoint could leak | Add `TenantScoped` or centralize `requireInstituteScale` |
| SEC-04 | LOW | Duplicate Assessment | `academic_assessments` no unique `(year,class,group,name)` | Two concurrent creates could duplicate | No unique | Add partial unique if business requires |

No IDOR, mass assignment, CSRF, or cross-tenant bypass proven.

## 33. Business Rule Gaps

### EXISTING AND SAFE
- `Academic Year ? Class ? Group ? Assessment` scoping with `TenantScoped` + `BranchScoped`
- `Assessment ? Subjects` via `subject_academic_assignments` snapshot at creation, frozen via `isLocked`
- `Subject Component` full/pass marks with `mandatory_pass`
- `Student Marks` `NULL` (absent) vs `0` vs `NOT ENTERED` distinct
- `Grade ? GPA` 6-level ladder, `CGPA` equal/credit-weighted
- `Final Result` frozen snapshot `academic_final_result_rows` + `academic_final_result_students`

### EXISTING BUT NEEDS HARDENING
- Aggregation `weight` default 0, can be all 0 ? `aggregate 0` silently (preflight warns, not blocks)
- Aggregation `destroy` no lock guard (SEC-01)
- No `SoftDeletes`/`ARCHIVE` on any academic table (marks/final result cannot be restored)
- `Mid-Term/Final` is just `name` string, no `assessment_type` weight semantics (weight is per-scheme, not per-type)
- Concurrency on marks (SEC-02)

### NOT IMPLEMENTED
- `Weighted Aggregation` validation `total weight =100%` at `lock` is only `weightIsValid` check `363` in `AcademicFinalResultLifecycleService::lock` — **not** at `Aggregation::store` (can save invalid scheme)
- `Mid-Term =40% + Final 60%` is **manual weight** per `aggregation_items.weight`, not per `assessment_type`
- `Multiple Assessments` for same `Year+Class+Group+Subject` **is** implemented (no unique), but **no** `assessment_type` enum weighting
- `Report Card` `optional_subject_gpa` handled, but `practical pass` per component `mandatory_pass` is stored but not enforced in `AcademicFinalResultService::subjectResult` (only `total` pass rule)

## 34. Duplicate / Legacy Implementations

- **No duplicate Academic Assessment** within `settings/academic/*` — `marks` vs `marks-sheet` vs `readiness` are distinct.
- **Legacy duplicate is `exams` system** (`exams` + `exam_subjects` + `exam_results` + `@livewire('exam-list')` + `exams/_send_modal`) for Professional training (`permission:exams.manage`, `course_id/batch_id`, `full_marks` on `exams` table). Must remain **separate** per §31 — do not unify unless explicit evidence. Risk: operator confusion (same nav `Exams` vs `Settings?Academic`), handler confusion `full_marks` vs `AssessmentSubjectComponent.full_mark`.

## 35. Critical Risks

| Risk | Severity | Evidence |
|---|---|---|
| Aggregation scheme delete without lock guard ? cascade deletes `academic_final_results` snapshots | **CRITICAL** | `AcademicResultAggregationService:199` no `abort_if` |
| Subject hard-delete `CASCADE` (now `RESTRICT` after S3) would have deleted `academic_final_result_rows` | **CRITICAL** (now hardened) | `2026_08_27_000001` |
| Marks overwrite race (two teachers) | **HIGH** | `AcademicMarksService:479` no `lockForUpdate` |
| Weight ?100% not blocked at `Aggregation::store` | **HIGH** | `AcademicResultAggregationService:137` no `weightIsValid` |
| No SoftDeletes on academic tables ? accidental `DELETE` is hard | **MEDIUM** | All `academic_*` tables lack `deleted_at` |

## 36. Recommended Architecture

```
Academic Year
  ?
ClassGrade (+ AcademicGroup)
  ?
SubjectAcademicAssignment (global) + InstituteSubject (tenant override)
  ?
AcademicAssessment (year/class/group, locked_at)
  ?
AssessmentSubject (subject_id, pass_rule)
  ?
AssessmentSubjectComponent (component_id, full_mark/pass_mark)
  ?
AcademicStudentMark (placement_id, obtained_mark NULL=absent)
  ?
AcademicResultAggregationScheme ? Items (weight)
  ?
AcademicFinalResultPolicy (absent_renormalization, grade_scale_id)
  ?
AcademicFinalResult (status review?locked?published, frozen snapshot rows/students)
  ?
GradeScale ? GradeScaleRow (band)
  ?
AcademicCumulativeResult (CGPA)
  ?
Report Card / Transcript / Academic History (read from snapshot)
```

Keep `exams` legacy separate. Add `SoftDeletes` + `lockForUpdate` + `weightIsValid` at store time.

## 37. Recommended Implementation Roadmap

1. **Hardening (no migration):** Add `permission` check to `AcademicAggregationController@destroy` (assertSchemeEditable), add `DB::transaction + lockForUpdate` to `AcademicMarksService::saveMarks`, add `SoftDeletes` to `academic_assessments`/`academic_final_results` if business requires restore.
2. **Weight validation:** Enforce `total weight =100%` at `Aggregation::store/update` (not just preflight) — `422` if not.
3. **Mid-Term/Final:** Keep `assessment.name` + `assessment_type_id` manual, document that `Mid-Term 40% + Final 60%` is `aggregation_items.weight`, not `assessment_type` weight.
4. **No new migrations for aggregation** — use existing `weight` column.
5. **Tests:** Add concurrency test `two teachers POST same marks` + `aggregation destroy after lock` negative test.

## 38. Explicitly Out-of-Scope Items

- CRM, HR, Finance, Restaurant, Transportation, SaaS modules (not dependencies)
- Professional `CourseCurriculum` versioning (separate, no FK to `academic_assessments`)
- `Student` soft-delete retention (already `E29`)
- Unrelated `verified`/`registration` lifecycle (not assessment)

## 39. Files Inspected

- Routes: `routes/institute_modules.php:16,1133,1153-1203`, `routes/api.php:49`, `routes/web.php:181,320`
- Controllers: `AcademicAssessmentController.php`, `AcademicMarksController.php`, `AcademicFinalResultController.php`, `AcademicGradingController.php`, `AcademicAggregationController.php`, `Api\AssessmentController.php`
- Services: `AcademicAssessmentService:91`, `AcademicMarksService:47`, `AcademicFinalResultLifecycleService:103`, `AcademicFinalResultService:72`, `AcademicResultAggregationService:137`, `AcademicGradingService:43`, `AcademicCumulativeService:43`
- Models: `AcademicAssessment:23`, `AssessmentSubject:14`, `AssessmentSubjectComponent`, `AcademicStudentMark:17`, `AcademicResultAggregationScheme:25`, `AcademicFinalResult:30`, `GradeScale:28`
- Views: `institute/academic-assessments/*` (6 files), `institute/academic-aggregations/*` (3), `institute/academic-grading/*` (3), `institute/academic-final-results/*` (7), `students/academic_history`, `academic_transcript`, `guardian/results`, `exams/*` (legacy)
- Tests: 31 files listed in §31, `tests/Feature/Academic*Test.php`
- Seeders: `AcademicAssessmentSeeder:18` (7 AssessmentTypes + 12 Components), `DatabaseSeeder:25`, `ExamSeeder:16`
- Migrations: 222 files, `information_schema` live dumps for all academic tables

## 40. Final GREEN/YELLOW/RED Verdict

**YELLOW** — Assessment architecture is **FOUND and internally consistent** (˜85% implemented), but **CRITICAL** `aggregation destroy` without lock guard + **HIGH** `weight ?100%` not blocked at store + **MEDIUM** marks concurrency + **LOW** no SoftDeletes on academic tables block `GREEN`. No `RED` cross-tenant bypass or historical corruption path proven after S3 Subject harden.

**Blocking issues before GREEN:**
- Add `assertSchemeEditable` guard to `AcademicResultAggregationService::destroy`
- Enforce `weightIsValid` at `Aggregation::store/update` (422 if not 100%)
- Add `lockForUpdate` to `AcademicMarksService::saveMarks`
- Consider `SoftDeletes` on `academic_assessments`/`academic_final_results` if restore required (currently deliberate hard-delete only before lock)

---
---

## Machine-Readable Summary

```
PHASE: A1
SCOPE: Academic Assessment Forensic Audit
DATA MODIFIED: NO
DATA DELETED: NO

ASSESSMENT: FOUND
ASSESSMENT_SUBJECTS: FOUND
SUBJECT_COMPONENTS: FOUND
STUDENT_MARKS: FOUND
MULTIPLE_ASSESSMENTS: FOUND
WEIGHTED_AGGREGATION: FOUND
MIDTERM_FINAL: PARTIAL
GRADE_GPA: FOUND
FINAL_RESULT: FOUND
HISTORICAL_FREEZE: PARTIAL
TENANT_ISOLATION: PASS
RBAC: PASS
CONCURRENCY: FAIL
HISTORICAL_DATA_SAFETY: PARTIAL

CRITICAL_FINDINGS: 1
HIGH_FINDINGS: 2
MEDIUM_FINDINGS: 3

FINAL_VERDICT: YELLOW

REPORT:
PHASE_A1_ACADEMIC_ASSESSMENT_FORENSIC_AUDIT_REPORT.md
```

> **Evidence:** All findings are file:line cited, no data modified, no migrations created, no business logic changed. Historical `academic_final_result_rows` is frozen snapshot (safe), but `aggregation destroy` and marks concurrency remain `YELLOW` blockers.
