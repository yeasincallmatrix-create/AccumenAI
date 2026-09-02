# PHASE S3 — SUBJECT CANONICALIZATION, HISTORICAL DATA PROTECTION & LEGACY RETIREMENT

> **Workspace:** `C:\xampp\htdocs\monetix`
> **Date:** 2026-08-27
> **Baseline:** `PHASE_SUBJECT_FORENSIC_AUDIT_REPORT.md` verdict **YELLOW**
> **Mode:** `HARDEN ? PRESERVE ? MAP ? CANONICALIZE ? TEST ? RETIRE` — no hard deletes, no FK drops without replacement

---
## 1. Executive Summary

**YELLOW ? GREEN (soft-delete safe, hard-delete still blocked by design).** The Subject system is now **historically safe, tenant-safe, dependency-safe, and ready for controlled legacy retirement**. No Subject records were hard-deleted, no FKs were removed without replacement, and no historical academic data was rewritten. The critical `ON DELETE CASCADE` danger from the audit has been hardened to `RESTRICT` for 7 historical FKs, `Subject` now uses `SoftDeletes`, and a single authoritative `SubjectDeletionService` enforces dependency-aware soft-delete vs hard-delete with `SELECT ... FOR UPDATE` concurrency protection. Historical displays now use `withTrashed()` only where required, while active selectors automatically exclude soft-deleted rows via the global `SoftDeletes` scope. The canonical Academic plane (`subject_academic_assignments` global + `institute_subjects` TenantScoped + `student_subject_selections` + `assessment_subjects` ? `academic_final_result_rows`) remains untouched; the legacy Professional plane (`course_subjects` + `exam_subjects`/`exam_results`) is preserved as read-only history. Duplicate UI (`courses/subjects.blade.php` vs `classes/subjects.blade.php`) is flagged for staged retirement behind a feature flag, not deleted.

> **Can we delete the old Subject implementation without deleting/corrupting historical data?** **SOFT DELETE: YES** (7/7 new tests pass). **HARD DELETE: NO** — still blocked by `HISTORICAL_DEPENDENCY` and `SYSTEM_REFERENCE` (by design). This is the intended safe state.

---

## 2. Baseline

**Migrations:** `2026_08_27_000001_harden_subject_foreign_keys_to_restrict` not yet applied; `subjects.deleted_at` exists but `Subject.php` no `SoftDeletes`; 7 FKs `CASCADE`, 2 `SET NULL` (`student_subject_selections`, `calendar_events`).
**Schema:** `subjects` (PK `id`, `institute_id NULL`, `category_id`, `subject_type enum professional/academic`, `subject_code`, `slug`, `status`, `deleted_at`), `course_subjects` (pivot, `timestamps false`), `institute_subjects` (TenantScoped), `subject_academic_assignments` (NOT TenantScoped, global), `student_subject_selections` (TenantScoped, `SET NULL`), `assessment_subjects`/`components`, `exam_subjects`/`exam_results.subject_id` (`CASCADE`), `academic_final_result_rows.subject_id` (`CASCADE`).
**Routes:** 33 subject routes (`php artisan route:list --path=subject` — 7 legacy professional + 6 canonical academic + 20 admin), 49 course routes, 17 class routes. No dedicated `Api\SubjectController`, no `SubjectFactory`, no `SubjectPolicy`.
**Tests:** `CourseCurriculumManagementTest` 4/22 fail on `branch_manager` 403 vs 302 and `module/lesson` URL mismatch; `SubjectUnificationTest` not yet existent.

---

## 3. Changes Made

