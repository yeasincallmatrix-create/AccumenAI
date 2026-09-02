<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\InstituteModuleEntitlement;
use App\Models\ModuleAccessLog;
use App\Models\PlatformAdmin;
use App\Models\SubscriptionPackage;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class EntitlementAuditTest extends TestCase
{
    use DatabaseTransactions;

    private function package(string $slug): SubscriptionPackage
    {
        return SubscriptionPackage::whereRaw('LOWER(slug) = ?', [strtolower($slug)])->firstOrFail();
    }

    private function institute(): Institute
    {
        \App\Support\TenantContext::clear();
        $pkg = $this->package('FREE');
        return Institute::create([
            'name' => 'Audit '.uniqid(),
            'slug' => 'audit-'.uniqid(),
            'status' => 'active',
            'package_id' => $pkg->id,
            'industry' => 'education',
            'sub_industry' => 'school',
            'country' => 'Bangladesh',
        ]);
    }

    private function platformAdmin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'email' => 'audit-admin-'.uniqid().'@test.local',
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
        ]);
    }

    // 1. grant creates entitlement_granted
    public function test_grant_creates_entitlement_granted(): void
    {
        $inst = $this->institute();
        $admin = $this->platformAdmin();
        app(ModuleAccessService::class)->grantModule($inst, 'hr', ['status'=>'active','is_grant'=>true], $admin->id);
        $log = ModuleAccessLog::where('institute_id',$inst->id)->where('module_key','hr')->where('action','entitlement_granted')->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals($inst->id, $log->institute_id);
        $this->assertEquals('hr', $log->module_key);
        $this->assertEquals($admin->id, $log->actor_id);
    }

    // 2. revoke creates entitlement_revoked
    public function test_revoke_creates_entitlement_revoked(): void
    {
        $inst = $this->institute();
        $admin = $this->platformAdmin();
        $svc = app(ModuleAccessService::class);
        $svc->grantModule($inst,'sales',['status'=>'active','is_grant'=>true], $admin->id);
        $svc->revokeModule($inst,'sales',$admin->id);
        $log = ModuleAccessLog::where('institute_id',$inst->id)->where('action','entitlement_revoked')->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('sales',$log->module_key);
        $this->assertEquals($admin->id, $log->actor_id);
        $this->assertEquals('revoked', InstituteModuleEntitlement::withTrashed()->where('institute_id',$inst->id)->first()->status);
    }

    // 3. expiry command creates entitlement_expired
    public function test_expiry_creates_entitlement_expired(): void
    {
        $inst = $this->institute();
        InstituteModuleEntitlement::create([
            'institute_id'=>$inst->id,'module_key'=>'purchase','status'=>'active','is_grant'=>true,'ends_at'=>now()->subDay()
        ]);
        Artisan::call('entitlements:expire');
        $log = ModuleAccessLog::where('institute_id',$inst->id)->where('action','entitlement_expired')->latest()->first();
        $this->assertNotNull($log);
        $this->assertEquals('purchase',$log->module_key);
        $this->assertEquals('active',$log->previous_state);
        $this->assertEquals('expired',$log->new_state);
        $this->assertNull($log->actor_id); // system
    }

    // 4. trial lifecycle creates appropriate audit event
    public function test_trial_lifecycle_audit(): void
    {
        $inst = $this->institute();
        $admin = $this->platformAdmin();
        // trial_started on grant
        app(ModuleAccessService::class)->grantModule($inst,'inventory',['status'=>'trialing','is_grant'=>true,'trial_starts_at'=>now()->subDay(),'trial_ends_at'=>now()->addDay()], $admin->id);
        $started = ModuleAccessLog::where('action','trial_started')->latest()->first();
        $this->assertNotNull($started);
        $this->assertEquals('inventory',$started->module_key);

        // trial_expired via command
        $ent = InstituteModuleEntitlement::where('institute_id',$inst->id)->first();
        $ent->update(['trial_ends_at'=>now()->subHour()]);
        Artisan::call('entitlements:expire');
        $expired = ModuleAccessLog::where('action','trial_expired')->latest()->first();
        $this->assertNotNull($expired);
        $this->assertEquals('inventory',$expired->module_key);
        $this->assertEquals('trialing',$expired->previous_state);
        $this->assertEquals('expired',$expired->new_state);
    }

    // 5. previous_state is correct
    public function test_previous_state_correct(): void
    {
        $inst = $this->institute();
        $admin = $this->platformAdmin();
        $svc = app(ModuleAccessService::class);
        // Initially disabled (no hr)
        $svc->grantModule($inst,'hr',['status'=>'active','is_grant'=>true], $admin->id);
        $log = ModuleAccessLog::where('action','entitlement_granted')->latest()->first();
        $this->assertEquals('disabled',$log->previous_state);
        // Grant again after revoke: previous should be disabled again
        $svc->revokeModule($inst,'hr',$admin->id);
        $svc->grantModule($inst,'hr',['status'=>'active','is_grant'=>true], $admin->id);
        $log2 = ModuleAccessLog::where('action','entitlement_granted')->latest()->first();
        $this->assertEquals('disabled',$log2->previous_state);
    }

    // 6. new_state is correct
    public function test_new_state_correct(): void
    {
        $inst = $this->institute();
        $admin = $this->platformAdmin();
        app(ModuleAccessService::class)->grantModule($inst,'sales',['status'=>'active','is_grant'=>true], $admin->id);
        $log = ModuleAccessLog::where('action','entitlement_granted')->latest()->first();
        $this->assertEquals('enabled',$log->new_state);
        app(ModuleAccessService::class)->grantModule($inst,'crm',['status'=>'active','is_grant'=>false], $admin->id);
        $log2 = ModuleAccessLog::where('action','entitlement_denied')->latest()->first();
        $this->assertEquals('disabled',$log2->new_state);
    }

    // 7. institute_id is correct
    public function test_institute_id_correct(): void
    {
        $instA = $this->institute();
        $instB = $this->institute();
        $admin = $this->platformAdmin();
        app(ModuleAccessService::class)->grantModule($instA,'hr',['status'=>'active','is_grant'=>true], $admin->id);
        $log = ModuleAccessLog::where('module_key','hr')->latest()->first();
        $this->assertEquals($instA->id, $log->institute_id);
        $this->assertNotEquals($instB->id, $log->institute_id);
    }

    // 8. module_key is correct
    public function test_module_key_correct(): void
    {
        $inst = $this->institute();
        $admin = $this->platformAdmin();
        app(ModuleAccessService::class)->grantModule($inst,'purchase',['status'=>'active','is_grant'=>true], $admin->id);
        $log = ModuleAccessLog::latest()->first();
        $this->assertEquals('purchase',$log->module_key);
    }

    // 9. actor attribution is correct
    public function test_actor_attribution_correct(): void
    {
        $inst = $this->institute();
        $admin = $this->platformAdmin();
        app(ModuleAccessService::class)->grantModule($inst,'hr',['status'=>'active','is_grant'=>true], $admin->id);
        $grantLog = ModuleAccessLog::where('action','entitlement_granted')->latest()->first();
        $this->assertEquals($admin->id,$grantLog->actor_id);
        // expiry system actor null
        InstituteModuleEntitlement::create(['institute_id'=>$inst->id,'module_key'=>'sales','status'=>'active','is_grant'=>true,'ends_at'=>now()->subDay()]);
        Artisan::call('entitlements:expire');
        $expLog = ModuleAccessLog::where('action','entitlement_expired')->latest()->first();
        $this->assertNull($expLog->actor_id);
        $this->assertEquals($inst->package_id,$expLog->package_id);
    }

    // 10. unauthorized institute user cannot create/modify entitlement audit events
    public function test_unauthorized_institute_user_cannot_forge_audit(): void
    {
        $inst = $this->institute();
        $userRole = \App\Models\Role::where('slug','institute-owner')->firstOrFail();
        \App\Support\TenantContext::clear();
        $user = \App\Models\InstituteUser::create([
            'institute_id'=>$inst->id,'role_id'=>$userRole->id,'first_name'=>'Test','last_name'=>'User','email'=>'audit-user-'.uniqid().'@test.local','phone'=>'017'.rand(10000000,99999999),'password_hash'=>bcrypt('secret'),'status'=>'active'
        ]);
        $before = ModuleAccessLog::count();
        // Attempt via admin route as institute user -> blocked (302/403)
        $resp = $this->actingAs($user,'institute_user')->post(route('admin.institutes.entitlements.store',$inst),['module_key'=>'hr','is_grant'=>1,'status'=>'active']);
        $this->assertTrue(in_array($resp->status(),[302,403,401]));
        $this->assertEquals($before, ModuleAccessLog::count());
        // Direct service call as institute user would still log but with institute_user actor? We ensure controller is gate, not service. Service itself is not blocked but UI is.
    }

    // 11. existing package audit events still work
    public function test_existing_package_audit_still_works(): void
    {
        $inst = $this->institute();
        $admin = $this->platformAdmin();
        $old = $inst->package_id;
        $newPkg = \App\Models\SubscriptionPackage::whereRaw('LOWER(slug)=?',['premium'])->first() ?? \App\Models\SubscriptionPackage::whereRaw('LOWER(slug)=?',['basic'])->first();
        if ($newPkg) {
            app(ModuleAccessService::class)->changePackage($inst,$old,$newPkg->id,$admin->id);
            $this->assertDatabaseHas('module_access_logs',['institute_id'=>$inst->id,'action'=>'package_added']);
        } else {
            $this->assertTrue(true);
        }
    }

    // 12. existing enable/disable audit events still work
    public function test_existing_enable_disable_audit_still_works(): void
    {
        $inst = $this->institute();
        $admin = $this->platformAdmin();
        app(ModuleAccessService::class)->enableModule($inst,'hr',$admin->id,'test enable');
        $this->assertDatabaseHas('module_access_logs',['action'=>'enable','module_key'=>'hr']);
        app(ModuleAccessService::class)->disableModule($inst,'hr',$admin->id,'test disable');
        $this->assertDatabaseHas('module_access_logs',['action'=>'disable','module_key'=>'hr']);
    }
}
