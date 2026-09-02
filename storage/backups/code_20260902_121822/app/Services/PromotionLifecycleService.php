<?php

namespace App\Services;

use App\Models\AcademicFinalResult;
use App\Models\AcademicGroup;
use App\Models\AcademicYear;
use App\Models\ClassGrade;
use App\Models\Institute;
use App\Models\PromotionDecision;
use App\Models\PromotionDecisionItem;
use App\Models\PromotionPolicy;
use Illuminate\Support\Facades\DB;

/**
 * Promotion decision lifecycle (Step 11).
 *
 *   published final result
 *     → createDecision() materializes per-student verdicts (pending)
 *     → review() records the reviewer (pending → review)
 *     → approve() validates targets, creates NEXT-YEAR placements for
 *       promotable outcomes, links them back and seals the decision
 *       (→ approved, terminal).
 *
 * Authoritative source rule: ONLY a PUBLISHED academic_final_results row may
 * start a decision; in-flight results are refused. The frozen snapshot
 * (result_students / result_rows) is consumed as-is, never recalculated.
 *
 * A promotion NEVER updates or deletes the source placement; it only inserts
 * a new row for the target year via PromotionPlacementService (which enforces
 * the (student_id, academic_year_id) uniqueness and revalidates subject
 * selections against the target class/group).
 */
class PromotionLifecycleService
{
    public function __construct(
        private readonly PromotionEvaluationService $evaluator,
        private readonly PromotionPlacementService $placements,
        private readonly AcademicSubjectService $subjects
    ) {}

