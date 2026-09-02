<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class SalesCycleCompleteTest extends \Tests\TestCase
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

    protected function provisionInstitute(string $name): array
    {
        $inst = Institute::create([
            'name' => $name . ' ' . uniqid(),
            'slug' => \Illuminate\Support\Str::slug($name . ' ' . uniqid()),
            'status' => 'active',
        ]);

        app(AccountingSetupService::class)->setupForInstitute($inst->id);

        $owner = (new UserAccountService)->registerOwner([
            'name' => 'Sales Owner',
            'first_name' => 'Sales',
            'last_name' => 'Owner',
            'email' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid() . '@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $membership = (new MembershipService)->assign($owner, $inst->id, \App\Models\Role::where('slug', 'institute-owner')->firstOrFail()->id);

        return ['institute' => $inst, 'owner' => $owner, 'membership' => $membership];
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    public function test_sales_quotations_index_renders(): void
    {
        ['institute' => $inst, 'owner' => $owner, 'membership' => $m] = $this->provisionInstitute('SalesCycle Quo');
        TenantContext::set($inst->id);

        $this->asUser($owner, (int) $m->institution_id)
            ->get(route('sales.quotations.index'))
            ->assertOk();
    }

    public function test_sales_orders_index_renders(): void
    {
        ['institute' => $inst, 'owner' => $owner, 'membership' => $m] = $this->provisionInstitute('SalesCycle Ord');
        TenantContext::set($inst->id);

        $this->asUser($owner, (int) $m->institution_id)
            ->get(route('sales.orders.index'))
            ->assertOk();
    }

    public function test_sales_deliveries_index_renders(): void
    {
        ['institute' => $inst, 'owner' => $owner, 'membership' => $m] = $this->provisionInstitute('SalesCycle Del');
        TenantContext::set($inst->id);

        $this->asUser($owner, (int) $m->institution_id)
            ->get(route('sales.deliveries.index'))
            ->assertOk();
    }

    public function test_sales_returns_index_renders(): void
    {
        ['institute' => $inst, 'owner' => $owner, 'membership' => $m] = $this->provisionInstitute('SalesCycle Ret');
        TenantContext::set($inst->id);

        $this->asUser($owner, (int) $m->institution_id)
            ->get(route('sales.returns.index'))
            ->assertOk();
    }

    public function test_customers_index_renders(): void
    {
        ['institute' => $inst, 'owner' => $owner, 'membership' => $m] = $this->provisionInstitute('SalesCycle Cust');
        TenantContext::set($inst->id);

        $this->asUser($owner, (int) $m->institution_id)
            ->get(route('sales.customers.manage.index'))
            ->assertOk();
    }

    public function test_leads_index_renders(): void
    {
        ['institute' => $inst, 'owner' => $owner, 'membership' => $m] = $this->provisionInstitute('SalesCycle Lead');
        TenantContext::set($inst->id);

        $this->asUser($owner, (int) $m->institution_id)
            ->get(route('sales.leads.index'))
            ->assertOk();
    }
}
