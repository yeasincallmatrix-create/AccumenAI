<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\SubscriptionPackage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 8c (B17): the testing-only auto-PREMIUM hook is flag-controlled
 * via config('testing.auto_premium', true).
 *
 * Default true = backward compatible (legacy tests keep working).
 * Opt out per-test with config(['testing.auto_premium' => false])
 * to preserve an explicit null package_id.
 */
class TestingAutoPremiumFlagTest extends TestCase
{
    use DatabaseTransactions;

    public function test_auto_premium_defaults_to_true(): void
    {
        $this->assertTrue(config('testing.auto_premium'));

        $premiumId = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['premium'])->value('id');
        $this->assertNotNull($premiumId, 'PREMIUM package must be seeded');

        $inst = Institute::create([
            'name' => 'AutoPremium Default '.uniqid(),
            'slug' => 'autoprem-default-'.uniqid(),
            'status' => 'active',
            'industry' => 'retail',
            'country' => 'Bangladesh',
        ]);

        $this->assertSame((int) $premiumId, (int) $inst->package_id);
    }

    public function test_auto_premium_can_be_disabled(): void
    {
        config(['testing.auto_premium' => false]);

        $inst = Institute::create([
            'name' => 'AutoPremium Disabled '.uniqid(),
            'slug' => 'autoprem-disabled-'.uniqid(),
            'status' => 'active',
            'package_id' => null,
            'industry' => 'retail',
            'country' => 'Bangladesh',
        ]);

        $this->assertNull($inst->package_id);
    }
}
