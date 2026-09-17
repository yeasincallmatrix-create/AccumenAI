<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ambulance extends Model
{
    use SoftDeletes;

    protected $table = 'ambulances';

    public const TYPES = [
        'basic' => 'Basic Life Support',
        'advanced_life_support' => 'Advanced Life Support',
        'mobile_icu' => 'Mobile ICU',
        'neonatal' => 'Neonatal',
        'mortuary' => 'Mortuary',
    ];

    public const STATUSES = [
        'available' => 'Available',
        'dispatched' => 'Dispatched',
        'on_trip' => 'On Trip',
        'maintenance' => 'Maintenance',
        'out_of_service' => 'Out of Service',
    ];

    public const EQUIPMENT_ITEMS = [
        'oxygen_cylinder',
        'stretcher',
        'defibrillator',
        'ventilator',
        'suction_machine',
        'first_aid_kit',
        'ecg_monitor',
        'infusion_pump',
        'spine_board',
        'fire_extinguisher',
    ];

    protected $fillable = [
        'institute_id', 'branch_id', 'vehicle_number', 'registration_number',
        'make', 'model', 'year', 'type', 'fuel_type',
        'capacity_patients', 'capacity_attendants', 'equipment', 'status',
        'last_service_date', 'next_service_date', 'insurance_expiry',
        'fitness_expiry', 'odometer_km', 'notes', 'is_active',
    ];

    protected $casts = [
        'year' => 'integer',
        'capacity_patients' => 'integer',
        'capacity_attendants' => 'integer',
        'equipment' => 'array',
        'last_service_date' => 'date',
        'next_service_date' => 'date',
        'insurance_expiry' => 'date',
        'fitness_expiry' => 'date',
        'odometer_km' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function trips()
    {
        return $this->hasMany(AmbulanceTrip::class, 'ambulance_id');
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('ambulances.institute_id', $id);
    }

    public function scopeActive($q)
    {
        return $q->where('ambulances.is_active', true);
    }

    public function scopeAvailable($q)
    {
        return $q->where('ambulances.status', 'available')->where('ambulances.is_active', true);
    }

    public function scopeDispatched($q)
    {
        return $q->where('ambulances.status', 'dispatched');
    }

    public function scopeOnTrip($q)
    {
        return $q->where('ambulances.status', 'on_trip');
    }

    public function scopeInMaintenance($q)
    {
        return $q->whereIn('ambulances.status', ['maintenance', 'out_of_service']);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available' && (bool) $this->is_active;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'available' => 'success',
            'dispatched' => 'info',
            'on_trip' => 'primary',
            'maintenance' => 'warning',
            'out_of_service' => 'danger',
            default => 'secondary',
        };
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function needsService(): bool
    {
        return $this->next_service_date !== null
            && $this->next_service_date->isPastOrToday();
    }

    public function isInsuranceExpiringSoon(int $days = 30): bool
    {
        return $this->insurance_expiry !== null
            && $this->insurance_expiry->lte(today()->addDays($days));
    }
}
