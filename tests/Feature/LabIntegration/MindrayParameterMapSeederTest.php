<?php

namespace Tests\Feature\LabIntegration;

use App\Models\Institute;
use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabAnalyzerParameterMap;
use App\Support\TenantContext;
use App\Support\Workspace;
use Database\Seeders\MindrayBc5150ParameterMapSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MindrayParameterMapSeederTest extends TestCase
{
    use DatabaseTransactions;

    protected LabAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $institute = Institute::create([
            'name' => 'Mindray Seeder Hospital',
            'slug' => 'mindray-seeder-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        Workspace::set($institute->id);
        TenantContext::set($institute->id);

        $this->analyzer = LabAnalyzer::factory()->create([
            'institute_id' => $institute->id,
            'code' => 'SEED-BC-1',
            'name' => 'Seeder Mindray BC-5150',
            'manufacturer' => 'Mindray',
            'model' => 'BC-5150',
            'adapter_key' => 'mindray_bc',
            'adapter_version' => 'v1',
            'protocol' => 'astm',
            'instrument_type' => 'hematology',
        ]);
    }

    public function test_seeds_19_parameter_maps(): void
    {
        $this->seed(MindrayBc5150ParameterMapSeeder::class);

        $this->assertSame(
            19,
            LabAnalyzerParameterMap::where('analyzer_id', $this->analyzer->id)->count()
        );
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(MindrayBc5150ParameterMapSeeder::class);
        $this->seed(MindrayBc5150ParameterMapSeeder::class);

        $this->assertSame(
            19,
            LabAnalyzerParameterMap::where('analyzer_id', $this->analyzer->id)->count()
        );
    }

    public function test_seeder_skips_when_no_mindray_analyzers(): void
    {
        $this->analyzer->delete();

        $this->seed(MindrayBc5150ParameterMapSeeder::class);

        $this->assertSame(0, LabAnalyzerParameterMap::count());
    }

    public function test_all_core_cbc_parameters_present(): void
    {
        $this->seed(MindrayBc5150ParameterMapSeeder::class);
        $codes = LabAnalyzerParameterMap::where('analyzer_id', $this->analyzer->id)->pluck('vendor_code')->all();

        foreach (['WBC', 'RBC', 'HGB', 'HCT', 'MCV', 'MCH', 'MCHC', 'PLT'] as $code) {
            $this->assertContains($code, $codes);
        }
    }

    public function test_3part_differential_present(): void
    {
        $this->seed(MindrayBc5150ParameterMapSeeder::class);
        $codes = LabAnalyzerParameterMap::where('analyzer_id', $this->analyzer->id)->pluck('vendor_code')->all();

        foreach (['LYM%', 'MID%', 'GRAN%', 'LYM#', 'MID#', 'GRAN#'] as $code) {
            $this->assertContains($code, $codes);
        }
    }

    public function test_mid_and_gran_maps_present(): void
    {
        $this->seed(MindrayBc5150ParameterMapSeeder::class);

        $mid = LabAnalyzerParameterMap::where('analyzer_id', $this->analyzer->id)
            ->where('vendor_code', 'MID%')->firstOrFail();
        $gran = LabAnalyzerParameterMap::where('analyzer_id', $this->analyzer->id)
            ->where('vendor_code', 'GRAN%')->firstOrFail();

        $this->assertSame('MID_PCT', $mid->universal_code);
        $this->assertSame('GRAN_PCT', $gran->universal_code);
    }

    public function test_units_canonical(): void
    {
        $this->seed(MindrayBc5150ParameterMapSeeder::class);
        $wbc = LabAnalyzerParameterMap::where('analyzer_id', $this->analyzer->id)
            ->where('vendor_code', 'WBC')
            ->firstOrFail();

        $this->assertSame('10^3/uL', $wbc->unit_to);
        $this->assertSame('WBC', $wbc->universal_code);
    }

    public function test_universal_codes_distinct_from_sysmex(): void
    {
        $this->seed(MindrayBc5150ParameterMapSeeder::class);
        $universals = LabAnalyzerParameterMap::where('analyzer_id', $this->analyzer->id)
            ->pluck('universal_code')->all();

        // 3-part codes exist...
        $this->assertContains('MID_PCT', $universals);
        $this->assertContains('GRAN_PCT', $universals);
        // ...and 5-part Sysmex-only codes do not leak in.
        $this->assertNotContains('NEU_PCT', $universals);
        $this->assertNotContains('MON_PCT', $universals);
        $this->assertNotContains('NEU_ABS', $universals);
    }
}
