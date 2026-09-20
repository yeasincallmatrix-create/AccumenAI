<?php

namespace Tests\Feature;

use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\PackageFeature;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use App\Models\TenantAccessGrant;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 7b (B76): grants respect Gate 1 (parent module must be enabled).
 *
 * A super-admin grant is additive but never bypasses the module gate —
 * consistent with institute_feature_overrides. A grant for a feature
 * whose parent module is disabled has no effect.
 */
class GrantGateOneTest extends TestCase
{
    use DatabaseTransactions;

    private ModuleAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('tenant_access_grants')
            || ! Schema::hasTable('feature_registry')
            || ! Schema::hasTable('package_features')) {
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

    private function admin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'first_name' => 'GateOne',
            'last_name' => 'Admin',
            'email' => 'gateone-admin-'.uniqid().'@example.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * Free package has no medical module, so Gate 1 blocks medical
     * features regardless of grants.
     */
    private function makeFreeInstitute(): Institute
    {
        $pkg = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->firstOrFail();

        $instId = DB::table('institutes')->insertGetId([
            'name' => 'GateOne Test '.uniqid(),
            'slug' => 'gateone-test-'.uniqid(),
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

    public function test_feature_grant_respects_disabled_parent_module(): void
    {
        $inst = $this->makeFreeInstitute();
        $this->assertFalse($this->service->isEnabled($inst, 'medical'));

        TenantAccessGrant::create([
            'institute_id' => $inst->id,
            'grant_type' => 'feature',
            'grant_key' => 'medical.pharmacy',
            'granted_by' => $this->admin()->id,
            'status' => 'active',
        ]);

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
    }

    public function test_module_grant_respects_disabled_parent_module(): void
    {
        $inst = $this->makeFreeInstitute();
        $this->assertFalse($this->service->isEnabled($inst, 'medical'));

        TenantAccessGrant::create([
            'institute_id' => $inst->id,
            'grant_type' => 'module',
            'grant_key' => 'medical',
            'granted_by' => $this->admin()->id,
            'status' => 'active',
        ]);

        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.pharmacy'));
        $this->assertFalse($this->service->isFeatureEnabled($inst, 'medical.laboratory'));
    }
}
