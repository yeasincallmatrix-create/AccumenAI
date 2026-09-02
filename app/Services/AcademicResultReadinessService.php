<?php

namespace App\Services;

use App\Models\AcademicAssessment;
use App\Models\AcademicStudentMark;
use App\Models\AssessmentSubject;
use Illuminate\Support\Collection;

/**
 * Step 27 — read-only result-readiness / assessment-completion evaluation.
 *
 * Answers "is this assessment's data sufficiently complete for final-result
 * processing?" for ONE assessment (its academic year + class/grade + group
 * scope). It is strictly read-only: it never writes marks, assessments,
 * placements, promotion decisions or final-result snapshots, and it never
 * aggregates or grades. No calculation engine is duplicated — placement
 * eligibility is reused from AcademicMarksService::eligiblePlacements (the
 * same rule marks entry uses) and every status maps to existing coverage
 * states derived from academic_student_marks rows.
 *
 * Coverage rules (per placement × subject, over the subject's configured
 * components):
 *   complete       every configured component has a row and at least one is
 *                  ENTERED
 *   incomplete     some (not all) configured components have rows (partial
 *                  entry)
 *   absent         every configured component is rowed as ABSENT with nothing
 *                  entered — legitimate absence, never treated as missing
 *   missing        the subject is required (in the placement's selection) but
 *                  has no marks rows at all — an unrecorded mark is NOT
 *                  treated as absence
 *   not_eligible   the subject is not in the placement's selection (no
 *                  participation required)
 *
 * Per-student overall (worst-first precedence):
 *   not_eligible   no configured subject is required of this placement
 *   no_assessment  placed but zero marks rows across every required subject
 *   missing        at least one required subject is fully unrecorded
 *   incomplete     at least one required subject is partially entered
 *   absent         every required subject is accounted for, at least one
 *                  absent
 *   complete       every required subject is complete
 *
 * Assessment-level summary:
 *   READY                  no exceptions at all
 *   READY WITH EXCEPTIONS  exceptions exist but are only legitimate absences
 *   NOT READY              any missing / incomplete / no-assessment exception
 *
 * Elasticity: placements, subjects and marks are bulk-loaded once and the
 * matrix is built entirely in memory — no per-student / per-subject queries.
 *
 * Institute/branch identity is never taken from request input; callers pass
 * the tenant + branch scoped assessment from route binding.
 */
class AcademicResultReadinessService
{
    public const STATUS_READY = 'ready';

    public const STATUS_READY_WITH_EXCEPTIONS = 'ready_with_exceptions';

    public const STATUS_NOT_READY = 'not_ready';

    // Subject-cell statuses.
    public const SUBJECT_COMPLETE = 'complete';

    public const SUBJECT_INCOMPLETE = 'incomplete';

    public const SUBJECT_ABSENT = 'absent';

    public const SUBJECT_MISSING = 'missing';

    public const SUBJECT_NOT_ELIGIBLE = 'not_eligible';

    // Per-student overall statuses.
    public const STUDENT_COMPLETE = 'complete';

    public const STUDENT_INCOMPLETE = 'incomplete';

    public const STUDENT_ABSENT = 'absent';

    public const STUDENT_MISSING = 'missing';

    public const STUDENT_NO_ASSESSMENT = 'no_assessment';

    public const STUDENT_NOT_ELIGIBLE = 'not_eligible';

    private const EXCEPTION_STATUSES = [
        self::STUDENT_NO_ASSESSMENT,
        self::STUDENT_MISSING,
        self::STUDENT_INCOMPLETE,
        self::STUDENT_ABSENT,
    ];

    public function __construct(
        private readonly AcademicMarksService $marks
    ) {}

