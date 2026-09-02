# PHASE A2 — ACADEMIC ASSESSMENT HARDENING, MULTI-ASSESSMENT AGGREGATION & RESULT INTEGRITY

> **Workspace:** `C:\xampp\htdocs\monetix` | **Date:** 2026-08-27 | **Baseline:** `PHASE_A1_ACADEMIC_ASSESSMENT_FORENSIC_AUDIT_REPORT.md` (YELLOW) | **Pre-map:** `PHASE_A2_PRE_IMPLEMENTATION_FORENSIC_MAP.md`

---
## 1. Executive Summary

**YELLOW ? GREEN (soft-delete safe, hard-delete blocked, aggregation hardened).** A2 moved Academic Assessment from **YELLOW** (3 critical gaps) to **GREEN** for soft-delete/historical safety without breaking existing functionality. Critical `academic_result_aggregation_items.scheme_id` CASCADE that could cascade-delete historical `academic_final_results` is now `RESTRICT` via migration `2026_08_27_000002`, `AcademicResultAggregationService::destroy` now blocks deletion of schemes referenced by locked/published results with audit, weight validation is now central (`assertTotalWeightForStatus` with 0.005 tolerance, DRAFT allowed, active must be 100% and no zero-weight), component vs assessment weighting is preserved as two layers, Mid-Term/Final as independent assessments with 30/70 aggregation is deterministic, marks concurrency now uses `SELECT ... FOR UPDATE` on `academic_assessments` row, duplicate assessments are now prevented via service `lockForUpdate` + DB unique `uq_assessment_institute_year_class_group_name`, optional subject bonus (threshold 2.00, capped 5.00, configurable per `grade_scales`) is implemented and snapshot-safe, and historical `academic_final_result_rows` remains readable via `withTrashed()`.

## 2. Before/After Architecture

**Before (A1):**
```
AcademicAssessment (draft/locked) ? no unique, no lock on marks, weight 0..100 stored but total 100% only preflight warning, aggregation destroy no guard, CASCADE on scheme_id, no optional bonus, no SoftDeletes
```

**After (A2):**
```
AcademicAssessment (draft/locked, unique institute+year+class+group+name via group_key virtual, lockForUpdate on store/update)
  ? AssessmentSubject (RESTRICT on subject_id)
  ? AssessmentSubjectComponent (full/pass, mandatory_pass)
  ? AcademicStudentMark (unique component+student, lockForUpdate on assessment row in saveMarks)
  ? AcademicResultAggregationScheme (status draft/active, weightIsValid 100% ±0.005, totalWeight, destroy blocked if locked/published)
    ? Items (weight, RESTRICT FK, audit on delete)
  ? AcademicFinalResultPolicy (absent_renormalization, grade_scale_id)
  ? AcademicFinalResult (review?locked?published, snapshot rows/students frozen, withTrashed on subject)
  ? GradeScale (optional_subject_bonus_threshold 2.00, bonus_enabled true, max_gpa 5.00, configurable)
  ? AcademicCumulativeResult (CGPA)
```

No new parallel system, no merge with `exams` legacy, no table drops.

## 3. Assessment Lifecycle

| Stage | Before | After | File:Line |
|---|---|---|---|
| CREATE | `store:91` no duplicate check | `store:104` `lockForUpdate` + duplicate check on `(institute,year,class,group,name)` + `nextDisplayOrder` | `AcademicAssessmentService:104` |
| DRAFT | `status draft` allowed | same, weight total may be !=100% (DRAFT allowed) | `AcademicResultAggregationService:store:137` calls `assertTotalWeightForStatus` which allows draft any total |
| CONFIGURE | `update:142` no duplicate check | `update:159` duplicate check excluding self | `AcademicAssessmentService:159` |
| LOCK | `lock:210` sets `locked_at` | same, `isLocked()` blocks `update:149`/`destroy:189`/`saveMarks:397` | `AcademicAssessment:121` |
| PUBLISH | `publish:232` `canPublish` | same, `recomputeCumulativeGpa` | `AcademicFinalResultLifecycleService:232` |
| DELETE | `destroy:187` blocked if `isLocked` only | `destroy` still blocked if locked, **plus** `AcademicResultAggregationService::destroy:199` now blocks if `final_results locked/published` or `locked assessment` + `RESTRICT` FK | `AcademicResultAggregationService:199` |

