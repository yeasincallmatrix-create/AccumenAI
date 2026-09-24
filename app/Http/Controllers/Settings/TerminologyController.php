<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Services\TerminologyService;
use App\Support\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Phase 5 — Tenant Terminology Overrides.
 *
 * Lets a tenant rename module terms (e.g. Customer → Client) for their own
 * UI. Resolution priority (TermResolver): tenant override > country > global.
 * Only keys seeded into module_terminology can be overridden.
 */
class TerminologyController extends Controller
{
    public function index(): View
    {
        $institute = Institute::findOrFail(TenantContext::id());
        $service = app(TerminologyService::class);

        $rows = DB::table('module_terminology')
            ->where('is_active', true)
            ->orderBy('term_key')
            ->orderBy('country_code')
            ->get();

        $global = $rows->whereNull('country_code')->keyBy('term_key');
        $country = $rows
            ->filter(fn ($r) => $r->country_code === ($institute->country_code ?? 'BD'))
            ->keyBy('term_key');

        $overrides = $institute->terminology_overrides;
        $overrides = is_string($overrides)
            ? (json_decode($overrides, true) ?? [])
            : ($overrides ?? []);

        $terms = [];
        foreach ($global as $key => $row) {
            $countryValue = $country->get($key)?->term_value;
            $terms[$key] = [
                'label' => $row->term_value,
                'global' => $row->term_value,
                'country' => $countryValue,
                'override' => $overrides[$key] ?? null,
                'effective' => $service->get($key, $institute, $row->term_value),
            ];
        }

        return view('settings.terminology.index', compact('institute', 'terms', 'overrides'));
    }

    public function update(Request $request): RedirectResponse
    {
        $institute = Institute::findOrFail(TenantContext::id());

        $validated = $request->validate([
            'terms' => 'nullable|array',
            'terms.*' => 'nullable|string|max:150',
        ]);

        $allowed = DB::table('module_terminology')
            ->where('is_active', true)
            ->pluck('term_key')
            ->unique()
            ->flip();

        $terms = $validated['terms'] ?? [];
        $service = app(TerminologyService::class);
        $saved = 0;

        foreach ($terms as $key => $value) {
            if (! $allowed->has((string) $key)) {
                continue; // unknown key — silently skip (not seedable)
            }

            $value = trim((string) $value);

            if ($value === '') {
                // Empty → drop the override, fall back to country/global.
                $service->removeOverride($institute, (string) $key);
                $saved++;
                continue;
            }

            $service->setOverride($institute, (string) $key, $value);
            $saved++;
        }

        return back()->with('success', "Terminology saved ({$saved} term(s)). New wording applies across this tenant's UI.");
    }
}
