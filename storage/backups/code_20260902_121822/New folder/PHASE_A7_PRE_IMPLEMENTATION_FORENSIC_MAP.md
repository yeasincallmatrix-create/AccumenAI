# PHASE A7 PRE-IMPLEMENTATION FORENSIC MAP
## Academic Result Business Rules + GPA Precision + Exceptional Status

> Date: 2026-08-28
> Baseline: S3 (Subject SoftDeletes/RESTRICT), A1-A6 (Assessment/Marks/Aggregation/Final Result GREEN), C1 (Curriculum NOT APPLICABLE)

---

### 1. Scope
Optional subject bonus (Bangladesh 2.00 threshold), multiple optional policy, GPA denominator, max cap, precision/rounding, GradeScale config, absent/withheld/exempted/grace, assessment status vs locked_at, final result authority, snapshot reproducibility, report/transcript/certificate consistency, tenant/branch isolation, concurrency, audit.

### 2. Architecture Map
```
AcademicYear (institute) → ClassGrade (global) → AcademicGroup (global/class) → StudentAcademicPlacement (TenantScoped, unique student+year, status active/completed/transferred/dropped)
  → Curriculum/Subjects: SubjectAcademicAssignment (global class→subject, requirement_type mandatory/optional/elective) + InstituteSubject (override) + AcademicSelectionGroup (min/max) → AcademicSubjectService::resolveForSelection
  → StudentSubjectSelection (placement→subject, withTrashed, unique placement+subject)
  → AcademicAssessment (TenantScoped + Branch, locked_at) → AssessmentSubject (pass_rule) → AssessmentSubjectComponent (full/pass, mandatory_pass)
  → AcademicStudentMark (unique component+student, entered 0..full / absent NULL)
  → AcademicResultAggregationScheme (TenantScoped+Branch, items weight 0..100, weightIsValid 100±0.005) → AcademicResultAggregationItem (assessment weight)
  → AcademicResultAggregationService::subjectAggregate (percentage = obtained/full*100, effective_weight = renormalize? original/Σentered*100 : original, aggregate = Σ percentage*effective/100, 4dp→2dp)
  → AcademicFinalResultService::subjectResult (aggregate→band→grade/point/PASS/FAIL) → gpa (optional bonus, denominator, cap) → preview
  → AcademicFinalResultPolicy (absent_renormalization, require_approval, grade_scale_id override) → AcademicFinalResult (review→approved→locked→published, snapshot at lock)
  → AcademicFinalResultRow (result+placement+subject snapshot, optional flag) + AcademicFinalResultStudent (gpa)
  → Report Card / Transcript (StudentAcademicHistoryService published snapshots) / Certificate / Promotion
```

### 3. Model Map

