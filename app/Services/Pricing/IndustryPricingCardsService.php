<?php

namespace App\Services\Pricing;

use Illuminate\Support\Facades\DB;

/**
 * Effective pricing cards for one industry.
 *
 * Resolution order per package:
 *   package_industries override → package_country_prices (country) →
 *   country/industry discount → trial days.
 *
 * Shared by the admin Package-by-Industry showcase and the public landing
 * page so both always show identical numbers.
 *
 * NOTE: package_industries / package_country_prices are GLOBAL tables
 * (no institute_id) — TenantScoped must NOT be applied here.
 */
class IndustryPricingCardsService
{
    /**
     * Tier ordering: FREE → starter → growth → enterprise.
     */
    public function tierOf(string $slug): ?int
    {
        if ($slug === 'free') {
            return 0;
        }

        foreach (['starter' => 1, 'growth' => 2, 'enterprise' => 3] as $tier => $order) {
            if (str_ends_with($slug, '_'.$tier)) {
                return $order;
            }
        }

        return null;
    }

    /**
     * @param  string  $country  ISO2 country for package_country_prices ('' = industry default)
     * @param  bool  $includeFree  Append the universal FREE fallback card
     * @return array<int, array<string, mixed>>
     */
    public function cards(string $industry, string $country = '', bool $includeFree = true): array
    {
        $mapping = DB::table('package_industries')
            ->where('industry_key', $industry)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get()
            ->keyBy('package_id');

        $packages = DB::table('subscription_packages')
            ->where('status', 'active')
            ->whereIn('id', $mapping->keys()->all())
            ->get()
            ->keyBy('id');

        $cards = [];
        foreach ($mapping as $packageId => $map) {
            $package = $packages->get($packageId);
            if (! $package) {
                continue;
            }
            $tier = $this->tierOf((string) $package->slug);
            if ($tier === null || ($tier === 0 && ! $includeFree)) {
                continue;
            }

            $baseMonthly = $map->price_monthly !== null ? (float) $map->price_monthly : (float) $package->price_monthly;
            $baseYearly = $map->price_yearly !== null ? (float) $map->price_yearly : (float) $package->price_yearly;
            $trialDays = isset($map->trial_days) && $map->trial_days !== null ? (int) $map->trial_days : null;

            $countryPrice = null;
            if ($country !== '') {
                $countryPrice = DB::table('package_country_prices')
                    ->where('package_id', $packageId)
                    ->where('country_code', $country)
                    ->where('is_active', true)
                    ->first();
                if ($countryPrice) {
                    if ($countryPrice->price_monthly !== null) {
                        $baseMonthly = (float) $countryPrice->price_monthly;
                    }
                    if ($countryPrice->price_yearly !== null) {
                        $baseYearly = (float) $countryPrice->price_yearly;
                    }
                    if (isset($countryPrice->trial_days) && $countryPrice->trial_days !== null) {
                        $trialDays = (int) $countryPrice->trial_days;
                    }
                }
            }

            // Country discount wins over the industry discount.
            $discountSource = $map;
            if ($countryPrice && isset($countryPrice->discount_percent) && $countryPrice->discount_percent !== null
                && (float) $countryPrice->discount_percent > 0) {
                $discountSource = $countryPrice;
            }
            $discount = $this->discountState($discountSource);
            $pct = $discount['percent'];
            $effectiveMonthly = $baseMonthly;
            $effectiveYearly = $baseYearly;
            if ($discount['active'] && $pct !== null && $pct > 0) {
                $factor = max(0, 1 - $pct / 100);
                $effectiveMonthly = round($baseMonthly * $factor, 2);
                $effectiveYearly = round($baseYearly * $factor, 2);
            }

            $moduleCount = DB::table('package_industry_modules')
                ->where('package_id', $packageId)
                ->where('industry_key', $industry)
                ->where('enabled', true)
                ->count();
            if ($moduleCount === 0) {
                $moduleCount = DB::table('package_modules')
                    ->where('package_id', $packageId)
                    ->where('enabled', true)
                    ->count();
            }
            $featureCount = DB::table('package_features')
                ->where('package_id', $packageId)
                ->where('enabled', true)
                ->count();

            $cards[] = [
                'package' => $package,
                'tier' => $tier,
                'base_monthly' => $baseMonthly,
                'base_yearly' => $baseYearly,
                'monthly' => $effectiveMonthly,
                'yearly' => $effectiveYearly,
                'discount_percent' => $pct,
                'discount_ends_at' => $discount['ends_at'],
                'discount_days_left' => $discount['days_left'],
                'discount_active' => $discount['active'],
                'trial_days' => $trialDays,
                'module_count' => $moduleCount,
                'feature_count' => $featureCount,
            ];
        }

        usort($cards, static fn ($a, $b) => $a['tier'] <=> $b['tier']);
        $cards = array_values($cards);

        // FREE is the universal fallback — always shown as the first card
        // even when the industry has no explicit free mapping.
        if ($includeFree && ! collect($cards)->contains(static fn ($c) => $c['tier'] === 0)) {
            $free = DB::table('subscription_packages')
                ->where('slug', 'free')
                ->where('status', 'active')
                ->first();
            if ($free) {
                $freeMonthly = (float) $free->price_monthly;
                $freeYearly = (float) $free->price_yearly;
                if ($country !== '') {
                    $countryPrice = DB::table('package_country_prices')
                        ->where('package_id', $free->id)
                        ->where('country_code', $country)
                        ->where('is_active', true)
                        ->first();
                    if ($countryPrice) {
                        if ($countryPrice->price_monthly !== null) {
                            $freeMonthly = (float) $countryPrice->price_monthly;
                        }
                        if ($countryPrice->price_yearly !== null) {
                            $freeYearly = (float) $countryPrice->price_yearly;
                        }
                    }
                }
                array_unshift($cards, [
                    'package' => $free,
                    'tier' => 0,
                    'base_monthly' => $freeMonthly,
                    'base_yearly' => $freeYearly,
                    'monthly' => $freeMonthly,
                    'yearly' => $freeYearly,
                    'discount_percent' => null,
                    'discount_ends_at' => null,
                    'discount_days_left' => null,
                    'discount_active' => false,
                    'trial_days' => null,
                    'module_count' => DB::table('package_modules')
                        ->where('package_id', $free->id)
                        ->where('enabled', true)
                        ->count(),
                    'feature_count' => DB::table('package_features')
                        ->where('package_id', $free->id)
                        ->where('enabled', true)
                        ->count(),
                ]);
            }
        }

        return $cards;
    }

    /**
     * Discount state for one package_industries / package_country_prices row.
     *
     * @return array{percent: ?float, ends_at: ?string, days_left: ?int, active: bool}
     */
    public function discountState(mixed $map): array
    {
        $percent = $map !== null && $map->discount_percent !== null ? (float) $map->discount_percent : null;
        $endsAt = $map !== null && $map->discount_ends_at ? (string) $map->discount_ends_at : null;

        if ($percent === null || $percent <= 0) {
            return ['percent' => $percent, 'ends_at' => $endsAt, 'days_left' => null, 'active' => false];
        }

        if ($endsAt === null) {
            return ['percent' => $percent, 'ends_at' => null, 'days_left' => null, 'active' => true];
        }

        $daysLeft = (int) floor((strtotime($endsAt) - strtotime(date('Y-m-d'))) / 86400);

        return [
            'percent' => $percent,
            'ends_at' => $endsAt,
            'days_left' => $daysLeft,
            'active' => $daysLeft >= 0,
        ];
    }
}
