<?php

namespace Tests\Feature;

use App\Models\AcademicAssessment;
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
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\StudentSubjectSelection;
use App\Models\Subject;
use App\Models\SubjectAcademicAssignment;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Database\Seeders\AcademicAssessmentSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Class-wide assessment marks sheet + CSV export.
 *
 * The sheet reads live assessment marks only (the same eligibility and
 * derived-result rules as the per-subject entry grid); it never touches
 * final-result snapshots. Both routes are tenant + branch scoped by the
 * AcademicAssessment route binding, and placements are restricted to the
 * acting branch.
 */
class AcademicMarksSheetTest extends TestCase
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
            ['name' => 'Bangladesh', 'iso3' => strtoupper($iso2).'M', 'phone_code' => '880', 'status' => true]
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

    private function classGrade(AcademicLevel $level, string $code = 'ms-c8'): ClassGrade
    {
        return ClassGrade::withoutGlobalScopes()->firstOrCreate(
            ['country_id' => $level->country_id, 'education_system_id' => $level->education_system_id, 'academic_level_id' => $level->id, 'code' => $code],
            ['name' => 'Class 8', 'display_order' => 0, 'status' => true]
        );
    }

    private function group(ClassGrade $classGrade, string $code = 'ms-sci'): AcademicGroup
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

    private function institute(Country $country, string $name = 'MS Inst'): Institute
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
            'first_name' => 'MS',
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function student(Institute $institute, ?Branch $branch = null): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'student_id_number' => 'MS'.mt_rand(100000, 999999),
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

    private function curriculum(): array
    {
        $country = $this->country();
        $classGrade = $this->classGrade($this->level($this->system($country)));
        $institute = $this->institute($country);
        $math = $this->subject('Mathematics', 'MS100001');
        $english = $this->subject('English', 'MS100002');

        $this->assign($math, $classGrade, 'mandatory', 1);
        $this->assign($english, $classGrade, 'mandatory', 2);

        return [
            'country' => $country,
            'class_grade' => $classGrade,
            'group' => $this->group($classGrade),
            'institute' => $institute,
            'owner' => $this->user($institute, 'institute-owner', 'ms-owner'),
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

        foreach ([$c['math'], $c['english']] as $order => $subject) {
            $config = AssessmentSubject::create([
                'assessment_id' => $assessment->id,
                'subject_id' => $subject->id,
                'display_order' => $order + 1,
                'status' => 'active',
            ]);

            AssessmentSubjectComponent::create([
                'assessment_subject_id' => $config->id,
                'component_id' => $written->id,
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

    private function act(array $c, string $verb, string $routeName, array $params = [])
    {
        TenantContext::set($c['institute']->id);

        return $this->actingAs($c['owner'], 'institute_user')->{$verb}(route($routeName, $params));
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

    public function test_marks_sheet_renders_class_wide_matrix_with_derived_statuses(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c, 'Mid Term 2026');

        $configMath = $assessment->subjects()->where('subject_id', $c['math']->id)->firstOrFail();
        $configEnglish = $assessment->subjects()->where('subject_id', $c['english']->id)->firstOrFail();

        $passer = $this->placement($c, $this->student($c['institute']));
        $partial = $this->placement($c, $this->student($c['institute']));
        $absent = $this->placement($c, $this->student($c['institute']));

        // Full entry → pass (140/200 = 70%).
        $this->mark($passer, $configMath, 80);
        $this->mark($passer, $configEnglish, 60);
        // One subject only → incomplete.
        $this->mark($partial, $configMath, 95);
        // Absent in one subject, other not entered → absent.
        $this->mark($absent, $configMath, 0, 'absent');

        $response = $this->act($c, 'get', 'settings.academic.assessments.marks-sheet', [$assessment])
            ->assertOk();

        $response->assertSee('Mathematics')
            ->assertSee('English')
            ->assertSee('70%')
            ->assertSee('Pass')
            ->assertSee('Incomplete')
            ->assertSee('Absent')
            ->assertSee('1 subject(s) pending');
    }

    public function test_marks_sheet_exports_csv_of_whole_matrix(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c, 'Mid Term 2026');

        $configMath = $assessment->subjects()->where('subject_id', $c['math']->id)->firstOrFail();
        $configEnglish = $assessment->subjects()->where('subject_id', $c['english']->id)->firstOrFail();

        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student);
        $this->mark($placement, $configMath, 80);
        $this->mark($placement, $configEnglish, 60);

        $response = $this->act($c, 'get', 'settings.academic.assessments.marks-sheet.export', [$assessment])
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $all = $this->parseCsv($this->csvContent($response));
        $headers = $all[0];

        $this->assertContains('Mathematics (obtained)', $headers);
        $this->assertContains('English (status)', $headers);
        $this->assertContains('Total obtained', $headers);
        $this->assertContains('Overall status', $headers);

        $this->assertCount(2, $all);
        [$_, $id, $reg, $mathOb, $mathFull, $mathStatus, $engOb, $engFull, $engStatus, $totalOb, $totalFull, $percentage, $overall, $entered, $absent, $notEntered] = $all[1];

        $this->assertSame($student->full_name, $_);
        $this->assertSame((string) $student->student_id_number, $id);
        $this->assertSame('80', $mathOb);
        $this->assertSame('100', $mathFull);
        $this->assertSame('Pass', $mathStatus);
        $this->assertSame('60', $engOb);
        $this->assertSame('Pass', $engStatus);
        $this->assertSame('140', $totalOb);
        $this->assertSame('200', $totalFull);
        $this->assertSame('70%', $percentage);
        $this->assertSame('Pass', $overall);
        $this->assertSame('2', $entered);
        $this->assertSame('0', $absent);
        $this->assertSame('0', $notEntered);
    }

    public function test_branch_admin_marks_sheet_only_lists_their_branch_students(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c, 'Mid Term 2026');

        $branchB = $this->branch($c['institute'], 'Branch B');
        $otherBranch = $this->branch($c['institute'], 'Branch Other');
        $adminB = $this->user($c['institute'], 'institute-admin', 'ms-admin-b', $branchB);

        $mine = $this->student($c['institute'], $branchB);
        $theirs = $this->student($c['institute'], $otherBranch);

        $mine->update(['first_name' => 'Mine']);
        $theirs->update(['first_name' => 'Theirs']);

        $this->placement($c, $mine);
        $this->placement($c, $theirs);

        TenantContext::set($c['institute']->id);
        BranchContext::set($branchB->id);

        $this->actingAs($adminB, 'institute_user')
            ->get(route('settings.academic.assessments.marks-sheet', $assessment))
            ->assertOk()
            ->assertSee($mine->full_name)
            ->assertDontSee($theirs->full_name);
    }

    public function test_marks_sheet_and_export_are_404_for_other_institute(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c, 'Mid Term 2026');

        $other = $this->institute($this->country('IN'), 'Other 404 Inst');
        $otherAdmin = $this->user($other, 'institute-admin', 'ms-other');

        TenantContext::set($other->id);

        $this->actingAs($otherAdmin, 'institute_user')
            ->get(route('settings.academic.assessments.marks-sheet', $assessment))
            ->assertStatus(404);

        $this->actingAs($otherAdmin, 'institute_user')
            ->get(route('settings.academic.assessments.marks-sheet.export', $assessment))
            ->assertStatus(404);
    }

    public function test_staff_without_education_manage_is_blocked_from_marks_sheet(): void
    {
        $c = $this->curriculum();
        $assessment = $this->assessment($c, 'Mid Term 2026');
        $teacher = $this->user($c['institute'], 'teacher', 'ms-teacher');

        TenantContext::set($c['institute']->id);

        $this->actingAs($teacher, 'institute_user')
            ->get(route('settings.academic.assessments.marks-sheet', $assessment))
            ->assertForbidden();

        $this->actingAs($teacher, 'institute_user')
            ->get(route('settings.academic.assessments.marks-sheet.export', $assessment))
            ->assertForbidden();
    }
}
