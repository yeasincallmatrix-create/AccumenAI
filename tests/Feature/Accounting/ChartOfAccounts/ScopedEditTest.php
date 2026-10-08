<?php

namespace Tests\Feature\Accounting\ChartOfAccounts;

use App\Livewire\ChartOfAccountList;
use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Edit access is scope-aware: every visible row (own + shared globals) offers
 * the Edit action, but the form only lets this institute change its own data.
 * The shared definition stays read-only and the definition PUT still 403s.
 */
class ScopedEditTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        TenantContext::set($this->institute->id);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_every_row_has_an_edit_action_and_the_read_only_label_is_gone(): void
    {
        $this->asOwner();

        Livewire::test(ChartOfAccountList::class)
            ->set('perPage', 500)
            ->assertSee('bi-pencil-square')
            ->assertDontSee('Read-only');
    }

    public function test_global_row_links_to_the_edit_page(): void
    {
        $this->asOwner();
        $global = $this->globalAccount('1000.1');

        Livewire::test(ChartOfAccountList::class)
            ->set('perPage', 500)
            ->assertSee(route('finance.chart-of-accounts.edit', $global), false);
    }

    public function test_global_account_edit_page_locks_the_shared_definition(): void
    {
        $this->asOwner();
        $global = $this->balanceSheetGlobal();

        $this->get(route('finance.chart-of-accounts.edit', $global))
            ->assertOk()
            ->assertSee('is shared by every institute')
            ->assertSee($global->name)
            ->assertDontSee('name="code"', false)
            ->assertDontSee('name="type"', false)
            ->assertSee('name="opening_balance"', false);
    }

    public function test_own_account_edit_page_keeps_the_full_definition_form(): void
    {
        $this->asOwner();
        $own = $this->ownAccount();

        $this->get(route('finance.chart-of-accounts.edit', $own))
            ->assertOk()
            ->assertDontSee('is shared by every institute')
            ->assertSee('name="code"', false)
            ->assertSee('name="type"', false)
            ->assertSee('name="opening_balance"', false);
    }

    public function test_profit_and_loss_global_offers_no_opening_balance_block(): void
    {
        $this->asOwner();
        $income = ChartOfAccount::withoutGlobalScopes()
            ->whereNull('institute_id')
            ->where('type', 'income')
            ->orderBy('code')
            ->firstOrFail();

        $this->get(route('finance.chart-of-accounts.edit', $income))
            ->assertOk()
            ->assertSee('is shared by every institute')
            ->assertDontSee('name="opening_balance"', false);
    }

    public function test_opening_balance_from_the_edit_page_is_tenant_scoped(): void
    {
        $this->asOwner();
        $fiscalYear = $this->fiscalYear('2026-01-01', '2026-12-31');
        $global = $this->balanceSheetGlobal();
        $name = $global->name;

        $this->post(route('finance.chart-of-accounts.update.opening', $global), [
            'opening_balance' => 5000,
            'opening_balance_date' => '2026-01-05',
        ])->assertRedirect(route('finance.chart-of-accounts.edit', $global));

        $this->assertDatabaseHas('opening_balances', [
            'institute_id' => $this->institute->id,
            'fiscal_year_id' => $fiscalYear->id,
            'coa_id' => $global->id,
            'debit' => 5000,
            'credit' => 0,
        ]);

        // The shared row itself must not move a byte.
        $this->assertSame($name, $global->fresh()->name);
        $this->assertNull($global->fresh()->institute_id);
    }

    public function test_blank_amount_clears_the_institute_opening_balance(): void
    {
        $this->asOwner();
        $fiscalYear = $this->fiscalYear('2026-01-01', '2026-12-31');
        $global = $this->balanceSheetGlobal();

        $this->post(route('finance.chart-of-accounts.update.opening', $global), [
            'opening_balance' => 5000,
            'opening_balance_date' => '2026-01-05',
        ])->assertRedirect();

        $this->post(route('finance.chart-of-accounts.update.opening', $global), [
            'opening_balance' => null,
            'opening_balance_date' => '2026-01-05',
        ])->assertRedirect();

        $this->assertDatabaseMissing('opening_balances', [
            'institute_id' => $this->institute->id,
            'fiscal_year_id' => $fiscalYear->id,
            'coa_id' => $global->id,
        ]);
    }

    public function test_opening_balance_without_an_open_fiscal_year_is_rejected(): void
    {
        $this->asOwner();
        $global = $this->balanceSheetGlobal();

        $this->from(route('finance.chart-of-accounts.edit', $global))
            ->post(route('finance.chart-of-accounts.update.opening', $global), [
                'opening_balance' => 100,
                'opening_balance_date' => '2026-06-30',
            ])
            ->assertSessionHasErrors('opening_balance_date');

        $this->assertDatabaseMissing('opening_balances', [
            'institute_id' => $this->institute->id,
            'coa_id' => $global->id,
        ]);
    }

    public function test_definition_update_of_a_global_account_is_still_forbidden(): void
    {
        $this->asOwner();
        $global = $this->globalAccount('1000.1');

        $this->put(route('finance.chart-of-accounts.update', $global), [
            'code' => $global->code,
            'name' => 'HACKED',
            'type' => $global->type,
        ])->assertForbidden();

        $this->assertSame($global->name, $global->fresh()->name);
    }

    // ------------------------------------------------------------ Helpers

    private function globalAccount(string $code): ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()
            ->whereNull('institute_id')
            ->where('code', $code)
            ->firstOrFail();
    }

    private function balanceSheetGlobal(): ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()
            ->whereNull('institute_id')
            ->whereIn('type', ['asset', 'liability', 'equity'])
            ->orderBy('code')
            ->firstOrFail();
    }

    private function ownAccount(): ChartOfAccount
    {
        $groupId = AccountGroup::withoutGlobalScope('institute')
            ->where('institute_id', $this->institute->id)
            ->where('category', 'asset')
            ->value('id')
            ?? AccountGroup::withoutGlobalScope('institute')
                ->whereNull('institute_id')
                ->where('is_system', 1)
                ->where('category', 'asset')
                ->value('id');

        return ChartOfAccount::withoutGlobalScope('institute')->create([
            'institute_id' => $this->institute->id,
            'branch_id' => null,
            'account_group_id' => $groupId,
            'code' => '9777.1',
            'name' => 'Scoped Edit Own Account',
            'type' => 'asset',
            'is_system' => 0,
            'is_active' => 1,
        ]);
    }

    private function fiscalYear(string $start, string $end, string $status = 'open'): FiscalYear
    {
        return FiscalYear::create([
            'institute_id' => $this->institute->id,
            'name' => 'FY '.$start,
            'start_date' => $start,
            'end_date' => $end,
            'status' => $status,
            'is_current' => true,
        ]);
    }

    private function asOwner(): InstituteUser
    {
        $ownerRoleId = Role::withoutGlobalScopes()->where('slug', 'institute-owner')->firstOrFail()->id;

        $instituteUser = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $ownerRoleId,
            'first_name' => 'Scoped',
            'last_name' => 'Owner',
            'email' => 'coa-scoped-'.uniqid().'@example.test',
            'phone' => '01700'.mt_rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $this->actingAs($instituteUser, 'institute_user');

        return $instituteUser;
    }
}
