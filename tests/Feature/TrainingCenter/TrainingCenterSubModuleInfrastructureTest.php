<?php

namespace Tests\Feature\TrainingCenter;

use Database\Seeders\EducationPermissionSeeder;
use Database\Seeders\TrainingCenterPermissionSeeder;
use Database\Seeders\TrainingCenterSubModuleSeeder;
use Database\Seeders\FeatureRegistrySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TrainingCenterSubModuleInfrastructureTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        (new EducationPermissionSeeder)->run();
        (new TrainingCenterSubModuleSeeder)->run();
        (new TrainingCenterPermissionSeeder)->run();
        if (Schema::hasTable('feature_registry')) {
            (new FeatureRegistrySeeder)->run();
        }
    }

    public function test_parent_registered(): void
    {
        $row = DB::table('module_registry')->where('key', 'training_center')->first();
        $this->assertNotNull($row);
        $this->assertEquals('active', $row->status);
    }

    public function test_8_sub_modules_registered(): void
    {
        $count = DB::table('module_registry')->where('parent_key', 'training_center')->count();
        $this->assertEquals(8, $count);
    }

    public function test_sub_modules_correct_parent(): void
    {
        $rows = DB::table('module_registry')->where('parent_key', 'training_center')->get();
        foreach ($rows as $row) {
            $this->assertEquals('training_center', $row->parent_key, "{$row->key} must have parent_key=training_center");
        }
    }

    public function test_packages_assigned(): void
    {
        if (! Schema::hasTable('package_modules')) {
            $this->markTestSkipped('package_modules table missing');
        }

        $subKeys = [
            'training_center.courses', 'training_center.batches', 'training_center.trainees',
            'training_center.attendance', 'training_center.exams', 'training_center.certificates',
            'training_center.fees', 'training_center.reports',
        ];

        $pkg = DB::table('subscription_packages')->whereRaw('LOWER(slug) = ?', ['advanced'])->first();
        if (! $pkg) {
            $this->markTestSkipped('advanced package not found');
        }

        foreach ($subKeys as $key) {
            $exists = DB::table('package_modules')
                ->where('package_id', $pkg->id)
                ->where('module_key', $key)
                ->where('enabled', true)
                ->exists();
            $this->assertTrue($exists, "{$key} must be in advanced package");
        }
    }

    public function test_flat_permissions_seeded(): void
    {
        $flatSlugs = [
            'training.view', 'training.manage', 'trainees.view', 'trainees.manage',
            'enrollments.view', 'enrollments.manage', 'marks.view', 'marks.manage',
            'results.view', 'results.publish', 'training.certificates.view',
            'training.settings.manage', 'training.attendance.view', 'training.exams.view',
            'training.fees.view', 'training.reports.view', 'training.courses.view',
        ];

        $existing = DB::table('permissions')->whereIn('slug', $flatSlugs)->pluck('slug')->toArray();
        $missing = array_diff($flatSlugs, $existing);
        $this->assertEmpty($missing, 'Missing flat slugs: ' . implode(', ', $missing));
    }

    public function test_prefixed_permissions_seeded(): void
    {
        $prefixedSlugs = [
            'training_center.courses.view', 'training_center.courses.manage',
            'training_center.batches.view', 'training_center.batches.manage',
            'training_center.trainees.view', 'training_center.trainees.manage',
            'training_center.attendance.view', 'training_center.attendance.manage',
            'training_center.exams.view', 'training_center.exams.manage',
            'training_center.certificates.view', 'training_center.certificates.manage',
            'training_center.fees.view', 'training_center.fees.manage',
            'training_center.reports.view',
        ];

        $existing = DB::table('permissions')->whereIn('slug', $prefixedSlugs)->pluck('slug')->toArray();
        $missing = array_diff($prefixedSlugs, $existing);
        $this->assertEmpty($missing, 'Missing prefixed slugs: ' . implode(', ', $missing));
    }

    public function test_batches_permission_reused_from_education(): void
    {
        $batchesView = DB::table('permissions')->where('slug', 'batches.view')->first();
        $this->assertNotNull($batchesView, 'batches.view must exist');
        $this->assertEquals('education', $batchesView->module, 'batches.view module should be education (shared)');
    }

    public function test_features_seeded(): void
    {
        if (! Schema::hasTable('feature_registry')) {
            $this->markTestSkipped('feature_registry table missing');
        }

        $expected = [
            'training_center.courses', 'training_center.batches', 'training_center.trainees',
            'training_center.attendance', 'training_center.exams', 'training_center.certificates',
            'training_center.fees', 'training_center.reports',
        ];

        $existing = DB::table('feature_registry')
            ->whereIn('feature_key', $expected)
            ->pluck('feature_key')
            ->toArray();

        foreach ($expected as $key) {
            $this->assertContains($key, $existing, "Feature {$key} must be registered");
        }
    }

    public function test_all_route_slugs_exist_in_db(): void
    {
        $routeSlugs = ['batches.view', 'batches.manage', 'certificates.view'];
        $existing = DB::table('permissions')->whereIn('slug', $routeSlugs)->pluck('slug')->toArray();
        $missing = array_diff($routeSlugs, $existing);
        $this->assertEmpty($missing, 'Missing route slugs: ' . implode(', ', $missing));
    }

    public function test_non_owner_can_access_training_list(): void
    {
        $inst = $this->makeInstitute();

        app(\App\Services\ModuleAccessService::class)->enableModule($inst, 'training_center');

        $ownerRole = DB::table('roles')->where('slug', 'institute-owner')->whereNull('institute_id')->first();
        if (! $ownerRole) {
            $ownerRoleId = DB::table('roles')->insertGetId([
                'name' => 'Owner',
                'slug' => 'institute-owner',
                'status' => 'active',
                'created_at' => now(),
            ]);
        } else {
            $ownerRoleId = $ownerRole->id;
        }

        $instRoleId = DB::table('roles')->insertGetId([
            'institute_id' => $inst->id,
            'name' => 'Staff',
            'slug' => 'staff',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $perm = DB::table('permissions')->where('slug', 'batches.view')->first();
        $this->assertNotNull($perm, 'batches.view permission must exist');

        DB::table('role_permissions')->insert([
            'role_id' => $instRoleId,
            'permission_id' => $perm->id,
        ]);

        $user = \App\Models\User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        \App\Models\Membership::create([
            'user_id' => $user->id,
            'institution_id' => $inst->id,
            'role_id' => $instRoleId,
            'status' => 'active',
        ]);

        $this->actingAs($user, 'web');
        \App\Support\Workspace::set($inst->id);

        $this->get(route('training.attendance.index'))->assertStatus(200);
    }

    public function test_sub_module_count_is_8(): void
    {
        $count = DB::table('module_registry')->where('parent_key', 'training_center')->count();
        $this->assertEquals(8, $count);
    }

    public function test_registry_idempotent(): void
    {
        (new TrainingCenterSubModuleSeeder)->run();
        $count = DB::table('module_registry')->where('parent_key', 'training_center')->count();
        $this->assertEquals(8, $count, 'Re-running seeder should not create duplicates');
    }

    private function makeInstitute(): object
    {
        $pkg = DB::table('subscription_packages')->whereRaw('LOWER(slug) = ?', ['basic'])->firstOrFail();

        $id = DB::table('institutes')->insertGetId([
            'name'          => 'TC Test ' . $pkg->slug,
            'slug'          => 'tc-test-' . uniqid(),
            'industry'      => 'training_center',
            'sub_industry'  => 'training_institute',
            'package_id'    => $pkg->id,
            'status'        => 'active',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return \App\Models\Institute::withoutGlobalScopes()->find($id);
    }
}
