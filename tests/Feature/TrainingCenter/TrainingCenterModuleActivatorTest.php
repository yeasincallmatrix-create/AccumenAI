<?php

namespace Tests\Feature\TrainingCenter;

use App\Models\Institute;
use App\Models\Permission;
use App\Models\Role;
use App\Services\TrainingCenterModuleActivator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TrainingCenterModuleActivatorTest extends TestCase
{
    use DatabaseTransactions;

    private TrainingCenterModuleActivator $activator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activator = app(TrainingCenterModuleActivator::class);
    }

    public function test_activate_assigns_permissions(): void
    {
        $institute = $this->createInstitute();

        Role::create([
            'institute_id' => $institute->id,
            'name' => 'Owner',
            'slug' => 'institute-owner',
            'status' => 'active',
        ]);

        $this->activator->activateForTrainingCenter($institute);

        $role = Role::where('institute_id', $institute->id)->where('slug', 'institute-owner')->first();
        $this->assertNotNull($role);

        $trainingPermCount = Permission::where('module', 'like', 'training_%')
            ->orWhere('module', 'training')
            ->count();
        $this->assertGreaterThan(0, $trainingPermCount);

        $assigned = DB::table('role_permissions')
            ->where('role_id', $role->id)
            ->count();
        $this->assertGreaterThan(0, $assigned);
    }

    public function test_activate_grants_entitlements(): void
    {
        $institute = $this->createInstitute();
        $this->activator->activateForTrainingCenter($institute);

        $override = DB::table('institute_module_overrides')
            ->where('institute_id', $institute->id)
            ->where('module_key', 'training_center')
            ->first();
        $this->assertNotNull($override);
        $this->assertEquals(1, $override->enabled);

        $subOverrides = DB::table('institute_module_overrides')
            ->where('institute_id', $institute->id)
            ->where('module_key', 'like', 'training_center.%')
            ->count();
        $this->assertEquals(9, $subOverrides);
    }

    public function test_activate_is_idempotent(): void
    {
        $institute = $this->createInstitute();

        $this->activator->activateForTrainingCenter($institute);
        $count1 = DB::table('institute_module_overrides')
            ->where('institute_id', $institute->id)
            ->where('module_key', 'training_center')
            ->count();

        $this->activator->activateForTrainingCenter($institute);
        $count2 = DB::table('institute_module_overrides')
            ->where('institute_id', $institute->id)
            ->where('module_key', 'training_center')
            ->count();

        $this->assertEquals($count1, $count2);
    }

    public function test_activate_does_not_break_education(): void
    {
        $eduInstitute = Institute::create([
            'name' => 'School',
            'slug' => 'school-' . uniqid(),
            'industry' => 'education',
            'status' => 'active',
        ]);

        app(\App\Services\EducationModuleActivator::class)->activateForEducation($eduInstitute);

        $eduOverrides = DB::table('institute_module_overrides')
            ->where('institute_id', $eduInstitute->id)
            ->where('module_key', 'like', 'education%')
            ->count();
        $this->assertGreaterThan(0, $eduOverrides);

        $tcInstitute = $this->createInstitute();
        $this->activator->activateForTrainingCenter($tcInstitute);

        $eduOverridesAfter = DB::table('institute_module_overrides')
            ->where('institute_id', $eduInstitute->id)
            ->where('module_key', 'like', 'education%')
            ->count();
        $this->assertEquals($eduOverrides, $eduOverridesAfter, 'Education overrides should not change');
    }

    public function test_non_admin_not_affected(): void
    {
        $institute = $this->createInstitute();
        $this->activator->activateForTrainingCenter($institute);

        $staffRole = Role::create([
            'institute_id' => $institute->id,
            'name' => 'Staff',
            'slug' => 'staff',
            'status' => 'active',
        ]);

        $assigned = DB::table('role_permissions')
            ->where('role_id', $staffRole->id)
            ->count();
        $this->assertEquals(0, $assigned, 'Staff role should not get permissions automatically');
    }

    public function test_module_registry_intact(): void
    {
        $institute = $this->createInstitute();
        $this->activator->activateForTrainingCenter($institute);

        $subs = DB::table('module_registry')->where('parent_key', 'training_center')->count();
        $this->assertEquals(9, $subs);
    }

    public function test_feature_entries_intact(): void
    {
        (new \Database\Seeders\FeatureRegistrySeeder)->run();

        $institute = $this->createInstitute();
        $this->activator->activateForTrainingCenter($institute);

        if (DB::getSchemaBuilder()->hasTable('feature_registry')) {
            $features = DB::table('feature_registry')
                ->where('feature_key', 'like', 'training_center%')
                ->count();
            $this->assertGreaterThanOrEqual(8, $features);
        }
    }

    private function createInstitute(): Institute
    {
        return Institute::create([
            'name' => 'TC Activator Test',
            'slug' => 'tc-act-' . uniqid(),
            'industry' => 'training_center',
            'status' => 'active',
        ]);
    }
}
