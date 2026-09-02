<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Country;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\CrmLeadStatus;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\InvoiceService;
use App\Services\Accounting\PartyService;
use App\Services\Ai\AiContext;
use App\Services\Ai\AiToolRegistry;
use App\Services\Ai\Tools\Core\GetCrmSummaryTool;
use App\Services\Ai\Tools\Core\GetFinancialSummaryTool;
use App\Services\Ai\Tools\Core\IncomeExpenseTool;
use App\Services\Ai\Tools\Education\StudentsTool;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Step 33 — AI Core: the new industry-neutral finance & CRM tools must reuse
 * the Step 31/32 services/models, respect permissions and stay read-only,
 * tenant-safe, branch-safe and bounded.
 */
class AiCoreToolingTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
    }

    // ------------------------------------------------------------ Fixtures

    protected function freshInstitute(string $industry = 'education'): Institute
    {
        $country = Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );

        $institute = Institute::create([
            'name' => 'Fixture '.ucfirst($industry).' '.mt_rand(1000, 9999),
            'slug' => $industry.'-'.mt_rand(1000, 9999),
            'industry' => $industry,
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);

        InstituteSetting::withoutGlobalScopes()->create([
            'institute_id' => $institute->id,
            'ai_config' => [
                'enabled' => true,
                'features' => ['assistant'],
                'daily_limit' => 0,
                'monthly_limit' => 0,
            ],
        ]);

        return $institute;
    }

    protected function branch(Institute $institute, string $name): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    protected function userFor(Institute $institute, string $roleSlug, string $prefix, ?Branch $branch = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'first_name' => $prefix,
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function ownerFor(Institute $institute, string $prefix): InstituteUser
    {
        return $this->userFor($institute, 'institute-owner', $prefix);
    }

    protected function contextFor(InstituteUser $user, Institute $institute): AiContext
    {
        return AiContext::resolve($user, $institute);
    }

    protected function setupAccounting(Institute $institute, ?Branch $branch = null): void
    {
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch?->id);
    }

    protected function postSale(Institute $institute, ?Branch $branch, int $actorId, int $partyId, float $amount): void
    {
        app(AccountingSetupService::class)->setSetting($institute->id, 'invoice_auto_post', true, $branch?->id);

        app(InvoiceService::class)->create($institute->id, $branch?->id, [
            'party_id' => $partyId,
            'invoice_type' => 'other',
            'discount' => 0,
            'items' => [
                ['description' => 'Service', 'amount' => $amount],
            ],
        ], $actorId);
    }

    protected function customer(Institute $institute, int $actorId): int
    {
        $party = app(PartyService::class)->create($institute->id, null, [
            'type' => 'customer',
            'name' => 'Acme '.mt_rand(1000, 9999),
            'phone' => '01711'.rand(100000, 999999),
        ], $actorId);

        return (int) $party->id;
    }

    protected function leadStatus(string $slug): int
    {
        return (int) CrmLeadStatus::query()->where('slug', $slug)->value('id');
    }

    // ------------------------------------------------ Finance summary tool

    public function test_financial_summary_tool_reports_ledger_totals(): void
    {
        $institute = $this->freshInstitute('education');
        $branch = $this->branch($institute, 'Downtown');
        TenantContext::set($institute->id);
        $this->setupAccounting($institute, $branch);
        $owner = $this->ownerFor($institute, 'fin-summary');
        $party = $this->customer($institute, (int) $owner->id);
        $this->postSale($institute, $branch, (int) $owner->id, $party, 100.00);

        $ctx = $this->contextFor($owner, $institute);
        $result = app(GetFinancialSummaryTool::class)->handle(['section' => 'overview'], $ctx);

        $this->assertSame(100.0, $result['income_statement']['total_income']);
        $this->assertSame(0.0, $result['income_statement']['total_expense']);
        $this->assertSame(100.0, $result['income_statement']['net']);
        $this->assertSame(100.0, $result['receivables_payables']['total_receivable']);
        $this->assertSame(0.0, $result['receivables_payables']['total_payable']);
        $this->assertSame(100.0, $result['balance_sheet']['total_assets']);
        $this->assertSame(100.0, $result['balance_sheet']['total_equity']);
        $this->assertSame(100.0, $result['balance_sheet']['net_income']);
        $this->assertIsArray($result['cash_bank']);

        $single = app(GetFinancialSummaryTool::class)->handle(['section' => 'income'], $ctx);
        $this->assertArrayHasKey('income_statement', $single);
        $this->assertArrayNotHasKey('balance_sheet', $single);
        $this->assertArrayNotHasKey('receivables_payables', $single);
    }

    public function test_financial_summary_tool_is_read_only_and_bounded(): void
    {
        $tool = app(GetFinancialSummaryTool::class);
        $this->assertSame('read', $tool->mode());
        $this->assertSame('finance.view', $tool->permission());

        $institute = $this->freshInstitute('education');
        $branch = $this->branch($institute, 'Central');
        TenantContext::set($institute->id);
        $this->setupAccounting($institute, $branch);
        $owner = $this->ownerFor($institute, 'fin-bound');

        for ($i = 0; $i < 15; $i++) {
            $party = $this->customer($institute, (int) $owner->id);
            $this->postSale($institute, $branch, (int) $owner->id, $party, 10.00);
        }

        $ctx = $this->contextFor($owner, $institute);
        $result = app(GetFinancialSummaryTool::class)->handle([
            'section' => 'receivables',
            'limit' => 5,
        ], $ctx);

        $this->assertCount(5, $result['top_customers']);
        $this->assertSame([], $result['top_suppliers']);
        $this->assertSame(150.0, $result['receivables_payables']['total_receivable']);
    }

    public function test_financial_summary_tool_requires_finance_permission(): void
    {
        $institute = $this->freshInstitute('education');
        TenantContext::set($institute->id);
        $registry = app(AiToolRegistry::class);

        $teacher = $this->userFor($institute, 'teacher', 'fin-denied');
        $teacherCtx = $this->contextFor($teacher, $institute);
        $this->assertArrayNotHasKey('get_financial_summary', $registry->available($teacherCtx));
        $this->assertArrayNotHasKey('get_income_expense', $registry->available($teacherCtx));

        $admin = $this->userFor($institute, 'institute-admin', 'fin-allowed');
        $adminCtx = $this->contextFor($admin, $institute);
        $this->assertArrayHasKey('get_financial_summary', $registry->available($adminCtx));
    }

    public function test_financial_summary_tool_isolates_tenants(): void
    {
        $institute = $this->freshInstitute('education');
        $branch = $this->branch($institute, 'Downtown');
        TenantContext::set($institute->id);
        $this->setupAccounting($institute, $branch);
        $owner = $this->ownerFor($institute, 'fin-tenant');
        $party = $this->customer($institute, (int) $owner->id);
        $this->postSale($institute, $branch, (int) $owner->id, $party, 500.00);

        $other = $this->freshInstitute('education');
        $otherBranch = $this->branch($other, 'Other');

        TenantContext::set($other->id);
        $this->setupAccounting($other, $otherBranch);
        $otherOwner = $this->ownerFor($other, 'fin-other');
        $otherParty = $this->customer($other, (int) $otherOwner->id);
        $this->postSale($other, $otherBranch, (int) $otherOwner->id, $otherParty, 999999.00);

        TenantContext::set($institute->id);

        $ctx = $this->contextFor($owner, $institute);
        $result = app(GetFinancialSummaryTool::class)->handle([
            'section' => 'overview',
            'institute_id' => $other->id,
        ], $ctx);

        $this->assertSame(500.0, $result['income_statement']['net']);
        $this->assertSame(500.0, $result['receivables_payables']['total_receivable']);
        $this->assertStringNotContainsString('999999', json_encode($result));
    }

    public function test_financial_summary_tool_respects_branch_restriction(): void
    {
        $institute = $this->freshInstitute('education');
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        TenantContext::set($institute->id);
        $this->setupAccounting($institute, $branchB);
        $owner = $this->ownerFor($institute, 'fin-branch');
        $ownerId = (int) $owner->id;

        $partyB = $this->customer($institute, $ownerId);
        $this->postSale($institute, $branchB, $ownerId, $partyB, 777.00);

        $managerA = $this->userFor($institute, 'branch-manager', 'fin-mgr-a', $branchA);
        $ctxA = $this->contextFor($managerA, $institute);

        $result = app(GetFinancialSummaryTool::class)->handle([
            'section' => 'income',
            'branch_id' => $branchB->id,
        ], $ctxA);

        $this->assertSame(0.0, $result['income_statement']['net'], 'branch A manager must not see branch B data');
    }

    // ------------------------------------------------------- CRM summary tool

    public function test_crm_summary_tool_counts_contacts_leads_and_activities(): void
    {
        $institute = $this->freshInstitute('education');
        $branch = $this->branch($institute, 'Downtown');
        TenantContext::set($institute->id);

        $contact = CrmContact::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'first_name' => 'Rahim',
            'last_name' => 'Ahmed',
            'is_customer' => true,
            'status' => 'active',
        ]);

        CrmLead::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'first_name' => 'New',
            'last_name' => 'Lead',
            'status_id' => $this->leadStatus('new'),
            'value_amount' => 500,
        ]);
        CrmLead::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'first_name' => 'Won',
            'last_name' => 'Deal',
            'status_id' => $this->leadStatus('won'),
            'value_amount' => 1000,
        ]);

        CrmActivity::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'subject_type' => 'contact',
            'subject_id' => $contact->id,
            'type' => 'call',
            'summary' => 'Intro call',
            'activity_at' => Carbon::today(),
        ]);
        CrmActivity::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'subject_type' => 'contact',
            'subject_id' => $contact->id,
            'type' => 'meeting',
            'summary' => 'Site visit',
            'activity_at' => Carbon::today()->subDays(2),
        ]);

        $owner = $this->ownerFor($institute, 'crm-summary');
        $ctx = $this->contextFor($owner, $institute);

        $result = app(GetCrmSummaryTool::class)->handle([], $ctx);

        $this->assertSame(1, $result['contacts']['total']);
        $this->assertSame(1, $result['contacts']['customers']);
        $this->assertSame(2, $result['leads']['total']);
        $this->assertSame(1, $result['leads']['open']);
        $this->assertSame(1500.0, $result['leads']['total_value']);
        $this->assertSame(1000.0, $result['leads']['won_value']);
        $this->assertArrayHasKey('new', $result['leads']['by_status']);
        $this->assertArrayHasKey('won', $result['leads']['by_status']);
        $this->assertSame(2, $result['activities']['count']);
        $this->assertSame(1, $result['activities']['by_type']['call']);
        $this->assertSame(1, $result['activities']['by_type']['meeting']);
        $this->assertCount(2, $result['rows']);

        $filtered = app(GetCrmSummaryTool::class)->handle(['lead_status' => 'won'], $ctx);
        $this->assertSame(1, $filtered['leads']['total']);
        $this->assertSame(1000.0, $filtered['leads']['won_value']);
    }

    public function test_crm_summary_tool_is_read_only_and_bounded(): void
    {
        $tool = app(GetCrmSummaryTool::class);
        $this->assertSame('read', $tool->mode());
        $this->assertSame('crm.view', $tool->permission());

        $institute = $this->freshInstitute('education');
        $branch = $this->branch($institute, 'Central');
        TenantContext::set($institute->id);
        $contact = CrmContact::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'first_name' => 'Count',
            'last_name' => 'Me',
            'status' => 'active',
        ]);

        for ($i = 0; $i < 8; $i++) {
            CrmActivity::create([
                'institute_id' => $institute->id,
                'branch_id' => $branch->id,
                'subject_type' => 'contact',
                'subject_id' => $contact->id,
                'type' => 'note',
                'summary' => 'Activity '.$i,
                'activity_at' => Carbon::today()->subHours($i),
            ]);
        }

        $owner = $this->ownerFor($institute, 'crm-bound');
        $ctx = $this->contextFor($owner, $institute);

        $result = app(GetCrmSummaryTool::class)->handle(['limit' => 3], $ctx);
        $this->assertCount(3, $result['rows']);
        $this->assertSame(8, $result['activities']['count']);
    }

    public function test_crm_summary_tool_requires_crm_permission(): void
    {
        $institute = $this->freshInstitute('education');
        TenantContext::set($institute->id);
        $registry = app(AiToolRegistry::class);

        $teacher = $this->userFor($institute, 'teacher', 'crm-denied');
        $teacherCtx = $this->contextFor($teacher, $institute);
        $this->assertArrayNotHasKey('get_crm_summary', $registry->available($teacherCtx));

        $accountant = $this->userFor($institute, 'accountant', 'crm-view');
        $accountantCtx = $this->contextFor($accountant, $institute);
        $this->assertArrayHasKey('get_crm_summary', $registry->available($accountantCtx));
    }

    public function test_crm_summary_tool_isolates_tenants(): void
    {
        $institute = $this->freshInstitute('education');
        $branch = $this->branch($institute, 'Downtown');
        TenantContext::set($institute->id);

        CrmLead::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'first_name' => 'Mine',
            'last_name' => 'Lead',
            'status_id' => $this->leadStatus('won'),
            'value_amount' => 250,
        ]);

        $other = $this->freshInstitute('education');
        $otherBranch = $this->branch($other, 'Other');
        CrmLead::create([
            'institute_id' => $other->id,
            'branch_id' => $otherBranch->id,
            'first_name' => 'Other',
            'last_name' => 'Tenant',
            'status_id' => $this->leadStatus('won'),
            'value_amount' => 999999,
        ]);

        $owner = $this->ownerFor($institute, 'crm-tenant');
        $ctx = $this->contextFor($owner, $institute);

        $result = app(GetCrmSummaryTool::class)->handle(['institute_id' => $other->id], $ctx);
        $this->assertSame(250.0, $result['leads']['total_value']);
        $this->assertStringNotContainsString('999999', json_encode($result));
    }

    public function test_crm_summary_tool_respects_branch_restriction(): void
    {
        $institute = $this->freshInstitute('education');
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        TenantContext::set($institute->id);

        CrmLead::create([
            'institute_id' => $institute->id,
            'branch_id' => $branchB->id,
            'first_name' => 'Other',
            'last_name' => 'Branch',
            'status_id' => $this->leadStatus('won'),
            'value_amount' => 777,
        ]);
        CrmLead::create([
            'institute_id' => $institute->id,
            'branch_id' => $branchA->id,
            'first_name' => 'Own',
            'last_name' => 'Branch',
            'status_id' => $this->leadStatus('new'),
            'value_amount' => 100,
        ]);

        $managerA = $this->userFor($institute, 'branch-manager', 'crm-mgr-a', $branchA);
        $ctxA = $this->contextFor($managerA, $institute);

        $result = app(GetCrmSummaryTool::class)->handle([
            'branch_id' => $branchB->id,
        ], $ctxA);

        $this->assertSame(100.0, $result['leads']['total_value'], 'branch A manager must not see branch B leads');
    }

    public function test_core_tools_offered_to_every_industry_but_not_education_tools(): void
    {
        $registry = app(AiToolRegistry::class);

        foreach (['education', 'real_estate', 'healthcare', 'restaurant'] as $industry) {
            $ctx = new AiContext(
                actor: null,
                institute: null,
                industry: $industry,
                aiEnabled: true,
                enabledFeatures: ['assistant'],
                permissions: ['*'],
            );

            $available = $registry->available($ctx);

            $this->assertArrayHasKey((new IncomeExpenseTool)->name(), $available);
            $this->assertArrayHasKey('get_financial_summary', $available);
            $this->assertArrayHasKey('get_crm_summary', $available);

            if ($industry !== 'education') {
                $this->assertArrayNotHasKey((new StudentsTool)->name(), $available, $industry.' must not get education tools');
            }
        }
    }
}
