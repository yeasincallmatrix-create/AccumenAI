<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $table = 'appointments';

    protected $fillable = [
        'institute_id',
        'patient_id',
        'doctor_id',
        'appointment_date',
        'appointment_time',
        'serial_number',
        'status',
        'fee_applied',
        'complaints',
        'notes',
    ];

    protected $casts = [
        'appointment_date' => 'date',
        'serial_number' => 'integer',
        'fee_applied' => 'decimal:2',
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

    /**
     * The Doctor profile (medical_doctors row) behind appointments.doctor_id
     * (which references users.id). A user can have profiles in several
     * institutes — callers must match institute_id (see isFollowUp()).
     */
    public function doctorProfile()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id', 'user_id');
    }

    /**
     * Whether this appointment was billed at the follow-up rate.
     */
    public function isFollowUp(): bool
    {
        if ($this->fee_applied === null) {
            return false;
        }

        $profile = $this->relationLoaded('doctorProfile')
            ? $this->doctorProfile
            : $this->doctorProfile()->first();

        if (! $profile || (int) $profile->institute_id !== (int) $this->institute_id) {
            return false;
        }

        return (float) $this->fee_applied < $profile->firstVisitFee();
    }

    /**
     * Days since the patient's previous completed visit with this doctor
     * (null when there is no earlier completed visit).
     */
    public function daysSinceLastVisit(): ?int
    {
        $last = Appointment::where('patient_id', $this->patient_id)
            ->where('doctor_id', $this->doctor_id)
            ->where('id', '!=', $this->id)
            ->where('status', 'completed')
            ->latest('appointment_date')
            ->first();

        return $last && $last->appointment_date
            ? (int) $last->appointment_date->diffInDays(now())
            : null;
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('appointment_date', today());
    }

    public function scopeByDoctor($query, $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }
}
