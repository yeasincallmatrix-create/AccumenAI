# PHASE A4 — PRE-IMPLEMENTATION FORENSIC MAP

> **Workspace:** `C:\xampp\htdocs\monetix` | **Date:** 2026-08-27 | **Mode:** AUDIT-ONLY (no code changes, no migrations, no data deletion)
> **Baseline:** A1 YELLOW ? A2 GREEN (soft-delete, FK RESTRICT, weight 100% central, optional bonus threshold 2.00, `lockForUpdate` on marks, unique `uq_assessment_institute_year_class_group_name`)

---
## 1. Marks Entry — FOUND

**File:** `app/Services/AcademicMarksService.php:395` `saveMarks(AcademicAssessment, AssessmentSubject, ?int $userId, array $rows)` ; `app/Http/Controllers/AcademicMarksController.php:52` `store()` ? `assertAssessmentEditable:59` + `marks->saveMarks:63`
**Table:** `academic_student_marks` (`2026_08_17_150100:27`) — `institute_id CASCADE`, `academic_assessment_id/assessment_subject_id/assessment_component_id/student_id/placement_id CASCADE`, `obtained_mark decimal(10,2) NULLABLE` (NULL=absent), `status varchar(20) entered/absent`, `unique (assessment_component_id,student_id)` `2026_08_17_150100:41`
**Logic:** One row per `(student, component)`. `entered` keeps `obtained_mark` (0 is real zero), `absent` has `NULL`, no row = not entered. `saveMarks` does `DB::transaction { lockForUpdate on assessment row (A2), foreach placement: validate eligible via `eligiblePlacementsForSubject:66`, validate `0 <= obtained <= full_mark` per component `450`, blank ? `clearRows`, `updateOrCreate` per component `479` }`. BranchContext filters eligible.

## 2. Assessment Aggregation — FOUND

**File:** `app/Services/AcademicResultAggregationService.php:239` `subjectAggregate(scheme, placement, subjectId, renormalizeAbsent=true)`
**Formula:** `pct = obtained / total_full *100` per assessment (4dp), `effective_weight = weight / sumEntered*100` if renormalize else `weight`, `aggregate = SUM(pct * effective_weight/100)` (4dp) ? `round 2dp` `360`. `total_full = sum(full_mark)` per subject `283`, `obtained = sum(entered obtained_mark)` `305`.
**Source:** `academic_result_aggregation_items.weight` per `scheme_id + assessment_id` (unique `ari_scheme_assessment_unique`), `weightIsValid()` `104` checks `abs(sum-100) <0.005`.

## 3. Subject Aggregation — FOUND

Same as above per `(placement, subjectId)` across all `assessment_subjects` that contain the subject. `coveredSubjectIds:96` via `AssessmentSubject whereIn assessmentIds`. If `placement.selections` does not contain `subjectId` ? `NOT_ELIGIBLE` `243`.

## 4. Multiple-Assessment Aggregation — FOUND

Scheme `academic_result_aggregation_schemes` (year/class/group) + `items` (`scheme_id, assessment_id, weight`). Example `Mid-Term 30% + Final 70%` is two `AcademicAssessment` rows same `academic_year_id, class_grade_id, academic_group_id, name` different, scheme `Items: (Mid-Term,30),(Final,70)`. Verified via `AcademicResultAggregationTest:40_60_weightage`.

## 5. Component Aggregation — FOUND

`AssessmentSubjectComponent` `full_mark/pass_mark` per `assessment_subject_id, component_id`. `totalFull = sum(full_mark)` `346`, not percentage. Component can be changed only before `isLocked()`.

---
## 6. Pass-Mark / Full-Mark — FOUND

- **Component:** `assessment_subject_components.full_mark/pass_mark decimal(10,2)` (`2026_08_17_140400:24-25`), validation `pass <= full` `394`, `full >0` `388`.
- **Subject:** `totalFull = sum(full_mark)` `346`, `totalPass = sum(pass_mark)` `348`, `pass_rule` `total_only/mandatory_components/both` (`AssessmentSubject:22`).
- **Validation:** `AcademicAssessmentService:validateSubjects:328` and `AcademicMarksService:saveMarks:450` both enforce `0 <= obtained <= full`, `pass <= full`.

