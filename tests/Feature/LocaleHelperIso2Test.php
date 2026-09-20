<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 9b-2 (B108): locale helpers accept ISO2 codes in addition to
 * country names. Signatures and name-keyed behavior are unchanged.
 */
class LocaleHelperIso2Test extends TestCase
{
    use DatabaseTransactions;

    public function test_currency_symbol_accepts_iso2(): void
    {
        $taka = "\xE0\xA7\xB3"; // ৳
        $rupee = "\xE2\x82\xB9"; // ₹

        $this->assertSame($taka, mawa_currency_symbol('BD'));
        $this->assertSame($taka, mawa_currency_symbol('Bangladesh'));
        $this->assertSame($rupee, mawa_currency_symbol('IN'));
        $this->assertSame($taka, mawa_currency_symbol('ZZ'));
        $this->assertSame($taka, mawa_currency_symbol(null));
    }

    public function test_country_flag_accepts_iso2(): void
    {
        if (! \App\Models\Country::where('iso2', 'US')->exists()) {
            \Illuminate\Support\Facades\DB::table('countries')->insert([
                'name' => 'United States',
                'iso2' => 'US',
                'iso3' => 'USA',
                'phone_code' => '1',
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->assertSame('https://flagcdn.com/w40/bd.png', mawa_country_flag('BD'));
        $this->assertSame(mawa_country_flag('Bangladesh'), mawa_country_flag('BD'));
        $this->assertSame('https://flagcdn.com/w40/us.png', mawa_country_flag('US'));
        $this->assertSame('https://flagcdn.com/w40/xx.png', mawa_country_flag('ZZ'));
        $this->assertSame('https://flagcdn.com/w40/xx.png', mawa_country_flag(null));
    }
}
