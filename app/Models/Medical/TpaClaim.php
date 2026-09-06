<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;

class TpaClaim extends Model
{
    protected $table = 'tpa_claims';

    protected $fillable = [
        'institute_id',
        'patient_id',
        'invoice_id',
        'claim_number',
        'tpa_company_name',
        'policy_number',
        'claim_amount',
        'approved_amount',
        'status',
        'remarks',
        'claim_date',
        'approval_date',
        'settlement_date',
        'documents',
    ];

    protected $casts = [
        'claim_date' => 'date',
        'approval_date' => 'date',
        'settlement_date' => 'date',
        'claim_amount' => 'decimal:2',
        'approved_amount' => 'decimal:2',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeSettled($query)
    {
        return $query->where('status', 'settled');
    }

    /**
     * Phase 4 addition.
     */
    public function canProcess(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Phase 4 addition.
     */
    public function getStatusClassAttribute(): string
    {
        return match ($this->status) {
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            'partial' => 'info',
            'settled' => 'primary',
            default => 'secondary',
        };
    }
}
