<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Industry extends Model
{
    /**
     * Explicit mass-assignment allow-list. Generated/database-managed fields
     * (id, timestamps) are intentionally NOT fillable.
     */
    protected $fillable = [
        'name',
        'slug',
        'code',
        'description',
        'status',
        'sort_order',
    ];

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
