<?php

namespace Tests\Feature\Accounting\ChartOfAccounts;

use App\Livewire\ChartOfAccountList;
use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\Journal;
use App\Models\JournalEntry;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * QuickBooks-style balance column: income/liability/equity show on their
 * normal credit side (no minus for a healthy credit balance); a minus only
 * survives when the balance sits on the wrong side (e.g. overdrawn bank).
 * The internal `balance` attribute stays raw Dr-Cr.
 */
class AccountBalanceSignTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    private string $incomeCode;

    private string $cashCode;

    private string $overdrawnCode;

    private ChartOfAccount $income;

    private ChartOfAccount $cash;

    private ChartOfAccount $overdrawn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        TenantContext::set($this->institute->id);

        $suffix = mt_rand(1000, 9999);
        $this->incomeCode = '8895.'.$suffix;
        $this->cashCode = '8896.'.$suffix;
        $this->overdrawnCode = '8897.'.$suffix;

        $this->income = $this->account($this->incomeCode, 'income', 'Balance Sign Probe Income '.$suffix);
        $this->cash = $this->account($this->cashCode, 'asset', 'Balance Sign Probe Cash '.$suffix);
        $this->overdrawn = $this->account($this->overdrawnCode, 'asset', 'Balance Sign Probe Overdrawn '.$suffix);

        // J1: Dr cash 700 / Cr income 700.
        // J2: Dr income 50 / Cr overdrawn bank 50 (wrong side for an asset).
        $this->postJournal([
            [$this->cash->id, 700, 0],
            [$this->income->id, 0, 700],
        ]);
        $this->postJournal([
            [$this->income->id, 50, 0],
            [$this->overdrawn->id, 0, 50],
        ]);
    }

    protected function tearDown(): void
    {
        TenantContext::clear();

        parent::tearDown();
    }

    public function test_credit_normal_account_balance_renders_on_credit_side(): void
    {
        $row = $this->rowFor($this->incomeCode);

        $this->assertEqualsWithDelta(-650.0, (float) $row->balance, 0.0001, 'internal balance stays raw Dr-Cr');
        $this->assertEqualsWithDelta(650.0, (float) $row->display_balance, 0.0001, 'column flips to the credit side');

        Livewire::test(ChartOfAccountList::class)
            ->set('search', $this->incomeCode)
            ->assertSee('650.00')
            ->assertDontSee('-650.00');
    }

    public function test_debit_normal_account_balance_renders_on_debit_side(): void
    {
        $row = $this->rowFor($this->cashCode);

        $this->assertEqualsWithDelta(700.0, (float) $row->balance, 0.0001);
        $this->assertEqualsWithDelta(700.0, (float) $row->display_balance, 0.0001);

        Livewire::test(ChartOfAccountList::class)
            ->set('search', $this->cashCode)
            ->assertSee('700.00')
            ->assertDontSee('-700.00');
    }

    public function test_wrong_side_debit_account_keeps_minus_sign(): void
    {
        $row = $this->rowFor($this->overdrawnCode);

        $this->assertEqualsWithDelta(-50.0, (float) $row->balance, 0.0001);
        $this->assertEqualsWithDelta(-50.0, (float) $row->display_balance, 0.0001);

        Livewire::test(ChartOfAccountList::class)
            ->set('search', $this->overdrawnCode)
            ->assertSee('-50.00');
    }

    private function rowFor(string $code): ChartOfAccount
    {
        $page = Livewire::test(ChartOfAccountList::class)
            ->set('search', $code)
            ->viewData('accounts');

        $row = collect($page->items())->firstWhere('code', $code);

        $this->assertNotNull($row, "account {$code} missing from list page");

        return $row;
    }

    private function account(string $code, string $type, string $name): ChartOfAccount
    {
        return ChartOfAccount::create([
            'institute_id' => $this->institute->id,
            'branch_id' => null,
            'account_group_id' => $this->groupId($type),
            'code' => $code,
            'name' => $name,
            'type' => $type,
            'is_system' => 0,
            'is_active' => 1,
        ]);
    }

    private function groupId(string $type): int
    {
        return (int) (AccountGroup::withoutGlobalScope('institute')
            ->where('institute_id', $this->institute->id)
            ->where('category', $type)
            ->value('id')
            ?? AccountGroup::withoutGlobalScope('institute')
                ->whereNull('institute_id')
                ->where('is_system', 1)
                ->where('category', $type)
                ->value('id'));
    }

    /**
     * @param  array<int, array{0:int,1:float,2:float}>  $lines  [coa_id, debit, credit]
     */
    private function postJournal(array $lines): Journal
    {
        $fiscalYear = FiscalYear::create([
            'institute_id' => $this->institute->id,
            'name' => 'FY Sign Probe '.uniqid(),
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
            'status' => 'open',
            'is_current' => true,
        ]);

        $currencyId = (int) (Currency::where('code', 'BDT')->value('id')
            ?? Currency::orderBy('id')->value('id'));

        $journal = Journal::create([
            'institute_id' => $this->institute->id,
            'branch_id' => null,
            'journal_no' => 'J-SGN'.strtoupper(substr(uniqid(), -10)),
            'journal_date' => now()->toDateString(),
            'fiscal_year_id' => $fiscalYear->id,
            'type' => 'journal',
            'currency_id' => $currencyId,
            'status' => 'posted',
            'posted_at' => now(),
        ]);

        foreach ($lines as [$coaId, $debit, $credit]) {
            JournalEntry::create([
                'journal_id' => $journal->id,
                'institute_id' => $this->institute->id,
                'branch_id' => null,
                'coa_id' => $coaId,
                'journal_date' => now()->toDateString(),
                'debit' => $debit,
                'credit' => $credit,
            ]);
        }

        return $journal;
    }
}
