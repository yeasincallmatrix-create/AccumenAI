<?php

namespace Tests\Feature;

use App\Http\Middleware\BlockPlatformAdminEscalation;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Medical\Encounter;
use App\Models\Medical\Patient;
use App\Models\Medical\Prescription;
use App\Models\Medical\LabTest;
use App\Models\Medical\LabOrder;
use App\Models\Medical\LabResult;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\ResolvesTestIds;
use Tests\TestCase;

/**
 * Phase 12 — Security & Tenant Forensic Audit.
 *
 * Adversarial tests documenting security findings across four domains:
 *  1. Tenant isolation (IDOR, global scope bypass, workspace forgery)
 *  2. Authorization (missing permission middleware, cross-branch access)
 *  3. Platform admin boundary (escalation blocking, bypass semantics)
 *  4. Concurrency (lockForUpdate, transaction safety)
 *
 * CRITICAL: These tests document FINDINGS, not regressions. Each test
 * asserts the CURRENT behaviour (which may be a gap). The companion
 * report (PHASE_12_SECURITY_AUDIT.md) details remediation priorities.
 */
class SecurityForensicTest extends TestCase
{
    use DatabaseTransactions, ResolvesTestIds;

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function makeInstitute(): Institute
    {
        $slug = 'forensic-'.uniqid();
        return Institute::create([
            'name' => 'Forensic Test Institute '.uniqid(),
            'slug' => $slug,
            'status' => 'active',
            'package_id' => 1,
            'currency_id' => 1,
            'country_id' => $this->bdCountryId(),
        ]);
    }

    private function makeUser(Institute $institute): User
    {
        $user = User::create([
            'name' => 'Forensic User '.uniqid(),
            'email' => 'forensic_'.uniqid().'@test.com',
            'password_hash' => bcrypt('password'),
        ]);

        $ownerRoleId = \App\Models\Role::where('slug', 'institute-owner')->whereNull('institute_id')->value('id');

        \App\Models\Membership::create([
            'user_id' => $user->id,
            'institution_id' => $institute->id,
            'role_id' => $ownerRoleId,
            'status' => 'active',
        ]);

        return $user;
    }

    private function getExistingPlatformAdmin(): PlatformAdmin
    {
        $admin = PlatformAdmin::first();
        $this->assertNotNull($admin, 'A PlatformAdmin must exist for forensic tests.');

        return $admin;
    }

    // ================================================================
    // PART 1 — TENANT ISOLATION FORENSIC
    // ================================================================

    /** @test */
    public function workspace_forgery_is_rejected_with_403(): void
    {
        $inst = $this->makeInstitute();
        $user = $this->makeUser($inst);

        $otherInst = $this->makeInstitute();

        $this->actingAs($user, 'web');

        session(['workspace_id' => $otherInst->id]);

        $response = $this->get('/dashboard');

        $response->assertStatus(403);
    }

    /** @test */
    public function medical_models_lack_tenantscoped_trait(): void
    {
        $medicalModels = [
            Patient::class,
            Encounter::class,
            Prescription::class,
        ];

        foreach ($medicalModels as $modelClass) {
            $uses = class_uses_recursive($modelClass);
            $this->assertNotContains(
                \App\Models\Concerns\TenantScoped::class,
                $uses,
                "{$modelClass} should NOT use TenantScoped (documented gap)."
            );
        }
    }

    /** @test */
    public function labtest_model_has_tenantscoped_trait(): void
    {
        $uses = class_uses_recursive(LabTest::class);
        $this->assertContains(
            \App\Models\Concerns\TenantScoped::class,
            $uses,
            'LabTest is the only medical model with TenantScoped (Phase 10).'
        );
    }

    /** @test */
    public function labresult_model_has_tenantscoped_trait(): void
    {
        $uses = class_uses_recursive(LabResult::class);
        $this->assertContains(
            \App\Models\Concerns\TenantScoped::class,
            $uses,
            'LabResult has TenantScoped.'
        );
    }

    // ================================================================
    // PART 2 — AUTHORIZATION FORENSIC
    // ================================================================

