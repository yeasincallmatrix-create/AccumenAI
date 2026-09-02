<?php

namespace App\Support;

use App\Models\AdministrativeUnit;
use App\Models\Country;
use Illuminate\Support\Collection;

/**
 * Country-neutral helpers for the global 3-level address system.
 *
 * Everything here is driven by the dataset (countries / administrative_levels /
 * administrative_units) plus the curated label map in config/geo-labels.php.
 * No country-specific branches — Bangladesh, USA, India, ... all resolve
 * through the same code paths.
 */
final class GeoHierarchy
{
    /**
     * Resolve the display label for each of the 3 levels of a country.
     *
     * Database-stored labels (administrative_levels.name, editable by Super
     * Admin) take precedence; the curated config map is only a fallback for
     * countries whose level labels have not been saved yet.
     */
    public static function levelLabels(Country $country): array
    {
        $defaults = config('geo-labels.defaults');
        $labels = config('geo-labels.labels');

        $labelsByLevel = [];
        foreach ($country->selectableLevels()->orderBy('level_number')->get() as $level) {
            $labelsByLevel[(int) $level->level_number] = $level->name;
        }

        $countryLabels = $labels[$country->iso2] ?? [];

        return [
            1 => $labelsByLevel[1] ?? $countryLabels[1] ?? $defaults[1] ?? 'State / Province',
            2 => $labelsByLevel[2] ?? $countryLabels[2] ?? $defaults[2] ?? 'District / County',
            3 => $labelsByLevel[3] ?? $countryLabels[3] ?? $defaults[3] ?? 'City / Sub-district',
        ];
    }

    /** The levels the UI should expose for a country (max 3, sorted). */
    public static function selectableLevels(Country $country): Collection
    {
        return $country->selectableLevels()->orderBy('level_number')->get();
    }

    /**
     * Strict server-side validation of a submitted hierarchy.
     *
     * Returns null when valid, otherwise an error message keyed for the 422
     * response. A level-2 unit must belong to the submitted level-1 unit; a
     * level-3 unit must belong to the submitted level-2 unit; every unit must
     * belong to the submitted country. This blocks cross-country / cross-parent
     * tampering.
     */
    public static function validateHierarchy(
        int $countryId,
        int|string|null $level1Id,
        int|string|null $level2Id,
        int|string|null $level3Id,
    ): ?string {
        if (! Country::whereKey($countryId)->where('status', true)->exists()) {
            return 'geo.invalid_country';
        }

        if ($level1Id !== null && $level1Id !== '') {
            $l1 = self::unitInCountry($countryId, $level1Id, 1);
            if ($l1 === null) {
                return 'geo.invalid_level1';
            }

            if ($level2Id !== null && $level2Id !== '') {
                $l2 = self::unitInCountry($countryId, $level2Id, 2);
                if ($l2 === null || (int) $l2->parent_id !== (int) $l1->id) {
                    return 'geo.invalid_level2';
                }

                if ($level3Id !== null && $level3Id !== '') {
                    $l3 = self::unitInCountry($countryId, $level3Id, 3);
                    if ($l3 === null || (int) $l3->parent_id !== (int) $l2->id) {
                        return 'geo.invalid_level3';
                    }
                }
            }
        }

        return null;
    }

    private static function unitInCountry(int $countryId, int|string $id, int $level): ?AdministrativeUnit
    {
        return AdministrativeUnit::query()
            ->where('id', (int) $id)
            ->where('country_id', $countryId)
            ->where('status', true)
            ->whereHas('level', fn ($q) => $q->where('level_number', $level))
            ->first();
    }
}
