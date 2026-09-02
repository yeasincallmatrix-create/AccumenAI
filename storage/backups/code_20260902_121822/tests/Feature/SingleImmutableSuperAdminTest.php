<?php

namespace Tests\Feature;

use App\Exceptions\SingleSuperAdminViolationException;
use App\Models\Institute;
use App\Models\PlatformAdmin;
use App\Models\PlatformStaff;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SingleImmutableSuperAdminTest extends TestCase
{
    use DatabaseTransactions;

    public function test_exactly_one_super_admin_exists(): void
    {
        $this->assertEquals(1, PlatformAdmin::count());
        $admin = PlatformAdmin::first();
        $this->assertEquals(1, $admin->id);
        $this->assertEquals('yeasinsheikh999@gmail.com', strtolower($admin->email));
        $this->assertTrue((bool) $admin->is_owner);
        $this->assertEquals(1, $admin->singleton_guard);
    }

    public function test_second_super_admin_creation_blocked_via_model(): void
    {
        $this->expectException(SingleSuperAdminViolationException::class);
        PlatformAdmin::create([
            'email' => 'evil2@example.com',
            'password_hash' => Hash::make('password'),
            'first_name' => 'Evil',
            'last_name' => 'Admin',
        ]);
    }

    public function test_second_super_admin_creation_blocked_via_db(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('platform_admins')->insert([
            'singleton_guard' => 1,
            'first_name' => 'Hacker',
            'last_name' => 'Two',
            'email' => 'hacker2@example.com',
            'password_hash' => Hash::make('password'),
            'is_owner' => 1,
            'status' => 'active',
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_promote_staff_to_super_admin_blocked(): void
    {
        $staff = PlatformStaff::create([
            'first_name' => 'Support',
            'last_name' => 'Staff',
            'email' => 'support@example.com',
            'password_hash' => Hash::make('password'),
            'role' => 'support',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        // Attempt to escalate via mass assignment should not create PlatformAdmin
        $staff->fill(['is_owner' => 1, 'singleton_guard' => 1]);
        $this->assertNull($staff->getAttribute('is_owner') ?? null);

        // Direct attempt to create platform admin as staff fails
        try {
            PlatformAdmin::create([
                'email' => 'support-escalated@example.com',
                'password_hash' => Hash::make('password'),
                'is_owner' => 1,
            ]);
            $this->fail('Should have thrown');
        } catch (SingleSuperAdminViolationException $e) {
            $this->assertStringContainsString('already exists', $e->getMessage());
        }
        $this->assertEquals(1, PlatformAdmin::count());
    }

    public function test_mass_assignment_cannot_modify_is_owner(): void
    {
        $admin = PlatformAdmin::first();
        $admin->fill(['is_owner' => 0]);
        // fill should be ignored due to fillable restriction
        $this->assertTrue((bool) $admin->is_owner);
        // Force dirty attempt via direct attribute then save should throw
        $admin->is_owner = false;
        try {
            $admin->save();
            $this->fail('Demotion should be blocked');
        } catch (SingleSuperAdminViolationException $e) {
            $this->assertTrue(true);
        }
        $this->assertTrue((bool) $admin->fresh()->is_owner);
    }

    public function test_crafted_http_cannot_escalate_via_is_owner(): void
    {
        $staff = PlatformStaff::create([
            'first_name' => 'Http',
            'last_name' => 'Attacker',
            'email' => 'http-attacker@example.com',
            'password_hash' => Hash::make('password'),
            'role' => 'support',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $admin = PlatformAdmin::first();
        $this->actingAs($admin, 'platform_admin');
        // Attempt to update staff with is_owner injection
        $response = $this->put(route('admin.platform-staff.update', $staff), [
            'role' => 'finance',
            'is_owner' => 1,
            'singleton_guard' => 1,
            'super_admin' => 1,
        ]);
        $response->assertRedirect();
        $staff->refresh();
        $this->assertEquals('finance', $staff->role);
        // Ensure staff still not owner and no second PlatformAdmin
        $this->assertEquals(1, PlatformAdmin::count());
    }

    public function test_delete_super_admin_blocked(): void
    {
        $admin = PlatformAdmin::first();
        try {
            $admin->delete();
            $this->fail('Delete should be blocked');
        } catch (SingleSuperAdminViolationException $e) {
            $this->assertStringContainsString('cannot be deleted', $e->getMessage());
        }
        $this->assertDatabaseHas('platform_admins', ['id' => 1]);
    }

    public function test_demote_super_admin_blocked(): void
    {
        $admin = PlatformAdmin::first();
        $admin->is_owner = false;
        $this->expectException(SingleSuperAdminViolationException::class);
        $admin->save();
    }

    public function test_replace_super_admin_identity_blocked(): void
    {
        $admin = PlatformAdmin::first();
        $admin->email = 'new-owner@example.com';
        $this->expectException(SingleSuperAdminViolationException::class);
        $admin->save();
    }

    public function test_super_admin_login_pass(): void
    {
        $admin = PlatformAdmin::first();
        // Ensure known password for test
        $admin->password_hash = Hash::make('password');
        $admin->save();
        $this->assertTrue(Hash::check('password', $admin->password_hash) || Hash::check('password', $admin->getAuthPassword()));
        // Guard isolation: platform_admin guard should authenticate
        $this->assertTrue(auth('platform_admin')->attempt(['email' => 'yeasinsheikh999@gmail.com', 'password' => 'password']));
        auth('platform_admin')->logout();
    }

    public function test_staff_can_perform_assigned_task(): void
    {
        $staff = PlatformStaff::create([
            'first_name' => 'Finance',
            'last_name' => 'Staff',
            'email' => 'finance-staff@example.com',
            'password_hash' => Hash::make('password'),
            'role' => 'finance',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->assertTrue($staff->hasPermission('finance.view'));
        $this->assertTrue($staff->hasPermission('finance.manage'));
        $this->assertFalse($staff->hasPermission('institutes.verify'));
    }

    public function test_staff_cannot_perform_unassigned_task(): void
    {
        $staff = PlatformStaff::create([
            'first_name' => 'Support2',
            'last_name' => 'Staff',
            'email' => 'support2@example.com',
            'password_hash' => Hash::make('password'),
            'role' => 'support',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->assertFalse($staff->hasPermission('finance.manage'));
        $this->assertFalse($staff->hasPermission('courses.manage'));
        $this->assertTrue($staff->hasPermission('institutes.view'));
    }

    public function test_normal_institute_user_cannot_auth_as_platform_admin(): void
    {
        $user = User::factory()->create(['email' => 'normal@example.com', 'password_hash' => Hash::make('password')]);
        $this->assertFalse(auth('platform_admin')->attempt(['email' => 'normal@example.com', 'password' => 'password']));
    }

    public function test_concurrent_creation_cannot_produce_two_super_admins(): void
    {
        // Simulate concurrent attempts - second must fail via DB unique
        $firstExists = PlatformAdmin::count() === 1;
        $this->assertTrue($firstExists);
        try {
            DB::table('platform_admins')->insert([
                'singleton_guard' => 1,
                'first_name' => 'Concurrent',
                'last_name' => 'One',
                'email' => 'concurrent1@example.com',
                'password_hash' => Hash::make('password'),
                'is_owner' => 1,
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $this->fail('Concurrent insert should fail');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('Duplicate entry', $e->getMessage());
        }
        $this->assertEquals(1, PlatformAdmin::count());
    }

    public function test_staff_role_invalid_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PlatformStaff::create([
            'first_name' => 'Bad',
            'last_name' => 'Role',
            'email' => 'badrole@example.com',
            'password_hash' => Hash::make('password'),
            'role' => 'super_admin',
        ]);
    }
}
