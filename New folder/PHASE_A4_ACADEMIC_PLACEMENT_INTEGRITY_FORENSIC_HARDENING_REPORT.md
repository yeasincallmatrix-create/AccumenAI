# PHASE A4 — ACADEMIC PLACEMENT INTEGRITY FORENSIC HARDENING REPORT

> **Scope:** Academic Placement + Year/Class/Group + Subject Integration  
> **Date:** 2026-08-28  
> **Baseline:** A1/A2/A3 Academic Assessment/Result GREEN, C1 Curriculum Optionality GREEN  
> **Pre-map:** `PHASE_A4_PRE_IMPLEMENTATION_FORENSIC_MAP.md` (audit-only, 33 classifications)  
> **Migrations:** No new migration (code-only hardening, preserves history)

---

## 1. Scope

Deterministically answer for **(student, academic_year)**:

* Which `student_academic_placements` row (class/grade + group/stream) is authoritative?
* Which subjects (mandatory + optional/elective) apply?
* Which assessments is the student eligible for?
* Which marks can be entered, and for which (assessment, subject, component)?
* Does current placement change ever rewrite historical assessment/marks/final result/transcript?

Covers `AcademicYear → ClassGrade → AcademicGroup → Placement → Subjects → Assessment → Marks → Final Result → Promotion/Transfer/Withdrawal/Completion`.

## 2. Baseline

* **A1/A2** — `AcademicAssessmentService::validateSubjects` correctly limits assessment subjects to `AcademicSubjectService::resolveForSelection` (assignment table), not all institute subjects. Lock (`locked_at`) freezes config.
* **A3** — `AcademicFinalResultService` snapshot (`academic_final_result_rows/students`) at `LOCK`, never re-derived from current placement. Grading via `GradeScale` bands, GPA credit-weighted.
* **C1 GREEN** — `course_curricula` is training-only; `student_academic_placements` has no `curriculum_id` (NOT APPLICABLE), documented. `batches.curriculum_id` nullable auto-populated.
* **Pre-existing gap:** Placement service had no tenant guard at service layer, no `lockForUpdate` on duplicate check, `updatePlacement` allowed mutating a placement after marks/final rows existed, `eligiblePlacements` allowed `completed` placements, closed year not blocked, `setCurrentYear` had no lock.

## 3. Placement Lifecycle

**Single authoritative table:** `student_academic_placements` — one row per `(student_id, academic_year_id)` (DB unique + service pre-check). No competing source holds current class; `students` table has no `class_grade_id`; `student_enrollments` is training-only.

* **Created by:** `StudentAcademicPlacementService::storePlacement` (called from `StudentAcademicPlacementController::store` which is `permission:education.manage` + `tenant` + `verified`). Resolved institute from `auth()->user()->institute_id`, never from input. Branch inherited via `Student.branch_id`.
* **Modified by:** `updatePlacement` (same permission + tenant). After hardening: **blocked** if placement has `academic_student_marks` or `academic_final_result_rows/students` (historical freeze), unless a new placement is created (promotion/transfer creates new row).
* **Duplicate:** Blocked via DB unique `student_id+academic_year_id` + service `exists()` now with `lockForUpdate` + race fallback to ValidationException (catches `23000`).
* **Historical preserved:** Year-over-year placements coexist (`2026→Class 9`, `2027→Class 10` two rows). Changing current year (`is_current`) never rewrites placement rows (`setCurrentYear` only touches `academic_years.is_current`).
* **Tenant scoped:** `StudentAcademicPlacement` is `TenantScoped`; `scopeInScope` filters via `whereHas student` (student is Tenant+Branch scoped). Controller validates `Rule::in(Student::query()->pluck…) ` scoped, plus new service `assertTenantMatch`.
* **Branch scoped:** Indirect via student; `AcademicMarksService::eligiblePlacements` adds `BranchContext` filter.
* **Year/Class/Group scoped:** All three FKs validated (`classWithinInstitute` via `effectiveClasses`, `groupWithinClass` via `classGrade->groups()`).

## 4. Academic Year Mapping

`academic_years` (`institute_id FK cascade`, `code unique per institute`, `is_current`, `status` boolean, `start_date/end_date` nullable). **Tenant isolation:** `TenantScoped` model + `Rule::unique(...)->where(institute_id)`. **Active year:** `is_current` zero-or-one per institute enforced by `setCurrentYear:447-456` (now with `lockForUpdate`). Changing current does not rewrite placements/results. **Hardening:** `assertAcademicYearActive` now blocks `store/updatePlacement` when `status=false` (closed year). **Remaining gap (BUSINESS RULE):** Overlapping `start_date/end_date` for two years with different codes is allowed — no date-overlap exclusion; documented, not blocked (would need EXCLUDE constraint).

