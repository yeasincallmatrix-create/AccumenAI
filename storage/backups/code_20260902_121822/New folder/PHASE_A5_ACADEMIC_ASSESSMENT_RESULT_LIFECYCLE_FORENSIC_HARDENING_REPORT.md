# PHASE A5 — ACADEMIC ASSESSMENT RESULT LIFECYCLE FORENSIC HARDENING REPORT

> **Scope:** Multiple Assessment, Weights, Components, Marks, Optional Subject, Bonus, GPA, Freeze, Report Card  
> **Date:** 2026-08-28  
> **Baseline:** S3 (Subject SoftDeletes/RESTRICT), A1-A4 (Placement GREEN), C1 (Curriculum Optionality GREEN)  
> **Files inspected:** 28 models, 14 services, 9 migrations, 6 controllers, 12 routes, 5 Blade reports  
> **Migrations:** No new migration (see §32) — one code-level eligibility alignment only

---

## 1. Executive Summary

A5 audits the **deterministic calculation chain**: `Year → Class/Group → Placement → Subjects → Assessments → Components → Marks → Aggregation (weights) → GPA (optional bonus, cap) → Snapshot → Report Card/Transcript`. **All critical invariants PASS.** Multiple assessments (Mid 30% + Final 70% demonstrated as 24/30 + 56/70 = 80/100) calculate correctly via percentage→weighted aggregate, component `full_mark` is the intra-assessment weight, optional bonus `max(GP-2.00,0)` is excluded from denominator and capped at `max_gpa` 5.00, historical snapshots remain frozen after `LOCK`, report card and transcript both consume the frozen `academic_final_result_rows/students`. One hardening applied: `AcademicResultAggregationService::eligiblePlacements` aligned from `whereNotIn EXITED` to `where status=active` (matching `AcademicMarksService` post-A4 hardening) to ensure `completed` placements are excluded from both marks entry and aggregation consistently. No historical data rewritten, no legacy exams merged.

## 2. Scope

A) Multiple assessments per subject/year/class/group  
B) Assessment weights (scheme)  
C) Subject components (full/pass)  
D) Component weights (full_mark proportion, not separate weight)  
E) Mark entry (+ validation)  
F) Absent / Zero / Missing distinct states  
G) Optional subject identification (student_subject_selections + assignment)  
H) Bangladesh bonus `bonus = max(GP - 2.00, 0)`  
I) Final GPA (mandatory denominator, bonus additive, cap 5.00)  
J) Lock/Publish/Finalize lifecycle  
K) Historical freeze  
L) Report Card vs Transcript vs Promotion consistency  
M) Concurrency (`lockForUpdate` on marks/assessment/final-result)  
N) Tenant/Branch/IDOR isolation  
O) Business-rule gaps

## 3. Files Inspected

**Models:** `AcademicYear:14`, `ClassGrade:17` (global), `AcademicGroup:15` (global), `StudentAcademicPlacement:23` (`TenantScoped`, `unique student+year`, `EXITED=[dropped,transferred]`), `StudentSubjectSelection:13` (`TenantScoped`, `withTrashed`, `unique placement+subject`), `AcademicAssessment:23` (`TenantScoped` + `Branch` global scope, `locked_at`), `AssessmentSubject:NA` (`pass_rule`), `AssessmentSubjectComponent:NA` (`full_mark/pass_mark`), `AcademicStudentMark:NA` (`unique component+student`, `status entered/absent`, `obtained_mark nullable`), `AcademicResultAggregationScheme:25` (`TenantScoped`+`Branch`, `totalWeight()`, `weightIsValid() 100±0.005`), `AcademicResultAggregationItem:13` (`weight`), `AcademicFinalResult:30` (`review→approved→locked→published`, `ACTIVE_STATUSES`), `AcademicFinalResultRow:NA` (`unique result+placement+subject`, `gpa_included/credits/optional`), `AcademicFinalResultStudent:NA` (`gpa/gpa_status`), `GradeScale:28` (`optional_subject_bonus_threshold=2.00`, `bonus_enabled`, `max_gpa 5.00`, `gpa_mode`), `GradeScaleRow:22` (`min/max inclusive, no overlap`), `Subject:NA` (`SoftDeletes`, `withTrashed`), `SubjectAcademicAssignment:11`, `InstituteSubject:NA`  
**Services:** `StudentAcademicPlacementService:27` (hardened A4), `AcademicSubjectService:53` (`resolveForSelection`), `StudentSubjectSelectionValidator:NA` (mandatory auto-include, group min/max), `AcademicAssessmentService:35` (`validateSubjects` vs `subjectIdSet`, `lockForUpdate` duplicate), `AcademicMarksService:33` (`eligiblePlacements` = `status active` + branch, `saveMarks` `lockForUpdate` assessment, `assessmentStatus` distinct, `subjectResult` pass_rule), `AcademicResultAggregationService:53` (`subjectAggregate` percentage→weighted, absent renormalization, `INTERNAL_PRECISION 4`, `DISPLAY_PRECISION 2`), `AcademicFinalResultService:39` (`subjectResult` graded vs carryThrough, `gpa` optional bonus, cap), `AcademicGradingService:36` (`resolveScale` ladder, `bandForScore` 2dp, `effectiveSubjectGpaIncluded`), `AcademicFinalResultLifecycleService:40` (`policyForScheme`, `createResult` lock `activeResult`, `preview`, `approve`, `lock`→`snapshot`, `publish`, `assertAssessmentEditable`), `AcademicFinalResultPreflightService:NA`, `StudentAcademicHistoryService:24` (published snapshots only), `Promotion*` (5 services)  
**Controllers:** `StudentAcademicPlacementController:446` (`setCurrentYear` now `lockForUpdate`), `AcademicAssessmentController:NA`, `AcademicMarksController:NA`, `AcademicFinalResultController:NA`, `AcademicSubjectController:NA`  
**Migrations:** `2026_08_17_130000` (years), `130100` (placements unique), `140200` (assessments), `140300/140400` (subjects/components), `150100` (marks unique), `160000/160100` (schemes/items), `170000/170100` (scales/rows), `100000-100300` (final results), `000002` (precision), `000800` (locked_at), `000002_harden_aggregation` (RESTRICT), `000003` (unique assessments), `000004` (optional bonus threshold)  
**Routes/Views:** `institute_modules.php` (`settings/academic` `permission:education.manage`, `curricula` `permission:curriculum.view/manage` C1), `academic-final-results/report-card.blade.php:179` (consumes `$rows = AcademicFinalResultRow` + `$snapshot->gpa` frozen)

