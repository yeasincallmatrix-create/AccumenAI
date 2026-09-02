<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdministrativeLevel extends Model
{
    protected $table = 'administrative_levels';

    protected $fillable = [
        'country_id',
        'level_number',
        'name',
        'slug',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(AdministrativeUnit::class, 'administrative_level_id');
    }
}
