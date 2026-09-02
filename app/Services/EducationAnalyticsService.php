<?php

namespace App\Services;

use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultStudent;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Certificate;
use App\Models\ClassGrade;
use App\Models\Course;
use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\CrmLeadStatus;
use App\Models\CrmOrganization;
use App\Models\PromotionDecision;
use App\Models\PromotionDecisionItem;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\Training\Enrollment;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\ReceivablesPayablesService;
use App\Services\Education\StudentFinanceService;
use App\Support\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Step 44 — Education Analytics & Reports (read-only).
 *
 * Administrator-facing analytics layer. Everything here is an aggregate over
 * existing authoritative tables (students, placements, attendance, frozen
 * result snapshots, promotion decision items, certificates, education finance
 * invoices and CRM leads). Nothing writes, and no metric is recomputed with
 * new business logic — the same semantics as AcademicDashboardService and
 * AcademicAttendanceReportService are reused throughout:
 *
 *   - attendance counts only recorded rows; unrecorded days are never treated
 *     as absent;
 *   - completed / graduated figures are approved promotion decision outcomes,
 *     never guessed from statuses;
 *   - result figures come from the frozen published snapshots only;
 *   - tenant + branch isolation is inherited from the scoped models. Rows
 *     whose table has no direct branch_id (attendance, certificates, result
 *     snapshots, decision items) are always reached through a scoped parent
 *     (scoped placement / result / student subquery), never standalone.
 */
class EducationAnalyticsService
{
    public function __construct(
        private readonly AcademicDashboardService $dashboard,
        private readonly ReceivablesPayablesService $receivables,
        private readonly FinancialReportService $financials,
        private readonly StudentFinanceService $studentFinance,
    ) {}

    /** Academic years of the current institute, most recent first. */
    public function years(): Collection
    {
        return AcademicYear::query()->orderByDesc('id')->get();
    }

    /**
     * Consolidated admin overview: the academic operations summary plus, gated
     * by the caller's permissions, finance and CRM headline sections.
     *
     * @return array{academic: array, finance: ?array, crm: ?array}
     */
    public function overview(bool $financeAllowed, bool $crmAllowed, ?int $branchId = null): array
    {
        return [
            'academic' => $this->dashboard->summary(),
            'finance' => $financeAllowed ? $this->finance($branchId) : null,
            'crm' => $crmAllowed ? $this->crm() : null,
        ];
    }

    // ------------------------------------------------------------- Students

    /**
     * Base student query honouring the shared analytics filters (term, status,
     * admission window, course, batch, class/group within a year). Tenant +
     * branch scope is inherited from the Student global scopes.
     */
    public function studentQuery(array $filters = []): Builder
    {
        $yearId = isset($filters['academic_year_id']) && filled($filters['academic_year_id'])
            ? (int) $filters['academic_year_id']
            : null;

        $query = Student::query()->with('branch');

        if (filled($filters['term'] ?? null)) {
            $term = '%'.trim((string) $filters['term']).'%';
            $query->where(fn (Builder $q) => $q
                ->where('full_name', 'like', $term)
                ->orWhere('student_id_number', 'like', $term)
                ->orWhere('reg_no', 'like', $term)
                ->orWhere('phone', 'like', $term));
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', (string) $filters['status']);
        }

        if (filled($filters['branch_id'] ?? null)) {
            $query->where('branch_id', (int) $filters['branch_id']);
        }

        if (filled($filters['admission_from'] ?? null)) {
            $query->whereDate('admission_date', '>=', (string) $filters['admission_from']);
        }
        if (filled($filters['admission_to'] ?? null)) {
            $query->whereDate('admission_date', '<=', (string) $filters['admission_to']);
        }

        if (filled($filters['course_id'] ?? null)) {
            $courseId = (int) $filters['course_id'];
            $query->whereHas('enrollments', fn (Builder $q) => $q->whereHas('batch', fn (Builder $b) => $b->where('course_id', $courseId)));
        }

        if (filled($filters['batch_id'] ?? null)) {
            $batchId = (int) $filters['batch_id'];
            $query->whereHas('batches', fn (Builder $q) => $q->whereKey($batchId));
        }

        if (filled($filters['class_grade_id'] ?? null)) {
            $classId = (int) $filters['class_grade_id'];
            $query->whereHas('academicPlacements', fn (Builder $q) => $q
                ->when($yearId !== null, fn (Builder $w) => $w->where('academic_year_id', $yearId))
                ->where('class_grade_id', $classId));
        }

        if (filled($filters['academic_group_id'] ?? null)) {
            $groupId = (int) $filters['academic_group_id'];
            $query->whereHas('academicPlacements', fn (Builder $q) => $q
                ->when($yearId !== null, fn (Builder $w) => $w->where('academic_year_id', $yearId))
                ->where('academic_group_id', $groupId));
        }

        return $query;
    }

