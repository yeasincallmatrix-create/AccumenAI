<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use App\Models\TenantAccessGrant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantAccessGrantModelTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('tenant_access_grants') || ! Schema::hasTable('institutes') || ! Schema::hasTable('platform_admins')) {
            $this->markTestSkipped('Required tables do not exist.');
        }
    }

    private function institute(): Institute
    {
        $pkg = SubscriptionPackage::where('slug', 'free')->first();
        return Institute::create([
            'name' => 'Grant Test Institute '.uniqid(),
            'slug' => 'grant-test-'.uniqid(),
            'status' => 'active',
            'package_id' => $pkg?->id,
        ]);
    }

    private function admin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'first_name' => 'Grant',
            'last_name' => 'Admin',
            'email' => 'grant-admin-'.uniqid().'@example.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    public function test_is_expired_returns_false_when_no_expiry(): void
    {
        $grant = TenantAccessGrant::create([
            'institute_id' => $this->institute()->id,
            'grant_type' => 'feature',
            'grant_key' => 'medical.pharmacy',
            'granted_by' => $this->admin()->id,
            'reason' => 'Test grant',
        ]);

        $this->assertFalse($grant->isExpired());
    }

    public function test_is_expired_returns_false_when_future(): void
    {
        $grant = TenantAccessGrant::create([
            'institute_id' => $this->institute()->id,
            'grant_type' => 'feature',
            'grant_key' => 'medical.pharmacy',
            'granted_by' => $this->admin()->id,
            'expires_at' => now()->addDays(30),
            'reason' => 'Test grant',
        ]);

        $this->assertFalse($grant->isExpired());
    }

    public function test_is_expired_returns_true_when_past(): void
    {
        $grant = TenantAccessGrant::create([
            'institute_id' => $this->institute()->id,
            'grant_type' => 'feature',
            'grant_key' => 'medical.pharmacy',
            'granted_by' => $this->admin()->id,
            'expires_at' => now()->subDay(),
            'reason' => 'Test grant',
        ]);

        $this->assertTrue($grant->isExpired());
    }

    public function test_scope_active_filters_correctly(): void
    {
        $inst = $this->institute();
        $admin = $this->admin();

        TenantAccessGrant::create([
            'institute_id' => $inst->id,
            'grant_type' => 'feature',
            'grant_key' => 'medical.pharmacy',
            'granted_by' => $admin->id,
            'status' => 'active',
        ]);
        TenantAccessGrant::create([
            'institute_id' => $inst->id,
            'grant_type' => 'feature',
            'grant_key' => 'medical.lab',
            'granted_by' => $admin->id,
            'status' => 'revoked',
        ]);

        $this->assertEquals(1, TenantAccessGrant::active()->count());
    }

    public function test_scope_not_expired_filters_correctly(): void
    {
        $inst = $this->institute();
        $admin = $this->admin();

        TenantAccessGrant::create([
            'institute_id' => $inst->id,
            'grant_type' => 'feature',
            'grant_key' => 'medical.pharmacy',
            'granted_by' => $admin->id,
            'expires_at' => now()->addDays(30),
        ]);
        TenantAccessGrant::create([
            'institute_id' => $inst->id,
            'grant_type' => 'feature',
            'grant_key' => 'medical.lab',
            'granted_by' => $admin->id,
            'expires_at' => now()->subDay(),
        ]);

        $this->assertEquals(1, TenantAccessGrant::notExpired()->count());
    }

    public function test_cascade_delete_on_institute_delete(): void
    {
        $inst = $this->institute();
        $grantId = TenantAccessGrant::create([
            'institute_id' => $inst->id,
            'grant_type' => 'feature',
            'grant_key' => 'medical.pharmacy',
            'granted_by' => $this->admin()->id,
            'reason' => 'Test',
        ])->id;

        $inst->forceDelete();

        $this->assertDatabaseMissing('tenant_access_grants', ['id' => $grantId]);
    }
}
