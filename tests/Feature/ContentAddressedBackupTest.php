<?php

namespace Tests\Feature;

use App\Services\Backup\BackupChunkService;
use App\Services\Backup\ManifestService;
use Tests\TestCase;

class ContentAddressedBackupTest extends TestCase
{
    public function test_manifest_checksum_valid(): void
    {
        $svc = app(ManifestService::class);
        $manifest = $svc->buildManifest(1, 1, [
            'users' => ['hash' => 'abc', 'chunks' => [], 'row_count' => 0, 'total_size' => 0],
        ]);

        $this->assertTrue($svc->verifyChecksum($manifest));
    }

    public function test_manifest_checksum_tampered(): void
    {
        $svc = app(ManifestService::class);
        $manifest = $svc->buildManifest(1, 1, [
            'users' => ['hash' => 'abc', 'chunks' => [], 'row_count' => 0, 'total_size' => 0],
        ]);

        // Tamper with table data after checksum was computed
        $manifest['tables']['users']['hash'] = 'xyz';

        $this->assertFalse($svc->verifyChecksum($manifest));
    }

    public function test_adaptive_chunk_size(): void
    {
        $svc = app(BackupChunkService::class);
        $reflection = new \ReflectionClass($svc);
        $method = $reflection->getMethod('determineChunkSize');
        $method->setAccessible(true);

        // Small file → single chunk (0 = no split)
        $this->assertEquals(0, $method->invoke($svc, 1 * 1048576)); // 1 MB
        $this->assertEquals(0, $method->invoke($svc, 4 * 1048576)); // 4 MB

        // Medium → 5 MB chunks
        $this->assertEquals(5 * 1048576, $method->invoke($svc, 50 * 1048576));

        // Large → 20 MB chunks
        $this->assertEquals(20 * 1048576, $method->invoke($svc, 300 * 1048576));

        // Huge → 50 MB chunks
        $this->assertEquals(50 * 1048576, $method->invoke($svc, 1000 * 1048576));
    }

    public function test_config_defaults(): void
    {
        $this->assertEquals(1, config('backup.keep_backups_per_tenant'));
        $this->assertEquals(7, config('backup.trash_grace_days'));
        $this->assertEquals(5, config('backup.chunk_size_small_mb'));
        $this->assertEquals(20, config('backup.chunk_size_medium_mb'));
        $this->assertEquals(50, config('backup.chunk_size_large_mb'));
        $this->assertEquals(5, config('backup.chunk_single_threshold_mb'));
        $this->assertEquals(4, config('backup.manifest_backup_weeks'));
        $this->assertTrue(config('backup.chunk_enabled'));
    }
}
