<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 10 — normalized clinical concept (ingredient-level identity).
 * Single ("Paracetamol") or combination ("Calcium + Vitamin D3").
 * Global vocabulary; never tenant-scoped, never holding prices or stock.
 */
class MedicineConcept extends Model
{
    protected $fillable = [
        'canonical_name', 'normalized_name', 'description', 'concept_type', 'status',
    ];

    public function products()
    {
        return $this->hasMany(MedicineProduct::class, 'medicine_concept_id');
    }
}
