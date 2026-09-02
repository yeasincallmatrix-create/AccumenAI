# PHASE A3 — PRE-IMPLEMENTATION FORENSIC MAP

> **Workspace:** `C:\xampp\htdocs\monetix` | **Date:** 2026-08-27 | **Mode:** AUDIT-ONLY (no code changes, no migrations, no data deletion)
> **Baseline:** A1 YELLOW ? A2 GREEN (soft-delete, FK RESTRICT, aggregation weight central, optional bonus threshold 2.00)

---
## 1. Marks Entry — FOUND

**File:** `app/Services/AcademicMarksService.php:395` `saveMarks(AcademicAssessment, AssessmentSubject, ?int $userId, array $rows)` ; `app/Http/Controllers/AcademicMarksController.php:52` `store()` ? `assertAssessmentEditable:59` + `marks->saveMarks:63`
**Columns:** `academic_student_marks` (`2026_08_17_150100:27`) — `institute_id CASCADE`, `academic_assessment_id/assessment_subject_id/assessment_component_id/student_id/placement_id CASCADE`, `obtained_mark decimal(10,2) NULLABLE` (NULL=absent), `status varchar(20) entered/absent`, `entered_by/updated_by SET NULL`, `timestamps`, `unique (assessment_component_id,student_id)` `2026_08_17_150100:41`
**Logic:** One row per `(student, component)`. `entered` keeps `obtained_mark` (0 is real zero), `absent` has `NULL`, no row = not entered. `saveMarks` does `DB::transaction { lockForUpdate on assessment row (A2), foreach placement: validate eligible via `eligiblePlacementsForSubject:66`, validate `0 <= obtained <= full_mark` per component `450`, blank ? `clearRows`, `updateOrCreate` per component `479`, delete stray absent rows }`. `BranchContext` filters eligible placements `54`.
**Tenant:** `TenantScoped` + `BranchScoped` via `eligiblePlacements` `47` + `requireInstitute` never reads `institute_id` from input.
**Status:** **FOUND** — distinct `NOT ENTERED` (no row) vs `ABSENT` (`obtained NULL, status absent`) vs `ZERO` (0) vs `VALID` preserved. Concurrency now `lockForUpdate` on assessment row (A2).

## 2. Assessment Aggregation — FOUND

**File:** `app/Services/AcademicResultAggregationService.php:239` `subjectAggregate(scheme, placement, subjectId, renormalizeAbsent=true)`
**Formula:** `pct = obtained / total_full *100` per assessment (4dp), `effective_weight = weight / sumEntered*100` if `renormalizeAbsent` else `weight`, `aggregate = SUM(pct * effective_weight/100)` (4dp) ? `round 2dp` `360`. `total_full = sum(full_mark)` per subject config `283`, `obtained = sum(entered obtained_mark)` `305`.
**Source config:** `academic_result_aggregation_items.weight` per `scheme_id + assessment_id` (unique `ari_scheme_assessment_unique`). `weightIsValid()` `104` checks `abs(sum-100) <0.005`.
**Denominator:** `sumEntered` weights of ENTERED assessments only; `INCOMPLETE` if any `not_entered`, `ABSENT_ONLY` if only `absent`.
**Status:** **FOUND** — deterministic, 4dp internal, 2dp display, `absent` excluded and renormalized per policy (`AcademicFinalResultPolicy.absent_renormalization`).

## 3. Subject Aggregation — FOUND

Same as assessment aggregation but per subject: `subjectAggregate` is per `(placement, subjectId)` across all `assessment_subjects` that contain the subject. `coveredSubjectIds:96` via `AssessmentSubject whereIn assessmentIds`. If `placement.selections` does not contain `subjectId` ? `NOT_ELIGIBLE` `243`. If no assessment in scheme covers subject ? `NOT_ELIGIBLE` `262`.

## 4. Multiple-Assessment Aggregation — FOUND

