<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\InstituteUser;
use App\Models\Student;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admission lifecycle workflow (Step 36 + Approval Workflow).
 *
 * The funnel lives on the existing students row (admission_status) so there is
 * a single source of truth and no parallel "admission" table. Transitions are
 * server-side only and audited into the existing audit_logs table with
 * module='admission'. 'enrolled' / 'withdrawn' are reached programmatically by
 * the enrollment and academic-exit flows respectively, never by the status
 * button UI.
 *
 * Enrolled/withdrawn may never regress; rejected/cancelled/withdrawn are
 * terminal. Historical academic data is never touched here.
 *
 * Approval workflow:
 * - Owner/Admin: admission auto-approves on create (no pending state).
 * - Staff/Teacher: admission goes to 'submitted' → pending approval.
 * - Users with admission.approve permission can approve/reject.
 */
class AdmissionWorkflowService
{
    public const TRANSITIONS = [
        Student::ADMISSION_STATUS_DRAFT => [Student::ADMISSION_STATUS_SUBMITTED, Student::ADMISSION_STATUS_CANCELLED],
        Student::ADMISSION_STATUS_SUBMITTED => [Student::ADMISSION_STATUS_UNDER_REVIEW, Student::ADMISSION_STATUS_REJECTED, Student::ADMISSION_STATUS_CANCELLED],
        Student::ADMISSION_STATUS_UNDER_REVIEW => [Student::ADMISSION_STATUS_APPROVED, Student::ADMISSION_STATUS_REJECTED, Student::ADMISSION_STATUS_CANCELLED],
        Student::ADMISSION_STATUS_APPROVED => [Student::ADMISSION_STATUS_ENROLLED, Student::ADMISSION_STATUS_CANCELLED],
        Student::ADMISSION_STATUS_ENROLLED => [Student::ADMISSION_STATUS_WITHDRAWN],
        Student::ADMISSION_STATUS_REJECTED => [],
        Student::ADMISSION_STATUS_CANCELLED => [],
        Student::ADMISSION_STATUS_WITHDRAWN => [],
    ];

    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public static function nextStatuses(Student $student): array
    {
        return self::TRANSITIONS[$student->admission_status] ?? [];
    }

    /**
     * Statuses reachable from the UI. 'enrolled' and 'withdrawn' are reached
     * only by the enrollment / academic-exit flows and never by the status
     * button, so they are excluded here.
     */
    public static function manualNextStatuses(Student $student): array
    {
        return array_values(array_diff(self::nextStatuses($student), [
            Student::ADMISSION_STATUS_ENROLLED,
            Student::ADMISSION_STATUS_WITHDRAWN,
        ]));
    }

    /**
     * Determine whether the given user can directly create admissions without
     * needing approval. Owner and admin roles auto-approve.
     */
    public static function userCanDirectlyApprove(InstituteUser $user): bool
    {
        return $user->hasPermission('admission.approve');
    }

    /**
     * Whether the admission is in a pending/approvable state.
     */
    public static function isPendingApproval(Student $student): bool
    {
        return in_array($student->admission_status, [
            Student::ADMISSION_STATUS_SUBMITTED,
            Student::ADMISSION_STATUS_UNDER_REVIEW,
        ], true);
    }

