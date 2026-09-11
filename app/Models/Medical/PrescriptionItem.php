<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

class PrescriptionItem extends Model
{
    protected $table = 'prescription_items';

    protected $fillable = [
        'prescription_id',
        'medicine_id',
        'medicine_name',
        'dgda_code',
        // Phase 10 — immutable medicine-identity snapshots (written once at
        // item creation by PrescriptionService; never updated afterwards).
        'medicine_concept_id',
        'medicine_product_id',
        'display_name_snapshot',
        'strength_snapshot',
        'dosage_form_snapshot',
        'route_snapshot',
        'rxnorm_code_snapshot',
        'dosage',
        'frequency',
        'duration_days',
        'quantity',
        'special_instructions',
        'status',
    ];

    protected $casts = [
        'duration_days' => 'integer',
        'quantity' => 'integer',
    ];

    public function prescription()
    {
        return $this->belongsTo(Prescription::class);
    }

    public function medicine()
    {
        return $this->belongsTo(Medicine::class);
    }

    public function dispenses()
    {
        return $this->hasMany(PharmacyDispense::class, 'prescription_item_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeDispensed($query)
    {
        return $query->where('status', 'dispensed');
    }
}