| Model | File:Line | Table | Key Columns | Tenant/Branch | Notes |
|-------|-----------|-------|-------------|---------------|-------|
| AcademicYear | `app/Models/AcademicYear.php:14` | `academic_years` | `institute_id FK cascade, code unique per institute, is_current, status, start/end` | TenantScoped | is_current zero-or-one lockForUpdate (A4) |
| ClassGrade | `ClassGrade.php:17` | `class_grades` | global, NOT TenantScoped, country/system/level | — | Effective via AcademicStructureService |
| AcademicGroup | `AcademicGroup.php:15` | `academic_groups` | `class_grade_id FK`, global | — | `classGrade->groups()` |
| StudentAcademicPlacement | `StudentAcademicPlacement.php:23` | `student_academic_placements` | `institute_id, student_id, academic_year_id, class_grade_id nullOnDelete, academic_group_id nullOnDelete, status active/completed/transferred/dropped, unique student+year` | TenantScoped + Branch via student | A4 hardened: tenant guard, freeze after marks |
| Subject | `Subject.php:NA` | `subjects` | `subject_type academic/professional, SoftDeletes, status` | — | S3 withTrashed |
| SubjectAcademicAssignment | `SubjectAcademicAssignment.php:11` | `subject_academic_assignments` | `class_grade_id FK, academic_group_id nullable, subject_id FK, requirement_type mandatory/optional/elective, display_order, status` | global | Authoritative |
| InstituteSubject | `InstituteSubject.php:NA` | `institute_subjects` | `institute_id, subject_id, requirement_type override, is_custom, credit_hours, gpa_included, selection_group_id` | Tenant | Override |
| AcademicSelectionGroup | `AcademicSelectionGroup.php:NA` | `academic_selection_groups` | `class_grade_id, academic_group_id nullable, name, code, selection_type, min/max, status` | global | |
| StudentSubjectSelection | `StudentSubjectSelection.php:13` | `student_subject_selections` | `academic_placement_id FK cascade, subject_id nullOnDelete, selection_group_id nullOnDelete, is_mandatory, source, unique placement+subject` | TenantScoped | withTrashed subject |
| AcademicAssessment | `AcademicAssessment.php:23` | `academic_assessments` | `institute_id, branch_id nullable, academic_year_id FK cascade, class_grade_id nullOnDelete, academic_group_id nullOnDelete, name, locked_at/ locked_by, display_order, status draft/scheduled/open/completed/cancelled` | TenantScoped+Branch | Authority locked_at |
| AssessmentSubject | `AssessmentSubject.php:NA` | `assessment_subjects` | `assessment_id FK cascade, subject_id FK, pass_rule total_only/mandatory_components/both, display_order` | — | |
| AssessmentSubjectComponent | `AssessmentSubjectComponent.php:NA` | `assessment_subject_components` | `assessment_subject_id FK cascade, component_id FK, full_mark, pass_mark, mandatory_pass, display_order` | — | |
| Component | `Component.php:NA` | `components` | `institute_id nullable, slug unique per institute, name, status` | global+institute | availableFor |
| AcademicStudentMark | `AcademicStudentMark.php:NA` | `academic_student_marks` | `institute_id, academic_assessment_id FK cascade, assessment_subject_id FK cascade, assessment_component_id FK cascade, student_id, academic_placement_id FK cascade, obtained_mark nullable, status entered/absent, unique component+student` | Tenant? | 0 is entered, null is absent, no row is missing |
| AcademicResultAggregationScheme | `AcademicResultAggregationScheme.php:25` | `academic_result_aggregation_schemes` | `institute_id, branch_id nullable, academic_year_id, class_grade_id, academic_group_id nullable, name, status draft/active/archived, display_order, totalWeight(), weightIsValid()` | TenantScoped+Branch | |
| AcademicResultAggregationItem | `AcademicResultAggregationItem.php:13` | `academic_result_aggregation_items` | `scheme_id FK RESTRICT, academic_assessment_id FK RESTRICT, weight float 0..100, display_order, status active/inactive` | — | |
| AcademicFinalResultPolicy | `AcademicFinalResultPolicy.php:25` | `academic_final_result_policies` | `institute_id, branch_id nullable, scheme_id FK, name, absent_renormalization bool, require_approval bool, grade_scale_id nullable FK, status draft/active/archived` | TenantScoped+Branch | one per scheme |
| AcademicFinalResult | `AcademicFinalResult.php:30` | `academic_final_results` | `institute_id, branch_id nullable, policy_id FK, scheme_id FK RESTRICT, name, status review/approved/locked/published, reviewed_by/at, approved_by/at, locked_by/at, published_by/at, computed_at, ACTIVE_STATUSES=[review,approved,locked]` | TenantScoped+Branch | hasSnapshot via locked_at |
| AcademicFinalResultRow | `AcademicFinalResultRow.php:16` | `academic_final_result_rows` | `result_id FK cascade, placement_id FK cascade, subject_id FK cascade (withTrashed), status computed/incomplete/absent_only/not_eligible/no_scale/no_band, aggregate, grade, grade_point, subject_status PASS/FAIL, gpa_included, credits, optional bool, unique result+placement+subject` | via parent | Snapshot |
| AcademicFinalResultStudent | `AcademicFinalResultStudent.php:NA` | `academic_final_result_students` | `result_id FK, placement_id FK, gpa float, gpa_status computed/unavailable, gpa_mode, gpa_reason, passed/failed_count, unique result+placement` | via parent | Snapshot |
| GradeScale | `GradeScale.php:28` | `grade_scales` | `institute_id nullable (NULL=global), country_id nullable, education_system_id nullable, academic_level_id nullable, name, gpa_mode equal/credit_weighted, optional_subject_gpa included/excluded, display_order, status, marks_decimal_places, percentage_decimal_places, gpa_decimal_places, cgpa_decimal_places, optional_subject_bonus_threshold float (2.00), optional_subject_bonus_enabled bool, max_gpa float (5.00), rounding_mode` | scope ladder | Ladder 1-6 |
| GradeScaleRow | `GradeScaleRow.php:22` | `grade_scale_rows` | `grade_scale_id FK, grade, min_score 0..100, max_score, grade_point >=0, is_pass, gpa_included, display_order, status, no-overlap validated` | — | closed [min,max] |
| Course/Curriculum | `CourseCurriculum.php:16` | `course_curricula` | `institute_id, course_id, version, status draft/active/archived` | TenantScoped | NOT APPLICABLE to academic (C1) |

