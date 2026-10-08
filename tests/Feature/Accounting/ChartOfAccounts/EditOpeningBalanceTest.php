<?php

namespace Tests\Feature\Accounting\ChartOfAccounts;

use App\Livewire\ChartOfAccountList;
use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Services\Accounting\OpeningBalanceService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class EditOpeningBalanceTest extends TestCase
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

    public function test_edit_opening_balance_button_renders_on_rows(): void
    {
        $this->asOwner();

        Livewire::test(ChartOfAccountList::class)
            ->assertSee('openOpeningModal(', false)
            ->assertSee('bi-wallet2');
    }

    public function test_button_hidden_without_management_access(): void
    {
        Livewire::test(ChartOfAccountList::class)
            ->assertDontSee('openOpeningModal(', false);
    }

    public function test_store_opening_denied_without_management_permission(): void
    {
        Livewire::test(ChartOfAccountList::class)
            ->call('openOpeningModal', 1)
            ->assertSet('showOpeningModal', false)
            ->call('storeOpening')
            ->assertSet('showOpeningModal', false);
    }

    public function test_modal_opens_for_global_account_with_glass_design(): void
    {
        $this->asOwner();
        $global = $this->globalAccount('1000.1');

        Livewire::test(ChartOfAccountList::class)
            ->call('openOpeningModal', $global->id)
            ->assertSet('showOpeningModal', true)
            ->assertSee('Edit Opening Balance')
            ->assertSee('Save opening balance')
            ->assertSee('data-no-relocate')
            ->assertSee($global->code)
            ->assertSee($global->name);
    }

    public function test_global_account_opening_balance_can_be_set(): void
    {
        $this->asOwner();
        $fiscalYear = $this->fiscalYear('2026-01-01', '2026-12-31');
        $global = $this->globalAccount('1000.1');

        Livewire::test(ChartOfAccountList::class)
            ->call('openOpeningModal', $global->id)
            ->assertSet('showOpeningModal', true)
            ->set('openingForm.balance', 5000)
            ->set('openingForm.date', '2026-01-05')
            ->call('storeOpening')
            ->assertHasNoErrors()
            ->assertSet('showOpeningModal', false);

        $this->assertDatabaseHas('opening_balances', [
            'institute_id' => $this->institute->id,
            'fiscal_year_id' => $fiscalYear->id,
            'coa_id' => $global->id,
            'debit' => 5000,
            'credit' => 0,
        ]);
    }

    public function test_own_tenant_account_opening_balance_posts_to_credit_side(): void
    {
        $this->asOwner();
        $fiscalYear = $this->fiscalYear('2026-01-01', '2026-12-31');

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->set('form.code', '9876.1')
            ->set('form.name', 'Own OB Edit Account')
            ->set('form.type', 'liability')
            ->call('store')
            ->assertHasNoErrors();

        $own = ChartOfAccount::query()
            ->where('institute_id', $this->institute->id)
            ->where('code', '9876.1')
            ->firstOrFail();

        Livewire::test(ChartOfAccountList::class)
            ->call('openOpeningModal', $own->id)
            ->assertSet('showOpeningModal', true)
            ->set('openingForm.balance', 250)
            ->set('openingForm.date', '2026-01-05')
            ->call('storeOpening')
            ->assertHasNoErrors()
            ->assertSet('showOpeningModal', false);

        $this->assertDatabaseHas('opening_balances', [
            'institute_id' => $this->institute->id,
            'fiscal_year_id' => $fiscalYear->id,
            'coa_id' => $own->id,
            'debit' => 0,
            'credit' => 250,
        ]);
    }

    public function test_existing_opening_balance_prefills_and_blank_clears(): void
    {
        $this->asOwner();
        $this->fiscalYear('2026-01-01', '2026-12-31');
        $global = $this->globalAccount('1000.1');

        Livewire::test(ChartOfAccountList::class)
            ->call('openOpeningModal', $global->id)
            ->set('openingForm.balance', 5000)
            ->set('openingForm.date', '2026-01-05')
            ->call('storeOpening')
            ->assertHasNoErrors();

        Livewire::test(ChartOfAccountList::class)
            ->call('openOpeningModal', $global->id)
            ->assertSet('showOpeningModal', true)
            ->assertSet('openingForm.balance', 5000)
            ->assertSet('openingForm.date', '2026-01-01')
            ->set('openingForm.balance', null)
            ->call('storeOpening')
            ->assertHasNoErrors()
            ->assertSet('showOpeningModal', false);

        $this->assertDatabaseMissing('opening_balances', [
            'institute_id' => $this->institute->id,
            'coa_id' => $global->id,
        ]);
    }

    public function test_date_without_open_fiscal_year_rejected(): void
    {
        $this->asOwner();
        $global = $this->globalAccount('1000.1');

        Livewire::test(ChartOfAccountList::class)
            ->call('openOpeningModal', $global->id)
            ->set('openingForm.balance', 100)
            ->set('openingForm.date', '2026-06-30')
            ->call('storeOpening')
            ->assertHasErrors(['openingForm.date'])
            ->assertSet('showOpeningModal', true);

        $this->assertDatabaseMissing('opening_balances', [
            'institute_id' => $this->institute->id,
            'coa_id' => $global->id,
        ]);
    }

    public function test_date_in_closed_fiscal_year_rejected(): void
    {
        $this->asOwner();
        $this->fiscalYear('2026-01-01', '2026-12-31', 'closed');
        $global = $this->globalAccount('1000.1');

        Livewire::test(ChartOfAccountList::class)
            ->call('openOpeningModal', $global->id)
            ->set('openingForm.balance', 100)
            ->set('openingForm.date', '2026-06-30')
            ->call('storeOpening')
            ->assertHasErrors(['openingForm.date']);

        $this->assertDatabaseMissing('opening_balances', [
            'institute_id' => $this->institute->id,
            'coa_id' => $global->id,
        ]);
    }

    public function test_cross_industry_global_cannot_be_opened(): void
    {
        $this->asOwner();

        $retailGlobal = ChartOfAccount::withoutGlobalScopes()
            ->whereNull('institute_id')
            ->whereJsonContains('industries', 'retail')
            ->firstOrFail();

        Livewire::test(ChartOfAccountList::class)
            ->call('openOpeningModal', $retailGlobal->id)
            ->assertStatus(404);
    }

    public function test_foreign_tenant_account_cannot_be_opened(): void
    {
        $this->asOwner();

        $group = AccountGroup::query()->whereNull('institute_id')->where('is_system', 1)->firstOrFail();
        $foreign = Institute::where('name', 'Tutu Center')->firstOrFail();

        $foreignAccountId = DB::table('chart_of_accounts')->insertGetId([
            'institute_id' => $foreign->id,
            'account_group_id' => $group->id,
            'code' => '9871.'.mt_rand(1000, 9999),
            'name' => 'Foreign Tenant OB Probe',
            'type' => 'asset',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Livewire::test(ChartOfAccountList::class)
            ->call('openOpeningModal', $foreignAccountId)
            ->assertStatus(404);
    }

    public function test_opening_balance_button_hidden_on_profit_and_loss_rows(): void
    {
        $this->asOwner();

        $asset = ChartOfAccount::withoutGlobalScopes()
            ->whereNull('institute_id')
            ->where('type', 'asset')
            ->firstOrFail();
        $income = ChartOfAccount::withoutGlobalScopes()
            ->whereNull('institute_id')
            ->where('type', 'income')
            ->firstOrFail();
        $expense = ChartOfAccount::withoutGlobalScopes()
            ->whereNull('institute_id')
            ->where('type', 'expense')
            ->firstOrFail();

        Livewire::test(ChartOfAccountList::class)
            ->assertSee('openOpeningModal('.$asset->id.')', false)
            ->assertDontSee('openOpeningModal('.$income->id.')', false)
            ->assertDontSee('openOpeningModal('.$expense->id.')', false);
    }

    public function test_opening_balance_rejected_for_profit_and_loss_account(): void
    {
        $this->asOwner();
        // Fiscal year exists, so a missing guard would actually write the row.
        $this->fiscalYear('2026-01-01', '2026-12-31');

        $income = ChartOfAccount::withoutGlobalScopes()
            ->whereNull('institute_id')
            ->where('type', 'income')
            ->firstOrFail();

        Livewire::test(ChartOfAccountList::class)
            ->call('openOpeningModal', $income->id)
            ->assertSet('showOpeningModal', false);

        Livewire::test(ChartOfAccountList::class)
            ->set('openingAccountId', $income->id)
            ->set('openingForm.balance', 500)
            ->set('openingForm.date', '2026-06-15')
            ->call('storeOpening')
            ->assertSet('showOpeningModal', false);

        $this->assertDatabaseMissing('opening_balances', [
            'institute_id' => $this->institute->id,
            'coa_id' => $income->id,
        ]);
    }

    public function test_service_rejects_opening_balance_for_profit_and_loss_account(): void
    {
        $this->asOwner();
        $fiscalYear = $this->fiscalYear('2026-01-01', '2026-12-31');

        $income = ChartOfAccount::withoutGlobalScopes()
            ->whereNull('institute_id')
            ->where('type', 'income')
            ->firstOrFail();

        try {
            app(OpeningBalanceService::class)->upsertSingle(
                $this->institute->id,
                null,
                $fiscalYear,
                (int) $income->id,
                100.0,
                0.0,
                null,
            );
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('form', $e->errors());
            $this->assertStringContainsString('balance sheet', $e->errors()['form'][0]);
        }

        $this->assertDatabaseMissing('opening_balances', [
            'institute_id' => $this->institute->id,
            'coa_id' => $income->id,
        ]);
    }

    public function test_batch_upsert_rejects_profit_and_loss_account(): void
    {
        $this->asOwner();
        $fiscalYear = $this->fiscalYear('2026-01-01', '2026-12-31');

        Livewire::test(ChartOfAccountList::class)
            ->call('openCreateModal')
            ->set('form.code', '9878.1')
            ->set('form.name', 'Owned P and L Account')
            ->set('form.type', 'income')
            ->call('store')
            ->assertHasNoErrors();

        $income = ChartOfAccount::query()
            ->where('institute_id', $this->institute->id)
            ->where('code', '9878.1')
            ->firstOrFail();

        try {
            app(OpeningBalanceService::class)->upsert($this->institute->id, null, $fiscalYear, [
                ['coa_id' => $income->id, 'debit' => 100, 'credit' => 0],
            ], null);
            $this->fail('Expected ValidationException.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('entries', $e->errors());
            $this->assertStringContainsString('balance sheet', $e->errors()['entries'][0]);
        }

        $this->assertDatabaseMissing('opening_balances', [
            'institute_id' => $this->institute->id,
            'coa_id' => $income->id,
        ]);
    }

    private function globalAccount(string $code): ChartOfAccount
    {
        return ChartOfAccount::withoutGlobalScopes()
            ->whereNull('institute_id')
            ->where('code', $code)
            ->firstOrFail();
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
            'first_name' => 'Opening',
            'last_name' => 'Owner',
            'email' => 'coa-opening-'.uniqid().'@example.test',
            'phone' => '01700'.mt_rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $this->actingAs($instituteUser, 'institute_user');

        return $instituteUser;
    }
}
