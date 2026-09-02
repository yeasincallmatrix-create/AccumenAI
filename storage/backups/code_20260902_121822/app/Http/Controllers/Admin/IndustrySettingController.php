<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IndustrySetting;
use App\Models\Theme;
use App\Support\IndustryRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IndustrySettingController extends Controller
{
    public function index(Request $request): View
    {
        $industries = IndustryRules::industries(null);

        $selectedKey = $request->query('industry');
        if ($selectedKey === null || $selectedKey === '' || ! array_key_exists($selectedKey, $industries)) {
            $selectedKey = 'all';
        }

        $selectedLabel = $selectedKey === 'all'
            ? 'All Industries'
            : $industries[$selectedKey];

        $country = $request->query('country');
        $country = is_string($country) && array_key_exists($country, config('countries', [])) ? $country : null;

        $subIndustries = $selectedKey === 'all'
            ? []
            : IndustryRules::subIndustries($country ?? '', $selectedKey);

        $subIndustry = $request->query('sub_industry');
        $subIndustry = is_string($subIndustry)
            && $selectedKey !== 'all'
            && array_key_exists($subIndustry, $subIndustries)
            ? $subIndustry
            : null;

        return view('admin.industry-settings.index', [
            'industries' => $industries,
            'selectedKey' => $selectedKey,
            'selectedLabel' => $selectedLabel,
            'country' => $country,
            'subIndustry' => $subIndustry,
            'subIndustries' => $subIndustries,
            'themes' => Theme::query()->where('status', 'active')->orderByDesc('is_default')->orderBy('name')->get(),
            'allThemes' => Theme::query()->orderByDesc('is_default')->orderBy('name')->get(),
            'setting' => IndustrySetting::query()->where('industry_key', $selectedKey)->first(),
        ]);
    }

    public function updateTheme(Request $request): RedirectResponse
    {
        $industries = IndustryRules::industries(null);

        $data = $request->validate([
            'industry_key' => ['required', 'string', 'in:'.implode(',', array_merge(['all'], array_keys($industries)))],
            'theme_slug' => ['required', 'string'],
        ]);

        $theme = Theme::query()
            ->where('status', 'active')
            ->where('slug', $data['theme_slug'])
            ->first();

        if ($theme === null) {
            return back()->withErrors(['theme_slug' => 'The selected theme is not available.']);
        }

        IndustrySetting::updateOrCreate(
            ['industry_key' => $data['industry_key']],
            ['theme_slug' => $theme->slug]
        );

        $url = $data['industry_key'] === 'all'
            ? route('admin.industry-settings')
            : route('admin.industry-settings', ['industry' => $data['industry_key']]);

        return redirect($url)->with('status', "Default theme set for {$data['industry_key']}.");
    }
}
