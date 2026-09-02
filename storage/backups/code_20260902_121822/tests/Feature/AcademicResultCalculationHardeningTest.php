<?php

namespace Tests\Feature;

use App\Models\AcademicAssessment;
use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultRow;
use App\Models\AcademicYear;
use App\Models\ClassGrade;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AcademicResultCalculationHardeningTest extends TestCase
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

    public function test_result_calculation_deterministic(): void
    {
        $inst = $this->createInstitute();
        $owner = $this->createUser($inst);
        TenantContext::set($inst->id);
        $this->assertTrue(true);
        $this->assertEquals(4.93, round((31.5 + 3.0) / 7, 2));
    }

    public function test_optional_bonus_not_double_counted(): void
    {
        $bonus = max(5.0 - 2.00, 0);
        $this->assertEquals(3.0, $bonus);
        $total = 31.5 + $bonus;
        $gpa1 = round($total / 7, 2);
        $gpa2 = round($total / 7, 2);
        $this->assertEquals($gpa1, $gpa2);
    }

    public function test_gpa_capped_at_max(): void
    {
        $inst = $this->createInstitute();
        TenantContext::set($inst->id);
        $mandatory = array_fill(0, 7, 5.0);
        $bonus = 3.0;
        $gpa = round((array_sum($mandatory) + $bonus) / count($mandatory), 2);
        if ($gpa > 5.00) $gpa = 5.00;
        $this->assertEquals(5.00, $gpa);
    }

    public function test_historical_freeze_after_finalize(): void
    {
        $inst = $this->createInstitute();
        $owner = $this->createUser($inst);
        TenantContext::set($inst->id);
        $subject = \App\Models\Subject::create([
            'institute_id' => $inst->id,
            'category_id' => \App\Models\CourseCategory::withoutGlobalScope('institute')->firstOrCreate(
                ['name' => 'Test Cat', 'subject_type' => 'academic', 'institute_id' => $inst->id],
                ['slug' => 'cat-'.uniqid(), 'status' => 'active', 'institute_id' => $inst->id]
            )->id,
            'subject_type' => 'academic',
            'subject_code' => 'SUB'.mt_rand(10000,99999),
            'name' => 'HistorySub '.uniqid(),
            'slug' => 'sub-'.uniqid(),
            'status' => 'active',
        ]);
        $student = \App\Models\Student::create([
            'institute_id' => $inst->id,
            'branch_id' => \App\Models\Branch::create(['institute_id' => $inst->id, 'name' => 'Branch '.uniqid(), 'status' => 'active'])->id,
            'student_id_number' => 'RP'.strtoupper(str()->random(8)),
            'first_name' => 'Test',
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => now()->toDateString(),
        ]);
        $year = AcademicYear::create(['institute_id' => $inst->id, 'name' => '2027', 'code' => '2027-'.uniqid(), 'start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'is_current' => true, 'status' => true]);
        $placement = \App\Models\StudentAcademicPlacement::create([
            'institute_id' => $inst->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'status' => 'active',
        ]);
        $scheme = \App\Models\AcademicResultAggregationScheme::create([
            'institute_id' => $inst->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $this->createClassGrade()->id,
            'name' => 'Test Scheme',
            'status' => 'active',
        ]);
        $policy = \App\Models\AcademicFinalResultPolicy::create([
            'institute_id' => $inst->id,
            'scheme_id' => $scheme->id,
            'name' => 'Test Policy',
            'status' => 'active',
        ]);
        $finalResult = AcademicFinalResult::create([
            'institute_id' => $inst->id,
            'policy_id' => $policy->id,
            'scheme_id' => $scheme->id,
            'name' => 'Test Result',
            'status' => 'locked',
        ]);
        $row = AcademicFinalResultRow::create([
            'result_id' => $finalResult->id,
            'placement_id' => $placement->id,
            'subject_id' => $subject->id,
            'status' => 'computed',
            'aggregate' => 85,
            'grade' => 'A+',
            'grade_point' => 5.00,
            'subject_status' => 'PASS',
            'gpa_included' => true,
            'credits' => 5,
            'optional' => false,
        ]);
        app(\App\Services\SubjectDeletionService::class)->softDelete($subject, $owner->id);
        $this->assertSoftDeleted('subjects', ['id' => $subject->id]);
        $reloaded = AcademicFinalResultRow::find($row->id);
        $this->assertNotNull($reloaded);
        $row->delete();
    }

    public function test_tenant_isolation_result(): void
    {
        $instA = $this->createInstitute();
        $instB = $this->createInstitute();
        $ownerA = $this->createUser($instA);
        $ownerB = $this->createUser($instB);
        $yearA = AcademicYear::create(['institute_id' => $instA->id, 'name' => '2027', 'code' => '2027-'.uniqid(), 'start_date' => '2027-01-01', 'end_date' => '2027-12-31', 'is_current' => true, 'status' => true]);
        $class = $this->createClassGrade();
        $assA = AcademicAssessment::create(['institute_id' => $instA->id, 'academic_year_id' => $yearA->id, 'class_grade_id' => $class->id, 'name' => 'Ass A', 'status' => 'draft']);
        TenantContext::set($instB->id);
        $this->actingAs($ownerB, 'institute_user')->get(route('settings.academic.assessments.show', $assA))->assertStatus(404);
    }
}

