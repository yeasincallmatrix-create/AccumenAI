<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Workflow;
use App\Models\WorkflowHistory;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Step 51 — Lightweight Education workflow engine.
 *
 * A workflow is an ordered list of steps attached to an entity (typically a
 * student). Status transitions are validated server-side against the
 * Workflow::TRANSITIONS map; invalid transitions are rejected. Every action is
 * recorded in workflow_histories (immutable) and the shared audit_logs table.
 *
 * This is intentionally small and Education-focused — not a generic BPM
 * engine. Workflow definitions (steps per type) live in config so institutes
 * can adapt them without code changes.
 */
class WorkflowService
{
    /**
     * Create a workflow with its steps from the configured definition.
     */
    public function create(
        int $instituteId,
        string $workflowType,
        string $title,
        ?int $studentId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $branchId = null,
        ?int $initiatedBy = null,
        ?string $notes = null,
    ): Workflow {
        $definition = config("workflows.types.{$workflowType}");

        if ($definition === null) {
            throw ValidationException::withMessages([
                'workflow_type' => "Unknown workflow type: {$workflowType}.",
            ]);
        }

        return DB::transaction(function () use ($instituteId, $workflowType, $title, $studentId, $entityType, $entityId, $branchId, $initiatedBy, $notes, $definition) {
            $workflow = Workflow::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'workflow_type' => $workflowType,
                'title' => $title,
                'student_id' => $studentId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'status' => Workflow::STATUS_DRAFT,
                'current_step' => 1,
                'initiated_by' => $initiatedBy,
                'notes' => $notes,
            ]);

            $order = 1;
            foreach ($definition['steps'] ?? [] as $step) {
                WorkflowStep::create([
                    'workflow_id' => $workflow->id,
                    'step_order' => $order++,
                    'name' => $step['name'],
                    'responsible_role' => $step['role'] ?? null,
                    'responsible_permission' => $step['permission'] ?? null,
                    'status' => WorkflowStep::STATUS_PENDING,
                ]);
            }

            $this->recordHistory($workflow, $initiatedBy, 'created', null, Workflow::STATUS_DRAFT, $notes);
            $this->audit($instituteId, $initiatedBy, 'workflow_created', $workflow->id, null, [
                'workflow_type' => $workflowType,
                'title' => $title,
                'status' => Workflow::STATUS_DRAFT,
            ]);

            return $workflow->load('steps');
        });
    }

    /**
     * Transition a workflow to a new status. Validates the transition map and
     * records history + audit. Reject/return require a comment.
     */
    public function transition(
        Workflow $workflow,
        string $to,
        ?int $actorId,
        ?string $comment = null,
    ): Workflow {
        if (! $workflow->canTransitionTo($to)) {
            throw ValidationException::withMessages([
                'status' => "Workflow cannot move from {$workflow->status} to {$to}.",
            ]);
        }

        if (in_array($to, [Workflow::STATUS_REJECTED, Workflow::STATUS_RETURNED], true) && trim((string) $comment) === '') {
            throw ValidationException::withMessages([
                'comment' => 'A comment is required when rejecting or returning a workflow.',
            ]);
        }

        return DB::transaction(function () use ($workflow, $to, $actorId, $comment) {
            $from = $workflow->status;

            $data = ['status' => $to];

            if ($to === Workflow::STATUS_SUBMITTED && $workflow->started_at === null) {
                $data['started_at'] = now();
            }

            if (in_array($to, [Workflow::STATUS_COMPLETED, Workflow::STATUS_CANCELLED, Workflow::STATUS_REJECTED], true)) {
                $data['completed_at'] = now();
            }

            if ($to === Workflow::STATUS_UNDER_REVIEW) {
                $data['assigned_to'] = $actorId;
            }

            $workflow->update($data);

            $this->recordHistory($workflow, $actorId, $this->actionFor($to), $from, $to, $comment);
            $this->audit((int) $workflow->institute_id, $actorId, "workflow_{$this->actionFor($to)}", $workflow->id, ['status' => $from], ['status' => $to, 'comment' => $comment]);

            return $workflow->refresh()->load('steps', 'histories');
        });
    }

    /**
     * Approve the current step and advance. When the final step is approved the
     * workflow completes.
     */
    public function approveStep(Workflow $workflow, ?int $actorId, ?string $comment = null): Workflow
    {
        if (! in_array($workflow->status, [Workflow::STATUS_SUBMITTED, Workflow::STATUS_UNDER_REVIEW], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot approve a step while the workflow is {$workflow->status}.",
            ]);
        }

        return DB::transaction(function () use ($workflow, $actorId, $comment) {
            $step = $workflow->steps->firstWhere('step_order', $workflow->current_step);

            if ($step === null) {
                throw ValidationException::withMessages([
                    'step' => 'No active step found for this workflow.',
                ]);
            }

            $step->update([
                'status' => WorkflowStep::STATUS_APPROVED,
                'acted_by' => $actorId,
                'acted_at' => now(),
                'comment' => $comment,
            ]);

            $totalSteps = $workflow->steps->count();

            if ($workflow->current_step >= $totalSteps) {
                $from = $workflow->status;
                $workflow->update([
                    'status' => Workflow::STATUS_COMPLETED,
                    'completed_at' => now(),
                ]);
                $this->recordHistory($workflow, $actorId, 'step_approved', $from, Workflow::STATUS_COMPLETED, $comment);
                $this->recordHistory($workflow, $actorId, 'completed', $from, Workflow::STATUS_COMPLETED, $comment);
                $this->audit((int) $workflow->institute_id, $actorId, 'workflow_completed', $workflow->id, ['status' => $from], ['status' => Workflow::STATUS_COMPLETED]);
            } else {
                $from = $workflow->status;
                $workflow->update([
                    'current_step' => $workflow->current_step + 1,
                    'status' => Workflow::STATUS_UNDER_REVIEW,
                ]);
                $this->recordHistory($workflow, $actorId, 'step_approved', $from, Workflow::STATUS_UNDER_REVIEW, $comment);
                $this->audit((int) $workflow->institute_id, $actorId, 'workflow_step_approved', $workflow->id, ['step' => $step->step_order], ['next_step' => $workflow->current_step]);
            }

            return $workflow->refresh()->load('steps', 'histories');
        });
    }

    /**
     * Reject the current step — the whole workflow is rejected.
     */
    public function rejectStep(Workflow $workflow, ?int $actorId, string $comment): Workflow
    {
        if (! in_array($workflow->status, [Workflow::STATUS_SUBMITTED, Workflow::STATUS_UNDER_REVIEW], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot reject a step while the workflow is {$workflow->status}.",
            ]);
        }

        if (trim($comment) === '') {
            throw ValidationException::withMessages([
                'comment' => 'A comment is required when rejecting a workflow step.',
            ]);
        }

        return DB::transaction(function () use ($workflow, $actorId, $comment) {
            $step = $workflow->steps->firstWhere('step_order', $workflow->current_step);

            if ($step !== null) {
                $step->update([
                    'status' => WorkflowStep::STATUS_REJECTED,
                    'acted_by' => $actorId,
                    'acted_at' => now(),
                    'comment' => $comment,
                ]);
            }

            $from = $workflow->status;
            $workflow->update([
                'status' => Workflow::STATUS_REJECTED,
                'completed_at' => now(),
            ]);

            $this->recordHistory($workflow, $actorId, 'step_rejected', $from, Workflow::STATUS_REJECTED, $comment);
            $this->audit((int) $workflow->institute_id, $actorId, 'workflow_rejected', $workflow->id, ['status' => $from], ['status' => Workflow::STATUS_REJECTED, 'comment' => $comment]);

            return $workflow->refresh()->load('steps', 'histories');
        });
    }

    private function recordHistory(Workflow $workflow, ?int $actorId, string $action, ?string $from, ?string $to, ?string $comment): void
    {
        WorkflowHistory::create([
            'workflow_id' => $workflow->id,
            'actor_id' => $actorId,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'comment' => $comment,
            'created_at' => now(),
        ]);
    }

    private function actionFor(string $status): string
    {
        return match ($status) {
            Workflow::STATUS_SUBMITTED => 'submitted',
            Workflow::STATUS_UNDER_REVIEW => 'review_started',
            Workflow::STATUS_APPROVED => 'approved',
            Workflow::STATUS_REJECTED => 'rejected',
            Workflow::STATUS_RETURNED => 'returned',
            Workflow::STATUS_COMPLETED => 'completed',
            Workflow::STATUS_CANCELLED => 'cancelled',
            default => 'transitioned',
        };
    }

    private function audit(int $instituteId, ?int $actorId, string $action, ?int $recordId, ?array $oldValues, ?array $newValues): void
    {
        AuditLog::create([
            'institute_id' => $instituteId,
            'user_type' => 'institute_user',
            'user_id' => $actorId,
            'action' => $action,
            'module' => 'workflows',
            'record_id' => $recordId,
            'old_values' => $oldValues !== null ? json_encode($oldValues) : null,
            'new_values' => $newValues !== null ? json_encode($newValues) : null,
            'ip_address' => request()->ip(),
            'user_agent' => substr((string) request()->userAgent(), 0, 255),
            'created_at' => now(),
        ]);
    }
}
