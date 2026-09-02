<?php

namespace App\Services;

use App\Models\AcademicFinalResultPolicy;
use App\Models\AcademicResultAggregationItem;
use App\Models\AcademicResultAggregationScheme;

/**
 * Step 28 — final-result readiness gate.
 *
 * Read-only: answers "is this academic scope ready for final-result
 * generation / locking" for one aggregation scheme (its year + class/grade +
 * group + branch + required assessments with weights). Every required
 * assessment is the scheme's active items; per-assessment coverage is reused
 * wholesale from AcademicResultReadinessService (Step 27) — no readiness logic
 * is duplicated. Per student the individual assessment statuses are folded
 * into a FINAL-scope coverage using the same worst-first precedence.
 *
 * Strictly read-only: it never modifies marks, assessments, placements,
 * policies, final results, snapshots or promotion decisions, never aggregates
 * or grades, and — unlike the lifecycle service — does NOT lazily create a
 * missing policy.
 *
 * Institute/branch identity is never taken from request input; callers pass
 * the tenant + branch scoped scheme from route binding.
 */
class AcademicFinalResultReadinessService
{
    public const STATUS_READY = AcademicResultReadinessService::STATUS_READY;

    public const STATUS_READY_WITH_EXCEPTIONS = AcademicResultReadinessService::STATUS_READY_WITH_EXCEPTIONS;

    public const STATUS_NOT_READY = AcademicResultReadinessService::STATUS_NOT_READY;

    // Per-student (final-scope) statuses — reuse Step 27 semantics verbatim.
    public const STUDENT_COMPLETE = AcademicResultReadinessService::STUDENT_COMPLETE;

    public const STUDENT_INCOMPLETE = AcademicResultReadinessService::STUDENT_INCOMPLETE;

    public const STUDENT_ABSENT = AcademicResultReadinessService::STUDENT_ABSENT;

    public const STUDENT_MISSING = AcademicResultReadinessService::STUDENT_MISSING;

    public const STUDENT_NO_ASSESSMENT = AcademicResultReadinessService::STUDENT_NO_ASSESSMENT;

    public const STUDENT_NOT_ELIGIBLE = AcademicResultReadinessService::STUDENT_NOT_ELIGIBLE;

    private const SUBJECT_MISSING = AcademicResultReadinessService::SUBJECT_MISSING;

    private const SUBJECT_INCOMPLETE = AcademicResultReadinessService::SUBJECT_INCOMPLETE;

    private const SUBJECT_ABSENT = AcademicResultReadinessService::SUBJECT_ABSENT;

    private const EXCEPTION_STATUSES = [
        self::STUDENT_NO_ASSESSMENT,
        self::STUDENT_MISSING,
        self::STUDENT_INCOMPLETE,
        self::STUDENT_ABSENT,
    ];

    public function __construct(
        private readonly AcademicResultReadinessService $readiness,
        private readonly AcademicResultAggregationService $aggregation,
    ) {}

