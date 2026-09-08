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
        'chamber_address', 'room_no', 'phone', 'email', 'bio', 'is_active',
    ];

    protected $casts = [
        'consultation_fee' => 'decimal:2',
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