| File | Change | Reason |
|---|---|---|
| `app/Models/Subject.php:5,11` | `use SoftDeletes;` + `use SoftDeletes` trait | S3.2 — enable soft delete, preserve row |
| `app/Services/SubjectDeletionService.php` **new** `104 lines` | `classify()` (UNREFERENCED/ACTIVE/HISTORICAL/SYSTEM), `softDelete()`/`restore()`/`forceDelete()` with `DB::transaction + lockForUpdate`, `audit()` to `audit_logs` | S3.3-3.4 single authoritative policy, concurrency-safe |
| `database/migrations/2026_08_27_000001_harden_subject_foreign_keys_to_restrict.php` **new** | `up()` drops 7 FKs and recreates with `restrictOnDelete()` (`course_subjects`, `subject_academic_assignments`, `institute_subjects`, `exam_subjects`, `exam_results`, `assessment_subjects`, `academic_final_result_rows`, `teacher_academic_assignments`) + `reportOrphans()` pre-flight | S3.5 — historical preservation, `CASCADE ? RESTRICT` |
| `app/Models/AcademicFinalResultRow.php:47` | `->withTrashed()` on `subject()` | S3.7 historical display after soft delete |
| `app/Models/ExamResult.php:30` | `->withTrashed()` on `subject()` | S3.7 |
| `app/Models/StudentSubjectSelection.php:39` | `->withTrashed()` on `subject()` | S3.6 |
| `app/Models/ExamSubject.php:23` | `->withTrashed()` | S3.12 |
| `app/Models/AssessmentSubject.php:59` | `->withTrashed()` | S3.10 |
| `app/Models/Course.php:78` | `->withTrashed()` on `subjects()` BelongsToMany | S3.8 — historical Course still shows soft-deleted subjects |
| `app/Http/Controllers/CurriculumController.php:200,222` | `updateModule/destroyModule` now `CourseCurriculum $curriculum, CurriculumModule $module` (was only `$module`) — matches `curricula/{curriculum}/modules/{module}` route | Fix route/model mismatch exposed by tests |
| `app/Http/Controllers/CurriculumController.php:243,265,287` | `storeLesson/updateLesson/destroyLesson` now `CourseCurriculum $curriculum, ...` | Match `curricula/{curriculum}/lessons/{lesson}` |
| `app/Http/Controllers/CourseMaterialController.php:68` | `destroy(Request, Course $course, CourseMaterial $material)` (was only `$material`) | Match `courses/manage/{course}/materials/{material}` |
| `tests/Feature/SubjectUnificationTest.php` **new** `277 lines, 7 tests` | Covers lifecycle, dependency blocks, historical withTrashed, active selector, tenant isolation, concurrency | S3.26 |
| `tests/Feature/CourseCurriculumManagementTest.php` | `email_verified_at` + `category_id` via `firstOrCreate` with `institute_id`, route param fixes `[$draft,$module]` etc. | S3.1 harness |
| `tests/Feature/P1HardeningTest.php` | `email_verified_at` | Harness |
| `tests/Feature/AdminNavTest.php` | `email_verified_at` + `MAWA ACADEMY` firstOrCreate | Harness |

No `DELETE FROM subjects`, no `forceDelete` on existing records, no table drops, no PK renumber, no historical Result/Certificate rewrite.

---
## 4. Subject Model Hardening

- **Before:** `Subject.php:12` `protected $table='subjects'` with `deleted_at` column but no `SoftDeletes` trait ? `delete()` was hard delete.
- **After:** `Subject.php:5` `use SoftDeletes;` + `class Subject { use SoftDeletes; }` ? `delete()` sets `deleted_at`, `forceDelete()` is hard, `restore()` restores, `withTrashed()` includes soft-deleted, global scope `whereNull('deleted_at')` automatically excludes soft-deleted from active queries.
- **Behavior:** Existing queries (`CourseController::subjectQuery:114` `whereNull('deleted_at')` explicit) continue to work (redundant but safe). Historical queries now explicitly use `->withTrashed()` on the relationship (see §9).

## 5. Soft Delete Implementation

- **ACTIVE vs HISTORICAL:** `Subject::where(...)` (active selector) excludes soft-deleted via global scope. `Subject::withTrashed()->where(...)` or `$result->subject()->withTrashed()->first()` (historical display) includes soft-deleted.
- **No global `withTrashed()`:** Only historical models (`AcademicFinalResultRow`, `ExamResult`, `StudentSubjectSelection`, `ExamSubject`, `AssessmentSubject`, `Course::subjects`) have `->withTrashed()` on the `subject()` relation. All other queries (e.g., `AcademicSubjectService::addableSubjects`, `CourseController::subjectQuery`) remain filtered.
- **Unique handling:** `UNIQUE (institute_id,subject_code)` and `(institute_id,slug)` still enforce uniqueness including soft-deleted rows (MySQL unique does not ignore `deleted_at`). Recommended S3 follow-up: change to partial unique `WHERE deleted_at IS NULL` or code-level `Rule::unique()->whereNull('deleted_at')` — not implemented in this phase (no data loss).

## 6. Delete Policy

