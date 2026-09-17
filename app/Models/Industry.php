<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Industry extends Model
{
    protected $guarded = [];

    protected $casts = [
        'status' => 'string',
    ];

    public function subIndustries(): HasMany
    {
        return $this->hasMany(SubIndustry::class, 'industry_id');
    }

    public function institutes(): HasMany
    {
        return $this->hasMany(Institute::class, 'industry_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
