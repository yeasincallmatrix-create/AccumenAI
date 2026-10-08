<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Module Config — Plan tiers mode (/admin/module-config?mode=plans).
 *
 * Covers tier discovery (FREE excluded), the tier-nesting invariant
 * (Enterprise ⊇ Growth ⊇ Starter enforced on save), core forcing,
 * industry-disabled forcing, and the update-plans redirect.
 */
class ModuleConfigPlanModeTest extends TestCase
{
    use DatabaseTransactions;

    private int $starterId;

    private int $growthId;

    private int $enterpriseId;

    protected function setUp(): void
    {
        parent::setUp();

        // Resolve tier packages by slug and ensure the healthcare mapping
        // exists (rolled back with the transaction after each test).
        $ids = [];
        foreach (['medical_starter' => 0, 'medical_growth' => 1, 'medical_enterprise' => 2] as $slug => $sort) {
            $package = SubscriptionPackage::where('slug', $slug)->firstOrFail();
            $ids[$slug] = $package->id;
            DB::table('package_industries')->updateOrInsert(
                ['package_id' => $package->id, 'industry_key' => 'healthcare'],
                ['is_active' => true, 'sort_order' => $sort, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $this->starterId = $ids['medical_starter'];
        $this->growthId = $ids['medical_growth'];
        $this->enterpriseId = $ids['medical_enterprise'];
    }

    private function platformAdmin(): PlatformAdmin
    {
        TenantContext::clear();

        return PlatformAdmin::firstOrReuseForTests([
            'email' => 'platform-'.uniqid().'@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function savePlans(array $plans, string $industry = 'healthcare', string $subcategory = 'hospital')
    {
        return $this->actingAs($this->platformAdmin(), 'platform_admin')->put(
            route('admin.module-config.update-plans'),
            [
                'industry' => $industry,
                'subcategory' => $subcategory,
                'plans' => $plans,
            ]
        );
    }

    private function tierOn(string $industry, int $packageId, string $moduleKey): bool
    {
        return (bool) DB::table('package_industry_modules')
            ->where('package_id', $packageId)
            ->where('industry_key', $industry)
            ->where('module_key', $moduleKey)
            ->where('enabled', true)
            ->exists();
    }

    public function test_unauthenticated_is_redirected_to_admin_login(): void
    {
        $this->put(route('admin.module-config.update-plans'), [])->assertRedirect(route('admin.login'));
    }

    public function test_plans_mode_shows_tier_columns_without_free(): void
    {
        $response = $this->actingAs($this->platformAdmin(), 'platform_admin')
            ->get(route('admin.module-config.index', [
                'industry' => 'healthcare',
                'subcategory' => 'hospital',
                'mode' => 'plans',
            ]));

        $response->assertOk()
            ->assertSee('Plan tiers')
            ->assertSee('Medical Starter')
            ->assertSee('Medical Growth')
            ->assertSee('Medical Enterprise');

        $freeId = SubscriptionPackage::where('slug', 'free')->value('id');
        $response->assertDontSee('name="plans['.$freeId.'][]"', false);
    }

    public function test_starter_on_cascades_up_to_all_tiers(): void
    {
        $this->savePlans([$this->starterId => ['sales.orders']])
            ->assertRedirect(route('admin.module-config.index', [
                'industry' => 'healthcare',
                'subcategory' => 'hospital',
                'mode' => 'plans',
            ]));

        $this->assertTrue($this->tierOn('healthcare', $this->starterId, 'sales.orders'));
        $this->assertTrue($this->tierOn('healthcare', $this->growthId, 'sales.orders'));
        $this->assertTrue($this->tierOn('healthcare', $this->enterpriseId, 'sales.orders'));
    }

    public function test_enterprise_only_stays_enterprise_only(): void
    {
        $this->savePlans([$this->enterpriseId => ['sales.returns']]);

        $this->assertFalse($this->tierOn('healthcare', $this->starterId, 'sales.returns'));
        $this->assertFalse($this->tierOn('healthcare', $this->growthId, 'sales.returns'));
        $this->assertTrue($this->tierOn('healthcare', $this->enterpriseId, 'sales.returns'));
    }

    public function test_core_modules_forced_on_in_every_tier(): void
    {
        // crm submitted nowhere — core forcing must still switch it on.
        $this->savePlans([$this->starterId => ['sales.orders']]);

        foreach ([$this->starterId, $this->growthId, $this->enterpriseId] as $packageId) {
            $this->assertTrue($this->tierOn('healthcare', $packageId, 'crm'));
            $this->assertDatabaseHas('package_industry_modules', [
                'package_id' => $packageId,
                'industry_key' => 'healthcare',
                'module_key' => 'crm',
                'category' => 'mandatory',
            ]);
        }
    }

    public function test_industry_disabled_modules_forced_off_in_every_tier(): void
    {
        $this->savePlans([$this->starterId => ['education'], $this->growthId => ['education'], $this->enterpriseId => ['education']]);

        foreach ([$this->starterId, $this->growthId, $this->enterpriseId] as $packageId) {
            $this->assertFalse($this->tierOn('healthcare', $packageId, 'education'));
        }
    }

    public function test_other_industry_subtree_forced_off_in_every_tier(): void
    {
        // Real Estate (root + child) submitted ON must still end up OFF —
        // a disabled root disables its whole subtree.
        $this->savePlans([
            $this->starterId => ['real_estate', 'real_estate.properties'],
            $this->growthId => ['real_estate.properties'],
            $this->enterpriseId => ['real_estate'],
        ]);

        foreach ([$this->starterId, $this->growthId, $this->enterpriseId] as $packageId) {
            $this->assertFalse($this->tierOn('healthcare', $packageId, 'real_estate'));
            $this->assertFalse($this->tierOn('healthcare', $packageId, 'real_estate.properties'));
            $this->assertDatabaseHas('package_industry_modules', [
                'package_id' => $packageId,
                'industry_key' => 'healthcare',
                'module_key' => 'real_estate.properties',
                'category' => 'hidden',
            ]);
        }
    }

    public function test_healthcare_tenant_incompatible_with_other_industry_roots(): void
    {
        $service = app(ModuleAccessService::class);
        $institute = new Institute(['industry' => 'healthcare']);

        foreach (['real_estate', 'real_estate.properties', 'restaurant', 'manufacturing.bom', 'retail'] as $key) {
            $this->assertFalse($service->isIndustryCompatible($institute, $key), $key);
        }

        $this->assertTrue($service->isIndustryCompatible($institute, 'medical.opd'));
        $this->assertTrue($service->isIndustryCompatible($institute, 'crm'));
    }
}
