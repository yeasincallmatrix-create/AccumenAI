<?php

namespace Tests\Feature;

use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\InstituteFeatureOverride;
use App\Models\InstituteModuleEntitlement;
use App\Models\PackageFeature;
use App\Models\PackageModule;
use App\Models\PackageScope;
use App\Models\PackageScopedFeature;
use App\Models\PackageScopedModule;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use App\Models\TenantAccessDenial;
use App\Models\TenantAccessGrant;
use App\Services\EffectiveEntitlementResolver;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 8 — Effective Resolution Engine (contract §9 verification).
 *
 * The resolver is a pure facade over ModuleAccessService: every test
 * below asserts resolver output AND its equality with the corresponding
 * service call (behavior-preserving, no new behavior).
 *
 * Fixture rules (deterministic, no Faker):
 *   - Institutes are created with Institute::withoutEvents() so the
 *     AppServiceProvider auto-hooks (syncIndustryModule, premium
 *     auto-assign, auto-scope) never fire; every row the test needs is
 *     created explicitly in-test.
 *   - Module-base tests always get an ACTIVE institute_subscriptions row
 *     (resolvePackageModules() falls back to FREE without one).
 *   - All rows roll back via DatabaseTransactions; created institute ids
 *     are tracked and their caches flushed in tearDown().
 */
class EffectiveResolutionEngineTest extends TestCase
{
    use DatabaseTransactions;

    private ModuleAccessService $service;

    private EffectiveEntitlementResolver $resolver;

