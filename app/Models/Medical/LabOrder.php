<?php

namespace App\Models\Medical;

use App\Models\Institute;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LabOrder extends Model
{
    use SoftDeletes;

    protected $table = 'lab_orders';

    protected $fillable = [
        'institute_id',
        'patient_id',
        'doctor_id',
        'prescription_id',
        'order_number',
        'order_date',
        'priority',
        'status',
        'clinical_notes',
        'result_notes',
        'collected_at',
        'completed_at',
        'collected_by',
        'completed_by',
    ];

    protected $casts = [
        'order_date' => 'date',
        'collected_at' => 'datetime',
        'completed_at' => 'datetime',
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

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function collectedBy()
    {
        return $this->belongsTo(User::class, 'collected_by');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function results()
    {
        return $this->hasMany(LabResult::class, 'lab_order_id');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['ordered', 'collected', 'processing']);
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Phase 4 addition.
     *
     * Whether the order can still move forward in the pipeline.
     */
    public function canProcess(): bool
    {
        return in_array($this->status, ['ordered', 'collected'], true);
    }

    /**
     * Phase 4 addition.
     *
     * Whether results may be entered.
     */
    public function readyForResults(): bool
    {
        return in_array($this->status, ['collected', 'processing'], true);
    }

    /**
     * Phase 4 addition.
     */
    public function getStatusClassAttribute(): string
    {
        return match ($this->status) {
            'ordered' => 'primary',
            'collected' => 'info',
            'processing' => 'warning',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'secondary',
        };
    }
}
