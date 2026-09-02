<?php

namespace Tests\Feature;

use App\Models\AccountingAuditTrail;
use App\Models\AccountingPeriod;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\CashMemo;
use App\Models\ChartOfAccount;
use App\Models\Country;
use App\Models\Course;
use App\Models\Currency;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\InstituteCourse;
use App\Models\InstituteUser;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\OfflineSyncQueue;
use App\Models\Party;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\Accounting\AccountingPeriodService;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\FinancialReportService;
use App\Services\Accounting\InvoiceService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\PartyService;
use App\Services\Accounting\PaymentService;
use App\Services\Accounting\ReceivablesPayablesService;
use App\Services\Education\FeeHeadService;
use App\Services\Education\FeeStructureService;
use App\Services\Education\StudentFinanceService;
use App\Services\OfflineSyncService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * STEP 14 — Accounting Integration & Reconciliation.
 *
 * Cross-cutting integration between the business modules (invoices, payments,
 * cash memos / offline sync, education waivers) and the double-entry engine,
 * plus ledger-level reconciliation, reversal integrity, fiscal-period locking,
 * tenant/branch isolation, duplicate-posting and immutability guarantees.
 *
 * The education finance layer (StudentFinanceService) is exercised for the
 * waiver → sale-journal rebuild path; the offline-sync cash memo path for the
 * receipt-journal integration. Purchase/expense accounting does not exist yet
 * and is intentionally not fabricated here.
 */