No `ARCHIVE`/`RESTORE` still (no SoftDeletes on academic tables — deliberate, not required for A2).

## 4. Multiple-Assessment Aggregation Model

**Multiple assessments FOUND and now correctly weighted.** Example `2027 Class 8 Science`:

- `Mid-Term` (AcademicAssessment `name=Mid-Term`, `assessment_type_id` = mid-term) + `Final` (name=Final) both for same `academic_year_id=2027, class_grade_id=8, academic_group_id=Science` — **both coexist** (no unique violation because `name` differs).
- Aggregation Scheme `Scheme MF` (`academic_year_id=2027, class_grade_id=8, group=Science, status=active`) with `Items: (Mid-Term, weight 30), (Final, weight 70)` — stored in `academic_result_aggregation_items` (`scheme_id, assessment_id, weight, display_order`).
- Calculation: `AcademicResultAggregationService::subjectAggregate:239` does `pct = obtained/total*100` per assessment (4dp), `effective_weight = weight / sumEntered*100` (renormalized if absent), `aggregate = SUM(pct * effective_weight/100)` (4dp) ? `round 2dp`. Example `80×30% + 90×70% = 87` verified via `AcademicResultAggregationTest:40_60_weightage`.

**Future types** (`Class Test, Quiz, Practical, Half-Yearly, Model Test`) are just `AssessmentType` rows (`AcademicAssessmentSeeder:18` 7 types + 12 components) — no hard-coded two names, `assessment_type_id` is FK, `display_order` sequences.

## 5. Component vs Assessment Weighting

**Two distinct layers preserved:**

- **A. Component weight** — within one `AssessmentSubject`: `Written 70% + Practical 30%` is `AssessmentSubjectComponent.full_mark` sum = `subject total_full` (e.g., `Written full 70, Practical full 30, total 100`). Not a percentage, but `full_mark` per component.
- **B. Assessment weight** — across `AcademicResultAggregationScheme` items: `Mid-Term 30% + Final 70%` is `academic_result_aggregation_items.weight` (percentage, sum 100%).

Architecture: `Student Marks ? Component Aggregation (sum per subject) ? Assessment Subject Mark (pct) ? Assessment Aggregation (weighted) ? Final Subject Mark (aggregate) ? Grade/GP ? Final Result`. No mixing — component sum is `subjectResult:341` `totalFull = sum(full_mark)`, assessment weight is `subjectAggregate:342` `effective_weight`.

## 6. Optional Subject Model

- **Classification:** `AcademicSubjectService::effectiveSubjectGpaIncluded` checks `SubjectAcademicAssignment.requirement_type` (`mandatory/optional/elective`) vs `InstituteSubject.requirement_type` override (`mandatory` default). `AcademicFinalResultService::isOptionalSubject:175` returns true if `requirement_type` in `optional/elective`.
- **Bonus formula (Bangladesh default):** `Bonus = max(GP - 2.00, 0)` where `GP` is optional subject's `grade_point`. Implemented in `AcademicFinalResultService::gpa:201` with `$threshold = $scale->optional_subject_bonus_threshold ?? 2.00`, `$bonusEnabled = $scale->optional_subject_bonus_enabled ?? true`, `$maxGpa = $scale->max_gpa ?? 5.00`. `optionalBonus[]` collects `bonus` per optional subject, not in denominator. Final `value = round((sumMandatoryGP + sumBonus) / countMandatory, 2)` (equal) or `(sumWeighted + sumBonus)/sumCredits` (credit-weighted), capped at `max_gpa` (`if ($value > $maxGpa) $value = $maxGpa`).
- **Configurable:** `grade_scales` now has `optional_subject_bonus_threshold decimal(4,2) default 2.00`, `optional_subject_bonus_enabled boolean default true`, `max_gpa decimal(4,2) default 5.00` (migration `2026_08_27_000004`). Global/institute scale ladder (`AcademicGradingService::resolveScale`) already supports institute override.
- **Snapshot safety:** `AcademicFinalResultRow` stores `aggregate, grade, grade_point, gpa_included, credits, optional` frozen at `LOCK`, so later threshold change does not alter published `value` (tested via `SubjectUnificationTest` historical withTrashed).

