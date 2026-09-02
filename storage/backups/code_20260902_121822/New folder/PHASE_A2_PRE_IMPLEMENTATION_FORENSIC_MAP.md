# PHASE A2 — PRE-IMPLEMENTATION FORENSIC MAP

> **Date:** 2026-08-27 | **Mode:** READ-ONLY VERIFICATION | **Source:** `PHASE_A1_ACADEMIC_ASSESSMENT_FORENSIC_AUDIT_REPORT.md` (YELLOW)

Verification performed before any A2 code changes. All file:line cited from live `C:\xampp\htdocs\monetix`.

---
## 1. A1 Findings Verified

| A1 Finding | Verified | Evidence (file:line) |
|---|---|---|
| CRITICAL: `academic_result_aggregation_items.scheme_id` CASCADE can cascade-delete historical | **CONFIRMED** | `database/migrations/2026_08_17_160100_create_academic_result_aggregation_items_table.php:26` `foreignId('scheme_id')->constrained('academic_result_aggregation_schemes')->cascadeOnDelete()` ; `SHOW CREATE TABLE academic_result_aggregation_items` ? `FOREIGN KEY (scheme_id) REFERENCES ... ON DELETE CASCADE` |
| HIGH: weight defaults 0, 100% only preflight warning | **CONFIRMED** | `AcademicResultAggregationService.php:422-462` `validateItems()` checks `weight 0..100` but `weightIsValid()` `104-112` only called in `AcademicFinalResultLifecycleService::lock:363`, not in `AcademicResultAggregationService::store:137` |
| HIGH: multiple-assessment PARTIAL (Mid-Term+Final via name) | **CONFIRMED** | `academic_assessments` has `name varchar(120)` + `assessment_type_id` nullable, no unique `(year,class,group,name)`; `AcademicAssessmentTest` creates two assessments same year/class with names Mid-Term/Final and they coexist |
| MEDIUM: `AcademicMarksService::saveMarks` no lock | **CONFIRMED** | `app/Services/AcademicMarksService.php:395-521` `saveMarks` uses `updateOrCreate` per component `479-491` without `lockForUpdate` or `DB::transaction` on assessment row; `eligiblePlacements` `47` no lock |
| MEDIUM: duplicate assessments not prevented | **CONFIRMED** | `academic_assessments` has no unique `(institute_id,academic_year_id,class_grade_id,academic_group_id,name)`; `AcademicAssessmentService::store:91` has no `lockForUpdate` or `unique` check beyond `validateSubjects` |
| Historical freeze PARTIAL | **CONFIRMED** | `academic_assessments` has `locked_at/ locked_by` (`2026_08_21_000800:23`) and `isLocked():121` blocks `update:149`/`destroy:189`, but `academic_result_aggregation_schemes` `destroy:199` has **no** `isLocked` guard; `academic_final_result_rows` is frozen snapshot after `lock()` `358` via `updateOrCreate` |
| Tenant/RBAC PASS | **CONFIRMED** | All `settings/academic/*` routes have `tenant` + `verified` + `permission:education.manage` (`routes/institute_modules.php:1133`), models `AcademicAssessment:25` `TenantScoped` + `BranchScoped`, `GradeScale` manual `where institute_id` (`AcademicGradingController:204`) |

## 2. Current Schema (verified live)

