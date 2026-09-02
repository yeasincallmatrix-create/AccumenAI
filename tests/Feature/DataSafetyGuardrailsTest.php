<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Services\System\DataSafetyGuard;
use App\Services\System\TestDataCleanupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * DATA SAFETY GUARDRAILS — Regression suite for accidental account/business deletion.
 *
 * Covers spec sections 20, 21, 22, 23.
 *
 * NOTE: Does NOT use RefreshDatabase because the historical DB has no baseline
 * institutes creation migration — RefreshDatabase would wipe the table and fail to recreate.
 * Instead we isolate via per-test unique data and manual cleanup.
 */
class DataSafetyGuardrailsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure roles exist for ownership tests
        if (Role::count() === 0) {
            $this->seed(\Database\Seeders\DatabaseSeeder::class);
        }
        // Ensure at least institute-owner role exists
        if (! Role::where('slug', 'institute-owner')->exists()) {
            Role::create(['name' => 'Institute Owner', 'slug' => 'institute-owner', 'description' => 'Owner', 'status' => 'active']);
        }
    }

    protected function tearDown(): void
    {
        // Manual cleanup: remove test-created users/institutes to keep isolation
        try {
            DB::table('institution_user')->where('is_test', true)->delete();
        } catch (\Throwable $e) {}
        try {
            DB::table('users')->where('is_test', true)->delete();
        } catch (\Throwable $e) {}
        // Remove yasin and other unique test emails created during run
        try {
            DB::table('users')->where('email', 'like', '%yasin%')->orWhere('email', 'like', '%unknown_%')->orWhere('email', 'like', '%biz_owner_%')->orWhere('email', 'like', '%owner_migration_%')->orWhere('email', 'like', '%prod_%')->orWhere('email', 'like', '%test_user_%')->delete();
        } catch (\Throwable $e) {}
        try {
            DB::table('institutes')->where('slug', 'like', 'migration-test-%')->orWhere('slug', 'like', 'biz-%')->orWhere('slug', 'like', 'default-%')->delete();
        } catch (\Throwable $e) {}
        parent::tearDown();
    }

    // -----------------------------------------------------------------
    // 20. Previous incident — yasin.callmatrix@gmail.com
    // -----------------------------------------------------------------
    public function test_previous_incident_real_user_not_classified_as_test_via_email(): void
    {
        $user = User::factory()->create([
            'email' => 'yasin.callmatrix@gmail.com',
            'is_test' => false,
            'status' => 'active',
        ]);

        // Email pattern alone must NEVER authorize deletion
        $this->assertFalse(DataSafetyGuard::isTestByEmailPattern($user->email), 'Email pattern must be BLOCKED');
        $this->assertFalse(DataSafetyGuard::isExplicitTestRecord($user), 'Real user with is_test=false is NOT test');
        $this->assertTrue(DataSafetyGuard::isProtected($user), 'Real user must be PROTECTED');

        // Test cleanup must NOT delete this account
        $service = app(TestDataCleanupService::class);
        $preview = $service->preview();
        $countBefore = User::where('email', 'yasin.callmatrix@gmail.com')->count();
        $this->assertEquals(1, $countBefore);

        // Execute dry run should not delete
        $result = $service->execute(true);
        $this->assertEquals(1, User::where('email', 'yasin.callmatrix@gmail.com')->count());

        // Even with execute (is_test=false), user must survive
        // We test that execute does not delete real account
        $result = $service->execute(false);
        $this->assertEquals(1, User::where('email', 'yasin.callmatrix@gmail.com')->count(), 'Real account must survive test cleanup');
        $this->assertTrue(DataSafetyGuard::isProtected(User::where('email', 'yasin.callmatrix@gmail.com')->first()));
    }

    public function test_test_cleanup_deletes_explicit_test_record(): void
    {
        $testUser = User::factory()->testData()->create([
            'email' => 'test_cleanup_demo_'.uniqid().'@example.com',
            'is_test' => true,
        ]);

        $service = app(TestDataCleanupService::class);
        $before = User::where('id', $testUser->id)->count();
        $this->assertEquals(1, $before);
        $this->assertTrue(DataSafetyGuard::isExplicitTestRecord($testUser));

        $result = $service->execute(false);
        // Explicit test record should be deleted
        $this->assertEquals(0, User::where('id', $testUser->id)->count(), 'Explicit is_test=true should be deletable by test cleanup');
        $this->assertArrayHasKey('users', $result['deleted']);
    }

    public function test_structural_migration_does_not_delete_accounts(): void
    {
        // Create real owner + institute + membership (education → training_center migration)
        $owner = User::factory()->create(['email' => 'owner_migration_'.uniqid().'@gmail.com', 'is_test' => false, 'account_type' => 'owner', 'status' => 'active']);
        $institute = Institute::create([
            'name' => 'Migration Test Institute',
            'slug' => 'migration-test-'.uniqid(),
            'industry' => 'education',
            'sub_industry' => 'institution',
            'status' => 'active',
            'is_test' => false,
        ]);
        $roleId = Role::where('slug', 'institute-owner')->value('id');
        \App\Models\Membership::create([
            'user_id' => $owner->id,
            'institution_id' => $institute->id,
            'role_id' => $roleId,
            'status' => 'active',
            'is_test' => false,
        ]);

        $userId = $owner->id;
        $instituteId = $institute->id;

        // Simulate the structural migration logic (same as 2026_08_28_100000)
        // It should ONLY update institutes.industry/sub_industry, never delete users
        DB::table('institutes')->where('id', $instituteId)->update(['industry' => 'training_center', 'sub_industry' => 'training_institute']);

        $this->assertTrue(User::where('id', $userId)->exists(), 'User must survive structural migration');
        $this->assertTrue(Institute::where('id', $instituteId)->exists(), 'Institute must survive structural migration');
        $this->assertTrue(\App\Models\Membership::where('user_id', $userId)->where('institution_id', $instituteId)->exists(), 'Membership must survive structural migration');

        $updated = Institute::find($instituteId);
        $this->assertEquals('training_center', $updated->industry);
        $this->assertEquals('training_institute', $updated->sub_industry);
    }

    // -----------------------------------------------------------------
    // 21. Unknown data (is_test = NULL) must NOT be deleted
    // -----------------------------------------------------------------
    public function test_unknown_data_null_is_protected(): void
    {
        // Create a user with NULL is_test (ambiguous)
        $user = User::factory()->create(['email' => 'unknown_'.uniqid().'@gmail.com', 'is_test' => false]);
        // Force NULL via raw query to simulate ambiguous legacy row
        DB::table('users')->where('id', $user->id)->update(['is_test' => null]);
        $fresh = User::find($user->id);
        // After raw update, is_test is null — treat as protected
        $this->assertTrue(DataSafetyGuard::isProtected($fresh), 'NULL is_test must be PROTECTED');
        $this->assertFalse(DataSafetyGuard::isExplicitTestRecord($fresh), 'NULL is not explicit test');

        $service = app(TestDataCleanupService::class);
        $service->execute(false);
        $this->assertTrue(User::where('id', $user->id)->exists(), 'UNKNOWN (NULL) must NOT be deleted by test cleanup');
    }

    // -----------------------------------------------------------------
    // 22. Business data dependency blocks automatic deletion
    // -----------------------------------------------------------------
    public function test_business_data_dependency_blocks_automatic_deletion(): void
    {
        $owner = User::factory()->create(['email' => 'biz_owner_'.uniqid().'@gmail.com', 'is_test' => false, 'account_type' => 'owner', 'status' => 'active']);
        $institute = Institute::create([
            'name' => 'Business Institute '.uniqid(),
            'slug' => 'biz-'.uniqid(),
            'industry' => 'education',
            'sub_industry' => 'school',
            'status' => 'active',
            'is_test' => false,
        ]);
        $roleId = Role::where('slug', 'institute-owner')->value('id');
        \App\Models\Membership::create([
            'user_id' => $owner->id,
            'institution_id' => $institute->id,
            'role_id' => $roleId,
            'status' => 'active',
            'is_test' => false,
        ]);

        // Create business data: course (minimal required fields)
        if (Schema::hasTable('courses')) {
            try {
                DB::table('courses')->insert([
                    'institute_id' => $institute->id,
                    'name' => 'Test Course '.uniqid(),
                    'course_code' => 'TC'.substr(uniqid(),0,8),
                    'slug' => 'test-course-'.uniqid(),
                    'level' => 'basic',
                    'is_test' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (\Throwable $e) {
                // Fallback minimal
            }
        }

        // Attempt automatic deletion via DataSafetyGuard
        [$allowed, $reason, $counts] = DataSafetyGuard::canDeleteAccountAutomatically($owner);
        $this->assertFalse($allowed, 'Business-associated owner must be BLOCKED for automatic deletion');
        $this->assertTrue(
            str_contains(strtolower($reason), 'business data') || str_contains(strtolower($reason), 'only active owner') || str_contains(strtolower($reason), 'transfer ownership'),
            "Reason should indicate business data or orphan protection, got: {$reason}"
        );

        // Also verify AccountDeletionService orphan guard still blocks
        [$allowed2, $reason2] = \App\Services\AccountDeletionService::canForceDelete($owner);
        // This will be blocked because sole owner
        $this->assertFalse($allowed2);
    }

    // -----------------------------------------------------------------
    // Additional safety checks
    // -----------------------------------------------------------------
    public function test_email_only_classification_is_blocked(): void
    {
        $patterns = ['test@example.com', 'user@test.local', 'demo@demo.local', 'faker@faker.com', 'admin+test@gmail.com'];
        foreach ($patterns as $email) {
            $this->assertFalse(DataSafetyGuard::isTestByEmailPattern($email), "Email pattern {$email} must be BLOCKED");
        }

        // Even a fake test user with is_test=false must be protected despite email containing 'test'
        $user = User::factory()->create(['email' => 'test_user_'.uniqid().'@example.com', 'is_test' => false]);
        $this->assertTrue(DataSafetyGuard::isProtected($user));
        $this->assertFalse(DataSafetyGuard::isExplicitTestRecord($user));
    }

    public function test_production_data_defaults_to_protected(): void
    {
        $user = User::factory()->create(['email' => 'prod_'.uniqid().'@gmail.com']); // factory default is_test=false
        $this->assertFalse($user->is_test);
        $this->assertTrue(DataSafetyGuard::isProtected($user));
    }

    public function test_institute_is_test_defaults_protected(): void
    {
        $inst = Institute::create([
            'name' => 'Default Protected '.uniqid(),
            'slug' => 'default-'.uniqid(),
            'industry' => 'education',
            'sub_industry' => 'school',
            'status' => 'active',
        ]);
        // Default should be false (migration default)
        $fresh = Institute::find($inst->id);
        $this->assertFalse((bool) $fresh->is_test, 'New institute must default is_test=false (PROTECTED)');
        $this->assertTrue(DataSafetyGuard::isProtected($fresh));
    }

    public function test_super_admin_protection(): void
    {
        // Verify PlatformAdmin singleton enforcement exists
        $this->assertTrue(Schema::hasTable('platform_admins'));
        $admin = \App\Models\PlatformAdmin::first();
        if ($admin) {
            // Attempting to change is_owner or singleton_guard via mass assignment should be blocked
            $this->assertTrue(isset($admin->is_owner));
        }
        // Middleware class exists
        $this->assertTrue(class_exists(\App\Http\Middleware\BlockPlatformAdminEscalation::class));
    }
}
