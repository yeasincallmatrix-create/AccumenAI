<?php

namespace Tests\Feature;

use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultPolicy;
use App\Models\AcademicFinalResultRow;
use App\Models\AcademicFinalResultStudent;
use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicYear;
use App\Models\Branch;
use App\Models\ClassGrade;
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
use App\Services\StudentAcademicExitService;
use App\Services\StudentAcademicLifecycleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class StudentAcademicExitTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    // ------------------------------------------------------------- Fixtures

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

    private function classGrade(AcademicLevel $level, string $code, string $name): ClassGrade
    {
        return ClassGrade::create([
            'country_id' => $level->country_id,
            'education_system_id' => $level->education_system_id,
            'academic_level_id' => $level->id,
            'name' => $name,
            'code' => $code,
            'display_order' => 0,
            'status' => true,
        ]);
    }

    private function group(ClassGrade $classGrade, string $code = 'gen'): AcademicGroup
    {
        return AcademicGroup::create([
            'country_id' => $classGrade->country_id,
            'education_system_id' => $classGrade->education_system_id,
            'academic_level_id' => $classGrade->academic_level_id,
            'class_grade_id' => $classGrade->id,
            'name' => 'General',
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

    private function institute(Country $country, string $name = 'Exit Inst'): Institute
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
            'student_id_number' => 'EX'.mt_rand(100000, 999999),
            'first_name' => $name,
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => now()->toDateString(),
        ]);
    }

    private function year(Institute $institute, string $code, string $name): AcademicYear
    {
        return AcademicYear::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'code' => $code,
            'is_current' => false,
            'status' => true,
        ]);
    }

    private function curriculum(): array
    {
        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $class = $this->classGrade($level, 'ex-c10', 'Class 10');
        $group = $this->group($class);
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'ex-owner');

        $math = $this->subject('Mathematics', 'EX100001');
        $english = $this->subject('English', 'EX100002');
        $this->assign($math, $class);
        $this->assign($english, $class);

        return [
            'country' => $country,
            'class' => $class,
            'group' => $group,
            'institute' => $institute,
            'owner' => $owner,
            'subjects' => compact('math', 'english'),
        ];
    }

    private function placement(array $c, Student $student, AcademicYear $year, ClassGrade $class, ?AcademicGroup $group = null): StudentAcademicPlacement
    {
        $placement = StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $class->id,
            'academic_group_id' => $group?->id,
            'status' => StudentAcademicPlacement::STATUS_ACTIVE,
        ]);

        foreach ($c['subjects'] as $subject) {
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
     * A PUBLISHED final-result snapshot for the placement (frozen record).
     */
    private function publishedSnapshot(array $c, StudentAcademicPlacement $placement, AcademicYear $year, ClassGrade $class): AcademicFinalResult
    {
        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $year->id,
            'class_grade_id' => $class->id,
            'academic_group_id' => null,
            'name' => 'Scheme '.$year->name,
            'status' => 'active',
            'display_order' => 1,
        ]);

        $policy = AcademicFinalResultPolicy::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'scheme_id' => $scheme->id,
            'name' => $year->name.' Policy',
        ]);

        $result = AcademicFinalResult::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'policy_id' => $policy->id,
            'scheme_id' => $scheme->id,
            'name' => 'Term Final '.$year->name,
            'status' => AcademicFinalResult::STATUS_PUBLISHED,
            'published_at' => now(),
            'locked_at' => now(),
        ]);

        AcademicFinalResultStudent::create([
            'result_id' => $result->id,
            'placement_id' => $placement->id,
            'gpa' => 4.75,
            'gpa_status' => AcademicFinalResultStudent::GPA_COMPUTED,
            'passed_count' => 2,
            'failed_count' => 0,
        ]);

        AcademicFinalResultRow::create([
            'result_id' => $result->id,
            'placement_id' => $placement->id,
            'subject_id' => $c['subjects']['math']->id,
            'status' => 'computed',
            'aggregate' => 90.5,
            'grade' => 'A+',
            'grade_point' => 5.0,
            'subject_status' => 'PASS',
            'gpa_included' => true,
        ]);

        return $result;
    }

    private function withdrawRoute(Student $student): string
    {
        return route('students.academic-withdraw', $student);
    }

    private function transferRoute(Student $student): string
    {
        return route('students.academic-transfer', $student);
    }

    private function lifecycle(Student $student): array
    {
        return app(StudentAcademicLifecycleService::class)->forStudent($student);
    }

    // ------------------------------------------------------------- Authorization

    public function test_guest_is_redirected_to_login(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $this->post($this->withdrawRoute($student))->assertRedirect();
        $this->post($this->transferRoute($student))->assertRedirect();
    }

    public function test_unauthorized_role_is_blocked(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $this->placement($c, $student, $this->year($c['institute'], '2027', 'Session 2027'), $c['class'], $c['group']);

        $auditorRole = Role::create([
            'name' => 'EX Auditor',
            'slug' => 'ex-auditor-'.uniqid(),
            'status' => 'active',
        ]);
        $auditor = $this->user($c['institute'], $auditorRole->slug, 'ex-auditor');

        $this->actingAs($auditor, 'institute_user')
            ->post($this->withdrawRoute($student))
            ->assertForbidden();

        $this->actingAs($auditor, 'institute_user')
            ->post($this->transferRoute($student))
            ->assertForbidden();
    }

    // ------------------------------------------------------------- Isolation

    public function test_cross_tenant_student_is_blocked(): void
    {
        $c = $this->curriculum();
        $otherInstitute = $this->institute($this->country('IN', 'India'), 'Other Inst');
        $otherStudent = $this->student($otherInstitute, 'Other');

        $this->actingAs($c['owner'], 'institute_user')
            ->post($this->withdrawRoute($otherStudent))
            ->assertStatus(404);

        $this->actingAs($c['owner'], 'institute_user')
            ->post($this->transferRoute($otherStudent))
            ->assertStatus(404);
    }

    public function test_cross_branch_student_is_blocked(): void
    {
        $c = $this->curriculum();
        $branchA = $this->branch($c['institute'], 'Branch A');
        $branchB = $this->branch($c['institute'], 'Branch B');
        $studentA = $this->student($c['institute'], 'Rahim', $branchA);
        $this->placement($c, $studentA, $this->year($c['institute'], '2027', 'Session 2027'), $c['class'], $c['group']);
        $adminB = $this->user($c['institute'], 'institute-admin', 'ex-admin-b', $branchB);

        $this->actingAs($adminB, 'institute_user')
            ->post($this->withdrawRoute($studentA))
            ->assertStatus(404);
    }

    public function test_cross_tenant_target_branch_cannot_be_selected(): void
    {
        $c = $this->curriculum();
        $other = $this->institute($this->country('IN', 'India'), 'Other Inst');
        $crossTenantBranch = $this->branch($other, 'Foreign Branch');
        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, $this->year($c['institute'], '2027', 'Session 2027'), $c['class'], $c['group']);

        $this->actingAs($c['owner'], 'institute_user')
            ->post($this->transferRoute($student), [
                'branch_id' => $crossTenantBranch->id,
                'reason' => 'To another country',
            ])
            ->assertSessionHasErrors('branch_id');

        $this->assertSame(StudentAcademicPlacement::STATUS_ACTIVE, $placement->refresh()->status);
        $this->assertNull($student->refresh()->branch_id);
    }

    // ------------------------------------------------------------- Official actions

    public function test_authorized_user_can_withdraw_student(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);

        $this->actingAs($c['owner'], 'institute_user')
            ->post($this->withdrawRoute($student), ['reason' => 'Family relocation'])
            ->assertRedirect(route('students.show', $student));

        $placement = $placement->refresh();
        $this->assertSame(StudentAcademicPlacement::STATUS_DROPPED, $placement->status);
        $this->assertSame('Family relocation', $placement->notes);

        $state = $this->lifecycle($student);
        $this->assertSame('withdrawn', $state['outcome']);
        $this->assertTrue($state['isWithdrawal']);
        $this->assertFalse($state['hasActivePlacement']);
        $this->assertNull($state['progressingTo']);

        $this->assertDatabaseCount('student_academic_placements', 1);
    }

    public function test_authorized_user_can_transfer_student(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $targetBranch = $this->branch($c['institute'], 'Downtown Branch');

        $this->actingAs($c['owner'], 'institute_user')
            ->post($this->transferRoute($student), [
                'branch_id' => $targetBranch->id,
                'reason' => 'Moved to downtown campus',
            ])
            ->assertRedirect(route('students.show', $student));

        $placement = $placement->refresh();
        $this->assertSame(StudentAcademicPlacement::STATUS_TRANSFERRED, $placement->status);
        $this->assertSame('Moved to downtown campus', $placement->notes);
        $this->assertSame($targetBranch->id, $student->refresh()->branch_id);

        $state = $this->lifecycle($student);
        $this->assertSame('transferred', $state['outcome']);
        $this->assertTrue($state['isTransfer']);
        $this->assertFalse($state['hasActivePlacement']);

        $this->assertDatabaseCount('student_academic_placements', 1);
    }

    public function test_active_placement_is_not_treated_as_official_exit(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $this->placement($c, $student, $this->year($c['institute'], '2027', 'Session 2027'), $c['class'], $c['group']);

        $state = $this->lifecycle($student);
        $this->assertSame('active', $state['outcome']);
        $this->assertFalse($state['isWithdrawal']);
        $this->assertFalse($state['isTransfer']);
        $this->assertTrue($state['hasActivePlacement']);
    }

    // ------------------------------------------------------------- Duplicate / conflicting states

    public function test_duplicate_withdrawal_or_transfer_cannot_be_created(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, $this->year($c['institute'], '2027', 'Session 2027'), $c['class'], $c['group']);

        $this->actingAs($c['owner'], 'institute_user');
        $this->post($this->withdrawRoute($student))->assertRedirect();
        $this->post($this->withdrawRoute($student))->assertStatus(422);
        $this->post($this->transferRoute($student))->assertStatus(422);

        $this->assertSame(StudentAcademicPlacement::STATUS_DROPPED, $placement->refresh()->status);
        $this->assertDatabaseCount('student_academic_placements', 1);
        $this->assertSame('withdrawn', $this->lifecycle($student)['outcome']);
    }

    public function test_withdraw_requires_an_active_placement(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $this->placement($c, $student, $this->year($c['institute'], '2027', 'Session 2027'), $c['class'], $c['group'])
            ->update(['status' => StudentAcademicPlacement::STATUS_COMPLETED]);

        $this->actingAs($c['owner'], 'institute_user')
            ->post($this->withdrawRoute($student))
            ->assertStatus(422);
    }

    // ------------------------------------------------------------- Immutability

    public function test_historical_placement_and_published_results_remain_intact_after_withdrawal(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $result = $this->publishedSnapshot($c, $placement, $year, $c['class']);

        $before = [
            'result_students' => collect(DB::table('academic_final_result_students')->where('result_id', $result->id)->get()->map(fn ($row) => (array) $row)),
            'result_rows' => collect(DB::table('academic_final_result_rows')->where('result_id', $result->id)->get()->map(fn ($row) => (array) $row)),
        ];

        $this->actingAs($c['owner'], 'institute_user')
            ->post($this->withdrawRoute($student), ['reason' => 'Exit'])
            ->assertRedirect();

        // The placement row itself still exists — only its status changed.
        $this->assertDatabaseHas('student_academic_placements', [
            'id' => $placement->id,
            'status' => StudentAcademicPlacement::STATUS_DROPPED,
        ]);
        $this->assertEquals($before['result_students']->all(),
            collect(DB::table('academic_final_result_students')->where('result_id', $result->id)->get()->map(fn ($row) => (array) $row))->all());
        $this->assertEquals($before['result_rows']->all(),
            collect(DB::table('academic_final_result_rows')->where('result_id', $result->id)->get()->map(fn ($row) => (array) $row))->all());

        $this->assertDatabaseHas('academic_final_results', ['id' => $result->id, 'status' => AcademicFinalResult::STATUS_PUBLISHED]);
        $this->assertDatabaseHas('students', ['id' => $student->id, 'status' => 'active']);
    }

    public function test_operation_writes_only_the_intended_lifecycle_data(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, $this->year($c['institute'], '2027', 'Session 2027'), $c['class'], $c['group']);

        // Every snapshot table except the placement itself must stay untouched.
        // admission_status on students is the one intentional exception: since
        // Step 36 the exit flow also records the admission funnel's terminal
        // 'withdrawn' state on the same row.
        $tables = [
            'students',
            'student_subject_selections',
            'student_enrollments',
            'results',
            'invoices',
            'payments',
        ];

        $projection = fn (array $row) => collect($row)->except('admission_status')->all();

        $before = [];
        foreach ($tables as $table) {
            $before[$table] = collect(DB::table($table)->get()->map(fn ($row) => $projection((array) $row)));
        }

        $placementBefore = (array) DB::table('student_academic_placements')->where('id', $placement->id)->first();

        $this->actingAs($c['owner'], 'institute_user')
            ->post($this->withdrawRoute($student), ['reason' => 'Withdrawn'])
            ->assertRedirect();

        foreach ($tables as $table) {
            $after = collect(DB::table($table)->get()->map(fn ($row) => $projection((array) $row)));
            $this->assertEquals($before[$table]->all(), $after->all(), "{$table} was mutated by the withdrawal.");
        }

        // The withdrawal records the funnel terminal state on the student row.
        $this->assertSame(Student::ADMISSION_STATUS_WITHDRAWN, (string) DB::table('students')->where('id', $student->id)->value('admission_status'));

        // The placement row changes ONLY the intended lifecycle columns.
        $placementAfter = (array) DB::table('student_academic_placements')->where('id', $placement->id)->first();
        $changed = collect($placementBefore)
            ->keys()
            ->reject(fn ($column) => $placementBefore[$column] == $placementAfter[$column])
            ->values()
            ->all();

        $this->assertNotEmpty($changed);
        foreach ($changed as $column) {
            $this->assertContains($column, ['status', 'notes', 'updated_at'], "Unexpected column changed: {$column}");
        }

        $this->assertSame(StudentAcademicPlacement::STATUS_DROPPED, $placementAfter['status']);
        $this->assertSame('Withdrawn', $placementAfter['notes']);
    }

    // ------------------------------------------------------------- History integration

    public function test_academic_history_reflects_withdrawal(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);
        $this->publishedSnapshot($c, $placement, $year, $c['class']);

        $this->actingAs($c['owner'], 'institute_user')
            ->post($this->withdrawRoute($student), ['reason' => 'Exit'])
            ->assertRedirect();

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('students.academic-history', $student))
            ->assertOk()
            ->assertSee('Withdrawn')
            ->assertSee('Officially withdrawn from the academic program');

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('students.show', $student))
            ->assertOk()
            ->assertSee('Withdrawn');
    }

    public function test_academic_history_reflects_transfer(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $placement = $this->placement($c, $student, $this->year($c['institute'], '2027', 'Session 2027'), $c['class'], $c['group']);

        $this->actingAs($c['owner'], 'institute_user')
            ->post($this->transferRoute($student))
            ->assertRedirect();

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('students.academic-history', $student))
            ->assertOk()
            ->assertSee('Transferred')
            ->assertSee('Officially transferred from the current placement');
    }

    // ------------------------------------------------------------- Service contracts

    public function test_exit_service_reuses_the_current_active_placement_only(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->year($c['institute'], '2027', 'Session 2027');
        $placement = $this->placement($c, $student, $year, $c['class'], $c['group']);

        app(StudentAcademicExitService::class)->withdraw($student, 'Family reasons');

        $this->assertSame(StudentAcademicPlacement::STATUS_DROPPED, $placement->refresh()->status);

        $this->expectException(HttpException::class);
        app(StudentAcademicExitService::class)->withdraw($student, 'Again');
    }
}
