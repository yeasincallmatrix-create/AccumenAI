<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Phase 14 — first-class clinical encounter: one patient visit's
 * documentation envelope (notes, assessment, linked orders) from check-in
 * (or walk-in) through completion. Additive: appointments, prescriptions,
 * lab orders, admissions and invoices keep working exactly as before with
 * NULL encounter links; legacy rows are never backfilled.
 *
 * Lifecycle: open → in_progress → completed, with controlled cancellation.
 * Completed rows are immutable except through the reason-gated amend path
 * (controller + audit); there is deliberately no delete route.
 */
class Encounter extends Model
{
    protected $table = 'medical_encounters';

    public const TYPE_OPD = 'OPD';

    public const TYPE_EMERGENCY = 'EMERGENCY';

    public const TYPE_IPD = 'IPD';

    public const TYPE_FOLLOW_UP = 'FOLLOW_UP';

    public const TYPE_WALK_IN = 'WALK_IN';

    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'institute_id',
        'branch_id',
        'patient_id',
        'appointment_id',
        'doctor_id',
        'department_id',
        'specialty_id',
        'admission_id',
        'encounter_number',
        'encounter_type',
        'status',
        'started_at',
        'completed_at',
        'chief_complaint',
        'history_of_present_illness',
        'examination_notes',
        'assessment_notes',
        'plan_notes',
        'follow_up_notes',
        'diagnosis_text',
        'diagnosis_code',
        'created_by',
        'completed_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    use SoftDeletes;

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function specialty()
    {
        return $this->belongsTo(Specialty::class);
    }

    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    public function prescriptions()
    {
        return $this->hasMany(Prescription::class, 'encounter_id');
    }

    public function labOrders()
    {
        return $this->hasMany(LabOrder::class, 'encounter_id');
    }

    public function problems()
    {
        return $this->hasMany(PatientProblem::class, 'encounter_id');
    }

    public function followUps()
    {
        return $this->hasMany(FollowUp::class, 'encounter_id');
    }

    public function diagnoses()
    {
        return $this->hasMany(EncounterDiagnosis::class, 'encounter_id');
    }

    public function activeDiagnoses()
    {
        return $this->hasMany(EncounterDiagnosis::class, 'encounter_id')
            ->where('status', EncounterDiagnosis::STATUS_ACTIVE);
    }

    public function scopeOpen($query)
    {
        return $query->whereIn('status', [self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_IN_PROGRESS], true);
    }

    /**
     * Guarded lifecycle transition. Throws on illegal moves so callers
     * cannot silently revert completed work; audit happens in controllers.
     */
    public function transitionTo(string $status, ?int $actorId = null): void
    {
        $allowed = [
            self::STATUS_OPEN => [self::STATUS_IN_PROGRESS, self::STATUS_CANCELLED],
            self::STATUS_IN_PROGRESS => [self::STATUS_COMPLETED, self::STATUS_CANCELLED],
            self::STATUS_COMPLETED => [],
            self::STATUS_CANCELLED => [],
        ];

        if (! in_array($status, $allowed[$this->status] ?? [], true)) {
            throw new \RuntimeException(
                "Encounter cannot move from {$this->status} to {$status}."
            );
        }

        $attributes = ['status' => $status];
        if ($status === self::STATUS_IN_PROGRESS && ! $this->started_at) {
            $attributes['started_at'] = now();
        }
        if ($status === self::STATUS_COMPLETED) {
            $attributes['completed_at'] = now();
            $attributes['completed_by'] = $actorId;
        }

        $this->update($attributes);
    }
}