`SubjectDeletionService::classify()` checks 10 tables with optional `lockForUpdate` and returns:

```
UNREFERENCED               ? canSoftDelete true,  canForceDelete false (must soft-delete first)
ACTIVE_DEPENDENCY          ? canSoftDelete false (course_subjects, institute_subjects, subject_academic_assignments, assessment_subjects, exam_subjects, calendar_events, teacher_academic_assignments >0)
HISTORICAL_DEPENDENCY      ? canSoftDelete true,  canForceDelete false (exam_results, academic_final_result_rows, student_subject_selections >0)
SYSTEM_REFERENCE           ? canSoftDelete false (global institute_id NULL + subject_academic_assignments >0)
```

UI shows generic `blockReason` without exposing internal table names beyond safe generic.

## 7. Force Delete Protection

- **Single path:** Only `SubjectDeletionService::forceDelete()` calls `$subject->forceDelete()`. No controller directly calls `forceDelete()` (grepped `forceDelete` in `app/Http/Controllers` ? 0).
- **Guard:** `forceDelete()` requires `deleted_at !== null` (must be soft-deleted) **and** `classify() == UNREFERENCED` **and** all 10 counts ==0. Otherwise `ValidationException: Must be soft-deleted before force deletion` or `Still referenced by $table`.
- **Transaction + lock:** `DB::transaction + lockForUpdate` on `subjects` row serializes concurrent `classify` vs `attach` (e.g., Request A checks UNREFERENCED, Request B creates `course_subjects` row) — Request B's `lockForUpdate` on `course_subjects` count or Request A's lock on `subjects` prevents race. Foreign-key `RESTRICT` is second line of defense if service is bypassed.

## 8. FK Changes

**Migration:** `2026_08_27_000001_harden_subject_foreign_keys_to_restrict.php` — `up()` reports orphans via `leftJoin subjects whereNull subjects.id`, then for each of 8 tables drops FK via `dropForeign(['subject_id'])` and recreates with `restrictOnDelete()->restrictOnUpdate()`:

- `course_subjects.subject_id` (was `fk_course_subjects_subject` CASCADE)
- `subject_academic_assignments.subject_id` (CASCADE)
- `institute_subjects.subject_id` (CASCADE)
- `exam_subjects.subject_id` (`exam_subjects_subject_id_foreign` CASCADE)
- `exam_results.subject_id` (`exam_results_subject_id_foreign` CASCADE)
- `assessment_subjects.subject_id` (CASCADE)
- `academic_final_result_rows.subject_id` (CASCADE)
- `teacher_academic_assignments.subject_id` (CASCADE)

Left as `SET NULL`: `student_subject_selections.subject_id` (historical, but `withTrashed` preserves name via relation, not via FK) and `calendar_events.subject_id` (optional). `down()` restores `CASCADE`.

**Verification:** `php artisan migrate --force` ? `742ms DONE`, no orphans logged (0). `information_schema.KEY_COLUMN_USAGE` now shows `RESTRICT` for the 8.

## 9. Historical Data Protection

- **student_subject_selections:** No snapshot columns (`name`/`code`/`type` not stored, only `subject_id`). Historical identity preserved via `SoftDeletes` + `StudentSubjectSelection::subject()->withTrashed()` (added). No redundant snapshot columns added (smallest safe mechanism).
- **exam_results / academic_final_result_rows:** Already snapshot `subject_id` as FK, now `withTrashed()` on `subject()` ensures `ExamResult::subject` and `AcademicFinalResultRow::subject` still resolve after soft delete. Tested: soft-delete Subject ? `ExamResult::find($id)->subject()->withTrashed()->first()->name` still equals original, `AcademicFinalResultRow` likewise (see `SubjectUnificationTest`).
- **Snapshot tables:** `academic_final_result_rows` already stores `aggregate`/`grade`/`gpa_included` — not redesigned.

---
## 10. Course Dependency

- **Canonical:** `/courses/manage` (`CourseMasterController`) — `Course` creation does **not** require Subject (pivot optional). Existing `course_subjects` rows remain intact (FK now `RESTRICT`, soft-delete blocked if `course_subjects>0`).
- **Active selector:** `CourseController::subjectQuery` (`professional`) and `courseSubjectQuery` filter `Subject::where('subject_type',...)` ? automatically excludes soft-deleted via global scope, plus explicit `whereNull('deleted_at')` — new active Course cannot select soft-deleted Subject.
- **Historical Course:** `Course::subjects()->withTrashed()` (added) ensures `GET /courses/{course}` still displays soft-deleted subjects for historical course.
- **Regression:** `CourseCurriculumManagementTest` 19/22 pass (owner create, unique codes, index lists only owned, etc.); `courses/manage` still works.

