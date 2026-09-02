<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Student;
use Carbon\CarbonInterface;

/**
 * Step 21 — Academic attendance CSV export.
 *
 * Exports the *currently filtered* attendance report (student / class-group /
 * daily) using exactly the same tenant, branch, year, class and group scope
 * rules as the Step-20 report service, which it delegates to. Attendance rows
 * stay untouched: a missing row is exported as "not recorded" where applicable
 * and never promoted to "absent". Every dataset returned is an iterable stream
 * (generator/lazy query), so large exports never materialize a full collection.
 *
 * Strictly read-only — nothing here writes to attendance, placements or any
 * other table.
 */
class AcademicAttendanceExportService
{
    public function __construct(
        private readonly AcademicAttendanceReportService $reports,
    ) {}

    /**
     * Day-by-day export for one student over a window (date-aware placement
     * context resolved per record).
     *
     * @return array{
     *     valid: bool,
     *     message: ?string,
     *     filename: string,
     *     headers: array<int, string>,
     *     rows: \Generator<int, array<int, string>>,
     * }
     */
    public function student(Student $student, CarbonInterface $start, CarbonInterface $end, ?AcademicYear $year = null): array
    {
        return [
            'valid' => true,
            'message' => null,
            'filename' => sprintf(
                'student-attendance-report-%s-%s-to-%s.csv',
                $student->student_id_number ?: $student->id,
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
            ),
            'headers' => [
                'Student Name',
                'Student ID',
                'Registration Number',
                'Academic Year',
                'Class / Grade',
                'Group / Stream',
                'Date',
                'Batch',
                'Status',
                'Remarks',
            ],
            'rows' => $this->studentRows($student, $start, $end),
        ];
    }

    /**
     * Per-student summary export (whole roster, all pages) for a class/group
     * over the report window.
     *
     * @return array{
     *     valid: bool,
     *     message: ?string,
     *     filename: string,
     *     headers: array<int, string>,
     *     rows: \Generator<int, array<int, string>>,
     * }
     */
    public function classReport(?int $yearId, ?int $classId, ?int $groupId, CarbonInterface $start, CarbonInterface $end): array
    {
        $report = $this->reports->classReport($yearId, $classId, $groupId, $start, $end);

        if (! $report['valid']) {
            return $this->invalid($report['message']);
        }

        /** @var AcademicYear $year */
        $year = $report['year'];
        $window = $report['window'];

        return [
            'valid' => true,
            'message' => null,
            'filename' => sprintf(
                'class-attendance-report-%s-y%s-c%s-%s-to-%s.csv',
                str()->slug($year->name ?: ($year->code ?: (string) $year->id)),
                $yearId,
                $classId,
                $window['start']->format('Y-m-d'),
                $window['end']->format('Y-m-d'),
            ),
            'headers' => [
                'Student Name',
                'Student ID',
                'Registration Number',
                'Group / Stream',
                'Present',
                'Absent',
                'Late',
                'Leave',
                'Total',
                'Attendance %',
            ],
            'rows' => $this->classRows((int) $year->id, $classId, $groupId, (int) $year->institute_id, $window['start'], $window['end']),
        ];
    }

    /**
     * Per-student status export for a single date (whole roster, all pages).
     *
     * @return array{
     *     valid: bool,
     *     message: ?string,
     *     filename: string,
     *     headers: array<int, string>,
     *     rows: \Generator<int, array<int, string>>,
     * }
     */
    public function daily(CarbonInterface $date, ?int $classId, ?int $groupId): array
    {
        $report = $this->reports->dailyReport($date, $classId, $groupId);

        if (! $report['valid']) {
            return $this->invalid($report['message']);
        }

        /** @var AcademicYear $year */
        $year = $report['year'];

        return [
            'valid' => true,
            'message' => null,
            'filename' => 'daily-attendance-report-'.$date->format('Y-m-d').'.csv',
            'headers' => [
                'Student Name',
                'Student ID',
                'Registration Number',
                'Class / Grade',
                'Group / Stream',
                'Status',
            ],
            'rows' => $this->dailyRows((int) $year->id, $classId, $groupId, (int) $year->institute_id, $date->toDateString()),
        ];
    }

    // -------------------------------------------------------------- Rows

    /**
     * @return \Generator<int, array<int, string>>
     */
    private function studentRows(Student $student, CarbonInterface $start, CarbonInterface $end): \Generator
    {
        foreach ($this->reports->studentRecordsStream($student, $start, $end) as $record) {
            $placement = $record->academic_placement;

            yield [
                $student->full_name,
                $student->student_id_number ?? '',
                $student->reg_no ?? '',
                $placement?->academicYear?->name ?? '',
                $placement?->classGrade?->name ?? '',
                $placement?->academicGroup?->name ?? '—',
                $record->class_date?->toDateString() ?? '',
                $record->batch?->name ?? '',
                $record->status,
                $record->remarks ?? '—',
            ];
        }
    }

    /**
     * @return \Generator<int, array<int, string>>
     */
    private function classRows(int $yearId, ?int $classId, ?int $groupId, int $instituteId, CarbonInterface $start, CarbonInterface $end): \Generator
    {
        $roster = $this->reports->rosterForExport($yearId, $classId, $groupId);

        $counts = $this->reports->countsForStudents(
            $roster->pluck('student_id')->map(fn ($id) => (int) $id)->values()->all(),
            $instituteId,
            $start,
            $end,
        );

        foreach ($roster as $entry) {
            $student = $entry->student;

            $summary = $counts->get((int) $student->id) ?? [
                'total' => 0,
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'leave' => 0,
                'present_percent' => null,
            ];

            yield [
                $student->full_name,
                $student->student_id_number ?? '',
                $student->reg_no ?? '',
                $entry->academicGroup?->name ?? '—',
                $summary['present'],
                $summary['absent'],
                $summary['late'],
                $summary['leave'],
                $summary['total'],
                $summary['present_percent'] !== null ? number_format($summary['present_percent'], 1).'%' : '—',
            ];
        }
    }

    /**
     * @return \Generator<int, array<int, string>>
     */
    private function dailyRows(int $yearId, ?int $classId, ?int $groupId, int $instituteId, string $day): \Generator
    {
        $roster = $this->reports->rosterForExport($yearId, $classId, $groupId);

        $statuses = $this->reports->statusesForStudents(
            $roster->pluck('student_id')->map(fn ($id) => (int) $id)->values()->all(),
            $instituteId,
            $day,
        );

        foreach ($roster as $entry) {
            $student = $entry->student;
            $record = $statuses->get((int) $student->id);

            yield [
                $student->full_name,
                $student->student_id_number ?? '',
                $student->reg_no ?? '',
                $entry->classGrade?->name ?? ('Class #'.$entry->class_grade_id),
                $entry->academicGroup?->name ?? '—',
                $record?->status ?? 'not recorded',
            ];
        }
    }

    // ------------------------------------------------------------- Helpers

    /**
     * @return array{valid: false, message: string, filename: string, headers: array, rows: array}
     */
    private function invalid(string $message): array
    {
        return [
            'valid' => false,
            'message' => $message,
            'filename' => '',
            'headers' => [],
            'rows' => [],
        ];
    }
}
