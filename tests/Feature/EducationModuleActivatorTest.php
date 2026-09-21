<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Permission;
use App\Models\Role;
use App\Services\EducationModuleActivator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EducationModuleActivatorTest extends TestCase
{
    use DatabaseTransactions;

    private EducationModuleActivator $activator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activator = app(EducationModuleActivator::class);
    }

    public function test_noop_for_non_education_industry(): void
    {
        $institute = Institute::create([
            'name' => 'Hospital',
            'slug' => 'hospital-' . uniqid(),
            'industry' => 'healthcare',
            'status' => 'active',
        ]);

        $this->activator->activateForEducation($institute);

        $override = DB::table('institute_module_overrides')
            ->where('institute_id', $institute->id)
            ->where('module_key', 'education')
            ->first();
        if ($override) {
            $this->assertEquals(0, (int) $override->enabled, 'Activator should not enable education for healthcare institute');
        }
    }

    public function test_creates_override_for_education_institute(): void
    {
        $institute = Institute::create([
            'name' => 'School',
            'slug' => 'school-' . uniqid(),
            'industry' => 'education',
            'status' => 'active',
        ]);

        $this->activator->activateForEducation($institute);

        $override = DB::table('institute_module_overrides')
            ->where('institute_id', $institute->id)
            ->where('module_key', 'education')
            ->first();
        $this->assertNotNull($override);
        $this->assertEquals(1, $override->enabled);
    }

    public function test_seeds_permissions(): void
    {
        $before = Permission::where('module', 'like', 'education_%')->count();

        $institute = Institute::create([
            'name' => 'School',
            'slug' => 'school-' . uniqid(),
            'industry' => 'education',
            'status' => 'active',
        ]);

        $this->activator->activateForEducation($institute);

        $after = Permission::where('module', 'like', 'education_%')->count();
        $this->assertGreaterThanOrEqual($before, $after, 'Should create education permissions');
    }

    public function test_assigns_permissions_to_admin_role(): void
    {
        $institute = Institute::create([
            'name' => 'School',
            'slug' => 'school-' . uniqid(),
            'industry' => 'education',
            'status' => 'active',
        ]);

        $role = Role::create([
            'institute_id' => $institute->id,
            'name' => 'Owner',
            'slug' => 'institute-owner',
            'status' => 'active',
        ]);

        $this->activator->activateForEducation($institute);

        $eduPermIds = Permission::where('module', 'like', 'education_%')->pluck('id')->toArray();
        $assigned = DB::table('role_permissions')
            ->where('role_id', $role->id)
            ->whereIn('permission_id', $eduPermIds)
            ->count();

        $this->assertGreaterThan(0, $assigned, 'Should assign education permissions to admin role');
    }

    public function test_idempotent_on_repeated_calls(): void
    {
        $institute = Institute::create([
            'name' => 'School',
            'slug' => 'school-' . uniqid(),
            'industry' => 'education',
            'status' => 'active',
        ]);

        $this->activator->activateForEducation($institute);
        $count1 = DB::table('institute_module_overrides')
            ->where('institute_id', $institute->id)
            ->where('module_key', 'education')
            ->count();

        $this->activator->activateForEducation($institute);
        $count2 = DB::table('institute_module_overrides')
            ->where('institute_id', $institute->id)
            ->where('module_key', 'education')
            ->count();

        $this->assertEquals($count1, $count2, 'Should be idempotent');
    }

    public function test_registers_sub_modules(): void
    {
        $institute = Institute::create([
            'name' => 'School',
            'slug' => 'school-' . uniqid(),
            'industry' => 'education',
            'status' => 'active',
        ]);

        $this->activator->activateForEducation($institute);

        $subModules = DB::table('module_registry')
            ->where('parent_key', 'education')
            ->pluck('key')
            ->toArray();

        $expected = [
            'education.students', 'education.classes', 'education.exams',
            'education.attendance', 'education.fees', 'education.guardians', 'education.analytics',
        ];

        foreach ($expected as $key) {
            $this->assertContains($key, $subModules, "Sub-module {$key} should be registered");
        }
    }

    public function test_owner_role_gets_permissions(): void
    {
        $institute = Institute::create([
            'name' => 'School',
            'slug' => 'school-' . uniqid(),
            'industry' => 'education',
            'status' => 'active',
        ]);

        Role::create([
            'institute_id' => $institute->id,
            'name' => 'Owner',
            'slug' => 'institute-owner',
            'status' => 'active',
        ]);

        $this->activator->activateForEducation($institute);

        $ownerRole = Role::where('institute_id', $institute->id)->where('slug', 'institute-owner')->first();
        $eduPermCount = Permission::where('module', 'like', 'education_%')->count();
        $assigned = DB::table('role_permissions')
            ->where('role_id', $ownerRole->id)
            ->count();

        $this->assertGreaterThanOrEqual($eduPermCount, $assigned);
    }
}
