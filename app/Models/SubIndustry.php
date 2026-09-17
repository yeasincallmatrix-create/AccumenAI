<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubIndustry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => 'string',
        'country_id' => 'integer',
        'industry_id' => 'integer',
    ];

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class, 'industry_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class, 'country_id');
    }

    public function institutes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Institute::class, 'sub_industry_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForCountry($query, ?int $countryId)
    {
        return $query->where(function ($q) use ($countryId) {
            $q->whereNull('country_id')
              ->orWhere('country_id', $countryId);
        });
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isGlobal(): bool
    {
        return $this->country_id === null;
    }

    public function isAvailableInCountry(?int $countryId): bool
    {
        return $this->country_id === null || $this->country_id === $countryId;
    }
}
