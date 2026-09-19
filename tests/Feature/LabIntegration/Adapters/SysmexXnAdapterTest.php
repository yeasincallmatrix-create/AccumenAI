<?php

namespace Tests\Feature\LabIntegration\Adapters;

use App\Models\Institute;
use App\Models\LabIntegration\LabAnalyzer;
use App\Services\LabIntegration\Adapters\SysmexXnAdapter;
use App\Services\LabIntegration\AnalyzerAdapterRegistry;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SysmexXnAdapterTest extends TestCase
{
    use DatabaseTransactions;

    protected SysmexXnAdapter $adapter;
    protected LabAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adapter = app(SysmexXnAdapter::class);

        $institute = Institute::create([
            'name' => 'Sysmex Test Hospital',
            'slug' => 'sysmex-test-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        Workspace::set($institute->id);
        TenantContext::set($institute->id);

        $this->analyzer = LabAnalyzer::factory()->create([
            'institute_id' => $institute->id,
            'code' => 'SYSMEX-XN-550-TEST',
            'name' => 'Sysmex XN-550 Test',
            'manufacturer' => 'Sysmex',
            'model' => 'XN-550',
            'adapter_key' => 'sysmex_xn',
            'adapter_version' => 'v1',
            'protocol' => 'astm',
            'instrument_type' => 'hematology',
        ]);
    }

    private function fixture(string $name): string
    {
        return file_get_contents(base_path("tests/Fixtures/LabIntegration/Sysmex/{$name}"));
    }

    // === Adapter metadata ===

    public function test_key_returns_sysmex_xn(): void
    {
        $this->assertSame('sysmex_xn', $this->adapter->key());
    }

    public function test_version_is_v1(): void
    {
        $this->assertSame('v1', $this->adapter->version());
    }

    public function test_protocol_is_astm(): void
    {
        $this->assertSame('astm', $this->adapter->protocol());
    }

    public function test_capabilities_declare_bidirectional(): void
    {
        $caps = $this->adapter->capabilities();

        $this->assertTrue($caps['result_upload']);
        $this->assertTrue($caps['worklist']);
        $this->assertFalse($caps['query']);
        $this->assertTrue($caps['bidirectional']);
    }

    public function test_matches_vendor_detects_sysmex_in_payload(): void
    {
        $this->assertTrue($this->adapter->matchesVendor('H|^&|||SYSMEX^XN-550^00-18'));
        $this->assertTrue($this->adapter->matchesVendor('analyzer XN-550 ready'));
    }

    public function test_matches_vendor_rejects_non_sysmex(): void
    {
        $this->assertFalse($this->adapter->matchesVendor('H|^&|||Mindray^BC-5150'));
        $this->assertFalse($this->adapter->matchesVendor('random handshake'));
    }

    // === Parsing ===

    public function test_parses_astm_5part_cbc(): void
    {
        $result = $this->adapter->parse($this->fixture('xn550_astm_cbc_5part.txt'), $this->analyzer);

        $this->assertCount(21, $result['parameters']);
        $this->assertSame('ACC-2026-00001', $result['accession_number']);
        $this->assertSame('MRN-12345', $result['patient_hint']);
        $this->assertSame('sysmex_xn:v1', $result['raw_metadata']['adapter']);
        $this->assertSame('astm', $result['raw_metadata']['protocol']);
    }

    public function test_parses_hl7_5part_cbc(): void
    {
        $result = $this->adapter->parse($this->fixture('xn550_hl7_cbc_5part.txt'), $this->analyzer);

        $this->assertCount(21, $result['parameters']);
        $this->assertSame('ACC-2026-00001', $result['accession_number']);
        $this->assertSame('hl7', $result['raw_metadata']['protocol']);
    }

    public function test_auto_detects_hl7_vs_astm(): void
    {
        $astm = $this->adapter->parse($this->fixture('xn550_astm_cbc_5part.txt'), $this->analyzer);
        $hl7 = $this->adapter->parse($this->fixture('xn550_hl7_cbc_5part.txt'), $this->analyzer);

        $this->assertSame('astm', $astm['raw_metadata']['protocol']);
        $this->assertSame('hl7', $hl7['raw_metadata']['protocol']);
        // Same accession + same parameter count from both protocols.
        $this->assertSame($astm['accession_number'], $hl7['accession_number']);
        $this->assertCount(count($astm['parameters']), $hl7['parameters']);
    }

    public function test_parses_retic_mode_with_different_parameters(): void
    {
        $result = $this->adapter->parse($this->fixture('xn550_astm_retic_mode.txt'), $this->analyzer);
        $codes = array_column($result['parameters'], 'vendor_code');

        $this->assertCount(8, $result['parameters']);
        foreach (['RBC', 'HGB', 'RET#', 'RET%', 'IRF', 'LFR', 'MFR', 'HFR'] as $code) {
            $this->assertContains($code, $codes);
        }
        // No WBC — panel is dynamic, adapter must not assume a fixed CBC set.
        $this->assertNotContains('WBC', $codes);
    }

    public function test_handles_asterisk_error_values(): void
    {
        $result = $this->adapter->parse($this->fixture('xn550_astm_with_errors.txt'), $this->analyzer);
        $codes = array_column($result['parameters'], 'vendor_code');

        $this->assertContains('WBC', $codes);
        $this->assertContains('HGB', $codes);
        $this->assertNotContains('RBC', $codes);
        $this->assertNotContains('PLT', $codes);
        $this->assertCount(2, $result['parse_errors']);
    }

    public function test_handles_blank_parameters(): void
    {
        $result = $this->adapter->parse($this->fixture('xn550_hl7_with_blank_params.txt'), $this->analyzer);
        $codes = array_column($result['parameters'], 'vendor_code');

        // Blank RBC/HCT skipped by the Phase 2 parser; WBC/HGB survive.
        $this->assertContains('WBC', $codes);
        $this->assertContains('HGB', $codes);
        $this->assertNotContains('RBC', $codes);
        $this->assertNotContains('HCT', $codes);
    }

    public function test_normalizes_line_endings_cr_crlf_lf(): void
    {
        $lf = $this->fixture('xn550_astm_cbc_5part.txt');
        $crlf = str_replace("\n", "\r\n", $lf);
        $cr = str_replace("\n", "\r", $lf);

        $this->assertCount(21, $this->adapter->parse($lf, $this->analyzer)['parameters']);
        $this->assertCount(21, $this->adapter->parse($crlf, $this->analyzer)['parameters']);
        $this->assertCount(21, $this->adapter->parse($cr, $this->analyzer)['parameters']);
    }

    public function test_does_not_throw_on_garbage_input(): void
    {
        $result = $this->adapter->parse("\x00\xff\xfeGARBAGE", $this->analyzer);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('parameters', $result);
        $this->assertArrayHasKey('parse_errors', $result);
    }

    // === Enrichment (with parameter maps) ===

    private function seedWbcMap(): void
    {
        \App\Models\LabIntegration\LabAnalyzerParameterMap::updateOrCreate(
            ['analyzer_id' => $this->analyzer->id, 'vendor_code' => 'WBC'],
            [
                'institute_id' => $this->analyzer->institute_id,
                'universal_code' => 'WBC',
                'parameter_key' => 'WBC',
                'unit_from' => '10^3/uL',
                'unit_to' => '10^3/uL',
                'conversion_factor' => 1,
                'ref_low' => 4.0,
                'ref_high' => 11.0,
                'ref_range_text' => '4.0-11.0',
                'is_active' => true,
            ]
        );
    }

    public function test_enriches_with_universal_codes(): void
    {
        $this->seedWbcMap();
        $result = $this->adapter->parse($this->fixture('xn550_astm_cbc_5part.txt'), $this->analyzer);
        $wbc = collect($result['parameters'])->firstWhere('vendor_code', 'WBC');

        $this->assertSame('WBC', $wbc['universal_code']);
        $this->assertTrue($wbc['mapped']);
    }

    public function test_enriches_with_reference_ranges_from_map(): void
    {
        $this->seedWbcMap();
        $result = $this->adapter->parse($this->fixture('xn550_astm_cbc_5part.txt'), $this->analyzer);
        $wbc = collect($result['parameters'])->firstWhere('vendor_code', 'WBC');

        $this->assertSame(4.0, $wbc['ref_low']);
        $this->assertSame(11.0, $wbc['ref_high']);
        $this->assertSame('4.0-11.0', $wbc['ref_range']);
    }

    public function test_applies_unit_conversion(): void
    {
        \App\Models\LabIntegration\LabAnalyzerParameterMap::updateOrCreate(
            ['analyzer_id' => $this->analyzer->id, 'vendor_code' => 'HGB'],
            [
                'institute_id' => $this->analyzer->institute_id,
                'universal_code' => 'HGB',
                'unit_from' => 'g/L',
                'unit_to' => 'g/dL',
                'conversion_factor' => 0.1,
                'is_active' => true,
            ]
        );
        // HGB arrives as 141 g/L → converts to 14.1 g/dL.
        $raw = "O|1|ACC-CV||^^^CBC|R||20260920103000||||||||||||||||||F\n"
            ."R|1|^^^HGB|141|g/L|130-170|N||F\n"
            .'L|1|N';
        $result = $this->adapter->parse($raw, $this->analyzer);
        $hgb = collect($result['parameters'])->firstWhere('vendor_code', 'HGB');

        $this->assertEqualsWithDelta(14.1, $hgb['value'], 0.0001);
        $this->assertSame('g/dL', $hgb['unit']);
    }

    public function test_maps_vendor_code_to_universal_code(): void
    {
        \App\Models\LabIntegration\LabAnalyzerParameterMap::updateOrCreate(
            ['analyzer_id' => $this->analyzer->id, 'vendor_code' => 'NEUT#'],
            [
                'institute_id' => $this->analyzer->institute_id,
                'universal_code' => 'NEU_ABS',
                'parameter_key' => 'NEU_ABS',
                'unit_from' => '10^3/uL',
                'unit_to' => '10^3/uL',
                'is_active' => true,
            ]
        );
        $result = $this->adapter->parse($this->fixture('xn550_astm_cbc_5part.txt'), $this->analyzer);
        $neut = collect($result['parameters'])->firstWhere('vendor_code', 'NEUT#');

        $this->assertSame('NEU_ABS', $neut['universal_code']);
    }

    public function test_lab_test_id_linked_when_mapped(): void
    {
        $labTest = \App\Models\Medical\LabTest::create([
            'institute_id' => $this->analyzer->institute_id,
            'code' => 'CBC-'.uniqid(),
            'name' => 'CBC',
            'is_active' => true,
        ]);
        \App\Models\LabIntegration\LabAnalyzerParameterMap::updateOrCreate(
            ['analyzer_id' => $this->analyzer->id, 'vendor_code' => 'WBC'],
            [
                'institute_id' => $this->analyzer->institute_id,
                'universal_code' => 'WBC',
                'lab_test_id' => $labTest->id,
                'is_active' => true,
            ]
        );
        $result = $this->adapter->parse($this->fixture('xn550_astm_cbc_5part.txt'), $this->analyzer);
        $wbc = collect($result['parameters'])->firstWhere('vendor_code', 'WBC');

        $this->assertSame($labTest->id, (int) $wbc['lab_test_id']);
    }

    // === Worklist ===

    public function test_builds_hl7_worklist_message(): void
    {
        $wl = $this->adapter->buildWorklist(
            ['accession_number' => 'ACC-1', 'patient_name' => 'DOE^JOHN', 'patient_id' => 'MRN-1'],
            $this->analyzer
        );

        $this->assertStringStartsWith('MSH|', $wl);
        $this->assertStringContainsString('ORM^O01', $wl);
    }

    public function test_worklist_contains_accession_number(): void
    {
        $wl = $this->adapter->buildWorklist(['accession_number' => 'ACC-XYZ-9'], $this->analyzer);

        $this->assertStringContainsString('ACC-XYZ-9', $wl);
    }

    public function test_worklist_contains_patient_info(): void
    {
        $wl = $this->adapter->buildWorklist(
            ['accession_number' => 'ACC-1', 'patient_name' => 'DOE^JANE', 'patient_id' => 'MRN-77'],
            $this->analyzer
        );

        $this->assertStringContainsString('DOE^JANE', $wl);
        $this->assertStringContainsString('MRN-77', $wl);
    }

    // === Registry ===

    public function test_registry_resolves_sysmex_adapter(): void
    {
        $resolved = app(AnalyzerAdapterRegistry::class)->resolve('sysmex_xn', 'v1');

        $this->assertInstanceOf(SysmexXnAdapter::class, $resolved);
    }

    public function test_registry_does_not_resolve_unknown_key(): void
    {
        $this->assertNull(app(AnalyzerAdapterRegistry::class)->resolve('mindray_bc', 'v1'));
    }
}
