<?php

namespace App\Http\Controllers;

use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Support\GeoHierarchy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Generic AJAX endpoints for the global 3-level address selector.
 *
 * One country-neutral controller — no per-country routes/controllers. Every
 * endpoint returns the app-standard JSON envelope { success, message, data }.
 */
class GeoController extends Controller
{
    /** Active countries, optionally filtered by a search term. */
    public function countries(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q'));

        $query = Country::query()->where('status', true)->orderBy('name');

        if ($q !== '') {
            $query->where('name', 'like', '%'.$q.'%');
        }

        $countries = $query->limit(100)->get(['id', 'name', 'iso2'])->map(
            fn (Country $c) => ['id' => $c->id, 'name' => $c->name, 'iso2' => $c->iso2]
        );

        return response()->json([
            'success' => true,
            'message' => 'ok',
            'data' => ['countries' => $countries],
        ]);
    }

    /**
     * The (up to 3) selectable levels for a country, with their localized
     * display labels. Used to drive the dynamic field labels in the UI.
     */
    public function levels(Country $country, Request $request): JsonResponse
    {
        if (! $country->status) {
            return $this->geoError('geo.invalid_country');
        }

        $labels = GeoHierarchy::levelLabels($country);
        $levels = $country->selectableLevels()->orderBy('level_number')->get()->map(
            fn ($level) => [
                'level_number' => $level->level_number,
                'name' => $level->name,
                'label' => $labels[$level->level_number] ?? $level->name,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'country_id' => $country->id,
                'iso2' => $country->iso2,
                'levels' => $levels,
                'labels' => $labels,
            ],
        ]);
    }

    /**
     * Units of one level, filtered by parent (level >= 2) and a search term.
     *
     * Hierarchy is enforced server-side: level-2/3 units are only returned
     * under a parent that belongs to the same country and to the previous
     * level. Invalid combinations return HTTP 422.
     */
    public function units(Request $request): JsonResponse
    {
        $countryId = (int) $request->query('country_id');
        $level = (int) $request->query('level', 1);
        $parentId = $request->query('parent_id') !== null && $request->query('parent_id') !== ''
            ? (int) $request->query('parent_id')
            : null;
        $q = trim((string) $request->query('q'));

        if ($level < 1 || $level > 3) {
            return $this->geoError('geo.invalid_level');
        }

        $country = Country::whereKey($countryId)->where('status', true)->first();
        if (! $country) {
            return $this->geoError('geo.invalid_country');
        }

        $query = AdministrativeUnit::query()
            ->where('country_id', $countryId)
            ->where('status', true)
            ->whereHas('level', fn ($l) => $l->where('level_number', $level));

        if ($level === 1) {
            $query->whereNull('parent_id');
        } else {
            if ($parentId === null) {
                return $this->geoError('geo.parent_required');
            }

            // The parent must be a real unit of the *previous* level in this country.
            $parent = AdministrativeUnit::query()
                ->whereKey($parentId)
                ->where('country_id', $countryId)
                ->where('status', true)
                ->whereHas('level', fn ($l) => $l->where('level_number', $level - 1))
                ->first();

            if (! $parent) {
                return $this->geoError('geo.invalid_parent');
            }

            $query->where('parent_id', $parentId);
        }

        if ($q !== '') {
            $query->where('name', 'like', '%'.$q.'%');
        }

        // Server-side search + cap: never push the whole dataset to the browser.
        $units = $query->orderBy('name')->limit(200)->get()
            ->map(fn (AdministrativeUnit $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'postal_code' => $u->postal_code,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'country_id' => $countryId,
                'level' => $level,
                'parent_id' => $parentId,
                'units' => $units,
            ],
        ]);
    }

    /** Resolve unit names for display (show pages), with hierarchy checks. */
    public function resolve(Request $request): JsonResponse
    {
        $ids = array_values(array_filter(array_map('intval', explode(',', (string) $request->query('ids')))));
        if ($ids === []) {
            return $this->geoError('geo.invalid_ids');
        }

        $units = AdministrativeUnit::query()
            ->whereIn('id', $ids)
            ->where('status', true)
            ->get(['id', 'name', 'country_id', 'administrative_level_id']);

        $nameById = $units->pluck('name', 'id');
        $countryById = $units->pluck('country_id', 'id');

        return response()->json([
            'success' => true,
            'message' => 'ok',
            'data' => [
                'names' => $nameById,
                'country_ids' => $countryById,
            ],
        ]);
    }

    private function geoError(string $key): JsonResponse
    {
        $msg = mawa_lang('geo.'.$key);

        return response()->json([
            'success' => false,
            'message' => $msg,
            'data' => [],
            'errors' => ['geo' => [$msg]],
        ], 422);
    }
}
