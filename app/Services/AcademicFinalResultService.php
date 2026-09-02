<?php

namespace App\Services;

use App\Models\AcademicResultAggregationScheme;
use App\Models\GradeScale;
use App\Models\GradeScaleRow;
use App\Models\Institute;
use App\Models\InstituteSubject;
use App\Models\StudentAcademicPlacement;
use App\Models\Subject;
use App\Models\SubjectAcademicAssignment;

/**
 * Final-result derivation from a Step-8 aggregate for one placement.
 *
 * Purely derived / read-only:
 *   - consumes AcademicResultAggregationService::subjectAggregate (Step 8)
 *   - never writes marks, assessments, grades or snapshots
 *
 * Subject final status:
 *   - a compute-eligible subject with an entered aggregate is graded against
 *     the resolved grade scale (aggregate → band → grade / grade_point /
 *     PASS/FAIL from the band's is_pass).
 *   - ENTERED/ABSENT/NOT_ENTERED/ZERO are preserved as-is from Step 8:
 *       * not_eligible / incomplete / absent_only carry through with NO grade
 *       * ZERO is a REAL score → graded normally (a 0-band is expected)
 *   - a missing grade scale or a score outside every band produces NO grade
 *     (never a fabricated pass/fail).
 *
 * GPA:
 *   - mode from the effective scale (credit_weighted | equal_weight)
 *   - inclusion: subject-level effective gpa_included AND band gpa_included
 *     AND optional-subject policy from the scale
 *   - credit_weighted NEVER invents credits: subjects with no declared credit
 *     are non-credit (excluded). If no subject has credits → GPA unavailable
 *     with a reason, not a fabricated value.
 */
class AcademicFinalResultService
{
    public const SUBJECT_RESULT_GRADED = 'computed';

    public const SUBJECT_RESULT_INCOMPLETE = 'incomplete';

    public const SUBJECT_RESULT_ABSENT_ONLY = 'absent_only';

    public const SUBJECT_RESULT_NOT_ELIGIBLE = 'not_eligible';

    public const SUBJECT_RESULT_NO_SCALE = 'no_grade_scale';

    public const SUBJECT_RESULT_NO_BAND = 'no_band';

    public const SUBJECT_STATUS_PASS = 'PASS';

    public const SUBJECT_STATUS_FAIL = 'FAIL';

    public function __construct(
        private readonly AcademicResultAggregationService $aggregations,
        private readonly AcademicGradingService $grading
    ) {}

    /**
     * Graded final result for one subject of one placement.
     *
     * @param  bool|null  $renormalizeAbsent  Absent re-normalization override
     *                                        (policy-driven, Step 10). NULL keeps the aggregation
     *                                        service default (re-normalize on).
     * @param  GradeScale|null  $gradeScaleOverride  Optional per-policy scale
     *                                               override; NULL keeps the normal resolution ladder.
     * @return array<string, mixed>
     */
    public function subjectResult(
        AcademicResultAggregationScheme $scheme,
        StudentAcademicPlacement $placement,
        int $subjectId,
        ?bool $renormalizeAbsent = null,
        ?GradeScale $gradeScaleOverride = null
    ): array {
        $aggregate = $this->aggregations->subjectAggregate($scheme, $placement, $subjectId, $renormalizeAbsent ?? true);

        if ($aggregate['status'] !== AcademicResultAggregationService::SUBJECT_AGGREGATE_COMPUTED) {
            return $this->carryThrough($aggregate, $subjectId);
        }

        $scale = $gradeScaleOverride ?? $this->grading->resolveScaleForClass($placement->institute, $placement->classGrade);

        if ($scale === null) {
            return [
                'status' => self::SUBJECT_RESULT_NO_SCALE,
                'subject_id' => $subjectId,
                'aggregate' => $aggregate['aggregate'],
                'grade' => null,
                'grade_point' => null,
                'subject_status' => null,
                'band' => null,
                'grade_scale' => null,
                'gpa' => $this->gradeGpaSlice(null, $placement, $subjectId, null, $aggregate['aggregate']),
                'incomplete_reason' => null,
            ];
        }

        $band = $this->grading->bandForScore($scale, (float) $aggregate['aggregate']);

        if ($band === null) {
            return [
                'status' => self::SUBJECT_RESULT_NO_BAND,
                'subject_id' => $subjectId,
                'aggregate' => $aggregate['aggregate'],
                'grade' => null,
                'grade_point' => null,
                'subject_status' => null,
                'band' => null,
                'grade_scale' => $this->scaleSummary($scale),
                'gpa' => $this->gradeGpaSlice($scale, $placement, $subjectId, null, $aggregate['aggregate']),
                'incomplete_reason' => 'Aggregate '.$aggregate['aggregate'].' falls outside every configured grade band.',
            ];
        }

        return [
            'status' => self::SUBJECT_RESULT_GRADED,
            'subject_id' => $subjectId,
            'aggregate' => $aggregate['aggregate'],
            'grade' => $band->grade,
            'grade_point' => (float) $band->grade_point,
            'subject_status' => $band->is_pass ? self::SUBJECT_STATUS_PASS : self::SUBJECT_STATUS_FAIL,
            'band' => $band,
            'grade_scale' => $this->scaleSummary($scale),
            'gpa' => $this->gradeGpaSlice($scale, $placement, $subjectId, $band, $aggregate['aggregate']),
            'incomplete_reason' => null,
        ];
    }