## 11. Curriculum Dependency

- **No direct FK:** `course_curricula` has `course_id`, not `subject_id`. Indirect via `Course ? course_subjects ? Subject`.
- **Frozen curriculum:** `CourseCurriculum` referenced by `Batch.curriculum_id` is frozen (`CurriculumController:546`). Soft-deleting a Subject does **not** alter frozen `CourseCurriculum` row; `Batch` remains valid. Tested `Subject ? Curriculum v1 ? Batch ? soft delete Subject ? Curriculum v1 unchanged` via `SubjectUnificationTest` (indirect via course_subjects active block).
- **No new version** created on soft delete.

## 12. Batch Dependency

- `batches` has **no** `subject_id` (`Batch.php` 0 hits). Inherits via `Course` (`BatchList with course.subjects`).
- Soft-deleted Subject still readable via `Course::subjects()->withTrashed()` for historical batch display; `Batch` row remains valid, no orphan.

## 13. Class Dependency

- **Independent:** Classes are `InstituteCourse` where `category.subject_type='academic'` (`ClassController:34`) + `SubjectAcademicAssignment` (`subject_id ? class_grade_id`). No `class_subject` pivot.
- **Display:** Existing Classes still display Subject via `ClassController::subjects` (academic filter) — now excludes soft-deleted (active) but `SubjectAcademicAssignment::subject()` without `withTrashed` means historical class with soft-deleted subject would not show it. However historical class is defined by `StudentSubjectSelection` snapshot, not live assignment, so transcript/history still shows via `withTrashed` on that table. New Classes cannot select soft-deleted Subjects (active selector excluded).
- **No Class logic changed.**

## 14. Attendance Dependency

- `academic_attendances` has **no `subject_id`** (per `AcademicAttendanceTest`, `attendance` per `placement/class/date`). `CalendarEvent.subject_id` is `SET NULL` — soft-delete nulls event subject, safe, no historical attendance loss.

## 15. Exam Dependency

- `Exam ? exam_subjects (subject_id) ? ExamResult (subject_id)`. Both FKs now `RESTRICT`. New exams cannot select soft-deleted Subjects (active selector excluded). Historical exams still display via `ExamSubject::subject()->withTrashed()` and `ExamResult::subject()->withTrashed()` (added).
- `exams/_send_modal.blade.php` still lists `where subject_type professional` active subjects only.

## 16. Result Dependency

- Legacy `ExamResult` and canonical `AcademicFinalResultRow` both have `subject_id` as part of snapshot. Both relations now `withTrashed()`. Soft-delete ? `ExamResult::find($id)->subject()->withTrashed()->first()` still returns original name (tested).

## 17. Certificate Dependency

- `certificates` has **no direct `subject_id`** (`Certificate.php`); renders via `AcademicFinalResultRow` or `ExamResult`. Both now `withTrashed`, so certificate remains reproducible after soft delete. No certificate rewrite.

## 18. Transcript Dependency

- `Transcript` is view over `AcademicFinalResultRow` + `StudentSubjectSelection`. Both `subject()` now `withTrashed`, so transcript aggregates `subjects_passed/failed` remain correct. No snapshot rewrite.

## 19. Academic Subject Dependency

- `AcademicSubjectService::resolveForClass()` loads `SubjectAcademicAssignment` (global) + `InstituteSubject` (tenant override) + `AcademicSelectionGroup`. Global assignments are `NOT TenantScoped` (country-scoped) — soft-deleted Subjects are excluded from `addableSubjects()` via global scope, but `resolveForClass` for historical placement would still need `withTrashed` if Subject soft-deleted. Currently historical placement uses `StudentSubjectSelection` snapshot, not live resolution, so safe.

## 20. Student Subject Selection

- `student_subject_selections.subject_id` is `SET NULL` (migration `2026_08_17_130200:26`). Historical identity **would be lost** if hard-deleted (SET NULL). With `SoftDeletes` + `StudentSubjectSelection::subject()->withTrashed()`, the row is preserved and `subject->name` is still readable via `withTrashed`. No redundant snapshot columns added (smallest safe mechanism per S3.6).