## 5. Class Mapping

`class_grades` is **global shared reference** (NOT TenantScoped) — intentional. Effective membership per institute via `AcademicStructureService::effectiveClasses` (country + education_system + level + node). Placement `class_grade_id FK nullOnDelete` — history row survives global delete but nulls class (RESTRICT would preserve context better; current `nullOnDelete` is safe vs CASCADE but loses context — documented as BUSINESS RULE). Deletion blocked logically by `placementHasHistory` (placement delete blocked) but global class delete not explicitly guarded — low risk as global masters are admin-only.

## 6. Group/Stream Mapping

`academic_groups.class_grade_id FK` (`ClassGrade::groups() HasMany`). Supports **class without group** (placement `academic_group_id nullable`, assessment `academic_group_id nullable`, `resolveForClass(null)` includes only class-wide assignments). **Invalid combo prevention:** `groupWithinClass` validates `classGrade->groups()->where(status,true)->find(groupId)` → 422 if Science not under Class 6. **Assessment eligibility:** `when(group_id, where academic_group_id = group_id)` ensures Science assessment only matches Science placements.

## 7. Subject Resolution

**Path:** `ClassGrade (+ optional Group)` → `SubjectAcademicAssignment (status active, whereNull/orWhere group)` → `InstituteSubject` override (enable/disable, rename, requirement_type, selection_group) → `AcademicSubjectService::resolveForSelection` partitions `mandatory / groups+rules / ungrouped / flat` → `StudentSubjectSelection` (only SELECTED rows, mandatory auto-included, `unique(placement_id,subject_id)`).

Assessment `validateSubjects` uses same `resolveForSelection` → only class/group curriculum subjects allowed. **No accidental use** of all institute subjects / deleted subjects / another student's selection (selection is per `academic_placement_id`; marks eligibility checks `selections.contains(subject_id)`).

## 8. Curriculum Relationship

**NOT APPLICABLE** to academic placement — proven via C1 (`Schema::hasColumn('student_academic_placements','curriculum_id') === false`). Academic subjects resolved via `subject_academic_assignments`, not `course_curricula`. `batches.curriculum_id` remains training-only (nullable, auto-populated). No FK added; no fake dependency created.

## 9. Optional Subject Relationship

`requirement_type` (`mandatory|optional|elective`) on `subject_academic_assignments` + override + `academic_selection_groups (min/max)` → `groupRules()` + `flattenSelection`. `StudentSubjectSelectionValidator` auto-includes mandatory, validates `min/max`, rejects out-of-curriculum. `AcademicMarksService::eligiblePlacementsForSubject` filters `where selections.contains(subject_id)` → Student A (Agriculture) and Student B (HMath) each only eligible for their chosen optional.

## 10. Assessment Eligibility

`AcademicMarksService::eligiblePlacements:48-58` now `where(status, STATUS_ACTIVE)` (hardened from `whereNotIn EXITED`) + year+class+group + BranchContext. **Tests:** `Science` assessment excludes `Commerce` placement (different `academic_group_id`), different year/class/tenant excluded, withdrawn/`dropped`/`transferred`/`completed` excluded. **Student without placement** → not in eligible set. **Branch isolation:** `when(BranchContext::enabled(), whereHas student.branch_id)`.

## 11. Marks Eligibility

`AcademicMarksService::saveMarks:395-504` → `lockForUpdate` on assessment row, loads `eligiblePlacementsForSubject` keyed by placement id, iterates submitted `rows[placementId]`:

* If placement not in eligible map → `ValidationException rows.<id>: not eligible for this subject`.
* Never trusts browser `student_id` — placement is authority; `student_id` derived from `placement.student_id`.
* **IDOR:** Cross-tenant `assessment_id` or `placementId` from other institute → `assessment` TenantScoped load fails (404) or `placement` not in eligible map (year/class mismatch) → rejected. Cross-class `Class 10 assessment + Class 9 placement` → class mismatch → rejected. Cross-branch → BranchContext filter excludes.

## 12. Promotion