### 4. Migration Map

| Migration | Table | Key Change |
|-----------|-------|------------|
| `2026_08_17_130000` | `academic_years` | `unique institute_id+code`, `is_current`, `status` |
| `2026_08_17_130100` | `student_academic_placements` | `unique student+year`, `class_group nullable nullOnDelete`, `status` |
| `2026_08_17_130xxx` | `student_subject_selections` | `unique placement+subject`, `withTrashed` |
| `2026_08_17_140200` | `academic_assessments` | `locked_at/By` added `2026_08_21_000800`, `unique institute+year+class+group+name` `2026_08_27_000003` |
| `140300/140400` | `assessment_subjects/components` | `full/pass`, `pass_rule`, `mandatory_pass` |
| `150100` | `academic_student_marks` | `unique component+student`, `entered/absent` |
| `160000/160100` | `aggregation schemes/items` | `scheme_id RESTRICT` (`000002_harden`), `weight float` |
| `170000/170100` | `grade_scales/rows` | `threshold/bonus/max_gpa/decimal places` (`000004` adds `optional_subject_bonus_threshold`, `max_gpa` change), `000002` precision |
| `100000-100300` | `academic_final_results/rows/students/policies` | `review→published`, `snapshot unique`, `RESTRICT scheme_id` |

### 5. Service Map

| Service | File:Line | Responsibility | Tenant/Branch |
|---------|-----------|----------------|---------------|
| AcademicSubjectService | `53:99` | `resolveForClass`, `resolveForSelection` (mandatory/groups/ungrouped/flat) | effectiveClasses via AcademicStructureService |
| StudentAcademicPlacementService | `27:69` | `storePlacement` (tenant guard, year active, lockForUpdate duplicate, 23000 catch), `updatePlacement` (duplicate-year check, assertNotFrozen, lock), `selectionData` | Institute param, student/year tenant match |
| AcademicAssessmentService | `35:91` | `store/update` (requireInstituteYear/Class/Group, validateSubjects vs subjectIdSet, duplicate name lockForUpdate, isLocked guard), `subjectsForSelection` | Institute+Branch param |
| AcademicMarksService | `33:39` | `eligiblePlacements` (status=active + Branch), `eligiblePlacementsForSubject` (selections.contains), `saveMarks` (lockForUpdate assessment, per placement validation, status entered/absent, 0..full, absent rows), `sheet` (derived PASS/FAIL) | BranchContext |
| AcademicResultAggregationService | `53:76` | `eligiblePlacements` (now status=active after A5 fix), `subjectAggregate` (percentage = obtained/full*100 4dp, effective_weight renormalized, aggregate 2dp), `store/update/destroy` (weight 0..100, total 100±0.005 for active, zero-weight rejected, lockForUpdate) | Tenant+Branch |
| AcademicFinalResultService | `39:72` | `subjectResult` (aggregate→scale→band→grade/point, carryThrough for incomplete), `gpa` (optional bonus `max(GP-threshold,0)`, denominator mandatory only, cap max_gpa, credit modes), `preview` | ladder via AcademicGradingService |
| AcademicGradingService | `36:42` | `resolveScale` ladder 1-6, `bandForScore` 2dp closed range, `effectiveCreditHours`, `validateRows` no-overlap, store/update scales | Institute override |
| AcademicFinalResultLifecycleService | `40:50` | `policyForScheme`, `createResult` (preflight gate, activeResult lockForUpdate), `preview`, `approve`, `lock` (snapshot via preview → AcademicFinalResultRow/Student, weightIsValid), `publish` (notify, CGPA), `assertAssessmentEditable` | Tenant+Branch |
| StudentAcademicHistoryService | `24:55` | `forStudent` (placements sorted, publishedSnapshots where status=published, snapshotRows groupBy placement) | Tenant+Branch via parent |
| Promotion* | `PromotionLifecycleService:NA` | Uses published result only | Tenant |

