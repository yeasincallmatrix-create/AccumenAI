<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Maps ISO-3166-1 alpha-2 country codes to their national currency codes.
 * Used for auto-detecting a tenant's base currency at signup.
 */
class CountryCurrencyMap extends Model
{
    protected $table = 'country_currency_map';

    protected $guarded = [];

    /**
     * Return the currency code for a given 2-letter country code.
     */
    public static function currencyForCountry(string $countryCode): ?string
    {
        return static::where('country_code', strtoupper($countryCode))
            ->value('currency_code');
    }
}
