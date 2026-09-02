<?php

namespace Tests\Feature;

use App\Models\AcademicAssessment;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicYear;
use App\Models\ClassGrade;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Subject;
use App\Models\CourseCategory;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AcademicAssessmentHardeningTest extends TestCase
{
    use DatabaseTransactions;

    protected function createInstitute(): Institute
    {
        $country = \App\Models\Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]);
        return Institute::create([
            'name' => 'Test Inst '.uniqid(),
            'slug' => 'test-'.uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'education',
            'status' => 'active',
        ]);
    }

    protected function createUser(Institute $inst, string $role = 'institute-owner'): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $inst->id,
            'role_id' => Role::where('slug', $role)->firstOrFail()->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => uniqid().'@test.test',
            'phone' => '017'.mt_rand(10000000,99999999),
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    protected function createYear(Institute $inst): AcademicYear
    {
        return AcademicYear::create([
            'institute_id' => $inst->id,
            'name' => '2027',
            'code' => '2027-'.uniqid(),
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'is_current' => true,
            'status' => true,
        ]);
    }

    protected function createClassGrade(): ClassGrade
    {
        $country = \App\Models\Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]);
        $system = \App\Models\EducationSystem::firstOrCreate(['country_id' => $country->id, 'code' => 'BD-NCTB'], ['name' => 'NCTB', 'status' => true]);
        $level = \App\Models\AcademicLevel::firstOrCreate(['education_system_id' => $system->id, 'code' => 'secondary'], ['name' => 'Secondary', 'country_id' => $country->id, 'status' => true]);
        return ClassGrade::firstOrCreate(
            ['academic_level_id' => $level->id, 'code' => 'grade-8'],
            ['country_id' => $country->id, 'education_system_id' => $system->id, 'name' => 'Class 8', 'status' => true, 'display_order' => 0]
        );
    }

    public function test_aggregation_weight_valid_100(): void
    {
        $inst = $this->createInstitute();
        $owner = $this->createUser($inst);
        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user');
        $year = $this->createYear($inst);
        $class = $this->createClassGrade();
        $a1 = AcademicAssessment::create(['institute_id' => $inst->id, 'academic_year_id' => $year->id, 'class_grade_id' => $class->id, 'name' => 'Mid-Term', 'status' => 'draft']);
        $a2 = AcademicAssessment::create(['institute_id' => $inst->id, 'academic_year_id' => $year->id, 'class_grade_id' => $class->id, 'name' => 'Final', 'status' => 'draft']);
        $scheme = app(\App\Services\AcademicResultAggregationService::class)->store($inst, null, $owner->id, [
            'academic_year_id' => $year->id, 'class_grade_id' => $class->id, 'name' => 'Scheme 100', 'status' => 'active'
        ], [
            ['assessment_id' => $a1->id, 'weight' => 40],
            ['assessment_id' => $a2->id, 'weight' => 60],
        ]);
        $this->assertEquals(100, $scheme->totalWeight());
        $this->assertTrue($scheme->weightIsValid());
    }

    public function test_tenant_isolation(): void
    {
        $instA = $this->createInstitute();
        $instB = $this->createInstitute();
        $ownerA = $this->createUser($instA);
        $ownerB = $this->createUser($instB);
        $yearA = $this->createYear($instA);
        $class = $this->createClassGrade();
        $assA = AcademicAssessment::create(['institute_id' => $instA->id, 'academic_year_id' => $yearA->id, 'class_grade_id' => $class->id, 'name' => 'Ass A', 'status' => 'draft']);
        TenantContext::set($instB->id);
        $this->actingAs($ownerB, 'institute_user')->get(route('settings.academic.assessments.show', $assA))->assertStatus(404);
    }
}
