<?php

namespace Tests\Feature;

use App\Models\AccountGroup;
use App\Models\AccountingPeriod;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\BudgetVersion;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Branch;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BudgetingTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    private function institute(): Institute
    {
        return Institute::create([
            'name' => 'Budget Institute '.uniqid(),
            'slug' => 'budget-inst-'.uniqid(),
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
    }

    private function owner(Institute $inst): InstituteUser
    {
        $role = Role::where('slug', 'institute-owner')->firstOrFail();

        return InstituteUser::create([
            'institute_id' => $inst->id,
            'role_id' => $role->id,
            'first_name' => 'Budget',
            'last_name' => 'Owner',
            'email' => 'budget-owner-'.uniqid().'@test.com',
            'phone' => '01700'.mt_rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);
    }

    private function currency(): Currency
    {
        return Currency::firstOrCreate(['code' => 'BDT'], [
            'name' => 'Bangladeshi Taka',
            'symbol' => '৳',
            'decimal_places' => 2,
            'is_base' => true,
            'is_active' => true,
        ]);
    }

    private function branch(Institute $inst): Branch
    {
        return Branch::create([
            'institute_id' => $inst->id,
            'name' => 'Main Branch',
            'status' => 'active',
        ]);
    }

    private function fiscalYear(Institute $inst): FiscalYear
    {
        return FiscalYear::create([
            'institute_id' => $inst->id,
            'name' => 'FY 2027',
            'start_date' => '2027-01-01',
            'end_date' => '2027-12-31',
            'status' => 'open',
            'is_current' => true,
        ]);
    }

    private function accounts(Institute $inst): array
    {
        $group = AccountGroup::create([
            'institute_id' => $inst->id,
            'code' => '5',
            'name' => 'Expenses',
            'category' => 'expense',
            'sort_order' => 5,
        ]);

        $acc1 = ChartOfAccount::create([
            'institute_id' => $inst->id,
            'account_group_id' => $group->id,
            'code' => '5001',
            'name' => 'Salary Expense',
            'type' => 'expense',
        ]);

        $acc2 = ChartOfAccount::create([
            'institute_id' => $inst->id,
            'account_group_id' => $group->id,
            'code' => '5002',
            'name' => 'Rent Expense',
            'type' => 'expense',
        ]);

        return [$acc1, $acc2];
    }

    private function bind(Institute $inst): void
    {
        TenantContext::set($inst->id);
    }

    // -----------------------------------------------------------------
    // BUDGET CRUD
    // -----------------------------------------------------------------

    public function test_budget_index_page_loads(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $this->bind($inst);

        $this->actingAs($user, 'institute_user')
            ->get(route('finance.budgets.index'))
            ->assertOk();
    }

    public function test_budget_create_page_loads(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $fy = $this->fiscalYear($inst);
        $this->bind($inst);

        $this->actingAs($user, 'institute_user')
            ->get(route('finance.budgets.create'))
            ->assertOk();
    }

    public function test_budget_can_be_created(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $fy = $this->fiscalYear($inst);
        $currency = $this->currency();
        [$acc1, $acc2] = $this->accounts($inst);
        $this->bind($inst);

        $response = $this->actingAs($user, 'institute_user')
            ->post(route('finance.budgets.store'), [
                'name' => 'Annual Expense Budget',
                'type' => 'expense',
                'fiscal_year_id' => $fy->id,
                'currency_id' => $currency->id,
                'lines' => [
                    ['coa_id' => $acc1->id, 'month' => 0, 'amount' => 500000],
                    ['coa_id' => $acc2->id, 'month' => 0, 'amount' => 120000],
                ],
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('budgets', [
            'institute_id' => $inst->id,
            'name' => 'Annual Expense Budget',
            'status' => 'draft',
            'version' => 1,
        ]);

        $budget = Budget::where('institute_id', $inst->id)->first();
        $this->assertEquals(620000, (float) $budget->total_amount);

        $version = BudgetVersion::where('budget_id', $budget->id)->first();
        $this->assertNotNull($version);
        $this->assertEquals(1, $version->version);

        $lines = BudgetLine::where('budget_version_id', $version->id)->get();
        $this->assertCount(2, $lines);
    }

    public function test_budget_show_page_loads(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $fy = $this->fiscalYear($inst);
        $currency = $this->currency();
        $this->bind($inst);

        $budget = Budget::create([
            'institute_id' => $inst->id,
            'fiscal_year_id' => $fy->id,
            'currency_id' => $currency->id,
            'name' => 'Test Budget',
            'type' => 'expense',
            'status' => 'draft',
            'version' => 1,
            'total_amount' => 100000,
        ]);

        BudgetVersion::create([
            'budget_id' => $budget->id,
            'institute_id' => $inst->id,
            'version' => 1,
            'status' => 'draft',
            'total_amount' => 100000,
        ]);

        $this->actingAs($user, 'institute_user')
            ->get(route('finance.budgets.show', $budget->id))
            ->assertOk();
    }

    public function test_budget_can_be_updated_when_draft(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $fy = $this->fiscalYear($inst);
        $currency = $this->currency();
        [$acc1] = $this->accounts($inst);
        $this->bind($inst);

        $budget = Budget::create([
            'institute_id' => $inst->id,
            'fiscal_year_id' => $fy->id,
            'currency_id' => $currency->id,
            'name' => 'Original',
            'type' => 'expense',
            'status' => 'draft',
            'version' => 1,
            'total_amount' => 0,
        ]);

        BudgetVersion::create([
            'budget_id' => $budget->id,
            'institute_id' => $inst->id,
            'version' => 1,
            'status' => 'draft',
            'total_amount' => 0,
        ]);

        $this->actingAs($user, 'institute_user')
            ->put(route('finance.budgets.update', $budget->id), [
                'name' => 'Updated Budget',
                'lines' => [
                    ['coa_id' => $acc1->id, 'month' => 0, 'amount' => 75000],
                ],
            ])
            ->assertRedirect();

        $budget->refresh();
        $this->assertEquals('Updated Budget', $budget->name);
        $this->assertEquals(75000, (float) $budget->total_amount);
    }

    // -----------------------------------------------------------------
    // WORKFLOW: SUBMIT → APPROVE → LOCK
    // -----------------------------------------------------------------

    public function test_budget_can_be_submitted(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $fy = $this->fiscalYear($inst);
        $currency = $this->currency();
        $this->bind($inst);

        $budget = Budget::create([
            'institute_id' => $inst->id,
            'fiscal_year_id' => $fy->id,
            'currency_id' => $currency->id,
            'name' => 'Submit Test',
            'type' => 'expense',
            'status' => 'draft',
            'version' => 1,
            'total_amount' => 100000,
        ]);

        BudgetVersion::create([
            'budget_id' => $budget->id,
            'institute_id' => $inst->id,
            'version' => 1,
            'status' => 'draft',
            'total_amount' => 100000,
        ]);

        $this->actingAs($user, 'institute_user')
            ->post(route('finance.budgets.submit', $budget->id))
            ->assertRedirect();

        $budget->refresh();
        $this->assertEquals('submitted', $budget->status);
        $this->assertNotNull($budget->submitted_at);
    }

    public function test_budget_can_be_approved(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $fy = $this->fiscalYear($inst);
        $currency = $this->currency();
        $this->bind($inst);

        $budget = Budget::create([
            'institute_id' => $inst->id,
            'fiscal_year_id' => $fy->id,
            'currency_id' => $currency->id,
            'name' => 'Approve Test',
            'type' => 'expense',
            'status' => 'submitted',
            'version' => 1,
            'total_amount' => 100000,
        ]);

        BudgetVersion::create([
            'budget_id' => $budget->id,
            'institute_id' => $inst->id,
            'version' => 1,
            'status' => 'submitted',
            'total_amount' => 100000,
        ]);

        $this->actingAs($user, 'institute_user')
            ->post(route('finance.budgets.approve', $budget->id))
            ->assertRedirect();

        $budget->refresh();
        $this->assertEquals('approved', $budget->status);
        $this->assertNotNull($budget->approved_at);
    }

    public function test_budget_can_be_locked(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $fy = $this->fiscalYear($inst);
        $currency = $this->currency();
        $this->bind($inst);

        $budget = Budget::create([
            'institute_id' => $inst->id,
            'fiscal_year_id' => $fy->id,
            'currency_id' => $currency->id,
            'name' => 'Lock Test',
            'type' => 'expense',
            'status' => 'approved',
            'version' => 1,
            'total_amount' => 100000,
        ]);

        BudgetVersion::create([
            'budget_id' => $budget->id,
            'institute_id' => $inst->id,
            'version' => 1,
            'status' => 'approved',
            'total_amount' => 100000,
        ]);

        $this->actingAs($user, 'institute_user')
            ->post(route('finance.budgets.lock', $budget->id))
            ->assertRedirect();

        $budget->refresh();
        $this->assertEquals('locked', $budget->status);
        $this->assertNotNull($budget->locked_at);
    }

    public function test_budget_can_be_rejected(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $fy = $this->fiscalYear($inst);
        $currency = $this->currency();
        $this->bind($inst);

        $budget = Budget::create([
            'institute_id' => $inst->id,
            'fiscal_year_id' => $fy->id,
            'currency_id' => $currency->id,
            'name' => 'Reject Test',
            'type' => 'expense',
            'status' => 'submitted',
            'version' => 1,
            'total_amount' => 100000,
        ]);

        BudgetVersion::create([
            'budget_id' => $budget->id,
            'institute_id' => $inst->id,
            'version' => 1,
            'status' => 'submitted',
            'total_amount' => 100000,
        ]);

        $this->actingAs($user, 'institute_user')
            ->post(route('finance.budgets.reject', $budget->id), [
                'reason' => 'Over budget',
            ])
            ->assertRedirect();

        $budget->refresh();
        $this->assertEquals('rejected', $budget->status);
        $this->assertEquals('Over budget', $budget->notes);
    }

    // -----------------------------------------------------------------
    // REVISION
    // -----------------------------------------------------------------

    public function test_approved_budget_can_be_revised(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $fy = $this->fiscalYear($inst);
        $currency = $this->currency();
        [$acc1] = $this->accounts($inst);
        $this->bind($inst);

        $budget = Budget::create([
            'institute_id' => $inst->id,
            'fiscal_year_id' => $fy->id,
            'currency_id' => $currency->id,
            'name' => 'Revise Test',
            'type' => 'expense',
            'status' => 'approved',
            'version' => 1,
            'total_amount' => 100000,
        ]);

        BudgetVersion::create([
            'budget_id' => $budget->id,
            'institute_id' => $inst->id,
            'version' => 1,
            'status' => 'approved',
            'total_amount' => 100000,
        ]);

        $this->actingAs($user, 'institute_user')
            ->post(route('finance.budgets.revise', $budget->id), [
                'reason' => 'Need more for salaries',
                'lines' => [
                    ['coa_id' => $acc1->id, 'month' => 0, 'amount' => 150000],
                ],
            ])
            ->assertRedirect();

        $budget->refresh();
        $this->assertEquals('draft', $budget->status);
        $this->assertEquals(2, $budget->version);
        $this->assertEquals(150000, (float) $budget->total_amount);

        $versions = BudgetVersion::where('budget_id', $budget->id)->get();
        $this->assertCount(2, $versions);
    }

    public function test_locked_budget_can_be_revised(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $fy = $this->fiscalYear($inst);
        $currency = $this->currency();
        $this->bind($inst);

        $budget = Budget::create([
            'institute_id' => $inst->id,
            'fiscal_year_id' => $fy->id,
            'currency_id' => $currency->id,
            'name' => 'Locked Revise',
            'type' => 'expense',
            'status' => 'locked',
            'version' => 1,
            'total_amount' => 100000,
        ]);

        BudgetVersion::create([
            'budget_id' => $budget->id,
            'institute_id' => $inst->id,
            'version' => 1,
            'status' => 'locked',
            'total_amount' => 100000,
        ]);

        $this->actingAs($user, 'institute_user')
            ->post(route('finance.budgets.revise', $budget->id), [
                'reason' => 'Budget revision needed',
            ])
            ->assertRedirect();

        $budget->refresh();
        $this->assertEquals('draft', $budget->status);
        $this->assertEquals(2, $budget->version);
    }

    // -----------------------------------------------------------------
    // WORKFLOW RULES
    // -----------------------------------------------------------------

    public function test_cannot_edit_locked_budget(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $fy = $this->fiscalYear($inst);
        $currency = $this->currency();
        $this->bind($inst);

        $budget = Budget::create([
            'institute_id' => $inst->id,
            'fiscal_year_id' => $fy->id,
            'currency_id' => $currency->id,
            'name' => 'Locked',
            'type' => 'expense',
            'status' => 'locked',
            'version' => 1,
            'total_amount' => 100000,
        ]);

        $this->actingAs($user, 'institute_user')
            ->get(route('finance.budgets.edit', $budget->id))
            ->assertStatus(403);
    }

    public function test_cannot_approve_draft_budget(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $fy = $this->fiscalYear($inst);
        $currency = $this->currency();
        $this->bind($inst);

        $budget = Budget::create([
            'institute_id' => $inst->id,
            'fiscal_year_id' => $fy->id,
            'currency_id' => $currency->id,
            'name' => 'Draft',
            'type' => 'expense',
            'status' => 'draft',
            'version' => 1,
            'total_amount' => 0,
        ]);

        $this->actingAs($user, 'institute_user')
            ->post(route('finance.budgets.approve', $budget->id))
            ->assertStatus(500);
    }

    public function test_cannot_lock_unapproved_budget(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $fy = $this->fiscalYear($inst);
        $currency = $this->currency();
        $this->bind($inst);

        $budget = Budget::create([
            'institute_id' => $inst->id,
            'fiscal_year_id' => $fy->id,
            'currency_id' => $currency->id,
            'name' => 'Unapproved',
            'type' => 'expense',
            'status' => 'draft',
            'version' => 1,
            'total_amount' => 0,
        ]);

        $this->actingAs($user, 'institute_user')
            ->post(route('finance.budgets.lock', $budget->id))
            ->assertStatus(500);
    }

    // -----------------------------------------------------------------
    // BUDGET VS ACTUAL (via controller)
    // -----------------------------------------------------------------

    public function test_budget_vs_actual_report_loads(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $this->bind($inst);

        $this->actingAs($user, 'institute_user')
            ->get(route('finance.budgets.reports'))
            ->assertOk();
    }

    public function test_forecast_page_loads(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $this->bind($inst);

        $this->actingAs($user, 'institute_user')
            ->get(route('finance.budgets.forecast'))
            ->assertOk();
    }

    public function test_dashboard_page_loads(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $this->bind($inst);

        $this->actingAs($user, 'institute_user')
            ->get(route('finance.budgets.dashboard'))
            ->assertOk();
    }

    // -----------------------------------------------------------------
    // TENANT ISOLATION
    // -----------------------------------------------------------------

    public function test_budgets_are_tenant_isolated(): void
    {
        $instA = $this->institute();
        $instB = $this->institute();
        $userA = $this->owner($instA);
        $userB = $this->owner($instB);
        $fyA = $this->fiscalYear($instA);
        $fyB = $this->fiscalYear($instB);
        $currency = $this->currency();

        Budget::create([
            'institute_id' => $instA->id,
            'fiscal_year_id' => $fyA->id,
            'currency_id' => $currency->id,
            'name' => 'Budget A',
            'type' => 'expense',
            'status' => 'draft',
            'version' => 1,
            'total_amount' => 100000,
        ]);

        Budget::create([
            'institute_id' => $instB->id,
            'fiscal_year_id' => $fyB->id,
            'currency_id' => $currency->id,
            'name' => 'Budget B',
            'type' => 'expense',
            'status' => 'draft',
            'version' => 1,
            'total_amount' => 200000,
        ]);

        TenantContext::set($instA->id);
        $responseA = $this->actingAs($userA, 'institute_user')
            ->get(route('finance.budgets.index'));
        $responseA->assertOk();
        $this->assertStringContainsString('Budget A', $responseA->content());
        $this->assertStringNotContainsString('Budget B', $responseA->content());

        TenantContext::set($instB->id);
        $responseB = $this->actingAs($userB, 'institute_user')
            ->get(route('finance.budgets.index'));
        $responseB->assertOk();
        $this->assertStringContainsString('Budget B', $responseB->content());
        $this->assertStringNotContainsString('Budget A', $responseB->content());
    }

    // -----------------------------------------------------------------
    // UNIQUE CONSTRAINT: ONE BUDGET PER TYPE PER FY
    // -----------------------------------------------------------------

    public function test_unique_constraint_prevents_duplicate_type_per_fy(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $inst = $this->institute();
        $fy = $this->fiscalYear($inst);
        $currency = $this->currency();
        $branch = $this->branch($inst);

        Budget::create([
            'institute_id' => $inst->id,
            'branch_id' => $branch->id,
            'fiscal_year_id' => $fy->id,
            'currency_id' => $currency->id,
            'name' => 'First',
            'type' => 'expense',
            'status' => 'draft',
            'version' => 1,
            'total_amount' => 0,
        ]);

        Budget::create([
            'institute_id' => $inst->id,
            'branch_id' => $branch->id,
            'fiscal_year_id' => $fy->id,
            'currency_id' => $currency->id,
            'name' => 'Second',
            'type' => 'expense',
            'status' => 'draft',
            'version' => 1,
            'total_amount' => 0,
        ]);
    }

    public function test_different_types_allowed_for_same_fy(): void
    {
        $inst = $this->institute();
        $fy = $this->fiscalYear($inst);
        $currency = $this->currency();

        Budget::create([
            'institute_id' => $inst->id,
            'fiscal_year_id' => $fy->id,
            'currency_id' => $currency->id,
            'name' => 'Revenue Budget',
            'type' => 'revenue',
            'status' => 'draft',
            'version' => 1,
            'total_amount' => 0,
        ]);

        $b2 = Budget::create([
            'institute_id' => $inst->id,
            'fiscal_year_id' => $fy->id,
            'currency_id' => $currency->id,
            'name' => 'Expense Budget',
            'type' => 'expense',
            'status' => 'draft',
            'version' => 1,
            'total_amount' => 0,
        ]);

        $this->assertNotNull($b2);
        $this->assertEquals(2, Budget::where('institute_id', $inst->id)->count());
    }

    // -----------------------------------------------------------------
    // AUDIT TRAIL
    // -----------------------------------------------------------------

    public function test_budget_creation_is_audited(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $fy = $this->fiscalYear($inst);
        $currency = $this->currency();
        $this->bind($inst);

        $this->actingAs($user, 'institute_user')
            ->post(route('finance.budgets.store'), [
                'name' => 'Audited Budget',
                'type' => 'expense',
                'fiscal_year_id' => $fy->id,
                'currency_id' => $currency->id,
            ]);

        $budget = Budget::where('institute_id', $inst->id)->first();
        $this->assertDatabaseHas('accounting_audit_trails', [
            'institute_id' => $inst->id,
            'entity_type' => Budget::class,
            'entity_id' => $budget->id,
            'action' => 'create',
        ]);
    }

    // -----------------------------------------------------------------
    // VERSIONING
    // -----------------------------------------------------------------

    public function test_budget_starts_at_version_1(): void
    {
        $inst = $this->institute();
        $fy = $this->fiscalYear($inst);
        $currency = $this->currency();
        $this->bind($inst);

        $budget = Budget::create([
            'institute_id' => $inst->id,
            'fiscal_year_id' => $fy->id,
            'currency_id' => $currency->id,
            'name' => 'Version Test',
            'type' => 'expense',
            'status' => 'draft',
            'version' => 1,
            'total_amount' => 0,
        ]);

        $this->assertEquals(1, $budget->version);
    }

    public function test_revision_increments_version(): void
    {
        $inst = $this->institute();
        $user = $this->owner($inst);
        $fy = $this->fiscalYear($inst);
        $currency = $this->currency();
        $this->bind($inst);

        $budget = Budget::create([
            'institute_id' => $inst->id,
            'fiscal_year_id' => $fy->id,
            'currency_id' => $currency->id,
            'name' => 'Rev Test',
            'type' => 'expense',
            'status' => 'approved',
            'version' => 1,
            'total_amount' => 0,
        ]);

        BudgetVersion::create([
            'budget_id' => $budget->id,
            'institute_id' => $inst->id,
            'version' => 1,
            'status' => 'approved',
            'total_amount' => 0,
        ]);

        $this->actingAs($user, 'institute_user')
            ->post(route('finance.budgets.revise', $budget->id), [
                'reason' => 'Version bump',
            ]);

        $budget->refresh();
        $this->assertEquals(2, $budget->version);

        $versions = BudgetVersion::where('budget_id', $budget->id)->orderBy('version')->get();
        $this->assertCount(2, $versions);
        $this->assertEquals(1, $versions[0]->version);
        $this->assertEquals(2, $versions[1]->version);
    }

    // -----------------------------------------------------------------
    // PERMISSIONS
    // -----------------------------------------------------------------

    public function test_guest_cannot_access_budgets(): void
    {
        $this->get(route('finance.budgets.index'))
            ->assertRedirect();
    }
}
