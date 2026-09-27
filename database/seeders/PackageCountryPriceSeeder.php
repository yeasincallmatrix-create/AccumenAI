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
        // Phase 1 — SAARC
        'BD' => 1.00,   // Base
        'IN' => 0.75,   // India — 75% of BD
        'PK' => 0.85,   // Pakistan — 85%
        'LK' => 0.90,   // Sri Lanka — 90%
        'NP' => 0.70,   // Nepal — 70%
        'BT' => 0.75,   // Bhutan — 75% (pegged INR)
        'MV' => 1.30,   // Maldives — 130% (higher GDP/capita)

        // Phase 2 — Gulf
        'AE' => 3.50,   // UAE
        'SA' => 3.00,   // Saudi Arabia
        'QA' => 3.50,   // Qatar
        'KW' => 3.50,   // Kuwait
        'BH' => 3.00,   // Bahrain
        'OM' => 3.00,   // Oman

        // Phase 3 — Southeast Asia
        'MY' => 1.50,   // Malaysia
        'TH' => 1.50,   // Thailand
        'ID' => 1.00,   // Indonesia
        'PH' => 1.00,   // Philippines
        'VN' => 0.80,   // Vietnam

        // Phase 4 — First World
        'US' => 10.00,  // United States
        'GB' => 8.00,   // United Kingdom
        'CA' => 8.00,   // Canada
        'AU' => 8.00,   // Australia
        'DE' => 8.00,   // Germany
        'FR' => 8.00,   // France
        'SG' => 6.00,   // Singapore
        'NL' => 8.00,   // Netherlands

        // Phase 5 — European Union
        'IT' => 7.50,   // Italy
        'ES' => 7.00,   // Spain
        'PL' => 5.00,   // Poland
        'SE' => 8.00,   // Sweden
        'BE' => 8.00,   // Belgium
        'AT' => 8.00,   // Austria
        'DK' => 8.00,   // Denmark
        'FI' => 8.00,   // Finland
        'IE' => 8.00,   // Ireland
        'PT' => 5.50,   // Portugal
        'GR' => 5.00,   // Greece
        'CZ' => 5.00,   // Czech Republic
        'RO' => 3.50,   // Romania
        'HU' => 4.00,   // Hungary
    ];

    /**
     * Explicit fallback currency per country (used only if
     * country_currency_map has no row for the country).
     */
    private array $fallbackCurrencies = [
        // Phase 1 — SAARC
        'BD' => 'BDT',
        'IN' => 'INR',
        'PK' => 'PKR',
        'LK' => 'LKR',
        'NP' => 'NPR',
        'BT' => 'BTN',
        'MV' => 'MVR',

        // Phase 2 — Gulf
        'AE' => 'AED',
        'SA' => 'SAR',
        'QA' => 'QAR',
        'KW' => 'KWD',
        'BH' => 'BHD',
        'OM' => 'OMR',

        // Phase 3 — Southeast Asia
        'MY' => 'MYR',
        'TH' => 'THB',
        'ID' => 'IDR',
        'PH' => 'PHP',
        'VN' => 'VND',

        // Phase 4 — First World
        'US' => 'USD',
        'GB' => 'GBP',
        'CA' => 'CAD',
        'AU' => 'AUD',
        'DE' => 'EUR',
        'FR' => 'EUR',
        'SG' => 'SGD',
        'NL' => 'EUR',

        // Phase 5 — European Union
        'IT' => 'EUR',
        'ES' => 'EUR',
        'PL' => 'PLN',
        'SE' => 'SEK',
        'BE' => 'EUR',
        'AT' => 'EUR',
        'DK' => 'DKK',
        'FI' => 'EUR',
        'IE' => 'EUR',
        'PT' => 'EUR',
        'GR' => 'EUR',
        'CZ' => 'CZK',
        'RO' => 'RON',
        'HU' => 'HUF',
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
