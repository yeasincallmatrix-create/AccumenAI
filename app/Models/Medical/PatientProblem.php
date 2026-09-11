<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 17 — Longitudinal patient problem (documentation only).
 *
 * A clinician-recorded persistent problem, deliberately SEPARATE from
 * encounter diagnoses: diagnoses are immutable visit history, problems are
 * an explicit longitudinal abstraction. Nothing auto-creates, converts,
 * merges or infers problems — every row is an explicit clinician act.
 *
 * - problem_type is an application documentation category, never a claim
 *   of authoritative classification.
 * - Terminology follows the Phase 15 registry: codes are stored only as
 *   clinician-supplied text and mapping_status resolves ONLY via
 *   EncounterDiagnosis::resolvesCode() (empty until an authority exists).
 * - Lifecycle is active→inactive→resolved (plus active→resolved and
 *   inactive→active) through explicit model transitions. There is no
 *   delete path; resolved rows persist with resolved_date.
 * - Source links (encounter / encounter diagnosis) are evidence/context
 *   and never mutate the source record.
 */
class PatientProblem extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_RESOLVED = 'resolved';

    public const TYPE_CHRONIC = 'chronic';

    public const TYPE_ACUTE = 'acute';

    public const TYPE_HISTORICAL = 'historical';

    public const TYPE_SYMPTOM = 'symptom';

    public const TYPE_CONDITION = 'condition';

    public const TYPE_OTHER = 'other';

    public const TYPES = [
        self::TYPE_CHRONIC,
        self::TYPE_ACUTE,
        self::TYPE_HISTORICAL,
        self::TYPE_SYMPTOM,
        self::TYPE_CONDITION,
        self::TYPE_OTHER,
    ];

    protected $table = 'medical_patient_problems';

    protected $fillable = [
        'institute_id',
        'patient_id',
        'encounter_id',
        'encounter_diagnosis_id',
        'recorded_by',
        'label',
        'problem_type',
        'source',
        'mapping_status',
        'code',
        'code_system',
        'onset_date',
        'resolved_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'onset_date' => 'date',
        'resolved_date' => 'date',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter()
    {
        return $this->belongsTo(Encounter::class);
    }

    public function sourceDiagnosis()
    {
        return $this->belongsTo(EncounterDiagnosis::class, 'encounter_diagnosis_id');
    }

    public function recorder()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function followUps()
    {
        return $this->hasMany(FollowUp::class, 'problem_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function isTerminal(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    /**
     * Guarded lifecycle transition (inactivate/reactivate/resolve).
     * Throws on illegal moves; resolved_date is set on resolve (explicit
     * clinician-supplied date wins, otherwise the action date — the act of
     * resolving IS the explicit clinical statement per spec §4).
     */
    public function transitionTo(string $status, ?string $resolvedDate = null): void
    {
        $allowed = [
            self::STATUS_ACTIVE => [self::STATUS_INACTIVE, self::STATUS_RESOLVED],
            self::STATUS_INACTIVE => [self::STATUS_ACTIVE, self::STATUS_RESOLVED],
            self::STATUS_RESOLVED => [],
        ];

        if (! in_array($status, $allowed[$this->status] ?? [], true)) {
            throw new \RuntimeException(
                "Problem cannot move from {$this->status} to {$status}."
            );
        }

        $attributes = ['status' => $status];
        if ($status === self::STATUS_RESOLVED) {
            $attributes['resolved_date'] = $resolvedDate ?? now()->format('Y-m-d');
        }

        $this->update($attributes);
    }
}
