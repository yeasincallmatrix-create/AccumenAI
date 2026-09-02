<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Country extends Model
{
    protected $table = 'countries';

    protected $fillable = [
        'name',
        'iso2',
        'iso3',
        'phone_code',
        'academic_unit_label',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function levels(): HasMany
    {
        return $this->hasMany(AdministrativeLevel::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(AdministrativeUnit::class);
    }

    /** The three levels (level_number 1..3) the UI exposes for this country. */
    public function selectableLevels(): HasMany
    {
        return $this->levels()->where('level_number', '<=', 3)->orderBy('level_number');
    }

    /** The academic unit label for this country, e.g. Class / Grade / Year. */
    public function academicUnitLabel(): string
    {
        return $this->academic_unit_label ?: 'Class';
    }

    public function educationSystems(): HasMany
    {
        return $this->hasMany(EducationSystem::class)->orderBy('display_order')->orderBy('id');
    }
}
