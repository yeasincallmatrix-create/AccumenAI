<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AddressComponentBladeTest extends TestCase
{
    use DatabaseTransactions;

    public function test_component_renders_zip_and_street_fields_when_empty(): void
    {
        $view = $this->view('components.address', [
            'prefix' => 'present_',
            'countryId' => null,
            'level1Id' => null,
            'level2Id' => null,
            'level3Id' => null,
            'levelLabels' => [],
            'level1Options' => [],
            'level2Options' => [],
            'level3Options' => [],
            'postalCode' => null,
            'address' => null,
        ]);

        $view->assertSee('name="present_zip_code"', false)
            ->assertSee('name="present_address"', false)
            ->assertSee('Zip / Postal Code', false)
            ->assertSee('House / Road', false)
            ->assertSee('data-label-endpoint', false);
    }

    public function test_country_options_carry_dynamic_level_labels(): void
    {
        $au = \App\Models\Country::firstOrCreate(['iso2' => 'AU'], ['name' => 'Australia', 'iso3' => 'AUD',
            'phone_code' => '61', 'status' => true,
        ]);
        \App\Models\AdministrativeLevel::updateOrCreate(['country_id' => $au->id, 'level_number' => 1], ['name' => 'State', 'slug' => 'au_level_1', 'status' => true]);
        \App\Models\AdministrativeLevel::updateOrCreate(['country_id' => $au->id, 'level_number' => 2], ['name' => 'Local Government Area', 'slug' => 'au_level_2', 'status' => true]);
        \App\Models\AdministrativeLevel::updateOrCreate(['country_id' => $au->id, 'level_number' => 3], ['name' => 'Suburb', 'slug' => 'au_level_3', 'status' => true]);

        $view = $this->view('components.address', [
            'prefix' => '',
            'countryId' => null,
            'levelLabels' => [],
            'levelOptions' => [],
        ]);

        $view->assertSee('data-label-1="State"', false)
            ->assertSee('data-label-2="Local Government Area"', false)
            ->assertSee('data-label-3="Suburb"', false)
            ->assertSee('data-iso2="AU"', false);
    }
}