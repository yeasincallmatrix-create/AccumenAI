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

class PackageTrialTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasColumn('package_industries', 'trial_days')) {
            $this->markTestSkipped('trial_days column missing.');
        }

        DB::table('package_industries')->delete();
    }

    public function test_admin_can_save_trial_days(): void
    {
        $this->loginAsAdmin();

        $package = SubscriptionPackage::where('slug', 'medical_enterprise')->first()
            ?? SubscriptionPackage::where('slug', 'premium')->firstOrFail();

        $this->put(route('admin.package-industries.update'), [
            'industry' => 'healthcare',
            'packages' => [$package->id],
            'trial_days' => [$package->id => 14],
        ])->assertRedirect(route('admin.package-industries.index', ['industry' => 'healthcare']));

        $this->assertSame(14, (int) DB::table('package_industries')
            ->where('package_id', $package->id)
            ->where('industry_key', 'healthcare')
            ->value('trial_days'));

        $this->get(route('admin.package-industries.index', ['industry' => 'healthcare']))
            ->assertOk()->assertSee('14-day trial', false);
    }

    public function test_trial_grants_tier_access_while_entitled_free(): void
    {
        $service = app(ModuleAccessService::class);
        $free = SubscriptionPackage::where('slug', 'free')->firstOrFail();
        $tier = SubscriptionPackage::where('slug', 'medical_enterprise')->first()
            ?? SubscriptionPackage::where('slug', 'premium')->firstOrFail();

        $this->offerTrial($tier->id, 14);

        $institute = Institute::create([
            'name' => 'Trial HC '.uniqid(),
            'slug' => 'trial-hc-'.uniqid(),
            'industry' => 'healthcare',
            'status' => 'active',
            'package_id' => $free->id,
            'country_id' => null,
        ]);

        $trial = $service->startTrial($institute->fresh(), $tier->id);

        // Entitled as free: package_id untouched, price zero.
        $this->assertSame($free->id, $institute->fresh()->package_id);
        $this->assertSame('trial', $trial->billing_cycle);
        $this->assertSame(0, (int) $trial->price_paid);
        $this->assertEquals(now()->addDays(14)->toDateString(), $trial->end_date);

        // ...but tier access resolves.
        $service->flushCache($institute->id);
        $service->flushFeatureCache($institute->id);
        $this->assertTrue($service->isEnabled($institute->fresh(), 'medical.pharmacy'));
        $this->assertTrue($service->isFeatureEnabled($institute->fresh(), 'medical.pharmacy'));
        $this->assertSame(14, $service->trialDaysLeft($institute->fresh()));
    }

    public function test_expired_trial_falls_back_to_free(): void
    {
        $service = app(ModuleAccessService::class);
        $free = SubscriptionPackage::where('slug', 'free')->firstOrFail();
        $tier = SubscriptionPackage::where('slug', 'medical_enterprise')->first()
            ?? SubscriptionPackage::where('slug', 'premium')->firstOrFail();

        $this->offerTrial($tier->id, 14);

        $institute = Institute::create([
            'name' => 'Expired Trial '.uniqid(),
            'slug' => 'expired-trial-'.uniqid(),
            'industry' => 'healthcare',
            'status' => 'active',
            'package_id' => $free->id,
            'country_id' => null,
        ]);

        DB::table('institute_subscriptions')->insert([
            'institute_id' => $institute->id,
            'package_id' => $tier->id,
            'billing_cycle' => 'trial',
            'price_paid' => 0,
            'start_date' => now()->subDays(20)->toDateString(),
            'end_date' => now()->subDay()->toDateString(),
            'status' => 'active',
            'created_at' => now(),
        ]);

        $service->flushCache($institute->id);
        $service->flushFeatureCache($institute->id);
        $this->assertFalse($service->isEnabled($institute->fresh(), 'medical.pharmacy'));
        $this->assertFalse($service->isFeatureEnabled($institute->fresh(), 'medical.pharmacy'));
        $this->assertNull($service->trialDaysLeft($institute->fresh()));
    }

    public function test_trial_rejected_without_trial_days(): void
    {
        $service = app(ModuleAccessService::class);
        $free = SubscriptionPackage::where('slug', 'free')->firstOrFail();
        $tier = SubscriptionPackage::where('slug', 'premium')->firstOrFail();

        DB::table('package_industries')->insert([
            'package_id' => $tier->id,
            'industry_key' => 'healthcare',
            'is_active' => true,
            'sort_order' => 0,
            'trial_days' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $institute = Institute::create([
            'name' => 'No Trial '.uniqid(),
            'slug' => 'no-trial-'.uniqid(),
            'industry' => 'healthcare',
            'status' => 'active',
            'package_id' => $free->id,
            'country_id' => null,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $service->startTrial($institute, $tier->id);
    }

    private function offerTrial(int $packageId, int $days): void
    {
        DB::table('package_industries')->insert([
            'package_id' => $packageId,
            'industry_key' => 'healthcare',
            'is_active' => true,
            'sort_order' => 0,
            'trial_days' => $days,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Tier feature base (mirrors PackageFeatureSeeder for the tier).
        foreach (DB::table('feature_registry')->where('status', 'active')->pluck('feature_key') as $featureKey) {
            DB::table('package_features')->updateOrInsert(
                ['package_id' => $packageId, 'feature_key' => $featureKey],
                ['enabled' => true, 'created_at' => now(), 'updated_at' => now()]
            );
        }
    }

    private function loginAsAdmin(): PlatformAdmin
    {
        $admin = PlatformAdmin::firstOrReuseForTests([
            'first_name' => 'Trial',
            'last_name' => 'Tester',
            'email' => 'trial-'.uniqid().'@example.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin, 'platform_admin');

        return $admin;
    }
}
