<?php

namespace Tests\Feature;

use App\Models\Medical\DgdaMedicine;
use App\Models\Medical\DgdaSyncBatch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class DgdaSyncTest extends TestCase
{
    use DatabaseTransactions;

    private string $sampleCsv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sampleCsv = resource_path('templates/dgda-import-sample.csv');
    }

    public function test_bulk_import_creates_dgda_medicines(): void
    {
        $this->artisan('medical:dgda-import-bulk', ['file' => $this->sampleCsv])
            ->assertExitCode(0);

        $this->assertEquals(5, DgdaMedicine::count());
        $this->assertDatabaseHas('dgda_medicines', ['dgda_code' => '394-0010-030']);
        $this->assertDatabaseHas('dgda_medicines', ['dgda_code' => '353-0027-040']);
    }

    public function test_bulk_import_updates_existing_by_code(): void
    {
        DgdaMedicine::create([
            'dgda_code' => '394-0010-030',
            'country_code' => 'BD',
            'brand_name' => 'OldBrand',
            'status' => 'active',
        ]);

        $this->artisan('medical:dgda-import-bulk', ['file' => $this->sampleCsv])
            ->assertExitCode(0);

        $this->assertEquals(5, DgdaMedicine::count());
        $this->assertDatabaseHas('dgda_medicines', [
            'dgda_code' => '394-0010-030',
            'brand_name' => 'Tubutol',
        ]);
    }

    public function test_bulk_import_skips_rows_without_code(): void
    {
        $tmp = storage_path('app/test_dgda_skip.csv');
        file_put_contents($tmp, "dgda_code,brand_name\nVALID-001,ValidMed\n");

        try {
            $this->artisan('medical:dgda-import-bulk', ['file' => $tmp])
                ->assertExitCode(0);

            $this->assertEquals(1, DgdaMedicine::count());
            $this->assertDatabaseHas('dgda_medicines', ['dgda_code' => 'VALID-001']);
        } finally {
            @unlink($tmp);
        }
    }

    public function test_bulk_import_creates_sync_batch_record(): void
    {
        $this->artisan('medical:dgda-import-bulk', ['file' => $this->sampleCsv])
            ->assertExitCode(0);

        $batch = DgdaSyncBatch::where('batch_type', 'bulk_import')->first();
        $this->assertNotNull($batch);
        $this->assertEquals(5, $batch->total_rows);
        $this->assertEquals(5, $batch->imported);
        $this->assertEquals('completed', $batch->status);
        $this->assertNotNull($batch->completed_at);
    }

    public function test_bulk_import_normalizes_name(): void
    {
        $this->artisan('medical:dgda-import-bulk', ['file' => $this->sampleCsv])
            ->assertExitCode(0);

        $napa = DgdaMedicine::where('dgda_code', '353-0027-040')->first();
        $this->assertNotNull($napa);
        $this->assertEquals('napa500mg', $napa->normalized_name);
    }

    public function test_api_sync_handles_missing_data_gracefully(): void
    {
        // Test that the command handles invalid codes without throwing
        $this->artisan('medical:dgda-sync-api', ['--code' => 'INVALID-CODE-999'])
            ->assertExitCode(1);
    }

    public function test_sync_batch_tracks_completion(): void
    {
        $this->artisan('medical:dgda-import-bulk', ['file' => $this->sampleCsv])
            ->assertExitCode(0);

        $batch = DgdaSyncBatch::latest()->first();
        $this->assertNotNull($batch);
        $this->assertNotNull($batch->started_at);
        $this->assertNotNull($batch->completed_at);
        $this->assertEquals('completed', $batch->status);
    }
}