### 6. Controller Map

| Controller | File:Line | Actions | Validation/Authorization |
|------------|-----------|---------|--------------------------|
| StudentAcademicPlacementController | `44:446` | `store/update/destroy`, `setCurrentYear lockForUpdate`, `placementHasHistory` | `permission:education.manage`, requireInstitute, classWithinInstitute, groupWithinClass, assertPlacementVisible |
| AcademicAssessmentController | `NA` | `store/update/destroy/lock/unlock` | `permission:education.manage`, tenant |
| AcademicMarksController | `NA` | `store` marks | `permission:education.manage`, isLocked abort |
| AcademicFinalResultController | `42:138` | `index, policy, updatePolicy, storeResult, show, approve, sendBackToReview, lock, publish, report, resultSheet, export` | `requireInstitute` 404, grade_scale_id owned check, `abort_if status!=published` for report, snapshot membership check, student null 404 |
| AcademicGradingController | `NA` | `store/update` GradeScale | Super admin / institute override scope |

All report/transcript reads go through tenant-scoped parent `AcademicFinalResult where status=published`.

### 7. Route Map

Prefix `settings/academic` (middleware `permission:education.manage` where applicable): `assessments`, `aggregations`, `assessments/{id}/marks`, `final-results` (`storeResult`, `show`, `approve`, `lock`, `publish`, `report`, `result-sheet`, `export`), `placements`, `promotions`. Branch via global scope. No `withoutGlobalScopes` except justified history reads through tenant parent.

### 8. Permission Map

* `education.manage` — placements, assessments, aggregations, marks, final results (institute-owner/admin, branch-manager? Actually branch-manager lacks education.manage per matrix — but placements require it, so branch-manager blocked as intended)
* `promotion.manage` — promotions
* `curriculum.view/manage` — training curricula (C1) — NOT academic
* GradeScale create at global/country/system/level (super admin) vs institute override (institute-owner) — scope columns

### 9. GradeScale Map

**Ladder (most specific wins):** 1 Institute+Level → 2 Institute whole → 3 Level default → 4 System default → 5 Country default → 6 Global default. `status=true` only. Rows `min 0..100, max 0..100, min<=max, point>=0, no overlap` validated.

**Fields used for GPA:** `gpa_mode (equal/credit)`, `optional_subject_gpa (included/excluded)`, `optional_subject_bonus_threshold (2.00 default)`, `optional_subject_bonus_enabled (true)`, `max_gpa (5.00)`, `gpa_decimal_places (2)`, `cgpa_decimal_places (2)`, `rounding_mode (half_up)`. **Current code:** `AcademicFinalResultService::gpa:220-323` reads `threshold/bonusEnabled/maxGpa` from `GradeScale`, but **hardcodes `round(...,2)`** instead of `gpa_decimal_places` (HARDENING REQUIRED). `preciseRound` in `AcademicGradingService` respects scale's decimal but not used in `gpa`.

### 10. Optional Subject Map

* **Classification source:** `subject_academic_assignments.requirement_type` (`mandatory/optional/elective`) + `institute_subjects.requirement_type` override + `academic_selection_groups` (min/max). NOT on assessment_subject or curriculum.
* **Per-student storage:** `student_subject_selections` (placement→subject, `is_mandatory` bool, `source` inherited/customized/custom). Only SELECTED stored, mandatory auto-included via `StudentSubjectSelectionValidator`.
* **Determination at GPA time:** `AcademicFinalResultService::isOptionalSubject:175` re-resolves `assignment + override` → `in_array(optional/elective)`. **Snapshot flag:** `AcademicFinalResultRow.optional bool` stored at lock (414), so later assignment change does not mutate history. **No client trust:** `student_id` not trusted, placement is authority; `optional` flag computed server-side.

