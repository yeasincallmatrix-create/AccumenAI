<?php

namespace Tests\Feature;

use App\Models\CrmLead;
use App\Models\CrmLeadStatus;
use App\Models\CrmLeadSource;
use App\Models\Institute;
use App\Models\Membership;
use App\Models\Party;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SalesCrmModuleTest extends \Tests\TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
    }

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
        parent::tearDown();
    }

    protected function owner(string $email): User
    {
        return (new UserAccountService)->registerOwner([
            'name' => 'CRM Owner',
            'first_name' => 'CRM',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    protected function institute(string $name): Institute
    {
        return Institute::create([
            'name' => $name . ' ' . uniqid(),
            'slug' => \Illuminate\Support\Str::slug($name . ' ' . uniqid()),
            'status' => 'active',
        ]);
    }

    protected function setupAccounting(Institute $institute, ?int $branchId = null): void
    {
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branchId);
    }

    protected function roleId(string $slug): int
    {
        return Role::where('slug', $slug)->firstOrFail()->id;
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    protected function provisionInstitute(string $name): array
    {
        $inst = $this->institute($name);
        $this->setupAccounting($inst);
        $owner = $this->owner(strtolower(str_replace(' ', '-', $name)) . '-' . uniqid() . '@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));

        return ['institute' => $inst, 'owner' => $owner, 'membership' => $membership];
    }

    // ─── Customer Tests ───────────────────────────────────────

    public function test_customers_index_renders(): void
    {
        ['institute' => $inst, 'owner' => $owner, 'membership' => $m] = $this->provisionInstitute('CRM Cust Index');
        TenantContext::set($inst->id);

        $this->asUser($owner, (int) $m->institution_id)
            ->get(route('sales.customers.manage.index'))
            ->assertOk()
            ->assertSee('Customers');
    }

    public function test_customer_show_renders(): void
    {
        ['institute' => $inst, 'owner' => $owner, 'membership' => $m] = $this->provisionInstitute('CRM Cust Show');
        TenantContext::set($inst->id);

        $party = Party::create([
            'institute_id' => $inst->id,
            'type' => 'customer',
            'name' => 'Show Customer ' . uniqid(),
            'phone' => '017' . rand(10000000, 99999999),
            'is_active' => true,
        ]);

        $this->asUser($owner, (int) $m->institution_id)
            ->get(route('sales.customers.manage.show', $party))
            ->assertOk()
            ->assertSee($party->name);
    }

    public function test_customer_store_creates(): void
    {
        ['institute' => $inst, 'owner' => $owner, 'membership' => $m] = $this->provisionInstitute('CRM Cust Store');
        TenantContext::set($inst->id);

        $before = Party::withoutGlobalScopes()->where('institute_id', $inst->id)->count();

        $this->asUser($owner, (int) $m->institution_id)
            ->post(route('sales.customers.manage.store'), [
                'name' => 'Stored Customer ' . uniqid(),
                'phone' => '018' . rand(10000000, 99999999),
                'email' => 'stored-' . uniqid() . '@example.test',
                'credit_limit' => 5000,
            ])
            ->assertRedirect();

        $this->assertEquals($before + 1, Party::withoutGlobalScopes()->where('institute_id', $inst->id)->count());
    }

    // ─── Lead Tests ───────────────────────────────────────────

    public function test_leads_index_renders(): void
    {
        ['institute' => $inst, 'owner' => $owner, 'membership' => $m] = $this->provisionInstitute('CRM Lead Index');
        TenantContext::set($inst->id);

        $this->asUser($owner, (int) $m->institution_id)
            ->get(route('sales.leads.index'))
            ->assertOk()
            ->assertSee('Sales Leads');
    }

    public function test_lead_store_creates(): void
    {
        ['institute' => $inst, 'owner' => $owner, 'membership' => $m] = $this->provisionInstitute('CRM Lead Store');
        TenantContext::set($inst->id);

        $defaultStatus = CrmLeadStatus::where('slug', 'new')->first();

        $before = CrmLead::withoutGlobalScopes()->where('institute_id', $inst->id)->count();

        $this->asUser($owner, (int) $m->institution_id)
            ->post(route('sales.leads.store'), [
                'first_name' => 'New',
                'last_name' => 'Lead ' . uniqid(),
                'email' => 'lead-' . uniqid() . '@example.test',
                'phone' => '019' . rand(10000000, 99999999),
                'interest_summary' => 'Interested in premium package',
                'value_amount' => 15000,
                'status_id' => $defaultStatus?->id,
            ])
            ->assertRedirect();

        $this->assertEquals($before + 1, CrmLead::withoutGlobalScopes()->where('institute_id', $inst->id)->count());
    }

    // ─── Verify Existing Routes ───────────────────────────────

    public function test_quotations_index_renders(): void
    {
        ['institute' => $inst, 'owner' => $owner, 'membership' => $m] = $this->provisionInstitute('CRM Quo Verify');
        TenantContext::set($inst->id);

        $this->asUser($owner, (int) $m->institution_id)
            ->get(route('sales.quotations.index'))
            ->assertOk()
            ->assertSee('Quotations');
    }

    public function test_orders_index_renders(): void
    {
        ['institute' => $inst, 'owner' => $owner, 'membership' => $m] = $this->provisionInstitute('CRM Ord Verify');
        TenantContext::set($inst->id);

        $this->asUser($owner, (int) $m->institution_id)
            ->get(route('sales.orders.index'))
            ->assertOk()
            ->assertSee('Orders');
    }

    // ─── Tenant Isolation ─────────────────────────────────────

    public function test_tenant_isolation_sales_crm(): void
    {
        $provisionA = $this->provisionInstitute('CRM Tenant A');
        $provisionB = $this->provisionInstitute('CRM Tenant B');

        $instA = $provisionA['institute'];
        $instB = $provisionB['institute'];
        $ownerA = $provisionA['owner'];
        $ownerB = $provisionB['owner'];
        $mA = $provisionA['membership'];
        $mB = $provisionB['membership'];

        // Create customer and lead in tenant A
        $customerA = Party::create([
            'institute_id' => $instA->id,
            'type' => 'customer',
            'name' => 'Tenant A Customer',
            'is_active' => true,
        ]);

        $leadA = CrmLead::create([
            'institute_id' => $instA->id,
            'first_name' => 'Tenant A',
            'last_name' => 'Lead',
            'status_id' => CrmLeadStatus::where('slug', 'new')->value('id'),
        ]);

        // Tenant A can see its own data
        TenantContext::set($instA->id);
        $this->asUser($ownerA, (int) $mA->institution_id)
            ->get(route('sales.customers.manage.show', $customerA))
            ->assertOk();
        $this->asUser($ownerA, (int) $mA->institution_id)
            ->get(route('sales.leads.show', $leadA))
            ->assertOk();

        // Tenant B cannot see tenant A's data
        TenantContext::set($instB->id);
        $this->asUser($ownerB, (int) $mB->institution_id)
            ->get(route('sales.customers.manage.show', $customerA))
            ->assertNotFound();
        $this->asUser($ownerB, (int) $mB->institution_id)
            ->get(route('sales.leads.show', $leadA))
            ->assertNotFound();

        // Tenant B's index should not leak tenant A data
        $this->asUser($ownerB, (int) $mB->institution_id)
            ->get(route('sales.customers.manage.index'))
            ->assertOk()
            ->assertDontSee('Tenant A Customer');

        $this->asUser($ownerB, (int) $mB->institution_id)
            ->get(route('sales.leads.index'))
            ->assertOk()
            ->assertDontSee('Tenant A Lead');
    }
}
