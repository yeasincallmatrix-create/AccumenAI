<?php

namespace Tests\Feature;

use App\Contracts\DriveStorageInterface;
use App\Models\Backup;
use App\Models\RestoreLog;
use App\Models\TenantDriveConnection;
use App\Services\Backup\BackupService;
use App\Services\Backup\RestorePreviewService;
use App\Services\Backup\RestoreService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeDriveService;
use Tests\TestCase;

/**
 * Phase 3 Smart Restore E2E — findings C + D (found on the re-test run):
 *
 *  C. Restore payloads were built from the SELECT * dump, which carries
 *     generated columns. MariaDB rejects assigning them (error 1906), the
 *     row-level try/catch swallowed it, and the row was counted as
 *     skipped/kept — i.e. every row of a table with a generated column
 *     (account_groups, grade_scales, chart_of_accounts, students, ...) was
 *     silently NEVER restored.
 *
 *  D. Preview reports a row as changed only when SmartDiffCalculator says
 *     so, but both restore modes rewrote EVERY existing backup row. The user
 *     approved "update=0" while the restore wrote rows anyway — and those
 *     rows were also missing from the rollback snapshot, so rollback could
 *     not revert them. Tables without deleted_at/created_at additionally
 *     lost their `kept` count on the restore side.
 *
 * Both are asserted end-to-end: preview totals must equal the totals the
 * restore reports, changed rows must actually be written back, identical
 * rows must stay untouched, and the generated-column row must round-trip.
 */
class RestorePreviewParityTest extends TestCase
{
    use DatabaseTransactions;

    private const TENANT_ID = 900102;

    private FakeDriveService $fakeDrive;

    /** updated_at written onto AG3 *after* the backup (identical row otherwise). */
    private string $ag3TouchedAt = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeDrive = new FakeDriveService();
        $this->app->instance(DriveStorageInterface::class, $this->fakeDrive);

        // Restore writes are FK-scoped: account_groups.institute_id must
        // resolve. TEST-ONLY fixture (rolled back with the transaction).
        DB::table('institutes')->insertOrIgnore([
            'id'   => self::TENANT_ID,
            'name' => 'Restore Parity Fixture',
            'slug' => 'restore-parity-fixture-900102',
        ]);

