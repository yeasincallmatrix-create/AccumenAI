<?php

namespace Tests\Feature;

use App\Models\RestoreLog;
use App\Models\RestorePreview;
use App\Models\RestoreRollback;
use App\Services\Backup\RestoreRollbackService;
use App\Services\Backup\SmartDiffCalculator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SmartRestoreTest extends TestCase
{
    use DatabaseTransactions;

    private function tenantWithParties(): int
    {
        $tenantId = DB::table('parties')->value('institute_id');
        $this->assertNotNull($tenantId, 'No tenant with parties rows found');

        return (int) $tenantId;
    }

    public function test_services_exist(): void
    {
        $this->assertTrue(class_exists(SmartDiffCalculator::class));
        $this->assertTrue(class_exists(\App\Services\Backup\RestorePreviewService::class));
        $this->assertTrue(class_exists(RestoreRollbackService::class));
        $this->assertTrue(class_exists(RestorePreview::class));
        $this->assertTrue(class_exists(RestoreRollback::class));
        $this->assertTrue(class_exists(\App\Models\RestoreAudit::class));
    }

    public function test_preview_model_expiry_and_confirmation(): void
    {
        $preview = RestorePreview::create([
            'tenant_id'         => 999999,
            'backup_id'         => 1,
            'user_id'           => 1,
            'mode'              => 'smart',
            'diff'              => [],
            'total_insert'      => 0,
            'total_update'      => 0,
            'total_soft_delete' => 0,
            'total_kept'        => 0,
            'expires_at'        => now()->subMinute(),
        ]);

        $this->assertTrue($preview->isExpired());
        $this->assertFalse($preview->isConfirmed());
        $this->assertFalse($preview->isUsable());

        $preview->forceFill(['expires_at' => now()->addMinutes(10)])->save();
        $this->assertTrue($preview->isUsable());

        $preview->forceFill(['confirmed_at' => now()])->save();
        $this->assertTrue($preview->isConfirmed());
        $this->assertFalse($preview->isUsable());

        $preview->delete();
    }

    public function test_rollback_token_and_usability(): void
    {
        $token = RestoreRollback::generateToken();
        $this->assertSame(64, strlen($token));

        $rollback = RestoreRollback::create([
            'tenant_id'      => 999999,
            'restore_log_id' => 0,
            'rollback_token' => $token,
            'snapshot_data'  => [],
            'total_rows'     => 0,
            'expires_at'     => now()->addDay(),
        ]);

        $this->assertTrue($rollback->isUsable());

        $rollback->forceFill(['used_at' => now()])->save();
        $this->assertFalse($rollback->isUsable());

        $rollback->delete();
    }

    public function test_diff_empty_input_returns_array(): void
    {
        $diff = app(SmartDiffCalculator::class)
            ->computeDiff(999999, [], now()->toIso8601String());

        $this->assertIsArray($diff);
        $this->assertEmpty($diff);
    }

    /**
     * ⭐ Core rule: rows created AFTER the backup snapshot are never deleted.
     */
    public function test_new_data_created_after_backup_is_kept(): void
    {
        $tenantId = $this->tenantWithParties();
        $backupTime = now()->subDays(5);

        $oldId = DB::table('parties')->insertGetId([
            'institute_id' => $tenantId,
            'name'         => 'OLD ' . uniqid(),
            'phone'        => 'old' . substr(uniqid(), -8),
            'type'         => 'customer',
            'created_at'   => $backupTime->copy()->subDays(5),
            'updated_at'   => now(),
        ]);

        $newId = DB::table('parties')->insertGetId([
            'institute_id' => $tenantId,
            'name'         => 'NEW ' . uniqid(),
            'phone'        => 'new' . substr(uniqid(), -8),
            'type'         => 'customer',
            'created_at'   => $backupTime->copy()->addDay(),
            'updated_at'   => now(),
        ]);

        $targets = app(SmartDiffCalculator::class)->targetPks(
            $tenantId,
            'parties',
            [],               // empty backup => everything is "missing"
            $backupTime
        );

        $this->assertContains($oldId, $targets['soft_delete'], 'Row from before the backup must be soft-deleted');
        $this->assertNotContains($newId, $targets['soft_delete'], 'New data must NEVER be deleted');
        $this->assertGreaterThan(0, $targets['kept'], 'New row must be counted as kept');

        DB::table('parties')->whereIn('id', [$oldId, $newId])->delete();
    }

    public function test_row_missing_from_backup_is_recovered(): void
    {
        $tenantId = $this->tenantWithParties();

        $ghostId = 999999999;

        $targets = app(SmartDiffCalculator::class)->targetPks(
            $tenantId,
            'parties',
            [['id' => $ghostId, 'name' => 'Ghost', 'institute_id' => $tenantId]],
            now()->subDay()
        );

        $this->assertContains($ghostId, $targets['insert'], 'Row present only in backup must be re-inserted');
        $this->assertNotContains($ghostId, $targets['soft_delete']);
    }

    public function test_table_without_deleted_at_never_soft_deletes(): void
    {
        $tenantId = $this->tenantWithParties();

        $wardId = DB::table('wards')->insertGetId([
            'institute_id' => $tenantId,
            'name'         => 'W ' . substr(uniqid(), -6),
            'type'         => 'general',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $bedId = DB::table('beds')->insertGetId([
            'institute_id' => $tenantId,
            'ward_id'      => $wardId,
            'bed_number'   => 'T' . substr(uniqid(), -6),
            'status'       => 'available',
            'created_at'   => now()->subDays(30),
            'updated_at'   => now(),
        ]);

        $targets = app(SmartDiffCalculator::class)->targetPks(
            $tenantId,
            'beds',
            [],
            now()->subDays(7)
        );

        $this->assertEmpty($targets['soft_delete'], 'Table without deleted_at must never soft-delete');
        $this->assertNotContains($bedId, $targets['soft_delete']);

        DB::table('beds')->where('id', $bedId)->delete();
        DB::table('wards')->where('id', $wardId)->delete();
    }

    /**
     * ⭐ Execution-level check: applySmartMode() must keep new data, recover
     * backup rows and soft-delete only rows that existed before the snapshot.
     */
    public function test_apply_smart_mode_keeps_new_data(): void
    {
        $tenantId = $this->tenantWithParties();
        $backupTime = now()->subDays(5);

        $oldId = DB::table('parties')->insertGetId([
            'institute_id' => $tenantId,
            'name'         => 'SMART-OLD ' . uniqid(),
            'phone'        => 'so' . substr(uniqid(), -8),
            'type'         => 'customer',
            'created_at'   => $backupTime->copy()->subDays(10),
            'updated_at'   => now(),
        ]);

        $newId = DB::table('parties')->insertGetId([
            'institute_id' => $tenantId,
            'name'         => 'SMART-NEW ' . uniqid(),
            'phone'        => 'sn' . substr(uniqid(), -8),
            'type'         => 'customer',
            'created_at'   => $backupTime->copy()->addDays(2),
            'updated_at'   => now(),
        ]);

        $ghostId = 888888881;

        $method = new \ReflectionMethod(\App\Services\Backup\RestoreService::class, 'applySmartMode');
        $method->setAccessible(true);

        $result = $method->invoke(
            app(\App\Services\Backup\RestoreService::class),
            $tenantId,
            'parties',
            [
                [
                    'id'           => $ghostId,
                    'institute_id' => $tenantId,
                    'name'         => 'Recovered ' . uniqid(),
                    'type'         => 'customer',
                    'created_at'   => $backupTime->copy()->subDays(6)->toDateTimeString(),
                ],
            ],
            $backupTime,
            0
        );

        // Recovered from backup
        $this->assertSame(1, $result['inserted']);
        $this->assertDatabaseHas('parties', ['id' => $ghostId]);

        // Soft-deleted: existed before the snapshot, absent from it
        $this->assertGreaterThanOrEqual(1, $result['soft_deleted']);
        $this->assertNotNull(
            DB::table('parties')->where('id', $oldId)->value('deleted_at'),
            'Old row must be soft-deleted'
        );

        // ⭐ NEVER touched
        $this->assertNull(
            DB::table('parties')->where('id', $newId)->value('deleted_at'),
            'New data must never be soft-deleted'
        );
        $this->assertStringStartsWith(
            'SMART-NEW',
            (string) DB::table('parties')->where('id', $newId)->value('name')
        );
        $this->assertGreaterThanOrEqual(1, $result['kept']);
    }

    /**
     * Gate D: smart mode writes restore_rollbacks (never the legacy file path).
     */
    public function test_rollback_roundtrip_restores_pre_image(): void
    {
        $tenantId = $this->tenantWithParties();

        $id = DB::table('parties')->insertGetId([
            'institute_id' => $tenantId,
            'name'         => 'ROLLBACK ' . uniqid(),
            'phone'        => 'rb' . substr(uniqid(), -8),
            'type'         => 'customer',
            'created_at'   => now()->subDays(20),
            'updated_at'   => now(),
        ]);

        $service = app(RestoreRollbackService::class);

        // 1. capture BEFORE mutation
        $rows = $service->captureRows($tenantId, 'parties', [$id]);
        $this->assertCount(1, $rows);

        // 2. persist snapshot
        $rollback = $service->snapshotForRollback($tenantId, 0, ['parties' => $rows]);
        $this->assertNotNull($rollback);
        $this->assertSame(1, $rollback->total_rows);
        $this->assertNull($rollback->used_at);

        // 3. mutate (simulate smart restore soft-deleting it)
        DB::table('parties')->where('id', $id)->update(['deleted_at' => now()]);
        $this->assertNotNull(DB::table('parties')->where('id', $id)->value('deleted_at'));

        // 4. rollback -> pre-image restored
        $result = $service->rollback($rollback);
        $this->assertSame(1, $result['restored']);
        $this->assertNull(
            DB::table('parties')->where('id', $id)->value('deleted_at'),
            'Rollback must clear deleted_at'
        );
        $this->assertNotNull($rollback->fresh()->used_at);

        // 5. cannot roll back twice
        $this->assertFalse($rollback->fresh()->isUsable());

        DB::table('parties')->where('id', $id)->delete();
        $rollback->delete();
    }

    public function test_restore_log_accepts_smart_mode(): void
    {
        $log = RestoreLog::create([
            'tenant_id'  => 1,
            'backup_id'  => 1,
            'user_id'    => 1,
            'mode'       => 'smart',
            'status'     => 'pending',
        ]);

        $this->assertSame('smart', $log->fresh()->mode);
        $this->assertSame('merge', \App\Services\Backup\RestoreService::MODE_MERGE);
        $this->assertSame('smart', \App\Services\Backup\RestoreService::MODE_SMART);
    }
}
