<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\CrmLead;
use App\Models\CrmLeadSource;
use App\Models\HrApplication;
use App\Models\HrDepartment;
use App\Models\HrDesignation;
use App\Models\HrEmployee;
use App\Models\HrInterview;
use App\Models\HrOffer;
use App\Models\HrRequisition;
use App\Models\HrVacancy;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class HrRecruitmentTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    private function country(): Country
    {
        return Country::firstOrCreate(['iso2'=>'BD'], ['name'=>'Bangladesh','iso3'=>'BGD','phone_code'=>'880','status'=>true]);
    }

    private function institute(?Country $c=null): Institute
    {
        $c ??= $this->country();
        return Institute::create(['name'=>'Recruit Inst '.uniqid(),'slug'=>'recruit-'.uniqid(),'country'=>$c->name,'country_id'=>$c->id,'status'=>'active']);
    }

    private function branch(Institute $i, string $name='Branch'): Branch
    {
        return Branch::create(['institute_id'=>$i->id,'name'=>$name.' '.uniqid(),'status'=>'active']);
    }

    private function user(Institute $i, string $role, ?int $branchId=null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id'=>$i->id,
            'role_id'=>Role::where('slug',$role)->firstOrFail()->id,
            'branch_id'=>$branchId,
            'first_name'=>ucfirst($role),
            'last_name'=>'User',
            'email'=>$role.'-'.uniqid().'@example.test',
            'phone'=>'01700'.rand(100000,999999),
            'password_hash'=>bcrypt('secret12345'),
            'status'=>'active',
        ]);
    }

    // ------------------------------------------------ Requisition

    public function test_requisition_lifecycle(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        TenantContext::set($inst->id);

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.requisitions.store'), [
            'title'=>'Backend Developer',
            'openings'=>2,
            'required_skills'=>'Laravel, MySQL',
            'experience'=>'2-4 years',
            'education'=>'BSc CSE',
            'salary_min'=>40000,
            'salary_max'=>80000,
        ])->assertRedirect();

        $req = HrRequisition::where('institute_id',$inst->id)->firstOrFail();
        $this->assertSame('draft',$req->status);
        $this->assertSame(2,$req->openings);
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_requisition_created','record_id'=>$req->id]);

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.requisitions.submit',$req))->assertRedirect();
        $req->refresh();
        $this->assertSame('pending_approval',$req->status);

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.requisitions.decide',$req), ['decision'=>'approved'])->assertRedirect();
        $req->refresh();
        $this->assertSame('approved',$req->status);
        $this->assertNotNull($req->approved_by);

        // Approved requisition auto-creates vacancy
        $vac = HrVacancy::where('requisition_id',$req->id)->firstOrFail();
        $this->assertSame('approved',$vac->status);
        $this->assertSame('Backend Developer',$vac->title);
    }

    public function test_requisition_rejection(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        TenantContext::set($inst->id);

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.requisitions.store'), [
            'title'=>'Designer','openings'=>1,
        ])->assertRedirect();
        $req = HrRequisition::firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.requisitions.submit',$req))->assertRedirect();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.requisitions.decide',$req), ['decision'=>'rejected','reason'=>'Budget cut'])->assertRedirect();
        $req->refresh();
        $this->assertSame('rejected',$req->status);
        $this->assertSame('Budget cut',$req->rejection_reason);
        $this->assertEquals(0, HrVacancy::where('requisition_id',$req->id)->count());
    }

    // ------------------------------------------------ Vacancy

    public function test_vacancy_status_transitions(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        TenantContext::set($inst->id);

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.vacancies.store'), [
            'title'=>'HR Manager','openings'=>1,
        ])->assertRedirect();
        $vac = HrVacancy::firstOrFail();
        $this->assertSame('draft',$vac->status);

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.vacancies.status',$vac), ['status'=>'published'])->assertRedirect();
        $vac->refresh();
        $this->assertSame('published',$vac->status);
        $this->assertNotNull($vac->published_at);

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.vacancies.status',$vac), ['status'=>'closed'])->assertRedirect();
        $vac->refresh();
        $this->assertSame('closed',$vac->status);

        // Terminal cannot be reopened
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.vacancies.status',$vac), ['status'=>'published'])->assertSessionHasErrors();
    }

    // ------------------------------------------------ Candidate (CRM reuse)

    public function test_candidate_creation_reuses_crm_lead(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        TenantContext::set($inst->id);

        $source = CrmLeadSource::firstOrCreate(['slug'=>'referral'], ['name'=>'Referral','display_order'=>1]);

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.candidates.store'), [
            'first_name'=>'John',
            'last_name'=>'Doe',
            'email'=>'john.doe'.uniqid().'@example.test',
            'phone'=>'01711111111',
            'source_id'=>$source->id,
            'skills'=>'PHP, Laravel',
            'experience'=>'3 years',
            'education'=>'BSc',
            'notes'=>'Excellent candidate',
        ])->assertRedirect();

        $lead = CrmLead::where('institute_id',$inst->id)->where('email','like','john.doe%')->firstOrFail();
        $this->assertSame('John',$lead->first_name);
        $this->assertStringContainsString('PHP, Laravel',$lead->interest_summary);
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_candidate_created','record_id'=>$lead->id]);
        // CRM integration: lead visible via CRM
        $this->assertDatabaseHas('crm_leads',['id'=>$lead->id,'institute_id'=>$inst->id]);
    }

    // ------------------------------------------------ Application & Pipeline

    public function test_application_and_pipeline_transitions(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        TenantContext::set($inst->id);

        // Create vacancy
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.vacancies.store'), ['title'=>'Developer','openings'=>1])->assertRedirect();
        $vac = HrVacancy::firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.vacancies.status',$vac), ['status'=>'published'])->assertRedirect();

        // Candidate
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.candidates.store'), [
            'first_name'=>'Alice','last_name'=>'Smith','email'=>'alice'.uniqid().'@example.test',
        ])->assertRedirect();
        $lead = CrmLead::where('institute_id',$inst->id)->latest('id')->firstOrFail();

        // Apply
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.store'), [
            'vacancy_id'=>$vac->id,
            'candidate_lead_id'=>$lead->id,
        ])->assertRedirect();

        $app = HrApplication::where('candidate_lead_id',$lead->id)->firstOrFail();
        $this->assertSame('new',$app->current_stage);
        $this->assertEquals(1, $app->histories()->count());
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_application_created']);

        // Transition new -> screening
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.stage',$app), ['to_stage'=>'screening','notes'=>'CV reviewed'])->assertRedirect();
        $app->refresh();
        $this->assertSame('screening',$app->current_stage);
        $this->assertEquals(2, $app->histories()->count());

        // screening -> shortlisted -> interview -> selected
        foreach (['shortlisted','interview','selected'] as $stage) {
            $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.stage',$app), ['to_stage'=>$stage])->assertRedirect();
        }
        $app->refresh();
        $this->assertSame('selected',$app->current_stage);

        // Duplicate application prevented
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.store'), [
            'vacancy_id'=>$vac->id,
            'candidate_lead_id'=>$lead->id,
        ])->assertSessionHasErrors();

        // Terminal stage cannot be changed
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.stage',$app), ['to_stage'=>'rejected'])->assertRedirect();
        $app->refresh();
        $this->assertSame('rejected',$app->current_stage);
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.stage',$app), ['to_stage'=>'hired'])->assertSessionHasErrors();
    }

    public function test_pipeline_hired_only_from_selected(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        TenantContext::set($inst->id);

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.candidates.store'), ['first_name'=>'Bob','email'=>'bob'.uniqid().'@example.test'])->assertRedirect();
        $lead = CrmLead::latest('id')->firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.vacancies.store'), ['title'=>'Test Vac'])->assertRedirect();
        $vac = HrVacancy::firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.store'), ['vacancy_id'=>$vac->id,'candidate_lead_id'=>$lead->id])->assertRedirect();
        $app = HrApplication::firstOrFail();
        // Try direct hired from new should fail
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.stage',$app), ['to_stage'=>'hired'])->assertSessionHasErrors();
    }

    // ------------------------------------------------ Interview

    public function test_interview_scheduling(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        TenantContext::set($inst->id);

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.candidates.store'), ['first_name'=>'Int','email'=>'int'.uniqid().'@example.test'])->assertRedirect();
        $lead = CrmLead::latest('id')->firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.vacancies.store'), ['title'=>'Vac'])->assertRedirect();
        $vac = HrVacancy::firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.store'), ['vacancy_id'=>$vac->id,'candidate_lead_id'=>$lead->id])->assertRedirect();
        $app = HrApplication::firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.stage',$app), ['to_stage'=>'screening'])->assertRedirect();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.stage',$app), ['to_stage'=>'shortlisted'])->assertRedirect();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.stage',$app), ['to_stage'=>'interview'])->assertRedirect();

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.interviews.store'), [
            'application_id'=>$app->id,
            'interviewer_id'=>$owner->id,
            'interview_type'=>'online',
            'scheduled_at'=>now()->addDay()->format('Y-m-d H:i:s'),
            'location'=>'Zoom',
        ])->assertRedirect();

        $interview = HrInterview::where('application_id',$app->id)->firstOrFail();
        $this->assertSame('scheduled',$interview->status);
        $this->assertSame('online',$interview->interview_type);
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_interview_scheduled']);
        $this->assertDatabaseHas('crm_tasks',['title'=>'Interview: '.$lead->first_name]);

        // Update interview with score
        $this->actingAs($owner,'institute_user')->put(route('hr.recruitment.interviews.update',$interview), [
            'score'=>85.5,
            'feedback'=>'Strong technical skills',
            'recommendation'=>'hire',
            'status'=>'completed',
        ])->assertRedirect();
        $interview->refresh();
        $this->assertEquals(85.5, (float)$interview->score);
        $this->assertSame('completed',$interview->status);
    }

    // ------------------------------------------------ Offer

    public function test_offer_creation(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        TenantContext::set($inst->id);

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.candidates.store'), ['first_name'=>'Off','email'=>'off'.uniqid().'@example.test'])->assertRedirect();
        $lead = CrmLead::latest('id')->firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.vacancies.store'), ['title'=>'Offer Vac'])->assertRedirect();
        $vac = HrVacancy::firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.store'), ['vacancy_id'=>$vac->id,'candidate_lead_id'=>$lead->id])->assertRedirect();
        $app = HrApplication::firstOrFail();
        foreach (['screening','shortlisted','interview','selected'] as $s) {
            $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.stage',$app), ['to_stage'=>$s])->assertRedirect();
        }

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.offers.store'), [
            'application_id'=>$app->id,
            'offered_salary'=>60000,
            'joining_date'=>now()->addDays(15)->toDateString(),
            'offer_date'=>now()->toDateString(),
            'salary_reference'=>'60000 BDT',
        ])->assertRedirect();

        $offer = HrOffer::where('application_id',$app->id)->firstOrFail();
        $this->assertEquals(60000, (float)$offer->offered_salary);
        $this->assertSame('draft',$offer->status);

        // Duplicate offer prevented
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.offers.store'), [
            'application_id'=>$app->id,
            'offered_salary'=>65000,
        ])->assertSessionHasErrors();

        // Send offer
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.offers.status',$offer), ['status'=>'sent'])->assertRedirect();
        $offer->refresh();
        $this->assertSame('sent',$offer->status);
    }

    // ------------------------------------------------ Hiring / Conversion

    public function test_hiring_conversion_creates_employee(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        $branch = $this->branch($inst,'Main');
        $dept = HrDepartment::create(['institute_id'=>$inst->id,'branch_id'=>$branch->id,'name'=>'Eng','display_order'=>0,'is_active'=>true]);
        $desig = HrDesignation::create(['institute_id'=>$inst->id,'department_id'=>$dept->id,'name'=>'Developer','display_order'=>0,'is_active'=>true]);
        TenantContext::set($inst->id);

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.candidates.store'), [
            'first_name'=>'Hire','last_name'=>'Me','email'=>'hire'.uniqid().'@example.test','phone'=>'01700000001',
        ])->assertRedirect();
        $lead = CrmLead::latest('id')->firstOrFail();

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.vacancies.store'), [
            'title'=>'Dev Vac','department_id'=>$dept->id,'designation_id'=>$desig->id,
        ])->assertRedirect();
        $vac = HrVacancy::firstOrFail();

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.store'), [
            'vacancy_id'=>$vac->id,'candidate_lead_id'=>$lead->id,
        ])->assertRedirect();
        $app = HrApplication::firstOrFail();
        foreach (['screening','shortlisted','interview','selected'] as $s) {
            $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.stage',$app), ['to_stage'=>$s])->assertRedirect();
        }

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.offers.store'), [
            'application_id'=>$app->id,
            'offered_salary'=>55000,
            'joining_date'=>now()->addDays(10)->toDateString(),
        ])->assertRedirect();

        // Hire
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.hire',$app), [
            'branch_id'=>$branch->id,
            'department_id'=>$dept->id,
            'designation_id'=>$desig->id,
            'employment_type'=>'full_time',
            'joining_date'=>now()->addDays(10)->toDateString(),
        ])->assertRedirect();

        $app->refresh();
        $this->assertSame('hired',$app->current_stage);
        $this->assertNotNull($app->hired_employee_id);
        $employee = HrEmployee::find($app->hired_employee_id);
        $this->assertNotNull($employee);
        $this->assertSame($lead->email,$employee->email);
        $this->assertSame($dept->id,$employee->department_id);
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_employee_hired']);

        // Lead converted to contact
        $lead->refresh();
        $this->assertNotNull($lead->converted_contact_id);
        $this->assertDatabaseHas('crm_contacts',['id'=>$lead->converted_contact_id]);

        // Preserve history
        $this->assertGreaterThanOrEqual(6, $app->histories()->count());

        // Duplicate hire prevented
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.hire',$app), [])->assertSessionHasErrors();

        // Duplicate employee by email prevented
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.candidates.store'), [
            'first_name'=>'Duplicate','email'=>$lead->email,
        ])->assertRedirect();
        $lead2 = CrmLead::latest('id')->firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.vacancies.store'), ['title'=>'Vac2'])->assertRedirect();
        $vac2 = HrVacancy::latest('id')->firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.store'), ['vacancy_id'=>$vac2->id,'candidate_lead_id'=>$lead2->id])->assertRedirect();
        $app2 = HrApplication::where('candidate_lead_id',$lead2->id)->firstOrFail();
        foreach (['screening','shortlisted','interview','selected'] as $s) {
            $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.stage',$app2), ['to_stage'=>$s])->assertRedirect();
        }
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.hire',$app2), [])->assertSessionHasErrors();
    }

    // ------------------------------------------------ Tenant & Branch Isolation

    public function test_tenant_isolation(): void
    {
        $a = $this->institute();
        $b = $this->institute();
        $ownerA = $this->user($a,'institute-owner');
        $ownerB = $this->user($b,'institute-owner');
        TenantContext::set($a->id);
        $this->actingAs($ownerA,'institute_user')->post(route('hr.recruitment.requisitions.store'), ['title'=>'A Req'])->assertRedirect();
        $req = HrRequisition::where('institute_id',$a->id)->firstOrFail();

        TenantContext::set($b->id);
        $this->actingAs($ownerB,'institute_user')->get(route('hr.recruitment.requisitions'))->assertOk()->assertDontSee('A Req');
        // B cannot approve A's requisition (404 via ensureInstitute)
        $this->actingAs($ownerB,'institute_user')->post(route('hr.recruitment.requisitions.submit',$req))->assertNotFound();

        // Candidate isolation
        TenantContext::set($a->id);
        $this->actingAs($ownerA,'institute_user')->post(route('hr.recruitment.candidates.store'), ['first_name'=>'TenantA','email'=>'ta'.uniqid().'@example.test'])->assertRedirect();
        $leadA = CrmLead::where('institute_id',$a->id)->latest('id')->firstOrFail();
        TenantContext::set($b->id);
        // B cannot apply with A's lead
        $this->actingAs($ownerB,'institute_user')->post(route('hr.recruitment.vacancies.store'), ['title'=>'B Vac'])->assertRedirect();
        $vacB = HrVacancy::where('institute_id',$b->id)->firstOrFail();
        $this->actingAs($ownerB,'institute_user')->post(route('hr.recruitment.applications.store'), ['vacancy_id'=>$vacB->id,'candidate_lead_id'=>$leadA->id])->assertNotFound();
    }

    public function test_branch_isolation(): void
    {
        $inst = $this->institute();
        $branchA = $this->branch($inst,'Branch A');
        $branchB = $this->branch($inst,'Branch B');
        $owner = $this->user($inst,'institute-owner');
        $mgrA = $this->user($inst,'branch-manager',$branchA->id);
        $mgrB = $this->user($inst,'branch-manager',$branchB->id);
        TenantContext::set($inst->id);
        // Create requisition for branch A
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.requisitions.store'), [
            'title'=>'Branch A Req','branch_id'=>$branchA->id,
        ])->assertRedirect();
        $req = HrRequisition::where('institute_id',$inst->id)->where('branch_id',$branchA->id)->firstOrFail();

        // Manager A can see, Manager B cannot
        BranchContext::set($branchA->id);
        $this->actingAs($mgrA,'institute_user')->get(route('hr.recruitment.requisitions'))->assertOk()->assertSee('Branch A Req');
        BranchContext::set($branchB->id);
        $this->actingAs($mgrB,'institute_user')->get(route('hr.recruitment.requisitions'))->assertOk()->assertDontSee('Branch A Req');
        // Branch manager lacks requisition.manage, so expect 403 (permission) rather than 404
        $this->actingAs($mgrB,'institute_user')->post(route('hr.recruitment.requisitions.submit',$req))->assertForbidden();
        BranchContext::clear();

        // Application branch isolation
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.vacancies.store'), ['title'=>'A Vac','branch_id'=>$branchA->id])->assertRedirect();
        $vac = HrVacancy::where('branch_id',$branchA->id)->firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.candidates.store'), ['first_name'=>'Branch','email'=>'branch'.uniqid().'@example.test'])->assertRedirect();
        $lead = CrmLead::where('institute_id',$inst->id)->latest('id')->firstOrFail();
        // Need to set lead branch to A for isolation? Create lead with branch A via direct
        $lead->update(['branch_id'=>$branchA->id]);
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.store'), ['vacancy_id'=>$vac->id,'candidate_lead_id'=>$lead->id])->assertRedirect();
        $app = HrApplication::where('candidate_lead_id',$lead->id)->firstOrFail();

        BranchContext::set($branchB->id);
        $this->actingAs($mgrB,'institute_user')->get(route('hr.recruitment.applications.show',$app))->assertNotFound();
        BranchContext::set($branchA->id);
        $this->actingAs($mgrA,'institute_user')->get(route('hr.recruitment.applications.show',$app))->assertOk();
        BranchContext::clear();
    }

    // ------------------------------------------------ Permissions

    public function test_permission_matrix(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        $receptionist = $this->user($inst,'receptionist');
        $teacher = $this->user($inst,'teacher');
        TenantContext::set($inst->id);

        // Receptionist cannot manage recruitment
        $this->actingAs($receptionist,'institute_user')->get(route('hr.recruitment.dashboard'))->assertForbidden();
        $this->actingAs($receptionist,'institute_user')->post(route('hr.recruitment.requisitions.store'), ['title'=>'X'])->assertForbidden();
        $this->actingAs($teacher,'institute_user')->get(route('hr.recruitment.dashboard'))->assertForbidden();

        // Owner can
        $this->actingAs($owner,'institute_user')->get(route('hr.recruitment.dashboard'))->assertOk();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.requisitions.store'), ['title'=>'Perm Test'])->assertRedirect();

        // Branch manager can view but limited approve
        $branch = $this->branch($inst);
        $mgr = $this->user($inst,'branch-manager',$branch->id);
        BranchContext::set($branch->id);
        $this->actingAs($mgr,'institute_user')->get(route('hr.recruitment.requisitions'))->assertOk();
        $this->actingAs($mgr,'institute_user')->post(route('hr.recruitment.requisitions.store'), ['title'=>'Mgr Req'])->assertForbidden(); // mgr lacks requisition.manage
        $this->actingAs($mgr,'institute_user')->get(route('hr.recruitment.vacancies'))->assertOk();
        BranchContext::clear();
    }

    // ------------------------------------------------ Audit & Historical Safety

    public function test_audit_and_historical_safety(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        TenantContext::set($inst->id);

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.requisitions.store'), ['title'=>'Audit Req'])->assertRedirect();
        $req = HrRequisition::firstOrFail();
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_requisition_created']);

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.requisitions.submit',$req))->assertRedirect();
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_requisition_submitted']);

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.candidates.store'), ['first_name'=>'Audit','email'=>'audit'.uniqid().'@example.test'])->assertRedirect();
        $lead = CrmLead::latest('id')->firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.vacancies.store'), ['title'=>'Audit Vac'])->assertRedirect();
        $vac = HrVacancy::firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.store'), ['vacancy_id'=>$vac->id,'candidate_lead_id'=>$lead->id])->assertRedirect();
        $app = HrApplication::firstOrFail();
        $this->assertDatabaseHas('audit_logs',['module'=>'hr','action'=>'hr_application_created']);

        // Stage history immutable
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.stage',$app), ['to_stage'=>'screening'])->assertRedirect();
        $hist = $app->histories()->where('to_stage','screening')->firstOrFail();
        $this->assertSame('new',$hist->from_stage);
        $this->assertSame('screening',$hist->to_stage);

        // Candidate history preserved after hire
        foreach (['shortlisted','interview','selected'] as $s) {
            $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.stage',$app), ['to_stage'=>$s])->assertRedirect();
        }
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.hire',$app))->assertRedirect();
        $app->refresh();
        $this->assertSame('hired',$app->current_stage);
        // Histories still there, not deleted
        $this->assertGreaterThanOrEqual(5, $app->histories()->count());
        // CRM lead still exists (historical)
        $this->assertDatabaseHas('crm_leads',['id'=>$lead->id]);
    }

    // ------------------------------------------------ Reports

    public function test_reports(): void
    {
        $inst = $this->institute();
        $owner = $this->user($inst,'institute-owner');
        TenantContext::set($inst->id);

        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.requisitions.store'), ['title'=>'Report Req'])->assertRedirect();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.vacancies.store'), ['title'=>'Report Vac'])->assertRedirect();
        $vac = HrVacancy::firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.candidates.store'), ['first_name'=>'Report','email'=>'report'.uniqid().'@example.test'])->assertRedirect();
        $lead = CrmLead::latest('id')->firstOrFail();
        $this->actingAs($owner,'institute_user')->post(route('hr.recruitment.applications.store'), ['vacancy_id'=>$vac->id,'candidate_lead_id'=>$lead->id])->assertRedirect();

        $this->actingAs($owner,'institute_user')->get(route('hr.recruitment.dashboard'))->assertOk()->assertSee('Recruitment Dashboard');
        $this->actingAs($owner,'institute_user')->get(route('hr.recruitment.vacancies'))->assertOk();
        $this->actingAs($owner,'institute_user')->get(route('hr.recruitment.applications'))->assertOk();
    }
}