### 11. GPA Calculation Trace

1. `subjectAggregate` per subject per placement: `percentage = obtained/full*100` (4dp) → `effective_weight = renormalize? original/Σentered*100 : original` → `aggregate = Σ percentage*effective/100` → `round 2dp` (or `incomplete/absent_only/not_eligible`).
2. `subjectResult`: if not computed → `carryThrough` (no grade). Else `scale = resolveScaleForClass` (or override), `band = bandForScore(aggregate 2dp)`, if no band → `no_band`. Else `grade/point = band.grade/point`, `PASS/FAIL = band.is_pass`.
3. `gpa`: for each covered subject, `subjectResult` → if optional && bonusEnabled → `bonus = max(GP - threshold,0)` pushed to `optionalBonus[]` and **continue (not in denominator)**. Else if not `gpaIncluded` → skip. Else `included[]` push. If `included==[]` → unavailable. **Equal weight:** `(Σ GP + Σ bonus)/count(mandatory)` → round 2dp (hardcoded) → cap max_gpa. **Credit-weighted:** `(Σ GP*credits + bonusSum)/Σ credits` → round 2dp → cap.
* **Denominator:** `count(included)` = mandatory count only (optional excluded when bonus active). **Frozen** into `AcademicFinalResultStudent.gpa` at lock.

### 12. Denominator Trace

* Mandatory subjects where `gpa['included']=true` (subject gpa_included && band gpa_included && (!optional || policy included)). Optional subjects when bonus active are **not** in denominator (continue). When bonus disabled, optional falls through to normal `included` check → if policy `included`, optional would be in denominator (current code). **Test:** 7 mandatory GP 31.5 + optional 5.00 → `count=7`, not 8.
* Failed/absent/missing subjects: `subjectResult` not `computed` → not in `included` nor `optionalBonus` → denominator excludes them (they contribute reason but not count). Exempted/withheld not modeled → currently would be missing/incomplete → excluded.

### 13. Precision/Rounding Trace

* **Calculated:** Internal `4dp` per assessment percentage, contribution `4dp`, final aggregate `2dp half_up` (`AcademicResultAggregationService:379,425,433`). GPA `round(...,2)` hardcoded (should be `gpa_decimal_places`).
* **Stored:** `AcademicFinalResultStudent.gpa` float, `AcademicFinalResultRow.aggregate 5,2`. Snapshot frozen at `2dp`.
* **Displayed:** `report-card.blade.php:190-228` `number_format(aggregate,2)`, `number_format(gpa,2)` — hardcoded `2`, not `gpa_decimal_places`. Export same. **Hardening required:** make authoritative precision from `GradeScale.gpa_decimal_places`.

### 14. Absent Trace

* **Representation:** `academic_student_marks.status = 'absent'`, `obtained_mark NULL`, one row per component (storeAbsent). `assessmentStatus` returns `absent` when all rows are absent and none entered. `subjectAggregate` → absent assessments excluded, `effective_weight` renormalized, `enteredOnly===[] → absent_only`.
* **Calculation:** `absent_only` subject → `carryThrough` → no grade, `gpa` unavailable, report shows “Absent” badge, not numeric, GPA denominator excludes it (not computed). Policy `absent_renormalization` true (default) → remaining entered weights re-scaled to 100%.

### 15. Withheld Trace

* **Currently NOT MODELED** — no `withheld` status on `AcademicFinalResultRow`, `AcademicFinalResultStudent`, `academic_student_marks`, or `AcademicFinalResult`. Search `withheld` yields 0 hits in `app/` except HR. **Business rule gap:** If withheld is required, safest minimal design is `AcademicFinalResultRow.status='withheld'` or `AcademicFinalResultStudent` flag, excluded from GPA, report shows “Withheld”, audit logged, release via new result cycle (not overwrite). Document as `NOT MODELED` → `BUSINESS_RULE_REQUIRED` if needed, but hardening should reuse existing `status` without inventing if not required.

