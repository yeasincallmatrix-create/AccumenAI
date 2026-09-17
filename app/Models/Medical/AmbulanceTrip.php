<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AmbulanceTrip extends Model
{
    use SoftDeletes;

    protected $table = 'ambulance_trips';

    public const TRIP_TYPES = [
        'emergency_pickup' => 'Emergency Pickup',
        'inter_hospital_transfer' => 'Inter-Hospital Transfer',
        'discharge_drop' => 'Discharge Drop',
        'dialysis_run' => 'Dialysis Run',
        'medical_checkup' => 'Medical Checkup',
        'standby' => 'Standby',
        'deceased_transport' => 'Deceased Transport',
        'other' => 'Other',
    ];

    public const STATUSES = [
        'requested' => 'Requested',
        'dispatched' => 'Dispatched',
        'en_route_to_pickup' => 'En Route to Pickup',
        'at_pickup' => 'At Pickup',
        'en_route_to_dropoff' => 'En Route to Drop-off',
        'arrived' => 'Arrived',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
        'aborted' => 'Aborted',
    ];

    public const ACTIVE_STATUSES = [
        'dispatched',
        'en_route_to_pickup',
        'at_pickup',
        'en_route_to_dropoff',
        'arrived',
    ];

    public const FINAL_STATUSES = [
        'completed',
        'cancelled',
        'aborted',
    ];

    public const PRIORITIES = [
        'routine' => 'Routine',
        'urgent' => 'Urgent',
        'critical' => 'Critical',
    ];

    protected $fillable = [
        'institute_id', 'branch_id', 'trip_number',
        'ambulance_id', 'driver_id', 'attendant_id',
        'patient_id', 'emergency_visit_id', 'admission_id',
        'trip_type', 'pickup_location', 'pickup_address',
        'pickup_lat', 'pickup_lng', 'dropoff_location', 'dropoff_address',
        'dropoff_lat', 'dropoff_lng',
        'patient_condition_at_pickup', 'vitals_at_pickup', 'treatment_en_route',
        'requested_at', 'dispatched_at', 'arrived_at_pickup', 'departed_pickup',
        'arrived_at_dropoff', 'completed_at',
        'odometer_start_km', 'odometer_end_km', 'distance_km', 'duration_minutes',
        'status', 'priority',
        'base_fee', 'distance_fee', 'waiting_fee', 'total_fee', 'payment_status',
        'invoice_id', 'cancellation_reason', 'cancelled_by', 'cancelled_at',
        'driver_notes', 'dispatch_notes',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'arrived_at_pickup' => 'datetime',
        'departed_pickup' => 'datetime',
        'arrived_at_dropoff' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'vitals_at_pickup' => 'array',
        'pickup_lat' => 'decimal:7',
        'pickup_lng' => 'decimal:7',
        'dropoff_lat' => 'decimal:7',
        'dropoff_lng' => 'decimal:7',
        'odometer_start_km' => 'decimal:2',
        'odometer_end_km' => 'decimal:2',
        'distance_km' => 'decimal:2',
        'duration_minutes' => 'integer',
        'base_fee' => 'decimal:2',
        'distance_fee' => 'decimal:2',
        'waiting_fee' => 'decimal:2',
        'total_fee' => 'decimal:2',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function ambulance()
    {
        return $this->belongsTo(Ambulance::class);
    }

    public function driver()
    {
        return $this->belongsTo(AmbulanceDriver::class, 'driver_id');
    }

    public function attendant()
    {
        return $this->belongsTo(User::class, 'attendant_id');
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('ambulance_trips.institute_id', $id);
    }

    public function scopePending($q)
    {
        return $q->where('ambulance_trips.status', 'requested');
    }

    public function scopeActive($q)
    {
        return $q->whereIn('ambulance_trips.status', self::ACTIVE_STATUSES);
    }

    public function scopeCompleted($q)
    {
        return $q->where('ambulance_trips.status', 'completed');
    }

    public function scopeCancelled($q)
    {
        return $q->whereIn('ambulance_trips.status', ['cancelled', 'aborted']);
    }

    public function scopeToday($q)
    {
        return $q->whereDate('ambulance_trips.requested_at', today());
    }

    public function scopeForAmbulance($q, int $ambulanceId)
    {
        return $q->where('ambulance_trips.ambulance_id', $ambulanceId);
    }

    public function scopeForDriver($q, int $driverId)
    {
        return $q->where('ambulance_trips.driver_id', $driverId);
    }

    public function scopeForPatient($q, int $patientId)
    {
        return $q->where('ambulance_trips.patient_id', $patientId);
    }

    public function scopeEmergency($q)
    {
        return $q->where('ambulance_trips.trip_type', 'emergency_pickup');
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'requested' => 'secondary',
            'dispatched', 'en_route_to_pickup' => 'info',
            'at_pickup', 'en_route_to_dropoff' => 'primary',
            'arrived' => 'warning',
            'completed' => 'success',
            'cancelled', 'aborted' => 'danger',
            default => 'dark',
        };
    }

    public function tripTypeLabel(): string
    {
        return self::TRIP_TYPES[$this->trip_type] ?? $this->trip_type;
    }

    public function priorityColor(): string
    {
        return match ($this->priority) {
            'routine' => 'info',
            'urgent' => 'warning',
            'critical' => 'danger',
            default => 'secondary',
        };
    }

    public function calculateDuration(): ?int
    {
        if (! $this->requested_at || ! $this->completed_at) {
            return null;
        }

        return $this->requested_at->diffInMinutes($this->completed_at);
    }

    public function calculateTotalFee(): float
    {
        return round((float) $this->base_fee + (float) $this->distance_fee + (float) $this->waiting_fee, 2);
    }
}