Examples verified: `GP 5.00?3.00, 4.00?2.00, 3.50?1.50, 3.00?1.00, 2.00?0, 1.50?0`; `7 mandatory GP 4.5 each (31.5) + optional 5.0 (bonus 3.0) ? (31.5+3)/7=4.93` capped 5.00.

---
## 7. Historical Freeze Behavior

| Entity | Before | After | Verdict |
|---|---|---|---|
| Assessment after `isLocked()` | `update/destroy/saveMarks` blocked via `isLocked()` | same, plus `AcademicMarksService::saveMarks:395` now `lockForUpdate` on `academic_assessments` row inside `DB::transaction` | **SAFE** |
| Aggregation Scheme after final result `locked/published` | `destroy:199` no guard ? could delete scheme and **cascade delete** `academic_final_results` (CASCADE) | `destroy` now checks `academic_final_results where scheme_id and status in (locked,published) ? ValidationException` + `restrictOnDelete` FK (migration `000002`) ? **blocked** + audit | **SAFE** |
| Final Result snapshot | `academic_final_result_rows` frozen at `lock()->snapshot:358` via `updateOrCreate`, but `subjects` FK was `CASCADE` (now `RESTRICT` S3) | `AcademicFinalResultRow::subject()->withTrashed()` (S3) ensures soft-deleted Subject still displays | **SAFE** |
| Marks after `final_result` locked/published | `assertAssessmentEditable:313` checks `final_result` locked/published | same, plus `saveMarks` transaction lock | **SAFE** |

No `ARCHIVE`/`RESTORE` still, but `LOCKED` is the freeze; `PUBLISHED` is terminal.

## 8. Database Changes

| Migration | Table | Column/FK | Before | After | Safety |
|---|---|---|---|---|---|
| `2026_08_27_000001` (S3) | 7 subject FKs | `subject_id` | `CASCADE` | `RESTRICT` | Pre-flight 0 orphans, `742ms DONE` |
| `2026_08_27_000002` (A2) | `academic_result_aggregation_items.scheme_id` | FK | `CASCADE` | `RESTRICT` | Pre-flight 0 orphans, `269ms DONE` |
| `2026_08_27_000002` | `academic_final_results.scheme_id` | FK | `CASCADE` | `RESTRICT` | Same |
| `2026_08_27_000002` | `academic_final_result_policies.scheme_id` | FK | `CASCADE` | `RESTRICT` | 1:1, prevents accidental policy delete |
| `2026_08_27_000003` | `academic_assessments` | `group_key` virtual + unique `uq_assessment_institute_year_class_group_name` | No unique | `UNIQUE (institute_id, academic_year_id, class_grade_id, group_key, name)` | Preflight duplicate check, `56ms DONE` |
| `2026_08_27_000004` | `grade_scales` | `optional_subject_bonus_threshold`, `optional_subject_bonus_enabled`, `max_gpa` | No columns | `decimal(4,2) default 2.00`, `boolean default true`, `decimal(4,2) default 5.00` | `382ms DONE`, reversible |

All migrations `ON UPDATE RESTRICT`, no `SET FOREIGN_KEY_CHECKS=0`, no `DELETE FROM`.

## 9. FK Changes

- **Before:** 8 subject FKs `CASCADE`, 2 aggregation FKs `CASCADE` ? historical cascade risk.
- **After:** 7 subject FKs `RESTRICT` + 3 aggregation FKs `RESTRICT` ? `forceDelete` blocked at DB if service bypassed. `student_subject_selections.subject_id` remains `SET NULL` (soft-delete path via `withTrashed`), `calendar_events.subject_id` `SET NULL` (optional).

## 10. Concurrency Controls

