<?php

namespace App\Models\Medical;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VitalSign extends Model
{
    use SoftDeletes;

    protected $table = 'vital_signs';

    protected $fillable = [
        'admission_id',
        'appointment_id',
        'patient_id',
        'doctor_id',
        'temperature',
        'blood_pressure_systolic',
        'blood_pressure_diastolic',
        'pulse',
        'heart_rate',
        'respiratory_rate',
        'spo2',
        'pain_score',
        'blood_sugar',
        'weight',
        'height',
        'recorded_by',
        'recorded_at',
        'notes',
    ];

    protected $casts = [
        'temperature' => 'decimal:1',
        'blood_pressure_systolic' => 'integer',
        'blood_pressure_diastolic' => 'integer',
        'pulse' => 'integer',
        'heart_rate' => 'integer',
        'respiratory_rate' => 'integer',
        'spo2' => 'integer',
        'pain_score' => 'integer',
        'blood_sugar' => 'decimal:1',
        'weight' => 'decimal:1',
        'height' => 'decimal:1',
        'recorded_at' => 'datetime',
    ];

    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        // doctor_id stores the users.id (same convention as
        // appointments.doctor_id); the profile is keyed by user_id.
        return $this->belongsTo(Doctor::class, 'doctor_id', 'user_id');
    }

    /** OPD vitals: recorded against an appointment. */
    public function scopeOpd($query)
    {
        return $query->whereNotNull('appointment_id');
    }

    /** IPD vitals: recorded against an admission. */
    public function scopeIpd($query)
    {
        return $query->whereNotNull('admission_id');
    }

    public function recordedBy()
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function getBloodPressureAttribute()
    {
        if ($this->blood_pressure_systolic && $this->blood_pressure_diastolic) {
            return $this->blood_pressure_systolic.'/'.$this->blood_pressure_diastolic;
        }

        return null;
    }

    public function getBmiAttribute()
    {
        if ($this->weight && $this->height) {
            $heightInMeters = $this->height / 100;

            return round($this->weight / ($heightInMeters * $heightInMeters), 1);
        }

        return null;
    }
}
