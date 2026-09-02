<?php

namespace App\Services;

use App\Models\AcademicAssessment;
use App\Models\AcademicStudentMark;
use App\Models\AssessmentSubject;
use App\Models\AssessmentSubjectComponent;
use App\Models\StudentAcademicPlacement;
use App\Support\BranchContext;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Academic marks entry + derived component/subject results (Education Engine).
 *
 * - Eligibility: a placement is eligible for an assessment when it matches the
 *   assessment's year + class/grade + group (students filtered to the acting
 *   branch); a placement is eligible for a subject when its subject selection
 *   actually contains that subject. Mandatory subjects are always present in a
 *   placement's selection (Step 5 auto-includes them), so mandatory subjects
 *   naturally cover every placement.
 * - Persistence: one academic_student_marks row per (student, component).
 *   'entered' keeps the obtained mark (0 is a real zero), 'absent' has NULL
 *   marks, no row at all = not entered.
 * - Results: totals + overall pass mark are always derived, never stored. The
 *   pass rule lives on the assessment_subject row (total_only /
 *   mandatory_components / both); component mandatory_pass rows gate the rule.
 * - Safety: the acting branch restricts which students are eligible; input can
 *   never invent a student/placement/component outside the resolved context.
 */
class AcademicMarksService
{
    public function __construct(
        private readonly AcademicAssessmentAuditService $audit,
    ) {}

    // ------------------------------------------------------------- Eligibility

