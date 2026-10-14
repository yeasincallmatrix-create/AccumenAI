<?php

namespace Tests\Feature\Medical;

use App\Models\Country;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Setting;
use App\Services\MedicalModuleActivator;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class NewInstituteBillingSettingsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );

        $this->institute = Institute::create([
            'name' => 'Test Healthcare ' . uniqid(),
            'slug' => 'test-healthcare-' . uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'healthcare',
            'status' => 'active',
        ]);

        $role = Role::where('slug', 'institute-owner')->firstOrFail();
        $this->owner = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'first_name' => 'Test',
            'last_name' => 'Owner',
            'email' => 'test-owner-' . uniqid() . '@example.test',
            'phone' => '01700' . rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    public function test_new_healthcare_institute_gets_billing_settings_seeded(): void
    {
        // Activate medical module (which now seeds billing settings)
        app(MedicalModuleActivator::class)->activateForHealthcare($this->institute);

        // Assert both settings keys exist in the DB
        $revenueModeKey = "medical.billing.revenue_mode.{$this->institute->id}";
        $autoPostKey = "medical.billing.auto_post.{$this->institute->id}";

        $this->assertNotNull(Setting::get($revenueModeKey), "Revenue mode setting not seeded");
        $this->assertNotNull(Setting::get($autoPostKey), "Auto post setting not seeded");

        // Assert values match config defaults
        $this->assertEquals(config('medical_billing.revenue_mode', 'accrual'), Setting::get($revenueModeKey));
        $this->assertEquals(config('medical_billing.auto_post', true) ? '1' : '0', Setting::get($autoPostKey));
    }

    public function test_non_healthcare_institute_does_not_get_billing_settings(): void
    {
        $country = Country::firstOrCreate(
            ['iso2' => 'US'],
            ['name' => 'United States', 'iso3' => 'USA', 'phone_code' => '1', 'status' => true]
        );

        $educationInstitute = Institute::create([
            'name' => 'Test Education ' . uniqid(),
            'slug' => 'test-education-' . uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'education',
            'status' => 'active',
        ]);

        // Activate medical module for non-healthcare (should be no-op)
        app(MedicalModuleActivator::class)->activateForHealthcare($educationInstitute);

        // Assert settings NOT seeded for non-healthcare
        $revenueModeKey = "medical.billing.revenue_mode.{$educationInstitute->id}";
        $autoPostKey = "medical.billing.auto_post.{$educationInstitute->id}";

        $this->assertNull(Setting::get($revenueModeKey), "Revenue mode should not be seeded for non-healthcare");
        $this->assertNull(Setting::get($autoPostKey), "Auto post should not be seeded for non-healthcare");
    }

    public function test_settings_are_idempotent(): void
    {
        // First activation
        app(MedicalModuleActivator::class)->activateForHealthcare($this->institute);

        $revenueModeKey = "medical.billing.revenue_mode.{$this->institute->id}";
        $autoPostKey = "medical.billing.auto_post.{$this->institute->id}";

        $firstRevenueMode = Setting::get($revenueModeKey);
        $firstAutoPost = Setting::get($autoPostKey);

        // Second activation (should not overwrite)
        app(MedicalModuleActivator::class)->activateForHealthcare($this->institute);

        $this->assertEquals($firstRevenueMode, Setting::get($revenueModeKey));
        $this->assertEquals($firstAutoPost, Setting::get($autoPostKey));
    }
}