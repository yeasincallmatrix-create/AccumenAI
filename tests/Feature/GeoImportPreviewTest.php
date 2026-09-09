<?php

namespace Tests\Feature;

use App\Geo\Providers\LocalPackageProvider;
use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Services\GeoImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 2: impact preview must flag risky packages (code renames, mass
 * inserts on populated data, hierarchy mismatches) while letting clean
 * re-uploads through without confirmation. Read-only: preview never writes.
 */
class GeoImportPreviewTest extends TestCase
{
    use DatabaseTransactions;

    private Country $country;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir().'/geo-preview-'.uniqid();
        mkdir($this->tmp);

        $this->country = Country::create([
            'name' => 'Previewland',
            'iso2' => 'PV',
            'status' => true,
        ]);
        $div = AdministrativeLevel::create(['country_id' => $this->country->id, 'level_number' => 1, 'name' => 'Division', 'slug' => 'pv-div', 'status' => true]);
        $dist = AdministrativeLevel::create(['country_id' => $this->country->id, 'level_number' => 2, 'name' => 'District', 'slug' => 'pv-dis', 'status' => true]);
        AdministrativeLevel::create(['country_id' => $this->country->id, 'level_number' => 3, 'name' => 'Upazila', 'slug' => 'pv-upa', 'status' => true]);

        $d1 = AdministrativeUnit::create(['country_id' => $this->country->id, 'administrative_level_id' => $div->id, 'parent_id' => null, 'name' => 'North', 'code' => 'PV-N', 'status' => true]);
        AdministrativeUnit::create(['country_id' => $this->country->id, 'administrative_level_id' => $dist->id, 'parent_id' => $d1->id, 'name' => 'Oldtown', 'code' => 'PV-N-OLD', 'status' => true]);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->tmp.'/*') ?: []);
        rmdir($this->tmp);
        parent::tearDown();
    }

    private function providerFor(array $rows): LocalPackageProvider
    {
        $path = $this->tmp.'/pkg-'.uniqid().'.jsonl';
        file_put_contents($path, implode("\n", array_map(fn ($r) => json_encode($r), $rows))."\n");

        return new LocalPackageProvider($path);
    }

    public function test_clean_reupload_needs_no_confirmation(): void
    {
        $report = app(GeoImportService::class)->preview($this->providerFor([
            ['level' => 1, 'code' => 'PV-N', 'name' => 'North'],
            ['level' => 2, 'code' => 'PV-N-OLD', 'name' => 'Oldtown', 'parent_code' => 'PV-N'],
        ]), $this->country);

        $this->assertSame(0, $report['inserted']);
        $this->assertSame(2, $report['updated']);
        $this->assertFalse($report['preview']['requires_confirm']);
        $this->assertFalse($report['preview']['mass_insert_warning']);
    }

    public function test_rename_and_new_rows_require_confirmation(): void
    {
        $report = app(GeoImportService::class)->preview($this->providerFor([
            ['level' => 1, 'code' => 'PV-N', 'name' => 'North'],
            ['level' => 2, 'code' => 'PV-N-OLD', 'name' => 'RENAMED', 'parent_code' => 'PV-N'],
            ['level' => 2, 'code' => 'PV-N-NEW', 'name' => 'Newtown', 'parent_code' => 'PV-N'],
        ]), $this->country);

        $this->assertTrue($report['preview']['requires_confirm']);
        $this->assertTrue($report['preview']['mass_insert_warning']);
        $this->assertSame(1, $report['preview']['code_name_conflict_count']);
        $this->assertSame('PV-N-OLD', $report['preview']['code_name_conflicts'][0]['code']);
    }

    public function test_district_under_wrong_division_is_flagged(): void
    {
        $report = app(GeoImportService::class)->preview($this->providerFor([
            ['level' => 1, 'code' => 'PV-S', 'name' => 'South'],
            ['level' => 2, 'code' => 'PV-S-OLD', 'name' => 'Oldtown', 'parent_code' => 'PV-S'],
        ]), $this->country);

        $this->assertSame(1, $report['preview']['hierarchy_conflict_count']);
        $this->assertSame('district_division_mismatch', $report['preview']['hierarchy_conflicts'][0]['type']);
        $this->assertTrue($report['preview']['requires_confirm']);
    }

    public function test_preview_writes_nothing(): void
    {
        $before = AdministrativeUnit::where('country_id', $this->country->id)->count();
        app(GeoImportService::class)->preview($this->providerFor([
            ['level' => 1, 'code' => 'PV-X', 'name' => 'Extra'],
        ]), $this->country);

        $this->assertSame($before, AdministrativeUnit::where('country_id', $this->country->id)->count());
    }
}