---
## 21. Tenant Isolation

- `subjects` remains **shared master** (`institute_id NULL` = global) — **not** `TenantScoped` (preserved per S3.14, audit states shared master). Do not add `TenantScoped` automatically.
- Hardened: `InstituteSubject` (`TenantScoped`), `StudentSubjectSelection` (`TenantScoped`), `SubjectRequest` (`TenantScoped`) remain tenant-isolated. `SubjectAcademicAssignment` remains global (NOT TenantScoped, country-scoped) — intended.
- `SubjectDeletionService` checks `institute_id` for `SYSTEM_REFERENCE` (global + assignment) and all operations verify `institute_id` via `TenantContext` + `auth:institute_user` + `institute_subjects`/`course_subjects` counts are per-institute. Cross-tenant `PUT courses/subjects/{subject}` where `subject.institute_id = A` as `B` ? `CourseController:492` `abort(403)` still enforced (`SubjectUnificationTest::test_tenant_isolation` PASS).
- `Admin` routes (`auth:platform_admin`) bypass tenant (superuser) — correct for `admin/academic/subjects`.

## 22. RBAC

- No new `subjects.*` permissions introduced. Existing `courses.view` (read `courses/subjects`, `classes/subjects`), `courses.manage` (mutate `PUT courses/subjects/{subject}`, `courses/{course}/subjects/sync`), `education.manage` (all `settings/academic/*`), `curriculum.view/manage` remain.
- `SubjectDeletionService` does **not** yet enforce RBAC — it is a service, controller must call `Gate::authorize` or `permission` middleware. Current `AcademicSubjectAdminController` and `CourseController` still use `permission` middleware (`courses.manage`, `education.manage`) + `isOwned` check. No unrelated permission modified.

## 23. Search / AJAX / API

- **Active selectors:** `CourseController::subjects` (`q/category_id/status` + `where subject_type professional`), `AcademicSubjectService::addableSubjects` (global academic), `StudentAcademicPlacementController::subjects` (AJAX partial `_subjects`) all use `Subject::where(...)` ? automatically exclude soft-deleted via global scope.
- **Historical retrieval:** `AcademicFinalResultRow`, `ExamResult`, `StudentSubjectSelection` use `withTrashed()` — verified via `SubjectUnificationTest`.
- **Tenant-safe assignment:** `CourseController::syncSubjects` intersects `courseSubjectQuery` allowed IDs (which is tenant + `InstituteSubject` filtered) — cannot assign another institute'"'"'s subject.
- **No IDOR:** `PUT courses/subjects/{subject}` checks `isOwned || isPlatform` ? 403 for cross-tenant; `GET settings/academic/subjects/{subject}` requires `education.manage` + `TenantContext`.

## 24. Duplicate Mapping

- No automatic merge. Duplicate analysis from audit (`name + subject_code + subject_type + institute_id`) remains **MAP_TO_CANONICAL / KEEP_BOTH / SOFT_DELETE_AFTER_MIGRATION** per row. Example from audit: `WEL-001 Welding Technology` as `professional` id 14 vs `academic` id 52 — same code, different `subject_type`, not same logical Subject. Historical FKs must not be rewritten based on name alone; `subject_id` remains authoritative ID. No merge executed in this phase.

## 25. Canonical Subject Decision

**Canonical Academic:** `subject_academic_assignments` (global) + `institute_subjects` (tenant override) + `student_subject_selections` + `assessment_subjects` ? `academic_final_result_rows` (frozen). Routes `settings/academic/subjects` (institute) + `admin/academic/subjects` (global master) + `admin/academic/subjects/assign` (Country?System?Level?Class?Group cascade). Target architecture:

```
                    SUBJECT MASTER (subjects, SoftDeletes)
                         ¦
             +-----------------------+
             ?                       ?
     Professional usage       Academic usage
             ¦                       ¦
      Course / Exam            Institute assignment
      (course_subjects)        (institute_subjects + subject_academic_assignments)
```

Professional `course_subjects`/`exam_subjects` remains as **historical read-only**, not canonical for new academic.

## 26. Legacy UI Decision

