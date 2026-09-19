<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use App\Models\TenantAccessDenial;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantAccessDenialModelTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('tenant_access_denials') || ! Schema::hasTable('institutes') || ! Schema::hasTable('platform_admins')) {
            $this->markTestSkipped('Required tables do not exist.');
        }
    }

    private function institute(): Institute
    {
        $pkg = SubscriptionPackage::where('slug', 'free')->first();
        return Institute::create([
            'name' => 'Denial Test Institute '.uniqid(),
            'slug' => 'denial-test-'.uniqid(),
            'status' => 'active',
            'package_id' => $pkg?->id,
        ]);
    }

    private function admin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'first_name' => 'Denial',
            'last_name' => 'Admin',
            'email' => 'denial-admin-'.uniqid().'@example.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_unique_constraint_prevents_duplicate_active_deny(): void
    {
        $inst = $this->institute();
        $admin = $this->admin();

        TenantAccessDenial::create([
            'institute_id' => $inst->id,
            'deny_type' => 'feature',
            'deny_key' => 'medical.pharmacy',
            'denied_by' => $admin->id,
            'reason' => 'Security concern',
            'status' => 'active',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        TenantAccessDenial::create([
            'institute_id' => $inst->id,
            'deny_type' => 'feature',
            'deny_key' => 'medical.pharmacy',
            'denied_by' => $admin->id,
            'reason' => 'Duplicate attempt',
            'status' => 'active',
        ]);
    }

    public function test_is_expired_returns_false_when_no_expiry(): void
    {
        $denial = TenantAccessDenial::create([
            'institute_id' => $this->institute()->id,
            'deny_type' => 'feature',
            'deny_key' => 'medical.pharmacy',
            'denied_by' => $this->admin()->id,
            'reason' => 'Test denial',
        ]);

        $this->assertFalse($denial->isExpired());
    }

    public function test_is_expired_returns_true_when_past(): void
    {
        $denial = TenantAccessDenial::create([
            'institute_id' => $this->institute()->id,
            'deny_type' => 'feature',
            'deny_key' => 'medical.pharmacy',
            'denied_by' => $this->admin()->id,
            'expires_at' => now()->subDay(),
            'reason' => 'Test denial',
        ]);

        $this->assertTrue($denial->isExpired());
    }

    public function test_relation_resolves(): void
    {
        $inst = $this->institute();
        $admin = $this->admin();

        $denial = TenantAccessDenial::create([
            'institute_id' => $inst->id,
            'deny_type' => 'feature',
            'deny_key' => 'medical.pharmacy',
            'denied_by' => $admin->id,
            'reason' => 'Test',
        ]);

        $this->assertEquals($inst->id, $denial->institute->id);
        $this->assertEquals($admin->id, $denial->deniedBy->id);
    }

    public function test_reason_is_mandatory(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        TenantAccessDenial::create([
            'institute_id' => $this->institute()->id,
            'deny_type' => 'feature',
            'deny_key' => 'medical.pharmacy',
            'denied_by' => $this->admin()->id,
            'reason' => null,
        ]);
    }

    public function test_cascade_delete_on_institute_delete(): void
    {
        $inst = $this->institute();
        $denialId = TenantAccessDenial::create([
            'institute_id' => $inst->id,
            'deny_type' => 'feature',
            'deny_key' => 'medical.pharmacy',
            'denied_by' => $this->admin()->id,
            'reason' => 'Test',
        ])->id;

        $inst->forceDelete();

        $this->assertDatabaseMissing('tenant_access_denials', ['id' => $denialId]);
    }
}