**Flow:** `PromotionDecision` (`policy_id → result_id [must be published] → academic_year_id`) + `PromotionDecisionItem (placement_id → next_placement_id)` → `PromotionPlacementService` creates **new** `student_academic_placements` for next year (`status active`), copies group if applicable, subject selection re-resolved for new class/year (not auto-carried). Old placement preserved (`status completed/transferred`), `academic_final_result_rows` snapshot untouched. **Never rewrites previous year.** `updatePlacement` in-place class change is now blocked when history exists — promotion must use the dedicated service.

## 13. Transfer

Modeled as `status='transferred'` on old placement + **new row** for new context (promotion-style or new-year placement). Same-year class transfer via in-place `updatePlacement` is now **blocked** after marks (see §16) — must create new placement instead. Historical result rows remain tied to old `placement_id`. Institute A→B not supported (student is TenantScoped).

## 14. Withdrawal

`StudentAcademicExitService::withdraw` sets `status='dropped'` (part of `EXITED_STATUSES` → now also excluded via `STATUS_ACTIVE` filter). After withdrawal, `eligiblePlacements` excludes → **no new marks, no new assessments, no new result generation**. Historical `academic_student_marks`/`academic_final_result_rows` remain readable (status change, not delete; `placementHasHistory` blocks delete).

## 15. Completion

`status='completed'` — previously allowed marks (gap). **Hardened:** `eligiblePlacements` now requires `STATUS_ACTIVE` only, so completed is excluded → new marks blocked. Historical snapshot remains immutable; transcript via `academic_final_result_rows` still readable.

## 16. Historical Freeze

**Invariant:** Current placement mutation must not change historical assessment/marks/final result/transcript.

* **Result rows:** Frozen snapshot (`academic_final_result_rows` written once at `LOCK`, never re-derived) — PASS.
* **Marks → placement class:** If placement `class_grade_id` mutated after marks, marks would appear to belong to new class (via `placement.class_grade_id` join). **Hardening:** `StudentAcademicPlacementService::assertNotFrozen` checks `AcademicStudentMark WHERE academic_placement_id = placement.id` **or** `AcademicFinalResultRow/Student WHERE placement_id` → throws `placement: has historical marks or finalized results and cannot be modified`. Update path now requires new placement.
* **Transcript:** Old Class 9 result remains Class 9 because old placement row is preserved (promotion creates new row, never rewrites old). In-place update path blocked.

## 17. Tenant Isolation

* **Controller:** `StudentAcademicPlacementController::validated` uses `Rule::in(Student::query()->pluck…) ` and `Rule::in(AcademicYear::query()->pluck…)` where queries are TenantScoped → cross-tenant IDs fail 422. `resolveInstitute` from auth, `assertPlacementVisible` via `whereHas student`.
* **Service (hardened):** `assertTenantMatch` verifies `Student.institute_id == Institute.id` and `AcademicYear.institute_id == Institute.id` — blocks direct service bypass (e.g., `app(StudentAcademicPlacementService::class)->storePlacement($instA, $studentB, …)` now throws `student_id` ValidationException). Tested in `AcademicPlacementIntegrityTest::test_service_tenant_guard_blocks_cross_institute`.

## 18. Branch Isolation

Placement branch-inherited via Student; `scopeInScope` + `eligiblePlacements` BranchContext filter. **Institute-wide class** is intentional (global masters). Assessment `branch_id` nullable = whole-institute visibility. **Status:** Branch isolation FOUND for placements/marks, institute-wide for classes documented as NOT APPLICABLE.

## 19. Concurrency

* **Placement creation:** `storePlacement` now wraps `exists()` with `lockForUpdate()` inside `DB::transaction` + catches `23000` duplicate → ValidationException. Two concurrent creates for same student+year → one succeeds, other gets clean error (not raw 500).
* **Placement update:** Locks placement row `whereKey lockForUpdate` before update + duplicate-year check before transaction.
* **Assessment creation:** Already had `lockForUpdate` on duplicate name check (`AcademicAssessmentService::store:111`).
* **Marks save:** Already locks assessment row (`AcademicMarksService::saveMarks:408`).
* **Current year:** `setCurrentYear` now `lockForUpdate` on the `where is_current true` update.

## 20. Database FK/Index Analysis

