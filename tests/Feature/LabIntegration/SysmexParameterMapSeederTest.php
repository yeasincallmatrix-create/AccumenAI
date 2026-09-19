<?php

namespace Tests\Feature\LabIntegration;

use App\Models\Institute;
use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabAnalyzerParameterMap;
use App\Support\TenantContext;
use App\Support\Workspace;
use Database\Seeders\SysmexXn550ParameterMapSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SysmexParameterMapSeederTest extends TestCase
{
    use DatabaseTransactions;

    protected LabAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $institute = Institute::create([
            'name' => 'Seeder Test Hospital',
            'slug' => 'seeder-test-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        Workspace::set($institute->id);
        TenantContext::set($institute->id);

        $this->analyzer = LabAnalyzer::factory()->create([
            'institute_id' => $institute->id,
            'code' => 'SEED-XN-1',
            'name' => 'Seeder Sysmex XN-550',
            'manufacturer' => 'Sysmex',
            'model' => 'XN-550',
            'adapter_key' => 'sysmex_xn',
            'adapter_version' => 'v1',
            'protocol' => 'astm',
            'instrument_type' => 'hematology',
        ]);
    }

    public function test_seeds_36_parameter_maps(): void
    {
        $this->seed(SysmexXn550ParameterMapSeeder::class);

        $this->assertSame(
            36,
            LabAnalyzerParameterMap::where('analyzer_id', $this->analyzer->id)->count()
        );
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(SysmexXn550ParameterMapSeeder::class);
        $this->seed(SysmexXn550ParameterMapSeeder::class);

        $this->assertSame(
            36,
            LabAnalyzerParameterMap::where('analyzer_id', $this->analyzer->id)->count()
        );
    }

    public function test_seeder_skips_when_no_sysmex_analyzers(): void
    {
        $this->analyzer->delete();

        // Must not throw; seeds nothing.
        $this->seed(SysmexXn550ParameterMapSeeder::class);

        $this->assertSame(0, LabAnalyzerParameterMap::count());
    }

    public function test_all_core_cbc_parameters_present(): void
    {
        $this->seed(SysmexXn550ParameterMapSeeder::class);
        $codes = LabAnalyzerParameterMap::where('analyzer_id', $this->analyzer->id)->pluck('vendor_code')->all();

        foreach (['WBC', 'RBC', 'HGB', 'HCT', 'MCV', 'MCH', 'MCHC', 'PLT'] as $code) {
            $this->assertContains($code, $codes);
        }
    }

    public function test_all_5part_diff_parameters_present(): void
    {
        $this->seed(SysmexXn550ParameterMapSeeder::class);
        $codes = LabAnalyzerParameterMap::where('analyzer_id', $this->analyzer->id)->pluck('vendor_code')->all();

        foreach (['NEUT#', 'LYMPH#', 'MONO#', 'EO#', 'BASO#', 'NEUT%', 'LYMPH%', 'MONO%', 'EO%', 'BASO%'] as $code) {
            $this->assertContains($code, $codes);
        }
    }

    public function test_all_retic_parameters_present(): void
    {
        $this->seed(SysmexXn550ParameterMapSeeder::class);
        $codes = LabAnalyzerParameterMap::where('analyzer_id', $this->analyzer->id)->pluck('vendor_code')->all();

        foreach (['RET#', 'RET%', 'IRF', 'LFR', 'MFR', 'HFR'] as $code) {
            $this->assertContains($code, $codes);
        }
    }

    public function test_ref_ranges_populated(): void
    {
        $this->seed(SysmexXn550ParameterMapSeeder::class);

        $this->assertSame(
            0,
            LabAnalyzerParameterMap::where('analyzer_id', $this->analyzer->id)
                ->whereNull('ref_range_text')
                ->count()
        );
    }

    public function test_units_canonical(): void
    {
        $this->seed(SysmexXn550ParameterMapSeeder::class);
        $wbc = LabAnalyzerParameterMap::where('analyzer_id', $this->analyzer->id)
            ->where('vendor_code', 'WBC')
            ->firstOrFail();

        $this->assertSame('10^3/uL', $wbc->unit_to);
        $this->assertSame('WBC', $wbc->universal_code);
    }
}
