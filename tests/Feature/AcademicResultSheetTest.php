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

class AcademicResultSheetTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    // ------------------------------------------------------------- Fixtures
    // Direct-creation fixtures identical in spirit to AcademicReportCardTest:
    // frozen snapshot rows are written straight in, so every read target is a
    // published snapshot row — no live marks pipeline involved.

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

    private function institute(Country $country, string $name = 'Sheet Inst'): Institute
    {
        return Institute::create([
            'name' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'address' => 'Sheet Address Road, Dhaka',
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
            'student_id_number' => 'RS'.mt_rand(100000, 999999),
            'registration_number' => 'RREG'.mt_rand(100000, 999999),
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
        $class = $this->classGrade($level, 'rs-c8', 'Class 8');
        $group = $this->group($class);
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'rs-owner');

        $math = $this->subject('Mathematics', 'RS100001');
        $english = $this->subject('English', 'RS100002');
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
     * A single policy's final result (scheme + policy + result header), with
     * optional branch scoping; status defaults to published.
     */
    private function createResult(
        array $c,
        AcademicYear $year,
        ClassGrade $class,
        ?AcademicGroup $group,
        string $name,
        ?Branch $branch = null,
        string $status = AcademicFinalResult::STATUS_PUBLISHED
    ): AcademicFinalResult {
        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => $branch?->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $class->id,
            'academic_group_id' => $group?->id,
            'name' => 'Scheme '.$name,
            'status' => 'active',
            'display_order' => 1,
        ]);

        $policy = AcademicFinalResultPolicy::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => $branch?->id,
            'scheme_id' => $scheme->id,
            'name' => $name.' Policy',
        ]);

        return AcademicFinalResult::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => $branch?->id,
            'policy_id' => $policy->id,
            'scheme_id' => $scheme->id,
            'name' => $name,
            'status' => $status,
            'locked_at' => in_array($status, [AcademicFinalResult::STATUS_LOCKED, AcademicFinalResult::STATUS_PUBLISHED], true) ? now() : null,
            'published_at' => $status === AcademicFinalResult::STATUS_PUBLISHED ? now() : null,
        ]);
    }

    /**
     * Freezes one placement into the result's snapshot: GPA header + rows.
     */
    private function addSnapshot(
        AcademicFinalResult $result,
        StudentAcademicPlacement $placement,
        float $gpa = 4.75,
        array $rows = [],
        int $passed = 2,
        int $failed = 0
    ): void {
        AcademicFinalResultStudent::create([
            'result_id' => $result->id,
            'placement_id' => $placement->id,
            'gpa' => $gpa,
            'gpa_status' => AcademicFinalResultStudent::GPA_COMPUTED,
            'passed_count' => $passed,
            'failed_count' => $failed,
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
    }

    private function standardRows(array $c): array
    {
        return [
            ['subject' => $c['subjects']['math'], 'aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS', 'gpa_included' => true],
            ['subject' => $c['subjects']['english'], 'aggregate' => 85.0, 'grade' => 'A', 'grade_point' => 4.0, 'subject_status' => 'PASS', 'gpa_included' => true],
        ];
    }

    private function approvedPromotion(array $c, StudentAcademicPlacement $placement, AcademicYear $year, ClassGrade $class, AcademicFinalResult $result, StudentAcademicPlacement $nextPlacement, ClassGrade $targetClass): void
    {
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

    private function sheetRoute(AcademicFinalResult $result): string
    {
        return route('settings.academic.final-results.result-sheet', $result);
    }

    // ------------------------------------------------------------- Access & security

    public function test_authorized_user_can_access_a_published_result_sheet(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $p1 = $this->placement($c, $this->student($c['institute'], 'Rahim'), $year, $c['class'], $c['group']);
        $p2 = $this->placement($c, $this->student($c['institute'], 'Karim'), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($result, $p1, 4.75, $this->standardRows($c));
        $this->addSnapshot($result, $p2, 3.5, $this->standardRows($c));

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->sheetRoute($result))
            ->assertOk()
            ->assertSee('Sheet Inst')
            ->assertSee('Sheet Address Road, Dhaka')
            ->assertSee('Class / Group Result Sheet')
            ->assertSee('Term Final 2026')
            ->assertSee('Rahim Student')
            ->assertSee('Karim Student')
            ->assertSee('Mathematics')
            ->assertSee('English')
            ->assertSee('4.75')
            ->assertSee('3.50')
            ->assertSee('Print Result Sheet');
    }

    public function test_unauthorized_role_is_blocked(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $this->student($c['institute']), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($result, $placement, 4.75, $this->standardRows($c));

        $auditorRole = Role::create([
            'name' => 'RS Auditor',
            'slug' => 'rs-auditor-'.uniqid(),
            'status' => 'active',
        ]);
        $auditor = $this->user($c['institute'], $auditorRole->slug, 'rs-auditor');

        TenantContext::set($c['institute']->id);

        $this->actingAs($auditor, 'institute_user')
            ->get($this->sheetRoute($result))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $this->student($c['institute']), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($result, $placement, 4.75, $this->standardRows($c));

        $this->get($this->sheetRoute($result))->assertRedirect();
    }

    public function test_cross_tenant_result_is_blocked(): void
    {
        $c = $this->curriculum();
        $other = $this->institute($this->country('IN', 'India'), 'Other Inst');
        $otherOwner = $this->user($other, 'institute-owner', 'rs-other-owner');
        $o = [...$c, 'institute' => $other];

        $year = $this->year($other, '2026', 'Session 2026');
        $placement = $this->placement($o, $this->student($other), $year, $c['class'], $c['group']);
        $result = $this->createResult($o, $year, $c['class'], $c['group'], 'Other Result');
        $this->addSnapshot($result, $placement, 4.75, $this->standardRows($c));

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->sheetRoute($result))
            ->assertStatus(404);

        TenantContext::set($other->id);

        $this->actingAs($otherOwner, 'institute_user')
            ->get($this->sheetRoute($result))
            ->assertOk();
    }

    public function test_cross_branch_result_is_blocked(): void
    {
        $c = $this->curriculum();
        $branchA = $this->branch($c['institute'], 'Branch A');
        $branchB = $this->branch($c['institute'], 'Branch B');
        $adminB = $this->user($c['institute'], 'institute-admin', 'rs-admin-b', $branchB);

        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $this->student($c['institute'], 'Rahim', $branchA), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026', $branchA);
        $this->addSnapshot($result, $placement, 4.75, $this->standardRows($c));

        TenantContext::set($c['institute']->id);

        $this->actingAs($adminB, 'institute_user')
            ->get($this->sheetRoute($result))
            ->assertStatus(404);
    }

    public function test_unpublished_result_is_blocked(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $this->student($c['institute']), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Locked only',
            status: AcademicFinalResult::STATUS_LOCKED);
        $this->addSnapshot($result, $placement, 4.75, $this->standardRows($c));

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->sheetRoute($result))
            ->assertStatus(404);
    }

    // ------------------------------------------------------------- Snapshot membership

    public function test_only_students_belonging_to_the_selected_result_snapshot_appear(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $pA = $this->placement($c, $this->student($c['institute'], 'Rahim'), $year, $c['class'], $c['group']);
        $pB = $this->placement($c, $this->student($c['institute'], 'Karim'), $year, $c['class'], $c['group']);

        $resultA = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($resultA, $pA, 4.75, $this->standardRows($c));

        $otherResult = $this->createResult($c, $year, $c['class'], $c['group'], 'Second Cycle');
        $this->addSnapshot($otherResult, $pB, 3.5, $this->standardRows($c));

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->sheetRoute($resultA))
            ->assertOk()
            ->assertSee('Rahim Student')
            ->assertDontSee('Karim Student');
    }

    public function test_multiple_students_render_correctly(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');

        foreach (['Rahim', 'Karim', 'Jamal'] as $name) {
            $placement = $this->placement($c, $this->student($c['institute'], $name), $year, $c['class'], $c['group']);
            $this->addSnapshot($result, $placement, 4.5, $this->standardRows($c));
        }

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->sheetRoute($result))
            ->assertOk()
            ->assertSee('Rahim Student')
            ->assertSee('Karim Student')
            ->assertSee('Jamal Student');
    }

    public function test_subject_columns_come_from_the_selected_result_snapshot(): void
    {
        $c = $this->curriculum();
        $physics = $this->subject('Physics', 'RS100003');

        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $pA = $this->placement($c, $this->student($c['institute'], 'Rahim'), $year, $c['class'], $c['group']);
        $pB = $this->placement($c, $this->student($c['institute'], 'Karim'), $year, $c['class'], $c['group']);

        $resultA = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($resultA, $pA, 4.75, $this->standardRows($c));

        $resultB = $this->createResult($c, $year, $c['class'], $c['group'], 'Science Cycle');
        $this->addSnapshot($resultB, $pB, 4.0, [
            ['subject' => $physics, 'aggregate' => 80.0, 'grade' => 'A', 'grade_point' => 4.0, 'subject_status' => 'PASS', 'gpa_included' => true],
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->sheetRoute($resultA))
            ->assertOk()
            ->assertSee('Mathematics')
            ->assertSee('English')
            ->assertDontSee('Physics');
    }

    public function test_gpa_and_grades_come_from_the_frozen_snapshot_of_the_selected_result(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $this->student($c['institute'], 'Rahim'), $year, $c['class'], $c['group']);

        $resultA = $this->createResult($c, $year, $c['class'], $c['group'], 'Term 1 2026');
        $this->addSnapshot($resultA, $placement, 3.0, [
            ['subject' => $c['subjects']['math'], 'aggregate' => 70.25, 'grade' => 'B', 'grade_point' => 3.0, 'subject_status' => 'PASS', 'gpa_included' => true],
        ]);

        $resultB = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($resultB, $placement, 4.75, [
            ['subject' => $c['subjects']['math'], 'aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS', 'gpa_included' => true],
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->sheetRoute($resultA))
            ->assertOk()
            ->assertSee('3.00')
            ->assertSee('70.25%')
            ->assertDontSee('4.75')
            ->assertDontSee('90.5%');

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->sheetRoute($resultB))
            ->assertOk()
            ->assertSee('4.75')
            ->assertSee('90.5%')
            ->assertDontSee('3.00')
            ->assertDontSee('70.25%');
    }

    // ------------------------------------------------------------- Optional / GPA semantics

    public function test_optional_and_not_in_gpa_markers_are_preserved(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $this->student($c['institute']), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($result, $placement, 5.0, [
            ['subject' => $c['subjects']['math'], 'aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS', 'gpa_included' => true],
            ['subject' => $c['subjects']['english'], 'aggregate' => 88.0, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS', 'gpa_included' => false, 'optional' => true],
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->sheetRoute($result))
            ->assertOk()
            ->assertSee('(O)')
            ->assertSee('* = Not counted in GPA')
            ->assertSee('Pass');
    }

    // ------------------------------------------------------------- Promotion

    public function test_approved_promotion_is_shown(): void
    {
        $c = $this->curriculum();
        $year2026 = $this->year($c['institute'], '2026', 'Session 2026');
        $year2027 = $this->year($c['institute'], '2027', 'Session 2027');
        $p26 = $this->placement($c, $this->student($c['institute'], 'Rahim'), $year2026, $c['class'], $c['group']);
        $p27 = $this->placement($c, $this->student($c['institute'], 'Rahim'), $year2027, $c['class']);
        $result = $this->createResult($c, $year2026, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($result, $p26, 4.75, $this->standardRows($c));
        $this->approvedPromotion($c, $p26, $year2026, $c['class'], $result, $p27, $c['class']);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->sheetRoute($result))
            ->assertOk()
            ->assertSee('Promoted')
            ->assertSee('Session 2027');
    }

    public function test_pending_promotion_is_not_shown_as_official(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $this->student($c['institute'], 'Rahim'), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($result, $placement, 4.75, $this->standardRows($c));
        $this->pendingPromotion($c, $placement, $year, $c['class'], $result);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->sheetRoute($result))
            ->assertOk()
            ->assertDontSee('Promoted')
            ->assertDontSee('Pending');
    }

    // ------------------------------------------------------------- Read-only safety

    public function test_viewing_the_result_sheet_does_not_mutate_records(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $p1 = $this->placement($c, $this->student($c['institute'], 'Rahim'), $year, $c['class'], $c['group']);
        $p2 = $this->placement($c, $this->student($c['institute'], 'Karim'), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($result, $p1, 4.75, $this->standardRows($c));
        $this->addSnapshot($result, $p2, 3.5, $this->standardRows($c));

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
            ->get($this->sheetRoute($result))
            ->assertOk();

        foreach ($tables as $table) {
            $after = collect(DB::table($table)->get()->map(fn ($row) => (array) $row));
            $this->assertEquals($before[$table]->all(), $after->all(), "{$table} was mutated by viewing the result sheet.");
        }
    }

    // ------------------------------------------------------------- Print UI

    public function test_print_specific_content_renders(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', 'Session 2026');
        $placement = $this->placement($c, $this->student($c['institute'], 'Rahim'), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($result, $placement, 4.75, $this->standardRows($c));

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->sheetRoute($result))
            ->assertOk()
            ->assertSee('Print Result Sheet')
            ->assertSee('window.print()')
            ->assertSee('media print')
            ->assertSee('Class Teacher')
            ->assertSee('Head / Principal')
            ->assertSee('Generated');
    }
}
