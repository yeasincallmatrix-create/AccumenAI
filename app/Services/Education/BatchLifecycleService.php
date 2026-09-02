<?php

namespace App\Services\Education;

use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\Course;
use App\Models\InstituteCourse;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Models\Training\Enrollment;
use App\Services\BatchAuditService;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Batch lifecycle management (STEP 41).
 *
 * Single authority for batch creation, updates, status transitions, seat
 * capacity enforcement, roll-number generation and the authoritative seat
 * counter. Every mutation is audited via BatchAuditService.
 *
 * Status lifecycle (keeps the existing statuses — never renamed):
 *   upcoming  → ongoing | cancelled | archived
 *   ongoing   → completed | cancelled | archived
 *   completed → ongoing | archived
 *   cancelled → (terminal)
 *   archived  → ongoing (unarchive)
 */
class BatchLifecycleService
{
    // 'running' removed — canonical is 'ongoing'; 'running' is auto-normalized to 'ongoing' on save
    public const STATUSES = ['upcoming', 'ongoing', 'completed', 'cancelled', 'archived'];

    public const TRANSITIONS = [
        'upcoming' => ['ongoing', 'cancelled', 'archived'],
        'ongoing' => ['completed', 'cancelled', 'archived'],
        'completed' => ['ongoing', 'archived'],
        'cancelled' => [],
        'archived' => ['ongoing'],
        // Legacy 'running' transitions retained for backwards compatibility with old rows (normalized on save)
        'running' => ['completed', 'cancelled', 'archived'],
    ];

    public function __construct(
        private readonly BatchAuditService $audit,
        private readonly NotificationService $notifications,
    ) {}

    public function canTransition(string $from, string $to): bool
    {
        return in_array($to, self::TRANSITIONS[$from] ?? [], true);
    }

    public function allowedTransitions(Batch $batch): array
    {
        return self::TRANSITIONS[$batch->status] ?? [];
    }

    /**
     * Authoritative count of seats currently taken: active enrollments only.
     */
    public function activeEnrollmentCount(Batch $batch): int
    {
        return (int) $batch->enrollments()->where('status', 'active')->count();
    }

    public function availableSeats(Batch $batch): int
    {
        return max(0, (int) $batch->seat_capacity - $this->activeEnrollmentCount($batch));
    }

    /**
     * Keep the legacy seat_filled counter in sync with the authoritative
     * active-enrollment count so every view (batches, courses, classes) stays
     * consistent. Idempotent.
     */
    public function recount(Batch $batch): Batch
    {
        $active = $this->activeEnrollmentCount($batch);

        if ((int) $batch->seat_filled !== $active) {
            $batch->update(['seat_filled' => $active]);
        }

        return $batch->refresh();
    }

    public function create(int $instituteId, array $data, int $actorId): Batch
    {
        $this->assertCourseUsable($instituteId, (int) $data['course_id']);
        $this->assertYearUsable($instituteId, $data['academic_year_id'] ?? null);

        $batch = Batch::create([
            ...$data,
            'institute_id' => $instituteId,
            'batch_code' => $this->nextBatchCode($instituteId),
            'seat_filled' => 0,
        ]);

        $this->audit->record($instituteId, $actorId, 'batch_created', $batch->id, null, $this->snapshot($batch));

        return $batch;
    }

    public function update(Batch $batch, array $data, int $actorId): Batch
    {
        $old = $this->snapshot($batch);

        if (array_key_exists('course_id', $data) && (int) $data['course_id'] !== (int) $batch->course_id) {
            $this->assertCourseUsable((int) $batch->institute_id, (int) $data['course_id']);
        }

        if (array_key_exists('academic_year_id', $data) && $data['academic_year_id'] !== $batch->academic_year_id) {
            $this->assertYearUsable((int) $batch->institute_id, $data['academic_year_id'] ?? null);
        }

        // A submitted status change must follow the lifecycle transitions.
        if (array_key_exists('status', $data) && $data['status'] !== $batch->status) {
            $this->assertTransition($batch->status, $data['status']);
        }

        $batch->update($data);

        $new = $this->snapshot($batch);

        if ($old !== $new) {
            $this->audit->record((int) $batch->institute_id, $actorId, 'batch_updated', $batch->id, $old, $new);
        }

        if (array_key_exists('status', $data) && $data['status'] !== $batch->getOriginal('status')) {
            $this->notifyStatusChanged($batch, (string) $batch->status, $actorId);
        }

        return $batch->refresh();
    }

