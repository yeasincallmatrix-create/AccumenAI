<?php

namespace Tests\Feature;

use App\Models\AccountingAuditTrail;
use App\Models\AccountingPeriod;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Country;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Journal;
use App\Models\Party;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\PartyService;
use App\Services\Accounting\PurchaseAccountingService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * STEP 15 — Purchase, Expense & Accounts Payable Accounting.
 *
 * The purchase/expense/AP lifecycle is entirely journal-derived through the
 * existing engine: purchases post `purchase` journals (Dr expense / Cr AP),
 * supplier payments post `payment` journals (Dr AP / Cr cash-bank via the
 * shared STEP 14 payment-method resolver), cash expenses post `journal` rows
 * (Dr expense / Cr cash-bank), and cancellation uses the engine's reversal
 * convention. No AP balance tables, no duplicate party systems, no new
 * business models: the existing Party records (type supplier|both) are the
 * suppliers and ReceivablesPayablesService derives every balance.
 */
class PurchaseAccountingTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    // ------------------------------------------------------------ Fixtures

    private function service(): PurchaseAccountingService
    {
        return app(PurchaseAccountingService::class);
    }

    private function country(): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );
    }

    private function institute(string $name = 'Purchase Inst'): Institute
    {
        $country = $this->country();

        return Institute::create([
            'name' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute, string $name): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function setupAccounting(Institute $institute, ?Branch $branch = null): void
    {
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch?->id);
    }

    private function user(Institute $institute, string $roleSlug, string $prefix, ?Branch $branch = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'first_name' => $prefix,
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function supplier(Institute $institute, int $actorId, array $overrides = [], ?Branch $branch = null): Party
    {
        return app(PartyService::class)->create($institute->id, $branch?->id, array_merge([
            'type' => 'supplier',
            'name' => 'Test Supplier',
            'phone' => '01712'.rand(100000, 999999),
        ], $overrides), $actorId);
    }

    private function coaId(Institute $institute, string $code, ?Branch $branch = null): ?int
    {
        return ChartOfAccount::query()
            ->where('institute_id', $institute->id)
            ->when($branch !== null, fn ($query) => $query->where('branch_id', $branch->id))
            ->where('code', $code)
            ->value('id');
    }

    private function account(Institute $institute, ?int $actorId, string $code, string $name, string $type, ?Branch $branch = null): ChartOfAccount
    {
        return app(ChartOfAccountService::class)->createAccount(
            $institute->id,
            $branch?->id,
            ['code' => $code, 'name' => $name, 'type' => $type],
            $actorId,
        );
    }

    private function currencyId(string $code = 'BDT'): int
    {
        return (int) (Currency::query()->where('code', $code)->value('id') ?? Currency::query()->orderBy('code')->value('id'));
    }

    private function assertRejected(string $exception, callable $callback): void
    {
        try {
            $callback();
            $this->fail("Expected {$exception} to be thrown.");
        } catch (\Throwable $thrown) {
            $this->assertInstanceOf($exception, $thrown);
        }
    }

    private function payableLine(Journal $journal, ?int $payableAccountId): ?object
    {
        return $journal->entries->firstWhere('coa_id', $payableAccountId);
    }

    private function journalDerivedPayable(Institute $institute, Party $supplier): float
    {
        return round((float) DB::table('journal_entries as je')
            ->join('journals as j', 'j.id', '=', 'je.journal_id')
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'je.coa_id')
            ->where('je.institute_id', $institute->id)
            ->where('je.party_id', $supplier->id)
            ->where('j.status', 'posted')
            ->whereNull('j.reversal_of')
            ->where('coa.is_payable', true)
            ->selectRaw('COALESCE(SUM(je.credit - je.debit), 0) AS b')
            ->value('b'), 4);
    }

    // ------------------------------------------------------- Purchases

    public function test_purchase_posts_a_balanced_journal_to_expense_and_ap(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $journal = $this->service()->postPurchase(
            $institute->id,
            null,
            $supplier,
            10000,
            null,
            null,
            null,
            (int) $owner->id,
        );

        $this->assertSame('posted', $journal->status);
        $this->assertSame('purchase', $journal->type);
        $this->assertSame('purchase', $journal->ref_type);
        $this->assertMatchesRegularExpression('/^J-\d{8}-[A-Z0-9]{5}$/', $journal->journal_no);
        $this->assertGreaterThanOrEqual(2, $journal->entries->count());

        $totals = ['debit' => 0.0, 'credit' => 0.0];
        foreach ($journal->entries as $line) {
            $totals['debit'] += (float) $line->debit;
            $totals['credit'] += (float) $line->credit;
        }
        $this->assertSame(round($totals['debit'], 4), round($totals['credit'], 4), 'Purchase journal must balance.');

        $payableId = (int) $this->coaId($institute, '2001');
        $line = $this->payableLine($journal, $payableId);
        $this->assertNotNull($line, 'A purchase must credit Accounts Payable.');
        $this->assertSame(10000.0, round((float) $line->credit, 4));
        $this->assertSame((int) $supplier->id, (int) $line->party_id);
    }

    public function test_purchase_creates_accounts_payable(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $this->service()->postPurchase($institute->id, null, $supplier, 5000, null, null, null, (int) $owner->id);

        $balance = $this->service()->supplierBalance($supplier);
        $this->assertSame(5000.0, round($balance['payable'], 4));
        $this->assertSame(5000.0, round($this->service()->apTotals($institute->id)['payable'], 4));
    }

    public function test_input_tax_books_when_tax_account_supplied(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);
        $vat = $this->account($institute, (int) $owner->id, '1501', 'Input VAT', 'asset');

        $journal = $this->service()->postPurchase(
            $institute->id,
            null,
            $supplier,
            10000,
            null,
            (int) $vat->id,
            1500,
            (int) $owner->id,
        );

        $expenseDebit = $journal->entries->sum(fn ($line) => (float) $line->debit);
        $payableCredit = $journal->entries->sum(fn ($line) => (float) $line->credit);
        $this->assertSame(11500.0, round($expenseDebit, 4));
        $this->assertSame(11500.0, round($payableCredit, 4));
        $this->assertSame(1500.0, round((float) $journal->entries->firstWhere('coa_id', $vat->id)->debit, 4));
        $this->assertSame(11500.0, round($this->service()->supplierBalance($supplier)['payable'], 4));
    }

    public function test_input_tax_requires_a_tax_account(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $this->assertRejected(ValidationException::class, fn () => $this->service()->postPurchase(
            $institute->id,
            null,
            $supplier,
            10000,
            null,
            null,
            1500,
            (int) $owner->id,
        ));
    }

    // ------------------------------------------------- Supplier payments

    public function test_supplier_payment_reduces_ap(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $this->service()->postPurchase($institute->id, null, $supplier, 5000, null, null, null, (int) $owner->id);
        $this->service()->postSupplierPayment($institute->id, null, $supplier, 5000, null, 'cash', (int) $owner->id);

        $this->assertSame(0.0, round($this->service()->supplierBalance($supplier)['payable'], 4));
    }

    public function test_partial_supplier_payment_leaves_remaining_ap(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $this->service()->postPurchase($institute->id, null, $supplier, 10000, null, null, null, (int) $owner->id);
        $this->service()->postSupplierPayment($institute->id, null, $supplier, 3000, null, 'cash', (int) $owner->id);

        $this->assertSame(7000.0, round($this->service()->supplierBalance($supplier)['payable'], 4));
        $this->assertSame(-3000.0, round((float) app(FinancialReportService::class)->cashBankSummary($institute->id, null)->firstWhere('code', '1001')->balance, 4));
    }

    public function test_full_supplier_payment_zeroes_ap(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $this->service()->postPurchase($institute->id, null, $supplier, 7777, null, null, null, (int) $owner->id);
        $this->service()->postSupplierPayment($institute->id, null, $supplier, 7777, null, 'bank', (int) $owner->id);

        $this->assertSame(0.0, round($this->service()->supplierBalance($supplier)['payable'], 4));
        $this->assertSame(-7777.0, round((float) app(FinancialReportService::class)->cashBankSummary($institute->id, null)->firstWhere('code', '1002')->balance, 4));
    }

    public function test_overpayment_of_supplier_is_rejected(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $this->service()->postPurchase($institute->id, null, $supplier, 5000, null, null, null, (int) $owner->id);

        $this->assertRejected(ValidationException::class, fn () => $this->service()->postSupplierPayment(
            $institute->id,
            null,
            $supplier,
            5001,
            null,
            'cash',
            (int) $owner->id,
        ));

        $this->assertSame(5000.0, round($this->service()->supplierBalance($supplier)['payable'], 4));
        $this->assertSame(1, Journal::query()->where('institute_id', $institute->id)->count(), 'Rejected overpayment must not post a journal.');
    }

    // ---------------------------------------------- Payment-method mapping

    public function test_cash_payment_credits_cash_coa(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $this->service()->postPurchase($institute->id, null, $supplier, 900, null, null, null, (int) $owner->id);
        $payment = $this->service()->postSupplierPayment($institute->id, null, $supplier, 900, null, 'cash', (int) $owner->id);

        $cashId = (int) $this->coaId($institute, '1001');
        $this->assertSame(900.0, round((float) $payment->entries->firstWhere('coa_id', $cashId)->credit, 4));
    }

    public function test_bank_payment_credits_bank_coa(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $this->service()->postPurchase($institute->id, null, $supplier, 900, null, null, null, (int) $owner->id);
        $payment = $this->service()->postSupplierPayment($institute->id, null, $supplier, 900, null, 'bank', (int) $owner->id);

        $bankId = (int) $this->coaId($institute, '1002');
        $this->assertSame(900.0, round((float) $payment->entries->firstWhere('coa_id', $bankId)->credit, 4));
    }

    public function test_payment_method_coa_mapping_wins_over_legacy_string(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $cashId = (int) $this->coaId($institute, '1001');
        $method = PaymentMethod::query()->create([
            'institute_id' => $institute->id,
            'name' => 'Custom Cash Desk',
            'coa_id' => $cashId,
            'is_system' => false,
            'is_active' => true,
            'created_by' => (int) $owner->id,
        ]);

        $this->service()->postPurchase($institute->id, null, $supplier, 800, null, null, null, (int) $owner->id);
        $payment = $this->service()->postSupplierPayment(
            $institute->id,
            null,
            $supplier,
            800,
            (int) $method->id,
            'bank',
            (int) $owner->id,
        );

        $this->assertSame(800.0, round((float) $payment->entries->firstWhere('coa_id', $cashId)->credit, 4));
    }

    // --------------------------------------------------- Cancellation

    public function test_purchase_cancellation_reverses_via_engine_convention(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $journal = $this->service()->postPurchase($institute->id, null, $supplier, 6000, null, null, null, (int) $owner->id);
        $reversal = $this->service()->reversePurchase($journal, (int) $institute->id, (int) $owner->id, 'Purchase cancelled');

        $this->assertSame('reversed', $journal->refresh()->status);
        $this->assertSame('posted', $reversal->status);
        $this->assertSame((int) $journal->id, (int) $reversal->reversal_of);
        $this->assertSame(0, Journal::query()->where('institute_id', $institute->id)->where('status', 'draft')->count());
    }

    public function test_reversal_nets_ap_to_zero(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $journal = $this->service()->postPurchase($institute->id, null, $supplier, 6000, null, null, null, (int) $owner->id);
        $this->service()->reversePurchase($journal, (int) $institute->id, (int) $owner->id, 'Return');

        $this->assertSame(0.0, round($this->service()->supplierBalance($supplier)['payable'], 4));
        $this->assertSame(0.0, round($this->journalDerivedPayable($institute, $supplier), 4));
        $this->assertSame(0.0, round((float) app(FinancialReportService::class)->incomeStatement($institute->id, null)['total_expense'], 4));
    }

    // ---------------------------------------- Engine integrity guarantees

    public function test_posted_journal_cannot_be_modified_reposted_or_reversed_twice(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $journal = $this->service()->postPurchase($institute->id, null, $supplier, 4200, null, null, null, (int) $owner->id);

        $this->assertSame('posted', $journal->status);

        $this->assertRejected(\LogicException::class, fn () => app(JournalPostingService::class)->post($journal, (int) $institute->id, (int) $owner->id));
        $this->assertRejected(\LogicException::class, fn () => app(JournalPostingService::class)->void($journal, (int) $institute->id, (int) $owner->id));

        $originalEntries = $journal->entries->map(fn ($line) => [(float) $line->debit, (float) $line->credit])->all();
        $reversal = $this->service()->reversePurchase($journal, (int) $institute->id, (int) $owner->id);

        $this->assertRejected(\LogicException::class, fn () => app(JournalPostingService::class)->reverse($journal, (int) $institute->id, (int) $owner->id));
        $this->assertRejected(\LogicException::class, fn () => app(JournalPostingService::class)->reverse($reversal, (int) $institute->id, (int) $owner->id));

        $this->assertSame(
            $originalEntries,
            $journal->refresh()->entries->map(fn ($line) => [(float) $line->debit, (float) $line->credit])->all(),
            'Reversal must not mutate the original posted entries.',
        );
    }

    public function test_duplicate_posting_of_the_same_journal_is_rejected(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $journal = $this->service()->postPurchase($institute->id, null, $supplier, 3000, null, null, null, (int) $owner->id);

        $this->assertRejected(\LogicException::class, fn () => app(JournalPostingService::class)->post($journal, (int) $institute->id, (int) $owner->id));

        $this->assertSame(3000.0, round($this->service()->supplierBalance($supplier)['payable'], 4));
        $this->assertSame(1, Journal::query()->where('institute_id', $institute->id)->count());
    }

    public function test_closed_fiscal_period_rejects_purchase_posting(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $year = FiscalYear::query()->where('institute_id', $institute->id)->firstOrFail();
        app(AccountingPeriodService::class)->createMonthlyPeriods($year, (int) $owner->id);

        $period = AccountingPeriod::query()
            ->where('institute_id', $institute->id)
            ->where('fiscal_year_id', $year->id)
            ->where('status', 'open')
            ->whereDate('start_date', '<=', now()->toDateString())
            ->whereDate('end_date', '>=', now()->toDateString())
            ->first();

        $this->assertNotNull($period);
        app(AccountingPeriodService::class)->closePeriod($period, $institute->id, (int) $owner->id);

        $this->assertRejected(ValidationException::class, fn () => $this->service()->postPurchase(
            $institute->id,
            null,
            $supplier,
            1000,
            null,
            null,
            null,
            (int) $owner->id,
        ));

        $this->assertSame(0, Journal::query()->where('institute_id', $institute->id)->count());
    }

    // --------------------------------------------------------- Isolation

    public function test_cross_tenant_supplier_is_rejected(): void
    {
        $a = $this->institute('Tenant A');
        $b = $this->institute('Tenant B');
        $this->setupAccounting($a);
        $this->setupAccounting($b);
        $ownerA = $this->user($a, 'institute-owner', 'owner-a');
        $ownerB = $this->user($b, 'institute-owner', 'owner-b');
        $supplierA = $this->supplier($a, (int) $ownerA->id);

        $this->assertRejected(ValidationException::class, fn () => $this->service()->postPurchase(
            $b->id,
            null,
            $supplierA,
            1000,
            null,
            null,
            null,
            (int) $ownerB->id,
        ));

        $this->assertSame(0, Journal::query()->where('institute_id', $b->id)->count());
        $this->assertSame(0.0, round($this->service()->apTotals($b->id)['payable'], 4));
    }

    public function test_branch_isolated_supplier_cannot_be_used_elsewhere(): void
    {
        $institute = $this->institute();
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $this->setupAccounting($institute, $branchA);
        $this->setupAccounting($institute, $branchB);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplierA = $this->supplier($institute, (int) $owner->id, [], $branchA);

        $this->service()->postPurchase($institute->id, $branchA->id, $supplierA, 2000, null, null, null, (int) $owner->id);

        $this->assertRejected(ValidationException::class, fn () => $this->service()->postPurchase(
            $institute->id,
            $branchB->id,
            $supplierA,
            2000,
            null,
            null,
            null,
            (int) $owner->id,
        ));

        $this->assertSame(2000.0, round($this->service()->apTotals($institute->id, $branchA->id)['payable'], 4));
        $this->assertSame(0.0, round($this->service()->apTotals($institute->id, $branchB->id)['payable'], 4));
    }

    public function test_supplier_ownership_and_type_are_validated(): void
    {
        $a = $this->institute('Tenant A');
        $b = $this->institute('Tenant B');
        $this->setupAccounting($a);
        $this->setupAccounting($b);
        $ownerA = $this->user($a, 'institute-owner', 'owner-a');
        $ownerB = $this->user($b, 'institute-owner', 'owner-b');

        $customer = $this->supplier($a, (int) $ownerA->id, ['type' => 'customer', 'name' => 'A Customer']);
        $foreign = $this->supplier($b, (int) $ownerB->id);

        $this->assertRejected(ValidationException::class, fn () => $this->service()->postPurchase(
            $a->id,
            null,
            $customer,
            500,
            null,
            null,
            null,
            (int) $ownerA->id,
        ));

        $this->assertRejected(ValidationException::class, fn () => $this->service()->postPurchase(
            $a->id,
            null,
            $foreign,
            500,
            null,
            null,
            null,
            (int) $ownerA->id,
        ));

        $this->assertSame(0, Journal::query()->where('institute_id', $a->id)->count());
    }

    // --------------------------------------- Balances, aging, reconciliation

    public function test_ap_balance_equals_journal_derived_balance(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $this->service()->postPurchase($institute->id, null, $supplier, 4000, null, null, null, (int) $owner->id);
        $this->service()->postPurchase($institute->id, null, $supplier, 6000, null, null, null, (int) $owner->id);
        $this->service()->postSupplierPayment($institute->id, null, $supplier, 3000, null, 'cash', (int) $owner->id);

        $this->assertSame(7000.0, round($this->service()->supplierBalance($supplier)['payable'], 4));
        $this->assertSame(7000.0, round($this->journalDerivedPayable($institute, $supplier), 4));
        $this->assertSame(7000.0, round($this->service()->supplierBalances($institute->id)->first()->payable, 4));
    }

    public function test_ap_aging_buckets_are_correct(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $this->service()->postPurchase($institute->id, null, $supplier, 1000, null, null, null, (int) $owner->id, now()->subDays(40)->toDateString());
        $this->service()->postPurchase($institute->id, null, $supplier, 500, null, null, null, (int) $owner->id, now()->toDateString());

        $aging = $this->service()->supplierAging($supplier);
        $this->assertSame(500.0, round($aging['current'], 4));
        $this->assertSame(1000.0, round($aging['31_60'], 4));
        $this->assertSame(0.0, round($aging['61_90'], 4));
        $this->assertSame(0.0, round($aging['91_plus'], 4));

        $withAging = $this->service()->supplierBalancesWithAging($institute->id)->first();
        $this->assertSame(500.0, round($withAging->aging['current'], 4));
        $this->assertSame(1000.0, round($withAging->aging['31_60'], 4));
    }

    public function test_full_purchase_payment_cycle_reconciles_every_report(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $this->service()->postPurchase($institute->id, null, $supplier, 10000, null, null, null, (int) $owner->id);
        $this->service()->postSupplierPayment($institute->id, null, $supplier, 10000, null, 'cash', (int) $owner->id);

        $this->assertSame(0.0, round($this->service()->supplierBalance($supplier)['payable'], 4));
        $this->assertSame(0.0, round($this->service()->apTotals($institute->id)['payable'], 4));

        $tb = app(FinancialReportService::class)->trialBalance($institute->id, null);
        $this->assertSame(round($tb->sum('debit'), 4), round($tb->sum('credit'), 4), 'Trial balance must balance.');
        $this->assertGreaterThan(0, $tb->sum('debit'));

        $pl = app(FinancialReportService::class)->incomeStatement($institute->id, null);
        $this->assertSame(10000.0, round($pl['total_expense'], 4));
        $this->assertSame(0.0, round($pl['total_income'], 4));

        $sheet = app(FinancialReportService::class)->balanceSheet($institute->id, null);
        $this->assertSame(round($sheet['total_assets'], 4), round($sheet['total_liabilities'] + $sheet['total_equity'], 4), 'Assets must equal liabilities plus equity.');
        $this->assertSame(10000.0, round((float) $sheet['assets']->firstWhere('code', '1001')->balance, 4) * -1);
    }

    // --------------------------------------------------------------- Expenses

    public function test_cash_expense_posts_directly_without_ap(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $journal = $this->service()->postExpense(
            $institute->id,
            null,
            750,
            null,
            null,
            'cash',
            $supplier,
            (int) $owner->id,
        );

        $this->assertSame('posted', $journal->status);
        $this->assertSame('journal', $journal->type);
        $this->assertSame('expense', $journal->ref_type);

        $expenseId = (int) $this->coaId($institute, '5001');
        $cashId = (int) $this->coaId($institute, '1001');
        $this->assertSame(750.0, round((float) $journal->entries->firstWhere('coa_id', $expenseId)->debit, 4));
        $this->assertSame(750.0, round((float) $journal->entries->firstWhere('coa_id', $cashId)->credit, 4));

        $this->assertSame(0.0, round($this->service()->supplierBalance($supplier)['payable'], 4));
        $this->assertSame(750.0, round((float) app(FinancialReportService::class)->incomeStatement($institute->id, null)['total_expense'], 4));
    }

    // ------------------------------------------------------- Audit + config

    public function test_audit_trail_created_for_purchase_payment_and_expense(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $journal = $this->service()->postPurchase($institute->id, null, $supplier, 9000, null, null, null, (int) $owner->id);
        $this->service()->postSupplierPayment($institute->id, null, $supplier, 4000, null, 'cash', (int) $owner->id);
        $this->service()->postExpense($institute->id, null, 500, null, null, 'cash', null, (int) $owner->id);
        $this->service()->reversePurchase($journal, (int) $institute->id, (int) $owner->id, 'Adjust');

        $pairs = AccountingAuditTrail::query()
            ->where('institute_id', $institute->id)
            ->get(['action', 'entity_type'])
            ->map(fn ($row) => $row->action.'|'.$row->entity_type)
            ->all();

        $this->assertContains('create|purchase', $pairs);
        $this->assertContains('create|supplier_payment', $pairs);
        $this->assertContains('create|expense', $pairs);
        $this->assertContains('reverse|purchase', $pairs);
        $this->assertContains('create|journal', $pairs);
        $this->assertContains('post|journal', $pairs);
    }

    public function test_currency_configuration_is_respected(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        app(AccountingSetupService::class)->setSetting($institute->id, 'base_currency', 'USD');
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $supplier = $this->supplier($institute, (int) $owner->id);

        $usdId = $this->currencyId('USD');

        $journal = $this->service()->postPurchase($institute->id, null, $supplier, 500, null, null, null, (int) $owner->id);
        $this->assertSame($usdId, (int) $journal->currency_id);

        $payment = $this->service()->postSupplierPayment($institute->id, null, $supplier, 500, null, 'cash', (int) $owner->id);
        $this->assertSame($usdId, (int) $payment->currency_id);
    }
}