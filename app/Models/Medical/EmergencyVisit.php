<?php

namespace App\Models\Medical;

use App\Models\Branch;
use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmergencyVisit extends Model
{
    use SoftDeletes;

    protected $table = 'emergency_visits';

    protected $fillable = [
        'institute_id',
        'branch_id',
        'visit_number',
        'patient_id',
        'patient_name_temp',
        'patient_age',
        'patient_gender',
        'patient_phone',
        'triage_level',
        'triaged_at',
        'triaged_by',
        'arrival_mode',
        'arrival_reference',
        'arrived_at',
        'chief_complaint',
        'history_notes',
        'vitals_snapshot',
        'examination_findings',
        'provisional_diagnosis',
        'treatment_given',
        'attending_doctor_id',
        'attended_at',
        'status',
        'disposition',
        'admission_id',
        'disposition_at',
        'disposition_by',
        'disposition_notes',
        'triage_fee',
        'total_fee',
    ];

    protected $casts = [
        'arrived_at'       => 'datetime',
        'triaged_at'       => 'datetime',
        'attended_at'      => 'datetime',
        'disposition_at'   => 'datetime',
        'vitals_snapshot'  => 'array',
        'triage_fee'       => 'decimal:2',
        'total_fee'        => 'decimal:2',
        'patient_age'      => 'integer',
    ];

    // Statuses
    const STATUS_WAITING   = 'waiting';
    const STATUS_REGISTERED = 'registered';
    const STATUS_TRIAGED   = 'triaged';
    const STATUS_ATTENDED  = 'attended';
    const STATUS_DISCHARGED = 'discharged';
    const STATUS_ADMITTED  = 'admitted';
    const STATUS_TRANSFERRED = 'transferred';
    const STATUS_EXPIRED   = 'expired';
    const STATUS_LEFT      = 'left_without_treatment';

    // Triage levels (ESI 5-level)
    const TRIAGE_RED       = 'red';       // Level 1 — Resuscitation
    const TRIAGE_ORANGE    = 'orange';    // Level 2 — Emergent
    const TRIAGE_YELLOW    = 'yellow';    // Level 3 — Urgent
    const TRIAGE_GREEN     = 'green';     // Level 4 — Less Urgent
    const TRIAGE_WHITE     = 'white';     // Level 5 — Non-Urgent

    const TRIAGE_LEVELS = [
        self::TRIAGE_RED    => 'Level 1 — Resuscitation',
        self::TRIAGE_ORANGE => 'Level 2 — Emergent',
        self::TRIAGE_YELLOW => 'Level 3 — Urgent',
        self::TRIAGE_GREEN  => 'Level 4 — Less Urgent',
        self::TRIAGE_WHITE  => 'Level 5 — Non-Urgent',
    ];

    const STATUSES = [
        self::STATUS_WAITING     => 'Waiting',
        self::STATUS_REGISTERED  => 'Registered',
        self::STATUS_TRIAGED     => 'Triaged',
        self::STATUS_ATTENDED    => 'Attended',
        self::STATUS_DISCHARGED  => 'Discharged',
        self::STATUS_ADMITTED    => 'Admitted',
        self::STATUS_TRANSFERRED => 'Transferred',
        self::STATUS_EXPIRED     => 'Expired',
        self::STATUS_LEFT        => 'Left Without Treatment',
    ];

    const ARRIVAL_MODES = [
        'walk_in'      => 'Walk-in',
        'ambulance'    => 'Ambulance',
        'police'       => 'Police',
        'referral'     => 'Referral',
        'self'         => 'Self',
        'other'        => 'Other',
    ];

    // --- Relationships ---

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function attendingDoctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'attending_doctor_id');
    }

    public function triagedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triaged_by');
    }

    public function dispositionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disposition_by');
    }

    public function admission(): HasOne
    {
        return $this->hasOne(Admission::class);
    }

    // --- Scopes ---

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', [
            self::STATUS_DISCHARGED,
            self::STATUS_ADMITTED,
            self::STATUS_TRANSFERRED,
            self::STATUS_EXPIRED,
            self::STATUS_LEFT,
        ]);
    }

    public function scopeTriaged($query)
    {
        return $query->where('triage_level', '!=', null);
    }

    public function scopeByTriage($query, string $level)
    {
        return $query->where('triage_level', $level);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeForToday($query)
    {
        return $query->whereDate('arrived_at', today());
    }

    // --- Helpers ---

    public function isWaiting(): bool
    {
        return in_array($this->status, [self::STATUS_WAITING, self::STATUS_REGISTERED]);
    }

    public function isActive(): bool
    {
        return ! in_array($this->status, [
            self::STATUS_DISCHARGED,
            self::STATUS_ADMITTED,
            self::STATUS_TRANSFERRED,
            self::STATUS_EXPIRED,
            self::STATUS_LEFT,
        ]);
    }

    public function isRedTriage(): bool
    {
        return $this->triage_level === self::TRIAGE_RED;
    }

    public function triageLabel(): string
    {
        return self::TRIAGE_LEVELS[$this->triage_level] ?? 'Not Triaged';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function patientDisplayName(): string
    {
        if ($this->patient) {
            return $this->patient->name;
        }
        return $this->patient_name_temp ?? 'Unknown Patient';
    }
}
