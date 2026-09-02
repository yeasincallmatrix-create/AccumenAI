<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\Demo\DemoDataService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DemoDataAccountsTest extends TestCase
{
    use DatabaseTransactions;

    private DemoDataService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DemoDataService(new UserAccountService, new MembershipService);
    }

    private function inst(string $industry, ?string $sub = null): Institute
    {
        return Institute::create([
            'name' => ucfirst($industry).' T'.mt_rand(100000, 999999),
            'slug' => $industry.'-t'.mt_rand(100000, 999999),
            'industry' => $industry,
            'sub_industry' => $sub,
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
    }

    private function owner(Institute $i): User
    {
        return $this->service->createOwnerAccount($i, $i->industry, $i->sub_industry, '12345678');
    }

    public function test_owner_education(): void
    {
        $i = $this->inst('education', 'school');
        $o = $this->owner($i);
        $this->assertEquals('school@gmail.com', $o->email);
        $this->assertEquals('owner', $o->account_type);
    }

    public function test_owner_healthcare(): void
    {
        $i = $this->inst('healthcare', 'hospital');
        $o = $this->owner($i);
        $this->assertEquals('hospital@gmail.com', $o->email);
        $this->assertEquals('owner', $o->account_type);
    }

    public function test_owner_retail(): void
    {
        $i = $this->inst('retail', 'supermarket');
        $o = $this->owner($i);
        $this->assertEquals('supermarket@gmail.com', $o->email);
    }

    public function test_owner_manufacturing(): void
    {
        $i = $this->inst('manufacturing', 'garments');
        $o = $this->owner($i);
        $this->assertEquals('garments@gmail.com', $o->email);
    }

    public function test_owner_restaurant(): void
    {
        $i = $this->inst('restaurant');
        $o = $this->owner($i);
        $this->assertEquals('restaurant@gmail.com', $o->email);
    }

    public function test_owner_real_estate(): void
    {
        $i = $this->inst('real_estate');
        $o = $this->owner($i);
        $this->assertEquals('real_estate@gmail.com', $o->email);
    }

    public function test_owner_receives_institute_owner_role(): void
    {
        $i = $this->inst('education');
        $o = $this->owner($i);
        $rid = Role::where('slug', 'institute-owner')->value('id');
        $m = Membership::where('user_id', $o->id)->where('institution_id', $i->id)->where('role_id', $rid)->first();
        $this->assertNotNull($m);
        $this->assertEquals('active', $m->status);
        $this->assertNull($m->branch_id);
    }

    public function test_staff_account_type(): void
    {
        $i = $this->inst('education');
        $this->owner($i);
        $staff = $this->service->createStaffAccounts($i, ['accountant' => 1]);
        $this->assertCount(1, $staff);
        $this->assertEquals('staff', $staff[0]->account_type);
    }

    public function test_staff_receives_correct_role(): void
    {
        $i = $this->inst('education');
        $this->owner($i);
        $staff = $this->service->createStaffAccounts($i, ['accountant' => 1]);
        $rid = Role::where('slug', 'accountant')->value('id');
        $m = Membership::where('user_id', $staff[0]->id)->where('institution_id', $i->id)->where('role_id', $rid)->first();
        $this->assertNotNull($m);
    }

    public function test_tenant_isolation(): void
    {
        $a = $this->inst('education', 'school');
        $b = $this->inst('healthcare', 'hospital');
        $oa = $this->owner($a);
        $ob = $this->owner($b);
        $this->assertNotEquals($oa->email, $ob->email);
        $this->assertTrue(Membership::where('user_id', $oa->id)->where('institution_id', $a->id)->exists());
        $this->assertTrue(Membership::where('user_id', $ob->id)->where('institution_id', $b->id)->exists());
    }

    public function test_owner_password_hash(): void
    {
        $i = $this->inst('education', 'school');
        $o = $this->owner($i);
        $this->assertTrue(Hash::check('12345678', $o->password_hash));
    }

    public function test_staff_password_hash(): void
    {
        $i = $this->inst('education');
        $this->owner($i);
        $staff = $this->service->createStaffAccounts($i, ['accountant' => 1]);
        $this->assertTrue(Hash::check('12345678', $staff[0]->password_hash));
    }

    public function test_deterministic_email(): void
    {
        $i = $this->inst('education', 'computer_it_training_institute');
        $o = $this->owner($i);
        $this->assertEquals('computer_it_training_institute@gmail.com', $o->email);
    }

    public function test_existing_user_not_overwritten(): void
    {
        $i = $this->inst('education', 'school');
        $o = $this->owner($i);
        $originalName = $o->first_name;
        $o2 = $this->service->createOwnerAccount($i, 'education', 'school', '12345678');
        $this->assertEquals($o->id, $o2->id);
        $this->assertEquals($originalName, $o2->first_name);
    }

    public function test_duplicate_email_protected(): void
    {
        $i = $this->inst('education', 'school');
        $o = $this->owner($i);
        $this->assertDatabaseHas('users', ['email' => 'school@gmail.com']);
        $o2 = $this->service->createOwnerAccount($i, 'education', 'school', '12345678');
        $this->assertEquals(1, User::where('email', 'school@gmail.com')->count());
    }

    public function test_owner_cannot_become_staff(): void
    {
        $i = $this->inst('education');
        $o = $this->owner($i);
        $tid = Role::where('slug', 'teacher')->value('id');
        $this->expectException(\App\Exceptions\AccountTypeMismatchException::class);
        app(MembershipService::class)->assign($o, $i->id, $tid);
    }

    public function test_staff_cannot_become_owner(): void
    {
        $i = $this->inst('education');
        $this->owner($i);
        $staff = $this->service->createStaffAccounts($i, ['accountant' => 1]);
        $oid = Role::where('slug', 'institute-owner')->value('id');
        $this->expectException(\App\Exceptions\AccountTypeMismatchException::class);
        app(MembershipService::class)->assign($staff[0], $i->id, $oid);
    }

    public function test_multiple_staff_roles(): void
    {
        $i = $this->inst('education');
        $this->owner($i);
        $staff = $this->service->createStaffAccounts($i, ['accountant' => 2, 'receptionist' => 2]);
        $this->assertCount(4, $staff);
        foreach ($staff as $s) {
            $this->assertEquals('staff', $s->account_type);
        }
    }
}
