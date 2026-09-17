<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmbulanceDriver extends Model
{
    use SoftDeletes;

    protected $table = 'ambulance_drivers';

    public const STATUSES = [
        'active' => 'Active',
        'on_leave' => 'On Leave',
        'suspended' => 'Suspended',
        'terminated' => 'Terminated',
    ];

    public const EMPLOYEE_TYPES = [
        'full_time' => 'Full Time',
        'part_time' => 'Part Time',
        'contract' => 'Contract',
        'volunteer' => 'Volunteer',
    ];

    public const LICENSE_TYPES = [
        'light' => 'Light Vehicle',
        'heavy' => 'Heavy Vehicle',
        'ambulance' => 'Ambulance / Emergency',
    ];

    public const ACTIVE_TRIP_STATUSES = [
        'dispatched',
        'en_route_to_pickup',
        'at_pickup',
        'en_route_to_dropoff',
    ];

    protected $fillable = [
        'institute_id', 'branch_id', 'user_id', 'driver_number',
        'name', 'phone', 'email', 'date_of_birth', 'gender', 'address',
        'license_number', 'license_type', 'license_expiry',
        'employee_type', 'joined_date', 'status',
        'medical_fitness_expiry', 'emergency_contact', 'notes',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
        'license_expiry' => 'date',
        'joined_date' => 'date',
        'medical_fitness_expiry' => 'date',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function trips()
    {
        return $this->hasMany(AmbulanceTrip::class, 'driver_id');
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('ambulance_drivers.institute_id', $id);
    }

    public function scopeActive($q)
    {
        return $q->where('ambulance_drivers.status', 'active');
    }

    public function scopeOnLeave($q)
    {
        return $q->where('ambulance_drivers.status', 'on_leave');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isLicenseExpiring(int $days = 30): bool
    {
        return $this->license_expiry !== null
            && $this->license_expiry->lte(today()->addDays($days));
    }

    public function isOnActiveTrip(): bool
    {
        return $this->trips()->whereIn('status', self::ACTIVE_TRIP_STATUSES)->exists();
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'on_leave' => 'warning',
            'suspended' => 'danger',
            'terminated' => 'dark',
            default => 'secondary',
        };
    }
}
