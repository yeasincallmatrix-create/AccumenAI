<?php

namespace Tests\Feature;

use App\Models\AcademicAssessment;
use App\Models\AcademicFinalResultRow;
use App\Models\AcademicFinalResultStudent;
use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
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
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\StudentSubjectSelection;
use App\Models\Subject;
use App\Models\SubjectAcademicAssignment;
use App\Services\AcademicFinalResultLifecycleService;
use App\Services\AcademicFinalResultReadinessService;
use App\Services\AcademicGradingService;
use App\Services\AcademicResultAggregationService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Database\Seeders\AcademicAssessmentSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Step 28 — final-result readiness gate.
 *
 * Read-only layer "is this scheme's scope ready for final-result generation /
 * locking". The suite verifies the scope aggregation across required
 * assessments (READY / READY WITH EXCEPTIONS / NOT READY), the per-student
 * final coverage categories, weight / missing-assessment config limitations,
 * tenant + branch isolation, permission gating, read-only guarantees
 * (including that a missing policy is never created and frozen final-result
 * snapshots are never touched) and the CSV exceptions export.
 */
class AcademicFinalResultReadinessTest extends TestCase
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

    private function classGrade(AcademicLevel $level, string $code = 'fr-c8'): ClassGrade
    {
        return ClassGrade::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $level->country_id, 'education_system_id' => $level->education_system_id, 'academic_level_id' => $level->id, 'code' => $code],
            ['name' => 'Class 8', 'display_order' => 0, 'status' => true]
        );
    }

    private function group(ClassGrade $classGrade, string $code = 'fr-sci'): AcademicGroup
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

    private function institute(Country $country, string $name = 'FR Inst'): Institute
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
            'first_name' => 'FR',
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
            'student_id_number' => 'FR'.mt_rand(100000, 999999),
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
        $math = $this->subject('Mathematics', 'FR100001');
        $english = $this->subject('English', 'FR100002');

        $this->assign($math, $classGrade, 'mandatory', 1);
        $this->assign($english, $classGrade, 'mandatory', 2);

        return [
            'country' => $country,
            'class_grade' => $classGrade,
            'group' => $this->group($classGrade),
            'institute' => $institute,
            'owner' => $this->user($institute, 'institute-owner', 'fr-owner'),
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

    /** Fully enter both subjects of an assessment for a placement. */
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

    private function finalReadiness(Institute $institute, AcademicResultAggregationScheme $scheme): array
    {
        TenantContext::set($institute->id);

        return app(AcademicFinalResultReadinessService::class)->forScheme($scheme);
    }

    /**
     * Institute-wide grade scale so the Step-29 pre-flight generation gate
     * (Step 30) passes for fixtures that start a cycle.
     */
    private function generationScale(array $c): GradeScale
    {
        return app(AcademicGradingService::class)->store([
            'name' => 'Readiness Scale',
            'institute_id' => $c['institute']->id,
            'gpa_mode' => 'equal_weight',
            'optional_subject_gpa' => 'included',
            'status' => true,
            'display_order' => 1,
        ], [
            ['grade' => 'A+', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'F', 'min_score' => 0, 'max_score' => 79.99, 'grade_point' => 0.0, 'is_pass' => false, 'gpa_included' => true],
        ]);
    }

    // ------------------------------------------------------------ CSV helpers

    private function csvContent(TestResponse $response): string
    {
        return $response->streamedContent();
    }

    private function parseCsv(string $content): array
    {
        $content = ltrim($content, "\xEF\xBB\xBF");

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    // -------------------------------------------------------------- Tests

    public function test_authorized_user_can_view_readiness_for_complete_scheme(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');

        $one = $this->placement($c, $this->student($c['institute'], 'One'));
        $two = $this->placement($c, $this->student($c['institute'], 'Two'));

        foreach ([$one, $two] as $placement) {
            $this->enterAll($placement, $mid);
            $this->enterAll($placement, $final);
        }

        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);

        $policy = app(AcademicFinalResultLifecycleService::class)->policyForScheme($c['institute'], $scheme);

        TenantContext::set($c['institute']->id);

        $response = $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.final-results.readiness', $scheme))
            ->assertOk();

        $response->assertSee('READY')
            ->assertSee('Final Term 2026')
            ->assertSee('Mid Term 2026')
            ->assertSee($policy->name)
            ->assertSee($one->student->full_name)
            ->assertSee($two->student->full_name)
            ->assertSee('Eligible Students');

        $data = $this->finalReadiness($c['institute'], $scheme);
        $this->assertTrue($data['is_ready']);
        $this->assertSame('ready', $data['readiness']);
        $this->assertSame(2, $data['summary']['required_assessments']);
        $this->assertSame(2, $data['summary']['ready_assessments']);
        $this->assertSame(0, $data['summary']['not_ready_assessments']);
        $this->assertSame(2, $data['summary']['eligible_students']);
        $this->assertSame(2, $data['summary']['complete']);
        $this->assertSame(0, $data['summary']['absent']);
        $this->assertSame(0, $data['summary']['missing']);
        $this->assertSame(0, $data['summary']['incomplete']);
        $this->assertSame(0, $data['summary']['no_assessment']);
        $this->assertSame([], $data['exceptions']);
    }

    public function test_policy_page_has_check_readiness_link(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.final-results.policy', $scheme))
            ->assertOk()
            ->assertSee('Check Readiness')
            ->assertSee(route('settings.academic.final-results.readiness', $scheme));
    }

    public function test_show_page_has_result_readiness_link(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);

        TenantContext::set($c['institute']->id);
        $this->generationScale($c);
        $this->placement($c, $this->student($c['institute']));

        $lifecycle = app(AcademicFinalResultLifecycleService::class);
        $policy = $lifecycle->policyForScheme($c['institute'], $scheme);
        $result = $lifecycle->createResult($c['institute'], $policy, 'Annual Final 2026', (int) $c['owner']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.final-results.show', $result))
            ->assertOk()
            ->assertSee('Result Readiness')
            ->assertSee(route('settings.academic.final-results.readiness', $scheme));
    }

    public function test_staff_without_education_manage_is_blocked(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);
        $teacher = $this->user($c['institute'], 'teacher', 'fr-teacher');

        TenantContext::set($c['institute']->id);

        $this->actingAs($teacher, 'institute_user')
            ->get(route('settings.academic.final-results.readiness', $scheme))
            ->assertForbidden();

        $this->actingAs($teacher, 'institute_user')
            ->get(route('settings.academic.final-results.readiness.export', $scheme))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);

        TenantContext::set($c['institute']->id);

        $this->get(route('settings.academic.final-results.readiness', $scheme))->assertRedirect();
        $this->get(route('settings.academic.final-results.readiness.export', $scheme))->assertRedirect();
    }

    public function test_cross_tenant_access_is_404(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);

        $other = $this->institute($this->country('IN'), 'Other FR Inst');
        $otherAdmin = $this->user($other, 'institute-admin', 'fr-other');

        TenantContext::set($other->id);

        $this->actingAs($otherAdmin, 'institute_user')
            ->get(route('settings.academic.final-results.readiness', $scheme))
            ->assertStatus(404);

        $this->actingAs($otherAdmin, 'institute_user')
            ->get(route('settings.academic.final-results.readiness.export', $scheme))
            ->assertStatus(404);
    }

    public function test_cross_branch_access_is_404(): void
    {
        $c = $this->curriculum();
        $branchA = $this->branch($c['institute'], 'Branch A');
        $branchB = $this->branch($c['institute'], 'Branch B');
        $adminB = $this->user($c['institute'], 'institute-admin', 'fr-admin-b', $branchB);

        $mid = $this->assessment($c, 'Mid Term 2026', $branchA);
        $final = $this->assessment($c, 'Final Term 2026', $branchA);
        $scheme = $this->scheme($c, 'Branch A Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ], $branchA);

        TenantContext::set($c['institute']->id);
        BranchContext::set($branchB->id);

        $this->actingAs($adminB, 'institute_user')
            ->get(route('settings.academic.final-results.readiness', $scheme))
            ->assertStatus(404);

        $this->actingAs($adminB, 'institute_user')
            ->get(route('settings.academic.final-results.readiness.export', $scheme))
            ->assertStatus(404);
    }

    public function test_missing_marks_in_one_assessment_marks_scheme_not_ready(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');

        $complete = $this->placement($c, $this->student($c['institute'], 'Complete'));
        $this->enterAll($complete, $mid);
        $this->enterAll($complete, $final);

        $missing = $this->placement($c, $this->student($c['institute'], 'Missing'));
        $this->enterAll($missing, $mid);
        // Final Term: only English entered, Mathematics entirely unrecorded.
        $english = $final->subjects()->where('subject_id', $c['english']->id)->firstOrFail();
        $this->mark($missing, $english, 70);

        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);

        $data = $this->finalReadiness($c['institute'], $scheme);
        $this->assertFalse($data['is_ready']);
        $this->assertSame('not_ready', $data['readiness']);
        $this->assertSame(1, $data['summary']['ready_assessments']);
        $this->assertSame(1, $data['summary']['not_ready_assessments']);
        $this->assertSame(2, $data['summary']['eligible_students']);
        $this->assertSame(1, $data['summary']['complete']);
        $this->assertSame(1, $data['summary']['missing']);

        $this->assertSame(
            'Missing marks in Final Term 2026 (Mathematics).',
            $data['exceptions'][0]['reason']
        );
        $this->assertSame(['Mathematics'], $data['exceptions'][0]['missing_subjects']);
        $this->assertSame(['Final Term 2026'], $data['exceptions'][0]['missing_assessments']);

        $notReadyReason = collect($data['reasons'])->first(fn (string $reason) => str_contains($reason, 'not ready'));
        $this->assertNotNull($notReadyReason);
    }

    public function test_absent_is_not_treated_as_missing_the_scheme_is_ready_with_exceptions(): void
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

        $complete = $this->placement($c, $this->student($c['institute'], 'Complete'));
        $this->enterAll($complete, $mid);
        $this->enterAll($complete, $final);

        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);

        $data = $this->finalReadiness($c['institute'], $scheme);
        $this->assertSame('ready_with_exceptions', $data['readiness']);
        $this->assertFalse($data['is_ready']);
        $this->assertSame(1, $data['summary']['absent']);
        $this->assertSame(1, $data['summary']['complete']);
        $this->assertSame(0, $data['summary']['missing']);

        $this->assertSame(['Final Term 2026'], $data['exceptions'][0]['absent_assessments']);

        $absentReason = collect($data['reasons'])->first(fn (string $reason) => str_contains($reason, 'legitimately absent'));
        $this->assertNotNull($absentReason);
    }

    public function test_incomplete_missing_and_no_assessment_are_distinguished_across_assessments(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $midMath = $mid->subjects()->where('subject_id', $c['math']->id)->firstOrFail();
        $midEnglish = $mid->subjects()->where('subject_id', $c['english']->id)->firstOrFail();
        $finalMath = $final->subjects()->where('subject_id', $c['math']->id)->firstOrFail();
        $finalEnglish = $final->subjects()->where('subject_id', $c['english']->id)->firstOrFail();

        $partial = $this->placement($c, $this->student($c['institute'], 'Partial'));
        $this->enterAll($partial, $mid); // then override to make the mid assessment partial
        AcademicStudentMark::where('academic_placement_id', $partial->id)->delete();
        $this->mark($partial, $midMath, 40, 'entered', 0); // mcq unrecorded → mathematics incomplete
        $this->mark($partial, $midEnglish, 60); // english complete, so the mid assessment is INCOMPLETE overall
        $this->mark($partial, $finalMath, 80);
        $this->mark($partial, $finalMath, 20, 'entered', 1);
        $this->mark($partial, $finalEnglish, 80);

        $missing = $this->placement($c, $this->student($c['institute'], 'Missing'));
        $this->enterAll($missing, $mid);
        $this->mark($missing, $finalEnglish, 70); // math in final unrecorded

        $noRecord = $this->placement($c, $this->student($c['institute'], 'NoRecord'));
        $this->enterAll($noRecord, $mid);
        // zero marks at all in the final assessment.

        $complete = $this->placement($c, $this->student($c['institute'], 'Complete'));
        $this->enterAll($complete, $mid);
        $this->enterAll($complete, $final);

        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);

        $data = $this->finalReadiness($c['institute'], $scheme);
        $this->assertSame('not_ready', $data['readiness']);

        $statusById = [];
        $countsById = [];
        foreach ($data['students'] as $row) {
            $statusById[$row['student']->id] = $row['status'];
            $countsById[$row['student']->id] = [
                'no_assessment' => $row['no_assessment'],
                'missing' => $row['missing'],
                'incomplete' => $row['incomplete'],
                'absent' => $row['absent'],
            ];
        }

        $this->assertSame('incomplete', $statusById[$partial->student_id]);
        $this->assertSame('missing', $statusById[$missing->student_id]);
        $this->assertSame('no_assessment', $statusById[$noRecord->student_id]);
        $this->assertSame('complete', $statusById[$complete->student_id]);

        $this->assertSame(1, $countsById[$partial->student_id]['incomplete']);
        $this->assertSame(1, $countsById[$missing->student_id]['missing']);
        $this->assertSame(1, $countsById[$noRecord->student_id]['no_assessment']);

        $this->assertSame(1, $data['summary']['incomplete']);
        $this->assertSame(1, $data['summary']['missing']);
        $this->assertSame(1, $data['summary']['no_assessment']);
        $this->assertSame(1, $data['summary']['complete']);
    }

    public function test_scheme_without_required_assessments_is_not_ready(): void
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

        $data = $this->finalReadiness($c['institute'], $scheme);
        $this->assertSame('not_ready', $data['readiness']);
        $this->assertFalse($data['is_ready']);
        $this->assertSame(0, $data['summary']['required_assessments']);
        $this->assertTrue(collect($data['reasons'])->contains(
            fn (string $reason) => str_contains($reason, 'No required assessments')
        ));
    }

    public function test_invalid_weight_total_marks_scheme_not_ready(): void
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

        $data = $this->finalReadiness($c['institute'], $scheme);
        $this->assertSame('not_ready', $data['readiness']);
        $this->assertFalse($data['is_ready']);
        $this->assertTrue(collect($data['reasons'])->contains(
            fn (string $reason) => str_contains($reason, '60%')
        ));
        $this->assertSame('not_ready', $data['readiness']);
    }

    public function test_readiness_does_not_mutate_any_database_records(): void
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

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.final-results.readiness', $scheme))
            ->assertOk();

        $this->assertDatabaseCount('academic_student_marks', 6);
        $this->assertDatabaseCount('student_academic_placements', 1);
        $this->assertDatabaseCount('academic_assessments', 2);
        $this->assertDatabaseCount('academic_final_result_policies', 0);
        $this->assertDatabaseCount('academic_final_result_students', 0);
        $this->assertDatabaseCount('academic_final_result_rows', 0);
        $this->assertDatabaseHas('academic_student_marks', ['obtained_mark' => 80.0]);
    }

    public function test_readiness_does_not_touch_locked_final_result_snapshots(): void
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

        TenantContext::set($c['institute']->id);
        $this->generationScale($c);

        $lifecycle = app(AcademicFinalResultLifecycleService::class);
        $policy = $lifecycle->policyForScheme($c['institute'], $scheme);
        $policy->update(['require_approval' => false]);
        $result = $lifecycle->createResult($c['institute'], $policy, 'Annual Final 2026', (int) $c['owner']->id);
        $lifecycle->lock($result, (int) $c['owner']->id);

        $studentRows = AcademicFinalResultStudent::where('result_id', $result->id)->count();
        $rowRows = AcademicFinalResultRow::where('result_id', $result->id)->count();
        $gpa = AcademicFinalResultStudent::where('result_id', $result->id)->firstOrFail()->gpa;

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.final-results.readiness', $scheme))
            ->assertOk();

        $this->assertSame($studentRows, AcademicFinalResultStudent::where('result_id', $result->id)->count());
        $this->assertSame($rowRows, AcademicFinalResultRow::where('result_id', $result->id)->count());
        $this->assertSame($gpa, AcademicFinalResultStudent::where('result_id', $result->id)->firstOrFail()->gpa);
        $this->assertSame($result->status, $result->refresh()->status);
    }

    public function test_readiness_is_bulk_loaded_without_n_plus_1(): void
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

        DB::enableQueryLog();
        app(AcademicFinalResultReadinessService::class)->forScheme($scheme);

        $marksSelects = collect(DB::getQueryLog())
            ->filter(fn ($query) => str_contains($query['query'], 'academic_student_marks') && str_starts_with(ltrim($query['query']), 'select'))
            ->count();

        $placementSelects = collect(DB::getQueryLog())
            ->filter(fn ($query) => str_contains($query['query'], 'student_academic_placements') && str_starts_with(ltrim($query['query']), 'select'))
            ->count();

        // One bulk marks load per required assessment; placements are bulk
        // loaded once per assessment + once for the scheme scope (never per
        // student).
        $this->assertSame(2, $marksSelects);
        $this->assertSame(3, $placementSelects);
    }

    public function test_readiness_does_not_create_a_missing_policy(): void
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

        $this->assertDatabaseCount('academic_final_result_policies', 0);

        $data = $this->finalReadiness($c['institute'], $scheme);
        $this->assertNull($data['policy']);
        $this->assertDatabaseCount('academic_final_result_policies', 0);
    }

    public function test_csv_export_lists_only_exceptions_with_the_gate_columns(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');

        // Tricky name (comma + quote), Mathematics unrecorded in the final.
        $tricky = $this->student($c['institute'], 'Karim, "Tricky"');
        $trickyPlacement = $this->placement($c, $tricky);
        $this->enterAll($trickyPlacement, $mid);
        $finalEnglish = $final->subjects()->where('subject_id', $c['english']->id)->firstOrFail();
        $this->mark($trickyPlacement, $finalEnglish, 70);

        // Complete student must NOT appear in the exceptions export.
        $ok = $this->placement($c, $this->student($c['institute'], 'Fine'));
        $this->enterAll($ok, $mid);
        $this->enterAll($ok, $final);

        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);

        TenantContext::set($c['institute']->id);

        $response = $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.final-results.readiness.export', $scheme))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $all = $this->parseCsv($this->csvContent($response));
        $this->assertSame(
            ['Student', 'Student ID', 'Registration Number', 'Missing Assessment', 'Missing Subject', 'Incomplete Assessment', 'Absent Assessment', 'Readiness', 'Reason'],
            $all[0]
        );
        $this->assertCount(2, $all); // header + one exception row

        $row = $all[1];
        $this->assertSame('Karim, "Tricky" Student', $row[0]);
        $this->assertSame('Final Term 2026', $row[3]);
        $this->assertSame('Mathematics', $row[4]);
        $this->assertSame('', $row[5]);
        $this->assertSame('', $row[6]);
        $this->assertSame('Missing marks', $row[7]);
        $this->assertNotSame($ok->student->full_name, $row[0]);
    }
}