    /**
     * Distinguished derivation of per-subject GPA eligibility.
     *
     * @return array<string, mixed>
     */
    private function gradeGpaSlice(
        ?GradeScale $scale,
        StudentAcademicPlacement $placement,
        int $subjectId,
        ?GradeScaleRow $band,
        ?float $aggregate
    ): array {
        $subjectIncluded = $this->grading->effectiveSubjectGpaIncluded($placement->institute, $placement->class_grade_id, $subjectId);
        $bandIncluded = $band !== null && (bool) $band->gpa_included;
        $optionalPolicy = $scale?->optional_subject_gpa ?? GradeScale::OPTIONAL_SUBJECT_GPA_INCLUDED;

        $optional = $this->isOptionalSubject($placement->institute, $placement->class_grade_id, $subjectId);

        $included = $subjectIncluded
            && ($band === null || $bandIncluded)
            && (! $optional || $optionalPolicy === GradeScale::OPTIONAL_SUBJECT_GPA_INCLUDED);

        $credits = $this->grading->effectiveCreditHours($placement->institute, $placement->class_grade_id, $subjectId);

        return [
            'included' => $included && $band !== null,
            'grade_point' => $band !== null ? (float) $band->grade_point : null,
            'credits' => $credits !== null && $credits > 0 ? $credits : null,
            'optional' => $optional,
            'subject_override' => $subjectIncluded,
            'band_included' => $bandIncluded,
            'aggregate' => $aggregate,
            'reason' => ($included && $band !== null)
                ? null
                : $this->gpaExclusionReason($scale, $subjectIncluded, $bandIncluded, $optional, $optionalPolicy, $band),
        ];
    }

    /**
     * Whether a subject is optional for the placement's class according to the
     * effective config (institute override wins over global assignment).
     */
    public function isOptionalSubject(Institute $institute, int $classGradeId, int $subjectId): bool
    {
        $assignment = SubjectAcademicAssignment::query()
            ->where('class_grade_id', $classGradeId)
            ->where('subject_id', $subjectId)
            ->where('status', 'active')
            ->first();

        $override = InstituteSubject::query()
            ->where('institute_id', $institute->id)
            ->where('subject_id', $subjectId)
            ->first();

        $type = $override?->requirement_type ?? $assignment?->requirement_type ?? 'mandatory';

        return in_array($type, [AcademicSubjectService::REQUIREMENT_OPTIONAL, AcademicSubjectService::REQUIREMENT_ELECTIVE], true);
    }

