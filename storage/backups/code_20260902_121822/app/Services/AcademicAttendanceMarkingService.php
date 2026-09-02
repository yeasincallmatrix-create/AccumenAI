<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\Training\Enrollment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Attendance marking for the Education Engine (Step 19).
 *
 * Keeps the legacy `attendance` table as the single source of truth. The class
 * roster is derived from tenant + branch scoped academic placements for the
 * selected year / class / group, never from a frontend-submitted student list.
 * Because `attendance.batch_id` is NOT NULL but academic placements carry no
 * batch, the legacy batch for each write is derived from the student's own
 * active enrollment (institute + date window checked server-side) — never from
 * the frontend. Students without a resolvable batch are skipped with a clear
 * reason instead of writing an invalid row.
 *
 * The whole save is transactional and the existing
 * `uq_attendance_student_date (batch_id, student_id, class_date)` unique
 * constraint remains the final duplicate barrier (re-saving updates, never
 * duplicates).
 */
class AcademicAttendanceMarkingService
{
    /** Academic years of the current institute, most recent first. */
    public function years(): Collection
    {
        return AcademicYear::query()->orderByDesc('code')->get();
    }

    /**
     * Class/grades visible in the current tenant + branch scope, gathered from
     * the placements themselves (a class that has no placements cannot be
     * marked and is not offered).
     */
    public function classOptions(): Collection
    {
        return StudentAcademicPlacement::query()
            ->inScope()
            ->with('classGrade')
            ->get(['class_grade_id'])
            ->groupBy('class_grade_id')
            ->map(fn (Collection $rows) => $rows->first()->classGrade)
            ->filter()
            ->sortBy('name')
            ->values();
    }

