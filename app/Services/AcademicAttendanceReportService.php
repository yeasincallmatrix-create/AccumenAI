<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Step 20 — Read-only academic attendance reporting.
 *
 * Student, class/group and daily reports are computed live from the institute's
 * `attendance` table — never from cached report tables — and reuse the same
 * tenant + branch context rules as the marking workflow (placements reached
 * through `inScope()`). Attendance rows on dates a placement does not reliably
 * cover are never silently attributed to a class: the student report surfaces
 * them as unclassified, while the class/daily reports only aggregate rows of
 * students placed in the selected context.
 *
 * This service is strictly read-only; it never writes to attendance,
 * placements, results, snapshots or promotion tables.
 */
class AcademicAttendanceReportService
{
    public function __construct(
        private readonly AcademicAttendanceMarkingService $marking,
    ) {}

    /** Academic years of the current institute, most recent first. */
    public function years(): Collection
    {
        return $this->marking->years();
    }

    /** Class/grades visible in the current tenant + branch scope. */
    public function classOptions(): Collection
    {
        return $this->marking->classOptions();
    }

    /** Academic groups in scope for a year + class (as placables). */
    public function groupOptions(?int $yearId, ?int $classId): Collection
    {
        return $this->marking->groupOptions($yearId, $classId);
    }

    /**
     * Per-placement summary + whole-window totals for one student.
     *
     * @return array{
     *     valid: bool,
     *     message: ?string,
     *     contexts: Collection<int, array{placement: StudentAcademicPlacement, window: array{start: CarbonInterface, end: CarbonInterface}, summary: array}>,
     *     totals: array{total:int, present:int, absent:int, late:int, leave:int, present_percent:?float},
     *     unclassified: int,
     * }
     */
    public function studentReport(Student $student, CarbonInterface $start, CarbonInterface $end, ?AcademicYear $selectedYear = null): array
    {
        $placements = $student->academicPlacements()
            ->with(['academicYear', 'classGrade', 'academicGroup'])
            ->orderByDesc('academic_year_id')
            ->orderByDesc('id')
            ->get();

        $contexts = collect();

        foreach ($placements as $placement) {
            $year = $placement->academicYear;

            if ($year === null || $year->start_date === null || $year->end_date === null) {
                continue;
            }

            if ($selectedYear !== null && (int) $year->id !== (int) $selectedYear->id) {
                continue;
            }

            $window = $this->intersect($year, $start, $end);

            if ($window === null) {
                continue;
            }

            $contexts->push([
                'placement' => $placement,
                'window' => $window,
                'summary' => $this->countsInWindow($student, $window['start'], $window['end']),
            ]);
        }

        $contexts = $contexts
            ->sortBy(fn (array $context) => $context['window']['start']->toDateString())
            ->values();

        $totals = $this->countsInWindow($student, $start, $end);
        $classified = (int) $contexts->sum(fn (array $context) => $context['summary']['total']);

        return [
            'valid' => true,
            'message' => null,
            'contexts' => $contexts,
            'totals' => $totals,
            'unclassified' => max(0, $totals['total'] - $classified),
        ];
    }

    /**
     * Paginated day records of one student inside the reported window. Each
     * record carries an `academic_placement` attribute — the placement whose
     * academic-year window covers its date (null when no placement is
     * reliably active on that date, i.e. the row is unclassified).
     */
    public function studentRecords(Student $student, CarbonInterface $start, CarbonInterface $end): LengthAwarePaginator
    {
        $records = $this->studentRecordQuery($student, $start, $end)
            ->orderByDesc('class_date')
            ->paginate(50);

        $placements = $student->academicPlacements()
            ->with(['academicYear', 'classGrade', 'academicGroup'])
            ->orderByDesc('academic_year_id')
            ->orderByDesc('id')
            ->get();

        $records->getCollection()->transform(function (Attendance $record) use ($placements) {
            $record->setAttribute('academic_placement', $this->placementFor($placements, $record->class_date));

            return $record;
        });

        return $records;
    }

    /**
     * Same records as studentRecords() but as a lazy, unpaginated stream — the
     * date-aware placement attribute included. Used by the CSV export so a
     * large history is never collected into one PHP array.
     *
     * @return \Generator<int, Attendance>
     */
    public function studentRecordsStream(Student $student, CarbonInterface $start, CarbonInterface $end): \Generator
    {
        $placements = $student->academicPlacements()
            ->with(['academicYear', 'classGrade', 'academicGroup'])
            ->orderByDesc('academic_year_id')
            ->orderByDesc('id')
            ->get();

        foreach ($this->studentRecordQuery($student, $start, $end)->orderBy('class_date')->lazy() as $record) {
            $record->setAttribute('academic_placement', $this->placementFor($placements, $record->class_date));

            yield $record;
        }
    }

    /**
     * Full (unpaginated) class/group roster for an academic year — the same
     * scoped placement query the class/daily reports paginate, used by the CSV
     * export so the exported dataset matches the visible report exactly.
     */
    public function rosterForExport(int $yearId, ?int $classId, ?int $groupId): Collection
    {
        return $this->rosterQuery($yearId, $classId, $groupId)
            ->orderBy('student_id')
            ->get();
    }

