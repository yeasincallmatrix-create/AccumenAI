<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Global currency catalog. Shared across all institutes (no institute_id).
 * One row is flagged is_base per installation; each institute pins its own base
 * currency via tenant_currency_settings.
 */
class Currency extends Model
{
    protected $table = 'currencies';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'decimal_places' => 'integer',
            'rounding' => 'integer',
            'is_base' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function exchangeRatesFrom(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'from_currency_id');
    }

    public function exchangeRatesTo(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'to_currency_id');
    }
}
