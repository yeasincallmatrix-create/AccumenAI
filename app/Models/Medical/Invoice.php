<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    // Phase 0: `medical_invoices` — the plain `invoices` table belongs to the
    // finance module (student/course billing) and must not be touched.
    protected $table = 'medical_invoices';

    protected $fillable = [
        'institute_id',
        'patient_id',
        'admission_id',
        'invoice_number',
        'invoice_date',
        'due_date',
        'type',
        'subtotal',
        'tax',
        'discount',
        'total',
        'paid_amount',
        'due_amount',
        'status',
        'payment_method',
        'payment_reference',
        'items_data',
        'notes',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'due_date' => 'date',
        'subtotal' => 'decimal:2',
        'tax' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_amount' => 'decimal:2',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    public function tpaClaims()
    {
        return $this->hasMany(TpaClaim::class, 'invoice_id');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'partial']);
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    /**
     * Phase 4 addition.
     *
     * Overdue = still unpaid past its due date (null due date never overdue).
     */
    public function isOverdue(): bool
    {
        return $this->status === 'pending'
            && $this->due_date !== null
            && $this->due_date->lt(now());
    }

    /**
     * Phase 4 addition.
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    /**
     * Phase 4 addition.
     */
    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            'paid' => 'Paid',
            'partial' => 'Partial',
            'pending' => 'Pending',
            'draft' => 'Draft',
            'cancelled' => 'Cancelled',
            default => 'Unknown',
        };
    }

    /**
     * Phase 4 addition.
     */
    public function getTypeClassAttribute(): string
    {
        return match ($this->type) {
            'opd' => 'primary',
            'ipd' => 'info',
            'pharmacy' => 'warning',
            'lab' => 'success',
            'surgery' => 'danger',
            default => 'secondary',
        };
    }
}
