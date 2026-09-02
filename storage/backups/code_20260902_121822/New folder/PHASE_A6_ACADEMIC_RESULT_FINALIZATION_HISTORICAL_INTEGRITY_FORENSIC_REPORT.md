# PHASE A6 — Academic Result Finalization, Publishing & Historical Integrity Forensic Report

## A. Files Inspected
**Models:** `AcademicFinalResult:30` (review→approved→locked→published, ACTIVE_STATUSES, canApprove/canLock/canPublish/hasSnapshot), `AcademicFinalResultPolicy:25` (absent_renormalization, require_approval, grade_scale_id), `AcademicFinalResultRow:35` (subject()->withTrashed), `AcademicFinalResultStudent:NA` (gpa), `AcademicResultAggregationScheme:25` (weightIsValid 100±0.005), `AcademicResultAggregationItem:13` (weight), `AcademicAssessment:23` (locked_at), `AcademicStudentMark:NA` (entered/absent), `GradeScale:28` (threshold 2.00, max_gpa 5.00), `Subject:NA` (SoftDeletes)  
**Services:** `AcademicFinalResultLifecycleService:40` (policyForScheme, createResult lockForUpdate, preview, approve, lock→snapshot, publish, assertAssessmentEditable), `AcademicFinalResultService:39` (subjectResult, gpa bonus, preview), `AcademicResultAggregationService:53` (subjectAggregate percentage→weighted, renormalize), `AcademicMarksService:33` (eligiblePlacements status=active, saveMarks lockForUpdate), `StudentAcademicHistoryService:24` (publishedSnapshots), `AcademicGradingService:36`  
**Controllers:** `AcademicFinalResultController:42` (requireInstitute, report/reportSheet/export use frozen rows, 404 if not published), `AcademicAssessmentController`, `AcademicMarksController`  
**Migrations:** `2026_08_17_160000/160100` (schemes/items), `170000/170100` (scales/rows), `100000-100300` (final results), `000800` (locked_at), `000002` (RESTRICT), `000004` (bonus threshold)  
**Routes/Views:** `institute_modules.php` (`settings/academic/final-results` permission:education.manage), `report-card.blade.php:179` (frozen `$rows/$snapshot->gpa`)

## B. Files Changed
* `app/Services/AcademicResultAggregationService.php:79-87` — `eligiblePlacements` changed from `whereNotIn EXITED=[dropped,transferred]` to `where status=active` (aligns with `AcademicMarksService` post-A4 hardening; `completed` now excluded from both marks and aggregation)
* `tests/Feature/AcademicResultFinalizationIntegrityTest.php` (new, 9 tests covering A6 items 1-18)
* No migration, no historical data rewritten.

## C. Migrations Created
**None** — eligibility alignment is service-layer only; FKs already RESTRICT where needed (A2), bonus threshold already migrated (000004). No `FOREIGN_KEY_CHECKS=0`, no mass rewrite.

## D. Security Findings
* **Tenant isolation:** `AcademicFinalResult`/`Policy`/`Scheme` are `TenantScoped` + `BranchContext` global scopes; `policyForScheme abort_if institute mismatch`, `createResult abort_if activeResult lockForUpdate exists`, controller `requireInstitute` from auth, `grade_scale_id` override validated `where institute_id = policy.institute_id`. Direct service bypass with forged `institute_id/student_id/result_id` blocked via `Institute` param + `assertTenantMatch` pattern (A4) and scoped queries. **PASS**
* **IDOR:** Cross-tenant assessment/marks/result via forged IDs → `eligiblePlacements` map miss → `not eligible` + TenantScoped 404. Report card `abort_if status!=published` + `where result_id+placement_id` snapshot membership check → 404 for foreign placement. **PASS**
* **RBAC:** Final-result routes under `permission:education.manage` (institute_modules), controller `requireInstitute` 404, lifecycle `approve/lock/publish` no separate role beyond education.manage (as intended); branch isolation via `BranchContext` (NULL=whole-institute). **PASS**
* **Mass assignment:** `guarded=[]` but institute/branch never from input (taken from resolved `Institute`/`policy.branch_id`), `created_by/locked_by` from `creatorId(request)` (InstituteUser). **PASS**

## E. Historical Integrity Findings
* **Subject soft-delete:** `AcademicFinalResultRow::subject()->withTrashed()` → historical row remains readable (grade/aggregate stored, name via trashed). **PASS**
* **Grade Scale change:** Snapshot stores `grade/grade_point/gpa` at `LOCK` (`AcademicFinalResultLifecycleService::snapshot` calls `AcademicFinalResultService::gpa` once, stores `AcademicFinalResultStudent.gpa` + `AcademicFinalResultRow.grade/aggregate`). Later `GradeScale` threshold/max_gpa/rows change does not mutate published `gpa` (verified: preview re-computes, published reads frozen `students.gpa`). **PASS**
* **Assessment weight / Course / Class / Student delete:** Assessment `locked_at` blocks `AcademicMarksService::saveMarks` + `AggregationService::destroy` (locked assessment check) + `AcademicFinalResultLifecycleService::assertAssessmentEditable`. Class/Student hard delete blocked by `placementHasHistory` and `RESTRICT` FKs. **PASS**
* **Assessment components/weights changed after lock:** `subjectAggregate` live recompute would change percentage, but published snapshot is frozen — `report()` and `historyService` read `AcademicFinalResultRow.aggregate` (stored), not live `obtained/full`. **PASS**

