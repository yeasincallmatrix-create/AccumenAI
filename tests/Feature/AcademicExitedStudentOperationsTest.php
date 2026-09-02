<?php

namespace Tests\Feature;

use App\Models\AcademicAssessment;
use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicStudentMark;
use App\Models\AcademicYear;
use App\Models\AssessmentSubject;
use App\Models\AssessmentSubjectComponent;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Component;
use App\Models\Country;
use App\Models\Course;
use App\Models\EducationSystem;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\StudentEnrollment;
use App\Models\StudentSubjectSelection;
use App\Models\Subject;
use App\Models\SubjectAcademicAssignment;
use App\Services\AcademicResultAggregationService;
use App\Services\StudentAcademicExitService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Database\Seeders\AcademicAssessmentSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * General-Education finalization — exit safety (Step 35).
 *
 * Once a student is officially exited (current placement closed as dropped or
 * transferred) they must not be able to enter NEW academic operations — marks
 * entry, final-result generation/readiness eligibility or batch enrollment —
 * while their historical placement, marks, snapshots and enrollment rows stay
 * untouched. Completed placements (a normal year close) remain fully eligible.
 */
class AcademicExitedStudentOperationsTest extends TestCase
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
            ['name' => 'Bangladesh', 'iso3' => strtoupper($iso2).'E', 'phone_code' => '880', 'status' => true]
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

    private function classGrade(AcademicLevel $level, string $code = 'eo-c8'): ClassGrade
    {
        return ClassGrade::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $level->country_id, 'education_system_id' => $level->education_system_id, 'academic_level_id' => $level->id, 'code' => $code],
            ['name' => 'Class 8', 'display_order' => 0, 'status' => true]
        );
    }

    private function group(ClassGrade $classGrade, string $code = 'eo-sci'): AcademicGroup
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

    private function institute(Country $country, string $name = 'EO Inst'): Institute
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
            'first_name' => 'EO',
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
            'student_id_number' => 'EO'.mt_rand(100000, 999999),
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

    private function curriculum(): array
    {
        $country = $this->country();
        $classGrade = $this->classGrade($this->level($this->system($country)));
        $institute = $this->institute($country);
        $math = $this->subject('Mathematics', 'EO100001');

        $this->assign($math, $classGrade);

        return [
            'country' => $country,
            'class_grade' => $classGrade,
            'group' => $this->group($classGrade),
            'institute' => $institute,
            'owner' => $this->user($institute, 'institute-owner', 'eo-owner'),
            'math' => $math,
        ];
    }

    private function assessment(array $c, string $name = 'Annual 2026'): AcademicAssessment
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

        $config = AssessmentSubject::create([
            'assessment_id' => $assessment->id,
            'subject_id' => $c['math']->id,
            'display_order' => 1,
            'status' => 'active',
        ]);

        AssessmentSubjectComponent::create([
            'assessment_subject_id' => $config->id,
            'component_id' => $this->writtenComponent()->id,
            'full_mark' => 100,
            'pass_mark' => 33,
            'mandatory_pass' => false,
            'display_order' => 1,
            'status' => 'active',
        ]);

        return $assessment;
    }

    private function placement(array $c, Student $student, string $status = 'active'): StudentAcademicPlacement
    {
        $placement = StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $student->id,
            'academic_year_id' => $this->defaultYear($c['institute'])->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'status' => $status,
        ]);

        StudentSubjectSelection::create([
            'institute_id' => $c['institute']->id,
            'academic_placement_id' => $placement->id,
            'subject_id' => $c['math']->id,
            'is_selected' => true,
            'is_mandatory' => true,
        ]);

        return $placement;
    }

    private function mark(StudentAcademicPlacement $placement, AssessmentSubject $config, float $obtained): AcademicStudentMark
    {
        $component = AssessmentSubjectComponent::where('assessment_subject_id', $config->id)->firstOrFail();

        return AcademicStudentMark::create([
            'institute_id' => $placement->institute_id,
            'academic_assessment_id' => $config->assessment_id,
            'assessment_subject_id' => $config->id,
            'assessment_component_id' => $component->id,
            'student_id' => $placement->student_id,
            'academic_placement_id' => $placement->id,
            'obtained_mark' => $obtained,
            'status' => 'entered',
        ]);
    }

    private function course(Institute $institute): Course
    {
        return Course::create([
            'institute_id' => $institute->id,
            'course_code' => 'EO'.mt_rand(10000, 99999),
            'name' => 'Computer Basics',
        ]);
    }

    private function batch(Institute $institute, Course $course): Batch
    {
        return Batch::create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
            'name' => 'EO Batch',
            'batch_code' => 'E'.str_pad((string) mt_rand(1, 999), 3, '0', STR_PAD_LEFT),
            'shift' => 'day',
            'start_date' => now()->toDateString(),
            'seat_capacity' => 30,
            'status' => 'upcoming',
        ]);
    }

    private function act(array $c, string $verb, string $routeName, array $params = [])
    {
        TenantContext::set($c['institute']->id);

        return $this->actingAs($c['owner'], 'institute_user')->{$verb}(route($routeName, $params));
    }

    // -------------------------------------------------------------- Marks

    public function test_exited_student_is_excluded_from_marks_entry_grid(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c);
        $config = $assessment->subjects()->firstOrFail();

        $active = $this->placement($c, $this->student($c['institute'], 'Active'));
        $dropped = $this->placement($c, $this->student($c['institute'], 'Dropped'), 'dropped');
        $transferred = $this->placement($c, $this->student($c['institute'], 'Transferred'), 'transferred');

        $this->mark($dropped, $config, 55);

        $response = $this->act($c, 'get', 'settings.academic.assessments.marks', [$assessment, $config])->assertOk();

        $response->assertSee($active->student->full_name)
            ->assertDontSee($dropped->student->full_name)
            ->assertDontSee($transferred->student->full_name);

        // The exited student's historical mark row is never removed.
        $this->assertSame(1, AcademicStudentMark::where('academic_placement_id', $dropped->id)->count());
    }

    public function test_exited_student_is_excluded_from_marks_sheet(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c);

        $active = $this->placement($c, $this->student($c['institute'], 'Active'));
        $dropped = $this->placement($c, $this->student($c['institute'], 'Dropped'), 'dropped');

        $response = $this->act($c, 'get', 'settings.academic.assessments.marks-sheet', [$assessment])->assertOk();

        $response->assertSee($active->student->full_name)
            ->assertDontSee($dropped->student->full_name);
    }

    public function test_exited_student_cannot_receive_new_marks(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c);
        $config = $assessment->subjects()->firstOrFail();

        $dropped = $this->placement($c, $this->student($c['institute'], 'Dropped'), 'dropped');
        $component = AssessmentSubjectComponent::where('assessment_subject_id', $config->id)->firstOrFail();

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('settings.academic.assessments.marks.store', [$assessment, $config]), [
                'rows' => [
                    $dropped->id => [
                        'status' => 'entered',
                        'marks' => [$component->id => 80],
                    ],
                ],
            ])
            ->assertSessionHasErrors('rows.'.$dropped->id);

        $this->assertDatabaseMissing('academic_student_marks', [
            'academic_placement_id' => $dropped->id,
        ]);
    }

    public function test_exited_student_is_excluded_from_final_result_eligibility_but_completed_is_kept(): void
    {
        $c = $this->curriculum();
        $year = $this->defaultYear($c['institute']);

        $active = $this->placement($c, $this->student($c['institute'], 'Active'));
        $completed = $this->placement($c, $this->student($c['institute'], 'Completed'), 'completed');
        $dropped = $this->placement($c, $this->student($c['institute'], 'Dropped'), 'dropped');
        $transferred = $this->placement($c, $this->student($c['institute'], 'Transferred'), 'transferred');

        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $year->id,
            'class_grade_id' => $c['class_grade']->id,
            'academic_group_id' => $c['group']->id,
            'name' => 'Annual Scheme',
            'status' => 'active',
            'display_order' => 1,
        ]);

        TenantContext::set($c['institute']->id);

        $eligible = app(AcademicResultAggregationService::class)->eligiblePlacements($scheme);

        $this->assertSame([$active->id, $completed->id], $eligible->pluck('id')->sort()->values()->all());
        $this->assertTrue($eligible->pluck('id')->doesntContain($dropped->id));
        $this->assertTrue($eligible->pluck('id')->doesntContain($transferred->id));
    }

    // ----------------------------------------------------------- Enrollment

    public function test_exited_student_cannot_enroll_in_new_batch(): void
    {
        $c = $this->curriculum();
        $this->placement($c, $student = $this->student($c['institute']), 'dropped');
        $course = $this->course($c['institute']);
        $batch = $this->batch($c['institute'], $course);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('students.enroll', $student), [
                'batch_id' => $batch->id,
                'enrollment_date' => now()->toDateString(),
            ])
            ->assertSessionHasErrors('batch_id');

        $this->assertDatabaseMissing('student_enrollments', ['student_id' => $student->id, 'batch_id' => $batch->id]);
    }

    public function test_exited_student_cannot_enroll_via_json(): void
    {
        $c = $this->curriculum();
        $this->placement($c, $student = $this->student($c['institute']), 'transferred');
        $course = $this->course($c['institute']);
        $batch = $this->batch($c['institute'], $course);

        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('students.enroll', $student), [
                'batch_id' => $batch->id,
                'enrollment_date' => now()->toDateString(),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJson(['success' => false]);

        $this->assertDatabaseMissing('student_enrollments', ['student_id' => $student->id, 'batch_id' => $batch->id]);
    }

    public function test_active_or_unplaced_student_can_still_enroll(): void
    {
        $c = $this->curriculum();
        $this->placement($c, $active = $this->student($c['institute'], 'Active'));
        $unplaced = $this->student($c['institute'], 'Unplaced');
        $course = $this->course($c['institute']);
        $batch = $this->batch($c['institute'], $course);

        TenantContext::set($c['institute']->id);

        foreach ([$active, $unplaced] as $i => $student) {
            $this->actingAs($c['owner'], 'institute_user')
                ->post(route('students.enroll', $student), [
                    'batch_id' => $batch->id,
                    'roll_number' => 'EO-R-'.($i + 1),
                    'enrollment_date' => now()->toDateString(),
                ])
                ->assertSessionDoesntHaveErrors();
        }

        $this->assertSame(2, StudentEnrollment::where('batch_id', $batch->id)->count());
    }

    // ------------------------------------------------------ Historical safety

    public function test_exit_leaves_historical_marks_and_placements_intact(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c);
        $config = $assessment->subjects()->firstOrFail();

        $placement = $this->placement($c, $student = $this->student($c['institute']));
        $this->mark($placement, $config, 70);

        app(StudentAcademicExitService::class)->withdraw($student, 'Family moved');

        $placement->refresh();

        $this->assertSame('dropped', $placement->status);
        $this->assertSame('Family moved', $placement->notes);
        $this->assertSame(1, AcademicStudentMark::where('academic_placement_id', $placement->id)->count());
        $this->assertSame(70.0, (float) AcademicStudentMark::where('academic_placement_id', $placement->id)->first()->obtained_mark);
    }
}
