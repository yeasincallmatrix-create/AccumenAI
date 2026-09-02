<?php

namespace App\Services;

use App\Models\AcademicFinalResult;
use App\Models\PromotionDecision;
use App\Models\PromotionDecisionItem;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Read-only aggregate of a student's official academic lifecycle.
 *
 * Two existing sources of truth are consulted — never a duplicate one:
 *
 *  1. The promotion engine: a PromotionDecisionItem whose
 *     PromotionDecision is APPROVED and whose source result is the PUBLISHED
 *     final result. `completed` / `graduated` are existing promotion outcomes.
 *  2. The existing student_academic_placements.status column: a current
 *     placement closed as `dropped` / `transferred` (written by
 *     StudentAcademicExitService) is derived as `withdrawn` / `transferred`
 *     when no promotion decision exists.
 *
 * The service derives the current lifecycle state and never writes anything.
 * Tenant + branch isolation is preserved by reaching the unscoped item rows
 * through the tenant + branch scoped PromotionDecision parent, and the current
 * placement through the tenant + branch scoped Student.
 */
class StudentAcademicLifecycleService
{
    public const OUTCOME_WITHDRAWN = 'withdrawn';

    public const OUTCOME_TRANSFERRED = 'transferred';

    /**
     * Latest official lifecycle state for the student.
     *
     * @return array{
     *     outcome: string,
     *     isCompletion: bool,
     *     isGraduation: bool,
     *     isWithdrawal: bool,
     *     isTransfer: bool,
     *     isTerminal: bool,
     *     hasActivePlacement: bool,
     *     item: ?PromotionDecisionItem,
     *     approvedDate: ?Carbon,
     *     progressingTo: mixed,
     * }
     */
    public function forStudent(Student $student): array
    {
        $item = PromotionDecisionItem::query()
            ->where('student_id', $student->id)
            ->whereHas('decision', function (Builder $query) {
                $query->where('status', PromotionDecision::STATUS_APPROVED)
                    ->whereHas('result', fn (Builder $resultQuery) => $resultQuery->where('status', AcademicFinalResult::STATUS_PUBLISHED));
            })
            ->with([
                'decision.result.scheme.academicYear',
                'decision.result.scheme.classGrade',
                'decision.result.scheme.academicGroup',
                'placement.academicYear',
                'placement.classGrade',
                'placement.academicGroup',
                'nextPlacement.academicYear',
                'nextPlacement.classGrade',
            ])
            ->orderByDesc('approved_at')
            ->orderByDesc('id')
            ->first();

        $placement = $student->currentAcademicPlacement();
        $hasActivePlacement = $placement?->status === StudentAcademicPlacement::STATUS_ACTIVE;

        if ($item !== null) {
            $outcome = (string) $item->getAttribute('decision');
        } else {
            $outcome = match ($placement?->status) {
                StudentAcademicPlacement::STATUS_DROPPED => self::OUTCOME_WITHDRAWN,
                StudentAcademicPlacement::STATUS_TRANSFERRED => self::OUTCOME_TRANSFERRED,
                default => 'active',
            };
        }

        return [
            'outcome' => $outcome,
            'isCompletion' => $outcome === PromotionDecisionItem::DECISION_COMPLETED,
            'isGraduation' => $outcome === PromotionDecisionItem::DECISION_GRADUATED,
            'isWithdrawal' => $outcome === self::OUTCOME_WITHDRAWN,
            'isTransfer' => $outcome === self::OUTCOME_TRANSFERRED,
            'isTerminal' => in_array($outcome, [
                PromotionDecisionItem::DECISION_COMPLETED,
                PromotionDecisionItem::DECISION_GRADUATED,
                self::OUTCOME_WITHDRAWN,
                self::OUTCOME_TRANSFERRED,
            ], true),
            'hasActivePlacement' => $hasActivePlacement,
            'item' => $item,
            'approvedDate' => $item?->approved_at,
            'progressingTo' => in_array($outcome, [
                PromotionDecisionItem::DECISION_PROMOTED,
                PromotionDecisionItem::DECISION_CONDITIONAL,
            ], true) ? $item?->nextPlacement?->academicYear : null,
        ];
    }
}
