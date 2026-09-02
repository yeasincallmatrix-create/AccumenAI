<?php

namespace Tests\Feature;

use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultPolicy;
use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Certificate;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\Course;
use App\Models\EducationSystem;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PromotionDecision;
use App\Models\PromotionDecisionItem;
use App\Models\PromotionPolicy;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\StudentEnrollment;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Step 35 — institute-side certificate request (Completion/Graduation →
 * Certificate chain). The request is created as `pending` and the platform
 * registry approves/rejects/revokes it through the existing admin flow.
 */
class CertificateRequestTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    // ------------------------------------------------------------- Fixtures

    private function seededContext(): array
    {
        $country = Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh',
            'iso3' => 'BGD',
            'phone_code' => '880',
            'status' => true,
        ]);

        $system = EducationSystem::firstOrCreate(
            ['country_id' => $country->id, 'code' => 'general'],
            ['name' => 'General Education', 'display_order' => 0, 'status' => true]
        );

        $level = AcademicLevel::create([
            'country_id' => $country->id,
            'education_system_id' => $system->id,
            'name' => 'Secondary',
            'code' => 'secondary',
            'display_order' => 1,
            'status' => true,
        ]);

        $class = ClassGrade::create([
            'country_id' => $country->id,
            'education_system_id' => $system->id,
            'academic_level_id' => $level->id,
            'name' => 'Class 10',
            'code' => 'c10',
            'display_order' => 0,
            'status' => true,
        ]);

        $group = AcademicGroup::create([
            'country_id' => $country->id,
            'education_system_id' => $system->id,
            'academic_level_id' => $level->id,
            'class_grade_id' => $class->id,
            'name' => 'General',
            'code' => 'g'.mt_rand(10, 99),
            'display_order' => 0,
            'status' => true,
        ]);

        $institute = Institute::create([
            'name' => 'Certificate Institute',
            'slug' => str()->slug('certificate-institute-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);

        $branch = Branch::create([
            'institute_id' => $institute->id,
            'name' => 'Main Campus',
            'status' => 'active',
        ]);

        $owner = InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'role_id' => Role::where('slug', 'institute-owner')->firstOrFail()->id,
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => 'cert-owner-'.uniqid().'@example.test',
            'phone' => '01700'.mt_rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        $teacher = InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'role_id' => Role::where('slug', 'teacher')->firstOrFail()->id,
            'first_name' => 'Teacher',
            'last_name' => 'User',
            'email' => 'cert-teacher-'.uniqid().'@example.test',
            'phone' => '01700'.mt_rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        $student = Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'student_id_number' => 'CR'.mt_rand(100000, 999999),
            'first_name' => 'Grad',
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => '2024-01-01',
        ]);

        $course = Course::create([
            'course_code' => 'CT'.mt_rand(1000, 9999),
            'name' => 'Certificate Course',
        ]);

        $batch = Batch::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'name' => 'Batch C',
            'batch_code' => 'BC-'.mt_rand(10, 99),
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'status' => 'ongoing',
        ]);

        StudentEnrollment::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'roll_number' => 'R'.mt_rand(10, 99),
            'enrollment_date' => '2024-01-05',
            'fee_payable' => 10000,
            'discount' => 0,
            'status' => 'active',
        ]);

        $year = AcademicYear::create([
            'institute_id' => $institute->id,
            'name' => 'Session 2024',
            'code' => '2024',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'is_current' => false,
            'status' => true,
        ]);

        $placement = StudentAcademicPlacement::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $class->id,
            'status' => StudentAcademicPlacement::STATUS_ACTIVE,
        ]);

        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $institute->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $class->id,
            'name' => 'Aggregation Scheme',
            'status' => 'active',
        ]);

        $policy = AcademicFinalResultPolicy::create([
            'institute_id' => $institute->id,
            'scheme_id' => $scheme->id,
            'name' => 'Final Result Policy',
            'absent_renormalization' => true,
            'require_approval' => true,
            'status' => 'active',
        ]);

        $result = AcademicFinalResult::create([
            'institute_id' => $institute->id,
            'policy_id' => $policy->id,
            'scheme_id' => $scheme->id,
            'name' => 'Final Result 2024',
            'status' => AcademicFinalResult::STATUS_PUBLISHED,
        ]);

        $promoPolicy = PromotionPolicy::create([
            'institute_id' => $institute->id,
            'name' => 'Promotion Policy',
            'academic_year_id' => $year->id,
            'class_grade_id' => $class->id,
            'status' => 'active',
        ]);

        $decision = PromotionDecision::create([
            'policy_id' => $promoPolicy->id,
            'result_id' => $result->id,
            'institute_id' => $institute->id,
            'academic_year_id' => $year->id,
            'status' => PromotionDecision::STATUS_APPROVED,
        ]);

        PromotionDecisionItem::create([
            'decision_id' => $decision->id,
            'placement_id' => $placement->id,
            'student_id' => $student->id,
            'decision' => PromotionDecisionItem::DECISION_GRADUATED,
            'approved_at' => now(),
        ]);

        return compact('institute', 'branch', 'owner', 'teacher', 'student', 'course', 'batch');
    }

    private function withContext(Institute $institute): void
    {
        TenantContext::set($institute->id);
        BranchContext::clear();
    }

    // ---------------------------------------------------------------- Tests

    public function test_eligible_graduate_can_request_a_certificate(): void
    {
        $ctx = $this->seededContext();

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('students.certificate-request', $ctx['student']));

        $response->assertRedirect();

        $this->assertDatabaseHas('certificates', [
            'institute_id' => $ctx['institute']->id,
            'student_id' => $ctx['student']->id,
            'course_id' => $ctx['course']->id,
            'batch_id' => $ctx['batch']->id,
            'status' => 'pending',
            'issued_by' => $ctx['owner']->id,
        ]);
        $this->assertNull(Certificate::query()
            ->where('student_id', $ctx['student']->id)
            ->value('certificate_number'));
    }

    public function test_ineligible_student_request_is_rejected(): void
    {
        $ctx = $this->seededContext();

        // Remove the approved graduated outcome → no eligibility.
        DB::table('promotion_decision_items')->where('student_id', $ctx['student']->id)->delete();

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('students.certificate-request', $ctx['student']));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('certificates', ['student_id' => $ctx['student']->id]);
    }

    public function test_duplicate_pending_request_is_rejected(): void
    {
        $ctx = $this->seededContext();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('students.certificate-request', $ctx['student']))
            ->assertRedirect();

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('students.certificate-request', $ctx['student']));

        $response->assertSessionHas('error');
        $this->assertSame(1, Certificate::query()
            ->where('student_id', $ctx['student']->id)
            ->where('status', 'pending')
            ->count());
    }

    public function test_issued_certificate_for_same_batch_blocks_new_request(): void
    {
        $ctx = $this->seededContext();

        Certificate::create([
            'institute_id' => $ctx['institute']->id,
            'student_id' => $ctx['student']->id,
            'course_id' => $ctx['course']->id,
            'batch_id' => $ctx['batch']->id,
            'status' => 'active',
            'certificate_number' => 'MNT-2026-'.mt_rand(10000, 99999),
            'issue_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('students.certificate-request', $ctx['student']));

        $response->assertSessionHas('error');
        $this->assertSame(0, Certificate::query()
            ->where('student_id', $ctx['student']->id)
            ->where('status', 'pending')
            ->count());
    }

    public function test_teacher_without_certificates_manage_cannot_request(): void
    {
        $ctx = $this->seededContext();

        $response = $this->actingAs($ctx['teacher'], 'institute_user')
            ->post(route('students.certificate-request', $ctx['student']));

        $response->assertForbidden();
        $this->assertDatabaseMissing('certificates', ['student_id' => $ctx['student']->id]);
    }

    public function test_certificate_request_is_read_only_for_other_sources_of_truth(): void
    {
        $ctx = $this->seededContext();

        $tables = [
            'students',
            'student_academic_placements',
            'academic_final_results',
            'promotion_decisions',
            'promotion_decision_items',
            'student_enrollments',
            'batches',
        ];

        $before = [];
        foreach ($tables as $table) {
            $before[$table] = collect(DB::table($table)->get()->map(fn ($row) => (array) $row));
        }

        $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('students.certificate-request', $ctx['student']))
            ->assertRedirect();

        foreach ($tables as $table) {
            $after = collect(DB::table($table)->get()->map(fn ($row) => (array) $row));
            $this->assertEquals($before[$table]->all(), $after->all(), "{$table} was mutated by the certificate request.");
        }
    }
}
