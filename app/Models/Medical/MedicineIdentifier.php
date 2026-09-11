<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

/**
 * Phase 10 — external identifier layer (dgda|rxnorm|atc|ndc|gtin…).
 * One row per (system, type, value); uniqueness is per source system, so a
 * product may carry many identifiers and different systems never collide.
 * Nothing here is ever fabricated — rows come only from authoritative
 * sources or explicit curation (Phase 11/12 own the sync paths).
 */
class MedicineIdentifier extends Model
{
    public const SYSTEM_DGDA = 'dgda';

    public const SYSTEM_RXNORM = 'rxnorm';

    protected $fillable = [
        'medicine_product_id', 'medicine_ingredient_id', 'system',
        'identifier_type', 'value', 'status', 'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function product()
    {
        return $this->belongsTo(MedicineProduct::class, 'medicine_product_id');
    }
}
