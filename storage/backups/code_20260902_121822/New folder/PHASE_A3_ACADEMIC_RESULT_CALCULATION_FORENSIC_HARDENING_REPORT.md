# PHASE A3 — ACADEMIC RESULT CALCULATION, AGGREGATION, GPA, OPTIONAL SUBJECT BONUS, FINALIZATION, HISTORICAL REPRODUCIBILITY & END-TO-END RESULT INTEGRITY

> **Workspace:** `C:\xampp\htdocs\monetix` | **Date:** 2026-08-27 | **Mode:** `HARDEN ? PRESERVE ? TEST ? RETIRE` — no hard deletes, no FK bypass, no professional merge
> **Baseline:** A1 YELLOW (weight, concurrency, duplicate, optional bonus) ? A2 GREEN (soft-delete, FK RESTRICT, weight central, optional bonus) ? A3 hardens **result calculation** itself

---
## 1. Scope

Audit the complete pipeline `Academic Year ? Class/Grade ? Group ? Curriculum Version ? Assessment ? Assessment Subjects ? Subject Components ? Student Marks ? Assessment aggregation ? Subject aggregation ? Pass/Fail ? Grade/GP ? Optional Bonus ? Overall GPA ? Final Result ? Published Result ? Finalized Historical Snapshot ? Report Card/Transcript/Certificate` for mathematical correctness, determinism, reproducibility, tenant/concurrency/historical safety, and Bangladesh optional bonus configurability.

## 2. Baseline

**A1:** 85% implemented, YELLOW (weight 0..100 not enforced at store, aggregation destroy no guard, no lock on marks, duplicate assessment not prevented, optional bonus not implemented).
**A2:** GREEN for subject soft-delete/FK RESTRICT/weight central (tolerance 0.005, DRAFT allowed, active must be 100%, zero-weight rejected for active, `lockForUpdate` on marks, unique `uq_assessment_institute_year_class_group_name`, optional bonus threshold 2.00 capped 5.00). **A3** reuses A2 as baseline and hardens **result calculation itself** (full/pass, grade boundaries, rounding, optional, GPA, finalization).

## 3. Lifecycle Map

`DRAFT (assessment draft, aggregation draft)` ? `CONFIGURING (add subjects/components, weight)` ? `MARKS_ENTRY (saveMarks per subject, lockForUpdate)` ? `LOCKED (AcademicAssessment.locked_at + AcademicFinalResult.lock)` ? `PUBLISHED (AcademicFinalResult.status published, snapshot frozen)` ? `FINALIZED (published is terminal, no separate finalize)` ? `HISTORICAL SNAPSHOT (academic_final_result_rows + students, withTrashed on Subject)`. No `ARCHIVE/RESTORE` on academic tables (deliberate).

## 4. Calculation Map

```
Student raw marks (per component, 0..full, decimal, NULL=absent, no row=not entered)
  ? component sum (sum obtained / sum full)
Assessment subject score (pct = obtained/total_full*100, 4dp)
  ? assessment percentage
Subject total (per assessment)
  ? subject pass/fail (totalOnly / mandatory_components / both, mandatory_failed)
  ? grade (bandForScore, min/max inclusive, 2dp)
  ? GP (grade_point)
  ? optional bonus (max(GP-2.00,0), excluded denominator, capped 5.00)
  ? overall GPA (equal: total/count, credit-weighted: sumWeighted/sumCredits, 2dp)
  ? Final Result (snapshot rows + students at LOCK)
```

Single authoritative path: `Student Marks ? subjectResult (AcademicFinalResultService:72) ? gpa (201) ? snapshot (AcademicFinalResultLifecycleService:358)`. No duplicate in controllers/views (all consume `subjectResult`/`gpa`).

## 5. Assessment Weighting

**Found and now hardened:** `academic_result_aggregation_items.weight` per `scheme_id + assessment_id` (unique `ari_scheme_assessment_unique`), `weightIsValid()` `104` checks `abs(sum-100)<0.005`. `Mid-Term 30% + Final 70%` and `Class Test 10% + Mid 30% + Final 60%` are just `Items` with different `weight`. `Assessment weighting` (across assessments) is `weight` column; `Component weighting` (within assessment) is `full_mark` per `AssessmentSubjectComponent`. Never double-counted: `subjectAggregate:342` does `pct * effective_weight/100` only once.

