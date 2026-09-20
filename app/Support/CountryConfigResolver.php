<?php

namespace App\Support;

use App\Models\Country;
use App\Models\CountryCurrencyMap;
use App\Models\Institute;

class CountryConfigResolver
{
    /**
     * Resolve a locale config value for an institute.
     *
     * Chain (first match wins):
     *   1. Institute's country row (DB)
     *   2. CountryCurrencyMap (currency keys only)
     *   3. config("locale.$key")
     *   4. Provided $default
     */
    public function resolve(Institute $institute, string $key, mixed $default = null): mixed
    {
        if (str_starts_with($key, 'phone.')) {
            return $this->resolvePhone($institute, $default);
        }
        if (str_starts_with($key, 'currency.')) {
            return $this->resolveCurrency($institute, $default);
        }
        return config("locale.$key", $default);
    }

    protected function resolvePhone(Institute $institute, mixed $default): mixed
    {
        $country = $this->countryFor($institute);
        if ($country && $country->phone_code) {
            return $country->phone_code;
        }
        return config('locale.phone.default_country_code', $default);
    }

    protected function resolveCurrency(Institute $institute, mixed $default): mixed
    {
        $country = $this->countryFor($institute);
        if ($country) {
            $map = CountryCurrencyMap::where('country_code', $country->iso2)->first();
            if ($map && $map->currency_code) {
                return $map->currency_code;
            }
        }
        return config('locale.currency.default_code', $default);
    }

    protected function countryFor(Institute $institute): ?Country
    {
        if (! $institute->country_id) {
            return null;
        }
        return Country::find($institute->country_id);
    }

    /**
     * Convenience: is institute in a specific country (by ISO2)?
     */
    public function isCountry(Institute $institute, string $iso2): bool
    {
        $country = $this->countryFor($institute);
        return $country && $country->iso2 === $iso2;
    }
}