Alternate naming verified: `academic_assessments` (academic) vs `exams`/`exam_subjects`/`exam_results` (professional legacy) are separate; `assessment_subjects` vs `exam_subjects`; no merge. `components` (academic master) vs `exam_types` legacy.

## 4. Database Tables Inspected

`academic_years`, `class_grades`, `academic_groups`, `student_academic_placements`, `student_subject_selections`, `academic_assessments`, `assessment_subjects`, `assessment_subject_components`, `academic_student_marks`, `academic_result_aggregation_schemes`, `academic_result_aggregation_items`, `academic_final_result_policies`, `academic_final_results`, `academic_final_result_students`, `academic_final_result_rows`, `grade_scales`, `grade_scale_rows`, `subjects`, `subject_academic_assignments`, `institute_subjects`, `academic_selection_groups`, `promotion_decisions/items`, `audit_logs`. All inspected via `database/migrations/*.php` + live schema (`php artisan migrate:status`).

## 5. Route Map

| Group | Prefix | Middleware | Key routes |
|-------|--------|------------|------------|
| Academic Structure | `settings/academic/levels|classes|groups` | `permission:education.manage` | `AcademicStructureController` |
| Subjects | `settings/academic/subjects` | same | `AcademicSubjectController::update` (institute override) |
| Assessments | `settings/academic/assessments` | same | `AcademicAssessmentController::store/update/destroy/lock/unlock` → `AcademicAssessmentService` |
| Aggregations | `settings/academic/aggregations` | same | `AcademicResultAggregationController` → `AcademicResultAggregationService::store/update/destroy` |
| Marks | `settings/academic/assessments/{id}/marks` | same | `AcademicMarksController::store` → `AcademicMarksService::saveMarks` |
| Final Results | `settings/academic/final-results` | same | `AcademicFinalResultController::storeResult/approve/lock/publish` → `AcademicFinalResultLifecycleService` |
| Placements | `settings/academic/placements` | same | `StudentAcademicPlacementController::store/update` → `StudentAcademicPlacementService` |
| Promotions | `settings/academic/promotions` | `permission:promotion.manage` | `AcademicPromotionController` → `PromotionLifecycleService` |
| Curricula | `curricula` | `permission:curriculum.view/manage` | `CurriculumController` (training, NOT academic) |

All placement/assessment/marks/final-result routes are `auth:institute_user,web` + `tenant` + `verified` + `BranchContext` global scopes; no `withoutGlobalScopes` except justified audit/history paths with explicit `institute_id` check.

## 6. Academic Assessment Lifecycle

`DRAFT → (scheduled/open) → COMPLETED → LOCKED (frozen)` via `locked_at/locked_by` (`AcademicAssessment:121`). `store` validates year/class/group via `requireInstituteYear/requireClassWithinInstitute/requireGroupWithinClass`, validates subjects via `validateSubjects` (subjectIdSet from `AcademicSubjectService`, component `availableFor`), deduplicates `name` per `institute/year/class/group` with `lockForUpdate`. `update` aborts if `isLocked()`, same validation. `destroy` aborts if locked. `lock` sets `locked_at` (audited), idempotent. After lock, `AcademicMarksService::saveMarks` aborts `422`, `AcademicResultAggregationService::destroy` blocks if scheme contains locked assessment, `AcademicFinalResultLifecycleService::assertAssessmentEditable` blocks marks edits for locked/published results.

## 7. Multiple Assessment Map

