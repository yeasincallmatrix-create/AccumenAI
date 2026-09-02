<?php

namespace Tests\Feature;

use App\Models\AccountingAuditTrail;
use App\Models\Branch;
use App\Models\ChartOfAccount;
use App\Models\Country;
use App\Models\Currency;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Party;
use App\Models\Role;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ChartOfAccountService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\InvoiceService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\PartyService;
use App\Services\Accounting\PaymentService;
use App\Services\Accounting\ReceivablesPayablesService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Step 32 — Finance & Accounting: services (invoices, payments, periods,
 * reports), the accounting permission matrix and tenant/branch isolation.
 */
class FinanceCoreTest extends TestCase
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

    private function country(): Country
    {
        return Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );
    }

    private function institute(string $name = 'Fin Inst'): Institute
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
        $service = new AccountingSetupService(new ChartOfAccountService);
        $service->setupForInstitute($institute->id, $branch?->id);
    }

    private function autoPost(Institute $institute): void
    {
        app(AccountingSetupService::class)->setSetting($institute->id, 'invoice_auto_post', true);
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

    private function party(Institute $institute, int $actorId, array $overrides = []): Party
    {
        return app(PartyService::class)->create($institute->id, null, array_merge([
            'type' => 'customer',
            'name' => 'Acme Customer',
            'phone' => '01711'.rand(100000, 999999),
        ], $overrides), $actorId);
    }

    private function invoiceData(int $partyId, array $overrides = []): array
    {
        return array_merge([
            'party_id' => $partyId,
            'invoice_type' => 'other',
            'discount' => 0,
            'items' => [
                ['description' => 'Service', 'amount' => 100.00],
                ['description' => 'Product', 'amount' => 50.00],
            ],
        ], $overrides);
    }

    private function invoice(Institute $institute, int $partyId, int $actorId, array $overrides = []): Invoice
    {
        return app(InvoiceService::class)->create($institute->id, null, $this->invoiceData($partyId, $overrides), $actorId);
    }

    private function coaId(Institute $institute, string $code): ?int
    {
        return ChartOfAccount::query()
            ->where('institute_id', $institute->id)
            ->where('code', $code)
            ->value('id');
    }

    private function currencyId(): int
    {
        return (int) (Currency::query()->where('code', 'BDT')->value('id') ?? Currency::query()->orderBy('code')->value('id'));
    }

    private function journalPayload(Institute $institute, ?int $branchId, int $cashId, int $incomeId, float $amount): array
    {
        return [
            'institute_id' => $institute->id,
            'branch_id' => $branchId,
            'journal_date' => now()->toDateString(),
            'type' => 'journal',
            'currency_id' => $this->currencyId(),
            'entries' => [
                ['coa_id' => $cashId, 'debit' => $amount, 'credit' => 0],
                ['coa_id' => $incomeId, 'debit' => 0, 'credit' => $amount],
            ],
        ];
    }

    // ------------------------------------------------------------ Invoices & payments

    public function test_creates_invoice_with_items_and_draft_sale_journal(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);

        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);

        $this->assertSame(2, $invoice->items->count());
        $this->assertSame(150.0, round((float) $invoice->total_amount, 4));
        $this->assertSame(150.0, round((float) $invoice->payable_amount, 4));
        $this->assertSame(150.0, round((float) $invoice->due_amount, 4));
        $this->assertSame('unpaid', $invoice->status);
        $this->assertNotNull($invoice->journal_id);
        $this->assertSame('sale', $invoice->journal->type);
        $this->assertSame('draft', $invoice->journal->status);
    }

    public function test_invoice_auto_post_posts_the_sale_journal(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $this->autoPost($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);

        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);

        $this->assertSame('posted', $invoice->journal->status);

        $arDebit = $invoice->journal->entries()
            ->where('coa_id', $this->coaId($institute, '1100'))
            ->sum('debit');
        $this->assertSame(150.0, round((float) $arDebit, 4));
    }

    public function test_invoice_discount_reduces_payable_and_debit(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $this->autoPost($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);

        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id, ['discount' => 30]);

        $this->assertSame(120.0, round((float) $invoice->payable_amount, 4));
        $this->assertSame(120.0, round((float) $invoice->due_amount, 4));

        $arDebit = $invoice->journal->entries()
            ->where('coa_id', $this->coaId($institute, '1100'))
            ->sum('debit');
        $this->assertSame(120.0, round((float) $arDebit, 4));
    }

    public function test_records_full_payment_and_marks_invoice_paid(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);
        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);

        $payment = app(PaymentService::class)->record($institute->id, null, [
            'invoice_id' => $invoice->id,
            'amount' => 150,
            'payment_method' => 'cash',
        ], (int) $owner->id);

        $invoice->refresh();
        $this->assertSame(150.0, round((float) $invoice->paid_amount, 4));
        $this->assertSame(0.0, round((float) $invoice->due_amount, 4));
        $this->assertSame('paid', $invoice->status);
        $this->assertNotNull($payment->journal_id);
        $this->assertSame('receipt', $payment->journal->type);
    }

    public function test_records_partial_payment_and_updates_status(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);
        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);

        app(PaymentService::class)->record($institute->id, null, [
            'invoice_id' => $invoice->id,
            'amount' => 50,
            'payment_method' => 'bank',
        ], (int) $owner->id);

        $invoice->refresh();
        $this->assertSame(50.0, round((float) $invoice->paid_amount, 4));
        $this->assertSame(100.0, round((float) $invoice->due_amount, 4));
        $this->assertSame('partial', $invoice->status);
    }

    public function test_overpayment_is_rejected(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);
        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);

        try {
            app(PaymentService::class)->record($institute->id, null, [
                'invoice_id' => $invoice->id,
                'amount' => 151,
                'payment_method' => 'cash',
            ], (int) $owner->id);
            $this->fail('Overpayment should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('amount', $exception->errors());
        }

        $this->assertSame(0, $invoice->refresh()->payments()->count());
    }

    public function test_reverse_payment_restores_invoice(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);
        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);

        $payment = app(PaymentService::class)->record($institute->id, null, [
            'invoice_id' => $invoice->id,
            'amount' => 150,
            'payment_method' => 'cash',
        ], (int) $owner->id);

        app(PaymentService::class)->reverse($payment, $institute->id, (int) $owner->id, 'Test reversal');

        $invoice->refresh();
        $this->assertSame(0.0, round((float) $invoice->paid_amount, 4));
        $this->assertSame(150.0, round((float) $invoice->due_amount, 4));
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame('reversed', $payment->refresh()->journal->status);
    }

    public function test_cancelled_invoice_cannot_receive_payments(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);
        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);

        app(InvoiceService::class)->cancel($invoice, $institute->id, (int) $owner->id);
        $this->assertSame('cancelled', $invoice->refresh()->status);

        try {
            app(PaymentService::class)->record($institute->id, null, [
                'invoice_id' => $invoice->id,
                'amount' => 10,
                'payment_method' => 'cash',
            ], (int) $owner->id);
            $this->fail('Payment on cancelled invoice should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice_id', $exception->errors());
        }
    }

    // ------------------------------------------------------------ Chart of accounts

    public function test_creates_and_updates_account_with_code_unique_guard(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $service = new ChartOfAccountService;

        $account = $service->createAccount($institute->id, null, [
            'code' => '6001',
            'name' => 'Software',
            'type' => 'expense',
        ], (int) $owner->id);

        $this->assertSame('6001', $account->code);
        $this->assertTrue($account->is_active);

        try {
            $service->createAccount($institute->id, null, [
                'code' => '6001',
                'name' => 'Duplicate',
                'type' => 'expense',
            ], (int) $owner->id);
            $this->fail('Duplicate account code should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('code', $exception->errors());
        }

        $updated = $service->updateAccount($account, ['code' => '6001', 'name' => 'Software Licenses', 'type' => 'expense'], (int) $owner->id);
        $this->assertSame('Software Licenses', $updated->name);
    }

    public function test_system_account_cannot_be_deleted_but_can_be_deactivated(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $service = new ChartOfAccountService;
        $cash = ChartOfAccount::query()->where('institute_id', $institute->id)->where('code', '1001')->firstOrFail();

        try {
            $service->delete($cash, (int) $owner->id);
            $this->fail('System account deletion should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('account', $exception->errors());
        }

        $deactivated = $service->toggleActive($cash, (int) $owner->id);
        $this->assertFalse($deactivated->is_active);
    }

    public function test_account_with_journal_entries_cannot_be_deleted(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $service = new ChartOfAccountService;

        $account = $service->createAccount($institute->id, null, [
            'code' => '6002',
            'name' => 'New Expense',
            'type' => 'expense',
        ], (int) $owner->id);

        $cash = ChartOfAccount::query()->where('institute_id', $institute->id)->where('code', '1001')->firstOrFail();

        app(JournalPostingService::class)->create($this->journalPayload($institute, null, $cash->id, $account->id, 5), (int) $owner->id);

        try {
            $service->delete($account, (int) $owner->id);
            $this->fail('Deletion of an account with entries should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('account', $exception->errors());
        }
    }

    // ------------------------------------------------------------ Parties

    public function test_duplicate_party_phone_within_scope_is_rejected(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $service = app(PartyService::class);

        $first = $service->create($institute->id, null, [
            'type' => 'customer',
            'name' => 'First',
            'phone' => '01800000000',
        ], (int) $owner->id);

        $this->assertSame('01800000000', $first->phone);

        try {
            $service->create($institute->id, null, [
                'type' => 'customer',
                'name' => 'Second',
                'phone' => '01800000000',
            ], (int) $owner->id);
            $this->fail('Duplicate party phone should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('phone', $exception->errors());
        }

        // Different type -> allowed.
        $supplier = $service->create($institute->id, null, [
            'type' => 'supplier',
            'name' => 'Supplier',
            'phone' => '01800000000',
        ], (int) $owner->id);
        $this->assertSame('supplier', $supplier->type);
    }

    // ------------------------------------------------------------ Reports

    public function test_trial_balance_balances_after_invoice_and_payment(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $this->autoPost($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);
        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);

        app(PaymentService::class)->record($institute->id, null, [
            'invoice_id' => $invoice->id,
            'amount' => 150,
            'payment_method' => 'cash',
        ], (int) $owner->id);

        $rows = app(FinancialReportService::class)->trialBalance($institute->id, null);

        $debit = round($rows->sum('debit'), 4);
        $credit = round($rows->sum('credit'), 4);

        $this->assertSame($debit, $credit, 'Trial balance must balance.');
        $this->assertGreaterThan(0, $debit);
    }

    public function test_income_statement_reflects_sale(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $this->autoPost($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);

        $this->invoice($institute, $customer->id, (int) $owner->id);

        $statement = app(FinancialReportService::class)->incomeStatement($institute->id, null);
        $this->assertSame(150.0, round($statement['total_income'], 4));
        $this->assertSame(150.0, round($statement['net'], 4));
    }

    public function test_balance_sheet_reports_cash_and_receivables(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $this->autoPost($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);
        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);

        app(PaymentService::class)->record($institute->id, null, [
            'invoice_id' => $invoice->id,
            'amount' => 50,
            'payment_method' => 'cash',
        ], (int) $owner->id);

        $sheet = app(FinancialReportService::class)->balanceSheet($institute->id, null);

        $cash = $sheet['assets']->firstWhere('code', '1001');
        $ar = $sheet['assets']->firstWhere('code', '1100');

        $this->assertNotNull($cash, 'Cash account expected on the balance sheet.');
        $this->assertSame(50.0, round((float) $cash->balance, 4));
        $this->assertSame(100.0, round((float) $ar->balance, 4));
        $this->assertSame(150.0, round($sheet['total_assets'], 4));
    }

    public function test_ar_derives_from_invoices_and_payments(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $this->autoPost($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);
        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);

        app(PaymentService::class)->record($institute->id, null, [
            'invoice_id' => $invoice->id,
            'amount' => 50,
            'payment_method' => 'bank',
        ], (int) $owner->id);

        $balance = app(ReceivablesPayablesService::class)->partyBalance($customer);
        $this->assertSame(100.0, round($balance['receivable'], 4));

        $totals = app(ReceivablesPayablesService::class)->totals($institute->id);
        $this->assertSame(100.0, round($totals['receivable'], 4));
    }

    public function test_cash_bank_summary_lists_cash_accounts(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $this->autoPost($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);
        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);

        app(PaymentService::class)->record($institute->id, null, [
            'invoice_id' => $invoice->id,
            'amount' => 150,
            'payment_method' => 'cash',
        ], (int) $owner->id);

        $summary = app(FinancialReportService::class)->cashBankSummary($institute->id, null);

        $cash = $summary->firstWhere('code', '1001');
        $this->assertNotNull($cash);
        $this->assertSame(150.0, round((float) $cash->balance, 4));
    }

    public function test_reversal_nets_ledger_totals(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');

        $cash = ChartOfAccount::query()->where('institute_id', $institute->id)->where('code', '1001')->firstOrFail();
        $tuition = ChartOfAccount::query()->where('institute_id', $institute->id)->where('code', '4001')->firstOrFail();

        $journal = app(JournalPostingService::class)->create($this->journalPayload($institute, null, $cash->id, $tuition->id, 100), (int) $owner->id);

        app(JournalPostingService::class)->reverse($journal, (int) $institute->id, (int) $owner->id, 'mistake');

        $statement = app(FinancialReportService::class)->incomeStatement($institute->id, null);
        $this->assertSame(0.0, round($statement['total_income'], 4));
    }

    // ------------------------------------------------------------ Periods

    public function test_closed_period_blocks_new_postings(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');

        $year = FiscalYear::query()->where('institute_id', $institute->id)->firstOrFail();
        app(AccountingPeriodService::class)->createMonthlyPeriods($year, (int) $owner->id);
        $period = $year->periods()->first();

        $this->assertNotNull($period);
        $this->assertTrue($period->isOpen());

        app(AccountingPeriodService::class)->closePeriod($period, $institute->id, (int) $owner->id);
        $this->assertFalse($period->refresh()->isOpen());

        $cash = ChartOfAccount::query()->where('institute_id', $institute->id)->where('code', '1001')->firstOrFail();
        $tuition = ChartOfAccount::query()->where('institute_id', $institute->id)->where('code', '4001')->firstOrFail();

        try {
            app(JournalPostingService::class)->create(array_merge($this->journalPayload($institute, null, $cash->id, $tuition->id, 10), [
                'journal_date' => $period->start_date,
                'period_id' => $period->id,
            ]), (int) $owner->id);
            $this->fail('Posting into a closed period should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('period_id', $exception->errors());
        }

        app(AccountingPeriodService::class)->reopenPeriod($period, $institute->id, (int) $owner->id);
        $this->assertTrue($period->refresh()->isOpen());
    }

    public function test_institute_wide_journal_posts_without_branch(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');

        $cash = ChartOfAccount::query()->where('institute_id', $institute->id)->where('code', '1001')->firstOrFail();
        $tuition = ChartOfAccount::query()->where('institute_id', $institute->id)->where('code', '4001')->firstOrFail();

        $journal = app(JournalPostingService::class)->create($this->journalPayload($institute, null, $cash->id, $tuition->id, 25), (int) $owner->id);

        $this->assertNull($journal->branch_id);
        $this->assertSame('posted', $journal->status);
    }

    public function test_audit_trail_records_financial_mutations(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);

        app(ChartOfAccountService::class)->createAccount($institute->id, null, [
            'code' => '6003',
            'name' => 'Audited Expense',
            'type' => 'expense',
        ], (int) $owner->id);

        $this->invoice($institute, $customer->id, (int) $owner->id);

        $audits = AccountingAuditTrail::query()
            ->where('institute_id', $institute->id)
            ->get();

        $this->assertTrue($audits->contains('entity_type', 'invoice'));
        $this->assertTrue($audits->contains('entity_type', 'journal'));
        $this->assertTrue($audits->contains('entity_type', 'chart_of_account'));
    }

    // ------------------------------------------------------------ Permissions

    public function test_teacher_without_accounting_permission_is_forbidden(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $teacher = $this->user($institute, 'teacher', 'teacher');

        $this->actingAs($teacher, 'institute_user')
            ->get(route('finance.dashboard'))
            ->assertForbidden();

        $this->actingAs($teacher, 'institute_user')
            ->get(route('finance.chart-of-accounts.index'))
            ->assertForbidden();
    }

    public function test_receptionist_can_view_accounts_but_not_manage(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $receptionist = $this->user($institute, 'receptionist', 'receptionist');

        $this->actingAs($receptionist, 'institute_user')
            ->get(route('finance.chart-of-accounts.index'))
            ->assertOk();

        $this->actingAs($receptionist, 'institute_user')
            ->get(route('finance.chart-of-accounts.create'))
            ->assertForbidden();

        $this->actingAs($receptionist, 'institute_user')
            ->post(route('finance.chart-of-accounts.store'), [
                'code' => '9999',
                'name' => 'No',
                'type' => 'expense',
            ])
            ->assertForbidden();
    }

    public function test_receptionist_can_create_journal_but_not_post_or_reverse(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $receptionist = $this->user($institute, 'receptionist', 'receptionist');

        $this->actingAs($receptionist, 'institute_user')
            ->get(route('finance.journals.create'))
            ->assertOk();

        $cash = ChartOfAccount::query()->where('institute_id', $institute->id)->where('code', '1001')->firstOrFail();
        $tuition = ChartOfAccount::query()->where('institute_id', $institute->id)->where('code', '4001')->firstOrFail();

        $this->actingAs($receptionist, 'institute_user')
            ->post(route('finance.journals.store'), [
                'journal_date' => now()->toDateString(),
                'type' => 'journal',
                'entries' => [
                    ['coa_id' => $cash->id, 'debit' => 10, 'credit' => 0],
                    ['coa_id' => $tuition->id, 'debit' => 0, 'credit' => 10],
                ],
            ])
            ->assertRedirect();

        $journal = Journal::query()->where('institute_id', $institute->id)->firstOrFail();
        $this->assertSame('posted', $journal->status);

        $this->actingAs($receptionist, 'institute_user')
            ->post(route('finance.journals.post', $journal))
            ->assertForbidden();

        $this->actingAs($receptionist, 'institute_user')
            ->post(route('finance.journals.reverse', $journal))
            ->assertForbidden();
    }

    public function test_accountant_can_manage_accounts_and_reports(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $accountant = $this->user($institute, 'accountant', 'accountant');

        $this->actingAs($accountant, 'institute_user')
            ->get(route('finance.dashboard'))
            ->assertOk();

        $this->actingAs($accountant, 'institute_user')
            ->get(route('finance.chart-of-accounts.index'))
            ->assertOk();

        $this->actingAs($accountant, 'institute_user')
            ->get(route('finance.reports.trial-balance'))
            ->assertOk();

        $this->actingAs($accountant, 'institute_user')
            ->get(route('finance.reports.income-statement'))
            ->assertOk();

        $this->actingAs($accountant, 'institute_user')
            ->get(route('finance.reports.balance-sheet'))
            ->assertOk();
    }

    public function test_branch_manager_has_view_but_not_manage(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $manager = $this->user($institute, 'branch-manager', 'manager');

        $this->actingAs($manager, 'institute_user')
            ->get(route('finance.dashboard'))
            ->assertOk();

        $this->actingAs($manager, 'institute_user')
            ->get(route('finance.chart-of-accounts.create'))
            ->assertForbidden();

        $this->actingAs($manager, 'institute_user')
            ->get(route('finance.parties.create'))
            ->assertForbidden();
    }

    // ------------------------------------------------------------ Tenant isolation

    public function test_cross_tenant_journal_and_account_are_not_visible(): void
    {
        $a = $this->institute('Inst A');
        $b = $this->institute('Inst B');
        $this->setupAccounting($a);
        $this->setupAccounting($b);
        $ownerA = $this->user($a, 'institute-owner', 'owner-a');
        $ownerB = $this->user($b, 'institute-owner', 'owner-b');

        $cash = ChartOfAccount::query()->where('institute_id', $a->id)->where('code', '1001')->firstOrFail();
        $tuition = ChartOfAccount::query()->where('institute_id', $a->id)->where('code', '4001')->firstOrFail();
        $journal = app(JournalPostingService::class)->create($this->journalPayload($a, null, $cash->id, $tuition->id, 10), (int) $ownerA->id);

        $account = ChartOfAccount::query()->where('institute_id', $a->id)->first();

        $this->actingAs($ownerB, 'institute_user')
            ->get(route('finance.journals.show', $journal))
            ->assertNotFound();

        $this->actingAs($ownerB, 'institute_user')
            ->get(route('finance.chart-of-accounts.edit', $account))
            ->assertNotFound();
    }

    public function test_branch_scoped_journal_is_not_seen_by_other_branch(): void
    {
        $institute = $this->institute();
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $this->setupAccounting($institute, $branchA);
        $this->setupAccounting($institute, $branchB);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $managerA = $this->user($institute, 'branch-manager', 'manager-a', $branchA);
        $managerB = $this->user($institute, 'branch-manager', 'manager-b', $branchB);

        $cash = ChartOfAccount::query()->where('institute_id', $institute->id)->where('branch_id', $branchA->id)->where('code', '1001')->firstOrFail();
        $tuition = ChartOfAccount::query()->where('institute_id', $institute->id)->where('branch_id', $branchA->id)->where('code', '4001')->firstOrFail();

        $journal = app(JournalPostingService::class)->create($this->journalPayload($institute, $branchA->id, $cash->id, $tuition->id, 10), (int) $owner->id);
        $this->assertSame((int) $journal->branch_id, (int) $branchA->id);

        $this->actingAs($managerA, 'institute_user')
            ->get(route('finance.journals.show', $journal))
            ->assertOk();

        $this->actingAs($managerB, 'institute_user')
            ->get(route('finance.journals.show', $journal))
            ->assertNotFound();
    }

    public function test_institute_wide_account_is_visible_to_branch_user(): void
    {
        $institute = $this->institute();
        $branchA = $this->branch($institute, 'Branch A');
        $this->setupAccounting($institute);
        $managerA = $this->user($institute, 'branch-manager', 'manager-a', $branchA);

        app(ChartOfAccountService::class)->createAccount($institute->id, null, [
            'code' => '6004',
            'name' => 'Institute Wide',
            'type' => 'expense',
        ], (int) $managerA->id);

        $this->actingAs($managerA, 'institute_user')
            ->get(route('finance.chart-of-accounts.index'))
            ->assertSee('Institute Wide');
    }

    public function test_party_created_for_branch_is_scoped_to_that_branch(): void
    {
        $institute = $this->institute();
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');

        $party = app(PartyService::class)->create($institute->id, (int) $branchA->id, [
            'type' => 'customer',
            'name' => 'Branch Customer',
            'phone' => '01721'.rand(100000, 999999),
        ], (int) $owner->id);

        $this->assertSame((int) $party->branch_id, (int) $branchA->id);
        $this->assertNotSame((int) $party->branch_id, (int) $branchB->id);
    }
}
