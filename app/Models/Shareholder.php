<?php

namespace App\Models;

use App\Models\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shareholder extends Model
{
    use TenantScoped;

    protected $fillable = [
        'institute_id', 'name', 'email', 'nid', 'address',
        'shares', 'face_value', 'share_percent', 'certificate_no', 'issued_at',
        'is_director', 'director_designation', 'is_active',
    ];

    protected $casts = [
        'shares' => 'integer',
        'face_value' => 'decimal:2',
        'share_percent' => 'decimal:2',
        'issued_at' => 'date',
        'is_director' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function institute(): BelongsTo
    {
        return $this->belongsTo(Institute::class);
    }

    public function getTotalInvestmentAttribute(): float
    {
        return (float) ($this->shares * $this->face_value);
    }

    public function scopeDirectors($query)
    {
        return $query->where('is_director', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