    /**
     * Materialize a new decision cycle from a published final result.
     */
    public function createDecision(Institute $institute, PromotionPolicy $policy, AcademicFinalResult $result, ?int $actorId = null): PromotionDecision
    {
        abort_if((int) $policy->institute_id !== (int) $institute->id, 404, 'Policy does not belong to this institute.');
        abort_if((int) $result->institute_id !== (int) $institute->id, 404, 'Result does not belong to this institute.');
        abort_if($result->status !== AcademicFinalResult::STATUS_PUBLISHED, 422, 'Only a PUBLISHED final result can be evaluated for promotion.');

        $this->assertContextMatches($policy, $result);

        return DB::transaction(function () use ($institute, $policy, $result, $actorId) {
            $inflight = PromotionDecision::query()
                ->where('result_id', $result->id)
                ->whereIn('status', PromotionDecision::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->exists();

            abort_if($inflight, 422, 'This published result already has an in-flight promotion decision. Approve (or abandon) it before starting another.');

            $decision = PromotionDecision::create([
                'policy_id' => $policy->id,
                'result_id' => $result->id,
                'institute_id' => $institute->id,
                'branch_id' => $result->branch_id,
                'academic_year_id' => $policy->academic_year_id,
                'status' => PromotionDecision::STATUS_PENDING,
                'created_by' => $actorId,
            ]);

            foreach ($this->evaluator->evaluatePolicy($policy, $result) as $row) {
                $student = $row['student'];
                abort_if($student === null, 422, 'The published result contains a placement without a student.');

                PromotionDecisionItem::create([
                    'decision_id' => $decision->id,
                    'placement_id' => (int) $row['placement_id'],
                    'student_id' => (int) $student->id,
                    'decision' => $row['decision'],
                    'reasons' => $row['reasons'],
                    'target_class_grade_id' => null,
                    'target_academic_group_id' => null,
                ]);
            }

            return $decision->refresh();
        });
    }

    public function review(PromotionDecision $decision, ?int $actorId = null): PromotionDecision
    {
        abort_if(! $decision->canStartReview(), 422, 'Only a pending decision can be moved to review.');

        $decision->update([
            'status' => PromotionDecision::STATUS_REVIEW,
            'reviewed_by' => $actorId,
            'reviewed_at' => now(),
        ]);

        return $decision->refresh();
    }

    public function sendBackToReview(PromotionDecision $decision, ?int $actorId = null): PromotionDecision
    {
        abort_if(! $decision->canSendBackToReview(), 422, 'Only a decision in review can be sent back to pending.');

        $decision->update([
            'status' => PromotionDecision::STATUS_PENDING,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ]);

        return $decision->refresh();
    }

    /**
     * P2-5 — Cancel (rollback) a promotion decision.
     * Only pending/review decisions can be cancelled. If next-year placements
     * were already created (e.g., a prior approve that was partially applied),
     * they are removed only if they have no marks/final-result history; otherwise
     * cancellation is blocked. The decision status becomes 'cancelled' (terminal).
     */
    public function cancelDecision(PromotionDecision $decision, ?int $actorId = null): PromotionDecision
    {
        abort_if(! $decision->canCancel(), 422, 'Only pending or review decisions can be cancelled. A cancelled or approved decision cannot be cancelled.');

        return DB::transaction(function () use ($decision, $actorId) {
            $decision->load('items.nextPlacement');

            // Lock the decision row for concurrent cancel vs approve.
            PromotionDecision::whereKey($decision->id)->lockForUpdate()->firstOrFail();

            foreach ($decision->items as $item) {
                $nextId = $item->next_placement_id;
                if ($nextId === null) {
                    continue;
                }

                // Block if next placement already has historical data.
                $hasMarks = \App\Models\AcademicStudentMark::where('academic_placement_id', $nextId)->exists();
                $hasFinalRows = \App\Models\AcademicFinalResultRow::where('placement_id', $nextId)->exists();
                $hasFinalStudents = \App\Models\AcademicFinalResultStudent::where('placement_id', $nextId)->exists();
                $hasPromotionHistory = \App\Models\PromotionDecisionItem::where('next_placement_id', $nextId)->where('decision_id', '!=', $decision->id)->exists();

                if ($hasMarks || $hasFinalRows || $hasFinalStudents || $hasPromotionHistory) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'decision' => 'Cannot cancel: student '.$item->student_id.' already has marks or finalized results in the target placement (ID '.$nextId.').',
                    ]);
                }

                // Delete the next-year placement (hard delete — it was just created for this decision).
                \App\Models\StudentAcademicPlacement::whereKey($nextId)->delete();

                $item->update([
                    'next_placement_id' => null,
                    'target_class_grade_id' => null,
                    'target_academic_group_id' => null,
                ]);
            }

            $update = [
                'status' => PromotionDecision::STATUS_CANCELLED,
            ];
            // Use cancelled_* columns if they exist (added by P2-5 migration), otherwise fallback to approved_* for audit.
            if (\Illuminate\Support\Facades\Schema::hasColumn('promotion_decisions', 'cancelled_by')) {
                $update['cancelled_by'] = $actorId;
            }
            if (\Illuminate\Support\Facades\Schema::hasColumn('promotion_decisions', 'cancelled_at')) {
                $update['cancelled_at'] = now();
            }

            $decision->update($update);

            return $decision->refresh();
        });
    }

    /**
     * Approve a decision: validate per-student targets, create next-year
     * placements for promotable outcomes and seal the cycle.
     *
     * @param  array<int|string, array<string, mixed>>  $targets  keyed by source placement_id
     */
    public function approve(
        Institute $institute,
        PromotionDecision $decision,
        AcademicYear $targetYear,
        array $targets,
        ?int $actorId = null
    ): PromotionDecision {
        abort_if((int) $decision->institute_id !== (int) $institute->id, 404, 'Decision does not belong to this institute.');
        abort_if(! $decision->canApprove(), 422, 'Only a pending or in-review decision can be approved.');
        abort_if((int) $targetYear->institute_id !== (int) $institute->id, 404, 'Target academic year does not belong to this institute.');

        return DB::transaction(function () use ($institute, $decision, $targetYear, $targets, $actorId) {
            $decision->load('items.placement.student', 'items.nextPlacement');

            foreach ($decision->items as $item) {
                if ($item->needsPlacement()) {
                    $target = $targets[(string) $item->placement_id] ?? null;
                    abort_if($target === null || empty($target['class_grade_id']), 422, 'Missing target class for '.($item->student?->full_name ?? 'a student').'.');

                    $targetClass = $this->classWithinInstitute($institute, (int) $target['class_grade_id']);
                    abort_if($targetClass === null, 422, 'Invalid target class for '.($item->student?->full_name ?? 'a student').'.');
                    $targetGroup = $this->groupWithinClass($targetClass, $target['academic_group_id'] ?? null);

                    $source = $item->placement;
                    abort_if($source === null, 422, 'Source placement for '.($item->student?->full_name ?? 'a student').' no longer exists.');

                    $next = $this->placements->createPlacement(
                        $institute,
                        $source->student,
                        $targetYear,
                        $targetClass,
                        $targetGroup,
                        $source
                    );

                    $item->update([
                        'target_class_grade_id' => $targetClass->id,
                        'target_academic_group_id' => $targetGroup?->id,
                        'next_placement_id' => $next->id,
                        'approved_by' => $actorId,
                        'approved_at' => now(),
                    ]);
                } else {
                    $item->update([
                        'target_class_grade_id' => $item->target_class_grade_id,
                        'target_academic_group_id' => $item->target_academic_group_id,
                        'approved_by' => $actorId,
                        'approved_at' => now(),
                    ]);
                }
            }

            $decision->update([
                'status' => PromotionDecision::STATUS_APPROVED,
                'approved_by' => $actorId,
                'approved_at' => now(),
            ]);

            return $decision->refresh();
        });
    }

    // ------------------------------------------------------------- Internals

    private function assertContextMatches(PromotionPolicy $policy, AcademicFinalResult $result): void
    {
        $scheme = $result->scheme;

        $matches = (int) $scheme->academic_year_id === (int) $policy->academic_year_id
            && (int) $scheme->class_grade_id === (int) $policy->class_grade_id
            && ((int) $scheme->academic_group_id ?? null) === ((int) $policy->academic_group_id ?? null);

        abort_if(! $matches, 422, 'The policy academic context does not match the published result context (year / class / group).');
    }

    private function classWithinInstitute(Institute $institute, int $classGradeId): ?ClassGrade
    {
        foreach ($this->subjects->effectiveClasses($institute) as $entry) {
            if ((int) $entry['class_grade']->id === $classGradeId) {
                return $entry['class_grade'];
            }
        }

        return null;
    }

    private function groupWithinClass(ClassGrade $classGrade, int|string|null $groupId): ?AcademicGroup
    {
        if (! filled($groupId)) {
            return null;
        }

        $group = $classGrade->groups()->where('status', true)->find((int) $groupId);
        abort_if($group === null, 422, 'Invalid target group / stream.');

        return $group;
    }
}