    /**
     * Paginated student analytics with the computed academic columns attached
     * to each row.
     *
     * @return array{rows: LengthAwarePaginator, total: int, year: ?AcademicYear}
     */
    public function students(array $filters = [], int $perPage = 50): array
    {
        $yearId = isset($filters['academic_year_id']) && filled($filters['academic_year_id'])
            ? (int) $filters['academic_year_id']
            : null;

        $rows = $this->studentQuery($filters)
            ->orderBy('full_name')
            ->paginate($perPage);

        $total = (int) $rows->total();

        $decorated = $this->decorateStudents($rows->getCollection(), $yearId);

        $rows->setCollection(new EloquentCollection($decorated));

        return [
            'rows' => $rows,
            'total' => $total,
            'year' => $yearId !== null ? AcademicYear::query()->find($yearId) : null,
        ];
    }

    /**
     * Attach the analytics columns (placement, promotion outcome, frozen result
     * sums, attendance summary, certificate status) to a batch of students.
     *
     * @return EloquentCollection<int, array>
     */
    public function decorateStudents(EloquentCollection $students, ?int $yearId = null): EloquentCollection
    {
        if ($students->isEmpty()) {
            return $students;
        }

        $studentIds = $students->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $placements = StudentAcademicPlacement::query()
            ->inScope()
            ->with(['academicYear', 'classGrade', 'academicGroup'])
            ->whereIn('student_id', $studentIds)
            ->when($yearId !== null, fn (Builder $q) => $q->where('academic_year_id', $yearId))
            ->orderByDesc('academic_year_id')
            ->orderByDesc('id')
            ->get();

        $placementByStudent = $placements->groupBy('student_id');

        $placementIds = $placements->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $outcomes = $this->approvedOutcomesByPlacement($placementIds);
        $resultSums = $this->publishedResultSumsByPlacement($placementIds);
        $certificates = $this->certificateStatusByStudent($studentIds);

        $reportYear = $this->resolveYear($yearId === null ? [] : ['academic_year_id' => $yearId]);
        $attendance = $this->attendanceSummaryByStudent($studentIds, $reportYear);

        return new EloquentCollection($students->map(function (Student $student) use ($placementByStudent, $outcomes, $resultSums, $certificates, $attendance) {
            $placement = $placementByStudent->get((int) $student->id)?->first();

            $placementId = $placement !== null ? (int) $placement->id : null;

            $result = $placementId !== null ? ($resultSums[$placementId] ?? null) : null;

            return [
                'student' => $student,
                'placement' => $placement,
                'promotion' => $placementId !== null ? ($outcomes[$placementId] ?? null) : null,
                'passed' => $result['passed'] ?? 0,
                'failed' => $result['failed'] ?? 0,
                'attendance' => $attendance->get((int) $student->id),
                'certificate_status' => $certificates[(int) $student->id] ?? null,
            ];
        })->values());
    }

    // -------------------------------------------------------------- Courses