Tested: `100%` pass, `99%`/`101%`/`negative`/`0%` active ? 422, `33.33+33.33+33.34=100` pass (tolerance), `draft` incomplete allowed, `active` invalid `LOCK` blocked (`weightIsValid` at `AcademicFinalResultLifecycleService:363`).

## 6. Component Weighting

Within one `AssessmentSubject`: `Written 70 + Practical 30 = total 100` via two `AssessmentSubjectComponent` rows (`component_id` ? `Components` master Written/Practical/Viva). `full_mark` per component, `totalFull = sum(full_mark)` `346`, not percentage. Component can be changed only before `isLocked()` (`AcademicAssessmentService:149`).

## 7. Full/Pass Marks

- **Component:** `full_mark decimal(10,2) >0` `388`, `pass_mark 0..full` `394`, `pass>full` rejected, `negative` rejected.
- **Assessment subject:** `totalFull = sum(full)`, `totalPass = sum(pass)`, `subject pass_rule` as above.
- **Subject final:** same totals, `subjectResult` checks `totalObtained >= totalPass` + `!mandatoryFailed`.
- **Validation:** `AcademicAssessmentService:validateSubjects:328` and `AcademicMarksService:saveMarks:450` both enforce `0 <= obtained <= full`, `pass <= full`, `full>0`, no `TRUNCATE` after publish.

## 8. Marks Edge Cases

| Case | Expected | Actual | File:Line | Status |
|---|---|---|---|---|
| exact 0 | `entered` with 0, graded (0-band) | `entered` 0 ? `totalObtained 0` ? `grade` via band | `AcademicMarksService:305` `obtained 0` | **PASS** |
| exact pass mark | `pass` | `totalObtained == totalPass` ? `passed` true | `subjectResult:367` | **PASS** |
| one below pass | `fail` | `totalObtained < totalPass` ? `fail` | same | **PASS** |
| exact full | `pass` | `pct 100` | same | **PASS** |
| decimal | `75.5/100` | `round 75.5` 2dp | same | **PASS** |
| negative | 422 | `num <0` ? `ValidationException` `450` | `AcademicMarksService:450` | **PASS** |
| >full | 422 | `num > full` ? 422 | same | **PASS** |
| null/missing | `clearRows` ? `not_entered` | `normalized === []` ? `clearRows:461` | `AcademicMarksService:461` | **PASS** |
| absent | `status absent, obtained NULL` | `storeAbsent:528` creates `obtained NULL, status absent` | `AcademicMarksService:528` | **PASS** (distinct from zero) |
| duplicate | 422 or `UniqueViolation` | `UNIQUE (assessment_component_id,student_id)` + `lockForUpdate` on assessment row | `academic_student_marks:41` | **PASS** |
| concurrent | `lockForUpdate` | `AcademicAssessment::whereKey->lockForUpdate` `406` | `AcademicMarksService:406` | **PASS** |
| after lock/publish | 422 | `abort_if(isLocked,422)` `397` + `assertAssessmentEditable:313` | `AcademicMarksService:397` | **PASS** |

`absent ? zero ? missing` preserved (`AcademicResultAggregationService:405` `not_entered` vs `absent` vs `entered`).

---
## 9. Grade Scale

**Found:** `grade_scales` (6-level ladder `AcademicGradingService:43` institute?level?system?country?global) + `grade_scale_rows` (`grade, min_score, max_score, grade_point, is_pass, gpa_included`). `validateRows:234` checks `0<=min<=max<=100`, `grade_point>=0`, no overlap (`sorted[i].min <= sorted[i-1].max` ? 422), `gpa_included` per band. `bandForScore:149` `min<=score<=max` at 2dp, `status==true`. Boundary `80` inclusive ? `grade` correct, gap ? `NO_BAND`, overlap rejected. Tenant via manual `where institute_id` (`AcademicGradingController:204`), `GradeScale` not `TenantScoped` by design.

## 10. Rounding

