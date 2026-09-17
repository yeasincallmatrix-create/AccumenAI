<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class PatientTimelineEvent extends Model
{
    protected $table = 'patient_timeline_events';

    public const EVENT_TYPES = [
        'prescription' => 'Prescription',
        'appointment' => 'Appointment',
        'admission' => 'Admission',
        'discharge' => 'Discharge',
        'vitals' => 'Vitals',
        'lab_order' => 'Lab Order',
        'lab_result' => 'Lab Result',
        'radiology_order' => 'Radiology Order',
        'radiology_report' => 'Radiology Report',
        'emergency' => 'Emergency Visit',
        'blood_transfusion' => 'Blood Transfusion',
        'vaccination' => 'Vaccination',
        'dental_procedure' => 'Dental Procedure',
        'physiotherapy_session' => 'Physiotherapy Session',
        'document' => 'Document',
        'diagnosis' => 'Diagnosis',
        'allergy' => 'Allergy',
        'note' => 'Clinical Note',
    ];

    protected $fillable = [
        'institute_id', 'branch_id', 'patient_id',
        'event_type', 'title', 'description', 'event_at', 'event_date',
        'source_type', 'source_id', 'doctor_id', 'department_id',
        'location', 'metadata', 'severity', 'icon',
    ];

    protected $casts = [
        'event_at' => 'datetime',
        'event_date' => 'date',
        'metadata' => 'array',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function source()
    {
        return $this->morphTo(__FUNCTION__, 'source_type', 'source_id');
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('patient_timeline_events.institute_id', $id);
    }

    public function scopeForPatient($q, int $patientId)
    {
        return $q->where('patient_timeline_events.patient_id', $patientId);
    }

    public function scopeOfType($q, string|array $type)
    {
        return is_array($type)
            ? $q->whereIn('patient_timeline_events.event_type', $type)
            : $q->where('patient_timeline_events.event_type', $type);
    }

    public function scopeBetweenDates($q, $from, $to)
    {
        if ($from) {
            $q->where('patient_timeline_events.event_at', '>=', $from);
        }
        if ($to) {
            $q->where('patient_timeline_events.event_at', '<=', $to);
        }

        return $q;
    }

    public function scopeCritical($q)
    {
        return $q->whereIn('patient_timeline_events.severity', ['critical', 'danger']);
    }

    public function icon(): string
    {
        if ($this->icon) {
            return $this->icon;
        }

        return match ($this->event_type) {
            'prescription' => 'bi-file-medical',
            'appointment' => 'bi-calendar-check',
            'admission' => 'bi-hospital',
            'discharge' => 'bi-box-arrow-right',
            'vitals' => 'bi-heart-pulse',
            'lab_order', 'lab_result' => 'bi-eyedropper',
            'radiology_order', 'radiology_report' => 'bi-radioactive',
            'emergency' => 'bi-lightning',
            'blood_transfusion' => 'bi-droplet-fill',
            'vaccination' => 'bi-shield-plus',
            'dental_procedure' => 'bi-emoji-smile',
            'physiotherapy_session' => 'bi-activity',
            'document' => 'bi-file-earmark-text',
            'diagnosis' => 'bi-clipboard-pulse',
            'allergy' => 'bi-exclamation-triangle',
            'note' => 'bi-journal-text',
            default => 'bi-circle',
        };
    }

    public function severityColor(): string
    {
        return match ($this->severity) {
            'critical', 'danger' => 'danger',
            'warning' => 'warning',
            'success' => 'success',
            'info' => 'info',
            default => 'secondary',
        };
    }

    public function eventTypeLabel(): string
    {
        return self::EVENT_TYPES[$this->event_type] ?? $this->event_type;
    }
}
