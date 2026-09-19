<?php

namespace Tests\Feature;

use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use App\Models\TenantAccessDenial;
use App\Models\TenantAccessGrant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantAccessControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('tenant_access_grants')
            || ! Schema::hasTable('tenant_access_denials')) {
            $this->markTestSkipped('Required tables do not exist.');
        }

        if (FeatureRegistry::count() === 0) {
            (new \Database\Seeders\FeatureRegistrySeeder)->run();
        }
    }

    private function loginAsAdmin(): PlatformAdmin
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'first_name' => 'Access',
            'last_name' => 'Admin',
            'email' => 'access-admin-' . uniqid() . '@example.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin, 'platform_admin');

        return $admin;
    }

    private function institute(): Institute
    {
        $pkg = SubscriptionPackage::where('slug', 'free')->first();

        return Institute::create([
            'name' => 'Access Test Inst ' . uniqid(),
            'slug' => 'access-test-' . uniqid(),
            'status' => 'active',
            'package_id' => $pkg?->id,
        ]);
    }

    public function test_show_requires_platform_admin(): void
    {
        $institute = $this->institute();

        $this->get(route('admin.institutes.access', $institute))
            ->assertRedirect();
    }

    public function test_show_displays_grants_and_denials(): void
    {
        $admin = $this->loginAsAdmin();
        $institute = $this->institute();

        TenantAccessGrant::create([
            'institute_id' => $institute->id,
            'grant_type' => 'feature',
            'grant_key' => 'medical.pharmacy',
            'granted_by' => $admin->id,
            'status' => 'active',
        ]);

        TenantAccessDenial::create([
            'institute_id' => $institute->id,
            'deny_type' => 'feature',
            'deny_key' => 'medical.laboratory',
            'denied_by' => $admin->id,
            'reason' => 'Test denial',
            'status' => 'active',
        ]);

        $this->get(route('admin.institutes.access', $institute))
            ->assertOk()
            ->assertSee('medical.pharmacy')
            ->assertSee('medical.laboratory');
    }

    public function test_add_grant_creates_record(): void
    {
        $this->loginAsAdmin();
        $institute = $this->institute();

        $this->post(route('admin.institutes.grants.store', $institute), [
            'grant_type' => 'feature',
            'grant_key' => 'medical.pharmacy',
            'reason' => 'Pilot access',
        ])->assertRedirect();

        $this->assertDatabaseHas('tenant_access_grants', [
            'institute_id' => $institute->id,
            'grant_type' => 'feature',
            'grant_key' => 'medical.pharmacy',
            'status' => 'active',
        ]);
    }

    public function test_add_grant_flushes_feature_cache(): void
    {
        $this->loginAsAdmin();
        $institute = $this->institute();

        Cache::put('feature_access:' . $institute->id, ['stale' => true], 3600);

        $this->post(route('admin.institutes.grants.store', $institute), [
            'grant_type' => 'feature',
            'grant_key' => 'medical.pharmacy',
        ])->assertRedirect();

        $this->assertFalse(Cache::has('feature_access:' . $institute->id));
    }

    public function test_revoke_grant_sets_status(): void
    {
        $admin = $this->loginAsAdmin();
        $institute = $this->institute();

        $grant = TenantAccessGrant::create([
            'institute_id' => $institute->id,
            'grant_type' => 'feature',
            'grant_key' => 'medical.pharmacy',
            'granted_by' => $admin->id,
            'status' => 'active',
        ]);

        $this->delete(route('admin.institutes.grants.revoke', [$institute, $grant]))
            ->assertRedirect();

        $this->assertDatabaseHas('tenant_access_grants', [
            'id' => $grant->id,
            'status' => 'revoked',
        ]);
    }

    public function test_add_denial_creates_record(): void
    {
        $this->loginAsAdmin();
        $institute = $this->institute();

        $this->post(route('admin.institutes.denials.store', $institute), [
            'deny_type' => 'feature',
            'deny_key' => 'medical.laboratory',
            'reason' => 'Abuse prevention',
        ])->assertRedirect();

        $this->assertDatabaseHas('tenant_access_denials', [
            'institute_id' => $institute->id,
            'deny_type' => 'feature',
            'deny_key' => 'medical.laboratory',
            'status' => 'active',
        ]);
    }

    public function test_add_denial_requires_reason(): void
    {
        $this->loginAsAdmin();
        $institute = $this->institute();

        $this->post(route('admin.institutes.denials.store', $institute), [
            'deny_type' => 'feature',
            'deny_key' => 'medical.laboratory',
        ])->assertSessionHasErrors('reason');
    }

    public function test_lift_denial_sets_status(): void
    {
        $admin = $this->loginAsAdmin();
        $institute = $this->institute();

        $denial = TenantAccessDenial::create([
            'institute_id' => $institute->id,
            'deny_type' => 'feature',
            'deny_key' => 'medical.laboratory',
            'denied_by' => $admin->id,
            'reason' => 'Temporary block',
            'status' => 'active',
        ]);

        $this->delete(route('admin.institutes.denials.lift', [$institute, $denial]))
            ->assertRedirect();

        $this->assertDatabaseHas('tenant_access_denials', [
            'id' => $denial->id,
            'status' => 'lifted',
        ]);
    }
}
