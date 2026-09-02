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
use App\Services\StudentAcademicHistoryService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StudentAcademicHistoryTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

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

    private function institute(Country $country, string $name = 'History Inst'): Institute
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
            'student_id_number' => 'SID'.mt_rand(100000, 999999),
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

    /**
     * Base school structure + a Class 6 and Class 9 grade with two subjects.
     */
    private function curriculum(): array
    {
        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $class6 = $this->classGrade($level, 'ah-c6', 'Class 6');
        $class9 = $this->classGrade($level, 'ah-c9', 'Class 9');
        $group = $this->group($class6);
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'ah-owner');

        $math = $this->subject('Mathematics', 'AH100001');
        $english = $this->subject('English', 'AH100002');

        $this->assign($math, $class6);
        $this->assign($english, $class6);

        return [
            'country' => $country,
            'class6' => $class6,
            'class9' => $class9,
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

    /**
     * Materializes a published (or in-flight) final result with a frozen
     * snapshot header + per-subject rows for the placement.
     */
    private function finalResult(
        array $c,
        StudentAcademicPlacement $placement,
        AcademicYear $year,
        ClassGrade $class,
        ?AcademicGroup $group,
        string $name,
        string $status = AcademicFinalResult::STATUS_PUBLISHED,
        float $gpa = 4.75,
        array $rows = []
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
        }

        foreach ($rows as $row) {
            AcademicFinalResultRow::create([
                'result_id' => $result->id,
                'placement_id' => $placement->id,
                'subject_id' => $row['subject']->id,
                'status' => 'computed',
                'aggregate' => $row['aggregate'],
                'grade' => $row['grade'],
                'grade_point' => $row['grade_point'],
                'subject_status' => $row['subject_status'],
                'gpa_included' => $row['gpa_included'],
                'credits' => $row['credits'] ?? null,
                'optional' => $row['optional'] ?? false,
            ]);
        }

        return $result;
    }

    private function approvedPromotion(
        array $c,
        StudentAcademicPlacement $placement,
        AcademicYear $year,
        ClassGrade $class,
        AcademicFinalResult $result,
        StudentAcademicPlacement $nextPlacement,
        ClassGrade $targetClass
    ): void {
        $policy = PromotionPolicy::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'name' => 'Promote '.$class->name,
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
            'status' => PromotionDecision::STATUS_APPROVED,
            'approved_by' => $c['owner']->id,
            'approved_at' => now(),
            'created_by' => $c['owner']->id,
        ]);

        PromotionDecisionItem::create([
            'decision_id' => $decision->id,
            'placement_id' => $placement->id,
            'student_id' => $placement->student_id,
            'decision' => PromotionDecisionItem::DECISION_PROMOTED,
            'reasons' => ['Promoted on merit'],
            'target_class_grade_id' => $targetClass->id,
            'target_academic_group_id' => null,
            'next_placement_id' => $nextPlacement->id,
            'approved_by' => $c['owner']->id,
            'approved_at' => now(),
        ]);
    }

    private function pendingPromotion(array $c, StudentAcademicPlacement $placement, AcademicYear $year, ClassGrade $class, AcademicFinalResult $result): void
    {
        $policy = PromotionPolicy::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'name' => 'Pending '.$class->name,
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
            'status' => PromotionDecision::STATUS_PENDING,
            'created_by' => $c['owner']->id,
        ]);

        PromotionDecisionItem::create([
            'decision_id' => $decision->id,
            'placement_id' => $placement->id,
            'student_id' => $placement->student_id,
            'decision' => PromotionDecisionItem::DECISION_PENDING,
            'reasons' => ['Awaiting review'],
        ]);
    }

    private function historyRoute(Student $student): string
    {
        return route('students.academic-history', $student);
    }

    // ------------------------------------------------------------- Access & security

    public function test_authorized_user_can_access_academic_history(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $student, $year, $c['class6'], $c['group']);
        $this->finalResult($c, $placement, $year, $c['class6'], $c['group'], 'Term Final 2026', rows: [
            ['subject' => $c['subjects']['math'], 'aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS', 'gpa_included' => true],
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->historyRoute($student))
            ->assertOk()
            ->assertSee('Academic History')
            ->assertSee('Term Final 2026')
            ->assertSee('4.75');
    }

    public function test_unauthorized_user_cannot_access_academic_history(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $auditorRole = Role::create([
            'name' => 'AH Auditor',
            'slug' => 'ah-auditor-'.uniqid(),
            'status' => 'active',
        ]);
        $auditor = $this->user($c['institute'], $auditorRole->slug, 'ah-auditor');

        TenantContext::set($c['institute']->id);

        $this->actingAs($auditor, 'institute_user')
            ->get($this->historyRoute($student))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $this->get($this->historyRoute($student))->assertRedirect();
    }

    public function test_cross_tenant_student_is_blocked(): void
    {
        $c = $this->curriculum();
        $otherInstitute = $this->institute($this->country('IN', 'India'), 'Other Inst');
        $otherStudent = $this->student($otherInstitute, 'Other');

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->historyRoute($otherStudent))
            ->assertStatus(404);
    }

    public function test_cross_branch_student_is_blocked(): void
    {
        $c = $this->curriculum();
        $branchA = $this->branch($c['institute'], 'Branch A');
        $studentA = $this->student($c['institute'], 'Rahim', $branchA);
        $branchB = $this->branch($c['institute'], 'Branch B');
        $adminB = $this->user($c['institute'], 'institute-admin', 'ah-admin-b', $branchB);

        TenantContext::set($c['institute']->id);

        $this->actingAs($adminB, 'institute_user')
            ->get($this->historyRoute($studentA))
            ->assertStatus(404);
    }

    // ------------------------------------------------------------- Timeline & result display

    public function test_multiple_academic_placements_are_listed_chronologically(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year2026 = $this->year($c['institute'], '2026', 'Session 2026');
        $year2027 = $this->year($c['institute'], '2027', 'Session 2027');
        $this->placement($c, $student, $year2026, $c['class6'], $c['group']);
        $this->placement($c, $student, $year2027, $c['class9']);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->historyRoute($student))
            ->assertOk()
            ->assertSee('Class 6')
            ->assertSee('Class 9');

        $timeline = app(StudentAcademicHistoryService::class)->forStudent($student)['timeline'];
        $this->assertCount(2, $timeline);
        $this->assertSame('Class 6', $timeline[0]['placement']->classGrade->name);
        $this->assertSame('Class 9', $timeline[1]['placement']->classGrade->name);
        $this->assertTrue($timeline[1]['isCurrent']);
    }

    public function test_published_final_result_snapshot_is_displayed(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $student, $year, $c['class6'], $c['group']);
        $this->finalResult($c, $placement, $year, $c['class6'], $c['group'], 'Term Final 2026');

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->historyRoute($student))
            ->assertOk()
            ->assertSee('Term Final 2026')
            ->assertSee('Published')
            ->assertSee('4.75');
    }

    public function test_subject_level_snapshot_rows_are_displayed(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $student, $year, $c['class6'], $c['group']);
        $this->finalResult($c, $placement, $year, $c['class6'], $c['group'], 'Term Final 2026', rows: [
            ['subject' => $c['subjects']['math'], 'aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS', 'gpa_included' => true],
            ['subject' => $c['subjects']['english'], 'aggregate' => 85.0, 'grade' => 'A', 'grade_point' => 4.0, 'subject_status' => 'PASS', 'gpa_included' => true],
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->historyRoute($student))
            ->assertOk()
            ->assertSee('Mathematics')
            ->assertSee('A+')
            ->assertSee('90.5%')
            ->assertSee('5.00')
            ->assertSee('English')
            ->assertSee('A')
            ->assertSee('Pass');
    }

    public function test_historical_gpa_comes_from_the_latest_published_snapshot(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $student, $year, $c['class6'], $c['group']);

        // Older publish cycle: GPA 3.00, aggregate 70.25.
        $this->finalResult($c, $placement, $year, $c['class6'], $c['group'], 'Term 1 2026', gpa: 3.0, rows: [
            ['subject' => $c['subjects']['math'], 'aggregate' => 70.25, 'grade' => 'B', 'grade_point' => 3.0, 'subject_status' => 'PASS', 'gpa_included' => true],
        ]);

        // Newer publish cycle: GPA 4.75, aggregate 90.5.
        $this->finalResult($c, $placement, $year, $c['class6'], $c['group'], 'Term Final 2026', gpa: 4.75, rows: [
            ['subject' => $c['subjects']['math'], 'aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS', 'gpa_included' => true],
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->historyRoute($student))
            ->assertOk()
            ->assertSee('Term Final 2026')
            ->assertSee('4.75')
            ->assertSee('90.5%')
            ->assertDontSee('3.00')
            ->assertDontSee('70.25');
    }

    // ------------------------------------------------------------- Unpublished handling

    public function test_unpublished_result_is_not_treated_as_official(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $student, $year, $c['class6'], $c['group']);
        $this->finalResult($c, $placement, $year, $c['class6'], $c['group'], 'Term Final 2026 (draft)',
            status: AcademicFinalResult::STATUS_LOCKED, gpa: 4.75);

        TenantContext::set($c['institute']->id);

        $this->assertDatabaseMissing('academic_final_result_students', ['placement_id' => $placement->id]);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->historyRoute($student))
            ->assertOk()
            ->assertSee('is being prepared and is not published yet')
            ->assertDontSee('Term Final 2026 (draft)')
            ->assertDontSee('4.75');
    }

    // ------------------------------------------------------------- Promotion history

    public function test_approved_promotion_history_appears(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year2026 = $this->year($c['institute'], '2026', 'Session 2026');
        $year2027 = $this->year($c['institute'], '2027', 'Session 2027');
        $placement26 = $this->placement($c, $student, $year2026, $c['class6'], $c['group']);
        $placement27 = $this->placement($c, $student, $year2027, $c['class9']);
        $result = $this->finalResult($c, $placement26, $year2026, $c['class6'], $c['group'], 'Term Final 2026');
        $this->approvedPromotion($c, $placement26, $year2026, $c['class6'], $result, $placement27, $c['class9']);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->historyRoute($student))
            ->assertOk()
            ->assertSee('Promoted')
            ->assertSee('→ Session 2027');
    }

    public function test_pending_promotion_is_not_shown_as_history(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year2026 = $this->year($c['institute'], '2026', 'Session 2026');
        $year2027 = $this->year($c['institute'], '2027', 'Session 2027');
        $placement26 = $this->placement($c, $student, $year2026, $c['class6'], $c['group']);
        $placement27 = $this->placement($c, $student, $year2027, $c['class9']);
        $this->finalResult($c, $placement26, $year2026, $c['class6'], $c['group'], 'Term Final 2026');
        $this->pendingPromotion($c, $placement27, $year2027, $c['class9'], AcademicFinalResult::query()->where('institute_id', $c['institute']->id)->firstOrFail());

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->historyRoute($student))
            ->assertOk()
            ->assertSee('No approved promotion decision recorded')
            ->assertDontSee('Promoted');
    }

    // ------------------------------------------------------------- Filtering & read-only safety

    public function test_academic_year_filter_limits_the_timeline(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year2026 = $this->year($c['institute'], '2026', 'Session 2026');
        $year2027 = $this->year($c['institute'], '2027', 'Session 2027');
        $this->placement($c, $student, $year2026, $c['class6'], $c['group']);
        $this->placement($c, $student, $year2027, $c['class9']);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->historyRoute($student).'?academic_year_id='.$year2026->id)
            ->assertOk()
            ->assertSee('Class 6')
            ->assertDontSee('Class 9');

        $timeline = app(StudentAcademicHistoryService::class)->forStudent($student, $year2026->id)['timeline'];
        $this->assertCount(1, $timeline);
        $this->assertSame($year2026->id, $timeline[0]['placement']->academic_year_id);
    }

    public function test_viewing_academic_history_does_not_mutate_records(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year2026 = $this->year($c['institute'], '2026', 'Session 2026');
        $year2027 = $this->year($c['institute'], '2027', 'Session 2027');
        $placement26 = $this->placement($c, $student, $year2026, $c['class6'], $c['group']);
        $placement27 = $this->placement($c, $student, $year2027, $c['class9']);
        $result = $this->finalResult($c, $placement26, $year2026, $c['class6'], $c['group'], 'Term Final 2026', rows: [
            ['subject' => $c['subjects']['math'], 'aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS', 'gpa_included' => true],
        ]);
        $this->approvedPromotion($c, $placement26, $year2026, $c['class6'], $result, $placement27, $c['class9']);

        $tables = [
            'student_academic_placements',
            'student_subject_selections',
            'academic_final_results',
            'academic_final_result_students',
            'academic_final_result_rows',
            'promotion_decisions',
            'promotion_decision_items',
        ];

        $before = [];
        foreach ($tables as $table) {
            $before[$table] = collect(DB::table($table)->get()->map(fn ($row) => (array) $row));
        }

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->historyRoute($student))
            ->assertOk();

        foreach ($tables as $table) {
            $after = collect(DB::table($table)->get()->map(fn ($row) => (array) $row));
            $this->assertEquals($before[$table]->all(), $after->all(), "{$table} was mutated by viewing academic history.");
        }
    }
}
