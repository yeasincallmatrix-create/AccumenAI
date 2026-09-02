<?php

namespace App\Http\Controllers;

use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Models\IndustrySetting;
use App\Models\Institute;
use App\Models\Role;
use App\Models\Theme;
use App\Models\User;
use App\Services\Demo\DemoDataService;
use App\Services\MembershipService;
use App\Support\GeoHierarchy;
use App\Support\IndustryRules;
use App\Support\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Institute creation + owner onboarding.
 *
 * An authenticated owner creates a new organization. The existing global User
 * account is reused (never duplicated) and a fresh institute-owner membership
 * is created inside a transaction. The active workspace is switched to the new
 * organization so the owner lands directly in it.
 *
 * Step 1 (InstituteOnboardingController) picks the country + industry +
 * sub-industry; step 2 here only collects the organization details. The
 * country/industry trio is read from the session — never from the browser —
 * and cleared once the institute is created.
 */
class InstituteCreationController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isOwnerAccount(), 403);

        $selection = InstituteOnboardingController::selection();
        if ($selection === null) {
            return redirect()->route('workspace.onboarding');
        }

        $preview = $this->previewDefaultStructure($selection);

        return view('workspace.create', [
            'selection' => $selection,
            'countryLabel' => config('countries.'.$selection['country'], $selection['country']),
            'industryLabel' => IndustryRules::label($selection['country'], $selection['industry']) ?? $selection['industry'],
            'subIndustryLabel' => $selection['sub_industry'] !== null
                ? (IndustryRules::subIndustries($selection['country'], $selection['industry'])[$selection['sub_industry']] ?? $selection['sub_industry'])
                : null,
            'themePrimary' => $this->industryThemeColor($selection['industry'], 'primary_color'),
            'themeSecondary' => $this->industryThemeColor($selection['industry'], 'secondary_color'),
            'geoAddress' => $this->geoAddress($selection['country']),
            'defaultTemplate' => $preview['template'],
            'defaultLevels' => $preview['levels'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isOwnerAccount(), 403);

        $selection = InstituteOnboardingController::selection();
        if ($selection === null) {
            return redirect()->route('workspace.onboarding');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'regex:/^\+?\d{4,20}$/'],
            'email' => ['nullable', 'email', 'max:150'],
            'country_id' => ['nullable', 'integer'],
            'admin_1_id' => ['nullable', 'integer'],
            'admin_2_id' => ['nullable', 'integer'],
            'admin_3_id' => ['nullable', 'integer'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
        ]);

        $geoAddress = $this->geoAddress($selection['country']);

        if ($geoAddress !== null) {
            // The country is locked from step 1 — if the client sends a country_id it must stay inside it.
            // Missing country_id is allowed (legacy tests / simple onboarding) — we still store the selection's country.
            if (array_key_exists('country_id', $data) && $data['country_id'] !== null && $data['country_id'] !== '' && (int) $data['country_id'] !== (int) $geoAddress['country_id']) {
                throw ValidationException::withMessages([
                    'country_id' => 'The address country must match the selected organization country.',
                ]);
            }

            $error = GeoHierarchy::validateHierarchy(
                (int) $geoAddress['country_id'],
                $data['admin_1_id'] ?? null,
                $data['admin_2_id'] ?? null,
                $data['admin_3_id'] ?? null,
            );

            if ($error !== null) {
                throw ValidationException::withMessages([
                    'admin_1_id' => mawa_lang($error),
                ]);
            }
        }

        $ownerRoleId = Role::query()->where('slug', 'institute-owner')->value('id');
        abort_unless($ownerRoleId !== null, 422, 'The institute-owner role is not configured.');

        $institute = DB::transaction(function () use ($user, $data, $selection, $ownerRoleId, $geoAddress) {
            $institute = Institute::create([
                'name' => $data['name'],
                'slug' => $this->uniqueSlug($data['name']),
                'industry' => $selection['industry'],
                'sub_industry' => $selection['sub_industry'],
                'country' => $selection['country'],
                'country_id' => $geoAddress['country_id'] ?? null,
                'admin_level_1_id' => $data['admin_1_id'] ?? null,
                'admin_level_2_id' => $data['admin_2_id'] ?? null,
                'admin_level_3_id' => $data['admin_3_id'] ?? null,
                'postal_code' => $data['zip_code'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'status' => 'active',
            ]);

            $this->syncLegacyLocationFields($institute, $data);

            app(MembershipService::class)->assign($user, $institute->id, $ownerRoleId, [
                'branch_id' => null,
                'status' => 'active',
            ]);

            // Default certificate approval to Admin Controlled (new institutes)
            \App\Models\InstituteSetting::withoutGlobalScope('institute')->updateOrCreate(
                ['institute_id' => $institute->id],
                ['certificate_approval_mode' => \App\Models\InstituteSetting::CERTIFICATE_APPROVAL_ADMIN]
            );

            return $institute;
        });

        InstituteOnboardingController::clear();

        Workspace::set($institute->id);

        // Phase 4: auto-assign default learning structure template (country+industry+sub)
        try {
            $this->assignDefaultLearningStructure($institute);
        } catch (\Throwable $e) {
            Log::warning('InstituteCreation: learning structure assignment failed', [
                'institute_id' => $institute->id,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }

        // Academic defaults (year + global grade scale) — idempotent, never blocks creation
        try {
            app(\App\Services\AcademicSetupService::class)->ensureDefaults($institute);
        } catch (\Throwable $e) {
            Log::warning('InstituteCreation: academic defaults failed', [
                'institute_id' => $institute->id,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }

        // Auto demo seeding disabled (undistructive mode) - keeps existing institutes/logins clean.
        // To seed manually: app(DemoDataService::class)->seed($institute, $user, ['force' => true])
        try {
            app(DemoDataService::class)->seed($institute, $user, ['force' => false]);
        } catch (\Throwable $e) {
            Log::warning('InstituteCreation: demo seeding failed', [
                'institute_id' => $institute->id,
                'error' => $e->getMessage(),
            ]);
            report($e);
        }

        return redirect()
            ->route('dashboard')
            ->with('status', mawa_lang('workspace.created', ['name' => $institute->name]));
    }

    /**
     * Data for the <x-address> selector on the create form. The country is
     * locked (already chosen in step 1), so only its level labels and the
     * top-level (level 1) options are pre-rendered; deeper levels load via the
     * geo AJAX endpoints as the user cascades down. Returns null when the
     * selected country has no geo records yet — the form then falls back to a
     * plain address input.
     */
    protected function geoAddress(string $countryName): ?array
    {
        $country = Country::query()
            ->where('name', $countryName)
            ->where('status', true)
            ->first();

        if ($country === null) {
            return null;
        }

        $labels = GeoHierarchy::levelLabels($country);

        $level1 = AdministrativeUnit::query()
            ->where('country_id', $country->id)
            ->where('status', true)
            ->whereNull('parent_id')
            ->whereHas('level', fn ($q) => $q->where('level_number', 1))
            ->orderBy('name')
            ->get();

        return [
            'country' => $country,
            'country_id' => $country->id,
            'level_labels' => $labels,
            'level1_options' => $level1->pluck('name', 'id')->all(),
            'address_label' => config('geo-labels.localities.'.$country->iso2, config('geo-labels.defaults.locality', 'Address')),
            'zip_first' => $country->iso2 === 'BD',
        ];
    }

    /**
     * Keep the legacy division/district/upazila free-text columns in sync with
     * the structured administrative-unit ids so admin views that still read the
     * old columns keep showing sensible location names.
     */
    protected function syncLegacyLocationFields(Institute $institute, array $data): void
    {
        $ids = array_filter(array_map('intval', [
            $data['admin_1_id'] ?? null,
            $data['admin_2_id'] ?? null,
            $data['admin_3_id'] ?? null,
        ]));

        if ($ids === []) {
            return;
        }

        $names = AdministrativeUnit::query()
            ->whereIn('id', $ids)
            ->pluck('name', 'id');

        $institute->forceFill([
            'division' => $names->get((int) ($data['admin_1_id'] ?? 0)),
            'district' => $names->get((int) ($data['admin_2_id'] ?? 0)),
            'upazila' => $names->get((int) ($data['admin_3_id'] ?? 0)),
        ])->save();
    }

    /**
     * Theme colors configured for an industry (IndustrySetting, falling back
     * to the "all" default) — null when none, so the theme partial falls back
     * to the platform defaults.
     */
    protected function industryThemeColor(string $industry, string $column): ?string
    {
        $setting = IndustrySetting::query()->where('industry_key', $industry)->first()
            ?? IndustrySetting::query()->where('industry_key', 'all')->first();

        if ($setting === null || ! filled($setting->theme_slug)) {
            return null;
        }

        $theme = Theme::query()->where('slug', $setting->theme_slug)->first();

        $color = $theme?->{$column};
        if ($color === null || ! preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            return null;
        }

        return $color;
    }

    protected function previewDefaultStructure(array $selection): array
    {
        try {
            $dummy = new \App\Models\Institute([
                'country' => $selection['country'],
                'industry' => $selection['industry'],
                'sub_industry' => $selection['sub_industry'],
            ]);
            $country = \App\Models\Country::where('name', $selection['country'])->first();
            if ($country) $dummy->country_id = $country->id;
            $resolved = app(\App\Services\LearningStructureResolver::class)->resolveTemplate($dummy);
            $template = $resolved['template'] ?? null;
            if (! $template) return ['template' => null, 'levels' => []];
            $levels = $template->levels()->orderBy('level_order')->get();
            return ['template' => $template, 'levels' => $levels];
        } catch (\Throwable) {
            return ['template' => null, 'levels' => []];
        }
    }

    /**
     * Phase 4: assign default learning structure template without overwriting explicit one.
     */
    protected function assignDefaultLearningStructure(\App\Models\Institute $institute): void
    {
        $existing = \App\Models\InstituteSetting::withoutGlobalScope('institute')->where('institute_id', $institute->id)->value('structure_template_id');
        if ($existing) return;
        $resolved = app(\App\Services\LearningStructureResolver::class)->resolveTemplate($institute);
        $template = $resolved['template'] ?? null;
        if (! $template) return;
        \App\Models\InstituteSetting::withoutGlobalScope('institute')->updateOrCreate(
            ['institute_id' => $institute->id],
            ['structure_template_id' => $template->id]
        );
    }

    /**
     * Slug unique against every institute row, including soft-deleted ones
     * (the slug column is globally unique, so recycling is never allowed).
     */
    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'institute';
        $slug = $base;
        $suffix = 2;

        while (Institute::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