### 16. Exempted Trace

* **NOT MODELED** — no `exempted` column; exempted subject would currently be `not_eligible` (not in selection) or `incomplete` (no marks). Should be explicit `exempted` status excluded from denominator, not affecting pass/fail, no bonus. Reuse `status` enum if needed. **Gap.**

### 17. Grace Trace

* **NOT MODELED** — no `grace_marks` or `grace_status` columns; grace would alter `obtained_mark` vs `grade` only. No audit for grace. **Gap.** Minimal safe design would be `AcademicStudentMark.grace_marks` nullable + audit, frozen at lock, not overwriting raw marks.

### 18. Assessment State Trace

* **Columns:** `academic_assessments.status` (`draft/scheduled/open/completed/cancelled` per model) + `locked_at/locked_by` (added `2026_08_21_000800`). **Authority:** `locked_at` is authoritative freeze (checked in `AcademicMarksService:397 abort_if isLocked`, `AcademicAssessmentService update/destroy abort_if isLocked`, `AcademicResultAggregationService destroy checks locked_at`, `AcademicFinalResultLifecycleService assertAssessmentEditable checks locked/published results`).
* **Contradiction risk:** `status='draft'` but `locked_at` not null could occur (lock does not update `status`). Current `isLocked()` ignores `status`, so `draft+locked` still blocks edits — safe but inconsistent. **Hardening:** Ensure `status` transition aligns or treat `locked_at` as single source.

### 19. Final Result State Trace

`review → approved → locked → published` per `AcademicFinalResult:30` constants + `canApprove/canLock/canPublish/hasSnapshot`. `policyForScheme` ensures one policy per scheme, `createResult` ensures at most one in-flight (`whereIn ACTIVE lockForUpdate`). No `finalized_at` separate column (published is terminal). Repeated lock/publish safe via `canLock/canPublish false` → 422.

### 20. Snapshot Trace

* **Snapshot tables:** `academic_final_result_rows` (`result_id+placement_id+subject_id unique`, `aggregate, grade, point, subject_status, gpa_included, credits, optional`) + `academic_final_result_students` (`result_id+placement_id unique`, `gpa, gpa_status/mode/reason, passed/failed_count`). Written once in `AcademicFinalResultLifecycleService::snapshot:358-420` via `preview` → `updateOrCreate` inside `lock` transaction. After `locked_at`, `renderSnapshot` reads frozen rows, `renderPreview` recomputes live (only for review). **No live recalc after lock.**

### 21. Report Card Trace

`AcademicFinalResultController::report:187` → `abort_if status != published` → `AcademicFinalResultStudent where result+placement` → `AcademicFinalResultRow where result+placement with subject` → view `report-card.blade.php:179-233` renders `row->aggregate/grade/point/credits/optional badge` + `snapshot->gpa`. No `AcademicFinalResultService` call (frozen). **Consistent** with transcript.

### 22. Transcript Trace

`StudentAcademicHistoryService::forStudent:55` → `placements` sorted → `publishedSnapshots where result.status=published` → `snapshotRows where result_id in snapshots` → timeline per placement. Same `AcademicFinalResultRow/Student` source as report card. **Consistent.**

### 23. Certificate Trace

`StudentAcademicCertificateService` not directly inspected, but certificate uses `AcademicFinalResultStudent`? Check `Certificate` model has no `curriculum_id` (C1), and `AcademicFinalResult` publish notifies student; certificate generation likely reads `AcademicFinalResultStudent` snapshot for GPA, not live marks. Preserve.

### 24. Tenant Isolation Trace

Every place query: `StudentAcademicPlacement` `TenantScoped`, `AcademicAssessment` `TenantScoped`, `AcademicResultAggregationScheme` `TenantScoped`, `AcademicFinalResult` `TenantScoped`, all filtered by `institute_id`. Controllers resolve `Institute` from `auth()->institute_id`, never input. `assertTenantMatch` in placement service (A4) blocks direct service bypass. **Pass.**

### 25. Branch Isolation Trace

