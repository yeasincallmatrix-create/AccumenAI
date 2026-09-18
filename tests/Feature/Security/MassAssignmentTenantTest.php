<?php

namespace Tests\Feature\Security;

use App\Models\AccountingSetting;
use App\Models\Industry;
use App\Models\IndustrySetting;
use App\Models\Institute;
use App\Models\SubIndustry;
use App\Models\SubscriptionPackage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * SEC-02b: the tenant/platform cluster models must expose explicit
 * $fillable allow-lists (no $guarded = []). Poison fields (id /
 * timestamps / non-fillable columns / privilege-escalation keys) are
 * ignored on create(), while every $fillable field round-trips.
 */
class MassAssignmentTenantTest extends TestCase
{
    use DatabaseTransactions;

    private int $packageId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packageId = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->firstOrFail()->id;
    }

    public function test_institute_drops_escalation_keys_and_poison_fields(): void
    {
        $row = Institute::create([
            'id' => 999999999,
            'name' => 'SEC02B '.uniqid(),
            'slug' => 'sec02b-'.uniqid(),
            'status' => 'active',
            'is_owner' => true,
            'singleton_guard' => true,
            'super_admin' => true,
            'platform_admin' => true,
            'guard' => 'platform_admin',
            'role' => 'institute-owner',
            'division' => 'Dhaka',
            'created_at' => '2001-01-01 00:00:00',
        ]);

        $this->assertNotSame(999999999, $row->id);
        $this->assertDatabaseMissing('institutes', ['id' => 999999999]);
        foreach (['is_owner', 'singleton_guard', 'super_admin', 'platform_admin', 'guard', 'role', 'division'] as $key) {
            $this->assertArrayNotHasKey($key, $row->getAttributes());
        }
        $this->assertNotSame('2001-01-01 00:00:00', $row->fresh()->created_at->format('Y-m-d H:i:s'));
    }

    public function test_institute_fillable_round_trips(): void
    {
        $industryId = Industry::where('slug', 'education')->value('id');
        $subId = SubIndustry::where('slug', 'school')->whereNull('country_id')->value('id');

        $row = Institute::create([
            'name' => 'SEC02B '.uniqid(),
            'short_name' => 'S2B',
            'slug' => 'sec02b-'.uniqid(),
            'institute_code' => 'S2B-'.uniqid(),
            'status' => 'active',
            'verified' => true,
            'package_id' => $this->packageId,
            'subscription_expiry' => now()->addYear()->toDateString(),
            'phone' => '01700000001',
            'email' => 'sec02b-'.uniqid().'@test.local',
            'website' => 'https://example.test',
            'address' => 'Addr',
            'founded_year' => 2001,
            'country' => 'Bangladesh',
            'industry' => 'education',
            'industry_id' => $industryId,
            'sub_industry' => 'school',
            'sub_industry_id' => $subId,
            'description' => 'desc',
            'is_test' => true,
            'onboarded_at' => now(),
        ]);

        $fresh = $row->fresh();
        $this->assertSame('S2B', $fresh->short_name);
        $this->assertSame('active', $fresh->status);
        $this->assertTrue((bool) $fresh->verified);
        $this->assertSame($this->packageId, (int) $fresh->package_id);
        $this->assertSame('01700000001', $fresh->phone);
        $this->assertSame('Addr', $fresh->address);
        $this->assertSame('Bangladesh', $fresh->country);
        $this->assertSame('education', $fresh->industry);
        $this->assertSame((int) $industryId, (int) $fresh->industry_id);
        $this->assertSame('school', $fresh->sub_industry);
        $this->assertTrue((bool) $fresh->is_test);
        $this->assertNotNull($fresh->onboarded_at);
        $this->assertNotNull($fresh->uid);
    }

    public function test_subscription_package_ignores_poison_fields(): void
    {
        $row = SubscriptionPackage::create([
            'id' => 999999999,
            'name' => 'SEC02B',
            'slug' => 'sec02b-'.uniqid(),
            'features' => ['evil' => true],
            'created_at' => '2001-01-01 00:00:00',
        ]);

        $this->assertNotSame(999999999, $row->id);
        $this->assertDatabaseMissing('subscription_packages', ['id' => 999999999]);
        $this->assertNull($row->fresh()->features);
        $this->assertNotSame('2001-01-01 00:00:00', $row->fresh()->created_at->format('Y-m-d H:i:s'));
    }

    public function test_subscription_package_fillable_round_trips(): void
    {
        $slug = 'sec02b-'.uniqid();
        $row = SubscriptionPackage::create([
            'name' => 'SEC02B',
            'slug' => $slug,
            'price_monthly' => 1500,
            'price_yearly' => 15000,
            'max_students' => 300,
            'max_teachers' => 10,
            'max_courses' => 15,
            'max_branches' => 2,
            'storage_limit_mb' => 2000,
            'sms_limit_monthly' => 200,
            'is_default' => false,
            'status' => 'active',
        ]);

        $fresh = SubscriptionPackage::where('slug', $slug)->firstOrFail();
        $this->assertEquals(1500, $fresh->price_monthly);
        $this->assertEquals(15000, $fresh->price_yearly);
        $this->assertSame(300, (int) $fresh->max_students);
        $this->assertSame(200, (int) $fresh->sms_limit_monthly);
        $this->assertFalse((bool) $fresh->is_default);
        $this->assertSame('active', $fresh->status);
        $this->assertSame($row->id, $fresh->id);
    }

    public function test_accounting_setting_ignores_poison_fields(): void
    {
        $institute = Institute::create([
            'name' => 'SEC02B '.uniqid(),
            'slug' => 'sec02b-'.uniqid(),
            'status' => 'active',
        ]);

        $row = AccountingSetting::create([
            'id' => 999999999,
            'institute_id' => $institute->id,
            'branch_id' => null,
            'settings_key' => 'sec02b-'.uniqid(),
            'settings_value' => ['a' => 1],
            'created_at' => '2001-01-01 00:00:00',
        ]);

        $this->assertNotSame(999999999, $row->id);
        $this->assertDatabaseMissing('accounting_settings', ['id' => 999999999]);
        $this->assertNotSame('2001-01-01 00:00:00', $row->fresh()->created_at->format('Y-m-d H:i:s'));
    }

    public function test_accounting_setting_fillable_round_trips(): void
    {
        $institute = Institute::create([
            'name' => 'SEC02B '.uniqid(),
            'slug' => 'sec02b-'.uniqid(),
            'status' => 'active',
        ]);
        $key = 'sec02b-'.uniqid();

        $row = AccountingSetting::create([
            'institute_id' => $institute->id,
            'branch_id' => null,
            'settings_key' => $key,
            'settings_value' => ['a' => 1],
            'created_by' => null,
            'updated_by' => null,
        ]);

        $fresh = $row->fresh();
        $this->assertSame($institute->id, (int) $fresh->institute_id);
        $this->assertSame($key, $fresh->settings_key);
        $this->assertSame(['a' => 1], $fresh->settings_value);
    }

    public function test_industry_setting_ignores_poison_fields(): void
    {
        $row = IndustrySetting::create([
            'id' => 999999999,
            'industry_key' => 'sec02b-'.uniqid(),
            'theme_slug' => 'royal-purple',
            'created_at' => '2001-01-01 00:00:00',
        ]);

        $this->assertNotSame(999999999, $row->id);
        $this->assertDatabaseMissing('industry_settings', ['id' => 999999999]);
        $this->assertNotSame('2001-01-01 00:00:00', $row->fresh()->created_at->format('Y-m-d H:i:s'));
    }

    public function test_industry_setting_fillable_round_trips(): void
    {
        $key = 'sec02b-'.uniqid();
        IndustrySetting::create([
            'industry_key' => $key,
            'theme_slug' => 'royal-purple',
        ]);

        $this->assertSame('royal-purple', IndustrySetting::where('industry_key', $key)->value('theme_slug'));
    }
}
