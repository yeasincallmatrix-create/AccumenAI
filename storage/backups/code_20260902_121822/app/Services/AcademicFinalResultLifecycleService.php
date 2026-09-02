<?php

namespace App\Services;

use App\Models\AcademicAssessment;
use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultPolicy;
use App\Models\AcademicFinalResultRow;
use App\Models\AcademicFinalResultStudent;
use App\Models\AcademicResultAggregationScheme;
use App\Models\Institute;
use App\Models\StudentAcademicPlacement;
use App\Models\Workflow;
use App\Services\Notification\NotificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Final-result lifecycle (Step 10): policy → review → approve → lock → publish.
 *
 * The Step-9 derivation (AcademicFinalResultService) stays purely derived and
 * read-only. This service owns the OFFICIAL copy: it turns a policy-affine
 * preview into a lifecycle record and, on LOCK, materializes an immutable
 * snapshot (per student GPA + per placement/subject rows) so the published
 * numbers survive later edits to marks, schemes or grade scales.
 *
 * Rules (backend only, never Blade/JS):
 *   - one policy per aggregation scheme (auto-created on first access);
 *   - at most one in-flight (non-published) result per policy;
 *   - review → approved → locked → published; approved is skipped only when
 *     policy.require_approval is disabled (lock allowed straight from review);
 *   - LOCK requires the aggregation scheme's weights to total 100% and the
 *     derived preview to be computed (the derivation refuses otherwise);
 *   - assessments that participate in a locked/published scheme can no longer
 *     accept marks edits (assertAssessmentEditable — used by marks entry).
 *
 * Institute/branch identity is NEVER taken from request input here: callers
 * pass the resolved Institute and the acting branch is inherited from the
 * policy/scheme rows (branch_id NULL = whole-institute).
 */
class AcademicFinalResultLifecycleService
{
    public function __construct(
        private readonly AcademicFinalResultService $finalResults,
        private readonly AcademicFinalResultPreflightService $preflight,
        private readonly AcademicAssessmentAuditService $audit,
        private readonly NotificationService $notifications,
        private readonly AcademicCumulativeService $cumulative,
        private readonly WorkflowService $workflows,
    ) {}

    // ------------------------------------------------------------- Policy

    /**
     * Find (or lazily create) the policy for a scheme. The policy defaults keep
     * every long-standing behavior: absent re-normalization ON, ladder-based
     * grade scale (no override), explicit approval required.
     */
    public function policyForScheme(Institute $institute, AcademicResultAggregationScheme $scheme): AcademicFinalResultPolicy
    {
        abort_if((int) $scheme->institute_id !== (int) $institute->id, 404, 'Scheme does not belong to this institute.');

        $policy = AcademicFinalResultPolicy::query()->where('scheme_id', $scheme->id)->first();
        if ($policy !== null) {
            return $policy;
        }

        return DB::transaction(function () use ($institute, $scheme) {
            return AcademicFinalResultPolicy::create([
                'institute_id' => $institute->id,
                'branch_id' => $scheme->branch_id,
                'scheme_id' => $scheme->id,
                'name' => $scheme->name.' — Final Result',
                'absent_renormalization' => true,
                'grade_scale_id' => null,
                'require_approval' => true,
                'status' => AcademicFinalResultPolicy::STATUS_ACTIVE,
            ]);
        });
    }

    /**
     * The in-flight (review / approved / locked) result for a policy, if any.
     */
    public function activeResult(AcademicFinalResultPolicy $policy): ?AcademicFinalResult
    {
        return AcademicFinalResult::query()
            ->where('policy_id', $policy->id)
            ->whereIn('status', AcademicFinalResult::ACTIVE_STATUSES)
            ->orderByDesc('id')
            ->first();
    }

    // ------------------------------------------------------------- Lifecycle