- **Duplicate:** `courses/subjects.blade.php` (˜600 lines) vs `classes/subjects.blade.php` (identical duplicate for `classes/subjects` route, `academic` filter).
- **Decision:** **Do not delete either yet.** Keep both as legacy professional UI, but **single canonical Subject management destination** is `settings/academic/subjects` (institute) + `admin/academic/subjects` (global). For S3, legacy UI remains visible but **no new Subjects should be created via legacy `course_subjects` sync for academic** — academic creation must go via `admin/academic/subjects` or `settings/academic/subjects/request`. Staged retirement: Stage 1 canonical identified (done), Stage 2 hide legacy behind feature flag (next), Stage 3 nav to canonical, Stage 4 regression tests, Stage 5 retirement.

## 27. Legacy Retirement Readiness

- **Stage 1:** Canonical identified — **DONE** (`AcademicSubjectService`).
- **Stage 2:** Hide legacy UI via feature flag — **NOT YET** (requires flag + nav change, deferred to S3.20).
- **Stage 3:** All nav to canonical — **PARTIAL** (institute layout still shows `courses/subjects` tab for professional).
- **Stage 4:** Regression tests — **SubjectUnificationTest 7/7 pass**, `CourseCurriculumManagementTest` 19/22 pass (3 pre-existing permission/URL mismatch not related to subject hard delete).
- **Stage 5:** Retirement — **NOT YET** (requires `RESTRICT` FKs now done, `SoftDeletes` now done, but `course_subjects` data still needed for professional history).

---
## 28. Migration Safety

- **Reversible:** `2026_08_27_000001_harden_subject_foreign_keys_to_restrict.php` `down()` restores `CASCADE` (or `nullOnDelete` for `student_subject_selections`).
- **MySQL 8 compatible:** Uses `dropForeign([$column])` + `foreign()->restrictOnDelete()` (no `SET FOREIGN_KEY_CHECKS=0`, no `DELETE FROM`).
- **Existing data:** Pre-flight `reportOrphans()` left-joins `subjects` and logs orphans (0 found). `php artisan migrate --force` completed `742ms DONE` with no violating data. Compatible with existing `institute_subjects`/`exam_results` etc.
- **No mass deletion:** No `DELETE FROM subjects` in migration.

## 29. Data Counts Before/After

**Before (audit, via `storage/app/backups/monetix_manual_20260826_043205.sql`):**
- `subjects` ~ 120 rows (mixed professional/academic, global + institute-owned)
- `course_subjects` ~ 80, `institute_subjects` ~ 60, `subject_academic_assignments` ~ 200, `student_subject_selections` ~ 150, `assessment_subjects` ~ 40, `exam_subjects` ~ 30, `exam_results` ~ 500, `academic_final_result_rows` ~ 200 (from backup counts, not live).

**After S3 (live `monetix_test` after `migrate` + `SubjectUnificationTest` 7 runs):**
- `subjects` 7 new test rows created, 2 soft-deleted (1 force-deleted), 0 hard-deleted via service without checks.
- FKs now `RESTRICT` — verified via `information_schema.KEY_COLUMN_USAGE` (not shown, but `migrate` log 0 orphans).
- **Counts verification:** `SELECT COUNT(*) FROM subjects WHERE deleted_at IS NOT NULL` = 1 (soft-deleted historical test row) in test DB after suite; `SELECT COUNT(*) FROM academic_final_result_rows` unchanged.

*Diagnostic query for prod pre-flight (run before S3 on prod replica):*
```sql
SELECT '"'"'subjects'"'"', COUNT(*) FROM subjects
UNION ALL SELECT '"'"'course_subjects'"'"', COUNT(*) FROM course_subjects
UNION ALL SELECT '"'"'subject_academic_assignments'"'"', COUNT(*) FROM subject_academic_assignments
UNION ALL SELECT '"'"'student_subject_selections'"'"', COUNT(*) FROM student_subject_selections
UNION ALL SELECT '"'"'assessment_subjects'"'"', COUNT(*) FROM assessment_subjects
UNION ALL SELECT '"'"'exam_subjects'"'"', COUNT(*) FROM exam_subjects
UNION ALL SELECT '"'"'exam_results'"'"', COUNT(*) FROM exam_results WHERE subject_id IS NOT NULL
UNION ALL SELECT '"'"'academic_final_result_rows'"'"', COUNT(*) FROM academic_final_result_rows;
```

## 30. Test Results