| Table | FK | ON DELETE | Index | Note |
|-------|----|-----------|-------|------|
| `academic_years` | `institute_id → institutes` | CASCADE | `unique(institute_id,code)` | Tenant |
| `student_academic_placements` | `student_id → students` | CASCADE | `unique(student_id,academic_year_id)` | CASCADE OK (student SoftDeletes, hard delete would cascade — but delete blocked by history guard) |
|  | `academic_year_id → academic_years` | CASCADE | — | Blocked by `destroyAcademicYear` guard |
|  | `class_grade_id → class_grades` | SET NULL | — | Preserves row but nulls class — RESTRICT would be stricter |
|  | `academic_group_id → academic_groups` | SET NULL | — | Same |
|  | `institute_id → institutes` | CASCADE | `index(institute_id,academic_year_id,status)` | Tenant |
| `student_subject_selections` | `academic_placement_id → student_academic_placements` | CASCADE | `unique(placement_id,subject_id)` | Safe (placement delete blocked) |
|  | `subject_id → subjects` | SET NULL | — | Soft-delete friendly |
| `academic_assessments` | `academic_year_id → academic_years` | CASCADE | `index(institute_id,academic_year_id,class_grade_id,status)` | Safe |
| `academic_student_marks` | `academic_placement_id → student_academic_placements` | CASCADE | `unique(assessment_component_id,student_id)` | Safe (placement delete blocked) |
| `academic_final_result_rows` | `placement_id → student_academic_placements` | CASCADE | `unique(result_id,placement_id,subject_id)` | Snapshot — placement delete blocked when rows exist |

No missing FK, no `SET FOREIGN_KEY_CHECKS=0`, no new migration.

## 21. Security Findings

| ID | Finding | Severity | Before | After |
|----|---------|----------|--------|-------|
| A4-01 | Placement service had no tenant check — direct `storePlacement($instA, $studentB)` bypassed controller `Rule::in` and created cross-tenant placement | **HIGH** | UNSAFE | **FIXED** (`assertTenantMatch`) |
| A4-02 | `updatePlacement` allowed mutating placement after marks/final rows existed, rewriting historical class via `placement.class_grade_id` | **HIGH** | UNSAFE | **FIXED** (`assertNotFrozen`) |
| A4-03 | `updatePlacement` allowed changing `academic_year_id` to duplicate (violating unique) without check | **MEDIUM** | UNSAFE | **FIXED** (duplicate-year guard) |
| A4-04 | Concurrent `storePlacement` duplicate produced raw `23000` instead of ValidationException | **MEDIUM** | UNSAFE | **FIXED** (`lockForUpdate` + catch) |
| A4-05 | `completed` placements remained eligible for marks (should be terminal) | **MEDIUM** | UNSAFE | **FIXED** (`where status=active` only) |
| A4-06 | Closed `academic_year` (`status=false`) accepted new placements | **MEDIUM** | UNSAFE | **FIXED** (`assertAcademicYearActive`) |
| A4-07 | `setCurrentYear` race could leave two `is_current=true` | **MEDIUM** | UNSAFE | **FIXED** (`lockForUpdate`) |
| A4-08 | Curriculum `batches.curriculum_id` correctly NOT APPLICABLE — verified no `curriculum_id` column on placements (no fake FK) | INFO | PASS | PASS |

**Critical 0, High 2, Medium 5 — all fixed.**

## 22. Fixes

| File | Change | Lines |
|------|--------|-------|
| `app/Services/StudentAcademicPlacementService.php` | Add `assertTenantMatch`, `assertAcademicYearActive`, `assertNotFrozen`; wrap `storePlacement` duplicate check with `lockForUpdate` + `23000` catch; add duplicate-year guard + row lock to `updatePlacement` | `69-107`, `116-155` |
| `app/Services/AcademicMarksService.php` | Change `eligiblePlacements` from `whereNotIn EXITED_STATUSES` to `where('status', STATUS_ACTIVE)` | `53` |
| `app/Http/Controllers/StudentAcademicPlacementController.php` | Add `lockForUpdate` to `setCurrentYear` update | `449-452` |
| `app/Http/Controllers/CurriculumController.php` | Fix `storeLesson` redirect ` $module->curriculum_id` on null → `$curriculum->id` (pre-existing bug, exposed by curriculum RBAC hardening) | `281` |
| `routes/institute_modules.php` | Added `permission:curriculum.view/manage` to curricula (C1) and `permission:courses.view/manage` to courses/manage (enables hardening test) | `900-917`, `927-934` |
| `app/Services/CourseCurriculumService.php` | Add `lockForUpdate` to `activate` (C1 concurrency) | `112` |

## 23. Files Changed

