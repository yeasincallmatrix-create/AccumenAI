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
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicReportCardTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    // ------------------------------------------------------------- Fixtures
    // Same light-weight direct-creation fixtures used by
    // StudentAcademicHistoryTest — snapshots are written directly, avoiding the
    // full marks pipeline, and every read target is a frozen snapshot row.

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

    private function institute(Country $country, string $name = 'Report Inst'): Institute
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
            'student_id_number' => 'RC'.mt_rand(100000, 999999),
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
        $class = $this->classGrade($level, 'rc-c8', 'Class 8');
        $group = $this->group($class);
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'rc-owner');

        $math = $this->subject('Mathematics', 'RC100001');
        $english = $this->subject('English', 'RC100002');
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

    /**
     * Published final result with a frozen snapshot for the placement.
     */
    private function publishedResult(
        array $c,
        StudentAcademicPlacement $placement,
        AcademicYear $year,
        ClassGrade $class,
        ?AcademicGroup $group,
        string $name,
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
            'status' => AcademicFinalResult::STATUS_PUBLISHED,
            'locked_at' => now(),
            'published_at' => now(),
        ]);

        AcademicFinalResultStudent::create([
            'result_id' => $result->id,
            'placement_id' => $placement->id,
            'gpa' => $gpa,
            'gpa_status' => AcademicFinalResultStudent::GPA_COMPUTED,
            'passed_count' => 2,
            'failed_count' => 0,
        ]);

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

    private function reportRoute(AcademicFinalResult $result, StudentAcademicPlacement $placement): string
    {
        return route('settings.academic.final-results.report', [$result, $placement]);
    }

    // ------------------------------------------------------------- Access

    public function test_authorized_user_can_view_official_report_card(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute'], 'Rahim');
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $result = $this->publishedResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2026', rows: [
            ['subject' => $c['subjects']['math'], 'aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS', 'gpa_included' => true],
            ['subject' => $c['subjects']['english'], 'aggregate' => 85.0, 'grade' => 'A', 'grade_point' => 4.0, 'subject_status' => 'PASS', 'gpa_included' => true],
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->reportRoute($result, $placement))
            ->assertOk()
            ->assertSee('Report Inst')
            ->assertSee('Official Report Card')
            ->assertSee('Rahim Student')
            ->assertSee('Term Final 2026')
            ->assertSee('Mathematics')
            ->assertSee('A+')
            ->assertSee('90.5%')
            ->assertSee('English')
            ->assertSee('A')
            ->assertSee('4.75')
            ->assertSee('Print Report Card');
    }

    public function test_unauthorized_role_is_blocked(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $result = $this->publishedResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2026');

        $auditorRole = Role::create([
            'name' => 'RC Auditor',
            'slug' => 'rc-auditor-'.uniqid(),
            'status' => 'active',
        ]);
        $auditor = $this->user($c['institute'], $auditorRole->slug, 'rc-auditor');

        TenantContext::set($c['institute']->id);

        $this->actingAs($auditor, 'institute_user')
            ->get($this->reportRoute($result, $placement))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $result = $this->publishedResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2026');

        $this->get($this->reportRoute($result, $placement))->assertRedirect();
    }

    // ------------------------------------------------------------- Isolation

    public function test_cross_tenant_result_is_blocked(): void
    {
        $c = $this->curriculum();
        $other = $this->institute($this->country('IN', 'India'), 'Other Inst');
        $otherOwner = $this->user($other, 'institute-owner', 'rc-other-owner');
        $student = $this->student($other);
        $year = $this->year($other, '2026', 'Session 2026');
        $placement = $this->placement([
            ...$c,
            'institute' => $other,
        ], $student, $year, $c['class'], $c['group']);
        $result = $this->publishedResult([
            ...$c,
            'institute' => $other,
        ], $placement, $year, $c['class'], $c['group'], 'Other Result');

        TenantContext::set($c['institute']->id);

        // Result belongs to another institute → tenant-scoped binding 404s,
        // regardless of who asks.
        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->reportRoute($result, $placement))
            ->assertStatus(404);

        $this->actingAs($otherOwner, 'institute_user')
            ->get($this->reportRoute($result, $placement))
            ->assertOk();
    }

    public function test_cross_branch_student_is_blocked(): void
    {
        $c = $this->curriculum();
        $branchA = $this->branch($c['institute'], 'Branch A');
        $studentA = $this->student($c['institute'], 'Rahim', $branchA);
        $branchB = $this->branch($c['institute'], 'Branch B');
        $adminB = $this->user($c['institute'], 'institute-admin', 'rc-admin-b', $branchB);

        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $studentA, $year, $c['class'], $c['group']);
        $result = $this->publishedResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2026');

        TenantContext::set($c['institute']->id);

        $this->actingAs($adminB, 'institute_user')
            ->get($this->reportRoute($result, $placement))
            ->assertStatus(404);
    }

    // ------------------------------------------------------------- Snapshot rules

    public function test_report_card_requires_a_published_result(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);

        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $year->id,
            'class_grade_id' => $c['class']->id,
            'academic_group_id' => $c['group']->id,
            'name' => 'Locked scheme',
            'status' => 'active',
            'display_order' => 1,
        ]);
        $policy = AcademicFinalResultPolicy::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'scheme_id' => $scheme->id,
            'name' => 'Locked Policy',
        ]);
        $locked = AcademicFinalResult::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'policy_id' => $policy->id,
            'scheme_id' => $scheme->id,
            'name' => 'Locked only',
            'status' => AcademicFinalResult::STATUS_LOCKED,
            'locked_at' => now(),
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->reportRoute($locked, $placement))
            ->assertStatus(404);
    }

    public function test_placement_must_belong_to_the_snapshot(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute'], 'Rahim');
        $otherStudent = $this->student($c['institute'], 'Karim');
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $otherPlacement = $this->placement($c, $otherStudent, $year, $c['class'], $c['group']);
        $result = $this->publishedResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2026', rows: [
            ['subject' => $c['subjects']['math'], 'aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS', 'gpa_included' => true],
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->reportRoute($result, $otherPlacement))
            ->assertStatus(404);
    }

    public function test_report_card_marks_optional_subjects_as_not_counted_in_gpa(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $result = $this->publishedResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2026', rows: [
            ['subject' => $c['subjects']['math'], 'aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS', 'gpa_included' => true],
            ['subject' => $c['subjects']['english'], 'aggregate' => 88.0, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS', 'gpa_included' => false, 'optional' => true],
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->reportRoute($result, $placement))
            ->assertOk()
            ->assertSee('Optional')
            ->assertSee('Not counted in GPA');
    }

    // ------------------------------------------------------------- Promotion

    public function test_approved_promotion_verdict_appears_on_report_card(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year2026 = $this->year($c['institute'], '2026', 'Session 2026');
        $year2027 = $this->year($c['institute'], '2027', 'Session 2027');
        $placement26 = $this->placement($c, $student, $year2026, $c['class'], $c['group']);
        $placement27 = $this->placement($c, $student, $year2027, $c['class']);
        $result = $this->publishedResult($c, $placement26, $year2026, $c['class'], $c['group'], 'Term Final 2026');

        $policy = PromotionPolicy::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'name' => 'Promote Class 8',
            'academic_year_id' => $year2026->id,
            'class_grade_id' => $c['class']->id,
            'academic_group_id' => null,
            'status' => 'active',
            'created_by' => $c['owner']->id,
        ]);

        $decision = PromotionDecision::create([
            'policy_id' => $policy->id,
            'result_id' => $result->id,
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $year2026->id,
            'status' => PromotionDecision::STATUS_APPROVED,
            'approved_by' => $c['owner']->id,
            'approved_at' => now(),
            'created_by' => $c['owner']->id,
        ]);

        PromotionDecisionItem::create([
            'decision_id' => $decision->id,
            'placement_id' => $placement26->id,
            'student_id' => $student->id,
            'decision' => PromotionDecisionItem::DECISION_PROMOTED,
            'reasons' => ['Promoted on merit'],
            'target_class_grade_id' => $c['class']->id,
            'target_academic_group_id' => null,
            'next_placement_id' => $placement27->id,
            'approved_by' => $c['owner']->id,
            'approved_at' => now(),
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->reportRoute($result, $placement26))
            ->assertOk()
            ->assertSee('Promoted')
            ->assertSee('Session 2027');
    }

    public function test_pending_promotion_is_not_displayed(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year2026 = $this->year($c['institute'], '2026', 'Session 2026');
        $year2027 = $this->year($c['institute'], '2027', 'Session 2027');
        $placement26 = $this->placement($c, $student, $year2026, $c['class'], $c['group']);
        $placement27 = $this->placement($c, $student, $year2027, $c['class']);
        $result = $this->publishedResult($c, $placement26, $year2026, $c['class'], $c['group'], 'Term Final 2026');

        $policy = PromotionPolicy::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'name' => 'Pending Class 8',
            'academic_year_id' => $year2026->id,
            'class_grade_id' => $c['class']->id,
            'academic_group_id' => null,
            'status' => 'active',
            'created_by' => $c['owner']->id,
        ]);

        $decision = PromotionDecision::create([
            'policy_id' => $policy->id,
            'result_id' => $result->id,
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $year2026->id,
            'status' => PromotionDecision::STATUS_PENDING,
            'created_by' => $c['owner']->id,
        ]);

        PromotionDecisionItem::create([
            'decision_id' => $decision->id,
            'placement_id' => $placement26->id,
            'student_id' => $student->id,
            'decision' => PromotionDecisionItem::DECISION_PENDING,
            'reasons' => ['Awaiting review'],
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->reportRoute($result, $placement26))
            ->assertOk()
            ->assertDontSee('Promoted');
    }

    // ------------------------------------------------------------- Read-only safety

    public function test_viewing_a_report_card_does_not_mutate_records(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $result = $this->publishedResult($c, $placement, $year, $c['class'], $c['group'], 'Term Final 2026', rows: [
            ['subject' => $c['subjects']['math'], 'aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS', 'gpa_included' => true],
        ]);

        $tables = [
            'academic_final_results',
            'academic_final_result_students',
            'academic_final_result_rows',
            'student_academic_placements',
        ];

        $before = [];
        foreach ($tables as $table) {
            $before[$table] = collect(DB::table($table)->get()->map(fn ($row) => (array) $row));
        }

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->reportRoute($result, $placement))
            ->assertOk();

        foreach ($tables as $table) {
            $after = collect(DB::table($table)->get()->map(fn ($row) => (array) $row));
            $this->assertEquals($before[$table]->all(), $after->all(), "{$table} was mutated by viewing a report card.");
        }
    }
}
