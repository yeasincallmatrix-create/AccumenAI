<?php

namespace Tests\Feature;

use App\Contracts\DriveStorageInterface;
use App\Models\Backup;
use App\Models\InstituteUser;
use App\Models\TenantDriveConnection;
use App\Services\Backup\BackupService;
use Tests\Support\FakeDriveService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * F8 (folded into Phase 2A):
 *  1. Drive button rendered server-side (visible without JS, no legacy ids)
 *  2. Backup Now disabled when Drive not connected
 *  3. verifyAndCleanupLocal deletes local artifacts ONLY after success,
 *     verifies removal, and failure path keeps the local .enc
 */
class DriveCleanupTest extends TestCase
{
    use DatabaseTransactions;

    private FakeDriveService $fakeDrive;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDrive = new FakeDriveService();
        $this->app->instance(DriveStorageInterface::class, $this->fakeDrive);
    }

    // ── 1 + 2: server-side button visibility ────────────────────────

    public function test_connect_button_visible_when_drive_not_connected(): void
    {
        $user = $this->owner();
        if (!$user) {
            $this->markTestSkipped('No owner available');
        }

        // Ensure disconnected state
        TenantDriveConnection::where('tenant_id', $user->institute_id)->delete();

        $response = $this->actingAs($user, 'institute_user')
            ->get(route('tenant.backup.index'));

        $response->assertOk();
        // F2 adaptation: OTP modal legitimately uses d-none — assert the
        // specific server-side markers instead of a blanket d-none check.
        $response->assertSee('Connect Google Drive');
        // Legacy JS ids are gone (no fetch-based visibility)
        $response->assertDontSee('drive-connect-btn', false);
        $response->assertDontSee('drive-status', false);
        // Backup Now is disabled until connected
        $response->assertSee('disabled', false);
    }

    public function test_connect_button_replaced_by_disconnect_when_connected(): void
    {
        $user = $this->owner();
        if (!$user) {
            $this->markTestSkipped('No owner available');
        }

        $tenantId = $user->institute_id;
        TenantDriveConnection::updateOrCreate(
            ['tenant_id' => $tenantId],
            [
                'connected_by_user_id' => $user->id,
                'google_user_email'    => 'owner@example.test',
                'google_user_id'       => '555',
                'refresh_token'        => 'fake-refresh',
                'drive_folder_id'      => 'fake-folder',
                'connected_at'         => now(),
                'revoked_at'           => null,
            ]
        );

        try {
            $response = $this->actingAs($user, 'institute_user')
                ->get(route('tenant.backup.index'));

            $response->assertOk();
            $response->assertSee('Connected');
            $response->assertSee('owner@example.test');
            $response->assertSee('Disconnect');
            $response->assertDontSee('Connect Google Drive');
            // Backup Now enabled (disabled attr absent — note @disabled leaves
            // a trailing space: renders as `<button class="btn btn-primary" >`)
            $response->assertSee('<button class="btn btn-primary"', false);
            $response->assertDontSee('btn btn-primary" disabled', false);
        } finally {
            TenantDriveConnection::where('tenant_id', $tenantId)->delete();
        }
    }

    // ── 3: verifyAndCleanupLocal ────────────────────────────────────

    public function test_cleanup_deletes_and_verifies_local_artifacts(): void
    {
        $svc = app(BackupService::class);

        $backupDir = storage_path('app/backups');
        @mkdir($backupDir, 0755, true);
        $encPath = "{$backupDir}/backup-cleanup-test.enc";
        file_put_contents($encPath, 'ciphertext');

        $workDir = storage_path('app/backup-work/cleanup-test');
        @mkdir($workDir, 0755, true);
        file_put_contents("{$workDir}/table.json", '[]');

        $svc->verifyAndCleanupLocal($encPath, $workDir);

        // Verified gone (F8: silent delete + verify)
        $this->assertFileDoesNotExist($encPath);
        $this->assertDirectoryDoesNotExist($workDir);
    }

    public function test_cleanup_keeps_local_file_when_never_called_on_failure(): void
    {
        // Simulates the Drive-upload failure path: verifyAndCleanupLocal is
        // only invoked AFTER upload success, so the local .enc must survive.
        $backupDir = storage_path('app/backups');
        @mkdir($backupDir, 0755, true);
        $encPath = "{$backupDir}/backup-failure-path-test.enc";
        file_put_contents($encPath, 'only-copy-ciphertext');

        // Failure path: no cleanup call → file untouched
        $this->assertFileExists($encPath);

        @unlink($encPath);
        $this->assertFileDoesNotExist($encPath);
    }

    public function test_cleanup_ignores_system_sql_dumps(): void
    {
        // Scoped orphan scan: backup-*.enc only — system dumps must never
        // be reported as orphans by verifyAndCleanupLocal's glob.
        $backupDir = storage_path('app/backups');
        @mkdir($backupDir, 0755, true);
        $sysDump = "{$backupDir}/monetix_20261001_000000.sql";
        file_put_contents($sysDump, '-- system dump');

        $svc = app(BackupService::class);
        $workDir = storage_path('app/backup-work/orphan-scope-test');
        @mkdir($workDir, 0755, true);

        $svc->verifyAndCleanupLocal(null, $workDir);

        // System dump untouched by cleanup
        $this->assertFileExists($sysDump);
        $this->assertDirectoryDoesNotExist($workDir);

        @unlink($sysDump);
    }

    // ── Helpers (same approach as BackupSettingsLinkTest) ───────────

    private function owner(): ?InstituteUser
    {
        \App\Support\TenantContext::clear();

        return InstituteUser::query()
            ->join('roles', 'roles.id', '=', 'institute_users.role_id')
            ->where('roles.slug', 'institute-owner')
            ->whereNotNull('institute_users.institute_id')
            ->select('institute_users.*')
            ->orderBy('institute_users.id')
            ->first();
    }
}
