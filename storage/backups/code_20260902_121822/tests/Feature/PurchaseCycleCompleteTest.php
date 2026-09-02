<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PurchaseCycleCompleteTest extends \Tests\TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
        BranchContext::clear();
        Workspace::clear();
    }

    protected function owner(string $email): User
    {
        return (new UserAccountService)->registerOwner([
            'name' => 'Purchase Owner',
            'first_name' => 'Purchase',
            'last_name' => 'Owner',
            'email' => $email,
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    protected function institute(string $name): Institute
    {
        return Institute::create([
            'name' => $name.' '.uniqid(),
            'slug' => \Illuminate\Support\Str::slug($name.' '.uniqid()),
            'status' => 'active',
        ]);
    }

    protected function roleId(string $slug): int
    {
        return Role::where('slug', $slug)->firstOrFail()->id;
    }

    protected function setupAccounting(Institute $institute, ?int $branchId = null): void
    {
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branchId);
    }

    protected function asUser(User $user, int $workspaceId): static
    {
        return $this->withSession([Workspace::SESSION_KEY => $workspaceId])->actingAs($user, 'web');
    }

    public function test_purchase_orders_index_renders(): void
    {
        $inst = $this->institute('PO Cycle');
        $this->setupAccounting($inst);
        $owner = $this->owner('po-cycle@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('purchase.orders.index'))
            ->assertOk()
            ->assertSee('Purchase Order');
    }

    public function test_purchase_quotations_index_renders(): void
    {
        $inst = $this->institute('PQ Cycle');
        $this->setupAccounting($inst);
        $owner = $this->owner('pq-cycle@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('purchase.quotations.index'))
            ->assertOk()
            ->assertSee('Quotation');
    }

    public function test_goods_receipts_index_renders(): void
    {
        $inst = $this->institute('GR Cycle');
        $this->setupAccounting($inst);
        $owner = $this->owner('gr-cycle@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('purchase.receipts.index'))
            ->assertOk()
            ->assertSee('Receipt');
    }

    public function test_purchase_invoices_index_renders(): void
    {
        $inst = $this->institute('PI Cycle');
        $this->setupAccounting($inst);
        $owner = $this->owner('pi-cycle@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('purchase.invoices.index'))
            ->assertOk()
            ->assertSee('Invoice');
    }

    public function test_purchase_returns_index_renders(): void
    {
        $inst = $this->institute('PR Cycle');
        $this->setupAccounting($inst);
        $owner = $this->owner('pr-cycle@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('purchase.returns.index'))
            ->assertOk()
            ->assertSee('Return');
    }

    public function test_suppliers_index_renders(): void
    {
        $inst = $this->institute('SU Cycle');
        $this->setupAccounting($inst);
        $owner = $this->owner('su-cycle@example.test');
        $membership = (new MembershipService)->assign($owner, $inst->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('purchase.suppliers.index'))
            ->assertOk()
            ->assertJsonStructure(['suppliers', 'total', 'per_page', 'current_page', 'last_page']);
    }
}
