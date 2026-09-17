<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Institute;
use App\Models\User;
use App\Models\Medical\Patient;

class DentalTreatmentPlan extends Model
{
    use SoftDeletes;

    protected $table = 'dental_treatment_plans';

    protected $fillable = [
        'institute_id', 'branch_id', 'plan_number',
        'patient_id', 'dentist_id',
        'chief_complaint', 'diagnosis', 'treatment_summary',
        'planned_steps', 'total_steps', 'completed_steps',
        'start_date', 'expected_end_date', 'total_estimated_fee',
        'status', 'discontinue_reason', 'notes',
    ];

    protected $casts = [
        'planned_steps' => 'array',
        'total_steps' => 'integer',
        'completed_steps' => 'integer',
        'start_date' => 'date',
        'expected_end_date' => 'date',
        'total_estimated_fee' => 'decimal:2',
    ];

    public const STATUSES = [
        'active' => 'Active',
        'completed' => 'Completed',
        'discontinued' => 'Discontinued',
        'on_hold' => 'On Hold',
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

    public function dentist(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'dentist_id');
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

    public function scopeForPatient($q, int $patientId)
    {
        return $q->where('patient_id', $patientId);
    }

    public function progressPercent(): int
    {
        if ($this->total_steps <= 0) {
            return 0;
        }

        return min(100, (int) round(($this->completed_steps / $this->total_steps) * 100));
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
}
