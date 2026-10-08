<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use App\Services\Pricing\CountryPriceService;
use App\Services\Pricing\IndustryPricingCardsService;
use App\Support\PackageGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Mandatory package selection during account creation.
 *
 * Reached from RegistrationFlowController::finalizeRegistration() and from the
 * EnsurePackageSelected middleware, which keeps the dashboard locked until an
 * organization has a package.
 */
class PackageSelectionController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $institute = $this->resolveInstitute($request);
        if ($institute === null) {
            return $this->noWorkspace($request);
        }

        $country = $this->countryIso($institute);
        $cards = $this->cards($institute, $country);
        $prices = $this->pricing()->priceListFor(
            array_map(static fn (array $card): int => (int) $card['package']->id, $cards),
            $country
        );
        $currency = CountryPriceService::FALLBACK_CURRENCY;
        $firstPrice = collect($prices)->first();
        if (is_array($firstPrice) && ! empty($firstPrice['currency'])) {
            $currency = (string) $firstPrice['currency'];
        }

        return view('auth.register-package', [
            'institute' => $institute,
            'cards' => $cards,
            'prices' => $prices,
            'currency' => $currency,
            'country' => $country,
            'currentPackageId' => $institute->package_id,
            'isStaff' => $request->user('institute_user') instanceof InstituteUser,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->resolveInstitute($request);
        if ($institute === null) {
            return $this->noWorkspace($request);
        }

        $data = $request->validate([
            'package_id' => ['required', 'integer', 'exists:subscription_packages,id'],
        ]);

        $package = SubscriptionPackage::query()
            ->whereKey($data['package_id'])
            ->where('status', 'active')
            ->first();

        if ($package === null) {
            throw ValidationException::withMessages([
                'package_id' => 'Selected package is not available.',
            ]);
        }

        $previousPackageId = $institute->package_id;
        $wasLocked = $previousPackageId === null;

        DB::transaction(function () use ($institute, $package, $previousPackageId, $request): void {
            $institute->forceFill(['package_id' => $package->id])->save();

            app(ModuleAccessService::class)->changePackage(
                $institute,
                $previousPackageId,
                $package->id,
                $request->user('web')?->id ?? $request->user('institute_user')?->id
            );
            app(ModuleAccessService::class)->ensureScopeExistsForInstitute($institute);
            app(ModuleAccessService::class)->flushCache($institute->id);
        });

        if (! $wasLocked) {
            return redirect()
                ->route('dashboard')
                ->with('status', 'Package updated to '.$package->name.'.');
        }

        if (strtolower((string) $institute->industry) === 'education') {
            return redirect()
                ->route('register.education.placeholder')
                ->with('status', $package->name.' package selected for '.($institute->name ?? 'your organization').'.');
        }

        return redirect()
            ->route('dashboard')
            ->with('status', $package->name.' package selected for '.($institute->name ?? 'your organization').'.');
    }

    private function resolveInstitute(Request $request): ?Institute
    {
        return PackageGate::resolveInstitute($request);
    }

    /**
     * Authenticated but no organization yet — resume organization creation
     * instead of bouncing between the package page and the dashboard.
     */
    private function noWorkspace(Request $request): RedirectResponse
    {
        if ($request->user('institute_user') !== null) {
            return redirect()->route('login');
        }

        return redirect()->route('workspace.onboarding');
    }

    /**
     * Industry-scoped, country-priced cards (same source the landing page and
     * the admin showcase use), with a plain fallback when the industry has no
     * configured mapping yet.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cards(Institute $institute, string $country): array
    {
        $industry = (string) ($institute->industry ?? '');

        try {
            $cards = $industry === ''
                ? []
                : app(IndustryPricingCardsService::class)->cards($industry, $country, true);
        } catch (\Throwable) {
            $cards = [];
        }

        if ($cards !== []) {
            return $cards;
        }

        return SubscriptionPackage::query()
            ->where('status', 'active')
            ->orderBy('price_monthly')
            ->orderBy('id')
            ->get()
            ->map(static function (SubscriptionPackage $package): array {
                return [
                    'package' => (object) [
                        'id' => $package->id,
                        'name' => $package->name,
                        'slug' => $package->slug,
                        'max_students' => $package->max_students,
                        'max_teachers' => $package->max_teachers,
                        'max_courses' => $package->max_courses,
                    ],
                    'tier' => 0,
                    'base_monthly' => (float) $package->price_monthly,
                    'base_yearly' => (float) $package->price_yearly,
                    'monthly' => (float) $package->price_monthly,
                    'yearly' => (float) $package->price_yearly,
                    'discount_percent' => null,
                    'discount_ends_at' => null,
                    'discount_days_left' => null,
                    'discount_active' => false,
                    'trial_days' => null,
                    'module_count' => $package->packageModules()->where('enabled', true)->count(),
                    'feature_count' => $package->packageFeatures()->where('enabled', true)->count(),
                ];
            })
            ->all();
    }

    private function countryIso(Institute $institute): string
    {
        if (! empty($institute->country_id)) {
            $iso2 = DB::table('countries')
                ->where('id', $institute->country_id)
                ->value('iso2');
            if (is_string($iso2) && preg_match('/^[A-Za-z]{2}$/', $iso2)) {
                return strtoupper($iso2);
            }
        }

        return $this->pricing()->resolveCountry();
    }

    private function pricing(): CountryPriceService
    {
        return app(CountryPriceService::class);
    }
}
