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
use App\Models\PromotionDecision;
use App\Models\PromotionDecisionItem;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\StudentEnrollment;
use App\Models\StudentSubjectSelection;
use App\Models\Subject;
use App\Models\SubjectAcademicAssignment;
use App\Services\AcademicAttendanceExportService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Step 21 — Academic data export & administrative reporting.
 *
 * Attends a CSV download to each Step-20 attendance report (student /
 * class-group / daily — strictly read-only, tenant + branch scoped, never
 * promotes unrecorded days to "absent") and a CSV download of a PUBLISHED
 * final result that is produced exclusively from the frozen snapshot tables
 * (never from live marks, the derived preview or grading services).
 */
class AcademicDataExportTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    // ------------------------------------------------------- Attendance fixtures

    private function country(): Country
    {
        return Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true],
        );
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
            'registration_number' => 'REG'.mt_rand(100000, 999999),
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

    private function attendanceRow(Institute $institute, Student $student, Batch $batch, string $date, string $status, ?string $remarks = null): Attendance
    {
        return Attendance::create([
            'institute_id' => $institute->id,
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'class_date' => $date,
            'status' => $status,
            'remarks' => $remarks,
        ]);
    }

    private function withContext(Institute $institute, ?Branch $branch = null): void
    {
        TenantContext::set($institute->id);
        BranchContext::set($branch?->id ?? null);
    }

    /** Convenience institute + class8 + group + 2026 year + batch + one placed student. */
    private function standardContext(): array
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $institute = $this->institute($c, 'Export Institute');
        $owner = $this->user($institute, 'institute-owner', 'ex-owner');
        $group = $this->group($class8);
        $year = $this->year($institute, '2026', '2026-01-01', '2026-12-31');
        $batch = $this->batch($institute, 'EX-01');
        $student = $this->student($institute, 'Robin');
        $this->placement($institute, $student, $year, $class8, $group);
        $this->enroll($student, $batch);

        return compact('c', 'class8', 'institute', 'owner', 'group', 'year', 'batch', 'student');
    }

    private function studentExportUrl(Student $student, array $params = []): string
    {
        return route('academic-attendance.reports.export.student', $student).($params !== [] ? '?'.http_build_query($params) : '');
    }

    private function classExportUrl(array $params = []): string
    {
        return route('academic-attendance.reports.export.class').($params !== [] ? '?'.http_build_query($params) : '');
    }

    private function dailyExportUrl(array $params = []): string
    {
        return route('academic-attendance.reports.export.daily').($params !== [] ? '?'.http_build_query($params) : '');
    }

    // --------------------------------------------------------- Result fixtures

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

    private function curriculum(): array
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $class = $this->classGrade($level, 'ex-c8', 'Class 8');
        $group = $this->group($class);
        $institute = $this->institute($c, 'Result Export Institute');
        $owner = $this->user($institute, 'institute-owner', 'ex-r-owner');

        $math = $this->subject('Mathematics', 'EX100001');
        $english = $this->subject('English', 'EX100002');
        $this->assign($math, $class);
        $this->assign($english, $class);

        return compact('c', 'class', 'group', 'institute', 'owner', 'math', 'english');
    }

    private function placementWithSubjects(array $c, Student $student, AcademicYear $year, ClassGrade $class, ?AcademicGroup $group = null): StudentAcademicPlacement
    {
        $placement = $this->placement($c['institute'], $student, $year, $class, $group);

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

    private function createResult(array $c, AcademicYear $year, ClassGrade $class, ?AcademicGroup $group, string $name, ?Branch $branch = null, string $status = AcademicFinalResult::STATUS_PUBLISHED): AcademicFinalResult
    {
        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => $branch?->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $class->id,
            'academic_group_id' => $group?->id,
            'name' => 'Scheme '.$name,
            'status' => 'active',
            'display_order' => 1,
        ]);

        $policy = AcademicFinalResultPolicy::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => $branch?->id,
            'scheme_id' => $scheme->id,
            'name' => $name.' Policy',
        ]);

        return AcademicFinalResult::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => $branch?->id,
            'policy_id' => $policy->id,
            'scheme_id' => $scheme->id,
            'name' => $name,
            'status' => $status,
            'locked_at' => in_array($status, [AcademicFinalResult::STATUS_LOCKED, AcademicFinalResult::STATUS_PUBLISHED], true) ? now() : null,
            'published_at' => $status === AcademicFinalResult::STATUS_PUBLISHED ? now() : null,
        ]);
    }

    private function addSnapshot(AcademicFinalResult $result, StudentAcademicPlacement $placement, float $gpa = 4.75, array $rows = [], int $passed = 2, int $failed = 0): void
    {
        AcademicFinalResultStudent::create([
            'result_id' => $result->id,
            'placement_id' => $placement->id,
            'gpa' => $gpa,
            'gpa_status' => AcademicFinalResultStudent::GPA_COMPUTED,
            'passed_count' => $passed,
            'failed_count' => $failed,
        ]);

        foreach ($rows as $row) {
            AcademicFinalResultRow::create([
                'result_id' => $result->id,
                'placement_id' => $placement->id,
                'subject_id' => $row['subject']->id,
                'status' => 'computed',
                'aggregate' => $row['aggregate'],
                'grade' => $row['grade'],
                'grade_point' => $row['grade_point'],
                'subject_status' => $row['subject_status'],
                'gpa_included' => $row['gpa_included'] ?? true,
                'credits' => $row['credits'] ?? null,
                'optional' => $row['optional'] ?? false,
            ]);
        }
    }

    private function standardRows(array $c): array
    {
        return [
            ['subject' => $c['math'], 'aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS', 'gpa_included' => true],
            ['subject' => $c['english'], 'aggregate' => 85.0, 'grade' => 'A', 'grade_point' => 4.0, 'subject_status' => 'PASS', 'gpa_included' => true],
        ];
    }

    private function resultExportUrl(AcademicFinalResult $result): string
    {
        return route('settings.academic.final-results.export', $result);
    }

    // -------------------------------------------------------------- CSV helpers

    private function csvContent(TestResponse $response): string
    {
        return $response->streamedContent();
    }

    /** BOM + header + every data row, as parsed rows. */
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

    private function csvRowsFor(TestResponse $response): array
    {
        $all = $this->parseCsv($this->csvContent($response));
        array_shift($all); // drop the header row

        return $all;
    }

    private function assertGuestRedirectedToLogin(TestResponse $response): void
    {
        $response->assertRedirect();

        $this->assertStringContainsString('login', (string) $response->headers->get('location'));
    }

    // --------------------------------------------------------- Attendance export

    public function test_authorized_student_attendance_export_succeeds(): void
    {
        $ctx = $this->standardContext();
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-01', 'present', 'On time');
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-02', 'absent');

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->studentExportUrl($ctx['student']))
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('student-attendance-report-'.$ctx['student']->student_id_number, (string) $response->headers->get('content-disposition'));

        $body = $this->csvContent($response);
        $this->assertStringContainsString('Student Name', $body);
        $this->assertStringContainsString('Registration Number', $body);
        $this->assertStringContainsString('Remarks', $body);
        $this->assertStringContainsString('Robin Student', $body);
        $this->assertStringContainsString('present', $body);
        $this->assertStringContainsString('absent', $body);
        $this->assertStringContainsString('On time', $body);
    }

    public function test_authorized_class_attendance_export_succeeds(): void
    {
        $ctx = $this->standardContext();
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-01', 'present');
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-02', 'late');

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->classExportUrl(['academic_year_id' => $ctx['year']->id, 'class_grade_id' => $ctx['class8']->id]))
            ->assertOk();

        $body = $this->csvContent($response);
        $this->assertStringContainsString('Present', $body);
        $this->assertStringContainsString('Attendance %', $body);
        $this->assertStringContainsString('Robin Student', $body);
        $this->assertStringContainsString('50.0%', $body);
    }

    public function test_authorized_daily_attendance_export_succeeds(): void
    {
        $ctx = $this->standardContext();
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-15', 'leave');

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->dailyExportUrl(['attendance_date' => '2026-06-15']))
            ->assertOk();

        $body = $this->csvContent($response);
        $this->assertStringContainsString('daily-attendance-report-2026-06-15.csv', (string) $response->headers->get('content-disposition'));
        $this->assertStringContainsString('Robin Student', $body);
        $this->assertStringContainsString('leave', $body);
    }

    public function test_attendance_export_requires_attendance_manage_permission(): void
    {
        $ctx = $this->standardContext();
        $accountant = $this->user($ctx['institute'], 'accountant', 'ex-acct');

        $this->actingAs($accountant, 'institute_user')
            ->get($this->studentExportUrl($ctx['student']))
            ->assertForbidden();

        $this->actingAs($accountant, 'institute_user')
            ->get($this->classExportUrl(['academic_year_id' => $ctx['year']->id, 'class_grade_id' => $ctx['class8']->id]))
            ->assertForbidden();

        $this->actingAs($accountant, 'institute_user')
            ->get($this->dailyExportUrl(['attendance_date' => '2026-06-15']))
            ->assertForbidden();
    }

    public function test_attendance_export_guest_is_redirected_to_login(): void
    {
        $ctx = $this->standardContext();

        $this->assertGuestRedirectedToLogin(
            $this->get($this->studentExportUrl($ctx['student']))
        );

        $this->assertGuestRedirectedToLogin($this->get($this->classExportUrl()));
        $this->assertGuestRedirectedToLogin($this->get($this->dailyExportUrl()));
    }

    public function test_cross_tenant_student_attendance_export_is_404(): void
    {
        $ctx = $this->standardContext();

        $alienInstitute = $this->institute($ctx['c'], 'Alien Export Institute');
        $alienYear = $this->year($alienInstitute, '2026', '2026-01-01', '2026-12-31');
        $alien = $this->student($alienInstitute, 'Alien');
        $this->placement($alienInstitute, $alien, $alienYear, $ctx['class8']);

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->studentExportUrl($alien))
            ->assertNotFound();
    }

    public function test_cross_tenant_class_attendance_export_degrades_gracefully(): void
    {
        $ctx = $this->standardContext();

        $alienInstitute = $this->institute($ctx['c'], 'Alien Year Institute');
        $alienYear = $this->year($alienInstitute, '2026', '2026-01-01', '2026-12-31');

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->classExportUrl(['academic_year_id' => $alienYear->id, 'class_grade_id' => $ctx['class8']->id]))
            ->assertStatus(422);
    }

    public function test_branch_manager_attendance_export_only_sees_own_branch(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $institute = $this->institute($c, 'Branch Export Institute');
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $managerA = $this->user($institute, 'branch-manager', 'ex-bmgr', $branchA);
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $year = $this->year($institute, '2026', '2026-01-01', '2026-12-31');
        $group = $this->group($class8);
        $inA = $this->student($institute, 'Lokal', $branchA);
        $inB = $this->student($institute, 'Remote', $branchB);
        $this->placement($institute, $inA, $year, $class8, $group);
        $this->placement($institute, $inB, $year, $class8, $group);

        $params = ['academic_year_id' => $year->id, 'class_grade_id' => $class8->id];

        $response = $this->actingAs($managerA, 'institute_user')
            ->get($this->classExportUrl($params))
            ->assertOk();

        $body = $this->csvContent($response);
        $this->assertStringContainsString('Lokal Student', $body);
        $this->assertStringNotContainsString('Remote Student', $body);
    }

    public function test_student_attendance_export_respects_academic_year_filter(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $institute = $this->institute($c, 'Year Filter Institute');
        $owner = $this->user($institute, 'institute-owner', 'ex-yowner');
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
        $this->attendanceRow($institute, $student, $batch26, '2026-06-10', 'absent');

        $response = $this->actingAs($owner, 'institute_user')
            ->get($this->studentExportUrl($student, ['academic_year_id' => $year2025->id]))
            ->assertOk();

        $body = $this->csvContent($response);
        $this->assertStringContainsString('2025-06-10', $body);
        $this->assertStringNotContainsString('2026-06-10', $body);
        $this->assertStringContainsString('Session 2025', $body);
    }

    public function test_student_attendance_export_respects_date_range_filter(): void
    {
        $ctx = $this->standardContext();
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-01', 'present');
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-02', 'present');
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-12-20', 'present');

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->studentExportUrl($ctx['student'], ['start_date' => '2026-06-01', 'end_date' => '2026-09-01']))
            ->assertOk();

        $body = $this->csvContent($response);
        $this->assertStringContainsString('2026-06-01', $body);
        $this->assertStringContainsString('2026-06-02', $body);
        $this->assertStringNotContainsString('2026-12-20', $body);
    }

    public function test_class_attendance_export_respects_group_filter(): void
    {
        $ctx = $this->standardContext();
        $arts = $this->group($ctx['class8'], 'Arts');
        $artsKid = $this->student($ctx['institute'], 'Artsy');
        $this->placement($ctx['institute'], $artsKid, $ctx['year'], $ctx['class8'], $arts);

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->classExportUrl([
                'academic_year_id' => $ctx['year']->id,
                'class_grade_id' => $ctx['class8']->id,
                'academic_group_id' => $ctx['group']->id,
            ]))
            ->assertOk();

        $body = $this->csvContent($response);
        $this->assertStringContainsString('Robin Student', $body);
        $this->assertStringNotContainsString('Artsy Student', $body);
    }

    public function test_attendance_export_never_turns_missing_rows_into_absent(): void
    {
        $ctx = $this->standardContext();
        $studentB = $this->student($ctx['institute'], 'Fresh');
        $this->placement($ctx['institute'], $studentB, $ctx['year'], $ctx['class8'], $ctx['group']);

        $daily = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->dailyExportUrl(['attendance_date' => '2026-06-15']))
            ->assertOk();

        $this->assertStringContainsString('not recorded', $this->csvContent($daily));

        $class = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->classExportUrl(['academic_year_id' => $ctx['year']->id, 'class_grade_id' => $ctx['class8']->id]))
            ->assertOk();

        $rows = $this->csvRowsFor($class);
        $fresh = collect($rows)->first(fn ($row) => $row[0] === 'Fresh Student');

        $this->assertNotNull($fresh);
        $this->assertSame('0', $fresh[8]);
        $this->assertSame('—', $fresh[9]);

        $student = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->studentExportUrl($ctx['student']))
            ->assertOk();

        $this->assertSame([], $this->csvRowsFor($student));
    }

    public function test_daily_attendance_export_contains_correct_status_values(): void
    {
        $ctx = $this->standardContext();
        $mk = function (string $name, ?string $status) use ($ctx) {
            $s = $this->student($ctx['institute'], $name);
            $this->placement($ctx['institute'], $s, $ctx['year'], $ctx['class8'], $ctx['group']);
            $this->enroll($s, $this->batch($ctx['institute'], 'ST-'.$name));
            if ($status !== null) {
                $this->attendanceRow($ctx['institute'], $s, $ctx['batch'], '2026-06-15', $status);
            }

            return $s;
        };

        $mk('PresentOne', 'present');
        $mk('AbsentOne', 'absent');
        $mk('LateOne', 'late');
        $mk('LeaveOne', 'leave');
        $mk('NoRecordOne', null);

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->dailyExportUrl(['attendance_date' => '2026-06-15']))
            ->assertOk();

        $body = $this->csvContent($response);
        $this->assertStringContainsString('present', $body);
        $this->assertStringContainsString('absent', $body);
        $this->assertStringContainsString('late', $body);
        $this->assertStringContainsString('leave', $body);
        $this->assertStringContainsString('NoRecordOne Student', $body);
    }

    public function test_attendance_export_is_strictly_read_only(): void
    {
        $ctx = $this->standardContext();
        $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-15', 'present');

        $attendanceBefore = Attendance::query()->count();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->dailyExportUrl(['attendance_date' => '2026-06-15']))
            ->assertOk();

        $this->assertSame($attendanceBefore, Attendance::query()->count());
    }

    public function test_attendance_export_rows_are_lazy_streams(): void
    {
        $ctx = $this->standardContext();
        for ($day = 1; $day <= 30; $day++) {
            $this->attendanceRow($ctx['institute'], $ctx['student'], $ctx['batch'], '2026-06-'.str_pad((string) $day, 2, '0', STR_PAD_LEFT), 'present');
        }
        $this->withContext($ctx['institute']);

        $export = app(AcademicAttendanceExportService::class)
            ->classReport((int) $ctx['year']->id, (int) $ctx['class8']->id, null, now()->startOfYear(), now());

        $this->assertInstanceOf(\Generator::class, $export['rows']);
        $this->assertSame(1, iterator_count($export['rows']));
    }

    public function test_csv_headers_and_escaping_are_correct(): void
    {
        $ctx = $this->standardContext();
        $student = $this->student($ctx['institute'], 'Robin, "the Bold"');
        $this->placement($ctx['institute'], $student, $ctx['year'], $ctx['class8'], $ctx['group']);
        $this->enroll($student, $this->batch($ctx['institute'], 'CSV-B'));
        $this->attendanceRow($ctx['institute'], $student, $ctx['batch'], '2026-06-01', 'present', "Left early\nSaid \"hi\"");

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get($this->studentExportUrl($student))
            ->assertOk();

        $all = $this->parseCsv($this->csvContent($response));

        $this->assertSame([
            'Student Name', 'Student ID', 'Registration Number', 'Academic Year',
            'Class / Grade', 'Group / Stream', 'Date', 'Batch', 'Status', 'Remarks',
        ], $all[0]);

        $data = array_slice($all, 1);
        $this->assertCount(1, $data);
        $this->assertSame('Robin, "the Bold" Student', $data[0][0]);
        $this->assertSame('2026-06-01', $data[0][6]);
        $this->assertSame('present', $data[0][8]);
        $this->assertSame("Left early\nSaid \"hi\"", $data[0][9]);
    }

    // ------------------------------------------------------------- Result export

    public function test_authorized_published_result_export_succeeds(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', '2026-01-01', '2026-12-31');
        $placement = $this->placementWithSubjects($c, $this->student($c['institute'], 'Rahim'), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($result, $placement, 4.75, $this->standardRows($c));

        $response = $this->actingAs($c['owner'], 'institute_user')
            ->get($this->resultExportUrl($result))
            ->assertOk();

        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('term-final-2026-result', (string) $response->headers->get('content-disposition'));

        $body = $this->csvContent($response);
        $this->assertStringContainsString('Subject', $body);
        $this->assertStringContainsString('Aggregate %', $body);
        $this->assertStringContainsString('Rahim Student', $body);
        $this->assertStringContainsString('Mathematics', $body);
        $this->assertStringContainsString('90.5%', $body);
        $this->assertStringContainsString('A+', $body);
        $this->assertStringContainsString('4.75', $body);
    }

    public function test_result_export_requires_education_manage_permission(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', '2026-01-01', '2026-12-31');
        $placement = $this->placementWithSubjects($c, $this->student($c['institute'], 'Rahim'), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($result, $placement, 4.75, $this->standardRows($c));

        $accountant = $this->user($c['institute'], 'accountant', 'ex-r-acct');

        $this->actingAs($accountant, 'institute_user')
            ->get($this->resultExportUrl($result))
            ->assertForbidden();
    }

    public function test_result_export_guest_is_redirected_to_login(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', '2026-01-01', '2026-12-31');
        $placement = $this->placementWithSubjects($c, $this->student($c['institute'], 'Rahim'), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($result, $placement, 4.75, $this->standardRows($c));

        $this->assertGuestRedirectedToLogin(
            $this->get($this->resultExportUrl($result))
        );
    }

    public function test_cross_tenant_result_export_is_404(): void
    {
        $c = $this->curriculum();
        $other = $this->institute($this->country(), 'Other Result Inst');
        $otherOwner = $this->user($other, 'institute-owner', 'ex-r-other');
        $o = ['institute' => $other, 'class' => $c['class'], 'group' => $c['group'], 'math' => $c['math'], 'english' => $c['english']];

        $year = $this->year($other, '2026', '2026-01-01', '2026-12-31');
        $placement = $this->placementWithSubjects($o, $this->student($other, 'Alien'), $year, $c['class'], $c['group']);
        $result = $this->createResult($o, $year, $c['class'], $c['group'], 'Other Result');
        $this->addSnapshot($result, $placement, 4.75, $this->standardRows($c));

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->resultExportUrl($result))
            ->assertNotFound();

        $this->actingAs($otherOwner, 'institute_user')
            ->get($this->resultExportUrl($result))
            ->assertOk();
    }

    public function test_cross_branch_result_export_is_404(): void
    {
        $c = $this->curriculum();
        $branchA = $this->branch($c['institute'], 'Result Branch A');
        $branchB = $this->branch($c['institute'], 'Result Branch B');
        $managerA = $this->user($c['institute'], 'branch-manager', 'ex-r-bmgr', $branchA);

        $year = $this->year($c['institute'], '2026', '2026-01-01', '2026-12-31');
        $placement = $this->placementWithSubjects($c, $this->student($c['institute'], 'Rahim'), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Branch Result', $branchB);
        $this->addSnapshot($result, $placement, 4.75, $this->standardRows($c));

        $this->actingAs($managerA, 'institute_user')
            ->get($this->resultExportUrl($result))
            ->assertNotFound();
    }

    public function test_unpublished_result_export_is_404(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', '2026-01-01', '2026-12-31');
        $placement = $this->placementWithSubjects($c, $this->student($c['institute'], 'Rahim'), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Not Yet Published', status: AcademicFinalResult::STATUS_LOCKED);
        $this->addSnapshot($result, $placement, 4.75, $this->standardRows($c));

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->resultExportUrl($result))
            ->assertNotFound();
    }

    public function test_result_export_contains_the_frozen_snapshot_values(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', '2026-01-01', '2026-12-31');
        $placement = $this->placementWithSubjects($c, $this->student($c['institute'], 'Rahim'), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($result, $placement, 4.75, $this->standardRows($c));

        $rows = $this->csvRowsFor(
            $this->actingAs($c['owner'], 'institute_user')->get($this->resultExportUrl($result))->assertOk(),
        );

        $this->assertCount(2, $rows);

        $math = collect($rows)->first(fn ($row) => $row[6] === 'Mathematics');
        $this->assertNotNull($math);
        $this->assertSame('90.5%', $math[7]);
        $this->assertSame('A+', $math[8]);
        $this->assertSame('5.00', $math[9]);
        $this->assertSame('Pass', $math[11]);
        $this->assertSame('No', $math[12]);
        $this->assertSame('Yes', $math[13]);
        $this->assertSame('4.75', $math[14]);
        $this->assertSame('Pass', $math[15]);
        $this->assertSame('Session 2026', $math[5]);
    }

    public function test_live_marks_cannot_alter_the_exported_published_result(): void
    {
        // Two published results over the SAME student source data carry
        // different frozen snapshots. If the export recomputed from live marks
        // or current configuration it would output the same numbers for both;
        // because it reads only the selected result's snapshot tables, each
        // export is tied to its own frozen numbers.
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', '2026-01-01', '2026-12-31');
        $placement = $this->placementWithSubjects($c, $this->student($c['institute'], 'Rahim'), $year, $c['class'], $c['group']);

        $resultA = $this->createResult($c, $year, $c['class'], $c['group'], 'Term 1 2026');
        $this->addSnapshot($resultA, $placement, 3.0, [
            ['subject' => $c['math'], 'aggregate' => 70.25, 'grade' => 'B', 'grade_point' => 3.0, 'subject_status' => 'PASS', 'gpa_included' => true],
        ]);

        $resultB = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($resultB, $placement, 4.75, [
            ['subject' => $c['math'], 'aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS', 'gpa_included' => true],
        ]);

        $bodyA = $this->csvContent(
            $this->actingAs($c['owner'], 'institute_user')->get($this->resultExportUrl($resultA))->assertOk(),
        );
        $bodyB = $this->csvContent(
            $this->actingAs($c['owner'], 'institute_user')->get($this->resultExportUrl($resultB))->assertOk(),
        );

        $this->assertStringContainsString('3.00', $bodyA);
        $this->assertStringContainsString('70.25%', $bodyA);
        $this->assertStringNotContainsString('4.75', $bodyA);
        $this->assertStringNotContainsString('90.5%', $bodyA);

        $this->assertStringContainsString('4.75', $bodyB);
        $this->assertStringContainsString('90.5%', $bodyB);
        $this->assertStringNotContainsString('3.00', $bodyB);
        $this->assertStringNotContainsString('70.25%', $bodyB);
    }

    public function test_optional_and_gpa_included_markers_are_preserved(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', '2026-01-01', '2026-12-31');
        $placement = $this->placementWithSubjects($c, $this->student($c['institute'], 'Rahim'), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($result, $placement, 4.75, [
            ['subject' => $c['math'], 'aggregate' => 90.5, 'grade' => 'A+', 'grade_point' => 5.0, 'subject_status' => 'PASS', 'gpa_included' => true],
            ['subject' => $c['english'], 'aggregate' => 78.0, 'grade' => 'B+', 'grade_point' => 4.0, 'subject_status' => 'PASS', 'gpa_included' => false, 'optional' => true],
        ]);

        $rows = $this->csvRowsFor(
            $this->actingAs($c['owner'], 'institute_user')->get($this->resultExportUrl($result))->assertOk(),
        );

        $optional = collect($rows)->first(fn ($row) => $row[6] === 'English' && $row[12] === 'Yes');
        $this->assertNotNull($optional);
        $this->assertSame('No', $optional[13]);
        $this->assertSame('B+', $optional[8]);
    }

    public function test_gpa_comes_from_the_snapshot(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', '2026-01-01', '2026-12-31');
        $placement = $this->placementWithSubjects($c, $this->student($c['institute'], 'Rahim'), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($result, $placement, 4.75, $this->standardRows($c));

        $rows = $this->csvRowsFor(
            $this->actingAs($c['owner'], 'institute_user')->get($this->resultExportUrl($result))->assertOk(),
        );

        $this->assertSame('4.75', collect($rows)->first()[14]);
        $this->assertSame('Pass', collect($rows)->first()[15]);
    }

    public function test_gpa_unavailable_is_reflected_without_fabrication(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', '2026-01-01', '2026-12-31');
        $placement = $this->placementWithSubjects($c, $this->student($c['institute'], 'Rahim'), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        AcademicFinalResultStudent::create([
            'result_id' => $result->id,
            'placement_id' => $placement->id,
            'gpa' => null,
            'gpa_status' => AcademicFinalResultStudent::GPA_UNAVAILABLE,
            'passed_count' => 0,
            'failed_count' => 0,
        ]);
        AcademicFinalResultRow::create([
            'result_id' => $result->id,
            'placement_id' => $placement->id,
            'subject_id' => $c['math']->id,
            'status' => 'incomplete',
            'aggregate' => null,
            'grade' => null,
            'grade_point' => null,
            'subject_status' => null,
            'gpa_included' => true,
        ]);

        $body = $this->csvContent(
            $this->actingAs($c['owner'], 'institute_user')->get($this->resultExportUrl($result))->assertOk(),
        );

        $this->assertStringContainsString('Unavailable', $body);
        $this->assertStringContainsString('—', $body);
    }

    public function test_result_export_performs_no_writes_and_promotions_are_unchanged(): void
    {
        $c = $this->curriculum();
        $year = $this->year($c['institute'], '2026', '2026-01-01', '2026-12-31');
        $placement = $this->placementWithSubjects($c, $this->student($c['institute'], 'Rahim'), $year, $c['class'], $c['group']);
        $result = $this->createResult($c, $year, $c['class'], $c['group'], 'Term Final 2026');
        $this->addSnapshot($result, $placement, 4.75, $this->standardRows($c));

        $before = [
            'students' => AcademicFinalResultStudent::query()->count(),
            'rows' => AcademicFinalResultRow::query()->count(),
            'placements' => StudentAcademicPlacement::query()->count(),
            'promotions' => PromotionDecision::query()->count(),
            'promotionItems' => PromotionDecisionItem::query()->count(),
        ];

        $this->actingAs($c['owner'], 'institute_user')
            ->get($this->resultExportUrl($result))
            ->assertOk();

        $this->assertSame($before['students'], AcademicFinalResultStudent::query()->count());
        $this->assertSame($before['rows'], AcademicFinalResultRow::query()->count());
        $this->assertSame($before['placements'], StudentAcademicPlacement::query()->count());
        $this->assertSame($before['promotions'], PromotionDecision::query()->count());
        $this->assertSame($before['promotionItems'], PromotionDecisionItem::query()->count());
    }
}
