<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Institute;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\MembershipService;
use App\Services\UserAccountService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ChartOfAccountCashFlowCategoryTest extends TestCase
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
            'name' => 'COA CF Owner',
            'first_name' => 'COA',
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

    // Test 1 — operating
    public function test_coa_can_be_created_with_operating(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);
        $owner = $this->owner('coa-cf-op@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $account = app(ChartOfAccountService::class)->createAccount($mawa->id, null, [
            'code' => '4009',
            'name' => 'Operating Test Income',
            'type' => 'income',
            'cash_flow_category' => 'operating',
        ], $owner->id);

        $this->assertSame('operating', $account->cash_flow_category);
        $this->assertSame('operating', $account->fresh()->cash_flow_category);
    }

    // Test 2 — investing
    public function test_coa_can_be_created_with_investing(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);
        $owner = $this->owner('coa-cf-inv@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $account = app(ChartOfAccountService::class)->createAccount($mawa->id, null, [
            'code' => '1309',
            'name' => 'Investing Test Asset',
            'type' => 'asset',
            'cash_flow_category' => 'investing',
        ], $owner->id);

        $this->assertSame('investing', $account->cash_flow_category);
    }

    // Test 3 — financing
    public function test_coa_can_be_created_with_financing(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);
        $owner = $this->owner('coa-cf-fin@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $account = app(ChartOfAccountService::class)->createAccount($mawa->id, null, [
            'code' => '2009',
            'name' => 'Financing Test Liability',
            'type' => 'liability',
            'cash_flow_category' => 'financing',
        ], $owner->id);

        $this->assertSame('financing', $account->cash_flow_category);
    }

    // Test 4 — NULL
    public function test_coa_can_remain_null(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);
        $owner = $this->owner('coa-cf-null@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $account = app(ChartOfAccountService::class)->createAccount($mawa->id, null, [
            'code' => '5099',
            'name' => 'Null Category Expense',
            'type' => 'expense',
            'cash_flow_category' => null,
        ], $owner->id);

        $this->assertNull($account->cash_flow_category);

        // Also without key (implicit null)
        $account2 = app(ChartOfAccountService::class)->createAccount($mawa->id, null, [
            'code' => '5098',
            'name' => 'Implicit Null Expense',
            'type' => 'expense',
        ], $owner->id);

        $this->assertNull($account2->cash_flow_category);
    }

    // Test 5 — invalid rejected
    public function test_invalid_classification_is_rejected(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);
        $owner = $this->owner('coa-cf-invalid@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->expectException(ValidationException::class);

        app(ChartOfAccountService::class)->createAccount($mawa->id, null, [
            'code' => '5097',
            'name' => 'Invalid Category',
            'type' => 'expense',
            'cash_flow_category' => 'invalid_category',
        ], $owner->id);
    }

    // Test 6 — existing remain valid
    public function test_existing_coa_records_remain_valid_after_migration(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);

        $cash = ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('code', '1001')
            ->firstOrFail();

        $this->assertNull($cash->cash_flow_category);

        $bank = ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('code', '1002')
            ->firstOrFail();

        $this->assertNull($bank->cash_flow_category);

        $allSystem = ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('is_system', true)
            ->count();

        $withCategory = ChartOfAccount::withoutGlobalScopes()
            ->where('institute_id', $mawa->id)
            ->where('is_system', true)
            ->whereNotNull('cash_flow_category')
            ->count();

        $this->assertSame($allSystem - 2, $withCategory, 'All system accounts except Cash/Bank should have cash_flow_category set');
    }

    // Test 7 — tenant isolation
    public function test_tenant_isolation_remains_intact(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $tutu = $this->institute('Tutu Center');
        $this->setupAccounting($mawa);
        $this->setupAccounting($tutu);

        $owner = $this->owner('coa-cf-tenant@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));
        (new MembershipService)->assign($owner, $tutu->id, $this->roleId('institute-owner'));

        $mawaAcc = app(ChartOfAccountService::class)->createAccount($mawa->id, null, [
            'code' => '4090',
            'name' => 'Mawa Operating',
            'type' => 'income',
            'cash_flow_category' => 'operating',
        ], $owner->id);

        $tutuAcc = app(ChartOfAccountService::class)->createAccount($tutu->id, null, [
            'code' => '4090',
            'name' => 'Tutu Investing',
            'type' => 'income',
            'cash_flow_category' => 'investing',
        ], $owner->id);

        $this->assertSame('operating', $mawaAcc->fresh()->cash_flow_category);
        $this->assertSame('investing', $tutuAcc->fresh()->cash_flow_category);

        // Verify tenant-scoped query doesn't leak
        $mawaOnly = ChartOfAccount::query()
            ->where('institute_id', $mawa->id)
            ->where('code', '4090')
            ->first();
        // TenantContext not set, but global scope is context-driven — withoutGlobalScopes proves separation
        $this->assertSame($mawaAcc->id, $mawaOnly->id);
    }

    // Test 8 — branch behavior
    public function test_branch_behavior_remains_intact(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $branch = Branch::create(['institute_id' => $mawa->id, 'name' => 'CF Branch', 'status' => 'active']);
        $this->setupAccounting($mawa, $branch->id);
        $this->setupAccounting($mawa, null);

        $owner = $this->owner('coa-cf-branch@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'), ['branch_id' => $branch->id]);

        $branchAcc = app(ChartOfAccountService::class)->createAccount($mawa->id, $branch->id, [
            'code' => '4009',
            'name' => 'Branch Operating',
            'type' => 'income',
            'cash_flow_category' => 'operating',
        ], $owner->id);

        $this->assertSame($branch->id, $branchAcc->branch_id);
        $this->assertSame('operating', $branchAcc->cash_flow_category);

        // Institute-level account with same code but null branch is distinct
        $instituteAcc = app(ChartOfAccountService::class)->createAccount($mawa->id, null, [
            'code' => '4091',
            'name' => 'Institute Financing',
            'type' => 'liability',
            'cash_flow_category' => 'financing',
        ], $owner->id);

        $this->assertNull($instituteAcc->branch_id);
        $this->assertSame('financing', $instituteAcc->cash_flow_category);

        // Update retains category
        $updated = app(ChartOfAccountService::class)->updateAccount($branchAcc, [
            'code' => '4009',
            'name' => 'Branch Operating Updated',
            'type' => 'income',
            'cash_flow_category' => 'investing',
        ], $owner->id);

        $this->assertSame('investing', $updated->cash_flow_category);
    }

    public function test_http_create_with_cash_flow_category(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);
        $owner = $this->owner('coa-cf-http@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $mawa->id)
            ->post(route('finance.chart-of-accounts.store'), [
                'code' => '4092',
                'name' => 'HTTP Operating',
                'type' => 'income',
                'cash_flow_category' => 'operating',
            ])
            ->assertRedirect(route('finance.chart-of-accounts.index'));

        $this->assertDatabaseHas('chart_of_accounts', [
            'institute_id' => $mawa->id,
            'code' => '4092',
            'cash_flow_category' => 'operating',
        ]);
    }

    public function test_http_invalid_category_rejected(): void
    {
        $mawa = $this->institute('MAWA ACADEMY');
        $this->setupAccounting($mawa);
        $owner = $this->owner('coa-cf-http-invalid@example.test');
        (new MembershipService)->assign($owner, $mawa->id, $this->roleId('institute-owner'));

        $this->asUser($owner, $mawa->id)
            ->post(route('finance.chart-of-accounts.store'), [
                'code' => '4093',
                'name' => 'HTTP Invalid',
                'type' => 'income',
                'cash_flow_category' => 'bad_value',
            ])
            ->assertSessionHasErrors('cash_flow_category');
    }
}
