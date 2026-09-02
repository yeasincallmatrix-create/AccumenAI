<?php

namespace Tests\Feature;

use App\Models\AcademicAssessment;
use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicResultAggregationItem;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicSelectionGroup;
use App\Models\AcademicStudentMark;
use App\Models\AcademicYear;
use App\Models\AssessmentSubject;
use App\Models\AssessmentSubjectComponent;
use App\Models\AssessmentType;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Component;
use App\Models\Country;
use App\Models\EducationSystem;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\StudentSubjectSelection;
use App\Models\Subject;
use App\Models\SubjectAcademicAssignment;
use App\Services\AcademicResultAggregationService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Database\Seeders\AcademicAssessmentSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AcademicResultAggregationTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicAssessmentSeeder::class);
    }

    // ------------------------------------------------------------- Helpers

    private function country(string $iso2 = 'BD', string $name = 'Bangladesh'): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => $iso2],
            ['name' => $name, 'iso3' => strtoupper($iso2).'F', 'phone_code' => '880', 'status' => true]
        );
    }

    private function system(Country $country, string $code = 'general'): EducationSystem
    {
        return EducationSystem::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $country->id, 'code' => $code],
            ['name' => 'General Education', 'display_order' => 0, 'status' => true]
        );
    }

    private function level(EducationSystem $system, string $code = 'secondary'): AcademicLevel
    {
        return AcademicLevel::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $system->country_id, 'education_system_id' => $system->id, 'code' => $code],
            ['name' => 'Secondary', 'display_order' => 1, 'status' => true]
        );
    }

    private function classGrade(AcademicLevel $level, string $code = 'c8'): ClassGrade
    {
        return ClassGrade::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $level->country_id, 'education_system_id' => $level->education_system_id, 'academic_level_id' => $level->id, 'code' => $code],
            ['name' => 'Class 8', 'display_order' => 0, 'status' => true]
        );
    }

    private function group(ClassGrade $classGrade, string $code = 'sci'): AcademicGroup
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

    private function assign(Subject $subject, ClassGrade $classGrade, string $requirementType, int $displayOrder, ?int $selectionGroupId = null): SubjectAcademicAssignment
    {
        return SubjectAcademicAssignment::create([
            'subject_id' => $subject->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => null,
            'requirement_type' => $requirementType,
            'selection_group_id' => $selectionGroupId,
            'display_order' => $displayOrder,
            'status' => 'active',
        ]);
    }

    private function selectionGroup(ClassGrade $classGrade, int $min, int $max): AcademicSelectionGroup
    {
        return AcademicSelectionGroup::create([
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => null,
            'name' => 'Group A',
            'code' => 'groupA',
            'selection_type' => 'optional',
            'minimum_selection' => $min,
            'maximum_selection' => $max,
            'display_order' => 1,
            'status' => 'active',
        ]);
    }

    private function institute(Country $country, string $name = 'Agg Inst'): Institute
    {
        return Institute::create([
            'name' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute, string $name = 'Main Branch'): Branch
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

    private function year(Institute $institute, string $code = '2026', string $name = 'Academic Year 2026'): AcademicYear
    {
        return AcademicYear::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'code' => $code,
            'is_current' => true,
            'status' => true,
        ]);
    }

    private function defaultYear(Institute $institute): AcademicYear
    {
        return AcademicYear::withoutGlobalScopes()->firstOrCreate(
            ['institute_id' => $institute->id, 'code' => '2026'],
            ['name' => 'Academic Year 2026', 'is_current' => true, 'status' => true]
        );
    }

    protected function writtenComponent(string $slug = 'written'): Component
    {
        return Component::where('slug', $slug)->firstOrFail();
    }

    private function type(string $slug = 'mid-term'): AssessmentType
    {
        return AssessmentType::where('slug', $slug)->firstOrFail();
    }

    /**
     * Class-8 curriculum: mandatory Bangla/English/Mathematics + optional group
     * A {Biology, Higher Math} min 1 max 1.
     */
    private function curriculum(): array
    {
        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);
        $group = $this->group($classGrade);
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'ag-owner');

        $bangla = $this->subject('Bangla', 'AG100001');
        $english = $this->subject('English', 'AG100002');
        $math = $this->subject('Mathematics', 'AG100003');
        $bio = $this->subject('Biology', 'AG100004');
        $hmath = $this->subject('Higher Mathematics', 'AG100005');

        $this->assign($bangla, $classGrade, 'mandatory', 1);
        $this->assign($english, $classGrade, 'mandatory', 2);
        $this->assign($math, $classGrade, 'mandatory', 3);
        $selGroup = $this->selectionGroup($classGrade, 1, 1);
        $this->assign($bio, $classGrade, 'optional', 4, $selGroup->id);
        $this->assign($hmath, $classGrade, 'optional', 5, $selGroup->id);

        return [
            'country' => $country,
            'class_grade' => $classGrade,
            'group' => $group,
            'institute' => $institute,
            'owner' => $owner,
            'subjects' => compact('bangla', 'english', 'math', 'bio', 'hmath'),
        ];
    }

    /**
     * Create an assessment in the curriculum context with math (written
     * 100 full / 33 pass) and optional biology where requested.
     */
    private function assessment(array $c, string $name, array $subjectConfigs): AcademicAssessment
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

        foreach ($subjectConfigs as $config) {
            $subjectConfig = AssessmentSubject::create([
                'assessment_id' => $assessment->id,
                'subject_id' => $config['subject_id'],
                'display_order' => $config['order'] ?? 1,
                'status' => 'active',
            ]);
            foreach ($config['components'] as $ci => $component) {
                AssessmentSubjectComponent::create([
                    'assessment_subject_id' => $subjectConfig->id,
                    'component_id' => $component['component_id'],
                    'full_mark' => $component['full_mark'],
                    'pass_mark' => $component['pass_mark'],
                    'mandatory_pass' => $component['mandatory_pass'] ?? false,
                    'display_order' => $ci + 1,
                    'status' => 'active',
                ]);
            }
        }

        return $assessment;
    }

    /**
     * Place a student in the curriculum context and auto-select the requested
     * subjects (mirrors the placement service's mandatory auto-include).
     */
    private function placement(array $c, Student $student, array $subjectIds, ?AcademicGroup $group = null): StudentAcademicPlacement
    {
        $placement = StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $student->id,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $group?->id,
            'status' => 'active',
        ]);

        foreach ($subjectIds as $subjectId) {
            StudentSubjectSelection::create([
                'institute_id' => $c['institute']->id,
                'academic_placement_id' => $placement->id,
                'subject_id' => $subjectId,
                'is_selected' => true,
                'is_mandatory' => false,
            ]);
        }

        return $placement;
    }

    private function mark(StudentAcademicPlacement $placement, AssessmentSubject $subjectConfig, Component $component, float $obtained, string $status = 'entered'): AcademicStudentMark
    {
        $assessmentComponent = AssessmentSubjectComponent::where('assessment_subject_id', $subjectConfig->id)
            ->where('component_id', $component->id)
            ->firstOrFail();

        return AcademicStudentMark::create([
            'institute_id' => $placement->institute_id,
            'academic_assessment_id' => $subjectConfig->assessment_id,
            'assessment_subject_id' => $subjectConfig->id,
            'assessment_component_id' => $assessmentComponent->id,
            'student_id' => $placement->student_id,
            'academic_placement_id' => $placement->id,
            'obtained_mark' => $status === 'absent' ? null : $obtained,
            'status' => $status,
        ]);
    }

    private function assertDatabaseCountSettings(): array
    {
        return [
            'marks' => AcademicStudentMark::count(),
            'assessments' => AcademicAssessment::count(),
        ];
    }

    // ------------------------------------------------------------- Configuration

    public function test_owner_creates_scheme_with_manual_weights(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid Term', [
            ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]],
        ]);
        $final = $this->assessment($c, 'Final', [
            ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]],
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.aggregations.store'), [
                'academic_year_id' => $this->defaultYear($c['institute'])->id,
                'class_grade_id' => $c['class_grade']->id,
                'academic_group_id' => $c['group']->id,
                'name' => 'Annual Result',
                'status' => 'draft',
                'items' => [
                    ['assessment_id' => $mid->id, 'weight' => 40],
                    ['assessment_id' => $final->id, 'weight' => 60],
                ],
            ])
            ->assertRedirect();

        $scheme = AcademicResultAggregationScheme::where('name', 'Annual Result')->firstOrFail();
        $this->assertSame($c['institute']->id, $scheme->institute_id);
        $this->assertNull($scheme->branch_id);
        $this->assertSame(2, $scheme->items()->count());
        $this->assertSame(100.0, $scheme->totalWeight());
        $this->assertTrue($scheme->weightIsValid());

        $this->assertDatabaseHas('academic_result_aggregation_items', [
            'scheme_id' => $scheme->id,
            'academic_assessment_id' => $mid->id,
            'weight' => 40,
        ]);
        $this->assertDatabaseHas('academic_result_aggregation_items', [
            'scheme_id' => $scheme->id,
            'academic_assessment_id' => $final->id,
            'weight' => 60,
        ]);
    }

    public function test_edit_weights_updates_items(): void
    {
        $c = $this->curriculum();
        $a = $this->assessment($c, 'A', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $b = $this->assessment($c, 'B', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);

        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $c['institute']->id,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'name' => 'Annual',
            'status' => 'draft',
        ]);
        AcademicResultAggregationItem::create(['scheme_id' => $scheme->id, 'academic_assessment_id' => $a->id, 'weight' => 40, 'display_order' => 1]);
        AcademicResultAggregationItem::create(['scheme_id' => $scheme->id, 'academic_assessment_id' => $b->id, 'weight' => 60, 'display_order' => 2]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->put(route('settings.academic.aggregations.update', $scheme), [
                'academic_year_id' => $this->defaultYear($c['institute'])->id,
                'class_grade_id' => $c['class_grade']->id,
                'academic_group_id' => $c['group']->id,
                'name' => 'Annual',
                'status' => 'active',
                'items' => [
                    ['assessment_id' => $a->id, 'weight' => 70],
                    ['assessment_id' => $b->id, 'weight' => 30],
                ],
            ])
            ->assertRedirect();

        $scheme->refresh();
        $this->assertSame(70.0, $scheme->items()->where('academic_assessment_id', $a->id)->firstOrFail()->weight);
        $this->assertSame(30.0, $scheme->items()->where('academic_assessment_id', $b->id)->firstOrFail()->weight);
    }

    public function test_weight_out_of_range_is_rejected(): void
    {
        $c = $this->curriculum();
        $a = $this->assessment($c, 'A', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.aggregations.store'), [
                'academic_year_id' => $this->defaultYear($c['institute'])->id,
                'class_grade_id' => $c['class_grade']->id,
                'academic_group_id' => $c['group']->id,
                'name' => 'Annual',
                'status' => 'draft',
                'items' => [['assessment_id' => $a->id, 'weight' => 110]],
            ])
            ->assertSessionHasErrors('items.0.weight');

        $this->assertDatabaseCount('academic_result_aggregation_schemes', 0);
    }

    public function test_duplicate_assessment_in_scheme_is_rejected(): void
    {
        $c = $this->curriculum();
        $a = $this->assessment($c, 'A', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.aggregations.store'), [
                'academic_year_id' => $this->defaultYear($c['institute'])->id,
                'class_grade_id' => $c['class_grade']->id,
                'academic_group_id' => $c['group']->id,
                'name' => 'Annual',
                'status' => 'draft',
                'items' => [
                    ['assessment_id' => $a->id, 'weight' => 50],
                    ['assessment_id' => $a->id, 'weight' => 50],
                ],
            ])
            ->assertSessionHasErrors('items.1.assessment_id');

        $this->assertDatabaseCount('academic_result_aggregation_schemes', 0);
    }

    public function test_no_items_is_rejected(): void
    {
        $c = $this->curriculum();

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.aggregations.store'), [
                'academic_year_id' => $this->defaultYear($c['institute'])->id,
                'class_grade_id' => $c['class_grade']->id,
                'academic_group_id' => $c['group']->id,
                'name' => 'Annual',
                'status' => 'draft',
                'items' => [],
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('academic_result_aggregation_schemes', 0);
    }

    public function test_cross_context_assessment_is_rejected(): void
    {
        $c = $this->curriculum();
        $otherInstitute = $this->institute($this->country('IN', 'India'), 'Other Agg Inst');
        $otherYear = $this->year($otherInstitute, '2026');

        // Assessment in another institute but same class/group ids DO NOT exist
        // there; use an assessment from a different year of this institute.
        $foreignYear = $this->year($c['institute'], '2027');
        $foreignAssessment = AcademicAssessment::create([
            'institute_id' => $c['institute']->id,
            'academic_year_id' => $foreignYear->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'name' => 'Other Year Mid',
            'status' => 'draft',
            'display_order' => 1,
        ]);
        $subjectConfig = AssessmentSubject::create(['assessment_id' => $foreignAssessment->id, 'subject_id' => $c['subjects']['math']->id, 'display_order' => 1, 'status' => 'active']);
        AssessmentSubjectComponent::create(['assessment_subject_id' => $subjectConfig->id, 'component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33, 'display_order' => 1, 'status' => 'active']);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.aggregations.store'), [
                'academic_year_id' => $this->defaultYear($c['institute'])->id,
                'class_grade_id' => $c['class_grade']->id,
                'academic_group_id' => $c['group']->id,
                'name' => 'Annual',
                'status' => 'draft',
                'items' => [['assessment_id' => $foreignAssessment->id, 'weight' => 100]],
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('academic_result_aggregation_schemes', 0);
    }

    public function test_same_assessment_reusable_in_multiple_schemes(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $final = $this->assessment($c, 'Final', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);

        TenantContext::set($c['institute']->id);

        foreach ([['mid' => 40, 'final' => 60], ['mid' => 30, 'final' => 70]] as $i => $weights) {
            $this->actingAs($c['owner'], 'institute_user')
                ->post(route('settings.academic.aggregations.store'), [
                    'academic_year_id' => $this->defaultYear($c['institute'])->id,
                    'class_grade_id' => $c['class_grade']->id,
                    'academic_group_id' => $c['group']->id,
                    'name' => "Scheme $i",
                    'status' => 'active',
                    'items' => [
                        ['assessment_id' => $mid->id, 'weight' => $weights['mid']],
                        ['assessment_id' => $final->id, 'weight' => $weights['final']],
                    ],
                ])->assertRedirect();
        }

        $this->assertSame(2, AcademicResultAggregationScheme::count());
        $this->assertSame(2, AcademicResultAggregationItem::where('academic_assessment_id', $mid->id)->count());
    }

    // ------------------------------------------------------------- Calculation

    private function schemeFor(array $c, array $weights): AcademicResultAggregationScheme
    {
        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $c['institute']->id,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'name' => 'Annual',
            'status' => 'active',
        ]);

        foreach ($weights as $assessmentId => $weight) {
            AcademicResultAggregationItem::create([
                'scheme_id' => $scheme->id,
                'academic_assessment_id' => $assessmentId,
                'weight' => $weight,
            ]);
        }

        return $scheme->load('items');
    }

    private function mathConfig(AcademicAssessment $assessment, int $subjectId): AssessmentSubject
    {
        return AssessmentSubject::where('assessment_id', $assessment->id)->where('subject_id', $subjectId)->firstOrFail();
    }

    public function test_40_60_weightage(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $final = $this->assessment($c, 'Final', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $scheme = $this->schemeFor($c, [$mid->id => 40, $final->id => 60]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id]);
        $this->mark($placement, $this->mathConfig($mid, $c['subjects']['math']->id), $this->writtenComponent(), 72);
        $this->mark($placement, $this->mathConfig($final, $c['subjects']['math']->id), $this->writtenComponent(), 81);

        TenantContext::set($c['institute']->id);

        $agg = app(AcademicResultAggregationService::class)->subjectAggregate($scheme, $placement, $c['subjects']['math']->id);

        $this->assertSame(AcademicResultAggregationService::SUBJECT_AGGREGATE_COMPUTED, $agg['status']);
        $this->assertEqualsWithDelta(77.4, $agg['aggregate'], 0.01);
        // original weights preserved
        $entries = collect($agg['entries'])->keyBy('original_weight');
        $this->assertSame(40.0, $entries->get(40.0)['original_weight']);
        $this->assertSame(60.0, $entries->get(60.0)['original_weight']);
        // no re-normalization occurred (both entered)
        $this->assertSame(40.0, $entries->get(40.0)['effective_weight']);
        $this->assertSame(60.0, $entries->get(60.0)['effective_weight']);
    }

    public function test_20_30_50_weightage(): void
    {
        $c = $this->curriculum();
        $first = $this->assessment($c, 'First Term', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $mid = $this->assessment($c, 'Mid', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $final = $this->assessment($c, 'Final', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $scheme = $this->schemeFor($c, [$first->id => 20, $mid->id => 30, $final->id => 50]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id]);
        $this->mark($placement, $this->mathConfig($first, $c['subjects']['math']->id), $this->writtenComponent(), 65);
        $this->mark($placement, $this->mathConfig($mid, $c['subjects']['math']->id), $this->writtenComponent(), 72);
        $this->mark($placement, $this->mathConfig($final, $c['subjects']['math']->id), $this->writtenComponent(), 80);

        TenantContext::set($c['institute']->id);

        $agg = app(AcademicResultAggregationService::class)->subjectAggregate($scheme, $placement, $c['subjects']['math']->id);

        $this->assertSame(AcademicResultAggregationService::SUBJECT_AGGREGATE_COMPUTED, $agg['status']);
        $this->assertEqualsWithDelta(74.6, $agg['aggregate'], 0.01);
    }

    public function test_different_full_marks_are_normalized(): void
    {
        $c = $this->curriculum();
        // Mid Term: 72 / 100. Final: 160 / 200.
        $mid = $this->assessment($c, 'Mid', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $final = $this->assessment($c, 'Final', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 200, 'pass_mark' => 66]]]]);
        $scheme = $this->schemeFor($c, [$mid->id => 40, $final->id => 60]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id]);
        $this->mark($placement, $this->mathConfig($mid, $c['subjects']['math']->id), $this->writtenComponent(), 72);
        $this->mark($placement, $this->mathConfig($final, $c['subjects']['math']->id), $this->writtenComponent(), 160);

        TenantContext::set($c['institute']->id);

        $agg = app(AcademicResultAggregationService::class)->subjectAggregate($scheme, $placement, $c['subjects']['math']->id);

        $this->assertSame(AcademicResultAggregationService::SUBJECT_AGGREGATE_COMPUTED, $agg['status']);
        $this->assertEqualsWithDelta(76.8, $agg['aggregate'], 0.01);
    }

    public function test_decimal_precision_and_rounding(): void
    {
        $c = $this->curriculum();
        $a = $this->assessment($c, 'A', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $b = $this->assessment($c, 'B', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $scheme = $this->schemeFor($c, [$a->id => 50, $b->id => 50]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id]);
        // 1/3 = 33.3333... rounded to 2 dp = 33.33; 2/3 = 66.67 -> 50.00
        $this->mark($placement, $this->mathConfig($a, $c['subjects']['math']->id), $this->writtenComponent(), 33.3333);
        $this->mark($placement, $this->mathConfig($b, $c['subjects']['math']->id), $this->writtenComponent(), 66.6667);

        TenantContext::set($c['institute']->id);

        $agg = app(AcademicResultAggregationService::class)->subjectAggregate($scheme, $placement, $c['subjects']['math']->id);

        // 50.00 is the exact rounded half. Verify rounding rule: 33.3333*0.5 + 66.6667*0.5 = 50.0
        $this->assertSame(50.0, $agg['aggregate']);
    }

    public function test_zero_marks_count_as_real_zero(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $final = $this->assessment($c, 'Final', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $scheme = $this->schemeFor($c, [$mid->id => 40, $final->id => 60]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id]);
        $this->mark($placement, $this->mathConfig($mid, $c['subjects']['math']->id), $this->writtenComponent(), 0);
        $this->mark($placement, $this->mathConfig($final, $c['subjects']['math']->id), $this->writtenComponent(), 80);

        TenantContext::set($c['institute']->id);

        $agg = app(AcademicResultAggregationService::class)->subjectAggregate($scheme, $placement, $c['subjects']['math']->id);

        $this->assertSame(AcademicResultAggregationService::SUBJECT_AGGREGATE_COMPUTED, $agg['status']);
        $this->assertEqualsWithDelta(48.0, $agg['aggregate'], 0.01);
    }

    // ------------------------------------------------------------- Absent / Not entered

    public function test_absent_is_excluded_and_weights_renormalized(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $final = $this->assessment($c, 'Final', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $scheme = $this->schemeFor($c, [$mid->id => 40, $final->id => 60]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id]);
        $this->mark($placement, $this->mathConfig($mid, $c['subjects']['math']->id), $this->writtenComponent(), 0, 'absent');
        $this->mark($placement, $this->mathConfig($final, $c['subjects']['math']->id), $this->writtenComponent(), 80);

        TenantContext::set($c['institute']->id);

        $agg = app(AcademicResultAggregationService::class)->subjectAggregate($scheme, $placement, $c['subjects']['math']->id);

        $this->assertSame(AcademicResultAggregationService::SUBJECT_AGGREGATE_COMPUTED, $agg['status']);
        // Only Final entered -> effective weight 100% -> aggregate 80
        $this->assertEqualsWithDelta(80.0, $agg['aggregate'], 0.01);
        // original weights preserved, absent status preserved
        $entries = collect($agg['entries'])->keyBy('assessment.name');
        $this->assertSame(40.0, $entries->get('Mid')['original_weight']);
        $this->assertSame('absent', $entries->get('Mid')['status']);
        // effective weight of Final = 60 / 60 * 100 = 100
        $this->assertSame(100.0, $entries->get('Final')['effective_weight']);
    }

    public function test_not_entered_is_incomplete_without_renormalization(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $final = $this->assessment($c, 'Final', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $scheme = $this->schemeFor($c, [$mid->id => 40, $final->id => 60]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id]);
        // No marks at all for Mid Term -> not entered. Final entered.
        $this->mark($placement, $this->mathConfig($final, $c['subjects']['math']->id), $this->writtenComponent(), 80);

        TenantContext::set($c['institute']->id);

        $agg = app(AcademicResultAggregationService::class)->subjectAggregate($scheme, $placement, $c['subjects']['math']->id);

        $this->assertSame(AcademicResultAggregationService::SUBJECT_AGGREGATE_INCOMPLETE, $agg['status']);
        $this->assertNull($agg['aggregate']);
        $this->assertNotNull($agg['incomplete_reason']);
        $this->assertStringContainsString('Mid', $agg['incomplete_reason']);
    }

    public function test_all_absent_is_absent_only(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $final = $this->assessment($c, 'Final', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $scheme = $this->schemeFor($c, [$mid->id => 40, $final->id => 60]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id]);
        $this->mark($placement, $this->mathConfig($mid, $c['subjects']['math']->id), $this->writtenComponent(), 0, 'absent');
        $this->mark($placement, $this->mathConfig($final, $c['subjects']['math']->id), $this->writtenComponent(), 0, 'absent');

        TenantContext::set($c['institute']->id);

        $agg = app(AcademicResultAggregationService::class)->subjectAggregate($scheme, $placement, $c['subjects']['math']->id);

        $this->assertSame(AcademicResultAggregationService::SUBJECT_AGGREGATE_ABSENT_ONLY, $agg['status']);
        $this->assertNull($agg['aggregate']);
    }

    // ------------------------------------------------------------- Eligibility

    public function test_selected_optional_subject_is_included(): void
    {
        $c = $this->curriculum();
        // Biology is optional, present in both assessments
        $mid = $this->assessment($c, 'Mid', [
            ['subject_id' => $c['subjects']['bio']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]],
        ]);
        $final = $this->assessment($c, 'Final', [
            ['subject_id' => $c['subjects']['bio']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]],
        ]);
        $scheme = $this->schemeFor($c, [$mid->id => 40, $final->id => 60]);

        $rahim = $this->student($c['institute'], 'Rahim');
        $placement = $this->placement($c, $rahim, [$c['subjects']['bio']->id]);
        $this->mark($placement, $this->mathConfig($mid, $c['subjects']['bio']->id), $this->writtenComponent(), 60);
        $this->mark($placement, $this->mathConfig($final, $c['subjects']['bio']->id), $this->writtenComponent(), 80);

        TenantContext::set($c['institute']->id);

        $agg = app(AcademicResultAggregationService::class)->subjectAggregate($scheme, $placement, $c['subjects']['bio']->id);

        $this->assertSame(AcademicResultAggregationService::SUBJECT_AGGREGATE_COMPUTED, $agg['status']);
        $this->assertEqualsWithDelta(72.0, $agg['aggregate'], 0.01);
    }

    public function test_unselected_optional_subject_is_excluded(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid', [
            ['subject_id' => $c['subjects']['bio']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]],
        ]);
        $final = $this->assessment($c, 'Final', [
            ['subject_id' => $c['subjects']['bio']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]],
        ]);
        $scheme = $this->schemeFor($c, [$mid->id => 40, $final->id => 60]);

        // Karim did not select Biology (selected Higher Math instead)
        $karim = $this->student($c['institute'], 'Karim');
        $karimPlacement = $this->placement($c, $karim, [$c['subjects']['hmath']->id]);

        TenantContext::set($c['institute']->id);

        $agg = app(AcademicResultAggregationService::class)->subjectAggregate($scheme, $karimPlacement, $c['subjects']['bio']->id);

        $this->assertSame(AcademicResultAggregationService::SUBJECT_AGGREGATE_NOT_ELIGIBLE, $agg['status']);
        $this->assertNull($agg['aggregate']);
        // no zero invented
        $this->assertNull($agg['entries'][0]['percentage'] ?? null);
    }

    public function test_wrong_group_student_is_excluded_from_preview(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $final = $this->assessment($c, 'Final', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $scheme = $this->schemeFor($c, [$mid->id => 40, $final->id => 60]);

        $artsGroup = AcademicGroup::create([
            'country_id' => $c['class_grade']->country_id,
            'education_system_id' => $c['class_grade']->education_system_id,
            'academic_level_id' => $c['class_grade']->academic_level_id,
            'class_grade_id' => $c['class_grade']->id,
            'name' => 'Arts',
            'code' => 'arts',
            'display_order' => 1,
            'status' => true,
        ]);

        $artsStudent = $this->student($c['institute'], 'Karim');
        $this->placement($c, $artsStudent, [$c['subjects']['math']->id], $artsGroup);

        TenantContext::set($c['institute']->id);

        $preview = app(AcademicResultAggregationService::class)->preview($scheme, $c['subjects']['math']->id);

        // Arts-group student not in the science-group scheme -> placement not eligible
        $this->assertEmpty($preview['rows']);
        $this->assertTrue($preview['weights_valid']);
    }

    public function test_wrong_class_student_is_excluded(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $final = $this->assessment($c, 'Final', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $scheme = $this->schemeFor($c, [$mid->id => 40, $final->id => 60]);

        $c9 = $this->classGrade($this->level($this->system($this->country('BD', 'Bangladesh')), 'secondary'), 'c9');

        $student = $this->student($c['institute'], 'Class Nine Kid');
        $placement = StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $student->id,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c9->id,
            'status' => 'active',
        ]);
        StudentSubjectSelection::create(['institute_id' => $c['institute']->id, 'academic_placement_id' => $placement->id, 'subject_id' => $c['subjects']['math']->id, 'is_selected' => true, 'is_mandatory' => false]);

        TenantContext::set($c['institute']->id);

        $preview = app(AcademicResultAggregationService::class)->preview($scheme, $c['subjects']['math']->id);

        $this->assertEmpty($preview['rows']);
    }

    // ------------------------------------------------------------- Security

    public function test_branch_scheme_is_hidden_from_other_branch_admin(): void
    {
        $c = $this->curriculum();
        $branchA = $this->branch($c['institute'], 'Branch A');
        $branchB = $this->branch($c['institute'], 'Branch B');
        $adminB = $this->user($c['institute'], 'institute-admin', 'ag-admin-b', $branchB);

        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => $branchA->id,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'name' => 'Annual',
            'status' => 'draft',
        ]);

        TenantContext::set($c['institute']->id);
        BranchContext::set($branchB->id);

        $this->actingAs($adminB, 'institute_user')
            ->get(route('settings.academic.aggregations.show', $scheme))
            ->assertStatus(404);

        $this->actingAs($adminB, 'institute_user')
            ->put(route('settings.academic.aggregations.update', $scheme), [
                'academic_year_id' => $this->defaultYear($c['institute'])->id,
                'class_grade_id' => $c['class_grade']->id,
                'academic_group_id' => $c['group']->id,
                'name' => 'Annual',
                'status' => 'active',
                'items' => [],
            ])
            ->assertStatus(404);
    }

    public function test_whole_institute_scheme_is_visible_to_branch_admin(): void
    {
        $c = $this->curriculum();
        $branchB = $this->branch($c['institute'], 'Branch B');
        $adminB = $this->user($c['institute'], 'institute-admin', 'ag-admin-b2', $branchB);

        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'name' => 'Annual',
            'status' => 'active',
        ]);

        TenantContext::set($c['institute']->id);
        BranchContext::set($branchB->id);

        $this->actingAs($adminB, 'institute_user')
            ->get(route('settings.academic.aggregations.show', $scheme))
            ->assertOk()
            ->assertSee('Annual');
    }

    public function test_other_institute_admin_cannot_see_scheme(): void
    {
        $c = $this->curriculum();
        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $c['institute']->id,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'name' => 'Annual',
            'status' => 'draft',
        ]);

        $other = $this->institute($this->country('IN', 'India'), 'Other Agg Inst 2');
        $otherAdmin = $this->user($other, 'institute-admin', 'ag-other');

        TenantContext::set($other->id);

        $this->actingAs($otherAdmin, 'institute_user')
            ->get(route('settings.academic.aggregations.show', $scheme))
            ->assertStatus(404);
    }

    public function test_admin_without_education_manage_permission_is_blocked(): void
    {
        $c = $this->curriculum();
        $teacher = $this->user($c['institute'], 'teacher', 'ag-teacher');

        TenantContext::set($c['institute']->id);

        $this->actingAs($teacher, 'institute_user')
            ->get(route('settings.academic.aggregations.index'))
            ->assertForbidden();

        $this->actingAs($teacher, 'institute_user')
            ->post(route('settings.academic.aggregations.store'), [
                'academic_year_id' => $this->defaultYear($c['institute'])->id,
                'class_grade_id' => $c['class_grade']->id,
                'name' => 'Annual',
                'status' => 'draft',
                'items' => [],
            ])
            ->assertForbidden();
    }

    public function test_forged_institute_id_is_ignored(): void
    {
        $c = $this->curriculum();
        $other = $this->institute($this->country('IN', 'India'), 'Other Agg Inst 3');
        $a = $this->assessment($c, 'A', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.aggregations.store'), [
                'institute_id' => $other->id,
                'academic_year_id' => $this->defaultYear($c['institute'])->id,
                'class_grade_id' => $c['class_grade']->id,
                'academic_group_id' => $c['group']->id,
                'name' => 'Annual',
                'status' => 'draft',
                'items' => [['assessment_id' => $a->id, 'weight' => 100]],
            ])
            ->assertRedirect();

        $scheme = AcademicResultAggregationScheme::where('name', 'Annual')->firstOrFail();
        $this->assertSame($c['institute']->id, $scheme->institute_id);
    }

    public function test_forged_branch_id_is_ignored_branch_comes_from_user(): void
    {
        $c = $this->curriculum();
        $branchA = $this->branch($c['institute'], 'Branch A');
        $otherBranch = $this->branch($c['institute'], 'Other Branch');
        $adminA = $this->user($c['institute'], 'institute-admin', 'ag-admin-a', $branchA);
        $a = $this->assessment($c, 'A', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);

        TenantContext::set($c['institute']->id);
        BranchContext::set($branchA->id);

        $this->actingAs($adminA, 'institute_user')
            ->post(route('settings.academic.aggregations.store'), [
                'branch_id' => $otherBranch->id,
                'academic_year_id' => $this->defaultYear($c['institute'])->id,
                'class_grade_id' => $c['class_grade']->id,
                'academic_group_id' => $c['group']->id,
                'name' => 'Annual',
                'status' => 'draft',
                'items' => [['assessment_id' => $a->id, 'weight' => 100]],
            ])
            ->assertRedirect();

        $scheme = AcademicResultAggregationScheme::where('name', 'Annual')->firstOrFail();
        $this->assertSame($branchA->id, $scheme->branch_id);
    }

    public function test_cross_tenant_assessment_is_rejected_by_context(): void
    {
        $c = $this->curriculum();
        $other = $this->institute($this->country('IN', 'India'), 'Other Agg Inst 4');

        // Foreign assessment in the other institute — even if its class/group/ids
        // accidentally matched, it cannot be found in the current institute's scope.
        $otherAdmin = $this->user($other, 'institute-owner', 'ag-other4');
        $otherYear = AcademicYear::create(['institute_id' => $other->id, 'name' => 'OY', 'code' => '2026', 'is_current' => true, 'status' => true]);
        $foreignAssessment = AcademicAssessment::create([
            'institute_id' => $other->id,
            'academic_year_id' => $otherYear->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => null,
            'name' => 'Foreign Mid',
            'status' => 'draft',
            'display_order' => 1,
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.aggregations.store'), [
                'academic_year_id' => $this->defaultYear($c['institute'])->id,
                'class_grade_id' => $c['class_grade']->id,
                'academic_group_id' => $c['group']->id,
                'name' => 'Annual',
                'status' => 'draft',
                'items' => [['assessment_id' => $foreignAssessment->id, 'weight' => 100]],
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('academic_result_aggregation_schemes', 0);
    }

    // ------------------------------------------------------------- Pages

    public function test_index_create_and_preview_pages_render(): void
    {
        $c = $this->curriculum();
        $mid = $this->assessment($c, 'Mid', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $final = $this->assessment($c, 'Final', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $scheme = $this->schemeFor($c, [$mid->id => 40, $final->id => 60]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id], $c['group']);
        $this->mark($placement, $this->mathConfig($mid, $c['subjects']['math']->id), $this->writtenComponent(), 72);
        $this->mark($placement, $this->mathConfig($final, $c['subjects']['math']->id), $this->writtenComponent(), 81);

        TenantContext::set($c['institute']->id);
        BranchContext::clear();

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.aggregations.index'))
            ->assertOk()
            ->assertSee('Annual');

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.aggregations.create'))
            ->assertOk()
            ->assertSee('New Aggregation Scheme');

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.aggregations.show', $scheme))
            ->assertOk()
            ->assertSee('Configured Weightage')
            ->assertSee('Mid')
            ->assertSee('Final');

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.aggregations.show', ['scheme' => $scheme, 'subject_id' => $c['subjects']['math']->id]))
            ->assertOk()
            ->assertSee('Rahim Student')
            ->assertSee('77.4');
    }

    // ------------------------------------------------------------- Regression

    public function test_aggregation_preserves_marks_and_assessments(): void
    {
        $c = $this->curriculum();

        $mid = $this->assessment($c, 'Mid', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $final = $this->assessment($c, 'Final', [['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->writtenComponent()->id, 'full_mark' => 100, 'pass_mark' => 33]]]]);
        $scheme = $this->schemeFor($c, [$mid->id => 40, $final->id => 60]);

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, [$c['subjects']['math']->id]);
        $this->mark($placement, $this->mathConfig($mid, $c['subjects']['math']->id), $this->writtenComponent(), 72);
        $this->mark($placement, $this->mathConfig($final, $c['subjects']['math']->id), $this->writtenComponent(), 81);

        TenantContext::set($c['institute']->id);

        $before = $this->assertDatabaseCountSettings();

        $agg = app(AcademicResultAggregationService::class)->subjectAggregate($scheme, $placement, $c['subjects']['math']->id);
        $this->assertSame(AcademicResultAggregationService::SUBJECT_AGGREGATE_COMPUTED, $agg['status']);

        // Scheme + item created, but marks and assessments unchanged in count and value.
        $this->assertSame($before['marks'], $c['institute']->id === null ? 0 : AcademicStudentMark::count());
        $this->assertSame($before['assessments'], AcademicAssessment::count());
        $this->assertDatabaseHas('academic_student_marks', [
            'obtained_mark' => 72,
        ]);
        $mark = AcademicStudentMark::where('obtained_mark', 72)->firstOrFail();
        $this->assertSame(72.0, (float) $mark->obtained_mark);
    }
}