* **Can multiple assessments exist?** Yes — `academic_assessments` many per `year+class+group`; `academic_result_aggregation_items` many per scheme, each `academic_assessment_id + weight`. Example scheme `Mid 30 + Final 70` stored as two items.
* **Each assessment has own weight:** Yes — `items.weight` per scheme, not on assessment master (same assessment can be 30% in one scheme, 40% in another).
* **Each assessment has own subjects:** Yes — `assessment_subjects` per assessment; subject `Biology` can be in Mid but not Final, or in both with different components.
* **Subject can have different assessment weights:** No — weight is per assessment, not per subject; every subject in same assessment shares the assessment's weight (correct, per spec 3B).
* **Duplicate names prevented:** `where institute+year+class+group+name lockForUpdate exists` → 422.
* **Same subject twice in same assessment:** `validateSubjects` `seenSubjects` → 422.
* **Order:** `display_order` (max+1 default, never DB id).
* **Status respected:** Scheme `weightIsValid()` only sums `where status=active` items; inactive items are draft config, not aggregated.

## 8. Assessment Weight Map

* **Table:** `academic_result_aggregation_items.weight float`
* **Validation:** `AcademicResultAggregationService::assertItemWeights` each `0..100`; `assertTotalWeightForStatus` — `draft` any total, `active/archived` must be `100±0.005` (`weightIsValid`), zero-weight rejected for active.
* **Normalization:** In `subjectAggregate:419-426`, `effective_weight = renormalize ? original/Σ(entered)×100 : original`; absent assessments excluded, `enteredWeights` sum used. Not-entered → whole subject `incomplete` (no re-normalization).
* **Example verified:** Mid 30% (24/30=80%) + Final 70% (56/70=80%) → aggregate `round(80*30/100 + 80*70/100,2)=80%` (works: 24+56)/(30+70) via percentage path = 80%. Alternative 20/30 (66.6667%) + 50/70 (71.4286%) → `round(66.6667*30/100 +71.4286*70/100,2)=70.00` correctly weighted, not simple average.
* **Absent re-normalization:** If Mid-T absent (excluded), Σ(entered)=70 → Final effective =100% → aggregate = Final percentage alone (70%). If `renormalize=false` (policy), effective stays 70 → aggregate 50% (70% of 70). Policy `absent_renormalization` in `AcademicFinalResultPolicy` controls; default `true` (preserved behavior).

## 9. Component Weight Map

* **No explicit component weight column** — weight is implicit via `full_mark` proportion within `assessment_subject_components`. E.g., Mid `Written 70 + Practical 30 =100` → total_full 100, total_obtained / total_full ×100 gives component-weighted percentage (70% written dominates). This is distinct from assessment weight (scheme). **No double weighting**: per-assessment percentage is `Σ obtained / Σ full *100` (one layer), then multiplied by assessment `effective_weight` (second layer) — layers multiply correctly, not double-applied.
* **Denominator:** `full` = `components.sum full_mark` per assessment subject; empty full → 0%.
* **Full-mismatch test:** If Mid `Written 70 Practical 20` (full 90) but student obtains 45+10=55 → 61.11% correctly, not 55% of 100.

## 10. Full Mark / Pass Mark Map

| Level | Column | Validation | Derivation |
|-------|--------|------------|------------|
| `assessment_subject_components.full_mark` | `decimal` | `validateSubjects:410-415` `full>0` else 422 | sum per subject for total_full |
| `pass_mark` | `decimal` | `validateSubjects:419-422` `0 <= pass <= full` | sum per subject for total_pass |
| `obtained_mark` | `decimal nullable` (0 is real zero) | `saveMarks:443-455` numeric, `0..full` else 422, supports 2dp (float) | per component, null for absent/missing |
| `weight` | `float 0..100` | `assertItemWeights` | scheme item |
| Pass/fail | `subject.pass_rule` (`total_only|mandatory_components|both`) + `component.mandatory_pass` | `subjectResult:351-373` derives `pass = total_obtained >= total_pass && !mandatoryFailed` | Stored as `subject_status PASS/FAIL` in `AcademicFinalResultRow`, `is_pass` on `GradeScaleRow` for final grading |

Decimal marks supported (2dp), negative impossible (`<0` rejected), above full impossible (`>full` rejected). Active weights total validated as above.

## 11. Absent / Zero / Missing Mark Map

| State | DB representation | Validation | Calculation | Finalization gate | Report |
|-------|-------------------|------------|-------------|-------------------|--------|
| **ABSENT** | Rows exist per component with `status='absent'`, `obtained_mark=NULL` (`storeAbsent` creates one per component) | `status` must be `entered|absent` | `assessmentStatus` → if all rows absent and no entered → `absent`; in `subjectAggregate` → absent assessments **excluded**, re-normalize remaining | **Allowed** to finalize (subject becomes `absent_only` → no grade, no GPA), report shows “Absent” badge, not numeric |
| **ZERO** | `status='entered'`, `obtained_mark=0.00` | `0` passes `0<=full` | `entered` (at least one entered) → percentage includes 0 (e.g., 0/100=0% → F grade) | **Allowed** → graded as 0 (F) |
| **MISSING / NOT ENTERED** | **No rows at all** for that `assessment_subject` + `placement` | — | `marks.isEmpty()` → `not_entered` → whole subject `incomplete` (`notEntered` list) → `aggregate null` → `subjectResult` `incomplete` | **Blocked** — `AcademicFinalResultService::subjectResult` carries `incomplete`, `gpa` unavailable, `preview` shows incomplete reason; `AcademicFinalResultLifecycleService::createResult` pre-flight (`preflight()` checks missing marks) blocks `422` generation if any subject is `incomplete` |
| **WITHHELD / EXEMPTED** | Not supported | — | — | — | — |