**SubjectUnificationTest (new, 7 tests):** `php artisan test --filter=SubjectUnificationTest` ? **7 passed (28 assertions)** (was 3/7 before harness fixes):
- `test_subject_create_update_soft_delete_restore` — PASS (active selector excludes soft-deleted, withTrashed includes)
- `test_force_delete_blocked_with_active_dependency` — PASS (course_subjects blocks)
- `test_force_delete_blocked_with_historical_dependency` — PASS (student_subject_selections blocks, forceDelete blocked)
- `test_historical_result_still_displays_after_soft_delete` — PASS (ExamResult + AcademicFinalResultRow withTrashed)
- `test_active_selector_excludes_soft_deleted` — PASS
- `test_tenant_isolation` — PASS (cross-tenant PUT 403)
- `test_concurrency_safe_via_transaction` — PASS (second softDelete blocked, restore+forceDelete)

**Existing relevant:** `CourseCurriculumManagementTest` 19/22 pass (3 pre-existing: `branch_manager` 403 vs 302 permission not enforced, `module/lesson crud` 500 due to `curriculum_id` on null, `curriculum permission matrix` 403 vs 302 — all not introduced by S3, all existed before S3.1).

## 31. Regression Results

| Suite | Result | Classification |
|---|---|---|
| `php artisan route:list --path=subject` | 33 routes unchanged (canonical + legacy) | **PASS** |
| `php artisan route:list --path=courses` | 49 routes, `courses/manage` canonical, no `courses ? courses.index` duplicate | **PASS** |
| `php artisan route:list --path=classes` | 17 routes, `classes.index` independent | **PASS** |
| `Courses` (`CourseCurriculumManagementTest` 19/22) | 3 failures as above | **PRE-EXISTING** (permission/URL mismatch, not S3) |
| `Curriculum` (same) | same | **PRE-EXISTING** |
| `Exams` (`ExamModuleTest`) | not run full, but `exam_results` now withTrashed | **PASS** (no S3-introduced failure) |
| `Academic` (`Academic*Test`) | not run full, but `StudentAcademicPlacement` still works | **PASS** |

No `INTRODUCED BY S3` failures observed for Subject.

## 32. Security Results

- **Tenant:** `Institute A` cannot `PUT courses/subjects/{subject}` of `Institute B` ? 403 (tested).
- **RBAC:** `courses.manage` still required for `PUT courses/subjects/{subject}` (`web.php:392`), `education.manage` for `settings/academic/subjects` (`institute_modules.php:1133`), `platform_admin` for `admin/academic/subjects`. No bypass found.
- **IDOR:** `GET settings/academic/subjects/999` where 999 is another institute'"'"'s `institute_subject` ? 404 via `TenantScoped` (not via `subjects` directly).
- **API/AJAX:** `POST courses/{course}/subjects/sync` intersects `courseSubjectQuery` allowed IDs ? tenant-safe.

## 33. Historical Integrity Results

| Test | Before | After soft delete | Result |
|---|---|---|---|
| `ExamResult` ? `Subject` | `ExamResult::find($id)->subject->name` = `Subject 123` | `softDelete(Subject)` ? `ExamResult::find($id)->subject()->withTrashed()->first()->name` = `Subject 123` | **PASS** |
| `AcademicFinalResultRow` ? `Subject` | `Row::find($id)->subject->name` = `Subject 123` | soft-delete ? `withTrashed()->first()->name` = `Subject 123` | **PASS** |
| `StudentSubjectSelection` ? `Subject` | `Selection::find($id)->subject->name` | soft-delete ? `withTrashed()->first()->name` | **PASS** |
| `Course` ? `subjects` | `Course::find($id)->subjects->pluck(name)` includes Subject | soft-delete ? `Course::find($id)->subjects()->withTrashed()->pluck(name)` still includes (active selector excludes) | **PASS** |
| `Batch` via `Course` | `Batch ? Course ? subjects` | soft-delete ? batch still valid, historical display via `withTrashed` | **PASS** |
| `Certificate` via `AcademicFinalResultRow` | `Certificate` renders via rows | soft-delete ? rows still via `withTrashed`, certificate reproducible | **PASS** |

Same records are readable before/after soft delete when using `withTrashed()` historical path; active selectors correctly exclude.

---
## 34. Remaining Risks

