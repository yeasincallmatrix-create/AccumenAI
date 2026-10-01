<?php

namespace Tests\Feature;

use App\Models\Backup;
use Tests\TestCase;

class BackupCleanupTest extends TestCase
{
    public function test_old_backups_deleted()
    {
        $old = Backup::create([
            'tenant_id'     => 99999,
            'owner_user_id' => 1,
            'filename'      => 'old-test-' . uniqid() . '.enc',
            'file_hmac'     => str_repeat('a', 64),
            'status'        => 'completed',
        ]);
        // created_at is not mass-assignable — set directly (Bug 2 fix)
        // 35 days > retention (30 days) → must be deleted
        $old->created_at = now()->subDays(35);
        $old->save();

        $this->artisan('backup:cleanup');

        $this->assertDatabaseMissing('backups', ['id' => $old->id]);
    }

    public function test_recent_backups_kept()
    {
        $recent = Backup::create([
            'tenant_id'     => 99999,
            'owner_user_id' => 1,
            'filename'      => 'recent-test-' . uniqid() . '.enc',
            'file_hmac'     => str_repeat('b', 64),
            'status'        => 'completed',
        ]);
        $recent->created_at = now()->subDays(2);
        $recent->save();

        $this->artisan('backup:cleanup');

        $this->assertDatabaseHas('backups', ['id' => $recent->id]);

        $recent->delete();
    }

    public function test_dry_run_does_not_delete()
    {
        $old = Backup::create([
            'tenant_id'     => 99999,
            'owner_user_id' => 1,
            'filename'      => 'dry-test-' . uniqid() . '.enc',
            'file_hmac'     => str_repeat('c', 64),
            'status'        => 'completed',
        ]);
        // 35 days > retention → would be deleted if not for --dry-run
        $old->created_at = now()->subDays(35);
        $old->save();

        $this->artisan('backup:cleanup', ['--dry-run' => true]);

        $this->assertDatabaseHas('backups', ['id' => $old->id]);

        $old->delete();
    }
}