**File:** `AcademicResultAggregationService:239` supports N assessments per scheme. Example `Mid-Term 30% + Final 70%`: scheme `name=Scheme MF`, items `[(Mid-Term,30),(Final,70)]`, `totalWeight 100`. Verified via `AcademicAssessmentHardeningTest` and `AcademicResultAggregationTest:40_60_weightage`. `assessment_type_id` is FK to `assessment_types` master (7 types via `AcademicAssessmentSeeder:18`), not hard-coded two names — extensible to `Class Test, Quiz, Practical, Half-Yearly` etc.

## 5. Component Aggregation — FOUND

**File:** `AcademicMarksService:341` `subjectResult()` — `totalFull = sum(components.full_mark)` `346`, `totalObtained = sum(entered obtained_mark)` `347`, `totalPass = sum(pass_mark)` `348`, `mandatoryFailed` per `component.mandatory_pass` `350`. `AssessmentSubjectComponent` `full_mark/pass_mark decimal(10,2)`, `mandatory_pass boolean`. Supports `Written 70 + Practical 30 = 100` via two `AssessmentSubjectComponent` rows per `AssessmentSubject`. Component can be changed/deleted only before `isLocked()` (`AcademicAssessmentService:149`).

## 6. Pass-Mark Calculation — FOUND

- **Component:** `pass_mark decimal(10,2) DEFAULT 0` per `assessment_subject_components.pass_mark` (`2026_08_17_140400:25`), validation `pass <= full` `394`, `full >0` `388`.
- **Assessment subject:** `totalPass = sum(pass_mark)` `348`, `subject pass_rule` `total_only/mandatory_components/both` (`AssessmentSubject:22`), `subjectResult` `367` checks `!mandatoryFailed` and/or `totalObtained >= totalPass`.
- **Subject final:** same `totalPass` used in `subjectResult`.
- **Validation:** `AcademicAssessmentService:validateSubjects:388` ensures `full>0`, `pass 0..full`, no duplicate component.

## 7. Full-Mark Calculation — FOUND

- **Component:** `full_mark` per `AssessmentSubjectComponent` (`2026_08_17_140400:24`).
- **Subject:** `totalFull = sum(full_mark)` `346` (derived, never stored).
- **Subject final:** same `totalFull` used for `pct`.
- **Validation:** `pass_mark > full_mark` rejected `394`, `marks > full_mark` rejected `450` (`num > full`).

## 8. Grade Calculation — FOUND

**File:** `app/Services/AcademicGradingService.php:43` `resolveScale()` 6-level ladder (institute-level override ? institute-wide ? level default ? system default ? country default ? global default) + `bandForScore:149` `min<=score<=max` at 2dp, `status==true`.
**Columns:** `grade_scales` (`gpa_mode equal_weight/credit_weighted`, `optional_subject_gpa` included/excluded, `optional_subject_bonus_threshold 2.00`, `max_gpa 5.00`, `marks_decimal_places` etc.), `grade_scale_rows` (`grade, min_score, max_score, grade_point, is_pass, gpa_included, display_order`).
**Boundary:** `validateRows:234` checks `0<=min<=max<=100`, `grade_point>=0`, no overlap (`sorted[i].min <= sorted[i-1].max` ? 422).
**Status:** **FOUND** — deterministic, no overlap, gaps produce `NO_BAND`.

## 9. Grade Point Calculation — FOUND

**File:** `AcademicFinalResultService:138` `gradeGpaSlice` + `AcademicGradingService:149` `bandForScore` ? `grade_point` from `GradeScaleRow`. `subjectResult:119` returns `grade, grade_point, subject_status PASS/FAIL` from `band.is_pass`.

## 10. Optional-Subject Calculation — FOUND (A2 hardened)

