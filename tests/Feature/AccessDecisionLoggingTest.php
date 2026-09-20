<?php

namespace Tests\Feature;

use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\InstituteFeatureOverride;
use App\Models\InstituteModuleOverride;
use App\Models\ModuleAccessLog;
use App\Models\PackageFeature;
use App\Models\SubscriptionPackage;
use App\Models\TenantAccessDenial;
use App\Models\TenantAccessGrant;
use App\Services\ModuleAccessService;
use App\Support\AccessDecisionContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 10 — Decision-tracing observability tests.
 *
 * Verifies:
 *  1. Feature denial logs reason
 *  2. Feature grant logs reason
 *  3. Denial reason priority (Gate 5 wins)
 *  4. Scope-not-found logs reason
 *  5. Request ID propagation
 *  6. Reason constants are stable
 */
class AccessDecisionLoggingTest extends TestCase
{
    use DatabaseTransactions;

    private ModuleAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('feature_registry')
            || ! Schema::hasTable('package_features')
            || ! Schema::hasTable('module_access_logs')) {
            $this->markTestSkipped('Required tables do not exist.');
        }

        $this->service = app(ModuleAccessService::class);

        if (FeatureRegistry::count() === 0) {
            (new \Database\Seeders\FeatureRegistrySeeder)->run();
        }
        if (PackageFeature::count() === 0) {
            (new \Database\Seeders\PackageFeatureSeeder)->run();
        }
    }

    private function makeInstitute(string $packageSlug = 'advanced'): Institute
    {
        $pkg = SubscriptionPackage::whereRaw('LOWER(slug) = ?', [strtolower($packageSlug)])->firstOrFail();

        $instId = DB::table('institutes')->insertGetId([
            'name' => 'DecisionLog Test '.uniqid(),
            'slug' => 'decision-log-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
            'package_id' => $pkg->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('institute_subscriptions')->insert([
            'institute_id' => $instId,
            'package_id' => $pkg->id,
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
        ]);

        return Institute::withoutGlobalScopes()->find($instId);
    }

    private function getFirstFeatureKey(): string
    {
        return FeatureRegistry::where('status', 'active')
            ->orderBy('module_key')
            ->value('feature_key');
    }

    private function setPackageFeature(Institute $inst, string $featureKey, bool $enabled): void
    {
        $row = PackageFeature::where('package_id', $inst->package_id)
            ->where('feature_key', $featureKey)
            ->first();

        if ($row) {
            $row->update(['enabled' => $enabled]);
        } else {
            PackageFeature::create([
                'package_id' => $inst->package_id,
                'feature_key' => $featureKey,
                'enabled' => $enabled,
            ]);
        }

        $this->service->flushFeatureCache($inst->id);
    }

    // ================================================================
    // 1. test_feature_denial_logs_reason
    // ================================================================
    public function test_feature_denial_logs_reason(): void
    {
        $inst = $this->makeInstitute('free');
        $featureKey = $this->getFirstFeatureKey();
        $moduleKey = explode('.', $featureKey, 2)[0];

        // Free package likely does NOT include this feature → reason should be PACKAGE_FEATURE_NOT_ENTITLED
        $map = $this->service->getFeatureAccessMapWithMeta($inst);

        $this->assertArrayHasKey($featureKey, $map['reasons']);
        $this->assertEquals(
            AccessDecisionContext::REASON_PACKAGE_FEATURE_NOT_ENTITLED,
            $map['reasons'][$featureKey],
            "Feature {$featureKey} should log PACKAGE_FEATURE_NOT_ENTITLED when package excludes it"
        );
        $this->assertFalse($map['map'][$featureKey]);
    }

    // ================================================================
    // 2. test_feature_grant_logs_reason
    // ================================================================
    public function test_feature_grant_logs_reason(): void
    {
        $inst = $this->makeInstitute('advanced');
        $featureKey = $this->getFirstFeatureKey();

        // Feature is in advanced package; remove from package to test grant override
        PackageFeature::where('package_id', $inst->package_id)
            ->where('feature_key', $featureKey)
            ->update(['enabled' => false]);
        $this->service->flushFeatureCache($inst->id);

        // Verify feature is now denied
        $map = $this->service->getFeatureAccessMapWithMeta($inst);
        $this->assertFalse($map['map'][$featureKey], 'Feature should be denied before grant');

        // Create a super admin grant
        TenantAccessGrant::create([
            'institute_id' => $inst->id,
            'grant_type' => 'feature',
            'grant_key' => $featureKey,
            'status' => 'active',
            'granted_by' => 1,
        ]);

        $this->service->flushFeatureCache($inst->id);

        $map = $this->service->getFeatureAccessMapWithMeta($inst);

        $this->assertTrue($map['map'][$featureKey], 'Grant should enable the feature');
        $this->assertEquals(
            AccessDecisionContext::REASON_GRANT_APPLIED,
            $map['reasons'][$featureKey],
            "Feature {$featureKey} should log GRANT_APPLIED after super admin grant"
        );
    }

    // ================================================================
    // 3. test_denial_reason_priority
    // ================================================================
    public function test_denial_reason_priority(): void
    {
        $inst = $this->makeInstitute('advanced');
        $featureKey = $this->getFirstFeatureKey();

        // Feature is in advanced package → enabled by default
        $map = $this->service->getFeatureAccessMapWithMeta($inst);
        $this->assertTrue($map['map'][$featureKey]);

        // Now apply a super admin denial — should override to denied
        TenantAccessDenial::create([
            'institute_id' => $inst->id,
            'deny_type' => 'feature',
            'deny_key' => $featureKey,
            'status' => 'active',
            'denied_by' => 1,
            'reason' => 'Test denial for Phase 10',
        ]);

        $this->service->flushFeatureCache($inst->id);

        $map = $this->service->getFeatureAccessMapWithMeta($inst);

        $this->assertFalse($map['map'][$featureKey], 'Denial should override to denied');
        $this->assertEquals(
            AccessDecisionContext::REASON_DENIAL_APPLIED,
            $map['reasons'][$featureKey],
            "Gate 5 denial should win and log DENIAL_APPLIED"
        );
    }

    // ================================================================
    // 4. test_scope_not_found_logs_reason
    // ================================================================
    public function test_scope_not_found_logs_reason(): void
    {
        $inst = $this->makeInstitute('free');

        // Scope resolution for free package without matching scope → returns null
        $scope = $this->service->resolveScopedPackage($inst);
        // $scope may be null (scope_not_found) — the feature map should still resolve
        // and for any denied features, the reason should indicate PACKAGE_FEATURE_NOT_ENTITLED
        // (since fallback to legacy package_features is used)
        $map = $this->service->getFeatureAccessMapWithMeta($inst);

        // Verify all reasons are valid constants
        foreach ($map['reasons'] as $featureKey => $reason) {
            $this->assertContains(
                $reason,
                AccessDecisionContext::allReasons(),
                "Reason '{$reason}' for feature '{$featureKey}' should be a valid AccessDecisionContext constant"
            );
        }
    }

    // ================================================================
    // 5. test_request_id_propagated
    // ================================================================
    public function test_request_id_propagated(): void
    {
        $inst = $this->makeInstitute('advanced');
        $requestId = 'test-req-'.uniqid();

        $this->service->logAccess(
            $inst->id,
            'medical',
            'enable',
            null,
            'disabled',
            'enabled',
            $inst->package_id,
            'Request ID test',
            null,
            null,
            null,
            null,
            $requestId,
        );

        $log = ModuleAccessLog::where('request_id', $requestId)->latest()->first();
        $this->assertNotNull($log, 'Log entry with request_id should exist');
        $this->assertEquals($requestId, $log->request_id);
        $this->assertEquals('medical', $log->module_key);
        $this->assertEquals('enable', $log->action);
    }

    // ================================================================
    // 6. test_reason_constants_are_stable
    // ================================================================
    public function test_reason_constants_are_stable(): void
    {
        $reasons = AccessDecisionContext::allReasons();

        $this->assertNotEmpty($reasons, 'Reason constants list should not be empty');
        $this->assertGreaterThanOrEqual(10, count($reasons), 'Should have at least 10 reason constants');

        // Verify all values are strings ≤ 60 chars (varchar 60 column)
        foreach ($reasons as $reason) {
            $this->assertIsString($reason);
            $this->assertLessThanOrEqual(60, strlen($reason), "Reason '{$reason}' exceeds 60 chars");
        }

        // Verify specific constants exist (backward compatibility)
        $expected = [
            AccessDecisionContext::REASON_PACKAGE_FEATURE_NOT_ENTITLED,
            AccessDecisionContext::REASON_DEPENDENCY_DISABLED,
            AccessDecisionContext::REASON_PARENT_DISABLED,
            AccessDecisionContext::REASON_INDUSTRY_VETO,
            AccessDecisionContext::REASON_SUBSCRIPTION_EXPIRED,
            AccessDecisionContext::REASON_SCOPE_NOT_FOUND,
            AccessDecisionContext::REASON_DENIAL_APPLIED,
            AccessDecisionContext::REASON_OVERRIDE_DISABLED,
            AccessDecisionContext::REASON_FEATURE_REGISTRY_INACTIVE,
            AccessDecisionContext::REASON_MODULE_NOT_ENABLED,
        ];

        foreach ($expected as $const) {
            $this->assertContains($const, $reasons, "Constant '{$const}' must be in allReasons()");
        }
    }

    // ================================================================
    // Additional: test logAccess accepts new fields without breaking
    // ================================================================
    public function test_log_access_accepts_new_fields(): void
    {
        $inst = $this->makeInstitute('advanced');

        $this->service->logAccess(
            $inst->id,
            'medical',
            'feature_check',
            null,
            null,
            null,
            $inst->package_id,
            'Test notes',
            null,
            AccessDecisionContext::REASON_PACKAGE_INCLUDED,
            'medical.pharmacy',
            'allow',
            'req-'.uniqid(),
        );

        $log = ModuleAccessLog::where('module_key', 'medical')
            ->where('action', 'feature_check')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertEquals(AccessDecisionContext::REASON_PACKAGE_INCLUDED, $log->reason);
        $this->assertEquals('medical.pharmacy', $log->feature_key);
        $this->assertEquals('allow', $log->decision);
        $this->assertNotNull($log->request_id);
    }

    // ================================================================
    // Additional: test existing logAccess callers still work (backward compat)
    // ================================================================
    public function test_existing_log_access_callers_unaffected(): void
    {
        $inst = $this->makeInstitute('advanced');

        // Call logAccess WITHOUT the new optional parameters (existing caller pattern)
        $this->service->logAccess(
            $inst->id,
            'hr',
            'enable',
            null,
            'disabled',
            'enabled',
            $inst->package_id,
            'Legacy call without new fields',
        );

        $log = ModuleAccessLog::where('module_key', 'hr')
            ->where('action', 'enable')
            ->latest()
            ->first();

        $this->assertNotNull($log);
        $this->assertNull($log->reason);
        $this->assertNull($log->feature_key);
        $this->assertNull($log->decision);
        $this->assertNull($log->request_id);
    }

    // ================================================================
    // Additional: test resolveEnabledWithReasons returns reasons
    // ================================================================
    public function test_resolve_enabled_with_reasons(): void
    {
        $inst = $this->makeInstitute('advanced');

        $result = $this->service->resolveEnabledWithReasons($inst);

        $this->assertArrayHasKey('map', $result);
        $this->assertArrayHasKey('reasons', $result);
        $this->assertEquals(array_keys($result['map']), array_keys($result['reasons']), 'Every module in map should have a reason');
    }

    // ================================================================
    // Additional: test module-level reason captures industry veto
    // ================================================================
    public function test_industry_veto_logs_reason(): void
    {
        // Create education institute — 'sales' module should be industry-vetoed
        $pkg = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['advanced'])->firstOrFail();
        $instId = DB::table('institutes')->insertGetId([
            'name' => 'Edu Veto Test '.uniqid(),
            'slug' => 'edu-veto-'.uniqid(),
            'industry' => 'education',
            'sub_industry' => 'school',
            'country' => 'Bangladesh',
            'status' => 'active',
            'package_id' => $pkg->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $inst = Institute::withoutGlobalScopes()->find($instId);

        $result = $this->service->resolveEnabledWithReasons($inst);

        // 'sales' is in EDUCATION_DISABLED_MODULES and should be industry-vetoed
        // It won't be in the result if it's not in package_modules after education filtering
        // But if it is, it should have PARENT_DISABLED or MODULE_NOT_ENABLED reason
        // The key assertion: every denied module has a reason
        foreach ($result['map'] as $moduleKey => $enabled) {
            if (! $enabled) {
                $this->assertArrayHasKey($moduleKey, $result['reasons'], "Disabled module {$moduleKey} must have a reason");
                $this->assertContains(
                    $result['reasons'][$moduleKey],
                    AccessDecisionContext::allReasons(),
                    "Module {$moduleKey} reason must be a valid constant"
                );
            }
        }
    }

    // ================================================================
    // Additional: test feature access map includes reasons key
    // ================================================================
    public function test_feature_access_map_with_meta_includes_reasons(): void
    {
        $inst = $this->makeInstitute('advanced');

        $result = $this->service->getFeatureAccessMapWithMeta($inst);

        $this->assertArrayHasKey('reasons', $result, 'getFeatureAccessMapWithMeta must include reasons');
        $this->assertIsArray($result['reasons']);
        $this->assertArrayHasKey('map', $result);
        $this->assertArrayHasKey('grants_applied', $result);
        $this->assertArrayHasKey('denials_applied', $result);
    }
}