    /**
     * Placements matching the assessment context (year + class + group), tenant
     * filtered by the model scope and branch filtered by the acting user.
     *
     * @return Collection<int, StudentAcademicPlacement>
     */
    public function eligiblePlacements(AcademicAssessment $assessment): Collection
    {
        return StudentAcademicPlacement::query()
            // P3-1 — Exclude archived/soft-deleted placements from active workflows (SoftDeletes global scope)
            ->where('status', StudentAcademicPlacement::STATUS_ACTIVE)
            ->where('academic_year_id', $assessment->academic_year_id)
            ->where('class_grade_id', $assessment->class_grade_id)
            ->when($assessment->academic_group_id, fn ($q) => $q->where('academic_group_id', $assessment->academic_group_id))
            ->when(BranchContext::enabled(), fn ($q) => $q->whereHas('student', fn ($sq) => $sq->where('branch_id', BranchContext::id())))
            ->with(['student', 'selections'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Placements eligible for one subject: those whose selection includes it.
     *
     * @return Collection<int, StudentAcademicPlacement>
     */
    public function eligiblePlacementsForSubject(AcademicAssessment $assessment, AssessmentSubject $subject): Collection
    {
        return $this->eligiblePlacements($assessment)
            ->filter(fn (StudentAcademicPlacement $placement) => $placement->selections->contains('subject_id', $subject->subject_id))
            ->values();
    }

    // ------------------------------------------------------------- Grid

    /**
     * Marks-entry grid for one subject: rows per eligible placement with the
     * dynamic component columns, current marks and a derived subject result.
     *
     * @return array{components: Collection<int, AssessmentSubjectComponent>, rows: array<int, array<string, mixed>>, pass_rule: string}
     */
    public function grid(AcademicAssessment $assessment, AssessmentSubject $subject): array
    {
        $components = $subject->components()->with('component')->get();
        $placements = $this->eligiblePlacementsForSubject($assessment, $subject);

        $marks = AcademicStudentMark::query()
            ->where('assessment_subject_id', $subject->id)
            ->whereIn('academic_placement_id', $placements->pluck('id'))
            ->get()
            ->groupBy('academic_placement_id');

        $rows = [];
        foreach ($placements as $placement) {
            $placementMarks = $marks->get($placement->id, collect());
            $entered = $placementMarks->where('status', AcademicStudentMark::STATUS_ENTERED);

            $rows[] = [
                'placement' => $placement,
                'student' => $placement->student,
                'marks' => $placementMarks->keyBy('assessment_component_id'),
                'entered_count' => $entered->count(),
                'absent' => $placementMarks->where('status', AcademicStudentMark::STATUS_ABSENT)->count() > 0 && $entered->isEmpty(),
                'result' => $this->subjectResult($assessment, $subject, $placementMarks, $components),
            ];
        }

        return [
            'components' => $components,
            'rows' => $rows,
            'pass_rule' => $subject->pass_rule,
        ];
    }

    // ------------------------------------------------------------- Class sheet

    /**
     * Class-wide marks sheet for one assessment: every eligible placement ×
     * every subject configured on the assessment. Each cell is the derived
     * subject result (subjectResult), or null when the placement's subject
     * selection does not include that subject. Per-placement totals and the
     * percentage are summed over subjects with entered marks (pass/fail);
     * absent subjects are counted separately but excluded from the numeric
     * total. Overall status precedence: fail → absent → incomplete (some
     * eligible subjects not yet entered) → pass.
     *
     * @return array{
     *     subjects: Collection<int, AssessmentSubject>,
     *     rows: array<int, array<string, mixed>>,
     *     summary: array<string, int>,
     * }
     */
    public function sheet(AcademicAssessment $assessment): array
    {
        $subjects = $assessment->subjects()->with(['subject', 'components.component'])->get();
        $placements = $this->eligiblePlacements($assessment);

        $marks = AcademicStudentMark::query()
            ->where('academic_assessment_id', $assessment->id)
            ->whereIn('academic_placement_id', $placements->pluck('id'))
            ->get()
            ->groupBy(fn (AcademicStudentMark $mark) => $mark->assessment_subject_id.':'.$mark->academic_placement_id);

        $rows = [];
        $summary = ['pass' => 0, 'fail' => 0, 'absent' => 0, 'incomplete' => 0, 'not_entered' => 0, 'not_eligible' => 0];

        foreach ($placements as $placement) {
            $cells = [];
            $eligible = $entered = $absent = 0;
            $failed = false;
            $totalObtained = 0.0;
            $totalFull = 0.0;

            foreach ($subjects as $subjectConfig) {
                if (! $placement->selections->contains('subject_id', $subjectConfig->subject_id)) {
                    $cells[$subjectConfig->id] = null;

                    continue;
                }

                $eligible++;
                $result = $this->subjectResult(
                    $assessment,
                    $subjectConfig,
                    $marks->get($subjectConfig->id.':'.$placement->id, collect()),
                    $subjectConfig->components
                );
                $cells[$subjectConfig->id] = $result;

                if ($result['status'] === 'pass' || $result['status'] === 'fail') {
                    $entered++;
                    $totalObtained += $result['total_obtained'];
                    $totalFull += $result['total_full'];
                } elseif ($result['status'] === 'absent') {
                    $absent++;
                }

                if ($result['status'] === 'fail') {
                    $failed = true;
                }
            }

            $status = $this->overallStatus($entered, $absent, $eligible, $failed);
            $summary[$status]++;

            $rows[] = [
                'placement' => $placement,
                'student' => $placement->student,
                'cells' => $cells,
                'totals' => [
                    'eligible' => $eligible,
                    'entered' => $entered,
                    'absent' => $absent,
                    'not_entered' => max(0, $eligible - $entered - $absent),
                    'total_obtained' => round($totalObtained, 2),
                    'total_full' => round($totalFull, 2),
                    'percentage' => $totalFull > 0 ? round($totalObtained / $totalFull * 100, 2) : 0.0,
                ],
                'status' => $status,
            ];
        }

        return ['subjects' => $subjects, 'rows' => $rows, 'summary' => $summary];
    }

    /**
     * CSV export of the class-wide marks sheet (one row per placement). Reads
     * live assessment marks only; it never touches final-result snapshots.
     *
     * @return array{
     *     filename: string,
     *     headers: array<int, string>,
     *     rows: \Generator<int, array<int, string>>,
     * }
     */
    public function export(AcademicAssessment $assessment): array
    {
        $sheet = $this->sheet($assessment);

        $headers = ['Student', 'Student ID', 'Registration Number'];

        foreach ($sheet['subjects'] as $subjectConfig) {
            $name = $subjectConfig->subject?->name ?? ('Subject #'.$subjectConfig->subject_id);
            $headers[] = $name.' (obtained)';
            $headers[] = $name.' (full)';
            $headers[] = $name.' (status)';
        }

        array_push($headers, 'Total obtained', 'Total full', 'Percentage', 'Overall status', 'Subjects entered', 'Subjects absent', 'Subjects not entered');

        return [
            'filename' => sprintf(
                '%s-marks-sheet-%s.csv',
                str()->slug($assessment->name ?: 'assessment'),
                now()->format('Y-m-d')
            ),
            'headers' => $headers,
            'rows' => $this->exportRows($sheet),
        ];
    }

    /**
     * @return \Generator<int, array<int, string>>
     */
    private function exportRows(array $sheet): \Generator
    {
        foreach ($sheet['rows'] as $row) {
            $student = $row['student'];

            $cells = [
                $student?->full_name ?? ('Student #'.$row['placement']->student_id),
                $student?->student_id_number ?? '',
                $student?->reg_no ?? '',
            ];

            foreach ($sheet['subjects'] as $subjectConfig) {
                $cell = $row['cells'][$subjectConfig->id] ?? null;

                if ($cell === null) {
                    $cells[] = '';
                    $cells[] = '';
                    $cells[] = '';

                    continue;
                }

                if ($cell['status'] === 'pass' || $cell['status'] === 'fail') {
                    $cells[] = $this->formatNumber($cell['total_obtained']);
                } elseif ($cell['status'] === 'absent') {
                    $cells[] = 'Absent';
                } else {
                    $cells[] = '';
                }

                $cells[] = $this->formatNumber($cell['total_full']);
                $cells[] = match ($cell['status']) {
                    'pass' => 'Pass',
                    'fail' => 'Fail',
                    'absent' => 'Absent',
                    default => '',
                };
            }

            $cells[] = $this->formatNumber($row['totals']['total_obtained']);
            $cells[] = $this->formatNumber($row['totals']['total_full']);
            $cells[] = $this->formatNumber($row['totals']['percentage']).'%';
            $cells[] = $this->statusLabel($row['status']);
            $cells[] = (string) $row['totals']['entered'];
            $cells[] = (string) $row['totals']['absent'];
            $cells[] = (string) $row['totals']['not_entered'];

            yield $cells;
        }
    }

    private function overallStatus(int $entered, int $absent, int $eligible, bool $failed): string
    {
        if ($eligible === 0) {
            return 'not_eligible';
        }
        if ($entered === 0 && $absent === 0) {
            return 'not_entered';
        }
        if ($failed) {
            return 'fail';
        }
        if ($absent > 0) {
            return 'absent';
        }
        if ($entered + $absent < $eligible) {
            return 'incomplete';
        }

        return 'pass';
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2), '0'), '.');
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'pass' => 'Pass',
            'fail' => 'Fail',
            'absent' => 'Absent',
            'incomplete' => 'Incomplete',
            'not_entered' => 'Not entered',
            default => 'Not eligible',
        };
    }

    // ------------------------------------------------------------- Derived result

    /**
     * Derived subject result for a placement given its component marks.
     *
     * @param  Collection<int, AcademicStudentMark>  $marks
     * @param  Collection<int, AssessmentSubjectComponent>  $components
     * @return array<string, mixed>
     */
    public function subjectResult(AcademicAssessment $assessment, AssessmentSubject $subject, Collection $marks, Collection $components): array
    {
        $entered = $marks->where('status', AcademicStudentMark::STATUS_ENTERED);
        $absentCount = $marks->where('status', AcademicStudentMark::STATUS_ABSENT)->count();

        $totalFull = (float) $components->sum('full_mark');
        $totalObtained = (float) $entered->sum('obtained_mark');
        $totalPass = (float) $components->sum('pass_mark');

        $mandatoryFailed = false;
        foreach ($components as $component) {
            if (! $component->mandatory_pass) {
                continue;
            }

            $mark = $entered->firstWhere('assessment_component_id', $component->id);
            if ($mark === null || (float) $mark->obtained_mark < (float) $component->pass_mark) {
                $mandatoryFailed = true;
            }
        }

        if ($marks->isEmpty()) {
            $status = 'not_entered';
        } elseif ($absentCount > 0 && $entered->isEmpty()) {
            $status = 'absent';
        } else {
            $passed = match ($subject->pass_rule) {
                AssessmentSubject::PASS_RULE_MANDATORY_COMPONENTS => ! $mandatoryFailed,
                AssessmentSubject::PASS_RULE_BOTH => $totalObtained >= $totalPass && ! $mandatoryFailed,
                default => $totalObtained >= $totalPass,
            };

            $status = $passed ? 'pass' : 'fail';
        }

        return [
            'status' => $status,
            'total_full' => $totalFull,
            'total_obtained' => round($totalObtained, 2),
            'total_pass' => $totalPass,
            'percentage' => $totalFull > 0 ? round($totalObtained / $totalFull * 100, 2) : 0.0,
            'mandatory_failed' => $mandatoryFailed,
        ];
    }

    // ------------------------------------------------------------- Persistence

    /**
     * Bulk save marks for one assessment subject. One request handles every
     * eligible student and every component.
     *
     * @param  array<int, array<string, mixed>>  $rows  keyed by placement id
     * @return int number of persisted rows
     */
    public function saveMarks(AcademicAssessment $assessment, AssessmentSubject $subject, ?int $userId, array $rows): int
    {
        abort_if($assessment->isLocked(), 422, 'This assessment is locked and its marks can no longer be changed.');

        $components = $subject->components()->with('component')->get();
        $placements = $this->eligiblePlacementsForSubject($assessment, $subject)
            ->keyBy('id');

        $saved = 0;
        $absentRows = 0;

        DB::transaction(function () use ($assessment, $subject, $components, $placements, $rows, $userId, &$saved, &$absentRows) {
            // Concurrency: lock assessment row to serialize concurrent marks updates for same assessment
            AcademicAssessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            foreach ($rows as $rawPlacementId => $row) {
                $placementId = (int) $rawPlacementId;
                $placement = $placements->get($placementId);

                if ($placement === null) {
                    throw ValidationException::withMessages([
                        'rows.'.$placementId => 'A student in this entry is not eligible for this subject.',
                    ]);
                }

                $status = $row['status'] ?? AcademicStudentMark::STATUS_ENTERED;
                if (! in_array($status, [AcademicStudentMark::STATUS_ENTERED, AcademicStudentMark::STATUS_ABSENT], true)) {
                    throw ValidationException::withMessages([
                        'rows.'.$placementId.'.status' => 'Invalid entry status.',
                    ]);
                }

                $marks = array_map('strval', $row['marks'] ?? []);

                if ($status === AcademicStudentMark::STATUS_ABSENT) {
                    $this->storeAbsent($assessment, $subject, $placement, $components, $userId);
                    $saved += $components->count();
                    $absentRows++;

                    continue;
                }

                $normalized = [];
                foreach ($components as $component) {
                    $value = $marks[$component->id] ?? '';
                    if ($value === '') {
                        continue;
                    }

                    if (! is_numeric($value)) {
                        throw ValidationException::withMessages([
                            'rows.'.$placementId.'.marks.'.$component->id => 'Obtained marks must be a number.',
                        ]);
                    }

                    $num = (float) $value;
                    $full = (float) $component->full_mark;

                    if ($num < 0 || $num > $full) {
                        throw ValidationException::withMessages([
                            'rows.'.$placementId.'.marks.'.$component->id => "Obtained marks must be between 0 and $full.",
                        ]);
                    }

                    $normalized[$component->id] = $num;
                }

                // Nothing entered -> treat as not entered, drop any previous rows.
                if ($normalized === []) {
                    $this->clearRows($subject, $placement);

                    continue;
                }

                $studentId = (int) $placement->student_id;

                foreach ($components as $component) {
                    if (! array_key_exists($component->id, $normalized)) {
                        // Blank component -> not entered for that component.
                        AcademicStudentMark::query()
                            ->where('assessment_component_id', $component->id)
                            ->where('student_id', $studentId)
                            ->delete();

                        continue;
                    }

                    AcademicStudentMark::updateOrCreate(
                        ['assessment_component_id' => $component->id, 'student_id' => $studentId],
                        [
                            'institute_id' => $assessment->institute_id,
                            'academic_assessment_id' => $assessment->id,
                            'assessment_subject_id' => $subject->id,
                            'academic_placement_id' => $placement->id,
                            'obtained_mark' => $normalized[$component->id],
                            'status' => AcademicStudentMark::STATUS_ENTERED,
                            'entered_by' => $userId,
                            'updated_by' => $userId,
                        ]
                    );
                    $saved++;
                }

                // Clear any stray absent rows for components that are now entered.
                AcademicStudentMark::query()
                    ->where('assessment_subject_id', $subject->id)
                    ->where('academic_placement_id', $placement->id)
                    ->where('status', AcademicStudentMark::STATUS_ABSENT)
                    ->delete();
            }
        });

        if ($saved > 0) {
            $this->audit->record(
                $assessment->institute_id,
                $userId,
                'marks.entered',
                $subject->id,
                null,
                [
                    'assessment_id' => $assessment->id,
                    'subject_id' => $subject->subject_id,
                    'records_saved' => $saved,
                    'absent_rows' => $absentRows,
                ]
            );
        }

        return $saved;
    }

    // ------------------------------------------------------------- Internals

    /**
     * @param  Collection<int, AssessmentSubjectComponent>  $components
     */
    private function storeAbsent(AcademicAssessment $assessment, AssessmentSubject $subject, StudentAcademicPlacement $placement, Collection $components, ?int $userId): void
    {
        $this->clearRows($subject, $placement);

        foreach ($components as $component) {
            AcademicStudentMark::create([
                'institute_id' => $assessment->institute_id,
                'academic_assessment_id' => $assessment->id,
                'assessment_subject_id' => $subject->id,
                'assessment_component_id' => $component->id,
                'student_id' => $placement->student_id,
                'academic_placement_id' => $placement->id,
                'obtained_mark' => null,
                'status' => AcademicStudentMark::STATUS_ABSENT,
                'entered_by' => $userId,
                'updated_by' => $userId,
            ]);
        }
    }

    private function clearRows(AssessmentSubject $subject, StudentAcademicPlacement $placement): void
    {
        AcademicStudentMark::query()
            ->where('assessment_subject_id', $subject->id)
            ->where('academic_placement_id', $placement->id)
            ->delete();
    }
}