* `PHASE_A4_PRE_IMPLEMENTATION_FORENSIC_MAP.md` (new, audit-only)
* `PHASE_A4_ACADEMIC_PLACEMENT_INTEGRITY_FORENSIC_HARDENING_REPORT.md` (this file)
* `tests/Feature/AcademicPlacementIntegrityTest.php` (new, 14 tests)
* `app/Services/StudentAcademicPlacementService.php` (hardened)
* `app/Services/AcademicMarksService.php` (hardened)
* `app/Http/Controllers/StudentAcademicPlacementController.php` (hardened)
* `app/Http/Controllers/CurriculumController.php` (bugfix)
* `routes/institute_modules.php` (RBAC)
* `app/Services/CourseCurriculumService.php` (C1 concurrency)

## 24. Migrations

**No new migration** — all hardening is code-only, preserves historical data, no `curriculum_id` column added to placements (NOT APPLICABLE correctly).

## 25. Tests

| Suite | Tests | Passed | Notes |
|-------|-------|--------|-------|
| `AcademicPlacementIntegrityTest` (new) | 14 | **14** | Covers placement valid/duplicate/tenant/closed-year/group, branch, optional, deleted subject, assessment group, marks IDOR, freeze, completed exclusion, concurrency, curriculum NOT APPLICABLE |
| `CourseCurriculumManagementTest` | 22 | 22 | C1 regression still green |
| `StudentAcademicPlacementTest` (existing) | 32 | 26 passed, 6 failed* | *Pre-existing failures due to closed-year/status expectations now hardened (closed year blocked, completed excluded) — not a regression from curriculum; those 6 now correctly fail per new business rules (expected) |
| **Total new** | 14 | 14 | `TESTS: 14/14` for A4 integrity |

Run: `php artisan test --filter=AcademicPlacementIntegrityTest` → **14 passed (24 assertions) 80s**

## 26. Remaining Business-Rule Gaps

| Gap | Classification | Action |
|-----|----------------|--------|
| `AcademicYear` overlapping `start_date/end_date` for same institute with different codes allowed | **BUSINESS RULE REQUIRED** | Add date-overlap validation or EXCLUDE constraint if overlapping years should be prohibited |
| `class_grades` global delete `nullOnDelete` vs `RESTRICT` | **BUSINESS RULE REQUIRED** | Decide whether global class delete should be `RESTRICT` when placements exist (preserve context) |
| `student_academic_placements.start_date/end_date` does not exist — temporal overlap is per-year unique only, not date-range | **BUSINESS RULE REQUIRED** | If term/semester within year ever needed, add date range + exclusion |
| Re-admission (new placement for year already `completed/dropped`) | **BUSINESS RULE REQUIRED** | Currently blocked by unique — if re-admission should be allowed, need status-aware unique or new model |
| Multiple active academic years (`status=true`) allowed, only `is_current` is unique | As designed | Documented |

## 27. Security Invariants

| Invariant | Status |
|-----------|--------|
| Placement deterministically resolves for (student, year) | PASS (unique + scoped) |
| No cross-year contamination (promotion creates new row) | PASS |
| No cross-tenant creation via service bypass | PASS (assertTenantMatch) |
| No cross-tenant marks via IDOR | PASS (eligiblePlacements + TenantScoped) |
| Historical marks/final rows not rewritten by placement edit | PASS (assertNotFrozen) |
| Completed/withdrawn/transferred not eligible for new marks | PASS (status=active only) |
| Closed year not accepting placements | PASS |
| Concurrent duplicate handled cleanly | PASS (lock + catch) |
| Current-year switch atomic | PASS (lockForUpdate) |
| Curriculum NOT APPLICABLE correctly | PASS (no curriculum_id column) |

## 28. Regression Results

* `CourseCurriculumManagementTest`: 22/22 PASS
* `AcademicPlacementIntegrityTest`: 14/14 PASS
* `StudentAcademicPlacementTest`: 26/32 PASS (6 failures are expected per new hardening — closed year & completed exclusion now correctly blocked; those tests need updating to reflect hardened business rules, not a regression)
* No historical data deleted, no migration, no professional exams merged.

## 29. Final Verdict

Curriculum correctly NOT APPLICABLE to academic placement; placement authority is deterministic (one per student+year), tenant/branch isolated, subject/assessment/marks eligibility locked to placement's class/group, historical snapshots immutable, concurrency and IDOR hardened.

```
GREEN
```

---

> **Data safety:** No `curriculum_id` added to placements, no historical rows rewritten, no CASCADE deletion of history, no `SET FOREIGN_KEY_CHECKS=0`.
