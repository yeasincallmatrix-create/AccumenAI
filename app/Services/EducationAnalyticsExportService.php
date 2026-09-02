<?php

namespace App\Services;

use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Step 44 — Education analytics CSV export.
 *
 * Exports the *currently filtered* analytics datasets using exactly the same
 * tenant, branch and filter rules as EducationAnalyticsService, which it
 * delegates to. Student exports stream through lazyById so a large cohort is
 * never materialized into one PHP array; every other report is already a
 * bounded aggregate collection. Strictly read-only.
 */
class EducationAnalyticsExportService
{
    public function __construct(
        private readonly EducationAnalyticsService $analytics,
    ) {}

    /** @return array{valid: bool, message: ?string, filename: string, headers: array<int, string>, rows: iterable} */
    public function students(array $filters): array
    {
        $yearId = isset($filters['academic_year_id']) && filled($filters['academic_year_id'])
            ? (int) $filters['academic_year_id']
            : null;

        return [
            'valid' => true,
            'message' => null,
            'filename' => 'education-analytics-students-'.now()->format('Y-m-d').'.csv',
            'headers' => [
                'Student Name',
                'Student ID',
                'Registration Number',
                'Branch',
                'Academic Year',
                'Class / Grade',
                'Group / Stream',
                'Placement Status',
                'Promotion Outcome',
                'Subjects Passed',
                'Subjects Failed',
                'Attendance Records',
                'Present',
                'Attendance %',
                'Certificate Status',
            ],
            'rows' => $this->studentRows($filters, $yearId),
        ];
    }

    /** @return array{valid: bool, message: ?string, filename: string, headers: array<int, string>, rows: iterable} */
    public function courses(array $filters): array
    {
        return [
            'valid' => true,
            'message' => null,
            'filename' => 'education-analytics-courses-'.now()->format('Y-m-d').'.csv',
            'headers' => [
                'Course',
                'Batches',
                'Students',
                'Active',
                'Completed',
                'Graduated',
                'Dropped',
                'Transferred',
                'Subjects Passed',
                'Subjects Failed',
                'Pass %',
                'Attendance %',
            ],
            'rows' => $this->analytics->courses($filters)->map(fn (array $row) => [
                $row['label']->name,
                $row['batches'],
                $row['students'],
                $row['active'],
                $row['completed'],
                $row['graduated'],
                $row['dropped'],
                $row['transferred'],
                $row['passed'],
                $row['failed'],
                $row['pass_rate'] ?? '',
                $row['attendance']['present_percent'] ?? '',
            ]),
        ];
    }

    /** @return array{valid: bool, message: ?string, filename: string, headers: array<int, string>, rows: iterable} */
    public function batches(array $filters): array
    {
        return [
            'valid' => true,
            'message' => null,
            'filename' => 'education-analytics-batches-'.now()->format('Y-m-d').'.csv',
            'headers' => [
                'Batch',
                'Batch Code',
                'Course',
                'Students',
                'Active',
                'Completed',
                'Graduated',
                'Dropped',
                'Transferred',
                'Subjects Passed',
                'Subjects Failed',
                'Pass %',
                'Attendance %',
            ],
            'rows' => $this->analytics->batches($filters)->map(fn (array $row) => [
                $row['label']->name,
                $row['label']->batch_code ?? '',
                $row['course']?->name ?? '',
                $row['students'],
                $row['active'],
                $row['completed'],
                $row['graduated'],
                $row['dropped'],
                $row['transferred'],
                $row['passed'],
                $row['failed'],
                $row['pass_rate'] ?? '',
                $row['attendance']['present_percent'] ?? '',
            ]),
        ];
    }

    /** @return array{valid: bool, message: ?string, filename: string, headers: array<int, string>, rows: iterable} */
    public function attendance(array $filters): array
    {
        $report = $this->analytics->attendance($filters);

        if (! $report['valid']) {
            return $this->invalid($report['message']);
        }

        return [
            'valid' => true,
            'message' => null,
            'filename' => 'education-analytics-attendance-'.now()->format('Y-m-d').'.csv',
            'headers' => ['Period', 'Records', 'Present', 'Absent', 'Late', 'Leave', 'Attendance %'],
            'rows' => $report['buckets']->map(fn (array $bucket) => [
                $bucket['label'],
                $bucket['total'],
                $bucket['present'],
                $bucket['absent'],
                $bucket['late'],
                $bucket['leave'],
                $bucket['present_percent'] ?? '',
            ]),
        ];
    }

    /** @return array{valid: bool, message: ?string, filename: string, headers: array<int, string>, rows: iterable} */
    public function results(array $filters): array
    {
        $report = $this->analytics->results($filters);

        return [
            'valid' => true,
            'message' => null,
            'filename' => 'education-analytics-results-'.now()->format('Y-m-d').'.csv',
            'headers' => ['Final Result', 'Academic Year', 'Class / Grade', 'Status', 'Students', 'Passed', 'Failed', 'Pass %'],
            'rows' => $report['results']->map(fn (array $row) => [
                $row['result']->name,
                $row['year']?->name,
                $row['class']?->name,
                $row['result']->status,
                $row['students'],
                $row['passed'],
                $row['failed'],
                $row['pass_rate'] ?? '',
            ]),
        ];
    }

