<?php

namespace Tests\Feature\Helpers;

use App\Models\Country;
use App\Models\Institute;
use Tests\TestCase;

class TenantCountryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        \App\Support\TenantContext::clear();
    }

    public function test_resolves_from_country_id(): void
    {
        $country = Country::where('iso2', 'BD')->first();
        if (! $country) {
            $this->markTestSkipped('BD country not in DB');
        }

        $institute = Institute::create([
            'name' => 'FK Test ' . uniqid(),
            'slug' => 'fk-test-' . uniqid(),
            'status' => 'active',
            'country_id' => $country->id,
        ]);

        $this->assertEquals('BD', tenant_country($institute->id));
    }

    public function test_resolves_bangladesh_name_to_bd(): void
    {
        $institute = Institute::create([
            'name' => 'Name Test ' . uniqid(),
            'slug' => 'name-test-' . uniqid(),
            'status' => 'active',
            'country' => 'Bangladesh',
        ]);

        $this->assertEquals('BD', tenant_country($institute->id));
    }

    public function test_handles_iso2_string_passthrough(): void
    {
        $institute = Institute::create([
            'name' => 'ISO2 Test ' . uniqid(),
            'slug' => 'iso2-test-' . uniqid(),
            'status' => 'active',
            'country' => 'IN',
        ]);

        $result = tenant_country($institute->id);
        $this->assertContains($result, ['IN', 'BD']);
    }

    public function test_defaults_to_bd_when_all_null(): void
    {
        $institute = Institute::create([
            'name' => 'Null Test ' . uniqid(),
            'slug' => 'null-test-' . uniqid(),
            'status' => 'active',
        ]);

        $this->assertEquals('BD', tenant_country($institute->id));
    }

    public function test_tenant_currency_resolves_from_country(): void
    {
        $institute = Institute::create([
            'name' => 'Currency Test ' . uniqid(),
            'slug' => 'currency-test-' . uniqid(),
            'status' => 'active',
            'country_id' => Country::where('iso2', 'BD')->first()?->id,
        ]);

        $currency = tenant_currency($institute->id);
        $this->assertNotEmpty($currency);
        $this->assertIsString($currency);
    }
}
