<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Institute;

class VaccineStock extends Model
{
    use SoftDeletes;

    protected $table = 'vaccine_stocks';

    protected $fillable = [
        'institute_id', 'branch_id', 'vaccine_master_id',
        'batch_number', 'manufacture_date', 'expiry_date',
        'quantity_received', 'quantity_used', 'quantity_available',
        'storage_location', 'temperature_min', 'temperature_max', 'status',
    ];

    protected $casts = [
        'manufacture_date' => 'date',
        'expiry_date' => 'date',
        'quantity_received' => 'integer',
        'quantity_used' => 'integer',
        'quantity_available' => 'integer',
        'temperature_min' => 'decimal:2',
        'temperature_max' => 'decimal:2',
    ];

    public const STATUSES = [
        'available' => 'Available',
        'low_stock' => 'Low Stock',
        'expired' => 'Expired',
        'discarded' => 'Discarded',
    ];

    public function institute(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function branch()
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function vaccineMaster(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(VaccineMaster::class, 'vaccine_master_id');
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where('institute_id', $id);
    }

    public function scopeAvailable($q)
    {
        return $q->where('status', 'available')->where('quantity_available', '>', 0);
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expiry_date->lte(today()->addDays($days)) && $this->expiry_date->gte(today());
    }

    public function isLowStock(int $threshold = 5): bool
    {
        return $this->quantity_available <= $threshold && $this->quantity_available > 0;
    }

    public function isExpired(): bool
    {
        return $this->expiry_date->lt(today());
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'available' => 'success',
            'low_stock' => 'warning',
            'expired' => 'danger',
            'discarded' => 'secondary',
            default => 'secondary',
        };
    }
}