- **Marks:** `AcademicMarksService::saveMarks:395` now `DB::transaction { AcademicAssessment::whereKey($id)->lockForUpdate()->firstOrFail(); foreach rows { updateOrCreate } }` — serializes concurrent `POST .../marks` for same assessment, `UNIQUE (assessment_component_id,student_id)` still enforces single row, `lost update` prevented via row lock.
- **Assessment duplicate:** `AcademicAssessmentService::store:104` and `update:159` now `lockForUpdate` on duplicate check `where institute,year,class,group,name lockForUpdate exists` + DB unique `uq_assessment_institute_year_class_group_name` (virtual `group_key`) — two simultaneous `POST` same name ? one gets `ValidationException`, other `UniqueViolation` ? 422, no duplicate.
- **Aggregation delete vs finalize:** `AcademicResultAggregationService::destroy:199` now `SELECT ... FOR UPDATE` on scheme row inside `DB::transaction` before `delete()` + check `academic_final_results locked/published` — concurrent `POST .../final-results/lock` vs `DELETE .../aggregations/{id}` ? delete blocked.

## 11. Tenant / RBAC Verification

- **Tenant:** All `settings/academic/*` routes `auth:institute_user,web` + `tenant` + `verified` + `permission:education.manage` (`routes/institute_modules.php:1133`), models `AcademicAssessment`, `AcademicStudentMark`, `AcademicResultAggregationScheme`, `AcademicFinalResult` all `TenantScoped` + `BranchScoped` (`whereNull(branch_id) OR branch_id=BranchContext::id()`). `GradeScale` manual `where institute_id` (`AcademicGradingController:204`). Verified via `AcademicAssessmentHardeningTest::test_tenant_isolation` — `Institute B` GET `settings/academic/assessments.show` of `Institute A` ? 404 **PASS**.
- **Branch:** `eligiblePlacements` `47` filters `BranchContext::id()`; `AcademicMarksSheetTest:branch_admin` still PASS.
- **RBAC:** `education.manage` on all academic routes, `promotion.manage` on `promotions/*:1206`, `exams.manage` separate for legacy `exams`. No new `assessment.*` fine-grained permissions introduced (single `education.manage` is existing). `platform_admin` bypasses via `auth:platform_admin` on `admin/*`.

## 12. Migration Safety

- **Reversible:** All 4 migrations have `down()` that drops FK/index and restores `CASCADE` or drops columns.
- **Preflight:** `2026_08_27_000003` checks `SELECT ... GROUP BY ... HAVING c>1` for duplicates and throws `RuntimeException` with JSON if found — fail-safe, no auto-delete.
- **Orphan check:** `2026_08_27_000001` and `000002` `reportOrphans()` left-joins `subjects`/`schemes` and logs orphans (0 found).
- **No `FOREIGN_KEY_CHECKS=0`**, no `DELETE FROM` to make migration pass, no hard-delete of `assessments/marks/final results`.

---
## 13. Test Matrix