    /**
     * Final-result readiness report for one scheme.
     *
     * @return array<string, mixed>
     */
    public function forScheme(AcademicResultAggregationScheme $scheme): array
    {
        $scheme->load(['academicYear', 'classGrade', 'academicGroup', 'branch']);

        $items = $scheme->items()
            ->with('assessment.assessmentType')
            ->where('status', 'active')
            ->get();

        $policy = AcademicFinalResultPolicy::query()->where('scheme_id', $scheme->id)->first();

        $perAssessment = [];
        foreach ($items as $item) {
            $perAssessment[$item->id] = $this->readiness->forAssessment($item->assessment);
        }

        $assessments = $items
            ->map(fn (AcademicResultAggregationItem $item) => array_merge(
                [
                    'item' => $item,
                    'assessment' => $item->assessment,
                    'name' => $item->assessment?->name ?? ('Assessment #'.$item->academic_assessment_id),
                    'type' => $item->assessment?->assessmentType?->name,
                    'weight' => $item->weight,
                ],
                $perAssessment[$item->id]
            ))
            ->values()
            ->all();

        $rowsByPlacement = [];
        foreach ($perAssessment as $itemId => $previous) {
            $keyed = [];
            foreach ($previous['rows'] as $row) {
                $keyed[$row['placement']->id] = $row;
            }
            $rowsByPlacement[$itemId] = $keyed;
        }

        $eligiblePlacements = $this->aggregation->eligiblePlacements($scheme);

        $students = [];
        $summary = [
            'required_assessments' => $items->count(),
            'ready_assessments' => 0,
            'with_exceptions_assessments' => 0,
            'not_ready_assessments' => 0,
            'eligible_students' => $eligiblePlacements->count(),
            'complete' => 0,
            'incomplete' => 0,
            'absent' => 0,
            'missing' => 0,
            'no_assessment' => 0,
            'not_eligible' => 0,
        ];

        foreach ($assessments as $assessmentData) {
            $summary[$assessmentData['readiness'] === AcademicResultReadinessService::STATUS_READY ? 'ready_assessments' : ($assessmentData['readiness'] === AcademicResultReadinessService::STATUS_READY_WITH_EXCEPTIONS ? 'with_exceptions_assessments' : 'not_ready_assessments')]++;
        }

        foreach ($eligiblePlacements as $placement) {
            $perCounts = [
                'complete' => 0,
                'incomplete' => 0,
                'absent' => 0,
                'missing' => 0,
                'no_assessment' => 0,
                'not_eligible' => 0,
            ];

            $assessmentsForStudent = [];

            foreach ($items as $item) {
                $row = $rowsByPlacement[$item->id][$placement->id] ?? null;
                $status = $row['status'] ?? self::STUDENT_NOT_ELIGIBLE;
                $perCounts[$status]++;

                $name = $item->assessment?->name ?? ('Assessment #'.$item->academic_assessment_id);

                $single = [
                    'name' => $name,
                    'status' => $status,
                    'missing_subjects' => [],
                    'incomplete_subjects' => [],
                    'absent_subjects' => [],
                ];

                if ($row !== null && $status !== self::STUDENT_NO_ASSESSMENT) {
                    foreach ($row['cells'] as $cell) {
                        if ($cell === null) {
                            continue;
                        }
                        if ($cell['status'] === self::SUBJECT_MISSING) {
                            $single['missing_subjects'][] = $cell['name'];
                        } elseif ($cell['status'] === self::SUBJECT_INCOMPLETE) {
                            $single['incomplete_subjects'][] = $cell['name'];
                        } elseif ($cell['status'] === self::SUBJECT_ABSENT) {
                            $single['absent_subjects'][] = $cell['name'];
                        }
                    }
                }

                $assessmentsForStudent[$item->id] = $single;
            }

            $status = $this->studentStatus($items->count(), $perCounts);
            $summary[$status]++;

            $missingAssessmentNames = array_values(array_unique(array_map(
                fn (array $a) => $a['name'],
                array_filter($assessmentsForStudent, fn (array $a) => in_array($a['status'], [self::STUDENT_NO_ASSESSMENT, self::STUDENT_MISSING], true))
            )));
            $missingSubjectNames = array_values(array_unique($this->flattenSubjects($assessmentsForStudent, self::SUBJECT_MISSING)));
            $incompleteSubjectNames = array_values(array_unique($this->flattenSubjects($assessmentsForStudent, self::SUBJECT_INCOMPLETE)));
            $incompleteAssessmentNames = array_values(array_unique(array_map(
                fn (array $a) => $a['name'],
                array_filter($assessmentsForStudent, fn (array $a) => $a['status'] === self::STUDENT_INCOMPLETE)
            )));
            $absentAssessmentNames = array_values(array_unique(array_map(
                fn (array $a) => $a['name'],
                array_filter($assessmentsForStudent, fn (array $a) => $a['status'] === self::STUDENT_ABSENT)
            )));

            $students[] = [
                'placement' => $placement,
                'student' => $placement->student,
                'status' => $status,
                'required' => $items->count() - $perCounts['not_eligible'],
                'complete' => $perCounts['complete'],
                'incomplete' => $perCounts['incomplete'],
                'absent' => $perCounts['absent'],
                'missing' => $perCounts['missing'],
                'no_assessment' => $perCounts['no_assessment'],
                'not_eligible' => $perCounts['not_eligible'],
                'assessments' => $assessmentsForStudent,
                'missing_assessments' => $missingAssessmentNames,
                'missing_subjects' => $missingSubjectNames,
                'incomplete_subjects' => $incompleteSubjectNames,
                'incomplete_assessments' => $incompleteAssessmentNames,
                'absent_assessments' => $absentAssessmentNames,
                'reason' => $this->studentReason(
                    $status,
                    $missingAssessmentNames,
                    $missingSubjectNames,
                    $incompleteSubjectNames,
                    $incompleteAssessmentNames,
                    $absentAssessmentNames,
                ),
            ];
        }

        $exceptions = array_values(array_filter(
            $students,
            fn (array $row) => in_array($row['status'], self::EXCEPTION_STATUSES, true)
        ));

        [$readiness, $reasons] = $this->schemeReadiness($scheme, $items->count(), $summary);

        return [
            'scheme' => $scheme,
            'policy' => $policy,
            'assessments' => $assessments,
            'students' => $students,
            'exceptions' => $exceptions,
            'summary' => $summary,
            'is_ready' => $readiness === self::STATUS_READY,
            'readiness' => $readiness,
            'reasons' => $reasons,
        ];
    }

