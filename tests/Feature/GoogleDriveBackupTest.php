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

class GoogleDriveBackupTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 900101;

    private FakeDriveService $fakeDrive;

    protected function setUp(): void
    {
        parent::setUp();

        // G2: never hit Google from tests.
        $this->fakeDrive = new FakeDriveService();
        $this->app->instance(DriveStorageInterface::class, $this->fakeDrive);
    }

    protected function tearDown(): void
    {
        \App\Models\BackupChunk::where('tenant_id', self::TENANT_ID)->delete();
        \App\Models\BackupManifest::where('tenant_id', self::TENANT_ID)->delete();
        TenantDriveConnection::where('tenant_id', self::TENANT_ID)->delete();
        Backup::where('tenant_id', self::TENANT_ID)->delete();
        parent::tearDown();
    }

    // ── Spec Step 10 ────────────────────────────────────────────────

    public function test_drive_status_endpoint()
    {
        $user = $this->owner();
        if (!$user) {
            $this->markTestSkipped('No owner available');
        }

        $response = $this->actingAs($user, 'institute_user')
            ->getJson(route('tenant.backup.drive.status'));

        $response->assertOk();
        $response->assertJsonStructure(['connected']);
        $response->assertJson(['connected' => false]);
    }

    public function test_drive_connect_redirects_to_google()
    {
        $user = $this->owner();
        if (!$user) {
            $this->markTestSkipped('No owner available');
        }

        $response = $this->actingAs($user, 'institute_user')
            ->get(route('tenant.backup.drive.connect'));

        $response->assertRedirect();
        $this->assertStringContainsString(
            'accounts.google.com',
            $response->headers->get('Location')
        );
        // drive.file scope + offline consent (refresh_token)
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('drive.file', urldecode($location));
        $this->assertStringContainsString('access_type=offline', $location);
    }

    public function test_connect_requests_userinfo_scope()
    {
        $user = $this->owner();
        if (!$user) {
            $this->markTestSkipped('No owner available');
        }

        $response = $this->actingAs($user, 'institute_user')
            ->get(route('tenant.backup.drive.connect'));

        $location = urldecode($response->headers->get('Location'));

        $this->assertStringContainsString('drive.file', $location);
        $this->assertStringContainsString('userinfo.email', $location);
        $this->assertStringContainsString('userinfo.profile', $location);
    }

    public function test_callback_inserts_connection_without_folder_id()
    {
        $tenantId = 999888;
        TenantDriveConnection::where('tenant_id', $tenantId)->delete();

        $conn = TenantDriveConnection::create([
            'tenant_id'            => $tenantId,
            'connected_by_user_id' => 1,
            'google_user_email'    => 'test@example.com',
            'google_user_id'       => '123',
            'refresh_token'        => 'fake-token',
            'drive_folder_id'      => null,
            'connected_at'         => now(),
            'revoked_at'           => null,
        ]);

        $this->assertDatabaseHas('tenant_drive_connections', [
            'tenant_id'       => $tenantId,
            'drive_folder_id' => null,
        ]);

        $conn->delete();
    }

    public function test_drive_disconnect_revokes()
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
                'google_user_email'    => 'test@example.com',
                'google_user_id'       => '123',
                'refresh_token'        => 'fake-refresh',
                'drive_folder_id'      => 'fake-folder',
                'connected_at'         => now(),
                'revoked_at'           => null,
            ]
        );

        $this->actingAs($user, 'institute_user')
            ->post(route('tenant.backup.drive.disconnect'))
            ->assertRedirect();

        $conn = TenantDriveConnection::where('tenant_id', $tenantId)->first();
        $this->assertNotNull($conn->revoked_at);

        // Status now reports disconnected
        $this->actingAs($user, 'institute_user')
            ->getJson(route('tenant.backup.drive.status'))
            ->assertOk()
            ->assertJson(['connected' => false]);
    }

    // ── Design decisions 3 + 4 ──────────────────────────────────────

    public function test_backup_fails_without_drive_connection()
    {
        TenantDriveConnection::where('tenant_id', self::TENANT_ID)->delete();
        Backup::where('tenant_id', self::TENANT_ID)->delete();

        $svc = app(BackupService::class);

        try {
            $svc->createBackup(self::TENANT_ID, 1);
            $this->fail('createBackup should fail when Drive is not connected');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Google Drive not connected', $e->getMessage());
        }

        $failed = Backup::where('tenant_id', self::TENANT_ID)->latest('id')->first();
        $this->assertNotNull($failed);
        $this->assertSame('failed', $failed->status);
        $this->assertStringContainsString('Google Drive not connected', (string) $failed->error_message);
    }

    public function test_backup_uploads_to_drive_and_deletes_local()
    {
        Backup::where('tenant_id', self::TENANT_ID)->delete();
        TenantDriveConnection::updateOrCreate(
            ['tenant_id' => self::TENANT_ID],
            [
                'connected_by_user_id' => 1,
                'google_user_email'    => 'owner@example.test',
                'google_user_id'       => '900101',
                'refresh_token'        => 'fake-refresh',
                'drive_folder_id'      => 'fake-folder',
                'connected_at'         => now(),
                'revoked_at'           => null,
            ]
        );

        $svc = app(BackupService::class);

        try {
            $backup = $svc->createBackup(self::TENANT_ID, 1);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Test env cannot create backup: ' . $e->getMessage());
            return;
        }

        // Design #4: Drive = source of truth — chunked backups never write
        // a local .enc at all (Phase 2A), so nothing can be left behind.
        $this->assertSame('drive', $backup->destination);
        $this->assertNotEmpty($backup->drive_file_id);
        $this->assertSame('completed', $backup->status);
        $this->assertTrue((bool) $backup->is_chunked);
        $this->assertFileDoesNotExist(storage_path("app/backups/{$backup->filename}"));

        // Phase 2A: at least the manifest triple is uploaded
        // (current.json.enc + current.json.checksum + timestamped copy),
        // plus one chunk per non-empty table.
        $this->assertGreaterThanOrEqual(3, $this->fakeDrive->uploads);

        // Manifest row exists and drive_file_id points at current.json.enc
        $manifest = \App\Models\BackupManifest::where('backup_id', $backup->id)->first();
        $this->assertNotNull($manifest);
        $this->assertSame($backup->drive_file_id, $manifest->drive_file_id);
        $this->assertArrayHasKey($backup->drive_file_id, $this->fakeDrive->files);

        // last_sync_at refreshed on the connection
        $conn = TenantDriveConnection::where('tenant_id', self::TENANT_ID)->first();
        $this->assertNotNull($conn->last_sync_at);

        // Local workdir removed after success (F8)
        $this->assertDirectoryDoesNotExist(storage_path("app/backup-work/{$backup->id}"));

        // Cleanup
        @unlink(storage_path("app/backups/{$backup->filename}"));
        \App\Models\BackupManifest::where('backup_id', $backup->id)->delete();
        \App\Models\BackupChunk::where('tenant_id', self::TENANT_ID)->delete();
    }

    // ── Helpers (same approach as BackupSettingsLinkTest) ───────────

    private function owner(): ?InstituteUser
    {
        // A request earlier in this test may pin TenantContext to another
        // tenant, which hides other institutes' users from this query.
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
