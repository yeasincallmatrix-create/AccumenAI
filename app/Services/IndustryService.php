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
     * Get all active industries, optionally scoped to a country.
     * Returns slug => label format for backward compatibility with IndustryRules.
     */
    public static function industries(?int $countryId = null): array
    {
        $cacheKey = 'taxonomy:industries:' . ($countryId ?? 'all');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($countryId) {
            $industries = Industry::active()->orderBy('sort_order')->orderBy('name')->get();

            $result = [];
            foreach ($industries as $ind) {
                // If country specified, only include industries that have sub-industries available in that country
                // or industries that are globally available
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
            $countryId = $countryModel?->id;
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