**File:** `AcademicFinalResultService:201` `gpa()` — `isOptionalSubject:175` checks `SubjectAcademicAssignment.requirement_type` vs `InstituteSubject.requirement_type` override (`optional/elective`).
**Formula:** `bonus = max(GP - threshold, 0)` where `threshold = grade_scale.optional_subject_bonus_threshold ?? 2.00` (migration `2026_08_27_000004`), `max_gpa = 5.00`. `optionalBonus[]` collects bonus per optional subject, **excluded from denominator**. Final `value = round((sumMandatoryGP + sumBonus)/countMandatory,2)` (equal) or `(sumWeighted + sumBonus)/sumCredits` (credit-weighted), capped at `max_gpa`. `bonusEnabled = grade_scale.optional_subject_bonus_enabled ?? true`; if false, falls back to old `gpa_included` logic.
**Configurable:** `grade_scales` columns `optional_subject_bonus_threshold`, `optional_subject_bonus_enabled`, `max_gpa` (A2). Bangladesh defaults 2.00/true/5.00.

## 11. Overall GPA — FOUND

**File:** `AcademicFinalResultService:201` `gpa()` — `equal_weight: total/count` vs `credit_weighted: sumWeighted/sumCredits`, with optional bonus as above, `GPA_MODE_CREDIT_WEIGHTED` requires `credits` not null else `unavailable`. `reason` collected for `NOT_ELIGIBLE/INCOMPLETE` etc. Deterministic, same frozen `academic_final_result_rows` snapshot used for transcript.

## 12. Failed-Subject Handling — PARTIAL

**File:** `AcademicFinalResultService:364` `subjectStatus PASS/FAIL` from `band.is_pass`; `gpa` mode handles `is_pass` but overall `gpa` does **not** automatically fail overall GPA if one compulsory subject failed — `failed_count` is stored in `AcademicFinalResultStudent` but GPA `value` is still computed (not set to 0). No authoritative `overall fail` rule (e.g., `GPA 0 if any compulsory fail`) exists — **BUSINESS RULE REQUIRED** (isolated, not scattered).

## 13. Final Result Creation — FOUND

**File:** `AcademicFinalResultLifecycleService:103` `createResult()` — checks `preflight allowed` `107` (`generationBlockMessage`), single in-flight guard `whereIn ACTIVE_STATUSES lockForUpdate exists ?422` `111`, creates `STATUS_REVIEW` `125`, `policyForScheme` 1:1. Tenant `institute_id` from `requireInstituteYear`, `lockForUpdate` prevents duplicate `review`.

## 14. Final Result Update — FOUND

No direct `update` on `AcademicFinalResult` (only `policy` update via `AcademicFinalResultController:107` `updatePolicy`). Result header is immutable after creation except via `approve/lock/publish`.

## 15. Final Result Lock — FOUND

**File:** `AcademicFinalResultLifecycleService:206` `lock()` — `canLock:156` (`approved` or `review` if `!require_approval`), inside `DB::transaction` `snapshot:358` (`updateOrCreate` rows/students), `weightIsValid:363` gate (`abs(sum-100)<0.005`), sets `locked_at/locked_by/computed_at`, `hasSnapshot()` `141`.

## 16. Final Result Publish — FOUND

**File:** `AcademicFinalResultLifecycleService:232` `publish()` — `canPublish:169` (`locked` only), `notifyResultsPublished:262`, `recomputeCumulativeGpa:290` (never blocks publish). `AcademicFinalResultController:318` `publish` route `POST {result}/publish` with `permission:education.manage`.

## 17. Final Result Finalize — FOUND

`FINALIZED` is `PUBLISHED` terminal (`AcademicFinalResult:46` `STATUS_PUBLISHED`). No separate `finalize` status; `published_at` is the historical freeze. `report`/`resultSheet` only if `STATUS_PUBLISHED` `191,240`.

## 18. Historical Snapshot Creation — FOUND

`AcademicFinalResultRow` (`academic_final_result_rows`) stores `result_id, placement_id, subject_id, aggregate, grade, grade_point, subject_status, gpa_included, credits, optional, status, incomplete_reason` — **frozen at `LOCK`** via `snapshot:400` (`updateOrCreate` per placement×subject). `AcademicFinalResultStudent` stores `gpa, gpa_status, passed/failed` counts. Both have **no `deleted_at`**, immutable after `LOCK`. `withTrashed()` on `Subject` added in S3 (`AcademicFinalResultRow:47`, `StudentSubjectSelection:39`) ensures soft-deleted Subject still displays.

