<?php

namespace App\Services;

use App\Models\AcademicFinalResultPolicy;
use App\Models\AcademicResultAggregationItem;
use App\Models\AcademicResultAggregationScheme;
use App\Models\GradeScale;
use Illuminate\Support\Collection;

/**
 * Step 29 — final-result generation safety / pre-flight.
 *
 * Read-only validation gate layered directly in front of the existing
 * generation pipeline. It does NOT replace or duplicate the aggregation /
 * grading engine (Step 8 / Step 9), does not create a second grading engine,
 * and never publishes, locks or creates final results. It only verifies that
 * the existing pipeline has every input it needs before it is asked to
 * calculate / preview a result.
 *
 * Existing validation that is deliberately NOT duplicated:
 *   - AcademicFinalResultLifecycleService::lock already hard-aborts when the
 *     active weight total is not 100% (mirrored here as a blocking check).
 *   - preview() already resolves the policy override or the scale ladder and
 *     produces NO_SCALE / NO_BAND outcomes for missing grading.
 *
 * BLOCKING (a genuinely invalid configuration): missing policy, missing
 * required assessments, missing required subjects, invalid/zero weights,
 * non-100% weight total, missing grading configuration, no eligible students,
 * invalid academic scope.
 *
 * WARNING (conditions the existing engine already supports): legitimate
 * absences, incomplete/missing student marks, a resolved scale without active
 * bands, a non-active policy. Warnings never block the verdict.
 *
 * Student coverage reuses AcademicFinalResultReadinessService (Step 28)
 * wholesale; no per-student / per-subject / per-assessment queries are added.
 *
 * Institute/branch identity is never taken from request input; callers pass
 * the tenant + branch scoped scheme from route binding.
 */
class AcademicFinalResultPreflightService
{
    public const CHECK_PASS = 'pass';

    public const CHECK_WARNING = 'warning';

    public const CHECK_BLOCKED = 'blocked';

    public function __construct(
        private readonly AcademicGradingService $grading,
        private readonly AcademicResultAggregationService $aggregation,
        private readonly AcademicFinalResultReadinessService $readiness,
    ) {}

    /**
     * Pre-flight report for one scheme.
     *
     * @return array<string, mixed>
     */
    public function preflight(AcademicResultAggregationScheme $scheme): array
    {
        $scheme->load(['academicYear', 'classGrade', 'academicGroup', 'branch']);

        $items = $scheme->items()->where('status', 'active')->get();
        $policy = AcademicFinalResultPolicy::query()->where('scheme_id', $scheme->id)->first();

        $coverage = $this->readiness->forScheme($scheme);

        $scope = $this->scopeChecks($scheme);
        $policyCheck = $this->policyCheck($policy);
        $configuration = $this->configurationChecks($scheme, $items, $policy, $coverage);

        $blocking = [];
        $warnings = [];

        foreach (array_merge($scope, [$policyCheck], $configuration) as $check) {
            if ($check['status'] === self::CHECK_BLOCKED) {
                $blocking[] = $check['label'].': '.$check['reason'];
            } elseif ($check['status'] === self::CHECK_WARNING) {
                $warnings[] = $check['label'].': '.$check['reason'];
            }
        }

        foreach ($coverage['reasons'] as $reason) {
            $warnings[] = $reason;
        }

        return [
            'scheme' => $scheme,
            'scope' => $scope,
            'policy' => $policyCheck,
            'configuration' => $configuration,
            'coverage' => [
                'readiness' => $coverage['readiness'],
                'is_ready' => $coverage['is_ready'],
                'reasons' => $coverage['reasons'],
                'summary' => $coverage['summary'],
                'exceptions' => $coverage['exceptions'],
                'students' => $coverage['students'],
            ],
            'verdict' => [
                'allowed' => $blocking === [],
                'label' => $blocking === []
                    ? 'Final Result Generation Allowed'
                    : 'Final Result Generation Blocked',
                'blocking' => $blocking,
                'warnings' => $warnings,
                'blocking_count' => count($blocking),
                'warning_count' => count($warnings),
            ],
        ];
    }

