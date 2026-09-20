<?php

namespace Tests\Feature;

use App\Models\Country;
use Database\Seeders\AdditionalCountrySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 9b-5c: AdditionalCountrySeeder covers BD + US + GB.
 *
 * B125 (fresh installs lacked BD — dump lineage only), B121 (US),
 * B126 (GB). All assertions run inside DatabaseTransactions and
 * roll back; no shared-DB truncate or committed db:seed is used.
 */
class CountrySeederCoverageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_fresh_install_has_bd_us_gb(): void
    {
        (new AdditionalCountrySeeder)->run();

        foreach ([
            'BD' => ['name' => 'Bangladesh', 'phone_code' => '880'],
            'US' => ['name' => 'United States', 'phone_code' => '1'],
            'GB' => ['name' => 'United Kingdom', 'phone_code' => '44'],
        ] as $iso2 => $expect) {
            $row = Country::where('iso2', $iso2)->first();

            $this->assertNotNull($row, "Country row [{$iso2}] must exist after seeding");
            $this->assertSame($expect['name'], $row->name);
            $this->assertSame($expect['phone_code'], (string) $row->phone_code);
        }
    }

    public function test_seeder_is_idempotent_for_existing_rows(): void
    {
        (new AdditionalCountrySeeder)->run();
        $countAfterFirst = Country::count();

        $bdId = (int) Country::where('iso2', 'BD')->firstOrFail()->id;

        (new AdditionalCountrySeeder)->run();

        // No duplicates, no row loss: same count, same BD id.
        $this->assertSame($countAfterFirst, Country::count());
        $this->assertSame($bdId, (int) Country::where('iso2', 'BD')->firstOrFail()->id);

        foreach (['BD', 'US', 'GB'] as $iso2) {
            $this->assertSame(1, Country::where('iso2', $iso2)->count(), "Exactly one [{$iso2}] row");
        }
    }
}
