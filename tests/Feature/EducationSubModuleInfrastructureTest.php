<?php

namespace Tests\Feature;

use Database\Seeders\EducationPermissionSeeder;
use Database\Seeders\EducationSubModuleSeeder;
use Database\Seeders\FeatureRegistrySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EducationSubModuleInfrastructureTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        (new EducationSubModuleSeeder)->run();
        (new EducationPermissionSeeder)->run();
        if (Schema::hasTable('feature_registry')) {
            (new FeatureRegistrySeeder)->run();
        }
    }

    public function test_education_parent_module_exists(): void
    {
        $row = DB::table('module_registry')->where('key', 'education')->first();
        $this->assertNotNull($row, 'education parent module must exist');
        $this->assertEquals('active', $row->status);
    }

    public function test_all_7_sub_modules_seeded(): void
    {
        $expected = [
            'education.students',
            'education.classes',
            'education.exams',
            'education.attendance',
            'education.fees',
            'education.guardians',
            'education.analytics',
        ];

        $keys = DB::table('module_registry')
            ->where('parent_key', 'education')
            ->pluck('key')
            ->toArray();

        foreach ($expected as $key) {
            $this->assertContains($key, $keys, "Sub-module {$key} must be registered");
        }
    }

    public function test_sub_modules_are_active(): void
    {
        $rows = DB::table('module_registry')
            ->where('parent_key', 'education')
            ->get();

        $this->assertCount(7, $rows);

        foreach ($rows as $row) {
            $this->assertEquals('active', $row->status, "{$row->key} must be active");
            $this->assertFalse((bool) $row->coming_soon, "{$row->key} must not be coming_soon");
        }
    }

    public function test_sub_modules_have_icons(): void
    {
        $rows = DB::table('module_registry')
            ->where('parent_key', 'education')
            ->get();

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertNotEmpty($row->icon, "{$row->key} must have an icon");
        }
    }

    public function test_sub_modules_have_index_routes(): void
    {
        $rows = DB::table('module_registry')
            ->where('parent_key', 'education')
            ->get();

        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertNotEmpty($row->index_route, "{$row->key} must have an index_route");
        }
    }

    public function test_sub_modules_added_to_basic_package(): void
    {
        if (! Schema::hasTable('package_modules')) {
            $this->markTestSkipped('package_modules table missing');
        }

        $pkg = DB::table('subscription_packages')->whereRaw('LOWER(slug) = ?', ['basic'])->first();
        if (! $pkg) {
            $this->markTestSkipped('basic package not found');
        }

        $subKeys = [
            'education.students', 'education.classes', 'education.exams',
            'education.attendance', 'education.fees', 'education.guardians', 'education.analytics',
        ];

        foreach ($subKeys as $key) {
            $exists = DB::table('package_modules')
                ->where('package_id', $pkg->id)
                ->where('module_key', $key)
                ->where('enabled', true)
                ->exists();
            $this->assertTrue($exists, "{$key} must be in basic package");
        }
    }

    public function test_sub_modules_added_to_premium_package(): void
    {
        if (! Schema::hasTable('package_modules')) {
            $this->markTestSkipped('package_modules table missing');
        }

        $pkg = DB::table('subscription_packages')->whereRaw('LOWER(slug) = ?', ['premium'])->first();
        if (! $pkg) {
            $this->markTestSkipped('premium package not found');
        }

        $subKeys = [
            'education.students', 'education.classes', 'education.exams',
            'education.attendance', 'education.fees', 'education.guardians', 'education.analytics',
        ];

        foreach ($subKeys as $key) {
            $exists = DB::table('package_modules')
                ->where('package_id', $pkg->id)
                ->where('module_key', $key)
                ->where('enabled', true)
                ->exists();
            $this->assertTrue($exists, "{$key} must be in premium package");
        }
    }

    public function test_education_permissions_created(): void
    {
        $expected = [
            'education_students.view',
            'education_students.create',
            'education_students.edit',
            'education_students.delete',
            'education_students.enroll',
            'education_classes.view',
            'education_classes.manage',
            'education_exams.view',
            'education_exams.manage',
            'education_attendance.view',
            'education_attendance.manage',
            'education_fees.view',
            'education_fees.manage',
            'education_guardians.view',
            'education_guardians.manage',
            'education_analytics.view',
        ];

        $existing = DB::table('permissions')
            ->whereIn('slug', $expected)
            ->pluck('slug')
            ->toArray();

        foreach ($expected as $slug) {
            $this->assertContains($slug, $existing, "Permission {$slug} must exist");
        }
    }

    public function test_feature_registry_entries_created(): void
    {
        if (! Schema::hasTable('feature_registry')) {
            $this->markTestSkipped('feature_registry table missing');
        }

        $expected = [
            'education.students',
            'education.classes',
            'education.exams',
            'education.attendance',
            'education.fees',
            'education.guardians',
            'education.analytics',
        ];

        $existing = DB::table('feature_registry')
            ->whereIn('feature_key', $expected)
            ->pluck('feature_key')
            ->toArray();

        foreach ($expected as $key) {
            $this->assertContains($key, $existing, "Feature {$key} must be registered");
        }
    }

    public function test_feature_registry_entries_are_active(): void
    {
        if (! Schema::hasTable('feature_registry')) {
            $this->markTestSkipped('feature_registry table missing');
        }

        $rows = DB::table('feature_registry')
            ->where('module_key', 'education')
            ->get();

        $this->assertGreaterThanOrEqual(7, $rows->count());

        foreach ($rows as $row) {
            $this->assertEquals('active', $row->status, "Feature {$row->feature_key} must be active");
        }
    }

    public function test_parent_disabled_disables_all_children(): void
    {
        $inst = $this->makeInstitute('basic');

        DB::table('institute_module_overrides')->where('institute_id', $inst->id)->delete();
        DB::table('institute_module_overrides')->insert([
            'institute_id' => $inst->id,
            'module_key'   => 'education',
            'enabled'      => false,
        ]);

        app(\App\Services\ModuleAccessService::class)->flushCache($inst->id);

        $service = app(\App\Services\ModuleAccessService::class);
        $this->assertFalse($service->isEnabled($inst, 'education'));
        $this->assertFalse($service->isEnabled($inst, 'education.students'));
        $this->assertFalse($service->isEnabled($inst, 'education.exams'));
    }

    public function test_sort_order_is_deterministic(): void
    {
        $rows = DB::table('module_registry')
            ->where('parent_key', 'education')
            ->orderBy('sort_order')
            ->pluck('sort_order')
            ->toArray();

        $this->assertGreaterThanOrEqual(2, count($rows));

        for ($i = 1; $i < count($rows); $i++) {
            $this->assertGreaterThan($rows[$i - 1], $rows[$i], 'Sort orders must be strictly ascending');
        }
    }

    public function test_flat_permission_slugs_seeded(): void
    {
        $flatSlugs = [
            'students.view', 'students.manage',
            'courses.view', 'courses.manage',
            'batches.view', 'batches.manage',
            'attendance.view', 'attendance.manage',
            'exams.view', 'exams.manage',
            'certificates.view',
            'education.manage',
            'curriculum.view', 'curriculum.manage',
            'admission.approve',
            'promotion.manage',
        ];

        $existing = DB::table('permissions')->whereIn('slug', $flatSlugs)->pluck('slug')->toArray();
        $missing = array_diff($flatSlugs, $existing);
        $this->assertEmpty($missing, 'Missing flat slugs: ' . implode(', ', $missing));
    }

    public function test_non_owner_user_can_access_students_list(): void
    {
        $inst = $this->makeInstitute('basic');

        app(\App\Services\ModuleAccessService::class)->enableModule($inst, 'education');
        app(\App\Services\ModuleAccessService::class)->enableModule($inst, 'education.students');

        $staffRole = DB::table('roles')->insertGetId([
            'institute_id' => $inst->id,
            'name' => 'Staff',
            'slug' => 'staff',
            'status' => 'active',
            'created_at' => now(),
        ]);

        $perm = DB::table('permissions')->where('slug', 'students.view')->first();
        $this->assertNotNull($perm, 'students.view permission must exist');

        DB::table('role_permissions')->insert([
            'role_id' => $staffRole,
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
            'role_id' => $staffRole,
            'status' => 'active',
        ]);

        $this->actingAs($user, 'web');
        \App\Support\Workspace::set($inst->id);

        $this->get(route('students.index'))->assertStatus(200);
    }

    private function makeInstitute(string $packageSlug): object
    {
        $pkg = DB::table('subscription_packages')->whereRaw('LOWER(slug) = ?', [strtolower($packageSlug)])->firstOrFail();

        $id = DB::table('institutes')->insertGetId([
            'name'          => 'Edu Test ' . $pkg->slug,
            'slug'          => 'edu-test-' . uniqid(),
            'industry'      => 'education',
            'package_id'    => $pkg->id,
            'status'        => 'active',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        return \App\Models\Institute::withoutGlobalScopes()->find($id);
    }
}
