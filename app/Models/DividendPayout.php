<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DividendPayout extends Model
{
    use TenantScoped;

    protected $fillable = [
        'dividend_id', 'institute_id', 'shareholder_id',
        'shares', 'gross_amount', 'tax_rate', 'tax_amount', 'net_amount',
        'status', 'paid_date', 'payment_method', 'notes',
    ];

    protected $casts = [
        'shares' => 'integer',
        'gross_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'paid_date' => 'date',
    ];

    public function dividend(): BelongsTo
    {
        return $this->belongsTo(Dividend::class);
    }

    public function shareholder(): BelongsTo
    {
        return $this->belongsTo(Shareholder::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}