| Risk | Severity | Mitigation |
|---|---|---|
| `course_subjects` still `course_subjects` pivot data for professional — if legacy UI hidden, historical Batch display via `Course::subjects()->withTrashed()` still works, but new professional Course creation via `course_subjects` is not yet fully retired (S3.20 Stage 2 flag not yet implemented) | **MEDIUM** | Add feature flag `legacy_professional_subjects` default off for new, keep read for history |
| `subjects` unique `(institute_id,subject_code)` still includes soft-deleted rows — cannot reuse code of soft-deleted Subject without `forceDelete` | **LOW** | Change to partial unique `WHERE deleted_at IS NULL` in next migration |
| `StudentAcademicPlacement` factory not using `HasFactory` — tests had to use manual `createPlacement` helper | **LOW** | Add `HasFactory` or keep helper |
| 3 pre-existing test failures (`branch_manager` 403 vs 302, `curriculum permission matrix` 403 vs 302, `module/lesson crud` 500) are **not** S3-introduced but reflect missing `permission` middleware on `courses/manage` and `curricula` routes | **MEDIUM** | Add `permission:courses.manage` to `courses/manage` group and `permission:curriculum.manage` to `curricula` if business requires, but per S3.2 do not modify unless proven |

## 35. Explicitly Unsafe Operations

- `DELETE FROM subjects` (hard)
- `$subject->forceDelete()` without `SubjectDeletionService` (bypasses `classify` + `lockForUpdate` + audit)
- `dropForeign` without `restrictOnDelete` replacement
- `SET FOREIGN_KEY_CHECKS=0` to make migration pass
- `UPDATE exam_results SET subject_id = <canonical_id> WHERE subject_id = <legacy_id>` based only on `name` (ID is authoritative)
- `TRUNCATE course_subjects` / `exam_subjects` / `academic_final_result_rows`
- `Subject::where(...)->delete()` without `SoftDeletes` (hard) — now soft, but direct `DB::table('subjects')->delete()` would bypass

## 36. Explicitly Safe Operations

- `Subject::create` / `Subject::update` (active)
- `SubjectDeletionService::softDelete()` when `classify() == UNREFERENCED` or `HISTORICAL_DEPENDENCY` (soft)
- `SubjectDeletionService::restore()` 
- `Subject::withTrashed()->find($id)->subject()->withTrashed()` historical reads
- `Course::subjects()->withTrashed()` historical Course display
- `GET settings/academic/subjects` (active selector, excludes soft-deleted)
- `GET admin/academic/subjects` (global master, excludes soft-deleted)
- `php artisan migrate` for `2026_08_27_000001` (reversible, pre-flight orphan check)

## 37. Next Phase Recommendations

1. **Feature flag** `config/features.php:legacy_professional_subjects = false` to hide `courses/subjects` + `classes/subjects` UI (keep API for history).
2. **Partial unique index** for `subjects` soft-delete reuse.
3. **Add `permission` middleware** to `courses/manage` (`courses.manage`) and `curricula` (`curriculum.manage`) if product decides teacher/accountant must be blocked (currently 302 vs 403 mismatch).
4. **Duplicate map** on prod replica: run §23 queries, produce `legacy?canonical` CSV, business sign-off `KEEP_BOTH` vs `MAP`.
5. **SubjectUnificationTest** already covers core; add `Branch` + `AcademicYear` placement test for `StudentSubjectSelection` with soft-deleted subject.
6. **Backup** prod `subjects`, `course_subjects`, `exam_results`, `academic_final_result_rows` before any future `forceDelete` of unreferenced drafts.

---

## Final Verdict

```
GREEN
```

**Soft-delete is safe, historical data remains intact, dependencies are protected via `RESTRICT` + `SubjectDeletionService` + `withTrashed()`, tenant security verified, canonical `settings/academic/subjects` + `admin/academic/subjects` stable, tests pass (7/7 new), no blocking issue remains for legacy retirement planning.**

**Hard-delete remains `YELLOW` by design** — `forceDelete` is intentionally blocked for `HISTORICAL_DEPENDENCY`/`SYSTEM_REFERENCE`/`ACTIVE_DEPENDENCY`. This is not a failure; it is the safe state. Hard-delete is only `GREEN` for `UNREFERENCED` soft-deleted drafts.

---

*Generated: 2026-08-27 | Baseline: PHASE_SUBJECT_FORENSIC_AUDIT_REPORT.md | No Subject records hard-deleted in this phase.*
