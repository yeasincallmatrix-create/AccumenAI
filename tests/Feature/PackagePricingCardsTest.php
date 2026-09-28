<?php

namespace Tests\Feature;

use App\Models\PlatformAdmin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PackagePricingCardsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_page_requires_platform_admin(): void
    {
        $this->get(route('admin.package-industries.pricing-cards'))->assertRedirect();
    }

    public function test_page_shows_four_cards_with_dropdowns(): void
    {
        $this->loginAsAdmin();

        $page = $this->get(route('admin.package-industries.pricing-cards', ['industry' => 'healthcare']));
        $page->assertOk()
            ->assertSee('Package pricing card', false)
            ->assertSee('name="industry"', false)
            ->assertSee('name="country"', false)
            ->assertSee('FREE', false)
            ->assertSee('medical_enterprise', false);
    }

    public function test_page_accepts_country_and_unknown_industry(): void
    {
        $this->loginAsAdmin();

        $this->get(route('admin.package-industries.pricing-cards', ['industry' => 'healthcare', 'country' => 'XX']))
            ->assertOk();

        $this->get(route('admin.package-industries.pricing-cards', ['industry' => 'does-not-exist']))
            ->assertOk();
    }

    public function test_customer_view_hides_admin_actions_and_shows_trial(): void
    {
        $this->loginAsAdmin();

        $package = \App\Models\SubscriptionPackage::where('slug', 'medical_enterprise')->first()
            ?? \App\Models\SubscriptionPackage::where('slug', 'premium')->firstOrFail();

        \Illuminate\Support\Facades\DB::table('package_industries')->updateOrInsert(
            ['package_id' => $package->id, 'industry_key' => 'healthcare'],
            ['is_active' => true, 'sort_order' => 3, 'trial_days' => 14, 'created_at' => now(), 'updated_at' => now()]
        );

        $page = $this->get(route('admin.package-industries.pricing-cards', ['industry' => 'healthcare', 'view' => 'customer']));
        $page->assertOk()
            ->assertSee('Admin view', false)
            ->assertSee('14-day free trial', false)
            ->assertDontSee('Configure Modules', false);
    }

    private function loginAsAdmin(): PlatformAdmin
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'first_name' => 'Pricing',
            'last_name' => 'Cards',
            'email' => 'pricing-cards-'.uniqid().'@example.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin, 'platform_admin');

        return $admin;
    }
}
