<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TaxDeductionRule extends Model
{
    protected $table = 'tax_deduction_rules';

    public $timestamps = true;

    protected $fillable = [
        'country_code',
        'currency_code',
        'code',
        'name',
        'description',
        'category',
        'rate',
        'threshold',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'threshold' => 'decimal:2',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function scopeForCountry(Builder $builder, string $countryCode): Builder
    {
        return $builder->where('country_code', strtoupper($countryCode));
    }

    public function scopeActive(Builder $builder): Builder
    {
        return $builder->where('is_active', true);
    }

    public function scopeForCategory(Builder $builder, string $category): Builder
    {
        return $builder->where('category', $category);
    }
}