    // ------------------------------------------------------------- Scope

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scopeChecks(AcademicResultAggregationScheme $scheme): array
    {
        $year = $scheme->academicYear;
        $classGrade = $scheme->classGrade;
        $group = $scheme->academicGroup;
        $branch = $scheme->branch;

        $checks = [
            $this->result(
                $year !== null,
                $this->check(self::CHECK_PASS, 'Academic Year', $year?->name ?? '—', 'The academic year this scheme runs for.'),
                'Academic Year',
                $year?->name ?? '—',
                'The academic year referenced by this scheme does not exist.'
            ),
        ];

        $checks[] = $this->result($classGrade !== null, $this->check(self::CHECK_PASS, 'Class/Grade', $classGrade?->name ?? '—', 'The class/grade this scheme aggregates.'), 'Class/Grade', $classGrade?->name ?? '—', 'The class/grade referenced by this scheme does not exist.');

        $checks[] = $scheme->academic_group_id === null
            ? $this->check(self::CHECK_PASS, 'Academic Group', 'Not applicable', 'This scheme aggregates the whole class (no group).')
            : $this->result($group !== null, $this->check(self::CHECK_PASS, 'Academic Group', $group?->name ?? '—', 'The academic group this scheme aggregates.'), 'Academic Group', $group?->name ?? '—', 'The academic group referenced by this scheme does not exist.');

        $checks[] = $scheme->branch_id === null
            ? $this->check(self::CHECK_PASS, 'Branch', 'Institute-wide', 'This scheme covers every branch of the institute.')
            : $this->result($branch !== null, $this->check(self::CHECK_PASS, 'Branch', $branch?->name ?? '—', 'The branch this scheme is scoped to.'), 'Branch', $branch?->name ?? '—', 'The branch referenced by this scheme does not exist.');

        return $checks;
    }

    /**
     * @return array<string, mixed>
     */
    private function policyCheck(?AcademicFinalResultPolicy $policy): array
    {
        if ($policy === null) {
            return $this->check(
                self::CHECK_BLOCKED,
                'Policy',
                'Missing',
                'No final-result policy exists for this scheme. A policy is created when the cycle is started from the policy page.'
            );
        }

        $check = $this->check(
            self::CHECK_PASS,
            'Policy',
            $policy->name,
            'Final-result configuration for this scheme.'
        );

        if ($policy->status !== AcademicFinalResultPolicy::STATUS_ACTIVE) {
            return $this->check(
                self::CHECK_WARNING,
                'Policy',
                $policy->name,
                'The policy is not in the active state; generation still works but review it before use.'
            );
        }

        return $check;
    }

    // ------------------------------------------------------------- Configuration

    /**
     * @param  Collection<int, AcademicResultAggregationItem>  $items
     * @param  array<string, mixed>  $coverage
     * @return array<int, array<string, mixed>>
     */
    private function configurationChecks(
        AcademicResultAggregationScheme $scheme,
        $items,
        ?AcademicFinalResultPolicy $policy,
        array $coverage
    ): array {
        $checks = [];

        if ($items->isEmpty()) {
            $checks[] = $this->check(
                self::CHECK_BLOCKED,
                'Required Assessments',
                '0',
                'No required assessments are configured for this scheme; add assessments and weights before generating.'
            );
        } else {
            $checks[] = $this->check(
                self::CHECK_PASS,
                'Required Assessments',
                (string) $items->count(),
                'Every scheme item participates in the aggregate.'
            );
        }

        $checks[] = $this->weightsCheck($scheme, $items);

        $subjectIds = $items->isEmpty() ? [] : $this->aggregation->coveredSubjectIds($scheme);

        if ($items->isEmpty() || $subjectIds === []) {
            $checks[] = $this->check(
                self::CHECK_BLOCKED,
                'Required Subjects',
                '0',
                'No required subjects are covered by the scheme\'s assessments.'
            );
        } else {
            $checks[] = $this->check(
                self::CHECK_PASS,
                'Required Subjects',
                (string) count($subjectIds),
                'Every covered subject is graded from the assessment aggregate.'
            );
        }

        $checks[] = $this->gradingCheck($scheme, $policy);

        $eligible = (int) ($coverage['summary']['eligible_students'] ?? 0);
        if ($eligible === 0) {
            $checks[] = $this->check(
                self::CHECK_BLOCKED,
                'Eligible Students',
                '0',
                'No students are placed in this scheme\'s scope (year + class/grade + group).'
            );
        } else {
            $checks[] = $this->check(
                self::CHECK_PASS,
                'Eligible Students',
                (string) $eligible,
                'Placed students eligible for final-result generation.'
            );
        }

        return $checks;
    }

