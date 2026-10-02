<?php

namespace Tests\Feature;

use App\Contracts\DriveStorageInterface;
use App\Models\Backup;
use App\Services\Backup\BackupService;
use Illuminate\Support\Carbon;
use Tests\Support\FakeDriveService;
use Tests\TestCase;

/**
 * Retention race regression (incident 2026-10-02):
 *
 * cleanupPreviousBackups() used to delete EVERY row of the tenant except the
 * backup that had just completed — including pending/uploading rows whose
 * BackupJob was still sitting in the queue. Those jobs then aborted with
 * "backup not found" and the user's queued backups silently disappeared
 * (incident: backups 33 and 34 pruned by keep_count=1 while their jobs ran
 * 1 second later).
 *
 * Fix: retention only ever considers rows with status='completed'.
 */
class BackupRetentionRaceTest extends TestCase
{
    private const TENANT_ID = 900301;

    protected function setUp(): void
    {
        parent::setUp();

        // Same swap as BackupKeepLatestTest: no Google calls, and the
        // cleanup path only reaches Drive for rows carrying a drive_file_id
        // (our fixtures never set one).
        $this->app->instance(DriveStorageInterface::class, new FakeDriveService());

        Backup::where('tenant_id', self::TENANT_ID)->delete();
    }

    protected function tearDown(): void
    {
        Backup::where('tenant_id', self::TENANT_ID)->delete();
        parent::tearDown();
    }

    public function test_retention_does_not_delete_pending_backups(): void
    {
        config(['backup.keep_backups_per_tenant' => 1]);

        $oldCompleted  = $this->makeBackup('completed', now()->subDay(), now()->subDay());
        $pending       = $this->makeBackup('pending', now()->subMinutes(20));
        $uploading     = $this->makeBackup('uploading', now()->subMinutes(10));
        $failed        = $this->makeBackup('failed', now()->subHour(), now()->subHour(), 'boom');
        $justCompleted = $this->makeBackup('completed', now(), now());

        $this->runCleanup($justCompleted->id);

        // Rows whose BackupJob may still be queued MUST survive.
        $this->assertDatabaseHas('backups', ['id' => $pending->id, 'status' => 'pending']);
        $this->assertDatabaseHas('backups', ['id' => $uploading->id, 'status' => 'uploading']);

        // Failed rows are kept too — they are the only debugging record.
        $this->assertDatabaseHas('backups', ['id' => $failed->id, 'status' => 'failed']);

        // keep=1 still prunes the OLDER COMPLETED backup (legacy keep-1).
        $this->assertDatabaseMissing('backups', ['id' => $oldCompleted->id]);

        // The backup that triggered cleanup is never a candidate itself.
        $this->assertDatabaseHas('backups', ['id' => $justCompleted->id]);
    }

    public function test_retention_prunes_only_oldest_completed_rows(): void
    {
        config(['backup.keep_backups_per_tenant' => 1]);

        // Three finalized backups: newest survives, the two older ones go.
        $oldest  = $this->makeBackup('completed', now()->subDays(3), now()->subDays(3));
        $older   = $this->makeBackup('completed', now()->subDays(2), now()->subDays(2));
        $newest  = $this->makeBackup('completed', now()->subDay(), now()->subDay());
        $pending = $this->makeBackup('pending', now()->subMinutes(5));

        $this->runCleanup($newest->id);

        $this->assertDatabaseMissing('backups', ['id' => $oldest->id]);
        $this->assertDatabaseMissing('backups', ['id' => $older->id]);
        $this->assertDatabaseHas('backups', ['id' => $newest->id]);
        $this->assertDatabaseHas('backups', ['id' => $pending->id]);
    }

    public function test_retention_respects_keep_count_for_completed_rows(): void
    {
        config(['backup.keep_backups_per_tenant' => 3]);

        $oldCompleted  = $this->makeBackup('completed', now()->subDay(), now()->subDay());
        $pending       = $this->makeBackup('pending', now()->subMinutes(15));
        $justCompleted = $this->makeBackup('completed', now(), now());

        $this->runCleanup($justCompleted->id);

        // keep=3 → completed + oldCompleted + pending fit inside the budget;
        // the pending row is never a deletion candidate at all.
        $this->assertDatabaseHas('backups', ['id' => $oldCompleted->id]);
        $this->assertDatabaseHas('backups', ['id' => $pending->id]);
        $this->assertDatabaseHas('backups', ['id' => $justCompleted->id]);
    }

    private function makeBackup(
        string $status,
        Carbon $createdAt,
        ?Carbon $completedAt = null,
        ?string $error = null
    ): Backup {
        $backup = Backup::create([
            'tenant_id'     => self::TENANT_ID,
            'owner_user_id' => 1,
            'filename'      => 'retention-race-' . $status . '-' . bin2hex(random_bytes(4)) . '.enc',
            'status'        => $status,
            'destination'   => 'local',
            'error_message' => $error,
            'completed_at'  => $completedAt,
        ]);

        // created_at is not mass-assignable — order the rows explicitly so
        // the retention query's ORDER BY created_at is deterministic.
        Backup::where('id', $backup->id)->update(['created_at' => $createdAt]);

        return $backup->refresh();
    }

    /**
     * Invoke the private retention routine exactly as executeBackup() does
     * right after a successful run.
     */
    private function runCleanup(int $keepBackupId): void
    {
        $method = new \ReflectionMethod(BackupService::class, 'cleanupPreviousBackups');
        $method->setAccessible(true);
        $method->invoke(app(BackupService::class), self::TENANT_ID, $keepBackupId);
    }
}
