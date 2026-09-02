<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
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
use App\Models\StudentEnrollment;
use App\Services\AcademicAttendanceReportService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Step 20 — Read-only academic attendance reporting (AcademicAttendanceReport
 * Controller + Service): student, class/group and daily reports computed live
 * from the legacy `attendance` table; date-aware placement context; unrecorded
 * days never count as absence; tenant + branch isolation; clear messages for
 * out-of-scope filters; pagination; and a read-only guarantee (reports never
 * write).
 */
class AcademicAttendanceReportingTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    // ------------------------------------------------------------- Fixtures

    private function country(): Country
    {
        return Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh',
            'iso3' => 'BGD',
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

    private function group(ClassGrade $classGrade, string $name = 'General'): AcademicGroup
    {
        return AcademicGroup::create([
            'country_id' => $classGrade->country_id,
            'education_system_id' => $classGrade->education_system_id,
            'academic_level_id' => $classGrade->academic_level_id,
            'class_grade_id' => $classGrade->id,
            'name' => $name,
            'code' => 'g'.mt_rand(10, 99),
            'display_order' => 0,
            'status' => true,
        ]);
    }

    private function institute(Country $country, string $name): Institute
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
            'phone' => '01700'.mt_rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function student(Institute $institute, string $name, ?Branch $branch = null): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'student_id_number' => 'RP'.mt_rand(100000, 999999),
            'first_name' => $name,
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => '2026-01-01',
        ]);
    }

    private function year(Institute $institute, string $code, string $start, string $end): AcademicYear
    {
        return AcademicYear::create([
            'institute_id' => $institute->id,
            'name' => 'Session '.$code,
            'code' => $code,
            'start_date' => $start,
            'end_date' => $end,
            'is_current' => $code === date('Y'),
            'status' => true,
        ]);
    }

    private function batch(Institute $institute, string $code): Batch
    {
        return Batch::create([
            'institute_id' => $institute->id,
            'branch_id' => null,
            'course_id' => Course::create([
                'course_code' => 'C'.mt_rand(1000, 9999),
                'name' => 'Attendance Course',
            ])->id,
            'name' => 'Batch '.$code,
            'batch_code' => $code,
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'ongoing',
        ]);
    }

    private function placement(Institute $institute, Student $student, AcademicYear $academicYear, ClassGrade $class, ?AcademicGroup $group = null): StudentAcademicPlacement
    {
        return StudentAcademicPlacement::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'class_grade_id' => $class->id,
            'academic_group_id' => $group?->id,
            'status' => StudentAcademicPlacement::STATUS_ACTIVE,
        ]);
    }

    private function enroll(Student $student, Batch $batch): StudentEnrollment
    {
        return StudentEnrollment::create([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'course_id' => $batch->course_id,
            'batch_id' => $batch->id,
            'roll_number' => 'R'.mt_rand(100, 999),
            'enrollment_date' => '2026-01-01',
            'status' => 'active',
        ]);
    }

    private function attendanceRow(Institute $institute, Student $student, Batch $batch, string $date, string $status): Attendance
    {
        return Attendance::create([
            'institute_id' => $institute->id,
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'class_date' => $date,
            'status' => $status,
        ]);
    }

    // --------------------------------------------------------------- Helpers

    private function withContext(Institute $institute, ?Branch $branch = null): void
    {
        TenantContext::set($institute->id);
        BranchContext::set($branch?->id ?? null);
    }

    private function bodyHas(TestResponse $response, string $needle): void
    {
        $this->assertStringContainsString($needle, $response->getContent());
    }

    private function bodyMissing(TestResponse $response, string $needle): void
    {
        $this->assertStringNotContainsString($needle, $response->getContent());
    }

    private function studentReportUrl(Student $student, array $params = []): string
    {
        return route('academic-attendance.reports.student', $student).($params !== [] ? '?'.http_build_query($params) : '');
    }

    private function classReportUrl(array $params = []): string
    {
        return route('academic-attendance.reports.class').($params !== [] ? '?'.http_build_query($params) : '');
    }

    private function dailyReportUrl(array $params = []): string
    {
        return route('academic-attendance.reports.daily').($params !== [] ? '?'.http_build_query($params) : '');
    }

    /** Convenience: institute + class8/group + owner + 2026 year + batch + one placed & enrolled student. */
    private function standardContext(): array
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $institute = $this->institute($c, 'Report Institute');
        $owner = $this->user($institute, 'institute-owner', 'rp-owner');
        $group = $this->group($class8);
        $year = $this->year($institute, '2026', '2026-01-01', '2026-12-31');
        $batch = $this->batch($institute, 'BP-01');
        $student = $this->student($institute, 'Robin');
        $this->placement($institute, $student, $year, $class8, $group);
        $this->enroll($student, $batch);

        return compact('c', 'system', 'level', 'class8', 'institute', 'owner', 'group', 'year', 'batch', 'student');
    }

    // ----------------------------------------------------- Permission + pages

    public function test_all_report_pages_require_attendance_manage_permission(): void
    {
        $ctx = $this->standardContext();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get(route('academic-attendance.reports.index'))
            ->assertOk();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->studentReportUrl($ctx['student']))
            ->assertOk();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->classReportUrl())
            ->assertOk();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->dailyReportUrl())
            ->assertOk();

        $accountant = $this->user($ctx['institute'], 'accountant', 'rp-acct');

        $this->actingAs($accountant, 'institute_user')
            ->get(route('academic-attendance.reports.index'))
            ->assertForbidden();

        $this->actingAs($accountant, 'institute_user')
            ->get($this->studentReportUrl($ctx['student']))
            ->assertForbidden();

        $this->actingAs($accountant, 'institute_user')
            ->get($this->classReportUrl())
            ->assertForbidden();

        $this->actingAs($accountant, 'institute_user')
            ->get($this->dailyReportUrl())
            ->assertForbidden();
    }

    public function test_student_report_is_read_only(): void
    {
        $ctx = $this->standardContext();
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-15', 'present');

        $attendanceBefore = Attendance::query()->where('institute_id', $ctx['institute']->id)->count();
        $placementsBefore = StudentAcademicPlacement::query()->where('institute_id', $ctx['institute']->id)->count();
        $studentsBefore = Student::query()->where('institute_id', $ctx['institute']->id)->count();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->studentReportUrl($ctx['student']))
            ->assertOk();

        $this->assertSame($attendanceBefore, Attendance::query()->where('institute_id', $ctx['institute']->id)->count());
        $this->assertSame($placementsBefore, StudentAcademicPlacement::query()->where('institute_id', $ctx['institute']->id)->count());
        $this->assertSame($studentsBefore, Student::query()->where('institute_id', $ctx['institute']->id)->count());
    }

    // --------------------------------------------------------- Student report

    public function test_student_report_shows_totals_and_present_percent(): void
    {
        $ctx = $this->standardContext();
        foreach ([1, 2, 3, 4, 5] as $day) {
            $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-0'.$day, 'present');
        }
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-08', 'absent');
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-09', 'late');
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-10', 'leave');

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->studentReportUrl($ctx['student'], ['start_date' => '2026-01-01', 'end_date' => '2026-12-31']))
            ->assertOk();

        $this->bodyHas($response, '62.5%');
        $this->assertSame(8, Attendance::query()->where('student_id', $ctx['student']->id)->count());
    }

    public function test_student_report_respects_date_range(): void
    {
        $ctx = $this->standardContext();
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-01', 'present');
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-02', 'present');
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-12-20', 'present');

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->studentReportUrl($ctx['student'], ['start_date' => '2026-06-01', 'end_date' => '2026-09-01']))
            ->assertOk();

        $this->bodyHas($response, '100.0%');
        $this->bodyMissing($response, 'Dec 20, 2026');

        $this->withContext($ctx['institute']);
        $report = app(AcademicAttendanceReportService::class)
            ->studentReport($ctx['student'], Carbon::parse('2026-06-01'), Carbon::parse('2026-09-01'));
        $this->assertSame(2, $report['totals']['total']);
        $this->assertSame(2, $report['totals']['present']);
        $this->assertSame(0, $report['totals']['absent']);
    }

    public function test_student_report_missing_rows_are_not_absent(): void
    {
        $ctx = $this->standardContext();

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->studentReportUrl($ctx['student']))
            ->assertOk();

        $this->bodyHas($response, 'not counted as absent');
        $this->bodyHas($response, '—');

        $this->withContext($ctx['institute']);
        $report = app(AcademicAttendanceReportService::class)
            ->studentReport($ctx['student'], Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));
        $this->assertSame(0, $report['totals']['total']);
        $this->assertSame(0, $report['totals']['absent']);
        $this->assertNull($report['totals']['present_percent']);
    }

    public function test_student_report_academic_year_filter_narrows_window(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $institute = $this->institute($c, 'Year Filter Institute');
        $owner = $this->user($institute, 'institute-owner', 'rp-yowner');
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $class9 = $this->classGrade($level, 'c9', 'Class 9');
        $year2025 = $this->year($institute, '2025', '2025-03-01', '2025-12-31');
        $year2026 = $this->year($institute, '2026', '2026-01-01', '2026-12-31');
        $group = $this->group($class8);
        $batch25 = $this->batch($institute, 'YF-25');
        $batch26 = $this->batch($institute, 'YF-26');
        $student = $this->student($institute, 'Moni');
        $this->placement($institute, $student, $year2025, $class8, $group);
        $this->placement($institute, $student, $year2026, $class9);
        $this->enroll($student, $batch25);
        $this->enroll($student, $batch26);
        $this->attendanceRow($institute, $student, $batch25, '2025-06-10', 'present');
        $this->attendanceRow($institute, $student, $batch25, '2025-06-11', 'present');
        $this->attendanceRow($institute, $student, $batch26, '2026-06-10', 'absent');

        $response = $this->actingAs($owner, 'institute_user')
            ->get($this->studentReportUrl($student, ['academic_year_id' => $year2025->id]))
            ->assertOk();

        $this->bodyHas($response, 'Class 8');
        $this->bodyMissing($response, 'Class 9');

        $this->withContext($institute);
        $report = app(AcademicAttendanceReportService::class)
            ->studentReport($student, Carbon::parse('2025-03-01'), Carbon::parse('2025-12-31'), $year2025);
        $this->assertSame(2, $report['totals']['present']);
        $this->assertSame(0, $report['totals']['absent']);
    }

    public function test_student_report_context_is_date_aware_across_years(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $institute = $this->institute($c, 'Context Institute');
        $owner = $this->user($institute, 'institute-owner', 'rp-cowner');
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $class9 = $this->classGrade($level, 'c9', 'Class 9');
        $year2025 = $this->year($institute, '2025', '2025-03-01', '2025-12-31');
        $year2026 = $this->year($institute, '2026', '2026-01-01', '2026-12-31');
        $group = $this->group($class8);
        $batch25 = $this->batch($institute, 'CT-25');
        $batch26 = $this->batch($institute, 'CT-26');
        $student = $this->student($institute, 'Nirob');
        $this->placement($institute, $student, $year2025, $class8, $group);
        $this->placement($institute, $student, $year2026, $class9);
        $this->enroll($student, $batch25);
        $this->enroll($student, $batch26);
        $this->attendanceRow($institute, $student, $batch25, '2025-06-15', 'present');
        $this->attendanceRow($institute, $student, $batch26, '2026-06-15', 'absent');

        $response = $this->actingAs($owner, 'institute_user')
            ->get($this->studentReportUrl($student, ['start_date' => '2025-06-01', 'end_date' => '2026-07-31']))
            ->assertOk();

        $this->bodyHas($response, 'Class 8');
        $this->bodyHas($response, 'Class 9');
        $this->bodyHas($response, 'Session 2025');
        $this->bodyHas($response, 'Session 2026');
    }

    public function test_student_report_cross_tenant_student_is_404(): void
    {
        $ctx = $this->standardContext();

        $alienInstitute = $this->institute($ctx['c'], 'Alien Institute');
        $alienYear = $this->year($alienInstitute, '2026', '2026-01-01', '2026-12-31');
        $alien = $this->student($alienInstitute, 'Alien');
        $this->placement($alienInstitute, $alien, $alienYear, $ctx['class8']);

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->studentReportUrl($alien))
            ->assertNotFound();
    }

    public function test_student_report_cross_branch_student_is_404_for_branch_manager(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $institute = $this->institute($c, 'Branch Rpt Institute');
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $owner = $this->user($institute, 'institute-owner', 'rp-bowner');
        $managerA = $this->user($institute, 'branch-manager', 'rp-bmgr', $branchA);
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $year = $this->year($institute, '2026', '2026-01-01', '2026-12-31');
        $group = $this->group($class8);

        $inB = $this->student($institute, 'Remote', $branchB);
        $this->placement($institute, $inB, $year, $class8, $group);

        $this->actingAs($managerA, 'institute_user')
            ->get($this->studentReportUrl($inB))
            ->assertNotFound();

        $this->actingAs($owner, 'institute_user')
            ->get($this->studentReportUrl($inB))
            ->assertOk();
    }

    public function test_academic_history_links_attendance_report_only_for_allowed_users(): void
    {
        $ctx = $this->standardContext();
        $accountant = $this->user($ctx['institute'], 'accountant', 'rp-hacct');

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get(route('students.academic-history', $ctx['student']))
            ->assertOk()
            ->assertSee('View Attendance Report');

        $this->actingAs($accountant, 'institute_user')
            ->get(route('students.academic-history', $ctx['student']))
            ->assertOk()
            ->assertDontSee('View Attendance Report');
    }

    // --------------------------------------------------------- Class / Group

    public function test_class_report_requires_context(): void
    {
        $ctx = $this->standardContext();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->classReportUrl())
            ->assertOk()
            ->assertSee('Select an academic year first.');

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->classReportUrl(['academic_year_id' => $ctx['year']->id]))
            ->assertOk()
            ->assertSee('Select a class/grade first.');

        $accountant = $this->user($ctx['institute'], 'accountant', 'rp-cacct');
        $this->actingAs($accountant, 'institute_user')
            ->get($this->classReportUrl(['academic_year_id' => $ctx['year']->id, 'class_grade_id' => $ctx['class8']->id]))
            ->assertForbidden();
    }

    public function test_class_report_roster_is_scoped_to_selected_class(): void
    {
        $ctx = $this->standardContext();
        $class7 = $this->classGrade($ctx['level'], 'c7', 'Class 7');
        $other = $this->student($ctx['institute'], 'OtherClass');
        $this->placement($ctx['institute'], $other, $ctx['year'], $class7);

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->classReportUrl(['academic_year_id' => $ctx['year']->id, 'class_grade_id' => $ctx['class8']->id]))
            ->assertOk();

        $this->bodyHas($response, 'Robin');
        $this->bodyMissing($response, 'OtherClass');
    }

    public function test_class_report_aggregates_per_student_over_window(): void
    {
        $ctx = $this->standardContext();
        $studentB = $this->student($ctx['institute'], 'Tania');
        $this->placement($ctx['institute'], $studentB, $ctx['year'], $ctx['class8'], $ctx['group']);
        $this->enroll($studentB, $this->batch($ctx['institute'], 'BP-02'));

        foreach (['2026-06-01', '2026-06-02', '2026-06-03'] as $date) {
            $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], $date, 'present');
        }
        foreach (['2026-06-01', '2026-06-02'] as $date) {
            $this->attendanceRow($ctx['institute'], $studentB, $ctx['batch'], $date, 'absent');
        }

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->classReportUrl(['academic_year_id' => $ctx['year']->id, 'class_grade_id' => $ctx['class8']->id]))
            ->assertOk();

        $this->bodyHas($response, 'Robin');
        $this->bodyHas($response, 'Tania');
        $this->bodyHas($response, '100.0%');
        $this->bodyHas($response, '0.0%');
    }

    public function test_class_report_totals_match_the_sum_of_rows(): void
    {
        $ctx = $this->standardContext();
        $studentB = $this->student($ctx['institute'], 'Sufia');
        $this->placement($ctx['institute'], $studentB, $ctx['year'], $ctx['class8'], $ctx['group']);
        $this->enroll($studentB, $this->batch($ctx['institute'], 'BP-03'));

        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-01', 'present');
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-02', 'present');
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-03', 'present');
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-04', 'late');
        $this->attendanceRow($ctx['institute'], $studentB, $ctx['batch'], '2026-06-01', 'absent');
        $this->attendanceRow($ctx['institute'], $studentB, $ctx['batch'], '2026-06-02', 'absent');
        $this->attendanceRow($ctx['institute'], $studentB, $ctx['batch'], '2026-06-03', 'leave');

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->classReportUrl(['academic_year_id' => $ctx['year']->id, 'class_grade_id' => $ctx['class8']->id]))
            ->assertOk();

        $this->bodyHas($response, '75.0%');
        $this->bodyHas($response, '0.0%');
        $this->bodyHas($response, '42.9%');

        $this->withContext($ctx['institute']);
        $report = app(AcademicAttendanceReportService::class)
            ->classReport((int) $ctx['year']->id, (int) $ctx['class8']->id, null, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));
        $this->assertSame(7, $report['totals']['total']);
        $this->assertSame(3, $report['totals']['present']);
        $this->assertSame(2, $report['totals']['absent']);
        $this->assertSame(1, $report['totals']['late']);
        $this->assertSame(1, $report['totals']['leave']);
    }

    public function test_class_report_group_filter_narrows_roster(): void
    {
        $ctx = $this->standardContext();
        $arts = $this->group($ctx['class8'], 'Arts');
        $artsKid = $this->student($ctx['institute'], 'Artsy');
        $this->placement($ctx['institute'], $artsKid, $ctx['year'], $ctx['class8'], $arts);

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->classReportUrl([
                'academic_year_id' => $ctx['year']->id,
                'class_grade_id' => $ctx['class8']->id,
                'academic_group_id' => $ctx['group']->id,
            ]))
            ->assertOk();

        $this->bodyHas($response, 'Robin');
        $this->bodyMissing($response, 'Artsy');
    }

    public function test_class_report_branch_manager_only_sees_own_branch(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $institute = $this->institute($c, 'Branch Class Institute');
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $owner = $this->user($institute, 'institute-owner', 'rp-bcowner');
        $managerA = $this->user($institute, 'branch-manager', 'rp-bcmgr', $branchA);
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $year = $this->year($institute, '2026', '2026-01-01', '2026-12-31');
        $group = $this->group($class8);
        $inA = $this->student($institute, 'Lokal', $branchA);
        $inB = $this->student($institute, 'Remote', $branchB);
        $this->placement($institute, $inA, $year, $class8, $group);
        $this->placement($institute, $inB, $year, $class8, $group);

        $params = ['academic_year_id' => $year->id, 'class_grade_id' => $class8->id];

        $managerReport = $this->actingAs($managerA, 'institute_user')
            ->get($this->classReportUrl($params))
            ->assertOk();
        $this->bodyHas($managerReport, 'Lokal');
        $this->bodyMissing($managerReport, 'Remote');

        $ownerReport = $this->actingAs($owner, 'institute_user')
            ->get($this->classReportUrl($params))
            ->assertOk();
        $this->bodyHas($ownerReport, 'Lokal');
        $this->bodyHas($ownerReport, 'Remote');
    }

    public function test_class_report_no_records_shows_zero_and_dash_not_absent(): void
    {
        $ctx = $this->standardContext();

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->classReportUrl(['academic_year_id' => $ctx['year']->id, 'class_grade_id' => $ctx['class8']->id]))
            ->assertOk();

        $this->bodyHas($response, 'Robin');
        $this->bodyHas($response, '—');

        $this->withContext($ctx['institute']);
        $report = app(AcademicAttendanceReportService::class)
            ->classReport((int) $ctx['year']->id, (int) $ctx['class8']->id, null, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31'));
        $this->assertSame(0, $report['totals']['absent']);
        $this->assertSame(0, $report['totals']['total']);
        $this->assertNull($report['totals']['present_percent']);
    }

    public function test_class_report_clamps_window_to_academic_year(): void
    {
        $ctx = $this->standardContext();
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-05-01', 'present');
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2025-05-01', 'present');

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->classReportUrl([
                'academic_year_id' => $ctx['year']->id,
                'class_grade_id' => $ctx['class8']->id,
                'start_date' => '2025-01-01',
                'end_date' => '2026-12-31',
            ]))
            ->assertOk();

        $this->bodyHas($response, '100.0%');

        $this->withContext($ctx['institute']);
        $report = app(AcademicAttendanceReportService::class)
            ->classReport((int) $ctx['year']->id, (int) $ctx['class8']->id, null, Carbon::parse('2025-01-01'), Carbon::parse('2026-12-31'));
        $this->assertSame(1, $report['totals']['total']);
        $this->assertSame(1, $report['totals']['present']);
    }

    public function test_class_report_paginates_large_rosters(): void
    {
        $ctx = $this->standardContext();
        for ($i = 1; $i <= 60; $i++) {
            $s = $this->student($ctx['institute'], 'P'.str_pad((string) $i, 3, '0', STR_PAD_LEFT));
            $this->placement($ctx['institute'], $s, $ctx['year'], $ctx['class8'], $ctx['group']);
        }

        $params = ['academic_year_id' => $ctx['year']->id, 'class_grade_id' => $ctx['class8']->id];

        $page1 = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->classReportUrl($params))
            ->assertOk();
        $this->bodyHas($page1, 'P001');
        $this->bodyMissing($page1, 'P060');

        $page2 = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->classReportUrl($params + ['page' => 2]))
            ->assertOk();
        $this->bodyHas($page2, 'P060');
        $this->bodyMissing($page2, 'P001');
    }

    public function test_class_report_rejects_out_of_scope_class(): void
    {
        $ctx = $this->standardContext();
        $emptyClass = $this->classGrade($ctx['level'], 'c9', 'Class 9');

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->classReportUrl(['academic_year_id' => $ctx['year']->id, 'class_grade_id' => $emptyClass->id]))
            ->assertOk()
            ->assertSee('The selected class/grade has no students in this institute context.');
    }

    // ------------------------------------------------------------- Daily report

    public function test_daily_report_requires_permission(): void
    {
        $ctx = $this->standardContext();
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-15', 'present');

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->dailyReportUrl(['attendance_date' => '2026-06-15']))
            ->assertOk();

        $accountant = $this->user($ctx['institute'], 'accountant', 'rp-dacct');
        $this->actingAs($accountant, 'institute_user')
            ->get($this->dailyReportUrl(['attendance_date' => '2026-06-15']))
            ->assertForbidden();
    }

    public function test_daily_report_shows_the_status_of_each_student(): void
    {
        $ctx = $this->standardContext();
        $studentB = $this->student($ctx['institute'], 'Kona');
        $this->placement($ctx['institute'], $studentB, $ctx['year'], $ctx['class8'], $ctx['group']);
        $this->enroll($studentB, $this->batch($ctx['institute'], 'BP-04'));
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-15', 'present');
        $this->attendanceRow($ctx['institute'], $studentB, $ctx['batch'], '2026-06-15', 'absent');

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->dailyReportUrl(['attendance_date' => '2026-06-15']))
            ->assertOk();

        $this->bodyHas($response, 'Robin');
        $this->bodyHas($response, 'Kona');
        $this->bodyHas($response, 'text-success');
        $this->bodyHas($response, 'text-danger');
    }

    public function test_daily_report_unmarked_students_are_not_absent(): void
    {
        $ctx = $this->standardContext();
        $studentB = $this->student($ctx['institute'], 'Unmarked B');
        $studentC = $this->student($ctx['institute'], 'Unmarked C');
        $this->placement($ctx['institute'], $studentB, $ctx['year'], $ctx['class8'], $ctx['group']);
        $this->placement($ctx['institute'], $studentC, $ctx['year'], $ctx['class8'], $ctx['group']);
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-15', 'present');

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->dailyReportUrl(['attendance_date' => '2026-06-15']))
            ->assertOk();

        $this->bodyHas($response, 'Not recorded');

        $this->withContext($ctx['institute']);
        $report = app(AcademicAttendanceReportService::class)
            ->dailyReport(Carbon::parse('2026-06-15'), null, null);
        $this->assertSame(1, $report['totals']['present']);
        $this->assertSame(0, $report['totals']['absent']);
        $this->assertSame(1, $report['totals']['marked']);
        $this->assertSame(2, $report['totals']['unmarked']);
    }

    public function test_daily_report_resolves_the_academic_year_from_the_date(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $institute = $this->institute($c, 'Daily Year Institute');
        $owner = $this->user($institute, 'institute-owner', 'rp-dyowner');
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $year2025 = $this->year($institute, '2025', '2025-01-01', '2025-12-31');
        $year2026 = $this->year($institute, '2026', '2026-01-01', '2026-12-31');
        $group = $this->group($class8);
        $batch = $this->batch($institute, 'DY-26');
        $student = $this->student($institute, 'Dipped');
        $this->placement($institute, $student, $year2025, $class8, $group);
        $this->placement($institute, $student, $year2026, $class8, $group);
        $this->enroll($student, $batch);
        $this->attendanceRow($institute, $student, $batch, '2026-06-15', 'present');

        $response = $this->actingAs($owner, 'institute_user')
            ->get($this->dailyReportUrl(['attendance_date' => '2026-06-15']))
            ->assertOk();

        $this->bodyHas($response, 'Session 2026');
        $this->bodyMissing($response, 'Session 2025');
        $this->bodyHas($response, 'Dipped');
    }

    public function test_daily_report_date_outside_any_academic_year_is_graceful(): void
    {
        $ctx = $this->standardContext();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->dailyReportUrl(['attendance_date' => '2020-01-01']))
            ->assertOk()
            ->assertSee('No academic year in this institute covers the selected date.');
    }

    public function test_daily_report_group_filter_narrows_roster(): void
    {
        $ctx = $this->standardContext();
        $arts = $this->group($ctx['class8'], 'Arts');
        $artsKid = $this->student($ctx['institute'], 'Artsy');
        $this->placement($ctx['institute'], $artsKid, $ctx['year'], $ctx['class8'], $arts);
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-15', 'present');

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->dailyReportUrl([
                'attendance_date' => '2026-06-15',
                'class_grade_id' => $ctx['class8']->id,
                'academic_group_id' => $ctx['group']->id,
            ]))
            ->assertOk();

        $this->bodyHas($response, 'Robin');
        $this->bodyMissing($response, 'Artsy');
    }

    public function test_daily_report_branch_manager_only_sees_own_branch(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $institute = $this->institute($c, 'Daily Branch Institute');
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $managerA = $this->user($institute, 'branch-manager', 'rp-dbgr', $branchA);
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $year = $this->year($institute, '2026', '2026-01-01', '2026-12-31');
        $group = $this->group($class8);
        $inA = $this->student($institute, 'Lokal', $branchA);
        $inB = $this->student($institute, 'Remote', $branchB);
        $this->placement($institute, $inA, $year, $class8, $group);
        $this->placement($institute, $inB, $year, $class8, $group);
        $this->attendanceRow($institute, $inA, $this->batch($institute, 'DB-A'), '2026-06-15', 'present');
        $this->attendanceRow($institute, $inB, $this->batch($institute, 'DB-B'), '2026-06-15', 'absent');

        $response = $this->actingAs($managerA, 'institute_user')
            ->get($this->dailyReportUrl(['attendance_date' => '2026-06-15']))
            ->assertOk();

        $this->bodyHas($response, 'Lokal');
        $this->bodyMissing($response, 'Remote');

        $this->assertSame(1, Attendance::query()
            ->where('student_id', $inA->id)
            ->where('class_date', '2026-06-15')
            ->count());
    }
}
