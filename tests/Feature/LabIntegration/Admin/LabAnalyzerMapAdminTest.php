<?php

namespace Tests\Feature\LabIntegration\Admin;

use App\Models\LabIntegration\LabAnalyzer;
use App\Models\LabIntegration\LabAnalyzerParameterMap;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class LabAnalyzerMapAdminTest extends AdminTestCase
{
    use DatabaseTransactions;

    protected LabAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->analyzer = $this->analyzer(['code' => 'MAP-ADM-1', 'name' => 'Map Admin Analyzer']);
    }

    public function test_maps_index_renders(): void
    {
        $this->get(route('medical.laboratory.analyzers.maps.index', $this->analyzer))
            ->assertOk()
            ->assertSee('Parameter Maps');
    }

    public function test_store_creates_map(): void
    {
        $response = $this->post(route('medical.laboratory.analyzers.maps.store', $this->analyzer), [
            'vendor_code' => 'WBC',
            'universal_code' => 'WBC',
            'unit_from' => '10^3/uL',
            'unit_to' => '10^3/uL',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('lab_analyzer_parameter_maps', [
            'analyzer_id' => $this->analyzer->id,
            'vendor_code' => 'WBC',
            'universal_code' => 'WBC',
        ]);
    }

    public function test_update_modifies_map(): void
    {
        $map = LabAnalyzerParameterMap::create([
            'institute_id' => $this->institute->id,
            'analyzer_id' => $this->analyzer->id,
            'vendor_code' => 'HGB',
            'universal_code' => 'HGB',
        ]);

        $response = $this->put(
            route('medical.laboratory.analyzers.maps.update', [$this->analyzer, $map]),
            ['vendor_code' => 'HGB', 'universal_code' => 'HGB', 'ref_range_text' => '13.0-17.0']
        );

        $response->assertRedirect();
        $this->assertSame('13.0-17.0', $map->fresh()->ref_range_text);
    }

    public function test_destroy_deletes_map(): void
    {
        $map = LabAnalyzerParameterMap::create([
            'institute_id' => $this->institute->id,
            'analyzer_id' => $this->analyzer->id,
            'vendor_code' => 'DEL',
            'universal_code' => 'DEL',
        ]);

        $this->delete(route('medical.laboratory.analyzers.maps.destroy', [$this->analyzer, $map]))
            ->assertRedirect();

        $this->assertDatabaseMissing('lab_analyzer_parameter_maps', ['id' => $map->id]);
    }

    public function test_seed_sysmex_button_works(): void
    {
        $response = $this->post(route('medical.laboratory.analyzers.maps.seed-sysmex', $this->analyzer));

        $response->assertRedirect();
        $this->assertSame(
            36,
            LabAnalyzerParameterMap::where('analyzer_id', $this->analyzer->id)->count()
        );
    }

    public function test_seed_sysmex_rejected_for_non_sysmex_adapter(): void
    {
        $this->analyzer->update(['adapter_key' => 'generic_astm']);

        $response = $this->post(route('medical.laboratory.analyzers.maps.seed-sysmex', $this->analyzer));

        $response->assertRedirect();
        $response->assertSessionHas('warning');
        $this->assertSame(0, LabAnalyzerParameterMap::where('analyzer_id', $this->analyzer->id)->count());
    }

    public function test_map_cannot_cross_analyzers(): void
    {
        $other = LabAnalyzer::create([
            'institute_id' => $this->institute->id,
            'code' => 'MAP-ADM-2',
            'name' => 'Other Analyzer',
            'instrument_type' => 'hematology',
            'protocol' => 'astm',
            'adapter_key' => 'sysmex_xn',
            'connection_type' => 'tcp',
        ]);
        $foreignMap = LabAnalyzerParameterMap::create([
            'institute_id' => $this->institute->id,
            'analyzer_id' => $other->id,
            'vendor_code' => 'X',
            'universal_code' => 'X',
        ]);

        // Map belongs to $other, URL says $this->analyzer → 404.
        $this->put(
            route('medical.laboratory.analyzers.maps.update', [$this->analyzer, $foreignMap]),
            ['vendor_code' => 'X', 'universal_code' => 'Y']
        )->assertNotFound();
    }
}
