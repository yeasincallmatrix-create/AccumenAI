<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Country;
use App\Models\HomePage;
use App\Models\PlatformAuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class HomePageController extends Controller
{
    public function index(): View
    {
        $pages = HomePage::with('countries')->latest()->get();
        $countries = Country::where('status', true)->orderBy('name')->get();

        return view('admin.home-pages.index', compact('pages', 'countries'));
    }

    public function create(): View
    {
        $templates = HomePage::availableTemplates();
        $countries = Country::where('status', true)->orderBy('name')->get();

        return view('admin.home-pages.create', compact('templates', 'countries'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'slug'           => ['required', 'string', 'max:60', 'unique:home_pages,slug'],
            'description'    => ['nullable', 'string', 'max:1000'],
            'hero_title'     => ['required', 'string', 'max:255'],
            'hero_subtitle'  => ['nullable', 'string', 'max:1000'],
            'hero_badge'     => ['nullable', 'string', 'max:255'],
            'hero_cta_text'  => ['nullable', 'string', 'max:100'],
            'hero_cta_url'   => ['nullable', 'string', 'max:500'],
            'hero_image_url' => ['nullable', 'string', 'max:500'],
            'sections_json'  => ['nullable', 'string'],
            'is_active'      => ['nullable'],
            'is_global'      => ['nullable'],
            'countries'      => ['nullable', 'array'],
            'countries.*'    => ['exists:countries,id'],
        ]);

        $data['is_active']      = $request->boolean('is_active');
        $data['is_global']      = $request->boolean('is_global');
        $data['hero_cta_url']   = $data['hero_cta_url'] ?? route('owner.register');

        if (! empty($data['sections_json'])) {
            $decoded = json_decode($data['sections_json'], true);
            $data['sections_json'] = $decoded ?? null;
        } else {
            unset($data['sections_json']);
        }

        $countryIds = $data['countries'] ?? [];
        unset($data['countries']);

        $page = HomePage::create($data);

        if (! empty($countryIds)) {
            $page->countries()->sync($countryIds);
        }

        PlatformAuditLog::record('home-pages', 'home_page', "created: {$page->name}");

        return redirect()->route('admin.home-pages.index')
            ->with('status', "Home page \"{$page->name}\" created successfully.");
    }

    public function edit(HomePage $homePage): View
    {
        $homePage->load('countries');
        $templates = HomePage::availableTemplates();
        $countries = Country::where('status', true)->orderBy('name')->get();
        $assignedCountryIds = $homePage->countries->pluck('id')->toArray();

        return view('admin.home-pages.edit', compact('homePage', 'templates', 'countries', 'assignedCountryIds'));
    }

    public function update(Request $request, HomePage $homePage): RedirectResponse
    {
        $data = $request->validate([
            'name'           => ['required', 'string', 'max:100'],
            'slug'           => ['required', 'string', 'max:60', 'unique:home_pages,slug,' . $homePage->id],
            'description'    => ['nullable', 'string', 'max:1000'],
            'hero_title'     => ['required', 'string', 'max:255'],
            'hero_subtitle'  => ['nullable', 'string', 'max:1000'],
            'hero_badge'     => ['nullable', 'string', 'max:255'],
            'hero_cta_text'  => ['nullable', 'string', 'max:100'],
            'hero_cta_url'   => ['nullable', 'string', 'max:500'],
            'hero_image_url' => ['nullable', 'string', 'max:500'],
            'sections_json'  => ['nullable', 'string'],
            'is_active'      => ['nullable'],
            'is_global'      => ['nullable'],
            'countries'      => ['nullable', 'array'],
            'countries.*'    => ['exists:countries,id'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['is_global'] = $request->boolean('is_global');

        if (! empty($data['sections_json'])) {
            $decoded = json_decode($data['sections_json'], true);
            $data['sections_json'] = $decoded ?? null;
        } else {
            $data['sections_json'] = null;
        }

        $countryIds = $data['countries'] ?? [];
        unset($data['countries']);

        $homePage->update($data);
        $homePage->countries()->sync($countryIds);

        PlatformAuditLog::record('home-pages', 'home_page', "updated: {$homePage->name}");

        return redirect()->route('admin.home-pages.index')
            ->with('status', "Home page \"{$homePage->name}\" updated successfully.");
    }

    public function destroy(HomePage $homePage): RedirectResponse
    {
        $name = $homePage->name;
        $homePage->countries()->detach();
        $homePage->delete();

        PlatformAuditLog::record('home-pages', 'home_page', "deleted: {$name}");

        return redirect()->route('admin.home-pages.index')
            ->with('status', "Home page \"{$name}\" deleted.");
    }

    public function assignCountries(Request $request, HomePage $homePage): RedirectResponse
    {
        $data = $request->validate([
            'countries'   => ['nullable', 'array'],
            'countries.*' => ['exists:countries,id'],
        ]);

        $homePage->countries()->sync($data['countries'] ?? []);

        PlatformAuditLog::record('home-pages', 'home_page_countries', "updated for: {$homePage->name}");

        return back()->with('status', 'Country assignments updated.');
    }

    public function toggleActive(HomePage $homePage): RedirectResponse
    {
        $homePage->update(['is_active' => ! $homePage->is_active]);

        $state = $homePage->is_active ? 'activated' : 'deactivated';
        PlatformAuditLog::record('home-pages', 'home_page', "{$state}: {$homePage->name}");

        return back()->with('status', "Home page \"{$homePage->name}\" {$state}.");
    }

    public function setDefault(HomePage $homePage): RedirectResponse
    {
        HomePage::query()->update(['is_global' => false]);
        $homePage->update(['is_global' => true]);

        PlatformAuditLog::record('home-pages', 'home_page', "global default set: {$homePage->name}");

        return back()->with('status', "\"{$homePage->name}\" is now the global default home page.");
    }

    public function preview(HomePage $homePage): View
    {
        return view($homePage->viewPath(), ['homePage' => $homePage]);
    }
}
