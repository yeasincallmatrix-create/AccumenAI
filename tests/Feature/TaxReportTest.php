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

class TaxReportTest extends \Tests\TestCase
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
            'name' => 'Tax Owner',
            'first_name' => 'Tax',
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

    // ─── VAT Summary ──────────────────────────────────────

    public function test_vat_summary_renders(): void
    {
        $mawa = $this->institute('Tax VAT Sum');
        $this->setupAccounting($mawa);
        $owner = $this->owner('tax-vatsum@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.tax.vat-summary', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('VAT Summary');
    }

    // ─── Input VAT ────────────────────────────────────────

    public function test_input_vat_renders(): void
    {
        $mawa = $this->institute('Tax Input VAT');
        $this->setupAccounting($mawa);
        $owner = $this->owner('tax-inputvat@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.tax.input-vat', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Input VAT Detail');
    }

    // ─── Output VAT ───────────────────────────────────────

    public function test_output_vat_renders(): void
    {
        $mawa = $this->institute('Tax Output VAT');
        $this->setupAccounting($mawa);
        $owner = $this->owner('tax-outputvat@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.tax.output-vat', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Output VAT Detail');
    }

    // ─── Tax Liability ────────────────────────────────────

    public function test_tax_liability_renders(): void
    {
        $mawa = $this->institute('Tax Liability');
        $this->setupAccounting($mawa);
        $owner = $this->owner('tax-liability@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.tax.liability', [
                'as_of_date' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Tax Liability');
    }

    // ─── Tax Transactions ─────────────────────────────────

    public function test_tax_transactions_renders(): void
    {
        $mawa = $this->institute('Tax Transactions');
        $this->setupAccounting($mawa);
        $owner = $this->owner('tax-transactions@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.reports.tax.transactions', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk()
            ->assertSee('Tax Transactions');
    }

    // ─── Tenant Isolation ─────────────────────────────────

    public function test_tenant_isolation_tax_reports(): void
    {
        $mawa = $this->institute('Tax Tenant A');
        $other = $this->institute('Tax Tenant B');
        $this->setupAccounting($mawa);
        $this->setupAccounting($other);

        $ownerA = $this->owner('tax-tenanta@example.test');
        (new MembershipService)->assign($ownerA, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($ownerA, $mawa->id)
            ->get(route('accounting.reports.tax.vat-summary', [
                'from' => now()->startOfMonth()->toDateString(),
                'to' => now()->toDateString(),
            ]))
            ->assertOk();
    }
}
