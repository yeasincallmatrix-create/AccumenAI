<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdministrativeLevel;
use App\Models\Country;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Super Admin manager for the global administrative-level configuration.
 *
 * This is a definition manager only: it stores what each country calls its
 * three administrative levels (administrative_levels.name). It does not import
 * or manage actual location records — those are provided separately per country.
 *
 * Authorization is enforced by the `auth:platform_admin` route middleware group;
 * PlatformAdmin is an implicit superuser bypass in this application.
 */
class GeoAdminController extends Controller
{
    public function index(Request $request): View
    {
        $query = Country::query()->with(['levels' => fn ($q) => $q->orderBy('level_number')]);

        if ($request->query('q') !== null && trim((string) $request->query('q')) !== '') {
            $query->where('name', 'like', '%'.trim((string) $request->query('q')).'%');
        }

        $countries = $query->orderBy('name')->get();

        return view('admin.geo.index', [
            'countries' => $countries,
            'q' => $request->query('q'),
        ]);
    }

    public function createCountry(): View
    {
        return view('admin.geo.create_country');
    }

    public function storeCountry(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'iso2' => ['required', 'string', 'size:2', 'alpha', 'unique:countries,iso2'],
            'iso3' => ['nullable', 'string', 'size:3', 'alpha', 'unique:countries,iso3'],
            'phone_code' => ['nullable', 'string', 'max:10'],
        ], [
            'iso2.unique' => mawa_lang('admin_geo.duplicate_country'),
            'iso2.required' => mawa_lang('admin_geo.requires_iso2'),
        ]);

        $country = Country::create([
            'name' => $data['name'],
            'iso2' => strtoupper($data['iso2']),
            'iso3' => $data['iso3'] !== null && $data['iso3'] !== ''
                ? strtoupper($data['iso3'])
                : null,
            'phone_code' => $data['phone_code'] ?? null,
            'status' => true,
        ]);

        return redirect(route('admin.geo.edit', $country))->with('status', "Country {$country->name} added. Configure its administrative levels.");
    }

    public function edit(Request $request, Country $country): View
    {
        return view('admin.geo.edit', [
            'country' => $country,
            'levels' => $country->levels()->orderBy('level_number')->get(),
            'unitCount' => $country->units()->where('status', true)->count(),
        ]);
    }

    /**
     * Save the three administrative-level labels for a country.
     *
     * A blank label disables that level for the country (level record set to
     * inactive); a non-blank label upserts the level definition. Duplicate
     * levels are prevented by the DB unique constraint on country_id + level_number.
     */
    public function update(Request $request, Country $country): RedirectResponse
    {
        $data = $request->validate([
            'level_1' => ['nullable', 'string', 'max:80'],
            'level_2' => ['nullable', 'string', 'max:80'],
            'level_3' => ['nullable', 'string', 'max:80'],
        ]);

        foreach ([1, 2, 3] as $levelNumber) {
            $label = isset($data['level_'.$levelNumber])
                ? trim((string) $data['level_'.$levelNumber])
                : '';

            $existing = AdministrativeLevel::query()
                ->where('country_id', $country->id)
                ->where('level_number', $levelNumber)
                ->first();

            if ($label === '') {
                if ($existing !== null) {
                    $existing->forceFill(['status' => false])->save();
                }

                continue;
            }

            if ($existing !== null) {
                $existing->forceFill([
                    'name' => $label,
                    'slug' => $this->slugFor($country, $levelNumber),
                    'status' => true,
                ])->save();
            } else {
                AdministrativeLevel::create([
                    'country_id' => $country->id,
                    'level_number' => $levelNumber,
                    'name' => $label,
                    'slug' => $this->slugFor($country, $levelNumber),
                    'status' => true,
                ]);
            }
        }

        return redirect(route('admin.geo.edit', $country))->with('status', mawa_lang('admin_geo.saved'));
    }

    /**
     * Toggle a country's configuration active/inactive (the whole hierarchy).
     */
    public function toggleStatus(Request $request, Country $country): RedirectResponse
    {
        $country->forceFill(['status' => ! $country->status])->save();

        $state = $country->status ? mawa_lang('admin_geo.enabled') : mawa_lang('admin_geo.disabled');

        return redirect(route('admin.geo.edit', $country))->with('status', "{$country->name} configuration {$state}.");
    }

    private function slugFor(Country $country, int $levelNumber): string
    {
        return strtolower($country->iso2).'_level_'.$levelNumber;
    }
}