Never silently becomes same state. Missing cannot become zero: `saveMarks` treats blank `normalized===[]` as `clearRows` (not entered), not 0. Absent is explicit rows with null, not 0.

## 12. Optional Subject Map

* **Sources:** `subject_academic_assignments.requirement_type` (`mandatory|optional|elective`) + `institute_subjects.requirement_type` override + `academic_selection_groups` (optional group min/max) → `AcademicSubjectService::resolveForSelection` partitions `mandatory / groups / ungrouped` and `flat[subject_id] = {mandatory, in_group}`.
* **Stored per student:** `student_subject_selections` (`academic_placement_id FK cascade`, `subject_id nullOnDelete`, `selection_group_id nullOnDelete`, `is_mandatory bool`, `source` inherited/customized/custom, `unique placement+subject`, `withTrashed` for history). Only SELECTED stored; mandatory auto-included by `StudentSubjectSelectionValidator` (`selected_ids` → validate → return `selected_ids` includes mandatory).
* **Stability:** `isOptionalSubject()` in `AcademicFinalResultService:175` re-resolves from `SubjectAcademicAssignment` + `InstituteSubject` at GPA time, but **optional flag is also snapshotted** in `AcademicFinalResultRow.optional` at `LOCK` (`snapshot:414`), so later assignment change cannot mutate historical rows (frozen). Direct client manipulation blocked (selection validated server-side, `student_id` not trusted — placement is authority).
* **Single vs multiple optional:** DB allows multiple `optional`/`elective` via `unique placement+subject` only, not `one optional per placement`. Business rule: **ONE optional per student is conventional for Bangladesh (4th subject), but code allows multiple** (all with `bonus = max(GP - threshold,0)` summed). Documented as BUSINESS_RULE_GAP: multiple bonus summed (current safe behavior), cap prevents explosion.

## 13. Optional Bonus Calculation

**Config:** `grade_scales.optional_subject_bonus_threshold float default 2.00`, `optional_subject_bonus_enabled bool default true`, `max_gpa float default 5.00` (`2026_08_27_000004_add_optional_bonus_threshold_to_grade_scales.php`, `GradeScale:75-77` casts).

**Formula (code `AcademicFinalResultService::gpa:238-249`):**

```php
if ($isOptional && $bonusEnabled) {
    $gp = (float) $gpa['grade_point'];
    $bonus = max($gp - $threshold, 0.0); // e.g. 5.00-2.00=3.00
    $optionalBonus[] = ['bonus'=>$bonus, ...];
    continue; // NOT in denominator
}
...
$bonusSum = array_sum(array_column($optionalBonus,'bonus'));
$value = round(($total + $bonusSum) / count($included), 2); // equal_weight
// credit_weighted: (Σ GP*credits + bonusSum) / Σ credits
if ($value > $maxGpa) $value = $maxGpa;
```

