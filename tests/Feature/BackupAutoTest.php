<?php

namespace Tests\Feature;

use App\Models\Backup;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BackupAutoTest extends TestCase
{
    use DatabaseTransactions;

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

    public function test_dry_run_resolves_owner_from_membership_table()
    {
        $institute = \App\Models\Institute::create([
            'name'   => 'AutoOwner-' . uniqid(),
            'slug'   => str()->slug('auto-owner-' . uniqid()),
            'status' => 'active',
        ]);

        // No membership yet → dry-run must report NONE for this tenant
        $this->artisan('backup:auto', ['--dry-run' => true])
            ->expectsOutputToContain("{$institute->name} — owner: NONE (would skip)")
            ->assertExitCode(0);

        // Attach owner via institution_user (Membership model's table) —
        // NOT the legacy institute_users table.
        $user = \App\Models\User::create([
            'name'              => 'Auto Owner',
            'email'             => 'auto-owner-' . uniqid() . '@example.test',
            'password_hash'     => \Illuminate\Support\Facades\Hash::make('password'),
            'account_type'      => 'owner',
            'email_verified_at' => now(),
        ]);

        $ownerRole = \App\Models\Role::query()
            ->where('slug', 'institute-owner')
            ->whereNull('institute_id')
            ->firstOrFail();

        \DB::table('institution_user')->insert([
            'user_id'        => $user->id,
            'institution_id' => $institute->id,
            'role_id'        => $ownerRole->id,
            'status'         => 'active',
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);

        $this->artisan('backup:auto', ['--dry-run' => true])
            ->expectsOutputToContain("{$institute->name} — owner: {$user->id}")
            ->assertExitCode(0);

        // Cleanup test rows
        \DB::table('institution_user')->where('user_id', $user->id)->delete();
        $user->delete();
        $institute->delete();
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
        $this->assertEquals(1, $remaining, 'Should keep only 1 latest (keep-1 strategy)');

        Backup::where('tenant_id', $tenantId)->delete();
    }

    public function test_cleanup_age_based_retention()
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
            ->update(['created_at' => now()->subDays(400)]); // > retention 365d

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
