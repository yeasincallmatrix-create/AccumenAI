<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\TenantDriveConnection;
use App\Services\Backup\ManifestService;
use App\Services\Backup\TenantLockService;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ConcurrencyStressTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_config_defaults()
    {
        $this->assertEquals(3660, config('backup.lock_ttl_seconds'));
        $this->assertEquals(0, config('backup.lock_wait_seconds'));
        $this->assertTrue(config('backup.validate_before_commit'));
    }

    public function test_lock_acquire_and_release()
    {
        $svc = app(TenantLockService::class);
        $lock = $svc->acquire(999001, 'backup');
        $this->assertNotNull($lock);
        $this->assertTrue($svc->isLocked(999001));

        $svc->release($lock, 999001);
        $this->assertFalse($svc->isLocked(999001));

        // Note-4: isLocked() must not leave a stale lock behind —
        // 100 peeks must not poison a subsequent acquire.
        for ($i = 0; $i < 100; $i++) {
            $this->assertFalse($svc->isLocked(999001));
        }
        $again = $svc->acquire(999001, 'backup');
        $this->assertNotNull($again, 'acquire must still work after 100 isLocked() peeks');
        $svc->release($again, 999001);
    }

    public function test_second_acquire_fails_while_locked()
    {
        $svc = app(TenantLockService::class);
        $lock1 = $svc->acquire(999002, 'backup');
        $this->assertNotNull($lock1);

        // Second attempt (different operation, same tenant) → null
        $lock2 = $svc->acquire(999002, 'restore');
        $this->assertNull($lock2);

        $svc->release($lock1, 999002);
    }

    public function test_different_tenants_dont_block_each_other()
    {
        $svc = app(TenantLockService::class);
        $lockA = $svc->acquire(999003, 'backup');
        $lockB = $svc->acquire(999004, 'backup');

        $this->assertNotNull($lockA);
        $this->assertNotNull($lockB);

        $svc->release($lockA, 999003);
        $svc->release($lockB, 999004);
    }

    public function test_concurrent_dispatch_only_one_succeeds()
    {
        $tenantId = 999005;
        $svc = app(TenantLockService::class);

        // Simulate 10 parallel acquire attempts
        $acquired = 0;
        $locks = [];

        for ($i = 0; $i < 10; $i++) {
            $lock = $svc->acquire($tenantId, 'backup');
            if ($lock) {
                $acquired++;
                $locks[] = $lock;
            }
        }

        $this->assertEquals(1, $acquired, 'Only 1 acquire should succeed');

        foreach ($locks as $l) {
            $svc->release($l, $tenantId);
        }
    }

    public function test_manifest_validation_rejects_bad_data()
    {
        $svc = app(ManifestService::class);
        $reflection = new \ReflectionClass($svc);
        $method = $reflection->getMethod('validateManifest');
        $method->setAccessible(true);

        // Missing 'tables'
        $bad1 = ['backup_id' => 1, 'tenant_id' => 1, 'created_at' => now()->toIso8601String()];
        try {
            $method->invoke($svc, $bad1, '{}', 'sha');
            $this->fail('Should have thrown for missing tables');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('tables', $e->getMessage());
        }

        // Bad chunk structure (missing hash + drive_file_id)
        $bad2 = [
            'backup_id' => 1, 'tenant_id' => 1, 'created_at' => now()->toIso8601String(),
            'tables' => ['users' => ['hash' => 'x', 'chunks' => [['size' => 100]]]],
        ];
        try {
            $method->invoke($svc, $bad2, '{}', 'sha');
            $this->fail('Should have thrown for bad chunk');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('chunk', $e->getMessage());
        }
    }

    public function test_manifest_atomic_rollback_on_validation_failure()
    {
        $tenantId = 999006;
        $backup = Backup::create([
            'tenant_id'     => $tenantId,
            'owner_user_id' => 1,
            'filename'      => 'test-atomic-' . uniqid(),
            'status'        => 'pending',
            'destination'   => 'drive',
        ]);

        $conn = TenantDriveConnection::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'connected_by_user_id' => 1,
                'google_user_email'    => 'test@example.com',
                'google_user_id'       => '999',
                'refresh_token'        => 'fake',
                'drive_folder_id'      => 'fake',
                'connected_at'         => now(),
            ]
        );

        // Malformed manifest → must throw BEFORE any DB/Drive write
        $svc = app(ManifestService::class);
        $enc = app(\App\Services\Backup\EncryptionService::class);

        try {
            $svc->saveManifest($backup, $conn, ['bad' => 'data'], $enc);
            $this->fail('Should have thrown');
        } catch (\Throwable $e) {
            // Expected
        }

        $this->assertDatabaseMissing('backup_manifests', ['backup_id' => $backup->id]);

        // Cleanup
        $backup->delete();
        $conn->delete();
    }
}
