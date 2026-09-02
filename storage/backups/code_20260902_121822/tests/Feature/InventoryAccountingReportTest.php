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

class InventoryAccountingReportTest extends \Tests\TestCase
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
            'name' => 'Inv Owner',
            'first_name' => 'Inv',
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

    public function test_stock_valuation_renders(): void
    {
        $mawa = $this->institute('Inv Stock Val');
        $this->setupAccounting($mawa);
        $owner = $this->owner('inv-stockval@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.inventory-accounting.stock-valuation'))
            ->assertOk()
            ->assertSee('Stock Valuation Report');
    }

    public function test_movements_renders(): void
    {
        $mawa = $this->institute('Inv Movements');
        $this->setupAccounting($mawa);
        $owner = $this->owner('inv-movements@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.inventory-accounting.movements', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Inventory Movement Ledger');
    }

    public function test_cogs_renders(): void
    {
        $mawa = $this->institute('Inv COGS');
        $this->setupAccounting($mawa);
        $owner = $this->owner('inv-cogs@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.inventory-accounting.cogs', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Cost of Goods Sold Report');
    }

    public function test_slow_moving_renders(): void
    {
        $mawa = $this->institute('Inv Slow Mv');
        $this->setupAccounting($mawa);
        $owner = $this->owner('inv-slowmv@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.inventory-accounting.slow-moving'))
            ->assertOk()
            ->assertSee('Slow-Moving Inventory');
    }

    public function test_tenant_isolation_inventory_reports(): void
    {
        $mawa = $this->institute('Inv Tenant A');
        $other = $this->institute('Inv Tenant B');
        $this->setupAccounting($mawa);
        $this->setupAccounting($other);

        $ownerA = $this->owner('inv-tenanta@example.test');
        (new MembershipService)->assign($ownerA, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($ownerA, $mawa->id)
            ->get(route('accounting.reports.inventory-accounting.stock-valuation'))
            ->assertOk();
    }
}
