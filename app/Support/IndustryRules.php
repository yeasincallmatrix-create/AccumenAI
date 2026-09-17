<?php

namespace App\Support;

use App\Models\Country;
use App\Models\Industry as IndustryModel;
use App\Models\SubIndustry as SubIndustryModel;
use App\Services\IndustryService;

/**
 * Country-scoped industry accessor.
 *
 * Primary source: database (industries + sub_industries tables).
 * Fallback: config/industry_rules.php (during transition or if DB is empty).
 *
 * All public method signatures remain identical for backward compatibility.
 */
final class IndustryRules
{
    /**
     * The industries available for a country: country-scoped when defined,
     * otherwise the global list. Returns slug => label.
     *
     * @param  string|null  $country  null/empty means the global (platform) list.
     */
    public static function industries(?string $country): array
    {
        $countryId = self::resolveCountryId($country);
        $result = IndustryService::industries($countryId);

        if ($result !== []) {
            return $result;
        }

        // Fallback to config if DB is empty
        return (array) config('industry_rules.global.industries', []);
    }

    /**
     * The sub-industries a country+industry offers, slug => label. Empty when
     * the industry is not listed or has no sub-categories in that country.
     *
     * @param  string|null  $country  null/empty means the default (global) sub-industries.
     */
    public static function subIndustries(?string $country, string $industry): array
    {
        $countryId = self::resolveCountryId($country);
        $result = IndustryService::subIndustriesBySlug($country, $industry);

        if ($result !== []) {
            return $result;
        }

        // Fallback to config
        if ($country === null || $country === '') {
            return (array) config('industry_rules.global.sub_industries.' . $industry, []);
        }

        $subs = config('industry_rules.' . $country . '.' . $industry, null);

        return is_array($subs) ? $subs : [];
    }

    /**
     * Whether the industry requires a sub-industry choice for this country.
     */
    public static function hasSubIndustries(string $country, string $industry): bool
    {
        return self::subIndustries($country, $industry) !== [];
    }

    /**
     * Resolve the human label for an industry/sub-industry selection.
     *
     * @param  string  $country  exact country name (institutes.country)
     * @param  string  $industry  industry slug
     * @param  string|null  $sub  sub-industry slug (null for none)
     */
    public static function label(string $country, string $industry, ?string $sub = null): ?string
    {
        $countryId = self::resolveCountryId($country);
        $label = IndustryService::label($countryId, $industry, $sub);

        if ($label !== null) {
            return $label;
        }

        // Fallback to config
        $industryLabel = config('industry_rules.global.industries.' . $industry);
        if ($industryLabel === null) {
            $industryLabel = self::labelOf($industry);
        }

        if ($sub === null || $sub === '') {
            return $industryLabel;
        }

        return config('industry_rules.' . $country . '.' . $industry . '.' . $sub)
            ?? config('industry_rules.global.sub_industries.' . $industry . '.' . $sub)
            ?? $sub;
    }

    /**
     * The global label for an industry slug (its value in the global list),
     * falling back to a readable slug.
     */
    protected static function labelOf(string $industry): string
    {
        return config('industry_rules.global.industries.' . $industry)
            ?? ucwords(str_replace('_', ' ', $industry));
    }

    /**
     * Resolve country name to country_id for database lookups.
     */
    private static function resolveCountryId(?string $country): ?int
    {
        if ($country === null || $country === '') {
            return null;
        }

        $countryModel = Country::where('name', $country)->first();

        return $countryModel?->id;
    }
}
