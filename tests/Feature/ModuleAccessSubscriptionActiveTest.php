<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteSubscription;
use App\Models\PackageModule;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * SEC-01: isSubscriptionActive() must be fail-closed and read the real
 * institute_subscriptions columns (status + end_date).
 */
class ModuleAccessSubscriptionActiveTest extends TestCase
{
    use DatabaseTransactions;

    private SubscriptionPackage $freePkg;

    private SubscriptionPackage $paidPkg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->freePkg = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->firstOrFail();
        $this->paidPkg = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['basic'])->firstOrFail();
    }

    private function makeInstituteWithSubscription(string $status, string $endDate): Institute
    {
        $institute = Institute::create([
            'name' => 'SEC01 '.uniqid(),
            'slug' => 'sec01-'.uniqid(),
            'status' => 'active',
            'package_id' => $this->paidPkg->id,
        ]);

        InstituteSubscription::create([
            'institute_id' => $institute->id,
            'package_id' => $this->paidPkg->id,
            'billing_cycle' => 'monthly',
            'price_paid' => 0,
            'start_date' => now()->subMonth()->toDateString(),
            'end_date' => $endDate,
            'status' => $status,
        ]);

        return $institute->fresh();
    }

    private function subscriptionActive(Institute $institute): bool
    {
        $method = new \ReflectionMethod(ModuleAccessService::class, 'isSubscriptionActive');
        $method->setAccessible(true);

        return $method->invoke(app(ModuleAccessService::class), $institute);
    }

    /**
     * A module the paid package enables but FREE does not (runtime discriminator).
     * Industry modules (education/medical/training_center) are excluded because
     * AppServiceProvider::syncIndustryModule() writes per-institute overrides
     * for them on Institute::created, independent of the package base.
     */
    private function paidOnlyModule(): string
    {
        $industryModules = ['education', 'medical', 'training_center'];
        $paidKeys = PackageModule::where('package_id', $this->paidPkg->id)
            ->where('enabled', true)->pluck('module_key')->toArray();
        $freeKeys = PackageModule::where('package_id', $this->freePkg->id)
            ->where('enabled', true)->pluck('module_key')->toArray();
        $paidOnly = array_values(array_diff(array_diff($paidKeys, $freeKeys), $industryModules));
        $this->assertNotEmpty($paidOnly, 'Fixture needs a paid-only module to prove FREE fallback.');

        return $paidOnly[0];
    }

    public function test_active_subscription_with_past_end_date_is_inactive_and_falls_back_to_free(): void
    {
        $institute = $this->makeInstituteWithSubscription('active', now()->subDay()->toDateString());

        $this->assertFalse($this->subscriptionActive($institute));

        $service = app(ModuleAccessService::class);
        $resolved = $service->resolveEnabled($institute);
        $this->assertFalse($resolved[$this->paidOnlyModule()] ?? false);

        // Same package base as a FREE institute.
        $freeInstitute = Institute::create([
            'name' => 'SEC01 Free '.uniqid(),
            'slug' => 'sec01-free-'.uniqid(),
            'status' => 'active',
            'package_id' => $this->freePkg->id,
        ]);
        $this->assertSame($service->resolveEnabled($freeInstitute->fresh()), $resolved);
    }

    public function test_active_subscription_with_future_end_date_is_active(): void
    {
        $institute = $this->makeInstituteWithSubscription('active', now()->addMonth()->toDateString());

        $this->assertTrue($this->subscriptionActive($institute));
        $resolved = app(ModuleAccessService::class)->resolveEnabled($institute);
        $this->assertTrue($resolved[$this->paidOnlyModule()] ?? false);
    }

    public function test_cancelled_subscription_is_inactive(): void
    {
        $institute = $this->makeInstituteWithSubscription('cancelled', now()->addMonth()->toDateString());

        $this->assertFalse($this->subscriptionActive($institute));
    }

    public function test_expired_subscription_is_inactive(): void
    {
        $institute = $this->makeInstituteWithSubscription('expired', now()->addMonth()->toDateString());

        $this->assertFalse($this->subscriptionActive($institute));
    }
}
