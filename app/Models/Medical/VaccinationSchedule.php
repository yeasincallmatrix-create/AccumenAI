<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Institute;
use App\Models\Medical\Patient;

class VaccinationSchedule extends Model
{
    use SoftDeletes;

    protected $table = 'vaccination_schedules';

    protected $fillable = [
        'institute_id', 'branch_id', 'patient_id', 'vaccine_master_id',
        'dose_number', 'due_date', 'given_date', 'status',
        'age_in_days_at_due', 'notes', 'contraindication_reason',
    ];

    protected $casts = [
        'due_date' => 'date',
        'given_date' => 'date',
        'dose_number' => 'integer',
        'age_in_days_at_due' => 'integer',
    ];

    public const STATUSES = [
        'scheduled' => 'Scheduled',
        'given' => 'Given',
        'missed' => 'Missed',
        'skipped' => 'Skipped',
        'contraindicated' => 'Contraindicated',
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

    public function vaccineMaster(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VaccineMaster::class, 'vaccine_master_id');
    }

    public function records(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(VaccinationRecord::class, 'vaccination_schedule_id');
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('institute_id', $id);
    }

    public function scopeForBranch($q, $id)
    {
        return $id ? $q->where('branch_id', $id) : $q;
    }

    public function scopeScheduled($q)
    {
        return $q->where('status', 'scheduled');
    }

    public function scopeDueToday($q)
    {
        return $q->where('status', 'scheduled')
            ->whereDate('due_date', '<=', today());
    }

    public function scopeOverdue($q)
    {
        return $q->where('status', 'scheduled')
            ->whereDate('due_date', '<', today());
    }

    public function scopeForPatient($q, int $patientId)
    {
        return $q->where('patient_id', $patientId);
    }

    public function isDue(): bool
    {
        return $this->status === 'scheduled' && $this->due_date->lte(today());
    }

    public function isOverdue(): bool
    {
        return $this->status === 'scheduled' && $this->due_date->lt(today());
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'scheduled' => 'info',
            'given' => 'success',
            'missed' => 'danger',
            'skipped' => 'warning',
            'contraindicated' => 'secondary',
            default => 'secondary',
        };
    }
}
