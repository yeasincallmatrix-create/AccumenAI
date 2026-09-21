<?php

namespace App\Models\Accounting;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProgressiveContract extends Model
{
    use SoftDeletes, TenantScoped;

    protected $table = 'progressive_contracts';

    protected $fillable = [
        'institute_id', 'branch_id', 'contract_number',
        'party_id', 'sales_quotation_id',
        'title', 'description', 'total_value', 'currency', 'exchange_rate',
        'retention_percentage', 'retention_amount', 'retention_released',
        'total_billed', 'total_paid', 'remaining_value', 'progress_percentage',
        'start_date', 'expected_end_date', 'actual_end_date',
        'status', 'tax_group_id', 'tax_method', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'total_value' => 'decimal:2',
            'exchange_rate' => 'decimal:6',
            'retention_percentage' => 'decimal:2',
            'retention_amount' => 'decimal:2',
            'retention_released' => 'decimal:2',
            'total_billed' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'remaining_value' => 'decimal:2',
            'progress_percentage' => 'decimal:2',
            'start_date' => 'date',
            'expected_end_date' => 'date',
            'actual_end_date' => 'date',
        ];
    }

    public const STATUSES = ['active', 'on_hold', 'completed', 'cancelled', 'terminated'];

    public function institute(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Institute::class);
    }

    public function branch(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Branch::class);
    }

    public function party(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Party::class);
    }

    public function salesQuotation(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\SalesQuotation::class);
    }

    public function taxGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\TaxGroup::class);
    }

    public function invoices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\Invoice::class, 'progressive_contract_id');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    public function scopeForInstitute($q, $id)
    {
        return $q->where('institute_id', $id);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function percentBilled(): float
    {
        if ($this->total_value <= 0) {
            return 0;
        }

        return round((float) $this->total_billed / (float) $this->total_value * 100, 2);
    }

    public function remainingValue(): float
    {
        return round((float) $this->total_value - (float) $this->total_billed, 2);
    }

    public function retentionHeld(): float
    {
        return round((float) $this->retention_amount - (float) $this->retention_released, 2);
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            'active' => 'primary',
            'on_hold' => 'warning',
            'completed' => 'success',
            'cancelled' => 'secondary',
            'terminated' => 'danger',
            default => 'secondary',
        };
    }
}
