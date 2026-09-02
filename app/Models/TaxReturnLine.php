<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxReturnLine extends Model
{
    protected $table = 'tax_return_lines';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'total_sales' => 'decimal:4',
            'total_purchases' => 'decimal:4',
            'tax_collected' => 'decimal:4',
            'tax_paid' => 'decimal:4',
            'net_tax' => 'decimal:4',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function taxReturn(): BelongsTo
    {
        return $this->belongsTo(TaxReturnPeriod::class, 'tax_return_id');
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }
}