## 7. Grade / GP — FOUND

**File:** `app/Services/AcademicGradingService.php:43` `resolveScale()` 6-level ladder + `bandForScore:149` `min<=score<=max` at 2dp. `grade_scales` (`optional_subject_bonus_threshold 2.00`, `max_gpa 5.00` via `2026_08_27_000004`) + `grade_scale_rows` (`grade, min_score, max_score, grade_point, is_pass`). `AcademicFinalResultService:138` `gradeGpaSlice` ? `grade, grade_point`.

## 8. Optional-Subject / Overall GPA — FOUND (A2 hardened)

**File:** `AcademicFinalResultService:201` `gpa()` — `isOptionalSubject:175` via `SubjectAcademicAssignment.requirement_type` vs `InstituteSubject.requirement_type`. `bonus = max(GP - 2.00, 0)` with threshold from `grade_scales.optional_subject_bonus_threshold` (default 2.00), `bonusEnabled`, `max_gpa 5.00`, excluded from denominator, capped. Verified via `AcademicAssessmentHardeningTest`.

## 9. Failed-Subject Handling — PARTIAL

`AcademicFinalResultService:119` `subject_status PASS/FAIL` from `band.is_pass`; `failed_count` stored in `AcademicFinalResultStudent` but `gpa.value` still computed (not 0). No authoritative overall `GPA=0` rule — **BUSINESS RULE REQUIRED**.

## 10. Final Result Creation/Update/Lock/Publish/Finalize — FOUND

- **Create:** `AcademicFinalResultLifecycleService:103` `createResult()` preflight + single in-flight `lockForUpdate` `111` ? `STATUS_REVIEW`
- **Lock:** `lock:206` `canLock` + `snapshot:358` + `weightIsValid` `363` ? `locked_at`
- **Publish:** `publish:232` `canPublish` (`locked` only) ? `STATUS_PUBLISHED` terminal, `recomputeCumulativeGpa`
- **No `update` after `review`, no `destroy`** (deliberately), no `ARCHIVE/RESTORE`.

## 11. Historical Snapshot — FOUND

`academic_final_result_rows` stores `result_id, placement_id, subject_id, aggregate, grade, grade_point, subject_status, gpa_included, credits, optional` frozen at `LOCK` via `snapshot:400` (`updateOrCreate`). `AcademicFinalResultStudent` stores `gpa`. Both use `withTrashed()` on `Subject` (S3) for soft-deleted display.

## 12. Report Card / Transcript / Certificate — FOUND

- **Report Card:** `AcademicFinalResultController:187` `report()` requires `STATUS_PUBLISHED`, reads `AcademicFinalResultRow` snapshot (never live).
- **Transcript:** `StudentController:academicTranscript` reads `AcademicCumulativeResult` + `AcademicFinalResultRow` snapshots, `withTrashed` on subject.
- **Certificate:** No direct `subject_id`, renders via `AcademicFinalResultRow` snapshot (same as transcript).

## 13. Summary Table — FOUND / PARTIAL / MISSING

| Item | Status |
|---|---|
| marks entry | FOUND |
| assessment aggregation | FOUND |
| subject aggregation | FOUND |
| multiple-assessment | FOUND |
| component aggregation | FOUND |
| pass-mark | FOUND |
| full-mark | FOUND |
| grade | FOUND |
| grade point | FOUND |
| optional-subject | FOUND (A2) |
| overall GPA | FOUND |
| failed-subject | PARTIAL (BUSINESS RULE REQUIRED) |
| final result creation | FOUND |
| lock/publish/finalize | FOUND |
| historical snapshot | FOUND |
| report card | FOUND |
| transcript | FOUND |
| certificate | FOUND |

**No Data Modified** — audit-only, all evidence file:line + `information_schema`.

---