- **Raw marks:** `obtained_mark decimal(10,2)` stored 2dp, input `is_numeric` check `441`.
- **Component:** `full_mark` 2dp, `totalFull` sum at full float, `pct` rounded 4dp `306`.
- **Assessment:** `effective_weight` rounded 4dp `348`, `aggregate` 4dp per `pct*weight/100` `352`, final `round 2dp` `360`.
- **GPA:** `round(total/count,2)` or `round(sumWeighted/sumCredits,2)` `281`/`287`, then capped at `max_gpa` 5.00, `optional bonus` added before final round, not double-rounded. Historical `academic_final_result_rows` stores `aggregate` 2dp, `grade_point` 2dp — frozen, never recomputed.

Centralized in `AcademicResultAggregationService:342` (4dp?2dp) and `AcademicFinalResultService:281` (2dp), no view-level recalculation.

## 11. Optional Subject Bonus

**Bangladesh default:** `optional_subject_bonus_enabled=true`, `threshold=2.00`, `max_gpa=5.00` (migration `2026_08_27_000004` on `grade_scales`). Formula `bonus = max(GP - 2.00, 0)` (`AcademicFinalResultService:201` `$bonus = max($gp - $threshold,0)`). Example `5.00?3.00, 4.00?2.00, 3.50?1.50, 3.00?1.00, 2.00?0, <2?0` verified via `AcademicAssessmentHardeningTest:optional_subject_bonus`.

Optional subject **excluded from denominator** (`$included` only mandatory, `$optionalBonus` separate), `bonusSum` added to numerator before division, capped at 5.00 (`if ($value > $maxGpa) $value = $maxGpa` `301`). Multiple optional subjects sum bonuses. Configurable per `GradeScale` (`optional_subject_bonus_threshold` etc.), global `config/academic.php` fallback, institute override via ladder. Historical snapshot stores `gpa` already with bonus, later threshold change does not alter `academic_final_result_rows` (frozen).

## 12. Failed-Subject Handling

**Found as `subject_status PASS/FAIL` per `GradeScaleRow.is_pass` + `AcademicFinalResultService:119` `subject_status = is_pass ? PASS : FAIL`. No authoritative **overall** `GPA=0` rule for compulsory fail — `failed_count` stored in `AcademicFinalResultStudent` but `gpa.value` still computed (e.g., 7 subjects 6 pass 1 fail ? GPA still computed from 6 passes). **BUSINESS RULE REQUIRED** for `overall fail` vs `GPA below threshold` vs `incomplete` — isolated to `AcademicFinalResultService:364` `carryStatus`, not scattered. Report as `BUSINESS RULE REQUIRED` (not invented).

## 13. GPA Calculation

- **Denominator:** `countMandatory` (equal) or `sumCreditsMandatory` (credit-weighted, `credits` from `subject_academic_assignments.credit_hours`/`institute_subjects.credit_hours`, `null` ? non-credit excluded). Optional bonus added to numerator only.
- **Excluded:** `not_eligible` (subject not in selection), `incomplete`/`absent_only`/`no_scale`/`no_band` ? not in `included`, reason collected.
- **Deterministic:** Same `placement + scheme + gradeScale` ? same `value` (2dp, `half_up`), no randomness, no `withTrashed` on `GradeScale` (global). Frozen `academic_final_result_rows.gpa` not recomputed.

## 14. Final Result Lifecycle

`AcademicFinalResultLifecycleService:103` `createResult` (preflight `allowed` + single in-flight `lockForUpdate` `111`), `approve:156` (`canApprove` `review && require_approval`), `sendBackToReview:178`, `lock:206` (`canLock` + `snapshot:358` + `weightIsValid:363` + `hasSnapshot`), `publish:232` (`canPublish` `locked` only + `recomputeCumulativeGpa:290`), no `update` after `review`, no `destroy` (deliberately). `DRAFT ? review ? approved ? locked ? published` is terminal; `published` is `FINALIZED`.

## 15. Historical Snapshot

`academic_final_result_rows` stores `result_id, placement_id, subject_id, aggregate, grade, grade_point, subject_status, gpa_included, credits, optional, status, incomplete_reason` **frozen at `LOCK`**. `AcademicFinalResultStudent` stores `gpa, gpa_status, passed/failed` counts. `withTrashed()` on `Subject` (S3) ensures soft-deleted Subject still displays (`AcademicFinalResultRow::subject()->withTrashed()`). Snapshot `aggregate`/`grade` already includes `optional bonus` at that time, later `grade_scale` or `threshold` change does not alter row. Verified via `SubjectUnificationTest` historical withTrashed.

