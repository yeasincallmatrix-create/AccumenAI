<?php

namespace Tests\Feature;

use App\Models\AcademicAssessment;
use App\Models\AcademicFinalResultRow;
use App\Models\AcademicFinalResultStudent;
use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
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
use App\Services\AcademicGradingService;
use App\Services\AcademicResultAggregationService;
use App\Services\AcademicResultReadinessService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Database\Seeders\AcademicAssessmentSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Step 27 — Academic result-readiness / assessment-completion.
 *
 * Read-only layer between marks entry and final-result generation. The suite
 * verifies the coverage categories (complete / incomplete / absent / missing /
 * no assessment record), the READY / READY WITH EXCEPTIONS / NOT READY summary,
 * tenant + branch isolation, permission gating and that no database record is
 * ever mutated — including frozen final-result snapshots.
 */
class AcademicResultReadinessTest extends TestCase
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

    private function classGrade(AcademicLevel $level, string $code = 'rr-c8'): ClassGrade
    {
        return ClassGrade::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $level->country_id, 'education_system_id' => $level->education_system_id, 'academic_level_id' => $level->id, 'code' => $code],
            ['name' => 'Class 8', 'display_order' => 0, 'status' => true]
        );
    }

    private function group(ClassGrade $classGrade, string $code = 'rr-sci'): AcademicGroup
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

    private function institute(Country $country, string $name = 'RR Inst'): Institute
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
            'first_name' => 'RR',
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    /**
     * @param  string|null  $firstName  overrides the default name so CSV/scoping
     *                                  assertions can tell students apart
     */
    private function student(Institute $institute, string $firstName = 'Rahim', ?Branch $branch = null): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'student_id_number' => 'RR'.mt_rand(100000, 999999),
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

    /**
     * Mathematics has TWO components (written 70 + mcq 30) so partial entry is
     * possible; English has ONE component (written 100).
     */
    private function curriculum(): array
    {
        $country = $this->country();
        $classGrade = $this->classGrade($this->level($this->system($country)));
        $institute = $this->institute($country);
        $math = $this->subject('Mathematics', 'RR100001');
        $english = $this->subject('English', 'RR100002');

        $this->assign($math, $classGrade, 'mandatory', 1);
        $this->assign($english, $classGrade, 'mandatory', 2);

        return [
            'country' => $country,
            'class_grade' => $classGrade,
            'group' => $this->group($classGrade),
            'institute' => $institute,
            'owner' => $this->user($institute, 'institute-owner', 'rr-owner'),
            'math' => $math,
            'english' => $english,
        ];
    }

    private function assessment(array $c, string $name = 'Mid Term 2026', ?Branch $branch = null): AcademicAssessment
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

        // Mathematics: written 70 + mcq 30.
        $math = $this->addSubject($assessment, $c['math']->id, 1);
        $this->addComponent($math, $written->id, 70, 23);
        $this->addComponent($math, $mcq->id, 30, 10);

        // English: written 100.
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

    /**
     * Institute-wide grade scale so the Step-29 pre-flight generation gate
     * (Step 30) passes for fixtures that start a cycle.
     */
    private function generationScale(Institute $institute): GradeScale
    {
        return app(AcademicGradingService::class)->store([
            'name' => 'Readiness Scale',
            'institute_id' => $institute->id,
            'gpa_mode' => 'equal_weight',
            'optional_subject_gpa' => 'included',
            'status' => true,
            'display_order' => 1,
        ], [
            ['grade' => 'A+', 'min_score' => 80, 'max_score' => 100, 'grade_point' => 5.0, 'is_pass' => true, 'gpa_included' => true],
            ['grade' => 'F', 'min_score' => 0, 'max_score' => 79.99, 'grade_point' => 0.0, 'is_pass' => false, 'gpa_included' => true],
        ]);
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

    private function readiness(Institute $institute, AcademicAssessment $assessment): array
    {
        TenantContext::set($institute->id);

        return app(AcademicResultReadinessService::class)->forAssessment($assessment);
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

    public function test_authorized_user_can_view_readiness_for_complete_assessment(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c, 'Final 2026');
        $math = $assessment->subjects()->where('subject_id', $c['math']->id)->firstOrFail();
        $english = $assessment->subjects()->where('subject_id', $c['english']->id)->firstOrFail();

        $one = $this->placement($c, $this->student($c['institute']));
        $two = $this->placement($c, $this->student($c['institute']));

        foreach ([$one, $two] as $placement) {
            $this->mark($placement, $math, 80);
            $this->mark($placement, $math, 15, 'entered', 1);
            $this->mark($placement, $english, 60);
        }

        TenantContext::set($c['institute']->id);

        $response = $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.assessments.readiness', $assessment))
            ->assertOk();

        $response->assertSee('READY')
            ->assertSee('Eligible Students')
            ->assertSee('Complete')
            ->assertSee($one->student->full_name)
            ->assertSee($two->student->full_name)
            ->assertSee('Mathematics')
            ->assertSee('English');

        $data = $this->readiness($c['institute'], $assessment);
        $this->assertTrue($data['is_ready']);
        $this->assertSame('ready', $data['readiness']);
        $this->assertSame(2, $data['summary']['eligible_students']);
        $this->assertSame(2, $data['summary']['complete']);
        $this->assertSame(0, $data['summary']['absent']);
        $this->assertSame(0, $data['summary']['missing']);
        $this->assertSame(0, $data['summary']['incomplete']);
        $this->assertSame(0, $data['summary']['no_assessment']);
        $this->assertCount(0, $data['subjects_with_missing_marks']);
    }

    public function test_staff_without_education_manage_is_blocked(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c);
        $teacher = $this->user($c['institute'], 'teacher', 'rr-teacher');

        TenantContext::set($c['institute']->id);

        $this->actingAs($teacher, 'institute_user')
            ->get(route('settings.academic.assessments.readiness', $assessment))
            ->assertForbidden();

        $this->actingAs($teacher, 'institute_user')
            ->get(route('settings.academic.assessments.readiness.export', $assessment))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c);

        $this->get(route('settings.academic.assessments.readiness', $assessment))->assertRedirect();
        $this->get(route('settings.academic.assessments.readiness.export', $assessment))->assertRedirect();
    }

    public function test_cross_tenant_access_is_404(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c);

        $other = $this->institute($this->country('IN'), 'Other RR Inst');
        $otherAdmin = $this->user($other, 'institute-admin', 'rr-other');

        TenantContext::set($other->id);

        $this->actingAs($otherAdmin, 'institute_user')
            ->get(route('settings.academic.assessments.readiness', $assessment))
            ->assertStatus(404);

        $this->actingAs($otherAdmin, 'institute_user')
            ->get(route('settings.academic.assessments.readiness.export', $assessment))
            ->assertStatus(404);
    }

    public function test_cross_branch_access_is_404(): void
    {
        $c = $this->curriculum();
        $branchA = $this->branch($c['institute'], 'Branch A');
        $branchB = $this->branch($c['institute'], 'Branch B');
        $adminB = $this->user($c['institute'], 'institute-admin', 'rr-admin-b', $branchB);

        $assessment = $this->assessment($c, 'Branch A Assessment', $branchA);

        TenantContext::set($c['institute']->id);
        BranchContext::set($branchB->id);

        $this->actingAs($adminB, 'institute_user')
            ->get(route('settings.academic.assessments.readiness', $assessment))
            ->assertStatus(404);

        $this->actingAs($adminB, 'institute_user')
            ->get(route('settings.academic.assessments.readiness.export', $assessment))
            ->assertStatus(404);
    }

    public function test_missing_marks_marks_assessment_not_ready(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c);
        $math = $assessment->subjects()->where('subject_id', $c['math']->id)->firstOrFail();
        $english = $assessment->subjects()->where('subject_id', $c['english']->id)->firstOrFail();

        $placement = $this->placement($c, $this->student($c['institute']));
        // English fully entered, Mathematics completely unrecorded.
        $this->mark($placement, $math, 80);
        $this->mark($placement, $math, 15, 'entered', 1);
        $this->mark($placement, $english, 60);

        $missing = $this->placement($c, $this->student($c['institute'], 'Missing'));
        // No Mathematics marks at all (English entered only).
        $this->mark($missing, $english, 70);

        $data = $this->readiness($c['institute'], $assessment);
        $this->assertFalse($data['is_ready']);
        $this->assertSame('not_ready', $data['readiness']);
        $this->assertSame(2, $data['summary']['eligible_students']);
        $this->assertSame(1, $data['summary']['complete']);
        $this->assertSame(1, $data['summary']['missing']);
        $this->assertCount(1, $data['subjects_with_missing_marks']);
        $this->assertSame('Mathematics', $data['subjects_with_missing_marks'][0]['name']);
        $this->assertSame(1, $data['subjects_with_missing_marks'][0]['missing']);
    }

    public function test_absent_is_not_treated_as_missing(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c);
        $math = $assessment->subjects()->where('subject_id', $c['math']->id)->firstOrFail();
        $english = $assessment->subjects()->where('subject_id', $c['english']->id)->firstOrFail();

        $placement = $this->placement($c, $this->student($c['institute']));
        $this->mark($placement, $math, 0, 'absent', 0);
        $this->mark($placement, $math, 0, 'absent', 1);
        $this->mark($placement, $english, 0, 'absent');

        $complete = $this->placement($c, $this->student($c['institute'], 'Complete'));
        $this->mark($complete, $math, 90);
        $this->mark($complete, $math, 20, 'entered', 1);
        $this->mark($complete, $english, 80);

        $data = $this->readiness($c['institute'], $assessment);

        // The fully-absent student is legitimately ABSENT — never missing.
        $this->assertSame('ready_with_exceptions', $data['readiness']);
        $this->assertFalse($data['is_ready']);
        $this->assertSame(1, $data['summary']['absent']);
        $this->assertSame(1, $data['summary']['complete']);
        $this->assertSame(0, $data['summary']['missing']);
        $this->assertSame(0, $data['summary']['incomplete']);
        $this->assertCount(0, $data['subjects_with_missing_marks']);
    }

    public function test_incomplete_is_distinguished_from_missing_and_no_assessment(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c);
        $math = $assessment->subjects()->where('subject_id', $c['math']->id)->firstOrFail();
        $english = $assessment->subjects()->where('subject_id', $c['english']->id)->firstOrFail();

        // Partial: only the written component of Mathematics, English complete.
        $partial = $this->placement($c, $this->student($c['institute'], 'Partial'));
        $this->mark($partial, $math, 40, 'entered', 0);
        $this->mark($partial, $english, 60);

        // Missing: English complete, Mathematics entirely unrecorded.
        $missing = $this->placement($c, $this->student($c['institute'], 'Missing'));
        $this->mark($missing, $english, 60);

        // No assessment record at all.
        $noRecord = $this->placement($c, $this->student($c['institute'], 'NoRecord'));

        $data = $this->readiness($c['institute'], $assessment);
        $this->assertSame('not_ready', $data['readiness']);
        $this->assertSame(1, $data['summary']['incomplete']);
        $this->assertSame(1, $data['summary']['missing']);
        $this->assertSame(1, $data['summary']['no_assessment']);
        $this->assertSame(0, $data['summary']['absent']);

        $statusById = [];
        foreach ($data['rows'] as $row) {
            $statusById[$row['student']->id] = $row['status'];
        }
        $this->assertSame('incomplete', $statusById[$partial->student_id]);
        $this->assertSame('missing', $statusById[$missing->student_id]);
        $this->assertSame('no_assessment', $statusById[$noRecord->student_id]);
    }

    public function test_multiple_subjects_are_summarised_per_subject(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c);
        $math = $assessment->subjects()->where('subject_id', $c['math']->id)->firstOrFail();
        $english = $assessment->subjects()->where('subject_id', $c['english']->id)->firstOrFail();

        $placement = $this->placement($c, $this->student($c['institute']));
        $this->mark($placement, $math, 30, 'entered', 0); // mcq unrecorded → incomplete
        $this->mark($placement, $english, 0, 'absent');

        $data = $this->readiness($c['institute'], $assessment);

        $summary = collect($data['subject_summary'])->keyBy('name');
        $this->assertSame(2, $data['subjects_included']);
        $this->assertSame(1, $summary['Mathematics']['incomplete']);
        $this->assertSame(1, $summary['English']['eligible']);
        $this->assertSame(1, $summary['English']['absent']);
    }

    public function test_readiness_does_not_mutate_any_database_records(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c);
        $math = $assessment->subjects()->where('subject_id', $c['math']->id)->firstOrFail();
        $english = $assessment->subjects()->where('subject_id', $c['english']->id)->firstOrFail();

        $placement = $this->placement($c, $this->student($c['institute']));
        $this->mark($placement, $math, 80);
        $this->mark($placement, $math, 15, 'entered', 1);
        $this->mark($placement, $english, 60);

        $this->assertDatabaseCount('academic_student_marks', 3);
        $this->assertDatabaseCount('student_academic_placements', 1);
        $this->assertDatabaseCount('academic_assessments', 1);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.assessments.readiness', $assessment))
            ->assertOk();

        $this->assertDatabaseCount('academic_student_marks', 3);
        $this->assertDatabaseCount('student_academic_placements', 1);
        $this->assertDatabaseCount('academic_assessments', 1);
        $this->assertDatabaseCount('academic_final_result_students', 0);
        $this->assertDatabaseCount('academic_final_result_rows', 0);
        $this->assertDatabaseHas('academic_student_marks', ['obtained_mark' => 80.0]);
    }

    public function test_readiness_does_not_touch_published_final_result_snapshots(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c);
        $math = $assessment->subjects()->where('subject_id', $c['math']->id)->firstOrFail();
        $english = $assessment->subjects()->where('subject_id', $c['english']->id)->firstOrFail();

        $placement = $this->placement($c, $this->student($c['institute']));
        $this->mark($placement, $math, 80);
        $this->mark($placement, $math, 15, 'entered', 1);
        $this->mark($placement, $english, 60);

        $scheme = app(AcademicResultAggregationService::class)->store(
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
            [['assessment_id' => $assessment->id, 'weight' => 100]]
        );

        TenantContext::set($c['institute']->id);
        $this->generationScale($c['institute']);

        $lifecycle = app(AcademicFinalResultLifecycleService::class);
        $policy = $lifecycle->policyForScheme($c['institute'], $scheme);
        $policy->update(['require_approval' => false]);
        $result = $lifecycle->createResult($c['institute'], $policy, 'Term Final 2026', (int) $c['owner']->id);
        $lifecycle->lock($result, (int) $c['owner']->id);

        $this->assertDatabaseCount('academic_final_result_students', 1);
        $this->assertDatabaseCount('academic_final_result_rows', 2);

        $gpa = AcademicFinalResultStudent::where('result_id', $result->id)->firstOrFail()->gpa;

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.assessments.readiness', $assessment))
            ->assertOk();

        $this->assertDatabaseCount('academic_final_result_students', 1);
        $this->assertDatabaseCount('academic_final_result_rows', 2);
        $this->assertSame($gpa, AcademicFinalResultStudent::where('result_id', $result->id)->firstOrFail()->gpa);
        $this->assertSame(2, AcademicFinalResultRow::where('result_id', $result->id)->count());
    }

    public function test_readiness_matrix_is_bulk_loaded_without_n_plus_1(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c);
        $math = $assessment->subjects()->where('subject_id', $c['math']->id)->firstOrFail();
        $english = $assessment->subjects()->where('subject_id', $c['english']->id)->firstOrFail();

        foreach (['A', 'B', 'C', 'D'] as $name) {
            $placement = $this->placement($c, $this->student($c['institute'], $name));
            $this->mark($placement, $math, 80);
            $this->mark($placement, $math, 15, 'entered', 1);
            $this->mark($placement, $english, 60);
        }

        DB::enableQueryLog();
        app(AcademicResultReadinessService::class)->forAssessment($assessment);

        $marksSelects = collect(DB::getQueryLog())
            ->filter(fn ($query) => str_contains($query['query'], 'academic_student_marks') && str_starts_with(ltrim($query['query']), 'select'))
            ->count();

        $this->assertSame(1, $marksSelects);
    }

    public function test_csv_export_lists_only_exceptions_and_escapes_values(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c);
        $math = $assessment->subjects()->where('subject_id', $c['math']->id)->firstOrFail();
        $english = $assessment->subjects()->where('subject_id', $c['english']->id)->firstOrFail();

        // Student with a comma + quote in the name, missing Mathematics marks.
        $tricky = $this->student($c['institute'], 'Karim, "Tricky"');
        $trickyPlacement = $this->placement($c, $tricky);
        $this->mark($trickyPlacement, $english, 60);

        // Complete student must NOT appear in the exceptions export.
        $ok = $this->placement($c, $this->student($c['institute'], 'Fine'));
        $this->mark($ok, $math, 80);
        $this->mark($ok, $math, 15, 'entered', 1);
        $this->mark($ok, $english, 60);

        TenantContext::set($c['institute']->id);

        $response = $this->actingAs($c['owner'], 'institute_user')
            ->get(route('settings.academic.assessments.readiness.export', $assessment))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $all = $this->parseCsv($this->csvContent($response));
        $this->assertSame(
            ['Student', 'Student ID', 'Registration Number', 'Subject', 'Status', 'Issue'],
            $all[0]
        );
        $this->assertCount(2, $all); // header + one exception row (Mathematics for the tricky student)

        $row = $all[1];
        $this->assertSame('Karim, "Tricky" Student', $row[0]);
        $this->assertSame('Mathematics', $row[3]);
        $this->assertSame('Missing marks', $row[4]);
        $this->assertSame('No marks recorded for this subject.', $row[5]);
        $this->assertNotSame($ok->student->full_name, $row[0]);
    }
}