    /**
     * CSV export of the readiness exceptions only. One row per student in
     * exception, with the assessments/subjects distilled into the columns of
     * the final-result readiness gate.
     *
     * @return array{
     *     filename: string,
     *     headers: array<int, string>,
     *     rows: \Generator<int, array<int, string>>,
     * }
     */
    public function export(AcademicResultAggregationScheme $scheme): array
    {
        $report = $this->forScheme($scheme);

        return [
            'filename' => sprintf(
                '%s-final-result-readiness-exceptions-%s.csv',
                str()->slug($scheme->name ?: 'scheme'),
                now()->format('Y-m-d')
            ),
            'headers' => [
                'Student',
                'Student ID',
                'Registration Number',
                'Missing Assessment',
                'Missing Subject',
                'Incomplete Assessment',
                'Absent Assessment',
                'Readiness',
                'Reason',
            ],
            'rows' => $this->exportRows($report['exceptions']),
        ];
    }

    // ------------------------------------------------------------- Internals

    /**
     * @param  array<string, int>  $perCounts
     */
    private function studentStatus(int $itemsCount, array $perCounts): string
    {
        if ($itemsCount === 0) {
            return self::STUDENT_NOT_ELIGIBLE;
        }
        if ($perCounts['not_eligible'] === $itemsCount) {
            return self::STUDENT_NOT_ELIGIBLE;
        }
        if ($perCounts['no_assessment'] > 0) {
            return self::STUDENT_NO_ASSESSMENT;
        }
        if ($perCounts['missing'] > 0) {
            return self::STUDENT_MISSING;
        }
        if ($perCounts['incomplete'] > 0) {
            return self::STUDENT_INCOMPLETE;
        }
        if ($perCounts['absent'] > 0) {
            return self::STUDENT_ABSENT;
        }

        return self::STUDENT_COMPLETE;
    }

