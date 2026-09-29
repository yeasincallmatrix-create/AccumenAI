<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Database\Seeders\PackageIndustrySeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackageIndustryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('package_industries')
            || ! Schema::hasTable('package_industry_modules')
            || ! Schema::hasTable('industries')) {
            $this->markTestSkipped('package_industries tables do not exist.');
        }

        // Hermetic: every resolution assertion assumes an unconfigured
        // industry. Rows are cleared inside the per-test transaction.
        // Country prices cleared too: institutes default to Bangladesh,
        // so dump BD rows would otherwise win over industry rows.
        DB::table('package_industries')->delete();
        DB::table('package_industry_modules')->delete();
        DB::table('package_country_prices')->delete();
    }

    // ── Schema ──────────────────────────────────────────────────────

    public function test_package_industries_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('package_industries'));
        $this->assertTrue(Schema::hasTable('package_industry_modules'));

        $columns = Schema::getColumnListing('package_industries');
        foreach (['id', 'package_id', 'industry_key', 'is_active', 'sort_order', 'price_monthly', 'price_yearly'] as $column) {
            $this->assertContains($column, $columns);
        }

        $moduleColumns = Schema::getColumnListing('package_industry_modules');
        foreach (['package_id', 'industry_key', 'module_key', 'enabled'] as $column) {
            $this->assertContains($column, $moduleColumns);
        }
    }

    // ── Admin UI ────────────────────────────────────────────────────

    public function test_index_requires_platform_admin(): void
    {
        $this->get(route('admin.package-industries.index'))
            ->assertRedirect();
    }

    public function test_admin_can_view_package_industry_page(): void
    {
        $this->loginAsAdmin();

        $this->get(route('admin.package-industries.index', ['industry' => 'healthcare']))
            ->assertOk()
            ->assertSee('Package Configuration by Industry')
            ->assertSee('healthcare', false);
    }

    public function test_index_falls_back_to_first_industry_for_unknown_slug(): void
    {
        $this->loginAsAdmin();

        $response = $this->get(route('admin.package-industries.index', ['industry' => 'does-not-exist']));
        $response->assertOk();
    }

    public function test_industry_package_config_saves(): void
    {
        $this->loginAsAdmin();

        $packages = SubscriptionPackage::where('status', 'active')->pluck('id')->values()->take(2);
        $this->assertTrue($packages->isNotEmpty());

        $this->put(route('admin.package-industries.update'), [
            'industry' => 'healthcare',
            'packages' => $packages->all(),
            'price_monthly' => [$packages[0] => 1234.56],
        ])->assertRedirect(route('admin.package-industries.index', ['industry' => 'healthcare']));

        $this->assertSame(
            $packages->count(),
            DB::table('package_industries')
                ->where('industry_key', 'healthcare')
                ->where('is_active', true)
                ->count()
        );

        $price = DB::table('package_industries')
            ->where('industry_key', 'healthcare')
            ->where('package_id', $packages[0])
            ->value('price_monthly');

        $this->assertEquals(1234.56, (float) $price);
    }

    public function test_industry_package_save_requires_at_least_one_package(): void
    {
        $this->loginAsAdmin();

        $this->put(route('admin.package-industries.update'), [
            'industry' => 'healthcare',
            'packages' => [],
        ])->assertSessionHasErrors('packages');
    }

    public function test_industry_package_save_rejects_unknown_industry(): void
    {
        $this->loginAsAdmin();

        $packageId = SubscriptionPackage::value('id');

        $this->put(route('admin.package-industries.update'), [
            'industry' => 'not-an-industry',
            'packages' => [$packageId],
        ])->assertSessionHasErrors('industry');
    }

    public function test_admin_can_view_and_save_industry_modules(): void
    {
        $this->loginAsAdmin();

        $package = SubscriptionPackage::where('slug', 'premium')->first()
            ?: SubscriptionPackage::orderBy('id')->first();

        $this->get(route('admin.package-industries.show-modules', [
            'package' => $package->id,
            'industry' => 'healthcare',
        ]))->assertOk()->assertSee('Modules');

        $moduleKey = DB::table('module_registry')
            ->where('status', 'active')
            ->where('type', 'core')
            ->value('key');

        $this->assertNotEmpty($moduleKey);

        $this->put(route('admin.package-industries.update-modules', [
            'package' => $package->id,
            'industry' => 'healthcare',
        ]), [
            'modules' => [$moduleKey],
        ])->assertRedirect(route('admin.package-industries.show-modules', [
            'package' => $package->id,
            'industry' => 'healthcare',
        ]));

        $this->assertTrue(
            DB::table('package_industry_modules')
                ->where('package_id', $package->id)
                ->where('industry_key', 'healthcare')
                ->where('module_key', $moduleKey)
                ->where('enabled', true)
                ->exists()
        );
    }

    public function test_show_modules_404s_for_unknown_package(): void
    {
        $this->loginAsAdmin();

        $this->get(route('admin.package-industries.show-modules', [
            'package' => 999999999,
            'industry' => 'healthcare',
        ]))->assertNotFound();
    }

    // ── Seeder ──────────────────────────────────────────────────────

    public function test_seeder_maps_expected_industries(): void
    {
        DB::table('package_industries')->where('industry_key', 'healthcare')->delete();

        (new PackageIndustrySeeder)->run();

        $expected = [
            'healthcare' => ['free', 'medical_starter', 'medical_growth', 'medical_enterprise'],
            'education' => ['free', 'education_starter', 'education_growth', 'education_enterprise'],
            'manufacturing' => ['free', 'manufacturing_starter', 'manufacturing_growth', 'manufacturing_enterprise'],
        ];

        foreach ($expected as $industry => $slugs) {
            $mapped = DB::table('package_industries')
                ->where('industry_key', $industry)
                ->where('is_active', true)
                ->pluck('package_id')
                ->all();

            $expectedIds = DB::table('subscription_packages')
                ->whereIn('slug', $slugs)
                ->pluck('id')
                ->all();

            sort($mapped);
            sort($expectedIds);

            $this->assertSame($expectedIds, $mapped, "Industry {$industry} mapping mismatch.");
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        (new PackageIndustrySeeder)->run();
        $first = DB::table('package_industries')->count();

        (new PackageIndustrySeeder)->run();
        $second = DB::table('package_industries')->count();

        $this->assertSame($first, $second);
    }

    // ── Resolution ──────────────────────────────────────────────────

    public function test_tenant_on_unconfigured_industry_keeps_package_modules(): void
    {
        $institute = $this->healthcareInstitute('premium');
        $control = $this->service()->getEnabledModules($institute);

        $candidate = $this->premiumOnlyModule($institute, $control);
        $this->assertNotNull($candidate, 'premium should expose a module beyond FREE/core/industry defaults');

        $this->assertContains($candidate, $control);
    }

    public function test_tenant_on_non_offered_package_falls_back_to_free(): void
    {
        $institute = $this->healthcareInstitute('premium');
        $control = $this->service()->getEnabledModules($institute);
        $candidate = $this->premiumOnlyModule($institute, $control);
        $this->assertNotNull($candidate);

        $free = SubscriptionPackage::where('slug', 'free')->firstOrFail();

        DB::table('package_industries')->where('industry_key', 'healthcare')->delete();
        foreach (['free', 'basic', 'advanced'] as $slug) {
            $id = SubscriptionPackage::where('slug', $slug)->value('id');
            if ($id) {
                DB::table('package_industries')->insert([
                    'package_id' => $id,
                    'industry_key' => 'healthcare',
                    'is_active' => true,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->service()->flushCache($institute->id);
        $gated = $this->service()->getEnabledModules($institute);

        $this->assertNotContains($candidate, $gated, 'non-offered package must fall back to FREE');
        $this->assertLessThan(count($control), count($gated));
        $this->assertContains('notifications', $gated);
        $this->assertNotNull($free->id);
    }

    public function test_tenant_on_offered_package_keeps_its_modules(): void
    {
        $institute = $this->healthcareInstitute('premium');
        $control = $this->service()->getEnabledModules($institute);
        $candidate = $this->premiumOnlyModule($institute, $control);
        $this->assertNotNull($candidate);

        DB::table('package_industries')->where('industry_key', 'healthcare')->delete();
        foreach (['free', 'basic', 'advanced', 'premium'] as $slug) {
            $id = SubscriptionPackage::where('slug', $slug)->value('id');
            if ($id) {
                DB::table('package_industries')->insert([
                    'package_id' => $id,
                    'industry_key' => 'healthcare',
                    'is_active' => true,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->service()->flushCache($institute->id);
        $after = $this->service()->getEnabledModules($institute);

        $this->assertContains($candidate, $after, 'offered package must keep its modules');
    }

    public function test_industry_module_configuration_overrides_package_defaults(): void
    {
        $institute = $this->healthcareInstitute('premium');
        $control = $this->service()->getEnabledModules($institute);
        $candidate = $this->premiumOnlyModule($institute, $control);
        $this->assertNotNull($candidate);

        $package = SubscriptionPackage::where('slug', 'premium')->firstOrFail();

        DB::table('package_industries')->where('industry_key', 'healthcare')->delete();
        DB::table('package_industries')->insert([
            'package_id' => $package->id,
            'industry_key' => 'healthcare',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $coreKeys = array_values(array_filter(
            config('industry-modules.core', []),
            fn ($key) => DB::table('module_registry')->where('key', $key)->exists()
        ));
        $this->assertNotEmpty($coreKeys);

        DB::table('package_industry_modules')
            ->where('package_id', $package->id)
            ->where('industry_key', 'healthcare')
            ->delete();

        foreach ($coreKeys as $key) {
            DB::table('package_industry_modules')->insert([
                'package_id' => $package->id,
                'industry_key' => 'healthcare',
                'module_key' => $key,
                'enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->service()->flushCache($institute->id);
        $gated = $this->service()->getEnabledModules($institute);

        $this->assertNotContains($candidate, $gated, 'industry module set must win over package defaults');
        $this->assertLessThanOrEqual(count($coreKeys) + 10, count($gated));
    }

    public function test_industry_price_override_is_returned(): void
    {
        $institute = $this->healthcareInstitute('premium');
        $package = SubscriptionPackage::where('slug', 'premium')->firstOrFail();

        DB::table('package_industries')->where('industry_key', 'healthcare')->delete();
        DB::table('package_industries')->insert([
            'package_id' => $package->id,
            'industry_key' => 'healthcare',
            'is_active' => true,
            'sort_order' => 0,
            'price_monthly' => 7777.00,
            'price_yearly' => 77770.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $price = $this->service()->resolveScopedPrice($institute);

        $this->assertEquals(7777.00, $price['monthly']);
        $this->assertEquals(77770.00, $price['yearly']);
    }

    public function test_price_falls_back_to_package_price_without_override(): void
    {
        $institute = $this->healthcareInstitute('premium');
        $package = SubscriptionPackage::where('slug', 'premium')->firstOrFail();

        DB::table('package_industries')->where('industry_key', 'healthcare')->delete();
        DB::table('package_industries')->insert([
            'package_id' => $package->id,
            'industry_key' => 'healthcare',
            'is_active' => true,
            'sort_order' => 0,
            'price_monthly' => null,
            'price_yearly' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $price = $this->service()->resolveScopedPrice($institute);

        $this->assertEquals((float) $package->price_monthly, $price['monthly']);
    }

    // ── Helpers ─────────────────────────────────────────────────────

    private function loginAsAdmin(): PlatformAdmin
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'first_name' => 'Package',
            'last_name' => 'Industry',
            'email' => 'package-industry-'.uniqid().'@example.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin, 'platform_admin');

        return $admin;
    }

    private function healthcareInstitute(string $slug): Institute
    {
        $package = SubscriptionPackage::where('slug', $slug)->firstOrFail();

        $institute = Institute::create([
            'name' => 'PI Healthcare '.uniqid(),
            'slug' => 'pi-healthcare-'.uniqid(),
            'industry' => 'healthcare',
            'status' => 'active',
            'package_id' => $package->id,
            'country_id' => null,
        ]);

        DB::table('institute_subscriptions')->insert([
            'institute_id' => $institute->id,
            'package_id' => $package->id,
            'billing_cycle' => 'monthly',
            'price_paid' => 0,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
        ]);

        return $institute->fresh();
    }

    private function service(): ModuleAccessService
    {
        return new ModuleAccessService;
    }

    /**
     * A module the premium package grants, that FREE does not, is not core
     * and is not gated off by the healthcare industry matrix.
     */
    private function premiumOnlyModule(Institute $institute, array $enabled): ?string
    {
        $premium = SubscriptionPackage::where('slug', 'premium')->first();
        $free = SubscriptionPackage::where('slug', 'free')->first();

        if (! $premium || ! $free) {
            return null;
        }

        $premiumKeys = DB::table('package_modules')
            ->where('package_id', $premium->id)
            ->where('enabled', true)
            ->pluck('module_key');

        $freeKeys = DB::table('package_modules')
            ->where('package_id', $free->id)
            ->where('enabled', true)
            ->pluck('module_key');

        $optional = array_flip(config('industry-modules.healthcare.optional', []));
        $defaults = array_flip(config('industry-modules.healthcare.default', []));
        $service = $this->service();

        foreach ($premiumKeys->diff($freeKeys) as $key) {
            if ($service->isCoreModule($key)) {
                continue;
            }
            if (isset($optional[$key]) || isset($defaults[$key])) {
                continue;
            }
            if (! in_array($key, $enabled, true)) {
                continue;
            }
            if (! $service->isIndustryCompatible($institute, $key)) {
                continue;
            }

            return $key;
        }

        return null;
    }

    // ── Categories (Mandatory/Default/Optional/Hidden) ────────────

    private function categoryFixtureKeys(int $count = 3): array
    {
        $service = $this->service();
        $disabled = array_flip(config('industry-modules.healthcare.disabled', []));

        return DB::table('module_registry')
            ->where('status', 'active')
            ->orderBy('id')
            ->pluck('key')
            ->reject(fn ($key) => $service->isCoreModule($key) || isset($disabled[$key]))
            ->take($count)
            ->values()
            ->all();
    }

    private function expectedCategory(string $key, string $wanted): string
    {
        $disabled = array_flip(config('industry-modules.healthcare.disabled', []));

        if (isset($disabled[$key])) {
            return 'hidden';
        }
        if ($this->service()->isCoreModule($key)) {
            return 'mandatory';
        }

        return $wanted;
    }

    public function test_matrix_payload_round_trips_categories(): void
    {
        $this->loginAsAdmin();

        $package = SubscriptionPackage::where('slug', 'premium')->first()
            ?: SubscriptionPackage::orderBy('id')->first();
        $keys = $this->categoryFixtureKeys(3);
        $this->assertCount(3, $keys);

        $wanted = [$keys[0] => 'mandatory', $keys[1] => 'optional', $keys[2] => 'hidden'];
        $payload = ['modules' => []];
        foreach ($wanted as $key => $category) {
            $payload['modules'][] = ['module_key' => $key, 'category' => $category];
        }

        $this->put(route('admin.package-industries.update-modules', [
            'package' => $package->id,
            'industry' => 'healthcare',
        ]), $payload)->assertRedirect(route('admin.package-industries.show-modules', [
            'package' => $package->id,
            'industry' => 'healthcare',
        ]));

        foreach ($wanted as $key => $category) {
            $row = DB::table('package_industry_modules')
                ->where('package_id', $package->id)
                ->where('industry_key', 'healthcare')
                ->where('module_key', $key)
                ->first();
            $this->assertNotNull($row, "missing row for {$key}");
            $expected = $this->expectedCategory($key, $category);
            $this->assertSame($expected, $row->category, "category mismatch for {$key}");
            $this->assertEquals(
                in_array($expected, ['mandatory', 'default'], true) ? 1 : 0,
                (int) $row->enabled,
                "enabled flag mismatch for {$key}"
            );
        }
    }

    public function test_legacy_flat_payload_maps_to_default_category(): void
    {
        $this->loginAsAdmin();

        $package = SubscriptionPackage::where('slug', 'premium')->first()
            ?: SubscriptionPackage::orderBy('id')->first();
        $keys = $this->categoryFixtureKeys(1);
        $this->assertCount(1, $keys);

        $this->put(route('admin.package-industries.update-modules', [
            'package' => $package->id,
            'industry' => 'healthcare',
        ]), [
            'modules' => [$keys[0]],
        ])->assertRedirect();

        $row = DB::table('package_industry_modules')
            ->where('package_id', $package->id)
            ->where('industry_key', 'healthcare')
            ->where('module_key', $keys[0])
            ->first();

        $this->assertNotNull($row);
        $this->assertSame($this->expectedCategory($keys[0], 'default'), $row->category);
    }

    public function test_core_module_is_locked_to_mandatory(): void
    {
        $this->loginAsAdmin();

        $package = SubscriptionPackage::orderBy('id')->first();
        $coreKey = DB::table('module_registry')
            ->where('status', 'active')
            ->orderBy('id')
            ->pluck('key')
            ->first(fn ($key) => $this->service()->isCoreModule($key));
        $this->assertNotEmpty($coreKey);

        $this->put(route('admin.package-industries.update-modules', [
            'package' => $package->id,
            'industry' => 'healthcare',
        ]), [
            'modules' => [['module_key' => $coreKey, 'category' => 'hidden']],
        ])->assertRedirect();

        $row = DB::table('package_industry_modules')
            ->where('package_id', $package->id)
            ->where('industry_key', 'healthcare')
            ->where('module_key', $coreKey)
            ->first();

        $this->assertNotNull($row);
        $this->assertSame('mandatory', $row->category);
        $this->assertEquals(1, (int) $row->enabled);
    }

    public function test_resolver_honors_mandatory_default_optional_hidden(): void
    {
        $institute = $this->healthcareInstitute('premium');
        $packageId = (int) $institute->package_id;

        DB::table('package_industries')->insert([
            'package_id' => $packageId,
            'industry_key' => 'healthcare',
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $keys = $this->categoryFixtureKeys(3);
        $this->assertCount(3, $keys);

        $now = now();
        foreach ([$keys[0] => 'mandatory', $keys[1] => 'optional', $keys[2] => 'hidden'] as $key => $category) {
            DB::table('package_industry_modules')->insert([
                'package_id' => $packageId,
                'industry_key' => 'healthcare',
                'module_key' => $key,
                'enabled' => in_array($category, ['mandatory', 'default'], true),
                'category' => $category,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $this->service()->flushCache($institute->id);
        $enabled = $this->service()->getEnabledModules($institute);

        $this->assertContains($keys[0], $enabled, 'mandatory must resolve on');
        $this->assertNotContains($keys[1], $enabled, 'optional must stay off');
        $this->assertNotContains($keys[2], $enabled, 'hidden must stay off');
    }

    // ── Locked (upgrade) ──────────────────────────────────────────

    public function test_package_tier_rank_orders_slugs(): void
    {
        $this->assertSame(0, \App\Services\ModuleAccessService::packageTierRank('free'));
        $this->assertSame(1, \App\Services\ModuleAccessService::packageTierRank('medical_starter'));
        $this->assertSame(1, \App\Services\ModuleAccessService::packageTierRank('basic'));
        $this->assertSame(2, \App\Services\ModuleAccessService::packageTierRank('medical_growth'));
        $this->assertSame(3, \App\Services\ModuleAccessService::packageTierRank('medical_enterprise'));
        $this->assertSame(3, \App\Services\ModuleAccessService::packageTierRank('premium'));
    }

    public function test_upgrade_locked_detects_higher_package_modules(): void
    {
        $starter = SubscriptionPackage::where('slug', 'medical_starter')->firstOrFail();
        $growth = SubscriptionPackage::where('slug', 'medical_growth')->firstOrFail();
        $keys = $this->categoryFixtureKeys(2);
        $this->assertCount(2, $keys);

        $now = now();
        DB::table('package_industry_modules')->insert([
            'package_id' => $starter->id, 'industry_key' => 'healthcare',
            'module_key' => $keys[0], 'enabled' => false, 'category' => 'hidden',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('package_industry_modules')->insert([
            'package_id' => $growth->id, 'industry_key' => 'healthcare',
            'module_key' => $keys[0], 'enabled' => true, 'category' => 'default',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('package_industry_modules')->insert([
            'package_id' => $starter->id, 'industry_key' => 'healthcare',
            'module_key' => $keys[1], 'enabled' => true, 'category' => 'default',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $locked = $this->service()->upgradeUnlockedBy($starter->id, 'healthcare');

        $this->assertArrayHasKey($keys[0], $locked, 'off-here + on-in-growth must lock');
        $this->assertContains('medical_growth', $locked[$keys[0]]);
        $this->assertArrayNotHasKey($keys[1], $locked, 'on-here must not lock');
    }

    public function test_modules_page_shows_locked_badge(): void
    {
        $this->loginAsAdmin();

        $starter = SubscriptionPackage::where('slug', 'medical_starter')->firstOrFail();
        $growth = SubscriptionPackage::where('slug', 'medical_growth')->firstOrFail();
        $keys = $this->categoryFixtureKeys(1);
        $this->assertCount(1, $keys);

        $now = now();
        DB::table('package_industry_modules')->insert([
            'package_id' => $growth->id, 'industry_key' => 'healthcare',
            'module_key' => $keys[0], 'enabled' => true, 'category' => 'default',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->get(route('admin.package-industries.show-modules', [
            'package' => $starter->id,
            'industry' => 'healthcare',
        ]))->assertOk()->assertSee('Locked', false);
    }
}
