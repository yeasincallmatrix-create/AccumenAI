<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Country;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\Party;
use App\Models\PaymentMethod;
use App\Services\Accounting\AccountingAuditService;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\ReceivablesPayablesService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Steps 1-5 — Global Accounting Engine: schema, models, CoA setup,
 * double-entry posting engine and derived receivables/payables.
 */
class AccountingEngineTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    // ------------------------------------------------------------ Fixtures

    private function institute(): Institute
    {
        $country = Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );

        return Institute::create([
            'name' => 'Accounting Inst',
            'slug' => str()->slug('accounting-inst-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => 'Head Office',
            'status' => 'active',
        ]);
    }

    private function setupAccounting(Institute $institute, Branch $branch): AccountingSetupService
    {
        $service = new AccountingSetupService(new ChartOfAccountService);
        $service->setupForInstitute($institute->id, $branch->id);

        return $service;
    }

    private function posting(): JournalPostingService
    {
        return new JournalPostingService(new AccountingAuditService);
    }

    private function coaId(Institute $institute, string $code): int
    {
        return ChartOfAccount::query()
            ->where('institute_id', $institute->id)
            ->where('code', $code)
            ->value('id');
    }

    private function currencyId(): int
    {
        return \DB::table('currencies')->where('code', 'BDT')->value('id');
    }

    // ------------------------------------------------------------ Tests

    public function test_setup_is_idempotent(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);

        $this->setupAccounting($institute, $branch);
        $this->setupAccounting($institute, $branch);

        $this->assertSame(5, \DB::table('account_groups')->where('institute_id', $institute->id)->count());
        $this->assertSame(38, ChartOfAccount::query()->where('institute_id', $institute->id)->count());
        $this->assertSame(4, PaymentMethod::query()->where('institute_id', $institute->id)->count());
        $this->assertSame(1, FiscalYear::query()->where('institute_id', $institute->id)->count());
    }

    public function test_posts_a_balanced_journal(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        $this->setupAccounting($institute, $branch);

        $cash = $this->coaId($institute, '1001');
        $tuition = $this->coaId($institute, '4001');

        $journal = $this->posting()->create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'journal_date' => now()->toDateString(),
            'type' => 'receipt',
            'currency_id' => $this->currencyId(),
            'description' => 'Tuition fee',
            'entries' => [
                ['coa_id' => $cash, 'debit' => 10000, 'credit' => 0],
                ['coa_id' => $tuition, 'debit' => 0, 'credit' => 10000],
            ],
        ], 1);

        $this->assertSame('posted', $journal->status);
        $this->assertStringStartsWith('J-', $journal->journal_no);
        $this->assertCount(2, $journal->entries);
        $this->assertEqualsWithDelta(0.0, (float) $journal->entries->sum('debit') - (float) $journal->entries->sum('credit'), 0.0001);
    }

    public function test_unbalanced_journal_is_rejected(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        $this->setupAccounting($institute, $branch);

        $cash = $this->coaId($institute, '1001');
        $tuition = $this->coaId($institute, '4001');

        $this->expectException(ValidationException::class);

        $this->posting()->create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $cash, 'debit' => 100, 'credit' => 0],
                ['coa_id' => $tuition, 'debit' => 0, 'credit' => 90],
            ],
        ], 1);
    }

    public function test_double_sided_line_is_rejected(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        $this->setupAccounting($institute, $branch);

        $cash = $this->coaId($institute, '1001');
        $tuition = $this->coaId($institute, '4001');

        $this->expectException(ValidationException::class);

        $this->posting()->create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $cash, 'debit' => 100, 'credit' => 5],
                ['coa_id' => $tuition, 'debit' => 0, 'credit' => 95],
            ],
        ], 1);
    }

    public function test_reversal_nets_the_balance_to_zero(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        $this->setupAccounting($institute, $branch);

        $ar = $this->coaId($institute, '1100');
        $tuition = $this->coaId($institute, '4001');

        $customer = Party::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'type' => 'customer',
            'name' => 'Customer',
            'phone' => '01700000099',
        ]);

        $journal = $this->posting()->create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'journal_date' => now()->subDays(5)->toDateString(),
            'type' => 'sale',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $ar, 'debit' => 5000, 'credit' => 0, 'party_id' => $customer->id],
                ['coa_id' => $tuition, 'debit' => 0, 'credit' => 5000],
            ],
        ], 1);

        $rp = new ReceivablesPayablesService;

        $this->assertSame(5000.0, $rp->partyBalance($customer)['net']);

        $this->posting()->reverse($journal, (int) $institute->id, 1, 'cancel sale');

        $this->assertSame(0.0, $rp->partyBalance($customer)['net']);
        $this->assertSame('reversed', $journal->refresh()->status);
    }

    public function test_void_draft_journal_has_no_effect(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        $this->setupAccounting($institute, $branch);

        $cash = $this->coaId($institute, '1001');
        $tuition = $this->coaId($institute, '4001');

        $draft = $this->posting()->create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $cash, 'debit' => 50, 'credit' => 0],
                ['coa_id' => $tuition, 'debit' => 0, 'credit' => 50],
            ],
        ], 1, false);

        $this->assertSame('draft', $draft->status);

        $this->posting()->void($draft, (int) $institute->id, 1);

        $this->assertSame('void', $draft->refresh()->status);
    }

    public function test_derived_ar_ap_balances_and_aging(): void
    {
        $institute = $this->institute();
        $branch = $this->branch($institute);
        $this->setupAccounting($institute, $branch);

        $cash = $this->coaId($institute, '1001');
        $ar = $this->coaId($institute, '1100');
        $ap = $this->coaId($institute, '2001');
        $tuition = $this->coaId($institute, '4001');
        $misc = $this->coaId($institute, '5006');

        $customer = Party::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'type' => 'customer',
            'name' => 'AR Customer',
            'phone' => '01711110000',
        ]);
        $supplier = Party::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'type' => 'supplier',
            'name' => 'AP Supplier',
            'phone' => '01722220000',
        ]);

        $posting = $this->posting();

        $posting->create([
            'institute_id' => $institute->id, 'branch_id' => $branch->id,
            'journal_date' => now()->subDays(10)->toDateString(), 'type' => 'sale',
            'currency_id' => $this->currencyId(), 'description' => 'Sale on credit',
            'entries' => [
                ['coa_id' => $ar, 'debit' => 5000, 'credit' => 0, 'party_id' => $customer->id],
                ['coa_id' => $tuition, 'debit' => 0, 'credit' => 5000],
            ],
        ], 1);

        $posting->create([
            'institute_id' => $institute->id, 'branch_id' => $branch->id,
            'journal_date' => now()->subDays(3)->toDateString(), 'type' => 'receipt',
            'currency_id' => $this->currencyId(), 'description' => 'Partial payment',
            'entries' => [
                ['coa_id' => $cash, 'debit' => 2000, 'credit' => 0],
                ['coa_id' => $ar, 'debit' => 0, 'credit' => 2000, 'party_id' => $customer->id],
            ],
        ], 1);

        $posting->create([
            'institute_id' => $institute->id, 'branch_id' => $branch->id,
            'journal_date' => now()->subDays(45)->toDateString(), 'type' => 'purchase',
            'currency_id' => $this->currencyId(), 'description' => 'Supplies on credit',
            'entries' => [
                ['coa_id' => $misc, 'debit' => 3000, 'credit' => 0],
                ['coa_id' => $ap, 'debit' => 0, 'credit' => 3000, 'party_id' => $supplier->id],
            ],
        ], 1);

        $rp = new ReceivablesPayablesService;

        $this->assertSame(3000.0, $rp->partyBalance($customer)['net']);
        $this->assertSame(-3000.0, $rp->partyBalance($supplier)['net']);

        $totals = $rp->totals($institute->id, $branch->id);
        $this->assertSame(3000.0, $totals['receivable']);
        $this->assertSame(3000.0, $totals['payable']);

        $customers = $rp->customerBalances($institute->id, $branch->id);
        $this->assertCount(1, $customers);
        $this->assertSame(3000.0, $customers->first()->receivable);

        $aging = $rp->aging($customer);
        $this->assertSame(3000.0, $aging['current']);
    }

    public function test_accounting_permissions_are_seeded(): void
    {
        $slugs = \DB::table('permissions')->where('module', 'accounting')->pluck('slug')->all();

        $this->assertContains('accounts.view', $slugs);
        $this->assertContains('journals.post', $slugs);
        $this->assertContains('reports.financial.view', $slugs);
        $this->assertContains('settings.accounting.manage', $slugs);
    }
}
