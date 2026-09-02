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
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\Course;
use App\Models\EducationSystem;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\Subject;
use App\Services\StudentAcademicAttendanceService;
use App\Services\StudentAcademicExitService;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AcademicAttendanceTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    // ------------------------------------------------------------- Fixtures

    private function country(string $iso2 = 'BD', string $name = 'Bangladesh'): Country
    {
        return Country::firstOrCreate(['iso2' => $iso2], ['name' => $name,
            'iso3' => strtoupper($iso2).'D',
            'phone_code' => '880',
            'status' => true,
        ]);
    }

    private function system(Country $country): EducationSystem
    {
        return EducationSystem::firstOrCreate(
            ['country_id' => $country->id, 'code' => 'general'],
            ['name' => 'General Education', 'display_order' => 0, 'status' => true]
        );
    }

    private function level(EducationSystem $system): AcademicLevel
    {
        return AcademicLevel::create([
            'country_id' => $system->country_id,
            'education_system_id' => $system->id,
            'name' => 'Secondary',
            'code' => 'secondary',
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

    private function group(ClassGrade $classGrade): AcademicGroup
    {
        return AcademicGroup::create([
            'country_id' => $classGrade->country_id,
            'education_system_id' => $classGrade->education_system_id,
            'academic_level_id' => $classGrade->academic_level_id,
            'class_grade_id' => $classGrade->id,
            'name' => 'General',
            'code' => 'gen',
            'display_order' => 0,
            'status' => true,
        ]);
    }

    private function institute(Country $country, string $name = 'Att Inst'): Institute
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
            'student_id_number' => 'AT'.mt_rand(100000, 999999),
            'first_name' => $name,
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => now()->toDateString(),
        ]);
    }

    private function yearWithDates(Institute $institute, string $code, string $name, $start, $end): AcademicYear
    {
        return AcademicYear::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'code' => $code,
            'start_date' => $start,
            'end_date' => $end,
            'is_current' => false,
            'status' => true,
        ]);
    }

    private function yearWithoutDates(Institute $institute, string $code, string $name): AcademicYear
    {
        return AcademicYear::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'code' => $code,
            'is_current' => false,
            'status' => true,
        ]);
    }

    private function batch(Institute $institute, ?Branch $branch = null): Batch
    {
        return Batch::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'course_id' => Course::create(['course_code' => 'AC'.mt_rand(1000, 9999), 'name' => 'Att Course'])->id,
            'name' => 'Att Batch',
            'batch_code' => 'AB'.mt_rand(1000, 9999),
            'start_date' => now(),
        ]);
    }

    private function placement(array $c, Student $student, AcademicYear $year, ClassGrade $class, ?AcademicGroup $group = null): StudentAcademicPlacement
    {
        return StudentAcademicPlacement::create([
            'institute_id' => $c['institute']->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $class->id,
            'academic_group_id' => $group?->id,
            'status' => StudentAcademicPlacement::STATUS_ACTIVE,
        ]);
    }

    private function attendance(Student $student, Batch $batch, $date, string $status, ?string $remarks = null): Attendance
    {
        return Attendance::create([
            'institute_id' => $student->institute_id,
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'class_date' => $date,
            'status' => $status,
            'remarks' => $remarks,
        ]);
    }

    private function curriculum(): array
    {
        $country = $this->country();
        $system = $this->system($country);
        $level = $this->level($system);
        $class7 = $this->classGrade($level, 'at-c07', 'Class 7');
        $class8 = $this->classGrade($level, 'at-c08', 'Class 8');
        $group = $this->group($class8);
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'at-owner');

        return [
            'country' => $country,
            'class7' => $class7,
            'class8' => $class8,
            'group' => $group,
            'institute' => $institute,
            'owner' => $owner,
        ];
    }

    private function attendanceUrl(Student $student, ?int $yearId = null): string
    {
        return route('students.academic-attendance', $student).($yearId !== null ? '?academic_year_id='.$yearId : '');
    }

    private function service(): StudentAcademicAttendanceService
    {
        return app(StudentAcademicAttendanceService::class);
    }

    // ------------------------------------------- Academic context resolution

    public function test_attendance_context_resolves_to_the_placement_active_on_the_date(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $y25 = $this->yearWithDates($c['institute'], '2025', 'Session 2025', '2025-01-01', '2025-12-31');
        $y26 = $this->yearWithDates($c['institute'], '2026', 'Session 2026', '2026-01-01', '2026-12-31');
        $p25 = $this->placement($c, $student, $y25, $c['class7']);
        $p26 = $this->placement($c, $student, $y26, $c['class8'], $c['group']);

        TenantContext::set($c['institute']->id);

        $a = $this->service()->placementForDate($student, Carbon::parse('2025-06-15'));
        $this->assertNotNull($a);
        $this->assertSame($p25->id, $a->id);
        $this->assertSame($y25->id, $a->academic_year_id);
        $this->assertSame($c['class7']->id, $a->class_grade_id);

        $b = $this->service()->placementForDate($student, Carbon::parse('2026-02-20'));
        $this->assertNotNull($b);
        $this->assertSame($p26->id, $b->id);
        $this->assertSame($y26->id, $b->academic_year_id);
        $this->assertSame($c['class8']->id, $b->class_grade_id);
        $this->assertSame($c['group']->id, $b->academic_group_id);
    }

    public function test_historical_placement_is_used_for_historical_attendance(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $y25 = $this->yearWithDates($c['institute'], '2025', 'Session 2025', '2025-01-01', '2025-12-31');
        $y26 = $this->yearWithDates($c['institute'], '2026', 'Session 2026', '2026-01-01', '2026-12-31');
        $this->placement($c, $student, $y25, $c['class7']);
        $this->placement($c, $student, $y26, $c['class8']);

        $this->assertSame($y26->id, $student->currentAcademicPlacement()->academic_year_id);

        TenantContext::set($c['institute']->id);

        // 2025 attendance must resolve to the 2025 placement, never the
        // student's current (2026) placement.
        $resolved = $this->service()->placementForDate($student, Carbon::parse('2025-06-15'));
        $this->assertSame($y25->id, $resolved->academic_year_id);
        $this->assertSame($c['class7']->id, $resolved->class_grade_id);
    }

    public function test_current_placement_never_rewrites_historical_attendance(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $y25 = $this->yearWithDates($c['institute'], '2025', 'Session 2025', '2025-01-01', '2025-12-31');
        $y26 = $this->yearWithDates($c['institute'], '2026', 'Session 2026', '2026-01-01', '2026-12-31');
        $p25 = $this->placement($c, $student, $y25, $c['class7']);
        $batch = $this->batch($c['institute']);
        $record = $this->attendance($student, $batch, '2025-09-01', Attendance::STATUS_PRESENT);

        TenantContext::set($c['institute']->id);

        $this->placement($c, $student, $y26, $c['class8']);

        $summary = $this->service()->summaryForPlacement($p25->refresh());
        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['present']);

        $this->assertDatabaseHas('attendance', ['id' => $record->id, 'class_date' => '2025-09-01', 'status' => 'present']);
        $this->assertDatabaseCount('attendance', 1);
    }

    public function test_no_placement_overlapping_the_date_resolves_to_null(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $this->placement($c, $student, $this->yearWithDates($c['institute'], '2025', 'Session 2025', '2025-01-01', '2025-12-31'), $c['class7']);

        TenantContext::set($c['institute']->id);

        $this->assertNull($this->service()->placementForDate($student, Carbon::parse('2026-05-01')));
    }

    // ------------------------------------------------------------- Summaries

    public function test_summary_is_accurate_and_scoped_to_student_and_year(): void
    {
        $c = $this->curriculum();
        $institute = $c['institute'];
        $student = $this->student($institute);
        $other = $this->student($institute, 'Karim');
        $y25 = $this->yearWithDates($institute, '2025', 'Session 2025', '2025-01-01', '2025-12-31');
        $p25 = $this->placement($c, $student, $y25, $c['class7']);
        $batch = $this->batch($institute);

        foreach (['2025-02-01' => 'present', '2025-02-02' => 'present', '2025-02-03' => 'present',
            '2025-02-04' => 'absent', '2025-02-05' => 'late', '2025-02-06' => 'leave', ] as $date => $status) {
            $this->attendance($student, $batch, $date, $status);
        }
        // Outside the year's window — must not count.
        $this->attendance($student, $batch, '2024-12-31', Attendance::STATUS_PRESENT);
        // Another student inside the window — must not count.
        $this->attendance($other, $batch, '2025-03-01', Attendance::STATUS_ABSENT);

        TenantContext::set($institute->id);

        $summary = $this->service()->summaryForPlacement($p25->refresh());

        $this->assertSame(6, $summary['total']);
        $this->assertSame(3, $summary['present']);
        $this->assertSame(1, $summary['absent']);
        $this->assertSame(1, $summary['late']);
        $this->assertSame(1, $summary['leave']);
        $this->assertSame(50.0, $summary['present_percent']);
        $this->assertDatabaseCount('attendance', 8);
        $this->assertSame(6, $this->service()->recordsForStudentInYear($student, $y25)->total());
    }

    public function test_summary_is_null_without_reliable_year_dates(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->yearWithoutDates($c['institute'], '2025', 'Session 2025');
        $p = $this->placement($c, $student, $year, $c['class7']);

        TenantContext::set($c['institute']->id);

        $this->assertNull($this->service()->summaryForPlacement($p->refresh()));
        $this->assertNull($this->service()->recordsForStudentInYear($student, $year));
    }

    public function test_duplicate_attendance_record_cannot_be_inserted(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $batch = $this->batch($c['institute']);

        $this->attendance($student, $batch, '2025-02-10', Attendance::STATUS_PRESENT);

        try {
            $this->attendance($student, $batch, '2025-02-10', Attendance::STATUS_ABSENT);
            $this->fail('Duplicate (batch, student, date) attendance must be rejected.');
        } catch (QueryException $e) {
            $this->assertTrue(true);
        }

        $this->assertDatabaseCount('attendance', 1);
        $this->assertDatabaseHas('attendance', ['status' => 'present']);
    }

    // ------------------------------------------------------- Academic history

    public function test_academic_attendance_page_filters_by_academic_year(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $y25 = $this->yearWithDates($c['institute'], '2025', 'Session 2025', '2025-01-01', '2025-12-31');
        $y26 = $this->yearWithDates($c['institute'], '2026', 'Session 2026', '2026-01-01', '2026-12-31');
        $this->placement($c, $student, $y25, $c['class7']);
        $this->placement($c, $student, $y26, $c['class8']);
        $batch = $this->batch($c['institute']);
        $this->attendance($student, $batch, '2025-05-15', Attendance::STATUS_PRESENT, 'Spring');
        $this->attendance($student, $batch, '2026-03-15', Attendance::STATUS_ABSENT);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->attendanceUrl($student, $y25->id))
            ->assertOk()
            ->assertSee('May 15, 2025')
            ->assertSee('Spring')
            ->assertDontSee('Mar 15, 2026');

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->attendanceUrl($student, $y26->id))
            ->assertOk()
            ->assertSee('Mar 15, 2026')
            ->assertDontSee('Spring');
    }

    public function test_academic_history_shows_reliable_attendance_summary(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $y25 = $this->yearWithDates($c['institute'], '2025', 'Session 2025', '2025-01-01', '2025-12-31');
        $p = $this->placement($c, $student, $y25, $c['class7']);
        $batch = $this->batch($c['institute']);
        $this->attendance($student, $batch, '2025-03-10', Attendance::STATUS_PRESENT);
        $this->attendance($student, $batch, '2025-03-11', Attendance::STATUS_ABSENT);

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('students.academic-history', $student))
            ->assertOk()
            ->assertSee('Attendance')
            ->assertSee('50.0%')
            ->assertSee('1 present')
            ->assertSee('1 absent');

        $summary = $this->service()->summariesForPlacements(collect([$p]));
        $this->assertArrayHasKey($p->id, $summary->all());
    }

    public function test_academic_history_hides_summary_without_year_dates(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $year = $this->yearWithoutDates($c['institute'], '2025', 'Session 2025');
        $this->placement($c, $student, $year, $c['class7']);
        $batch = $this->batch($c['institute']);
        $this->attendance($student, $batch, '2025-03-10', Attendance::STATUS_PRESENT);

        TenantContext::set($c['institute']->id);
        $this->assertNull($this->service()->summaryForPlacement($student->academicPlacements()->firstOrFail()));

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('students.academic-history', $student))
            ->assertOk()
            ->assertDontSee('50.0%');
    }

    // ------------------------------------------------------------ Lifecycle

    public function test_withdrawn_student_retains_historical_attendance(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $y25 = $this->yearWithDates($c['institute'], '2025', 'Session 2025', '2025-01-01', '2025-12-31');
        $p = $this->placement($c, $student, $y25, $c['class7']);
        $batch = $this->batch($c['institute']);
        $this->attendance($student, $batch, '2025-04-01', Attendance::STATUS_PRESENT);
        $this->attendance($student, $batch, '2025-04-02', Attendance::STATUS_ABSENT);

        app(StudentAcademicExitService::class)->withdraw($student, 'Exit');

        TenantContext::set($c['institute']->id);

        $summary = $this->service()->summaryForPlacement($p->refresh());
        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['present']);
        $this->assertSame(1, $summary['absent']);
        $this->assertDatabaseCount('attendance', 2);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->attendanceUrl($student, $y25->id))
            ->assertOk()
            ->assertSee('Apr 1, 2025');
    }

    public function test_transferred_student_retains_historical_attendance(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $y25 = $this->yearWithDates($c['institute'], '2025', 'Session 2025', '2025-01-01', '2025-12-31');
        $placement = $this->placement($c, $student, $y25, $c['class7']);
        $batch = $this->batch($c['institute']);
        $this->attendance($student, $batch, '2025-05-20', Attendance::STATUS_LATE);

        app(StudentAcademicExitService::class)->transfer($student, 'Moved');

        TenantContext::set($c['institute']->id);

        $this->assertSame(1, $this->service()->summaryForPlacement($placement->refresh())['late']);
        $this->assertDatabaseCount('attendance', 1);
        $this->assertDatabaseHas('attendance', ['student_id' => $student->id, 'status' => 'late']);
    }

    // ----------------------------------------------------- Legacy compatibility

    public function test_legacy_batch_attendance_remains_functional(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $y25 = $this->yearWithDates($c['institute'], '2025', 'Session 2025', '2025-01-01', '2025-12-31');
        $this->placement($c, $student, $y25, $c['class7']);
        $batch = $this->batch($c['institute']);
        $this->attendance($student, $batch, '2025-01-20', Attendance::STATUS_PRESENT);

        // The row keeps its batch relationship and remains readable.
        $row = Attendance::query()->where('batch_id', $batch->id)->first();
        $this->assertSame($batch->id, $row->batch_id);
        $this->assertSame($student->id, $row->student_id);

        // Existing batch-scoped read (the legacy domain) is untouched.
        $this->assertSame(1, Attendance::query()->where('batch_id', $batch->id)->where('status', 'present')->count());

        $this->assertDatabaseHas('batches', ['id' => $batch->id, 'name' => 'Att Batch']);
        $this->assertDatabaseCount('attendance', 1);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->attendanceUrl($student, $y25->id))
            ->assertOk()
            ->assertSee('Att Batch');
    }

    // ----------------------------------------------------------- Isolation

    public function test_cross_tenant_student_is_blocked(): void
    {
        $c = $this->curriculum();
        $other = $this->institute($this->country('IN', 'India'), 'Other Att Inst');
        $otherStudent = $this->student($other, 'Other');

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->attendanceUrl($otherStudent))
            ->assertStatus(404);
    }

    public function test_cross_branch_student_is_blocked(): void
    {
        $c = $this->curriculum();
        $branchA = $this->branch($c['institute'], 'Branch A');
        $branchB = $this->branch($c['institute'], 'Branch B');
        $studentA = $this->student($c['institute'], 'Rahim', $branchA);
        $adminB = $this->user($c['institute'], 'institute-admin', 'at-admin-b', $branchB);

        $this->actingAs($adminB, 'institute_user')
            ->get($this->attendanceUrl($studentA))
            ->assertStatus(404);
    }

    // ------------------------------------------------------- Authorization

    public function test_unauthorized_role_is_blocked(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $auditorRole = Role::create([
            'name' => 'AT Auditor',
            'slug' => 'at-auditor-'.uniqid(),
            'status' => 'active',
        ]);
        $auditor = $this->user($c['institute'], $auditorRole->slug, 'at-auditor');

        $this->actingAs($auditor, 'institute_user')
            ->get($this->attendanceUrl($student))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);

        $this->get($this->attendanceUrl($student))->assertRedirect();
    }

    // ------------------------------------------------------ Result immutability

    public function test_attendance_operations_never_mutate_academic_results_or_snapshots(): void
    {
        $c = $this->curriculum();
        $student = $this->student($c['institute']);
        $y25 = $this->yearWithDates($c['institute'], '2025', 'Session 2025', '2025-01-01', '2025-12-31');
        $p25 = $this->placement($c, $student, $y25, $c['class7']);
        $batch = $this->batch($c['institute']);
        $this->attendance($student, $batch, '2025-06-10', Attendance::STATUS_ABSENT);

        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'academic_year_id' => $y25->id,
            'class_grade_id' => $c['class7']->id,
            'academic_group_id' => null,
            'name' => 'Scheme',
            'status' => 'active',
            'display_order' => 1,
        ]);
        $policy = AcademicFinalResultPolicy::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'scheme_id' => $scheme->id,
            'name' => 'Policy',
        ]);
        $result = AcademicFinalResult::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => null,
            'policy_id' => $policy->id,
            'scheme_id' => $scheme->id,
            'name' => 'Term Final',
            'status' => AcademicFinalResult::STATUS_PUBLISHED,
            'published_at' => now(),
            'locked_at' => now(),
        ]);
        AcademicFinalResultStudent::create([
            'result_id' => $result->id,
            'placement_id' => $p25->id,
            'gpa' => 4.5,
            'gpa_status' => AcademicFinalResultStudent::GPA_COMPUTED,
            'passed_count' => 1,
            'failed_count' => 0,
        ]);
        $subject = Subject::create([
            'subject_type' => 'academic',
            'subject_code' => 'MATH'.mt_rand(10000, 99999),
            'name' => 'Mathematics',
            'slug' => str()->slug('Mathematics-'.uniqid()),
            'short_name' => 'Math',
            'status' => 'active',
        ]);
        AcademicFinalResultRow::create([
            'result_id' => $result->id,
            'placement_id' => $p25->id,
            'subject_id' => $subject->id,
            'status' => 'computed',
            'aggregate' => 90.0,
            'grade' => 'A',
            'grade_point' => 4.5,
            'subject_status' => 'PASS',
            'gpa_included' => true,
        ]);

        $frozen = ['academic_final_results', 'academic_final_result_students', 'academic_final_result_rows'];
        $before = [];
        foreach ($frozen as $table) {
            $before[$table] = collect(DB::table($table)->get()->map(fn ($row) => (array) $row));
        }

        TenantContext::set($c['institute']->id);
        $summary = $this->service()->summaryForPlacement($p25->refresh());
        $this->assertSame(1, $summary['total']);

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->attendanceUrl($student, $y25->id))
            ->assertOk();

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('students.academic-history', $student))
            ->assertOk();

        foreach ($frozen as $table) {
            $after = collect(DB::table($table)->get()->map(fn ($row) => (array) $row));
            $this->assertEquals($before[$table]->all(), $after->all(), "{$table} was mutated by the attendance integration.");
        }

        $this->assertDatabaseHas('academic_final_results', ['id' => $result->id, 'status' => AcademicFinalResult::STATUS_PUBLISHED]);
        $this->assertDatabaseHas('student_academic_placements', ['id' => $p25->id, 'status' => StudentAcademicPlacement::STATUS_ACTIVE]);
    }
}
