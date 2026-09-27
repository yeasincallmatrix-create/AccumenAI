<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PackageCountryPriceSeeder extends Seeder
{
    /**
     * Country multipliers relative to BD base pricing.
     * Round to nearest 10 for clean numbers.
     *
     * Adjust these as needed based on purchasing power parity.
     */
    private array $multipliers = [
        'BD' => 1.00,   // Base
        'IN' => 0.75,   // India — 75% of BD
        'PK' => 0.85,   // Pakistan — 85%
        'LK' => 0.90,   // Sri Lanka — 90%
        'NP' => 0.70,   // Nepal — 70%
        'BT' => 0.75,   // Bhutan — 75% (pegged INR)
        'MV' => 1.30,   // Maldives — 130% (higher GDP/capita)
    ];

    /**
     * Explicit fallback currency per country (used only if
     * country_currency_map has no row for the country).
     */
    private array $fallbackCurrencies = [
        'BD' => 'BDT',
        'IN' => 'INR',
        'PK' => 'PKR',
        'LK' => 'LKR',
        'NP' => 'NPR',
        'BT' => 'BTN',
        'MV' => 'MVR',
    ];

    public function run(): void
    {
        $this->command->info('PackageCountryPriceSeeder started...');

        // Self-sufficient: iterate multiplier keys directly.
        // Does NOT depend on the countries table being populated.
        $countries = array_keys($this->multipliers);

        $currencyMap = DB::table('country_currency_map')
            ->whereIn('country_code', $countries)
            ->pluck('currency_code', 'country_code')
            ->toArray();

        $packages = DB::table('subscription_packages')->get();
        $inserted = 0;
        $updated = 0;

        foreach ($packages as $pkg) {
            $baseMonthly = (float) $pkg->price_monthly;
            $baseYearly = (float) $pkg->price_yearly;

            foreach ($countries as $iso2) {
                $currency = $currencyMap[$iso2] ?? ($this->fallbackCurrencies[$iso2] ?? 'USD');
                $mult = $this->multipliers[$iso2] ?? 1.0;

                $monthly = $this->roundPrice($baseMonthly * $mult);
                $yearly = $this->roundPrice($baseYearly * $mult);

                $exists = DB::table('package_country_prices')
                    ->where('package_id', $pkg->id)
                    ->where('country_code', $iso2)
                    ->exists();

                if ($exists) {
                    DB::table('package_country_prices')
                        ->where('package_id', $pkg->id)
                        ->where('country_code', $iso2)
                        ->update([
                            'currency_code' => $currency,
                            'price_monthly' => $monthly,
                            'price_yearly' => $yearly,
                            'is_active' => true,
                            'updated_at' => now(),
                        ]);
                    $updated++;
                } else {
                    DB::table('package_country_prices')->insert([
                        'package_id' => $pkg->id,
                        'country_code' => $iso2,
                        'currency_code' => $currency,
                        'price_monthly' => $monthly,
                        'price_yearly' => $yearly,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $inserted++;
                }
            }
        }

        $this->command->info("PackageCountryPriceSeeder complete: {$inserted} inserted, {$updated} updated.");
    }

    /**
     * Round to nearest 10 (for amounts >= 100)
     * Round to nearest 100 (for amounts >= 10000)
     */
    private function roundPrice(float $amount): float
    {
        if ($amount >= 10000) {
            return round($amount / 100) * 100;
        }
        if ($amount >= 100) {
            return round($amount / 10) * 10;
        }
        return round($amount);
    }
}
