<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 2-4 country expansion: Gulf (6) + Southeast Asia (5) + First World (8).
 *
 * Together with the seven SAARC countries this is 26 countries x 25 packages
 * = 650 rows in `package_country_prices`.
 */
class GlobalPricingTest extends TestCase
{
    /** Phase 2-4 target countries and their required currency. */
    private array $targets = [
        // Phase 2 — Gulf
        'AE' => 'AED', 'SA' => 'SAR', 'QA' => 'QAR', 'KW' => 'KWD',
        'BH' => 'BHD', 'OM' => 'OMR',
        // Phase 3 — Southeast Asia
        'MY' => 'MYR', 'TH' => 'THB', 'ID' => 'IDR', 'PH' => 'PHP', 'VN' => 'VND',
        // Phase 4 — First World
        'US' => 'USD', 'GB' => 'GBP', 'CA' => 'CAD', 'AU' => 'AUD',
        'DE' => 'EUR', 'FR' => 'EUR', 'SG' => 'SGD', 'NL' => 'EUR',
    ];

    private array $saarc = [
        'BD' => 'BDT', 'IN' => 'INR', 'PK' => 'PKR', 'LK' => 'LKR',
        'NP' => 'NPR', 'BT' => 'BTN', 'MV' => 'MVR',
    ];

    public function test_total_rows_650(): void
    {
        $this->assertEquals(
            650,
            DB::table('package_country_prices')->count(),
            'package_country_prices should hold 26 countries x 25 packages'
        );
    }

    public function test_all_countries_complete_and_active(): void
    {
        $all = array_merge($this->targets, $this->saarc);

        foreach ($all as $iso2 => $currency) {
            $country = DB::table('countries')->where('iso2', $iso2)->first();
            $this->assertNotNull($country, "Country {$iso2} missing from countries table");
            $this->assertEquals(1, (int) $country->status, "Country {$iso2} should be active");

            $this->assertEquals(
                25,
                DB::table('package_country_prices')->where('country_code', $iso2)->count(),
                "{$iso2} should have 25 price rows"
            );

            $mismatch = DB::table('package_country_prices')
                ->where('country_code', $iso2)
                ->where('currency_code', '!=', $currency)
                ->count();
            $this->assertEquals(0, $mismatch, "{$iso2} rows must all be {$currency}");
        }
    }

    public function test_currency_matches(): void
    {
        foreach (array_merge($this->targets, $this->saarc) as $iso2 => $currency) {
            $mapped = DB::table('country_currency_map')
                ->where('country_code', $iso2)
                ->value('currency_code');

            $this->assertEquals($currency, $mapped, "{$iso2} currency map mismatch");
        }
    }

    public function test_first_world_higher_prices(): void
    {
        $bd = $this->basicMonthly('BD');
        $us = $this->basicMonthly('US');

        $this->assertGreaterThan($bd * 5, $us, 'US basic must be more than 5x the BD price');
    }

    public function test_region_price_ladder(): void
    {
        $vn = $this->basicMonthly('VN');
        $bd = $this->basicMonthly('BD');
        $ae = $this->basicMonthly('AE');
        $us = $this->basicMonthly('US');

        $this->assertLessThan($bd, $vn, 'Vietnam should be cheaper than Bangladesh');
        $this->assertGreaterThan($bd, $ae, 'UAE should cost more than Bangladesh');
        $this->assertGreaterThan($ae, $us, 'United States should cost more than UAE');
    }

    private function basicMonthly(string $country): float
    {
        return (float) DB::table('package_country_prices')
            ->where('country_code', $country)
            ->whereIn('package_id', function ($query) {
                $query->select('id')->from('subscription_packages')->where('slug', 'basic');
            })
            ->value('price_monthly');
    }
}
