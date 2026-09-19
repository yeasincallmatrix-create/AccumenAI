<?php

namespace Tests\Feature;

use App\Models\FeatureRegistry;
use App\Models\Institute;
use App\Models\InstituteFeatureOverride;
use App\Models\InstituteUser;
use App\Models\PackageFeature;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Models\SubscriptionPackage;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InstituteFeatureAccessViewTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('feature_registry') || ! Schema::hasTable('package_features')) {
            $this->markTestSkipped('feature_registry or package_features table does not exist.');
        }

        if (FeatureRegistry::count() === 0) {
            (new \Database\Seeders\FeatureRegistrySeeder)->run();
        }
        if (PackageFeature::count() === 0) {
            (new \Database\Seeders\PackageFeatureSeeder)->run();
        }
    }

    protected function createInstitute(string $packageSlug = 'advanced'): Institute
    {
        $pkg = SubscriptionPackage::where('slug', $packageSlug)->first();
        if (! $pkg) {
            $this->markTestSkipped("Package '{$packageSlug}' not found.");
        }

        return Institute::create([
            'name'       => 'Feature Access Test ' . uniqid(),
            'slug'       => 'feature-access-' . uniqid(),
            'status'     => 'active',
            'package_id' => $pkg->id,
            'industry'   => 'healthcare',
        ]);
    }

    protected function createOwner(Institute $inst): InstituteUser
    {
        return InstituteUser::create([
            'institute_id'    => $inst->id,
            'role_id'         => Role::where('slug', 'institute-owner')->firstOrFail()->id,
            'first_name'      => 'Test',
            'last_name'       => 'Owner',
            'email'           => uniqid() . '@test.test',
            'phone'           => '017' . mt_rand(10000000, 99999999),
            'password_hash'   => bcrypt('secret'),
            'status'          => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_institute_admin_sees_feature_access_page(): void
    {
        $inst = $this->createInstitute();
        $owner = $this->createOwner($inst);

        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user');

        $response = $this->get(route('settings.features'));
        $response->assertOk();
        $response->assertSee('Feature Access');
        $response->assertSee($inst->name);
    }

    public function test_institute_admin_sees_only_own_institute_features(): void
    {
        $instA = $this->createInstitute('advanced');
        $ownerA = $this->createOwner($instA);

        $instB = $this->createInstitute('basic');
        $ownerB = $this->createOwner($instB);

        InstituteFeatureOverride::create([
            'institute_id'  => $instA->id,
            'feature_key'   => 'medical.pharmacy',
            'enabled'       => true,
            'overridden_by' => PlatformAdmin::first()?->id,
            'reason'        => 'Granted for A',
        ]);

        TenantContext::set($instA->id);
        $this->actingAs($ownerA, 'institute_user');

        $response = $this->get(route('settings.features'));
        $response->assertOk();
        $response->assertSee('Granted for A');
        $response->assertDontSee('Granted for B');

        TenantContext::clear();
    }

    public function test_institute_admin_sees_override_badge(): void
    {
        $inst = $this->createInstitute();
        $owner = $this->createOwner($inst);

        InstituteFeatureOverride::create([
            'institute_id'  => $inst->id,
            'feature_key'   => 'medical.pharmacy',
            'enabled'       => true,
            'overridden_by' => PlatformAdmin::first()?->id,
        ]);

        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user');

        $response = $this->get(route('settings.features'));
        $response->assertOk();
        $response->assertSee('Override granted');
    }

    public function test_guest_and_platform_admin_cannot_access_institute_features(): void
    {
        $inst = $this->createInstitute();

        TenantContext::set($inst->id);

        $response = $this->get(route('settings.features'));
        $response->assertStatus(302);

        $admin = PlatformAdmin::firstOrReuseForTests([
            'first_name'       => 'Test',
            'last_name'        => 'Admin',
            'email'            => 'test-admin-' . uniqid() . '@example.com',
            'password_hash'    => bcrypt('password'),
            'status'           => 'active',
            'email_verified_at' => now(),
        ]);
        $this->actingAs($admin, 'platform_admin');

        $response = $this->get(route('settings.features'));
        $response->assertStatus(302);

        TenantContext::clear();
    }

    public function test_institute_admin_cannot_toggle_features(): void
    {
        $inst = $this->createInstitute();
        $owner = $this->createOwner($inst);

        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user');

        $response = $this->post(route('settings.features') . '/medical.pharmacy/toggle');
        $response->assertStatus(404);

        TenantContext::clear();
    }

    public function test_view_shows_package_default_source_badge(): void
    {
        $inst = $this->createInstitute('advanced');
        $owner = $this->createOwner($inst);

        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user');

        $response = $this->get(route('settings.features'));
        $response->assertOk();
        $response->assertSee('Package default');
        $response->assertDontSee('Override granted');
    }

    public function test_view_shows_override_denied_badge(): void
    {
        $inst = $this->createInstitute('advanced');
        $owner = $this->createOwner($inst);

        InstituteFeatureOverride::create([
            'institute_id'  => $inst->id,
            'feature_key'   => 'medical.pharmacy',
            'enabled'       => false,
            'overridden_by' => PlatformAdmin::first()?->id,
            'reason'        => 'Denied for testing',
        ]);

        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user');

        $response = $this->get(route('settings.features'));
        $response->assertOk();
        $response->assertSee('Override denied');
        $response->assertSee('Denied for testing');
    }
}
