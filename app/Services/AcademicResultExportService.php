<?php

namespace App\Services;

use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultStudent;
use App\Models\StudentAcademicPlacement;
use Illuminate\Support\Collection;

/**
 * Step 21 — Published academic result CSV export.
 *
 * Exports one PUBLISHED final result strictly from its frozen snapshot tables
 * (academic_final_results, academic_final_result_students,
 * academic_final_result_rows). It never reads live marks, never re-runs the
 * aggregation/grading/recalculation services and never touches the derived
 * preview — the exported numbers are exactly what was published. The result
 * header and every placement/student reaches this service tenant + branch
 * scoped (route binding), and placements whose students are not reachable in
 * the acting user's branch scope are skipped, mirroring the result sheet.
 */
class AcademicResultExportService
{
    /**
     * @return array{
     *     filename: string,
     *     headers: array<int, string>,
     *     rows: \Generator<int, array<int, string>>,
     * }
     */
    public function export(AcademicFinalResult $result): array
    {
        $result->load(['scheme.academicYear', 'scheme.classGrade', 'scheme.academicGroup']);

        $snapshots = $result->students()->get()->keyBy('placement_id');

        $rowsByPlacement = $result->rows()
            ->with('subject')
            ->orderBy('id')
            ->get()
            ->groupBy('placement_id')
            ->map(fn ($group) => $group->sortBy('subject_id')->values());

        $placements = StudentAcademicPlacement::query()
            ->with(['student', 'classGrade', 'academicGroup', 'academicYear'])
            ->whereIn('id', $snapshots->keys()->all())
            ->orderBy('id')
            ->get();

        return [
            'filename' => sprintf(
                '%s-result-%s.csv',
                str()->slug($result->name ?: 'result'),
                $result->published_at?->format('Y-m-d') ?? 'published',
            ),
            'headers' => [
                'Student',
                'Student ID',
                'Registration Number',
                'Class / Grade',
                'Group / Stream',
                'Academic Year',
                'Subject',
                'Aggregate %',
                'Grade',
                'Grade Point',
                'Credits',
                'Pass/Fail',
                'Optional',
                'GPA Included',
                'GPA',
                'Overall Result',
            ],
            'rows' => $this->rows($placements, $snapshots, $rowsByPlacement),
        ];
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, StudentAcademicPlacement>  $placements
     * @param  Collection<int, AcademicFinalResultStudent>  $snapshots
     * @param  Collection<int, Collection>  $rowsByPlacement
     * @return \Generator<int, array<int, string>>
     */
    private function rows($placements, $snapshots, $rowsByPlacement): \Generator
    {
        foreach ($placements as $placement) {
            $student = $placement->student;

            // A placement whose student is not reachable in the acting user's
            // tenant/branch scope is excluded entirely (never leaks).
            if ($student === null) {
                continue;
            }

            $snapshot = $snapshots->get((int) $placement->id);

            foreach ($rowsByPlacement->get((int) $placement->id, collect()) as $row) {
                yield [
                    $student->full_name,
                    $student->student_id_number ?? '',
                    $student->reg_no ?? '',
                    $placement->classGrade?->name ?? ('Class #'.$placement->class_grade_id),
                    $placement->academicGroup?->name ?? '—',
                    $placement->academicYear?->name ?? ('Year #'.$placement->academic_year_id),
                    $row->subject?->name ?? ('Subject #'.$row->subject_id),
                    $row->aggregate !== null ? $this->percent((float) $row->aggregate) : '',
                    $row->grade ?? '',
                    $row->grade_point !== null ? number_format((float) $row->grade_point, 2) : '',
                    $row->credits !== null ? (string) $row->credits : '',
                    $this->passFail($row->subject_status),
                    $row->optional ? 'Yes' : 'No',
                    $row->gpa_included ? 'Yes' : 'No',
                    $this->gpa($snapshot),
                    $this->overall($snapshot),
                ];
            }
        }
    }

    // ------------------------------------------------------------- Formatting

    private function percent(float $aggregate): string
    {
        return rtrim(rtrim(number_format($aggregate, 2), '0'), '.').'%';
    }

    private function passFail(?string $status): string
    {
        return match ($status) {
            'PASS' => 'Pass',
            'FAIL' => 'Fail',
            default => '—',
        };
    }

    private function gpa(?AcademicFinalResultStudent $snapshot): string
    {
        if ($snapshot === null || ! is_numeric($snapshot->gpa)) {
            return '';
        }

        return number_format((float) $snapshot->gpa, 2);
    }

    private function overall(?AcademicFinalResultStudent $snapshot): string
    {
        if ($snapshot === null) {
            return '—';
        }

        if (! is_numeric($snapshot->gpa)) {
            return $snapshot->gpa_status === AcademicFinalResultStudent::GPA_COMPUTED ? '—' : 'Unavailable';
        }

        return (int) $snapshot->failed_count > 0 ? 'Fail' : 'Pass';
    }
}
