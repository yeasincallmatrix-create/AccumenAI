<?php

namespace Tests\Feature\Medical;

use App\Models\Country;
use App\Models\Institute;
use App\Models\Setting;
use App\Services\MedicalModuleActivator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Verify-3: seedMedicalBillingSettings() must seed defaults ONLY when the
 * key is absent. Setting::set() is an unconditional updateOrCreate, so a
 * reactivation of the medical module used to silently reset a tenant's
 * customized revenue_mode / auto_post back to defaults.
 */
class MedicalModuleActivatorTest extends TestCase
{
    use DatabaseTransactions;

    protected Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );

        // Creation runs the observer -> activateForHealthcare -> the seeding
        // method under test, so setUp ends with both keys holding defaults.
        $this->institute = Institute::create([
            'name' => 'Activator Hospital '.uniqid(),
            'slug' => 'activator-hospital-'.uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'status' => 'active',
        ]);
    }

    protected function reactivate(): void
    {
        app(MedicalModuleActivator::class)->activateForHealthcare($this->institute);
    }

    public function test_seeds_defaults_when_keys_absent(): void
    {
        $revenueKey = "medical.billing.revenue_mode.{$this->institute->id}";
        $autoPostKey = "medical.billing.auto_post.{$this->institute->id}";

        Setting::query()->whereIn('key', [$revenueKey, $autoPostKey])->delete();
        $this->assertFalse(Setting::has($revenueKey));
        $this->assertFalse(Setting::has($autoPostKey));

        $this->reactivate();

        $this->assertTrue(Setting::has($revenueKey));
        $this->assertTrue(Setting::has($autoPostKey));
        $this->assertSame(
            config('medical_billing.revenue_mode', 'accrual'),
            Setting::get($revenueKey)
        );
        $this->assertSame(
            config('medical_billing.auto_post', true) ? '1' : '0',
            Setting::get($autoPostKey)
        );
    }

    public function test_does_not_overwrite_existing_revenue_mode(): void
    {
        $revenueKey = "medical.billing.revenue_mode.{$this->institute->id}";

        Setting::set($revenueKey, 'cash');
        $this->assertSame('cash', Setting::get($revenueKey));

        // Re-activating the medical module must NOT reset the tenant choice.
        $this->reactivate();

        $this->assertSame('cash', Setting::get($revenueKey));
    }

    public function test_does_not_overwrite_existing_auto_post(): void
    {
        $autoPostKey = "medical.billing.auto_post.{$this->institute->id}";

        Setting::set($autoPostKey, '0');
        $this->assertSame('0', Setting::get($autoPostKey));

        $this->reactivate();

        $this->assertSame('0', Setting::get($autoPostKey));
    }
}