    /**
     * @param  array<int, string>  $missingAssessmentNames
     * @param  array<int, string>  $missingSubjectNames
     * @param  array<int, string>  $incompleteSubjectNames
     * @param  array<int, string>  $incompleteAssessmentNames
     * @param  array<int, string>  $absentAssessmentNames
     */
    private function studentReason(
        string $status,
        array $missingAssessmentNames,
        array $missingSubjectNames,
        array $incompleteSubjectNames,
        array $incompleteAssessmentNames,
        array $absentAssessmentNames
    ): string {
        return match ($status) {
            self::STUDENT_NO_ASSESSMENT => 'No assessment record in: '.implode(', ', $missingAssessmentNames).'.',
            self::STUDENT_MISSING => 'Missing marks in '.implode(', ', $missingAssessmentNames)
                .($missingSubjectNames ? ' ('.implode(', ', $missingSubjectNames).')' : '')
                .'.',
            self::STUDENT_INCOMPLETE => 'Incomplete marks in '.implode(', ', $incompleteAssessmentNames)
                .($incompleteSubjectNames ? ' ('.implode(', ', $incompleteSubjectNames).')' : '')
                .'.',
            self::STUDENT_ABSENT => 'Absent in '.implode(', ', $absentAssessmentNames).'.',
            default => 'Not eligible for any required assessment.',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $assessmentsForStudent
     * @return array<int, string>
     */
    private function flattenSubjects(array $assessmentsForStudent, string $cellStatus): array
    {
        $subjects = [];

        foreach ($assessmentsForStudent as $single) {
            if ($single['status'] !== self::STUDENT_MISSING && $single['status'] !== self::STUDENT_INCOMPLETE) {
                continue;
            }

            $source = match ($cellStatus) {
                self::SUBJECT_MISSING => $single['missing_subjects'],
                self::SUBJECT_INCOMPLETE => $single['incomplete_subjects'],
                self::SUBJECT_ABSENT => $single['absent_subjects'],
                default => [],
            };

            foreach ($source as $name) {
                $subjects[] = $name;
            }
        }

        return array_values(array_unique($subjects));
    }

    /**
     * @param  array<string, int>  $summary
     * @return array{0: string, 1: array<int, string>}
     */
    private function schemeReadiness(AcademicResultAggregationScheme $scheme, int $itemsCount, array $summary): array
    {
        $reasons = [];

        if ($itemsCount === 0) {
            $reasons[] = 'No required assessments are configured for this aggregation scheme.';

            return [self::STATUS_NOT_READY, $reasons];
        }

        // Weight validity shares the existing lock rule verbatim (read-only
        // check that mirrors AcademicFinalResultLifecycleService::lock) — it is
        // reported as a limitation, never guessed.
        if (! $scheme->weightIsValid()) {
            $reasons[] = 'Configured assessment weights total '.$scheme->totalWeight().'%; a final result cannot be locked until they total 100%.';

            return [self::STATUS_NOT_READY, $reasons];
        }

        if ($summary['not_ready_assessments'] > 0) {
            $reasons[] = $summary['not_ready_assessments'].' required assessment(s) are not ready.';
        }

        foreach ([
            'no_assessment' => 'student(s) have no assessment record in at least one required assessment.',
            'missing' => 'student(s) have at least one subject with missing (unrecorded) marks in a required assessment.',
            'incomplete' => 'student(s) have partially entered marks in a required assessment.',
        ] as $key => $message) {
            if ($summary[$key] > 0) {
                $reasons[] = $summary[$key].' '.$message;
            }
        }

        if ($summary['not_eligible'] > 0) {
            $reasons[] = $summary['not_eligible'].' placed student(s) are not eligible for any required assessment.';
        }

        $hasHardIssue = $summary['not_ready_assessments'] > 0
            || $summary['no_assessment'] > 0
            || $summary['missing'] > 0
            || $summary['incomplete'] > 0
            || $summary['not_eligible'] > 0;

        if ($hasHardIssue) {
            return [self::STATUS_NOT_READY, $reasons];
        }

        if ($summary['absent'] > 0 || $summary['with_exceptions_assessments'] > 0) {
            $reasons[] = $summary['absent'].' student(s) are legitimately absent in at least one required assessment; no marks are missing.';

            return [self::STATUS_READY_WITH_EXCEPTIONS, $reasons];
        }

        $reasons[] = 'Every required assessment is ready and every placed student is complete.';

        return [self::STATUS_READY, $reasons];
    }

    /**
     * @param  array<int, array<string, mixed>>  $exceptions
     * @return \Generator<int, array<int, string>>
     */
    private function exportRows(array $exceptions): \Generator
    {
        foreach ($exceptions as $row) {
            $student = $row['student'];
            $identity = [
                $student?->full_name ?? ('Student #'.$row['placement']->student_id),
                $student?->student_id_number ?? '',
                $student?->reg_no ?? '',
            ];

            yield array_merge($identity, [
                implode(', ', $row['missing_assessments']),
                implode(', ', $row['missing_subjects']),
                implode(', ', $row['incomplete_assessments']),
                implode(', ', $row['absent_assessments']),
                $this->statusLabel($row['status']),
                $row['reason'],
            ]);
        }
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            self::STUDENT_MISSING => 'Missing marks',
            self::STUDENT_INCOMPLETE => 'Incomplete',
            self::STUDENT_ABSENT => 'Absent',
            self::STUDENT_NO_ASSESSMENT => 'No assessment record',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
