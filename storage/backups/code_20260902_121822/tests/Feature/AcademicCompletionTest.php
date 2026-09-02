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
use App\Services\StudentAcademicLifecycleService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicCompletionTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    // ------------------------------------------------------------- Fixtures
    // Direct-creation fixtures shared in spirit with the academic history test;
    // every read target is a frozen snapshot / an approved promotion decision.

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

    private function institute(Country $country, string $name = 'Completion Inst'): Institute
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
            'student_id_number' => 'AC'.mt_rand(100000, 999999),
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
        $class = $this->classGrade($level, 'ac-c10', 'Class 10');
        $group = $this->group($class);
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'ac-owner');

        $math = $this->subject('Mathematics', 'AC100001');
        $english = $this->subject('English', 'AC100002');
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

    /**
     * Promotion decision (pending / review / approved) with one item verdict.
     * completed / graduated are existing outcomes on the item.
     */
    private function decision(
        array $c,
        StudentAcademicPlacement $placement,
        Student $student,
        AcademicYear $year,
        ClassGrade $class,
        AcademicFinalResult $result,
        string $outcome,
        string $status = PromotionDecision::STATUS_APPROVED,
        ?StudentAcademicPlacement $nextPlacement = null
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
            'next_placement_id' => $nextPlacement?->id,
            'approved_by' => $status === PromotionDecision::STATUS_APPROVED ? $c['owner']->id : null,
            'approved_at' => $status === PromotionDecision::STATUS_APPROVED ? now() : null,
        ]);
    }

    private function historyRoute(Student $student): string
    {
        return route('students.academic-history', $student);
    }

    private function lifecycle(Student $student): array
    {
        return app(StudentAcademicLifecycleService::class)->forStudent($student);
    }

    // ------------------------------------------------------------- Outcome recognition

    public function test_completed_outcome_is_recognized(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $result = $this->finalResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2027');
        $this->decision($c, $placement, $student, $year, $c['class'], $result, PromotionDecisionItem::DECISION_COMPLETED);

        TenantContext::set($c['institute']->id);

        $lifecycle = $this->lifecycle($student);
        $this->assertSame('completed', $lifecycle['outcome']);
        $this->assertTrue($lifecycle['isCompletion']);
        $this->assertFalse($lifecycle['isGraduation']);
        $this->assertTrue($lifecycle['isTerminal']);
        $this->assertNotNull($lifecycle['item']);
    }

    public function test_graduated_outcome_is_recognized(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $result = $this->finalResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2027');
        $this->decision($c, $placement, $student, $year, $c['class'], $result, PromotionDecisionItem::DECISION_GRADUATED);

        TenantContext::set($c['institute']->id);

        $lifecycle = $this->lifecycle($student);
        $this->assertSame('graduated', $lifecycle['outcome']);
        $this->assertTrue($lifecycle['isGraduation']);
        $this->assertTrue($lifecycle['isTerminal']);
        $this->assertNull($lifecycle['progressingTo']);
    }

    // ------------------------------------------------------------- Official vs in-flight

    public function test_pending_decision_is_not_official(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $result = $this->finalResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2027');
        $this->decision($c, $placement, $student, $year, $c['class'], $result, PromotionDecisionItem::DECISION_GRADUATED,
            PromotionDecision::STATUS_PENDING);

        TenantContext::set($c['institute']->id);

        $lifecycle = $this->lifecycle($student);
        $this->assertSame('active', $lifecycle['outcome']);
        $this->assertFalse($lifecycle['isGraduation']);
        $this->assertNull($lifecycle['item']);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->historyRoute($student))
            ->assertOk()
            ->assertDontSee('Officially graduated');
    }

    public function test_review_decision_is_not_official(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $result = $this->finalResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2027');
        $this->decision($c, $placement, $student, $year, $c['class'], $result, PromotionDecisionItem::DECISION_COMPLETED,
            PromotionDecision::STATUS_REVIEW);

        TenantContext::set($c['institute']->id);

        $lifecycle = $this->lifecycle($student);
        $this->assertSame('active', $lifecycle['outcome']);
        $this->assertFalse($lifecycle['isCompletion']);
        $this->assertNull($lifecycle['item']);
    }

    public function test_decision_on_unpublished_result_cannot_establish_completion(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        // A LOCKED (not published) result cannot source an official decision.
        $lockedResult = $this->finalResult($c, $placement, $year, $c['class'], $c['group'], 'Locked Result',
            AcademicFinalResult::STATUS_LOCKED);
        $this->decision($c, $placement, $student, $year, $c['class'], $lockedResult, PromotionDecisionItem::DECISION_GRADUATED);

        TenantContext::set($c['institute']->id);

        $lifecycle = $this->lifecycle($student);
        $this->assertSame('active', $lifecycle['outcome']);
        $this->assertFalse($lifecycle['isGraduation']);
        $this->assertNull($lifecycle['item']);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->historyRoute($student))
            ->assertOk()
            ->assertDontSee('Officially graduated')
            ->assertSee('Academic journey in progress');
    }

    // ------------------------------------------------------------- History integration

    public function test_academic_history_shows_completion_correctly(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $result = $this->finalResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2027');
        $this->decision($c, $placement, $student, $year, $c['class'], $result, PromotionDecisionItem::DECISION_COMPLETED);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->historyRoute($student))
            ->assertOk()
            ->assertSee('Academic Lifecycle')
            ->assertSee('Completed')
            ->assertSee('Officially completed the academic program');
    }

    public function test_academic_history_shows_graduation_correctly(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $result = $this->finalResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2027');
        $this->decision($c, $placement, $student, $year, $c['class'], $result, PromotionDecisionItem::DECISION_GRADUATED);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->historyRoute($student))
            ->assertOk()
            ->assertSee('Graduated')
            ->assertSee('Officially graduated');
    }

    public function test_graduated_student_is_not_shown_as_progressing(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $result = $this->finalResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2027');
        $this->decision($c, $placement, $student, $year, $c['class'], $result, PromotionDecisionItem::DECISION_GRADUATED);

        TenantContext::set($c['institute']->id);

        $this->assertNull($this->lifecycle($student)['progressingTo']);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->historyRoute($student))
            ->assertOk()
            ->assertSee('Officially graduated')
            ->assertDontSee('Progressing to');
    }

    // ------------------------------------------------------------- Security

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
        $adminB = $this->user($c['institute'], 'institute-admin', 'ac-admin-b', $branchB);

        TenantContext::set($c['institute']->id);

        $this->actingAs($adminB, 'institute_user')
            ->get($this->historyRoute($studentA))
            ->assertStatus(404);
    }

    public function test_unauthorized_role_is_blocked(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $auditorRole = Role::create([
            'name' => 'AC Auditor',
            'slug' => 'ac-auditor-'.uniqid(),
            'status' => 'active',
        ]);
        $auditor = $this->user($c['institute'], $auditorRole->slug, 'ac-auditor');

        TenantContext::set($c['institute']->id);

        $this->actingAs($auditor, 'institute_user')
            ->get($this->historyRoute($student))
            ->assertForbidden();
    }

    // ------------------------------------------------------------- Integrity & read-only

    public function test_no_duplicate_completion_record_is_created(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $result = $this->finalResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2027');
        $this->decision($c, $placement, $student, $year, $c['class'], $result, PromotionDecisionItem::DECISION_GRADUATED);

        $this->assertSame(1, DB::table('promotion_decision_items')->where('student_id', $student->id)->count());

        TenantContext::set($c['institute']->id);

        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($c['owner'], 'institute_user')
                ->get($this->historyRoute($student))
                ->assertOk();
            $this->lifecycle($student);
        }

        $this->assertSame(1, DB::table('promotion_decision_items')->where('student_id', $student->id)->count());
        $this->assertSame(1, DB::table('promotion_decisions')->where('institute_id', $c['institute']->id)->count());
    }

    public function test_viewing_lifecycle_does_not_mutate_records(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $result = $this->finalResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2027');
        $this->decision($c, $placement, $student, $year, $c['class'], $result, PromotionDecisionItem::DECISION_COMPLETED);

        $tables = [
            'promotion_policies',
            'promotion_decisions',
            'promotion_decision_items',
            'academic_final_results',
            'academic_final_result_students',
            'academic_final_result_rows',
            'student_academic_placements',
            'students',
        ];

        $before = [];
        foreach ($tables as $table) {
            $before[$table] = collect(DB::table($table)->get()->map(fn ($row) => (array) $row));
        }

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->historyRoute($student))
            ->assertOk();
        $this->lifecycle($student);

        foreach ($tables as $table) {
            $after = collect(DB::table($table)->get()->map(fn ($row) => (array) $row));
            $this->assertEquals($before[$table]->all(), $after->all(), "{$table} was mutated by viewing the academic lifecycle.");
        }
    }
}
