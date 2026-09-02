<?php

namespace Tests\Feature;

use App\Models\Institute;
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

class ArApManagementUiTest extends \Tests\TestCase
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
            'name' => 'ARAP Owner',
            'first_name' => 'ARAP',
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

    protected function customer(Institute $institute, string $name): Party
    {
        return Party::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'type' => 'customer',
        ]);
    }

    protected function supplier(Institute $institute, string $name): Party
    {
        return Party::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'type' => 'supplier',
        ]);
    }

    // ─── Receivables ─────────────────────────────────────────────

    public function test_receivables_index_renders(): void
    {
        $mawa = $this->institute('ARAP AR Index');
        $this->setupAccounting($mawa);
        $owner = $this->owner('arap-ar-index@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.receivables.index'))
            ->assertOk()
            ->assertSee('Receivable');
    }

    public function test_receivables_statement_renders(): void
    {
        $mawa = $this->institute('ARAP AR Stmt');
        $this->setupAccounting($mawa);
        $owner = $this->owner('arap-ar-stmt@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $cust = $this->customer($mawa, 'Test Customer');

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.receivables.statement', ['partyId' => $cust->id]))
            ->assertOk()
            ->assertSee('Test Customer');
    }

    public function test_receivables_aging_renders(): void
    {
        $mawa = $this->institute('ARAP AR Aging');
        $this->setupAccounting($mawa);
        $owner = $this->owner('arap-ar-aging@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.receivables.aging'))
            ->assertOk()
            ->assertSee('Aging');
    }

    // ─── Payables ────────────────────────────────────────────────

    public function test_payables_index_renders(): void
    {
        $mawa = $this->institute('ARAP AP Index');
        $this->setupAccounting($mawa);
        $owner = $this->owner('arap-ap-index@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.payables.index'))
            ->assertOk()
            ->assertSee('Payable');
    }

    public function test_payables_statement_renders(): void
    {
        $mawa = $this->institute('ARAP AP Stmt');
        $this->setupAccounting($mawa);
        $owner = $this->owner('arap-ap-stmt@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $supp = $this->supplier($mawa, 'Test Supplier');

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.payables.statement', ['partyId' => $supp->id]))
            ->assertOk()
            ->assertSee('Test Supplier');
    }

    public function test_payables_aging_renders(): void
    {
        $mawa = $this->institute('ARAP AP Aging');
        $this->setupAccounting($mawa);
        $owner = $this->owner('arap-ap-aging@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.payables.aging'))
            ->assertOk()
            ->assertSee('Aging');
    }

    // ─── Tenant isolation ────────────────────────────────────────

    public function test_tenant_isolation_receivables(): void
    {
        $mawa = $this->institute('ARAP Tenant A');
        $other = $this->institute('ARAP Tenant B');
        $this->setupAccounting($mawa);
        $this->setupAccounting($other);

        $ownerA = $this->owner('arap-tenanta@example.test');
        $ownerB = $this->owner('arap-tenantb@example.test');
        (new MembershipService)->assign($ownerA, $mawa->id, $this->roleId('institute-owner'));
        (new MembershipService)->assign($ownerB, $other->id, $this->roleId('institute-owner'));

        $custA = $this->customer($mawa, 'Cust A');
        $custB = $this->customer($other, 'Cust B');

        $this->asUser($ownerA, $mawa->id)
            ->get(route('accounting.receivables.statement', ['partyId' => $custA->id]))
            ->assertOk()
            ->assertSee('Cust A');

        $this->asUser($ownerA, $mawa->id)
            ->get(route('accounting.receivables.index'))
            ->assertOk()
            ->assertDontSee('Cust B');
    }
}