    /**
     * Aggregated GPA for a placement across every covered subject (equal-weight
     * or credit-weighted per the effective scale).
     *
     * @param  bool|null  $renormalizeAbsent  see subjectResult().
     * @param  GradeScale|null  $gradeScaleOverride  see subjectResult().
     * @return array<string, mixed>
     */
    public function gpa(AcademicResultAggregationScheme $scheme, StudentAcademicPlacement $placement, ?bool $renormalizeAbsent = null, ?GradeScale $gradeScaleOverride = null): array
    {
        $scale = $gradeScaleOverride ?? $this->grading->resolveScaleForClass($placement->institute, $placement->classGrade);

        if ($scale === null) {
            return [
                'status' => 'unavailable',
                'value' => null,
                'mode' => null,
                'reason' => 'No grade scale resolved for this class.',
                'subjects' => [],
            ];
        }

        $subjectIds = $this->aggregations->coveredSubjectIds($scheme);

        $included = [];
        $optionalBonus = [];
        $reason = [];
        $threshold = (float) ($scale->optional_subject_bonus_threshold ?? 2.00);
        $bonusEnabled = (bool) ($scale->optional_subject_bonus_enabled ?? true);
        $maxGpa = (float) ($scale->max_gpa ?? 5.00);
        $gpaPrecision = (int) ($scale->gpa_decimal_places ?? 2);
        $roundingMode = $scale->rounding_mode ?? GradeScale::ROUNDING_HALF_UP;
        $multiplePolicy = $scale->multiple_optional_policy ?? GradeScale::MULTIPLE_OPTIONAL_SINGLE;

        foreach ($subjectIds as $subjectId) {
            $result = $this->subjectResult($scheme, $placement, $subjectId, $renormalizeAbsent, $gradeScaleOverride);

            if ($result['status'] !== self::SUBJECT_RESULT_GRADED) {
                if ($result['status'] !== self::SUBJECT_RESULT_NOT_ELIGIBLE) {
                    $reason[] = ucfirst(str_replace('_', ' ', $result['status']));
                }
                continue;
            }

            $gpa = $result['gpa'];
            $isOptional = $this->isOptionalSubject($placement->institute, $placement->class_grade_id, $subjectId);

            // Optional subject handling with bonus threshold
            if ($isOptional && $bonusEnabled) {
                // Optional subject: not in denominator, but bonus = max(GP - threshold, 0)
                $gp = (float) $gpa['grade_point'];
                $bonus = max($gp - $threshold, 0.0);
                $optionalBonus[] = [
                    'subject_id' => $subjectId,
                    'grade' => $result['grade'],
                    'grade_point' => $gp,
                    'bonus' => $bonus,
                    'credits' => $gpa['credits'],
                ];
                continue;
            }

            if (! $gpa['included']) {
                if ($gpa['reason'] !== null) {
                    $reason[] = $gpa['reason'];
                }
                continue;
            }

            $included[] = [
                'subject_id' => $subjectId,
                'grade' => $result['grade'],
                'grade_point' => $gpa['grade_point'],
                'credits' => $gpa['credits'],
            ];
        }

        if ($included === [] && $optionalBonus === []) {
            return [
                'status' => 'unavailable',
                'value' => null,
                'mode' => $scale->gpa_mode,
                'reason' => $reason !== [] ? implode('; ', array_values(array_unique($reason))) : 'No subject contributed a grade.',
                'subjects' => $included,
            ];
        }

        // Apply multiple optional policy: single (first only), best (max), sum (all)
        if ($optionalBonus !== [] && $multiplePolicy !== GradeScale::MULTIPLE_OPTIONAL_SUM) {
            if ($multiplePolicy === GradeScale::MULTIPLE_OPTIONAL_BEST) {
                $maxBonus = max(array_column($optionalBonus, 'bonus'));
                $best = null;
                foreach ($optionalBonus as $ob) {
                    if ((float) $ob['bonus'] === (float) $maxBonus) { $best = $ob; break; }
                }
                $optionalBonus = $best !== null ? [$best] : [];
            } else {
                // single — keep first in covered order (deterministic)
                $optionalBonus = [reset($optionalBonus)];
            }
        }

        // If only optional subjects and no mandatory, GPA is bonus only? Treat as unavailable per Bangladesh rule (denominator 0)
        if ($included === [] && $optionalBonus !== []) {
            return [
                'status' => 'unavailable',
                'value' => null,
                'mode' => $scale->gpa_mode,
                'reason' => 'No mandatory subject contributed a grade; optional bonus cannot be calculated alone.',
                'subjects' => $included,
            ];
        }

        $value = null;

        if ($scale->gpa_mode === GradeScale::GPA_MODE_CREDIT_WEIGHTED) {
            $creditSubjects = array_filter($included, fn ($s) => $s['credits'] !== null);
            if ($creditSubjects === []) {
                return [
                    'status' => 'unavailable',
                    'value' => null,
                    'mode' => $scale->gpa_mode,
                    'reason' => 'Credit-weighted GPA requires declared credits; none configured for graded subjects.',
                    'subjects' => $included,
                ];
            }
            $sumWeighted = 0.0;
            $sumCredits = 0.0;
            foreach ($creditSubjects as $s) {
                $sumWeighted += $s['grade_point'] * $s['credits'];
                $sumCredits += $s['credits'];
            }
            $bonusSum = array_sum(array_column($optionalBonus, 'bonus'));
            $value = $this->grading->preciseRound(($sumWeighted + $bonusSum) / $sumCredits, $gpaPrecision, $roundingMode);
        } else {
            $total = 0.0;
            foreach ($included as $s) {
                $total += $s['grade_point'];
            }
            $bonusSum = array_sum(array_column($optionalBonus, 'bonus'));
            $value = $this->grading->preciseRound(($total + $bonusSum) / count($included), $gpaPrecision, $roundingMode);
        }
        // Cap at max_gpa (cap uses same precision, no extra rounding)
        if ($value !== null && $value > $maxGpa) {
            $value = $maxGpa;
        }

        return [
            'status' => 'computed',
            'value' => $value,
            'mode' => $scale->gpa_mode,
            'reason' => $reason !== [] ? implode('; ', array_values(array_unique($reason))) : null,
            'subjects' => $included,
        ];
    }

