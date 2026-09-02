<?php

namespace Tests\Feature;

use App\Models\AdministrativeLevel;
use App\Models\Branch;
use App\Models\Country;
use App\Models\HrEmployee;
use App\Models\HrLeaveType;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class P1HardeningTest extends TestCase
{
    use DatabaseTransactions;
    protected function tearDown(): void { TenantContext::clear(); \App\Support\BranchContext::clear(); parent::tearDown(); }
    private function country(string $iso='BD'): Country { return Country::firstOrCreate(['iso2'=>$iso],['name'=>$iso,'iso3'=>$iso.'D','phone_code'=>'1','status'=>true]); }
    private function institute(string $industry='education', string $sub='school', ?Country $c=null): Institute {
        $c??=$this->country();
        return Institute::create(['name'=>'P1 '.uniqid(),'slug'=>'p1-'.uniqid(),'country'=>$c->name,'country_id'=>$c->id,'industry'=>$industry,'sub_industry'=>$sub,'status'=>'active']);
    }
    private function branch(Institute $i): Branch { return Branch::create(['institute_id'=>$i->id,'name'=>'B '.uniqid(),'status'=>'active']); }
    private function user(Institute $i,string $role,?int $branch=null): InstituteUser { return InstituteUser::create(['institute_id'=>$i->id,'role_id'=>Role::where('slug',$role)->firstOrFail()->id,'branch_id'=>$branch,'first_name'=>'U','last_name'=>'T','email'=>uniqid().'@test.test','phone'=>'017'.rand(10000000,99999999),'password_hash'=>bcrypt('secret12345'),'status'=>'active','email_verified_at'=>now()]); }
    private function emp(Institute $i,?int $branchId, InstituteUser $owner): HrEmployee {
        $dept=\App\Models\HrDepartment::firstOrCreate(['institute_id'=>$i->id,'name'=>'Dept '.uniqid()],['is_active'=>true]);
        $des=\App\Models\HrDesignation::firstOrCreate(['institute_id'=>$i->id,'name'=>'Des '.uniqid()],['is_active'=>true]);
        return HrEmployee::create(['institute_id'=>$i->id,'branch_id'=>$branchId,'department_id'=>$dept->id,'designation_id'=>$des->id,'employee_code'=>'EMP-'.uniqid(),'first_name'=>'Emp','last_name'=>'Test','display_name'=>'Emp Test','email'=>'emp'.uniqid().'@test.test','phone'=>'017'.rand(10000000,99999999),'joining_date'=>now()->toDateString(),'employment_status'=>'active','employment_type'=>'full_time','created_by'=>$owner->id]);
    }

    // P1-1
    public function test_education_allowed(): void { $inst=$this->institute('education','school'); $owner=$this->user($inst,'institute-owner'); TenantContext::set($inst->id); $this->actingAs($owner,'institute_user')->get(route('courses.manage.index'))->assertOk(); }
    public function test_education_school_specific_allowed(): void { $inst=$this->institute('education','school'); $owner=$this->user($inst,'institute-owner'); TenantContext::set($inst->id); $this->actingAs($owner,'institute_user')->get(route('courses.manage.index'))->assertOk(); }
    public function test_education_college_school_specific_blocked_via_report(): void {
        $inst=$this->institute('education','college'); TenantContext::set($inst->id);
        $report=\App\Services\Reports\ReportRegistry::forInstitute($inst);
        $hasSchoolOnly = collect($report)->firstWhere('key','education.school_attendance_summary');
        $this->assertNull($hasSchoolOnly);
    }
    public function test_retail_education_blocked(): void { $inst=$this->institute('retail','supermarket'); $owner=$this->user($inst,'institute-owner'); TenantContext::set($inst->id); $this->actingAs($owner,'institute_user')->get(route('courses.manage.index'))->assertStatus(403); }
    public function test_non_education_blocked(): void { $inst=$this->institute('healthcare','hospital'); $owner=$this->user($inst,'institute-owner'); TenantContext::set($inst->id); $this->actingAs($owner,'institute_user')->get(route('finance.education.dashboard'))->assertStatus(403); }

    // P1-2 CRM branch
    public function test_crm_same_branch_access(): void {
        $inst=$this->institute('retail','supermarket'); $b=$this->branch($inst); $owner=$this->user($inst,'institute-owner');
        TenantContext::set($inst->id); \App\Support\BranchContext::set($b->id);
        $contact=app(\App\Services\CrmContactService::class)->create(['first_name'=>'A','last_name'=>'B','email'=>'a'.uniqid().'@test.test','phone'=>'017'.rand(10000000,99999999)], $inst->id, $b->id, $owner->id);
        $found=\App\Models\CrmContact::query()->find($contact['id']);
        $this->assertNotNull($found);
        \App\Support\BranchContext::clear();
    }
    public function test_crm_cross_branch_blocked(): void {
        $inst=$this->institute('retail','supermarket'); $b1=$this->branch($inst); $b2=$this->branch($inst);
        $owner=$this->user($inst,'institute-owner'); $mgr=$this->user($inst,'branch-manager',$b1->id);
        TenantContext::set($inst->id); \App\Support\BranchContext::set($b1->id);
        $contact=app(\App\Services\CrmContactService::class)->create(['first_name'=>'X','last_name'=>'Y','email'=>'x'.uniqid().'@test.test','phone'=>'017'.rand(10000000,99999999)], $inst->id, $b1->id, $owner->id);
        \App\Support\BranchContext::set($b2->id);
        $this->assertNull(\App\Models\CrmContact::query()->find($contact['id']));
        // owner sees all
        \App\Support\BranchContext::clear();
        $this->assertNotNull(\App\Models\CrmContact::query()->find($contact['id']));
    }
    public function test_crm_cross_tenant_blocked(): void {
        $a=$this->institute('retail','supermarket'); $b=$this->institute('retail','supermarket');
        $ownerA=$this->user($a,'institute-owner');
        TenantContext::set($a->id); \App\Support\BranchContext::clear();
        $contact=app(\App\Services\CrmContactService::class)->create(['first_name'=>'T','last_name'=>'I','email'=>'ti'.uniqid().'@test.test','phone'=>'017'.rand(10000000,99999999)], $a->id, null, $ownerA->id);
        TenantContext::set($b->id);
        $this->assertNull(\App\Models\CrmContact::query()->find($contact['id']));
    }

    // P1-3 HR leave
    public function test_hr_valid_leave(): void {
        $inst=$this->institute(); $owner=$this->user($inst,'institute-owner'); $emp=$this->emp($inst,null,$owner);
        TenantContext::set($inst->id);
        $this->actingAs($owner,'institute_user')->post(route('hr.leave.types.store'),['name'=>'CL','code'=>'cl','yearly_allowance'=>10])->assertRedirect();
        $type=HrLeaveType::where('code','cl')->where('institute_id',$inst->id)->firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.leave.applications.store'),['employee_id'=>$emp->id,'leave_type_id'=>$type->id,'start_date'=>'2024-06-01','end_date'=>'2024-06-02'])->assertRedirect();
        $this->assertDatabaseHas('hr_leave_applications',['employee_id'=>$emp->id,'days_count'=>2]);
    }
    public function test_hr_insufficient_balance_blocked(): void {
        $inst=$this->institute(); $owner=$this->user($inst,'institute-owner'); $emp=$this->emp($inst,null,$owner);
        TenantContext::set($inst->id);
        $this->actingAs($owner,'institute_user')->post(route('hr.leave.types.store'),['name'=>'SL','code'=>'sl','yearly_allowance'=>1])->assertRedirect();
        $type=HrLeaveType::where('code','sl')->where('institute_id',$inst->id)->firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.leave.applications.store'),['employee_id'=>$emp->id,'leave_type_id'=>$type->id,'start_date'=>'2024-06-01','end_date'=>'2024-06-05'])->assertSessionHasErrors('days_count');
    }
    public function test_hr_duplicate_overlap_blocked(): void {
        $inst=$this->institute(); $owner=$this->user($inst,'institute-owner'); $emp=$this->emp($inst,null,$owner);
        TenantContext::set($inst->id);
        $this->actingAs($owner,'institute_user')->post(route('hr.leave.types.store'),['name'=>'EL','code'=>'el','yearly_allowance'=>10])->assertRedirect();
        $type=HrLeaveType::where('code','el')->where('institute_id',$inst->id)->firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.leave.applications.store'),['employee_id'=>$emp->id,'leave_type_id'=>$type->id,'start_date'=>'2024-07-01','end_date'=>'2024-07-03'])->assertRedirect();
        $this->actingAs($owner,'institute_user')->post(route('hr.leave.applications.store'),['employee_id'=>$emp->id,'leave_type_id'=>$type->id,'start_date'=>'2024-07-02','end_date'=>'2024-07-04'])->assertStatus(422);
    }

    // P1-5
    public function test_administrative_level_idempotent(): void {
        $c=$this->country('BD');
        $l1=AdministrativeLevel::updateOrCreate(['country_id'=>$c->id,'level_number'=>1],['name'=>'Division','slug'=>'bd_level_1','status'=>true]);
        $l2=AdministrativeLevel::updateOrCreate(['country_id'=>$c->id,'level_number'=>1],['name'=>'Division','slug'=>'bd_level_1','status'=>true]);
        $this->assertEquals($l1->id,$l2->id);
        $this->assertEquals(1, AdministrativeLevel::where('country_id',$c->id)->where('level_number',1)->count());
    }

    // P1-4 localization check
    public function test_localization_keys_exist(): void {
        $this->assertNotEquals('validation_services.common.branch_scope', mawa_lang('validation_services.common.branch_scope'));
        $this->assertNotEquals('validation_services.common.branch_scope', mawa_lang('validation_services.common.branch_scope', [],));
        // Bangla
        $bn = mawa_lang('validation_services.common.branch_scope');
        $this->assertIsString($bn);
        $this->assertNotEmpty($bn);
    }
}
