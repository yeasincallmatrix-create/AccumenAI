<?php

namespace App\Services\Accounting;

use App\Models\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use Illuminate\Support\Facades\DB;

/**
 * STEP 75 — Approval Workflow Service.
 *
 * Manages workflow creation, request lifecycle (submit → approve/reject),
 * and multi-step approval chains.
 */
class ApprovalWorkflowService
{
    /**
     * Create a workflow with steps.
     */
    public function createWorkflow(int $instituteId, array $data, array $steps, int $actorId): ApprovalWorkflow
    {
        return DB::transaction(function () use ($instituteId, $data, $steps, $actorId) {
            $workflow = ApprovalWorkflow::create(array_merge($data, [
                'institute_id' => $instituteId,
                'created_by' => $actorId,
            ]));

            foreach ($steps as $i => $step) {
                ApprovalStep::create([
                    'workflow_id' => $workflow->id,
                    'institute_id' => $instituteId,
                    'step_order' => $i + 1,
                    'approver_role_id' => $step['approver_role_id'],
                ]);
            }

            return $workflow;
        });
    }

    /**
     * Find matching workflow for a module + amount.
     */
    public function findMatchingWorkflow(int $instituteId, string $module, float $amount): ?ApprovalWorkflow
    {
        return ApprovalWorkflow::where('institute_id', $instituteId)
            ->where('module', $module)
            ->where('is_active', true)
            ->where('amount_from', '<=', $amount)
            ->where('amount_to', '>=', $amount)
            ->with('steps')
            ->first();
    }

    /**
     * Submit a request for approval.
     */
    public function submitRequest(int $instituteId, ApprovalWorkflow $workflow, string $refType, int $refId, float $amount, int $actorId): ApprovalRequest
    {
        return ApprovalRequest::create([
            'institute_id' => $instituteId,
            'workflow_id' => $workflow->id,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'amount' => $amount,
            'status' => 'pending_approval',
            'current_step' => 1,
            'requested_by' => $actorId,
            'requested_at' => now(),
        ]);
    }

    /**
     * Approve a request at the current step.
     */
    public function approve(ApprovalRequest $request, int $approverId, ?string $notes = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $approverId, $notes) {
            $stepOrder = $request->current_step;
            $totalSteps = $request->workflow->steps()->count();

            ApprovalAction::create([
                'request_id' => $request->id,
                'institute_id' => $request->institute_id,
                'step_order' => $stepOrder,
                'approver_id' => $approverId,
                'action' => 'approved',
                'notes' => $notes,
                'acted_at' => now(),
            ]);

            if ($stepOrder >= $totalSteps) {
                $request->update([
                    'status' => 'approved',
                    'resolved_by' => $approverId,
                    'resolved_at' => now(),
                ]);
            } else {
                $request->update(['current_step' => $stepOrder + 1]);
            }

            return $request->fresh();
        });
    }

    /**
     * Reject a request at the current step.
     */
    public function reject(ApprovalRequest $request, int $approverId, ?string $notes = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $approverId, $notes) {
            ApprovalAction::create([
                'request_id' => $request->id,
                'institute_id' => $request->institute_id,
                'step_order' => $request->current_step,
                'approver_id' => $approverId,
                'action' => 'rejected',
                'notes' => $notes,
                'acted_at' => now(),
            ]);

            $request->update([
                'status' => 'rejected',
                'resolved_by' => $approverId,
                'resolved_at' => now(),
            ]);

            return $request->fresh();
        });
    }

    /**
     * Get pending requests for a specific role.
     */
    public function pendingForRole(int $instituteId, int $roleId): \Illuminate\Database\Eloquent\Collection
    {
        $stepRoleIds = ApprovalStep::where('institute_id', $instituteId)
            ->where('approver_role_id', $roleId)
            ->pluck('workflow_id');

        return ApprovalRequest::where('institute_id', $instituteId)
            ->whereIn('workflow_id', $stepRoleIds)
            ->where('status', 'pending_approval')
            ->with('workflow', 'actions')
            ->get();
    }
}
