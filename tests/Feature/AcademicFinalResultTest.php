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
use App\Models\AssessmentType;
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
use App\Services\AcademicFinalResultService;
use App\Services\AcademicGradingService;
use App\Services\AcademicResultAggregationService;
use App\Support\TenantContext;
use Database\Seeders\AcademicAssessmentSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AcademicFinalResultTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicAssessmentSeeder::class);
    }

    // ------------------------------------------------------------- Fixtures

    private function country(string $iso2 = 'BD'): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => $iso2],
            ['name' => 'Bangladesh', 'iso3' => strtoupper($iso2).'F', 'phone_code' => '880', 'status' => true]
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

    private function institute(Country $country): Institute
    {
        return Institute::create([
            'name' => 'FR Inst',
            'slug' => str()->slug('FR Inst-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);
    }

    private function owner(Institute $institute): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => null,
            'role_id' => Role::where('slug', 'institute-owner')->firstOrFail()->id,
            'first_name' => 'FR',
            'last_name' => 'Owner',
            'email' => 'fr-owner-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function student(Institute $institute): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'branch_id' => null,
            'student_id_number' => 'FR'.mt_rand(100000, 999999),
            'first_name' => 'Rahim',
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

    private function type(): AssessmentType
    {
        return AssessmentType::where('slug', 'mid-term')->firstOrFail();
    }

    private function curriculum(): array
    {
        $country = $this->country();
        $classGrade = $this->classGrade($this->level($this->system($country)));
        $institute = $this->institute($country);
        $math = $this->subject('Mathematics', 'FR100001');

        $this->assign($math, $classGrade, 'mandatory', 1);

        return [
            'country' => $country,
            'class_grade' => $classGrade,
            'group' => $this->group($classGrade),
            'institute' => $institute,
            'owner' => $this->owner($institute),
            'math' => $math,
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
            'assessment_type_id' => $this->type()->id,
            'name' => $name,
            'status' => 'scheduled',
            'display_order' => 1,
        ]);

        $subjectConfig = AssessmentSubject::create([
            'assessment_id' => $assessment->id,
            'subject_id' => $c['math']->id,
            'display_order' => 1,
            'status' => 'active',
        ]);

        AssessmentSubjectComponent::create([
            'assessment_subject_id' => $subjectConfig->id,
            'component_id' => $this->writtenComponent()->id,
            'full_mark' => 100,
            'pass_mark' => 33,
            'mandatory_pass' => false,
            'display_order' => 1,
            'status' => 'active',
        ]);

        return $assessment;
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

        StudentSubjectSelection::create([
            'institute_id' => $c['institute']->id,
            'academic_placement_id' => $placement->id,
            'subject_id' => $c['math']->id,
            'is_selected' => true,
            'is_mandatory' => false,
        ]);

        return $placement;
    }

    private function mark(StudentAcademicPlacement $placement, AssessmentSubject $config, float $obtained, string $status = 'entered'): AcademicStudentMark
    {
        $assessmentComponent = AssessmentSubjectComponent::where('assessment_subject_id', $config->id)->firstOrFail();

        return AcademicStudentMark::create([
            'institute_id' => $placement->institute_id,
            'academic_assessment_id' => $config->assessment_id,
            'assessment_subject_id' => $config->id,
            'assessment_component_id' => $assessmentComponent->id,
            'student_id' => $placement->student_id,
            'academic_placement_id' => $placement->id,
            'obtained_mark' => $status === 'absent' ? null : $obtained,
            'status' => $status,
        ]);
    }

    private function scheme(array $c, array $weights): AcademicResultAggregationScheme
    {
        return app(AcademicResultAggregationService::class)->store(
            $c['institute'],
            null,
            (int) $c['owner']->id,
            [
                'academic_year_id' => $this->defaultYear($c['institute'])->id,
                'class_grade_id' => $c['class_grade']->id,
                'academic_group_id' => $c['group']->id,
                'name' => 'Annual Result',
                'status' => 'active',
                'display_order' => 1,
            ],
            $weights
        );
    }

    private function instituteScale(Institute $institute, array $rows): GradeScale
    {
        return app(AcademicGradingService::class)->store([
            'name' => 'FR Scale',
            'institute_id' => $institute->id,
            'gpa_mode' => 'equal_weight',
            'optional_subject_gpa' => 'included',
            'status' => true,
            'display_order' => 1,
        ], $rows);
    }

    /**
     * Satisfies the Step-29 pre-flight gate (Step 30): every cycle start is
     * blocked unless a grade scale resolves for the scheme context.
     */
    private function generationScale(array $c): GradeScale
    {
        return $this->instituteScale($c['institute'], [
            ['grade' => 'A+', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.0, 'is_pass' => '1', 'gpa_included' => '1'],
            ['grade' => 'F', 'min_score' => 0, 'max_score' => 79.99, 'grade_point' => 0.0, 'is_pass' => '0', 'gpa_included' => '0'],
        ]);
    }

    private function openPolicy(array $c, AcademicResultAggregationScheme $scheme): AcademicFinalResultPolicy
    {
        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.final-results.policy', $scheme))
            ->assertOk();

        return AcademicFinalResultPolicy::query()->where('scheme_id', $scheme->id)->firstOrFail();
    }

    private function startCycle(array $c, AcademicFinalResultPolicy $policy, string $name = 'Term Final 2026'): AcademicFinalResult
    {
        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.final-results.store', $policy), ['name' => $name])
            ->assertRedirect();

        return AcademicFinalResult::query()->where('policy_id', $policy->id)->orderByDesc('id')->firstOrFail();
    }

    private function act(array $c, string $method, string $routeName, mixed $routeParams, array $data = [])
    {
        TenantContext::set($c['institute']->id);

        return $this->actingAs($c['owner'], 'institute_user')
            ->{$method}(route($routeName, $routeParams), $data);
    }

    // ------------------------------------------------------------- Tests

    public function test_policy_is_auto_created_for_a_scheme_with_defaults(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');
        $scheme = $this->scheme($c, [
            ['assessment_id' => $mid->id, 'weight' => 50],
            ['assessment_id' => $final->id, 'weight' => 50],
        ]);

        $policy = $this->openPolicy($c, $scheme);

        $this->assertSame('active', $policy->status);
        $this->assertTrue($policy->absent_renormalization);
        $this->assertTrue($policy->require_approval);
        $this->assertNull($policy->grade_scale_id);
        $this->assertSame($c['institute']->id, $policy->institute_id);
    }

    public function test_policy_update_applies_knobs_and_institute_scale_override(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');
        $scheme = $this->scheme($c, [
            ['assessment_id' => $mid->id, 'weight' => 50],
            ['assessment_id' => $final->id, 'weight' => 50],
        ]);
        $policy = $this->openPolicy($c, $scheme);

        $scale = $this->instituteScale($c['institute'], [
            ['grade' => 'A+', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.0, 'is_pass' => '1', 'gpa_included' => '1'],
            ['grade' => 'F', 'min_score' => 0, 'max_score' => 79.99, 'grade_point' => 0.0, 'is_pass' => '0', 'gpa_included' => '0'],
        ]);

        $this->act($c, 'put', 'settings.academic.final-results.policy.update', $policy, [
            'name' => 'FR Policy v2',
            'absent_renormalization' => '0',
            'require_approval' => '0',
            'grade_scale_id' => $scale->id,
        ])->assertRedirect();

        $policy->refresh();

        $this->assertSame('FR Policy v2', $policy->name);
        $this->assertFalse($policy->absent_renormalization);
        $this->assertFalse($policy->require_approval);
        $this->assertSame($scale->id, $policy->grade_scale_id);
    }

    public function test_policy_rejects_foreign_or_inactive_scale_override(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');
        $scheme = $this->scheme($c, [
            ['assessment_id' => $mid->id, 'weight' => 50],
            ['assessment_id' => $final->id, 'weight' => 50],
        ]);
        $policy = $this->openPolicy($c, $scheme);

        $other = $this->institute($this->country());
        $foreignScale = $this->instituteScale($other, [
            ['grade' => 'A', 'min_score' => 0, 'max_score' => 100, 'grade_point' => 4.0, 'is_pass' => '1', 'gpa_included' => '1'],
        ]);

        $this->act($c, 'put', 'settings.academic.final-results.policy.update', $policy, [
            'name' => 'Nope',
            'grade_scale_id' => $foreignScale->id,
        ])->assertStatus(422);

        $active = $this->instituteScale($c['institute'], [
            ['grade' => 'A', 'min_score' => 0, 'max_score' => 100, 'grade_point' => 4.0, 'is_pass' => '1', 'gpa_included' => '1'],
        ]);
        $active->update(['status' => false]);

        $this->act($c, 'put', 'settings.academic.final-results.policy.update', $policy, [
            'name' => 'Nope',
            'grade_scale_id' => $active->id,
        ])->assertStatus(422);

        $policy->refresh();
        $this->assertNull($policy->grade_scale_id);
    }

    public function test_cycle_lifecycle_review_approve_lock_publish_with_snapshot(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');
        $scheme = $this->scheme($c, [
            ['assessment_id' => $mid->id, 'weight' => 50],
            ['assessment_id' => $final->id, 'weight' => 50],
        ]);
        $policy = $this->openPolicy($c, $scheme);

        $scale = $this->instituteScale($c['institute'], [
            ['grade' => 'A+', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.0, 'is_pass' => '1', 'gpa_included' => '1'],
            ['grade' => 'F', 'min_score' => 0, 'max_score' => 79.99, 'grade_point' => 0.0, 'is_pass' => '0', 'gpa_included' => '0'],
        ]);
        $this->act($c, 'put', 'settings.academic.final-results.policy.update', $policy, [
            'name' => $policy->name,
            'absent_renormalization' => '1',
            'require_approval' => '1',
            'grade_scale_id' => $scale->id,
        ])->assertRedirect();

        $stud = $this->student($c['institute']);
        $placement = $this->placement($c, $stud);
        $this->mark($placement, $mid->subjects->first(), 80);
        $this->mark($placement, $final->subjects->first(), 90);

        $result = $this->startCycle($c, $policy);

        $this->assertSame('review', $result->status);
        $this->assertNull($result->approved_at);

        // Live preview renders and shows the derived aggregate under the override scale.
        $this->act($c, 'get', 'settings.academic.final-results.show', $result)
            ->assertOk()
            ->assertSee('85');

        // approve → lock → publish
        $this->act($c, 'post', 'settings.academic.final-results.approve', $result)->assertRedirect();
        $this->assertSame('approved', $result->refresh()->status);
        $this->assertNotNull($result->approved_at);

        $this->act($c, 'post', 'settings.academic.final-results.lock', $result)->assertRedirect();
        $this->assertSame('locked', $result->refresh()->status);
        $this->assertNotNull($result->locked_at);
        $this->assertNotNull($result->computed_at);

        // Snapshot frozen: one graded row + one GPA row.
        $row = AcademicFinalResultRow::query()->where('result_id', $result->id)->firstOrFail();
        $this->assertSame(85.0, $row->aggregate);
        $this->assertSame('A+', $row->grade);
        $this->assertSame('PASS', $row->subject_status);
        $this->assertTrue((bool) $row->gpa_included);

        $gpaRow = AcademicFinalResultStudent::query()->where('result_id', $result->id)->firstOrFail();
        $this->assertSame(5.0, $gpaRow->gpa);
        $this->assertSame('computed', $gpaRow->gpa_status);

        $this->act($c, 'post', 'settings.academic.final-results.publish', $result)->assertRedirect();
        $this->assertSame('published', $result->refresh()->status);
        $this->assertNotNull($result->published_at);

        // Snapshot render still 200.
        $this->act($c, 'get', 'settings.academic.final-results.show', $result)->assertOk();
    }

    public function test_only_one_inflight_cycle_per_policy(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');
        $scheme = $this->scheme($c, [
            ['assessment_id' => $mid->id, 'weight' => 50],
            ['assessment_id' => $final->id, 'weight' => 50],
        ]);
        $policy = $this->openPolicy($c, $scheme);

        $this->generationScale($c);
        $this->placement($c, $this->student($c['institute']));

        $this->startCycle($c, $policy, 'First');

        $this->act($c, 'post', 'settings.academic.final-results.store', $policy, ['name' => 'Second'])
            ->assertStatus(422);
    }

    public function test_lock_requires_approval_by_default(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');
        $scheme = $this->scheme($c, [
            ['assessment_id' => $mid->id, 'weight' => 50],
            ['assessment_id' => $final->id, 'weight' => 50],
        ]);
        $policy = $this->openPolicy($c, $scheme);

        $this->generationScale($c);
        $this->placement($c, $this->student($c['institute']));

        $result = $this->startCycle($c, $policy);

        $this->act($c, 'post', 'settings.academic.final-results.lock', $result)->assertStatus(422);
        $this->assertSame('review', $result->refresh()->status);
    }

    public function test_lock_allowed_from_review_when_approval_disabled(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');
        $scheme = $this->scheme($c, [
            ['assessment_id' => $mid->id, 'weight' => 50],
            ['assessment_id' => $final->id, 'weight' => 50],
        ]);
        $policy = $this->openPolicy($c, $scheme);

        $this->act($c, 'put', 'settings.academic.final-results.policy.update', $policy, [
            'name' => $policy->name,
            'require_approval' => '0',
        ])->assertRedirect();

        $this->generationScale($c);
        $this->placement($c, $this->student($c['institute']));

        $result = $this->startCycle($c, $policy);
        $this->act($c, 'post', 'settings.academic.final-results.lock', $result)->assertRedirect();
        $this->assertSame('locked', $result->refresh()->status);
    }

    public function test_send_back_to_review_resets_approval(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');
        $scheme = $this->scheme($c, [
            ['assessment_id' => $mid->id, 'weight' => 50],
            ['assessment_id' => $final->id, 'weight' => 50],
        ]);
        $policy = $this->openPolicy($c, $scheme);

        $this->generationScale($c);
        $this->placement($c, $this->student($c['institute']));

        $result = $this->startCycle($c, $policy);

        $this->act($c, 'post', 'settings.academic.final-results.approve', $result)->assertRedirect();
        $this->assertSame('approved', $result->refresh()->status);

        $this->act($c, 'post', 'settings.academic.final-results.send-to-review', $result)->assertRedirect();
        $result->refresh();
        $this->assertSame('review', $result->status);
        $this->assertNull($result->approved_by);
        $this->assertNull($result->approved_at);
    }

    public function test_marks_are_frozen_after_lock(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');
        $scheme = $this->scheme($c, [
            ['assessment_id' => $mid->id, 'weight' => 50],
            ['assessment_id' => $final->id, 'weight' => 50],
        ]);
        $policy = $this->openPolicy($c, $scheme);

        $stud = $this->student($c['institute']);
        $placement = $this->placement($c, $stud);
        $midConfig = $mid->subjects->first();
        $this->mark($placement, $midConfig, 80);
        $this->mark($placement, $final->subjects->first(), 90);

        $this->generationScale($c);

        $result = $this->startCycle($c, $policy);
        $this->act($c, 'post', 'settings.academic.final-results.approve', $result)->assertRedirect();
        $this->act($c, 'post', 'settings.academic.final-results.lock', $result)->assertRedirect();

        $component = $midConfig->components->first();
        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.assessments.marks.store', [$mid, $midConfig]), [
                'rows' => [
                    $placement->id => ['status' => 'entered', 'marks' => [$component->id => '95']],
                ],
            ])
            ->assertStatus(422);

        // The frozen snapshot is untouched.
        $row = AcademicFinalResultRow::query()->where('result_id', $result->id)->firstOrFail();
        $this->assertSame(85.0, $row->aggregate);
    }

    public function test_absent_renormalization_flag_changes_the_derived_aggregate(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');
        $scheme = $this->scheme($c, [
            ['assessment_id' => $mid->id, 'weight' => 50],
            ['assessment_id' => $final->id, 'weight' => 50],
        ]);
        $policy = $this->openPolicy($c, $scheme);

        $stud = $this->student($c['institute']);
        $placement = $this->placement($c, $stud);
        $this->mark($placement, $mid->subjects->first(), 100);
        $this->mark($placement, $final->subjects->first(), 100, 'absent');

        // Renormalize ON (default): the entered 50% is re-scaled to 100% → 100.
        $this->act($c, 'put', 'settings.academic.final-results.policy.update', $policy, [
            'name' => $policy->name,
            'absent_renormalization' => '1',
        ])->assertRedirect();

        $this->generationScale($c);

        $result = $this->startCycle($c, $policy);
        $preview = app(AcademicFinalResultLifecycleService::class)->preview($result);
        $subjectRow = $preview['rows'][0]['subjects'][0]['result'];
        $this->assertSame(100.0, $subjectRow['aggregate']);

        // Renormalize OFF: configured weight kept → 50.
        $this->act($c, 'put', 'settings.academic.final-results.policy.update', $policy, [
            'name' => $policy->name,
            'absent_renormalization' => '0',
        ])->assertRedirect();
        $result->refresh();
        $preview = app(AcademicFinalResultLifecycleService::class)->preview($result);
        $subjectRow = $preview['rows'][0]['subjects'][0]['result'];
        $this->assertSame(50.0, $subjectRow['aggregate']);
    }

    public function test_grade_scale_override_feeds_gpa_when_ladder_misses_it(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');
        $scheme = $this->scheme($c, [
            ['assessment_id' => $mid->id, 'weight' => 50],
            ['assessment_id' => $final->id, 'weight' => 50],
        ]);

        $stud = $this->student($c['institute']);
        $placement = $this->placement($c, $stud);
        $this->mark($placement, $mid->subjects->first(), 80);
        $this->mark($placement, $final->subjects->first(), 90);

        // Override scale scoped to a level this class is NOT on: the ladder can
        // never resolve it, only the explicit policy override can.
        $otherLevel = AcademicLevel::withoutGlobalScopes()->create([
            'country_id' => $c['class_grade']->country_id,
            'education_system_id' => $c['class_grade']->education_system_id,
            'code' => 'fr-other-'.uniqid(),
            'name' => 'Other Level',
            'display_order' => 99,
            'status' => true,
        ]);

        $scale = app(AcademicGradingService::class)->store([
            'name' => 'FR Elsewhere',
            'institute_id' => $c['institute']->id,
            'academic_level_id' => $otherLevel->id,
            'gpa_mode' => 'equal_weight',
            'optional_subject_gpa' => 'included',
            'status' => true,
            'display_order' => 99,
        ], [
            ['grade' => 'A+', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.0, 'is_pass' => '1', 'gpa_included' => '1'],
            ['grade' => 'F', 'min_score' => 0, 'max_score' => 79.99, 'grade_point' => 0.0, 'is_pass' => '0', 'gpa_included' => '0'],
        ]);

        $service = app(AcademicFinalResultService::class);

        // Ladder alone resolves nothing for the class → GPA unavailable.
        $this->assertSame('unavailable', $service->gpa($scheme, $placement, true, null)['status']);

        // The policy override must drive BOTH grades and GPA.
        $override = $service->gpa($scheme, $placement, true, $scale);
        $this->assertSame('computed', $override['status']);
        $this->assertSame(5.0, $override['value']);
    }

    public function test_publish_is_terminal_and_a_new_cycle_can_start_after(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');
        $scheme = $this->scheme($c, [
            ['assessment_id' => $mid->id, 'weight' => 50],
            ['assessment_id' => $final->id, 'weight' => 50],
        ]);
        $policy = $this->openPolicy($c, $scheme);

        $stud = $this->student($c['institute']);
        $placement = $this->placement($c, $stud);
        $this->mark($placement, $mid->subjects->first(), 70);
        $this->mark($placement, $final->subjects->first(), 70);

        $this->generationScale($c);

        $first = $this->startCycle($c, $policy, 'First Cycle');
        $this->act($c, 'post', 'settings.academic.final-results.approve', $first)->assertRedirect();
        $this->act($c, 'post', 'settings.academic.final-results.lock', $first)->assertRedirect();
        $this->act($c, 'post', 'settings.academic.final-results.publish', $first)->assertRedirect();

        // Publish is terminal: no more transitions.
        $this->act($c, 'post', 'settings.academic.final-results.publish', $first)->assertStatus(422);
        $this->act($c, 'post', 'settings.academic.final-results.approve', $first)->assertStatus(422);

        // A new cycle may now begin.
        $second = $this->startCycle($c, $policy, 'Second Cycle');
        $this->assertSame('review', $second->status);
        $this->assertNotSame($first->id, $second->id);
    }
}