    /** @var array<int, int> */
    private array $madeInstituteIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('package_scopes')
            || ! Schema::hasTable('tenant_access_grants')
            || ! Schema::hasTable('tenant_access_denials')
            || ! Schema::hasTable('feature_registry')
            || ! Schema::hasTable('package_features')
            || ! Schema::hasTable('package_modules')
            || ! Schema::hasTable('institute_subscriptions')) {
            $this->markTestSkipped('Required tables do not exist.');
        }

        $this->service = app(ModuleAccessService::class);
        $this->resolver = app(EffectiveEntitlementResolver::class);

        if (FeatureRegistry::count() === 0) {
            (new \Database\Seeders\FeatureRegistrySeeder)->run();
        }
        if (PackageFeature::count() === 0) {
            (new \Database\Seeders\PackageFeatureSeeder)->run();
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->madeInstituteIds as $id) {
            $this->service->flushCache($id);
            $this->service->flushFeatureCache($id);
        }
        Cache::flush();
        parent::tearDown();
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function package(string $slug): SubscriptionPackage
    {
        return SubscriptionPackage::whereRaw('LOWER(slug) = ?', [strtolower($slug)])->firstOrFail();
    }

    private function freshPackage(): SubscriptionPackage
    {
        return SubscriptionPackage::create([
            'slug' => 'effres-'.uniqid(),
            'name' => 'EffRes '.uniqid(),
            'price_monthly' => 0,
            'price_yearly' => 0,
            'status' => 'active',
            'is_default' => false,
        ]);
    }

    private function localeIds(): array
    {
        $countryId = DB::table('countries')->where('id', 21)->exists()
            ? 21
            : (int) DB::table('countries')->orderBy('id')->value('id');
        $industryId = (int) DB::table('industries')->orderBy('id')->value('id');
        $subId = DB::table('sub_industries')->where('industry_id', $industryId)->orderBy('id')->value('id')
            ?? DB::table('sub_industries')->orderBy('id')->value('id');

        return [(int) $countryId, (int) $industryId, (int) $subId];
    }

    /**
     * @param array{industry?: string, country_id?: ?int, industry_id?: ?int, sub_industry_id?: ?int, package_null?: bool} $opts
     */
    private function makeInstitute(SubscriptionPackage $pkg, array $opts = []): Institute
    {
        [$countryId, $industryId, $subId] = $this->localeIds();

        $inst = Institute::withoutEvents(fn () => Institute::create([
            'name' => 'EffRes '.uniqid(),
            'slug' => 'effres-'.uniqid(),
            'status' => 'active',
            'package_id' => ($opts['package_null'] ?? false) ? null : $pkg->id,
            'industry' => $opts['industry'] ?? 'healthcare',
            'country' => 'Bangladesh',
            'country_id' => $opts['country_id'] ?? $countryId,
            'industry_id' => array_key_exists('industry_id', $opts) ? $opts['industry_id'] : $industryId,
            'sub_industry_id' => array_key_exists('sub_industry_id', $opts) ? $opts['sub_industry_id'] : $subId,
        ]));

        if (! ($opts['package_null'] ?? false)) {
            $this->addSubscription($inst->id, $pkg->id, 'active', now()->addYear()->toDateString());
        }

        $this->madeInstituteIds[] = $inst->id;

        return $inst->fresh();
    }

    private function addSubscription(int $instituteId, int $packageId, string $status, string $endDate): void
    {
        // NOTE: institute_subscriptions has no created_at/updated_at
        // columns — insert only the real columns.
        DB::table('institute_subscriptions')->insert([
            'institute_id' => $instituteId,
            'package_id' => $packageId,
            'status' => $status,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => $endDate,
        ]);
    }

    private function admin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'first_name' => 'EffRes',
            'last_name' => 'Admin',
            'email' => 'effres-admin-'.uniqid().'@example.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function grant(Institute $inst, string $type, string $key, array $extra = []): TenantAccessGrant
    {
        return TenantAccessGrant::create(array_merge([
            'institute_id' => $inst->id,
            'grant_type' => $type,
            'grant_key' => $key,
            'granted_by' => $this->admin()->id,
            'status' => 'active',
        ], $extra));
    }

    private function deny(Institute $inst, string $type, string $key, array $extra = []): TenantAccessDenial
    {
        return TenantAccessDenial::create(array_merge([
            'institute_id' => $inst->id,
            'deny_type' => $type,
            'deny_key' => $key,
            'denied_by' => $this->admin()->id,
            'reason' => 'EffRes test denial',
            'status' => 'active',
        ], $extra));
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

    private function globalScope(SubscriptionPackage $pkg): PackageScope
    {
        return PackageScope::create([
            'package_id' => $pkg->id,
            'country_id' => null,
            'industry_id' => null,
            'sub_industry_id' => null,
            'inherit_from_parent' => false,
            'status' => 'active',
        ]);
    }

    // -----------------------------------------------------------------
    // A) Module resolution (12)
    // -----------------------------------------------------------------

    public function test_a01_package_base_only(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));
        $modules = $this->resolver->resolveModules($inst);

        $this->assertTrue($modules['finance']);
        $this->assertTrue($modules['sales']);
        $this->assertFalse($modules['hr']);
        $this->assertSame($this->service->resolveEnabled($inst), $modules);
    }

    public function test_a02_override_enable_wins_over_base(): void
    {
        $inst = $this->makeInstitute($this->package('basic'));
        $this->assertFalse($this->resolver->isModuleEnabled($inst, 'hr'));

        $this->service->enableModule($inst, 'hr', null, 'EffRes');
        $this->assertTrue($this->resolver->isModuleEnabled($inst, 'hr'));
        $this->assertSame(
            $this->service->resolveEnabled($inst),
            $this->resolver->resolveModules($inst)
        );
    }

    public function test_a03_override_disable_wins_over_base(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));
        $this->assertTrue($this->resolver->isModuleEnabled($inst, 'sales'));

        $this->service->disableModule($inst, 'sales', null, 'EffRes');
        $this->assertFalse($this->resolver->isModuleEnabled($inst, 'sales'));
    }

    public function test_a04_grant_wins_over_override(): void
    {
        $inst = $this->makeInstitute($this->package('basic'));
        $this->service->disableModule($inst, 'finance', null, 'EffRes');
        $this->assertFalse($this->resolver->isModuleEnabled($inst, 'finance'));

        $this->service->grantModule($inst, 'finance', ['is_grant' => true], $this->admin()->id);
        $this->assertTrue($this->resolver->isModuleEnabled($inst, 'finance'));
    }

    public function test_a05_entitlement_denial_wins_over_grant(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));
        $this->service->grantModule($inst, 'finance', ['is_grant' => true], $this->admin()->id);
        $this->assertTrue($this->resolver->isModuleEnabled($inst, 'finance'));

        $this->service->grantModule($inst, 'finance', ['is_grant' => false], $this->admin()->id);
        $this->assertFalse($this->resolver->isModuleEnabled($inst, 'finance'));
    }

    public function test_a06_industry_veto(): void
    {
        // 'finance' is not an industry module: base stays intact while the
        // industry-incompatible module is vetoed on the same institute.
        $inst = $this->makeInstitute($this->package('advanced'), ['industry' => 'finance']);

        $this->assertTrue($this->resolver->isModuleEnabled($inst, 'finance'));
        $this->assertFalse($this->resolver->isModuleEnabled($inst, 'medical'));
        $this->assertFalse($this->service->isEnabled($inst, 'medical'));
    }

    public function test_a07_dependency_veto(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));
        $this->assertTrue($this->resolver->isModuleEnabled($inst, 'finance'));

        \App\Models\ModuleRegistry::where('key', 'finance')->update(['dependencies' => ['ghost-dep-xyz']]);
        $this->service->flushCache($inst->id);

        $this->assertFalse($this->resolver->isModuleEnabled($inst, 'finance'));
        $this->assertFalse($this->service->isEnabled($inst, 'finance'));
    }

    public function test_a08_parent_veto(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));
        $this->assertTrue($this->resolver->isModuleEnabled($inst, 'medical'));
        $this->assertTrue($this->resolver->isModuleEnabled($inst, 'medical.opd'));

        $this->service->disableModule($inst, 'medical', null, 'EffRes');

        $this->assertFalse($this->resolver->isModuleEnabled($inst, 'medical'));
        $this->assertFalse($this->resolver->isModuleEnabled($inst, 'medical.opd'));
    }

    public function test_a09_scope_specific_module(): void
    {
        $pkg = $this->freshPackage();
        $inst = $this->makeInstitute($pkg);
        $scope = $this->globalScope($pkg);
        PackageScopedModule::create(['package_scope_id' => $scope->id, 'module_key' => 'finance', 'enabled' => true]);

        $modules = $this->resolver->resolveModules($inst);

        $this->assertTrue($modules['finance']);
        $this->assertFalse($modules['sales']);
        $this->assertSame($this->service->resolveEnabled($inst), $modules);
    }

    public function test_a10_subscription_expired_falls_back_to_free(): void
    {
        $advanced = $this->package('advanced');
        $inst = $this->makeInstitute($advanced);
        DB::table('institute_subscriptions')->where('institute_id', $inst->id)->delete();
        $this->addSubscription($inst->id, $advanced->id, 'expired', now()->subDay()->toDateString());

        $this->assertFalse($this->resolver->isModuleEnabled($inst, 'sales'));

        // Same locale, same (override-free) setup: both resolve the FREE
        // package base with no overrides, so the full maps must be equal.
        $free = $this->makeInstitute($this->package('free'));

        $this->assertSame(
            $this->service->resolveEnabled($free->fresh()),
            $this->resolver->resolveModules($inst)
        );
    }

    public function test_a11_null_package_falls_back_to_free(): void
    {
        // No GLOBAL scope for FREE in-test: deterministic legacy fallback.
        $free = $this->package('free');
        PackageScope::where('package_id', $free->id)->delete();

        $inst = $this->makeInstitute($free, ['package_null' => true]);

        $this->assertNull($inst->package_id);
        $this->assertFalse($this->resolver->isModuleEnabled($inst, 'sales'));

        $resolved = $this->resolver->resolveForInstitute($inst);
        $this->assertNull($resolved['scope']);
        $this->assertSame(EffectiveEntitlementResolver::SOURCE_FALLBACK, $resolved['meta']['resolution_source']);
    }

    public function test_a12_education_filter(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'), ['industry' => 'education']);

        $this->assertFalse($this->resolver->isModuleEnabled($inst, 'sales'));

        $this->service->enableModule($inst, 'sales', null, 'EffRes');
        $this->assertTrue($this->resolver->isModuleEnabled($inst, 'sales'));
    }

    // -----------------------------------------------------------------
    // B) Feature resolution (12)
    // -----------------------------------------------------------------

    public function test_b01_feature_package_base(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));

        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertSame(
            $this->service->isFeatureEnabled($inst, 'medical.pharmacy'),
            $this->resolver->isFeatureEnabled($inst, 'medical.pharmacy')
        );
    }

    public function test_b02_feature_override_grant(): void
    {
        $inst = $this->makeInstitute($this->package('basic'));
        $this->service->enableModule($inst, 'medical', null, 'EffRes');
        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));

        InstituteFeatureOverride::create([
            'institute_id' => $inst->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => true,
        ]);

        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_b03_feature_override_deny(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));

        InstituteFeatureOverride::create([
            'institute_id' => $inst->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => false,
        ]);

        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertSame(
            $this->service->isFeatureEnabled($inst, 'medical.pharmacy'),
            $this->resolver->isFeatureEnabled($inst, 'medical.pharmacy')
        );
    }

    public function test_b04_feature_grant(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));
        $this->setPackageFeature($inst, 'medical.pharmacy', false);
        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));

        $this->grant($inst, 'feature', 'medical.pharmacy');
        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_b05_feature_denial(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));
        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));

        $this->deny($inst, 'feature', 'medical.pharmacy');
        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_b06_module_grant_enables_all_module_features(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));
        $this->setPackageFeature($inst, 'medical.pharmacy', false);
        $this->setPackageFeature($inst, 'medical.laboratory', false);
        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));

        $this->grant($inst, 'module', 'medical');

        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.laboratory'));
    }

    public function test_b07_tier_grant_enables_tier_features(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));
        $this->setPackageFeature($inst, 'medical.pharmacy', false);
        $this->setPackageFeature($inst, 'medical.laboratory', false);
        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));

        $this->grant($inst, 'tier', 'advanced');

        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.laboratory'));
    }

    public function test_b08_empty_scope_falls_back_to_legacy(): void
    {
        $pkg = $this->freshPackage();
        $inst = $this->makeInstitute($pkg);
        $this->globalScope($pkg);
        // Module on via entitlement; scoped feature set empty on purpose.
        $this->service->grantModule($inst, 'medical', ['is_grant' => true], $this->admin()->id);
        PackageFeature::create(['package_id' => $pkg->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true]);

        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_b09_feature_grant_respects_gate1(): void
    {
        $inst = $this->makeInstitute($this->package('free'));
        $this->assertFalse($this->resolver->isModuleEnabled($inst, 'medical'));

        $this->grant($inst, 'feature', 'medical.pharmacy');
        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_b10_module_grant_respects_gate1(): void
    {
        $inst = $this->makeInstitute($this->package('free'));

        $this->grant($inst, 'module', 'medical');

        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.laboratory'));
    }

    public function test_b11_unknown_feature_grant_skipped(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));
        $this->grant($inst, 'feature', 'medical.ghost-xyz');

        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.ghost-xyz'));
        $this->assertArrayNotHasKey('medical.ghost-xyz', $this->resolver->resolveFeatures($inst));
    }

    public function test_b12_malformed_feature_key_fail_closed(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));

        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'pharmacy'));
        $this->assertFalse($this->resolver->isFeatureEnabled($inst, ''));
        $this->assertSame(
            $this->service->isFeatureEnabled($inst, 'pharmacy'),
            $this->resolver->isFeatureEnabled($inst, 'pharmacy')
        );
    }

    // -----------------------------------------------------------------
    // C) Scope fallback (12)
    // -----------------------------------------------------------------

    public function test_c01_exact_match_returns_most_specific(): void
    {
        [$countryId, $industryId, $subId] = $this->localeIds();
        $pkg = $this->freshPackage();
        $global = $this->globalScope($pkg);
        $exact = PackageScope::create([
            'package_id' => $pkg->id, 'country_id' => $countryId,
            'industry_id' => $industryId, 'sub_industry_id' => $subId, 'status' => 'active',
        ]);

        $inst = $this->makeInstitute($pkg);

        $resolved = $this->resolver->resolveForInstitute($inst);
        $this->assertNotNull($resolved['scope']);
        $this->assertSame($exact->id, $resolved['scope']->id);
        $this->assertNotSame($global->id, $resolved['scope']->id);
        $this->assertSame(EffectiveEntitlementResolver::SOURCE_SCOPE, $resolved['meta']['resolution_source']);
    }

    public function test_c02_country_industry_fallback(): void
    {
        [$countryId, $industryId] = $this->localeIds();
        $pkg = $this->freshPackage();
        $this->globalScope($pkg);
        $ci = PackageScope::create([
            'package_id' => $pkg->id, 'country_id' => $countryId,
            'industry_id' => $industryId, 'sub_industry_id' => null, 'status' => 'active',
        ]);

        $inst = $this->makeInstitute($pkg);

        $this->assertSame($ci->id, $this->resolver->resolveForInstitute($inst)['scope']->id);
    }

    public function test_c03_industry_only_fallback(): void
    {
        [, $industryId] = $this->localeIds();
        $pkg = $this->freshPackage();
        $global = $this->globalScope($pkg);
        $ind = PackageScope::create([
            'package_id' => $pkg->id, 'country_id' => null,
            'industry_id' => $industryId, 'sub_industry_id' => null, 'status' => 'active',
        ]);

        $inst = $this->makeInstitute($pkg, ['sub_industry_id' => null]);

        $resolved = $this->resolver->resolveForInstitute($inst);
        $this->assertSame($ind->id, $resolved['scope']->id);
        $this->assertNotSame($global->id, $resolved['scope']->id);
    }

    public function test_c04_sub_only_scope_resolves_for_country_institute(): void
    {
        // B101: the [null, null, S] candidate sits ahead of GLOBAL, so a
        // sub-only scope now wins for country-carrying institutes
        // (previously fell through to GLOBAL).
        [, , $subId] = $this->localeIds();
        $pkg = $this->freshPackage();
        $global = $this->globalScope($pkg);
        $subOnly = PackageScope::create([
            'package_id' => $pkg->id, 'country_id' => null,
            'industry_id' => null, 'sub_industry_id' => $subId, 'status' => 'active',
        ]);

        $inst = $this->makeInstitute($pkg);

        $resolved = $this->resolver->resolveForInstitute($inst);
        $this->assertSame($subOnly->id, $resolved['scope']->id);
        $this->assertNotSame($global->id, $resolved['scope']->id);
        $this->assertSame(EffectiveEntitlementResolver::SOURCE_SCOPE, $resolved['meta']['resolution_source']);
    }

    public function test_c05_global_only(): void
    {
        $pkg = $this->freshPackage();
        $global = $this->globalScope($pkg);
        $inst = $this->makeInstitute($pkg, ['country_id' => null, 'industry_id' => null, 'sub_industry_id' => null]);

        $resolved = $this->resolver->resolveForInstitute($inst);
        $this->assertSame($global->id, $resolved['scope']->id);
        $this->assertSame(EffectiveEntitlementResolver::SOURCE_GLOBAL, $resolved['meta']['resolution_source']);
    }

    public function test_c06_no_scope_falls_back(): void
    {
        $pkg = $this->freshPackage();
        $inst = $this->makeInstitute($pkg, ['country_id' => null, 'industry_id' => null, 'sub_industry_id' => null]);

        $resolved = $this->resolver->resolveForInstitute($inst);
        $this->assertNull($resolved['scope']);
        $this->assertSame(EffectiveEntitlementResolver::SOURCE_FALLBACK, $resolved['meta']['resolution_source']);
    }

    public function test_c07_inherit_merges_parent_features(): void
    {
        [$countryId] = $this->localeIds();
        $pkg = $this->freshPackage();
        $global = $this->globalScope($pkg);
        $global->update(['inherit_from_parent' => true]);
        PackageScopedFeature::create(['package_scope_id' => $global->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true]);
        $child = PackageScope::create([
            'package_id' => $pkg->id, 'country_id' => $countryId,
            'industry_id' => null, 'sub_industry_id' => null,
            'inherit_from_parent' => true, 'status' => 'active',
        ]);
        PackageScopedFeature::create(['package_scope_id' => $child->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true]);
        PackageScopedFeature::create(['package_scope_id' => $child->id, 'feature_key' => 'medical.laboratory', 'enabled' => true]);

        // Legacy rows + enabled parent module so the scoped set applies.
        $inst = $this->makeInstitute($pkg, ['industry_id' => null, 'sub_industry_id' => null]);
        $this->service->grantModule($inst, 'medical', ['is_grant' => true], $this->admin()->id);
        PackageFeature::create(['package_id' => $pkg->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true]);
        PackageFeature::create(['package_id' => $pkg->id, 'feature_key' => 'medical.laboratory', 'enabled' => true]);

        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.laboratory'));
    }

    public function test_c08_independent_scope_ignores_parent(): void
    {
        [$countryId] = $this->localeIds();
        $pkg = $this->freshPackage();
        $global = $this->globalScope($pkg);
        PackageScopedFeature::create(['package_scope_id' => $global->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true]);
        $child = PackageScope::create([
            'package_id' => $pkg->id, 'country_id' => $countryId,
            'industry_id' => null, 'sub_industry_id' => null,
            'inherit_from_parent' => false, 'status' => 'active',
        ]);
        PackageScopedFeature::create(['package_scope_id' => $child->id, 'feature_key' => 'medical.laboratory', 'enabled' => true]);

        $inst = $this->makeInstitute($pkg, ['industry_id' => null, 'sub_industry_id' => null]);
        $this->service->grantModule($inst, 'medical', ['is_grant' => true], $this->admin()->id);
        PackageFeature::create(['package_id' => $pkg->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true]);
        PackageFeature::create(['package_id' => $pkg->id, 'feature_key' => 'medical.laboratory', 'enabled' => true]);

        // Scoped set is {laboratory} only: pharmacy is out, laboratory in.
        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.laboratory'));
    }

    public function test_c09_recursive_inherit_three_levels(): void
    {
        [$countryId, $industryId] = $this->localeIds();
        $pkg = $this->freshPackage();
        $global = $this->globalScope($pkg);
        $global->update(['inherit_from_parent' => true]);
        PackageScopedFeature::create(['package_scope_id' => $global->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true]);
        $mid = PackageScope::create([
            'package_id' => $pkg->id, 'country_id' => $countryId,
            'industry_id' => null, 'sub_industry_id' => null,
            'inherit_from_parent' => true, 'status' => 'active',
        ]);
        $leaf = PackageScope::create([
            'package_id' => $pkg->id, 'country_id' => $countryId,
            'industry_id' => $industryId, 'sub_industry_id' => null,
            'inherit_from_parent' => true, 'status' => 'active',
        ]);
        PackageScopedFeature::create(['package_scope_id' => $leaf->id, 'feature_key' => 'medical.laboratory', 'enabled' => true]);

        $inst = $this->makeInstitute($pkg, ['sub_industry_id' => null]);
        $this->service->grantModule($inst, 'medical', ['is_grant' => true], $this->admin()->id);
        PackageFeature::create(['package_id' => $pkg->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true]);
        PackageFeature::create(['package_id' => $pkg->id, 'feature_key' => 'medical.laboratory', 'enabled' => true]);

        // Leaf resolves most-specific; features merge GLOBAL → mid → leaf.
        $this->assertSame($leaf->id, $this->resolver->resolveForInstitute($inst)['scope']->id);
        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.laboratory'));
        $this->assertNotSame($mid->id, $this->resolver->resolveForInstitute($inst)['scope']->id);
    }

    public function test_c10_disabled_row_excluded_from_inherit(): void
    {
        [$countryId] = $this->localeIds();
        $pkg = $this->freshPackage();
        $global = $this->globalScope($pkg);
        $global->update(['inherit_from_parent' => true]);
        PackageScopedFeature::create(['package_scope_id' => $global->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true]);
        $child = PackageScope::create([
            'package_id' => $pkg->id, 'country_id' => $countryId,
            'industry_id' => null, 'sub_industry_id' => null,
            'inherit_from_parent' => true, 'status' => 'active',
        ]);
        PackageScopedFeature::create(['package_scope_id' => $child->id, 'feature_key' => 'medical.pharmacy', 'enabled' => false]);

        $inst = $this->makeInstitute($pkg, ['industry_id' => null, 'sub_industry_id' => null]);
        $this->service->grantModule($inst, 'medical', ['is_grant' => true], $this->admin()->id);
        PackageFeature::create(['package_id' => $pkg->id, 'feature_key' => 'medical.pharmacy', 'enabled' => false]);

        // Scoped set minus disabled = empty; legacy is also disabled → off.
        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_c11_feature_cache_hit_returns_identical_map(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));

        $first = $this->resolver->resolveFeatures($inst);
        $second = $this->resolver->resolveFeatures($inst);

        $this->assertSame($first, $second);

        $scope = $this->service->resolveScopedPackage($inst);
        $hash = $scope?->scope_hash ?? 'global';
        $this->assertTrue(Cache::has('feature_access:'.$inst->id.':'.$hash));
    }

    public function test_c12_mutation_visible_after_flush(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));
        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));

        InstituteFeatureOverride::create([
            'institute_id' => $inst->id,
            'feature_key' => 'medical.pharmacy',
            'enabled' => false,
        ]);
        $this->resolver->flushCache($inst->id);

        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertSame(
            $this->service->isFeatureEnabled($inst, 'medical.pharmacy'),
            $this->resolver->isFeatureEnabled($inst, 'medical.pharmacy')
        );
    }

    public function test_c13_sub_only_scope_takes_priority_over_global(): void
    {
        // B101: with more-specific scopes absent, the sub-only row beats
        // GLOBAL for a country-carrying institute.
        [, , $subId] = $this->localeIds();
        $pkg = $this->freshPackage();
        $global = $this->globalScope($pkg);
        $subOnly = PackageScope::create([
            'package_id' => $pkg->id, 'country_id' => null,
            'industry_id' => null, 'sub_industry_id' => $subId, 'status' => 'active',
        ]);

        $inst = $this->makeInstitute($pkg);

        $this->assertSame($subOnly->id, $this->service->resolveScopedPackage($inst)->id);
        $this->assertNotSame($global->id, $this->service->resolveScopedPackage($inst)->id);
    }

    public function test_c14_sub_only_scope_dedup_when_sub_null(): void
    {
        // B101: when the institute has no sub-industry, the new
        // [null, null, S] row dedup-collapses into GLOBAL — resolution
        // still lands on GLOBAL with source 'global'.
        $pkg = $this->freshPackage();
        $global = $this->globalScope($pkg);

        $inst = $this->makeInstitute($pkg, ['sub_industry_id' => null]);

        $resolved = $this->resolver->resolveForInstitute($inst);
        $this->assertSame($global->id, $resolved['scope']->id);
        $this->assertSame(EffectiveEntitlementResolver::SOURCE_GLOBAL, $resolved['meta']['resolution_source']);
    }

    public function test_c15_parent_scope_walks_from_sub_only_to_global(): void
    {
        // B101: inheritance above a sub-only scope needs no new step —
        // its parent walk already lands on GLOBAL.
        [, , $subId] = $this->localeIds();
        $pkg = $this->freshPackage();
        $global = $this->globalScope($pkg);
        $subOnly = PackageScope::create([
            'package_id' => $pkg->id, 'country_id' => null,
            'industry_id' => null, 'sub_industry_id' => $subId, 'status' => 'active',
        ]);

        $parent = $this->service->resolveParentScope($subOnly);

        $this->assertNotNull($parent);
        $this->assertSame($global->id, $parent->id);
    }

    public function test_c16_sub_only_scope_tier_path(): void
    {
        // B101: the tier-grant chain (resolveScopedPackageForPackage) is
        // kept in lockstep — a sub-only scope on the tier package wins
        // for a country-carrying institute.
        [, , $subId] = $this->localeIds();
        $tier = $this->freshPackage();
        $global = PackageScope::create([
            'package_id' => $tier->id, 'country_id' => null,
            'industry_id' => null, 'sub_industry_id' => null, 'status' => 'active',
        ]);
        $subOnly = PackageScope::create([
            'package_id' => $tier->id, 'country_id' => null,
            'industry_id' => null, 'sub_industry_id' => $subId, 'status' => 'active',
        ]);

        $inst = $this->makeInstitute($this->package('advanced'));

        $resolved = $this->service->resolveScopedPackageForPackage($inst, $tier);
        $this->assertNotNull($resolved);
        $this->assertSame($subOnly->id, $resolved->id);
        $this->assertNotSame($global->id, $resolved->id);
    }

    public function test_c17_resolver_meta_matches_service_meta(): void
    {
        // B100: resolver meta must equal the service's single-computation
        // meta (same rows, no drift).
        $inst = $this->makeInstitute($this->package('advanced'));
        $this->grant($inst, 'feature', 'medical.pharmacy');
        $this->grant($inst, 'module', 'medical');
        $this->deny($inst, 'feature', 'medical.laboratory');
        $this->grant($inst, 'feature', 'medical.laboratory', ['expires_at' => now()->subDay()]);

        $serviceMeta = $this->service->getFeatureAccessMapWithMeta($inst);
        $resolverMeta = $this->resolver->resolveForInstitute($inst)['meta'];

        $this->assertSame(2, $serviceMeta['grants_applied']);
        $this->assertSame(1, $serviceMeta['denials_applied']);
        $this->assertSame($serviceMeta['grants_applied'], $resolverMeta['grants_applied']);
        $this->assertSame($serviceMeta['denials_applied'], $resolverMeta['denials_applied']);
        $this->assertSame($serviceMeta['map'], $this->resolver->resolveForInstitute($inst)['features']);
    }

    public function test_c18_single_computation_queries_grants_denials_once(): void
    {
        // B100: one computation → tenant_access_grants and
        // tenant_access_denials are each queried EXACTLY once (before:
        // once inside computeFeatureAccessMap plus once per resolver
        // count query).
        $inst = $this->makeInstitute($this->package('advanced'));
        $this->grant($inst, 'feature', 'medical.pharmacy');
        $this->deny($inst, 'feature', 'medical.laboratory');

        DB::enableQueryLog();
        DB::flushQueryLog();
        $withMeta = $this->service->getFeatureAccessMapWithMeta($inst);
        $log = DB::getQueryLog();
        DB::disableQueryLog();

        $grantQueries = array_filter($log, fn ($q) => str_contains($q['query'], 'tenant_access_grants'));
        $denialQueries = array_filter($log, fn ($q) => str_contains($q['query'], 'tenant_access_denials'));

        $this->assertCount(1, $grantQueries);
        $this->assertCount(1, $denialQueries);
        $this->assertSame(1, $withMeta['grants_applied']);
        $this->assertSame(1, $withMeta['denials_applied']);
    }

    // -----------------------------------------------------------------
    // D) Grant + denial edge cases (12)
    // -----------------------------------------------------------------

    public function test_d01_expired_grant_has_no_effect(): void
    {
        $inst = $this->makeInstitute($this->package('basic'));
        $this->service->enableModule($inst, 'medical', null, 'EffRes');
        $this->setPackageFeature($inst, 'medical.pharmacy', false);

        $this->grant($inst, 'feature', 'medical.pharmacy', ['expires_at' => now()->subDay()]);

        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertSame(0, $this->resolver->resolveForInstitute($inst)['meta']['grants_applied']);
    }

    public function test_d02_expired_denial_has_no_effect(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));

        $this->deny($inst, 'feature', 'medical.pharmacy', ['expires_at' => now()->subDay()]);

        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertSame(0, $this->resolver->resolveForInstitute($inst)['meta']['denials_applied']);
    }

    public function test_d03_revoked_grant_has_no_effect(): void
    {
        $inst = $this->makeInstitute($this->package('basic'));
        $this->service->enableModule($inst, 'medical', null, 'EffRes');
        $this->setPackageFeature($inst, 'medical.pharmacy', false);

        $this->grant($inst, 'feature', 'medical.pharmacy', ['status' => 'revoked']);

        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertSame(0, $this->resolver->resolveForInstitute($inst)['meta']['grants_applied']);
    }

    public function test_d04_lifted_denial_has_no_effect(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));

        $this->deny($inst, 'feature', 'medical.pharmacy', ['status' => 'lifted']);

        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_d05_grant_plus_denial_same_key_denial_wins(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));

        $this->grant($inst, 'feature', 'medical.pharmacy');
        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));

        $this->deny($inst, 'feature', 'medical.pharmacy');
        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_d06_module_grant_parent_disabled_blocked(): void
    {
        $inst = $this->makeInstitute($this->package('free'));

        $this->grant($inst, 'module', 'medical');

        $this->assertFalse($this->resolver->isModuleEnabled($inst, 'medical'));
        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_d07_tier_grant_respects_industry_veto(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'), ['industry' => 'finance']);

        $this->grant($inst, 'tier', 'advanced');

        $this->assertFalse($this->resolver->isModuleEnabled($inst, 'medical'));
        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_d08_unknown_tier_grant_skipped(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));

        $this->grant($inst, 'tier', 'no-such-tier-xyz');

        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertSame(
            $this->service->isFeatureEnabled($inst, 'medical.pharmacy'),
            $this->resolver->isFeatureEnabled($inst, 'medical.pharmacy')
        );
    }

    public function test_d09_multiple_grants_same_key_idempotent(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));
        $this->setPackageFeature($inst, 'medical.pharmacy', false);

        $this->grant($inst, 'feature', 'medical.pharmacy');
        $this->grant($inst, 'feature', 'medical.pharmacy');

        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertSame(2, $this->resolver->resolveForInstitute($inst)['meta']['grants_applied']);
    }

    public function test_d10_meta_counts_correct(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));

        $this->grant($inst, 'feature', 'medical.pharmacy');
        $this->grant($inst, 'module', 'medical');
        $this->deny($inst, 'feature', 'medical.laboratory');

        $meta = $this->resolver->resolveForInstitute($inst)['meta'];
        $this->assertSame(2, $meta['grants_applied']);
        $this->assertSame(1, $meta['denials_applied']);
    }

    public function test_d11_entitlement_window_respected(): void
    {
        $inst = $this->makeInstitute($this->package('basic'));
        $this->assertFalse($this->resolver->isModuleEnabled($inst, 'hr'));

        $expiredId = InstituteModuleEntitlement::create([
            'institute_id' => $inst->id,
            'module_key' => 'hr',
            'status' => 'active',
            'is_grant' => true,
            'ends_at' => now()->subDay(),
        ])->id;
        // Freeze the expired row strictly older: timestamps are
        // second-precision, so two fast creates could tie (and a tie
        // keeps the first row). Backdating makes latest-wins deterministic.
        DB::table('institute_module_entitlements')->where('id', $expiredId)->update([
            'updated_at' => now()->subHour(), 'created_at' => now()->subHour(),
        ]);
        $this->service->flushCache($inst->id);
        $this->assertFalse($this->resolver->isModuleEnabled($inst, 'hr'));

        InstituteModuleEntitlement::create([
            'institute_id' => $inst->id,
            'module_key' => 'hr',
            'status' => 'active',
            'is_grant' => true,
            'ends_at' => now()->addMonth(),
        ]);
        $this->service->flushCache($inst->id);
        // Latest updated_at wins: the open grant is newest.
        $this->assertTrue($this->resolver->isModuleEnabled($inst, 'hr'));
    }

    public function test_d12_entitlement_deny_wins_on_tie(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));
        $this->assertTrue($this->resolver->isModuleEnabled($inst, 'finance'));

        $stamp = now()->startOfSecond();
        $ids = [];
        $ids[] = InstituteModuleEntitlement::create([
            'institute_id' => $inst->id, 'module_key' => 'finance',
            'status' => 'active', 'is_grant' => true,
        ])->id;
        $ids[] = InstituteModuleEntitlement::create([
            'institute_id' => $inst->id, 'module_key' => 'finance',
            'status' => 'active', 'is_grant' => false,
        ])->id;
        DB::table('institute_module_entitlements')->whereIn('id', $ids)->update([
            'updated_at' => $stamp, 'created_at' => $stamp,
        ]);
        $this->service->flushCache($inst->id);

        $this->assertFalse($this->resolver->isModuleEnabled($inst, 'finance'));
        $this->assertFalse($this->service->isEnabled($inst, 'finance'));
    }

    // -----------------------------------------------------------------
    // E) Backward compatibility (14)
    // -----------------------------------------------------------------

    public function test_e01_is_feature_enabled_parity(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));

        foreach (['medical.pharmacy', 'medical.laboratory', 'medical.ghost-xyz', 'pharmacy', ''] as $key) {
            $this->assertSame(
                $this->service->isFeatureEnabled($inst, $key),
                $this->resolver->isFeatureEnabled($inst, $key),
                "Parity failed for key [{$key}]"
            );
        }
    }

    public function test_e02_resolve_modules_parity(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));

        $this->assertSame($this->service->resolveEnabled($inst), $this->resolver->resolveModules($inst));
    }

    public function test_e03_resolve_features_parity(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));

        $this->assertSame($this->service->getFeatureAccessMap($inst), $this->resolver->resolveFeatures($inst));
    }

    public function test_e04_resolve_for_institute_shape(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));
        $resolved = $this->resolver->resolveForInstitute($inst);

        $this->assertArrayHasKey('modules', $resolved);
        $this->assertArrayHasKey('features', $resolved);
        $this->assertArrayHasKey('scope', $resolved);
        $this->assertArrayHasKey('meta', $resolved);
        $this->assertArrayHasKey('resolution_source', $resolved['meta']);
        $this->assertArrayHasKey('grants_applied', $resolved['meta']);
        $this->assertArrayHasKey('denials_applied', $resolved['meta']);
        $this->assertIsArray($resolved['modules']);
        $this->assertIsArray($resolved['features']);
        $this->assertContains($resolved['meta']['resolution_source'], ['scope', 'global', 'fallback']);
    }

    public function test_e05_resolution_source_values(): void
    {
        // scope: fresh package with a country scope
        [$countryId] = $this->localeIds();
        $pkgS = $this->freshPackage();
        PackageScope::create([
            'package_id' => $pkgS->id, 'country_id' => $countryId,
            'industry_id' => null, 'sub_industry_id' => null, 'status' => 'active',
        ]);
        $instS = $this->makeInstitute($pkgS, ['industry_id' => null, 'sub_industry_id' => null]);
        $this->assertSame('scope', $this->resolver->resolveForInstitute($instS)['meta']['resolution_source']);

        // global: fresh package with GLOBAL scope only
        $pkgG = $this->freshPackage();
        $this->globalScope($pkgG);
        $instG = $this->makeInstitute($pkgG, ['country_id' => null, 'industry_id' => null, 'sub_industry_id' => null]);
        $this->assertSame('global', $this->resolver->resolveForInstitute($instG)['meta']['resolution_source']);

        // fallback: fresh package with no scopes at all
        $pkgF = $this->freshPackage();
        $instF = $this->makeInstitute($pkgF, ['country_id' => null, 'industry_id' => null, 'sub_industry_id' => null]);
        $this->assertSame('fallback', $this->resolver->resolveForInstitute($instF)['meta']['resolution_source']);
    }

    public function test_e06_module_cache_key_format_unchanged(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));

        $this->service->getEnabledModules($inst);

        $this->assertTrue(Cache::has('module_access:'.$inst->id));
    }

    public function test_e07_feature_cache_key_format_unchanged(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));

        $this->service->getFeatureAccessMap($inst);

        $scope = $this->service->resolveScopedPackage($inst);
        $hash = $scope?->scope_hash ?? 'global';
        $this->assertTrue(Cache::has('feature_access:'.$inst->id.':'.$hash));
    }

    public function test_e08_service_public_surface_intact(): void
    {
        foreach ([
            'isEnabled', 'isFeatureEnabled', 'resolveEnabled',
            'getFeatureAccessMap', 'getEnabledModules', 'resolveScopedPackage',
            'resolveScopedPackageForPackage', 'getTierFeatureKeys', 'flushCache',
            'flushFeatureCache', 'grantModule', 'revokeModule',
        ] as $method) {
            $this->assertTrue(
                method_exists($this->service, $method),
                "ModuleAccessService::{$method}() must remain public"
            );
        }
    }

    public function test_e09_middleware_entry_points_still_resolve(): void
    {
        $this->assertTrue(method_exists(\App\Http\Middleware\CheckModuleAccess::class, 'handle'));
        $this->assertTrue(method_exists(\App\Http\Middleware\CheckFeatureAccess::class, 'handle'));

        $inst = $this->makeInstitute($this->package('advanced'));
        $this->deny($inst, 'feature', 'medical.pharmacy');

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_e10_flush_cache_clears_both_caches(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));
        $this->service->getEnabledModules($inst);
        $this->service->getFeatureAccessMap($inst);

        $this->assertTrue(Cache::has('module_access:'.$inst->id));

        $this->resolver->flushCache($inst->id);

        $this->assertFalse(Cache::has('module_access:'.$inst->id));
    }

    public function test_e11_flush_unknown_id_does_not_throw(): void
    {
        $this->resolver->flushCache(999999999);
        $this->assertTrue(true);
    }

    public function test_e12_resolver_stateless_repeatable(): void
    {
        $inst = $this->makeInstitute($this->package('advanced'));

        $first = $this->resolver->resolveForInstitute($inst);
        $second = $this->resolver->resolveForInstitute($inst);

        $this->assertSame($first['modules'], $second['modules']);
        $this->assertSame($first['features'], $second['features']);
        $this->assertSame($first['meta'], $second['meta']);
        $this->assertSame(
            $first['scope']?->id,
            $second['scope']?->id
        );
    }

    public function test_e13_full_chain_parity_end_to_end(): void
    {
        $inst = $this->makeInstitute($this->package('basic'));
        $this->service->enableModule($inst, 'medical', null, 'EffRes');
        $this->setPackageFeature($inst, 'medical.pharmacy', false);
        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));

        // Override grants → grant wins path is open …
        InstituteFeatureOverride::create([
            'institute_id' => $inst->id, 'feature_key' => 'medical.pharmacy', 'enabled' => true,
        ]);
        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));

        // … until a denial lands (wins over everything).
        $this->deny($inst, 'feature', 'medical.pharmacy');
        $this->assertFalse($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));

        $this->assertSame(
            $this->service->getFeatureAccessMap($inst),
            $this->resolver->resolveFeatures($inst)
        );
        $this->assertSame(
            $this->service->resolveEnabled($inst),
            $this->resolver->resolveModules($inst)
        );
    }

    public function test_e14_scope_plus_grant_combined_parity(): void
    {
        [$countryId] = $this->localeIds();
        $pkg = $this->freshPackage();
        $scope = PackageScope::create([
            'package_id' => $pkg->id, 'country_id' => $countryId,
            'industry_id' => null, 'sub_industry_id' => null,
            'inherit_from_parent' => false, 'status' => 'active',
        ]);
        PackageScopedModule::create(['package_scope_id' => $scope->id, 'module_key' => 'medical', 'enabled' => true]);
        PackageScopedFeature::create(['package_scope_id' => $scope->id, 'feature_key' => 'medical.laboratory', 'enabled' => true]);

        $inst = $this->makeInstitute($pkg, ['industry_id' => null, 'sub_industry_id' => null]);
        PackageFeature::create(['package_id' => $pkg->id, 'feature_key' => 'medical.laboratory', 'enabled' => true]);

        $this->grant($inst, 'feature', 'medical.pharmacy');

        $this->assertTrue($this->resolver->isModuleEnabled($inst, 'medical'));
        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.laboratory'));
        $this->assertTrue($this->resolver->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertSame(
            $this->service->getFeatureAccessMap($inst),
            $this->resolver->resolveFeatures($inst)
        );
        $this->assertSame('scope', $this->resolver->resolveForInstitute($inst)['meta']['resolution_source']);
    }
}