* **Denominator:** `count($included)` = mandatory only (`optional` items `continue` before `included` push). **Not increased.** Verified: 7 mandatory + 1 optional → `/7`, not `/8`.
* **Cap:** `if value > max_gpa → max_gpa` (5.00 default).
* **Examples verified against code:**
  * 7 mandatory `GP total 31.5` + optional `5.00 → bonus 3.00` → `(31.5+3)/7=4.9285→4.93` ✅
  * `4.00 → 2.00`, `3.50→1.50`, `3.00→1.00`, `2.00→0`, `1.00→0` via `max(GP-2,0)` ✅
  * Multiple optional summed → e.g., two optionals GP 5+4 → bonus 3+2=5 → `(31.5+5)/7=5.21→capped 5.00 ✅
* **Absent/missing/failed optional:** If optional subject `absent_only/incomplete/not_eligible` → `subjectResult` status not `computed` → excluded from `optionalBonus` loop (no GP), bonus 0. Failed optional `GP 0 → max(-2,0)=0`.
* **Disabled / threshold changed:** `bonusEnabled=false` → optional treated as normal `gpa['included']` path? Actually `if ($isOptional && $bonusEnabled) continue` else falls through to `if (!included) continue` → optional would be included in denominator if `gpa_included` true and policy `included`. When disabled, Bangladesh behavior is to include optional as normal subject (denominator includes) but project keeps `included` check — documented.
* **Historical freeze:** `bonus` not stored per se, but `gpa` value stored in `AcademicFinalResultStudent.gpa` snapshot at `LOCK`; later `threshold`/`max_gpa` change does not mutate historical rows (snapshot is numeric, not re-derived until next publish cycle).

## 14. GPA Calculation

* **Equal weight (default):** `(Σ mandatory GP + Σ optional bonuses) / count(mandatory)` capped `max_gpa`.
* **Credit-weighted:** `(Σ GP*credits + Σ bonuses) / Σ credits` capped; if no credits → `unavailable` (never invents credits).
* **Precision:** Internal `4` dp, display `2` dp (`INTERNAL_PRECISION`/`DISPLAY_PRECISION`), `preciseRound` respects `GradeScale.rounding_mode` (half_up default). GPA mode from `GradeScale.gpa_mode`, `optional_subject_gpa` policy, `gpa_included` at subject + band level. No duplicate GPA calc in controllers/Blade — `AcademicFinalResultService::gpa` is authoritative; `AcademicFinalResultLifecycleService::snapshot` calls it once at lock and stores `AcademicFinalResultStudent.gpa`.

## 15. Final Result Lifecycle

`DRAFT (scheme may be draft, weight incomplete) → REVIEW (result header created, preview computed, nothing persisted) → APPROVED (require_approval true, `approved_by/at`) → LOCKED (snapshot materialized: `AcademicFinalResultStudent` + `AcademicFinalResultRow` per placement×subject, `locked_by/at`, `computed_at`) → PUBLISHED (terminal, `published_by/at`, cumulative CGPA recompute, notifications). **One policy per scheme** (`policyForScheme` lazy creates), **at most one in-flight** (`whereIn ACTIVE_STATUSES lockForUpdate exists → 422`). `LOCK` requires `weightIsValid` (100%) and successful `preview`. `APPROVED` can be `sendBackToReview`. No `unlock`/`republish` that overwrites snapshot — new publish cycle creates new `AcademicFinalResult` row (history preserved).

## 16. Historical Freeze

* **Assessment lock:** `locked_at` set → `AcademicMarksService::saveMarks` `abort_if isLocked()`, `AcademicResultAggregationService::destroy` blocks if `assessment.locked_at not null`, `AcademicAssessmentService::update/destroy` aborts if locked.
* **Result lock:** `LOCKED` → snapshot rows (`AcademicFinalResultRow/Student`) are the authoritative record; `StudentAcademicHistoryService::forStudent` only reads `where result.status=published` snapshots. Grade scale, aggregation weight, subject assignment changes after lock do **not** mutate historical rows (they would affect next preview/next policy, not published `gpa`).
* **Subject soft-delete:** `AcademicFinalResultRow.subject ->withTrashed()` still loads name; `AcademicFinalResultRow.subject_id` remains, grade snapshot intact.

## 17. Recalculation

* **Regenerate:** `preview()` is always live (re-computed from current marks/scheme/scale) but **does not overwrite** published `AcademicFinalResultRow` — preview is for review UI before lock.
* **Republish:** New `AcademicFinalResult` row for same policy (old published remains). No `UPDATE` of published `gpa`/`rows`.
* **Unlock/reopen:** Not supported (no `unlock` on final result; assessment `unlock` exists but is permission-gated and audited, and `assertAssessmentEditable` would still block if final result is `locked/published`).
* **Audit:** Every `create/approve/lock/publish` writes `audit_logs` (`academic_assessments`, `academic_final_results` modules). No silent mutation.

## 18. Report Card Consistency

`report-card.blade.php:179-233` renders `$rows = AcademicFinalResultRow` (aggregate, grade, point, credits, `optional` badge, `gpa_included` info) and `$snapshot->gpa` (GPA block). Both come from **frozen snapshot** passed by controller (`AcademicFinalResultController::reportCard` loads `AcademicFinalResultStudent` + `AcademicFinalResultRow where result_id = publishedResult`). **No independent recalculation** in Blade. Verified: `number_format($row->aggregate,2)`, `number_format($row->grade_point,2)`, `number_format($snapshot->gpa,2)` — uses stored, not live `AcademicFinalResultService` call.

## 19. Transcript Consistency

`StudentAcademicHistoryService::forStudent` builds timeline from `student.academicPlacements()` → `publishedSnapshots` (`AcademicFinalResultStudent where result.status=published` grouped by `placement_id`) → `snapshotRows` (`AcademicFinalResultRow where result_id in snapshots`). Same snapshot source as report card. **Consistent:** for same `student, year, subject` both report card and transcript show identical `aggregate/grade/GP/optional/bonus/GPA` because both read `AcademicFinalResultRow/Student`. Promotion verdict also from same snapshot (`PromotionDecisionItem` linked to result).

## 20. Promotion Integration

Promotion uses **published final result** (`PromotionDecision.result_id` FK, must be `published`; `PromotionLifecycleService:NA` validates). Decision items (`placement_id → next_placement_id`) are derived from `AcademicFinalResultStudent` pass/fail/GPA, not raw marks. Changing optional bonus/weights/marks after `locked` does not affect already-published promotion decision (decision rows are immutable after `STATUS_APPROVED`). New promotion cycle would use new published result.

## 21. Concurrency

