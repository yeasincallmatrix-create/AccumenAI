<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxRateHistory extends Model
{
    protected $table = 'tax_rate_history';
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'old_rate' => 'decimal:4',
            'new_rate' => 'decimal:4',
            'changed_at' => 'date',
        ];
    }

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }
}