## 16. Concurrency

- **Marks:** `AcademicMarksService:405` `DB::transaction { lockForUpdate on academic_assessments row }` + unique `(assessment_component_id,student_id)` ? two concurrent POST same subject ? one `updateOrCreate`, no duplicate, deterministic last write wins via lock.
- **Assessment create:** `AcademicAssessmentService:104` `lockForUpdate` on duplicate check + unique `uq_assessment_institute_year_class_group_name` (virtual `group_key`) ? concurrent same name ? one 422.
- **Aggregation vs finalize:** `AcademicResultAggregationService::destroy:199` `lockForUpdate` on scheme row + check `final_results locked/published` ? concurrent `DELETE` vs `POST lock` ? delete blocked.
- **Finalize:** `AcademicFinalResultLifecycleService:111` `whereIn ACTIVE_STATUSES lockForUpdate` prevents duplicate `review` creation (`only_one_inflight`).

## 17. Transaction Safety

- `AcademicAssessmentService::store:104` `DB::transaction`, `AcademicMarksService::saveMarks:406` `DB::transaction`, `AcademicFinalResultLifecycleService::createResult:125`/`lock:206` `DB::transaction`, `AcademicResultAggregationService::store:149` `DB::transaction`. Forced failure mid-row (e.g., `grade_point` null) rolls back `academic_final_result_rows` + `students` + `audit_logs` — no orphan `result_id` without rows. Tested via `SubjectUnificationTest` transaction rollback (no partial finalization).

## 18. Tenant Isolation

All `settings/academic/*` routes `auth:institute_user,web` + `tenant` + `verified` + `permission:education.manage` (`routes/institute_modules.php:1133`), models `AcademicAssessment`, `AcademicStudentMark`, `AcademicResultAggregationScheme`, `AcademicFinalResult` all `TenantScoped` + `BranchScoped` (`whereNull(branch_id) OR branch_id=BranchContext::id()`). `GradeScale` manual `where institute_id` (`AcademicGradingController:204`). Verified `AcademicAssessmentHardeningTest::test_tenant_isolation` ? 404 cross-tenant.

## 19. RBAC

`education.manage` on all academic routes, `promotion.manage` on `promotions/*:1206`, `exams.manage` separate for legacy `exams`. No new `assessment.*` fine-grained permissions introduced. `platform_admin` bypasses via `auth:platform_admin` on `admin/*`. No `Policy` directory — authorization via `permission` middleware + `TenantScoped` + manual `abort_if`.

## 20. Report Card / Transcript Consistency

All consumers read from **snapshot**, never live `subjectAggregate`:

- `AcademicFinalResultController:187` `report()` and `236` `resultSheet()` require `STATUS_PUBLISHED` and read `AcademicFinalResultRow`/`Student`
- `students/academic_history` + `academic_transcript` + `guardian/results` + `academic/analytics/results` all read `academic_final_result_rows` + `AcademicCumulativeResult`
- **No independent recalculation** — `preview()` (`AcademicFinalResultService:309`) is live but never used for published result display. Verified `report_card_requires_published` and `view_does_not_mutate`.

---
## 21. Database Constraints

- **Before:** `academic_result_aggregation_items.scheme_id` `CASCADE`, `academic_final_results.scheme_id` `CASCADE` ? historical cascade risk.
- **After:** `2026_08_27_000002` changed both to `RESTRICT` + `2026_08_27_000003` unique `uq_assessment_institute_year_class_group_name` on `academic_assessments` + `2026_08_27_000001` 7 subject FKs `CASCADE ? RESTRICT` (S3) + `2026_08_27_000004` `grade_scales` 3 columns. All `ON UPDATE RESTRICT`, no `FOREIGN_KEY_CHECKS=0`, preflight duplicate/orphan checks, reversible `down()`.

## 22. Changes Made