    /**
     * Per-course performance rows. A course is measured through its batches in
     * scope; a course with no batches shows a zero cohort.
     *
     * @return Collection<int, array>
     */
    public function courses(array $filters = []): Collection
    {
        $yearId = isset($filters['academic_year_id']) && filled($filters['academic_year_id'])
            ? (int) $filters['academic_year_id']
            : null;

        $batches = Batch::query()->with('course')->get();

        if ($batches->isEmpty()) {
            return collect();
        }

        $batchIds = $batches->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $batchCountByCourse = $batches->whereNotNull('course_id')->groupBy('course_id')->map->count();

        $courseIds = $batches->pluck('course_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $courses = $courseIds->isEmpty()
            ? collect()
            : Course::query()->whereKey($courseIds)->get()->keyBy('id');

        $enrollments = $this->enrollmentsForBatches($batchIds);

        $studentCountByBatch = $enrollments
            ->groupBy('batch_id')
            ->map(fn (Collection $group) => $group->pluck('student_id')->unique()->count());

        $batchOfStudent = $enrollments->pluck('batch_id', 'student_id');

        $batchToCourse = $batches->pluck('course_id', 'id');

        $courseOfStudent = $batchOfStudent->map(fn ($batchId) => (int) ($batchToCourse[$batchId] ?? 0));

        $studentCountByCourse = $courseOfStudent->groupBy(fn ($courseId) => $courseId)->map->unique()->count();

        $coursePlacements = $this->placementsForStudents($courseOfStudent->keys()->map(fn ($id) => (int) $id)->values()->all(), $yearId);

        $outcomes = $this->approvedOutcomesByPlacement($coursePlacements->pluck('id')->map(fn ($id) => (int) $id)->values()->all());
        $resultSums = $this->publishedResultSumsByPlacement($coursePlacements->pluck('id')->map(fn ($id) => (int) $id)->values()->all());

        $reportYear = $this->resolveYear($yearId === null ? [] : ['academic_year_id' => $yearId]);
        $attendance = $this->attendanceSummaryByStudent($courseOfStudent->keys()->map(fn ($id) => (int) $id)->values()->all(), $reportYear);

        $placementsByCourse = $coursePlacements->map(function (StudentAcademicPlacement $placement) use ($courseOfStudent) {
            $placement->setAttribute('analytics_course_id', (int) ($courseOfStudent->get((int) $placement->student_id) ?? 0));

            return $placement;
        })->groupBy('analytics_course_id');

        $rows = collect();

        foreach ($courses as $courseId => $course) {
            $placements = $placementsByCourse->get((int) $courseId) ?? collect();

            $rows->push($this->courseRow($course, (int) ($batchCountByCourse[$courseId] ?? 0), (int) ($studentCountByCourse[$courseId] ?? 0), $placements, $outcomes, $resultSums, $attendance));
        }

        return $rows->sortByDesc('students')->values();
    }

    /** @return Collection<int, array> */
    public function batches(array $filters = []): Collection
    {
        $yearId = isset($filters['academic_year_id']) && filled($filters['academic_year_id'])
            ? (int) $filters['academic_year_id']
            : null;

        $batches = Batch::query()->with('course')->get();

        if ($batches->isEmpty()) {
            return collect();
        }

        $batchIds = $batches->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $enrollments = $this->enrollmentsForBatches($batchIds);

        $studentCountByBatch = $enrollments->groupBy('batch_id')->map(fn (Collection $group) => $group->pluck('student_id')->unique()->count());
        $batchesOfStudent = $enrollments->groupBy('student_id')->map(fn (Collection $group) => $group->pluck('batch_id')->map(fn ($id) => (int) $id)->unique()->values());

        $placements = $this->placementsForStudents($batchesOfStudent->keys()->map(fn ($id) => (int) $id)->values()->all(), $yearId);

        $outcomes = $this->approvedOutcomesByPlacement($placements->pluck('id')->map(fn ($id) => (int) $id)->values()->all());
        $resultSums = $this->publishedResultSumsByPlacement($placements->pluck('id')->map(fn ($id) => (int) $id)->values()->all());

        $reportYear = $this->resolveYear($yearId === null ? [] : ['academic_year_id' => $yearId]);
        $attendance = $this->attendanceSummaryByStudent($batchesOfStudent->keys()->map(fn ($id) => (int) $id)->values()->all(), $reportYear);

        $placementsByBatch = collect();

        foreach ($placements as $placement) {
            foreach ($batchesOfStudent->get((int) $placement->student_id) ?? collect() as $batchId) {
                $placementsByBatch->push([(int) $batchId, $placement]);
            }
        }

        $rows = collect();

        foreach ($batches as $batch) {
            $cohort = $placementsByBatch->where(0, (int) $batch->id)->map(fn ($pair) => $pair[1]);
            $studentIds = $enrollments->where('batch_id', (int) $batch->id)->pluck('student_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

            $rows->push($this->cohortRow(
                label: $batch,
                course: $batch->course,
                students: (int) ($studentCountByBatch[(int) $batch->id] ?? 0),
                placements: $cohort,
                outcomes: $outcomes,
                resultSums: $resultSums,
                attendance: $attendance->only($studentIds),
            ));
        }

        return $rows->sortByDesc('students')->values();
    }

    // ----------------------------------------------------------- Attendance

    /**
     * Attendance analytics over a window (explicit from/to or the selected /
     * current academic year): whole-window totals, weekly or monthly trend
     * buckets, and a per class/grade breakdown.
     *
     * @return array{
     *     valid: bool,
     *     message: ?string,
     *     year: ?AcademicYear,
     *     start: ?CarbonInterface,
     *     end: ?CarbonInterface,
     *     period: string,
     *     totals: array,
     *     buckets: Collection<int, array>,
     *     classes: Collection<int, array>,
     * }
     */
    public function attendance(array $filters = []): array
    {
        $year = $this->resolveYear($filters);

        $start = $this->windowStart($filters, $year);
        $end = $this->windowEnd($filters, $year);

        $invalid = $start === null || $end === null || $start->gt($end);

        if ($invalid) {
            return $this->attendanceInvalid($year);
        }

        $studentIds = Student::query()->select('id');

        $rows = Attendance::query()
            ->whereIn('student_id', $studentIds)
            ->whereBetween('class_date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw('class_date, status, count(*) as c')
            ->groupBy('class_date', 'status')
            ->get();

        $totals = $this->summary($rows->groupBy('status')->map->sum('c'));

        $period = $start->diffInDays($end) <= 45 ? 'week' : 'month';
        $buckets = $this->buckets($rows, $period);

        $classes = $this->attendanceClasses($year, $start, $end);

        return [
            'valid' => true,
            'message' => null,
            'year' => $year,
            'start' => $start,
            'end' => $end,
            'period' => $period,
            'totals' => $totals,
            'buckets' => $buckets,
            'classes' => $classes,
        ];
    }

    // -------------------------------------------------------------- Results

    /**
     * Final-result analytics from the frozen published snapshots only.
     *
     * @return array{totals: array, results: Collection<int, array>, classes: Collection<int, array>, year: ?AcademicYear}
     */
    public function results(array $filters = []): array
    {
        $yearId = isset($filters['academic_year_id']) && filled($filters['academic_year_id'])
            ? (int) $filters['academic_year_id']
            : null;

        $results = AcademicFinalResult::query()
            ->where('status', AcademicFinalResult::STATUS_PUBLISHED)
            ->with(['scheme.academicYear', 'scheme.classGrade', 'scheme.academicGroup', 'branch'])
            ->when($yearId !== null, fn (Builder $q) => $q->whereHas('scheme', fn (Builder $s) => $s->where('academic_year_id', $yearId)))
            ->orderByDesc('id')
            ->get();

        $resultIds = $results->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        $snapshots = $resultIds === []
            ? collect()
            : AcademicFinalResultStudent::query()
                ->whereIn('result_id', $resultIds)
                ->get(['result_id', 'placement_id', 'passed_count', 'failed_count']);

        $byResult = $snapshots->groupBy('result_id');

        $placementIds = $snapshots->pluck('placement_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $placements = $placementIds === []
            ? collect()
            : StudentAcademicPlacement::query()
                ->inScope()
                ->with('classGrade')
                ->whereIn('id', $placementIds)
                ->get(['id', 'class_grade_id']);

        $classOfPlacement = $placements->pluck('class_grade_id', 'id');

        $perResult = $results->map(function (AcademicFinalResult $result) use ($byResult) {
            $group = $byResult->get((int) $result->id) ?? collect();
            $passed = (int) $group->sum('passed_count');
            $failed = (int) $group->sum('failed_count');

            return [
                'result' => $result,
                'year' => $result->scheme?->academicYear,
                'class' => $result->scheme?->classGrade,
                'scheme' => $result->scheme,
                'students' => $group->count(),
                'passed' => $passed,
                'failed' => $failed,
                'pass_rate' => ($passed + $failed) > 0 ? round($passed / ($passed + $failed) * 100, 1) : null,
            ];
        });

        $classes = $snapshots
            ->groupBy(fn ($row) => (int) ($classOfPlacement->get((int) $row->placement_id) ?? 0))
            ->map(function (Collection $group) use ($placements) {
                $passed = (int) $group->sum('passed_count');
                $failed = (int) $group->sum('failed_count');
                $classId = (int) $group->first()->placement_id;

                return [
                    'class' => $placements->firstWhere('id', $classId)?->classGrade,
                    'results' => $group->pluck('result_id')->unique()->count(),
                    'students' => $group->count(),
                    'passed' => $passed,
                    'failed' => $failed,
                    'pass_rate' => ($passed + $failed) > 0 ? round($passed / ($passed + $failed) * 100, 1) : null,
                ];
            })
            ->sortByDesc('students')
            ->values();

        return [
            'totals' => [
                'results' => $results->count(),
                'students' => $snapshots->count(),
                'passed' => (int) $snapshots->sum('passed_count'),
                'failed' => (int) $snapshots->sum('failed_count'),
            ],
            'results' => $perResult,
            'classes' => $classes,
            'year' => $yearId !== null ? AcademicYear::query()->find($yearId) : null,
        ];
    }

    // ----------------------------------------------------------- Promotions

    /**
     * Promotion analytics per academic year: decision status counts plus the
     * approved outcome distribution.
     *
     * @return array{rows: Collection<int, array>}
     */
    public function promotions(array $filters = []): Collection
    {
        return $this->years()->map(function (AcademicYear $year) {
            $decisions = PromotionDecision::query()
                ->where('academic_year_id', $year->id)
                ->with(['result.scheme.academicYear', 'result.scheme.classGrade'])
                ->orderByDesc('id')
                ->get();

            $statuses = $decisions->groupBy('status')->map->count();

            $decisionIds = $decisions->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

            $outcomes = $decisionIds === []
                ? collect()
                : PromotionDecisionItem::query()
                    ->whereIn('decision_id', $decisionIds)
                    ->whereHas('decision', fn (Builder $q) => $q->where('status', PromotionDecision::STATUS_APPROVED))
                    ->selectRaw('decision, count(*) as c')
                    ->groupBy('decision')
                    ->pluck('c', 'decision');

            return [
                'year' => $year,
                'decisions' => $decisions,
                'statuses' => $statuses,
                'outcomes' => $outcomes,
            ];
        });
    }

    // --------------------------------------------------------- Completion

    /**
     * Completion / exit analytics per academic year: cohort, placement status
     * counts and official completed / graduated outcomes (approved decisions).
     *
     * @return Collection<int, array>
     */
    public function completion(array $filters = []): Collection
    {
        return $this->years()->map(function (AcademicYear $year) {
            $placements = StudentAcademicPlacement::query()->inScope()->where('academic_year_id', $year->id);

            $cohort = (clone $placements)->distinct()->count('student_id');

            $statusCounts = (clone $placements)
                ->selectRaw('status, count(*) as c')
                ->groupBy('status')
                ->pluck('c', 'status');

            $completed = min($this->approvedDecisionCount($year, PromotionDecisionItem::DECISION_COMPLETED), $cohort);
            $graduated = min($this->approvedDecisionCount($year, PromotionDecisionItem::DECISION_GRADUATED), $cohort);

            $active = (int) ($statusCounts[StudentAcademicPlacement::STATUS_ACTIVE] ?? 0);
            $dropped = (int) ($statusCounts[StudentAcademicPlacement::STATUS_DROPPED] ?? 0);
            $transferred = (int) ($statusCounts[StudentAcademicPlacement::STATUS_TRANSFERRED] ?? 0);

            $rate = fn (int $n) => $cohort > 0 ? round($n / $cohort * 100, 1) : null;

            return [
                'year' => $year,
                'cohort' => $cohort,
                'active' => $active,
                'completed' => $completed,
                'graduated' => $graduated,
                'dropped' => $dropped,
                'transferred' => $transferred,
                'rates' => [
                    'completed' => $rate($completed),
                    'graduated' => $rate($graduated),
                    'dropped' => $rate($dropped),
                    'transferred' => $rate($transferred),
                ],
            ];
        });
    }

    // -------------------------------------------------------- Certificates

    /**
     * Certificate analytics: status totals plus a per-course breakdown.
     *
     * @return array{totals: array, byCourse: Collection<int, array>}
     */
    public function certificates(array $filters = []): array
    {
        $base = Certificate::query()->whereIn('student_id', Student::query()->select('id'));

        $statusCounts = (clone $base)
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->pluck('c', 'status');

        $pending = (int) ($statusCounts['pending'] ?? 0);
        $issued = (int) ($statusCounts['active'] ?? 0);
        $revoked = (int) ($statusCounts['revoked'] ?? 0);
        $rejected = (int) ($statusCounts['rejected'] ?? 0);
        $total = $pending + $issued + $revoked + $rejected;

        $rows = (clone $base)
            ->whereNotNull('course_id')
            ->with('course')
            ->selectRaw('course_id, status, count(*) as c')
            ->groupBy('course_id', 'status')
            ->get();

        $byCourse = $rows->groupBy('course_id')->map(function (Collection $group) {
            $counts = $group->pluck('c', 'status');
            $issued = (int) ($counts['active'] ?? 0);
            $revoked = (int) ($counts['revoked'] ?? 0);
            $pending = (int) ($counts['pending'] ?? 0);
            $rejected = (int) ($counts['rejected'] ?? 0);

            return [
                'course' => $group->first()->course,
                'issued' => $issued,
                'revoked' => $revoked,
                'pending' => $pending,
                'rejected' => $rejected,
                'total' => $issued + $revoked + $pending + $rejected,
            ];
        })->sortByDesc('total')->values();

        return [
            'totals' => [
                'pending' => $pending,
                'issued' => $issued,
                'revoked' => $revoked,
                'rejected' => $rejected,
                'total' => $total,
                'issued_rate' => $total > 0 ? round($issued / $total * 100, 1) : null,
            ],
            'byCourse' => $byCourse,
        ];
    }

    // -------------------------------------------------------------- Finance

    /**
     * Finance & education cross-report. Reuses the existing accounting +
     * education finance services verbatim (receivables/payables, income
     * statement, per-batch / per-course billing summaries).
     *
     * @return array{receivable: float, payable: float, net: float, net_income: float, batches: Collection, courses: Collection}
     */
    public function finance(?int $branchId = null): array
    {
        $instituteId = (int) TenantContext::id();

        $totals = $this->receivables->totals($instituteId, $branchId);
        $income = $this->financials->incomeStatement($instituteId, $branchId);

        return [
            'receivable' => (float) $totals['receivable'],
            'payable' => (float) $totals['payable'],
            'net' => (float) $totals['net'],
            'net_income' => (float) ($income['net'] ?? 0),
            'batches' => $this->studentFinance->batchSummary($instituteId, $branchId),
            'courses' => $this->studentFinance->courseSummary($instituteId, $branchId),
        ];
    }

    // ------------------------------------------------------------------ CRM

    /**
     * CRM → admission analytics (gated by the caller). Headline counts plus
     * the lead → converted-admission funnel and status distribution.
     *
     * @return array{
     *     contacts: int,
     *     organizations: int,
     *     leads: int,
     *     open: int,
     *     won: int,
     *     lost: int,
     *     converted: int,
     *     conversion_rate: ?float,
     *     byStatus: Collection<int, int>,
     *     statuses: Collection<int, CrmLeadStatus>,
     * }
     */
    public function crm(): array
    {
        $wonId = (int) CrmLeadStatus::query()->where('slug', CrmLeadStatus::SLUG_WON)->value('id');
        $lostId = (int) CrmLeadStatus::query()->where('slug', CrmLeadStatus::SLUG_LOST)->value('id');

        $leads = CrmLead::query()->with('status')->get();

        $convertedLeadIds = Student::query()
            ->whereNotNull('crm_lead_id')
            ->pluck('crm_lead_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $converted = count($convertedLeadIds);

        return [
            'contacts' => CrmContact::query()->count(),
            'organizations' => CrmOrganization::query()->count(),
            'leads' => $leads->count(),
            'open' => $leads->filter(fn (CrmLead $lead) => ! in_array((int) $lead->status_id, [$wonId, $lostId], true))->count(),
            'won' => $leads->filter(fn (CrmLead $lead) => (int) $lead->status_id === $wonId)->count(),
            'lost' => $leads->filter(fn (CrmLead $lead) => (int) $lead->status_id === $lostId)->count(),
            'converted' => $converted,
            'conversion_rate' => $leads->count() > 0 ? round($converted / $leads->count() * 100, 1) : null,
            'byStatus' => $leads->groupBy(fn (CrmLead $lead) => (int) $lead->status_id)->map->count(),
            'statuses' => CrmLeadStatus::query()->where('status', 'active')->orderBy('display_order')->get(),
        ];
    }

    // ------------------------------------------------------------- Helpers

    /** Resolve the selected academic year, falling back to the current one. */
    private function resolveYear(array $filters): ?AcademicYear
    {
        $yearId = isset($filters['academic_year_id']) && filled($filters['academic_year_id'])
            ? (int) $filters['academic_year_id']
            : null;

        if ($yearId !== null) {
            return AcademicYear::query()->find($yearId);
        }

        return AcademicYear::query()->where('is_current', true)->orderByDesc('id')->first();
    }

    private function windowStart(array $filters, ?AcademicYear $year): ?CarbonInterface
    {
        if (filled($filters['from'] ?? null)) {
            return Carbon::parse((string) $filters['from'])->startOfDay();
        }

        if ($year !== null && $year->start_date !== null) {
            return $year->start_date->copy()->startOfDay();
        }

        return null;
    }

    private function windowEnd(array $filters, ?AcademicYear $year): ?CarbonInterface
    {
        if (filled($filters['to'] ?? null)) {
            return Carbon::parse((string) $filters['to'])->endOfDay();
        }

        if ($year !== null && $year->end_date !== null) {
            return $year->end_date->copy()->endOfDay();
        }

        return null;
    }

    /** @return array{valid: false, message: string, year: ?AcademicYear, start: null, end: null, period: string, totals: array, buckets: Collection, classes: Collection} */
    private function attendanceInvalid(?AcademicYear $year): array
    {
        return [
            'valid' => false,
            'message' => $year === null
                ? 'No academic year is selected and none is marked current, so an attendance window cannot be resolved.'
                : 'The selected academic year has no reliable start/end dates and no explicit window was given.',
            'year' => $year,
            'start' => null,
            'end' => null,
            'period' => 'month',
            'totals' => $this->emptySummary(),
            'buckets' => collect(),
            'classes' => collect(),
        ];
    }

    /**
     * Weekly / monthly trend buckets from the raw grouped attendance rows.
     *
     * @param  Collection<int, object>  $rows
     * @return Collection<int, array>
     */
    private function buckets(Collection $rows, string $period): Collection
    {
        $buckets = collect();

        $rows->groupBy('class_date')->each(function (Collection $statuses, string $date) use (&$buckets, $period) {
            $day = Carbon::parse($date);
            $key = $period === 'week' ? $day->startOfWeek()->toDateString() : $day->startOfMonth()->toDateString();

            $bucket = $buckets->get($key) ?? [
                'key' => $key,
                'label' => $period === 'week'
                    ? $day->startOfWeek()->format('d M Y')
                    : $day->startOfMonth()->format('M Y'),
                'total' => 0,
                'present' => 0,
                'absent' => 0,
                'late' => 0,
                'leave' => 0,
                'present_percent' => null,
            ];

            foreach ($statuses as $row) {
                $status = $row->status;
                $count = (int) $row->c;

                if ($status === Attendance::STATUS_PRESENT) {
                    $bucket['present'] += $count;
                } elseif ($status === Attendance::STATUS_ABSENT) {
                    $bucket['absent'] += $count;
                } elseif ($status === Attendance::STATUS_LATE) {
                    $bucket['late'] += $count;
                } elseif ($status === Attendance::STATUS_LEAVE) {
                    $bucket['leave'] += $count;
                }
            }

            $bucket['total'] = $bucket['present'] + $bucket['absent'] + $bucket['late'] + $bucket['leave'];
            $bucket['present_percent'] = $bucket['total'] > 0 ? round($bucket['present'] / $bucket['total'] * 100, 1) : null;

            $buckets->put($key, $bucket);
        });

        return $buckets->sortBy('key')->values();
    }

    /**
     * Per class/grade attendance totals over the window. Roster is the set of
     * placements in scope for the (selected or current) year; unrecorded days
     * are never treated as absent.
     *
     * @return Collection<int, array>
     */
    private function attendanceClasses(?AcademicYear $year, CarbonInterface $start, CarbonInterface $end): Collection
    {
        $placements = StudentAcademicPlacement::query()
            ->inScope()
            ->when($year !== null, fn (Builder $q) => $q->where('academic_year_id', $year->id))
            ->select('student_id', 'class_grade_id')
            ->get();

        if ($placements->isEmpty()) {
            return collect();
        }

        $studentIds = $placements->pluck('student_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $byStudentStatus = $studentIds === []
            ? collect()
            : Attendance::query()
                ->whereIn('student_id', $studentIds)
                ->whereBetween('class_date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw('student_id, status, count(*) as c')
                ->groupBy('student_id', 'status')
                ->get()
                ->groupBy('student_id')
                ->map(fn (Collection $group) => $group->pluck('c', 'status'));

        $classIds = $placements->pluck('class_grade_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $classNames = $classIds->isEmpty()
            ? collect()
            : ClassGrade::query()->whereKey($classIds)->pluck('name', 'id');

        return $placements
            ->groupBy('class_grade_id')
            ->map(function (Collection $group) use ($byStudentStatus, $classNames) {
                $totals = $this->emptySummary();

                foreach ($group->pluck('student_id')->map(fn ($id) => (int) $id)->unique() as $studentId) {
                    $counts = $byStudentStatus->get($studentId) ?? collect();

                    $totals['present'] += (int) ($counts[Attendance::STATUS_PRESENT] ?? 0);
                    $totals['absent'] += (int) ($counts[Attendance::STATUS_ABSENT] ?? 0);
                    $totals['late'] += (int) ($counts[Attendance::STATUS_LATE] ?? 0);
                    $totals['leave'] += (int) ($counts[Attendance::STATUS_LEAVE] ?? 0);
                }

                $totals['total'] = $totals['present'] + $totals['absent'] + $totals['late'] + $totals['leave'];
                $totals['present_percent'] = $totals['total'] > 0 ? round($totals['present'] / $totals['total'] * 100, 1) : null;

                $classId = (int) $group->first()->class_grade_id;

                return [
                    'class' => $classId > 0 ? ($classNames[$classId] ?? null) : null,
                    'students' => $group->count(),
                    ...$totals,
                ];
            })
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Enrollments of a set of batches, keyed with their student / batch /
     * course ids. The enrollments table carries institute_id and is reached
     * through the scoped batch ids, preserving tenant + branch isolation.
     */
    private function enrollmentsForBatches(array $batchIds): Collection
    {
        if ($batchIds === []) {
            return collect();
        }

        return Enrollment::query()
            ->whereIn('batch_id', $batchIds)
            ->get(['student_id', 'batch_id']);
    }

    /**
     * Placements of a set of student ids (optionally restricted to a year),
     * scoped to the tenant + branch via the owning students.
     */
    private function placementsForStudents(array $studentIds, ?int $yearId): EloquentCollection
    {
        if ($studentIds === []) {
            return new EloquentCollection;
        }

        return StudentAcademicPlacement::query()
            ->inScope()
            ->with('academicYear')
            ->whereIn('student_id', $studentIds)
            ->when($yearId !== null, fn (Builder $q) => $q->where('academic_year_id', $yearId))
            ->get();
    }

    /** placement_id => approved promotion outcome (latest approved item). */
    private function approvedOutcomesByPlacement(array $placementIds): array
    {
        if ($placementIds === []) {
            return [];
        }

        return PromotionDecisionItem::query()
            ->whereIn('placement_id', $placementIds)
            ->whereHas('decision', fn (Builder $q) => $q->where('status', PromotionDecision::STATUS_APPROVED))
            ->orderByDesc('id')
            ->get(['placement_id', 'decision'])
            ->mapToGroups(fn ($row) => [(int) $row->placement_id => $row->decision])
            ->map->first()
            ->all();
    }

    /** placement_id => ['passed' => int, 'failed' => int] from published frozen snapshots. */
    private function publishedResultSumsByPlacement(array $placementIds): array
    {
        if ($placementIds === []) {
            return [];
        }

        return AcademicFinalResultStudent::query()
            ->whereIn('placement_id', $placementIds)
            ->whereHas('result', fn (Builder $q) => $q->where('status', AcademicFinalResult::STATUS_PUBLISHED))
            ->selectRaw('placement_id, SUM(passed_count) as passed, SUM(failed_count) as failed')
            ->groupBy('placement_id')
            ->get()
            ->mapWithKeys(fn ($row) => [(int) $row->placement_id => ['passed' => (int) $row->passed, 'failed' => (int) $row->failed]])
            ->all();
    }

    /** student_id => certificate status (prefers issued over pending). */
    private function certificateStatusByStudent(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        $rows = Certificate::query()
            ->whereIn('student_id', $studentIds)
            ->whereIn('status', ['active', 'pending'])
            ->selectRaw('student_id, status, MAX(id) as mid')
            ->groupBy('student_id', 'status')
            ->get()
            ->sortByDesc(fn ($row) => $row->status === 'active' ? 1 : 0)
            ->groupBy('student_id');

        return $rows->map(fn ($group) => $group->first()->status)->all();
    }

    /** student_id => attendance summary over the year window (empty when none). */
    private function attendanceSummaryByStudent(array $studentIds, ?AcademicYear $year): Collection
    {
        if ($studentIds === [] || $year === null || $year->start_date === null || $year->end_date === null) {
            return collect();
        }

        $rows = Attendance::query()
            ->whereIn('student_id', $studentIds)
            ->whereBetween('class_date', [$year->start_date->toDateString(), $year->end_date->toDateString()])
            ->selectRaw('student_id, status, count(*) as c')
            ->groupBy('student_id', 'status')
            ->get();

        return $rows
            ->groupBy('student_id')
            ->map(fn (Collection $group) => $this->summary($group->pluck('c', 'status')));
    }

    /** Approved decision items of a year carrying the given terminal outcome. */
    private function approvedDecisionCount(AcademicYear $year, string $decision): int
    {
        $placements = StudentAcademicPlacement::query()
            ->inScope()
            ->where('academic_year_id', $year->id)
            ->select('id');

        return PromotionDecisionItem::query()
            ->where('decision', $decision)
            ->whereHas('decision', fn (Builder $q) => $q->where('status', PromotionDecision::STATUS_APPROVED))
            ->whereIn('placement_id', $placements)
            ->distinct()
            ->count('placement_id');
    }

    /** Per-course row assembled from the shared aggregates. */
    private function courseRow(Course $course, int $batches, int $students, Collection $placements, array $outcomes, array $resultSums, Collection $attendance): array
    {
        $studentIds = $placements->pluck('student_id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        return $this->cohortRow($course, $course, $students, $placements, $outcomes, $resultSums, $attendance->only($studentIds)) + [
            'batches' => $batches,
            'label' => $course,
        ];
    }

    /**
     * Shared cohort aggregation: placement status counts, approved completion
     * outcomes and published pass/fail sums for one course / batch.
     *
     * @param  Collection<int, StudentAcademicPlacement|array{0: int, 1: StudentAcademicPlacement}>  $placements
     */
    private function cohortRow(mixed $label, mixed $course, int $students, Collection $placements, array $outcomes, array $resultSums, Collection $attendance): array
    {
        $active = 0;
        $dropped = 0;
        $transferred = 0;
        $completed = 0;
        $graduated = 0;
        $passed = 0;
        $failed = 0;

        foreach ($placements as $entry) {
            $placement = is_array($entry) ? $entry[1] : $entry;

            $status = $placement->status;

            if ($status === StudentAcademicPlacement::STATUS_ACTIVE) {
                $active++;
            } elseif ($status === StudentAcademicPlacement::STATUS_DROPPED) {
                $dropped++;
            } elseif ($status === StudentAcademicPlacement::STATUS_TRANSFERRED) {
                $transferred++;
            }

            $outcome = $outcomes[(int) $placement->id] ?? null;

            if ($outcome === PromotionDecisionItem::DECISION_COMPLETED) {
                $completed++;
            } elseif ($outcome === PromotionDecisionItem::DECISION_GRADUATED) {
                $graduated++;
            }

            $result = $resultSums[(int) $placement->id] ?? null;

            if ($result !== null) {
                $passed += $result['passed'];
                $failed += $result['failed'];
            }
        }

        $totals = $this->emptySummary();

        foreach ($attendance as $summary) {
            foreach (['present', 'absent', 'late', 'leave'] as $key) {
                $totals[$key] += (int) $summary[$key];
            }
        }

        $totals['total'] = $totals['present'] + $totals['absent'] + $totals['late'] + $totals['leave'];
        $totals['present_percent'] = $totals['total'] > 0 ? round($totals['present'] / $totals['total'] * 100, 1) : null;

        $passRate = ($passed + $failed) > 0 ? round($passed / ($passed + $failed) * 100, 1) : null;

        return [
            'label' => $label,
            'course' => $course,
            'students' => $students,
            'active' => $active,
            'completed' => $completed,
            'graduated' => $graduated,
            'dropped' => $dropped,
            'transferred' => $transferred,
            'passed' => $passed,
            'failed' => $failed,
            'pass_rate' => $passRate,
            'attendance' => $totals,
        ];
    }

    /**
     * @param  Collection<int, mixed>  $counts  status => count
     * @return array{total: int, present: int, absent: int, late: int, leave: int, present_percent: ?float}
     */
    private function summary(Collection $counts): array
    {
        $present = (int) ($counts[Attendance::STATUS_PRESENT] ?? 0);
        $absent = (int) ($counts[Attendance::STATUS_ABSENT] ?? 0);
        $late = (int) ($counts[Attendance::STATUS_LATE] ?? 0);
        $leave = (int) ($counts[Attendance::STATUS_LEAVE] ?? 0);
        $total = $present + $absent + $late + $leave;

        return [
            'total' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'leave' => $leave,
            'present_percent' => $total > 0 ? round($present / $total * 100, 1) : null,
        ];
    }

    private function emptySummary(): array
    {
        return [
            'total' => 0,
            'present' => 0,
            'absent' => 0,
            'late' => 0,
            'leave' => 0,
            'present_percent' => null,
        ];
    }
}
