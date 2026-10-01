<?php

namespace Tests\Feature;

use App\Models\Backup;
use Tests\TestCase;

class BackupAutoTest extends TestCase
{
    public function test_auto_command_exists()
    {
        $this->assertTrue(
            collect(\Artisan::all())->has('backup:auto')
        );
    }

    public function test_auto_dry_run_lists_tenants_without_backup()
    {
        $before = Backup::count();

        $this->artisan('backup:auto', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertEquals($before, Backup::count(), 'Dry-run must not create backups');
    }

    public function test_cleanup_enforces_max_backups()
    {
        $tenantId = 999999;

        for ($i = 0; $i < 7; $i++) {
            $b = Backup::create([
                'tenant_id'     => $tenantId,
                'owner_user_id' => 1,
                'filename'      => "test-max-{$i}-" . uniqid() . '.enc',
                'file_hmac'     => str_repeat('a', 64),
                'status'        => 'completed',
            ]);
            // created_at is not mass-assignable — set via query builder
            \DB::table('backups')->where('id', $b->id)
                ->update(['created_at' => now()->subDays($i)]);
        }

        $this->artisan('backup:cleanup', ['--tenant' => $tenantId]);

        $remaining = Backup::where('tenant_id', $tenantId)->count();
        $this->assertEquals(5, $remaining, 'Should keep only 5 latest');

        Backup::where('tenant_id', $tenantId)->delete();
    }

    public function test_cleanup_30_day_retention()
    {
        $tenantId = 999998;

        $old = Backup::create([
            'tenant_id'     => $tenantId,
            'owner_user_id' => 1,
            'filename'      => 'age-test-' . uniqid() . '.enc',
            'file_hmac'     => str_repeat('b', 64),
            'status'        => 'completed',
        ]);
        \DB::table('backups')->where('id', $old->id)
            ->update(['created_at' => now()->subDays(35)]);

        $this->artisan('backup:cleanup');

        $this->assertDatabaseMissing('backups', ['id' => $old->id]);
    }

    public function test_cleanup_keeps_backups_within_retention_and_max()
    {
        $tenantId = 999997;

        $recent = Backup::create([
            'tenant_id'     => $tenantId,
            'owner_user_id' => 1,
            'filename'      => 'recent-keep-' . uniqid() . '.enc',
            'file_hmac'     => str_repeat('c', 64),
            'status'        => 'completed',
        ]);

        $this->artisan('backup:cleanup', ['--tenant' => $tenantId]);

        $this->assertDatabaseHas('backups', ['id' => $recent->id]);

        $recent->delete();
    }
}
