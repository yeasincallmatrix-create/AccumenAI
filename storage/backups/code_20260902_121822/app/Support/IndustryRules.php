<?php

namespace App\Support;

/**
 * Country-scoped industry accessor backed by config/industry_rules.php.
 *
 * The config is the single source of truth: a "global" section holds the
 * platform-wide industry list plus default sub-industries, and every other
 * key is a country defining which industries (and their sub-industries) exist
 * there. A country without an entry falls back to the global industry list
 * with no sub-industries, so onboarding and validation always have a usable
 * answer.
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
        if ($country !== null && filled($country)) {
            $scoped = config('industry_rules.'.$country, null);
            if (is_array($scoped) && $scoped !== []) {
                $industries = [];
                foreach ($scoped as $industry => $subs) {
                    $industries[$industry] = self::labelOf($industry);
                }

                return $industries;
            }
        }

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
        if ($country === null || $country === '') {
            return (array) config('industry_rules.global.sub_industries.'.$industry, []);
        }

        $subs = config('industry_rules.'.$country.'.'.$industry, null);

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
        $industryLabel = self::industries($country)[$industry] ?? null;
        if ($industryLabel === null) {
            return null;
        }

        if ($sub === null || $sub === '') {
            return $industryLabel;
        }

        return self::subIndustries($country, $industry)[$sub] ?? $sub;
    }

    /**
     * The global label for an industry slug (its value in the global list),
     * falling back to a readable slug.
     */
    protected static function labelOf(string $industry): string
    {
        return config('industry_rules.global.industries.'.$industry)
            ?? ucwords(str_replace('_', ' ', $industry));
    }
}
