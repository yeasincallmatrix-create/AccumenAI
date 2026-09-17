<?php

namespace App\Services;

use App\Models\Country;
use App\Models\Industry;
use App\Models\SubIndustry;
use Illuminate\Support\Facades\Cache;

/**
 * Database-driven industry/sub-industry taxonomy service.
 *
 * Replaces config/industry_rules.php as the runtime source of truth.
 * Falls back to config during transition period if DB is empty.
 */
final class IndustryService
{
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get all active industries.
     *
     * CONTRACT (Option A — industries are global): industries are
     * country-independent by design. Every country entry in
     * config/industry_rules.php lists the same industry keys (industries
     * without local sub-industries are empty arrays), onboarding validates
     * the industry against the global list, and callers rely on every
     * country receiving the full list. The $countryId parameter is therefore
     * intentionally accepted but ignored, and is kept only for backward
     * compatibility with existing callers (IndustryRules and its tests).
     * Country scoping applies to SUB-industries, not industries.
     *
     * Returns slug => label format for backward compatibility with IndustryRules.
     */
    public static function industries(?int $countryId = null): array
    {
        $cacheKey = 'taxonomy:industries:' . ($countryId ?? 'all');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () {
            $industries = Industry::active()->orderBy('sort_order')->orderBy('name')->get();

            $result = [];
            foreach ($industries as $ind) {
                $result[$ind->slug] = $ind->name;
            }
            return $result;
        });
    }

    /**
     * Get sub-industries for a given country + industry.
     * Returns slug => label format for backward compatibility.
     */
    public static function subIndustries(?int $countryId, int $industryId): array
    {
        $cacheKey = 'taxonomy:sub_industries:' . ($countryId ?? 'null') . ':' . $industryId;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($countryId, $industryId) {
            return SubIndustry::active()
                ->where('industry_id', $industryId)
                ->forCountry($countryId)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->pluck('name', 'slug')
                ->toArray();
        });
    }

    /**
     * Get sub-industries by industry slug (for backward compatibility with IndustryRules).
     *
     * A non-empty country name that matches no Country row is treated as an
     * unknown country and yields [] (the caller then falls back to config,
     * which likewise offers no sub-industries for unlisted countries).
     * Without this, an unknown country would silently receive the global
     * sub-industry list, contradicting the documented config contract
     * ("a country with no entry ... offers no country-scoped sub-industries").
     */
    public static function subIndustriesBySlug(?string $country, string $industrySlug): array
    {
        $industry = Industry::where('slug', $industrySlug)->first();
        if (!$industry) {
            return [];
        }

        $countryId = null;
        if ($country && $country !== '') {
            $countryModel = Country::where('name', $country)->first();
            if (!$countryModel) {
                return [];
            }
            $countryId = $countryModel->id;
        }

        return self::subIndustries($countryId, $industry->id);
    }

    /**
     * Get industries by country name (for backward compatibility with IndustryRules).
     */
    public static function industriesByCountryName(?string $country): array
    {
        if ($country === null || $country === '') {
            return self::industries(null);
        }

        $countryModel = Country::where('name', $country)->first();
        $countryId = $countryModel?->id;

        return self::industries($countryId);
    }

    /**
     * Check if sub-industries exist for a country + industry.
     */
    public static function hasSubIndustries(?int $countryId, int $industryId): bool
    {
        return SubIndustry::active()
            ->where('industry_id', $industryId)
            ->forCountry($countryId)
            ->exists();
    }

    /**
     * Resolve human label for an industry/sub-industry selection.
     */
    public static function label(?int $countryId, ?string $industrySlug, ?string $subSlug = null): ?string
    {
        $industry = Industry::where('slug', $industrySlug)->first();
        if (!$industry) {
            return null;
        }

        if ($subSlug === null || $subSlug === '') {
            return $industry->name;
        }

        $sub = SubIndustry::where('industry_id', $industry->id)
            ->where('slug', $subSlug)
            ->forCountry($countryId)
            ->first();

        return $sub?->name ?? $subSlug;
    }

    /**
     * Validate that an industry/sub-industry/country combination is valid.
     */
    public static function isValidCombination(?int $countryId, ?int $industryId, ?int $subIndustryId): bool
    {
        if ($industryId === null) {
            return false;
        }

        $industry = Industry::find($industryId);
        if (!$industry || !$industry->isActive()) {
            return false;
        }

        if ($subIndustryId !== null) {
            $sub = SubIndustry::find($subIndustryId);
            if (!$sub || !$sub->isActive()) {
                return false;
            }
            if ($sub->industry_id !== $industryId) {
                return false;
            }
            if (!$sub->isAvailableInCountry($countryId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Flush all taxonomy caches.
     */
    public static function flushCache(): void
    {
        $keys = Cache::get('taxonomy:cache_keys', []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget('taxonomy:cache_keys');
    }
}