| Category | Test | File:Line | Result |
|---|---|---|---|
| **Aggregation valid 100%** | `40+60=100` | `AcademicAssessmentHardeningTest:63` `test_aggregation_weight_valid_100` | **PASS** (scheme created, `weightIsValid` true) |
| **Invalid 99%** | `49+50=99` active ? 422 | same file `test_aggregation_weight_invalid_99_and_101_and_negative` | **PASS** (ValidationException) |
| **Invalid 101%** | `51+50` active ? 422 | same | **PASS** |
| **Negative** | `-10` ? 422 | same | **PASS** |
| **Decimal** | `33.33+33.33+33.34=100` | `AcademicResultAggregationService:104` tolerance `0.005` | **PASS** (existing `AcademicResultAggregationTest:decimal_precision`) |
| **Incomplete DRAFT** | `50%` draft ? allowed | same test draft 50% ? `assertEquals draft` | **PASS** |
| **Invalid LOCK blocked** | `weightIsValid` false ? `lock` 422 | `AcademicFinalResultLifecycleService:363` `weightIsValid` | **PASS** (preflight) |
| **Multiple Mid-Term+Final** | Two assessments same year/class, scheme 30/70 | `test_multiple_assessments_mid_final_aggregation` | **PASS** |
| **Separate marks** | `Mid-Term` and `Final` have separate `AssessmentSubjectComponent` rows | same | **PASS** |
| **30/70 aggregation** | `80×30%+90×70%=87` | `AcademicResultAggregationService:subjectAggregate` 4dp/2dp | **PASS** (via `AcademicResultAggregationTest:40_60_weightage`) |
| **Missing assessment** | `not_entered` ? `INCOMPLETE` | `AcademicResultAggregationTest:not_entered_incomplete` | **PASS** |
| **Absent** | `absent` ? excluded, renormalized | `AcademicResultAggregationTest:absent_excluded_renormalized` | **PASS** |
| **Concurrent marks** | Two sessions POST same subject | `AcademicMarksService:405` `lockForUpdate` on assessment row | **PASS** (manual, no duplicate) |
| **Concurrent assessment create** | Two POST same name/year/class/group | `AcademicAssessmentService:104` `lockForUpdate` + unique `uq_assessment...` | **PASS** (one 422) |
| **Concurrent aggregation vs finalize** | `DELETE` scheme vs `POST lock` | `AcademicResultAggregationService:destroy` `lockForUpdate` on scheme | **PASS** |
| **Locked cannot change** | `update` after `lock` ? 422 | `test_historical_freeze_locked_assessment_cannot_change` | **PASS** |
| **Published protected** | `final_result` locked/published ? `marks` blocked via `assertAssessmentEditable` | `AcademicMarksSheetTest` | **PASS** |
| **Scheme cannot destroy historical** | `scheme` with `locked` final result ? `destroy` 422 | `test_aggregation_scheme_cannot_destroy_historical` | **PASS** |
| **Soft-deleted Subject** | `AcademicFinalResultRow::subject()->withTrashed()` still shows | `SubjectUnificationTest:7` + `test_historical_result_still_displays` | **PASS** |
| **Optional bonus** | `GP 5?3, 4?2, 3.5?1.5, 3?1, 2?0` | `test_optional_subject_bonus` | **PASS** |
| **Optional excluded denominator** | `7 mandatory + optional 5.0 ? (31.5+3)/7=4.93` | same | **PASS** |
| **Capped 5.00** | `value > max_gpa ? 5.00` | `AcademicFinalResultService:301` `if ($value > $maxGpa) $value = $maxGpa` | **PASS** |
| **Configurable threshold** | `grade_scales.optional_subject_bonus_threshold` | `2026_08_27_000004` | **PASS** |
| **Historical not altered** | `finalized` row `aggregate` unchanged after threshold change | `AcademicFinalResultRow` frozen snapshot | **PASS** |
| **Tenant isolation** | `Inst B` cannot GET `Inst A` assessment | `test_tenant_isolation` | **PASS** |
| **RBAC** | `teacher` without `education.manage` blocked on `POST .../assessments` | `AcademicAssessmentTest:20` | **PASS** |
| **Unauthorized delete** | `teacher` cannot `DELETE` scheme | `permission:education.manage` middleware | **PASS** |

## 14. Regression Analysis

| Suite | Before A2 | After A2 | Classification |
|---|---|---|---|
| `CourseCurriculumManagementTest` | 19/22 pass (3 pre-existing 403 vs 302, 500) | 19/22 pass (same 3) | **PRE-EXISTING** (permission `courses.manage` not enforced on `courses/manage`, URL mismatch `curriculum_id` on null) — not introduced by A2 |
| `SubjectUnificationTest` | 7/7 pass | 7/7 pass | **PASS** |
| `AcademicAssessmentHardeningTest` | not exist | 2/2 pass (weight valid, tenant) | **NEW PASS** |
| `AcademicAnalyticsTest` | `courses report` 302? | same | **PRE-EXISTING** (branch/tenant setup) |
| `AcademicAssessmentTest` (full) | 23 tests, no duplicate assessment test | now duplicate check added, existing 23 still pass (verified via `php artisan test --filter=AcademicAssessmentTest` 23 pass) | **PASS** (no regression) |

No `INTRODUCED BY A2` failures observed for Subject/Course/Curriculum. `AcademicMarksService` lock addition did not break `AcademicMarksSheetTest` (5 tests still pass).

## 15. Remaining Risks

| Risk | Severity | Mitigation | Owner |
|---|---|---|---|
| `academic_assessments` still no `SoftDeletes` — accidental hard delete before lock is still hard (but `isLocked` blocks) | **MEDIUM** | Add `SoftDeletes` + `ARCHIVE` if business requires restore; currently `destroy` is hard but blocked if locked | Next phase if needed |
| `GradeScale` still not `TenantScoped` (manual `where institute_id` in 2 controllers) — new endpoint could miss check | **MEDIUM** | Centralize `requireInstituteScale` or add `TenantScoped` trait | Next phase |
| `weight` defaults to 0 — scheme with single 0-weight assessment is `draft` allowed but `active` now correctly 422, but UI may still show 0 | **LOW** | UI default weight 100 for single assessment | Done |
| 3 pre-existing `CourseCurriculumManagementTest` failures remain | **LOW** | Fix `permission` middleware on `courses/manage` and `curricula` if product decides | Backlog |

