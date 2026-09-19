<?php

namespace Tests\Feature\LabIntegration\Adapters;

use App\Models\Institute;
use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabAnalyzerParameterMap;
use App\Services\LabIntegration\Adapters\MindrayBcAdapter;
use App\Services\LabIntegration\Adapters\SysmexXnAdapter;
use App\Services\LabIntegration\AnalyzerAdapterRegistry;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MindrayBcAdapterTest extends TestCase
{
    use DatabaseTransactions;

    protected MindrayBcAdapter $adapter;
    protected LabAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = app(MindrayBcAdapter::class);

        $institute = Institute::create([
            'name' => 'Mindray Test Hospital',
            'slug' => 'mindray-test-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        Workspace::set($institute->id);
        TenantContext::set($institute->id);

        $this->analyzer = LabAnalyzer::factory()->create([
            'institute_id' => $institute->id,
            'code' => 'MINDRAY-BC-5150-TEST',
            'name' => 'Mindray BC-5150 Test',
            'manufacturer' => 'Mindray',
            'model' => 'BC-5150',
            'adapter_key' => 'mindray_bc',
            'adapter_version' => 'v1',
            'protocol' => 'astm',
            'instrument_type' => 'hematology',
            'is_enabled' => true,
            'status' => 'active',
        ]);
    }

    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/LabIntegration/Mindray/{$name}"));
    }

    // === Adapter metadata ===

    public function test_key_returns_mindray_bc(): void
    {
        $this->assertSame('mindray_bc', $this->adapter->key());
    }

    public function test_version_is_v1(): void
    {
        $this->assertSame('v1', $this->adapter->version());
    }

    public function test_protocol_is_astm(): void
    {
        $this->assertSame('astm', $this->adapter->protocol());
    }

    public function test_capabilities_no_worklist_no_bidirectional(): void
    {
        $caps = $this->adapter->capabilities();

        $this->assertTrue($caps['result_upload']);
        $this->assertFalse($caps['worklist']);
        $this->assertFalse($caps['query']);
        $this->assertFalse($caps['bidirectional']);
    }

    public function test_matches_vendor_detects_mindray(): void
    {
        $this->assertTrue($this->adapter->matchesVendor('H|^&|||MINDRAY^BC-5150^3.0'));
    }

    public function test_matches_vendor_detects_bc5150(): void
    {
        $this->assertTrue($this->adapter->matchesVendor('analyzer BC5150 ready'));
        $this->assertTrue($this->adapter->matchesVendor('BC-5150'));
    }

    public function test_matches_vendor_rejects_sysmex(): void
    {
        $this->assertFalse($this->adapter->matchesVendor('H|^&|||SYSMEX^XN-550'));
        // And Sysmex must not claim Mindray either (vendor isolation).
        $this->assertFalse(app(SysmexXnAdapter::class)->matchesVendor('H|^&|||MINDRAY^BC-5150'));
    }

    // === Parsing ===

    public function test_parses_astm_3part_cbc(): void
    {
        $result = $this->adapter->parse($this->fixture('bc5150_astm_cbc_3part.txt'), $this->analyzer);

        $this->assertCount(16, $result['parameters']);
        $this->assertSame('ACC-2026-MD-001', $result['accession_number']);
        $this->assertSame('MRN-55555', $result['patient_hint']);
        $this->assertSame('mindray_bc:v1', $result['raw_metadata']['adapter']);
    }

    public function test_parses_hl7_3part_cbc(): void
    {
        $result = $this->adapter->parse($this->fixture('bc5150_hl7_cbc_3part.txt'), $this->analyzer);

        $this->assertCount(8, $result['parameters']);
        $this->assertSame('hl7', $result['raw_metadata']['protocol']);
    }

    public function test_auto_detects_hl7_vs_astm(): void
    {
        $astm = $this->adapter->parse($this->fixture('bc5150_astm_cbc_3part.txt'), $this->analyzer);
        $hl7 = $this->adapter->parse($this->fixture('bc5150_hl7_cbc_3part.txt'), $this->analyzer);

        $this->assertSame('astm', $astm['raw_metadata']['protocol']);
        $this->assertSame('hl7', $hl7['raw_metadata']['protocol']);
    }

    public function test_handles_na_placeholder_values(): void
    {
        $result = $this->adapter->parse($this->fixture('bc5150_astm_with_na_values.txt'), $this->analyzer);
        $codes = array_column($result['parameters'], 'vendor_code');

        $this->assertContains('WBC', $codes);
        $this->assertContains('HGB', $codes);
        $this->assertNotContains('RBC', $codes); // N/A skipped
    }

    public function test_handles_double_dash_placeholder_values(): void
    {
        $result = $this->adapter->parse($this->fixture('bc5150_astm_with_na_values.txt'), $this->analyzer);
        $codes = array_column($result['parameters'], 'vendor_code');

        $this->assertNotContains('PLT', $codes); // "--" skipped
        $this->assertContains('MCV', $codes);
    }

    public function test_handles_line_ending_variations(): void
    {
        $lf = $this->fixture('bc5150_astm_cbc_3part.txt');
        $crlf = str_replace("\n", "\r\n", $lf);

        $this->assertCount(16, $this->adapter->parse($lf, $this->analyzer)['parameters']);
        $this->assertCount(16, $this->adapter->parse($crlf, $this->analyzer)['parameters']);
    }

    public function test_mid_gran_vendor_codes_present(): void
    {
        $result = $this->adapter->parse($this->fixture('bc5150_astm_cbc_3part.txt'), $this->analyzer);
        $codes = array_column($result['parameters'], 'vendor_code');

        foreach (['LYM%', 'MID%', 'GRAN%', 'LYM#', 'MID#', 'GRAN#'] as $code) {
            $this->assertContains($code, $codes);
        }
        // Sysmex 5-part codes must NOT appear in a 3-part panel.
        $this->assertNotContains('NEUT%', $codes);
        $this->assertNotContains('MONO%', $codes);
    }

    public function test_does_not_throw_on_garbage_input(): void
    {
        $result = $this->adapter->parse("\x00\xffGARBAGE", $this->analyzer);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('parameters', $result);
    }

    // === Enrichment ===

    private function seedMaps(): void
    {
        foreach ([
            ['MID%', 'MID_PCT'], ['GRAN%', 'GRAN_PCT'], ['LYM%', 'LYM_PCT'],
        ] as [$vendor, $universal]) {
            LabAnalyzerParameterMap::updateOrCreate(
                ['analyzer_id' => $this->analyzer->id, 'vendor_code' => $vendor],
                [
                    'institute_id' => $this->analyzer->institute_id,
                    'universal_code' => $universal,
                    'parameter_key' => $universal,
                    'unit_from' => '%',
                    'unit_to' => '%',
                    'is_active' => true,
                ]
            );
        }
    }

    public function test_enriches_with_universal_codes(): void
    {
        $this->seedMaps();
        $result = $this->adapter->parse($this->fixture('bc5150_astm_cbc_3part.txt'), $this->analyzer);
        $mid = collect($result['parameters'])->firstWhere('vendor_code', 'MID%');

        $this->assertSame('MID_PCT', $mid['universal_code']);
        $this->assertTrue($mid['mapped']);
    }

    public function test_mid_pct_maps_to_mid_pct(): void
    {
        $this->seedMaps();
        $result = $this->adapter->parse($this->fixture('bc5150_astm_cbc_3part.txt'), $this->analyzer);
        $mid = collect($result['parameters'])->firstWhere('vendor_code', 'MID%');

        $this->assertSame('MID_PCT', $mid['universal_code']);
        $this->assertSame('MID_PCT', $mid['parameter_key']);
    }

    public function test_gran_pct_maps_to_gran_pct(): void
    {
        $this->seedMaps();
        $result = $this->adapter->parse($this->fixture('bc5150_astm_cbc_3part.txt'), $this->analyzer);
        $gran = collect($result['parameters'])->firstWhere('vendor_code', 'GRAN%');

        $this->assertSame('GRAN_PCT', $gran['universal_code']);
    }

    public function test_distinct_from_sysmex_neut_mono(): void
    {
        // Same platform, different vendor codes: Sysmex uses NEUT%/MONO%,
        // Mindray uses GRAN%/MID% — mappings must not collide.
        $this->seedMaps();
        $result = $this->adapter->parse($this->fixture('bc5150_astm_cbc_3part.txt'), $this->analyzer);
        $universals = array_column($result['parameters'], 'universal_code');

        $this->assertNotContains('NEU_PCT', $universals);
        $this->assertNotContains('MON_PCT', $universals);
        $this->assertContains('MID_PCT', $universals);
        $this->assertContains('GRAN_PCT', $universals);
    }

    // === Worklist ===

    public function test_build_worklist_returns_null(): void
    {
        $this->assertNull($this->adapter->buildWorklist(['accession_number' => 'ACC-1'], $this->analyzer));
    }

    // === Registry ===

    public function test_registry_resolves_mindray_adapter(): void
    {
        $resolved = app(AnalyzerAdapterRegistry::class)->resolve('mindray_bc', 'v1');

        $this->assertInstanceOf(MindrayBcAdapter::class, $resolved);
    }

    public function test_registry_resolves_both_adapters(): void
    {
        $registry = app(AnalyzerAdapterRegistry::class);

        $this->assertInstanceOf(MindrayBcAdapter::class, $registry->resolve('mindray_bc', 'v1'));
        $this->assertInstanceOf(SysmexXnAdapter::class, $registry->resolve('sysmex_xn', 'v1'));
        $this->assertCount(2, $registry->all());
    }
}