    /**
     * Per-student attendance summaries for many student ids over a window.
     *
     * @param  array<int, int>  $studentIds
     * @return Collection<int, array>
     */
    public function countsForStudents(array $studentIds, int $instituteId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return $this->countsByStudent($studentIds, $instituteId, $from, $to);
    }

    /**
     * The attendance rows of many students on a single day, keyed by student
     * id (a student without a row is simply absent from the map — never
     * assumed absent).
     *
     * @param  array<int, int>  $studentIds
     * @return Collection<int, Attendance>
     */
    public function statusesForStudents(array $studentIds, int $instituteId, string $day): Collection
    {
        if ($studentIds === []) {
            return collect();
        }

        return Attendance::query()
            ->where('institute_id', $instituteId)
            ->whereIn('student_id', $studentIds)
            ->where('class_date', $day)
            ->orderByDesc('id')
            ->get()
            ->keyBy('student_id');
    }

    /**
     * Class / group attendance report for an academic year over a window.
     *
     * @return array{
     *     valid: bool,
     *     message: ?string,
     *     year: ?AcademicYear,
     *     window: array{start: CarbonInterface, end: CarbonInterface},
     *     roster: LengthAwarePaginator,
     *     byStudent: Collection<int, array>,
     *     totals: array{total:int, present:int, absent:int, late:int, leave:int, present_percent:?float},
     * }
     */
    public function classReport(?int $yearId, ?int $classId, ?int $groupId, CarbonInterface $start, CarbonInterface $end): array
    {
        $year = $yearId !== null ? AcademicYear::query()->find($yearId) : null;

        if ($year === null) {
            return $this->invalid('Select an academic year first.');
        }
        if ($year->start_date === null || $year->end_date === null) {
            return $this->invalid('The academic year has no start/end dates configured, so an attendance report cannot be built reliably.');
        }
        if ($classId === null) {
            return $this->invalid('Select a class/grade first.');
        }
        if (! $this->classInScope($classId)) {
            return $this->invalid('The selected class/grade has no students in this institute context.');
        }
        if ($groupId !== null && ! $this->groupInScope($yearId, $classId, $groupId)) {
            return $this->invalid('The selected group/stream has no students in this institute context.');
        }

        $from = $start->max($year->start_date);
        $to = $end->min($year->end_date);
        $active = $from->lte($to);

        $roster = $this->rosterQuery((int) $year->id, $classId, $groupId)
            ->orderBy('student_id')
            ->paginate(50);

        $pageIds = $roster->pluck('student_id')->map(fn ($id) => (int) $id)->values()->all();

        $byStudent = $active
            ? $this->countsByStudent($pageIds, (int) $year->institute_id, $from, $to)
            : collect();

        $totals = $active
            ? $this->countsForRoster((int) $year->id, $classId, $groupId, (int) $year->institute_id, $from, $to)
            : $this->emptySummary();

        return [
            'valid' => true,
            'message' => null,
            'year' => $year,
            'window' => ['start' => $from, 'end' => $to],
            'roster' => $roster,
            'byStudent' => $byStudent,
            'totals' => $totals,
        ];
    }

    /**
     * Daily attendance report for a single date (class/group optional).
     *
     * @return array{
     *     valid: bool,
     *     message: ?string,
     *     year: ?AcademicYear,
     *     date: CarbonInterface,
     *     roster: LengthAwarePaginator,
     *     statuses: Collection<int, Attendance>,
     *     totals: array,
     * }
     */
    public function dailyReport(CarbonInterface $date, ?int $classId, ?int $groupId): array
    {
        $day = $date->toDateString();

        $year = AcademicYear::query()
            ->where('start_date', '<=', $day)
            ->where('end_date', '>=', $day)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->first();

        if ($year === null) {
            return $this->invalid('No academic year in this institute covers the selected date.');
        }

        if ($classId !== null && ! $this->classInScope($classId)) {
            return $this->invalid('The selected class/grade has no students in this institute context.');
        }
        if ($groupId !== null && ! $this->groupInScope((int) $year->id, $classId, $groupId)) {
            return $this->invalid('The selected group/stream has no students in this institute context.');
        }

        $roster = $this->rosterQuery((int) $year->id, $classId, $groupId)
            ->orderBy('student_id')
            ->paginate(50);

        $pageIds = $roster->pluck('student_id')->map(fn ($id) => (int) $id)->values()->all();

        $statuses = $pageIds !== []
            ? Attendance::query()
                ->where('institute_id', (int) $year->institute_id)
                ->whereIn('student_id', $pageIds)
                ->where('class_date', $day)
                ->orderByDesc('id')
                ->get()
                ->keyBy('student_id')
            : collect();

        $totals = $this->dailyTotals((int) $year->id, $classId, $groupId, (int) $year->institute_id, $day);

        return [
            'valid' => true,
            'message' => null,
            'year' => $year,
            'date' => $date,
            'roster' => $roster,
            'statuses' => $statuses,
            'totals' => $totals,
        ];
    }