class AccountingIntegrationTest extends TestCase
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

    private function institute(string $name = 'Integration Inst'): Institute
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

    private function autoPost(Institute $institute, ?Branch $branch = null): void
    {
        app(AccountingSetupService::class)->setSetting($institute->id, 'invoice_auto_post', true, $branch?->id);
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

    private function party(Institute $institute, int $actorId, array $overrides = [], ?Branch $branch = null): Party
    {
        return app(PartyService::class)->create($institute->id, $branch?->id, array_merge([
            'type' => 'customer',
            'name' => 'Integration Customer',
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

    private function invoice(Institute $institute, int $partyId, int $actorId, array $overrides = [], ?Branch $branch = null): Invoice
    {
        return app(InvoiceService::class)->create($institute->id, $branch?->id, $this->invoiceData($partyId, $overrides), $actorId);
    }

    private function coaId(Institute $institute, string $code, ?Branch $branch = null): ?int
    {
        return ChartOfAccount::query()
            ->where('institute_id', $institute->id)
            ->when($branch !== null, fn ($query) => $query->where('branch_id', $branch->id))
            ->where('code', $code)
            ->value('id');
    }

    private function currencyId(string $code = 'BDT'): int
    {
        return (int) (Currency::query()->where('code', $code)->value('id') ?? Currency::query()->orderBy('code')->value('id'));
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

    private function course(Institute $institute, string $name): Course
    {
        $course = Course::create([
            'course_code' => 'C'.mt_rand(1000, 9999),
            'name' => $name,
        ]);

        InstituteCourse::create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
        ]);

        return $course;
    }

    private function feeHead(Institute $institute, Branch $branch, string $type, string $name): FeeHead
    {
        return app(FeeHeadService::class)->create(
            $institute->id,
            $branch->id,
            ['type' => $type, 'name' => $name, 'description' => 'Test head'],
            null,
        );
    }

    private function batch(Institute $institute, Branch $branch, Course $course, string $name): Batch
    {
        return Batch::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'name' => $name,
            'batch_code' => 'B'.mt_rand(1000, 9999),
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'ongoing',
        ]);
    }

    private function student(Institute $institute, Branch $branch, string $name): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'student_id_number' => 'ST'.mt_rand(100000, 999999),
            'first_name' => $name,
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => '2026-01-01',
        ]);
    }

    private function enroll(Student $student, Batch $batch): StudentEnrollment
    {
        return StudentEnrollment::create([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'course_id' => $batch->course_id,
            'enrollment_date' => '2026-01-05',
            'roll_number' => 'R'.mt_rand(100, 999),
            'status' => 'active',
        ]);
    }

    private function structure(Institute $institute, Branch $branch, Course $course, array $items, array $extra = []): FeeStructure
    {
        return app(FeeStructureService::class)->create($institute->id, $branch->id, array_merge([
            'name' => 'Structure '.uniqid(),
            'academic_year_id' => null,
            'course_id' => $course->id,
            'batch_id' => null,
            'installments_count' => 1,
            'installments_interval_days' => 30,
            'status' => 'active',
            'items' => $items,
        ], $extra), null);
    }

    private function queue(Institute $institute, InstituteUser $creator, array $payload = []): OfflineSyncQueue
    {
        return OfflineSyncQueue::query()->withoutGlobalScopes()->create([
            'client_uuid' => Str::uuid()->toString(),
            'entity_type' => 'cash_memo',
            'institute_id' => $institute->id,
            'created_by' => $creator->id,
            'payload' => array_merge([
                'amount' => 200,
                'description' => 'Offline collection',
                'payment_method' => 'cash',
            ], $payload),
            'created_offline_at' => now(),
            'status' => 'pending_review',
        ]);
    }

    private function materializeCashMemo(Institute $institute, InstituteUser $reviewer, array $payload = []): CashMemo
    {
        return app(OfflineSyncService::class)->materialize($this->queue($institute, $reviewer, $payload), $reviewer);
    }

    private function entryTotals(Journal $journal): array
    {
        $rows = $journal->entries()->get();

        return [
            'debit' => round($rows->sum('debit'), 4),
            'credit' => round($rows->sum('credit'), 4),
            'count' => $rows->count(),
        ];
    }

    // ------------------------------------------------- Sales → journal

    public function test_invoice_posts_balanced_sale_journal_to_ar_and_income(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $this->autoPost($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);

        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);
        $journal = $invoice->journal;

        $this->assertNotNull($journal);
        $this->assertSame('sale', $journal->type);
        $this->assertSame('posted', $journal->status);

        $totals = $this->entryTotals($journal);
        $this->assertSame($totals['debit'], $totals['credit'], 'Sale journal must balance.');
        $this->assertGreaterThanOrEqual(2, $totals['count']);

        $arDebit = $journal->entries()->where('coa_id', $this->coaId($institute, '1100'))->sum('debit');
        $this->assertSame(150.0, round((float) $arDebit, 4));

        $arEntry = $journal->entries()->where('coa_id', $this->coaId($institute, '1100'))->first();
        $this->assertSame((int) $customer->id, (int) $arEntry->party_id);
    }

    public function test_draft_sale_journal_flows_to_ledger_after_explicit_post(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);

        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);
        $this->assertSame('draft', $invoice->journal->status);

        app(InvoiceService::class)->postJournal($invoice, $institute->id, null, (int) $owner->id);
        $this->assertSame('posted', $invoice->refresh()->journal->status);

        $statement = app(FinancialReportService::class)->incomeStatement($institute->id, null);
        $this->assertSame(150.0, round($statement['total_income'], 4));
    }

    // ------------------------------------------------- Payments → journal

    public function test_payment_receipt_journal_maps_cash_and_bank_methods(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $this->autoPost($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);
        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);

        $cashPayment = app(PaymentService::class)->record($institute->id, null, [
            'invoice_id' => $invoice->id,
            'amount' => 50,
            'payment_method' => 'cash',
        ], (int) $owner->id);

        $bankPayment = app(PaymentService::class)->record($institute->id, null, [
            'invoice_id' => $invoice->id,
            'amount' => 50,
            'payment_method' => 'bank',
        ], (int) $owner->id);

        $cashDebit = $cashPayment->journal->entries()->where('coa_id', $this->coaId($institute, '1001'))->sum('debit');
        $bankDebit = $bankPayment->journal->entries()->where('coa_id', $this->coaId($institute, '1002'))->sum('debit');
        $arCredit = $bankPayment->journal->entries()->where('coa_id', $this->coaId($institute, '1100'))->sum('credit');

        $this->assertSame(50.0, round((float) $cashDebit, 4));
        $this->assertSame(50.0, round((float) $bankDebit, 4));
        $this->assertSame(50.0, round((float) $arCredit, 4));
        $this->assertSame('receipt', $cashPayment->journal->type);
    }

    public function test_full_payment_clears_ar_and_income_statement_keeps_sale(): void
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

        $this->assertSame(0.0, round(app(ReceivablesPayablesService::class)->partyBalance($customer)['receivable'], 4));
        $this->assertSame(150.0, round(app(FinancialReportService::class)->incomeStatement($institute->id, null)['net'], 4));
        $this->assertSame(150.0, round((float) app(FinancialReportService::class)->cashBankSummary($institute->id, null)->firstWhere('code', '1001')->balance, 4));
    }

    public function test_partial_payment_reduces_ar_only(): void
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

        $this->assertSame(100.0, round(app(ReceivablesPayablesService::class)->partyBalance($customer)['receivable'], 4));
        $sheet = app(FinancialReportService::class)->balanceSheet($institute->id, null);
        $this->assertSame(100.0, round((float) $sheet['assets']->firstWhere('code', '1100')->balance, 4));
        $this->assertSame(150.0, round($sheet['net_income'], 4));
    }

    public function test_overpayment_is_rejected_without_ledger_effect(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $this->autoPost($institute);
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
        $this->assertSame(1, Journal::query()->where('institute_id', $institute->id)->count());
        $this->assertSame(150.0, round(app(FinancialReportService::class)->incomeStatement($institute->id, null)['total_income'], 4));
    }

    public function test_payment_reversal_restores_invoice_and_nets_reports(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $this->autoPost($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);
        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);

        $payment = app(PaymentService::class)->record($institute->id, null, [
            'invoice_id' => $invoice->id,
            'amount' => 150,
            'payment_method' => 'cash',
        ], (int) $owner->id);

        app(PaymentService::class)->reverse($payment, $institute->id, (int) $owner->id, 'Integration reversal');

        $invoice->refresh();
        $this->assertSame(150.0, round((float) $invoice->due_amount, 4));
        $this->assertSame('unpaid', $invoice->status);
        $this->assertSame('reversed', $payment->refresh()->journal->status);
        $this->assertSame(0.0, round((float) app(FinancialReportService::class)->cashBankSummary($institute->id, null)->firstWhere('code', '1001')->balance, 4));
        $this->assertSame(150.0, round(app(FinancialReportService::class)->incomeStatement($institute->id, null)['net'], 4));

        $rows = app(FinancialReportService::class)->trialBalance($institute->id, null);
        $this->assertSame(round($rows->sum('debit'), 4), round($rows->sum('credit'), 4));
    }

    // ------------------------------------------------- Invoice lifecycle

    public function test_cancelled_posted_invoice_reverses_sale_and_nets_income(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $this->autoPost($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);
        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);

        app(InvoiceService::class)->cancel($invoice, $institute->id, (int) $owner->id);

        $this->assertSame('cancelled', $invoice->refresh()->status);
        $this->assertSame('reversed', $invoice->journal->status);
        $this->assertNotNull($invoice->journal->reversals()->first());
        $this->assertSame('posted', $invoice->journal->reversals()->first()->status);
        $this->assertSame(0.0, round(app(FinancialReportService::class)->incomeStatement($institute->id, null)['total_income'], 4));
        $this->assertSame(0.0, round(app(ReceivablesPayablesService::class)->totals($institute->id)['receivable'], 4));
    }

    public function test_cancelled_draft_invoice_voids_the_sale_journal(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);
        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);

        $this->assertSame('draft', $invoice->journal->status);
        app(InvoiceService::class)->cancel($invoice, $institute->id, (int) $owner->id);

        $this->assertSame('cancelled', $invoice->refresh()->status);
        $this->assertSame('void', $invoice->journal->refresh()->status);
        $this->assertTrue(app(FinancialReportService::class)->trialBalance($institute->id, null)->isEmpty());
    }

    public function test_invoice_with_payments_cannot_be_cancelled(): void
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

        try {
            app(InvoiceService::class)->cancel($invoice->fresh(), $institute->id, (int) $owner->id);
            $this->fail('Invoices with payments must not be cancellable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice', $exception->errors());
        }

        $this->assertNotSame('cancelled', $invoice->refresh()->status);
        $this->assertSame('posted', $invoice->journal->status);
    }

    // ------------------------------------------------- Waiver (education)

    public function test_waiver_reverses_and_rebuilds_sale_journal_at_new_payable(): void
    {
        $institute = $this->institute('Waiver Institute');
        $branch = $this->branch($institute, 'Main');
        $this->setupAccounting($institute, $branch);
        $this->autoPost($institute, $branch);
        $owner = $this->user($institute, 'institute-owner', 'owner');

        $course = $this->course($institute, 'Accounting Course');
        $head = $this->feeHead($institute, $branch, 'course_tuition', 'Tuition Fee');
        $batch = $this->batch($institute, $branch, $course, 'Batch A1');
        $student = $this->student($institute, $branch, 'Rahim');
        $enrollment = $this->enroll($student, $batch);

        $structure = $this->structure($institute, $branch, $course, [
            ['fee_head_id' => $head->id, 'amount' => 150],
        ]);

        $invoice = app(StudentFinanceService::class)->generateInvoice(
            $enrollment,
            $structure,
            ['due_date' => now()->addDays(30)->toDateString()],
            (int) $owner->id,
        );

        $waived = app(StudentFinanceService::class)->applyWaiver($invoice, 30, 'Scholarship', $institute->id, (int) $owner->id);

        $this->assertSame(120.0, round((float) $waived->payable_amount, 4));
        $this->assertSame(120.0, round((float) $waived->due_amount, 4));

        $oldJournal = $invoice->journal;
        $this->assertSame('reversed', $oldJournal->refresh()->status);
        $this->assertNotSame($oldJournal->id, $waived->journal_id);
        $this->assertSame('posted', $waived->journal->status);

        $this->assertSame(120.0, round(app(FinancialReportService::class)->incomeStatement($institute->id, $branch->id)['net'], 4));
        $this->assertSame(120.0, round(app(ReceivablesPayablesService::class)->totals($institute->id, $branch->id)['receivable'], 4));
    }

    // ------------------------------------------------- Cash memos (offline sync)

    public function test_cash_memo_materialization_posts_receipt_journal(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $reviewer = $this->user($institute, 'institute-owner', 'owner');

        $memo = $this->materializeCashMemo($institute, $reviewer, ['amount' => 200]);

        $this->assertNotNull($memo->journal_id);
        $journal = $memo->journal;
        $this->assertSame('receipt', $journal->type);
        $this->assertSame('posted', $journal->status);
        $this->assertSame('cash_memo', $journal->ref_type);
        $this->assertSame((int) $memo->id, (int) $journal->ref_id);

        $totals = $this->entryTotals($journal);
        $this->assertSame($totals['debit'], $totals['credit'], 'Cash memo journal must balance.');

        $this->assertSame(200.0, round((float) $journal->entries()->where('coa_id', $this->coaId($institute, '1001'))->sum('debit'), 4));
        $this->assertSame(200.0, round((float) $journal->entries()->where('coa_id', $this->coaId($institute, '4004'))->sum('credit'), 4));

        $this->assertSame(200.0, round(app(FinancialReportService::class)->incomeStatement($institute->id, null)['total_income'], 4));
        $this->assertSame(200.0, round((float) app(FinancialReportService::class)->cashBankSummary($institute->id, null)->firstWhere('code', '1001')->balance, 4));
    }

    public function test_cash_memo_bank_method_debits_bank_account(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $reviewer = $this->user($institute, 'institute-owner', 'owner');

        $memo = $this->materializeCashMemo($institute, $reviewer, ['amount' => 250, 'payment_method' => 'bank']);

        $this->assertNotNull($memo->journal_id);
        $this->assertSame(250.0, round((float) $memo->journal->entries()->where('coa_id', $this->coaId($institute, '1002'))->sum('debit'), 4));
    }

    public function test_cash_memo_without_accounting_stays_legacy_memo_only(): void
    {
        $institute = $this->institute();
        $reviewer = $this->user($institute, 'institute-owner', 'owner');

        $memo = $this->materializeCashMemo($institute, $reviewer, ['amount' => 300]);

        $this->assertNotNull($memo->id);
        $this->assertNull($memo->journal_id);
        $this->assertSame(0, Journal::query()->where('institute_id', $institute->id)->count());
    }

    // ------------------------------------------------- Fiscal period lock

    public function test_closed_period_blocks_new_payment_postings(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $this->autoPost($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);
        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);

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

        try {
            app(PaymentService::class)->record($institute->id, null, [
                'invoice_id' => $invoice->id,
                'amount' => 50,
                'payment_method' => 'cash',
            ], (int) $owner->id);
            $this->fail('Payments into a closed period should be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('period_id', $exception->errors());
        }

        $this->assertSame(0, $invoice->refresh()->payments()->count());
    }

    // ------------------------------------------------- Isolation

    public function test_ledger_is_isolated_between_tenants(): void
    {
        $a = $this->institute('Tenant A');
        $b = $this->institute('Tenant B');
        $this->setupAccounting($a);
        $this->setupAccounting($b);
        $this->autoPost($a);
        $ownerA = $this->user($a, 'institute-owner', 'owner-a');
        $customerA = $this->party($a, (int) $ownerA->id);

        $invoice = $this->invoice($a, $customerA->id, (int) $ownerA->id);
        app(PaymentService::class)->record($a->id, null, [
            'invoice_id' => $invoice->id,
            'amount' => 150,
            'payment_method' => 'cash',
        ], (int) $ownerA->id);

        $this->assertTrue(app(FinancialReportService::class)->trialBalance($b->id, null)->isEmpty());
        $this->assertSame(0, DB::table('journal_entries')->where('institute_id', $b->id)->count());
        $this->assertSame(2, DB::table('journals')->where('institute_id', $a->id)->count());
    }

    public function test_branch_reports_exclude_other_branch_postings(): void
    {
        $institute = $this->institute();
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $this->setupAccounting($institute, $branchA);
        $this->setupAccounting($institute, $branchB);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id, [], $branchA);
        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id, [], $branchA);

        app(PaymentService::class)->record($institute->id, $branchA->id, [
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'payment_method' => 'cash',
        ], (int) $owner->id);

        $branchATotals = app(FinancialReportService::class)->trialBalance($institute->id, $branchA->id);
        $this->assertFalse($branchATotals->isEmpty());
        $this->assertSame(100.0, round((float) $branchATotals->sum('debit'), 4));
        $this->assertSame(100.0, round((float) $branchATotals->sum('credit'), 4));

        $this->assertTrue(app(FinancialReportService::class)->trialBalance($institute->id, $branchB->id)->isEmpty());
    }

    // ------------------------------------------------- Reconciliation

    public function test_full_cycle_reconciliation_balances_every_report(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $this->autoPost($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);

        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);
        app(PaymentService::class)->record($institute->id, null, [
            'invoice_id' => $invoice->id,
            'amount' => 100,
            'payment_method' => 'cash',
        ], (int) $owner->id);
        $this->materializeCashMemo($institute, $owner, ['amount' => 200]);

        $tb = app(FinancialReportService::class)->trialBalance($institute->id, null);
        $this->assertSame(round($tb->sum('debit'), 4), round($tb->sum('credit'), 4), 'Trial balance must balance.');
        $this->assertGreaterThan(0, $tb->sum('debit'));

        $pl = app(FinancialReportService::class)->incomeStatement($institute->id, null);
        $this->assertSame(350.0, round($pl['total_income'], 4));
        $this->assertSame(0.0, round($pl['total_expense'], 4));
        $this->assertSame(350.0, round($pl['net'], 4));

        $sheet = app(FinancialReportService::class)->balanceSheet($institute->id, null);
        $this->assertSame(300.0, round((float) $sheet['assets']->firstWhere('code', '1001')->balance, 4));
        $this->assertSame(50.0, round((float) $sheet['assets']->firstWhere('code', '1100')->balance, 4));
        $this->assertSame(round($sheet['total_assets'], 4), round($sheet['total_liabilities'] + $sheet['total_equity'], 4), 'Assets must equal liabilities plus equity.');
        $this->assertSame(350.0, round($sheet['total_assets'], 4));

        $arpTotals = app(ReceivablesPayablesService::class)->totals($institute->id);
        $this->assertSame(50.0, round($arpTotals['receivable'], 4));
        $this->assertSame(50.0, round(app(ReceivablesPayablesService::class)->partyBalance($customer)['receivable'], 4));
    }

    // ------------------------------------------------- Reversal integrity

    public function test_reversal_integrity_nets_the_ledger_to_zero(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');

        $cash = ChartOfAccount::query()->where('institute_id', $institute->id)->where('code', '1001')->firstOrFail();
        $tuition = ChartOfAccount::query()->where('institute_id', $institute->id)->where('code', '4001')->firstOrFail();

        $journal = app(JournalPostingService::class)->create($this->journalPayload($institute, null, $cash->id, $tuition->id, 100), (int) $owner->id);
        $reversal = app(JournalPostingService::class)->reverse($journal, (int) $institute->id, (int) $owner->id, 'Correction');

        $this->assertSame('reversed', $journal->refresh()->status);
        $this->assertSame('posted', $reversal->status);
        $this->assertSame((int) $journal->id, (int) $reversal->reversal_of);
        $this->assertSame(100.0, round((float) $reversal->entries()->where('coa_id', $tuition->id)->sum('debit'), 4));
        $this->assertSame(100.0, round((float) $reversal->entries()->where('coa_id', $cash->id)->sum('credit'), 4));

        $totals = $this->entryTotals($reversal);
        $this->assertSame($totals['debit'], $totals['credit'], 'Reversal journal must balance.');

        $this->assertSame(0.0, round(app(FinancialReportService::class)->incomeStatement($institute->id, null)['total_income'], 4));
        $this->assertSame(0.0, round((float) app(FinancialReportService::class)->cashBankSummary($institute->id, null)->firstWhere('code', '1001')->balance, 4));
    }

    // ------------------------------------------------- Duplicate posting

    public function test_duplicate_payment_attempt_does_not_double_post(): void
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

        try {
            app(PaymentService::class)->record($institute->id, null, [
                'invoice_id' => $invoice->id,
                'amount' => 150,
                'payment_method' => 'cash',
            ], (int) $owner->id);
            $this->fail('A second payment on a paid invoice must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('invoice_id', $exception->errors());
        }

        $this->assertSame(1, $invoice->refresh()->payments()->count());
        $this->assertSame(2, Journal::query()->where('institute_id', $institute->id)->count());
        $this->assertSame(150.0, round((float) app(FinancialReportService::class)->cashBankSummary($institute->id, null)->firstWhere('code', '1001')->balance, 4));
    }

    // ------------------------------------------------- Immutability

    public function test_posted_journal_cannot_be_reposted_voided_or_reversed_twice(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');

        $cash = ChartOfAccount::query()->where('institute_id', $institute->id)->where('code', '1001')->firstOrFail();
        $tuition = ChartOfAccount::query()->where('institute_id', $institute->id)->where('code', '4001')->firstOrFail();

        $journal = app(JournalPostingService::class)->create($this->journalPayload($institute, null, $cash->id, $tuition->id, 50), (int) $owner->id);
        $this->assertSame('posted', $journal->status);

        try {
            app(JournalPostingService::class)->post($journal, (int) $institute->id, (int) $owner->id);
            $this->fail('A posted journal must not be reposted.');
        } catch (\LogicException) {
        }

        try {
            app(JournalPostingService::class)->void($journal, (int) $institute->id, (int) $owner->id);
            $this->fail('A posted journal must not be voided.');
        } catch (\LogicException) {
        }

        $reversal = app(JournalPostingService::class)->reverse($journal, (int) $institute->id, (int) $owner->id);

        try {
            app(JournalPostingService::class)->reverse($journal, (int) $institute->id, (int) $owner->id);
            $this->fail('A reversed journal must not be reversed again.');
        } catch (\LogicException) {
        }

        try {
            app(JournalPostingService::class)->reverse($reversal, (int) $institute->id, (int) $owner->id);
            $this->fail('A reversal journal must not itself be reversed.');
        } catch (\LogicException) {
        }
    }

    // ------------------------------------------------- Audit trail

    public function test_audit_trail_covers_the_full_financial_lifecycle(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $this->autoPost($institute);
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);

        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);
        $payment = app(PaymentService::class)->record($institute->id, null, [
            'invoice_id' => $invoice->id,
            'amount' => 150,
            'payment_method' => 'cash',
        ], (int) $owner->id);
        app(PaymentService::class)->reverse($payment, $institute->id, (int) $owner->id, 'Audit reversal');
        $this->materializeCashMemo($institute, $owner, ['amount' => 100]);

        $audits = AccountingAuditTrail::query()
            ->where('institute_id', $institute->id)
            ->get(['action', 'entity_type']);

        $pairs = $audits->map(fn ($row) => $row->action.'|'.$row->entity_type)->all();

        $this->assertContains('create|invoice', $pairs);
        $this->assertContains('create|journal', $pairs);
        $this->assertContains('post|journal', $pairs);
        $this->assertContains('create|payment', $pairs);
        $this->assertContains('reverse|payment', $pairs);
        $this->assertContains('reverse|journal', $pairs);
        $this->assertContains('create|cash_memo', $pairs);
    }

    // ------------------------------------------------- Configuration

    public function test_journal_currency_comes_from_accounting_setting_not_hardcode(): void
    {
        $institute = $this->institute();
        $this->setupAccounting($institute);
        $this->autoPost($institute);
        app(AccountingSetupService::class)->setSetting($institute->id, 'base_currency', 'USD');
        $owner = $this->user($institute, 'institute-owner', 'owner');
        $customer = $this->party($institute, (int) $owner->id);

        $usdId = $this->currencyId('USD');
        $this->assertSame($usdId, (int) (Currency::query()->where('code', 'USD')->value('id')));

        $invoice = $this->invoice($institute, $customer->id, (int) $owner->id);
        $this->assertSame($usdId, (int) $invoice->journal->currency_id);

        $memo = $this->materializeCashMemo($institute, $owner, ['amount' => 75]);
        $this->assertSame($usdId, (int) $memo->journal->currency_id);
    }
}
