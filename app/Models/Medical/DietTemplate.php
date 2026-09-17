<?php

namespace App\Models\Medical;

use App\Models\Institute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DietTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'diet_templates';

    protected $fillable = [
        'institute_id', 'name', 'diet_type',
        'description', 'meal_items', 'total_calories', 'is_active',
    ];

    protected $casts = [
        'meal_items' => 'array',
        'total_calories' => 'integer',
        'is_active' => 'boolean',
    ];

    public function institute()
    {
        return $this->belongsTo(Institute::class);
    }

    public function scopeForInstitute($q, int $id)
    {
        return $q->where(function ($qq) use ($id) {
            $qq->where('diet_templates.institute_id', $id)
                ->orWhereNull('diet_templates.institute_id');
        });
    }

    public function scopeActive($q)
    {
        return $q->where('diet_templates.is_active', true);
    }

    public function scopeByType($q, string $type)
    {
        return $q->where('diet_templates.diet_type', $type);
    }

    public function dietTypeLabel(): string
    {
        return DietPlan::DIET_TYPES[$this->diet_type] ?? $this->diet_type;
    }

    public function isGlobal(): bool
    {
        return $this->institute_id === null;
    }
}