`AcademicAssessment`/`Scheme`/`FinalResult` have `BranchContext` global scope (`whereNull branch_id or where branch_id = BranchContext::id()`), `eligiblePlacements` adds `whereHas student.branch_id` when BranchContext enabled. Whole-institute (`NULL`) visible to every branch — intentional. **Pass.**

### 26. Concurrency Trace

* Placement create: `lockForUpdate` on `where student+year` + `23000` catch (A4)
* Marks: `lockForUpdate` on `AcademicAssessment::whereKey` in `saveMarks:408`
* Assessment duplicate: `lockForUpdate` on `where institute+year+class+group+name`
* Final result create: `lockForUpdate` on `where policy+ACTIVE exists` (109-115)
* Final result lock: transaction around `snapshot` + `update`
* Current year: `lockForUpdate` on `where institute+is_current`
* Aggregation scheme delete: `lockForUpdate` on scheme row before delete
* No `lockForUpdate` on GradeScale update — low risk

### 27. Existing Tests

* `AcademicPlacementIntegrityTest:14/14 PASS` (A4)
* `CourseCurriculumManagementTest:22/22 PASS` (C1)
* `AcademicResultFinalizationIntegrityTest:9/9 PASS` (A6, state machine, freeze, tenant, audit)
* No dedicated test for `gpa_decimal_places` or multiple optional policy — gap.

### 28. Findings

**CRITICAL:** none

**HIGH:**
* H1: `AcademicFinalResultService::gpa` hardcodes `round(...,2)` and `AcademicResultAggregationService::subjectAggregate` hardcodes `2dp` instead of `GradeScale.gpa_decimal_places/percentage_decimal_places` — violates §H authoritative precision.
* H2: Multiple optional policy currently `sum` of all bonuses with no configuration — business gap (A6) remains, must be made explicit (`single|best|sum`) with default `single` and DB configurability.

**MEDIUM:**
* M1: `AcademicAssessment.status` vs `locked_at` can contradict (draft+locked still blocks via `isLocked()` but status inconsistent).
* M2: Withheld/Exempted/Grace not modeled — if required, need minimal status without destroying marks.

**LOW:**
* L1: Report card display `number_format(...,2)` hardcoded — should use `gpa_decimal_places` after H1 fix.

### 29. Required Changes

1. **GPA precision hardening:** Make `AcademicFinalResultService::gpa` and `AcademicResultAggregationService::subjectAggregate` use `GradeScale.gpa_decimal_places` / `percentage_decimal_places` via `AcademicGradingService::gpaDecimal()` and `preciseRound()`, fallback 2. Store frozen `gpa` with correct precision. Display via same.
2. **Multiple optional policy:** Add `GradeScale.multiple_optional_policy enum('single','best','sum') default 'single'` via migration, validate, apply in `AcademicFinalResultService::gpa`: `single` → only first optional (lowest subject_id or first bonus), `best` → max bonus, `sum` → current sum. Default `single` preserves Bangladesh single-optional. Make configurable at GradeScale level, frozen at lock (snapshot already stores optional flag, but policy must be snapshotted too — add `AcademicFinalResultPolicy.multiple_optional_policy` or read from scale at lock time and freeze via policy snapshot).
3. **Assessment state authority:** Treat `locked_at` as authoritative, optionally sync `status` to `locked`/`completed` on lock, add check `where locked_at not null → isLocked` regardless of status, or add DB constraint `CHECK (status != 'draft' OR locked_at IS NULL)` — minimal: add service validation `if status==draft && locked_at!=null → inconsistency` and sync status on lock.
4. **Withheld/Exempted/Grace:** Document as `NOT MODELED` → `N/A` for invariants unless stakeholder requires; do not add columns unless needed. Ensure `AcademicFinalResultRow.status` enum can represent them later.

### 30. Explicit Non-Changes

* Do not merge `exams`/`exam_subjects`/`exam_results` (Professional) into Academic.
* Do not add `curriculum_id` to placements/assessments (NOT APPLICABLE).
* Do not rewrite historical `AcademicFinalResultRow/Student` GPA.
* Do not hard-delete subjects/curriculum versions.
* Do not use `FOREIGN_KEY_CHECKS=0`.
* Preserve tenant/branch isolation, lockForUpdate, audit logs, `withTrashed()`.
