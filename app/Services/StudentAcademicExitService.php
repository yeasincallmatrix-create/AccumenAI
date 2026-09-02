<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use Illuminate\Support\Facades\DB;

/**
 * Official academic exit actions (Step 17): withdrawal + transfer.
 *
 * Deliberately NOT a parallel lifecycle source of truth: the ONLY official
 * lifecycle marker written here is the existing
 * student_academic_placements.status column (`dropped` = withdrawal,
 * `transferred` = transfer-out). The services never delete a placement, never
 * touch published final-result snapshots or promotion decisions, and never
 * create a duplicate placement.
 *
 * Guards: an exit action may only close the student's CURRENT ACTIVE
 * placement. Once closed the action is idempotent — a second call is refused
 * until a new active placement exists, which prevents duplicate withdrawal /
 * transfer states.
 */
class StudentAcademicExitService
{
    /**
     * Mark the student's current active placement as withdrawn (`dropped`).
     *
     * Withdrawal means "student officially stopped continuing the current
     * academic program". Historical placements, marks, results and promotion
     * history are preserved.
     */
    public function withdraw(Student $student, ?string $reason = null): StudentAcademicPlacement
    {
        return $this->close($student, StudentAcademicPlacement::STATUS_DROPPED, $reason);
    }

    /**
     * Mark the student's current active placement as transferred.
     *
     * The student officially moved out of this placement/context. Where they
     * continue is recorded through the existing placement / promotion flows
     * (a new placeholder is never invented here and never duplicated).
     *
     * When an in-institute target branch is supplied the student's branch
     * follows the transfer; otherwise the branch is unchanged.
     */
    public function transfer(Student $student, ?string $reason = null, ?Branch $targetBranch = null): StudentAcademicPlacement
    {
        return DB::transaction(function () use ($student, $reason, $targetBranch) {
            $placement = $this->close($student, StudentAcademicPlacement::STATUS_TRANSFERRED, $reason);

            if ($targetBranch !== null) {
                $student->update(['branch_id' => $targetBranch->id]);
            }

            return $placement;
        });
    }

    /**
     * Close the student's current active placement with the given official
     * status. Refuses to operate when there is nothing to close or when the
     * current placement is already closed, so no conflicting lifecycle states
     * can coexist.
     */
    private function close(Student $student, string $status, ?string $reason): StudentAcademicPlacement
    {
        $placement = $student->currentAcademicPlacement();

        abort_if($placement === null, 422, 'This student has no academic placement to close.');
        abort_if($placement->status !== StudentAcademicPlacement::STATUS_ACTIVE, 422, 'This student has no active academic placement to close.');

        return DB::transaction(function () use ($placement, $status, $reason) {
            $placement->update([
                'status' => $status,
                'notes' => $reason !== null && trim($reason) !== ''
                    ? trim($reason)
                    : $placement->notes,
            ]);

            return $placement->refresh();
        });
    }
}
