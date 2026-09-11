<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 10 — product↔ingredient pivot with per-ingredient strength.
 * Pure dependent of the product (SAFE CASCADE).
 */
class MedicineProductIngredient extends Model
{
    protected $fillable = [
        'medicine_product_id', 'medicine_ingredient_id',
        'strength_value', 'strength_unit', 'sequence',
    ];

    protected $casts = [
        'strength_value' => 'decimal:3',
        'sequence' => 'integer',
    ];

    public function product()
    {
        return $this->belongsTo(MedicineProduct::class, 'medicine_product_id');
    }

    public function ingredient()
    {
        return $this->belongsTo(MedicineIngredient::class, 'medicine_ingredient_id');
    }
}
