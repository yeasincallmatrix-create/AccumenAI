<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Services\System\ImportSafetyService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ImportSafetyTest extends TestCase
{
    use DatabaseTransactions;

    public function test_track_import(): void
    {
        $service = app(ImportSafetyService::class);
        $batch = $service->startImport('students', 'test.csv', [
            ['name' => 'A', 'email' => 'a@test.com'],
            ['name' => 'B', 'email' => 'b@test.com'],
        ]);

        $this->assertDatabaseHas('import_batches', [
            'id' => $batch->id,
            'module' => 'students',
            'total_rows' => 2,
        ]);
        $this->assertEquals(2, $batch->rows()->count());
    }

    public function test_rollback_failed_import(): void
    {
        $service = app(ImportSafetyService::class);
        $batch = $service->startImport('students', 'test.csv', [
            ['name' => 'A'],
        ]);
        $token = $batch->getAttribute('raw_token');
        $this->assertNotEmpty($token);

        // Invalid token should throw
        try {
            $service->rollback($batch, 'invalid');
            $this->fail('Should throw');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Invalid', $e->getMessage());
        }

        // Valid rollback
        $result = $service->rollback($batch, $token);
        $this->assertTrue($result);
        $this->assertEquals('rolled_back', $batch->fresh()->status);
    }

    public function test_error_reporting(): void
    {
        $service = app(ImportSafetyService::class);
        $batch = $service->startImport('students', 'test.csv', [
            ['name' => 'A'],
            ['name' => 'B'],
        ]);

        $service->processRow($batch, 1, function ($data) { throw new \Exception('Row 1 failed'); });
        $service->processRow($batch, 2, function ($data) { /* success */ });

        $report = $service->errorReport($batch);
        $this->assertEquals(1, $report['failed_rows']);
        $this->assertStringContainsString('Row 1 failed', $report['errors'][0]['error']);
    }

    public function test_rollback_token_is_hashed(): void
    {
        $service = app(ImportSafetyService::class);
        $batch = $service->startImport('inventory', 'inv.csv', [['sku' => '123']]);

        $this->assertNotEmpty($batch->rollback_token);
        $this->assertEquals(64, strlen($batch->rollback_token)); // sha256
    }

    public function test_complete_marks_status(): void
    {
        $service = app(ImportSafetyService::class);
        $batch = $service->startImport('students', 'test.csv', [['name' => 'A']]);
        $service->processRow($batch, 1, fn($d) => true);
        $completed = $service->complete($batch);

        $this->assertEquals('completed', $completed->status);
    }
}
