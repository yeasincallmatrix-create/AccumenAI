<?php

namespace Tests\Feature\Security;

use App\Models\Institute;
use App\Models\InstituteModuleEntitlement;
use App\Models\InstituteModuleOverride;
use App\Models\ModuleAccessLog;
use App\Models\ModuleRegistry;
use App\Models\PackageModule;
use App\Models\SubscriptionPackage;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * SEC-02a: the module/subscription cluster models must expose explicit
 * $fillable allow-lists (no $guarded = []). For each model: poison
 * fields (id / timestamps / non-fillable columns) are ignored on
 * create(), while every $fillable field round-trips.
 */
class MassAssignmentTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    private int $packageId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->packageId = SubscriptionPackage::whereRaw('LOWER(slug) = ?', ['free'])->firstOrFail()->id;

        $this->institute = Institute::create([
            'name' => 'SEC02A '.uniqid(),
            'slug' => 'sec02a-'.uniqid(),
            'status' => 'active',
            'package_id' => $this->packageId,
        ]);
    }

    public function test_module_registry_ignores_poison_fields(): void
    {
        $row = ModuleRegistry::create([
            'id' => 999999999,
            'key' => 'sec02a-'.uniqid(),
            'name' => 'Probe',
            'dependencies' => ['anything'],
            'created_at' => '2001-01-01 00:00:00',
        ]);

        $this->assertNotSame(999999999, $row->id);
        $this->assertDatabaseMissing('module_registry', ['id' => 999999999]);
        $this->assertNull($row->fresh()->dependencies);
        $this->assertNotSame('2001-01-01 00:00:00', $row->fresh()->created_at->format('Y-m-d H:i:s'));
    }

    public function test_module_registry_fillable_round_trips(): void
    {
        $key = 'sec02a-'.uniqid();
        $row = ModuleRegistry::create([
            'key' => $key,
            'parent_key' => null,
            'name' => 'Probe',
            'type' => 'core',
            'description' => 'desc',
            'sort_order' => 99,
            'icon' => 'bi-test',
            'coming_soon' => false,
            'index_route' => null,
            'status' => 'active',
        ]);

        $fresh = $row->fresh();
        $this->assertSame($key, $fresh->key);
        $this->assertSame('Probe', $fresh->name);
        $this->assertSame('core', $fresh->type);
        $this->assertSame('desc', $fresh->description);
        $this->assertSame(99, (int) $fresh->sort_order);
        $this->assertSame('bi-test', $fresh->icon);
        $this->assertFalse((bool) $fresh->coming_soon);
        $this->assertSame('active', $fresh->status);
    }

    public function test_package_module_ignores_poison_fields(): void
    {
        $row = PackageModule::create([
            'id' => 999999999,
            'package_id' => $this->packageId,
            'module_key' => 'sec02a-probe',
            'enabled' => true,
            'created_at' => '2001-01-01 00:00:00',
        ]);

        $this->assertNotSame(999999999, $row->id);
        $this->assertDatabaseMissing('package_modules', ['id' => 999999999]);
        $this->assertNotSame('2001-01-01 00:00:00', $row->fresh()->created_at->format('Y-m-d H:i:s'));
    }

    public function test_package_module_fillable_round_trips(): void
    {
        $row = PackageModule::create([
            'package_id' => $this->packageId,
            'module_key' => 'sec02a-probe-'.uniqid(),
            'enabled' => true,
        ]);

        $fresh = $row->fresh();
        $this->assertSame($this->packageId, (int) $fresh->package_id);
        $this->assertTrue((bool) $fresh->enabled);
    }

    public function test_module_access_log_ignores_poison_fields(): void
    {
        $row = ModuleAccessLog::create([
            'id' => 999999999,
            'module_key' => 'sales',
            'action' => 'enable',
            'created_at' => '2001-01-01 00:00:00',
        ]);

        $this->assertNotSame(999999999, $row->id);
        $this->assertDatabaseMissing('module_access_logs', ['id' => 999999999]);
        $this->assertNotSame('2001-01-01 00:00:00', $row->fresh()->created_at->format('Y-m-d H:i:s'));
    }

    public function test_module_access_log_fillable_round_trips(): void
    {
        $row = ModuleAccessLog::create([
            'institute_id' => $this->institute->id,
            'module_key' => 'sales',
            'action' => 'enable',
            'actor_id' => null,
            'previous_state' => 'disabled',
            'new_state' => 'enabled',
            'package_id' => $this->packageId,
            'notes' => 'probe',
        ]);

        $fresh = $row->fresh();
        $this->assertSame($this->institute->id, (int) $fresh->institute_id);
        $this->assertSame('sales', $fresh->module_key);
        $this->assertSame('enable', $fresh->action);
        $this->assertSame('disabled', $fresh->previous_state);
        $this->assertSame('enabled', $fresh->new_state);
        $this->assertSame($this->packageId, (int) $fresh->package_id);
        $this->assertSame('probe', $fresh->notes);
    }

    public function test_entitlement_ignores_poison_fields(): void
    {
        $row = InstituteModuleEntitlement::create([
            'id' => 999999999,
            'institute_id' => $this->institute->id,
            'module_key' => 'sales',
            'deleted_at' => '2001-01-01 00:00:00',
            'created_at' => '2001-01-01 00:00:00',
        ]);

        $this->assertNotSame(999999999, $row->id);
        $this->assertDatabaseMissing('institute_module_entitlements', ['id' => 999999999]);
        $this->assertNull($row->fresh()->deleted_at);
        $this->assertNotSame('2001-01-01 00:00:00', $row->fresh()->created_at->format('Y-m-d H:i:s'));
    }

    public function test_entitlement_fillable_round_trips(): void
    {
        $row = InstituteModuleEntitlement::create([
            'institute_id' => $this->institute->id,
            'module_key' => 'sales',
            'status' => 'active',
            'is_grant' => true,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'trial_starts_at' => null,
            'trial_ends_at' => null,
            'monthly_price' => 99.99,
            'yearly_price' => 999.99,
            'billing_cycle' => 'monthly',
            'auto_renew' => true,
            'discount_percent' => 10.50,
            'purchased_by' => null,
            'granted_by' => null,
            'notes' => 'probe',
        ]);

        $fresh = $row->fresh();
        $this->assertSame($this->institute->id, (int) $fresh->institute_id);
        $this->assertSame('sales', $fresh->module_key);
        $this->assertSame('active', $fresh->status);
        $this->assertTrue($fresh->is_grant);
        $this->assertNotNull($fresh->starts_at);
        $this->assertNotNull($fresh->ends_at);
        $this->assertEquals(99.99, $fresh->monthly_price);
        $this->assertEquals(999.99, $fresh->yearly_price);
        $this->assertSame('monthly', $fresh->billing_cycle);
        $this->assertTrue($fresh->auto_renew);
        $this->assertEquals(10.50, $fresh->discount_percent);
        $this->assertSame('probe', $fresh->notes);
    }

    public function test_override_ignores_poison_fields(): void
    {
        $row = InstituteModuleOverride::create([
            'id' => 999999999,
            'institute_id' => $this->institute->id,
            'module_key' => 'sales',
            'enabled' => true,
            'created_at' => '2001-01-01 00:00:00',
        ]);

        $this->assertNotSame(999999999, $row->id);
        $this->assertDatabaseMissing('institute_module_overrides', ['id' => 999999999]);
        $this->assertNotSame('2001-01-01 00:00:00', $row->fresh()->created_at->format('Y-m-d H:i:s'));
    }

    public function test_override_fillable_round_trips(): void
    {
        $row = InstituteModuleOverride::create([
            'institute_id' => $this->institute->id,
            'module_key' => 'sales',
            'enabled' => true,
            'overridden_by' => null,
            'reason' => 'probe',
        ]);

        $fresh = $row->fresh();
        $this->assertSame($this->institute->id, (int) $fresh->institute_id);
        $this->assertSame('sales', $fresh->module_key);
        $this->assertTrue((bool) $fresh->enabled);
        $this->assertSame('probe', $fresh->reason);
    }
}
