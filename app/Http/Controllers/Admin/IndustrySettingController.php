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
    public function index(Request $request)
    {
        // Abolished as a standalone page — served as a pane inside
        // Configuration Center. Query params are carried over.
        $url = route('admin.platform-settings.index', array_filter(
            $request->only(['industry', 'country', 'sub_industry']),
            fn ($v) => $v !== null && $v !== ''
        )).'#pane-industry';

        abort(redirect($url, 301));
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

        $url = route('admin.platform-settings.index', $data['industry_key'] === 'all'
            ? []
            : ['industry' => $data['industry_key']]).'#pane-industry';

        return redirect($url)->with('status', "Default theme set for {$data['industry_key']}.");
    }
}