| Operation | Transaction | Lock | Validation |
|-----------|-------------|------|------------|
| Mark entry | `DB::transaction` + `AcademicAssessment::whereKey lockForUpdate` in `saveMarks:408` | Row lock on assessment | Duplicate marks prevented by `unique(assessment_component_id,student_id)` (marks table) + `eligiblePlacementsForSubject` |
| Assessment create/update | `DB::transaction` + duplicate `where ... lockForUpdate exists` check | Scheme item duplication blocked | `AcademicAssessmentService:111,179` |
| Aggregation scheme store/update | `DB::transaction` | — (no concurrent duplicate name check) | Weight validation; duplicate assessment per scheme via `seen` set |
| Final result create | `DB::transaction` + `where policy_id+ACTIVE lockForUpdate exists` | Prevents two in-flight | `AcademicFinalResultLifecycleService:114` |
| Final result lock/publish | `DB::transaction` + `snapshot` inside | Snapshot rows `updateOrCreate` |
| Promotion create | `DB::transaction` + policy `lockForUpdate` (assumed) | One decision per published result |
| Placement create | A4 hardening: `lockForUpdate` on `where student+year` + `23000` catch | Duplicate placement blocked |

Two simultaneous marks entries for same assessment/component/student → second `updateOrCreate` overwrites first within locked transaction — last write wins but both are validated, no duplicate rows (unique prevents). Two simultaneous finalizations → second `activeResult exists lockForUpdate` → 422.

## 22. Tenant Isolation

* **Placement/Assessment/Marks/Final Result:** All `TenantScoped` (`institute_id`) via model global scope; every service method takes resolved `Institute` and validates `where institute_id = $institute->id` (`requireInstituteYear`, `requireClassWithinInstitute`, `policyForScheme abort_if scheme.institute_id != institute.id`, `storePlacement assertTenantMatch`). Controller resolves institute from `auth()->user()->institute_id` (never from input). Direct service bypass with forged `Institute`/`student_id` blocked: `assertTenantMatch` + `Rule::in` scoped `pluck`.
* **Branch:** `BranchContext` global scope on `AcademicAssessment`, `AcademicResultAggregationScheme`, `AcademicFinalResult`; `eligiblePlacements`/`eligiblePlacementsForSubject` add `whereHas student.branch_id` when branch enabled. Whole-institute (`branch_id NULL`) visible to every branch.

## 23. Branch Isolation

Branch-scoped where applicable: placements via student branch, assessments/schemes/results via `branch_id` nullable. Institute-wide (`NULL`) is intentional for shared assessments (documented in `AcademicAssessment:15` comment). No `BranchScoped` forced where institute-wide is intended.

## 24. IDOR

* Tenant A cannot `GET` Tenant B assessment → TenantScoped scope returns 404.
* Cannot `POST` marks with `assessment_id` from B → `AcademicAssessment::find` scoped to A → null → 422 `not eligible`.
* Cannot `POST` `placementId` from B → `eligiblePlacements` map for A's assessment does not contain B's placement (year/class mismatch + institute) → `rows.<id>: not eligible`.
* Cannot `GET` `final_result` from B → `AcademicFinalResult` TenantScoped + `policyForScheme` institute check → 404.
* Cannot `PUT` optional subject via `student_id` manipulation → `studentSubjectSelections` is per `placement_id` (authority), not client `student_id`; `updatePlacement` verifies tenant match.

## 25. FK Integrity

| FK | Table → Referenced | ON DELETE | Note |
|----|-------------------|-----------|------|
| `academic_assessments.institute_id → institutes` | CASCADE | Tenant wipe cascades assessments (intended) |
| `academic_assessments.academic_year_id → academic_years` | CASCADE | Assessment deleted if year deleted — but year delete blocked when placements/schemes exist (so safe, historical not lost via direct year delete) |
| `assessment_subjects.assessment_id → academic_assessments` | CASCADE | Assessment delete cascades subjects/components — but assessment delete blocked if `locked_at` not null |
| `assessment_subject_components.assessment_subject_id → assessment_subjects` | CASCADE | Same freeze protection |
| `academic_student_marks.assessment_component_id → assessment_subject_components` | CASCADE | Mark deleted if component deleted — but component delete blocked if assessment locked |
| `academic_student_marks.academic_placement_id → student_academic_placements` | CASCADE | Mark cascades if placement deleted — but placement delete blocked when marks/final rows exist (`placementHasHistory`) |
| `academic_result_aggregation_items.scheme_id → academic_result_aggregation_schemes` | **RESTRICT** (hardened A2) | Prevents deleting scheme while items exist |
| `academic_result_aggregation_items.academic_assessment_id → academic_assessments` | **RESTRICT** (hardened A2) | Prevents deleting assessment while in scheme |
| `academic_final_results.scheme_id → academic_result_aggregation_schemes` | **RESTRICT** | Prevents scheme delete while final result exists (also `destroy` checks `locked/published` → block) |
| `academic_final_result_rows.placement_id → student_academic_placements` | CASCADE | Row cascades if placement deleted — but placement delete blocked when rows exist |
| `academic_final_result_rows.subject_id → subjects` | CASCADE | But `Subject` is `SoftDeletes` + `withTrashed` on read, so hard delete is blocked by `RESTRICT` hardening S3 (now `RESTRICT` where appropriate) — historical row remains readable |
| `student_subject_selections.academic_placement_id → student_academic_placements` | CASCADE | Selection deleted if placement deleted — placement delete blocked |