    /**
     * @test
     *
     * FINDING: Legacy flat medical routes (lines 75-219 of medical.php)
     * lack `medical.module` middleware that the new grouped routes have.
     * The legacy routes only use auth + tenant + medical (module check).
     * They do have permission middleware via the HasMiddleware interface
     * on controllers, but NOT route-level medical.module middleware.
     */
    public function legacy_medical_flat_routes_lack_medical_module_middleware(): void
    {
        $routesWithoutModuleMiddleware = [];

        $legacyMedicalRoutes = [
            'medical.patients.index',
            'medical.patients.store',
            'medical.appointments.index',
            'medical.appointments.store',
            'medical.prescriptions.index',
            'medical.prescriptions.store',
            'medical.admissions.index',
            'medical.admissions.store',
            'medical.tpa.claims.index',
            'medical.tpa.claims.approve',
        ];

        foreach ($legacyMedicalRoutes as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            if ($route === null) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            $hasMedicalModule = false;
            foreach ($middleware as $m) {
                if (is_string($m) && str_contains($m, 'medical.module')) {
                    $hasMedicalModule = true;
                    break;
                }
            }
            if (! $hasMedicalModule) {
                $routesWithoutModuleMiddleware[] = $routeName;
            }
        }

        $this->assertNotEmpty(
            $routesWithoutModuleMiddleware,
            'Legacy medical flat routes should NOT have medical.module middleware (documented gap). '
            .'Routes without medical.module: '.implode(', ', $routesWithoutModuleMiddleware)
        );
    }

    /** @test */
    public function finance_web_routes_lack_permission_middleware(): void
    {
        $routesWithoutPermission = [];

        $financeRoutes = [
            'finance.dashboard',
            'finance.chart-of-accounts.index',
            'finance.invoices.index',
            'finance.payments.index',
            'finance.parties.index',
            'finance.payment-methods.index',
            'finance.periods.index',
            'accounting.dashboard',
            'accounting.reports.profit-loss',
            'accounting.reports.balance-sheet',
            'accounting.reports.cash-flow',
        ];

        foreach ($financeRoutes as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            if ($route === null) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            $hasPermission = false;
            foreach ($middleware as $m) {
                if (is_string($m) && str_starts_with($m, 'permission:')) {
                    $hasPermission = true;
                    break;
                }
            }
            if (! $hasPermission) {
                $routesWithoutPermission[] = $routeName;
            }
        }

        $this->assertNotEmpty(
            $routesWithoutPermission,
            'Finance/accounting web routes should NOT have route-level permission middleware (documented gap). '
            .'Routes without permission: '.implode(', ', $routesWithoutPermission)
        );
    }

    /** @test */
    public function api_routes_have_permission_middleware(): void
    {
        $apiRoutesWithoutPermission = [];

        $apiRoutes = [
            'api.students.index',
            'api.students.show',
            'api.courses.index',
            'api.invoices.index',
            'api.payments.index',
            'api.crm.contacts.index',
            'api.notifications.index',
            'api.purchase.orders.index',
        ];

        foreach ($apiRoutes as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            if ($route === null) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            $hasPermission = false;
            foreach ($middleware as $m) {
                if (is_string($m) && str_starts_with($m, 'permission:')) {
                    $hasPermission = true;
                    break;
                }
            }
            if (! $hasPermission) {
                $apiRoutesWithoutPermission[] = $routeName;
            }
        }

        $this->assertEmpty(
            $apiRoutesWithoutPermission,
            'All API routes must have permission middleware. Missing on: '.implode(', ', $apiRoutesWithoutPermission)
        );
    }