    /**
     * Readiness matrix for one assessment.
     *
     * @return array<string, mixed>
     */
    public function forAssessment(AcademicAssessment $assessment): array
    {
        $subjects = $assessment->subjects()->with(['subject', 'components'])->get();
        $placements = $this->marks->eligiblePlacements($assessment);
        $placementIds = $placements->pluck('id');

        $markRows = AcademicStudentMark::query()
            ->where('academic_assessment_id', $assessment->id)
            ->whereIn('academic_placement_id', $placementIds)
            ->get()
            ->groupBy(fn (AcademicStudentMark $mark) => $mark->assessment_subject_id.':'.$mark->academic_placement_id);

        $summary = [
            'eligible_students' => $placements->count(),
            'complete' => 0,
            'incomplete' => 0,
            'absent' => 0,
            'missing' => 0,
            'no_assessment' => 0,
            'not_eligible' => 0,
        ];

        $subjectSummary = [];
        foreach ($subjects as $config) {
            $subjectSummary[$config->id] = [
                'config' => $config,
                'name' => $config->subject?->name ?? ('Subject #'.$config->subject_id),
                'components' => $config->components->count(),
                'eligible' => 0,
                'complete' => 0,
                'incomplete' => 0,
                'absent' => 0,
                'missing' => 0,
                'not_eligible' => 0,
            ];
        }

        $rows = [];

        foreach ($placements as $placement) {
            $cells = [];
            $required = 0;
            $totalRows = 0;
            $perCounts = ['complete' => 0, 'incomplete' => 0, 'absent' => 0, 'missing' => 0, 'not_eligible' => 0];

            foreach ($subjects as $config) {
                $subjectMarkRows = $markRows->get($config->id.':'.$placement->id, collect());

                if (! $placement->selections->contains('subject_id', $config->subject_id)) {
                    $cells[$config->id] = $this->cell($config, self::SUBJECT_NOT_ELIGIBLE, $subjectMarkRows);
                    $perCounts['not_eligible']++;
                    $subjectSummary[$config->id]['not_eligible']++;

                    continue;
                }

                $required++;
                $totalRows += $subjectMarkRows->count();

                $status = $this->subjectStatus($config, $subjectMarkRows);
                $cells[$config->id] = $this->cell($config, $status, $subjectMarkRows);
                $perCounts[$status]++;
                $subjectSummary[$config->id]['eligible']++;
                $subjectSummary[$config->id][$status]++;
            }

            $status = $this->studentStatus($required, $totalRows, $perCounts);
            $summary[$status]++;

            $rows[] = [
                'placement' => $placement,
                'student' => $placement->student,
                'status' => $status,
                'required' => $required,
                'complete' => $perCounts['complete'],
                'incomplete' => $perCounts['incomplete'],
                'absent' => $perCounts['absent'],
                'missing' => $perCounts['missing'],
                'not_eligible' => $perCounts['not_eligible'],
                'cells' => $cells,
            ];
        }

        $exceptions = array_values(array_filter(
            $rows,
            fn (array $row) => in_array($row['status'], self::EXCEPTION_STATUSES, true)
        ));

        $subjectsWithMissing = collect($subjectSummary)
            ->filter(fn (array $subject) => $subject['missing'] > 0)
            ->values();

        [$readiness, $reasons] = $this->assessmentReadiness($subjects, $exceptions, $summary);

        return [
            'subjects' => $subjects,
            'subjects_included' => $subjects->count(),
            'subject_summary' => $subjectSummary,
            'subjects_with_missing_marks' => $subjectsWithMissing->map(fn (array $subject) => [
                'name' => $subject['name'],
                'eligible' => $subject['eligible'],
                'missing' => $subject['missing'],
            ]),
            'rows' => $rows,
            'summary' => $summary,
            'is_ready' => $readiness === self::STATUS_READY,
            'readiness' => $readiness,
            'reasons' => $reasons,
        ];
    }

    /**
     * CSV export of the readiness exceptions only (students never marked
     * complete / not-eligible). One row per affected (student × subject),
     * collapsed to a single row for students with no assessment record at all.
     *
     * @return array{
     *     filename: string,
     *     headers: array<int, string>,
     *     rows: \Generator<int, array<int, string>>,
     * }
     */
    public function export(AcademicAssessment $assessment): array
    {
        $readiness = $this->forAssessment($assessment);

        return [
            'filename' => sprintf(
                '%s-readiness-exceptions-%s.csv',
                str()->slug($assessment->name ?: 'assessment'),
                now()->format('Y-m-d')
            ),
            'headers' => ['Student', 'Student ID', 'Registration Number', 'Subject', 'Status', 'Issue'],
            'rows' => $this->exportRows($readiness),
        ];
    }

    // ------------------------------------------------------------- Internals

    /**
     * @param  Collection<int, AcademicStudentMark>  $rows
     */
    private function subjectStatus(AssessmentSubject $config, Collection $rows): string
    {
        $components = $config->components;

        if ($components->isEmpty()) {
            return self::SUBJECT_COMPLETE;
        }

        $entered = 0;
        $absent = 0;

        foreach ($components as $component) {
            $mark = $rows->firstWhere('assessment_component_id', $component->id);

            if ($mark === null) {
                continue;
            }

            if ($mark->status === AcademicStudentMark::STATUS_ENTERED) {
                $entered++;
            } else {
                $absent++;
            }
        }

        $covered = $entered + $absent;

        if ($covered === 0) {
            return self::SUBJECT_MISSING;
        }
        if ($covered < $components->count()) {
            return self::SUBJECT_INCOMPLETE;
        }
        if ($absent === $covered) {
            return self::SUBJECT_ABSENT;
        }

        return self::SUBJECT_COMPLETE;
    }

