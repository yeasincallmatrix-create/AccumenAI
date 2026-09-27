<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EuPricingTest extends TestCase
{
    private array $euCountries = [
        'IT' => 'EUR', 'ES' => 'EUR', 'PL' => 'PLN', 'SE' => 'SEK', 'BE' => 'EUR',
        'AT' => 'EUR', 'DK' => 'DKK', 'FI' => 'EUR', 'IE' => 'EUR', 'PT' => 'EUR',
        'GR' => 'EUR', 'CZ' => 'CZK', 'RO' => 'RON', 'HU' => 'HUF',
    ];

    public function test_total_rows_1000(): void
    {
        $this->assertEquals(1000, DB::table('package_country_prices')->count());
    }

    public function test_eu_countries_present(): void
    {
        foreach ($this->euCountries as $iso2 => $currency) {
            $count = DB::table('package_country_prices')
                ->where('country_code', $iso2)->count();
            $this->assertEquals(25, $count, "{$iso2} should have 25 rows");
        }
    }

    public function test_eu_currencies_correct(): void
    {
        foreach ($this->euCountries as $iso2 => $currency) {
            $actual = DB::table('package_country_prices')
                ->where('country_code', $iso2)->value('currency_code');
            $this->assertEquals($currency, $actual, "{$iso2} currency mismatch");
        }
    }

    public function test_saarc_gulf_sea_firstworld_unchanged(): void
    {
        // SAARC (175) + Gulf (150) + SEA (125) + FirstWorld (200) = 650
        $existing = DB::table('package_country_prices')
            ->whereIn('country_code', [
                'BD', 'IN', 'PK', 'LK', 'NP', 'BT', 'MV',
                'AE', 'SA', 'QA', 'KW', 'BH', 'OM',
                'MY', 'TH', 'ID', 'PH', 'VN',
                'US', 'GB', 'CA', 'AU', 'DE', 'FR', 'SG', 'NL',
            ])->count();
        $this->assertEquals(650, $existing);
    }
}