    public function transition(Student $student, string $to, ?string $reason, ?int $actorId, int $instituteId): Student
    {
        $from = $student->admission_status;

        if (! in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => "Admission cannot move from {$from} to {$to}.",
            ]);
        }

        return DB::transaction(function () use ($student, $to, $reason, $actorId, $instituteId) {
            $old = $student->admission_status;
            $data = ['admission_status' => $to];

            if (in_array($to, [Student::ADMISSION_STATUS_REJECTED, Student::ADMISSION_STATUS_CANCELLED], true)) {
                $data['admission_reject_reason'] = $reason;
            }

            if ($to === Student::ADMISSION_STATUS_APPROVED) {
                $student->status = Student::STATUS_ACTIVE;
                $student->save();
            }

            $student->update($data);

            $this->audit(
                $instituteId,
                $actorId,
                "Admission {$old} → {$to}",
                $student->id,
                ['admission_status' => $old],
                ['admission_status' => $to, 'reason' => $reason],
            );

            return $student->refresh();
        });
    }

    /**
     * Approve a pending admission. Sets approval metadata, generates
     * registration number, activates the student, and notifies the creator.
     */
    public function approve(Student $student, int $actorId, int $instituteId): Student
    {
        if (! self::isPendingApproval($student)) {
            throw ValidationException::withMessages([
                'status' => 'This admission is not pending approval.',
            ]);
        }

        return DB::transaction(function () use ($student, $actorId, $instituteId) {
            $old = $student->admission_status;

            $student->update([
                'admission_status' => Student::ADMISSION_STATUS_APPROVED,
                'status' => Student::STATUS_ACTIVE,
                'approved_by' => $actorId,
                'approved_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'admission_reject_reason' => null,
            ]);

            $this->audit(
                $instituteId,
                $actorId,
                "Admission approved ({$old} → approved)",
                $student->id,
                ['admission_status' => $old],
                ['admission_status' => Student::ADMISSION_STATUS_APPROVED],
            );

            $this->notifyApprovalDecision($student, $actorId, $instituteId, true);

            return $student->refresh();
        });
    }

    /**
     * Reject a pending admission. Sets rejection metadata and notifies the
     * creator.
     */
    public function reject(Student $student, int $actorId, int $instituteId, ?string $reason = null): Student
    {
        if (! self::isPendingApproval($student)) {
            throw ValidationException::withMessages([
                'status' => 'This admission is not pending approval.',
            ]);
        }

        return DB::transaction(function () use ($student, $actorId, $instituteId, $reason) {
            $old = $student->admission_status;

            $student->update([
                'admission_status' => Student::ADMISSION_STATUS_REJECTED,
                'rejected_by' => $actorId,
                'rejected_at' => now(),
                'admission_reject_reason' => $reason,
            ]);

            $this->audit(
                $instituteId,
                $actorId,
                "Admission rejected ({$old} → rejected)",
                $student->id,
                ['admission_status' => $old],
                ['admission_status' => Student::ADMISSION_STATUS_REJECTED, 'reason' => $reason],
            );

            $this->notifyApprovalDecision($student, $actorId, $instituteId, false, $reason);

            return $student->refresh();
        });
    }

    /**
     * Notify users with admission.approve permission when a new admission is
     * submitted for approval.
     */
    public function notifyPendingApproval(Student $student, int $actorId, int $instituteId): void
    {
        $actor = InstituteUser::find($actorId);
        $approvers = $this->resolveApprovers($instituteId, $actorId);

        if ($approvers->isEmpty()) {
            return;
        }

        $course = $student->appliedCourse?->name ?? '—';
        $batch = $student->preferredBatch?->name ?? '—';

        $this->notifications->send(
            'admission.pending_approval',
            $approvers->all(),
            [
                'student_name' => $student->full_name,
                'application_number' => $student->application_number ?? '—',
                'course_name' => $course,
                'batch_name' => $batch,
                'submitted_by' => $actor?->name ?? '—',
                'institute_name' => $student->institute?->name ?? '',
            ],
            [
                'institute_id' => $instituteId,
                'link' => route('admissions.review', $student->id),
                'actor_type' => 'institute_user',
                'actor_id' => $actorId,
            ],
        );
    }

    /**
     * Fired after a successful batch enrollment: an approved admission becomes
     * 'enrolled'. Students that were never in the approved state (legacy rows)
     * are left untouched.
     */
    public function markEnrolled(Student $student, int $instituteId, ?int $actorId): void
    {
        if ($student->admission_status === Student::ADMISSION_STATUS_APPROVED) {
            $this->transition($student, Student::ADMISSION_STATUS_ENROLLED, null, $actorId, $instituteId);
        }
    }

    /**
     * Fired after an official academic exit (withdraw / transfer, Step 35).
     * Only an approved or enrolled admission can be marked withdrawn.
     */
    public function markWithdrawn(Student $student, int $instituteId, ?int $actorId): void
    {
        if (in_array($student->admission_status, [
            Student::ADMISSION_STATUS_APPROVED,
            Student::ADMISSION_STATUS_ENROLLED,
        ], true)) {
            $this->transition($student, Student::ADMISSION_STATUS_WITHDRAWN, null, $actorId, $instituteId);
        }
    }

    /**
     * Resolve all active InstituteUsers in the given institute who have the
     * admission.approve permission, excluding the actor (optional).
     */
    private function resolveApprovers(int $instituteId, ?int $exceptUserId = null): \Illuminate\Support\Collection
    {
        return InstituteUser::query()
            ->where('institute_id', $instituteId)
            ->where('status', 'active')
            ->where('id', '!=', $exceptUserId)
            ->get()
            ->filter(fn (InstituteUser $user) => $user->hasPermission('admission.approve'))
            ->values();
    }

    /**
     * Notify the admission creator about the approval/rejection decision.
     */
    private function notifyApprovalDecision(Student $student, int $actorId, int $instituteId, bool $approved, ?string $reason = null): void
    {
        $creatorId = $student->created_by;
        if ($creatorId === null || (int) $creatorId === $actorId) {
            return;
        }

        $creator = InstituteUser::find($creatorId);
        if ($creator === null) {
            return;
        }

        $event = $approved ? 'admission.approved' : 'admission.rejected';
        $course = $student->appliedCourse?->name ?? '—';
        $batch = $student->preferredBatch?->name ?? '—';

        $data = [
            'student_name' => $student->full_name,
            'application_number' => $student->application_number ?? '—',
            'course_name' => $course,
            'batch_name' => $batch,
            'status' => $approved ? 'Approved' : 'Rejected',
            'institute_name' => $student->institute?->name ?? '',
        ];

        if (! $approved && $reason !== null) {
            $data['rejection_reason'] = $reason;
        }

        $this->notifications->send(
            $event,
            $creator,
            $data,
            [
                'institute_id' => $instituteId,
                'link' => route('admissions.show', $student->id),
                'actor_type' => 'institute_user',
                'actor_id' => $actorId,
            ],
        );
    }

    private function audit(int $instituteId, ?int $actorId, string $action, ?int $recordId, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::create([
            'institute_id' => $instituteId,
            'user_type' => 'institute_user',
            'user_id' => $actorId,
            'action' => $action,
            'module' => 'admission',
            'record_id' => $recordId,
            'old_values' => $oldValues !== null ? json_encode($oldValues) : null,
            'new_values' => $newValues !== null ? json_encode($newValues) : null,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
