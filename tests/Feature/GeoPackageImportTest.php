<?php

namespace Tests\Feature;

use App\Geo\Providers\LocalPackageProvider;
use App\Models\AdministrativeLevel;
use App\Models\AdministrativeUnit;
use App\Models\Country;
use App\Models\GeoImport;
use App\Models\PlatformAdmin;
use App\Services\GeoImportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GeoPackageImportTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function platformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'email' => 'geo-import-admin@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function country(string $iso2 = 'US', string $name = 'United States'): Country
    {
        return Country::firstOrCreate(['iso2' => $iso2], [
            'name' => $name,
            'iso3' => strtoupper($iso2).'D',
            'phone_code' => '1',
            'status' => true,
        ]);
    }

    private function level(Country $country, int $number, string $label): AdministrativeLevel
    {
        return AdministrativeLevel::firstOrCreate(
            ['country_id' => $country->id, 'level_number' => $number],
            ['name' => $label, 'slug' => strtolower($country->iso2).'_level_'.$number, 'status' => true]
        );
    }

    private function writeFakePackage(array $lines, string $extension = 'jsonl'): string
    {
        $path = sys_get_temp_dir().'/geo-test-'.uniqid().'.'.$extension;
        file_put_contents($path, implode("\n", $lines));

        return $path;
    }

    private function cleanup(string $path): void
    {
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function test_import_creates_levels_units_and_hierarchy(): void
    {
        $us = $this->country('US', 'United States');
        $this->level($us, 1, 'State');
        $this->level($us, 2, 'County');
        $this->level($us, 3, 'City');

        $path = $this->writeFakePackage([
            '{"level":1,"code":"CA","name":"California","parent_code":null}',
            '{"level":2,"code":"CA.LA","name":"Los Angeles","parent_code":"CA"}',
            '{"level":3,"code":"CA.LA.NE","name":"East LA","parent_code":"CA.LA"}',
        ]);

        try {
            $report = GeoImportService::fromConfig()->import(new LocalPackageProvider($path), $us);

            $this->assertSame('completed', $report['status']);
            $this->assertSame(3, $report['total']);
            $this->assertSame(3, $report['inserted']);
            $this->assertSame(0, $report['updated']);

            $ca = AdministrativeUnit::where('country_id', $us->id)->where('code', 'CA')->first();
            $la = AdministrativeUnit::where('country_id', $us->id)->where('code', 'CA.LA')->first();
            $ne = AdministrativeUnit::where('country_id', $us->id)->where('code', 'CA.LA.NE')->first();

            $this->assertNotNull($ca);
            $this->assertNotNull($la);
            $this->assertNotNull($ne);
            $this->assertNull($ca->parent_id);
            $this->assertSame($ca->id, $la->parent_id);
            $this->assertSame($la->id, $ne->parent_id);
        } finally {
            $this->cleanup($path);
        }
    }

    public function test_reimport_updates_existing_records(): void
    {
        $us = $this->country('US', 'United States');
        $this->level($us, 1, 'State');
        $this->level($us, 2, 'County');

        $path = $this->writeFakePackage([
            '{"level":1,"code":"CA","name":"California"}',
            '{"level":2,"code":"CA.LA","name":"Los Angeles","parent_code":"CA"}',
        ]);

        try {
            GeoImportService::fromConfig()->import(new LocalPackageProvider($path), $us);

            // Re-import with a changed name.
            $path2 = $this->writeFakePackage([
                '{"level":1,"code":"CA","name":"California (Updated)"}',
                '{"level":2,"code":"CA.LA","name":"Los Angeles County","parent_code":"CA"}',
            ]);

            $report = GeoImportService::fromConfig()->import(new LocalPackageProvider($path2), $us);

            $this->assertSame(0, $report['inserted']);
            $this->assertSame(2, $report['updated']);
            $this->assertSame(2, AdministrativeUnit::where('country_id', $us->id)->count());
            $this->assertSame('California (Updated)', AdministrativeUnit::where('country_id', $us->id)->where('code', 'CA')->value('name'));

            $this->cleanup($path2);
        } finally {
            $this->cleanup($path);
        }
    }

    public function test_duplicate_codes_inside_package_are_safe(): void
    {
        $us = $this->country('US', 'United States');
        $this->level($us, 1, 'State');

        $path = $this->writeFakePackage([
            '{"level":1,"code":"CA","name":"California"}',
            '{"level":1,"code":"CA","name":"California Duplicate"}',
            '{"level":1,"code":"TX","name":"Texas"}',
        ]);

        try {
            $report = GeoImportService::fromConfig()->import(new LocalPackageProvider($path), $us);

            $this->assertSame(3, $report['total']);
            $this->assertSame(1, $report['duplicates']);
            $this->assertSame(2, $report['inserted']);
            // Only a single CA exists (insert + duplicate-guard count).
            $this->assertSame(2, AdministrativeUnit::where('country_id', $us->id)->count());
        } finally {
            $this->cleanup($path);
        }
    }

    public function test_invalid_parent_is_reported_and_skipped(): void
    {
        $us = $this->country('US', 'United States');
        $this->level($us, 1, 'State');
        $this->level($us, 2, 'County');

        $path = $this->writeFakePackage([
            '{"level":1,"code":"CA","name":"California"}',
            '{"level":2,"code":"CA.XX","name":"Mystery","parent_code":"NOPE"}',
        ]);

        try {
            $report = GeoImportService::fromConfig()->import(new LocalPackageProvider($path), $us);

            $this->assertSame(1, $report['inserted']);
            $this->assertSame(1, $report['errors']);
            $this->assertStringContainsString('NOPE', (string) $report['error_summary']);
            $this->assertNull(AdministrativeUnit::where('country_id', $us->id)->where('code', 'CA.XX')->first());
        } finally {
            $this->cleanup($path);
        }
    }

    public function test_validate_mode_does_not_write(): void
    {
        $us = $this->country('US', 'United States');
        $this->level($us, 1, 'State');
        $this->level($us, 2, 'County');

        $path = $this->writeFakePackage([
            '{"level":1,"code":"CA","name":"California"}',
            '{"level":2,"code":"CA.LA","name":"Los Angeles","parent_code":"CA"}',
        ]);

        try {
            $report = GeoImportService::fromConfig()->validate(new LocalPackageProvider($path), $us);

            $this->assertSame('validated', $report['status']);
            $this->assertSame(2, $report['total']);
            $this->assertSame(0, AdministrativeUnit::where('country_id', $us->id)->count());
        } finally {
            $this->cleanup($path);
        }
    }

    public function test_validate_catches_invalid_rows(): void
    {
        $us = $this->country('US', 'United States');
        $this->level($us, 1, 'State');

        $path = $this->writeFakePackage([
            '{"level":1,"code":"CA","name":"California"}',
            '{"level":2,"code":"CA.X","name":"Orphan","parent_code":"MISSING"}',
        ]);

        try {
            $report = GeoImportService::fromConfig()->validate(new LocalPackageProvider($path), $us);

            $this->assertSame(1, $report['errors']);
            $this->assertNotNull($report['error_summary']);
        } finally {
            $this->cleanup($path);
        }
    }

    public function test_missing_levels_are_auto_created_from_config(): void
    {
        // New country with NO configured levels — importer creates them from config labels.
        $this->country('IN', 'India');
        $in = Country::where('iso2', 'IN')->first();

        $path = $this->writeFakePackage([
            '{"level":1,"code":"MH","name":"Maharashtra"}',
            '{"level":2,"code":"MH.PU","name":"Pune","parent_code":"MH"}',
        ]);

        try {
            $report = GeoImportService::fromConfig()->import(new LocalPackageProvider($path), $in);

            $this->assertSame(2, $report['inserted']);
            $this->assertSame('State', AdministrativeLevel::where('country_id', $in->id)->where('level_number', 1)->value('name'));
            $this->assertSame('District', AdministrativeLevel::where('country_id', $in->id)->where('level_number', 2)->value('name'));
        } finally {
            $this->cleanup($path);
        }
    }

    public function test_resumable_batch_accumulates_and_completes(): void
    {
        Storage::fake('local');
        $us = $this->country('US', 'United States');
        $this->level($us, 1, 'State');

        $lines = [];
        for ($i = 0; $i < 25; $i++) {
            $lines[] = '{"level":1,"code":"S'.str_pad((string) $i, 2, '0', STR_PAD_LEFT).'","name":"State '.$i.'"}';
        }
        $path = $this->writeFakePackage($lines);

        try {
            $import = GeoImport::create([
                'country_id' => $us->id,
                'filename' => 'resumable.jsonl',
                'file_size' => filesize($path),
                'format' => 'jsonl',
                'status' => 'pending',
                'mode' => 'upsert',
                'created_by' => $this->platformAdmin()->id,
            ]);

            $service = new GeoImportService(chunkSize: 10, recordsPerRequest: 10);

            // Batch 1: first 10 records.
            $r1 = $service->runBatch($import, new LocalPackageProvider($path, 0), 10);
            $this->assertSame('importing', $r1['status']);
            $this->assertSame(10, $import->fresh()->total_records);

            // Batch 2: next 10.
            $r2 = $service->runBatch($import, new LocalPackageProvider($path, 10), 10);
            $this->assertSame('importing', $r2['status']);
            $this->assertSame(20, $import->fresh()->total_records);

            // Batch 3: final 5, finished.
            $r3 = $service->runBatch($import, new LocalPackageProvider($path, 20), 10);
            $this->assertSame('completed', $r3['status']);
            $this->assertSame(25, $import->fresh()->total_records);
            $this->assertSame(25, AdministrativeUnit::where('country_id', $us->id)->count());
            $this->assertNotNull($import->fresh()->completed_at);
        } finally {
            $this->cleanup($path);
        }
    }

    public function test_admin_upload_run_and_poll_flow(): void
    {
        Storage::fake('local');

        $admin = $this->platformAdmin();
        $us = $this->country('US', 'United States');
        $this->level($us, 1, 'State');

        $content = implode("\n", [
            '{"level":1,"code":"CA","name":"California"}',
            '{"level":1,"code":"TX","name":"Texas"}',
            '{"level":1,"code":"NY","name":"New York"}',
        ]);

        // 1. Upload → creates the import row.
        $upload = $this->actingAs($admin, 'platform_admin')->postJson(route('admin.geo.imports.store'), [
            'country_id' => $us->id,
            'mode' => 'upsert',
            'file' => UploadedFile::fake()->createWithContent('states.jsonl', $content),
        ]);
        $upload->assertOk()->assertJsonPath('data.import.status', 'pending');

        $import = GeoImport::where('country_id', $us->id)->firstOrFail();

        // 2. Run batch → processes all 3 records.
        $run = $this->actingAs($admin, 'platform_admin')->postJson(route('admin.geo.imports.run', $import));
        $run->assertOk()->assertJsonPath('data.import.status', 'completed');
        $this->assertSame(3, AdministrativeUnit::where('country_id', $us->id)->count());

        // 3. Status returns the report.
        $this->actingAs($admin, 'platform_admin')
            ->getJson(route('admin.geo.imports.status', $import))
            ->assertOk()
            ->assertJsonPath('data.import.inserted_records', 3);
    }

    public function test_admin_upload_rejects_bad_extension(): void
    {
        Storage::fake('local');
        $admin = $this->platformAdmin();
        $us = $this->country('US', 'United States');

        $this->actingAs($admin, 'platform_admin')->postJson(route('admin.geo.imports.store'), [
            'country_id' => $us->id,
            'file' => UploadedFile::fake()->createWithContent('data.txt', 'nope'),
        ])->assertStatus(422);
    }
}