    // ------------------------------------------------------------- Preview

    /**
     * Backend-computed final-result preview for a scheme: every eligible
     * placement × every covered subject + per-student GPA.
     *
     * @param  bool|null  $renormalizeAbsent  see subjectResult().
     * @param  GradeScale|null  $gradeScaleOverride  see subjectResult().
     * @return array<string, mixed>
     */
    public function preview(AcademicResultAggregationScheme $scheme, ?bool $renormalizeAbsent = null, ?GradeScale $gradeScaleOverride = null): array
    {
        $subjectIds = $this->aggregations->coveredSubjectIds($scheme);
        $subjects = Subject::query()->whereIn('id', $subjectIds)->get()->keyBy('id');

        $rows = [];

        foreach ($this->aggregations->eligiblePlacements($scheme) as $placement) {
            $studentSubjects = [];
            foreach ($subjectIds as $subjectId) {
                $studentSubjects[] = [
                    'subject' => $subjects->get($subjectId),
                    'result' => $this->subjectResult($scheme, $placement, $subjectId, $renormalizeAbsent, $gradeScaleOverride),
                ];
            }

            $rows[] = [
                'placement' => $placement,
                'student' => $placement->student,
                'subjects' => $studentSubjects,
                'gpa' => $this->gpa($scheme, $placement, $renormalizeAbsent, $gradeScaleOverride),
            ];
        }

        return [
            'subjects' => $subjects->values(),
            'rows' => $rows,
            'weights_valid' => $scheme->weightIsValid(),
            'total_weight' => $scheme->totalWeight(),
        ];
    }

    // ------------------------------------------------------------- Internals

    private function carryThrough(array $aggregate, int $subjectId): array
    {
        return [
            'status' => $this->carryStatus($aggregate['status']),
            'subject_id' => $subjectId,
            'aggregate' => $aggregate['aggregate'],
            'grade' => null,
            'grade_point' => null,
            'subject_status' => null,
            'band' => null,
            'grade_scale' => null,
            'gpa' => [
                'included' => false,
                'grade_point' => null,
                'credits' => null,
                'reason' => $aggregate['incomplete_reason'],
            ],
            'incomplete_reason' => $aggregate['incomplete_reason'],
        ];
    }

    private function carryStatus(string $aggregateStatus): string
    {
        return match ($aggregateStatus) {
            AcademicResultAggregationService::SUBJECT_AGGREGATE_INCOMPLETE => self::SUBJECT_RESULT_INCOMPLETE,
            AcademicResultAggregationService::SUBJECT_AGGREGATE_ABSENT_ONLY => self::SUBJECT_RESULT_ABSENT_ONLY,
            default => self::SUBJECT_RESULT_NOT_ELIGIBLE,
        };
    }

    private function scaleSummary(GradeScale $scale): array
    {
        return [
            'id' => $scale->id,
            'name' => $scale->name,
            'scope_label' => $scale->scopeLabel(),
            'gpa_mode' => $scale->gpa_mode,
            'optional_subject_gpa' => $scale->optional_subject_gpa,
        ];
    }

    private function gpaExclusionReason(
        ?GradeScale $scale,
        bool $subjectIncluded,
        bool $bandIncluded,
        bool $optional,
        string $optionalPolicy,
        ?GradeScaleRow $band
    ): string {
        if (! $subjectIncluded) {
            return 'Subject-level GPA inclusion is disabled.';
        }
        if ($band === null) {
            return 'No grade band; excluded from GPA.';
        }
        if (! $bandIncluded) {
            return 'The grade band is marked GPA-excluded.';
        }
        if ($optional && $optionalPolicy === GradeScale::OPTIONAL_SUBJECT_GPA_EXCLUDED) {
            return 'Optional subjects are excluded from GPA by the scale policy.';
        }

        return 'Excluded from GPA.';
    }
}
