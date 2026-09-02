<?php

namespace Tests\Feature;

use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultPolicy;
use App\Models\AcademicFinalResultStudent;
use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Certificate;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\Course;
use App\Models\EducationSystem;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PromotionDecision;
use App\Models\PromotionDecisionItem;
use App\Models\PromotionPolicy;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Services\AcademicDashboardService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Step 24 — Academic Operations Dashboard (AcademicDashboardController +
 * AcademicDashboardService).
 *
 * Covers: the read-only overview for the CURRENT academic year, accuracy of
 * every aggregated metric (students, results, promotion, attendance,
 * certificates), the "eligible for certificate" figure derived from approved
 * promotion outcomes, attendance semantics matching the report service
 * (unrecorded days never count as absent), graceful handling of an institute
 * with no current academic year, tenant + branch isolation, and a read-only
 * guarantee (viewing the dashboard never writes).
 */
class AcademicOperationsDashboardTest extends TestCase
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

    private function group(ClassGrade $classGrade): AcademicGroup
    {
        return AcademicGroup::create([
            'country_id' => $classGrade->country_id,
            'education_system_id' => $classGrade->education_system_id,
            'academic_level_id' => $classGrade->academic_level_id,
            'class_grade_id' => $classGrade->id,
            'name' => 'General',
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

    private function year(Institute $institute, string $code, string $start, string $end, bool $isCurrent): AcademicYear
    {
        return AcademicYear::create([
            'institute_id' => $institute->id,
            'name' => 'Session '.$code,
            'code' => $code,
            'start_date' => $start,
            'end_date' => $end,
            'is_current' => $isCurrent,
            'status' => true,
        ]);
    }

    private function batch(Institute $institute): Batch
    {
        return Batch::create([
            'institute_id' => $institute->id,
            'branch_id' => null,
            'course_id' => Course::create([
                'course_code' => 'C'.mt_rand(1000, 9999),
                'name' => 'Dashboard Course',
            ])->id,
            'name' => 'Batch D',
            'batch_code' => 'BD-'.mt_rand(10, 99),
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'ongoing',
        ]);
    }

    private function placement(
        Institute $institute,
        Student $student,
        AcademicYear $academicYear,
        ClassGrade $class,
        string $status
    ): StudentAcademicPlacement {
        return StudentAcademicPlacement::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'academic_year_id' => $academicYear->id,
            'class_grade_id' => $class->id,
            'status' => $status,
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

    private function scheme(Institute $institute, AcademicYear $year, ClassGrade $class): AcademicResultAggregationScheme
    {
        return AcademicResultAggregationScheme::create([
            'institute_id' => $institute->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $class->id,
            'name' => 'Aggregation Scheme',
            'status' => 'active',
        ]);
    }

    private function policy(Institute $institute, AcademicResultAggregationScheme $scheme): AcademicFinalResultPolicy
    {
        return AcademicFinalResultPolicy::create([
            'institute_id' => $institute->id,
            'scheme_id' => $scheme->id,
            'name' => 'Final Result Policy',
            'absent_renormalization' => true,
            'require_approval' => true,
            'status' => 'active',
        ]);
    }

    private function finalResult(Institute $institute, AcademicFinalResultPolicy $policy, AcademicResultAggregationScheme $scheme, string $status): AcademicFinalResult
    {
        return AcademicFinalResult::create([
            'institute_id' => $institute->id,
            'policy_id' => $policy->id,
            'scheme_id' => $scheme->id,
            'name' => 'Final Result ('.$status.')',
            'status' => $status,
        ]);
    }

    private function resultStudent(AcademicFinalResult $result, StudentAcademicPlacement $placement, int $passed, int $failed): AcademicFinalResultStudent
    {
        return AcademicFinalResultStudent::create([
            'result_id' => $result->id,
            'placement_id' => $placement->id,
            'gpa' => 3.5,
            'gpa_status' => 'computed',
            'passed_count' => $passed,
            'failed_count' => $failed,
        ]);
    }

    private function promotionPolicy(Institute $institute, AcademicYear $year, ClassGrade $class): PromotionPolicy
    {
        return PromotionPolicy::create([
            'institute_id' => $institute->id,
            'name' => 'Promotion Policy',
            'academic_year_id' => $year->id,
            'class_grade_id' => $class->id,
            'status' => 'active',
        ]);
    }

    private function promotionDecision(
        Institute $institute,
        PromotionPolicy $policy,
        AcademicFinalResult $result,
        AcademicYear $year,
        string $status
    ): PromotionDecision {
        return PromotionDecision::create([
            'policy_id' => $policy->id,
            'result_id' => $result->id,
            'institute_id' => $institute->id,
            'academic_year_id' => $year->id,
            'status' => $status,
        ]);
    }

    private function decisionItem(
        PromotionDecision $decision,
        Student $student,
        StudentAcademicPlacement $placement,
        string $outcome
    ): PromotionDecisionItem {
        return PromotionDecisionItem::create([
            'decision_id' => $decision->id,
            'placement_id' => $placement->id,
            'student_id' => $student->id,
            'decision' => $outcome,
            'approved_at' => now(),
        ]);
    }

    private function certificate(Institute $institute, Student $student, Batch $batch, string $status): Certificate
    {
        return Certificate::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'course_id' => $batch->course_id,
            'batch_id' => $batch->id,
            'status' => $status,
            'certificate_number' => 'MNT-2026-'.mt_rand(10000, 99999),
            'issue_date' => now()->toDateString(),
            'verification_url' => 'https://example.test/verify/certificate/mnt-2026-test',
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

    private function summary(Institute $institute, ?Branch $branch = null): array
    {
        $this->withContext($institute, $branch);

        return app(AcademicDashboardService::class)->summary();
    }

    /**
     * Full dashboard scenario: 6 students in the current (2026) year,
     * one published final result, an approved + a pending promotion decision,
     * attendance rows and certificate records across statuses.
     *
     * @return array<string, mixed>
     */
    private function seededContext(): array
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $institute = $this->institute($c, 'Dashboard Institute');
        $owner = $this->user($institute, 'institute-owner', 'ad-owner');
        $year = $this->year($institute, '2026', '2026-01-01', '2026-12-31', true);
        $batch = $this->batch($institute);

        $activeA = $this->student($institute, 'Alpha');
        $activeB = $this->student($institute, 'Beta');
        $completed = $this->student($institute, 'Chad');
        $graduated = $this->student($institute, 'Delta');
        $withdrawn = $this->student($institute, 'Epsilon');
        $transferred = $this->student($institute, 'Foxtrot');

        $this->placement($institute, $activeA, $year, $class8, StudentAcademicPlacement::STATUS_ACTIVE);
        $this->placement($institute, $activeB, $year, $class8, StudentAcademicPlacement::STATUS_ACTIVE);
        $placeCompleted = $this->placement($institute, $completed, $year, $class8, StudentAcademicPlacement::STATUS_ACTIVE);
        $placeGraduated = $this->placement($institute, $graduated, $year, $class8, StudentAcademicPlacement::STATUS_ACTIVE);
        $this->placement($institute, $withdrawn, $year, $class8, StudentAcademicPlacement::STATUS_DROPPED);
        $this->placement($institute, $transferred, $year, $class8, StudentAcademicPlacement::STATUS_TRANSFERRED);

        $scheme = $this->scheme($institute, $year, $class8);
        $policy = $this->policy($institute, $scheme);
        $promoPolicy = $this->promotionPolicy($institute, $year, $class8);
        $published = $this->finalResult($institute, $policy, $scheme, AcademicFinalResult::STATUS_PUBLISHED);
        $this->finalResult($institute, $policy, $scheme, AcademicFinalResult::STATUS_REVIEW);
        $this->resultStudent($published, $placeCompleted, 5, 1);
        $this->resultStudent($published, $placeGraduated, 6, 0);

        $approvedDecision = $this->promotionDecision($institute, $promoPolicy, $published, $year, PromotionDecision::STATUS_APPROVED);
        $this->decisionItem($approvedDecision, $completed, $placeCompleted, PromotionDecisionItem::DECISION_COMPLETED);
        $this->decisionItem($approvedDecision, $graduated, $placeGraduated, PromotionDecisionItem::DECISION_GRADUATED);
        $this->promotionDecision($institute, $promoPolicy, $published, $year, PromotionDecision::STATUS_PENDING);

        foreach (['2026-06-01', '2026-06-02', '2026-06-03'] as $date) {
            $this->attendanceRow($institute, $activeA, $batch, $date, 'present');
        }
        $this->attendanceRow($institute, $activeA, $batch, '2026-06-04', 'absent');
        $this->attendanceRow($institute, $activeB, $batch, '2026-06-01', 'present');
        $this->attendanceRow($institute, $activeB, $batch, '2026-06-02', 'present');
        $this->attendanceRow($institute, $activeB, $batch, '2026-06-05', 'late');
        $this->attendanceRow($institute, $withdrawn, $batch, '2026-06-01', 'present');
        // Outside the current-year window — must never leak into the totals.
        $this->attendanceRow($institute, $activeA, $batch, '2025-05-01', 'present');

        $this->certificate($institute, $activeA, $batch, 'active');
        $this->certificate($institute, $activeB, $batch, 'revoked');
        $this->certificate($institute, $completed, $batch, 'pending');

        return compact(
            'c', 'system', 'level', 'class8', 'institute', 'owner', 'year',
            'batch', 'activeA', 'activeB', 'completed', 'graduated',
            'withdrawn', 'transferred', 'scheme', 'policy', 'published',
        );
    }

    // ------------------------------------------------------------ Access

    public function test_dashboard_requires_authentication(): void
    {
        $this->get(route('academic.dashboard'))
            ->assertRedirect('/admin/login');
    }

    public function test_dashboard_page_renders_all_sections(): void
    {
        $ctx = $this->seededContext();

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get(route('academic.dashboard'))
            ->assertOk();

        $this->bodyHas($response, 'Academic Operations');
        $this->bodyHas($response, 'Current academic year');
        $this->bodyHas($response, 'Session 2026');
        $this->bodyHas($response, 'Active academic students');
        $this->bodyHas($response, 'Final Results');
        $this->bodyHas($response, 'Promotion');
        $this->bodyHas($response, 'Attendance');
        $this->bodyHas($response, 'Certificates');
    }

    // ------------------------------------------------------------ Metrics

    public function test_dashboard_metrics_aggregate_existing_data_accurately(): void
    {
        $ctx = $this->seededContext();
        $summary = $this->summary($ctx['institute']);

        $this->assertSame($ctx['year']->id, $summary['year']->id);

        $this->assertSame(6, $summary['students']['cohort'], 'distinct current-year placed students');
        $this->assertSame(4, $summary['students']['active'], 'placements with status active (incl. completed/graduated)');
        $this->assertSame(1, $summary['students']['completed'], 'approved completed decision items');
        $this->assertSame(1, $summary['students']['graduated'], 'approved graduated decision items');
        $this->assertSame(1, $summary['students']['withdrawn'], 'placements closed as dropped');
        $this->assertSame(1, $summary['students']['transferred'], 'placements closed as transferred');

        $this->assertSame(1, $summary['results']['published_results'], 'only PUBLISHED results of the current year count');
        $this->assertSame(11, $summary['results']['passed_students'], 'sum of frozen passed_count snapshots');
        $this->assertSame(1, $summary['results']['failed_students'], 'sum of frozen failed_count snapshots');

        $this->assertSame(1, $summary['promotion']['pending']);
        $this->assertSame(0, $summary['promotion']['review']);
        $this->assertSame(1, $summary['promotion']['approved']);

        $this->assertSame(2, $summary['certificates']['eligible'], 'students with approved completed/graduated outcomes');
        $this->assertSame(1, $summary['certificates']['issued'], 'status active');
        $this->assertSame(1, $summary['certificates']['revoked'], 'status revoked');
        $this->assertSame(1, $summary['certificates']['pending'], 'status pending');
    }

    public function test_dashboard_attendance_follows_report_semantics(): void
    {
        $ctx = $this->seededContext();
        $summary = $this->summary($ctx['institute']);
        $attendance = $summary['attendance'];

        $this->assertTrue($attendance['available']);
        $this->assertSame(8, $attendance['total'], 'present 6 + absent 1 + late 1');
        $this->assertSame(6, $attendance['present']);
        $this->assertSame(1, $attendance['absent']);
        $this->assertSame(1, $attendance['late']);
        $this->assertSame(0, $attendance['leave']);
        $this->assertSame(75.0, $attendance['present_percent']);
    }

    public function test_dashboard_renders_attendance_totals_on_page(): void
    {
        $ctx = $this->seededContext();

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->get(route('academic.dashboard'))
            ->assertOk();

        $this->bodyHas($response, '75.0%');
        $this->bodyHas($response, '6');
        $this->bodyHas($response, '1');
    }

    // -------------------------------------------------------- Graceful cases

    public function test_dashboard_without_current_year_is_graceful(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $institute = $this->institute($c, 'No Current Year Institute');
        $owner = $this->user($institute, 'institute-owner', 'ad-ncy');
        $pastYear = $this->year($institute, '2025', '2025-01-01', '2025-12-31', false);
        $batch = $this->batch($institute);
        $student = $this->student($institute, 'Old');
        $this->placement($institute, $student, $pastYear, $class8, StudentAcademicPlacement::STATUS_ACTIVE);
        $this->attendanceRow($institute, $student, $batch, '2025-06-01', 'present');

        $summary = $this->summary($institute);
        $this->assertNull($summary['year']);
        $this->assertSame(0, $summary['students']['cohort']);
        $this->assertSame(0, $summary['results']['published_results']);
        $this->assertSame(0, $summary['certificates']['eligible']);
        $this->assertFalse($summary['attendance']['available']);
        $this->assertNull($summary['attendance']['present_percent']);

        $response = $this->actingAs($owner, 'institute_user')
            ->get(route('academic.dashboard'))
            ->assertOk();

        $this->bodyHas($response, 'No current academic year is configured for this institute.');
        $this->bodyHas($response, 'Attendance data unavailable');
    }

    public function test_dashboard_year_without_dates_reports_attendance_unavailable(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $institute = $this->institute($c, 'No Dates Institute');
        $owner = $this->user($institute, 'institute-owner', 'ad-nd');
        $this->year($institute, '2026', '2026-01-01', '2026-12-31', true)
            ->update(['start_date' => null, 'end_date' => null]);

        $attendance = $this->summary($institute)['attendance'];

        $this->assertFalse($attendance['available']);
        $this->assertStringContainsString('no reliable start/end dates', (string) $attendance['message']);
        $this->assertNull($attendance['present_percent']);

        $response = $this->actingAs($owner, 'institute_user')
            ->get(route('academic.dashboard'))
            ->assertOk();

        $this->bodyHas($response, 'no reliable start/end dates');
    }

    // ---------------------------------------------------------- Isolation

    public function test_dashboard_branch_manager_sees_only_own_branch(): void
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $institute = $this->institute($c, 'Branch Dashboard Institute');
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $owner = $this->user($institute, 'institute-owner', 'ad-bowner');
        $managerA = $this->user($institute, 'branch-manager', 'ad-bmgr', $branchA);
        $year = $this->year($institute, '2026', '2026-01-01', '2026-12-31', true);
        $batch = $this->batch($institute);

        $inA = $this->student($institute, 'LokalA', $branchA);
        $inB = $this->student($institute, 'RemoteB', $branchB);
        $this->placement($institute, $inA, $year, $class8, StudentAcademicPlacement::STATUS_ACTIVE);
        $this->placement($institute, $inB, $year, $class8, StudentAcademicPlacement::STATUS_ACTIVE);
        $this->attendanceRow($institute, $inA, $batch, '2026-06-01', 'present');
        $this->attendanceRow($institute, $inA, $batch, '2026-06-02', 'present');
        $this->attendanceRow($institute, $inB, $batch, '2026-06-01', 'present');
        $this->attendanceRow($institute, $inB, $batch, '2026-06-02', 'present');

        $managerSummary = $this->summary($institute, $branchA);
        $this->assertSame(1, $managerSummary['students']['cohort']);
        $this->assertSame(1, $managerSummary['students']['active']);
        $this->assertSame(2, $managerSummary['attendance']['present']);

        $ownerSummary = $this->summary($institute);
        $this->assertSame(2, $ownerSummary['students']['cohort']);
        $this->assertSame(2, $ownerSummary['students']['active']);
        $this->assertSame(4, $ownerSummary['attendance']['present']);
    }

    // ---------------------------------------------------------- Read-only

    public function test_dashboard_is_read_only(): void
    {
        $ctx = $this->seededContext();

        $before = [
            'placements' => StudentAcademicPlacement::query()->where('institute_id', $ctx['institute']->id)->count(),
            'students' => Student::query()->where('institute_id', $ctx['institute']->id)->count(),
            'attendance' => Attendance::query()->where('institute_id', $ctx['institute']->id)->count(),
            'certificates' => Certificate::query()->where('institute_id', $ctx['institute']->id)->count(),
            'results' => AcademicFinalResult::query()->where('institute_id', $ctx['institute']->id)->count(),
            'decisions' => PromotionDecision::query()->where('institute_id', $ctx['institute']->id)->count(),
            'items' => PromotionDecisionItem::query()->count(),
        ];

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get(route('academic.dashboard'))
            ->assertOk();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get(route('academic.dashboard'))
            ->assertOk();

        $this->assertSame($before['placements'], StudentAcademicPlacement::query()->where('institute_id', $ctx['institute']->id)->count());
        $this->assertSame($before['students'], Student::query()->where('institute_id', $ctx['institute']->id)->count());
        $this->assertSame($before['attendance'], Attendance::query()->where('institute_id', $ctx['institute']->id)->count());
        $this->assertSame($before['certificates'], Certificate::query()->where('institute_id', $ctx['institute']->id)->count());
        $this->assertSame($before['results'], AcademicFinalResult::query()->where('institute_id', $ctx['institute']->id)->count());
        $this->assertSame($before['decisions'], PromotionDecision::query()->where('institute_id', $ctx['institute']->id)->count());
        $this->assertSame($before['items'], PromotionDecisionItem::query()->count());
    }
}
