<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Institute;
use App\Models\User;
use App\Models\Medical\Patient;

class PhysiotherapyPlan extends Model
{
    use SoftDeletes;

    protected $table = 'physiotherapy_plans';

    protected $fillable = [
        'institute_id', 'branch_id', 'plan_number',
        'patient_id', 'therapist_id', 'referring_doctor_id', 'appointment_id',
        'chief_complaint', 'assessment', 'diagnosis', 'treatment_goals', 'pain_score_initial',
        'modality', 'sessions_planned', 'sessions_completed', 'frequency',
        'start_date', 'expected_end_date',
        'status', 'discontinue_reason',
        'fee_per_session', 'total_fee', 'payment_status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'expected_end_date' => 'date',
        'pain_score_initial' => 'integer',
        'sessions_planned' => 'integer',
        'sessions_completed' => 'integer',
        'fee_per_session' => 'decimal:2',
        'total_fee' => 'decimal:2',
    ];

    public const STATUSES = [
        'active' => 'Active',
        'completed' => 'Completed',
        'discontinued' => 'Discontinued',
        'on_hold' => 'On Hold',
    ];

    public const FREQUENCIES = [
        'daily' => 'Daily',
        'alternate_day' => 'Alternate Day',
        'weekly' => 'Weekly',
        'biweekly' => 'Bi-Weekly',
        'custom' => 'Custom',
    ];

    public const MODALITIES = [
        'exercise' => 'Exercise Therapy',
        'electrotherapy' => 'Electrotherapy',
        'manual' => 'Manual Therapy',
        'hydrotherapy' => 'Hydrotherapy',
        'combined' => 'Combined',
        'other' => 'Other',
    ];

    public function institute(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function patient(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function therapist(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'therapist_id');
    }

    public function referringDoctor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'referring_doctor_id');
    }

    public function sessions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PhysiotherapySession::class, 'physiotherapy_plan_id');
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('institute_id', $id);
    }

    public function scopeForBranch($q, $id)
    {
        return $id ? $q->where('branch_id', $id) : $q;
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeCompleted($q)
    {
        return $q->where('status', 'completed');
    }

    public function scopeForPatient($q, int $patientId)
    {
        return $q->where('patient_id', $patientId);
    }

    public function progressPercent(): float
    {
        if ($this->sessions_planned <= 0) {
            return 0;
        }

        return round(($this->sessions_completed / $this->sessions_planned) * 100, 1);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'active' => 'primary',
            'completed' => 'success',
            'discontinued' => 'danger',
            'on_hold' => 'warning',
            default => 'secondary',
        };
    }

    public function frequencyLabel(): string
    {
        return self::FREQUENCIES[$this->frequency] ?? $this->frequency;
    }

    public function modalityLabel(): string
    {
        return self::MODALITIES[$this->modality] ?? $this->modality;
    }
}