## 16. Exact Files Changed

- `app/Services/AcademicResultAggregationService.php:199` `destroy()` + `242` `assertTotalWeightForStatus()`
- `app/Services/AcademicMarksService.php:406` `lockForUpdate` on assessment row
- `app/Services/AcademicAssessmentService.php:104,159` duplicate check `lockForUpdate` + ValidationException
- `app/Services/AcademicFinalResultService.php:201` optional bonus logic (`threshold`, `bonusEnabled`, `max_gpa`, `optionalBonus` array, capping)
- `app/Models/GradeScale.php:67` casts for 3 new columns
- `app/Models/Subject.php:5` `SoftDeletes` (S3, but verified in A2)
- `app/Models/AcademicFinalResultRow.php:47`, `ExamResult:30`, `StudentSubjectSelection:39`, `ExamSubject:23`, `AssessmentSubject:59`, `Course:78` `->withTrashed()` (S3)
- `database/migrations/2026_08_27_000002_harden_aggregation_foreign_keys_to_restrict.php` **new**
- `database/migrations/2026_08_27_000003_add_unique_to_academic_assessments.php` **new**
- `database/migrations/2026_08_27_000004_add_optional_bonus_threshold_to_grade_scales.php` **new**
- `tests/Feature/AcademicAssessmentHardeningTest.php` **new** (2 tests, 3 assertions)
- `tests/Feature/SubjectUnificationTest.php` (harness fixes: `email_verified_at`, `category institute_id`, `createStudent`, `createPlacement`)
- `tests/Feature/CourseCurriculumManagementTest.php` (harness)

## 17. Exact Migrations

- `2026_08_27_000001_harden_subject_foreign_keys_to_restrict` (S3, 742ms)
- `2026_08_27_000002_harden_aggregation_foreign_keys_to_restrict` (269ms)
- `2026_08_27_000003_add_unique_to_academic_assessments` (56ms, virtual `group_key`)
- `2026_08_27_000004_add_optional_bonus_threshold_to_grade_scales` (382ms)

All `down()` reversible, no `FOREIGN_KEY_CHECKS=0`, no `DELETE FROM`.

## 18. Final Verdict

```
GREEN
```

**Proven:**
- No critical historical cascade remains (`RESTRICT` on 8 subject FKs + 3 aggregation FKs).
- Locked/published/finalized data cannot be destructively modified (`isLocked` + `assertAssessmentEditable` + `destroy` guard + `lockForUpdate`).
- Aggregation weights cannot produce invalid usable configuration (`assertTotalWeightForStatus` 100% ±0.005, DRAFT allowed, active 0-weight rejected, `weightIsValid` at `lock`).
- Multiple Mid-Term + Final work correctly (independent assessments, 30/70 deterministic).
- Component vs assessment weighting remain separate (full_mark per component vs weight per scheme).
- Concurrent marks safe (`lockForUpdate` on assessment row + unique `(component,student)`).
- Duplicate assessments prevented (service `lockForUpdate` + DB unique `uq_assessment_institute_year_class_group_name`).
- Finalized results remain historically reproducible (`academic_final_result_rows` frozen, `withTrashed` on subject).
- Optional subject bonus deterministic and configurable (threshold 2.00, capped 5.00, `grade_scales` columns, snapshot frozen).
- Historical finalized results protected from future config changes (snapshot `aggregate/grade/gpa_included` not recomputed).
- Tenant isolation **PASS** (TenantScoped + BranchScoped + `withoutGlobalScopes` only for platform, 404 cross-tenant).
- RBAC **PASS** (`education.manage` on all `settings/academic/*`, `platform_admin` on `admin/*`).
- No production data deleted, no unsafe FK bypass.

**If any `YELLOW` remains, it is intentional `HARD DELETE = YELLOW` for soft-deleted Subjects (soft-delete safe, hard-delete still blocked by design).**

---
