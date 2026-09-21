<?php

namespace Tests\Feature\Settings;

use App\Models\CountryTaxConfig;
use App\Models\Institute;
use App\Services\Accounting\CountryTaxConfigService;
use Tests\TestCase;

class CountryTaxConfigTest extends TestCase
{
    protected function makeInstitute(string $countryCode): Institute
    {
        $countryId = \DB::table('countries')->where('iso2', $countryCode)->value('id');

        return Institute::create([
            'name' => 'Test ' . $countryCode . ' ' . uniqid(),
            'slug' => 'test-' . strtolower($countryCode) . '-' . uniqid(),
            'country' => $countryCode,
            'country_id' => $countryId,
        ]);
    }

    public function test_configs_seeded(): void
    {
        $this->assertGreaterThanOrEqual(5, CountryTaxConfig::count());
        $this->assertDatabaseHas('country_tax_configs', ['country_code' => 'BD']);
        $this->assertDatabaseHas('country_tax_configs', ['country_code' => 'IN']);
        $this->assertDatabaseHas('country_tax_configs', ['country_code' => 'US']);
        $this->assertDatabaseHas('country_tax_configs', ['country_code' => 'GB']);
        $this->assertDatabaseHas('country_tax_configs', ['country_code' => 'DE']);
    }

    public function test_bd_labels(): void
    {
        $bd = $this->makeInstitute('BD');
        $this->assertEquals('TDS', tenant_tds_label($bd->id));
        $this->assertEquals('NBR', tenant_tax_authority($bd->id));
        $this->assertEquals('e-TIN', tenant_tin_label($bd->id));
    }

    public function test_in_labels(): void
    {
        $in = $this->makeInstitute('IN');
        $this->assertEquals('TDS', tenant_tds_label($in->id));
        $this->assertEquals('CBDT', tenant_tax_authority($in->id));
        $this->assertEquals('PAN', tenant_tin_label($in->id));
    }

    public function test_us_labels(): void
    {
        $us = $this->makeInstitute('US');
        $this->assertEquals('Withholding', tenant_tds_label($us->id));
        $this->assertEquals('IRS', tenant_tax_authority($us->id));
        $this->assertEquals('EIN', tenant_tin_label($us->id));
    }

    public function test_gb_labels(): void
    {
        $gb = $this->makeInstitute('GB');
        $this->assertEquals('PAYE', tenant_tds_label($gb->id));
        $this->assertEquals('HMRC', tenant_tax_authority($gb->id));
        $this->assertEquals('UTR', tenant_tin_label($gb->id));
    }

    public function test_de_labels(): void
    {
        $de = $this->makeInstitute('DE');
        $this->assertEquals('Quellensteuer', tenant_tds_label($de->id));
        $this->assertEquals('BZSt', tenant_tax_authority($de->id));
        $this->assertEquals('Steuernummer', tenant_tin_label($de->id));
    }

    public function test_module_label_changes_by_country(): void
    {
        $bd = $this->makeInstitute('BD');
        $us = $this->makeInstitute('US');

        $this->assertEquals('TDS & Tax', tenant_tax_module_label($bd->id));
        $this->assertEquals('Withholding & Tax', tenant_tax_module_label($us->id));
    }

    public function test_unknown_country_falls_back_to_bd(): void
    {
        $institute = $this->makeInstitute('ZZ');
        $config = app(CountryTaxConfigService::class)->config($institute->id);

        $this->assertEquals('ZZ', $config['country_code']);
        $this->assertTrue($config['_fallback_used'] ?? false);
    }
}
