<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Doctor extends Model
{
    protected $table = 'medical_doctors';

    protected $fillable = [
        'institute_id', 'user_id', 'department_id', 'specialty_id', 'registration_number',
        'qualification', 'experience_years', 'consultation_fee',
        'first_visit_fee', 'follow_up_fee', 'follow_up_days',
        'collect_fee_before_visit',
        'chamber_address', 'room_no', 'phone', 'email', 'bio', 'is_active',
    ];

    protected $casts = [
        'consultation_fee' => 'decimal:2',
        'first_visit_fee' => 'decimal:2',
        'follow_up_fee' => 'decimal:2',
        'follow_up_days' => 'integer',
        'collect_fee_before_visit' => 'boolean',
        'experience_years' => 'integer',
        'is_active' => 'boolean',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function specialty()
    {
        return $this->belongsTo(Specialty::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function availabilities()
    {
        return $this->hasMany(DoctorAvailability::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function getFullNameAttribute()
    {
        return $this->user?->name ?? 'Unknown';
    }

    public function getSpecialtyNameAttribute(): string
    {
        return $this->specialty?->name ?? 'General';
    }

    public function getDepartmentNameAttribute(): string
    {
        return $this->department?->name
            ?? $this->specialty?->department?->name
            ?? 'N/A';
    }

    /**
     * Resolve the Doctor profile for a given system user (appointments link
     * to users.id, not medical_doctors.id).
     */
    public static function resolveForUser(int $userId, ?int $instituteId = null): ?self
    {
        $query = static::where('user_id', $userId);
        if ($instituteId !== null) {
            $query->where('institute_id', $instituteId);
        }

        return $query->first();
    }

    /**
     * Most recent completed visit of the given patient with this doctor.
     *
     * Phase 02: constrained to this profile's institute — history rows from
     * another institute must never influence fee calculation here.
     */
    public function lastCompletedVisitFor(Patient $patient): ?Appointment
    {
        return Appointment::where('institute_id', $this->institute_id)
            ->where('patient_id', $patient->id)
            ->where('doctor_id', $this->user_id)
            ->where('status', 'completed')
            ->latest('appointment_date')
            ->first();
    }

    /**
     * Days since the patient's last completed visit (null when never visited).
     */
    public function daysSinceLastVisitFor(Patient $patient): ?int
    {
        $last = $this->lastCompletedVisitFor($patient);

        return $last && $last->appointment_date
            ? (int) $last->appointment_date->diffInDays(now())
            : null;
    }

    /**
     * First-visit fee (falls back to consultation_fee, then 700).
     */
    public function firstVisitFee(): float
    {
        return (float) ($this->first_visit_fee ?? $this->consultation_fee ?? 700);
    }

    /**
     * Check if the patient qualifies for the follow-up rate.
     */
    public function hasFollowUpRateFor(Patient $patient): bool
    {
        $days = $this->daysSinceLastVisitFor($patient);

        if ($days === null) {
            return false;
        }

        return $days <= max(1, (int) ($this->follow_up_days ?? 30));
    }

    /**
     * Get the applicable fee for a given patient (follow-up vs first visit).
     */
    public function getApplicableFee(Patient $patient): float
    {
        if ($this->hasFollowUpRateFor($patient)) {
            return (float) ($this->follow_up_fee ?? 500);
        }

        return $this->firstVisitFee();
    }

    /**
     * Get available time slots for a specific date (Y-m-d).
     */
    public function getAvailableSlots(string $date): array
    {
        $dayOfWeek = strtolower(date('l', strtotime($date)));
        $availability = $this->availabilities()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_available', true)
            ->first();

        if (! $availability) {
            return [];
        }

        $slots = [];
        $start = strtotime((string) $availability->start_time);
        $end = strtotime((string) $availability->end_time);
        $duration = ((int) $availability->slot_duration) * 60;

        if ($duration <= 0) {
            return [];
        }

        while ($start < $end) {
            $slots[] = [
                'start' => date('H:i', $start),
                'end' => date('H:i', $start + $duration),
            ];
            $start += $duration;
        }

        return $slots;
    }
}
