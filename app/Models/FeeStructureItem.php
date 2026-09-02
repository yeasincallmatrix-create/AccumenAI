<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a fee structure: the fee head to charge and the amount (a
 * structure may override the head's default amount). Optional lines are not
 * included unless explicitly requested when the invoice is generated.
 */
class FeeStructureItem extends Model
{
    protected $table = 'fee_structure_items';

    public $timestamps = true;

    protected $fillable = [
        'fee_structure_id',
        'fee_head_id',
        'amount',
        'is_optional',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'is_optional' => 'boolean',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class, 'fee_structure_id');
    }

    public function feeHead(): BelongsTo
    {
        return $this->belongsTo(FeeHead::class, 'fee_head_id');
    }
}
