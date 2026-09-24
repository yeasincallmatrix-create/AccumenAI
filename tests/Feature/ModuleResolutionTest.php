<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 4 — 10-layer module resolution verification against real
 * Phase 3 seeded data (industry_subcategories, subcategory_default_modules,
 * country_tax_modules) on monetix_test.
 *
 * Hard boundaries under test:
 *   Layer 7 — industry boundary (medical.* only for healthcare, ...)
 *   Layer 8 — country tax filter (BD → vat/tds only; cannot be bypassed
 *             even by a direct tenant override row)
 */
class ModuleResolutionTest extends TestCase
{
    use DatabaseTransactions;

    protected ModuleAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ModuleAccessService::class);
    }

    public function test_pharmacy_tenant_bd_gets_correct_modules(): void
    {
        $inst = $this->makeSavedInstitute('healthcare', 'pharmacy', 'BD', $this->packageId('basic'));

        $enabled = $this->enabledKeys($inst);

        // Layer 3 — sub-category mandatory/default (Phase 3 seeded rows)
        $this->assertContains('medical.pharmacy', $enabled);
        $this->assertContains('medical.billing', $enabled);
        $this->assertContains('medical.records', $enabled);

        // Layer 1 core + Layer 4 package (package 2 includes tds)
        $this->assertContains('vat', $enabled);
        $this->assertContains('tds', $enabled);

        // Layer 8 hard country boundary — BD allows vat/tds only
        $this->assertNotContains('gst', $enabled);
        $this->assertNotContains('sales_tax', $enabled);

        // Layer 7 hard industry boundary — no education/training leakage
        $this->assertEmpty(
            array_values(array_filter($enabled, fn ($m) => str_starts_with($m, 'education') || str_starts_with($m, 'training_center'))),
            'Healthcare tenant must not receive education/training modules'
        );
    }

    public function test_education_tenant_cannot_get_medical_modules(): void
    {
        $inst = $this->makeInstitute('education', 'school', 'BD');
        $enabled = $this->enabledKeys($inst);

        $medicalModules = array_values(array_filter($enabled, fn ($m) => str_starts_with($m, 'medical')));
        $this->assertEmpty($medicalModules, 'Education tenant should not have medical modules');

        // Layer 3 still applies for the education sub-category
        $this->assertContains('education.fees', $enabled);
        $this->assertContains('education.students', $enabled);
    }

    public function test_bd_tenant_cannot_get_gst_even_via_override(): void
    {
        $inst = $this->makeSavedInstitute('retail', 'grocery', 'BD', $this->packageId('basic'));
        $enabled = $this->enabledKeys($inst);

        $this->assertContains('vat', $enabled);
        $this->assertNotContains('gst', $enabled);
        $this->assertNotContains('sales_tax', $enabled);

        // Hard boundary: even a direct tenant override cannot enable a
        // country-disallowed tax module (Layer 8 runs AFTER Layer 5).
        DB::table('institute_module_overrides')->insert([
            ['institute_id' => $inst->id, 'module_key' => 'gst', 'enabled' => true, 'reason' => 'phase4 boundary probe', 'created_at' => now(), 'updated_at' => now()],
            ['institute_id' => $inst->id, 'module_key' => 'sales_tax', 'enabled' => true, 'reason' => 'phase4 boundary probe', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $this->service->flushCache($inst->id);

        $forced = $this->enabledKeys($inst);
        $this->assertNotContains('gst', $forced, 'Layer 8 must veto gst despite tenant override');
        $this->assertNotContains('sales_tax', $forced, 'Layer 8 must veto sales_tax despite tenant override');
    }

    public function test_training_center_gets_training_modules(): void
    {
        $inst = $this->makeInstitute('training_center', 'it_training', 'BD');
        $enabled = $this->enabledKeys($inst);

        $trainingModules = array_values(array_filter($enabled, fn ($m) => str_starts_with($m, 'training_center')));
        $this->assertNotEmpty($trainingModules, 'Training tenant must resolve training_center modules');

        // Phase 3 sub-category mapping keys (registry-validated in Phase 3)
        $this->assertContains('training_center.courses', $enabled);
        $this->assertContains('training_center.batches', $enabled);
        $this->assertContains('training_center.students', $enabled);
        $this->assertContains('training_center.certificates', $enabled);
        $this->assertContains('training_center.classes', $enabled);

        // Layer 7 — no medical leakage
        $this->assertEmpty(
            array_values(array_filter($enabled, fn ($m) => str_starts_with($m, 'medical'))),
            'Training tenant must not receive medical modules'
        );
    }

    public function test_cache_flushes_correctly(): void
    {
        $inst = Institute::first();
        if (! $inst) {
            $this->markTestSkipped('No institute');
        }

        $before = $this->service->getEnabledModules($inst);
        $this->service->flushCache($inst->id);
        $after = $this->service->getEnabledModules($inst);

        $this->assertEquals(count($before), count($after));
        $this->assertEquals($before, $after, 'Re-resolution after flush must be identical');
    }

    // ─── Phase 6 — Layer 6.5: Super Admin override wiring ───

    public function test_super_admin_override_enables_industry_blocked_module(): void
    {
        $inst = $this->makeSavedInstitute('education', 'school', 'BD', $this->packageId('basic'));
        $this->assertNotContains('medical.pharmacy', $this->enabledKeys($inst), 'precondition');

        $this->insertSuperAdminOverride($inst->id, 'medical.pharmacy', 'industry');

        $enabled = $this->enabledKeys($inst);
        $this->assertContains('medical.pharmacy', $enabled, 'Layer 6.5 + Layer 7 bypass must apply');
    }

    public function test_super_admin_override_enables_country_blocked_tax_module(): void
    {
        // US fallback allows sales_tax only — vat is a Layer 8 block.
        $inst = $this->makeSavedInstitute('retail', 'grocery', 'US', $this->packageId('basic'));
        $this->assertNotContains('vat', $this->enabledKeys($inst), 'precondition');

        $this->insertSuperAdminOverride($inst->id, 'vat', 'country');

        $this->assertContains('vat', $this->enabledKeys($inst), 'Layer 6.5 + Layer 8 bypass must apply');
    }

    public function test_expired_super_admin_override_not_applied(): void
    {
        $inst = $this->makeSavedInstitute('education', 'school', 'BD', $this->packageId('basic'));
        $this->insertSuperAdminOverride($inst->id, 'medical.pharmacy', 'industry', now()->subDay());

        $this->assertNotContains('medical.pharmacy', $this->enabledKeys($inst), 'expired row must be ignored');
    }

    public function test_super_admin_override_does_not_affect_other_tenant(): void
    {
        $a = $this->makeSavedInstitute('education', 'school', 'BD', $this->packageId('basic'));
        $b = $this->makeSavedInstitute('education', 'school', 'BD', $this->packageId('basic'));

        $this->insertSuperAdminOverride($a->id, 'medical.pharmacy', 'industry');

        $this->assertContains('medical.pharmacy', $this->enabledKeys($a));
        $this->assertNotContains('medical.pharmacy', $this->enabledKeys($b), 'tenant isolation');
    }

    public function test_resolve_enabled_with_reasons_matches_with_override(): void
    {
        $inst = $this->makeSavedInstitute('education', 'school', 'BD', $this->packageId('basic'));
        $this->insertSuperAdminOverride($inst->id, 'medical.pharmacy', 'industry');

        $map = array_keys(array_filter($this->service->resolveEnabled($inst)));
        $withReasons = $this->service->resolveEnabledWithReasons($inst);

        $this->assertContains('medical.pharmacy', $map);
        $this->assertContains('medical.pharmacy', array_keys(array_filter($withReasons['map'])));
        $this->assertSame(
            array_keys(array_filter($this->service->resolveEnabled($inst))),
            array_keys(array_filter($withReasons['map'])),
            'resolveEnabledWithReasons must stay in lockstep with resolveEnabled'
        );
    }

    public function test_super_admin_override_returns_full_registry_map(): void
    {
        $inst = $this->makeSavedInstitute('education', 'school', 'BD', $this->packageId('basic'));
        $this->insertSuperAdminOverride($inst->id, 'medical.pharmacy', 'industry');

        $result = $this->service->resolveEnabled($inst);
        $registryCount = DB::table('module_registry')->count();

        $this->assertCount($registryCount, $result, 'every registry key must get a boolean state');
        $this->assertArrayHasKey('crm', $result);
        $this->assertTrue($result['crm'], 'package modules unaffected by override');
    }

    public function test_super_admin_override_beats_tenant_disable_override(): void
    {
        $inst = $this->makeSavedInstitute('education', 'school', 'BD', $this->packageId('basic'));
        $this->assertTrue(in_array('crm', $this->enabledKeys($inst)), 'precondition: crm enabled');

        // Tenant disables crm, Super Admin emergency re-adds it (Layer 6.5
        // runs AFTER the tenant override layer).
        DB::table('institute_module_overrides')->insert([
            'institute_id' => $inst->id,
            'module_key' => 'crm',
            'enabled' => false,
            'reason' => 'phase6 probe',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->insertSuperAdminOverride($inst->id, 'crm', 'industry');

        $this->assertContains('crm', $this->enabledKeys($inst), 'super admin wins over tenant disable');
    }

    public function test_hard_boundaries_still_hold_without_super_admin_override(): void
    {
        $edu = $this->makeSavedInstitute('education', 'school', 'BD', $this->packageId('basic'));
        $us = $this->makeSavedInstitute('retail', 'grocery', 'US', $this->packageId('basic'));

        $eduKeys = $this->enabledKeys($edu);
        $usKeys = $this->enabledKeys($us);

        $this->assertEmpty(
            array_values(array_filter($eduKeys, fn ($m) => str_starts_with($m, 'medical'))),
            'Layer 7 holds when no override exists'
        );
        $this->assertNotContains('vat', $usKeys, 'Layer 8 holds when no override exists');
    }

    public function test_override_for_unknown_module_key_does_not_break_resolution(): void
    {
        $inst = $this->makeSavedInstitute('education', 'school', 'BD', $this->packageId('basic'));
        $this->insertSuperAdminOverride($inst->id, 'ghost.module', 'industry');

        $result = $this->service->resolveEnabled($inst);
        $registryCount = DB::table('module_registry')->count();

        $this->assertCount($registryCount, $result, 'non-registry override key must be ignored safely');
        $this->assertFalse($result['ghost.module'] ?? false);
    }

    public function test_parent_stays_disabled_while_overridden_child_enabled(): void
    {
        $inst = $this->makeSavedInstitute('education', 'school', 'BD', $this->packageId('basic'));
        $this->insertSuperAdminOverride($inst->id, 'medical.pharmacy', 'industry');

        $result = $this->service->resolveEnabled($inst);

        $this->assertTrue($result['medical.pharmacy'], 'overridden child module is ON');
        $this->assertFalse($result['medical'], 'parent stays OFF — bypass is per-module, not per-tree');
    }

    private function insertSuperAdminOverride(int $instituteId, string $moduleKey, string $layer, $expiresAt = null): void
    {
        DB::table('super_admin_overrides')->insert([
            'institute_id' => $instituteId,
            'module_key' => $moduleKey,
            'override_layer' => $layer,
            'reason' => 'Phase 6 resolution verification override',
            'approved_by' => null,
            'two_factor_verified' => true,
            'started_at' => now(),
            'expires_at' => $expiresAt ?? now()->addDays(7),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->service->flushCache($instituteId);
    }

    /**
     * resolveEnabled() returns module_key => bool — extract enabled keys.
     */
    protected function enabledKeys(Institute $inst): array
    {
        return array_keys(array_filter($this->service->resolveEnabled($inst)));
    }

    /**
     * Unsaved institute — resolution reads attributes only (no override /
     * entitlement / subscription rows exist for a null id).
     */
    protected function makeInstitute(string $industry, string $subcategory, string $country): Institute
    {
        $inst = new Institute();
        $inst->industry = $industry;
        $inst->subcategory_key = $subcategory;
        $inst->country_code = $country;
        $inst->status = 'active';

        return $inst;
    }

    /**
     * Package ids differ per database (accumen_ai: 1-4, monetix_test:
     * 900-903) — resolve by slug so the test runs on both.
     */
    protected function packageId(string $slug): int
    {
        $id = DB::table('subscription_packages')->where('slug', $slug)->value('id');
        $this->assertNotNull($id, "Package {$slug} missing");

        return (int) $id;
    }

    /**
     * Persisted institute (+ active subscription so Layer 4 resolves the
     * given package instead of falling back to free). withoutEvents()
     * bypasses the B17 auto-assign-PREMIUM creating hook so package_id
     * stays as given. All rows roll back with DatabaseTransactions.
     */
    protected function makeSavedInstitute(string $industry, string $subcategory, string $country, int $packageId): Institute
    {
        $suffix = substr(uniqid(), -8);

        $inst = Institute::withoutEvents(function () use ($industry, $subcategory, $country, $packageId, $suffix) {
            $inst = Institute::create([
                'name' => "Phase4 {$industry} {$suffix}",
                'slug' => "phase4-{$industry}-{$suffix}",
                'status' => 'active',
                'country' => 'Bangladesh',
                'industry' => $industry,
                'package_id' => $packageId,
            ]);
            // Not in $fillable — assign directly so mass assignment
            // cannot silently drop the Layer 3 / Layer 8 attributes.
            $inst->subcategory_key = $subcategory;
            $inst->country_code = $country;
            $inst->save();

            return $inst;
        });

        DB::table('institute_subscriptions')->insert([
            'institute_id' => $inst->id,
            'package_id' => $packageId,
            'billing_cycle' => 'yearly',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
        ]);

        return $inst->fresh();
    }
}