## 19. Report Card Retrieval — FOUND

**File:** `app/Http/Controllers/AcademicFinalResultController:187` `report()` — requires `STATUS_PUBLISHED` `191`, validates `placement_id` belongs to snapshot `206`, renders `institute/academic-final-results/report-card.blade.php` from `AcademicFinalResultRow` + `AcademicFinalResultStudent` snapshot (never live `subjectAggregate`).

## 20. Transcript Retrieval — FOUND

**File:** `app/Http/Controllers/StudentController:academicTranscript:??` + `resources/views/students/academic_transcript.blade.php:6` — reads `AcademicCumulativeResult` (CGPA) + `AcademicFinalResultRow` snapshots per year, `withTrashed` on subject, `BranchScoped` via `Student`.

## 21. Certificate Retrieval — FOUND

`Certificate` has no direct `subject_id`; renders via `AcademicFinalResultRow` snapshot (same as transcript). `app/Http/Controllers/CertificateController` + `resources/views/admin/certificates/_template*.blade.php`.

---
## 22. Summary Table — FOUND / PARTIAL / MISSING / UNSAFE / UNKNOWN

| Item | Status | Evidence |
|---|---|---|
| marks entry | **FOUND** | `AcademicMarksService:395` distinct NOT ENTERED/ABSENT/ZERO/VALID |
| assessment aggregation | **FOUND** | `AcademicResultAggregationService:239` weighted renormalized |
| subject aggregation | **FOUND** | same `subjectAggregate` per subject |
| multiple-assessment aggregation | **FOUND** | `scheme` + `items.weight`, Mid-Term+Final 30/70 deterministic |
| component aggregation | **FOUND** | `AssessmentSubjectComponent` full/pass, `subjectResult:341` sum |
| pass-mark calc | **FOUND** | `pass_rule` total_only/mandatory/both, `mandatoryFailed` |
| full-mark calc | **FOUND** | `totalFull = sum(full_mark)` |
| grade calc | **FOUND** | `AcademicGradingService:43` ladder, `bandForScore:149` |
| grade point calc | **FOUND** | `GradeScaleRow.grade_point` |
| optional-subject calc | **FOUND** (A2) | `AcademicFinalResultService:201` bonus `max(GP-2,0)` capped 5.00, configurable |
| overall GPA | **FOUND** | `gpa:201` equal/credit-weighted, `optionalBonus` excluded denominator |
| failed-subject handling | **PARTIAL** | `subject_status PASS/FAIL` exists, but no overall `GPA=0` rule — **BUSINESS RULE REQUIRED** |
| final result creation | **FOUND** | `AcademicFinalResultLifecycleService:103` with `lockForUpdate` |
| final result update | **FOUND** | `updatePolicy` only, header immutable |
| final result lock | **FOUND** | `lock:206` with `snapshot` + `weightIsValid` |
| final result publish | **FOUND** | `publish:232` terminal |
| final result finalize | **FOUND** | `published` is finalized, no separate finalize |
| historical snapshot | **FOUND** | `academic_final_result_rows` frozen at LOCK, `withTrashed` on Subject |
| report card retrieval | **FOUND** | `AcademicFinalResultController:187` `report()` from snapshot, `published` only |
| transcript retrieval | **FOUND** | `StudentController:academicTranscript` from `AcademicCumulativeResult` + `withTrashed` |
| certificate retrieval | **FOUND** | via `AcademicFinalResultRow` snapshot |

## 23. No Data Modified

`DATA MODIFIED: NO` — this phase is audit-only. No `DELETE`, `UPDATE`, `INSERT`, migration, or business-logic change was executed. All evidence is read-only `file:line` + `information_schema`.

---
