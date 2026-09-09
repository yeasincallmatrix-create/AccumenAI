<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;

class LabTest extends Model
{
    protected $table = 'lab_tests';

    protected $fillable = [
        'institute_id',
        'code',
        'name',
        'category',
        'description',
        'normal_range',
        'unit',
        'price',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function results()
    {
        return $this->hasMany(LabResult::class, 'lab_test_id');
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
            $q->where('name', 'LIKE', "%{$search}%")
                ->orWhere('code', 'LIKE', "%{$search}%");
        });
    }

    /**
     * Phase 4 addition.
     *
     * Display name with category.
     */
    public function getDisplayNameAttribute(): string
    {
        $name = (string) $this->name;
        if ($this->category) {
            $name .= ' ('.$this->category.')';
        }

        return $name;
    }

    /**
     * Phase 4 addition.
     */
    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    /**
     * Phase 4 addition.
     */
    public function scopeCategory($query, $category)
    {
        return $query->where('category', $category);
    }
}
