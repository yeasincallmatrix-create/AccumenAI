<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 10 — normalized active ingredient (e.g. Paracetamol, Clavulanic
 * Acid). Global vocabulary; combinations reference several of these.
 */
class MedicineIngredient extends Model
{
    protected $fillable = ['canonical_name', 'normalized_name', 'status'];

    public function products()
    {
        return $this->belongsToMany(
            MedicineProduct::class,
            'medicine_product_ingredients',
            'medicine_ingredient_id',
            'medicine_product_id'
        )->withPivot(['strength_value', 'strength_unit', 'sequence']);
    }
}
