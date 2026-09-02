<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SaasEnterpriseTest extends TestCase
{
    use DatabaseTransactions;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'saas-enterprise-admin-' . uniqid() . '@test.local',
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
        ]);
    }

    private function institute(string $package = 'FREE'): Institute
    {
        \App\Support\TenantContext::clear();
        $pkg = SubscriptionPackage::whereRaw('LOWER(slug)=?', [strtolower($package)])->firstOrFail();
        return Institute::create([
            'name' => 'Enterprise Test ' . uniqid(),
            'slug' => 'enterprise-test-' . uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'industry' => 'education',
            'sub_industry' => 'school',
            'country' => 'Bangladesh',
        ]);
    }

    public function test_saas_admin_dashboard_renders(): void
    {
        $this->institute('FREE');
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('saas.admin.dashboard'))
            ->assertOk()
            ->assertSee('SaaS Subscription Dashboard')
            ->assertSee('Total Institutes');
    }

    public function test_saas_admin_usage_renders(): void
    {
        $this->institute('FREE');
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('saas.admin.usage'))
            ->assertOk()
            ->assertSee('Module Usage Analytics');
    }

    public function test_saas_admin_billing_renders(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('saas.admin.billing'))
            ->assertOk()
            ->assertSee('Billing & Revenue Report')
            ->assertSee('Success Rate');
    }

    public function test_saas_admin_limits_renders(): void
    {
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('saas.admin.limits'))
            ->assertOk()
            ->assertSee('Package Feature Limits Matrix');
    }

    public function test_subscription_packages_available(): void
    {
        $inst = $this->institute('FREE');
        $role = \App\Models\Role::where('slug', 'institute-owner')->firstOrFail();
        \App\Support\TenantContext::clear();
        $user = \App\Models\InstituteUser::create([
            'institute_id' => $inst->id,
            'role_id' => $role->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'enterprise-pkg-' . uniqid() . '@test.local',
            'phone' => '017' . rand(10000000, 99999999),
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
        ]);

        $this->actingAs($user, 'institute_user')
            ->get(route('saas.packages'))
            ->assertOk()
            ->assertSee('SaaS Subscription Packages');
    }
}
