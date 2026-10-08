<?php

namespace Tests\Feature\Accounting\ChartOfAccounts;

use App\Livewire\ChartOfAccountList;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

class CreateAccountModalTest extends TestCase
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

    public function test_new_account_button_opens_popup_for_manager(): void
    {
        $this->asOwner();

        Livewire::test(ChartOfAccountList::class)
            ->assertSee('New Account')
            ->call('openCreateModal')
            ->assertSet('showCreateModal', true)
            ->assertSee('Create account')
            ->assertSee('Cash Flow Category');
    }

    public function test_popup_stores_tenant_owned_account(): void
    {
        $this->asOwner();

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->set('form.code', '9988.77')
            ->set('form.name', 'Popup Created Account')
            ->set('form.type', 'asset')
            ->call('store')
            ->assertHasNoErrors()
            ->assertSet('showCreateModal', false);

        $this->assertDatabaseHas('chart_of_accounts', [
            'institute_id' => $this->institute->id,
            'code' => '9988.77',
            'name' => 'Popup Created Account',
            'type' => 'asset',
            'is_system' => 0,
        ]);
    }

    public function test_popup_validation_requires_code_and_keeps_popup_open(): void
    {
        $this->asOwner();

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->set('form.name', 'No Code Account')
            ->call('store')
            ->assertHasErrors(['form.code'])
            ->assertSet('showCreateModal', true);

        $this->assertDatabaseMissing('chart_of_accounts', ['name' => 'No Code Account']);
    }

    public function test_popup_keeps_glass_design_and_opts_out_of_relocation(): void
    {
        $this->asOwner();

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            // Original Bootstrap/glass design must stay untouched.
            ->assertSee('modal fade show d-block', false)
            ->assertSee('modal-backdrop fade show', false)
            // popup-fix.js relocates any .modal into <body>, detaching it from
            // the Livewire root so wire:click close actions could never remove
            // it. The overlay must opt out while keeping its .modal classes.
            ->assertSee('data-no-relocate', false)
            ->call('closeCreateModal')
            ->assertSet('showCreateModal', false)
            ->assertDontSee('Create account');
    }

    public function test_glass_css_scopes_stacking_release_and_backdrop_to_popup(): void
    {
        $css = file_get_contents(public_path('css/components.css'));

        // Releases main.content's will-change stacking context so the popup's
        // z-index clears the fixed topbar (z-index:1030).
        $this->assertStringContainsString('body:has(.modal[data-no-relocate]) main.content', $css);
        // Shows the standard glass backdrop (default blur) that is otherwise
        // hidden by `body:not(.modal-open) .modal-backdrop { display:none }`.
        $this->assertStringContainsString('body:has(.modal[data-no-relocate]) .modal-backdrop', $css);
    }

    public function test_popup_rejects_code_that_conflicts_with_a_global_account(): void
    {
        $this->asOwner();

        $globalCode = (string) ChartOfAccount::query()
            ->whereNull('institute_id')
            ->where('is_system', 1)
            ->value('code');

        $this->assertNotSame('', $globalCode, 'Expected a global system account in the fixture.');

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->set('form.code', $globalCode)
            ->set('form.name', 'Conflicting Account')
            ->call('store')
            ->assertHasErrors(['form.code']);

        $this->assertDatabaseMissing('chart_of_accounts', ['name' => 'Conflicting Account']);
    }

    public function test_button_hidden_without_management_access(): void
    {
        Livewire::test(ChartOfAccountList::class)
            ->assertDontSee('New Account')
            ->assertDontSee('Create account');
    }

    public function test_button_visible_for_web_workspace_owner(): void
    {
        $owner = $this->makeWebUser('coa-web-owner');
        Membership::create([
            'user_id' => $owner->id,
            'institution_id' => $this->institute->id,
            'role_id' => Role::query()->where('slug', 'institute-owner')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $this->actingAs($owner, 'web');

        Livewire::test(ChartOfAccountList::class)
            ->assertSee('New Account')
            ->call('openCreateModal')
            ->assertSet('showCreateModal', true)
            ->assertSee('Create account');
    }

    public function test_button_hidden_for_web_user_without_manage_permission(): void
    {
        $staff = $this->makeWebUser('coa-web-accountant', 'staff');
        Membership::create([
            'user_id' => $staff->id,
            'institution_id' => $this->institute->id,
            'role_id' => Role::query()->where('slug', 'accountant')->firstOrFail()->id,
            'status' => 'active',
        ]);

        $this->actingAs($staff, 'web');

        Livewire::test(ChartOfAccountList::class)
            ->assertDontSee('New Account')
            ->assertDontSee('Create account');
    }

    public function test_store_records_opening_balance_on_debit_side_for_asset(): void
    {
        $this->asOwner();
        $fiscalYear = $this->fiscalYear('2026-01-01', '2026-12-31');

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->assertSee('Opening balance')
            ->assertSee('Opening balance date')
            ->set('form.code', '9910')
            ->set('form.name', 'Opening Asset')
            ->set('form.type', 'asset')
            ->set('form.opening_balance', 1500.50)
            ->set('form.opening_balance_date', '2026-06-15')
            ->call('store')
            ->assertHasNoErrors()
            ->assertSet('showCreateModal', false);

        $account = ChartOfAccount::query()
            ->where('institute_id', $this->institute->id)
            ->where('code', '9910')
            ->firstOrFail();

        $this->assertDatabaseHas('opening_balances', [
            'institute_id' => $this->institute->id,
            'fiscal_year_id' => $fiscalYear->id,
            'coa_id' => $account->id,
            'debit' => 1500.5,
            'credit' => 0,
            'source' => 'manual',
        ]);
    }

    public function test_liability_opening_balance_posts_to_credit_side(): void
    {
        $this->asOwner();
        $fiscalYear = $this->fiscalYear('2026-01-01', '2026-12-31');

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->set('form.code', '9920')
            ->set('form.name', 'Opening Liability')
            ->set('form.type', 'liability')
            ->set('form.opening_balance', 2000)
            ->set('form.opening_balance_date', '2026-06-15')
            ->call('store')
            ->assertHasNoErrors();

        $account = ChartOfAccount::query()
            ->where('institute_id', $this->institute->id)
            ->where('code', '9920')
            ->firstOrFail();

        $this->assertDatabaseHas('opening_balances', [
            'institute_id' => $this->institute->id,
            'fiscal_year_id' => $fiscalYear->id,
            'coa_id' => $account->id,
            'debit' => 0,
            'credit' => 2000,
        ]);
    }

    public function test_opening_balance_date_without_fiscal_year_rolls_back_account(): void
    {
        $this->asOwner();

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->set('form.code', '9930')
            ->set('form.name', 'No Fiscal Year Account')
            ->set('form.type', 'asset')
            ->set('form.opening_balance', 500)
            ->set('form.opening_balance_date', '2026-06-15')
            ->call('store')
            ->assertHasErrors(['form.opening_balance_date'])
            ->assertSet('showCreateModal', true);

        $this->assertDatabaseMissing('chart_of_accounts', [
            'institute_id' => $this->institute->id,
            'name' => 'No Fiscal Year Account',
        ]);
        $this->assertDatabaseMissing('opening_balances', [
            'institute_id' => $this->institute->id,
            'debit' => 500,
        ]);
    }

    public function test_opening_balance_date_in_closed_fiscal_year_rejected(): void
    {
        $this->asOwner();
        $this->fiscalYear('2026-01-01', '2026-12-31', 'closed');

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->set('form.code', '9950')
            ->set('form.name', 'Closed Year Account')
            ->set('form.type', 'asset')
            ->set('form.opening_balance', 100)
            ->set('form.opening_balance_date', '2026-06-15')
            ->call('store')
            ->assertHasErrors(['form.opening_balance_date'])
            ->assertSet('showCreateModal', true);

        $this->assertDatabaseMissing('chart_of_accounts', [
            'institute_id' => $this->institute->id,
            'name' => 'Closed Year Account',
        ]);
    }

    public function test_negative_opening_balance_rejected(): void
    {
        $this->asOwner();
        $this->fiscalYear('2026-01-01', '2026-12-31');

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->set('form.code', '9970')
            ->set('form.name', 'Negative Balance Account')
            ->set('form.type', 'asset')
            ->set('form.opening_balance', -5)
            ->set('form.opening_balance_date', '2026-06-15')
            ->call('store')
            ->assertHasErrors(['form.opening_balance'])
            ->assertSet('showCreateModal', true);

        $this->assertDatabaseMissing('chart_of_accounts', [
            'institute_id' => $this->institute->id,
            'name' => 'Negative Balance Account',
        ]);
    }

    public function test_blank_opening_balance_creates_account_without_opening_balance_row(): void
    {
        $this->asOwner();

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->set('form.code', '9940')
            ->set('form.name', 'Plain Account')
            ->set('form.type', 'expense')
            ->set('form.opening_balance', '')
            ->call('store')
            ->assertHasNoErrors()
            ->assertSet('showCreateModal', false);

        $account = ChartOfAccount::query()
            ->where('institute_id', $this->institute->id)
            ->where('code', '9940')
            ->firstOrFail();

        $this->assertDatabaseMissing('opening_balances', ['coa_id' => $account->id]);
    }

    public function test_opening_balance_requires_date_when_amount_present(): void
    {
        $this->asOwner();

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->set('form.code', '9960')
            ->set('form.name', 'Missing Date Account')
            ->set('form.type', 'asset')
            ->set('form.opening_balance', 100)
            ->set('form.opening_balance_date', null)
            ->call('store')
            ->assertHasErrors(['form.opening_balance_date'])
            ->assertSet('showCreateModal', true);

        $this->assertDatabaseMissing('chart_of_accounts', [
            'institute_id' => $this->institute->id,
            'name' => 'Missing Date Account',
        ]);
    }

    public function test_switching_type_clears_stale_group_and_parent(): void
    {
        $this->asOwner();

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->set('form.type', 'asset')
            ->set('form.account_group_id', 123)
            ->set('form.parent_id', 456)
            ->set('form.type', 'liability')
            ->assertSet('form.account_group_id', null)
            ->assertSet('form.parent_id', null);
    }

    public function test_cash_flow_category_is_derived_from_type_and_readonly(): void
    {
        $this->asOwner();

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->assertSee('wire:model="form.cash_flow_category" disabled', false)
            ->assertSet('form.cash_flow_category', 'operating')
            ->set('form.type', 'equity')
            ->assertSet('form.cash_flow_category', 'financing')
            ->set('form.type', 'expense')
            ->assertSet('form.cash_flow_category', 'operating');
    }

    public function test_store_derives_cash_flow_category_and_ignores_posted_value(): void
    {
        $this->asOwner();

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->set('form.code', '9931')
            ->set('form.name', 'Derived Category Account')
            ->set('form.type', 'liability')
            ->set('form.cash_flow_category', 'investing')
            ->call('store')
            ->assertHasNoErrors()
            ->assertSet('showCreateModal', false);

        $this->assertDatabaseHas('chart_of_accounts', [
            'institute_id' => $this->institute->id,
            'code' => '9931',
            'cash_flow_category' => 'operating',
        ]);
    }

    public function test_cash_switch_clears_cash_flow_category(): void
    {
        $this->asOwner();

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->assertSet('form.cash_flow_category', 'operating')
            ->set('form.is_cash', true)
            ->assertSet('form.cash_flow_category', null)
            ->set('form.code', '9932')
            ->set('form.name', 'Cash Account Popup')
            ->call('store')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('chart_of_accounts', [
            'institute_id' => $this->institute->id,
            'code' => '9932',
            'cash_flow_category' => null,
        ]);
    }

    public function test_popup_parent_dropdown_hides_cross_industry_accounts(): void
    {
        $this->asOwner();

        // Test fixture institute is industry=education.
        $parents = Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->set('form.type', 'income')
            ->viewData('createParents');

        // Post-C1 (reanchor): only tenant-owned rows are offered as parents —
        // shared globals (even own-industry ones like 4100.1) and cross-
        // industry rows are both excluded.
        $foreign = ChartOfAccount::withoutGlobalScopes()
            ->whereIn('id', $parents->pluck('id'))
            ->where('institute_id', '!=', $this->institute->id)
            ->pluck('id');

        $this->assertEmpty($foreign, 'Every parent option must belong to this tenant.');

        $codes = $parents->pluck('code');
        $this->assertNotContains('4200.1', $codes, 'Training Course Fees must be hidden');
        $this->assertNotContains('4200.2', $codes, 'Training Registration Fees must be hidden');
        $this->assertNotContains('4300.1', $codes, 'Medical Consultation Fees must be hidden');
        $this->assertNotContains('4400.1', $codes, 'Retail Merchandise Sales must be hidden');
    }

    public function test_opening_balance_fields_hidden_for_profit_and_loss_types(): void
    {
        $this->asOwner();

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->assertSee('Opening balance <small', false)
            ->set('form.type', 'income')
            ->assertDontSee('Opening balance <small', false)
            ->set('form.type', 'expense')
            ->assertDontSee('Opening balance <small', false)
            ->set('form.type', 'liability')
            ->assertSee('Opening balance <small', false);
    }

    public function test_store_strips_opening_balance_for_profit_and_loss_account(): void
    {
        $this->asOwner();
        $this->fiscalYear('2026-01-01', '2026-12-31');

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->set('form.code', '9969')
            ->set('form.name', 'P and L Strip Account')
            ->set('form.type', 'income')
            ->set('form.opening_balance', 500)
            ->set('form.opening_balance_date', '2026-06-15')
            ->call('store')
            ->assertHasNoErrors()
            ->assertSet('showCreateModal', false);

        $account = ChartOfAccount::query()
            ->where('institute_id', $this->institute->id)
            ->where('code', '9969')
            ->firstOrFail();

        $this->assertDatabaseMissing('opening_balances', [
            'institute_id' => $this->institute->id,
            'coa_id' => $account->id,
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

    private function makeWebUser(string $prefix, string $accountType = 'owner'): User
    {
        return User::create([
            'name' => 'COA Web '.$prefix,
            'email' => $prefix.'-'.uniqid().'@example.test',
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
            'account_type' => $accountType,
            'email_verified_at' => now(),
        ]);
    }

    private function asOwner(): InstituteUser
    {
        $ownerRoleId = Role::withoutGlobalScopes()->where('slug', 'institute-owner')->firstOrFail()->id;

        $instituteUser = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $ownerRoleId,
            'first_name' => 'Modal',
            'last_name' => 'Owner',
            'email' => 'coa-modal-'.uniqid().'@example.test',
            'phone' => '01700'.mt_rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $this->actingAs($instituteUser, 'institute_user');

        return $instituteUser;
    }
}
