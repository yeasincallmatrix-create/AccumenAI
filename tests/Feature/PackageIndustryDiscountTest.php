<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackageIndustryDiscountTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('package_industries', 'discount_percent')
            || ! Schema::hasColumn('package_industries', 'discount_ends_at')) {
            $this->markTestSkipped('discount columns missing.');
        }

        DB::table('package_industries')->delete();
        DB::table('package_country_prices')->delete();
    }

    public function test_admin_can_save_discount_with_countdown(): void
    {
        $this->loginAsAdmin();

        $package = SubscriptionPackage::where('slug', 'premium')->firstOrFail();
        $endsAt = now()->addDays(5)->toDateString();

        $this->put(route('admin.package-industries.update'), [
            'industry' => 'healthcare',
            'packages' => [$package->id],
            'discount_percent' => [$package->id => 20],
            'discount_ends_at' => [$package->id => $endsAt],
        ])->assertRedirect(route('admin.package-industries.index', ['industry' => 'healthcare']));

        $row = DB::table('package_industries')
            ->where('package_id', $package->id)
            ->where('industry_key', 'healthcare')
            ->first();

        $this->assertNotNull($row);
        $this->assertEquals(20.0, (float) $row->discount_percent);
        $this->assertEquals($endsAt, $row->discount_ends_at);

        $page = $this->get(route('admin.package-industries.index', ['industry' => 'healthcare']));
        $page->assertOk()->assertSee('5 days left', false);
    }

    public function test_discount_reduces_resolved_price(): void
    {
        $institute = $this->healthcareInstitute('premium');
        $package = SubscriptionPackage::where('slug', 'premium')->firstOrFail();

        DB::table('package_industries')->insert([
            'package_id' => $package->id,
            'industry_key' => 'healthcare',
            'is_active' => true,
            'sort_order' => 0,
            'price_monthly' => 1000.00,
            'price_yearly' => 10000.00,
            'discount_percent' => 10,
            'discount_ends_at' => now()->addDays(3)->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $price = app(ModuleAccessService::class)->resolveScopedPrice($institute);

        $this->assertEquals(900.00, $price['monthly']);
        $this->assertEquals(9000.00, $price['yearly']);
    }

    public function test_expired_discount_is_ignored(): void
    {
        $institute = $this->healthcareInstitute('premium');
        $package = SubscriptionPackage::where('slug', 'premium')->firstOrFail();

        DB::table('package_industries')->insert([
            'package_id' => $package->id,
            'industry_key' => 'healthcare',
            'is_active' => true,
            'sort_order' => 0,
            'price_monthly' => 1000.00,
            'price_yearly' => 10000.00,
            'discount_percent' => 50,
            'discount_ends_at' => now()->subDay()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $price = app(ModuleAccessService::class)->resolveScopedPrice($institute);

        $this->assertEquals(1000.00, $price['monthly']);
        $this->assertEquals(10000.00, $price['yearly']);

        $this->loginAsAdmin();
        $page = $this->get(route('admin.package-industries.index', ['industry' => 'healthcare']));
        $page->assertOk()->assertSee('Expired', false);
    }

    public function test_discount_validation_rejects_over_100(): void
    {
        $this->loginAsAdmin();

        $package = SubscriptionPackage::where('slug', 'premium')->firstOrFail();

        $this->put(route('admin.package-industries.update'), [
            'industry' => 'healthcare',
            'packages' => [$package->id],
            'discount_percent' => [$package->id => 150],
        ])->assertSessionHasErrors('discount_percent.'.$package->id);
    }

    private function loginAsAdmin(): PlatformAdmin
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'first_name' => 'Discount',
            'last_name' => 'Tester',
            'email' => 'discount-'.uniqid().'@example.com',
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
            'name' => 'Discount HC '.uniqid(),
            'slug' => 'discount-hc-'.uniqid(),
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
}