        TenantDriveConnection::updateOrCreate(
            ['tenant_id' => self::TENANT_ID],
            [
                'connected_by_user_id' => 1,
                'google_user_email'    => 'restore-parity@example.test',
                'google_user_id'       => '900102',
                'refresh_token'        => 'fake-refresh-token',
                'drive_folder_id'      => 'fake-folder-900102',
                'connected_at'         => now(),
                'revoked_at'           => null,
            ]
        );
    }

    protected function tearDown(): void
    {
        $snapshotDir = storage_path('app/rollback-snapshots');
        foreach (glob("{$snapshotDir}/rollback-tenant-" . self::TENANT_ID . '-*') ?: [] as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_smart_restore_executes_exactly_what_preview_reported(): void
    {
        $tenantId = self::TENANT_ID;
        [$ag1, $ag3, $as1] = $this->seedRows();

        $backup = app(BackupService::class)->createBackup($tenantId, 1);
        $this->assertNotNull(Backup::find($backup->id), 'Backup must exist');

        [$ag2, $as2] = $this->mutate($ag1, $ag3, $as1);

        $preview = app(RestorePreviewService::class)
            ->compute($tenantId, $backup->id, 1, RestoreService::MODE_SMART);

        $this->assertSame(0, (int) $preview->total_insert, 'no backup row missing from the DB');
        $this->assertSame(2, (int) $preview->total_update, 'AG1 + AS1 changed after the backup');
        $this->assertSame(1, (int) $preview->total_soft_delete, 'AG2 predates the snapshot but is absent');
        $this->assertSame(1, (int) $preview->total_kept, 'AS2 lives in a table without deleted_at');

        $log = RestoreLog::create([
            'tenant_id'      => $tenantId,
            'backup_id'      => $backup->id,
            'user_id'        => 1,
            'status'         => 'pending',
            'mode'           => RestoreService::MODE_SMART,
            'progress_stage' => 'queued',
        ]);

        app(RestoreService::class)
            ->executeRestore($log, $backup->id, $tenantId, 1, RestoreService::MODE_SMART);
        $log->refresh();

        $this->assertSame('completed', $log->status, (string) $log->error_message);
        $affected = (array) $log->records_affected;

        // D: executed == approved (insert / update / soft_delete / kept).
        $this->assertSame((int) $preview->total_insert, $this->sum($affected, 'inserted'));
        $this->assertSame((int) $preview->total_update, $this->sum($affected, 'updated'));
        $this->assertSame((int) $preview->total_soft_delete, $this->sum($affected, 'soft_deleted'));
        $this->assertSame((int) $preview->total_kept, $this->sum($affected, 'kept'));

        // C: the generated-column row really came back (this UPDATE used to
        // die with MariaDB error 1906 and never write anything).
        $restoredAg1 = DB::table('account_groups')->where('id', $ag1)->first();
        $this->assertSame('Backed up group', $restoredAg1->name);
        $this->assertEquals(
            (string) $tenantId,
            (string) $restoredAg1->institute_key,
            'generated column must still hold the server-computed value'
        );
        $this->assertSame(
            '{"rp":"backed-up-value"}',
            DB::table('accounting_settings')->where('id', $as1)->value('settings_value')
        );

        // D: identical rows are not rewritten (updated_at must be untouched).
        $this->assertSame(
            'Untouched group',
            DB::table('account_groups')->where('id', $ag3)->value('name')
        );
        $untouchedAg3 = DB::table('account_groups')->where('id', $ag3)->first();
        $this->assertSame(
            $this->ag3TouchedAt,
            (string) $untouchedAg3->updated_at,
            'an identical backup row must not be rewritten (its post-backup updated_at must survive)'
        );

        // Smart delete + audit trail (account_groups carries deleted_at only;
        // deleted_by/deleted_reason are covered by invoices in the E2E run).
        $ag2Row = DB::table('account_groups')->where('id', $ag2)->first();
        $this->assertNotNull($ag2Row->deleted_at, 'AG2 must be soft-deleted');
        $this->assertSame('Not in the backup', $ag2Row->name, 'soft delete keeps the row readable');

        // AS2 (table without deleted_at) must be reported kept, never touched.
        $this->assertSame(
            2,
            (int) DB::table('accounting_settings')->where('institute_id', self::TENANT_ID)->count(),
            'smart restore must not insert/delete rows of a table without deleted_at'
        );
        $this->assertSame(
            '{"rp":"brand-new-value"}',
            DB::table('accounting_settings')->where('id', $as2)->value('settings_value')
        );
    }

    public function test_merge_restore_writes_changed_rows_only_and_handles_generated_columns(): void
    {
        $tenantId = self::TENANT_ID;
        [$ag1, $ag3, $as1] = $this->seedRows();

        $backup = app(BackupService::class)->createBackup($tenantId, 1);

        [$ag2] = $this->mutate($ag1, $ag3, $as1);

        $preview = app(RestorePreviewService::class)
            ->compute($tenantId, $backup->id, 1, RestoreService::MODE_MERGE);

        $this->assertSame(0, (int) $preview->total_insert);
        $this->assertSame(2, (int) $preview->total_update);

        $log = RestoreLog::create([
            'tenant_id'      => $tenantId,
            'backup_id'      => $backup->id,
            'user_id'        => 1,
            'status'         => 'pending',
            'mode'           => RestoreService::MODE_MERGE,
            'progress_stage' => 'queued',
        ]);

        app(RestoreService::class)
            ->executeRestore($log, $backup->id, $tenantId, 1, RestoreService::MODE_MERGE);
        $log->refresh();

        $this->assertSame('completed', $log->status, (string) $log->error_message);
        $affected = (array) $log->records_affected;

        // D: merge executed exactly the rows the preview called changed.
        $this->assertSame((int) $preview->total_insert, $this->sum($affected, 'inserted'));
        $this->assertSame((int) $preview->total_update, $this->sum($affected, 'updated'));
        $this->assertSame(
            1,
            $this->sum($affected, 'skipped'),
            'only the byte-identical AG3 row may be skipped'
        );

        // C: generated-column row written without error 1906.
        $restoredAg1 = DB::table('account_groups')->where('id', $ag1)->first();
        $this->assertSame('Backed up group', $restoredAg1->name);
        $this->assertSame(
            '{"rp":"backed-up-value"}',
            DB::table('accounting_settings')->where('id', $as1)->value('settings_value')
        );

        // D: identical row untouched.
        $untouchedAg3 = DB::table('account_groups')->where('id', $ag3)->first();
        $this->assertSame('Untouched group', $untouchedAg3->name);
        $this->assertSame(
            $this->ag3TouchedAt,
            (string) $untouchedAg3->updated_at,
            'an identical backup row must not be rewritten'
        );

        // Merge never deletes: AG2 (absent from the backup) stays live.
        $this->assertNull(
            DB::table('account_groups')->where('id', $ag2)->value('deleted_at'),
            'merge mode must not soft-delete rows'
        );
    }

    /**
     * AG1 + AS1 exist in the backup (and are mutated afterwards), AG3 stays
     * byte-identical so the "skip unchanged rows" path is covered.
     *
     * @return array{0:int,1:int,2:int} [ag1, ag3, as1]
     */
    private function seedRows(): array
    {
        $at = $this->seededAt();

        $ag1 = DB::table('account_groups')->insertGetId([
            'institute_id' => self::TENANT_ID,
            'branch_id'    => null,
            'parent_id'    => null,
            'code'         => 'RP-1',
            'name'         => 'Backed up group',
            'category'     => 'asset',
            'created_at'   => $at,
            'updated_at'   => $at,
        ]);

        $ag3 = DB::table('account_groups')->insertGetId([
            'institute_id' => self::TENANT_ID,
            'branch_id'    => null,
            'parent_id'    => null,
            'code'         => 'RP-3',
            'name'         => 'Untouched group',
            'category'     => 'asset',
            'created_at'   => $at,
            'updated_at'   => $at,
        ]);

        $as1 = DB::table('accounting_settings')->insertGetId([
            'institute_id'   => self::TENANT_ID,
            'settings_key'   => 'rp_key',
            'settings_value' => '{"rp":"backed-up-value"}',
            'created_at'     => $at,
            'updated_at'     => $at,
        ]);

        return [$ag1, $ag3, $as1];
    }

    /**
     * Post-backup mutations: AG1/AS1 diverge (preview must count updates),
     * AG2/AS2 are new rows created before the snapshot time (AG2 -> soft
     * delete, AS2 -> kept because accounting_settings has no deleted_at),
     * AG3 only gets a new updated_at — the diff ignores timestamps, so it
     * stays "identical" and a restore that rewrites it (the old behaviour)
     * is caught by the updated_at assertion.
     *
     * @return array{0:int,1:int} [ag2, as2]
     */
    private function mutate(int $ag1, int $ag3, int $as1): array
    {
        DB::table('account_groups')->where('id', $ag1)->update([
            'name'       => 'Renamed after backup',
            'updated_at' => now(),
        ]);
        DB::table('accounting_settings')->where('id', $as1)->update([
            'settings_value' => '{"rp":"changed-after-backup"}',
            'updated_at'     => now(),
        ]);

        $this->ag3TouchedAt = now()->addMinute()->startOfSecond()->toDateTimeString();
        DB::table('account_groups')->where('id', $ag3)->update([
            'updated_at' => $this->ag3TouchedAt,
        ]);

        $ag2 = DB::table('account_groups')->insertGetId([
            'institute_id' => self::TENANT_ID,
            'branch_id'    => null,
            'parent_id'    => null,
            'code'         => 'RP-2',
            'name'         => 'Not in the backup',
            'category'     => 'asset',
            'created_at'   => now()->subDay(),
            'updated_at'   => now()->subDay(),
        ]);

        $as2 = DB::table('accounting_settings')->insertGetId([
            'institute_id'   => self::TENANT_ID,
            'settings_key'   => 'rp_key_2',
            'settings_value' => '{"rp":"brand-new-value"}',
            'created_at'     => now()->subDay(),
            'updated_at'     => now()->subDay(),
        ]);

        return [$ag2, $as2];
    }

    private function seededAt(): string
    {
        return now()->subDays(3)->startOfSecond()->toDateTimeString();
    }

    /**
     * @param  array<string, array<string, int>>  $affected
     */
    private function sum(array $affected, string $key): int
    {
        $total = 0;
        foreach ($affected as $counts) {
            $total += (int) ($counts[$key] ?? 0);
        }

        return $total;
    }
}