    /** @return array{valid: bool, message: ?string, filename: string, headers: array<int, string>, rows: iterable} */
    public function promotions(array $filters): array
    {
        return [
            'valid' => true,
            'message' => null,
            'filename' => 'education-analytics-promotions-'.now()->format('Y-m-d').'.csv',
            'headers' => [
                'Academic Year',
                'Pending',
                'In Review',
                'Approved',
                'Promoted',
                'Not Promoted',
                'Conditional',
                'Repeat',
                'Completed',
                'Graduated',
            ],
            'rows' => $this->analytics->promotions($filters)->map(function (array $row) {
                $statuses = $row['statuses'];
                $outcomes = $row['outcomes'];

                return [
                    $row['year']->name,
                    (int) ($statuses['pending'] ?? 0),
                    (int) ($statuses['review'] ?? 0),
                    (int) ($statuses['approved'] ?? 0),
                    (int) ($outcomes['promoted'] ?? 0),
                    (int) ($outcomes['not_promoted'] ?? 0),
                    (int) ($outcomes['conditional'] ?? 0),
                    (int) ($outcomes['repeat'] ?? 0),
                    (int) ($outcomes['completed'] ?? 0),
                    (int) ($outcomes['graduated'] ?? 0),
                ];
            }),
        ];
    }

    /** @return array{valid: bool, message: ?string, filename: string, headers: array<int, string>, rows: iterable} */
    public function completion(array $filters): array
    {
        return [
            'valid' => true,
            'message' => null,
            'filename' => 'education-analytics-completion-'.now()->format('Y-m-d').'.csv',
            'headers' => [
                'Academic Year',
                'Cohort',
                'Active',
                'Completed',
                'Graduated',
                'Dropped',
                'Transferred',
                'Completed %',
                'Graduated %',
                'Dropped %',
                'Transferred %',
            ],
            'rows' => $this->analytics->completion($filters)->map(fn (array $row) => [
                $row['year']->name,
                $row['cohort'],
                $row['active'],
                $row['completed'],
                $row['graduated'],
                $row['dropped'],
                $row['transferred'],
                $row['rates']['completed'] ?? '',
                $row['rates']['graduated'] ?? '',
                $row['rates']['dropped'] ?? '',
                $row['rates']['transferred'] ?? '',
            ]),
        ];
    }

    /** @return array{valid: bool, message: ?string, filename: string, headers: array<int, string>, rows: iterable} */
    public function certificates(array $filters): array
    {
        $report = $this->analytics->certificates($filters);

        return [
            'valid' => true,
            'message' => null,
            'filename' => 'education-analytics-certificates-'.now()->format('Y-m-d').'.csv',
            'headers' => ['Course', 'Issued', 'Revoked', 'Pending', 'Rejected', 'Total'],
            'rows' => $report['byCourse']->map(fn (array $row) => [
                $row['course']?->name,
                $row['issued'],
                $row['revoked'],
                $row['pending'],
                $row['rejected'],
                $row['total'],
            ]),
        ];
    }

    /** @return array{valid: bool, message: ?string, filename: string, headers: array<int, string>, rows: iterable} */
    public function finance(): array
    {
        $report = $this->analytics->finance();

        return [
            'valid' => true,
            'message' => null,
            'filename' => 'education-analytics-finance-'.now()->format('Y-m-d').'.csv',
            'headers' => ['Course', 'Code', 'Students', 'Invoices', 'Billed', 'Outstanding', 'Overdue', 'Discounts'],
            'rows' => $report['courses']->map(fn (object $row) => [
                $row->name,
                $row->course_code ?? '',
                $row->student_count,
                $row->invoice_count,
                $row->billed,
                $row->outstanding,
                $row->overdue,
                $row->discounts,
            ]),
        ];
    }

    /** @return array{valid: bool, message: ?string, filename: string, headers: array<int, string>, rows: iterable} */
    public function crm(): array
    {
        $report = $this->analytics->crm();

        return [
            'valid' => true,
            'message' => null,
            'filename' => 'education-analytics-crm-'.now()->format('Y-m-d').'.csv',
            'headers' => ['Lead Status', 'Leads'],
            'rows' => $report['statuses']->map(fn ($status) => [
                $status->name,
                $report['byStatus']->get((int) $status->id) ?? 0,
            ]),
        ];
    }

    // ------------------------------------------------------------------ utils

    private function studentRows(array $filters, ?int $yearId): \Generator
    {
        $buffer = [];

        foreach ($this->analytics->studentQuery($filters)->orderBy('full_name')->lazyById(200) as $student) {
            $buffer[] = $student;

            if (count($buffer) >= 200) {
                yield from $this->yieldStudents($buffer, $yearId);
                $buffer = [];
            }
        }

        if ($buffer !== []) {
            yield from $this->yieldStudents($buffer, $yearId);
        }
    }

    private function yieldStudents(array $students, ?int $yearId): \Generator
    {
        $decorated = $this->analytics->decorateStudents(new EloquentCollection($students), $yearId);

        foreach ($decorated as $row) {
            yield $this->studentCsv($row);
        }
    }

    /** @param  array{student: Student, placement: ?StudentAcademicPlacement, promotion: ?string, passed: int, failed: int, attendance: ?array, certificate_status: ?string}  $row */
    private function studentCsv(array $row): array
    {
        $student = $row['student'];
        $placement = $row['placement'];
        $attendance = $row['attendance'] ?? null;

        return [
            $student->full_name,
            $student->student_id_number,
            $student->reg_no,
            $student->branch?->name,
            $placement?->academicYear?->name,
            $placement?->classGrade?->name,
            $placement?->academicGroup?->name,
            $placement?->status,
            $row['promotion'],
            $row['passed'],
            $row['failed'],
            $attendance['total'] ?? 0,
            $attendance['present'] ?? 0,
            $attendance['present_percent'] ?? '',
            $row['certificate_status'],
        ];
    }

    /** @return array{valid: false, message: string, filename: string, headers: array<int, string>, rows: array} */
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