    public function changeStatus(Batch $batch, string $to, int $actorId): Batch
    {
        $this->assertTransition($batch->status, $to);

        $oldStatus = $batch->status;

        $batch->update(['status' => $to]);

        $this->audit->record((int) $batch->institute_id, $actorId, 'batch_status_changed', $batch->id, [
            'status' => $oldStatus,
        ], [
            'status' => $to,
        ]);

        $this->notifyStatusChanged($batch, $to, $actorId);

        return $batch->refresh();
    }

    /**
     * @return Collection<int, InstituteUser>
     */
    private function notifyStatusChanged(Batch $batch, string $status, int $actorId): void
    {
        $this->notifications->send('education.batch_status_changed', $this->instituteOwners((int) $batch->institute_id), [
            'batch_name' => $batch->name,
            'course_name' => $batch->course?->name,
            'status' => $status,
        ], [
            'actor_type' => 'institute_user',
            'actor_id' => $actorId,
            'link' => route('batches.show', $batch->id),
        ]);
    }

    public function remove(Batch $batch, int $actorId): void
    {
        $old = $this->snapshot($batch);

        $batch->delete();

        $this->audit->record((int) $batch->institute_id, $actorId, 'batch_deleted', $batch->id, $old, null);
    }

    /**
     * Enforce seat capacity. Throws a ValidationException keyed to $field so
     * the calling form surfaces a friendly error.
     */
    public function assertCanEnroll(Batch $batch, string $field = 'batch_id'): void
    {
        if ($this->activeEnrollmentCount($batch) >= (int) $batch->seat_capacity) {
            throw ValidationException::withMessages([
                $field => 'This batch has reached its seat capacity.',
            ]);
        }
    }

