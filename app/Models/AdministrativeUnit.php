<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdministrativeUnit extends Model
{
    protected $table = 'administrative_units';

    protected $fillable = [
        'country_id',
        'administrative_level_id',
        'parent_id',
        'name',
        'code',
        'postal_code',
        'latitude',
        'longitude',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(AdministrativeLevel::class, 'administrative_level_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AdministrativeUnit::class, 'parent_id');
    }
}
