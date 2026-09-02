<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A named academic stage within an education system (Primary / Secondary /
 * Elementary / Middle School / Higher Secondary ...).
 *
 * Global shared reference data — intentionally NOT TenantScoped.
 */
class AcademicLevel extends Model
{
    protected $table = 'academic_levels';

    public $timestamps = true;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function educationSystem(): BelongsTo
    {
        return $this->belongsTo(EducationSystem::class);
    }

    public function classes(): HasMany
    {
        return $this->hasMany(ClassGrade::class, 'academic_level_id')
            ->orderBy('display_order')->orderBy('id');
    }
}