    public function nextRollNumber(Batch $batch): string
    {
        $max = (int) $batch->enrollments()
            ->where('roll_no', '!=', '')
            ->whereRaw('roll_no REGEXP ?', ['^[0-9]+$'])
            ->max(DB::raw('CAST(roll_no AS UNSIGNED)'));

        return str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    public function enrollStudent(Student $student, Batch $batch, array $data, int $instituteId, int $actorId): Enrollment
    {
        $this->assertCanEnroll($batch);

        $roll = filled($data['roll_number'] ?? null)
            ? $data['roll_number']
            : $this->nextRollNumber($batch);

        $enrollment = Enrollment::create([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'trainee_id' => $student->id,
            'batch_id' => $batch->id,
            'roll_no' => is_numeric($roll) ? (int) $roll : null,
            'enrollment_date' => $data['enrollment_date'],
            'status' => 'active',
            'payment_status' => 'pending',
        ]);

        $this->recount($batch);

        $this->audit->record($instituteId, $actorId, 'student_enrolled', $batch->id, null, [
            'student_id' => $student->id,
            'roll_number' => $roll,
        ]);

        $this->notifications->send('education.student_enrolled', $student, [
            'student_name' => $student->full_name ?: $student->first_name,
            'reg_no' => $student->reg_no,
            'course_name' => $batch->course?->name,
            'batch_name' => $batch->name,
            'start_date' => $batch->start_date instanceof \DateTimeInterface ? $batch->start_date->toDateString() : $batch->start_date,
        ], [
            'actor_type' => 'institute_user',
            'actor_id' => $actorId,
            'link' => route('students.show', $student->id),
        ]);

        $this->notifications->send('education.student_enrolled', $this->instituteOwners($instituteId), [
            'student_name' => $student->full_name ?: $student->first_name,
            'reg_no' => $student->reg_no,
            'course_name' => $batch->course?->name,
            'batch_name' => $batch->name,
        ], [
            'actor_type' => 'institute_user',
            'actor_id' => $actorId,
            'channels' => ['in_app'],
            'link' => route('students.show', $student->id),
        ]);

        return $enrollment;
    }

    public function transferBetween(Enrollment $enrollment, Batch $target, int $instituteId, int $actorId): void
    {
        $this->assertCanEnroll($target, 'target_batch_id');

        $source = $enrollment->batch;

        DB::transaction(function () use ($enrollment, $target, $source, $instituteId) {
            $enrollment->update(['status' => 'dropped']);

            Enrollment::create([
                'institute_id' => $instituteId,
                'student_id' => $enrollment->student_id,
                'trainee_id' => $enrollment->student_id,
                'batch_id' => $target->id,
                'roll_no' => $enrollment->roll_no,
                'enrollment_date' => now()->toDateString(),
                'status' => 'active',
                'payment_status' => 'pending',
            ]);

            $this->recount($source);
            $this->recount($target);
        });

        $this->audit->record($instituteId, $actorId, 'student_transferred', $target->id, [
            'from_batch_id' => $source->id,
            'student_id' => $enrollment->student_id,
            'roll_number' => $enrollment->roll_no,
        ], [
            'to_batch_id' => $target->id,
        ]);
    }

    public function dropStudent(Enrollment $enrollment, int $actorId): void
    {
        DB::transaction(function () use ($enrollment) {
            $enrollment->update(['status' => 'dropped']);
            $this->recount($enrollment->batch);
        });

        $this->audit->record((int) $enrollment->institute_id, $actorId, 'student_removed', (int) $enrollment->batch_id, null, [
            'student_id' => $enrollment->student_id,
            'roll_number' => $enrollment->roll_no,
        ]);
    }

    /**
     * A batch may only reference a course the institute can actually use:
     * an assigned InstituteCourse, an institute-owned course, or a shared
     * catalog course (institute_id null). Cross-tenant courses are rejected.
     */
    public function assertCourseUsable(int $instituteId, int $courseId): void
    {
        $course = Course::query()->find($courseId);

        if ($course === null) {
            throw ValidationException::withMessages(['course_id' => 'The selected course does not exist.']);
        }

        $assigned = InstituteCourse::query()
            ->where('institute_id', $instituteId)
            ->where('course_id', $courseId)
            ->exists();

        $usable = $assigned || $course->institute_id === null || (int) $course->institute_id === $instituteId;

        if (! $usable) {
            throw ValidationException::withMessages(['course_id' => 'The selected course is not available to this institute.']);
        }
    }

    public function assertYearUsable(int $instituteId, int|string|null $yearId): void
    {
        if (! filled($yearId)) {
            return;
        }

        $exists = AcademicYear::query()
            ->where('institute_id', $instituteId)
            ->whereKey((int) $yearId)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages(['academic_year_id' => 'The selected academic year does not belong to this institute.']);
        }
    }

    /**
     * Institute super-users who should receive in-app notices about batch
     * lifecycle changes. Owner status comes from the role slug, never from
     * request input.
     *
     * @return Collection<int, InstituteUser>
     */
    private function instituteOwners(int $instituteId): Collection
    {
        return InstituteUser::query()
            ->where('institute_id', $instituteId)
            ->whereHas('role', fn ($q) => $q->where('slug', 'institute-owner'))
            ->get();
    }

    private function assertTransition(string $from, string $to): void
    {
        if ($to === $from) {
            return;
        }

        if (! in_array($to, self::STATUSES, true) || ! $this->canTransition($from, $to)) {
            throw ValidationException::withMessages([
                'status' => "A batch cannot move from {$from} to {$to} directly.",
            ]);
        }
    }

    private function nextBatchCode(int $instituteId): string
    {
        $count = Batch::query()
            ->withoutGlobalScope('institute')
            ->where('institute_id', $instituteId)
            ->count();

        return 'B'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Batch $batch): array
    {
        return [
            'name' => $batch->name,
            'course_id' => $batch->course_id,
            'academic_year_id' => $batch->academic_year_id,
            'shift' => $batch->shift,
            'start_date' => $batch->start_date instanceof \DateTimeInterface ? $batch->start_date->toDateString() : $batch->start_date,
            'end_date' => $batch->end_date instanceof \DateTimeInterface ? $batch->end_date->toDateString() : $batch->end_date,
            'seat_capacity' => $batch->seat_capacity,
            'seat_filled' => $batch->seat_filled,
            'status' => $batch->status,
        ];
    }
}