    /** @test */
    public function branch_context_id_validates_institute_not_branch_access(): void
    {
        $inst = $this->makeInstitute();

        if (! Schema::hasTable('branches')) {
            $this->markTestSkipped('branches table not found.');
        }

        $branchA = DB::table('branches')->insertGetId([
            'institute_id' => $inst->id,
            'code' => 'BA'.rand(10, 99),
            'name' => 'Branch A',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $branchB = DB::table('branches')->insertGetId([
            'institute_id' => $inst->id,
            'code' => 'BB'.rand(10, 99),
            'name' => 'Branch B',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $controller = app(\App\Http\Controllers\Medical\PatientController::class);

        TenantContext::set($inst->id);
        BranchContext::set($branchA);

        $method = new \ReflectionMethod($controller, 'branchContextId');
        $method->setAccessible(true);
        $resolved = $method->invoke($controller);

        TenantContext::clear();
        BranchContext::clear();

        $this->assertSame(
            $branchA,
            $resolved,
            'branchContextId() returns the context branch id (validates branch belongs to institute).'
        );

        $this->assertNotSame(
            $branchB,
            $resolved,
            'branchContextId() does NOT return a different branch — but does not call ensureBranchAccess().'
        );
    }

    /** @test */
    public function new_medical_grouped_routes_have_medical_module_middleware(): void
    {
        $routesWithoutModuleMiddleware = [];

        $newMedicalRoutes = [
            'medical.opd.appointments.index',
            'medical.ipd.admissions.index',
            'medical.pharmacy.index',
            'medical.billing.index',
            'medical.emergency.index',
            'medical.emergency.store',
        ];

        foreach ($newMedicalRoutes as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            if ($route === null) {
                continue;
            }
            $middleware = $route->gatherMiddleware();
            $hasMedicalModule = false;
            foreach ($middleware as $m) {
                if (is_string($m) && str_contains($m, 'medical.module')) {
                    $hasMedicalModule = true;
                    break;
                }
            }
            if (! $hasMedicalModule) {
                $routesWithoutModuleMiddleware[] = $routeName;
            }
        }

        $this->assertEmpty(
            $routesWithoutModuleMiddleware,
            'New grouped medical routes must have medical.module middleware. Missing: '
            .implode(', ', $routesWithoutModuleMiddleware)
        );
    }

    // ================================================================
    // PART 3 — PLATFORM ADMIN BOUNDARY
    // ================================================================

    /** @test */
    public function platform_admin_bypasses_check_permission_middleware(): void
    {
        $admin = $this->getExistingPlatformAdmin();

        $this->actingAs($admin, 'platform_admin');

        $response = $this->get('/admin/dashboard');

        $this->assertNotSame(403, $response->status(), 'Platform admin should bypass permission checks.');
    }

    /** @test */
    public function block_platform_admin_escalation_blocks_is_owner_injection(): void
    {
        $request = Request::create('/admin/staff', 'POST', [
            'name' => 'Escalation Attempt',
            'email' => 'escalation_'.uniqid().'@test.com',
            'is_owner' => true,
        ]);

        $middleware = new BlockPlatformAdminEscalation();
        $response = $middleware->handle($request, fn ($r) => response()->json(['ok' => true]));

        $this->assertSame(403, $response->getStatusCode());
    }

    /** @test */
    public function block_platform_admin_escalation_blocks_super_admin_injection(): void
    {
        $request = Request::create('/admin/staff', 'POST', [
            'name' => 'Escalation Attempt',
            'email' => 'escalation_'.uniqid().'@test.com',
            'super_admin' => true,
        ]);

        $middleware = new BlockPlatformAdminEscalation();
        $response = $middleware->handle($request, fn ($r) => response()->json(['ok' => true]));

        $this->assertSame(403, $response->getStatusCode());
    }

    /** @test */
    public function block_platform_admin_escalation_allows_safe_fields(): void
    {
        $request = Request::create('/admin/staff', 'POST', [
            'name' => 'Safe Update',
            'email' => 'safe_'.uniqid().'@test.com',
            'phone' => '1234567890',
        ]);

        $middleware = new BlockPlatformAdminEscalation();
        $response = $middleware->handle($request, fn ($r) => response()->json(['ok' => true]));

        $this->assertSame(200, $response->getStatusCode());
    }

    /** @test */
    public function platform_admin_bypasses_check_module_access(): void
    {
        $admin = $this->getExistingPlatformAdmin();

        $middleware = new \App\Http\Middleware\CheckModuleAccess();
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $admin);

        $response = $middleware->handle($request, fn ($r) => response()->json(['ok' => true]), 'education');

        $this->assertSame(200, $response->getStatusCode());
    }

    /** @test */
    public function platform_admin_bypasses_check_feature_access(): void
    {
        $admin = $this->getExistingPlatformAdmin();

        $middleware = new \App\Http\Middleware\CheckFeatureAccess();
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $admin);

        $response = $middleware->handle($request, fn ($r) => response()->json(['ok' => true]), 'education.attendance');

        $this->assertSame(200, $response->getStatusCode());
    }

    // ================================================================
    // PART 4 — CONCURRENCY FORENSIC
    // ================================================================

    /** @test */
    public function tenant_access_grants_use_db_transaction(): void
    {
        if (! Schema::hasTable('tenant_access_grants')) {
            $this->markTestSkipped('tenant_access_grants table not found.');
        }

        $reflection = new \ReflectionClass(\App\Http\Controllers\Admin\TenantAccessController::class);
        $addGrantMethod = $reflection->getMethod('addGrant');

        $source = file_get_contents($addGrantMethod->getFileName());
        $startLine = $addGrantMethod->getStartLine();
        $endLine = $addGrantMethod->getEndLine();
        $methodSource = implode("\n", array_slice(explode("\n", $source), $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('DB::transaction', $methodSource);
    }

    /** @test */
    public function tenant_access_denials_use_db_transaction(): void
    {
        if (! Schema::hasTable('tenant_access_denials')) {
            $this->markTestSkipped('tenant_access_denials table not found.');
        }

        $reflection = new \ReflectionClass(\App\Http\Controllers\Admin\TenantAccessController::class);
        $addDenialMethod = $reflection->getMethod('addDenial');

        $source = file_get_contents($addDenialMethod->getFileName());
        $startLine = $addDenialMethod->getStartLine();
        $endLine = $addDenialMethod->getEndLine();
        $methodSource = implode("\n", array_slice(explode("\n", $source), $startLine - 1, $endLine - $startLine + 1));

        $this->assertStringContainsString('DB::transaction', $methodSource);
    }

    /** @test */
    public function registration_flow_uses_lock_for_update(): void
    {
        $reflection = new \ReflectionClass(\App\Http\Controllers\Auth\RegistrationFlowController::class);

        $foundLock = false;

        if ($reflection->hasMethod('finalizeRegistration')) {
            $method = $reflection->getMethod('finalizeRegistration');
            $source = file_get_contents($method->getFileName());
            $startLine = $method->getStartLine();
            $endLine = $method->getEndLine();
            $methodSource = implode("\n", array_slice(explode("\n", $source), $startLine - 1, $endLine - $startLine + 1));

            if (str_contains($methodSource, 'lockForUpdate')) {
                $foundLock = true;
            }
        }

        $this->assertTrue($foundLock, 'Registration flow finalizeRegistration() should use lockForUpdate to prevent race conditions.');
    }

    /** @test */
    public function tenant_context_is_static_and_request_scoped(): void
    {
        TenantContext::set(999);
        $this->assertSame(999, TenantContext::id());
        $this->assertTrue(TenantContext::enabled());

        TenantContext::clear();
        $this->assertNull(TenantContext::id());
        $this->assertFalse(TenantContext::enabled());
    }

    /** @test */
    public function branch_context_is_static_and_request_scoped(): void
    {
        BranchContext::set(42);
        $this->assertSame(42, BranchContext::id());

        BranchContext::clear();
        $this->assertNull(BranchContext::id());
    }

    /** @test */
    public function tenant_context_clearance_between_requests(): void
    {
        TenantContext::set(100);
        $this->assertSame(100, TenantContext::id());

        TenantContext::clear();
        $this->assertNull(TenantContext::id());
        $this->assertFalse(TenantContext::enabled());
    }

    // ================================================================
    // CROSS-CUTTING — OBSERVABILITY AUDIT TRAIL
    // ================================================================

    /** @test */
    public function platform_audit_log_records_escalation_attempts(): void
    {
        if (! Schema::hasTable('platform_audit_logs')) {
            $this->markTestSkipped('platform_audit_logs table not found.');
        }

        $before = DB::table('platform_audit_logs')->where('section', 'security')->count();

        $request = Request::create('/admin/staff', 'POST', [
            'name' => 'Logged Attempt',
            'email' => 'logged_'.uniqid().'@test.com',
            'is_owner' => true,
        ]);

        $middleware = new BlockPlatformAdminEscalation();
        $middleware->handle($request, fn ($r) => response()->json(['ok' => true]));

        $after = DB::table('platform_audit_logs')->where('section', 'security')->count();

        $this->assertGreaterThan($before, $after, 'Escalation attempts should be logged to platform_audit_logs.');
    }
}
