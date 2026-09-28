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

class PackageCountryScopeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('package_country_prices', 'discount_percent')
            || ! Schema::hasColumn('package_country_prices', 'trial_days')) {
            $this->markTestSkipped('country discount/trial columns missing.');
        }

        DB::table('package_industries')->delete();
        DB::table('package_country_prices')->delete();
    }

    public function test_country_discount_beats_industry_discount(): void
    {
        $institute = $this->bdInstitute('premium');
        $package = SubscriptionPackage::where('slug', 'premium')->firstOrFail();

        DB::table('package_industries')->insert([
            'package_id' => $package->id, 'industry_key' => 'healthcare', 'is_active' => true,
            'sort_order' => 0, 'price_monthly' => 1000.00, 'price_yearly' => 10000.00,
            'discount_percent' => 10, 'discount_ends_at' => now()->addDays(5)->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('package_country_prices')->insert([
            'package_id' => $package->id, 'country_code' => 'BD', 'currency_code' => 'BDT',
            'price_monthly' => 1000.00, 'price_yearly' => 10000.00,
            'discount_percent' => 25, 'discount_ends_at' => now()->addDays(5)->toDateString(),
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $price = app(ModuleAccessService::class)->resolveScopedPrice($institute);

        $this->assertEquals(750.00, $price['monthly']);
        $this->assertEquals(7500.00, $price['yearly']);
        $this->assertSame('BDT', $price['currency']);
    }

    public function test_country_price_used_without_country_discount(): void
    {
        $institute = $this->bdInstitute('premium');
        $package = SubscriptionPackage::where('slug', 'premium')->firstOrFail();

        DB::table('package_industries')->insert([
            'package_id' => $package->id, 'industry_key' => 'healthcare', 'is_active' => true,
            'sort_order' => 0, 'price_monthly' => 1000.00, 'price_yearly' => 10000.00,
            'discount_percent' => 10, 'discount_ends_at' => now()->addDays(5)->toDateString(),
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('package_country_prices')->insert([
            'package_id' => $package->id, 'country_code' => 'BD', 'currency_code' => 'BDT',
            'price_monthly' => 2000.00, 'price_yearly' => 20000.00,
            'discount_percent' => null, 'discount_ends_at' => null,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $price = app(ModuleAccessService::class)->resolveScopedPrice($institute);

        // Country base price with industry discount fallback.
        $this->assertEquals(1800.00, $price['monthly']);
        $this->assertEquals(18000.00, $price['yearly']);
    }

    public function test_country_trial_days_beat_industry_trial_days(): void
    {
        $service = app(ModuleAccessService::class);
        $free = SubscriptionPackage::where('slug', 'free')->firstOrFail();
        $tier = SubscriptionPackage::where('slug', 'medical_enterprise')->first()
            ?? SubscriptionPackage::where('slug', 'premium')->firstOrFail();

        DB::table('package_industries')->insert([
            'package_id' => $tier->id, 'industry_key' => 'healthcare', 'is_active' => true,
            'sort_order' => 0, 'trial_days' => 7,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('package_country_prices')->insert([
            'package_id' => $tier->id, 'country_code' => 'BD', 'currency_code' => 'BDT',
            'price_monthly' => 0, 'price_yearly' => 0,
            'trial_days' => 30,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (DB::table('feature_registry')->where('status', 'active')->pluck('feature_key') as $fk) {
            DB::table('package_features')->updateOrInsert(
                ['package_id' => $tier->id, 'feature_key' => $fk],
                ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        $institute = Institute::create([
            'name' => 'BD Trial '.uniqid(), 'slug' => 'bd-trial-'.uniqid(),
            'industry' => 'healthcare', 'status' => 'active',
            'package_id' => $free->id, 'country' => 'Bangladesh', 'country_id' => null,
        ]);

        $trial = $service->startTrial($institute->fresh(), $tier->id);
        $this->assertEquals(now()->addDays(30)->toDateString(), $trial->end_date);
        $this->assertSame(30, $service->trialDaysLeft($institute->fresh()));
    }

    public function test_country_price_save_accepts_discount_and_trial(): void
    {
        $this->loginAsAdmin();

        $package = SubscriptionPackage::where('slug', 'premium')->firstOrFail();

        $this->postJson(route('admin.package-industries.country-prices.save'), [
            'country' => 'BD',
            'prices' => [[
                'package_id' => $package->id, 'monthly' => 500, 'yearly' => 5000,
                'discount_percent' => 15, 'discount_ends_at' => now()->addDays(4)->toDateString(),
                'trial_days' => 10,
            ]],
        ])->assertOk()->assertJsonPath('ok', true);

        $row = DB::table('package_country_prices')
            ->where('package_id', $package->id)->where('country_code', 'BD')->first();

        $this->assertEquals(15.0, (float) $row->discount_percent);
        $this->assertEquals(now()->addDays(4)->toDateString(), $row->discount_ends_at);
        $this->assertSame(10, (int) $row->trial_days);
    }

    public function test_country_trial_only_save_keeps_prices(): void
    {
        $this->loginAsAdmin();

        $package = SubscriptionPackage::where('slug', 'premium')->firstOrFail();

        DB::table('package_country_prices')->insert([
            'package_id' => $package->id, 'country_code' => 'BD', 'currency_code' => 'BDT',
            'price_monthly' => 5000.00, 'price_yearly' => 50000.00,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->postJson(route('admin.package-industries.country-prices.save'), [
            'country' => 'BD',
            'prices' => [[
                'package_id' => $package->id, 'monthly' => null, 'yearly' => null,
                'trial_days' => 21,
            ]],
        ])->assertOk()->assertJsonPath('ok', true);

        $row = DB::table('package_country_prices')
            ->where('package_id', $package->id)->where('country_code', 'BD')->first();

        $this->assertEquals(5000.00, (float) $row->price_monthly);
        $this->assertEquals(50000.00, (float) $row->price_yearly);
        $this->assertSame(21, (int) $row->trial_days);
    }

    public function test_country_trial_zero_blocks_industry_trial(): void
    {
        $service = app(ModuleAccessService::class);
        $free = SubscriptionPackage::where('slug', 'free')->firstOrFail();
        $tier = SubscriptionPackage::where('slug', 'medical_enterprise')->first()
            ?? SubscriptionPackage::where('slug', 'premium')->firstOrFail();

        DB::table('package_industries')->insert([
            'package_id' => $tier->id, 'industry_key' => 'healthcare', 'is_active' => true,
            'sort_order' => 0, 'trial_days' => 14,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('package_country_prices')->insert([
            'package_id' => $tier->id, 'country_code' => 'BD', 'currency_code' => 'BDT',
            'price_monthly' => 0, 'price_yearly' => 0,
            'trial_days' => 0,
            'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $institute = Institute::create([
            'name' => 'BD Blocked '.uniqid(), 'slug' => 'bd-blocked-'.uniqid(),
            'industry' => 'healthcare', 'status' => 'active',
            'package_id' => $free->id, 'country' => 'Bangladesh', 'country_id' => null,
        ]);

        $this->assertSame(0, $service->packageTrialDays($tier->id, $institute->fresh()));

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->startTrial($institute->fresh(), $tier->id);
    }

    public function test_industry_trial_applies_without_country_row(): void
    {
        $service = app(ModuleAccessService::class);
        $tier = SubscriptionPackage::where('slug', 'medical_enterprise')->first()
            ?? SubscriptionPackage::where('slug', 'premium')->firstOrFail();

        DB::table('package_industries')->insert([
            'package_id' => $tier->id, 'industry_key' => 'healthcare', 'is_active' => true,
            'sort_order' => 0, 'trial_days' => 14,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // US has no country row -> industry trial applies.
        $institute = Institute::create([
            'name' => 'US Trial '.uniqid(), 'slug' => 'us-trial-'.uniqid(),
            'industry' => 'healthcare', 'status' => 'active',
            'package_id' => SubscriptionPackage::where('slug', 'free')->firstOrFail()->id,
            'country' => 'United States', 'country_id' => null,
        ]);

        $this->assertSame(14, $service->packageTrialDays($tier->id, $institute->fresh()));
    }

    private function bdInstitute(string $slug): Institute
    {
        $package = SubscriptionPackage::where('slug', $slug)->firstOrFail();

        $institute = Institute::create([
            'name' => 'BD Price '.uniqid(), 'slug' => 'bd-price-'.uniqid(),
            'industry' => 'healthcare', 'status' => 'active',
            'package_id' => $package->id, 'country' => 'Bangladesh', 'country_id' => null,
        ]);

        DB::table('institute_subscriptions')->insert([
            'institute_id' => $institute->id, 'package_id' => $package->id,
            'billing_cycle' => 'monthly', 'price_paid' => 0,
            'start_date' => now()->toDateString(), 'end_date' => now()->addYear()->toDateString(),
            'status' => 'active', 'created_at' => now(),
        ]);

        return $institute->fresh();
    }

    private function loginAsAdmin(): PlatformAdmin
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'first_name' => 'Country', 'last_name' => 'Scope',
            'email' => 'country-scope-'.uniqid().'@example.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active', 'email_verified_at' => now(),
        ]);

        $this->actingAs($admin, 'platform_admin');

        return $admin;
    }
}