    /** Academic groups in scope for a year + class (as placables). */
    public function groupOptions(?int $yearId, ?int $classId): Collection
    {
        return StudentAcademicPlacement::query()
            ->inScope()
            ->when($yearId !== null, fn ($q) => $q->where('academic_year_id', $yearId))
            ->when($classId !== null, fn ($q) => $q->where('class_grade_id', $classId))
            ->whereNotNull('academic_group_id')
            ->with('academicGroup')
            ->get(['academic_group_id'])
            ->map(fn (StudentAcademicPlacement $p) => $p->academicGroup)
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * The authoritative marking roster for a context + date.
     *
     * @return array{
     *     valid: bool,
     *     message: ?string,
     *     year: ?AcademicYear,
     *     roster: Collection<int, array>,
     *     summary: ?array,
     * }
     *
     * Each roster entry:
     *   placement   StudentAcademicPlacement
     *   student     Student
     *   existing    ?Attendance  (row already stored on the date, if any)
     *   can_mark    bool
     *   reason      ?string      (why marking is not possible)
     *   batch_id    ?int         (batch to write, when can_mark)
     */
    public function rosterForContext(?int $yearId, ?int $classId, ?int $groupId, CarbonInterface $date): array
    {
        $year = $yearId !== null ? AcademicYear::query()->find($yearId) : null;

        if ($year === null) {
            return $this->invalid('Select an academic year first.');
        }
        if ($year->start_date === null || $year->end_date === null) {
            return $this->invalid('The academic year has no start/end dates configured, so attendance cannot be marked reliably.');
        }
        if ($date->lt($year->start_date) || $date->gt($year->end_date)) {
            return $this->invalid('The selected date is outside the academic year’s date range.');
        }
        if ($classId === null) {
            return $this->invalid('Select a class/grade first.');
        }

        $placements = StudentAcademicPlacement::query()
            ->inScope()
            ->where('academic_year_id', $year->id)
            ->where('class_grade_id', (int) $classId)
            ->when($groupId !== null, fn ($q) => $q->where('academic_group_id', (int) $groupId))
            ->with(['student' => fn ($q) => $q->with('branch')])
            ->orderBy('student_id')
            ->get();

        $studentIds = $placements->pluck('student_id')->unique()->values()->all();

        $existing = $studentIds !== []
            ? Attendance::query()
                ->where('class_date', $date->toDateString())
                ->whereIn('student_id', $studentIds)
                ->get()
                ->keyBy('student_id')
            : collect();

        $batchById = $this->resolveBatches($placements, $existing, $date);

        $roster = $placements->map(function (StudentAcademicPlacement $placement) use ($existing, $batchById, $date) {
            $student = $placement->student;
            $row = $existing->get((int) $student->id);
            $exitedBefore = $this->exitedBefore($placement, $date);

            if ($exitedBefore && $row === null) {
                return $this->entry($placement, $student, null, false, 'Officially exited before this date.');
            }

            if ($row !== null) {
                return $this->entry($placement, $student, $row, true, null, (int) $row->batch_id);
            }

            $batch = $batchById->get((int) $student->id);
            if ($batch === null) {
                return $this->entry($placement, $student, null, false, 'No valid batch enrollment covers this date.');
            }

            return $this->entry($placement, $student, null, true, null, (int) $batch->id);
        });

        return [
            'valid' => true,
            'message' => null,
            'year' => $year,
            'roster' => $roster,
            'summary' => $this->summaryForRows($existing->values()),
        ];
    }

    /**
     * Bulk upsert for a context + date.
     *
     * The roster is rebuilt server-side; submitted student ids that are not in
     * the roster (or cannot be marked) are ignored/skipped, never written.
     *
     * @param  array<int, string>  $submitted  student_id => status
     * @return array{summary: array, skipped: array<int, string>, changed: int}
     */
    public function saveContext(?int $yearId, ?int $classId, ?int $groupId, CarbonInterface $date, array $submitted, int $instituteId, int $markedBy): array
    {
        $context = $this->rosterForContext($yearId, $classId, $groupId, $date);

        if (! $context['valid']) {
            throw ValidationException::withMessages(['attendance_date' => $context['message']]);
        }

        $plans = [];
        $skipped = [];

        foreach ($context['roster'] as $entry) {
            $studentId = (int) $entry['student']->id;

            if (! $entry['can_mark']) {
                $skipped[$studentId] = $entry['reason'] ?? 'Not eligible for this date.';

                continue;
            }

            $status = $submitted[$studentId] ?? null;
            if ($status === null || ! in_array($status, Attendance::STATUSES, true)) {
                continue;
            }

            if ($entry['existing'] !== null && $entry['existing']->status === $status) {
                continue; // no-op resave
            }

            $plans[] = [
                'batch_id' => (int) $entry['batch_id'],
                'student_id' => $studentId,
                'status' => $status,
            ];
        }

        DB::transaction(function () use ($plans, $date, $instituteId, $markedBy) {
            foreach ($plans as $plan) {
                Attendance::query()->updateOrInsert(
                    [
                        'batch_id' => $plan['batch_id'],
                        'student_id' => $plan['student_id'],
                        'class_date' => $date->toDateString(),
                    ],
                    [
                        'institute_id' => $instituteId,
                        'status' => $plan['status'],
                        'marked_by' => $markedBy,
                    ]
                );
            }
        });

        $rosterIds = $context['roster']->pluck('student.id')->map(fn ($id) => (int) $id)->unique()->values()->all();

        $rows = Attendance::query()
            ->where('class_date', $date->toDateString())
            ->whereIn('student_id', $rosterIds)
            ->get();

        return [
            'summary' => $this->summaryForRows($rows),
            'skipped' => $skipped,
            'changed' => count($plans),
        ];
    }

    // ------------------------------------------------------------------ utils

    private function invalid(string $message): array
    {
        return [
            'valid' => false,
            'message' => $message,
            'year' => null,
            'roster' => collect(),
            'summary' => null,
        ];
    }

    /**
     * One batch per roster student that needs a new row, derived from the
     * student's active enrollments (server-side, institute + date window).
     *
     * The batch lookup intentionally bypasses the *branch* scope so that
     * branch managers can still attribute legacy enrollments: most batches in
     * the wild carry branch_id = null (they predate the branch system), and
     * the attendance row keeps the batch only as the legacy foreign key that
     * the roster/attendance joins already use. Authorization never relies on
     * the batch — the roster itself is branch-filtered through the student —
     * and every candidate is still verified for institute, soft-delete and
     * lifecycle status below.
     *
     * @return Collection<int, Batch>
     */
    private function resolveBatches(Collection $placements, Collection $existing, CarbonInterface $date): Collection
    {
        $needBatch = $placements
            ->reject(fn (StudentAcademicPlacement $placement) => $existing->has((int) $placement->student_id))
            ->reject(fn (StudentAcademicPlacement $placement) => $this->exitedBefore($placement, $date));

        if ($needBatch->isEmpty()) {
            return collect();
        }

        $studentIds = $needBatch->pluck('student_id')->unique()->values()->all();

        $enrollments = Enrollment::query()
            ->where('status', 'active')
            ->whereIn('student_id', $studentIds)
            ->get(['id', 'student_id', 'batch_id', 'institute_id'])
            ->groupBy('student_id')
            ->map(fn (Collection $rows) => $rows->sortByDesc('id'));

        $byStudent = $needBatch->mapWithKeys(function (StudentAcademicPlacement $placement) use ($enrollments) {
            $studentId = (int) $placement->student_id;

            return $enrollments->has($studentId)
                ? [$studentId => $enrollments[$studentId]->first()]
                : [];
        });

        $candidateIds = $byStudent->pluck('batch_id')->unique()->values()->all();

        $batches = $candidateIds !== []
            ? Batch::query()
                ->withoutGlobalScope('branch')
                ->whereKey($candidateIds)
                ->get()
                ->keyBy('id')
            : collect();

        return $byStudent->mapWithKeys(function (Enrollment $enrollment) use ($batches, $date) {
            $studentId = (int) $enrollment->student_id;
            $instituteId = (int) $enrollment->institute_id;
            $batch = $batches->get((int) $enrollment->batch_id);

            return $this->batchCovers($batch, $instituteId, $date)
                ? [$studentId => $batch]
                : [];
        });
    }

    private function batchCovers(?Batch $batch, int $instituteId, CarbonInterface $date): bool
    {
        if ($batch === null || (int) $batch->institute_id !== $instituteId) {
            return false;
        }
        if (in_array($batch->status, ['cancelled', 'archived'], true)) {
            return false;
        }
        if ($batch->start_date !== null && (string) $batch->start_date > $date->toDateString()) {
            return false;
        }
        if ($batch->end_date !== null && (string) $batch->end_date < $date->toDateString()) {
            return false;
        }

        return true;
    }

    /**
     * A placement closed as dropped/transferred strictly before the selected
     * date makes the student ineligible for new rows on/after that date, but
     * rows already stored before the exit stay editable.
     */
    private function exitedBefore(StudentAcademicPlacement $placement, CarbonInterface $date): bool
    {
        if (! in_array($placement->status, [StudentAcademicPlacement::STATUS_DROPPED, StudentAcademicPlacement::STATUS_TRANSFERRED], true)) {
            return false;
        }

        $closed = $placement->updated_at;

        return $closed !== null && $closed->lt($date->startOfDay());
    }

    /**
     * @return array{placement: StudentAcademicPlacement, student: Student, existing: ?Attendance, can_mark: bool, reason: ?string, batch_id: ?int}
     */
    private function entry(StudentAcademicPlacement $placement, Student $student, ?Attendance $existing, bool $canMark, ?string $reason = null, ?int $batchId = null): array
    {
        return [
            'placement' => $placement,
            'student' => $student,
            'existing' => $existing,
            'can_mark' => $canMark,
            'reason' => $reason,
            'batch_id' => $batchId,
        ];
    }

    /**
     * @return array{total: int, present: int, absent: int, late: int, leave: int, present_percent: ?float}|null
     */
    private function summaryForRows(Collection $rows): ?array
    {
        $present = (int) $rows->where('status', Attendance::STATUS_PRESENT)->count();
        $absent = (int) $rows->where('status', Attendance::STATUS_ABSENT)->count();
        $late = (int) $rows->where('status', Attendance::STATUS_LATE)->count();
        $leave = (int) $rows->where('status', Attendance::STATUS_LEAVE)->count();
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
}
