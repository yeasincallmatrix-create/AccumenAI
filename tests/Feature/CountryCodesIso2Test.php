<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Support\CountryCodes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 9b-2 (B108): CountryCodes gains FK/ISO2-backed lookups.
 * All pre-existing name-keyed behavior is pinned unchanged.
 */
class CountryCodesIso2Test extends TestCase
{
    use DatabaseTransactions;

    public function test_code_for_iso2_resolves_from_countries_table(): void
    {
        $this->assertSame('880', CountryCodes::codeForIso2('BD'));
        $this->assertSame('880', CountryCodes::codeForIso2('bd'));

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
        $this->assertSame('1', CountryCodes::codeForIso2('US'));
    }

    public function test_code_for_iso2_unknown_falls_back(): void
    {
        $this->assertSame('880', CountryCodes::codeForIso2('ZZ'));
    }

    public function test_code_for_country_model_uses_phone_code_column(): void
    {
        $bd = Country::where('iso2', 'BD')->firstOrFail();

        $this->assertSame('880', CountryCodes::codeForCountry($bd));
        $this->assertSame('880', CountryCodes::codeForCountry(null));
    }

    public function test_existing_name_lookups_unchanged(): void
    {
        $this->assertSame('880', CountryCodes::codeFor('Bangladesh'));
        $this->assertSame('91', CountryCodes::codeFor('India'));
        $this->assertSame('880', CountryCodes::codeFor(null));
        $this->assertSame('017XXXXXXXX', CountryCodes::phoneExampleFor('Bangladesh'));
        $this->assertSame([11, 11], CountryCodes::nationalLengthFor('Bangladesh'));
    }
}
