<?php

namespace Tests\Feature;

use App\Models\BankReconciliation;
use App\Models\BankStatement;
use App\Models\BankStatementLine;
use App\Models\ChartOfAccount;
use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\BankReconciliationService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class BankReconciliationUiTest extends \Tests\TestCase
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
            'name' => 'Bank UI Owner',
            'first_name' => 'Bank',
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

    protected function bankAccount(Institute $institute): ChartOfAccount
    {
        return ChartOfAccount::where('institute_id', $institute->id)
            ->where('is_bank', true)
            ->firstOrFail();
    }

    public function test_index_lists_bank_accounts(): void
    {
        $mawa = $this->institute('Bank UI Index');
        $this->setupAccounting($mawa);
        $owner = $this->owner('bank-ui-index@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.bank-reconciliation.index'))
            ->assertOk()
            ->assertSee('Bank Reconciliation');
    }

    public function test_statements_page_loads(): void
    {
        $mawa = $this->institute('Bank UI Stmt');
        $this->setupAccounting($mawa);
        $owner = $this->owner('bank-ui-stmt@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $bankAcct = $this->bankAccount($mawa);

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.bank-reconciliation.statements', ['accountId' => $bankAcct->id]))
            ->assertOk()
            ->assertSee('Bank Statements');
    }

    public function test_create_statement(): void
    {
        $mawa = $this->institute('Bank UI Create');
        $this->setupAccounting($mawa);
        $owner = $this->owner('bank-ui-create@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $bankAcct = $this->bankAccount($mawa);

        $this->asUser($owner, $membership->institution_id)
            ->post(route('accounting.bank-reconciliation.statements.store', ['accountId' => $bankAcct->id]), [
                'statement_date' => '2026-08-01',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('bank_statements', [
            'institute_id' => $mawa->id,
            'bank_account_id' => $bankAcct->id,
        ]);
    }

    public function test_show_statement_with_lines(): void
    {
        $mawa = $this->institute('Bank UI Show');
        $this->setupAccounting($mawa);
        $owner = $this->owner('bank-ui-show@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $bankAcct = $this->bankAccount($mawa);

        $stmt = BankStatement::create([
            'institute_id' => $mawa->id,
            'bank_account_id' => $bankAcct->id,
            'branch_id' => null,
            'statement_date' => '2026-08-01',
            'status' => 'imported',
        ]);

        BankStatementLine::create([
            'institute_id' => $mawa->id,
            'statement_id' => $stmt->id,
            'transaction_date' => '2026-08-05',
            'description' => 'Deposit from customer',
            'amount' => 5000,
            'type' => 'deposit',
        ]);

        $this->asUser($owner, $membership->institution_id)
            ->get(route('accounting.bank-reconciliation.show', ['statementId' => $stmt->id]))
            ->assertOk()
            ->assertSee('Deposit from customer');
    }

    public function test_auto_match_endpoint(): void
    {
        $mawa = $this->institute('Bank UI Match');
        $this->setupAccounting($mawa);
        $owner = $this->owner('bank-ui-match@example.test');
        $membership = (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $bankAcct = $this->bankAccount($mawa);

        $stmt = BankStatement::create([
            'institute_id' => $mawa->id,
            'bank_account_id' => $bankAcct->id,
            'branch_id' => null,
            'statement_date' => '2026-08-01',
            'status' => 'imported',
        ]);

        $this->asUser($owner, $membership->institution_id)
            ->post(route('accounting.bank-reconciliation.auto-match', ['statementId' => $stmt->id]))
            ->assertRedirect();
    }

    public function test_tenant_isolation(): void
    {
        $mawa = $this->institute('Bank UI Tenant A');
        $other = $this->institute('Bank UI Tenant B');
        $this->setupAccounting($mawa);
        $this->setupAccounting($other);

        $ownerA = $this->owner('bank-ui-tenanta@example.test');
        $ownerB = $this->owner('bank-ui-tenantb@example.test');
        (new MembershipService)->assign($ownerA, $mawa->id, $this->roleId('institute-owner'));
        $memB = (new MembershipService)->assign($ownerB, $other->id, $this->roleId('institute-owner'));

        $bankA = $this->bankAccount($mawa);
        $bankB = $this->bankAccount($other);

        $stmtA = BankStatement::create([
            'institute_id' => $mawa->id,
            'bank_account_id' => $bankA->id,
            'branch_id' => null,
            'statement_date' => '2026-08-01',
            'status' => 'imported',
        ]);

        $stmtB = BankStatement::create([
            'institute_id' => $other->id,
            'bank_account_id' => $bankB->id,
            'branch_id' => null,
            'statement_date' => '2026-08-01',
            'status' => 'imported',
        ]);

        $this->asUser($ownerA, $mawa->id)
            ->get(route('accounting.bank-reconciliation.show', ['statementId' => $stmtA->id]))
            ->assertOk();

        $this->asUser($ownerA, $mawa->id)
            ->get(route('accounting.bank-reconciliation.show', ['statementId' => $stmtB->id]))
            ->assertNotFound();
    }
}