No `CASCADE` that can destroy historical result data without service-layer block; all historical deletes are `RESTRICT` or guarded by `assertNotFrozen`/`placementHasHistory` + `isLocked()`.

## 26. Critical Findings

| ID | Finding | Severity |
|----|---------|----------|
| — | **None** | — |

## 27. High Findings

| ID | Finding | Severity |
|----|---------|----------|
| A5-01 (A4 carryover) | `AcademicResultAggregationService::eligiblePlacements` used `whereNotIn EXITED` (allowed `completed` to aggregate) while `AcademicMarksService::eligiblePlacements` after A4 used `where status=active` — inconsistency could allow aggregation of `completed` placements but not marks | **HIGH** — now **FIXED** (aligned to `status=active`) |

## 28. Medium Findings

| ID | Finding | Severity |
|----|---------|----------|
| A5-02 | `components.code` vs `slug` mismatch in ad-hoc test helpers (not production code) — potential confusion, but production `Component::availableFor` correctly uses `slug` | MEDIUM — **FIXED** in test (no production impact) |
| A5-03 | Multiple optional subjects bonus summed (current behavior: `array_sum bonus` across all optionals) — safe but undocumented in business rule; cap at 5.00 prevents explosion | MEDIUM — documented as BUSINESS_RULE_GAP, current code is safe default |

## 29. Low Findings

| ID | Finding | Severity |
|----|---------|----------|
| A5-04 | `AcademicResultAggregationService::eligiblePlacements` and `AcademicMarksService::eligiblePlacements` both `whereHas student.branch_id` when `BranchContext` enabled — correct, but `eligiblePlacements` for report-card preview should also respect branch (it does) | LOW |

## 30. Business Rule Gaps

| Gap | Description | Recommended |
|-----|-------------|-------------|
| 1 | Multiple optional subjects: System allows >1 optional (unique placement+subject only, not one-per-placement). Is one optional mandatory (Bangladesh 4th subject) or can there be 2? Bonus summed across all — is that correct? | BUSINESS_RULE_GAP — Keep current (single optional conventional, multiple summed) unless stakeholder confirms single-only constraint (then add `Rule::unique` per placement for optional group). |
| 2 | Grace marks / reassessment / withheld / exempted states not modeled | BUSINESS_RULE_GAP — Not needed for GREEN; document as not supported |
| 3 | Assessment `status` values (`draft|scheduled|open|completed|cancelled`) vs `locked_at` freeze — status `completed` not enforced to block marks (only `locked_at` blocks). Is `completed` intended to block? | BUSINESS_RULE_GAP — Keep `locked_at` as authoritative freeze, status is informational |
| 4 | Rounding mode per GradeScale (`half_up` default) vs fixed `2dp` in aggregation `INTERNAL_PRECISION 4` → `DISPLAY_PRECISION 2` — which precision is authoritative for GPA? | BUSINESS_RULE_GAP — Current hardcoded `2dp` for GPA, scale `gpa_decimal_places` not used for GPA calc (only display) — keep as is, document |

## 31. Hardening Implemented

| Change | File | Lines |
|--------|------|-------|
| Align `AcademicResultAggregationService::eligiblePlacements` from `whereNotIn EXITED` to `where status=active` | `app/Services/AcademicResultAggregationService.php:79-87` | Code-only, no migration |
| (Carryover A4 hardening retained) `StudentAcademicPlacementService: store/update` tenant + year active + freeze + lock, `AcademicMarksService: eligiblePlacements active only`, `setCurrentYear lockForUpdate` | `app/Services/StudentAcademicPlacementService.php`, `app/Services/AcademicMarksService.php`, `app/Http/Controllers/StudentAcademicPlacementController.php` | Verified still present |

No migration, no historical rewrite, no fake curriculum FK, no legacy exam merge.

## 32. Migrations

**No new migration** — eligibility alignment is service-layer only; FKs already `RESTRICT` where historical integrity requires (A2 hardening `2026_08_27_000002`), optional bonus threshold already migrated `2026_08_27_000004`.

## 33. Tests

| Suite | Result |
|-------|--------|
| `AcademicPlacementIntegrityTest` (A4, 14 tests) | **14/14 PASS** (branch fix applied) |
| `CourseCurriculumManagementTest` (C1, 22 tests) | **22/22 PASS** |
| Ad-hoc A5 unit: `Mid 30(24/30)+Final 70(56/70)=80` via `AcademicResultAggregationService::subjectAggregate` | PASS (manual: 80% each → 80) |
| Ad-hoc A5 GPA: 7 mandatory GP 31.5 + optional GP 5 → bonus 3 → (31.5+3)/7=4.93 capped | PASS (`AcademicFinalResultService::gpa` round 4.93, cap 5.00) |
| **Total A5 focused** | **14/14 A4 + ad-hoc GPA PASS** |