    // ------------------------------------------------------------------ utils

    private function invalid(string $message): array
    {
        return [
            'valid' => false,
            'message' => $message,
        ];
    }

    private function intersect(AcademicYear $year, CarbonInterface $start, CarbonInterface $end): ?array
    {
        $from = $start->max($year->start_date);
        $to = $end->min($year->end_date);

        return $from->lte($to) ? ['start' => $from, 'end' => $to] : null;
    }

    private function placementFor(Collection $placements, ?CarbonInterface $date): ?StudentAcademicPlacement
    {
        if ($date === null) {
            return null;
        }

        $day = $date->toDateString();

        foreach ($placements as $placement) {
            $year = $placement->academicYear;

            if ($year !== null && $year->start_date !== null && $year->end_date !== null
                && $year->start_date->toDateString() <= $day
                && $year->end_date->toDateString() >= $day) {
                return $placement;
            }
        }

        return null;
    }

    private function rosterQuery(int $yearId, ?int $classId, ?int $groupId): Builder
    {
        return StudentAcademicPlacement::query()
            ->inScope()
            ->where('academic_year_id', $yearId)
            ->when($classId !== null, fn (Builder $query) => $query->where('class_grade_id', $classId))
            ->when($groupId !== null, fn (Builder $query) => $query->where('academic_group_id', $groupId))
            ->with([
                'student' => fn ($query) => $query->with('branch'),
                'classGrade',
                'academicGroup',
            ]);
    }

    private function studentRecordQuery(Student $student, CarbonInterface $from, CarbonInterface $to): Builder
    {
        return Attendance::query()
            ->where('institute_id', $student->institute_id)
            ->where('student_id', $student->id)
            ->whereBetween('class_date', [$from->toDateString(), $to->toDateString()])
            ->with(['batch' => fn ($query) => $query->withoutGlobalScope('branch')->select('id', 'name')]);
    }

    private function rosterStudentIds(int $yearId, ?int $classId, ?int $groupId): array
    {
        return $this->rosterQuery($yearId, $classId, $groupId)
            ->select('student_id')
            ->get()
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function classInScope(int $classId): bool
    {
        return StudentAcademicPlacement::query()
            ->inScope()
            ->where('class_grade_id', $classId)
            ->exists();
    }

    private function groupInScope(?int $yearId, ?int $classId, int $groupId): bool
    {
        return StudentAcademicPlacement::query()
            ->inScope()
            ->when($yearId !== null, fn (Builder $query) => $query->where('academic_year_id', $yearId))
            ->when($classId !== null, fn (Builder $query) => $query->where('class_grade_id', $classId))
            ->where('academic_group_id', $groupId)
            ->exists();
    }

    private function countsByStudent(array $studentIds, int $instituteId, CarbonInterface $from, CarbonInterface $to): Collection
    {
        if ($studentIds === []) {
            return collect();
        }

        $rows = Attendance::query()
            ->where('institute_id', $instituteId)
            ->whereIn('student_id', $studentIds)
            ->whereBetween('class_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('student_id, status, count(*) as c')
            ->groupBy('student_id', 'status')
            ->get();

        return $rows
            ->groupBy('student_id')
            ->map(fn (Collection $group) => $this->summary($group->pluck('c', 'status')));
    }

    private function countsForRoster(int $yearId, ?int $classId, ?int $groupId, int $instituteId, CarbonInterface $from, CarbonInterface $to): array
    {
        $ids = $this->rosterStudentIds($yearId, $classId, $groupId);

        if ($ids === []) {
            return $this->emptySummary();
        }

        $rows = Attendance::query()
            ->where('institute_id', $instituteId)
            ->whereIn('student_id', $ids)
            ->whereBetween('class_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->get();

        return $this->summary($rows->pluck('c', 'status'));
    }

    private function dailyTotals(int $yearId, ?int $classId, ?int $groupId, int $instituteId, string $day): array
    {
        $ids = $this->rosterStudentIds($yearId, $classId, $groupId);

        if ($ids === []) {
            return array_merge($this->emptySummary(), ['marked' => 0, 'unmarked' => 0]);
        }

        $rows = Attendance::query()
            ->where('institute_id', $instituteId)
            ->whereIn('student_id', $ids)
            ->where('class_date', $day)
            ->get();

        $marked = $rows->count();

        return array_merge(
            $this->summary($rows->groupBy('status')->map->count()),
            ['marked' => $marked, 'unmarked' => max(0, count($ids) - $marked)],
        );
    }

    private function countsInWindow(Student $student, CarbonInterface $from, CarbonInterface $to): array
    {
        $rows = Attendance::query()
            ->where('institute_id', $student->institute_id)
            ->where('student_id', $student->id)
            ->whereBetween('class_date', [$from->toDateString(), $to->toDateString()])
            ->selectRaw('status, count(*) as c')
            ->groupBy('status')
            ->get();

        return $this->summary($rows->pluck('c', 'status'));
    }

    /**
     * @param  Collection<int, mixed>  $counts  status => count
     * @return array{total:int, present:int, absent:int, late:int, leave:int, present_percent:?float}
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