- `academic_assessments` — `id PK`, `institute_id NOT NULL CASCADE` TenantScoped, `branch_id SET NULL`, `academic_year_id CASCADE`, `class_grade_id SET NULL`, `academic_group_id SET NULL`, `assessment_type_id SET NULL`, `name`, `exam_date`, `status varchar(20) DEFAULT draft` (draft/scheduled/open/completed/cancelled) + `locked_at/locked_by`, no `deleted_at`
- `assessment_subjects` — `assessment_id CASCADE`, `subject_id RESTRICT` (hardened S3), `pass_rule total_only/mandatory_components/both`
- `assessment_subject_components` — `assessment_subject_id CASCADE`, `component_id CASCADE`, `full_mark/pass_mark decimal(10,2)`
- `academic_student_marks` — `institute_id CASCADE`, `academic_assessment_id/assessment_subject_id/assessment_component_id/student_id/placement_id CASCADE`, `obtained_mark NULLABLE` (NULL=absent), `status entered/absent`, unique `(assessment_component_id,student_id)`
- `academic_result_aggregation_schemes` — `institute_id CASCADE`, `branch_id SET NULL`, `academic_year_id/class_grade_id CASCADE`, `academic_group_id SET NULL`, `name`, `status draft/active/archived`, no unique on `(year,class,group,name)` — **allows duplicates**
- `academic_result_aggregation_items` — `scheme_id CASCADE`, `academic_assessment_id CASCADE`, `weight decimal(8,2) DEFAULT 0`, unique `(scheme_id,academic_assessment_id)`
- `academic_final_results` — `policy_id/scheme_id CASCADE`, `status review/approved/locked/published`, `locked_at/published_at`, no `deleted_at`
- `academic_final_result_rows` — `result_id CASCADE`, `placement_id CASCADE`, `subject_id RESTRICT`, `aggregate/grade/gpa_included` snapshot
- `grade_scales` — `institute_id NULL` global override ladder, no TenantScoped, manual check

Foreign keys verified via `information_schema.REFERENTIAL_CONSTRAINTS` — all `ON UPDATE RESTRICT`, `ON DELETE` as above.

## 3. Current Controllers/Services (verified)

- `AcademicAssessmentController:122` `store()` ? `AcademicAssessmentService::store:91` (no transaction, no unique check)
- `AcademicAssessmentController:193` `update()` ? `service->update:142` with `abort_if(isLocked,422)`
- `AcademicAssessmentController:212` `destroy()` ? `service->destroy:187` with same `isLocked` guard
- `AcademicMarksController:52` `store()` ? `assertAssessmentEditable:313` (checks `isLocked` + `final_result locked/published`) ? `AcademicMarksService::saveMarks:395` (no transaction/lock)
- `AcademicAggregationController:129` `store()` ? `AcademicResultAggregationService::store:137` (validates `weight 0..100` but not `sum==100`)
- `AcademicFinalResultController:309` `lock()` ? `AcademicFinalResultLifecycleService::lock:206` (`canLock` + `snapshot:358` + `weightIsValid:363` gate)
- `GradeScale` via `AcademicGradingService:43` ladder, no `SoftDeletes`

## 4. Existing Tests (verified)

- `AcademicAssessmentTest` 23 tests: creation, duplicate subject/component, tenant isolation, but **no** concurrent duplicate assessment test
- `AcademicMarksSheetTest` 5 tests: matrix, branch isolation, but **no** concurrent marks overwrite test
- `AcademicResultAggregationTest` 28 tests: most complete for aggregation (40/60 weight, decimal precision, absent renormalization) but **no** `weight 99%` blocked at store time
- `AcademicGradingTest` 37 tests: grade bands, GPA, but **no** optional subject threshold test (S3.11 not yet)
- All tests use `DatabaseTransactions`, `TenantContext`, `email_verified_at` where needed; no test for `aggregation destroy after lock` (gap noted in A1)

## 5. Direct Writes/Deletes Against Affected Tables (grep)

- `DB::table('academic_result_aggregation_schemes')->` — only in `AcademicResultAggregationService` and `AcademicAssessmentService`
- `DB::table('academic_result_aggregation_items')->` — only via `AcademicResultAggregationService::syncItems:464`
- `DB::table('academic_student_marks')->` — only via `AcademicMarksService` (`updateOrCreate`, `clearRows`, `storeAbsent`)
- No raw `DELETE FROM academic_assessments` or `academic_final_results` found outside services.

## 6. Search Summary

- `assessment` — 31 test files, 18 routes `--path=assessment`, 9 services, 7 models, 6 Blade views for academic, 3 for legacy `exams`
- `marks` — 4 routes `--path=marks`, `AcademicMarksService` + `AcademicMarksController`
- `final_result` — 18 routes `--path=result`, `AcademicFinalResult*` 4 services, 3 models, 7 views (hub, policy, show, report-card, result-sheet, readiness, preflight)

All A1 findings **confirmed** against live code. No undocumented Assessment implementation found. Ready for A2 hardening.

---