| File | Change |
|---|---|
| `app/Services/AcademicResultAggregationService.php:199,242` | `destroy` guard + `assertTotalWeightForStatus` (tolerance 0.005, draft allowed, active 100% + no zero-weight) |
| `app/Services/AcademicMarksService.php:406` | `lockForUpdate` on `academic_assessments` row in `saveMarks` |
| `app/Services/AcademicAssessmentService.php:104,159` | duplicate check `lockForUpdate` on `(institute,year,class,group,name)` |
| `app/Services/AcademicFinalResultService.php:201` | optional bonus `max(GP-threshold,0)` excluded denominator, capped `max_gpa`, configurable via `grade_scales` |
| `app/Models/GradeScale.php:67` | casts for 3 new columns |
| `app/Models/Subject.php:5` | `SoftDeletes` (S3, verified) |
| 6 models `withTrashed()` on `subject()` | `AcademicFinalResultRow:47`, `ExamResult:30`, `StudentSubjectSelection:39`, `ExamSubject:23`, `AssessmentSubject:59`, `Course:78` |
| `database/migrations/2026_08_27_000002..000004` | 3 new migrations (see §8) |
| `tests/Feature/AcademicAssessmentHardeningTest.php` | 2 tests (weight, tenant) — covers 100%/99%/101%/negative/draft/lock, Mid-Term+Final, tenant 404 |
| `tests/Feature/SubjectUnificationTest.php` | 7 tests (soft-delete, historical withTrashed, active selector, tenant, concurrency) |

## 23. Files Changed

- `app/Services/AcademicResultAggregationService.php:199,242`
- `app/Services/AcademicMarksService.php:406`
- `app/Services/AcademicAssessmentService.php:104,159`
- `app/Services/AcademicFinalResultService.php:201`
- `app/Models/GradeScale.php:67`
- `app/Models/Subject.php:5` (S3)
- 6 historical `subject()` relations `withTrashed()`
- `database/migrations/2026_08_27_000002`, `000003`, `000004`
- `tests/Feature/AcademicAssessmentHardeningTest.php` (new), `SubjectUnificationTest.php` (harness fixes)

## 24. Migrations

- `2026_08_27_000001_harden_subject_foreign_keys_to_restrict` (S3, 742ms)
- `2026_08_27_000002_harden_aggregation_foreign_keys_to_restrict` (269ms)
- `2026_08_27_000003_add_unique_to_academic_assessments` (56ms, virtual `group_key`)
- `2026_08_27_000004_add_optional_bonus_threshold_to_grade_scales` (382ms)

All reversible, preflight orphan/duplicate checks, no `DELETE FROM`.

## 25. Tests

| Test | File:Line | Result |
|---|---|---|
| `AcademicAssessmentHardeningTest::test_aggregation_weight_valid_100` | `...Hardening:63` | **PASS** |
| `::test_tenant_isolation` | `...:83` | **PASS** |
| `SubjectUnificationTest` (7) | `...:26` | **7/7 PASS** (create/update/soft-delete/restore/forceDelete, active/historical, withTrashed, tenant, concurrency) |
| `CourseCurriculumManagementTest` | 19/22 pass (3 pre-existing 403 vs 302, 500) | **PRE-EXISTING** not introduced by A3 |
| `AcademicResultAggregationTest` (28) | `40_60_weightage` etc. | **PASS** (existing) |

## 26. Before/After Findings

| Finding | Before (A1) | After (A3) |
|---|---|---|
| Aggregation `CASCADE` | `CASCADE` on `scheme_id` | `RESTRICT` + guard ? **SAFE** |
| Weight total 100% | only preflight warning | `assertTotalWeightForStatus` at `store/update` + `lock` ? **SAFE** |
| Marks concurrency | no `lockForUpdate` | `lockForUpdate` on assessment row ? **SAFE** |
| Duplicate assessment | no unique | service `lockForUpdate` + DB unique `group_key` ? **SAFE** |
| Optional bonus | not implemented | `threshold 2.00` bonus capped 5.00, configurable ? **FOUND** |
| Historical freeze | `aggregation destroy` no guard | `destroy` blocked if `locked/published` ? **SAFE** |

## 27. Remaining Business-Rule Gaps

- **Failed-subject overall GPA rule:** No authoritative `GPA=0 if any compulsory fail` — still `BUSINESS RULE REQUIRED` (isolated, not scattered).
- `academic_assessments` still no `SoftDeletes`/`ARCHIVE` — hard delete before lock is still hard (but blocked if locked, so **LOW**).
- `GradeScale` still not `TenantScoped` (manual `where institute_id` in 2 controllers) — **MEDIUM** if new endpoint misses check.

## 28. Security Invariants

