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
use App\Services\AcademicFinalResultPreflightService;
use App\Services\AcademicGradingService;
use App\Services\AcademicResultAggregationService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Database\Seeders\AcademicAssessmentSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * Step 30 — complete the generation → lock → publish pipeline with pre-flight
 * enforcement.
 *
 * The Step-29 pre-flight gate is now the single mandatory entry point for every
 * new publish cycle: a scheme that is blocked (missing grading configuration,
 * no eligible students, ...) can never be generated. This suite verifies the
 * enforcement at the generation boundary, that the policy page mirrors the
 * gate, and that the full approve → lock → publish lifecycle still holds for a
 * scheme that passes the gate (frozen snapshot, marks immutability, publish
 * terminality).
 */
class AcademicFinalResultGenerationTest extends TestCase
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

    private function classGrade(AcademicLevel $level, string $code = 'gn-c8'): ClassGrade
    {
        return ClassGrade::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $level->country_id, 'education_system_id' => $level->education_system_id, 'academic_level_id' => $level->id, 'code' => $code],
            ['name' => 'Class 8', 'display_order' => 0, 'status' => true]
        );
    }

    private function group(ClassGrade $classGrade, string $code = 'gn-sci'): AcademicGroup
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

    private function institute(Country $country, string $name = 'GN Inst'): Institute
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
            'first_name' => 'GN',
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
            'student_id_number' => 'GN'.mt_rand(100000, 999999),
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
        $math = $this->subject('Mathematics', 'GN100001');
        $english = $this->subject('English', 'GN100002');

        $this->assign($math, $classGrade, 'mandatory', 1);
        $this->assign($english, $classGrade, 'mandatory', 2);

        return [
            'country' => $country,
            'class_grade' => $classGrade,
            'group' => $this->group($classGrade),
            'institute' => $institute,
            'owner' => $this->user($institute, 'institute-owner', 'gn-owner'),
            'math' => $math,
            'english' => $english,
        ];
    }

    private function assessment(array $c, string $name): AcademicAssessment
    {
        $assessment = AcademicAssessment::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
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
    private function scheme(array $c, string $name, array $items): AcademicResultAggregationScheme
    {
        return app(AcademicResultAggregationService::class)->store(
            $c['institute'],
            null,
            (int) $c['owner']->id,
            [
                'academic_year_id' => $this->defaultYear($c['institute'])->id,
                'class_grade_id' => $c['class_grade']->id,
                'academic_group_id' => $c['group']->id,
                'name' => $name,
                'status' => 'active',
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

    /** Institute-level scale at the class's academic level. */
    private function instituteScale(array $c): GradeScale
    {
        return app(AcademicGradingService::class)->store([
            'name' => 'Generation Scale',
            'institute_id' => (int) $c['institute']->id,
            'academic_level_id' => (int) $c['class_grade']->academic_level_id,
            'gpa_mode' => 'equal_weight',
            'optional_subject_gpa' => 'included',
            'display_order' => 1,
            'status' => true,
        ], $this->gradeRows());
    }

    private function lifecycle(): AcademicFinalResultLifecycleService
    {
        return app(AcademicFinalResultLifecycleService::class);
    }

    private function policy(array $c, AcademicResultAggregationScheme $scheme): AcademicFinalResultPolicy
    {
        return $this->lifecycle()->policyForScheme($c['institute'], $scheme);
    }

    private function preflight(AcademicResultAggregationScheme $scheme): array
    {
        return app(AcademicFinalResultPreflightService::class)->preflight($scheme);
    }

    /**
     * Full valid scope: two assessments + complete placement + policy + scale.
     */
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

    /** HTTP helpers mirror AcademicFinalResultTest. */
    private function startCycle(array $c, AcademicFinalResultPolicy $policy, string $name = 'Annual Final 2026'): AcademicFinalResult
    {
        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.final-results.store', $policy), ['name' => $name])
            ->assertRedirect();

        return AcademicFinalResult::query()->where('policy_id', $policy->id)->orderByDesc('id')->firstOrFail();
    }

    private function act(array $c, string $method, string $routeName, mixed $routeParams, array $data = [])
    {
        return $this->actingAs($c['owner'], 'institute_user')
            ->{$method}(route($routeName, $routeParams), $data);
    }

    // -------------------------------------------------------------- Gate

    public function test_generation_is_blocked_when_scheme_has_no_grade_scale(): void
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
        $policy = $this->policy($c, $scheme);

        $report = $this->preflight($scheme);
        $this->assertFalse($report['verdict']['allowed']);

        $response = $this->act($c, 'post', 'settings.academic.final-results.store', $policy, ['name' => 'Annual Final 2026']);
        $response->assertStatus(422);
        $response->assertSee('Final-result generation is blocked by the pre-flight gate');
        $response->assertSee('No grade scale resolves');

        $this->assertDatabaseCount('academic_final_results', 0);
    }

    public function test_generation_is_blocked_when_no_student_is_eligible(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);
        $policy = $this->policy($c, $scheme);
        $this->instituteScale($c);

        $report = $this->preflight($scheme);
        $this->assertFalse($report['verdict']['allowed']);

        $response = $this->act($c, 'post', 'settings.academic.final-results.store', $policy, ['name' => 'Annual Final 2026']);
        $response->assertStatus(422);
        $response->assertSee('Final-result generation is blocked by the pre-flight gate');
        $response->assertSee('No students are placed');

        $this->assertDatabaseCount('academic_final_results', 0);
    }

    public function test_blocked_generation_lists_every_blocking_reason(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);
        $policy = $this->policy($c, $scheme);
        // No scale AND no placement.

        $report = $this->preflight($scheme);
        $blocking = $report['verdict']['blocking'];
        $this->assertGreaterThanOrEqual(2, count($blocking));

        $response = $this->act($c, 'post', 'settings.academic.final-results.store', $policy, ['name' => 'Annual Final 2026']);
        $response->assertStatus(422);

        foreach ($blocking as $reason) {
            $response->assertSee(mb_substr($reason, 0, 60));
        }

        $this->assertDatabaseCount('academic_final_results', 0);
    }

    public function test_generation_succeeds_when_the_preflight_gate_passes(): void
    {
        $fx = $this->readyFixture();

        $report = $this->preflight($fx['scheme']);
        $this->assertTrue($report['verdict']['allowed']);
        $this->assertSame([], $report['verdict']['blocking']);

        $result = $this->startCycle($fx['c'], $fx['policy']);

        $this->assertSame('review', $result->status);
        $this->assertSame('Annual Final 2026', $result->name);
        $this->assertSame($fx['scheme']->id, $result->scheme_id);
        $this->assertSame(1, AcademicFinalResult::where('policy_id', $fx['policy']->id)->count());
    }

    public function test_gate_message_matches_the_preflight_report_exactly(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $this->placement($c, $this->student($c['institute']));
        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);
        $policy = $this->policy($c, $scheme);
        // Placement exists but no scale → exactly one blocking reason.

        $blocking = $this->preflight($scheme)['verdict']['blocking'];
        $this->assertCount(1, $blocking);

        $response = $this->act($c, 'post', 'settings.academic.final-results.store', $policy, ['name' => 'Annual Final 2026']);
        $response->assertStatus(422);
        $response->assertSee($blocking[0]);
    }

    // ------------------------------------------------------- Policy page

    public function test_policy_page_replaces_the_start_form_with_the_gate_alert_when_blocked(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);
        $this->policy($c, $scheme);
        // No scale, no placement → gate blocks.

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.final-results.policy', $scheme))
            ->assertOk()
            ->assertSee('Generation is blocked by the pre-flight gate')
            ->assertSee('View Pre-flight Report')
            ->assertSee(route('settings.academic.final-results.preflight', $scheme))
            ->assertDontSee('Start cycle');
    }

    public function test_policy_page_keeps_the_start_form_when_preflight_passes(): void
    {
        $fx = $this->readyFixture();

        $this->actingAs($fx['c']['owner'], 'institute_user')
            ->get(route('settings.academic.final-results.policy', $fx['scheme']))
            ->assertOk()
            ->assertDontSee('Generation is blocked by the pre-flight gate')
            ->assertSee('Start cycle');
    }

    public function test_policy_page_shows_continue_button_while_a_cycle_is_in_flight(): void
    {
        $fx = $this->readyFixture();
        $result = $this->startCycle($fx['c'], $fx['policy']);

        $this->actingAs($fx['c']['owner'], 'institute_user')
            ->get(route('settings.academic.final-results.policy', $fx['scheme']))
            ->assertOk()
            ->assertSee('Continue')
            ->assertSee($result->name)
            ->assertDontSee('Start cycle')
            ->assertDontSee('Generation is blocked by the pre-flight gate');
    }

    // ------------------------------------------------- Lifecycle guarantees

    public function test_approve_lock_publish_roundtrip_materializes_a_frozen_snapshot(): void
    {
        $fx = $this->readyFixture();
        $result = $this->startCycle($fx['c'], $fx['policy']);

        $this->act($fx['c'], 'post', 'settings.academic.final-results.approve', $result)->assertRedirect();
        $this->assertSame('approved', $result->refresh()->status);

        $this->act($fx['c'], 'post', 'settings.academic.final-results.lock', $result)->assertRedirect();
        $this->assertSame('locked', $result->refresh()->status);
        $this->assertSame(1, AcademicFinalResultStudent::where('result_id', $result->id)->count());
        $this->assertSame(2, AcademicFinalResultRow::where('result_id', $result->id)->count());

        $aggregate = AcademicFinalResultRow::where('result_id', $result->id)->firstOrFail()->aggregate;

        // Marks edited AFTER lock must not leak into the snapshot.
        $mark = AcademicStudentMark::where('academic_placement_id', $fx['placement']->id)->firstOrFail();
        $mark->update(['obtained_mark' => 1]);
        $this->assertSame($aggregate, AcademicFinalResultRow::where('result_id', $result->id)->firstOrFail()->aggregate);

        $this->act($fx['c'], 'post', 'settings.academic.final-results.publish', $result)->assertRedirect();
        $this->assertSame('published', $result->refresh()->status);
        $this->assertNotNull($result->published_at);
    }

    public function test_locked_result_rejects_further_marks_edits(): void
    {
        $fx = $this->readyFixture();
        $result = $this->startCycle($fx['c'], $fx['policy']);

        $this->act($fx['c'], 'post', 'settings.academic.final-results.approve', $result)->assertRedirect();
        $this->act($fx['c'], 'post', 'settings.academic.final-results.lock', $result)->assertRedirect();

        $blocked = null;
        try {
            $this->lifecycle()->assertAssessmentEditable($fx['c']['institute'], $fx['mid']);
        } catch (HttpResponseException|HttpException $e) {
            $blocked = $e;
        }

        $this->assertNotNull($blocked, 'assertAssessmentEditable should abort for an assessment in a locked result.');
    }

    public function test_publish_is_terminal_and_a_new_cycle_can_start(): void
    {
        $fx = $this->readyFixture();
        $first = $this->startCycle($fx['c'], $fx['policy']);

        $this->act($fx['c'], 'post', 'settings.academic.final-results.approve', $first)->assertRedirect();
        $this->act($fx['c'], 'post', 'settings.academic.final-results.lock', $first)->assertRedirect();
        $this->act($fx['c'], 'post', 'settings.academic.final-results.publish', $first)->assertRedirect();
        $this->assertSame('published', $first->refresh()->status);

        // A published result is terminal: the policy has no in-flight cycle, so
        // a brand-new cycle may be generated.
        $second = $this->startCycle($fx['c'], $fx['policy'], 'Second Cycle 2026');
        $this->assertSame('review', $second->status);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, AcademicFinalResult::where('policy_id', $fx['policy']->id)->count());

        $this->assertSame('published', $first->fresh()->status);
    }

    public function test_absences_are_warnings_not_blockers_for_generation(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term 2026');
        $final = $this->assessment($c, 'Final Term 2026');
        $placement = $this->placement($c, $this->student($c['institute']));
        $this->enterAll($placement, $mid);

        // The student is absent from the entire final assessment.
        foreach ($final->subjects()->with('components')->get() as $config) {
            $this->mark($placement, $config, 0, 'absent');
            if ($config->components->count() > 1) {
                $this->mark($placement, $config, 0, 'absent', 1);
            }
        }

        $scheme = $this->scheme($c, 'Annual Result', [
            ['assessment_id' => $mid->id, 'weight' => 40],
            ['assessment_id' => $final->id, 'weight' => 60],
        ]);
        $policy = $this->policy($c, $scheme);
        $this->instituteScale($c);

        $report = $this->preflight($scheme);
        $this->assertTrue($report['verdict']['allowed']);
        $this->assertSame(0, $report['verdict']['blocking_count']);
        $this->assertGreaterThan(0, $report['verdict']['warning_count']);

        $result = $this->startCycle($c, $policy);
        $this->assertSame('review', $result->status);
    }
}
