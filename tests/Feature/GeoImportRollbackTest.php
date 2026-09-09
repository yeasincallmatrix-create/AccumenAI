<?php

namespace Tests\Feature;

use App\Geo\Providers\LocalPackageProvider;
use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Models\GeoImport;
use App\Services\GeoImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 3: every import leaves a snapshot trail (inserted ids + pre-update
 * values) so completed/failed imports can be rolled back; clearCountry
 * removes a whole country's tree with reference counts.
 */
class GeoImportRollbackTest extends TestCase
{
    use DatabaseTransactions;

    private Country $country;

    private string $tmp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tmp = sys_get_temp_dir().'/geo-rb-'.uniqid();
        mkdir($this->tmp);

        $this->country = Country::create(['name' => 'Rollbackland', 'iso2' => 'RB', 'status' => true]);
        AdministrativeLevel::create(['country_id' => $this->country->id, 'level_number' => 1, 'name' => 'Division', 'slug' => 'rb-div', 'status' => true]);
        AdministrativeLevel::create(['country_id' => $this->country->id, 'level_number' => 2, 'name' => 'District', 'slug' => 'rb-dis', 'status' => true]);
        AdministrativeLevel::create(['country_id' => $this->country->id, 'level_number' => 3, 'name' => 'Upazila', 'slug' => 'rb-upa', 'status' => true]);
    }

    protected function tearDown(): void
    {
        foreach (GeoImport::where('country_id', $this->country->id)->get() as $import) {
            @unlink(storage_path('app/geo-snapshots/'.$import->id.'.json'));
        }
        array_map('unlink', glob($this->tmp.'/*') ?: []);
        rmdir($this->tmp);
        parent::tearDown();
    }

    private function runPackage(array $rows): GeoImport
    {
        $path = $this->tmp.'/pkg-'.uniqid().'.jsonl';
        file_put_contents($path, implode("\n", array_map(fn ($r) => json_encode($r), $rows))."\n");
        $import = GeoImport::create([
            'country_id' => $this->country->id, 'filename' => basename($path), 'file_size' => 10,
            'format' => 'jsonl', 'status' => 'pending', 'mode' => 'upsert',
            'total_records' => 0, 'inserted_records' => 0, 'updated_records' => 0,
            'skipped_records' => 0, 'duplicate_count' => 0, 'error_count' => 0,
        ]);
        app(GeoImportService::class)->runBatch($import, new LocalPackageProvider($path), 2000);

        return $import->fresh();
    }

    private function unitCount(): int
    {
        return AdministrativeUnit::where('country_id', $this->country->id)->count();
    }

    public function test_rollback_deletes_inserted_rows(): void
    {
        $import = $this->runPackage([
            ['level' => 1, 'code' => 'RB-D', 'name' => 'Divvy'],
            ['level' => 2, 'code' => 'RB-D-T', 'name' => 'Disty', 'parent_code' => 'RB-D'],
        ]);
        $this->assertSame(2, $this->unitCount());

        $result = app(GeoImportService::class)->rollback($import);

        $this->assertSame(2, $result['deleted']);
        $this->assertSame(0, $this->unitCount());
        $this->assertSame('rolled_back', $import->fresh()->status);
    }

    public function test_rollback_restores_updated_rows(): void
    {
        $this->runPackage([
            ['level' => 1, 'code' => 'RB-D', 'name' => 'Divvy'],
            ['level' => 2, 'code' => 'RB-D-T', 'name' => 'Disty', 'parent_code' => 'RB-D'],
        ]);
        $second = $this->runPackage([
            ['level' => 1, 'code' => 'RB-D', 'name' => 'Divvy'],
            ['level' => 2, 'code' => 'RB-D-T', 'name' => 'RENAMED', 'parent_code' => 'RB-D'],
        ]);

        $result = app(GeoImportService::class)->rollback($second);

        $this->assertSame(1, $result['restored']);
        $this->assertSame('Disty', AdministrativeUnit::where('country_id', $this->country->id)->where('code', 'RB-D-T')->value('name'));
    }

    public function test_clear_country_removes_tree(): void
    {
        $this->runPackage([
            ['level' => 1, 'code' => 'RB-D', 'name' => 'Divvy'],
            ['level' => 2, 'code' => 'RB-D-T', 'name' => 'Disty', 'parent_code' => 'RB-D'],
        ]);

        $preview = app(GeoImportService::class)->clearPreview($this->country);
        $this->assertSame(2, $preview['total']);

        $result = app(GeoImportService::class)->clearCountry($this->country);

        $this->assertSame(0, $this->unitCount());
        $this->assertSame(1, $result['deleted'][1]);
        $this->assertSame(1, $result['deleted'][2]);
    }
}
