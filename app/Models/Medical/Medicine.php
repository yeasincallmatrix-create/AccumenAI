<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    protected $table = 'medicines';

    protected $fillable = [
        'institute_id',
        'medicine_product_id',
        'code',
        'generic_name',
        'brand_name',
        'category',
        'dosage_form',
        'strength',
        'unit',
        'pack_size',
        'purchase_price',
        'selling_price',
        'vat_percentage',
        'reorder_level',
        'reorder_quantity',
        'requires_prescription',
        'is_controlled',
        'dgda_code',
        'dgda_dar_number',
        'dgda_concept_id',
        'dgda_synced_at',
        'dgda_status',
        'side_effects',
        'contraindications',
        'storage_conditions',
        'is_active',
    ];

    protected $casts = [
        'pack_size' => 'integer',
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'vat_percentage' => 'decimal:2',
        'reorder_level' => 'integer',
        'reorder_quantity' => 'integer',
        'requires_prescription' => 'boolean',
        'is_controlled' => 'boolean',
        'is_active' => 'boolean',
        'dgda_synced_at' => 'datetime',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    /**
     * Phase 10 — compatibility link to the normalized product. Nullable
     * until mapped; all operational reads/writes keep using this row.
     */
    public function product()
    {
        return $this->belongsTo(MedicineProduct::class, 'medicine_product_id');
    }

    public function stocks()
    {
        return $this->hasMany(PharmacyStock::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Phase 02: keyword alternatives are grouped so a chained
     * where('institute_id', ...) can never be escaped by the ORs.
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('generic_name', 'LIKE', "%{$search}%")
                ->orWhere('brand_name', 'LIKE', "%{$search}%")
                ->orWhere('code', 'LIKE', "%{$search}%")
                ->orWhere('dgda_code', 'LIKE', "%{$search}%")
                ->orWhere('dgda_dar_number', 'LIKE', "%{$search}%");
        });
    }

    public function getTotalStockAttribute()
    {
        return $this->stocks()->sum('current_quantity');
    }

    public function getAvailableStockAttribute()
    {
        return $this->stocks()
            ->where('expiry_date', '>', now())
            ->where('current_quantity', '>', 0)
            ->sum('current_quantity');
    }

    /**
     * Phase 3 addition.
     *
     * Whether this medicine can be dispensed without a prescription.
     */
    public function isOverTheCounter(): bool
    {
        return ! $this->requires_prescription;
    }

    /**
     * Phase 3 addition.
     *
     * Display name (generic + brand).
     */
    public function getDisplayNameAttribute(): string
    {
        $name = (string) $this->generic_name;
        if ($this->brand_name) {
            $name .= ' ('.$this->brand_name.')';
        }

        return $name;
    }
}
