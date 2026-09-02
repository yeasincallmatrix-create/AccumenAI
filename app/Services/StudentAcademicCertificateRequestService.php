<?php

namespace App\Services;

use App\Models\AcademicFinalResult;
use App\Models\Certificate;
use App\Models\PromotionDecision;
use App\Models\PromotionDecisionItem;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * Step 35 — official certificate request for an eligible graduate.
 *
 * The only place a Certificate row is created on the institute side. It reuses
 * the exact eligibility rule the operations dashboard already uses: the
 * student must have an APPROVED promotion decision whose outcome is completed
 * or graduated, decided against a PUBLISHED final result. The request is
 * created as `pending` and the platform registry approves / rejects / revokes
 * it through the existing CertificateAdminController flow — numbering is
 * assigned on approval via Certificate::numberFor(), never here.
 *
 * The course / batch are taken from the student's existing enrollment (the
 * legacy source of truth that links a student to a course + batch). Nothing is
 * duplicated: no placement, result or promotion state is written.
 */
class StudentAcademicCertificateRequestService
{
    public const TERMINAL_OUTCOMES = [
        PromotionDecisionItem::DECISION_COMPLETED,
        PromotionDecisionItem::DECISION_GRADUATED,
    ];

    /**
     * Create a `pending` certificate request for an eligible student.
     *
     * @throws LogicException when the student is not eligible, has no
     *                        course/batch enrollment, or already has a request
     *                        / issued certificate for the same batch.
     */
    public function createForStudent(Student $student, int $actorId): Certificate
    {
        if ($this->eligibleItem($student) === null) {
            throw new LogicException('No approved completed/graduated final result exists for this student yet.');
        }

        $enrollment = $student->enrollments()->latest('id')->first();
        if ($enrollment === null) {
            throw new LogicException('This student has no course/batch enrollment to attach a certificate to.');
        }

        $batchId = (int) $enrollment->batch_id;

        if ($this->hasPendingRequest($student)) {
            throw new LogicException('This student already has a certificate request awaiting review.');
        }

        $issued = Certificate::query()
            ->where('student_id', $student->id)
            ->where('batch_id', $batchId)
            ->whereIn('status', ['active', 'revoked'])
            ->exists();
        if ($issued) {
            throw new LogicException('This student already has an issued certificate for this batch.');
        }

        return Certificate::create([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'course_id' => (int) $enrollment->course_id,
            'batch_id' => $batchId,
            'status' => 'pending',
            'issued_by' => $actorId,
        ]);
    }

    public function hasPendingRequest(Student $student): bool
    {
        return Certificate::query()
            ->where('student_id', $student->id)
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * Resubmit a rejected certificate request back to pending.
     *
     * Only rejected requests may be resubmitted. The original record is
     * reused — no new row is created — preserving the full audit trail.
     *
     * @throws LogicException when the certificate is not found or not rejected.
     */
    public function resubmit(Certificate $certificate): Certificate
    {
        if ($certificate->status !== 'rejected') {
            throw new LogicException('Only rejected certificate requests can be resubmitted.');
        }

        $certificate->update([
            'status' => 'pending',
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_note' => null,
        ]);

        return $certificate->fresh();
    }

    public function isEligible(Student $student): bool
    {
        return $this->eligibleItem($student) !== null;
    }

    private function eligibleItem(Student $student): ?PromotionDecisionItem
    {
        return PromotionDecisionItem::query()
            ->where('student_id', $student->id)
            ->whereIn('decision', self::TERMINAL_OUTCOMES)
            ->whereHas('decision', function (Builder $query) {
                $query->where('status', PromotionDecision::STATUS_APPROVED)
                    ->whereHas('result', fn (Builder $result) => $result->where('status', AcademicFinalResult::STATUS_PUBLISHED));
            })
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->first();
    }
}
