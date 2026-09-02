<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Read-only bridge between the legacy Batch-based attendance table and the
 * Education Engine's academic placement system.
 *
 * An attendance row identifies a student on a single date inside a legacy
 * batch — it carries no academic context. Because academic placements are
 * preserved per academic year (one row per year, standing side by side for
 * 2025 → Class 7 and 2026 → Class 8), the placement that was active for an
 * attendance date can be resolved from the attendance date alone using the
 * academic year's [start_date, end_date] window.
 *
 * This service never writes to the attendance table and never touches academic
 * results. It only derives context and read-only summaries, so:
 *
 *  - historical attendance is attributed to the placement that was active for
 *    that date (never the student's current placement);
 *  - changing the current placement can never rewrite past attendance;
 *  - withdrawn / transferred students keep their full attendance history.
 *
 * Tenant + branch isolation is preserved because every query is reached
 * through the tenant + branch scoped Student that owns the placement; the raw
 * attendance query is additionally pinned to the student's institute_id.
 */
class StudentAcademicAttendanceService
{
    /**
     * The academic placement that was active for the student on a given date.
     *
     * The placement whose academic year window contains the date wins,
     * preferring the most recent year when windows overlap. When no placement
     * reliably covers the date (missing / out-of-range academic dates) null is
     * returned — the caller must not fall back to the current placement,
     * otherwise historical attendance would be silently misattributed.
     */
    public function placementForDate(Student $student, CarbonInterface $date): ?StudentAcademicPlacement
    {
        $dateString = $date->toDateString();

        return $student->academicPlacements()
            ->with(['academicYear', 'classGrade', 'academicGroup'])
            ->whereHas('academicYear', function ($query) use ($dateString) {
                $query->where('start_date', '<=', $dateString)
                    ->where('end_date', '>=', $dateString);
            })
            ->orderByDesc('academic_year_id')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Read-only attendance summary for one placement (its academic year's
     * window). Returns null when the year cannot reliably define a date range,
     * i.e. no placement ↔ attendance grouping can be honest.
     *
     * @return array{
     *     placement_id: int,
     *     academic_year_id: int,
     *     total: int,
     *     present: int,
     *     absent: int,
     *     late: int,
     *     leave: int,
     *     present_percent: ?float,
     * }|null
     */
    public function summaryForPlacement(StudentAcademicPlacement $placement): ?array
    {
        if (! $placement->relationLoaded('academicYear')) {
            $placement->load('academicYear');
        }

        $year = $placement->academicYear;

        if ($year === null || $year->start_date === null || $year->end_date === null) {
            return null;
        }

        $counts = $this->attendanceInYear($placement->student_id, $placement->institute_id, $year->start_date, $year->end_date)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $present = (int) ($counts[Attendance::STATUS_PRESENT] ?? 0);
        $absent = (int) ($counts[Attendance::STATUS_ABSENT] ?? 0);
        $late = (int) ($counts[Attendance::STATUS_LATE] ?? 0);
        $leave = (int) ($counts[Attendance::STATUS_LEAVE] ?? 0);
        $total = $present + $absent + $late + $leave;

        return [
            'placement_id' => (int) $placement->id,
            'academic_year_id' => (int) $year->id,
            'total' => $total,
            'present' => $present,
            'absent' => $absent,
            'late' => $late,
            'leave' => $leave,
            'present_percent' => $total > 0 ? round($present / $total * 100, 1) : null,
        ];
    }

    /**
     * The academic year attendance window for a student: records inside the
     * year's [start_date, end_date] window, most recent first. Kept paginated
     * because the attendance table can grow very large. Returns null when the
     * year cannot reliably define a date range.
     */
    public function recordsForStudentInYear(Student $student, AcademicYear $year): ?LengthAwarePaginator
    {
        if ($year->start_date === null || $year->end_date === null) {
            return null;
        }

        return $this->attendanceInYear($student->id, $student->institute_id, $year->start_date, $year->end_date)
            ->with(['batch:id,name', 'student:id,full_name'])
            ->orderByDesc('class_date')
            ->paginate(50);
    }

    /**
     * Attendance summaries keyed by placement id for a set of placements
     * (year-window reliable rows only).
     */
    public function summariesForPlacements(Collection $placements): Collection
    {
        return $placements
            ->mapWithKeys(function (StudentAcademicPlacement $placement) {
                return [$placement->id => $this->summaryForPlacement($placement)];
            })
            ->filter(fn ($summary) => $summary !== null);
    }

    /**
     * Scoped attendance query for one student inside an inclusive date window.
     * Attendance is tenant-scoped already; the explicit institute_id guard
     * keeps the read honest even when no tenant context is active.
     */
    private function attendanceInYear(int $studentId, int $instituteId, $start, $end)
    {
        return Attendance::query()
            ->where('student_id', $studentId)
            ->where('institute_id', $instituteId)
            ->whereBetween('class_date', [$start, $end]);
    }
}
