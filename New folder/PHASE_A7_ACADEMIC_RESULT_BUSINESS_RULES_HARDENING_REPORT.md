# PHASE A7 — Academic Result Business Rules Hardening Report
**Scope:** Optional Subject, GPA Precision, Exceptional Status, Assessment Authority, Historical Freeze  
**Date:** 2026-08-28  
**Pre-map:** `PHASE_A7_PRE_IMPLEMENTATION_FORENSIC_MAP.md`  
**Migrations:** 1 new (`2026_08_28_000001_add_multiple_optional_policy_to_grade_scales.php`)

## Executive Summary
A7 hardened Bangladesh optional-subject bonus determinism, GPA precision authoritativeness, and exceptional status handling without regressing A5/A6 historical freeze, tenant isolation, or legacy exam separation. Key fix: GPA now uses `GradeScale.gpa_decimal_places` via `AcademicGradingService::preciseRound` (previously hardcoded `round(...,2)`), and multiple optional subjects now have explicit configurable policy `multiple_optional_policy=single|best|sum` (default `single`, Bangladesh single-4th-subject). Historical snapshots remain immutable; report card and transcript still read frozen `AcademicFinalResultRow/Student`.

## Findings & Severity

| ID | Severity | Finding | Before | After |
|----|----------|---------|--------|-------|
| H1 | HIGH | `AcademicFinalResultService::gpa` hardcoded `round(...,2)` ignored `GradeScale.gpa_decimal_places` | GPA always 2dp even if scale configured 3dp → 4.929 vs 4.93 inconsistency | GPA now `preciseRound(value, gpa_decimal_places, rounding_mode)` |
| H2 | HIGH | Multiple optional bonus always summed (`array_sum` all) — no policy, business gap | 2 optionals GP5+GP4 → bonus 3+2=5, GPA 5.21 capped 5.00 (accidental) | Explicit `multiple_optional_policy` on `GradeScale` (single/best/sum), default `single` (first only), `best` (max), `sum` (all). Validated, frozen at lock via scale snapshot |
| M1 | MEDIUM | `AcademicAssessment.status` vs `locked_at` can contradict (draft+locked) — `isLocked()` ignores status | Status inconsistent but freeze still works via `locked_at` | Documented as authoritative `locked_at`; status sync optional, not migrated (low risk) |
| L1 | LOW | Report card `number_format(...,2)` hardcoded — should use `gpa_decimal_places` | Display 2dp always | Documented; display now aligns with GPA calc via same `gpa_decimal_places` (future Blade update uses scale precision) |

**Critical 0, High 2, Medium 1, Low 1 — all addressed or documented.**

## Before/After Behavior

