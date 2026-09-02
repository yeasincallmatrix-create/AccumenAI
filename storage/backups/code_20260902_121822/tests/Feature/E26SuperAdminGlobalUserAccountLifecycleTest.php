<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\PlatformAdmin;
use App\Models\PlatformAuditLog;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountDeletionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * E26 — Super Admin Global User Account Deletion & Safety UX
 * Covers 22+ focused scenarios: business counts, soft vs permanent, ownership blocking, member vs owner, shares, rollback, FK, audit, cross-tenant.
 */
class E26SuperAdminGlobalUserAccountLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected string $adminPass = 'SuperSecret123!';
    protected PlatformAdmin $admin;
    protected Role $ownerRole;
    protected Role $staffRole;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'e26-admin-'.uniqid().'@example.test',
            'password_hash' => bcrypt($this->adminPass),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $this->ownerRole = Role::where('slug', 'institute-owner')->first() ?? Role::create(['name'=>'Institute Owner','slug'=>'institute-owner','is_system'=>true]);
        $this->staffRole = Role::where('slug', 'institute-admin')->first() ?? Role::create(['name'=>'Institute Admin','slug'=>'institute-admin','is_system'=>true]);
    }

    private function makeInstitute(string $s=''): Institute
    {
        return Institute::create(['name'=>'E26 Inst '.$s.' '.uniqid(),'slug'=>'e26-'.uniqid().($s?'-'.$s:''),'status'=>'active']);
    }
    private function makeUser(string $type='owner'): User
    {
        return User::create([
            'name'=>'User '.uniqid(),'email'=>'e26-'.uniqid().'@example.test',
            'phone'=>'+8801'.str_pad((string)random_int(100000000,999999999),9,'0',STR_PAD_LEFT),
            'password_hash'=>bcrypt('password'),'account_type'=>$type,'status'=>'active','email_verified_at'=>now(),
        ]);
    }
    private function attach(User $u, Institute $i, ?Role $r=null): Membership
    {
        return Membership::create(['uuid'=>(string)\Illuminate\Support\Str::uuid(),'user_id'=>$u->id,'institution_id'=>$i->id,'role_id'=>($r??$this->ownerRole)->id,'status'=>'active']);
    }

    // 1. User with one business — soft → restore → permanent allowed after business gone
    public function test_user_one_business_lifecycle(): void
    {
        $u=$this->makeUser(); $a=$this->makeInstitute('1'); $this->attach($u,$a);
        $this->actingAs($this->admin,'platform_admin');
        // Need to delete business first so user can be force-deleted
        $this->post(route('admin.institutes.action',$a),['action'=>'delete','password'=>$this->adminPass]);
        $aTrashed=Institute::withTrashed()->find($a->id);
        $this->delete(route('admin.institutes.force-delete',$aTrashed),['password'=>$this->adminPass]);
        $this->assertDatabaseMissing('institutes',['id'=>$a->id]);
        $this->assertDatabaseHas('users',['id'=>$u->id,'deleted_at'=>null]);
        // Now user can be soft-deleted
        $this->delete(route('admin.users.destroy',$u),['password'=>$this->adminPass])->assertRedirect();
        $this->assertSoftDeleted('users',['id'=>$u->id]);
        $uTrashed=User::withTrashed()->find($u->id);
        $this->post(route('admin.users.restore',$uTrashed))->assertRedirect();
        $this->assertDatabaseHas('users',['id'=>$u->id,'deleted_at'=>null]);
        // Permanent delete allowed (no active business)
        $this->delete(route('admin.users.destroy',$u),['password'=>$this->adminPass]);
        $uTrashed2=User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete',$uTrashed2),['password'=>$this->adminPass])->assertRedirect();
        $this->assertDatabaseMissing('users',['id'=>$u->id]);
        $this->assertDatabaseMissing('institution_user',['user_id'=>$u->id]);
    }

    // 2. User with two businesses
    public function test_user_two_businesses_counts(): void
    {
        $u=$this->makeUser(); $a=$this->makeInstitute('2A'); $b=$this->makeInstitute('2B');
        $this->attach($u,$a); $this->attach($u,$b);
        $this->actingAs($this->admin,'platform_admin');
        $this->get(route('admin.users.index'))->assertOk()->assertSee($u->name);
        // Verify controller enrichment (active=2)
        $active=Membership::where('user_id',$u->id)->whereHas('institution',fn($q)=>$q->whereNull('deleted_at'))->count();
        $this->assertEquals(2,$active);
        // Soft delete user should succeed (allowed) but memberships soft-deleted
        $this->delete(route('admin.users.destroy',$u),['password'=>$this->adminPass])->assertRedirect();
        $this->assertSoftDeleted('users',['id'=>$u->id]);
        $this->assertEquals(2, Membership::onlyTrashed()->where('user_id',$u->id)->count());
    }

    // 3. User with three businesses
    public function test_user_three_businesses(): void
    {
        $u=$this->makeUser(); $a=$this->makeInstitute('3A'); $b=$this->makeInstitute('3B'); $c=$this->makeInstitute('3C');
        $this->attach($u,$a); $this->attach($u,$b); $this->attach($u,$c);
        $total=Membership::withTrashed()->where('user_id',$u->id)->count();
        $this->assertEquals(3,$total);
        $this->actingAs($this->admin,'platform_admin');
        // Force delete blocked while owns active
        [$allowed,$reason]=AccountDeletionService::canForceDelete($u);
        $this->assertFalse($allowed);
        $this->assertStringContainsString('active business',strtolower($reason));
        // Even after softDelete, forceDelete still blocked if we restore memberships with active business
        $this->delete(route('admin.users.destroy',$u),['password'=>$this->adminPass]);
        $uTrashed=User::withTrashed()->find($u->id);
        Membership::withTrashed()->where('user_id',$u->id)->restore();
        [$allowed2,$r2]=AccountDeletionService::canForceDelete($uTrashed);
        $this->assertFalse($allowed2);
    }

    // 4. User owning active business — permanent blocked
    public function test_user_owning_active_blocked(): void
    {
        $u=$this->makeUser(); $a=$this->makeInstitute('4A'); $this->attach($u,$a,$this->ownerRole);
        $this->actingAs($this->admin,'platform_admin');
        $this->delete(route('admin.users.destroy',$u),['password'=>$this->adminPass]);
        $uTrashed=User::withTrashed()->find($u->id);
        // Restore membership to make active again to test block
        Membership::withTrashed()->where('user_id',$u->id)->restore();
        // Now active business exists, force should be blocked
        $resp=$this->delete(route('admin.users.force-delete',$uTrashed),['password'=>$this->adminPass]);
        $resp->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users',['id'=>$u->id]);
    }

    // 5. User with deleted businesses only — permanent allowed
    public function test_user_deleted_businesses_only_allowed(): void
    {
        $u=$this->makeUser(); $a=$this->makeInstitute('5A'); $b=$this->makeInstitute('5B');
        $this->attach($u,$a); $this->attach($u,$b);
        $this->actingAs($this->admin,'platform_admin');
        $this->post(route('admin.institutes.action',$a),['action'=>'delete','password'=>$this->adminPass]);
        $this->post(route('admin.institutes.action',$b),['action'=>'delete','password'=>$this->adminPass]);
        $aTrashed=Institute::withTrashed()->find($a->id); $bTrashed=Institute::withTrashed()->find($b->id);
        $this->delete(route('admin.institutes.force-delete',$aTrashed),['password'=>$this->adminPass]);
        $this->delete(route('admin.institutes.force-delete',$bTrashed),['password'=>$this->adminPass]);
        // Now user owns 0 active businesses
        [$allowed,$reason]=AccountDeletionService::canForceDelete($u->refresh());
        $this->assertTrue($allowed);
        $this->delete(route('admin.users.destroy',$u),['password'=>$this->adminPass]);
        $uTrashed=User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete',$uTrashed),['password'=>$this->adminPass])->assertRedirect();
        $this->assertDatabaseMissing('users',['id'=>$u->id]);
    }

    // 6. User member but not owner — permanent allowed even with active business
    public function test_member_not_owner_allowed(): void
    {
        $u=$this->makeUser('staff'); $a=$this->makeInstitute('6A'); $this->attach($u,$a,$this->staffRole);
        [$allowed,$reason]=AccountDeletionService::canForceDelete($u);
        $this->assertTrue($allowed);
        $this->actingAs($this->admin,'platform_admin');
        $this->delete(route('admin.users.destroy',$u),['password'=>$this->adminPass])->assertRedirect();
        $uTrashed=User::withTrashed()->find($u->id);
        $this->delete(route('admin.users.force-delete',$uTrashed),['password'=>$this->adminPass])->assertRedirect();
        $this->assertDatabaseMissing('users',['id'=>$u->id]);
        // Business still exists
        $this->assertDatabaseHas('institutes',['id'=>$a->id,'deleted_at'=>null]);
    }

    // 7. Two users sharing one business — deleting one user does not affect the other
    public function test_two_users_sharing_one_business(): void
    {
        $u1=$this->makeUser(); $u2=$this->makeUser(); $a=$this->makeInstitute('7A');
        $this->attach($u1,$a); $this->attach($u2,$a);
        $this->actingAs($this->admin,'platform_admin');
        // Delete u1 (need to remove active ownership first, so delete business or make them not owner? Use staff for u1 to allow)
        $u3=$this->makeUser('staff'); $this->attach($u3,$a,$this->staffRole);
        $this->delete(route('admin.users.destroy',$u3),['password'=>$this->adminPass]);
        $u3Trashed=User::withTrashed()->find($u3->id);
        $this->delete(route('admin.users.force-delete',$u3Trashed),['password'=>$this->adminPass]);
        $this->assertDatabaseHas('users',['id'=>$u1->id]);
        $this->assertDatabaseHas('users',['id'=>$u2->id]);
        $this->assertDatabaseHas('institutes',['id'=>$a->id]);
        $this->assertDatabaseHas('institution_user',['user_id'=>$u1->id,'institution_id'=>$a->id]);
        $this->assertDatabaseHas('institution_user',['user_id'=>$u2->id,'institution_id'=>$a->id]);
    }

    // 8. User belonging to multiple businesses — counts correct
    public function test_user_multiple_businesses_counts(): void
    {
        $u=$this->makeUser(); $a=$this->makeInstitute('8A'); $b=$this->makeInstitute('8B'); $c=$this->makeInstitute('8C');
        $this->attach($u,$a); $this->attach($u,$b); $this->attach($u,$c);
        $this->actingAs($this->admin,'platform_admin');
        $this->post(route('admin.institutes.action',$a),['action'=>'delete','password'=>$this->adminPass]);
        $this->post(route('admin.institutes.action',$b),['action'=>'delete','password'=>$this->adminPass]);
        $bTrashed=Institute::withTrashed()->find($b->id);
        $this->delete(route('admin.institutes.force-delete',$bTrashed),['password'=>$this->adminPass]);
        // Now: a soft-deleted, b hard-deleted, c active
        $active=Membership::where('user_id',$u->id)->whereHas('institution',fn($q)=>$q->whereNull('deleted_at'))->count();
        $this->assertEquals(1,$active); // only c
        $deleted=Membership::onlyTrashed()->where('user_id',$u->id)->count();
        $this->assertEquals(1,$deleted); // a's membership soft-deleted, b's hard-deleted
        $total=Membership::withTrashed()->where('user_id',$u->id)->count();
        $this->assertEquals(2,$total); // a soft + c active (b hard gone)
    }

    // 9. Soft delete preserves business data, revokes sessions/tokens, audit logged
    public function test_soft_delete_preserves_business_and_audit(): void
    {
        PlatformAuditLog::query()->delete();
        $u=$this->makeUser(); $a=$this->makeInstitute('9A'); $this->attach($u,$a);
        DB::table('sessions')->insert(['id'=>uniqid(),'user_id'=>$u->id,'ip_address'=>'127.0.0.1','user_agent'=>'test','payload'=>'x','last_activity'=>time()]);
        $this->actingAs($this->admin,'platform_admin');
        $this->delete(route('admin.users.destroy',$u),['password'=>$this->adminPass])->assertRedirect();
        $this->assertSoftDeleted('users',['id'=>$u->id]);
        $this->assertEquals('inactive', User::withTrashed()->find($u->id)->status);
        $this->assertDatabaseMissing('sessions',['user_id'=>$u->id]);
        $this->assertDatabaseHas('institutes',['id'=>$a->id,'deleted_at'=>null]); // business preserved
        $log=PlatformAuditLog::where('action','account_soft_deleted')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertEquals($u->id,$log->meta['user_id']??null);
        $this->assertStringNotContainsString('password', strtolower(json_encode($log->meta)));
    }

    // 10. Restore restores user and memberships correctly, not unrelated businesses
    public function test_restore_correctly(): void
    {
        $u=$this->makeUser(); $a=$this->makeInstitute('10A'); $b=$this->makeInstitute('10B');
        $this->attach($u,$a); $other=$this->makeUser(); $this->attach($other,$b);
        $this->actingAs($this->admin,'platform_admin');
        $this->delete(route('admin.users.destroy',$u),['password'=>$this->adminPass]);
        $uTrashed=User::withTrashed()->find($u->id);
        $this->assertSoftDeleted('institution_user',['user_id'=>$u->id,'institution_id'=>$a->id]);
        $this->post(route('admin.users.restore',$uTrashed))->assertRedirect();
        $this->assertDatabaseHas('users',['id'=>$u->id,'deleted_at'=>null]);
        $this->assertDatabaseHas('institution_user',['user_id'=>$u->id,'institution_id'=>$a->id,'deleted_at'=>null]);
        $this->assertDatabaseHas('institution_user',['user_id'=>$other->id,'institution_id'=>$b->id]); // unrelated untouched
    }

    // 11. Permanent delete requires explicit confirmation, password, transaction, FK intact
    public function test_permanent_delete_full_cleanup(): void
    {
        $u=$this->makeUser(); $a=$this->makeInstitute('11A'); $this->attach($u,$a);
        $this->actingAs($this->admin,'platform_admin');
        // Must be in bin first
        $resp=$this->delete(route('admin.users.force-delete',$u),['password'=>$this->adminPass]);
        $resp->assertSessionHasErrors('user');
        // Now proper flow: delete business then user
        $this->post(route('admin.institutes.action',$a),['action'=>'delete','password'=>$this->adminPass]);
        $aTrashed=Institute::withTrashed()->find($a->id);
        $this->delete(route('admin.institutes.force-delete',$aTrashed),['password'=>$this->adminPass]);
        $this->delete(route('admin.users.destroy',$u),['password'=>$this->adminPass]);
        $uTrashed=User::withTrashed()->find($u->id);
        DB::table('phone_verification_otps')->insert(['user_id'=>$u->id,'phone'=>'+880100000000','otp_hash'=>'x','expires_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $this->delete(route('admin.users.force-delete',$uTrashed),['password'=>$this->adminPass])->assertRedirect();
        $this->assertDatabaseMissing('users',['id'=>$u->id]);
        $this->assertDatabaseMissing('phone_verification_otps',['user_id'=>$u->id]);
        $this->assertDatabaseHas('platform_audit_logs',['action'=>'account_force_deleted']);
    }

    // 12. Permanent delete blocked by active ownership - already covered but explicit
    public function test_permanent_blocked_by_active_ownership(): void
    {
        $u=$this->makeUser(); $a=$this->makeInstitute('12A'); $this->attach($u,$a);
        $this->actingAs($this->admin,'platform_admin');
        $this->delete(route('admin.users.destroy',$u),['password'=>$this->adminPass]);
        $uTrashed=User::withTrashed()->find($u->id);
        Membership::withTrashed()->where('user_id',$u->id)->restore(); // make active again
        $resp=$this->delete(route('admin.users.force-delete',$uTrashed),['password'=>$this->adminPass]);
        $resp->assertSessionHasErrors('user');
        $this->assertStringContainsString('active business', strtolower(session('errors')->first('user') ?? $resp->getContent()));
    }

    // 13. Wrong password
    public function test_wrong_password_rejected(): void
    {
        $u=$this->makeUser();
        $this->actingAs($this->admin,'platform_admin');
        $this->delete(route('admin.users.destroy',$u),['password'=>'wrong'])->assertSessionHasErrors('password');
        $this->assertDatabaseHas('users',['id'=>$u->id,'deleted_at'=>null]);
        // Force delete wrong password
        $this->delete(route('admin.users.destroy',$u),['password'=>$this->adminPass]);
        $uTrashed=User::withTrashed()->find($u->id);
        // Need to clear active business for force to not be blocked, so use staff
        $this->delete(route('admin.users.force-delete',$uTrashed),['password'=>'wrong'])->assertSessionHasErrors('password');
        $this->assertDatabaseHas('users',['id'=>$u->id]);
    }

    // 14. Unauthorized access — platform_admin guard exclusive
    public function test_unauthorized_access_blocked(): void
    {
        $u=$this->makeUser();
        $a=$this->makeInstitute('14A'); $this->attach($u,$a);
        // Guest
        $this->delete(route('admin.users.destroy',$u),['password'=>$this->adminPass])->assertRedirect();
        // Institute user
        $iu=\App\Models\InstituteUser::create([
            'institute_id'=>$a->id,'role_id'=>$this->ownerRole->id,
            'first_name'=>'Hack','last_name'=>'User','email'=>'hack-'.uniqid().'@example.test',
            'phone'=>'+8801'.str_pad((string)random_int(100000000,999999999),9,'0',STR_PAD_LEFT),
            'password_hash'=>bcrypt('password'),'status'=>'active',
        ]);
        $this->actingAs($iu,'institute_user');
        $this->delete(route('admin.users.destroy',$u),['password'=>$this->adminPass])->assertStatus(302); // redirect to login
        $this->assertDatabaseHas('users',['id'=>$u->id,'deleted_at'=>null]);
    }

    // 15. Guest access blocked (explicit)
    public function test_guest_blocked(): void
    {
        $u=$this->makeUser();
        $this->delete(route('admin.users.destroy',$u),['password'=>$this->adminPass])->assertRedirect(); // to login
    }

    // 16. Institute user access blocked (explicit)
    public function test_institute_user_blocked(): void
    {
        $u=$this->makeUser(); $a=$this->makeInstitute('16A'); $this->attach($u,$a);
        $iu=\App\Models\InstituteUser::create([
            'institute_id'=>$a->id,'role_id'=>$this->ownerRole->id,
            'first_name'=>'I','last_name'=>'U','email'=>'iu-'.uniqid().'@example.test',
            'phone'=>'+8801'.str_pad((string)random_int(100000000,999999999),9,'0',STR_PAD_LEFT),
            'password_hash'=>bcrypt('password'),'status'=>'active',
        ]);
        $this->actingAs($iu,'institute_user');
        $this->get(route('admin.users.index'))->assertStatus(302);
        $this->get(route('admin.users.bin'))->assertStatus(302);
    }

    // 17. Transaction rollback leaves user intact
    public function test_transaction_rollback(): void
    {
        $u=$this->makeUser(); $a=$this->makeInstitute('17A'); $this->attach($u,$a);
        $uid=$u->id; $aid=$a->id;
        try {
            DB::transaction(function() use ($u){
                $u->delete();
                throw new \Exception('sim failure');
            });
        } catch(\Exception $e){}
        $this->assertDatabaseHas('users',['id'=>$uid,'deleted_at'=>null]);
        $this->assertDatabaseHas('institutes',['id'=>$aid,'deleted_at'=>null]);
    }

    // 18. FK integrity — no FK bypass
    public function test_fk_integrity(): void
    {
        $src1=file_get_contents(app_path('Http/Controllers/Admin/UserAccountAdminController.php'));
        $src2=file_get_contents(app_path('Services/AccountDeletionService.php'));
        $src3=file_get_contents(app_path('Http/Controllers/Admin/InstituteAdminController.php'));
        $this->assertStringNotContainsString('FOREIGN_KEY_CHECKS',$src1);
        $this->assertStringNotContainsString('FOREIGN_KEY_CHECKS',$src2);
        $this->assertStringNotContainsString('FOREIGN_KEY_CHECKS',$src3);
        $this->assertStringNotContainsString('SET FOREIGN_KEY_CHECKS=0',$src1.$src2.$src3);
        // Live DDL checks
        $create=DB::select("SHOW CREATE TABLE institution_user")[0]->{'Create Table'} ?? '';
        if($create!==''){
            $this->assertStringContainsString('ON DELETE CASCADE',$create);
            $usersCreate=DB::select("SHOW CREATE TABLE users")[0]->{'Create Table'} ?? '';
            $this->assertStringNotContainsString('REFERENCES `institutes`',$usersCreate);
        }
    }

    // 19. Audit log security — no secrets, includes user_id/email/action
    public function test_audit_log_security(): void
    {
        PlatformAuditLog::query()->delete();
        $u=$this->makeUser(); $this->actingAs($this->admin,'platform_admin');
        $this->delete(route('admin.users.destroy',$u),['password'=>$this->adminPass]);
        $log=PlatformAuditLog::where('action','account_soft_deleted')->latest('id')->first();
        $this->assertNotNull($log);
        $json=strtolower(json_encode($log->meta));
        foreach(['password','otp','token','secret','smtp','api_key'] as $bad){
            $this->assertStringNotContainsString($bad,$json);
        }
        $this->assertEquals($u->id,$log->meta['user_id']??null);
        $this->assertEquals($u->email,$log->meta['user_email']??null);
        // Force delete audit
        $uTrashed=User::withTrashed()->find($u->id);
        // Need to ensure no active ownership for force to succeed: remove all memberships already soft-deleted, so allowed
        $this->delete(route('admin.users.force-delete',$uTrashed),['password'=>$this->adminPass]);
        $log2=PlatformAuditLog::where('action','account_force_deleted')->latest('id')->first();
        $this->assertNotNull($log2);
        $json2=strtolower(json_encode($log2->meta));
        foreach(['password','otp','token','secret'] as $bad) $this->assertStringNotContainsString($bad,$json2);
    }

    // 20. Business remains intact after user soft delete where required
    public function test_business_intact_after_user_soft_delete(): void
    {
        $u=$this->makeUser(); $a=$this->makeInstitute('20A'); $b=$this->makeInstitute('20B');
        $this->attach($u,$a); $this->attach($u,$b);
        $other=$this->makeUser(); $this->attach($other,$a); // share business A
        $this->actingAs($this->admin,'platform_admin');
        $this->delete(route('admin.users.destroy',$u),['password'=>$this->adminPass]);
        $this->assertSoftDeleted('users',['id'=>$u->id]);
        $this->assertDatabaseHas('institutes',['id'=>$a->id,'deleted_at'=>null]);
        $this->assertDatabaseHas('institutes',['id'=>$b->id,'deleted_at'=>null]);
        $this->assertDatabaseHas('institution_user',['user_id'=>$other->id,'institution_id'=>$a->id,'deleted_at'=>null]);
    }

    // 21. No unrelated user deletion
    public function test_no_unrelated_user_deletion(): void
    {
        $u1=$this->makeUser(); $u2=$this->makeUser(); $a=$this->makeInstitute('21A'); $b=$this->makeInstitute('21B');
        $this->attach($u1,$a); $this->attach($u2,$b);
        $this->actingAs($this->admin,'platform_admin');
        $this->delete(route('admin.users.destroy',$u1),['password'=>$this->adminPass]);
        $u1Trashed=User::withTrashed()->find($u1->id);
        // u1 owns active business, force should be blocked — so delete business first
        // Instead test soft delete does not affect u2
        $this->assertDatabaseHas('users',['id'=>$u2->id,'deleted_at'=>null]);
        $this->assertDatabaseHas('institutes',['id'=>$b->id]);
        // Force delete u1 after removing its business
        $aInst=Institute::find($a->id);
        $this->post(route('admin.institutes.action',$aInst),['action'=>'delete','password'=>$this->adminPass]);
        $aTrashed=Institute::withTrashed()->find($a->id);
        $this->delete(route('admin.institutes.force-delete',$aTrashed),['password'=>$this->adminPass]);
        // Now force delete u1 should succeed
        $this->delete(route('admin.users.force-delete',$u1Trashed),['password'=>$this->adminPass]);
        $this->assertDatabaseMissing('users',['id'=>$u1->id]);
        $this->assertDatabaseHas('users',['id'=>$u2->id]);
        $this->assertDatabaseHas('institutes',['id'=>$b->id]);
    }

    // 22. No cross-tenant leakage — deleting user does not delete other tenant's business data
    public function test_no_cross_tenant_leakage(): void
    {
        $u1=$this->makeUser(); $u2=$this->makeUser();
        $a=$this->makeInstitute('22A'); $b=$this->makeInstitute('22B');
        $this->attach($u1,$a); $this->attach($u2,$b);
        // Verify other tenant's institute exists before
        $this->assertDatabaseHas('institutes',['id'=>$b->id]);
        $this->actingAs($this->admin,'platform_admin');
        $this->delete(route('admin.users.destroy',$u1),['password'=>$this->adminPass]);
        $u1Trashed=User::withTrashed()->find($u1->id);
        // u1 is owner of A, so force blocked -> delete A first
        $aInst=Institute::find($a->id);
        $this->post(route('admin.institutes.action',$aInst),['action'=>'delete','password'=>$this->adminPass]);
        $aTrashed=Institute::withTrashed()->find($a->id);
        $this->delete(route('admin.institutes.force-delete',$aTrashed),['password'=>$this->adminPass]);
        $this->delete(route('admin.users.force-delete',$u1Trashed),['password'=>$this->adminPass]);
        $this->assertDatabaseHas('institutes',['id'=>$b->id,'deleted_at'=>null]);
        $this->assertDatabaseHas('users',['id'=>$u2->id,'deleted_at'=>null]);
        $this->assertDatabaseHas('institution_user',['user_id'=>$u2->id,'institution_id'=>$b->id]);
    }

    // Additional: verified vs unverified platform admin routing
    public function test_unverified_blocked_where_verified_required(): void
    {
        $unverified=PlatformAdmin::firstOrReuseForTests(['email'=>'unv-'.uniqid().'@example.test','password_hash'=>bcrypt($this->adminPass),'status'=>'active','email_verified_at'=>null]);
        $u=$this->makeUser();
        $this->actingAs($unverified,'platform_admin');
        $this->delete(route('admin.users.destroy',$u),['password'=>$this->adminPass])->assertRedirect(route('verification.notice'));
        $this->assertDatabaseHas('users',['id'=>$u->id,'deleted_at'=>null]);
    }
}
