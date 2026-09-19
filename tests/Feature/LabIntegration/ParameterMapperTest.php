<?php

namespace Tests\Feature\LabIntegration;

use App\Models\Institute;
use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabAnalyzerParameterMap;
use App\Services\LabIntegration\ParameterMapper;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ParameterMapperTest extends TestCase
{
    use DatabaseTransactions;

    private function analyzer(): LabAnalyzer
    {
        $institute = Institute::create([
            'name' => 'Mapper Hospital',
            'slug' => 'mapper-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        Workspace::set($institute->id);
        TenantContext::set($institute->id);

        return LabAnalyzer::create([
            'institute_id' => $institute->id,
            'code' => 'MAP-1',
            'name' => 'Mapper Analyzer',
            'instrument_type' => 'hematology',
            'protocol' => 'astm',
            'adapter_key' => 'generic_astm',
            'connection_type' => 'tcp',
            'is_enabled' => true,
            'status' => 'active',
        ]);
    }

    public function test_resolves_mapped_vendor_code(): void
    {
        $analyzer = $this->analyzer();
        LabAnalyzerParameterMap::create([
            'institute_id' => $analyzer->institute_id,
            'analyzer_id' => $analyzer->id,
            'vendor_code' => 'WBC#',
            'universal_code' => 'WBC',
        ]);

        $resolved = ParameterMapper::resolve($analyzer, 'WBC#');

        $this->assertTrue($resolved['mapped']);
        $this->assertSame('WBC', $resolved['universal_code']);
        $this->assertSame('WBC', $resolved['parameter_key']);
    }

    public function test_falls_back_to_vendor_code_when_unmapped(): void
    {
        $analyzer = $this->analyzer();

        $resolved = ParameterMapper::resolve($analyzer, 'XYZ123');

        $this->assertFalse($resolved['mapped']);
        $this->assertSame('XYZ123', $resolved['universal_code']);
        $this->assertNull($resolved['lab_test_id']);
    }

    public function test_includes_unit_conversion(): void
    {
        $analyzer = $this->analyzer();
        LabAnalyzerParameterMap::create([
            'institute_id' => $analyzer->institute_id,
            'analyzer_id' => $analyzer->id,
            'vendor_code' => 'HGB',
            'universal_code' => 'HGB',
            'unit_from' => 'g/L',
            'unit_to' => 'g/dL',
            'conversion_factor' => 0.1,
        ]);

        $resolved = ParameterMapper::resolve($analyzer, 'HGB');

        $this->assertSame('g/L', $resolved['unit_from']);
        $this->assertSame('g/dL', $resolved['unit_to']);
        $this->assertEqualsWithDelta(0.1, $resolved['conversion_factor'], 0.000001);
    }

    public function test_includes_reference_range(): void
    {
        $analyzer = $this->analyzer();
        LabAnalyzerParameterMap::create([
            'institute_id' => $analyzer->institute_id,
            'analyzer_id' => $analyzer->id,
            'vendor_code' => 'WBC',
            'universal_code' => 'WBC',
            'ref_low' => 4.0,
            'ref_high' => 11.0,
            'ref_range_text' => '4.0-11.0',
        ]);

        $resolved = ParameterMapper::resolve($analyzer, 'WBC');

        $this->assertSame(4.0, $resolved['ref_low']);
        $this->assertSame(11.0, $resolved['ref_high']);
        $this->assertSame('4.0-11.0', $resolved['ref_range_text']);
    }
}