    /**
     * @param  Collection<int, AcademicStudentMark>  $rows
     * @return array<string, mixed>
     */
    private function cell(AssessmentSubject $config, string $status, Collection $rows): array
    {
        $entered = 0;
        $absent = 0;

        foreach ($config->components as $component) {
            $mark = $rows->firstWhere('assessment_component_id', $component->id);
            if ($mark === null) {
                continue;
            }
            if ($mark->status === AcademicStudentMark::STATUS_ENTERED) {
                $entered++;
            } else {
                $absent++;
            }
        }

        $components = $config->components->count();

        return [
            'name' => $config->subject?->name ?? ('Subject #'.$config->subject_id),
            'status' => $status,
            'entered' => $entered,
            'absent' => $absent,
            'components' => $components,
            'reason' => $this->subjectReason($status, $entered, $absent, $components),
        ];
    }

    private function subjectReason(string $status, int $entered, int $absent, int $components): string
    {
        return match ($status) {
            self::SUBJECT_MISSING => 'No marks recorded for this subject.',
            self::SUBJECT_ABSENT => 'Recorded absent for every component of this subject.',
            self::SUBJECT_INCOMPLETE => "{$entered} of {$components} component(s) recorded; {$absent} absent.",
            self::SUBJECT_COMPLETE => 'Every component of this subject is recorded.',
            default => 'Subject not selected by this student.',
        };
    }

    /**
     * @param  array<string, int>  $perCounts
     */
    private function studentStatus(int $required, int $totalRows, array $perCounts): string
    {
        if ($required === 0) {
            return self::STUDENT_NOT_ELIGIBLE;
        }
        if ($totalRows === 0) {
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
     * @param  array<int, array<string, mixed>>  $exceptions
     * @param  array<string, int>  $summary
     * @return array{0: string, 1: array<int, string>}
     */
    private function assessmentReadiness(Collection $subjects, array $exceptions, array $summary): array
    {
        $reasons = [];

        if ($subjects->isEmpty()) {
            $reasons[] = 'No subjects are configured for this assessment.';

            return [self::STATUS_NOT_READY, $reasons];
        }

        if ($exceptions === []) {
            return [self::STATUS_READY, $reasons];
        }

        $hasMissingData = (bool) array_filter(
            $exceptions,
            fn (array $row) => in_array(
                $row['status'],
                [self::STUDENT_NO_ASSESSMENT, self::STUDENT_MISSING, self::STUDENT_INCOMPLETE],
                true
            )
        );

        if ($hasMissingData) {
            if ($summary['no_assessment'] > 0) {
                $reasons[] = $summary['no_assessment'].' student(s) have no assessment record.';
            }
            if ($summary['missing'] > 0) {
                $reasons[] = $summary['missing'].' student(s) have at least one subject with missing (unrecorded) marks.';
            }
            if ($summary['incomplete'] > 0) {
                $reasons[] = $summary['incomplete'].' student(s) have partially entered marks.';
            }

            return [self::STATUS_NOT_READY, $reasons];
        }

        $reasons[] = $summary['absent'].' student(s) are legitimately absent; no marks are missing.';

        return [self::STATUS_READY_WITH_EXCEPTIONS, $reasons];
    }

    /**
     * @param  array<string, mixed>  $readiness
     * @return \Generator<int, array<int, string>>
     */
    private function exportRows(array $readiness): \Generator
    {
        foreach ($readiness['rows'] as $row) {
            if (! in_array($row['status'], self::EXCEPTION_STATUSES, true)) {
                continue;
            }

            $student = $row['student'];
            $identity = [
                $student?->full_name ?? ('Student #'.$row['placement']->student_id),
                $student?->student_id_number ?? '',
                $student?->reg_no ?? '',
            ];

            if ($row['status'] === self::STUDENT_NO_ASSESSMENT) {
                yield array_merge($identity, [
                    '',
                    'No assessment record',
                    'Placed but no assessment marks are recorded for any subject.',
                ]);

                continue;
            }

            foreach ($row['cells'] as $cell) {
                if ($cell === null || $cell['status'] === self::SUBJECT_COMPLETE || $cell['status'] === self::SUBJECT_NOT_ELIGIBLE) {
                    continue;
                }

                yield array_merge($identity, [
                    $cell['name'],
                    $this->statusLabel($cell['status']),
                    $cell['reason'],
                ]);
            }
        }
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            self::SUBJECT_MISSING, self::STUDENT_MISSING => 'Missing marks',
            self::SUBJECT_INCOMPLETE, self::STUDENT_INCOMPLETE => 'Incomplete',
            self::SUBJECT_ABSENT, self::STUDENT_ABSENT => 'Absent',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }
}