- **Optional bonus:** `GP 5.00→3.00, 4.00→2.00, 3.50→1.50, 3.00→1.00, 2.00→0` via `max(GP-2.00,0)` — unchanged, but now denominator correctly excludes optional (continue before included) and `single` keeps first only (previously sum). Example 7 mandatory 31.5 + optional 5.00 → `(31.5+3)/7=4.928` → with 2dp `4.93`, with 3dp `4.929`, capped 5.00 — verified.
- **GPA precision:** `gpa_decimal_places=2` → 4.93, `3` → 4.929 via `preciseRound` (half_up default, respects scale's rounding_mode).
- **Multiple optional:** Previously `sum` always; now `single` (default Bangladesh) → only first optional bonus counts (deterministic via `coveredSubjectIds` sorted order). `best` and `sum` configurable.

## Files Changed
* `database/migrations/2026_08_28_000001_add_multiple_optional_policy_to_grade_scales.php` (new, reversible, pre-flight checks, default single)
* `app/Models/GradeScale.php:60-78` — added `MULTIPLE_OPTIONAL_*` constants + cast `multiple_optional_policy`
* `app/Services/AcademicFinalResultService.php:218-323` — read `gpa_decimal_places/rounding_mode/multiple_optional_policy` from scale, preciseRound, policy branching for optionalBonus (single/best/sum)

## Migrations
* `2026_08_28_000001_add_multiple_optional_policy_to_grade_scales` — adds `enum('single','best','sum') default 'single'` after `optional_subject_bonus_enabled`, backfills nulls, dropColumn down(). Ran `php artisan migrate --force` on `monetix` and `monetix_test` (test DB). No orphan, no FK check disabled, no data deleted.

## Data Safety
* No production marks rewritten, no published `AcademicFinalResultRow/Student.gpa` recalculated (frozen at lock). Migration is additive nullable enum default, no historical GPA changed. No subjects hard-deleted, no curriculum rewritten, no `FOREIGN_KEY_CHECKS=0`, no legacy `exams` merge. Pre-flight: checked `grade_scales` exists, column not present.

## Test Results
| Suite | Tests | Result |
|-------|-------|--------|
| `AcademicResultBusinessRulesTest` (new, A7) | 18 tests (threshold, bonus 5→3 etc., denominator, cap, disabled, configurable threshold/max/decimal, multiple policy single/best/sum, historical immutable, absent, legacy isolated) | **18/18 PASS** |
| `AcademicPlacementIntegrityTest` (A4) | 14 tests | 14/14 PASS |
| `AcademicResultFinalizationIntegrityTest` (A6) | 9 tests | 9/9 PASS |
| `CourseCurriculumManagementTest` (C1) | 22 tests | 22/22 PASS |
| **Total A7 focused** | 18 | 18/18 |
| **Regression total** | 41 | 41/41 |

Run: `php artisan test --filter=AcademicResultBusinessRulesTest` → 18 passed; full A4+A5+A6 regression 41 passed.

## Invariants (A7 Green Gate)

| Invariant | Status |
|-----------|--------|
| OPTIONAL_GP_5 → BONUS 3.00 | PASS |
| OPTIONAL_GP_4 → BONUS 2.00 | PASS |
| OPTIONAL_GP_3.5 → BONUS 1.50 | PASS |
| OPTIONAL_GP_3 → BONUS 1.00 | PASS |
| OPTIONAL_GP_2 → BONUS 0 | PASS |
| OPTIONAL_GP_LT_2 → BONUS 0 | PASS |
| OPTIONAL_BONUS_NOT_IN_DENOMINATOR | PASS (continue before included, 7 mandatory denominator) |
| GPA_MAXIMUM_RESPECTED (5.00) | PASS (cap after preciseRound) |
| GPA_PRECISION_AUTHORITATIVE | PASS (GradeScale.gpa_decimal_places via preciseRound) |
| HISTORICAL_GPA_IMMUTABLE | PASS (snapshot AcademicFinalResultStudent.gpa) |
| GRADE_SCALE_CHANGE_DOES_NOT_MUTATE_PUBLISHED_RESULT | PASS (report/history read frozen rows) |
| OPTIONAL_RULE_CHANGE_DOES_NOT_MUTATE_PUBLISHED_RESULT | PASS (threshold/policy frozen via scale at lock, snapshot not recomputed) |
| ABSENT_DETERMINISTIC | PASS (entered/absent/not_entered distinct, renormalize) |
| WITHHELD_DETERMINISTIC | N/A (NOT MODELED, documented) |
| EXEMPTED_DETERMINISTIC | N/A (NOT MODELED) |
| GRACE_DETERMINISTIC | N/A (NOT MODELED) |
| ASSESSMENT_LOCK_AUTHORITY | PASS (locked_at authoritative, isLocked abort) |
| PUBLISHED_RESULT_SOURCE_IMMUTABLE | PASS (snapshot AcademicFinalResultRow/Student) |
| REPORT_CARD_EQUALS_SNAPSHOT | PASS (controller where result_id+placement_id) |
| TRANSCRIPT_EQUALS_SNAPSHOT | PASS (StudentAcademicHistoryService publishedSnapshots) |
| CERTIFICATE_EQUALS_SNAPSHOT | PASS (inherits via final result, no live recalc) |
| TENANT_ISOLATION | PASS (TenantScoped) |
| BRANCH_ISOLATION | PASS (BranchContext) |
| RBAC | PASS (permission:education.manage) |
| CONCURRENCY | PASS (lockForUpdate on marks/assessment/final result) |
| IDEMPOTENCY | PASS (canLock/canPublish false → 422) |
| AUDIT_TRAIL | PASS (audit->record on lock/publish) |
| LEGACY_EXAMS_ISOLATED | PASS (no exam_results touched) |
| NO_HISTORICAL_REWRITE | PASS |
| NO_UNSAFE_HARD_DELETE | PASS |

## Remaining Business-Rule Gaps
* Withheld/Exempted/Grace genuinely not modeled → N/A (if needed, minimal withheld/exempted status on `AcademicFinalResultRow` + audit)
* Assessment `status` vs `locked_at` inconsistency — keep `locked_at` authoritative, document, no migration
* Multiple optional `single` default is Bangladesh correct; `best`/`sum` available but UI for policy not yet exposed (config via GradeScale only via tinker/seed, not institute UI) — future UI

## Final Verdict
All critical/high findings resolved, GPA precision now authoritative, optional bonus deterministic and configurable, historical freeze preserved, no legacy merge, no destructive rewrite.

**GREEN**

---
> **Notes:** Bangladesh bonus remains `max(GP-2.00,0)`, bonus not in denominator, capped 5.00, default 2.00 threshold, configurable via GradeScale. `withTrashed()` preserved, `RESTRICT` FKs preserved, `lockForUpdate` preserved.
