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
use App\Models\PromotionDecision;
use App\Models\PromotionDecisionItem;
use App\Models\PromotionPolicy;
use App\Models\PromotionPolicyRule;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\StudentSubjectSelection;
use App\Models\Subject;
use App\Models\SubjectAcademicAssignment;
use App\Services\AcademicGradingService;
use App\Services\AcademicResultAggregationService;
use App\Services\PromotionLifecycleService;
use App\Support\TenantContext;
use Database\Seeders\AcademicAssessmentSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AcademicPromotionTest extends TestCase
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
            ['name' => 'Bangladesh', 'iso3' => strtoupper($iso2).'P', 'phone_code' => '880', 'status' => true]
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

    private function classGrade(AcademicLevel $level, string $code, string $name, int $order): ClassGrade
    {
        return ClassGrade::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $level->country_id, 'education_system_id' => $level->education_system_id, 'academic_level_id' => $level->id, 'code' => $code],
            ['name' => $name, 'display_order' => $order, 'status' => true]
        );
    }

    private function group(ClassGrade $classGrade, string $code, string $name): AcademicGroup
    {
        return AcademicGroup::withoutGlobalScopes()->firstOrCreate(
            ['class_grade_id' => $classGrade->id, 'code' => $code],
            [
                'country_id' => $classGrade->country_id,
                'education_system_id' => $classGrade->education_system_id,
                'academic_level_id' => $classGrade->academic_level_id,
                'name' => $name,
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
            'name' => 'Promo Inst',
            'slug' => str()->slug('Promo Inst-'.uniqid()),
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
            'first_name' => 'PR',
            'last_name' => 'Owner',
            'email' => 'pr-owner-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function teacher(Institute $institute): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => null,
            'role_id' => Role::where('slug', 'teacher')->firstOrFail()->id,
            'first_name' => 'PR',
            'last_name' => 'Teacher',
            'email' => 'pr-teacher-'.uniqid().'@example.test',
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
            'student_id_number' => 'PR'.mt_rand(100000, 999999),
            'first_name' => 'Rahim',
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => now()->toDateString(),
        ]);
    }

    private function defaultYear(Institute $institute, string $code = '2026', bool $current = true): AcademicYear
    {
        return AcademicYear::withoutGlobalScopes()->firstOrCreate(
            ['institute_id' => $institute->id, 'code' => $code],
            ['name' => 'Academic Year '.$code, 'is_current' => $current, 'status' => true]
        );
    }

    private function writtenComponent(): Component
    {
        return Component::where('slug', 'written')->firstOrFail();
    }

    private function midTermType(): AssessmentType
    {
        return AssessmentType::where('slug', 'mid-term')->firstOrFail();
    }

    private function curriculum(string $iso2 = 'BD'): array
    {
        $country = $this->country($iso2);
        $system = $this->system($country);
        $level = $this->level($system);
        $class = $this->classGrade($level, 'fr-c8', 'Class 8', 0);
        $institute = $this->institute($country);

        $math = $this->subject('Mathematics', 'PR100001');
        $science = $this->subject('Science', 'PR100002');
        $this->assign($math, $class, 'mandatory', 1);
        $this->assign($science, $class, 'mandatory', 2);

        return [
            'country' => $country,
            'system' => $system,
            'level' => $level,
            'class' => $class,
            'group' => $this->group($class, 'fr-sci', 'Science'),
            'institute' => $institute,
            'owner' => $this->owner($institute),
            'math' => $math,
            'science' => $science,
        ];
    }

    private function assessment(array $c, string $name): AcademicAssessment
    {
        $assessment = AcademicAssessment::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class']->id,
            'academic_group_id' => $c['group']->id,
            'assessment_type_id' => $this->midTermType()->id,
            'name' => $name,
            'status' => 'scheduled',
            'display_order' => 1,
        ]);

        foreach ([$c['math'], $c['science']] as $subject) {
            $subjectConfig = AssessmentSubject::create([
                'assessment_id' => $assessment->id,
                'subject_id' => $subject->id,
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
        }

        return $assessment;
    }

    private function placement(array $c, Student $student): StudentAcademicPlacement
    {
        $placement = StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $student->id,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class']->id,
            'academic_group_id' => $c['group']->id,
            'status' => 'active',
        ]);

        foreach ([$c['math'], $c['science']] as $subject) {
            StudentSubjectSelection::create([
                'institute_id' => $c['institute']->id,
                'academic_placement_id' => $placement->id,
                'subject_id' => $subject->id,
                'is_selected' => true,
                'is_mandatory' => true,
            ]);
        }

        return $placement;
    }

    private function mark(array $c, StudentAcademicPlacement $placement, AcademicAssessment $assessment, Subject $subject, float $obtained): AcademicStudentMark
    {
        $config = AssessmentSubject::where('assessment_id', $assessment->id)->where('subject_id', $subject->id)->firstOrFail();
        $component = AssessmentSubjectComponent::where('assessment_subject_id', $config->id)->firstOrFail();

        return AcademicStudentMark::create([
            'institute_id' => $placement->institute_id,
            'academic_assessment_id' => $assessment->id,
            'assessment_subject_id' => $config->id,
            'assessment_component_id' => $component->id,
            'student_id' => $placement->student_id,
            'academic_placement_id' => $placement->id,
            'obtained_mark' => $obtained,
            'status' => 'entered',
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
                'class_grade_id' => $c['class']->id,
                'academic_group_id' => $c['group']->id,
                'name' => 'Annual Result',
                'status' => 'active',
                'display_order' => 1,
            ],
            $weights
        );
    }

    private function globalScale(): GradeScale
    {
        return app(AcademicGradingService::class)->store([
            'name' => 'Global Scale',
            'gpa_mode' => 'equal_weight',
            'optional_subject_gpa' => 'included',
            'status' => true,
            'display_order' => 1,
        ], [
            ['grade' => 'A+', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'A', 'min_score' => 70, 'max_score' => 79.99, 'grade_point' => 4.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'B', 'min_score' => 60, 'max_score' => 69.99, 'grade_point' => 3.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'C', 'min_score' => 50, 'max_score' => 59.99, 'grade_point' => 2.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'E', 'min_score' => 40, 'max_score' => 49.99, 'grade_point' => 1.0, 'is_pass' => false, 'gpa_included' => true],
            ['grade' => 'F', 'min_score' => 0, 'max_score' => 39.99, 'grade_point' => 0.0, 'is_pass' => false, 'gpa_included' => true],
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

    private function startCycle(array $c, AcademicFinalResultPolicy $policy): AcademicFinalResult
    {
        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.final-results.store', $policy), ['name' => 'Annual 2026'])
            ->assertRedirect();

        return AcademicFinalResult::query()->where('policy_id', $policy->id)->orderByDesc('id')->firstOrFail();
    }

    private function act(array $c, string $method, string $routeName, mixed $routeParams, array $data = [])
    {
        TenantContext::set($c['institute']->id);

        return $this->actingAs($c['owner'], 'institute_user')
            ->{$method}(route($routeName, $routeParams), $data);
    }

    /**
     * Full Step-10 pipeline to a PUBLISHED result. Assumes the global scale
     * exists so marks actually grade. When the caller has already entered
     * marks against its own Mid/Final assessments, those same assessments must
     * be passed in so the scheme covers them (marks and scheme must share the
     * assessment ids).
     */
    private function publishedResult(array $c, ?AcademicAssessment $mid = null, ?AcademicAssessment $final = null): AcademicFinalResult
    {
        $mid ??= $this->assessment($c, 'Mid Term');
        $final ??= $this->assessment($c, 'Final Term');
        $scheme = $this->scheme($c, [
            ['assessment_id' => $mid->id, 'weight' => 50],
            ['assessment_id' => $final->id, 'weight' => 50],
        ]);

        $policy = $this->openPolicy($c, $scheme);
        $result = $this->startCycle($c, $policy);

        $this->act($c, 'post', 'settings.academic.final-results.approve', $result);
        $this->act($c, 'post', 'settings.academic.final-results.lock', $result);
        $this->act($c, 'post', 'settings.academic.final-results.publish', $result);

        return $result->fresh();
    }

    private function policy(array $c, string $name = 'Class 8 Rules'): PromotionPolicy
    {
        return PromotionPolicy::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'name' => $name,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class']->id,
            'academic_group_id' => $c['group']->id,
            'status' => 'active',
            'created_by' => $c['owner']->id,
        ]);
    }

    private function rule(PromotionPolicy $policy, string $ruleType, ?string $operator, ?string $value, string $pass, string $fail, int $order = 0): PromotionPolicyRule
    {
        return PromotionPolicyRule::create([
            'policy_id' => $policy->id,
            'rule_type' => $ruleType,
            'field' => match ($ruleType) {
                PromotionPolicyRule::RULE_GPA_THRESHOLD => PromotionPolicyRule::FIELD_GPA,
                PromotionPolicyRule::RULE_CONDITIONAL => PromotionPolicyRule::FIELD_FAILED_COUNT,
                PromotionPolicyRule::RULE_MAX_FAILED_SUBJECTS => PromotionPolicyRule::FIELD_FAILED_COUNT,
                default => null,
            },
            'operator' => $operator,
            'value' => $value,
            'pass_action' => $pass,
            'fail_action' => $fail,
            'display_order' => $order,
            'status' => true,
        ]);
    }

    private function verdictFor(PromotionDecision $decision, Student $student): PromotionDecisionItem
    {
        return $decision->items()->where('student_id', $student->id)->firstOrFail();
    }

    // ------------------------------------------------------------- Policy + rules

    public function test_policy_store_validates_context_and_rules(): void
    {
        $c = $this->curriculum();
        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.promotions.policies.store'), [
                'name' => 'Class 8 Rules',
                'academic_year_id' => $this->defaultYear($c['institute'])->id,
                'class_grade_id' => $c['class']->id,
                'academic_group_id' => $c['group']->id,
                'status' => 'active',
            ])
            ->assertRedirect();

        $policy = PromotionPolicy::where('institute_id', $c['institute']->id)->firstOrFail();

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.promotions.rules.store', $policy), [
                'rule_type' => 'gpa_threshold',
                'field' => 'gpa',
                'operator' => '>=',
                'value' => '2.00',
                'pass_action' => 'promoted',
                'fail_action' => 'repeat',
                'display_order' => 1,
            ])
            ->assertRedirect();

        $rule = $policy->rules()->firstOrFail();
        $this->assertSame('gpa_threshold', $rule->rule_type);
        $this->assertSame('2.00', $rule->value);

        // Uncontrolled rule type rejected by validation.
        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.promotions.rules.store', $policy), [
                'rule_type' => 'everything_passes',
                'pass_action' => 'promoted',
                'fail_action' => 'repeat',
            ])
            ->assertSessionHasErrors('rule_type');

        // Non-numeric threshold rejected by the service.
        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.promotions.rules.store', $policy), [
                'rule_type' => 'gpa_threshold',
                'operator' => '>=',
                'value' => 'abc',
                'pass_action' => 'promoted',
                'fail_action' => 'repeat',
            ])
            ->assertStatus(422);
    }

    public function test_policy_rejects_cross_tenant_context_and_class(): void
    {
        $c = $this->curriculum();
        $other = $this->curriculum('IN');
        TenantContext::set($c['institute']->id);

        // Year from another institute.
        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.promotions.policies.store'), [
                'name' => 'Bad Year',
                'academic_year_id' => $this->defaultYear($other['institute'])->id,
                'class_grade_id' => $c['class']->id,
                'academic_group_id' => null,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('academic_year_id');

        // Class from another institute's structure.
        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.promotions.policies.store'), [
                'name' => 'Bad Class',
                'academic_year_id' => $this->defaultYear($c['institute'])->id,
                'class_grade_id' => $other['class']->id,
                'academic_group_id' => null,
                'status' => 'active',
            ])
            ->assertSessionHasErrors('class_grade_id');
    }

    public function test_forged_institute_id_is_ignored_on_policy_store(): void
    {
        $c = $this->curriculum();
        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.promotions.policies.store'), [
                'name' => 'Safe',
                'academic_year_id' => $this->defaultYear($c['institute'])->id,
                'class_grade_id' => $c['class']->id,
                'academic_group_id' => null,
                'status' => 'active',
                'institute_id' => 999999,
            ])
            ->assertRedirect();

        $this->assertSame($c['institute']->id, PromotionPolicy::firstOrFail()->institute_id);
    }

    public function test_owner_can_update_and_delete_own_policy_rule(): void
    {
        $c = $this->curriculum();
        $policy = $this->policy($c);
        $this->rule($policy, 'gpa_threshold', '>=', '2.00', 'promoted', 'repeat', 1);

        $rule = $policy->rules()->firstOrFail();

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->put(route('settings.academic.promotions.rules.update', $rule), [
                'rule_type' => 'gpa_threshold',
                'operator' => '>=',
                'value' => '3.50',
                'pass_action' => 'promoted',
                'fail_action' => 'conditional',
                'display_order' => 1,
            ])
            ->assertRedirect();

        $this->assertSame('3.50', $rule->fresh()->value);
        $this->assertSame('conditional', $rule->fresh()->fail_action);

        $this->actingAs($c['owner'], 'institute_user')
            ->delete(route('settings.academic.promotions.rules.destroy', $rule))
            ->assertRedirect();

        $this->assertDatabaseMissing('promotion_policy_rules', ['id' => $rule->id]);
    }

    public function test_cross_tenant_policy_rule_update_and_delete_is_blocked(): void
    {
        $c = $this->curriculum();
        $other = $this->curriculum();

        $foreignPolicy = $this->policy($other, 'Foreign Rules');
        $this->rule($foreignPolicy, 'gpa_threshold', '>=', '2.00', 'promoted', 'repeat', 1);
        $foreignRule = $foreignPolicy->rules()->firstOrFail();

        // Institute A actor (owner has promotion.manage) targets Institute B's rule.
        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->put(route('settings.academic.promotions.rules.update', $foreignRule), [
                'rule_type' => 'gpa_threshold',
                'operator' => '>=',
                'value' => '9.99',
                'pass_action' => 'promoted',
                'fail_action' => 'repeat',
                'display_order' => 1,
            ])
            ->assertStatus(404);

        $this->assertSame('2.00', $foreignRule->fresh()->value);
        $this->assertSame('promoted', $foreignRule->fresh()->pass_action);

        $this->actingAs($c['owner'], 'institute_user')
            ->delete(route('settings.academic.promotions.rules.destroy', $foreignRule))
            ->assertStatus(404);

        $this->assertDatabaseHas('promotion_policy_rules', ['id' => $foreignRule->id]);
    }

    public function test_teacher_without_permission_cannot_update_or_delete_rules(): void
    {
        $c = $this->curriculum();
        $policy = $this->policy($c);
        $this->rule($policy, 'gpa_threshold', '>=', '2.00', 'promoted', 'repeat', 1);

        $rule = $policy->rules()->firstOrFail();

        TenantContext::set($c['institute']->id);

        $this->actingAs($this->teacher($c['institute']), 'institute_user')
            ->put(route('settings.academic.promotions.rules.update', $rule), [
                'rule_type' => 'gpa_threshold',
                'operator' => '>=',
                'value' => '4.00',
                'pass_action' => 'promoted',
                'fail_action' => 'repeat',
                'display_order' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($this->teacher($c['institute']), 'institute_user')
            ->delete(route('settings.academic.promotions.rules.destroy', $rule))
            ->assertForbidden();

        $this->assertDatabaseHas('promotion_policy_rules', ['id' => $rule->id]);
        $this->assertSame('2.00', $rule->fresh()->value);
    }

    // ------------------------------------------------------------- Decision eligibility

    public function test_only_published_result_can_start_a_decision(): void
    {
        $c = $this->curriculum();
        $this->globalScale();
        $this->placement($c, $this->student($c['institute']));

        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');
        $scheme = $this->scheme($c, [
            ['assessment_id' => $mid->id, 'weight' => 50],
            ['assessment_id' => $final->id, 'weight' => 50],
        ]);
        $policy = $this->policy($c);
        $this->rule($policy, 'overall_pass', null, null, 'promoted', 'repeat', 1);

        // In-flight (review) result â†’ refused.
        $result = $this->startCycle($c, $this->openPolicy($c, $scheme));
        $resp = $this->act($c, 'post', 'settings.academic.promotions.decisions.store', $policy, ['result_id' => $result->id]);
        $resp->assertStatus(422);

        // Publish it â†’ decision allowed.
        $this->act($c, 'post', 'settings.academic.final-results.approve', $result);
        $this->act($c, 'post', 'settings.academic.final-results.lock', $result);
        $this->act($c, 'post', 'settings.academic.final-results.publish', $result);

        $this->act($c, 'post', 'settings.academic.promotions.decisions.store', $policy, ['result_id' => $result->id])
            ->assertRedirect();

        $this->assertSame(1, PromotionDecision::query()->count());
    }

    public function test_inflight_decision_prevents_duplicate_for_same_result(): void
    {
        $c = $this->curriculum();
        $this->globalScale();
        $this->placement($c, $this->student($c['institute']));
        $result = $this->publishedResult($c);

        $policy = $this->policy($c);
        $this->rule($policy, 'overall_pass', null, null, 'promoted', 'repeat', 1);

        $this->act($c, 'post', 'settings.academic.promotions.decisions.store', $policy, ['result_id' => $result->id])
            ->assertRedirect();

        $this->act($c, 'post', 'settings.academic.promotions.decisions.store', $policy, ['result_id' => $result->id])
            ->assertStatus(422);
    }

    public function test_policy_context_must_match_result_context(): void
    {
        $c = $this->curriculum();
        $this->globalScale();
        $this->placement($c, $this->student($c['institute']));
        $result = $this->publishedResult($c);

        // Policy bound to the same year but a DIFFERENT (unused) class.
        $otherClass = $this->classGrade($c['level'], 'pr-c10', 'Class 10', 9);
        $this->group($otherClass, 'pr-sci10', 'Science 10');

        $policy = PromotionPolicy::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'name' => 'Wrong Context',
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $otherClass->id,
            'academic_group_id' => null,
            'status' => 'active',
        ]);

        $this->act($c, 'post', 'settings.academic.promotions.decisions.store', $policy, ['result_id' => $result->id])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------- Evaluation

    public function test_overall_pass_and_gpa_threshold_verdicts(): void
    {
        $c = $this->curriculum();
        $this->globalScale();

        $passing = $this->placement($c, $this->student($c['institute']));
        $failing = $this->placement($c, $this->student($c['institute']));

        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');
        $this->mark($c, $passing, $mid, $c['math'], 90);
        $this->mark($c, $passing, $mid, $c['science'], 90);
        $this->mark($c, $passing, $final, $c['math'], 90);
        $this->mark($c, $passing, $final, $c['science'], 90);

        $this->mark($c, $failing, $mid, $c['math'], 30);
        $this->mark($c, $failing, $mid, $c['science'], 90);
        $this->mark($c, $failing, $final, $c['math'], 30);
        $this->mark($c, $failing, $final, $c['science'], 90);

        $result = $this->publishedResult($c, $mid, $final);
        $policy = $this->policy($c, 'Overall Pass');
        $this->rule($policy, 'overall_pass', null, null, 'promoted', 'repeat', 1);
        $this->act($c, 'post', 'settings.academic.promotions.decisions.store', $policy, ['result_id' => $result->id]);

        $decision = PromotionDecision::firstOrFail();
        $this->assertSame('promoted', $this->verdictFor($decision, $passing->student)->decision);
        $this->assertSame('repeat', $this->verdictFor($decision, $failing->student)->decision);

        // gpa_threshold: passing GPA 5.00 >= 4.0 â†’ promoted; failing GPA
        // (5.0 + 0.0)/2 = 2.5 â†’ repeat.
        $policy2 = $this->policy($c, 'GPA 4.0');
        $this->rule($policy2, 'gpa_threshold', '>=', '4.00', 'promoted', 'repeat', 1);
        $this->act($c, 'post', 'settings.academic.promotions.decisions.store', $policy2, ['result_id' => $result->id]);

        $decision2 = PromotionDecision::orderByDesc('id')->firstOrFail();
        $this->assertSame('promoted', $this->verdictFor($decision2, $passing->student)->decision);
        $this->assertSame('repeat', $this->verdictFor($decision2, $failing->student)->decision);
    }

    public function test_max_failed_subjects_and_conditional_verdicts(): void
    {
        $c = $this->curriculum();
        $this->globalScale();

        $clean = $this->placement($c, $this->student($c['institute']));
        $oneFailed = $this->placement($c, $this->student($c['institute']));
        $twoFailed = $this->placement($c, $this->student($c['institute']));

        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');

        $this->mark($c, $clean, $mid, $c['math'], 90);
        $this->mark($c, $clean, $mid, $c['science'], 90);
        $this->mark($c, $clean, $final, $c['math'], 90);
        $this->mark($c, $clean, $final, $c['science'], 90);

        $this->mark($c, $oneFailed, $mid, $c['math'], 90);
        $this->mark($c, $oneFailed, $mid, $c['science'], 30);
        $this->mark($c, $oneFailed, $final, $c['math'], 90);
        $this->mark($c, $oneFailed, $final, $c['science'], 30);

        $this->mark($c, $twoFailed, $mid, $c['math'], 30);
        $this->mark($c, $twoFailed, $mid, $c['science'], 30);
        $this->mark($c, $twoFailed, $final, $c['math'], 30);
        $this->mark($c, $twoFailed, $final, $c['science'], 30);

        $result = $this->publishedResult($c, $mid, $final);

        // 0 failed â†’ promoted | 1 failed â†’ conditional | 2+ â†’ repeat.
        // Two ordered rules; rule 2 passes to "promoted" so a student with 0
        // failures keeps the higher-ranked promoted verdict.
        $policy = $this->policy($c, 'Failed Subjects');
        $this->rule($policy, 'max_failed_subjects', '<=', '0', 'promoted', 'conditional', 1);
        $this->rule($policy, 'max_failed_subjects', '<=', '1', 'promoted', 'repeat', 2);

        $this->act($c, 'post', 'settings.academic.promotions.decisions.store', $policy, ['result_id' => $result->id]);

        $decision = PromotionDecision::firstOrFail();
        $this->assertSame('promoted', $this->verdictFor($decision, $clean->student)->decision);
        $this->assertSame('conditional', $this->verdictFor($decision, $oneFailed->student)->decision);
        $this->assertSame('repeat', $this->verdictFor($decision, $twoFailed->student)->decision);
    }

    public function test_mandatory_pass_rule(): void
    {
        $c = $this->curriculum();
        $this->globalScale();

        $ok = $this->placement($c, $this->student($c['institute']));
        $mandatoryFailed = $this->placement($c, $this->student($c['institute']));

        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');

        $this->mark($c, $ok, $mid, $c['math'], 90);
        $this->mark($c, $ok, $mid, $c['science'], 90);
        $this->mark($c, $ok, $final, $c['math'], 90);
        $this->mark($c, $ok, $final, $c['science'], 90);

        $this->mark($c, $mandatoryFailed, $mid, $c['math'], 90);
        $this->mark($c, $mandatoryFailed, $mid, $c['science'], 30);
        $this->mark($c, $mandatoryFailed, $final, $c['math'], 90);
        $this->mark($c, $mandatoryFailed, $final, $c['science'], 30);

        $result = $this->publishedResult($c, $mid, $final);

        $policy = $this->policy($c, 'Mandatory Pass');
        $this->rule($policy, 'mandatory_pass', null, null, 'promoted', 'repeat', 1);

        $this->act($c, 'post', 'settings.academic.promotions.decisions.store', $policy, ['result_id' => $result->id]);

        $decision = PromotionDecision::firstOrFail();
        $this->assertSame('promoted', $this->verdictFor($decision, $ok->student)->decision);
        $this->assertSame('repeat', $this->verdictFor($decision, $mandatoryFailed->student)->decision);

        $reason = $this->verdictFor($decision, $mandatoryFailed->student)->reasons[0] ?? '';
        $this->assertStringContainsString('mandatory', strtolower($reason));
    }

    // ------------------------------------------------------------- Lifecycle + placements

    public function test_decision_lifecycle_review_and_send_back(): void
    {
        $c = $this->curriculum();
        $this->globalScale();
        $this->placement($c, $this->student($c['institute']));
        $result = $this->publishedResult($c);

        $policy = $this->policy($c);
        $this->rule($policy, 'overall_pass', null, null, 'promoted', 'repeat', 1);
        $this->act($c, 'post', 'settings.academic.promotions.decisions.store', $policy, ['result_id' => $result->id]);

        $decision = PromotionDecision::firstOrFail();
        $this->assertSame('pending', $decision->status);

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.promotions.decisions.show', $decision))
            ->assertOk();

        $this->act($c, 'post', 'settings.academic.promotions.decisions.review', $decision)->assertRedirect();
        $this->assertSame('review', $decision->fresh()->status);

        $this->act($c, 'post', 'settings.academic.promotions.decisions.send-to-review', $decision)->assertRedirect();
        $this->assertSame('pending', $decision->fresh()->status);
        $this->assertNull($decision->fresh()->reviewed_at);
    }

    public function test_approve_creates_next_year_placements_and_never_touches_source(): void
    {
        $c = $this->curriculum();
        $this->globalScale();

        $promoted = $this->placement($c, $this->student($c['institute']));
        $repeater = $this->placement($c, $this->student($c['institute']));

        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');

        $this->mark($c, $promoted, $mid, $c['math'], 90);
        $this->mark($c, $promoted, $mid, $c['science'], 90);
        $this->mark($c, $promoted, $final, $c['math'], 90);
        $this->mark($c, $promoted, $final, $c['science'], 90);

        $this->mark($c, $repeater, $mid, $c['math'], 30);
        $this->mark($c, $repeater, $mid, $c['science'], 30);
        $this->mark($c, $repeater, $final, $c['math'], 30);
        $this->mark($c, $repeater, $final, $c['science'], 30);

        $result = $this->publishedResult($c, $mid, $final);

        $policy = $this->policy($c);
        $this->rule($policy, 'overall_pass', null, null, 'promoted', 'repeat', 1);
        $this->act($c, 'post', 'settings.academic.promotions.decisions.store', $policy, ['result_id' => $result->id]);

        $decision = PromotionDecision::firstOrFail();

        // Target class (Class 9) with its own mandatory subject.
        $class9 = $this->classGrade($c['level'], 'pr-c9', 'Class 9', 1);
        $group9 = $this->group($class9, 'pr-sci9', 'Science 9');
        $math9 = $this->subject('Mathematics IX', 'PR100101');
        $this->assign($math9, $class9, 'mandatory', 1);

        $year2027 = $this->defaultYear($c['institute'], '2027', false);

        $targets = [
            $promoted->id => ['class_grade_id' => $class9->id, 'academic_group_id' => $group9->id],
        ];

        $this->act($c, 'post', 'settings.academic.promotions.decisions.approve', $decision, [
            'target_year_id' => $year2027->id,
            'targets' => $targets,
        ])->assertRedirect();

        // Decision sealed.
        $this->assertSame('approved', $decision->fresh()->status);

        // Promoted student got a NEW 2027 placement in Class 9 / Science 9.
        $new = StudentAcademicPlacement::where('student_id', $promoted->student_id)
            ->where('academic_year_id', $year2027->id)
            ->firstOrFail();
        $this->assertSame($class9->id, $new->class_grade_id);
        $this->assertSame($group9->id, $new->academic_group_id);
        $this->assertNotSame($promoted->id, $new->id);

        // Source placement unchanged: still 2026 Class 8, same id, selections intact.
        $this->assertSame($promoted->id, $promoted->fresh()->id);
        $this->assertSame(2026, (int) substr($promoted->fresh()->academicYear->code, 0, 4));
        $this->assertSame($c['class']->id, $promoted->fresh()->class_grade_id);
        $this->assertSame(2, $promoted->fresh()->selections()->count());

        // New placement subject selection revalidated against Class 9: only
        // the mandatory subject (Math IX) exists there â†’ exactly one selection.
        $this->assertSame(1, $new->selections()->count());
        $this->assertSame($math9->id, $new->selections()->first()->subject_id);

        // Repeater got no next placement.
        $repeaterItem = $this->verdictFor($decision, $repeater->student);
        $this->assertSame('repeat', $repeaterItem->decision);
        $this->assertNull($repeaterItem->next_placement_id);
        $this->assertFalse(StudentAcademicPlacement::where('student_id', $repeater->student_id)->where('academic_year_id', $year2027->id)->exists());

        // Historical link recorded.
        $promotedItem = $this->verdictFor($decision, $promoted->student);
        $this->assertSame($new->id, $promotedItem->next_placement_id);
    }

    public function test_approve_rejects_duplicate_target_year_placement_and_rolls_back(): void
    {
        $c = $this->curriculum();
        $this->globalScale();

        $promoted = $this->placement($c, $this->student($c['institute']));

        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');
        $this->mark($c, $promoted, $mid, $c['math'], 90);
        $this->mark($c, $promoted, $mid, $c['science'], 90);
        $this->mark($c, $promoted, $final, $c['math'], 90);
        $this->mark($c, $promoted, $final, $c['science'], 90);

        $result = $this->publishedResult($c, $mid, $final);

        $policy = $this->policy($c);
        $this->rule($policy, 'overall_pass', null, null, 'promoted', 'repeat', 1);
        $this->act($c, 'post', 'settings.academic.promotions.decisions.store', $policy, ['result_id' => $result->id]);

        $decision = PromotionDecision::firstOrFail();

        $class9 = $this->classGrade($c['level'], 'pr-c9b', 'Class 9', 1);
        $year2027 = $this->defaultYear($c['institute'], '2027', false);

        // The student already holds a 2027 placement.
        StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $promoted->student_id,
            'academic_year_id' => $year2027->id,
            'class_grade_id' => $class9->id,
            'academic_group_id' => null,
            'status' => 'active',
        ]);

        try {
            app(PromotionLifecycleService::class)->approve(
                $c['institute'],
                $decision,
                $year2027,
                [$promoted->id => ['class_grade_id' => $class9->id, 'academic_group_id' => null]],
                (int) $c['owner']->id
            );
            $this->fail('Expected a duplicate-year ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('academic_year_id', $e->errors());
        }

        // Decision NOT approved; no partial placements.
        $this->assertSame('pending', $decision->fresh()->status);
        $this->assertNull($this->verdictFor($decision, $promoted->student)->next_placement_id);
    }

    public function test_approve_requires_targets_for_promotable_students(): void
    {
        $c = $this->curriculum();
        $this->globalScale();
        $promoted = $this->placement($c, $this->student($c['institute']));

        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');
        $this->mark($c, $promoted, $mid, $c['math'], 90);
        $this->mark($c, $promoted, $mid, $c['science'], 90);
        $this->mark($c, $promoted, $final, $c['math'], 90);
        $this->mark($c, $promoted, $final, $c['science'], 90);

        $result = $this->publishedResult($c, $mid, $final);

        $policy = $this->policy($c);
        $this->rule($policy, 'overall_pass', null, null, 'promoted', 'repeat', 1);
        $this->act($c, 'post', 'settings.academic.promotions.decisions.store', $policy, ['result_id' => $result->id]);

        $decision = PromotionDecision::firstOrFail();
        $year2027 = $this->defaultYear($c['institute'], '2027', false);

        $this->act($c, 'post', 'settings.academic.promotions.decisions.approve', $decision, [
            'target_year_id' => $year2027->id,
            'targets' => [],
        ])->assertStatus(422);

        $this->assertSame('pending', $decision->fresh()->status);
    }

    // ------------------------------------------------------------- Security + permissions

    public function test_teacher_without_promotion_manage_permission_is_blocked(): void
    {
        $c = $this->curriculum();
        TenantContext::set($c['institute']->id);

        $this->actingAs($this->teacher($c['institute']), 'institute_user')
            ->get(route('settings.academic.promotions.index'))
            ->assertForbidden();
    }

    public function test_auth_required_for_promotion_routes(): void
    {
        $this->get(route('settings.academic.promotions.index'))->assertRedirect();
    }

    public function test_cross_tenant_result_is_rejected_for_decision(): void
    {
        $c = $this->curriculum();
        $other = $this->curriculum();
        $this->globalScale();
        $this->placement($other, $this->student($other['institute']));
        $foreignResult = $this->publishedResult($other);

        $policy = $this->policy($c);
        $this->rule($policy, 'overall_pass', null, null, 'promoted', 'repeat', 1);

        $this->act($c, 'post', 'settings.academic.promotions.decisions.store', $policy, ['result_id' => $foreignResult->id])
            ->assertStatus(422);
    }

    // ------------------------------------------------------------- Historical deletion protection

    public function test_placement_with_final_result_history_cannot_be_deleted(): void
    {
        $c = $this->curriculum();
        $this->globalScale();
        $placement = $this->placement($c, $this->student($c['institute']));
        $this->publishedResult($c);

        // The published snapshot actually records this placement.
        $this->assertTrue(AcademicFinalResultStudent::query()->where('placement_id', $placement->id)->exists());
        $this->assertTrue(AcademicFinalResultRow::query()->where('placement_id', $placement->id)->exists());

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->delete(route('settings.academic.placements.destroy', $placement))
            ->assertRedirect();

        // Blocked: placement retained; published snapshot history intact.
        $this->assertDatabaseHas('student_academic_placements', ['id' => $placement->id]);
        $this->assertDatabaseHas('academic_final_result_students', ['placement_id' => $placement->id]);
        $this->assertDatabaseHas('academic_final_result_rows', ['placement_id' => $placement->id]);
    }

    public function test_placement_referenced_by_promotion_decision_cannot_be_deleted(): void
    {
        $c = $this->curriculum();
        $this->globalScale();
        $placement = $this->placement($c, $this->student($c['institute']));
        $result = $this->publishedResult($c);

        $policy = $this->policy($c);
        $this->rule($policy, 'overall_pass', null, null, 'promoted', 'repeat', 1);
        $this->act($c, 'post', 'settings.academic.promotions.decisions.store', $policy, ['result_id' => $result->id]);

        $item = PromotionDecisionItem::query()->where('placement_id', $placement->id)->firstOrFail();

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->delete(route('settings.academic.placements.destroy', $placement))
            ->assertRedirect();

        // Blocked: placement + its decision item retained; decision cycle intact.
        $this->assertDatabaseHas('student_academic_placements', ['id' => $placement->id]);
        $this->assertDatabaseHas('promotion_decision_items', ['id' => $item->id]);
        $this->assertSame('pending', PromotionDecision::firstOrFail()->fresh()->status);
    }

    public function test_promotion_target_placement_cannot_be_deleted(): void
    {
        $c = $this->curriculum();
        $this->globalScale();

        $promoted = $this->placement($c, $this->student($c['institute']));

        $mid = $this->assessment($c, 'Mid Term');
        $final = $this->assessment($c, 'Final Term');
        $this->mark($c, $promoted, $mid, $c['math'], 90);
        $this->mark($c, $promoted, $mid, $c['science'], 90);
        $this->mark($c, $promoted, $final, $c['math'], 90);
        $this->mark($c, $promoted, $final, $c['science'], 90);

        $result = $this->publishedResult($c, $mid, $final);

        $policy = $this->policy($c);
        $this->rule($policy, 'overall_pass', null, null, 'promoted', 'repeat', 1);
        $this->act($c, 'post', 'settings.academic.promotions.decisions.store', $policy, ['result_id' => $result->id]);

        $decision = PromotionDecision::firstOrFail();

        $class9 = $this->classGrade($c['level'], 'pr-c9t', 'Class 9', 1);
        $group9 = $this->group($class9, 'pr-sci9t', 'Science 9');
        $math9 = $this->subject('Mathematics IX', 'PR100301');
        $this->assign($math9, $class9, 'mandatory', 1);

        $year2027 = $this->defaultYear($c['institute'], '2027', false);

        $this->act($c, 'post', 'settings.academic.promotions.decisions.approve', $decision, [
            'target_year_id' => $year2027->id,
            'targets' => [$promoted->id => ['class_grade_id' => $class9->id, 'academic_group_id' => $group9->id]],
        ])->assertRedirect();

        // The approved promotion created a NEW 2027 placement and recorded it
        // as next_placement_id in the decision item — pure promotion history.
        $target = StudentAcademicPlacement::where('student_id', $promoted->student_id)
            ->where('academic_year_id', $year2027->id)
            ->firstOrFail();
        $item = $this->verdictFor($decision, $promoted->student);
        $this->assertSame($target->id, $item->fresh()->next_placement_id);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->delete(route('settings.academic.placements.destroy', $target))
            ->assertRedirect();

        // Blocked: the promoted target stays; the promotion history link survives.
        $this->assertDatabaseHas('student_academic_placements', ['id' => $target->id]);
        $this->assertSame($target->id, $item->fresh()->next_placement_id);
    }

    public function test_pages_render(): void
    {
        $c = $this->curriculum();
        TenantContext::set($c['institute']->id);
        $policy = $this->policy($c);

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.promotions.index'))
            ->assertOk();

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.promotions.policies.show', $policy))
            ->assertOk();

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.promotions.policies.create'))
            ->assertOk();
    }
}
