<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DischargeSummary extends Model
{
    use SoftDeletes;

    protected $table = 'discharge_summaries';

    public const CONDITION_OPTIONS = [
        'recovered' => 'Recovered',
        'improved' => 'Improved',
        'stable' => 'Stable',
        'referred' => 'Referred',
        'against_advice' => 'Discharged Against Advice',
        'expired' => 'Expired',
    ];

    protected $fillable = [
        'institute_id', 'branch_id', 'summary_number',
        'admission_id', 'patient_id', 'prepared_by',
        'admission_date', 'discharge_date', 'length_of_stay_days',
        'admission_diagnosis', 'final_diagnosis', 'hospital_course',
        'procedures_done', 'investigations_summary', 'treatment_given',
        'discharge_medications', 'discharge_instructions',
        'diet_instructions', 'activity_restrictions', 'condition_on_discharge',
        'follow_up_date', 'follow_up_instructions', 'follow_up_department',
        'document_id',
    ];

    protected $casts = [
        'admission_date' => 'date',
        'discharge_date' => 'date',
        'follow_up_date' => 'date',
        'length_of_stay_days' => 'integer',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function preparedBy()
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function document()
    {
        return $this->belongsTo(MedicalDocument::class, 'document_id');
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('discharge_summaries.institute_id', $id);
    }

    public function scopeForPatient($q, int $patientId)
    {
        return $q->where('discharge_summaries.patient_id', $patientId);
    }

    public function scopeRecent($q, int $days = 30)
    {
        return $q->where('discharge_summaries.discharge_date', '>=', now()->subDays($days)->toDateString());
    }

    public function conditionColor(): string
    {
        return match ($this->condition_on_discharge) {
            'recovered' => 'success',
            'improved' => 'info',
            'stable' => 'primary',
            'referred' => 'warning',
            'against_advice' => 'danger',
            'expired' => 'dark',
            default => 'secondary',
        };
    }

    public function isFollowUpDue(): bool
    {
        return $this->follow_up_date !== null
            && $this->follow_up_date->isPastOrToday();
    }

    public static function computeLos(string $admissionDate, string $dischargeDate): int
    {
        return max(1, (int) now()->parse($admissionDate)->diffInDays(now()->parse($dischargeDate)) + 1);
    }
}
