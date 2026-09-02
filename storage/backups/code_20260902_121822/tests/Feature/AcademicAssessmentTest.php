<?php

namespace Tests\Feature;

use App\Models\AcademicAssessment;
use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicSelectionGroup;
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
use App\Models\Subject;
use App\Models\SubjectAcademicAssignment;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Database\Seeders\AcademicAssessmentSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AcademicAssessmentTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AcademicAssessmentSeeder::class);
    }

    // ------------------------------------------------------------- Setup data

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

    private function classGrade(AcademicLevel $level, string $code = 'c8'): ClassGrade
    {
        return ClassGrade::create([
            'country_id' => $level->country_id,
            'education_system_id' => $level->education_system_id,
            'academic_level_id' => $level->id,
            'name' => 'Class 8',
            'code' => $code,
            'display_order' => 0,
            'status' => true,
        ]);
    }

    private function group(ClassGrade $classGrade, string $code = 'sci'): AcademicGroup
    {
        return AcademicGroup::create([
            'country_id' => $classGrade->country_id,
            'education_system_id' => $classGrade->education_system_id,
            'academic_level_id' => $classGrade->academic_level_id,
            'class_grade_id' => $classGrade->id,
            'name' => 'Science',
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

    private function institute(Country $country, string $name = 'Assess Inst'): Institute
    {
        return Institute::create([
            'name' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
            'industry' => 'education',
            'sub_industry' => 'school',
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
            'email_verified_at' => now(),
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

    /**
     * Reuse the institute's default 2026 year instead of creating duplicates,
     * which would collide with the (institute_id, code) unique key. Global
     * scopes are ignored so this stays correct even when the current
     * TenantContext belongs to a different institute.
     */
    private function defaultYear(Institute $institute): AcademicYear
    {
        return AcademicYear::withoutGlobalScopes()->firstOrCreate(
            ['institute_id' => $institute->id, 'code' => '2026'],
            ['name' => 'Academic Year 2026', 'is_current' => true, 'status' => true]
        );
    }

    private function type(string $slug = 'mid-term'): AssessmentType
    {
        return AssessmentType::where('slug', $slug)->firstOrFail();
    }

    private function componentBySlug(string $slug = 'written'): Component
    {
        return Component::where('slug', $slug)->firstOrFail();
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
        $owner = $this->user($institute, 'institute-owner', 'aa-owner');

        $bangla = $this->subject('Bangla', 'AA100001');
        $english = $this->subject('English', 'AA100002');
        $math = $this->subject('Mathematics', 'AA100003');
        $bio = $this->subject('Biology', 'AA100004');
        $hmath = $this->subject('Higher Mathematics', 'AA100005');

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
     * @return array<string, mixed>
     */
    private function payload(array $c, array $overrides = []): array
    {
        $default = [
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'assessment_type_id' => $this->type()->id,
            'name' => 'Mid Term 2026',
            'exam_date' => '2026-06-01',
            'status' => 'scheduled',
            'notes' => 'First assessment',
        ];

        return array_merge($default, $overrides);
    }

    // ------------------------------------------------------------- Create

    public function test_owner_creates_assessment_with_subjects_and_components(): void
    {
        $c = $this->curriculum();
        $payload = $this->payload($c, [
            'subjects' => [
                ['subject_id' => $c['subjects']['math']->id, 'components' => [
                    ['component_id' => $this->componentBySlug('written')->id, 'full_mark' => 70, 'pass_mark' => 23],
                    ['component_id' => $this->componentBySlug('mcq')->id, 'full_mark' => 30, 'pass_mark' => 10],
                ]],
                ['subject_id' => $c['subjects']['english']->id, 'components' => [
                    ['component_id' => $this->componentBySlug('written')->id, 'full_mark' => 100, 'pass_mark' => 33],
                ]],
            ],
        ]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.assessments.store'), $payload)
            ->assertRedirect();

        $assessment = AcademicAssessment::where('name', 'Mid Term 2026')->firstOrFail();

        $this->assertSame($c['institute']->id, $assessment->institute_id);
        $this->assertNull($assessment->branch_id);
        $this->assertSame($c['class_grade']->id, $assessment->class_grade_id);
        $this->assertSame($c['group']->id, $assessment->academic_group_id);
        $this->assertSame($this->type()->id, $assessment->assessment_type_id);
        $this->assertSame('scheduled', $assessment->status);

        $this->assertSame(2, $assessment->subjects()->count());

        $mathConfig = AssessmentSubject::where('assessment_id', $assessment->id)->where('subject_id', $c['subjects']['math']->id)->firstOrFail();
        $this->assertSame(2, $mathConfig->components()->count());
        $this->assertSame(100.0, $mathConfig->totalFullMark());

        $this->assertDatabaseHas('assessment_subject_components', [
            'assessment_subject_id' => $mathConfig->id,
            'component_id' => $this->componentBySlug('written')->id,
            'full_mark' => 70,
            'pass_mark' => 23,
        ]);
    }

    public function test_display_order_auto_increments(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute']);
        $p1 = $this->payload($c, ['name' => 'First Term', 'subjects' => [
            ['subject_id' => $c['subjects']['bangla']->id, 'components' => [
                ['component_id' => $this->componentBySlug('written')->id, 'full_mark' => 100, 'pass_mark' => 33],
            ]],
        ]]);
        $p2 = $this->payload($c, ['name' => 'Second Term', 'subjects' => [
            ['subject_id' => $c['subjects']['english']->id, 'components' => [
                ['component_id' => $this->componentBySlug('written')->id, 'full_mark' => 100, 'pass_mark' => 33],
            ]],
        ]]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')->post(route('settings.academic.assessments.store'), $p1)->assertRedirect();
        $this->actingAs($c['owner'], 'institute_user')->post(route('settings.academic.assessments.store'), $p2)->assertRedirect();

        $first = AcademicAssessment::where('name', 'First Term')->firstOrFail();
        $second = AcademicAssessment::where('name', 'Second Term')->firstOrFail();

        $this->assertSame(1, $first->display_order);
        $this->assertSame(2, $second->display_order);
        $this->assertSame($year->id, $first->academic_year_id);
    }

    public function test_year_over_year_assessments_are_separate(): void
    {
        $c = $this->curriculum();
        $year2026 = $this->year($c['institute'], '2026');
        $year2027 = $this->year($c['institute'], '2027', 'Academic Year 2027');

        TenantContext::set($c['institute']->id);

        $p1 = $this->payload($c, ['academic_year_id' => $year2026->id, 'name' => 'Mid Term', 'subjects' => [
            ['subject_id' => $c['subjects']['math']->id, 'components' => [
                ['component_id' => $this->componentBySlug('written')->id, 'full_mark' => 100, 'pass_mark' => 33],
            ]],
        ]]);
        $p2 = $this->payload($c, ['academic_year_id' => $year2027->id, 'name' => 'Mid Term', 'subjects' => [
            ['subject_id' => $c['subjects']['math']->id, 'components' => [
                ['component_id' => $this->componentBySlug('written')->id, 'full_mark' => 100, 'pass_mark' => 33],
            ]],
        ]]);

        $this->actingAs($c['owner'], 'institute_user')->post(route('settings.academic.assessments.store'), $p1)->assertRedirect();
        $this->actingAs($c['owner'], 'institute_user')->post(route('settings.academic.assessments.store'), $p2)->assertRedirect();

        $this->assertSame(2, AcademicAssessment::where('name', 'Mid Term')->count());
        $this->assertNotSame(
            AcademicAssessment::where('academic_year_id', $year2026->id)->firstOrFail()->id,
            AcademicAssessment::where('academic_year_id', $year2027->id)->firstOrFail()->id
        );
    }

    // ------------------------------------------------------------- Validation

    public function test_duplicate_subject_is_rejected(): void
    {
        $c = $this->curriculum();
        $comp = $this->componentBySlug('written');

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.assessments.store'), $this->payload($c, [
                'subjects' => [
                    ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $comp->id, 'full_mark' => 100, 'pass_mark' => 33]]],
                    ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $comp->id, 'full_mark' => 50, 'pass_mark' => 17]]],
                ],
            ]))
            ->assertSessionHasErrors('subjects.1');

        $this->assertDatabaseCount('academic_assessments', 0);
    }

    public function test_no_subjects_is_rejected(): void
    {
        $c = $this->curriculum();

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.assessments.store'), $this->payload($c))
            ->assertSessionHasErrors('subjects');

        $this->assertDatabaseCount('academic_assessments', 0);
    }

    public function test_duplicate_component_within_subject_is_rejected(): void
    {
        $c = $this->curriculum();
        $comp = $this->componentBySlug('written');

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.assessments.store'), $this->payload($c, [
                'subjects' => [
                    ['subject_id' => $c['subjects']['math']->id, 'components' => [
                        ['component_id' => $comp->id, 'full_mark' => 60, 'pass_mark' => 20],
                        ['component_id' => $comp->id, 'full_mark' => 40, 'pass_mark' => 13],
                    ]],
                ],
            ]))
            ->assertSessionHasErrors('subjects.0.components.1.component_id');

        $this->assertDatabaseCount('academic_assessments', 0);
    }

    public function test_out_of_curriculum_subject_is_rejected(): void
    {
        $c = $this->curriculum();
        $foreign = $this->subject('Astronomy', 'AA109999');
        $comp = $this->componentBySlug('written');

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.assessments.store'), $this->payload($c, [
                'subjects' => [
                    ['subject_id' => $foreign->id, 'components' => [['component_id' => $comp->id, 'full_mark' => 100, 'pass_mark' => 33]]],
                ],
            ]))
            ->assertSessionHasErrors('subjects.0');

        $this->assertDatabaseCount('academic_assessments', 0);
    }

    public function test_pass_mark_greater_than_full_mark_is_rejected(): void
    {
        $c = $this->curriculum();
        $comp = $this->componentBySlug('written');

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.assessments.store'), $this->payload($c, [
                'subjects' => [
                    ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $comp->id, 'full_mark' => 100, 'pass_mark' => 150]]],
                ],
            ]))
            ->assertSessionHasErrors('subjects.0.components.0.pass_mark');

        $this->assertDatabaseCount('academic_assessments', 0);
    }

    public function test_zero_and_negative_full_mark_are_rejected(): void
    {
        $c = $this->curriculum();
        $comp = $this->componentBySlug('written');

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.assessments.store'), $this->payload($c, [
                'subjects' => [
                    ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $comp->id, 'full_mark' => 0, 'pass_mark' => 0]]],
                ],
            ]))
            ->assertSessionHasErrors('subjects.0.components.0.full_mark');

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.assessments.store'), $this->payload($c, [
                'subjects' => [
                    ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $comp->id, 'full_mark' => -5, 'pass_mark' => -1]]],
                ],
            ]))
            ->assertSessionHasErrors('subjects.0.components.0.full_mark');

        $this->assertDatabaseCount('academic_assessments', 0);
    }

    public function test_custom_institute_component_is_allowed_foreign_component_rejected(): void
    {
        $c = $this->curriculum();
        $mine = Component::create(['institute_id' => $c['institute']->id, 'name' => 'Custom', 'slug' => 'custom-'.uniqid(), 'display_order' => 99, 'status' => true]);
        $otherInstitute = $this->institute($this->country('IN', 'India'), 'Other Inst');
        $foreignComponent = Component::create(['institute_id' => $otherInstitute->id, 'name' => 'Foreign', 'slug' => 'foreign-'.uniqid(), 'display_order' => 99, 'status' => true]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.assessments.store'), $this->payload($c, [
                'subjects' => [
                    ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $mine->id, 'full_mark' => 100, 'pass_mark' => 40]]],
                ],
            ]))
            ->assertRedirect();

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.assessments.store'), $this->payload($c, [
                'name' => 'Foreign Component',
                'subjects' => [
                    ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $foreignComponent->id, 'full_mark' => 100, 'pass_mark' => 40]]],
                ],
            ]))
            ->assertSessionHasErrors('subjects.0.components.0.component_id');
    }

    public function test_foreign_assessment_type_is_rejected(): void
    {
        $c = $this->curriculum();
        $otherInstitute = $this->institute($this->country('IN', 'India'), 'Other Inst 2');
        $foreignType = AssessmentType::create(['institute_id' => $otherInstitute->id, 'name' => 'Foreign Type', 'slug' => 'foreign-type-'.uniqid(), 'display_order' => 99, 'status' => true]);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.assessments.store'), $this->payload($c, [
                'assessment_type_id' => $foreignType->id,
                'subjects' => [
                    ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->componentBySlug('written')->id, 'full_mark' => 100, 'pass_mark' => 33]]],
                ],
            ]))
            ->assertSessionHasErrors('assessment_type_id');

        $this->assertDatabaseCount('academic_assessments', 0);
    }

    // ------------------------------------------------------------- Update / Destroy

    public function test_update_replaces_subjects_and_components(): void
    {
        $c = $this->curriculum();
        $written = $this->componentBySlug('written');
        $mcq = $this->componentBySlug('mcq');

        $assessment = AcademicAssessment::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $this->year($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'assessment_type_id' => $this->type()->id,
            'name' => 'Mid Term 2026',
            'status' => 'scheduled',
            'display_order' => 1,
        ]);
        $subjectConfig = AssessmentSubject::create(['assessment_id' => $assessment->id, 'subject_id' => $c['subjects']['math']->id, 'display_order' => 1, 'status' => 'active']);
        AssessmentSubjectComponent::create(['assessment_subject_id' => $subjectConfig->id, 'component_id' => $written->id, 'full_mark' => 100, 'pass_mark' => 33, 'display_order' => 1, 'status' => 'active']);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->put(route('settings.academic.assessments.update', $assessment), $this->payload($c, [
                'name' => 'Mid Term 2026 (edited)',
                'status' => 'open',
                'subjects' => [
                    ['subject_id' => $c['subjects']['english']->id, 'components' => [
                        ['component_id' => $mcq->id, 'full_mark' => 40, 'pass_mark' => 12],
                        ['component_id' => $written->id, 'full_mark' => 60, 'pass_mark' => 21],
                    ]],
                ],
            ]))
            ->assertRedirect();

        $assessment->refresh();

        $this->assertSame('Mid Term 2026 (edited)', $assessment->name);
        $this->assertSame('open', $assessment->status);
        $this->assertSame(1, $assessment->subjects()->count());

        $englishConfig = $assessment->subjects()->where('subject_id', $c['subjects']['english']->id)->firstOrFail();
        $this->assertSame(2, $englishConfig->components()->count());
        $this->assertSame(100.0, $englishConfig->totalFullMark());

        $this->assertDatabaseMissing('assessment_subjects', ['assessment_id' => $assessment->id, 'subject_id' => $c['subjects']['math']->id]);
    }

    public function test_destroy_removes_assessment_and_configuration(): void
    {
        $c = $this->curriculum();
        $assessment = AcademicAssessment::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $this->year($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'assessment_type_id' => $this->type()->id,
            'name' => 'Mid Term 2026',
            'status' => 'draft',
            'display_order' => 1,
        ]);
        $subjectConfig = AssessmentSubject::create(['assessment_id' => $assessment->id, 'subject_id' => $c['subjects']['math']->id, 'display_order' => 1, 'status' => 'active']);
        AssessmentSubjectComponent::create(['assessment_subject_id' => $subjectConfig->id, 'component_id' => $this->componentBySlug('written')->id, 'full_mark' => 100, 'pass_mark' => 33, 'display_order' => 1, 'status' => 'active']);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->delete(route('settings.academic.assessments.destroy', $assessment))
            ->assertRedirect();

        $this->assertDatabaseMissing('academic_assessments', ['id' => $assessment->id]);
        $this->assertDatabaseMissing('assessment_subjects', ['assessment_id' => $assessment->id]);
        $this->assertDatabaseMissing('assessment_subject_components', ['assessment_subject_id' => $subjectConfig->id]);
    }

    // ------------------------------------------------------------- Security

    public function test_cross_tenant_academic_year_is_rejected(): void
    {
        $c = $this->curriculum();
        $otherInstitute = $this->institute($this->country('IN', 'India'), 'Other Inst 3');
        $otherYear = $this->year($otherInstitute, '2026');

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.assessments.store'), $this->payload($c, [
                'academic_year_id' => $otherYear->id,
                'subjects' => [
                    ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->componentBySlug('written')->id, 'full_mark' => 100, 'pass_mark' => 33]]],
                ],
            ]))
            ->assertSessionHasErrors('academic_year_id');

        $this->assertDatabaseCount('academic_assessments', 0);
    }

    public function test_forged_institute_id_is_ignored(): void
    {
        $c = $this->curriculum();
        $otherInstitute = $this->institute($this->country('IN', 'India'), 'Other Inst 4');

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.assessments.store'), $this->payload($c, [
                'institute_id' => $otherInstitute->id,
                'subjects' => [
                    ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->componentBySlug('written')->id, 'full_mark' => 100, 'pass_mark' => 33]]],
                ],
            ]))
            ->assertRedirect();

        $assessment = AcademicAssessment::where('name', 'Mid Term 2026')->firstOrFail();
        $this->assertSame($c['institute']->id, $assessment->institute_id);
    }

    public function test_forged_branch_id_is_ignored_branch_comes_from_user(): void
    {
        $c = $this->curriculum();
        $branchA = $this->branch($c['institute'], 'Branch A');
        $otherBranch = $this->branch($c['institute'], 'Branch Other');
        $adminA = $this->user($c['institute'], 'institute-admin', 'aa-admin-a', $branchA);

        TenantContext::set($c['institute']->id);
        BranchContext::set($branchA->id);

        $this->actingAs($adminA, 'institute_user')
            ->post(route('settings.academic.assessments.store'), $this->payload($c, [
                'branch_id' => $otherBranch->id,
                'subjects' => [
                    ['subject_id' => $c['subjects']['math']->id, 'components' => [['component_id' => $this->componentBySlug('written')->id, 'full_mark' => 100, 'pass_mark' => 33]]],
                ],
            ]))
            ->assertRedirect();

        $assessment = AcademicAssessment::where('name', 'Mid Term 2026')->firstOrFail();
        $this->assertSame($branchA->id, $assessment->branch_id);
    }

    public function test_branch_assessment_is_hidden_from_other_branch_admin(): void
    {
        $c = $this->curriculum();
        $branchA = $this->branch($c['institute'], 'Branch A');
        $branchB = $this->branch($c['institute'], 'Branch B');
        $adminB = $this->user($c['institute'], 'institute-admin', 'aa-admin-b', $branchB);

        $assessment = AcademicAssessment::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => $branchA->id,
            'academic_year_id' => $this->year($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'assessment_type_id' => $this->type()->id,
            'name' => 'Mid Term 2026',
            'status' => 'draft',
            'display_order' => 1,
        ]);

        TenantContext::set($c['institute']->id);
        BranchContext::set($branchB->id);

        $this->actingAs($adminB, 'institute_user')
            ->get(route('settings.academic.assessments.show', $assessment))
            ->assertStatus(404);

        $this->actingAs($adminB, 'institute_user')
            ->put(route('settings.academic.assessments.update', $assessment), $this->payload($c, ['status' => 'open']))
            ->assertStatus(404);
    }

    public function test_whole_institute_assessment_is_visible_to_branch_admin(): void
    {
        $c = $this->curriculum();
        $branchB = $this->branch($c['institute'], 'Branch B');
        $adminB = $this->user($c['institute'], 'institute-admin', 'aa-admin-b2', $branchB);

        $assessment = AcademicAssessment::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $this->year($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'assessment_type_id' => $this->type()->id,
            'name' => 'Final 2026',
            'status' => 'draft',
            'display_order' => 1,
        ]);
        $subjectConfig = AssessmentSubject::create(['assessment_id' => $assessment->id, 'subject_id' => $c['subjects']['math']->id, 'display_order' => 1, 'status' => 'active']);
        AssessmentSubjectComponent::create(['assessment_subject_id' => $subjectConfig->id, 'component_id' => $this->componentBySlug('written')->id, 'full_mark' => 100, 'pass_mark' => 33, 'display_order' => 1, 'status' => 'active']);

        TenantContext::set($c['institute']->id);
        BranchContext::set($branchB->id);

        $this->actingAs($adminB, 'institute_user')
            ->get(route('settings.academic.assessments.show', $assessment))
            ->assertOk()
            ->assertSee('Final 2026')
            ->assertSee('100 total marks');
    }

    public function test_visibility_for_other_institute_admin(): void
    {
        $c = $this->curriculum();
        $assessment = AcademicAssessment::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $this->year($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'assessment_type_id' => $this->type()->id,
            'name' => 'Mid Term 2026',
            'status' => 'draft',
            'display_order' => 1,
        ]);

        $otherInstitute = $this->institute($this->country('IN', 'India'), 'Other Inst 5');
        $otherAdmin = $this->user($otherInstitute, 'institute-admin', 'aa-other');

        TenantContext::set($otherInstitute->id);

        $this->actingAs($otherAdmin, 'institute_user')
            ->get(route('settings.academic.assessments.show', $assessment))
            ->assertStatus(404);

        $this->actingAs($otherAdmin, 'institute_user')
            ->put(route('settings.academic.assessments.update', $assessment), $this->payload($c, ['status' => 'open']))
            ->assertStatus(404);
    }

    public function test_admin_without_education_manage_permission_is_blocked(): void
    {
        $c = $this->curriculum();
        $teacher = $this->user($c['institute'], 'teacher', 'aa-teacher');

        TenantContext::set($c['institute']->id);

        $this->actingAs($teacher, 'institute_user')
            ->get(route('settings.academic.assessments.index'))
            ->assertForbidden();

        $this->actingAs($teacher, 'institute_user')
            ->get(route('settings.academic.assessments.create'))
            ->assertForbidden();
    }

    public function test_auth_required_for_assessment_routes(): void
    {
        $this->get(route('settings.academic.assessments.index'))->assertRedirect();
        $this->get(route('settings.academic.assessments.create'))->assertRedirect();
    }

    public function test_class_outside_institute_structure_is_rejected(): void
    {
        $c = $this->curriculum();
        $foreignClass = $this->classGrade($this->level($this->system($this->country('IN', 'India'))), 'c8x');

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.assessments.store'), $this->payload($c, [
                'class_grade_id' => $foreignClass->id,
            ]))
            ->assertSessionHasErrors('class_grade_id');

        $this->assertDatabaseCount('academic_assessments', 0);
    }

    // ------------------------------------------------------------- Pages

    public function test_index_create_show_and_subjects_pages_render(): void
    {
        $c = $this->curriculum();
        $assessment = AcademicAssessment::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $this->year($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'assessment_type_id' => $this->type()->id,
            'name' => 'Mid Term 2026',
            'status' => 'draft',
            'display_order' => 1,
        ]);
        $subjectConfig = AssessmentSubject::create(['assessment_id' => $assessment->id, 'subject_id' => $c['subjects']['math']->id, 'display_order' => 1, 'status' => 'active']);
        AssessmentSubjectComponent::create(['assessment_subject_id' => $subjectConfig->id, 'component_id' => $this->componentBySlug('written')->id, 'full_mark' => 70, 'pass_mark' => 23, 'display_order' => 1, 'status' => 'active']);
        AssessmentSubjectComponent::create(['assessment_subject_id' => $subjectConfig->id, 'component_id' => $this->componentBySlug('mcq')->id, 'full_mark' => 30, 'pass_mark' => 10, 'display_order' => 2, 'status' => 'active']);

        TenantContext::set($c['institute']->id);
        BranchContext::clear();

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.assessments.index'))
            ->assertOk()
            ->assertSee('Mid Term 2026');

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.assessments.create'))
            ->assertOk()
            ->assertSee('New Academic Assessment');

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.assessments.show', $assessment))
            ->assertOk()
            ->assertSee('Mathematics')
            ->assertSee('Written')
            ->assertSee('100 total marks');

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.assessments.subjects', ['assessment' => $assessment->id, 'class_grade_id' => $c['class_grade']->id, 'academic_group_id' => $c['group']->id]))
            ->assertOk()
            ->assertJsonPath('subjects.0.name', 'Bangla');
    }
}
