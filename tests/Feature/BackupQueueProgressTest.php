<?php

namespace Tests\Feature;

use App\Jobs\BackupJob;
use App\Jobs\RestoreJob;
use App\Models\Backup;
use App\Models\InstituteUser;
use App\Models\RestoreLog;
use App\Models\TenantDriveConnection;
use App\Services\Backup\ProgressService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BackupQueueProgressTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 900201;

    protected function tearDown(): void
    {
        Backup::where('tenant_id', self::TENANT_ID)->delete();
        RestoreLog::where('tenant_id', self::TENANT_ID)->delete();
        TenantDriveConnection::where('tenant_id', self::TENANT_ID)->delete();
        parent::tearDown();
    }

    public function test_backup_job_dispatched_on_store()
    {
        Queue::fake();
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

        $response = $this->actingAs($user, 'institute_user')
            ->postJson(route('tenant.backup.store'));

        $response->assertOk()->assertJson(['success' => true]);
        $backupId = $response->json('backup_id');
        $this->assertNotNull($backupId);

        Queue::assertPushed(BackupJob::class, function ($job) use ($backupId, $user) {
            return $job->backupId === $backupId
                && $job->tenantId !== null
                && $job->recipientEmail === $user->email;
        });

        // Row exists as pending (job faked — never ran)
        $backup = Backup::find($backupId);
        $this->assertSame('pending', $backup->status);
        $this->assertSame('queued', $backup->progress_stage);

        // Cleanup: dispatch would normally keep this row
        $backup->delete();

        // Leave no live conn behind (GoogleDriveBackupTest asserts connected=false)
        TenantDriveConnection::where('tenant_id', $tenantId)->delete();
    }

    public function test_store_fails_fast_without_drive_connection()
    {
        Queue::fake();
        $user = $this->owner();
        if (!$user) {
            $this->markTestSkipped('No owner available');
        }

        $tenantId = $user->institute_id;
        TenantDriveConnection::where('tenant_id', $tenantId)->delete();

        $before = Backup::where('tenant_id', $tenantId)->count();

        $response = $this->actingAs($user, 'institute_user')
            ->postJson(route('tenant.backup.store'));

        $response->assertStatus(422)->assertJson(['success' => false]);
        Queue::assertNotPushed(BackupJob::class);
        $this->assertSame($before, Backup::where('tenant_id', $tenantId)->count());
    }

    public function test_progress_update_throttled()
    {
        $backup = $this->makeBackup(5, 'pending');

        $svc = app(ProgressService::class);

        // First write always applies
        $svc->updateBackup($backup, 50, 'uploading', 'Table: users');
        $backup->refresh();
        $this->assertSame(50, (int) $backup->progress_percent);

        // Same stage, <2% delta → throttled (no write)
        $svc->updateBackup($backup, 51, 'uploading', 'Table: users');
        $backup->refresh();
        $this->assertSame(50, (int) $backup->progress_percent);

        // Stage change → always writes
        $svc->updateBackup($backup, 51, 'finalizing', 'Saving manifest...');
        $backup->refresh();
        $this->assertSame('finalizing', $backup->progress_stage);
        $this->assertSame(51, (int) $backup->progress_percent);
    }

    public function test_backup_progress_endpoint_json()
    {
        $user = $this->owner();
        if (!$user) {
            $this->markTestSkipped('No owner available');
        }

        $backup = $this->makeBackup(42, 'uploading', $user->institute_id, [
            'progress_message' => 'Table: invoices',
            'total_chunks'     => 12,
            'uploaded_chunks'  => 7,
        ]);

        $this->actingAs($user, 'institute_user')
            ->getJson(route('tenant.backup.progress', $backup->id))
            ->assertOk()
            ->assertJson([
                'id'               => $backup->id,
                'status'           => 'uploading',
                'progress_percent' => 42,
                'progress_stage'   => 'uploading',
                'total_chunks'     => 12,
                'uploaded_chunks'  => 7,
            ]);

        $backup->delete();
    }

    public function test_restore_progress_endpoint_json()
    {
        $user = $this->owner();
        if (!$user) {
            $this->markTestSkipped('No owner available');
        }

        $log = RestoreLog::create([
            'tenant_id'        => $user->institute_id,
            'backup_id'        => 1,
            'user_id'          => $user->id,
            'mode'             => 'merge',
            'status'           => 'pending',
            'progress_percent' => 35,
            'progress_stage'   => 'downloading',
            'progress_message' => 'Table: users',
            'total_chunks'     => 8,
            'downloaded_chunks' => 3,
        ]);

        $this->actingAs($user, 'institute_user')
            ->getJson(route('tenant.backup.restore.progress', $log->id))
            ->assertOk()
            ->assertJson([
                'id'                => $log->id,
                'status'            => 'pending',
                'progress_percent'  => 35,
                'progress_stage'    => 'downloading',
                'total_chunks'      => 8,
                'downloaded_chunks' => 3,
            ]);

        $log->delete();
    }

    public function test_config_defaults()
    {
        // Local dev: database; tests: sync (Adaptation 4 — both allowed)
        $this->assertContains(config('queue.default'), ['database', 'sync']);
        $this->assertIsInt(config('backup.download_concurrency'));
        $this->assertGreaterThanOrEqual(1, config('backup.download_concurrency'));
    }

    private function makeBackup(int $percent, string $stage, ?int $tenantId = null, array $extra = []): Backup
    {
        return Backup::create(array_merge([
            'tenant_id'        => $tenantId ?? self::TENANT_ID,
            'owner_user_id'    => 1,
            'filename'         => 'backup-test-' . bin2hex(random_bytes(4)) . '.enc',
            'status'           => 'uploading',
            'is_chunked'       => true,
            'destination'      => 'drive',
            'progress_percent' => $percent,
            'progress_stage'   => $stage,
            'progress_message' => 'Working...',
        ], $extra));
    }

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