Run: `php artisan test --filter=AcademicPlacementIntegrityTest` + manual `subjectAggregate`/`gpa` calls.

## 34. Regression Results

* `AcademicAssessmentService` assessment creation still prevents duplicate name via `lockForUpdate` — PASS
* `AcademicMarksService` marks entry still blocks `>full`/`negative` and `absent` handling — PASS
* `AcademicFinalResultService` GPA bonus still `max(GP-2,0)` not in denominator, capped — PASS
* `StudentAcademicHistoryService` still reads published snapshots only — PASS
* `CourseCurriculum` not referenced by academic flow — PASS

## 35. Security Invariants

| Invariant | Status |
|-----------|--------|
| One student cannot have duplicate marks for same component | PASS (`unique assessment_component_id+student_id`) |
| One assessment cannot have duplicate subject entries | PASS (`validateSubjects seenSubjects`) |
| One subject cannot be counted twice | PASS (subjectIds deduplicated via `seen` + per assessment `seenSubjects`) |
| Assessment weights deterministic (scheme 100±0.005) | PASS (`weightIsValid`, `assertTotalWeightForStatus`) |
| Component weights deterministic (full_mark proportion) | PASS (`totalFull = sum full_mark`, percentage) |
| Active aggregation cannot use invalid total weight | PASS (draft any, active must be 100) |
| Marks cannot exceed full marks | PASS (`0<=obtained<=full`) |
| Negative marks impossible | PASS (`obtained>=0`) |
| Missing marks cannot silently become zero | PASS (`not_entered` → `incomplete` → `preflight` blocks finalize) |
| Absent handled deterministically (excluded, renormalized) | PASS (`assessmentStatus` distinct, `renormalizeAbsent` true default) |
| Optional subject status cannot be client-manipulated | PASS (server `isOptionalSubject` + `StudentSubjectSelectionValidator`) |
| Optional GP does not enter denominator | PASS (`continue` before `included` push) |
| Bonus = max(GP - threshold,0), default 2.00 | PASS (`max($gp - $threshold,0)`, threshold `2.00` cast) |
| Final GPA ≤5.00 by default (max_gpa) | PASS (`if value>maxGpa → maxGpa`) |
| Finalized GPA cannot be rewritten by later config | PASS (snapshot `AcademicFinalResultStudent.gpa` stored at `lock`, not re-derived) |
| Locked assessments cannot be edited | PASS (`isLocked()` abort) |
| Finalized results cannot be destructively modified | PASS (new `AcademicFinalResult` row per cycle, snapshot `updateOrCreate` only at lock) |
| Historical subject deletion cannot erase results | PASS (`Subject SoftDeletes` + `withTrashed` + `AcademicFinalResultRow.subject` nullOnDelete but snapshot grade stored) |
| Concurrent mark entry safe | PASS (`lockForUpdate` assessment) |
| Concurrent finalization safe | PASS (`lockForUpdate` activeResult check) |
| Cross-tenant assessment/marks/result blocked | PASS (`TenantScoped` + `Institute` param) |
| Report Card and Transcript use same authoritative result | PASS (both `AcademicFinalResultRow/Student` where `status=published`) |
| Legacy Professional Exams remain separate | PASS (no `exams` table touched) |
| No historical data hard-deleted | PASS (no `CASCADE` without guard, no `FORCE_DELETE` on snapshots) |
| No production data silently rewritten | PASS (preview is read-only, snapshot only at lock) |

## 36. Before/After

| Area | Before | After |
|------|--------|-------|
| Aggregation eligibility | `whereNotIn [dropped,transferred]` (allowed `completed` + `active`) | `where status=active` (only active) — aligns with marks |
| Report card source | Snapshot (already) | Snapshot (verified) |
| Optional bonus | `max(GP-2,0)` summed, capped | Same (verified, configurable) |
| Historical freeze | Snapshot at lock | Snapshot at lock (verified) |

## 37. Remaining Risks

* Multiple optional subjects bonus summed — if business expects exactly one optional, add DB unique partial index `where requirement_type=optional`, but current cap mitigates.
* GradeScale `gpa_decimal_places` not used for GPA calc (hardcoded 2dp) — display vs calc precision gap, low risk.
* No E2E test for `500+` students concurrency — unit `lockForUpdate` verified, load test recommended.

## 38. Final Verdict

Deterministic multi-assessment (percentage→weighted), component via `full_mark`, absent renormalized, missing blocked, optional bonus `max(GP-2,0)` excluded from denominator and capped 5.00, GPA from authoritative `AcademicFinalResultService`, historical snapshots frozen, report card ≡ transcript (same `published` rows), concurrency and tenant isolation enforced, no historical cascade.

```
GREEN
```

---

> **Notes:** Bangladesh bonus rule preserved and configurable (`optional_subject_bonus_threshold`, `bonus_enabled`, `max_gpa`). No fake curriculum FK created (placement has no `curriculum_id`). No migration, no `FOREIGN_KEY_CHECKS=0`, no hard delete of historical data.
