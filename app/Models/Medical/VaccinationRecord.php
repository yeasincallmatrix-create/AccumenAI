<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Institute;
use App\Models\User;
use App\Models\Medical\Patient;

class VaccinationRecord extends Model
{
    use SoftDeletes;

    protected $table = 'vaccination_records';

    protected $fillable = [
        'institute_id', 'branch_id', 'record_number',
        'vaccination_schedule_id', 'patient_id', 'vaccine_master_id', 'dose_number',
        'administered_date', 'administered_at', 'administered_by',
        'site', 'route', 'dose_volume',
        'batch_number', 'batch_expiry', 'manufacturer', 'vaccine_vial_id',
        'consent_obtained', 'pre_vaccination_notes', 'post_vaccination_notes',
        'adverse_event', 'adverse_event_details', 'observation_end_at',
        'next_dose_due', 'fee', 'payment_status',
        'certificate_number', 'certificate_issued_at',
    ];

    protected $casts = [
        'administered_date' => 'date',
        'administered_at' => 'datetime',
        'batch_expiry' => 'date',
        'observation_end_at' => 'datetime',
        'next_dose_due' => 'date',
        'dose_number' => 'integer',
        'consent_obtained' => 'boolean',
        'fee' => 'decimal:2',
        'certificate_issued_at' => 'datetime',
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

    public function schedule(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VaccinationSchedule::class, 'vaccination_schedule_id');
    }

    public function administeredBy(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'administered_by');
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('institute_id', $id);
    }

    public function scopeForBranch($q, $id)
    {
        return $id ? $q->where('branch_id', $id) : $q;
    }

    public function scopeForPatient($q, int $patientId)
    {
        return $q->where('patient_id', $patientId);
    }

    public function hasAdverseEvent(): bool
    {
        return $this->adverse_event !== null && $this->adverse_event !== 'none';
    }

    public function statusColor(): string
    {
        return match ($this->adverse_event) {
            'none' => 'success',
            'mild' => 'warning',
            'moderate' => 'danger',
            'severe' => 'danger',
            default => 'secondary',
        };
    }

    public function adverseEventLabel(): string
    {
        return VaccineMaster::ADVERSE_EVENTS[$this->adverse_event] ?? ($this->adverse_event ?? 'None');
    }
}
