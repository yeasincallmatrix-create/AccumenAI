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
use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\CrmLeadStatus;
use App\Models\CrmOrganization;
use App\Models\EducationSystem;
use App\Models\Institute;
use App\Models\InstituteCourse;
use App\Models\InstituteUser;
use App\Models\Permission;
use App\Models\PromotionDecision;
use App\Models\PromotionDecisionItem;
use App\Models\PromotionPolicy;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\StudentEnrollment;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Education\FeeHeadService;
use App\Services\Education\FeeStructureService;
use App\Services\Education\StudentFinanceService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Step 44 — Education Analytics & Reports.
 *
 * Covers: the read-only analytics layer end-to-end. Every report is rendered
 * and asserted against the exact aggregates of the seeded world (students,
 * courses, batches, attendance, results, promotions, completion, certificates,
 * finance and CRM). Tenant + branch isolation, the finance / CRM permission
 * gates, "published snapshots only" results semantics, "unrecorded days are
 * never absent" attendance semantics, the streamed CSV exports and a read-only
 * guarantee.
 */
class AcademicAnalyticsTest extends TestCase
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

    /**
     * A role carrying exactly the given permission slugs (used to prove the
     * finance / CRM gates and the branch isolation of the analytics area).
     */
    private function customRole(array $permissionSlugs, string $prefix): Role
    {
        $role = Role::create([
            'institute_id' => null,
            'name' => 'Analytics '.$prefix,
            'slug' => 'analytics-'.$prefix.'-'.mt_rand(10000, 99999),
            'is_system' => false,
            'status' => 'active',
        ]);

        $permissionIds = Permission::query()->whereIn('slug', $permissionSlugs)->pluck('id');

        DB::table('role_permissions')->insert(
            $permissionIds->map(fn (int $id) => ['role_id' => $role->id, 'permission_id' => $id])->all()
        );

        return $role;
    }

    private function analyticsUser(Institute $institute, array $permissionSlugs, string $prefix, ?Branch $branch = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'role_id' => $this->customRole($permissionSlugs, $prefix)->id,
            'first_name' => 'Analytics',
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01701'.mt_rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function course(Institute $institute, string $name, int $fee = 5000): Course
    {
        $course = Course::create([
            'course_code' => 'C'.strtoupper(str()->random(8)),
            'name' => $name,
            'fee' => $fee,
        ]);

        InstituteCourse::create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
        ]);

        return $course;
    }

    private function batch(Institute $institute, Branch $branch, Course $course, string $name): Batch
    {
        return Batch::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'name' => $name,
            'batch_code' => 'B'.strtoupper(str()->random(8)),
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'ongoing',
        ]);
    }

    private function student(Institute $institute, string $name, ?Branch $branch = null, ?CrmLead $lead = null, string $status = 'active'): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'student_id_number' => 'RP'.strtoupper(str()->random(8)),
            'first_name' => $name,
            'last_name' => 'Student',
            'status' => $status,
            'admission_date' => '2026-01-01',
            'crm_lead_id' => $lead?->id,
        ]);
    }

    private function enroll(Student $student, Batch $batch): StudentEnrollment
    {
        return StudentEnrollment::create([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'course_id' => $batch->course_id,
            'enrollment_date' => '2026-01-05',
            'roll_number' => 'R'.strtoupper(str()->random(8)),
            'status' => 'active',
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
            'certificate_number' => 'MNT-2026-'.strtoupper(str()->random(6)),
            'issue_date' => now()->toDateString(),
            'verification_url' => 'https://example.test/verify/certificate/mnt-2026-test',
        ]);
    }

    private function crmContact(Institute $institute, string $first, ?Branch $branch = null): CrmContact
    {
        return CrmContact::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'first_name' => $first,
            'last_name' => 'Contact',
            'status' => 'active',
        ]);
    }

    private function crmOrg(Institute $institute, string $name): CrmOrganization
    {
        return CrmOrganization::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function crmLead(Institute $institute, string $first, string $statusSlug, ?Branch $branch = null): CrmLead
    {
        return CrmLead::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'status_id' => CrmLeadStatus::query()->where('slug', $statusSlug)->firstOrFail()->id,
            'first_name' => $first,
            'last_name' => 'Lead',
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

    private function bodyNotHas(TestResponse $response, string $needle): void
    {
        $this->assertStringNotContainsString($needle, $response->getContent());
    }

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

    /**
     * The complete analytics world (whole-institute owner view):
     *
     * 2026 current year, 2025 past year; courses A/B with one batch each;
     * six branch-A students + one branch-B student; one published + one
     * review final result; approved + pending promotions; attendance across
     * statuses (plus an out-of-window row); certificates across statuses;
     * CRM contacts / organizations / leads (one converted, one branch-B);
     * and a minimal accounting world (one invoice + one payment) for finance.
     *
     * @return array<string, mixed>
     */
    private function analyticsWorld(): array
    {
        $c = $this->country();
        $system = $this->system($c);
        $level = $this->level($system);
        $class8 = $this->classGrade($level, 'c8', 'Class 8');
        $class9 = $this->classGrade($level, 'c9', 'Class 9');

        $institute = $this->institute($c, 'Analytics Institute');
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');

        $owner = $this->user($institute, 'institute-owner', 'an-owner');
        $teacher = $this->user($institute, 'teacher', 'an-teacher', $branchA);
        $branchAOnly = $this->analyticsUser($institute, ['education.manage', 'finance.view', 'crm.view'], 'branched', $branchA);
        $noFinance = $this->analyticsUser($institute, ['education.manage'], 'nofin');

        $y2025 = $this->year($institute, '2025', '2025-01-01', '2025-12-31', false);
        $y2026 = $this->year($institute, '2026', '2026-01-01', '2026-12-31', true);

        $courseA = $this->course($institute, 'Course A');
        $courseB = $this->course($institute, 'Course B', 3000);
        $batchA = $this->batch($institute, $branchA, $courseA, 'Batch A1');
        $batchB = $this->batch($institute, $branchB, $courseB, 'Batch B1');

        // Leads (created before students so s1 can be marked converted).
        $leadNew = $this->crmLead($institute, 'Newbie', CrmLeadStatus::SLUG_NEW);
        $leadWonConverted = $this->crmLead($institute, 'Converted', CrmLeadStatus::SLUG_WON);
        $leadWon = $this->crmLead($institute, 'Wonny', CrmLeadStatus::SLUG_WON);
        $leadLost = $this->crmLead($institute, 'Loser', CrmLeadStatus::SLUG_LOST, $branchB);

        $contactA = $this->crmContact($institute, 'ContactA');
        $contactB = $this->crmContact($institute, 'ContactB', $branchB);
        $org = $this->crmOrg($institute, 'Org One');

        // Branch A cohort (enrolled in batchA / courseA).
        $s1 = $this->student($institute, 'Alpha', $branchA, $leadWonConverted);
        $s2 = $this->student($institute, 'Beta', $branchA);
        $s3 = $this->student($institute, 'Chad', $branchA, null, 'dropped');
        $s4 = $this->student($institute, 'Delta', $branchA);
        $s6 = $this->student($institute, 'Foxtrot', $branchA);
        // Branch B student (enrolled in batchB / courseB) — foreign for branch A.
        $s5 = $this->student($institute, 'Echo', $branchB);

        $enrollS1 = $this->enroll($s1, $batchA);
        $this->enroll($s2, $batchA);
        $this->enroll($s3, $batchA);
        $this->enroll($s4, $batchA);
        $this->enroll($s6, $batchA);
        $this->enroll($s5, $batchB);

        // Placements.
        $p1 = $this->placement($institute, $s1, $y2026, $class8, StudentAcademicPlacement::STATUS_ACTIVE);
        $p2 = $this->placement($institute, $s2, $y2026, $class8, StudentAcademicPlacement::STATUS_ACTIVE);
        $p3 = $this->placement($institute, $s3, $y2026, $class8, StudentAcademicPlacement::STATUS_DROPPED);
        $p4 = $this->placement($institute, $s4, $y2026, $class8, StudentAcademicPlacement::STATUS_ACTIVE);
        $p6 = $this->placement($institute, $s6, $y2026, $class8, StudentAcademicPlacement::STATUS_ACTIVE);
        $p5 = $this->placement($institute, $s5, $y2026, $class9, StudentAcademicPlacement::STATUS_ACTIVE);
        // Past-year placements.
        $p1old = $this->placement($institute, $s1, $y2025, $class8, StudentAcademicPlacement::STATUS_ACTIVE);
        $p6old = $this->placement($institute, $s6, $y2025, $class8, StudentAcademicPlacement::STATUS_ACTIVE);

        // Final results.
        $scheme = $this->scheme($institute, $y2026, $class8);
        $scheme9 = $this->scheme($institute, $y2026, $class9);
        $policy = $this->policy($institute, $scheme);
        $policy9 = $this->policy($institute, $scheme9);

        $published = $this->finalResult($institute, $policy, $scheme, AcademicFinalResult::STATUS_PUBLISHED);
        $review = $this->finalResult($institute, $policy, $scheme, AcademicFinalResult::STATUS_REVIEW);

        $this->resultStudent($published, $p1, 5, 1);
        $this->resultStudent($published, $p2, 6, 0);
        $this->resultStudent($published, $p4, 2, 3);
        // A review-status snapshot must never leak into the analytics.
        $this->resultStudent($review, $p6, 1, 1);

        // Promotions.
        $promoPolicy = $this->promotionPolicy($institute, $y2026, $class8);
        $approved = $this->promotionDecision($institute, $promoPolicy, $published, $y2026, PromotionDecision::STATUS_APPROVED);
        $this->decisionItem($approved, $s1, $p1, PromotionDecisionItem::DECISION_COMPLETED);
        $this->decisionItem($approved, $s4, $p4, PromotionDecisionItem::DECISION_GRADUATED);
        $this->promotionDecision($institute, $promoPolicy, $published, $y2026, PromotionDecision::STATUS_PENDING);

        $promoPolicyOld = $this->promotionPolicy($institute, $y2025, $class8);
        $approvedOld = $this->promotionDecision($institute, $promoPolicyOld, $published, $y2025, PromotionDecision::STATUS_APPROVED);
        $this->decisionItem($approvedOld, $s6, $p6old, PromotionDecisionItem::DECISION_PROMOTED);

        // Attendance (2026 window) — unrecorded days are never absent.
        foreach (['2026-06-01', '2026-06-02', '2026-06-03'] as $date) {
            $this->attendanceRow($institute, $s1, $batchA, $date, 'present');
        }
        $this->attendanceRow($institute, $s1, $batchA, '2026-06-04', 'absent');
        $this->attendanceRow($institute, $s2, $batchA, '2026-06-01', 'present');
        $this->attendanceRow($institute, $s2, $batchA, '2026-06-02', 'present');
        $this->attendanceRow($institute, $s2, $batchA, '2026-06-05', 'late');
        $this->attendanceRow($institute, $s5, $batchB, '2026-06-01', 'present');
        // Outside the 2026 window — must never leak into the totals.
        $this->attendanceRow($institute, $s1, $batchA, '2025-05-01', 'present');

        // Certificates.
        $this->certificate($institute, $s1, $batchA, 'active');
        $this->certificate($institute, $s2, $batchA, 'pending');
        $this->certificate($institute, $s4, $batchA, 'revoked');
        $this->certificate($institute, $s5, $batchB, 'rejected');

        // Finance: one invoice + one partial payment for the branch-A batch.
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branchA->id);
        app(AccountingSetupService::class)->setSetting($institute->id, 'invoice_auto_post', true, $branchA->id);
        $head = app(FeeHeadService::class)->create($institute->id, $branchA->id, [
            'type' => 'course_tuition',
            'name' => 'Tuition Fee',
        ], null);
        $structure = app(FeeStructureService::class)->create($institute->id, $branchA->id, [
            'name' => 'S1',
            'academic_year_id' => null,
            'course_id' => $courseA->id,
            'batch_id' => null,
            'installments_count' => 1,
            'installments_interval_days' => 30,
            'status' => 'active',
            'items' => [['fee_head_id' => $head->id, 'amount' => 5000]],
        ], null);
        $invoice = app(StudentFinanceService::class)->generateInvoice(
            $enrollS1,
            $structure,
            ['due_date' => now()->addDays(30)->toDateString()],
        );
        $payment = app(StudentFinanceService::class)->recordPayment($institute->id, $branchA->id, [
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'payment_method' => 'cash',
        ]);

        return compact(
            'c', 'system', 'level', 'class8', 'class9', 'institute', 'branchA', 'branchB',
            'owner', 'teacher', 'branchAOnly', 'noFinance', 'y2025', 'y2026',
            'courseA', 'courseB', 'batchA', 'batchB', 's1', 's2', 's3', 's4', 's5', 's6',
            'p1', 'p2', 'p3', 'p4', 'p5', 'p6', 'published', 'review', 'invoice', 'payment',
        );
    }

    // ------------------------------------------------------------- Access

    public function test_analytics_requires_authentication(): void
    {
        $this->get(route('academic.analytics.index'))->assertRedirect('/login');
        $this->get(route('academic.analytics.students'))->assertRedirect('/login');
        $this->get(route('academic.analytics.students.export'))->assertRedirect('/login');
        $this->get(route('academic.analytics.finance'))->assertRedirect('/login');
        $this->get(route('academic.analytics.crm'))->assertRedirect('/login');
    }

    public function test_analytics_requires_education_manage_permission(): void
    {
        $w = $this->analyticsWorld();

        $this->actingAs($w['teacher'], 'institute_user');

        foreach ([
            'academic.analytics.index',
            'academic.analytics.students',
            'academic.analytics.students.export',
            'academic.analytics.courses',
            'academic.analytics.batches',
            'academic.analytics.attendance',
            'academic.analytics.results',
            'academic.analytics.promotions',
            'academic.analytics.completion',
            'academic.analytics.certificates',
            'academic.analytics.finance',
            'academic.analytics.crm',
        ] as $route) {
            $this->get(route($route))->assertForbidden();
        }
    }

    public function test_index_renders_all_sections_for_owner(): void
    {
        $w = $this->analyticsWorld();

        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.index'))
            ->assertOk();

        $this->bodyHas($response, 'Education Analytics');
        $this->bodyHas($response, 'Current year');
        $this->bodyHas($response, 'Session 2026');
        $this->bodyHas($response, 'Active Students');
        $this->bodyHas($response, 'Published Results');
        $this->bodyHas($response, 'Certificate Eligible');
        $this->bodyHas($response, 'Attendance %');
        $this->bodyHas($response, '75.0%');
        $this->bodyHas($response, 'Finance Summary');
        $this->bodyHas($response, 'CRM');
        $this->bodyHas($response, 'Student Analytics');
        $this->bodyHas($response, 'Course Analytics');
        $this->bodyHas($response, 'Certificate Analytics');
    }

    public function test_index_hides_finance_and_crm_without_gating_permissions(): void
    {
        $w = $this->analyticsWorld();

        $response = $this->actingAs($w['noFinance'], 'institute_user')
            ->get(route('academic.analytics.index'))
            ->assertOk();

        $this->bodyHas($response, 'Education Analytics');
        $this->bodyNotHas($response, 'Finance Summary');
        $this->bodyNotHas($response, 'Receivable');
        $this->bodyNotHas($response, 'Leads');
        $this->bodyNotHas($response, 'Converted');
    }

    // ---------------------------------------------------------- Students

    public function test_students_report_lists_decorated_rows(): void
    {
        $w = $this->analyticsWorld();

        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.students'))
            ->assertOk();

        $this->bodyHas($response, '6 matched');

        foreach (['Alpha', 'Beta', 'Chad', 'Delta', 'Echo', 'Foxtrot'] as $name) {
            $this->bodyHas($response, $name);
        }

        // Decorated columns: promotion outcome, frozen pass/fail, certificate.
        $this->bodyHas($response, 'Completed');
        $this->bodyHas($response, 'Graduated');
        $this->bodyHas($response, 'Issued');
        $this->bodyHas($response, 'Pending');
        $this->bodyHas($response, '75.0%');
        $this->bodyHas($response, '66.7%');
    }

    public function test_students_filters_narrow_the_report(): void
    {
        $w = $this->analyticsWorld();

        // Term filter by name.
        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.students', ['term' => 'Alpha']))
            ->assertOk();
        $this->bodyHas($response, '1 matched');
        $this->bodyHas($response, 'Alpha');
        $this->bodyNotHas($response, 'Beta');

        // Status filter.
        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.students', ['status' => 'dropped']))
            ->assertOk();
        $this->bodyHas($response, '1 matched');
        $this->bodyHas($response, 'Chad');

        // Branch filter.
        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.students', ['branch_id' => $w['branchB']->id]))
            ->assertOk();
        $this->bodyHas($response, '1 matched');
        $this->bodyHas($response, 'Echo');
        $this->bodyNotHas($response, 'Alpha');

        // Course filter.
        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.students', ['course_id' => $w['courseA']->id]))
            ->assertOk();
        $this->bodyHas($response, '5 matched');

        // Batch filter.
        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.students', ['batch_id' => $w['batchB']->id]))
            ->assertOk();
        $this->bodyHas($response, '1 matched');
        $this->bodyHas($response, 'Echo');

        // Class filter within the current year.
        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.students', [
                'academic_year_id' => $w['y2026']->id,
                'class_grade_id' => $w['class9']->id,
            ]))
            ->assertOk();
        $this->bodyHas($response, '1 matched');
        $this->bodyHas($response, 'Echo');

        // Admission window.
        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.students', [
                'admission_from' => '2026-02-01',
                'admission_to' => '2026-12-31',
            ]))
            ->assertOk();
        $this->bodyHas($response, '0 matched');
    }

    public function test_students_export_streams_the_filtered_dataset(): void
    {
        $w = $this->analyticsWorld();

        $all = $this->parseCsv($this->csvContent(
            $this->actingAs($w['owner'], 'institute_user')
                ->get(route('academic.analytics.students.export'))
                ->assertOk()
        ));

        $this->assertCount(7, $all, 'header + 6 rows');
        $this->assertSame('Student Name', $all[0][0]);
        $this->assertSame('Alpha Student', $all[1][0]);
        $this->assertSame('completed', $all[1][8]);
        $this->assertSame('5', $all[1][9]);
        $this->assertSame('1', $all[1][10]);
        $this->assertSame('4', $all[1][11]);
        $this->assertSame('active', $all[1][14]);

        // Filtered export carries the same filters as the page.
        $filtered = $this->parseCsv($this->csvContent(
            $this->actingAs($w['owner'], 'institute_user')
                ->get(route('academic.analytics.students.export', ['status' => 'dropped']))
                ->assertOk()
        ));

        $this->assertCount(2, $filtered, 'header + 1 dropped row');
        $this->assertSame('Chad Student', $filtered[1][0]);
    }

    // -------------------------------------------------- Courses & batches

    public function test_courses_report_aggregates_per_course(): void
    {
        $w = $this->analyticsWorld();

        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.courses'))
            ->assertOk();

        $this->bodyHas($response, 'Course A');
        $this->bodyHas($response, 'Course B');
        $this->bodyHas($response, '76.5%');
        $this->bodyHas($response, '100.0%');
        $this->bodyHas($response, '71.4%');
    }

    public function test_batches_report_aggregates_per_batch(): void
    {
        $w = $this->analyticsWorld();

        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.batches'))
            ->assertOk();

        $this->bodyHas($response, 'Batch A1');
        $this->bodyHas($response, 'Batch B1');
        $this->bodyHas($response, '76.5%');
    }

    // ---------------------------------------------------------- Attendance

    public function test_attendance_window_totals_match_reported_semantics(): void
    {
        $w = $this->analyticsWorld();

        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.attendance'))
            ->assertOk();

        // Whole-window totals: 6 present + 1 absent + 1 late = 8 records.
        $this->bodyHas($response, '8');
        $this->bodyHas($response, '6');
        $this->bodyHas($response, '75.0%');
        // The 2025-05-01 row is outside the 2026 window and must not appear.
        $this->bodyNotHas($response, '2025-05-01');

        // Class breakdown: 5 students in Class 8.
        $this->bodyHas($response, 'Class 8');
    }

    public function test_attendance_weekly_window_and_unrecorded_never_absent(): void
    {
        $w = $this->analyticsWorld();

        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.attendance', [
                'from' => '2026-06-01',
                'to' => '2026-06-30',
            ]))
            ->assertOk();

        $this->bodyHas($response, 'Weekly Trend');
        $this->bodyHas($response, 'Jun 2026');
        // Absent is only the explicitly recorded absent row, never the gaps.
        $this->bodyHas($response, '75.0%');
    }

    public function test_attendance_export_streams_buckets(): void
    {
        $w = $this->analyticsWorld();

        $rows = $this->parseCsv($this->csvContent(
            $this->actingAs($w['owner'], 'institute_user')
                ->get(route('academic.analytics.attendance.export'))
                ->assertOk()
        ));

        $this->assertSame('Period', $rows[0][0]);
        $this->assertSame('Jun 2026', $rows[1][0]);
        $this->assertSame('8', $rows[1][1]);
        $this->assertSame('75', $rows[1][6]);
    }

    // ----------------------------------------------------------- Results

    public function test_results_analytics_use_published_snapshots_only(): void
    {
        $w = $this->analyticsWorld();

        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.results'))
            ->assertOk();

        // Only the published result appears (the review one is hidden).
        $this->bodyHas($response, 'Final Result (published)');
        $this->bodyNotHas($response, 'Final Result (review)');

        // Frozen snapshot sums: 13 passed / 4 failed across 3 students.
        $this->bodyHas($response, '76.5%');
        $this->bodyHas($response, '13');
        $this->bodyHas($response, '4');
        $this->bodyHas($response, 'Class 8');
    }

    public function test_results_export_streams_published_rows(): void
    {
        $w = $this->analyticsWorld();

        $rows = $this->parseCsv($this->csvContent(
            $this->actingAs($w['owner'], 'institute_user')
                ->get(route('academic.analytics.results.export'))
                ->assertOk()
        ));

        $this->assertCount(2, $rows, 'header + 1 published result');
        $this->assertSame('Final Result (published)', $rows[1][0]);
        $this->assertSame('13', $rows[1][5]);
        $this->assertSame('4', $rows[1][6]);
    }

    // --------------------------------------------------------- Promotions

    public function test_promotions_report_per_year(): void
    {
        $w = $this->analyticsWorld();

        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.promotions'))
            ->assertOk();

        $this->bodyHas($response, 'Session 2026');
        $this->bodyHas($response, 'Session 2025');
        // 2026: approved + pending; approved outcomes completed + graduated.
        $this->bodyHas($response, 'Approved');
        $this->bodyHas($response, 'Pending');
        $this->bodyHas($response, 'Completed');
        $this->bodyHas($response, 'Graduated');
        // 2025: approved decision with a promoted outcome.
        $this->bodyHas($response, 'Promoted');
    }

    public function test_promotions_export_streams_statuses_and_outcomes(): void
    {
        $w = $this->analyticsWorld();

        $rows = $this->parseCsv($this->csvContent(
            $this->actingAs($w['owner'], 'institute_user')
                ->get(route('academic.analytics.promotions.export'))
                ->assertOk()
        ));

        $this->assertCount(3, $rows, 'header + 2 years');
        $this->assertSame('Session 2026', $rows[1][0]);
        $this->assertSame('1', $rows[1][1], 'pending');
        $this->assertSame('1', $rows[1][3], 'approved');
        $this->assertSame('1', $rows[1][8], 'completed');
        $this->assertSame('1', $rows[1][9], 'graduated');
        $this->assertSame('1', $rows[2][4], '2025 promoted');
    }

    // --------------------------------------------------------- Completion

    public function test_completion_report_per_year(): void
    {
        $w = $this->analyticsWorld();

        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.completion'))
            ->assertOk();

        $this->bodyHas($response, 'Session 2026');
        $this->bodyHas($response, 'Session 2025');
        // 2026: cohort 6, 1 completed + 1 graduated (approved decisions).
        $this->bodyHas($response, '16.7%');
        $this->bodyHas($response, '16.7%');
        // 2025: cohort 2, zero official completions.
        $this->bodyHas($response, '0.0%');
    }

    public function test_completion_export_streams_approved_figures(): void
    {
        $w = $this->analyticsWorld();

        $rows = $this->parseCsv($this->csvContent(
            $this->actingAs($w['owner'], 'institute_user')
                ->get(route('academic.analytics.completion.export'))
                ->assertOk()
        ));

        $this->assertCount(3, $rows, 'header + 2 years');
        $this->assertSame('Session 2026', $rows[1][0]);
        $this->assertSame('6', $rows[1][1], 'cohort');
        $this->assertSame('1', $rows[1][3], 'completed');
        $this->assertSame('1', $rows[1][4], 'graduated');
        $this->assertSame('16.7', $rows[1][7]);
    }

    // ------------------------------------------------------- Certificates

    public function test_certificates_report_totals_and_by_course(): void
    {
        $w = $this->analyticsWorld();

        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.certificates'))
            ->assertOk();

        $this->bodyHas($response, 'Issued');
        $this->bodyHas($response, 'Pending');
        $this->bodyHas($response, 'Revoked');
        $this->bodyHas($response, 'Rejected');
        $this->bodyHas($response, '25.0%');
        $this->bodyHas($response, 'Course A');
        $this->bodyHas($response, 'Course B');
    }

    public function test_certificates_export_streams_by_course(): void
    {
        $w = $this->analyticsWorld();

        $rows = $this->parseCsv($this->csvContent(
            $this->actingAs($w['owner'], 'institute_user')
                ->get(route('academic.analytics.certificates.export'))
                ->assertOk()
        ));

        $this->assertCount(3, $rows, 'header + 2 courses');
        $this->assertSame('Course A', $rows[1][0]);
        $this->assertSame('1', $rows[1][1], 'issued');
        $this->assertSame('1', $rows[1][2], 'revoked');
        $this->assertSame('1', $rows[1][3], 'pending');
        $this->assertSame('3', $rows[1][5], 'total');
        $this->assertSame('Course B', $rows[2][0]);
        $this->assertSame('1', $rows[2][4], 'rejected');
    }

    // ----------------------------------------------------------- Finance

    public function test_finance_report_is_gated_by_finance_view(): void
    {
        $w = $this->analyticsWorld();

        // A user with education.manage but no finance.view is refused.
        $this->actingAs($w['noFinance'], 'institute_user')
            ->get(route('academic.analytics.finance'))
            ->assertForbidden();
        $this->actingAs($w['noFinance'], 'institute_user')
            ->get(route('academic.analytics.finance.export'))
            ->assertForbidden();

        // The owner sees the finance report with the seeded invoice data.
        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.finance'))
            ->assertOk();

        $this->bodyHas($response, 'Finance &amp; Education Analytics');
        $this->bodyHas($response, 'Receivable');
        $this->bodyHas($response, 'Course A');
        $this->bodyHas($response, '5,000.00');
        $this->bodyHas($response, '4,000.00');
    }

    public function test_finance_export_is_gated_and_streams_billing(): void
    {
        $w = $this->analyticsWorld();

        $rows = $this->parseCsv($this->csvContent(
            $this->actingAs($w['owner'], 'institute_user')
                ->get(route('academic.analytics.finance.export'))
                ->assertOk()
        ));

        $this->assertSame('Course', $rows[0][0]);
        $this->assertSame('Course A', $rows[1][0]);
        $this->assertSame('5000', $rows[1][4], 'billed');
    }

    // -------------------------------------------------------------- CRM

    public function test_crm_report_is_gated_by_crm_view(): void
    {
        $w = $this->analyticsWorld();

        // A user with education.manage but no crm.view is refused.
        $this->actingAs($w['noFinance'], 'institute_user')
            ->get(route('academic.analytics.crm'))
            ->assertForbidden();
        $this->actingAs($w['noFinance'], 'institute_user')
            ->get(route('academic.analytics.crm.export'))
            ->assertForbidden();

        // The owner sees the CRM funnel.
        $response = $this->actingAs($w['owner'], 'institute_user')
            ->get(route('academic.analytics.crm'))
            ->assertOk();

        $this->bodyHas($response, 'Contacts');
        $this->bodyHas($response, 'Organizations');
        $this->bodyHas($response, 'Leads');
        $this->bodyHas($response, '25.0%');
        $this->bodyHas($response, 'Won');
        $this->bodyHas($response, 'Lost');
    }

    public function test_crm_export_streams_lead_statuses(): void
    {
        $w = $this->analyticsWorld();

        $rows = $this->parseCsv($this->csvContent(
            $this->actingAs($w['owner'], 'institute_user')
                ->get(route('academic.analytics.crm.export'))
                ->assertOk()
        ));

        $byStatus = collect($rows)->skip(1)->keyBy(fn ($row) => $row[0])->map(fn ($row) => $row[1])->all();

        $this->assertSame('Lead Status', $rows[0][0]);
        $this->assertSame('1', $byStatus['New'], 'one new lead');
        $this->assertSame('0', $byStatus['Contacted'], 'no contacted leads');
        $this->assertSame('2', $byStatus['Won'], 'two won leads');
        $this->assertSame('1', $byStatus['Lost'], 'one lost (branch B) lead');
    }

    // ---------------------------------------------------------- Isolation

    public function test_analytics_isolation_cross_tenant(): void
    {
        $w = $this->analyticsWorld();

        $foreign = $this->institute($w['c'], 'Foreign Institute');
        $foreignBranch = $this->branch($foreign, 'Foreign Branch');
        $foreignOwner = $this->user($foreign, 'institute-owner', 'an-foreign');
        $foreignCourse = $this->course($foreign, 'Foreign Course');
        $foreignBatch = $this->batch($foreign, $foreignBranch, $foreignCourse, 'Foreign Batch');
        $foreignStudent = $this->student($foreign, 'Zulu', $foreignBranch);
        $this->enroll($foreignStudent, $foreignBatch);
        $foreignYear = $this->year($foreign, '2026', '2026-01-01', '2026-12-31', true);
        $this->placement($foreign, $foreignStudent, $foreignYear, $w['class8'], StudentAcademicPlacement::STATUS_ACTIVE);
        $this->certificate($foreign, $foreignStudent, $foreignBatch, 'active');

        // Owner of the first institute never sees the foreign data.
        $this->actingAs($w['owner'], 'institute_user');

        $this->bodyNotHas(
            $this->get(route('academic.analytics.students'))->assertOk(),
            'Zulu'
        );

        $this->bodyNotHas(
            $this->get(route('academic.analytics.certificates'))->assertOk(),
            'Foreign Course'
        );

        $rows = $this->parseCsv($this->csvContent(
            $this->get(route('academic.analytics.certificates.export'))->assertOk()
        ));
        $this->assertCount(3, $rows, 'only the two home courses');

        // And the foreign owner sees only their own institute.
        $this->actingAs($foreignOwner, 'institute_user');
        $response = $this->get(route('academic.analytics.students'))->assertOk();
        $this->bodyHas($response, 'Zulu');
        $this->bodyNotHas($response, 'Alpha');
    }

    public function test_analytics_isolation_cross_branch(): void
    {
        $w = $this->analyticsWorld();

        // A branch-A education manager sees only branch-A rows.
        $this->actingAs($w['branchAOnly'], 'institute_user');

        $response = $this->get(route('academic.analytics.students'))->assertOk();
        $this->bodyHas($response, '5 matched');
        foreach (['Alpha', 'Beta', 'Chad', 'Delta', 'Foxtrot'] as $name) {
            $this->bodyHas($response, $name);
        }
        $this->bodyNotHas($response, 'Echo');

        // Attendance window excludes the branch-B student's row.
        $response = $this->get(route('academic.analytics.attendance'))->assertOk();
        $this->bodyHas($response, '7');
        $this->bodyHas($response, '5');

        // Course B's batch is invisible to the branch-A manager.
        $response = $this->get(route('academic.analytics.batches'))->assertOk();
        $this->bodyHas($response, 'Batch A1');
        $this->bodyNotHas($response, 'Batch B1');

        // Certificates exclude the branch-B (rejected) certificate.
        $response = $this->get(route('academic.analytics.certificates'))->assertOk();
        $this->bodyHas($response, '3');

        // CRM: the branch-B lead and contact are hidden, others remain.
        $response = $this->get(route('academic.analytics.crm'))->assertOk();
        $this->bodyHas($response, 'Leads');
        $this->bodyHas($response, 'Contacts');
    }

    // ---------------------------------------------------------- Read-only

    public function test_analytics_is_read_only(): void
    {
        $w = $this->analyticsWorld();

        $before = [
            'students' => Student::query()->where('institute_id', $w['institute']->id)->count(),
            'enrollments' => StudentEnrollment::query()->where('institute_id', $w['institute']->id)->count(),
            'placements' => StudentAcademicPlacement::query()->where('institute_id', $w['institute']->id)->count(),
            'attendance' => Attendance::query()->where('institute_id', $w['institute']->id)->count(),
            'results' => AcademicFinalResult::query()->where('institute_id', $w['institute']->id)->count(),
            'snapshots' => AcademicFinalResultStudent::query()->count(),
            'decisions' => PromotionDecision::query()->where('institute_id', $w['institute']->id)->count(),
            'items' => PromotionDecisionItem::query()->count(),
            'certificates' => Certificate::query()->where('institute_id', $w['institute']->id)->count(),
            'leads' => CrmLead::query()->where('institute_id', $w['institute']->id)->count(),
        ];

        $this->actingAs($w['owner'], 'institute_user');

        foreach ([
            'academic.analytics.index',
            'academic.analytics.students',
            'academic.analytics.courses',
            'academic.analytics.batches',
            'academic.analytics.attendance',
            'academic.analytics.results',
            'academic.analytics.promotions',
            'academic.analytics.completion',
            'academic.analytics.certificates',
            'academic.analytics.finance',
            'academic.analytics.crm',
        ] as $route) {
            $this->get(route($route))->assertOk();
        }

        foreach ([
            'academic.analytics.students.export',
            'academic.analytics.courses.export',
            'academic.analytics.batches.export',
            'academic.analytics.attendance.export',
            'academic.analytics.results.export',
            'academic.analytics.promotions.export',
            'academic.analytics.completion.export',
            'academic.analytics.certificates.export',
            'academic.analytics.finance.export',
            'academic.analytics.crm.export',
        ] as $route) {
            $this->get(route($route))->assertOk();
        }

        $this->assertSame($before['students'], Student::query()->where('institute_id', $w['institute']->id)->count());
        $this->assertSame($before['enrollments'], StudentEnrollment::query()->where('institute_id', $w['institute']->id)->count());
        $this->assertSame($before['placements'], StudentAcademicPlacement::query()->where('institute_id', $w['institute']->id)->count());
        $this->assertSame($before['attendance'], Attendance::query()->where('institute_id', $w['institute']->id)->count());
        $this->assertSame($before['results'], AcademicFinalResult::query()->where('institute_id', $w['institute']->id)->count());
        $this->assertSame($before['snapshots'], AcademicFinalResultStudent::query()->count());
        $this->assertSame($before['decisions'], PromotionDecision::query()->where('institute_id', $w['institute']->id)->count());
        $this->assertSame($before['items'], PromotionDecisionItem::query()->count());
        $this->assertSame($before['certificates'], Certificate::query()->where('institute_id', $w['institute']->id)->count());
        $this->assertSame($before['leads'], CrmLead::query()->where('institute_id', $w['institute']->id)->count());
    }
}
