<?php

namespace Tests\Feature;

use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultPolicy;
use App\Models\AcademicFinalResultRow;
use App\Models\AcademicFinalResultStudent;
use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicYear;
use App\Models\Alumni;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\EducationSystem;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PromotionDecision;
use App\Models\PromotionDecisionItem;
use App\Models\PromotionPolicy;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\StudentSubjectSelection;
use App\Models\Subject;
use App\Models\SubjectAcademicAssignment;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Step 48 — Alumni Management.
 *
 * Covers activation eligibility (same rule as the certificate request service),
 * idempotency, tenant + branch isolation, the alumni.view/create/update/delete
 * permission matrix, profile update (academic provenance read-only), status
 * toggle, delete, directory/reports/export, and a data-integrity snapshot that
 * alumni operations never mutate the existing academic / CRM / finance records.
 */
class AlumniManagementTest extends TestCase
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

    private function country(string $iso2 = 'BD', string $name = 'Bangladesh'): Country
    {
        return Country::firstOrCreate(['iso2' => $iso2], ['name' => $name,
            'iso3' => strtoupper($iso2).'D',
            'phone_code' => '880',
            'status' => true,
        ]);
    }

    private function system(Country $country, string $code = 'general'): EducationSystem
    {
        return EducationSystem::firstOrCreate(
            ['country_id' => $country->id, 'code' => $code],
            ['name' => 'General Education', 'display_order' => 0, 'status' => true]
        );
    }

    private function level(EducationSystem $system, string $code = 'secondary'): AcademicLevel
    {
        return AcademicLevel::create([
            'country_id' => $system->country_id,
            'education_system_id' => $system->id,
            'name' => 'Secondary',
            'code' => $code,
            'display_order' => 1,
            'status' => true,
        ]);
    }

    private function classGrade(AcademicLevel $level, string $code, string $name): ClassGrade
    {
        return ClassGrade::create([
            'country_id' => $level->country_id,
            'education_system_id' => $level->education_system_id,
            'academic_level_id' => $level->id,
            'name' => $name,
            'code' => $code,
            'display_order' => 0,
            'status' => true,
        ]);
    }

    private function group(ClassGrade $classGrade, string $code = 'gen'): AcademicGroup
    {
        return AcademicGroup::create([
            'country_id' => $classGrade->country_id,
            'education_system_id' => $classGrade->education_system_id,
            'academic_level_id' => $classGrade->academic_level_id,
            'class_grade_id' => $classGrade->id,
            'name' => 'General',
            'code' => $code,
            'display_order' => 0,
            'status' => true,
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

    private function assign(Subject $subject, ClassGrade $classGrade): SubjectAcademicAssignment
    {
        return SubjectAcademicAssignment::create([
            'subject_id' => $subject->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => null,
            'requirement_type' => 'mandatory',
            'selection_group_id' => null,
            'display_order' => 1,
            'status' => 'active',
        ]);
    }

    private function institute(Country $country, string $name = 'Alumni Inst'): Institute
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

    private function user(Institute $institute, string $roleSlug, string $prefix, ?Branch $branch = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'first_name' => 'Staff',
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function student(Institute $institute, string $name = 'Rahim', ?Branch $branch = null): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'student_id_number' => 'AL'.mt_rand(100000, 999999),
            'first_name' => $name,
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => now()->toDateString(),
        ]);
    }

    private function year(Institute $institute, string $code, string $name): AcademicYear
    {
        return AcademicYear::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'code' => $code,
            'is_current' => false,
            'status' => true,
        ]);
    }

    private function curriculum(): array
    {
        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $class = $this->classGrade($level, 'al-c10', 'Class 10');
        $group = $this->group($class);
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'al-owner');

        $math = $this->subject('Mathematics', 'AL100001');
        $english = $this->subject('English', 'AL100002');
        $this->assign($math, $class);
        $this->assign($english, $class);

        return [
            'country' => $country,
            'class' => $class,
            'group' => $group,
            'institute' => $institute,
            'owner' => $owner,
            'subjects' => compact('math', 'english'),
        ];
    }

    private function placement(array $c, Student $student, AcademicYear $year, ClassGrade $class, ?AcademicGroup $group = null): StudentAcademicPlacement
    {
        $placement = StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $class->id,
            'academic_group_id' => $group?->id,
            'status' => 'active',
        ]);

        foreach ($c['subjects'] as $subject) {
            StudentSubjectSelection::create([
                'institute_id' => $c['institute']->id,
                'academic_placement_id' => $placement->id,
                'subject_id' => $subject->id,
                'is_selected' => true,
                'is_mandatory' => false,
            ]);
        }

        return $placement;
    }

    private function finalResult(
        array $c,
        StudentAcademicPlacement $placement,
        AcademicYear $year,
        ClassGrade $class,
        ?AcademicGroup $group,
        string $name,
        string $status = AcademicFinalResult::STATUS_PUBLISHED,
        float $gpa = 4.75
    ): AcademicFinalResult {
        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $year->id,
            'class_grade_id' => $class->id,
            'academic_group_id' => $group?->id,
            'name' => 'Scheme '.$name,
            'status' => 'active',
            'display_order' => 1,
        ]);

        $policy = AcademicFinalResultPolicy::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'scheme_id' => $scheme->id,
            'name' => $name.' Policy',
        ]);

        $result = AcademicFinalResult::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'policy_id' => $policy->id,
            'scheme_id' => $scheme->id,
            'name' => $name,
            'status' => $status,
            'published_at' => $status === AcademicFinalResult::STATUS_PUBLISHED ? now() : null,
            'locked_at' => $status !== AcademicFinalResult::STATUS_REVIEW ? now() : null,
        ]);

        if ($status === AcademicFinalResult::STATUS_PUBLISHED) {
            AcademicFinalResultStudent::create([
                'result_id' => $result->id,
                'placement_id' => $placement->id,
                'gpa' => $gpa,
                'gpa_status' => AcademicFinalResultStudent::GPA_COMPUTED,
                'passed_count' => 2,
                'failed_count' => 0,
            ]);

            AcademicFinalResultRow::create([
                'result_id' => $result->id,
                'placement_id' => $placement->id,
                'subject_id' => $c['subjects']['math']->id,
                'status' => 'computed',
                'aggregate' => 90.5,
                'grade' => 'A+',
                'grade_point' => 5.0,
                'subject_status' => 'PASS',
                'gpa_included' => true,
            ]);
        }

        return $result;
    }

    private function decision(
        array $c,
        StudentAcademicPlacement $placement,
        Student $student,
        AcademicYear $year,
        ClassGrade $class,
        AcademicFinalResult $result,
        string $outcome,
        string $status = PromotionDecision::STATUS_APPROVED
    ): void {
        $policy = PromotionPolicy::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'name' => 'Final '.$year->name,
            'academic_year_id' => $year->id,
            'class_grade_id' => $class->id,
            'academic_group_id' => null,
            'status' => 'active',
            'created_by' => $c['owner']->id,
        ]);

        $decision = PromotionDecision::create([
            'policy_id' => $policy->id,
            'result_id' => $result->id,
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $year->id,
            'status' => $status,
            'reviewed_at' => $status === PromotionDecision::STATUS_REVIEW ? now() : null,
            'approved_by' => $status === PromotionDecision::STATUS_APPROVED ? $c['owner']->id : null,
            'approved_at' => $status === PromotionDecision::STATUS_APPROVED ? now() : null,
            'created_by' => $c['owner']->id,
        ]);

        PromotionDecisionItem::create([
            'decision_id' => $decision->id,
            'placement_id' => $placement->id,
            'student_id' => $student->id,
            'decision' => $outcome,
            'reasons' => [$status === PromotionDecision::STATUS_APPROVED ? 'Approved outcome' : 'Under review'],
            'target_class_grade_id' => $class->id,
            'next_placement_id' => null,
            'approved_by' => $status === PromotionDecision::STATUS_APPROVED ? $c['owner']->id : null,
            'approved_at' => $status === PromotionDecision::STATUS_APPROVED ? now() : null,
        ]);
    }

    /**
     * A student that is eligible for alumni (graduated + published result) in
     * an institute that also has an owner.
     *
     * @return array{c: array, student: Student, year: AcademicYear}
     */
    private function eligibleStudent(string $outcome = PromotionDecisionItem::DECISION_GRADUATED): array
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $result = $this->finalResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2027');
        $this->decision($c, $placement, $student, $year, $c['class'], $result, $outcome);

        return ['c' => $c, 'student' => $student, 'year' => $year];
    }

    // ------------------------------------------------------------- Activation

    public function test_owner_can_activate_eligible_graduated_student(): void
    {
        ['c' => $c, 'student' => $student] = $this->eligibleStudent();

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.store'), ['student_id' => $student->id])
            ->assertRedirect(route('alumni.show', Alumni::query()->first()));

        $alumni = Alumni::query()->where('student_id', $student->id)->firstOrFail();
        $this->assertSame(Alumni::STATUS_ACTIVE, $alumni->status);
        $this->assertSame($student->institute_id, $alumni->institute_id);
        $this->assertNotNull($alumni->graduation_date);
        $this->assertSame($student->id, $alumni->student_id);
    }

    public function test_completed_outcome_is_eligible(): void
    {
        ['c' => $c, 'student' => $student] = $this->eligibleStudent(PromotionDecisionItem::DECISION_COMPLETED);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.store'), ['student_id' => $student->id])
            ->assertRedirect();

        $this->assertDatabaseHas('alumni', ['student_id' => $student->id]);
    }

    public function test_activation_is_idempotent(): void
    {
        ['c' => $c, 'student' => $student] = $this->eligibleStudent();

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.store'), ['student_id' => $student->id])
            ->assertRedirect();

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.store'), ['student_id' => $student->id])
            ->assertRedirect();

        $this->assertSame(1, Alumni::query()->where('student_id', $student->id)->count());
    }

    public function test_student_without_terminal_outcome_cannot_be_activated(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $this->actingAs($c['owner'], 'institute_user')
            ->from(route('alumni.create'))
            ->post(route('alumni.store'), ['student_id' => $student->id])
            ->assertRedirect(route('alumni.create'));

        $this->assertDatabaseMissing('alumni', ['student_id' => $student->id]);
    }

    public function test_pending_decision_is_not_eligible(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $result = $this->finalResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2027');
        $this->decision($c, $placement, $student, $year, $c['class'], $result,
            PromotionDecisionItem::DECISION_GRADUATED, PromotionDecision::STATUS_PENDING);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.store'), ['student_id' => $student->id])
            ->assertRedirect();

        $this->assertDatabaseMissing('alumni', ['student_id' => $student->id]);
    }

    public function test_unpublished_result_is_not_eligible(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $lockedResult = $this->finalResult($c, $placement, $year, $c['class'], $c['group'], 'Locked Result',
            AcademicFinalResult::STATUS_LOCKED);
        $this->decision($c, $placement, $student, $year, $c['class'], $lockedResult,
            PromotionDecisionItem::DECISION_GRADUATED);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.store'), ['student_id' => $student->id])
            ->assertRedirect();

        $this->assertDatabaseMissing('alumni', ['student_id' => $student->id]);
    }

    // ------------------------------------------------------------- Directory / show

    public function test_directory_lists_alumni_and_show_renders_profile(): void
    {
        ['c' => $c, 'student' => $student] = $this->eligibleStudent();

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.store'), ['student_id' => $student->id]);

        $alumni = Alumni::query()->firstOrFail();

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('alumni.directory'))
            ->assertOk()
            ->assertSee($student->first_name);

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('alumni.show', $alumni))
            ->assertOk()
            ->assertSee('Career Information')
            ->assertSee('Academic History');
    }

    public function test_reports_page_renders_totals(): void
    {
        ['c' => $c, 'student' => $student] = $this->eligibleStudent();

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.store'), ['student_id' => $student->id]);

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('alumni.reports'))
            ->assertOk()
            ->assertSee('By Course');
    }

    public function test_export_streams_csv(): void
    {
        ['c' => $c, 'student' => $student] = $this->eligibleStudent();

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.store'), ['student_id' => $student->id]);

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('alumni.directory.export'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    // ------------------------------------------------------------- Update / status / delete

    public function test_profile_can_be_updated_but_academic_provenance_is_read_only(): void
    {
        ['c' => $c, 'student' => $student] = $this->eligibleStudent();

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.store'), ['student_id' => $student->id]);

        $alumni = Alumni::query()->firstOrFail();
        $originalGraduationDate = $alumni->graduation_date;
        $originalCompletionYear = $alumni->completion_academic_year_id;

        $this->actingAs($c['owner'], 'institute_user')
            ->put(route('alumni.update', $alumni), [
                'current_occupation' => 'Software Engineer',
                'job_title' => 'Senior Engineer',
                'employer' => 'Acme Corp',
                'employment_sector' => 'Technology',
                'current_city' => 'Dhaka',
                'current_country' => 'Bangladesh',
                'higher_education' => 'BSc in CSE',
                'alumni_reference_number' => 'ALM-0001',
                'graduation_date' => now()->subYear()->toDateString(),
                'public_contact_preference' => Alumni::CONTACT_PREFERENCE_EMAIL,
                'profile_visibility' => Alumni::PROFILE_VISIBILITY_PUBLIC,
            ])
            ->assertRedirect(route('alumni.show', $alumni));

        $alumni->refresh();
        $this->assertSame('Software Engineer', $alumni->current_occupation);
        $this->assertSame('Acme Corp', $alumni->employer);
        $this->assertSame('ALM-0001', $alumni->alumni_reference_number);
        $this->assertSame(Alumni::PROFILE_VISIBILITY_PUBLIC, $alumni->profile_visibility);

        // Academic provenance is never changed by the profile update.
        $this->assertSame((string) $originalCompletionYear, (string) $alumni->completion_academic_year_id);
        $this->assertNotSame($originalGraduationDate->toDateString(), $alumni->graduation_date->toDateString());
    }

    public function test_status_can_be_toggled(): void
    {
        ['c' => $c, 'student' => $student] = $this->eligibleStudent();

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.store'), ['student_id' => $student->id]);

        $alumni = Alumni::query()->firstOrFail();

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.status', $alumni), ['status' => Alumni::STATUS_INACTIVE])
            ->assertRedirect();

        $this->assertSame(Alumni::STATUS_INACTIVE, $alumni->fresh()->status);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.status', $alumni), ['status' => Alumni::STATUS_ACTIVE])
            ->assertRedirect();

        $this->assertSame(Alumni::STATUS_ACTIVE, $alumni->fresh()->status);
    }

    public function test_alumni_can_be_deleted_and_student_survives(): void
    {
        ['c' => $c, 'student' => $student] = $this->eligibleStudent();

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.store'), ['student_id' => $student->id]);

        $alumni = Alumni::query()->firstOrFail();

        $this->actingAs($c['owner'], 'institute_user')
            ->delete(route('alumni.destroy', $alumni))
            ->assertRedirect(route('alumni.directory'));

        $this->assertDatabaseMissing('alumni', ['id' => $alumni->id]);
        $this->assertDatabaseHas('students', ['id' => $student->id]);
    }

    // ------------------------------------------------------------- Permissions

    public function test_teacher_and_accountant_are_forbidden(): void
    {
        $institute = $this->institute($this->country());
        $teacher = $this->user($institute, 'teacher', 'al-teacher');
        $accountant = $this->user($institute, 'accountant', 'al-accountant');

        foreach ([$teacher, $accountant] as $actor) {
            $this->actingAs($actor, 'institute_user')
                ->get(route('alumni.index'))
                ->assertForbidden();

            $this->actingAs($actor, 'institute_user')
                ->get(route('alumni.directory'))
                ->assertForbidden();
        }
    }

    public function test_receptionist_can_view_but_not_create_or_update(): void
    {
        ['c' => $c, 'student' => $student] = $this->eligibleStudent();

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.store'), ['student_id' => $student->id]);

        $alumni = Alumni::query()->firstOrFail();
        $receptionist = $this->user($c['institute'], 'receptionist', 'al-receptionist');

        $this->actingAs($receptionist, 'institute_user')
            ->get(route('alumni.index'))
            ->assertOk();

        $this->actingAs($receptionist, 'institute_user')
            ->get(route('alumni.show', $alumni))
            ->assertOk();

        $this->actingAs($receptionist, 'institute_user')
            ->get(route('alumni.create'))
            ->assertForbidden();

        $this->actingAs($receptionist, 'institute_user')
            ->put(route('alumni.update', $alumni), ['public_contact_preference' => 'private', 'profile_visibility' => 'private'])
            ->assertForbidden();
    }

    public function test_branch_manager_can_view_and_update_but_not_create_or_delete(): void
    {
        $c = $this->curriculum();
        $branch = $this->branch($c['institute'], 'Alumni Branch');
        $student = $this->student($c['institute'], 'Rahim', $branch);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $result = $this->finalResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2027');
        $this->decision($c, $placement, $student, $year, $c['class'], $result, PromotionDecisionItem::DECISION_GRADUATED);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.store'), ['student_id' => $student->id]);

        $alumni = Alumni::query()->firstOrFail();
        $manager = $this->user($c['institute'], 'branch-manager', 'al-manager', $branch);

        $this->actingAs($manager, 'institute_user')
            ->get(route('alumni.directory'))
            ->assertOk();

        $this->actingAs($manager, 'institute_user')
            ->get(route('alumni.create'))
            ->assertForbidden();

        $this->actingAs($manager, 'institute_user')
            ->delete(route('alumni.destroy', $alumni))
            ->assertForbidden();
    }

    // ------------------------------------------------------------- Isolation

    public function test_cross_tenant_alumni_is_blocked(): void
    {
        ['c' => $c, 'student' => $student] = $this->eligibleStudent();

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.store'), ['student_id' => $student->id]);

        $alumni = Alumni::query()->firstOrFail();

        $otherInstitute = $this->institute($this->country('IN', 'India'), 'Other Inst');
        $otherOwner = $this->user($otherInstitute, 'institute-owner', 'al-other');

        $this->actingAs($otherOwner, 'institute_user')
            ->get(route('alumni.show', $alumni))
            ->assertNotFound();

        $this->actingAs($otherOwner, 'institute_user')
            ->get(route('alumni.directory'))
            ->assertDontSee($student->first_name);
    }

    public function test_cross_branch_alumni_is_blocked(): void
    {
        $c = $this->curriculum();
        $branchA = $this->branch($c['institute'], 'Branch A');
        $studentA = $this->student($c['institute'], 'Rahim', $branchA);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $studentA, $year, $c['class'], $c['group']);
        $result = $this->finalResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2027');
        $this->decision($c, $placement, $studentA, $year, $c['class'], $result, PromotionDecisionItem::DECISION_GRADUATED);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.store'), ['student_id' => $studentA->id]);

        $alumni = Alumni::query()->firstOrFail();

        $branchB = $this->branch($c['institute'], 'Branch B');
        $adminB = $this->user($c['institute'], 'institute-admin', 'al-admin-b', $branchB);

        $this->actingAs($adminB, 'institute_user')
            ->get(route('alumni.show', $alumni))
            ->assertNotFound();
    }

    // ------------------------------------------------------------- Data integrity

    public function test_alumni_operations_preserve_academic_records(): void
    {
        ['c' => $c, 'student' => $student] = $this->eligibleStudent();

        $tables = [
            'students',
            'student_enrollments',
            'student_academic_placements',
            'student_subject_selections',
            'academic_final_results',
            'academic_final_result_students',
            'academic_final_result_rows',
            'academic_result_aggregation_schemes',
            'academic_final_result_policies',
            'promotion_policies',
            'promotion_decisions',
            'promotion_decision_items',
            'attendance',
            'certificates',
            'invoices',
            'payments',
        ];

        $snapshot = fn () => collect($tables)
            ->mapWithKeys(fn (string $table) => [$table => DB::table($table)->get()->map(fn ($row) => (array) $row)->all()]);

        $before = $snapshot();

        // Full lifecycle: activate, update, toggle, then delete.
        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.store'), ['student_id' => $student->id]);

        $alumni = Alumni::query()->firstOrFail();

        $this->actingAs($c['owner'], 'institute_user')
            ->put(route('alumni.update', $alumni), [
                'current_occupation' => 'Engineer',
                'employer' => 'Acme',
                'public_contact_preference' => 'private',
                'profile_visibility' => 'public',
            ]);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('alumni.status', $alumni), ['status' => Alumni::STATUS_INACTIVE]);

        $this->actingAs($c['owner'], 'institute_user')
            ->delete(route('alumni.destroy', $alumni));

        $after = $snapshot();

        foreach ($tables as $table) {
            $this->assertSame(
                $before[$table],
                $after[$table],
                "alumni operations mutated the existing {$table} table."
            );
        }
    }
}
