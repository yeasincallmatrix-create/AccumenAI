<?php

namespace Tests\Feature;

use App\Models\Country;
use App\Models\Institute;
use App\Services\Accounting\AccountingSetupService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 9b-2 (B108): accounting base currency resolves via
 * CountryConfigResolver (FK → iso2 → CountryCurrencyMap),
 * not the legacy 5-country name-keyed map.
 */
class AccountingSetupCountryTest extends TestCase
{
    use DatabaseTransactions;

    private function countryId(string $iso2, string $name, string $phone): int
    {
        $row = Country::where('iso2', $iso2)->first();
        if ($row) {
            return (int) $row->id;
        }

        return (int) DB::table('countries')->insertGetId([
            'name' => $name,
            'iso2' => $iso2,
            'iso3' => $iso2.'X',
            'phone_code' => $phone,
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function institute(?string $countryName, ?int $countryId): Institute
    {
        return Institute::create([
            'name' => 'AcctCntry '.uniqid(),
            'slug' => 'acctcntry-'.uniqid(),
            'status' => 'active',
            'industry' => 'retail',
            'country' => $countryName,
            'country_id' => $countryId,
        ]);
    }

    private function baseCurrency(Institute $inst): ?string
    {
        app(AccountingSetupService::class)->setupForInstitute($inst->id);

        return app(AccountingSetupService::class)->getSetting($inst->id, 'base_currency');
    }

    public function test_accounting_setup_uses_country_map_for_bd(): void
    {
        $inst = $this->institute('Bangladesh', $this->countryId('BD', 'Bangladesh', '880'));

        $this->assertSame('BDT', $this->baseCurrency($inst));
    }

    public function test_accounting_setup_uses_country_map_for_in(): void
    {
        $inst = $this->institute('India', $this->countryId('IN', 'India', '91'));

        $this->assertSame('INR', $this->baseCurrency($inst));
    }

    public function test_accounting_setup_uses_country_map_for_us(): void
    {
        $inst = $this->institute('United States', $this->countryId('US', 'United States', '1'));

        $this->assertSame('USD', $this->baseCurrency($inst));
    }

    public function test_accounting_setup_null_country_falls_back_to_platform_default(): void
    {
        // No FK and no usable name: platform base (USD) applies —
        // B112 restores the exact pre-9b-2 fallback.
        // (institutes.country is non-nullable, so empty string stands
        // in for "no name".)
        $inst = $this->institute('', null);

        $this->assertSame('USD', $this->baseCurrency($inst));
    }
}
