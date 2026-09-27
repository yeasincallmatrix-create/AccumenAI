<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;

class SaarcPricingTest extends TestCase
{
    private array $saarc = [
        'BD' => 'BDT',
        'IN' => 'INR',
        'PK' => 'PKR',
        'LK' => 'LKR',
        'NP' => 'NPR',
        'BT' => 'BTN',
        'MV' => 'MVR',
    ];

    public function test_all_saarc_countries_have_prices()
    {
        foreach ($this->saarc as $iso2 => $currency) {
            $count = DB::table('package_country_prices')
                ->where('country_code', $iso2)
                ->where('currency_code', $currency)
                ->count();
            $this->assertEquals(25, $count, "{$iso2}/{$currency} should have 25 rows");
        }
    }

    public function test_total_rows_175()
    {
        $this->assertEquals(175, DB::table('package_country_prices')->count());
    }

    public function test_bd_unchanged()
    {
        $count = DB::table('package_country_prices')
            ->where('country_code', 'BD')
            ->where('currency_code', 'BDT')
            ->count();
        $this->assertEquals(25, $count);
    }

    public function test_india_prices_lower_than_bd()
    {
        $bdBasic = DB::table('package_country_prices')
            ->where('country_code', 'BD')
            ->whereIn('package_id', function ($q) {
                $q->select('id')->from('subscription_packages')->where('slug', 'basic');
            })
            ->value('price_monthly');

        $inBasic = DB::table('package_country_prices')
            ->where('country_code', 'IN')
            ->whereIn('package_id', function ($q) {
                $q->select('id')->from('subscription_packages')->where('slug', 'basic');
            })
            ->value('price_monthly');

        $this->assertLessThan($bdBasic, $inBasic);
    }

    public function test_maldives_higher_than_bd()
    {
        $bdBasic = DB::table('package_country_prices')
            ->where('country_code', 'BD')
            ->whereIn('package_id', function ($q) {
                $q->select('id')->from('subscription_packages')->where('slug', 'basic');
            })
            ->value('price_monthly');

        $mvBasic = DB::table('package_country_prices')
            ->where('country_code', 'MV')
            ->whereIn('package_id', function ($q) {
                $q->select('id')->from('subscription_packages')->where('slug', 'basic');
            })
            ->value('price_monthly');

        $this->assertGreaterThan($bdBasic, $mvBasic);
    }

    public function test_all_prices_active()
    {
        $inactive = DB::table('package_country_prices')
            ->where('is_active', false)
            ->count();
        $this->assertEquals(0, $inactive);
    }

    public function test_currency_matches_country()
    {
        foreach ($this->saarc as $iso2 => $currency) {
            $mismatch = DB::table('package_country_prices')
                ->where('country_code', $iso2)
                ->where('currency_code', '!=', $currency)
                ->count();
            $this->assertEquals(0, $mismatch, "{$iso2} has wrong currency");
        }
    }

    public function test_countries_table_has_all_saarc()
    {
        $expected = ['BD', 'IN', 'PK', 'LK', 'NP', 'BT', 'MV'];
        $present = DB::table('countries')
            ->whereIn('iso2', $expected)
            ->pluck('iso2')
            ->toArray();

        foreach ($expected as $iso2) {
            $this->assertContains($iso2, $present, "Country {$iso2} missing from countries table");
        }
    }

    public function test_countries_currency_map_complete()
    {
        foreach ($this->saarc as $iso2 => $currency) {
            $mapped = DB::table('country_currency_map')
                ->where('country_code', $iso2)
                ->value('currency_code');
            $this->assertEquals($currency, $mapped, "{$iso2} currency map mismatch");
        }
    }
}
