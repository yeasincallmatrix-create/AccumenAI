<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A country's education system (General / Madrasa / Technical / International ...).
 *
 * Global shared reference data owned by a country — intentionally NOT
 * TenantScoped (see Concerns\TenantScoped docblock).
 */
class EducationSystem extends Model
{
    protected $table = 'education_systems';

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

    public function levels(): HasMany
    {
        return $this->hasMany(AcademicLevel::class)->orderBy('display_order')->orderBy('id');
    }
}
