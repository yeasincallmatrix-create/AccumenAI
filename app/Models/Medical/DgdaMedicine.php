<?php

namespace App\Models\Medical;

use Illuminate\Database\Eloquent\Model;

class DgdaMedicine extends Model
{
    protected $fillable = [
        'country_code',
        'dgda_code',
        'dar_number',
        'concept_id',
        'brand_name',
        'generic_name',
        'strength',
        'dosage_form',
        'route',
        'manufacturer',
        'pack_size',
        'normalized_name',
        'status',
        'synced_at',
    ];

    protected $casts = [
        'synced_at' => 'datetime',
    ];

    /**
     * Generate normalized_name from brand_name + strength.
     */
    public static function generateNormalized(string $brandName, ?string $strength): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower(trim($brandName.' '.($strength ?? ''))));
    }
}
