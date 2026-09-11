<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 10 — a marketed formulation: concept + form (+route) + strength +
 * brand. Global identity; tenant commercial data lives on
 * InstituteMedicine, never here.
 */
class MedicineProduct extends Model
{
    protected $fillable = [
        'medicine_concept_id', 'medicine_form_id', 'medicine_route_id',
        'display_name', 'normalized_name', 'strength_raw', 'brand_name',
        'category', 'side_effects', 'contraindications', 'storage_conditions',
        'requires_prescription', 'is_controlled', 'status',
    ];

    protected $casts = [
        'requires_prescription' => 'boolean',
        'is_controlled' => 'boolean',
    ];

    public function concept()
    {
        return $this->belongsTo(MedicineConcept::class, 'medicine_concept_id');
    }

    public function form()
    {
        return $this->belongsTo(MedicineForm::class, 'medicine_form_id');
    }

    public function route()
    {
        return $this->belongsTo(MedicineRoute::class, 'medicine_route_id');
    }

    public function productIngredients()
    {
        return $this->hasMany(MedicineProductIngredient::class, 'medicine_product_id');
    }

    public function ingredients()
    {
        return $this->belongsToMany(
            MedicineIngredient::class,
            'medicine_product_ingredients',
            'medicine_product_id',
            'medicine_ingredient_id'
        )->withPivot(['strength_value', 'strength_unit', 'sequence'])->orderByPivot('sequence');
    }

    public function identifiers()
    {
        return $this->hasMany(MedicineIdentifier::class, 'medicine_product_id');
    }

    public function catalogEntries()
    {
        return $this->hasMany(InstituteMedicine::class, 'medicine_product_id');
    }
}