## F. State-Machine Findings
`draft(review)→approved→locked→published` verified via `AcademicFinalResult:146-172`:
* `canApprove()` → `status==review && require_approval`
* `canLock()` → `status==approved` OR `review && !require_approval && reviewed_at==null`
* `canPublish()` → `status==locked`
* `hasSnapshot()` → `locked_at != null`
* Invalid transitions abort 422 (`AcademicFinalResultLifecycleService:158,208,234`). Repeated lock/publish → `canLock/canPublish false` → 422 (idempotent, no duplicate). Concurrent `createResult` → `where policy+ACTIVE lockForUpdate exists` → one succeeds, other 422. **PASS**

## G. RBAC Findings
* **Calculation/review/lock/publish/view:** All under `permission:education.manage` (final-result group) + `tenant`+`verified`. Ordinary institute user (e.g., `teacher` without education.manage) gets 403 on `storeResult/lock/publish` (verified via `CheckPermission` + `requireInstitute`).
* **Cross-institute publish/modify/lock:** TenantScoped + `abort_if institute mismatch` → 404, not 403 leak. **PASS**

## H. Concurrency Findings
| Operation | Lock | Result |
|-----------|------|--------|
| Mark entry | `AcademicAssessment::whereKey lockForUpdate` in `saveMarks:408` | Duplicate marks → `unique component+student` prevents, last write wins within lock |
| Assessment create | `lockForUpdate` on duplicate name check | Duplicate name → 422 |
| Aggregation scheme create | transaction only (no dup name lock) — low risk | — |
| Final result create | `where policy+ACTIVE lockForUpdate exists` | Only one in-flight per policy |
| Final result lock/publish | transaction + status guard (`canLock/canPublish`) | Second concurrent → 422 |
| Placement create | A4 `lockForUpdate` on student+year | Duplicate → ValidationException |
| Current year switch | `lockForUpdate` on `where is_current` update (A4) | Two currents prevented |

No `lockForUpdate` added unnecessarily. **PASS**

## I. Business-Rule Gaps
1. **Multiple optional subjects:** System allows >1 optional (unique placement+subject only), bonus summed (`array_sum bonus`). Bangladesh conventional is one 4th subject — is multiple allowed? Current summed+cap 5.00 is safe default. **GAP**
2. **Grace/reassessment/withheld/exempted states:** Not modeled (only entered/absent/not_entered). **GAP**
3. **Assessment status vs locked_at:** `status=completed` does not block marks (only `locked_at` does). Is status intended to block? Keep `locked_at` as authoritative. **GAP**
4. **Rounding:** Aggregation `INTERNAL 4dp → DISPLAY 2dp` vs GradeScale `gpa_decimal_places` (display only) — which is authoritative for GPA? Current hardcoded 2dp for GPA, scale display. **GAP**

Do not invent new rules; gaps remain YELLOW only if critical.

## J. Exact Test Results
```
AcademicResultFinalizationIntegrityTest: 9/9 PASS (32 assertions)
 - state machine guards ✓
 - review can lock without approval when policy disabled ✓
 - locked result cannot be modified via marks ✓
 - report card uses frozen snapshot ✓
 - historical result survives subject soft delete ✓
 - concurrent lock is idempotent ✓
 - tenant isolation on final result ✓
 - audit trail on lock ✓
 - legacy exams isolated ✓
```
`AcademicPlacementIntegrityTest: 14/14 PASS` (A4), `CourseCurriculumManagementTest: 22/22 PASS` (C1) still green.

## K. Remaining Risks
* Multiple optional bonus summed — if single-only is required, add partial unique index `where requirement_type=optional`, but cap mitigates.
* GradeScale `gpa_decimal_places` not used for GPA calc (hardcoded 2dp) — document vs fix.
* No E2E load test for 500 concurrent publishes — unit locks verified, load test recommended.

## L. Final Verdict
Result lifecycle is single-authority (`AcademicFinalResultService` calc → `AcademicFinalResultLifecycleService` snapshot at `LOCK` → `AcademicFinalResultRow/Student` frozen → Report Card/Transcript/Promotion/Certificate all read `where status=published` snapshot). Multiple assessments weight to 100±0.005, components via `full_mark`, absent renormalized, missing blocked via preflight, optional bonus `max(GP-2,0)` excluded from denominator and capped 5.00, configurable, historical freeze holds across subject/grade-scale/weight changes, concurrency and tenant isolation enforced, audit trail present, legacy exams untouched, no historical data hard-deleted.

**GREEN**

---
> Data safety: No `exams` merge, no subject hard-delete, no assessment/grade-scale rewrite of published rows, no `FOREIGN_KEY_CHECKS=0`, no mass data rewriting. One service-layer eligibility alignment only.
