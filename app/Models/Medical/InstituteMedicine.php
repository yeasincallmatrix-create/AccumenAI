<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase 10 — tenant catalog entry: "this institute offers this product".
 * Holds ONLY tenant-local commercial/operational data (code, names,
 * preference, prices, reorder points, active state). Product identity,
 * regulatory flags and clinical knowledge live on the product; inventory
 * lives on pharmacy_stock. Always tenant-scoped in queries.
 */
class InstituteMedicine extends Model
{
    protected $fillable = [
        'institute_id', 'medicine_product_id', 'local_code', 'local_name',
        'preferred', 'active', 'purchase_price', 'selling_price',
        'vat_percentage', 'reorder_level', 'reorder_quantity',
    ];

    protected $casts = [
        'preferred' => 'boolean',
        'active' => 'boolean',
        'purchase_price' => 'decimal:2',
        'selling_price' => 'decimal:2',
        'vat_percentage' => 'decimal:2',
        'reorder_level' => 'integer',
        'reorder_quantity' => 'integer',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function product()
    {
        return $this->belongsTo(MedicineProduct::class, 'medicine_product_id');
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
