<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use App\Models\TenantAccessDenial;
use App\Models\TenantAccessGrant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 7b (Part D): grants:process-expired command.
 *
 * Idempotent active → expired transition for past-due rows,
 * feature-cache flush for affected institutes, dry-run safety.
 */
class ProcessExpiredGrantsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('tenant_access_grants')
            || ! Schema::hasTable('tenant_access_denials')) {
            $this->markTestSkipped('Required tables do not exist.');
        }
    }

    private function admin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'first_name' => 'Expiry',
            'last_name' => 'Admin',
            'email' => 'expiry-admin-'.uniqid().'@example.com',
            'password_hash' => bcrypt('password'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    private function institute(): Institute
    {
        $pkg = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->firstOrFail();

        return Institute::create([
            'name' => 'Expiry Test '.uniqid(),
            'slug' => 'expiry-test-'.uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
        ]);
    }

    public function test_expires_past_due_grants_and_denials(): void
    {
        $inst = $this->institute();
        $admin = $this->admin();

        $grant = TenantAccessGrant::create([
            'institute_id' => $inst->id,
            'grant_type' => 'feature',
            'grant_key' => 'medical.pharmacy',
            'granted_by' => $admin->id,
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        $denial = TenantAccessDenial::create([
            'institute_id' => $inst->id,
            'deny_type' => 'feature',
            'deny_key' => 'medical.laboratory',
            'denied_by' => $admin->id,
            'reason' => 'Expiry test denial',
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('grants:process-expired')
            ->assertSuccessful();

        $this->assertSame('expired', $grant->fresh()->status);
        $this->assertSame('expired', $denial->fresh()->status);
    }

    public function test_dry_run_makes_no_changes(): void
    {
        $inst = $this->institute();
        $admin = $this->admin();

        $grant = TenantAccessGrant::create([
            'institute_id' => $inst->id,
            'grant_type' => 'feature',
            'grant_key' => 'medical.billing',
            'granted_by' => $admin->id,
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('grants:process-expired', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertSame('active', $grant->fresh()->status);
    }

    public function test_future_expiry_untouched_and_idempotent(): void
    {
        $inst = $this->institute();
        $admin = $this->admin();

        $future = TenantAccessGrant::create([
            'institute_id' => $inst->id,
            'grant_type' => 'feature',
            'grant_key' => 'medical.emergency',
            'granted_by' => $admin->id,
            'status' => 'active',
            'expires_at' => now()->addDays(30),
        ]);

        $past = TenantAccessGrant::create([
            'institute_id' => $inst->id,
            'grant_type' => 'feature',
            'grant_key' => 'medical.radiology',
            'granted_by' => $admin->id,
            'status' => 'active',
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('grants:process-expired')->assertSuccessful();
        $this->artisan('grants:process-expired')->assertSuccessful();

        $this->assertSame('active', $future->fresh()->status);
        $this->assertSame('expired', $past->fresh()->status);
    }
}
