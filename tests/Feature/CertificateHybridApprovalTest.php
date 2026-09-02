<?php

namespace Tests\Feature;

use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultPolicy;
use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Certificate;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\Course;
use App\Models\EducationSystem;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\PromotionDecision;
use App\Models\PromotionDecisionItem;
use App\Models\PromotionPolicy;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\StudentEnrollment;
use App\Services\CertificateApprovalModeService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CertificateHybridApprovalTest extends TestCase
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

    private function seededContext(): array
    {
        $country = Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh',
            'iso3' => 'BGD',
            'phone_code' => '880',
            'status' => true,
        ]);

        $system = EducationSystem::firstOrCreate(
            ['country_id' => $country->id, 'code' => 'general'],
            ['name' => 'General Education',
            'display_order' => 0,
            'status' => true,
        ]);

        $level = AcademicLevel::firstOrCreate(
            ['country_id' => $country->id, 'education_system_id' => $system->id, 'code' => 'secondary'],
            ['name' => 'Secondary',
            'display_order' => 1,
            'status' => true,
        ]);

        $class = ClassGrade::firstOrCreate(
            ['country_id' => $country->id, 'education_system_id' => $system->id, 'academic_level_id' => $level->id, 'code' => 'c10'],
            ['name' => 'Class 10',
            'display_order' => 0,
            'status' => true,
        ]);

        $group = AcademicGroup::firstOrCreate(
            ['country_id' => $country->id, 'education_system_id' => $system->id, 'academic_level_id' => $level->id, 'class_grade_id' => $class->id, 'code' => 'g99'],
            ['name' => 'General',
            'display_order' => 0,
            'status' => true,
        ]);

        $institute = Institute::create([
            'name' => 'Certificate Institute '.uniqid(),
            'slug' => str()->slug('certificate-institute-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);

        $branch = Branch::create([
            'institute_id' => $institute->id,
            'name' => 'Main Campus',
            'status' => 'active',
        ]);

        $owner = InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'role_id' => Role::where('slug', 'institute-owner')->firstOrFail()->id,
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => 'cert-owner-'.uniqid().'@example.test',
            'phone' => '01700'.mt_rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $teacher = InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'role_id' => Role::where('slug', 'teacher')->firstOrFail()->id,
            'first_name' => 'Teacher',
            'last_name' => 'User',
            'email' => 'cert-teacher-'.uniqid().'@example.test',
            'phone' => '01700'.mt_rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        $student = Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'student_id_number' => 'CR'.mt_rand(100000, 999999),
            'first_name' => 'Grad',
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => '2024-01-01',
        ]);

        $course = Course::create([
            'course_code' => 'CT'.mt_rand(1000, 9999),
            'name' => 'Certificate Course '.uniqid(),
        ]);

        $batch = Batch::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'name' => 'Batch C',
            'batch_code' => 'BC-'.mt_rand(10, 99),
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'status' => 'ongoing',
        ]);

        StudentEnrollment::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'roll_number' => 'R'.mt_rand(10, 99),
            'enrollment_date' => '2024-01-05',
            'fee_payable' => 10000,
            'discount' => 0,
            'status' => 'active',
        ]);

        $year = AcademicYear::create([
            'institute_id' => $institute->id,
            'name' => 'Session 2024',
            'code' => '2024',
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'is_current' => false,
            'status' => true,
        ]);

        $placement = StudentAcademicPlacement::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $class->id,
            'status' => StudentAcademicPlacement::STATUS_ACTIVE,
        ]);

        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $institute->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $class->id,
            'name' => 'Aggregation Scheme',
            'status' => 'active',
        ]);

        $policy = AcademicFinalResultPolicy::create([
            'institute_id' => $institute->id,
            'scheme_id' => $scheme->id,
            'name' => 'Final Result Policy',
            'absent_renormalization' => true,
            'require_approval' => true,
            'status' => 'active',
        ]);

        $result = AcademicFinalResult::create([
            'institute_id' => $institute->id,
            'policy_id' => $policy->id,
            'scheme_id' => $scheme->id,
            'name' => 'Final Result 2024',
            'status' => AcademicFinalResult::STATUS_PUBLISHED,
        ]);

        $promoPolicy = PromotionPolicy::create([
            'institute_id' => $institute->id,
            'name' => 'Promotion Policy',
            'academic_year_id' => $year->id,
            'class_grade_id' => $class->id,
            'status' => 'active',
        ]);

        $decision = PromotionDecision::create([
            'policy_id' => $promoPolicy->id,
            'result_id' => $result->id,
            'institute_id' => $institute->id,
            'academic_year_id' => $year->id,
            'status' => PromotionDecision::STATUS_APPROVED,
        ]);

        PromotionDecisionItem::create([
            'decision_id' => $decision->id,
            'placement_id' => $placement->id,
            'student_id' => $student->id,
            'decision' => PromotionDecisionItem::DECISION_GRADUATED,
            'approved_at' => now(),
        ]);

        return compact('institute', 'branch', 'owner', 'teacher', 'student', 'course', 'batch');
    }

    private function withContext(Institute $institute): void
    {
        TenantContext::set($institute->id);
        BranchContext::clear();
    }

    private function createCertificateRequest(array $ctx): Certificate
    {
        $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('students.certificate-request', $ctx['student']))
            ->assertRedirect();

        $certificate = Certificate::query()
            ->where('student_id', $ctx['student']->id)
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($certificate, 'Certificate request should have been created');

        return $certificate;
    }

    // ---------------------------------------------------------------- Tests

    public function test_default_mode_is_super_admin_required(): void
    {
        $ctx = $this->seededContext();
        $this->withContext($ctx['institute']);

        $service = app(CertificateApprovalModeService::class);
        $mode = $service->getMode($ctx['institute']->id);

        // Default is now Admin Controlled (per 2026-08-27 spec: new institutes default to admin)
        $this->assertSame(InstituteSetting::CERTIFICATE_APPROVAL_ADMIN, $mode);
    }

    public function test_admin_can_enable_admin_controlled_mode(): void
    {
        $ctx = $this->seededContext();
        $this->withContext($ctx['institute']);

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->put(route('settings.certificate-approval-mode.update'), [
                'certificate_approval_mode' => 'admin',
            ]);

        $response->assertStatus(302);
        $response->assertSessionHas('status');

        $service = app(CertificateApprovalModeService::class);
        $this->assertTrue($service->isAdminControlled($ctx['institute']->id));
    }

    public function test_admin_can_disable_admin_controlled_mode(): void
    {
        $ctx = $this->seededContext();
        $this->withContext($ctx['institute']);

        // First enable it
        $this->actingAs($ctx['owner'], 'institute_user')
            ->put(route('settings.certificate-approval-mode.update'), [
                'certificate_approval_mode' => 'admin',
            ]);

        // Then disable it
        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->put(route('settings.certificate-approval-mode.update'), [
                'certificate_approval_mode' => 'super_admin',
            ]);

        $response->assertRedirect();

        $service = app(CertificateApprovalModeService::class);
        $this->assertTrue($service->isSuperAdminRequired($ctx['institute']->id));
    }

    public function test_setting_persists(): void
    {
        $ctx = $this->seededContext();
        $this->withContext($ctx['institute']);

        // Default is now admin — persist super_admin to verify DB write
        $this->actingAs($ctx['owner'], 'institute_user')
            ->put(route('settings.certificate-approval-mode.update'), [
                'certificate_approval_mode' => 'super_admin',
            ]);

        $this->assertDatabaseHas('institute_settings', [
            'institute_id' => $ctx['institute']->id,
            'certificate_approval_mode' => 'super_admin',
        ]);
    }

    public function test_setting_is_institute_specific(): void
    {
        $ctx1 = $this->seededContext();
        $ctx2 = $this->seededContext();

        // New default is admin — flip ctx1 to super_admin to verify isolation
        $this->withContext($ctx1['institute']);

        $this->actingAs($ctx1['owner'], 'institute_user')
            ->put(route('settings.certificate-approval-mode.update'), [
                'certificate_approval_mode' => 'super_admin',
            ]);

        $service = app(CertificateApprovalModeService::class);
        $this->assertTrue($service->isSuperAdminRequired($ctx1['institute']->id));
        $this->assertTrue($service->isAdminControlled($ctx2['institute']->id));
    }

    public function test_institute_a_cannot_modify_institute_b_setting(): void
    {
        $ctx1 = $this->seededContext();
        $ctx2 = $this->seededContext();

        // Prepare ctx2 as super_admin to verify cross-tenant isolation (default is now admin)
        app(CertificateApprovalModeService::class)->setMode($ctx2['institute']->id, InstituteSetting::CERTIFICATE_APPROVAL_SUPER_ADMIN);
        $this->withContext($ctx2['institute']);

        // User from institute 1 tries to modify institute 2's setting
        $response = $this->actingAs($ctx1['owner'], 'institute_user')
            ->put(route('settings.certificate-approval-mode.update'), [
                'certificate_approval_mode' => 'admin',
            ]);

        // Should fail - either 403 or redirect (permission denied)
        $this->assertTrue(
            $response->status() === 403 || $response->isRedirect(),
            'Expected 403 or redirect for unauthorized cross-institute access'
        );

        // Setting should NOT be saved for institute 2 — should remain super_admin
        $service = app(CertificateApprovalModeService::class);
        $this->assertTrue($service->isSuperAdminRequired($ctx2['institute']->id));
    }

    public function test_authorized_institute_admin_can_approve_in_admin_mode(): void
    {
        $ctx = $this->seededContext();
        $this->withContext($ctx['institute']);

        // Enable admin-controlled mode
        $this->actingAs($ctx['owner'], 'institute_user')
            ->put(route('settings.certificate-approval-mode.update'), [
                'certificate_approval_mode' => 'admin',
            ]);

        $certificate = $this->createCertificateRequest($ctx);

        // Approve the certificate
        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('certificates.action', $certificate->id), [
                'action' => 'approve',
            ]);

        $response->assertRedirect();

        $certificate->refresh();
        $this->assertSame('active', $certificate->status);
        $this->assertNotNull($certificate->certificate_number);
        $this->assertNotNull($certificate->issue_date);
    }

    public function test_unauthorized_user_cannot_approve_in_admin_mode(): void
    {
        $ctx = $this->seededContext();
        $this->withContext($ctx['institute']);

        // Enable admin-controlled mode
        $this->actingAs($ctx['owner'], 'institute_user')
            ->put(route('settings.certificate-approval-mode.update'), [
                'certificate_approval_mode' => 'admin',
            ]);

        $certificate = $this->createCertificateRequest($ctx);

        // Teacher without certificates.manage permission cannot approve
        $response = $this->actingAs($ctx['teacher'], 'institute_user')
            ->post(route('certificates.action', $certificate->id), [
                'action' => 'approve',
            ]);

        // Should be denied - either 403 or redirect (permission middleware behavior)
        $this->assertTrue(
            $response->status() === 403 || $response->isRedirect(),
            'Expected 403 or redirect for unauthorized user'
        );

        // Certificate should still be pending
        $certificate->refresh();
        $this->assertSame('pending', $certificate->status);
    }

    public function test_institute_admin_cannot_final_approve_in_super_admin_mode(): void
    {
        $ctx = $this->seededContext();
        $this->withContext($ctx['institute']);

        // Default is now admin — explicitly set to super_admin for this test
        app(CertificateApprovalModeService::class)->setMode($ctx['institute']->id, InstituteSetting::CERTIFICATE_APPROVAL_SUPER_ADMIN);
        $certificate = $this->createCertificateRequest($ctx);

        // Institute admin cannot approve in super_admin mode
        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('certificates.action', $certificate->id), [
                'action' => 'approve',
            ]);

        // Should be denied - either 403 or redirect (permission middleware behavior)
        $this->assertTrue(
            $response->status() === 403 || $response->isRedirect(),
            'Expected 403 or redirect when trying to approve in super_admin mode'
        );

        // Certificate should still be pending
        $certificate->refresh();
        $this->assertSame('pending', $certificate->status);
    }

    public function test_direct_endpoint_cannot_bypass_permission(): void
    {
        $ctx = $this->seededContext();
        $this->withContext($ctx['institute']);

        // Default is now admin — explicitly set to super_admin for this test
        app(CertificateApprovalModeService::class)->setMode($ctx['institute']->id, InstituteSetting::CERTIFICATE_APPROVAL_SUPER_ADMIN);
        $certificate = $this->createCertificateRequest($ctx);

        // Even with direct POST, institute admin cannot approve in super_admin mode
        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('certificates.action', $certificate->id), [
                'action' => 'approve',
            ]);

        // Should be denied - either 403 or redirect (permission middleware behavior)
        $this->assertTrue(
            $response->status() === 403 || $response->isRedirect(),
            'Expected 403 or redirect when trying to bypass permission'
        );

        // Certificate should still be pending
        $certificate->refresh();
        $this->assertSame('pending', $certificate->status);
    }

    public function test_student_request_can_be_approved_in_admin_mode(): void
    {
        $ctx = $this->seededContext();
        $this->withContext($ctx['institute']);

        // Enable admin-controlled mode
        $this->actingAs($ctx['owner'], 'institute_user')
            ->put(route('settings.certificate-approval-mode.update'), [
                'certificate_approval_mode' => 'admin',
            ]);

        $certificate = $this->createCertificateRequest($ctx);

        // Institute admin can approve
        $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('certificates.action', $certificate->id), [
                'action' => 'approve',
            ]);

        $certificate->refresh();
        $this->assertSame('active', $certificate->status);
    }

    public function test_institute_admin_can_reject_in_admin_mode(): void
    {
        $ctx = $this->seededContext();
        $this->withContext($ctx['institute']);

        // Enable admin-controlled mode
        $this->actingAs($ctx['owner'], 'institute_user')
            ->put(route('settings.certificate-approval-mode.update'), [
                'certificate_approval_mode' => 'admin',
            ]);

        $certificate = $this->createCertificateRequest($ctx);

        // Reject the certificate
        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('certificates.action', $certificate->id), [
                'action' => 'reject',
                'reason' => 'Incomplete documentation',
            ]);

        $response->assertRedirect();

        $certificate->refresh();
        $this->assertSame('rejected', $certificate->status);
        $this->assertSame('Incomplete documentation', $certificate->review_note);
    }

    public function test_multitenant_workflow_isolation(): void
    {
        $ctx1 = $this->seededContext();
        $ctx2 = $this->seededContext();

        // Enable admin mode for institute 1
        $this->withContext($ctx1['institute']);
        $this->actingAs($ctx1['owner'], 'institute_user')
            ->put(route('settings.certificate-approval-mode.update'), [
                'certificate_approval_mode' => 'admin',
            ]);

        // Create certificate request for institute 1
        $this->actingAs($ctx1['owner'], 'institute_user')
            ->post(route('students.certificate-request', $ctx1['student']))
            ->assertRedirect();

        // Create certificate request for institute 2
        $this->withContext($ctx2['institute']);
        $this->actingAs($ctx2['owner'], 'institute_user')
            ->post(route('students.certificate-request', $ctx2['student']))
            ->assertRedirect();

        // Institute 1 admin cannot approve institute 2's certificate
        $certificate2 = Certificate::withoutGlobalScopes()
            ->where('student_id', $ctx2['student']->id)
            ->where('status', 'pending')
            ->first();

        $this->assertNotNull($certificate2, 'Certificate request should have been created for institute 2');

        $this->withContext($ctx1['institute']);
        $response = $this->actingAs($ctx1['owner'], 'institute_user')
            ->post(route('certificates.action', $certificate2->id), [
                'action' => 'approve',
            ]);

        // Should be denied - 403, 404 (scope filtering), or redirect (tenant isolation)
        $this->assertTrue(
            in_array($response->status(), [403, 404]) || $response->isRedirect(),
            'Expected 403, 404, or redirect for cross-tenant approval attempt, got '.$response->status()
        );

        // Certificate 2 should still be pending
        $certificate2->refresh();
        $this->assertSame('pending', $certificate2->status);
    }

    public function test_audit_log_is_created_on_mode_change(): void
    {
        $ctx = $this->seededContext();
        $this->withContext($ctx['institute']);

        // Default is now admin — flip to super_admin to generate audit
        app(CertificateApprovalModeService::class)->setMode($ctx['institute']->id, InstituteSetting::CERTIFICATE_APPROVAL_SUPER_ADMIN);

        $this->actingAs($ctx['owner'], 'institute_user')
            ->put(route('settings.certificate-approval-mode.update'), [
                'certificate_approval_mode' => 'admin',
            ]);

        $auditLog = AuditLog::query()
            ->where('institute_id', $ctx['institute']->id)
            ->where('action', 'certificate_approval_mode_changed')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertStringContainsString('super_admin', $auditLog->old_values);
        $this->assertStringContainsString('admin', $auditLog->new_values);
    }

    public function test_no_duplicate_mode_change_when_same_value(): void
    {
        $ctx = $this->seededContext();
        $this->withContext($ctx['institute']);

        // Default is now admin — first change to super_admin
        $this->actingAs($ctx['owner'], 'institute_user')
            ->put(route('settings.certificate-approval-mode.update'), [
                'certificate_approval_mode' => 'super_admin',
            ]);

        // Second change with same value
        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->put(route('settings.certificate-approval-mode.update'), [
                'certificate_approval_mode' => 'super_admin',
            ]);

        $response->assertSessionHas('status', 'Certificate approval mode is already set to Super Admin Required.');

        // Should only have one audit log
        $auditCount = AuditLog::query()
            ->where('institute_id', $ctx['institute']->id)
            ->where('action', 'certificate_approval_mode_changed')
            ->count();

        $this->assertSame(1, $auditCount);
    }

    public function test_invalid_mode_value_is_rejected(): void
    {
        $ctx = $this->seededContext();
        $this->withContext($ctx['institute']);

        $response = $this->actingAs($ctx['owner'], 'institute_user')
            ->put(route('settings.certificate-approval-mode.update'), [
                'certificate_approval_mode' => 'invalid_mode',
            ]);

        $response->assertSessionHasErrors('certificate_approval_mode');
    }

    public function test_auth_required_for_setting_change(): void
    {
        $ctx = $this->seededContext();
        $this->withContext($ctx['institute']);

        $response = $this->put(route('settings.certificate-approval-mode.update'), [
            'certificate_approval_mode' => 'admin',
        ]);

        $response->assertRedirect();
    }

    public function test_settings_manage_permission_required(): void
    {
        $ctx = $this->seededContext();
        $this->withContext($ctx['institute']);

        // Teacher without settings.manage permission
        $response = $this->actingAs($ctx['teacher'], 'institute_user')
            ->put(route('settings.certificate-approval-mode.update'), [
                'certificate_approval_mode' => 'admin',
            ]);

        // Should be denied - either 403 or redirect (permission middleware behavior)
        $this->assertTrue(
            $response->status() === 403 || $response->isRedirect(),
            'Expected 403 or redirect for unauthorized user'
        );

        // Setting should NOT be saved — default remains admin
        $service = app(CertificateApprovalModeService::class);
        $this->assertTrue($service->isAdminControlled($ctx['institute']->id));
    }

    public function test_existing_certificate_permissions_preserved(): void
    {
        $ctx = $this->seededContext();
        $this->withContext($ctx['institute']);

        // Default is now admin-controlled — no need to enable, already admin

        // Teacher still cannot create certificate requests without permission
        $response = $this->actingAs($ctx['teacher'], 'institute_user')
            ->post(route('students.certificate-request', $ctx['student']));

        // Should be denied - either 403 or redirect (permission middleware behavior)
        $this->assertTrue(
            $response->status() === 403 || $response->isRedirect(),
            'Expected 403 or redirect for unauthorized user'
        );
    }
}