    /**
     * @param  Collection<int, AcademicResultAggregationItem>  $items
     * @return array<string, mixed>
     */
    private function weightsCheck(AcademicResultAggregationScheme $scheme, $items): array
    {
        if ($items->isEmpty()) {
            return $this->check(self::CHECK_BLOCKED, 'Assessment Weights', '0%', 'No assessments are configured, so there is nothing to weight.');
        }

        foreach ($items as $item) {
            if ((float) $item->weight <= 0) {
                return $this->check(
                    self::CHECK_BLOCKED,
                    'Assessment Weights',
                    $item->weight.'%',
                    'Assessment "'.($item->assessment?->name ?? ('#'.$item->academic_assessment_id)).'" has an invalid weight of '.$item->weight.'%.'
                );
            }
        }

        $total = $scheme->totalWeight();

        if (! $scheme->weightIsValid()) {
            return $this->check(
                self::CHECK_BLOCKED,
                'Assessment Weights',
                $total.'%',
                'Configured weights total '.$total.'%; they must total 100% before a final result can be generated.'
            );
        }

        return $this->check(self::CHECK_PASS, 'Assessment Weights', $total.'%', 'Configured weights total 100%.');
    }

    /**
     * @return array<string, mixed>
     */
    private function gradingCheck(AcademicResultAggregationScheme $scheme, ?AcademicFinalResultPolicy $policy): array
    {
        $classGrade = $scheme->classGrade;

        if ($classGrade === null) {
            return $this->check(self::CHECK_BLOCKED, 'Grading Configuration', '—', 'A valid class/grade is required before a grade scale can be resolved.');
        }

        $scale = null;

        if ($policy !== null && $policy->grade_scale_id !== null) {
            $scale = GradeScale::query()
                ->where('id', $policy->grade_scale_id)
                ->where('institute_id', $scheme->institute_id)
                ->where('status', true)
                ->with('rows')
                ->first();

            if ($scale === null) {
                return $this->check(
                    self::CHECK_BLOCKED,
                    'Grading Configuration',
                    'Override missing',
                    'The policy references a grade-scale override that does not exist, is inactive, or is not owned by this institute.'
                );
            }
        } else {
            $scale = $this->grading->resolveScaleForClass($scheme->institute, $classGrade);

            if ($scale === null) {
                return $this->check(
                    self::CHECK_BLOCKED,
                    'Grading Configuration',
                    'No scale',
                    'No grade scale resolves for this class context (institute → level → system → country → global). Add a scale or set a policy override.'
                );
            }
        }

        $activeBands = $scale->rows->filter(fn ($row) => (bool) $row->status)->count();

        if ($activeBands === 0) {
            return $this->check(
                self::CHECK_WARNING,
                'Grading Configuration',
                $scale->name,
                'The resolved grade scale has no active bands; scores will not map to grades.'
            );
        }

        return $this->check(self::CHECK_PASS, 'Grading Configuration', $scale->name, 'Grade scale resolves for this class context ('.$scale->scopeLabel().').');
    }

    // ------------------------------------------------------------- Internals

    private function result(bool $valid, array $check, string $label, string $value, string $reason): array
    {
        if ($valid) {
            return $check;
        }

        return $this->check(self::CHECK_BLOCKED, $label, $value, $reason);
    }

    /**
     * @return array<string, string>
     */
    private function check(string $status, string $label, string $value, string $reason): array
    {
        return [
            'status' => $status,
            'label' => $label,
            'value' => $value,
            'reason' => $reason,
        ];
    }
}
