<?php

namespace Tests\Feature;

use App\Models\AcademicAssessment;
use App\Models\AcademicFinalResultPolicy;
use App\Models\AcademicFinalResultRow;
use App\Models\AcademicFinalResultStudent;
use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicResultAggregationItem;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicStudentMark;
use App\Models\AcademicYear;
use App\Models\AssessmentSubject;
use App\Models\AssessmentSubjectComponent;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Component;
use App\Models\Country;
use App\Models\EducationSystem;
use App\Models\GradeScale;
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
use App\Services\AcademicFinalResultLifecycleService;
use App\Services\AcademicFinalResultPreflightService;
use App\Services\AcademicGradingService;
use App\Services\AcademicResultAggregationService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Database\Seeders\AcademicAssessmentSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Step 29 — final-result generation safety / pre-flight.
 *
 * Read-only validation gate layered in front of the generation pipeline. The
 * suite verifies the blocking verdicts (missing policy, missing grading
 * configuration, invalid weights, missing assessments, missing subjects, no
 * eligible students), the non-blocking warnings (absences, incomplete/missing
 * marks, inactive policy), tenant + branch isolation, permission gating,
 * read-only guarantees (including that a missing policy is never created and
 * published snapshots / promotion decisions are never touched) and the bulk
 * query pattern.
 */
class AcademicFinalResultPreflightTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicAssessmentSeeder::class);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    // ------------------------------------------------------------ Fixtures

    private function country(string $iso2 = 'BD'): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => $iso2],
            ['name' => 'Bangladesh', 'iso3' => strtoupper($iso2).'R', 'phone_code' => '880', 'status' => true]
        );
    }

    private function system(Country $country): EducationSystem
    {
        return EducationSystem::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $country->id, 'code' => 'general'],
            ['name' => 'General Education', 'display_order' => 0, 'status' => true]
        );
    }

    private function level(EducationSystem $system): AcademicLevel
    {
        return AcademicLevel::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $system->country_id, 'education_system_id' => $system->id, 'code' => 'secondary'],
            ['name' => 'Secondary', 'display_order' => 1, 'status' => true]
        );
    }

    private function classGrade(AcademicLevel $level, string $code = 'pf-c8'): ClassGrade
    {
        return ClassGrade::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $level->country_id, 'education_system_id' => $level->education_system_id, 'academic_level_id' => $level->id, 'code' => $code],
            ['name' => 'Class 8', 'display_order' => 0, 'status' => true]
        );
    }

    private function group(ClassGrade $classGrade, string $code = 'pf-sci'): AcademicGroup
    {
        return AcademicGroup::withoutGlobalScopes()->firstOrCreate(
            ['class_grade_id' => $classGrade->id, 'code' => $code],
            [
                'country_id' => $classGrade->country_id,
                'education_system_id' => $classGrade->education_system_id,
                'academic_level_id' => $classGrade->academic_level_id,
                'name' => 'Science',
                'display_order' => 0,
                'status' => true,
            ]
        );
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

    private function assign(Subject $subject, ClassGrade $classGrade, string $requirementType, int $displayOrder): SubjectAcademicAssignment
    {
        return SubjectAcademicAssignment::create([
            'subject_id' => $subject->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => null,
            'requirement_type' => $requirementType,
            'selection_group_id' => null,
            'display_order' => $displayOrder,
            'status' => 'active',
        ]);
    }

    private function institute(Country $country, string $name = 'PF Inst'): Institute
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
            'first_name' => 'PF',
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function student(Institute $institute, string $firstName = 'Rahim', ?Branch $branch = null): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'student_id_number' => 'PF'.mt_rand(100000, 999999),
            'first_name' => $firstName,
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => now()->toDateString(),
        ]);
    }

    private function defaultYear(Institute $institute): AcademicYear
    {
        return AcademicYear::withoutGlobalScopes()->firstOrCreate(
            ['institute_id' => $institute->id, 'code' => '2026'],
            ['name' => 'Academic Year 2026', 'is_current' => true, 'status' => true]
        );
    }

    private function writtenComponent(): Component
    {
        return Component::where('slug', 'written')->firstOrFail();
    }

    private function mcqComponent(): Component
    {
        return Component::where('slug', 'mcq')->firstOrFail();
    }

    private function curriculum(): array
    {
        $country = $this->country();
        $classGrade = $this->classGrade($this->level($this->system($country)));
        $institute = $this->institute($country);
        $math = $this->subject('Mathematics', 'PF100001');
        $english = $this->subject('English', 'PF100002');

        $this->assign($math, $classGrade, 'mandatory', 1);
        $this->assign($english, $classGrade, 'mandatory', 2);

        return [
            'country' => $country,
            'class_grade' => $classGrade,
            'group' => $this->group($classGrade),
            'institute' => $institute,
            'owner' => $this->user($institute, 'institute-owner', 'pf-owner'),
            'math' => $math,
            'english' => $english,
        ];
    }

    private function assessment(array $c, string $name, ?Branch $branch = null): AcademicAssessment
    {
        $assessment = AcademicAssessment::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => $branch?->id,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'name' => $name,
            'status' => 'scheduled',
            'display_order' => 1,
        ]);

        $written = $this->writtenComponent();
        $mcq = $this->mcqComponent();

        $math = $this->addSubject($assessment, $c['math']->id, 1);
        $this->addComponent($math, $written->id, 70, 23);
        $this->addComponent($math, $mcq->id, 30, 10);

        $english = $this->addSubject($assessment, $c['english']->id, 2);
        $this->addComponent($english, $written->id, 100, 33);

        return $assessment;
    }

    /** Assessment that participates in a scheme but covers NO subjects. */
    private function subjectlessAssessment(array $c, string $name): AcademicAssessment
    {
        return AcademicAssessment::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'name' => $name,
            'status' => 'scheduled',
            'display_order' => 1,
        ]);
    }

    private function addSubject(AcademicAssessment $assessment, int $subjectId, int $displayOrder): AssessmentSubject
    {
        return AssessmentSubject::create([
            'assessment_id' => $assessment->id,
            'subject_id' => $subjectId,
            'display_order' => $displayOrder,
            'status' => 'active',
        ]);
    }

    private function addComponent(AssessmentSubject $config, int $componentId, float $fullMark, float $passMark): AssessmentSubjectComponent
    {
        return AssessmentSubjectComponent::create([
            'assessment_subject_id' => $config->id,
            'component_id' => $componentId,
            'full_mark' => $fullMark,
            'pass_mark' => $passMark,
            'mandatory_pass' => false,
            'display_order' => 1,
            'status' => 'active',
        ]);
    }

    private function placement(array $c, Student $student): StudentAcademicPlacement
    {
        $placement = StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $student->id,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'status' => 'active',
        ]);

        foreach ([$c['math'], $c['english']] as $subject) {
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

    private function mark(StudentAcademicPlacement $placement, AssessmentSubject $config, float $obtained, string $status = 'entered', ?int $componentIndex = 0): AcademicStudentMark
    {
        $component = $config->components->get($componentIndex ?? 0) ?? $config->components->first();

        return AcademicStudentMark::create([
            'institute_id' => $placement->institute_id,
            'academic_assessment_id' => $config->assessment_id,
            'assessment_subject_id' => $config->id,
            'assessment_component_id' => $component->id,
            'student_id' => $placement->student_id,
            'academic_placement_id' => $placement->id,
            'obtained_mark' => $status === 'absent' ? null : $obtained,
            'status' => $status,
        ]);
    }

    private function enterAll(StudentAcademicPlacement $placement, AcademicAssessment $assessment): void
    {
        foreach ($assessment->subjects()->with('components')->get() as $config) {
            $this->mark($placement, $config, 80);
            if ($config->components->count() > 1) {
                $this->mark($placement, $config, 20, 'entered', 1);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items  [['assessment_id' => int, 'weight' => float]]
     */
    private function scheme(array $c, string $name, array $items, ?Branch $branch = null, string $status = 'active'): AcademicResultAggregationScheme
    {
        return app(AcademicResultAggregationService::class)->store(
            $c['institute'],
            $branch,
            (int) $c['owner']->id,
            [
                'academic_year_id' => $this->defaultYear($c['institute'])->id,
                'class_grade_id' => $c['class_grade']->id,
                'academic_group_id' => $c['group']->id,
                'name' => $name,
                'status' => $status,
                'display_order' => 1,
            ],
            $items
        );
    }

    private function gradeRows(): array
    {
        return [
            ['grade' => 'A+', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.0, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 1, 'status' => true],
            ['grade' => 'A', 'min_score' => 70, 'max_score' => 79, 'grade_point' => 4.0, 'is_pass' => true, 'gpa_included' => true, 'display_order' => 2, 'status' => true],
            ['grade' => 'F', 'min_score' => 0, 'max_score' => 39, 'grade_point' => 0.0, 'is_pass' => false, 'gpa_included' => true, 'display_order' => 3, 'status' => true],
        ];
    }

    /** Institute-level override at the class's academic level. */
    private function instituteScale(array $c): GradeScale
    {
        return app(AcademicGradingService::class)->store([
            'name' => 'Preflight Scale',
            'institute_id' => (int) $c['institute']->id,
            'academic_level_id' => (int) $c['class_grade']->academic_level_id,
            'gpa_mode' => 'equal_weight',
            'optional_subject_gpa' => 'included',
            'display_order' => 1,
            'status' => true,
        ], $this->gradeRows());
    }

    private function policy(array $c, AcademicResultAggregationScheme $scheme): AcademicFinalResultPolicy
    {
        return app(AcademicFinalResultLifecycleService::class)->policyForScheme($c['institute'], $scheme);
    }

    private function preflight(AcademicResultAggregationScheme $scheme): array
    {
        return app(AcademicFinalResultPreflightService::class)->preflight($scheme);
    }

    /** Build the canonical valid scope: two assessments + complete placement + policy + scale. */
    private function readyFixture(string $name = 'Annual Result'): array
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $placement = $this->placement($c, $this->student($c['institute']));
        $this->enterAll($placement, $mid);
        $this->enterAll($placement, $final);
        $scheme = $this->scheme($c, $name, [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);
        $policy = $this->policy($c, $scheme);
        $scale = $this->instituteScale($c);

        return [
            'c' => $c,
            'mid' => $mid,
            'final' => $final,
            'placement' => $placement,
            'scheme' => $scheme,
            'policy' => $policy,
            'scale' => $scale,
        ];
    }

    private function checkByLabel(array $checks, string $label): array
    {
        foreach ($checks as $check) {
            if ($check['label'] === $label) {
                return $check;
            }
        }

        $this->fail('No check found with label '.$label.'.');
    }

    // -------------------------------------------------------------- Tests

    public function test_authorized_user_can_view_preflight_and_valid_configuration_allows_generation(): void
    {
        $fx = $this->readyFixture();

        TenantContext::set($fx['c']['institute']->id);

        $response = $this->actingAs($fx['c']['owner'], 'institute_user')
            ->get(route('settings.academic.final-results.preflight', $fx['scheme']))
            ->assertOk();

        $response->assertSee('Final Result Pre-flight')
            ->assertSee('Final Result Generation Allowed')
            ->assertSee($fx['policy']->name)
            ->assertSee('Preflight Scale')
            ->assertSee('100%');

        $data = $this->preflight($fx['scheme']);
        $this->assertTrue($data['verdict']['allowed']);
        $this->assertSame('Final Result Generation Allowed', $data['verdict']['label']);
        $this->assertSame([], $data['verdict']['blocking']);
        $this->assertSame(0, $data['verdict']['blocking_count']);

        $this->assertSame('pass', $data['policy']['status']);
        $this->assertSame('pass', $this->checkByLabel($data['configuration'], 'Required Assessments')['status']);
        $this->assertSame('2', $this->checkByLabel($data['configuration'], 'Required Assessments')['value']);
        $this->assertSame('pass', $this->checkByLabel($data['configuration'], 'Required Subjects')['status']);
        $this->assertSame('2', $this->checkByLabel($data['configuration'], 'Required Subjects')['value']);
        $this->assertSame('pass', $this->checkByLabel($data['configuration'], 'Assessment Weights')['status']);
        $this->assertSame('100%', $this->checkByLabel($data['configuration'], 'Assessment Weights')['value']);
        $this->assertSame('pass', $this->checkByLabel($data['configuration'], 'Grading Configuration')['status']);
        $this->assertSame('pass', $this->checkByLabel($data['configuration'], 'Eligible Students')['status']);
        $this->assertSame('1', $this->checkByLabel($data['configuration'], 'Eligible Students')['value']);

        $this->assertSame('ready', $data['coverage']['readiness']);
        $this->assertSame(1, $data['coverage']['summary']['complete']);
    }

    public function test_policy_page_has_preflight_check_link(): void
    {
        $fx = $this->readyFixture();

        TenantContext::set($fx['c']['institute']->id);

        $this->actingAs($fx['c']['owner'], 'institute_user')
            ->get(route('settings.academic.final-results.policy', $fx['scheme']))
            ->assertOk()
            ->assertSee('Pre-flight Check')
            ->assertSee(route('settings.academic.final-results.preflight', $fx['scheme']));
    }

    public function test_show_page_has_preflight_check_link(): void
    {
        $fx = $this->readyFixture();
        $fx['policy']->update(['require_approval' => false]);
        $result = app(AcademicFinalResultLifecycleService::class)->createResult(
            $fx['c']['institute'],
            $fx['policy'],
            'Annual Final 2026',
            (int) $fx['c']['owner']->id
        );

        TenantContext::set($fx['c']['institute']->id);

        $this->actingAs($fx['c']['owner'], 'institute_user')
            ->get(route('settings.academic.final-results.show', $result))
            ->assertOk()
            ->assertSee('Pre-flight Check')
            ->assertSee(route('settings.academic.final-results.preflight', $fx['scheme']));
    }

    public function test_staff_without_final_result_manage_is_blocked(): void
    {
        $fx = $this->readyFixture();
        $teacher = $this->user($fx['c']['institute'], 'teacher', 'pf-teacher');

        TenantContext::set($fx['c']['institute']->id);

        $this->actingAs($teacher, 'institute_user')
            ->get(route('settings.academic.final-results.preflight', $fx['scheme']))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $fx = $this->readyFixture();

        TenantContext::set($fx['c']['institute']->id);

        $this->get(route('settings.academic.final-results.preflight', $fx['scheme']))->assertRedirect();
    }

    public function test_cross_tenant_access_is_404(): void
    {
        $fx = $this->readyFixture();

        $other = $this->institute($this->country('IN'), 'Other PF Inst');
        $otherAdmin = $this->user($other, 'institute-admin', 'pf-other');

        TenantContext::set($other->id);

        $this->actingAs($otherAdmin, 'institute_user')
            ->get(route('settings.academic.final-results.preflight', $fx['scheme']))
            ->assertStatus(404);
    }

    public function test_cross_branch_access_is_404(): void
    {
        $c = $this->curriculum();
        $branchA = $this->branch($c['institute'], 'Branch A');
        $branchB = $this->branch($c['institute'], 'Branch B');
        $adminB = $this->user($c['institute'], 'institute-admin', 'pf-admin-b', $branchB);

        $mid = $this->assessment($c, 'Mid Term 2026', $branchA);
        $final = $this->assessment($c, 'Final Term 2026', $branchA);
        $scheme = $this->scheme($c, 'Branch A Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ], $branchA);

        TenantContext::set($c['institute']->id);
        BranchContext::set($branchB->id);

        $this->actingAs($adminB, 'institute_user')
            ->get(route('settings.academic.final-results.preflight', $scheme))
            ->assertStatus(404);
    }

    public function test_missing_policy_blocks_generation_and_is_never_created(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $placement = $this->placement($c, $this->student($c['institute']));
        $this->enterAll($placement, $mid);
        $this->enterAll($placement, $final);
        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);
        $this->instituteScale($c);

        $this->assertDatabaseCount('academic_final_result_policies', 0);

        $data = $this->preflight($scheme);
        $this->assertFalse($data['verdict']['allowed']);
        $this->assertSame('Final Result Generation Blocked', $data['verdict']['label']);

        $this->assertSame('blocked', $data['policy']['status']);
        $this->assertSame('Missing', $data['policy']['value']);
        $this->assertTrue(collect($data['verdict']['blocking'])->contains(
            fn (string $reason) => str_contains($reason, 'No final-result policy exists')
        ));

        $this->assertDatabaseCount('academic_final_result_policies', 0);
    }

    public function test_missing_grading_configuration_blocks_generation(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $placement = $this->placement($c, $this->student($c['institute']));
        $this->enterAll($placement, $mid);
        $this->enterAll($placement, $final);
        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);
        $this->policy($c, $scheme);
        // No grade scale is configured anywhere in the resolution ladder.

        $data = $this->preflight($scheme);
        $this->assertFalse($data['verdict']['allowed']);

        $gradingCheck = $this->checkByLabel($data['configuration'], 'Grading Configuration');
        $this->assertSame('blocked', $gradingCheck['status']);
        $this->assertSame('No scale', $gradingCheck['value']);
        $this->assertTrue(collect($data['verdict']['blocking'])->contains(
            fn (string $reason) => str_contains($reason, 'No grade scale resolves')
        ));
    }

    public function test_invalid_weight_total_blocks_generation(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $placement = $this->placement($c, $this->student($c['institute']));
        $this->enterAll($placement, $mid);
        $this->enterAll($placement, $final);
        $scheme = $this->scheme($c, 'Underweight Scheme', [
            ['assessment_id' => $mid->id, 'weight' => 30],
            ['assessment_id' => $final->id, 'weight' => 30],
        ]);
        $this->policy($c, $scheme);
        $this->instituteScale($c);

        $data = $this->preflight($scheme);
        $this->assertFalse($data['verdict']['allowed']);

        $weightsCheck = $this->checkByLabel($data['configuration'], 'Assessment Weights');
        $this->assertSame('blocked', $weightsCheck['status']);
        $this->assertSame('60%', $weightsCheck['value']);
        $this->assertTrue(collect($data['verdict']['blocking'])->contains(
            fn (string $reason) => str_contains($reason, 'must total 100%')
        ));
    }

    public function test_scheme_without_required_assessments_blocks_generation(): void
    {
        $c = $this->curriculum();
        $this->placement($c, $this->student($c['institute']));

        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'name' => 'Empty Scheme',
            'status' => 'active',
            'display_order' => 1,
            'created_by' => (int) $c['owner']->id,
        ]);
        $this->policy($c, $scheme);
        $this->instituteScale($c);

        $data = $this->preflight($scheme);
        $this->assertFalse($data['verdict']['allowed']);

        $assessmentsCheck = $this->checkByLabel($data['configuration'], 'Required Assessments');
        $this->assertSame('blocked', $assessmentsCheck['status']);
        $this->assertSame('0', $assessmentsCheck['value']);

        $subjectsCheck = $this->checkByLabel($data['configuration'], 'Required Subjects');
        $this->assertSame('blocked', $subjectsCheck['status']);

        $this->assertTrue(collect($data['verdict']['blocking'])->contains(
            fn (string $reason) => str_contains($reason, 'No required assessments')
        ));
    }

    public function test_assessment_without_subjects_blocks_generation(): void
    {
        $c = $this->curriculum();
        $final = $this->subjectlessAssessment($c, 'No Subjects Final');
        $placement = $this->placement($c, $this->student($c['institute']));
        $this->enterAll($placement, $final);
        $scheme = $this->scheme($c, 'No Subjects Result', [
            ['assessment_id' => $final->id, 'weight' => 100],
        ]);
        $this->policy($c, $scheme);
        $this->instituteScale($c);

        $this->assertSame(0, AcademicResultAggregationItem::where('scheme_id', $scheme->id)->firstOrFail()->assessment->subjects()->count());

        $data = $this->preflight($scheme);
        $this->assertFalse($data['verdict']['allowed']);

        $subjectsCheck = $this->checkByLabel($data['configuration'], 'Required Subjects');
        $this->assertSame('blocked', $subjectsCheck['status']);
        $this->assertSame('0', $subjectsCheck['value']);
        $this->assertTrue(collect($data['verdict']['blocking'])->contains(
            fn (string $reason) => str_contains($reason, 'No required subjects are covered')
        ));
    }

    public function test_no_eligible_students_blocks_generation(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);
        $this->policy($c, $scheme);
        $this->instituteScale($c);

        $data = $this->preflight($scheme);
        $this->assertFalse($data['verdict']['allowed']);

        $eligibleCheck = $this->checkByLabel($data['configuration'], 'Eligible Students');
        $this->assertSame('blocked', $eligibleCheck['status']);
        $this->assertSame('0', $eligibleCheck['value']);
        $this->assertTrue(collect($data['verdict']['blocking'])->contains(
            fn (string $reason) => str_contains($reason, 'No students are placed')
        ));
    }

    public function test_missing_marks_are_non_blocking_warnings(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');

        $complete = $this->placement($c, $this->student($c['institute'], 'Complete'));
        $this->enterAll($complete, $mid);
        $this->enterAll($complete, $final);

        $missing = $this->placement($c, $this->student($c['institute'], 'Missing'));
        $this->enterAll($missing, $mid);
        $finalEnglish = $final->subjects()->where('subject_id', $c['english']->id)->firstOrFail();
        $this->mark($missing, $finalEnglish, 70);

        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);
        $this->policy($c, $scheme);
        $this->instituteScale($c);

        $data = $this->preflight($scheme);
        $this->assertTrue($data['verdict']['allowed']);
        $this->assertSame(0, $data['verdict']['blocking_count']);

        $this->assertTrue(collect($data['verdict']['warnings'])->contains(
            fn (string $warning) => str_contains($warning, 'required assessment(s) are not ready')
        ));

        $this->assertSame('not_ready', $data['coverage']['readiness']);
        $this->assertSame(1, $data['coverage']['summary']['missing']);
        $this->assertSame('Missing marks in Final Term 2026 (Mathematics).', $data['coverage']['exceptions'][0]['reason']);
        $this->assertSame(['Final Term 2026'], $data['coverage']['exceptions'][0]['missing_assessments']);
    }

    public function test_legitimate_absence_is_non_blocking_with_warnings(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');

        $absent = $this->placement($c, $this->student($c['institute'], 'Absent'));
        $this->enterAll($absent, $mid);
        foreach ($final->subjects()->with('components')->get() as $config) {
            $this->mark($absent, $config, 0, 'absent', 0);
            if ($config->components->count() > 1) {
                $this->mark($absent, $config, 0, 'absent', 1);
            }
        }

        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);
        $this->policy($c, $scheme);
        $this->instituteScale($c);

        $data = $this->preflight($scheme);
        $this->assertTrue($data['verdict']['allowed']);
        $this->assertSame(0, $data['verdict']['blocking_count']);

        $this->assertTrue(collect($data['verdict']['warnings'])->contains(
            fn (string $warning) => str_contains($warning, 'legitimately absent')
        ));

        $this->assertSame(1, $data['coverage']['summary']['absent']);
        $this->assertSame(['Final Term 2026'], $data['coverage']['exceptions'][0]['absent_assessments']);
    }

    public function test_incomplete_missing_and_no_assessment_are_reported_across_assessments(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $midMath = $mid->subjects()->where('subject_id', $c['math']->id)->firstOrFail();
        $midEnglish = $mid->subjects()->where('subject_id', $c['english']->id)->firstOrFail();
        $finalMath = $final->subjects()->where('subject_id', $c['math']->id)->firstOrFail();
        $finalEnglish = $final->subjects()->where('subject_id', $c['english']->id)->firstOrFail();

        $partial = $this->placement($c, $this->student($c['institute'], 'Partial'));
        $this->enterAll($partial, $mid);
        AcademicStudentMark::where('academic_placement_id', $partial->id)->delete();
        $this->mark($partial, $midMath, 40, 'entered', 0);
        $this->mark($partial, $midEnglish, 60);
        $this->mark($partial, $finalMath, 80);
        $this->mark($partial, $finalMath, 20, 'entered', 1);
        $this->mark($partial, $finalEnglish, 80);

        $missing = $this->placement($c, $this->student($c['institute'], 'Missing'));
        $this->enterAll($missing, $mid);
        $this->mark($missing, $finalEnglish, 70);

        $noRecord = $this->placement($c, $this->student($c['institute'], 'NoRecord'));
        $this->enterAll($noRecord, $mid);

        $complete = $this->placement($c, $this->student($c['institute'], 'Complete'));
        $this->enterAll($complete, $mid);
        $this->enterAll($complete, $final);

        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);
        $this->policy($c, $scheme);
        $this->instituteScale($c);

        $data = $this->preflight($scheme);
        $this->assertTrue($data['verdict']['allowed']);

        $statusById = [];
        foreach ($data['coverage']['students'] as $row) {
            $statusById[$row['student']->id] = $row['status'];
        }

        $this->assertSame('incomplete', $statusById[$partial->student_id]);
        $this->assertSame('missing', $statusById[$missing->student_id]);
        $this->assertSame('no_assessment', $statusById[$noRecord->student_id]);
        $this->assertSame('complete', $statusById[$complete->student_id]);

        $this->assertSame(1, $data['coverage']['summary']['incomplete']);
        $this->assertSame(1, $data['coverage']['summary']['missing']);
        $this->assertSame(1, $data['coverage']['summary']['no_assessment']);
        $this->assertSame(1, $data['coverage']['summary']['complete']);
    }

    public function test_multiple_students_and_subjects_are_covered(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');

        foreach (['A', 'B', 'C'] as $name) {
            $placement = $this->placement($c, $this->student($c['institute'], $name));
            $this->enterAll($placement, $mid);
            $this->enterAll($placement, $final);
        }

        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);
        $this->policy($c, $scheme);
        $this->instituteScale($c);

        $data = $this->preflight($scheme);
        $this->assertTrue($data['verdict']['allowed']);
        $this->assertSame('2', $this->checkByLabel($data['configuration'], 'Required Assessments')['value']);
        $this->assertSame('2', $this->checkByLabel($data['configuration'], 'Required Subjects')['value']);
        $this->assertSame('3', $this->checkByLabel($data['configuration'], 'Eligible Students')['value']);
        $this->assertSame(3, $data['coverage']['summary']['complete']);
        $this->assertCount(3, $data['coverage']['students']);
    }

    public function test_preflight_is_read_only(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $placement = $this->placement($c, $this->student($c['institute']));
        $this->enterAll($placement, $mid);
        $this->enterAll($placement, $final);
        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);

        $this->assertDatabaseCount('academic_student_marks', 6);
        $this->assertDatabaseCount('student_academic_placements', 1);
        $this->assertDatabaseCount('academic_assessments', 2);
        $this->assertDatabaseCount('academic_final_result_policies', 0);
        $this->assertDatabaseCount('academic_final_results', 0);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.final-results.preflight', $scheme))
            ->assertOk();

        $this->assertDatabaseCount('academic_student_marks', 6);
        $this->assertDatabaseCount('student_academic_placements', 1);
        $this->assertDatabaseCount('academic_assessments', 2);
        $this->assertDatabaseCount('academic_final_result_policies', 0);
        $this->assertDatabaseCount('academic_final_results', 0);
        $this->assertDatabaseCount('academic_final_result_students', 0);
        $this->assertDatabaseCount('academic_final_result_rows', 0);
        $this->assertDatabaseHas('academic_student_marks', ['obtained_mark' => 80.0]);
    }

    public function test_preflight_does_not_touch_published_snapshot_or_promotion_rows(): void
    {
        $fx = $this->readyFixture();
        $fx['policy']->update(['require_approval' => false]);
        $lifecycle = app(AcademicFinalResultLifecycleService::class);
        $result = $lifecycle->createResult(
            $fx['c']['institute'],
            $fx['policy'],
            'Annual Final 2026',
            (int) $fx['c']['owner']->id
        );
        $lifecycle->lock($result, (int) $fx['c']['owner']->id);
        $lifecycle->publish($result, (int) $fx['c']['owner']->id);
        $this->assertSame('published', $result->refresh()->status);

        $promotionPolicy = PromotionPolicy::create([
            'institute_id' => $fx['c']['institute']->id,
            'branch_id' => null,
            'name' => 'Promotion Policy',
            'academic_year_id' => $this->defaultYear($fx['c']['institute'])->id,
            'class_grade_id' => $fx['c']['class_grade']->id,
            'academic_group_id' => $fx['c']['group']->id,
            'status' => 'approved',
            'created_by' => (int) $fx['c']['owner']->id,
        ]);

        $decision = PromotionDecision::create([
            'policy_id' => $promotionPolicy->id,
            'result_id' => $result->id,
            'institute_id' => $fx['c']['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $this->defaultYear($fx['c']['institute'])->id,
            'status' => 'pending',
            'created_by' => (int) $fx['c']['owner']->id,
        ]);

        PromotionDecisionItem::create([
            'decision_id' => $decision->id,
            'placement_id' => $fx['placement']->id,
            'student_id' => $fx['placement']->student_id,
            'decision' => 'pending',
        ]);

        $studentRows = AcademicFinalResultStudent::where('result_id', $result->id)->count();
        $rowRows = AcademicFinalResultRow::where('result_id', $result->id)->count();
        $gpa = AcademicFinalResultStudent::where('result_id', $result->id)->firstOrFail()->gpa;

        TenantContext::set($fx['c']['institute']->id);

        $this->actingAs($fx['c']['owner'], 'institute_user')
            ->get(route('settings.academic.final-results.preflight', $fx['scheme']))
            ->assertOk();

        $this->assertSame($studentRows, AcademicFinalResultStudent::where('result_id', $result->id)->count());
        $this->assertSame($rowRows, AcademicFinalResultRow::where('result_id', $result->id)->count());
        $this->assertSame($gpa, AcademicFinalResultStudent::where('result_id', $result->id)->firstOrFail()->gpa);
        $this->assertSame('published', $result->refresh()->status);
        $this->assertDatabaseCount('promotion_decisions', 1);
        $this->assertDatabaseCount('promotion_decision_items', 1);
        $this->assertDatabaseHas('promotion_decision_items', ['decision' => 'pending']);
    }

    public function test_preflight_bulk_loads_marks_without_n_plus_1(): void
    {
        $fx = $this->readyFixture();
        $fx['policy']->update(['require_approval' => false]);

        foreach (['D', 'E'] as $name) {
            $placement = $this->placement($fx['c'], $this->student($fx['c']['institute'], $name));
            $this->enterAll($placement, $fx['mid']);
            $this->enterAll($placement, $fx['final']);
        }

        DB::enableQueryLog();
        $this->preflight($fx['scheme']);

        $marksSelects = collect(DB::getQueryLog())
            ->filter(fn ($query) => str_contains($query['query'], 'academic_student_marks') && str_starts_with(ltrim($query['query']), 'select'))
            ->count();

        // One bulk marks load per required assessment — never per student.
        $this->assertSame(2, $marksSelects);
    }
}
