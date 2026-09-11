<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 01 — Clinical record integrity.
 *
 * Generalized append-only audit for clinical amendments. Complements (does
 * NOT replace) prescription_audit_logs and queue_audit_logs, which keep
 * recording lifecycle events in their existing shape.
 *
 * Rows are written explicitly by medical controllers at mutation points and
 * are never updated or deleted by the application (no update/delete paths
 * exist; the model exposes no destroy route).
 *
 * Snapshot convention: updates store changed attributes only
 * (old_values/new_values diffs); deletes store the full row snapshot in
 * old_values so a soft-deleted or removed record stays attributable.
 */
class ClinicalAuditLog extends Model
{
    protected $fillable = [
        'institute_id',
        'branch_id',
        'patient_id',
        'user_id',
        'user_type',
        'actor_name',
        'auditable_type',
        'auditable_id',
        'action',
        'reason',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function auditable()
    {
        return $this->morphTo();
    }

    /**
     * Record an amendment. Tenant and patient resolve from the auditable
     * itself (same-institute membership is verified by controllers before
     * any mutation, so the row can never attribute across institutes).
     *
     * @param  array{old?: ?array, new?: ?array, reason?: ?string}  $options
     */
    public static function record(Model $auditable, string $action, array $options = []): self
    {
        [$instituteId, $patientId] = static::resolveScope($auditable);
        [$userId, $userType, $actorName] = static::resolveActor();

        return static::create([
            'institute_id' => $instituteId,
            'patient_id' => $patientId,
            // Phase 18: branch context when the auditable carries (or
            // derives) one; historical rows without branch stay NULL.
            'branch_id' => static::resolveBranch($auditable),
            'user_id' => $userId,
            'user_type' => $userType,
            'actor_name' => $actorName,
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'action' => $action,
            'reason' => isset($options['reason']) ? mb_substr((string) $options['reason'], 0, 255) : null,
            'old_values' => $options['old'] ?? null,
            'new_values' => $options['new'] ?? null,
            'ip_address' => static::requestIp(),
            'user_agent' => static::requestUserAgent(),
        ]);
    }

    /**
     * Snapshot the persistent (fillable) attributes of a clinical row.
     */
    public static function snapshot(Model $model): array
    {
        $snapshot = array_intersect_key($model->getAttributes(), array_flip($model->getFillable()));
        ksort($snapshot);

        return array_map(fn ($v) => $v instanceof \Stringable || is_object($v) ? (string) $v : $v, $snapshot);
    }

    /**
     * Diff two snapshots; returns [old-changed, new-changed] with only the
     * keys whose values actually differ.
     */
    public static function diff(array $old, array $new): array
    {
        $oldChanged = [];
        $newChanged = [];
        foreach (array_unique(array_merge(array_keys($old), array_keys($new))) as $key) {
            $o = $old[$key] ?? null;
            $n = $new[$key] ?? null;
            if ((string) $o !== (string) $n) {
                $oldChanged[$key] = $o;
                $newChanged[$key] = $n;
            }
        }

        return [$oldChanged, $newChanged];
    }

    /**
     * Resolve [institute_id, patient_id] from the auditable. Child rows
     * without their own institute_id resolve through their parent, so the
     * audit always carries the tenant even for vitals/notes/results.
     */
    protected static function resolveScope(Model $auditable): array
    {
        return match (true) {
            $auditable instanceof Patient => [$auditable->institute_id, $auditable->getKey()],
            $auditable instanceof Admission,
            $auditable instanceof LabOrder,
            $auditable instanceof Prescription,
            $auditable instanceof Encounter => [$auditable->institute_id, $auditable->patient_id],
            // Diagnoses carry no patient_id by design (§28); the patient
            // always resolves through the encounter.
            $auditable instanceof EncounterDiagnosis => [
                $auditable->institute_id,
                $auditable->encounter?->patient_id,
            ],
            $auditable instanceof VitalSign,
            $auditable instanceof NursingNote => static::resolveParentScope($auditable),
            $auditable instanceof LabResult => [
                $auditable->labOrder->institute_id,
                $auditable->labOrder->patient_id,
            ],
            default => [$auditable->institute_id ?? null, $auditable->patient_id ?? null],
        };
    }

    /**
     * Phase 18 — branch attribution for audit rows. Direct branch_id wins;
     * child rows without their own column derive through their parent
     * (diagnosis→encounter, vitals/notes→admission/appointment,
     * result→order). Anything else stays NULL (legacy-valid).
     */
    protected static function resolveBranch(Model $auditable): ?int
    {
        try {
            if ($auditable instanceof EncounterDiagnosis) {
                return $auditable->encounter?->branch_id !== null
                    ? (int) $auditable->encounter->branch_id : null;
            }
            if ($auditable instanceof VitalSign || $auditable instanceof NursingNote) {
                $parent = $auditable->admission ?? $auditable->appointment ?? null;
                if ($auditable instanceof VitalSign && ! $auditable->admission_id && $auditable->appointment_id) {
                    $parent = $auditable->appointment;
                }
                $branch = $parent?->branch_id ?? null;

                return $branch !== null ? (int) $branch : null;
            }
            if ($auditable instanceof LabResult) {
                $branch = $auditable->labOrder?->branch_id ?? null;

                return $branch !== null ? (int) $branch : null;
            }
            $branch = $auditable->branch_id ?? null;

            return $branch !== null ? (int) $branch : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Scope for admission-linked rows (vitals/notes): OPD vitals resolve
     * through their appointment instead — admission is null for those.
     */
    protected static function resolveParentScope(Model $auditable): array
    {
        if ($auditable instanceof VitalSign && ! $auditable->admission_id && $auditable->appointment_id) {
            $appointment = $auditable->appointment;

            return [
                $appointment?->institute_id,
                $appointment?->patient_id ?? $auditable->patient_id,
            ];
        }

        if ($auditable instanceof VitalSign && ! $auditable->admission_id && ! $auditable->appointment_id) {
            // Patient-direct vitals (prescription page flow): neither parent
            // exists, so resolve the tenant through the patient instead.
            $patient = $auditable->patient;

            return [
                $patient?->institute_id,
                $patient?->getKey() ?? $auditable->patient_id,
            ];
        }

        return [
            $auditable->admission->institute_id,
            $auditable->admission->patient_id,
        ];
    }

    /**
     * Actor resolution mirrors PrescriptionAuditLog::record: institute guard
     * first, then web, then console/system fallback.
     */
    protected static function resolveActor(): array
    {
        $staff = auth('institute_user')->user() ?? auth('web')->user() ?? auth()->user();

        if (! $staff) {
            return [null, 'system', null];
        }

        $name = $staff->name ?? trim(($staff->first_name ?? '').' '.($staff->last_name ?? '')) ?: null;
        $type = match (true) {
            $staff instanceof \App\Models\PlatformAdmin => 'platform_admin',
            $staff instanceof \App\Models\InstituteUser => 'institute_user',
            default => 'user',
        };

        return [$staff->getKey(), $type, $name];
    }

    protected static function requestIp(): ?string
    {
        try {
            return app()->runningInConsole() && ! app()->runningUnitTests() ? null : request()->ip();
        } catch (\Throwable) {
            return null;
        }
    }

    protected static function requestUserAgent(): ?string
    {
        try {
            if (app()->runningInConsole() && ! app()->runningUnitTests()) {
                return null;
            }

            return mb_substr((string) request()->userAgent(), 0, 1000) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }
}