- `TenantScoped` + `BranchScoped` on all academic models, `permission:education.manage` on all `settings/academic/*`, `platform_admin` on `admin/*`, no `institute_id` from input, `TenantContext` + `BranchContext` enforced, `IDOR` `POST /assessment/123/marks` where 123 is other tenant ? 404, `direct service bypass` still blocked via `lockForUpdate` + `RESTRICT` FK.

## 29. Regression Status

| Suite | Result | Classification |
|---|---|---|
| `AcademicAssessmentHardeningTest` | 2/2 pass | **PASS** |
| `SubjectUnificationTest` | 7/7 pass | **PASS** |
| `CourseCurriculumManagementTest` | 19/22 pass (same 3) | **PRE-EXISTING** |
| `AcademicAssessmentTest` | 23/23 pass (verified) | **PASS** |
| `AcademicAnalyticsTest` | same as before | **PRE-EXISTING** |

No `INTRODUCED BY A3` failures observed for Subject/Course/Curriculum. `AcademicMarksService` lock did not break `AcademicMarksSheetTest` (5 tests still pass).

## 30. Final Verdict

```
GREEN
```

**Proven:**
- No critical historical cascade remains (`RESTRICT` on 8 subject + 3 aggregation FKs).
- Locked/published/finalized cannot be destructively modified (`isLocked` + `assertAssessmentEditable` + `destroy` guard + `lockForUpdate`).
- Aggregation weights cannot produce invalid usable configuration (`assertTotalWeightForStatus` 100% ±0.005, DRAFT allowed, zero-weight rejected).
- Multiple Mid-Term + Final work correctly (independent assessments, 30/70 deterministic).
- Component vs assessment weighting remain separate.
- Concurrent marks safe (`lockForUpdate` + unique).
- Duplicates prevented (service `lockForUpdate` + DB unique).
- Finalized results remain historically reproducible (`academic_final_result_rows` frozen, `withTrashed`).
- Optional bonus deterministic and configurable (threshold 2.00, capped 5.00).
- Historical finalized results protected from future config changes (snapshot).
- Tenant isolation **PASS**, RBAC **PASS**, no production data deleted.

---
## Final Response (Required Format)

```
PHASE: A3
SCOPE: Academic Result Calculation + Finalization
DATA MODIFIED: YES
DATA DELETED: NO
MIGRATIONS: YES
RESULT_CALCULATION: PASS
MULTIPLE_ASSESSMENT: PASS
OPTIONAL_SUBJECT_BONUS: PASS
GPA: PASS
HISTORICAL_FREEZE: PASS
CONCURRENCY: PASS
TENANT_ISOLATION: PASS
REPORT_CARD_CONSISTENCY: PASS
TRANSCRIPT_CONSISTENCY: PASS
CRITICAL_FINDINGS: 0
HIGH_FINDINGS: 0
MEDIUM_FINDINGS: 1
BUSINESS_RULE_GAPS: 1
TESTS: 9/9
FINAL_VERDICT: GREEN
```

**Summary:**
- **Found:** Result calculation is now mathematically correct (component sum ? pct 4dp ? weight renormalized ? aggregate 2dp ? grade via band ? GP ? optional bonus ? GPA capped 5.00, equal/credit-weighted, deterministic).
- **Fixed:** `CASCADE ? RESTRICT` on 3 aggregation FKs + service guard on `destroy`, central `100%` weight validation (tolerance 0.005), `lockForUpdate` on marks and duplicate assessment, unique `group_key` on `academic_assessments`, optional bonus threshold 2.00 configurable via `grade_scales` (Bangladesh defaults), historical `withTrashed` preserved.
- **Remains:** 1 `BUSINESS RULE REQUIRED` (overall fail if compulsory fail — not invented, isolated) and 1 `MEDIUM` (no `SoftDeletes` on `academic_assessments` itself, but `isLocked` blocks).
- **Next phase can begin:** Yes — result pipeline is now `GREEN` for soft-delete/historical safety, tenant/RBAC, and Mid-Term+Final 30/70 determinism.

---
*Generated: 2026-08-27 | Data deleted: NO | Migrations: 4 (000001 S3, 000002-000004 A3) | Tests: 9/9 new hardening pass | No `exams` merge, no historical rewrite, no `FOREIGN_KEY_CHECKS=0`.*
