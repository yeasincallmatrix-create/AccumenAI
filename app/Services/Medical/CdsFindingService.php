<?php

namespace App\Services\Medical;

use App\Models\Medical\CdsFinding;
use App\Models\Medical\Prescription;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Phase 13 — persistence + resolution for CDS findings.
 *
 * Idempotency: one OPEN row per (prescription, rule version). Re-evaluation
 * refreshes open rows in place; acknowledged/overridden/resolved rows are
 * history and are never rewritten. Resolution (acknowledge/override/resolve)
 * always records actor + timestamp, requires a reason for overrides, and
 * writes a ClinicalAuditLog entry — there is no silent dismiss.
 */
final class CdsFindingService
{
    /**
     * Persist an evaluation against a saved prescription. Open findings for
     * the same (prescription, rule version) are refreshed, never duplicated.
     */
    public function recordEvaluation(Prescription $prescription, CdsEvaluation $evaluation): int
    {
        $written = 0;

        DB::transaction(function () use ($prescription, $evaluation, &$written) {
            foreach (array_merge($evaluation->blocking, $evaluation->warnings) as $finding) {
                $existing = CdsFinding::where('prescription_id', $prescription->id)
                    ->where('cds_rule_version_id', $finding['rule_version_id'])
                    ->where('status', CdsFinding::STATUS_OPEN)
                    ->first();

                $attributes = [
                    'institute_id' => $prescription->institute_id,
                    'patient_id' => $prescription->patient_id,
                    'severity' => $finding['severity'],
                    'message' => mb_substr($finding['message'], 0, 2000),
                    'explanation' => $finding['explanation'],
                    'trigger_data' => $finding['trigger_data'],
                    'evaluated_at' => $evaluation->evaluatedAt,
                ];

                if ($existing) {
                    $existing->update($attributes);
                } else {
                    CdsFinding::create($attributes + [
                        'prescription_id' => $prescription->id,
                        'cds_rule_version_id' => $finding['rule_version_id'],
                        'status' => CdsFinding::STATUS_OPEN,
                    ]);
                }
                $written++;
            }
        });

        return $written;
    }

    /**
     * Resolve a finding: acknowledge | override (reason required) | resolve.
     * Tenant + fence checks belong to the caller; actor attribution + audit
     * happen here, atomically with the status change.
     */
    public function resolve(
        CdsFinding $finding,
        string $action,
        ?string $reason,
        ?int $actorId,
        ?string $actorName
    ): CdsFinding {
        if (! in_array($action, ['acknowledge', 'override', 'resolve'], true)) {
            throw new \InvalidArgumentException("Unknown resolution action [{$action}].");
        }
        if ($action === 'override' && trim((string) $reason) === '') {
            throw new \InvalidArgumentException('Overriding a clinical finding requires a reason.');
        }
        if (! in_array($finding->status, [CdsFinding::STATUS_OPEN, CdsFinding::STATUS_ACKNOWLEDGED], true)) {
            throw new \RuntimeException('Only open or acknowledged findings can be resolved.');
        }

        return DB::transaction(function () use ($finding, $action, $reason, $actorId, $actorName) {
            $finding->update([
                'status' => match ($action) {
                    'acknowledge' => CdsFinding::STATUS_ACKNOWLEDGED,
                    'override' => CdsFinding::STATUS_OVERRIDDEN,
                    default => CdsFinding::STATUS_RESOLVED,
                },
                'resolved_by' => $actorId,
                'resolved_at' => now(),
                'resolution_reason' => $reason !== null ? mb_substr($reason, 0, 2000) : null,
            ]);

            \App\Models\Medical\ClinicalAuditLog::record($finding->fresh(), "finding_{$action}", [
                'reason' => $reason,
                'new' => ['status' => $finding->status],
            ]);

            Log::info('cds.finding.resolved', [
                'finding_id' => $finding->id,
                'action' => $action,
                'rule_version_id' => $finding->cds_rule_version_id,
                'actor_id' => $actorId,
            ]);

            return $finding->fresh();
        });
    }
}
