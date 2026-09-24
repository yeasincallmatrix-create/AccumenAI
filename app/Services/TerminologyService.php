<?php

namespace App\Services;

use App\Models\Institute;
use Illuminate\Support\Facades\DB;

class TerminologyService
{
    /**
     * Get term for a tenant.
     * Priority: tenant override > country-specific > global > default.
     */
    public function get(string $termKey, ?Institute $institute = null, ?string $default = null): string
    {
        // 1. Tenant override
        if ($institute && $institute->terminology_overrides) {
            $overrides = is_string($institute->terminology_overrides)
                ? json_decode($institute->terminology_overrides, true)
                : $institute->terminology_overrides;

            if (is_array($overrides) && isset($overrides[$termKey])) {
                return (string) $overrides[$termKey];
            }
        }

        // 2. Country-specific
        if ($institute && $institute->country_code) {
            $term = DB::table('module_terminology')
                ->where('country_code', $institute->country_code)
                ->where('term_key', $termKey)
                ->where('is_active', true)
                ->first();
            if ($term) {
                return $term->term_value;
            }
        }

        // 3. Global (country_code IS NULL)
        $term = DB::table('module_terminology')
            ->whereNull('country_code')
            ->where('term_key', $termKey)
            ->where('is_active', true)
            ->first();

        if ($term) {
            return $term->term_value;
        }

        // 4. Fallback
        return $default ?? $termKey;
    }

    /**
     * Get all active terms for a country.
     *
     * @return array<string, string>
     */
    public function getAllForCountry(string $countryCode): array
    {
        return DB::table('module_terminology')
            ->where('country_code', $countryCode)
            ->where('is_active', true)
            ->pluck('term_value', 'term_key')
            ->toArray();
    }

    /**
     * Set a tenant-level term override (persists to institutes.terminology_overrides JSON).
     */
    public function setOverride(Institute $institute, string $termKey, string $value): void
    {
        $overrides = $institute->terminology_overrides;
        $overrides = is_string($overrides) ? (json_decode($overrides, true) ?? []) : ($overrides ?? []);

        $overrides[$termKey] = $value;

        // Institute has no array cast for this column yet — persist as JSON string.
        $institute->terminology_overrides = json_encode($overrides, JSON_UNESCAPED_UNICODE);
        $institute->save();
    }

    /**
     * Remove a tenant-level term override (fall back to country/global wording).
     */
    public function removeOverride(Institute $institute, string $termKey): void
    {
        $overrides = $institute->terminology_overrides;
        $overrides = is_string($overrides) ? (json_decode($overrides, true) ?? []) : ($overrides ?? []);

        if (! isset($overrides[$termKey])) {
            return;
        }

        unset($overrides[$termKey]);

        $institute->terminology_overrides = json_encode($overrides, JSON_UNESCAPED_UNICODE);
        $institute->save();
    }
}