    /**
     * Start a new publish cycle (status: review).
     *
     * Generation is gated on the Step-29 pre-flight report: a scheme with any
     * BLOCKING pre-flight check (missing policy, missing required assessments
     * or subjects, invalid weights, missing grade scale, no eligible students)
     * can never start a cycle. The gate reuses AcademicFinalResultPreflightService
     * wholesale — no readiness/pre-flight rule is duplicated here.
     */
    public function createResult(Institute $institute, AcademicFinalResultPolicy $policy, string $name, ?int $actorId = null): AcademicFinalResult
    {
        abort_if((int) $policy->institute_id !== (int) $institute->id, 404, 'Policy does not belong to this institute.');

        $report = $this->preflight->preflight($policy->scheme);
        abort_if(! $report['verdict']['allowed'], 422, $this->generationBlockMessage($report['verdict']['blocking']));

        return DB::transaction(function () use ($institute, $policy, $name, $actorId) {
            $active = AcademicFinalResult::query()
                ->where('policy_id', $policy->id)
                ->whereIn('status', AcademicFinalResult::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->exists();

            abort_if($active, 422, 'This policy already has an in-flight final result. Publish (or abandon) it before starting another cycle.');

            $result = AcademicFinalResult::create([
                'institute_id' => $institute->id,
                'branch_id' => $policy->branch_id,
                'policy_id' => $policy->id,
                'scheme_id' => $policy->scheme_id,
                'name' => $name,
                'status' => AcademicFinalResult::STATUS_REVIEW,
            ]);

            // P2-2 — Wire workflow into lifecycle: if approval required, create multi-step workflow and link it.
            if ((bool) $policy->require_approval) {
                try {
                    $workflow = $this->workflows->create(
                        $institute->id,
                        'final_result_review',
                        'Final Result Review: '.$name,
                        null,
                        AcademicFinalResult::class,
                        $result->id,
                        $policy->branch_id,
                        $actorId,
                        'Final result review for '.$name
                    );
                    // Move from draft → submitted so HoD can act immediately.
                    try {
                        $this->workflows->transition($workflow, Workflow::STATUS_SUBMITTED, $actorId, 'Auto-submitted for final result review');
                        $workflow->refresh();
                    } catch (\Throwable $e) {}
                    if (\Illuminate\Support\Facades\Schema::hasColumn('academic_final_results', 'workflow_id')) {
                        $result->update(['workflow_id' => $workflow->id]);
                    }
                } catch (\Throwable $e) {
                    // Workflow creation must not block result creation; log silently.
                }
            }

            $this->audit->record(
                $institute->id,
                $actorId,
                'final_result.created',
                $result->id,
                null,
                ['policy_id' => $policy->id, 'scheme_id' => $policy->scheme_id, 'name' => $name]
            );

            return $result->refresh();
        });
    }

    /**
     * Backend-computed preview under the policy's absent re-normalization
     * setting (what reviewers see before approval / locking).
     *
     * @return array<string, mixed>
     */
    public function preview(AcademicFinalResult $result): array
    {
        return $this->finalResults->preview(
            $result->scheme,
            $result->policy->absent_renormalization,
            $result->policy->gradeScale
        );
    }

    public function approve(AcademicFinalResult $result, ?int $actorId = null): AcademicFinalResult
    {
        abort_if(! $result->canApprove(), 422, 'A result can only be approved while it is in review.');

        // P2-2 — If workflow is wired, approve via workflow steps; only mark approved when workflow completes.
        $workflowId = $result->getAttribute('workflow_id');
        if (! empty($workflowId)) {
            $workflow = Workflow::find($workflowId);
            if ($workflow !== null && ! $workflow->isTerminal()) {
                $this->workflows->approveStep($workflow, $actorId, 'Final result step approved');
                $workflow->refresh();
                if ($workflow->status !== Workflow::STATUS_COMPLETED) {
                    // Still in multi-step review — do not yet mark result as approved.
                    return $result->refresh();
                }
                // Workflow completed → fall through to mark result approved.
            }
        }

        $result->update([
            'status' => AcademicFinalResult::STATUS_APPROVED,
            'approved_by' => $actorId,
            'approved_at' => now(),
        ]);

        $this->audit->record(
            $result->institute_id,
            $actorId,
            'final_result.approved',
            $result->id,
            null,
            ['status' => AcademicFinalResult::STATUS_APPROVED]
        );

        return $result->refresh();
    }

    public function sendBackToReview(AcademicFinalResult $result, ?int $actorId = null): AcademicFinalResult
    {
        abort_if(! $result->canSendBackToReview(), 422, 'Only an approved result can be sent back to review.');

        // P2-2 — If workflow exists, reject via workflow engine (requires comment).
        $workflowId = $result->getAttribute('workflow_id');
        if (! empty($workflowId)) {
            $workflow = Workflow::find($workflowId);
            if ($workflow !== null && in_array($workflow->status, [Workflow::STATUS_SUBMITTED, Workflow::STATUS_UNDER_REVIEW], true)) {
                try {
                    $this->workflows->rejectStep($workflow, $actorId, 'Final result sent back to review');
                } catch (\Throwable $e) {
                    // If reject requires comment or fails, continue to direct status change.
                }
            }
        }

        $result->update([
            'status' => AcademicFinalResult::STATUS_REVIEW,
            'approved_by' => null,
            'approved_at' => null,
        ]);

        $this->audit->record(
            $result->institute_id,
            $actorId,
            'final_result.reviewed',
            $result->id,
            null,
            ['status' => AcademicFinalResult::STATUS_REVIEW]
        );

        return $result->refresh();
    }

    /**
     * Freeze the cycle: materialize the snapshot rows from the current
     * backend-computed preview and mark the result locked. After this point
     * the snapshot is the authoritative reference and participating
     * assessments refuse further marks edits.
     */
    public function lock(AcademicFinalResult $result, ?int $actorId = null): AcademicFinalResult
    {
        abort_if(! $result->canLock(), 422, 'The result must be approved before it can be locked.');

        DB::transaction(function () use ($result, $actorId) {
            $this->snapshot($result);
            $result->update([
                'status' => AcademicFinalResult::STATUS_LOCKED,
                'locked_by' => $actorId,
                'locked_at' => now(),
                'computed_at' => now(),
            ]);

            $this->audit->record(
                $result->institute_id,
                $actorId,
                'final_result.locked',
                $result->id,
                null,
                ['status' => AcademicFinalResult::STATUS_LOCKED]
            );
        });

        return $result->refresh();
    }

    public function publish(AcademicFinalResult $result, ?int $actorId = null): AcademicFinalResult
    {
        abort_if(! $result->canPublish(), 422, 'Only a locked result can be published.');

        $result->update([
            'status' => AcademicFinalResult::STATUS_PUBLISHED,
            'published_by' => $actorId,
            'published_at' => now(),
        ]);

        $this->audit->record(
            $result->institute_id,
            $actorId,
            'final_result.published',
            $result->id,
            null,
            ['status' => AcademicFinalResult::STATUS_PUBLISHED]
        );

        $this->notifyResultsPublished($result, $actorId);

        $this->recomputeCumulativeGpa($result);

        return $result->refresh();
    }

    /**
     * Notify every student whose snapshot appears in the published result.
     * Runs per student (each carries its own result_status / gpa), inside the
     * safe NotificationService pipeline — it can never break the publish.
     */
    private function notifyResultsPublished(AcademicFinalResult $result, ?int $actorId): void
    {
        foreach ($result->students as $snapshot) {
            $student = $snapshot->placement?->student;
            if ($student === null) {
                continue;
            }

            $this->notifications->send('education.result_published', $student, [
                'student_name' => $student->full_name ?: $student->first_name,
                'reg_no' => $student->reg_no,
                'course_name' => $result->scheme?->name,
                'result_status' => (int) $snapshot->failed_count > 0 ? 'Failed' : 'Passed',
                'gpa' => $snapshot->gpa !== null ? 'GPA: '.number_format((float) $snapshot->gpa, 2) : '',
            ], [
                'actor_type' => 'institute_user',
                'actor_id' => $actorId,
                'link' => route('students.show', $student->id),
            ]);
        }
    }

    /**
     * Recompute CGPA for every student in the published result.
     * Wrapped in a try-catch: CGPA is a derived cache; if it fails the
     * publish is NOT rolled back.
     */
    private function recomputeCumulativeGpa(AcademicFinalResult $result): void
    {
        try {
            foreach ($result->students as $snapshot) {
                $placement = $snapshot->placement;
                if ($placement === null) {
                    continue;
                }

                $this->cumulative->recomputeAfterPublish($placement);
            }
        } catch (\Throwable) {
            // CGPA is a derived cache — never block the publish on failure.
        }
    }

    // ------------------------------------------------------------- Lock guard

    /**
     * Abort (422) when the assessment participates in a scheme whose result is
     * LOCKED or PUBLISHED — i.e. its marks are frozen and can no longer be
     * edited. Assembles the offending result names for the message.
     */
    public function assertAssessmentEditable(Institute $institute, AcademicAssessment $assessment): void
    {
        $names = AcademicFinalResult::query()
            ->where('institute_id', $institute->id)
            ->whereIn('status', [AcademicFinalResult::STATUS_LOCKED, AcademicFinalResult::STATUS_PUBLISHED])
            ->whereHas('scheme.items', function (Builder $query) use ($assessment) {
                $query->where('academic_assessment_id', $assessment->id);
            })
            ->pluck('name')
            ->unique()
            ->all();

        abort_if($names !== [], 422, 'This assessment belongs to a locked final result ("'.implode('", "', $names).'") and its marks can no longer be edited.');
    }

    // ------------------------------------------------------------- Internals

    /**
     * Human-readable 422 message from the pre-flight blocking checks.
     *
     * @param  array<int, string>  $blocking
     */
    private function generationBlockMessage(array $blocking): string
    {
        $prefix = 'Final-result generation is blocked by the pre-flight gate';

        if (count($blocking) <= 1) {
            return $prefix.': '.($blocking[0] ?? 'the scheme is not ready for generation.');
        }

        $shown = array_slice($blocking, 0, 3);
        $message = $prefix.': '.implode('; ', $shown);
        if (count($blocking) > 3) {
            $message .= ' (+'.(count($blocking) - 3).' more)';
        }

        return $message;
    }

    /**
     * Persist the derived preview as immutable snapshot rows. Runs inside the
     * lock transaction. Each student and each (student × subject) yields one
     * row keyed by the placement id (students can hold multiple academic
     * placements across years).
     */
    private function snapshot(AcademicFinalResult $finalResult): void
    {
        $preview = $this->preview($finalResult);
        $scheme = $finalResult->scheme;

        abort_if(! $scheme->weightIsValid(), 422, 'The aggregation scheme weight must total 100% before the final result can be locked.');

        foreach ($preview['rows'] as $row) {
            $placementId = (int) $row['placement']->id;
            $gpa = $row['gpa'] ?? [];

            $passed = 0;
            $failed = 0;
            foreach ($row['subjects'] as $entry) {
                if (($entry['result']['subject_status'] ?? null) === AcademicFinalResultService::SUBJECT_STATUS_PASS) {
                    $passed++;
                } elseif (($entry['result']['subject_status'] ?? null) === AcademicFinalResultService::SUBJECT_STATUS_FAIL) {
                    $failed++;
                }
            }

            AcademicFinalResultStudent::updateOrCreate(
                ['result_id' => $finalResult->id, 'placement_id' => $placementId],
                [
                    'gpa' => $gpa['value'] ?? null,
                    'gpa_status' => $gpa['status'] ?? AcademicFinalResultStudent::GPA_UNAVAILABLE,
                    'gpa_mode' => $gpa['mode'] ?? null,
                    'gpa_reason' => $gpa['reason'] ?? null,
                    'passed_count' => $passed,
                    'failed_count' => $failed,
                ]
            );

            foreach ($row['subjects'] as $entry) {
                $subject = $entry['subject'] ?? null;
                if ($subject === null) {
                    continue;
                }

                $subjectResult = $entry['result'];
                $gpaSlice = $subjectResult['gpa'] ?? [];

                AcademicFinalResultRow::updateOrCreate(
                    [
                        'result_id' => $finalResult->id,
                        'placement_id' => $placementId,
                        'subject_id' => (int) $subject->id,
                    ],
                    [
                        'status' => $subjectResult['status'],
                        'aggregate' => $subjectResult['aggregate'],
                        'grade' => $subjectResult['grade'],
                        'grade_point' => $subjectResult['grade_point'],
                        'subject_status' => $subjectResult['subject_status'],
                        'gpa_included' => (bool) ($gpaSlice['included'] ?? false),
                        'credits' => $gpaSlice['credits'] ?? null,
                        'optional' => (bool) ($gpaSlice['optional'] ?? false),
                        'incomplete_reason' => $subjectResult['incomplete_reason'],
                    ]
                );
            }
        }
    }
}
