<?php

namespace Tests\Feature;

use App\Models\AcademicAssessment;
use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultPolicy;
use App\Models\AcademicFinalResultRow;
use App\Models\AcademicFinalResultStudent;
use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicStudentMark;
use App\Models\AcademicYear;
use App\Models\AssessmentSubject;
use App\Models\AssessmentSubjectComponent;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Component;
use App\Models\Country;
use App\Models\Course;
use App\Models\Document;
use App\Models\EducationSystem;
use App\Models\Guardian;
use App\Models\GuardianStudent;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Step 47 — Parent / Guardian Portal.
 *
 * Dedicated guardian authentication, strict student-link authorization
 * (404 for anything unlinked or cross-institute), read-only pages (attendance,
 * published results, locked-assessment marks, fees, certificates, documents,
 * notifications), password management and a guardian audit trail.
 */
class GuardianPortalTest extends TestCase
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

    private function country(): Country
    {
        do {
            $iso2 = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 2);
        } while (Country::where('iso2', $iso2)->exists());

        return Country::create([
            'name' => 'Bangladesh',
            'iso2' => $iso2,
            'iso3' => 'BGD',
            'phone_code' => '880',
            'status' => true,
        ]);
    }

    private function system(Country $country): EducationSystem
    {
        return EducationSystem::firstOrCreate(
            ['country_id' => $country->id, 'code' => 'general'],
            ['name' => 'General Education', 'display_order' => 0, 'status' => true]
        );
    }

    private function level(EducationSystem $system): AcademicLevel
    {
        return AcademicLevel::create([
            'country_id' => $system->country_id,
            'education_system_id' => $system->id,
            'name' => 'Secondary',
            'code' => 'secondary',
            'display_order' => 1,
            'status' => true,
        ]);
    }

    private function classGrade(AcademicLevel $level): ClassGrade
    {
        return ClassGrade::create([
            'country_id' => $level->country_id,
            'education_system_id' => $level->education_system_id,
            'academic_level_id' => $level->id,
            'name' => 'Class 8',
            'code' => 'c8',
            'display_order' => 0,
            'status' => true,
        ]);
    }

    private function group(ClassGrade $classGrade): AcademicGroup
    {
        return AcademicGroup::create([
            'country_id' => $classGrade->country_id,
            'education_system_id' => $classGrade->education_system_id,
            'academic_level_id' => $classGrade->academic_level_id,
            'class_grade_id' => $classGrade->id,
            'name' => 'Science',
            'code' => 'sci-'.mt_rand(10, 99),
            'display_order' => 0,
            'status' => true,
        ]);
    }

    private function institute(Country $country, string $name): Institute
    {
        return Institute::create([
            'name' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute, string $name): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function owner(Institute $institute): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->firstOrFail()->id,
            'first_name' => 'Staff',
            'last_name' => 'User',
            'email' => 'owner-'.uniqid().'@example.test',
            'phone' => '01700'.mt_rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function year(Institute $institute): AcademicYear
    {
        return AcademicYear::create([
            'institute_id' => $institute->id,
            'name' => 'Session 2026',
            'code' => '2026',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'is_current' => true,
            'status' => true,
        ]);
    }

    private function batch(Institute $institute): Batch
    {
        return Batch::create([
            'institute_id' => $institute->id,
            'branch_id' => null,
            'course_id' => Course::create([
                'course_code' => 'C'.mt_rand(1000, 9999),
                'name' => 'Guardian Course',
            ])->id,
            'name' => 'Batch G-01',
            'batch_code' => 'BG-'.mt_rand(10, 99),
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'ongoing',
        ]);
    }

    private function student(Institute $institute, string $name, ?Branch $branch = null): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'student_id_number' => 'GP'.mt_rand(100000, 999999),
            'first_name' => $name,
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => '2026-01-01',
        ]);
    }

    private function placement(Institute $institute, Student $student, AcademicYear $academicYear, ClassGrade $class, ?AcademicGroup $group = null): StudentAcademicPlacement
    {
        return StudentAcademicPlacement::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'class_grade_id' => $class->id,
            'academic_group_id' => $group?->id,
            'status' => StudentAcademicPlacement::STATUS_ACTIVE,
        ]);
    }

    private function enroll(Student $student, Batch $batch): StudentEnrollment
    {
        return StudentEnrollment::create([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'course_id' => $batch->course_id,
            'batch_id' => $batch->id,
            'roll_number' => 'R'.mt_rand(1000, 9999),
            'enrollment_date' => '2026-01-01',
            'fee_payable' => 12000,
            'status' => 'active',
        ]);
    }

    private function guardian(Institute $institute, string $name, string $status = 'active'): Guardian
    {
        return Guardian::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'phone' => '01800'.mt_rand(100000, 999999),
            'email' => strtolower(str_replace(' ', '-', $name)).'-'.uniqid().'@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => $status,
            'preferred_language' => 'en',
        ]);
    }

    private function link(Guardian $guardian, Student $student, bool $primary = false): GuardianStudent
    {
        return GuardianStudent::create([
            'institute_id' => $guardian->institute_id,
            'guardian_id' => $guardian->id,
            'student_id' => $student->id,
            'relationship' => 'father',
            'is_primary' => $primary,
            'status' => 'active',
        ]);
    }

    private function attendanceRow(Institute $institute, Student $student, Batch $batch, string $date, string $status): Attendance
    {
        return Attendance::create([
            'institute_id' => $institute->id,
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'class_date' => $date,
            'status' => $status,
        ]);
    }

    private function invoice(Institute $institute, Student $student, string $status): Invoice
    {
        return Invoice::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'invoice_number' => 'GPINV-'.uniqid(),
            'invoice_type' => 'course_fee',
            'total_amount' => 12000,
            'discount' => 0,
            'payable_amount' => 12000,
            'paid_amount' => $status === 'paid' ? 12000 : 0,
            'due_amount' => $status === 'paid' ? 0 : 12000,
            'status' => $status,
            'due_date' => '2026-03-01',
        ]);
    }

    private function subject(string $name, string $code): Subject
    {
        return Subject::create([
            'institute_id' => null,
            'category_id' => null,
            'subject_type' => 'academic',
            'subject_code' => $code,
            'name' => $name,
            'slug' => str()->slug($name.'-'.substr(md5($name.$code), 0, 6)),
            'short_name' => substr($name, 0, 8),
            'description' => null,
            'status' => 'active',
        ]);
    }

    private function withContext(Institute $institute): void
    {
        TenantContext::set($institute->id);
        BranchContext::clear();
    }

    /**
     * Full portal context: institute + branch + owner + 2026 year + class8
     * group + batch + two linked students + one unlinked student in the same
     * institute + a second institute with its own guardian & student.
     */
    private function standardContext(): array
    {
        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $class8 = $this->classGrade($level);
        $group = $this->group($class8);

        $institute = $this->institute($country, 'Guardian Institute');
        $this->withContext($institute);
        $branch = $this->branch($institute, 'Main Branch');
        $owner = $this->owner($institute);
        $year = $this->year($institute);
        $batch = $this->batch($institute);

        $student = $this->student($institute, 'Robin');
        $this->placement($institute, $student, $year, $class8, $group);
        $this->enroll($student, $batch);

        $secondLinked = $this->student($institute, 'Sumona');
        $this->enroll($secondLinked, $batch);

        $unlinked = $this->student($institute, 'Tania');
        $this->enroll($unlinked, $batch);

        $guardian = $this->guardian($institute, 'Robin Parent');
        $this->link($guardian, $student, true);
        $this->link($guardian, $secondLinked);

        $otherInstitute = $this->institute($country, 'Other Institute');
        $this->withContext($otherInstitute);
        $otherStudent = $this->student($otherInstitute, 'Alice');
        $otherGuardian = $this->guardian($otherInstitute, 'Alice Parent');
        $this->link($otherGuardian, $otherStudent);

        $this->withContext($institute);

        return compact('country', 'class8', 'group', 'institute', 'branch', 'owner', 'year', 'batch',
            'student', 'secondLinked', 'unlinked', 'guardian', 'otherInstitute', 'otherStudent', 'otherGuardian');
    }

    private function bodyHas(TestResponse $response, string $needle): void
    {
        $this->assertStringContainsString($needle, $response->getContent());
    }

    private function bodyMissing(TestResponse $response, string $needle): void
    {
        $this->assertStringNotContainsString($needle, $response->getContent());
    }

    // ------------------------------------------------------ Authentication

    public function test_guardian_can_log_in_with_valid_credentials(): void
    {
        $ctx = $this->standardContext();

        $this->post(route('guardian.login.submit'), [
            'email' => $ctx['guardian']->email,
            'password' => $this->password,
        ])->assertRedirect(route('guardian.dashboard'));

        $this->assertAuthenticatedAs($ctx['guardian'], 'guardian');
    }

    public function test_guardian_login_fails_with_wrong_password(): void
    {
        $ctx = $this->standardContext();

        $this->post(route('guardian.login.submit'), [
            'email' => $ctx['guardian']->email,
            'password' => 'not-the-password',
        ])->assertSessionHasErrors('email');

        $this->assertGuest('guardian');
    }

    public function test_inactive_guardian_cannot_log_in(): void
    {
        $ctx = $this->standardContext();
        $ctx['guardian']->forceFill(['status' => 'inactive'])->save();

        $this->post(route('guardian.login.submit'), [
            'email' => $ctx['guardian']->email,
            'password' => $this->password,
        ])->assertSessionHasErrors('email');

        $this->assertGuest('guardian');
    }

    public function test_locked_guardian_cannot_log_in(): void
    {
        $ctx = $this->standardContext();
        $ctx['guardian']->forceFill(['locked_until' => now()->addMinutes(10)])->save();

        $this->post(route('guardian.login.submit'), [
            'email' => $ctx['guardian']->email,
            'password' => $this->password,
        ])->assertSessionHasErrors('email');

        $this->assertGuest('guardian');
    }

    public function test_repeated_failed_logins_lock_the_guardian_account(): void
    {
        $ctx = $this->standardContext();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('guardian.login.submit'), [
                'email' => $ctx['guardian']->email,
                'password' => 'wrong-pass',
            ]);
        }

        $ctx['guardian']->refresh();

        $this->assertNotNull($ctx['guardian']->locked_until);
        $this->assertTrue($ctx['guardian']->locked_until->isFuture());
    }

    public function test_guardian_never_receives_institute_permissions(): void
    {
        $ctx = $this->standardContext();

        $this->assertFalse($ctx['guardian']->hasPermission('education.manage'));
        $this->assertFalse($ctx['guardian']->hasAnyPermission(['finance.manage', 'crm.manage']));

        // Acting as a guardian cannot reach institute-staff protected pages.
        $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('dashboard'))
            ->assertRedirect();

        $this->assertNotSame('institute_user', config('auth.guards.guardian.driver'));
    }

    public function test_guest_is_redirected_to_guardian_login_for_guardian_routes(): void
    {
        $this->get(route('guardian.dashboard'))
            ->assertRedirect(route('guardian.login'));
    }

    // --------------------------------------------- Authorization (404 rules)

    public function test_guardian_cannot_access_another_institutes_student(): void
    {
        $ctx = $this->standardContext();

        $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('guardian.students.show', $ctx['otherStudent']->id))
            ->assertNotFound();
    }

    public function test_guardian_cannot_access_unlinked_student_in_same_institute(): void
    {
        $ctx = $this->standardContext();

        $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('guardian.students.show', $ctx['unlinked']->id))
            ->assertNotFound();
    }

    public function test_guardian_cannot_switch_to_unlinked_student(): void
    {
        $ctx = $this->standardContext();

        $this->actingAs($ctx['guardian'], 'guardian')
            ->post(route('guardian.student.switch'), ['student_id' => $ctx['unlinked']->id])
            ->assertNotFound();
    }

    // ------------------------------------------------------------ Dashboard

    public function test_dashboard_shows_linked_student_context(): void
    {
        $ctx = $this->standardContext();

        $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertSee('Robin Student');
    }

    public function test_students_list_shows_only_linked_students(): void
    {
        $ctx = $this->standardContext();

        $response = $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('guardian.students'))
            ->assertOk();

        $this->bodyHas($response, 'Robin Student');
        $this->bodyHas($response, 'Sumona Student');
        $this->bodyMissing($response, 'Tania Student');
    }

    public function test_student_profile_is_read_only(): void
    {
        $ctx = $this->standardContext();

        $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('guardian.students.show', $ctx['student']->id))
            ->assertOk()
            ->assertSee($ctx['student']->student_id_number)
            ->assertSee('Guardian Course');
    }

    // -------------------------------------------------------------- Academic

    public function test_attendance_page_reuses_read_only_report(): void
    {
        $ctx = $this->standardContext();
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-10', 'present');
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-11', 'absent');
        // A day with no row is unrecorded — never an absence.
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-12', 'present');

        $response = $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('guardian.students.attendance', $ctx['student']->id))
            ->assertOk();

        $this->bodyHas($response, 'Robin Student');
        $this->bodyHas($response, 'Session 2026');
        // 3 marked rows: 2 present + 1 absent -> 66.7%
        $this->bodyHas($response, '66.7%');

        // The page never writes attendance rows.
        $this->assertSame(3, Attendance::query()->where('student_id', $ctx['student']->id)->count());
    }

    public function test_results_page_shows_only_published_results(): void
    {
        $ctx = $this->standardContext();
        $subj = $this->subject('Mathematics', 'GM'.mt_rand(1000, 9999));

        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $ctx['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $ctx['year']->id,
            'class_grade_id' => $ctx['class8']->id,
            'academic_group_id' => $ctx['group']->id,
            'name' => 'Scheme 2026',
            'status' => 'active',
        ]);

        $policy = AcademicFinalResultPolicy::create([
            'institute_id' => $ctx['institute']->id,
            'branch_id' => null,
            'scheme_id' => $scheme->id,
            'name' => 'Policy 2026',
            'require_approval' => true,
            'status' => 'active',
        ]);

        $placement = $ctx['student']->currentAcademicPlacement();

        $published = AcademicFinalResult::create([
            'institute_id' => $ctx['institute']->id,
            'branch_id' => null,
            'policy_id' => $policy->id,
            'scheme_id' => $scheme->id,
            'name' => 'Term Final 2026',
            'status' => AcademicFinalResult::STATUS_PUBLISHED,
            'locked_at' => now(),
            'published_at' => now(),
        ]);

        AcademicFinalResultStudent::create([
            'result_id' => $published->id,
            'placement_id' => $placement->id,
            'gpa' => 4.5,
            'gpa_status' => 'computed',
            'passed_count' => 1,
            'failed_count' => 0,
        ]);

        AcademicFinalResultRow::create([
            'result_id' => $published->id,
            'placement_id' => $placement->id,
            'subject_id' => $subj->id,
            'status' => 'passed',
            'aggregate' => 88,
            'grade' => 'A+',
            'grade_point' => 5.0,
            'subject_status' => 'passed',
            'gpa_included' => true,
            'optional' => false,
        ]);

        // An approved-but-unpublished cycle must NOT be shown.
        AcademicFinalResult::create([
            'institute_id' => $ctx['institute']->id,
            'branch_id' => null,
            'policy_id' => $policy->id,
            'scheme_id' => $scheme->id,
            'name' => 'Draft Cycle Hidden',
            'status' => AcademicFinalResult::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        $response = $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('guardian.students.results', $ctx['student']->id))
            ->assertOk();

        $this->bodyHas($response, 'Term Final 2026');
        $this->bodyHas($response, '4.50');
        $this->bodyHas($response, 'Mathematics');
        $this->bodyMissing($response, 'Draft Cycle Hidden');
    }

    public function test_results_page_hides_marks_of_non_locked_assessments(): void
    {
        $ctx = $this->standardContext();
        $subj = $this->subject('English', 'GE'.mt_rand(1000, 9999));

        // A published final result in the same year unlocks locked-assessment
        // marks for that year.
        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $ctx['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $ctx['year']->id,
            'class_grade_id' => $ctx['class8']->id,
            'academic_group_id' => null,
            'name' => 'Scheme 2026',
            'status' => 'active',
        ]);

        $policy = AcademicFinalResultPolicy::create([
            'institute_id' => $ctx['institute']->id,
            'branch_id' => null,
            'scheme_id' => $scheme->id,
            'name' => 'Policy 2026',
            'status' => 'active',
        ]);

        $placement = $ctx['student']->currentAcademicPlacement();

        AcademicFinalResult::create([
            'institute_id' => $ctx['institute']->id,
            'branch_id' => null,
            'policy_id' => $policy->id,
            'scheme_id' => $scheme->id,
            'name' => 'Term Final 2026',
            'status' => AcademicFinalResult::STATUS_PUBLISHED,
            'locked_at' => now(),
            'published_at' => now(),
        ]);

        $published = AcademicFinalResult::query()->where('name', 'Term Final 2026')->firstOrFail();

        AcademicFinalResultStudent::create([
            'result_id' => $published->id,
            'placement_id' => $placement->id,
            'gpa' => 4.0,
            'gpa_status' => 'computed',
            'passed_count' => 1,
            'failed_count' => 0,
        ]);

        $locked = AcademicAssessment::create([
            'institute_id' => $ctx['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $ctx['year']->id,
            'class_grade_id' => $ctx['class8']->id,
            'name' => 'Mid Term Locked',
            'exam_date' => '2026-05-01',
            'status' => AcademicAssessment::STATUS_COMPLETED,
            'locked_at' => now(),
        ]);

        $draft = AcademicAssessment::create([
            'institute_id' => $ctx['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $ctx['year']->id,
            'class_grade_id' => $ctx['class8']->id,
            'name' => 'Monthly Draft Hidden',
            'exam_date' => '2026-04-01',
            'status' => AcademicAssessment::STATUS_DRAFT,
            'locked_at' => null,
        ]);

        $this->makeMark($ctx['institute'], $ctx['student'], $placement, $locked, $subj, 'WR', 90);
        $this->makeMark($ctx['institute'], $ctx['student'], $placement, $draft, $subj, 'WR', 10);

        $response = $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('guardian.students.results', $ctx['student']->id))
            ->assertOk();

        $this->bodyHas($response, 'Mid Term Locked');
        $this->bodyHas($response, '90');
        $this->bodyMissing($response, 'Monthly Draft Hidden');
    }

    private function makeMark(Institute $institute, Student $student, StudentAcademicPlacement $placement, AcademicAssessment $assessment, Subject $subject, string $componentSlug, float $obtained): void
    {
        $assessmentSubject = AssessmentSubject::create([
            'assessment_id' => $assessment->id,
            'subject_id' => $subject->id,
            'display_order' => 1,
            'status' => 'active',
        ]);

        $component = Component::query()->first()
            ?? Component::create([
                'institute_id' => null,
                'country_id' => null,
                'name' => 'Written',
                'slug' => 'written-'.mt_rand(100, 999),
                'description' => null,
                'display_order' => 1,
                'status' => true,
            ]);

        $assessmentComponent = AssessmentSubjectComponent::create([
            'assessment_subject_id' => $assessmentSubject->id,
            'component_id' => $component->id,
            'full_mark' => 100,
            'pass_mark' => 50,
            'mandatory_pass' => true,
            'display_order' => 1,
            'status' => 'active',
        ]);

        AcademicStudentMark::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'academic_assessment_id' => $assessment->id,
            'assessment_subject_id' => $assessmentSubject->id,
            'assessment_component_id' => $assessmentComponent->id,
            'academic_placement_id' => $placement->id,
            'obtained_mark' => $obtained,
            'status' => 'entered',
            'entered_by' => null,
        ]);
    }

    // ------------------------------------------------------------- Finance

    public function test_fees_page_is_read_only(): void
    {
        $ctx = $this->standardContext();
        $invoice = $this->invoice($ctx['institute'], $ctx['student'], 'unpaid');

        $response = $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('guardian.students.fees', $ctx['student']->id))
            ->assertOk();

        $this->bodyHas($response, $invoice->invoice_number);
        $this->bodyHas($response, '12,000');

        // The page never writes invoices/payments.
        $this->assertSame(1, Invoice::query()->where('student_id', $ctx['student']->id)->count());
    }

    // ---------------------------------------------------------- Certificates

    public function test_certificates_page_shows_student_certificates(): void
    {
        $ctx = $this->standardContext();

        $active = DB::table('certificates')->insertGetId([
            'uuid' => (string) Str::uuid(),
            'institute_id' => $ctx['institute']->id,
            'student_id' => $ctx['student']->id,
            'course_id' => $ctx['batch']->course_id,
            'batch_id' => $ctx['batch']->id,
            'certificate_number' => 'MNT-'.mt_rand(10000, 99999),
            'issue_date' => '2026-07-01',
            'status' => 'active',
        ]);

        DB::table('certificates')->insert([
            'uuid' => (string) Str::uuid(),
            'institute_id' => $ctx['institute']->id,
            'student_id' => $ctx['student']->id,
            'course_id' => $ctx['batch']->course_id,
            'batch_id' => $ctx['batch']->id,
            'certificate_number' => null,
            'issue_date' => null,
            'status' => 'pending',
        ]);

        $certificateNumber = DB::table('certificates')->where('id', $active)->value('certificate_number');

        $response = $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('guardian.students.certificates', $ctx['student']->id))
            ->assertOk();

        $this->bodyHas($response, 'Issued');
        $this->bodyHas($response, $certificateNumber);
        $this->bodyHas($response, route('verify.certificate', $certificateNumber));
        $this->bodyHas($response, 'Pending');
    }

    // ------------------------------------------------------------- Documents

    private function putDocument(Institute $institute, Student $student, string $status, string $label): Document
    {
        $path = 'guardian-tests/'.uniqid().'.txt';
        Storage::disk('public')->put($path, 'Hello '.$label);

        return Document::create([
            'institute_id' => $institute->id,
            'branch_id' => null,
            'documentable_type' => Student::class,
            'documentable_id' => $student->id,
            'title' => $label,
            'original_filename' => $label.'.txt',
            'file_path' => $path,
            'disk' => 'public',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'file_size' => strlen('Hello '.$label),
            'version' => 1,
            'status' => $status,
        ]);
    }

    public function test_documents_page_lists_only_active_documents(): void
    {
        $ctx = $this->standardContext();
        $active = $this->putDocument($ctx['institute'], $ctx['student'], 'active', 'Report Card');
        $this->putDocument($ctx['institute'], $ctx['student'], 'archived', 'Old Form');

        $response = $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('guardian.students.documents', $ctx['student']->id))
            ->assertOk();

        $this->bodyHas($response, 'Report Card');
        $this->bodyMissing($response, 'Old Form');

        Storage::disk('public')->delete($active->file_path);
    }

    public function test_document_download_for_linked_student(): void
    {
        $ctx = $this->standardContext();
        $document = $this->putDocument($ctx['institute'], $ctx['student'], 'active', 'Admission Form');

        $response = $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('guardian.students.documents.download', [$ctx['student']->id, $document->id]))
            ->assertOk()
            ->assertDownload('Admission Form.txt');

        $this->assertSame('Hello Admission Form', $response->streamedContent());

        Storage::disk('public')->delete($document->file_path);
    }

    public function test_document_download_404_for_unlinked_student_document(): void
    {
        $ctx = $this->standardContext();
        $document = $this->putDocument($ctx['institute'], $ctx['unlinked'], 'active', 'Private Doc');

        $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('guardian.students.documents.download', [$ctx['unlinked']->id, $document->id]))
            ->assertNotFound();

        Storage::disk('public')->delete($document->file_path);
    }

    public function test_document_download_404_for_cross_institute_document(): void
    {
        $ctx = $this->standardContext();
        $this->withContext($ctx['otherInstitute']);
        $document = $this->putDocument($ctx['otherInstitute'], $ctx['otherStudent'], 'active', 'Other Doc');
        $this->withContext($ctx['institute']);

        $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('guardian.students.documents.download', [$ctx['otherStudent']->id, $document->id]))
            ->assertNotFound();

        Storage::disk('public')->delete($document->file_path);
    }

    // --------------------------------------------------------- Notifications

    public function test_notifications_page_shows_institute_and_student_notifications(): void
    {
        $ctx = $this->standardContext();

        DB::table('notifications')->insert([
            [
                'scope' => 'institute',
                'institute_id' => $ctx['institute']->id,
                'target_user_type' => null,
                'target_user_id' => null,
                'category' => 'certificate',
                'title' => 'Institute-wide notice',
                'message' => 'Annual results released.',
                'created_by_type' => 'system',
                'created_by_id' => null,
                'created_at' => now(),
            ],
            [
                'scope' => 'user',
                'institute_id' => $ctx['institute']->id,
                'target_user_type' => 'student',
                'target_user_id' => $ctx['student']->id,
                'category' => 'academic',
                'title' => 'Student notice',
                'message' => 'Robin attendance update.',
                'created_by_type' => 'system',
                'created_by_id' => null,
                'created_at' => now(),
            ],
        ]);

        $response = $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('guardian.notifications'))
            ->assertOk();

        $this->bodyHas($response, 'Institute-wide notice');
        $this->bodyHas($response, 'Student notice');
    }

    // --------------------------------------------------------------- Profile

    public function test_profile_password_change_succeeds_with_correct_current_password(): void
    {
        $ctx = $this->standardContext();

        $this->actingAs($ctx['guardian'], 'guardian')
            ->put(route('guardian.profile.password'), [
                'current_password' => $this->password,
                'password' => 'BrandNewSecret1!',
                'password_confirmation' => 'BrandNewSecret1!',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('BrandNewSecret1!', $ctx['guardian']->fresh()->getAuthPassword()));
    }

    public function test_profile_password_change_fails_with_wrong_current_password(): void
    {
        $ctx = $this->standardContext();

        $this->actingAs($ctx['guardian'], 'guardian')
            ->put(route('guardian.profile.password'), [
                'current_password' => 'wrong-current',
                'password' => 'BrandNewSecret1!',
                'password_confirmation' => 'BrandNewSecret1!',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check($this->password, $ctx['guardian']->fresh()->getAuthPassword()));
    }

    // --------------------------------------------------------- Student switch

    public function test_active_student_switch_works(): void
    {
        $ctx = $this->standardContext();

        $this->actingAs($ctx['guardian'], 'guardian')
            ->post(route('guardian.student.switch'), ['student_id' => $ctx['secondLinked']->id])
            ->assertRedirect(route('guardian.dashboard'));

        $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('guardian.dashboard'))
            ->assertOk()
            ->assertSee('Sumona Student');
    }

    // ---------------------------------------------------------------- Audit

    public function test_guardian_actions_are_audited(): void
    {
        $ctx = $this->standardContext();
        $this->withContext($ctx['institute']);

        $this->post(route('guardian.login.submit'), [
            'email' => $ctx['guardian']->email,
            'password' => $this->password,
        ])->assertRedirect(route('guardian.dashboard'));

        $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('guardian.students.attendance', $ctx['student']->id))
            ->assertOk();

        $document = $this->putDocument($ctx['institute'], $ctx['student'], 'active', 'Audited Doc');

        $this->actingAs($ctx['guardian'], 'guardian')
            ->get(route('guardian.students.documents.download', [$ctx['student']->id, $document->id]))
            ->assertOk();

        $audited = DB::table('audit_logs')
            ->where('user_type', 'guardian')
            ->where('user_id', $ctx['guardian']->id)
            ->pluck('action')
            ->all();

        $this->assertContains('guardian_login', $audited);
        $this->assertContains('guardian_viewed_attendance', $audited);
        $this->assertContains('guardian_downloaded_document', $audited);

        Storage::disk('public')->delete($document->file_path);
    }
}
