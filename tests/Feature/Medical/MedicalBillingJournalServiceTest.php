<?php

namespace Tests\Feature\Medical;

use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Country;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Journal;
use App\Models\Medical\Invoice;
use App\Models\Medical\Patient;
use App\Models\Medical\Payment;
use App\Models\Role;
use App\Models\Setting;
use App\Services\Medical\BillingService;
use App\Services\Medical\MedicalBillingJournalService;
use App\Services\MedicalModuleActivator;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class MedicalBillingJournalServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $country = Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );

        $this->institute = Institute::create([
            'name' => 'Test Hospital '.uniqid(),
            'slug' => 'test-hospital-'.uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'healthcare',
            'status' => 'active',
        ]);

        // Enable medical module
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical');

        $role = Role::where('slug', 'institute-owner')->firstOrFail();
        $this->owner = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'first_name' => 'Test',
            'last_name' => 'Owner',
            'email' => 'test-owner-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $this->patient = Patient::create([
            'institute_id' => $this->institute->id,
            'mr_number' => 'MR-'.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'phone' => '017'.rand(10000000, 99999999),
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
        ]);

        // Manually create required accounts for medical billing
        $this->createMedicalAccounts();

        $this->service = app(MedicalBillingJournalService::class);
    }

    protected function createMedicalAccounts(): void
    {
        // Create fiscal year first (required for journal posting)
        $this->createFiscalYear();

        // Create account groups if not exist
        $assetGroup = AccountGroup::firstOrCreate([
            'institute_id' => $this->institute->id,
            'code' => '1',
        ], [
            'name' => 'Assets',
            'category' => 'asset',
            'is_system' => true,
        ]);

        $liabilityGroup = AccountGroup::firstOrCreate([
            'institute_id' => $this->institute->id,
            'code' => '2',
        ], [
            'name' => 'Liabilities',
            'category' => 'liability',
            'is_system' => true,
        ]);

        $incomeGroup = AccountGroup::firstOrCreate([
            'institute_id' => $this->institute->id,
            'code' => '4',
        ], [
            'name' => 'Income',
            'category' => 'income',
            'is_system' => true,
        ]);

        // Create required accounts
        $accounts = [
            ['code' => '1000.1', 'name' => 'Cash in Hand', 'type' => 'asset', 'group' => $assetGroup, 'flags' => ['is_cash' => true]],
            ['code' => '1200.1', 'name' => 'Trade Receivable', 'type' => 'asset', 'group' => $assetGroup, 'flags' => ['is_receivable' => true, 'cash_flow_category' => 'operating']],
            ['code' => '1200.4', 'name' => 'Insurance / TPA Receivable', 'type' => 'asset', 'group' => $assetGroup, 'flags' => ['is_receivable' => true, 'cash_flow_category' => 'operating']],
            ['code' => '2100.1', 'name' => 'VAT Output Payable', 'type' => 'liability', 'group' => $liabilityGroup, 'flags' => ['cash_flow_category' => 'operating']],
            ['code' => '4300.1', 'name' => 'Consultation Fees', 'type' => 'income', 'group' => $incomeGroup, 'flags' => []],
            ['code' => '4300.2', 'name' => 'Diagnostic Fees', 'type' => 'income', 'group' => $incomeGroup, 'flags' => []],
            ['code' => '4300.3', 'name' => 'Pharmacy Sales', 'type' => 'income', 'group' => $incomeGroup, 'flags' => []],
            ['code' => '4300.4', 'name' => 'Surgery & IPD Room Revenue', 'type' => 'income', 'group' => $incomeGroup, 'flags' => []],
            ['code' => '4000.5', 'name' => 'Discount Allowed', 'type' => 'income', 'group' => $incomeGroup, 'flags' => []],
        ];

        foreach ($accounts as $acc) {
            ChartOfAccount::firstOrCreate(
                ['institute_id' => $this->institute->id, 'code' => $acc['code']],
                [
                    'branch_id' => null,
                    'name' => $acc['name'],
                    'type' => $acc['type'],
                    'account_group_id' => $acc['group']->id,
                    'is_system' => true,
                    'is_active' => true,
                    'is_postable' => true,
                ] + $acc['flags']
            );
        }
    }

    protected function createFiscalYear(): void
    {
        FiscalYear::firstOrCreate(
            ['institute_id' => $this->institute->id, 'branch_id' => null, 'is_current' => true],
            [
                'name' => 'FY '.date('Y'),
                'start_date' => date('Y-01-01'),
                'end_date' => date('Y-12-31'),
                'status' => 'open',
            ]
        );
    }

    public function test_service_instantiates_and_reads_settings(): void
    {
        // Activate medical module (seeds billing settings)
        app(MedicalModuleActivator::class)->activateForHealthcare($this->institute);

        // Verify settings are readable
        $revenueMode = Setting::get("medical.billing.revenue_mode.{$this->institute->id}");
        $autoPost = Setting::get("medical.billing.auto_post.{$this->institute->id}");

        $this->assertEquals('accrual', $revenueMode);
        $this->assertEquals('1', $autoPost);

        // Service should be instantiable
        $this->assertInstanceOf(MedicalBillingJournalService::class, $this->service);
    }

    public function test_post_salejournal_returns_null_on_cash_mode(): void
    {
        // Force cash mode
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'cash');
        Setting::set("medical.billing.auto_post.{$this->institute->id}", '1');

        $invoice = BillingService::computeTotals([
            ['amount' => 100, 'quantity' => 1, 'discount' => 0],
        ]);

        $medicalInvoice = Invoice::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'invoice_number' => 'TEST-'.uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'type' => 'opd',
            'subtotal' => $invoice['subtotal'],
            'tax' => $invoice['tax'],
            'discount' => $invoice['discount'],
            'total' => $invoice['total'],
            'paid_amount' => 0,
            'due_amount' => $invoice['total'],
            'status' => 'pending',
        ]);

        $result = $this->service->postSaleJournal($medicalInvoice, (int) $this->owner->id);
        $this->assertNull($result, 'Sale journal should be null in cash mode');
    }

    public function test_post_salejournal_creates_journal_in_accrual_mode(): void
    {
        // Ensure accrual mode
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'accrual');
        Setting::set("medical.billing.auto_post.{$this->institute->id}", '1');

        $invoice = BillingService::computeTotals([
            ['amount' => 100, 'quantity' => 1, 'discount' => 0],
        ]);

        $medicalInvoice = Invoice::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'invoice_number' => 'TEST-'.uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'type' => 'opd',
            'subtotal' => $invoice['subtotal'],
            'tax' => $invoice['tax'],
            'discount' => $invoice['discount'],
            'total' => $invoice['total'],
            'paid_amount' => 0,
            'due_amount' => $invoice['total'],
            'status' => 'pending',
        ]);

        $result = $this->service->postSaleJournal($medicalInvoice, (int) $this->owner->id);
        $this->assertNotNull($result, 'Sale journal should be created in accrual mode');
        $this->assertEquals('sale', $result->type);
        $this->assertEquals($medicalInvoice->id, $result->ref_id);
    }

    public function test_reverse_invoice_journals_reverses_sale_and_all_receipts(): void
    {
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'accrual');
        Setting::set("medical.billing.auto_post.{$this->institute->id}", '1');

        $medicalInvoice = Invoice::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'invoice_number' => 'TEST-'.uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'type' => 'opd',
            'subtotal' => 1000,
            'tax' => 0,
            'discount' => 0,
            'total' => 1000,
            'paid_amount' => 0,
            'due_amount' => 1000,
            'status' => 'pending',
        ]);

        $sale = $this->service->postSaleJournal($medicalInvoice, (int) $this->owner->id);
        $this->assertNotNull($sale);
        $this->assertEquals('posted', $sale->refresh()->status);

        $payments = [];
        foreach ([400, 600] as $amount) {
            $payment = Payment::create([
                'institute_id' => $this->institute->id,
                'invoice_id' => $medicalInvoice->id,
                'amount' => $amount,
                'payment_method' => 'cash',
                'paid_at' => now(),
            ]);
            $receipt = $this->service->postReceiptJournal($payment, (int) $this->owner->id);
            $this->assertNotNull($receipt);
            $this->assertEquals('posted', $receipt->refresh()->status);
            $payments[] = $payment->fresh();
        }

        $saleId = $medicalInvoice->fresh()->journal_id;
        $receiptIds = array_map(fn ($p) => $p->journal_id, $payments);
        $this->assertNotNull($saleId);
        $this->assertNotContains(null, $receiptIds);

        // a) + b) sale + ALL receipts reversed, c) second run is a no-op
        $this->service->reverseInvoiceJournals($medicalInvoice->fresh(), (int) $this->owner->id);

        $this->assertEquals('reversed', Journal::find($saleId)->status);
        foreach ($receiptIds as $receiptId) {
            $this->assertEquals('reversed', Journal::find($receiptId)->status);
        }

        // d) FK columns nulled
        $this->assertNull($medicalInvoice->fresh()->journal_id);
        foreach ($payments as $payment) {
            $this->assertNull($payment->fresh()->journal_id);
        }

        // c) idempotent second run — no exception, FKs stay null
        $this->service->reverseInvoiceJournals($medicalInvoice->fresh(), (int) $this->owner->id);
        $this->assertNull($medicalInvoice->fresh()->journal_id);
    }

    public function test_cash_mode_partial_payment_allocates_revenue_and_vat_correctly(): void
    {
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'cash');
        Setting::set("medical.billing.auto_post.{$this->institute->id}", '1');

        // subtotal=1000, discount=100, tax=50, total=950; payment=500
        $medicalInvoice = Invoice::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'invoice_number' => 'TEST-'.uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'type' => 'opd',
            'subtotal' => 1000,
            'tax' => 50,
            'discount' => 100,
            'total' => 950,
            'paid_amount' => 0,
            'due_amount' => 950,
            'status' => 'pending',
        ]);

        $payment = Payment::create([
            'institute_id' => $this->institute->id,
            'invoice_id' => $medicalInvoice->id,
            'amount' => 500,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);

        $journal = $this->service->postReceiptJournal($payment, (int) $this->owner->id);
        $this->assertNotNull($journal);
        $this->assertEquals('receipt', $journal->type);

        // Formula: taxOn = round(50 * 500/950, 2) = 26.32; revenue = 500 - 26.32 = 473.68
        $byCode = [];
        foreach ($journal->entries as $entry) {
            $code = ChartOfAccount::where('id', $entry->coa_id)->value('code');
            $byCode[$code] = $entry;
        }

        $this->assertEquals(500.0, round((float) $byCode['1000.1']->debit, 2));
        $this->assertEquals(473.68, round((float) $byCode['4300.1']->credit, 2));
        $this->assertEquals(26.32, round((float) $byCode['2100.1']->credit, 2));

        $debit = round($journal->entries->sum('debit'), 2);
        $credit = round($journal->entries->sum('credit'), 2);
        $this->assertEquals($debit, $credit);
    }

    public function test_income_account_mapping_covers_all_five_invoice_types(): void
    {
        $instituteId = $this->institute->id;

        $this->assertEquals('4300.1', $this->service->incomeAccountFor('opd', $instituteId)->code);
        $this->assertEquals('4300.2', $this->service->incomeAccountFor('lab', $instituteId)->code);
        $this->assertEquals('4300.3', $this->service->incomeAccountFor('pharmacy', $instituteId)->code);

        // 4300.4 in CoaTemplate registry since Task 3.5 — direct hits.
        $this->assertEquals('4300.4', $this->service->incomeAccountFor('ipd', $instituteId)->code);
        $this->assertEquals('4300.4', $this->service->incomeAccountFor('surgery', $instituteId)->code);

        // Invoice-model path resolves the same way
        $invoice = new Invoice(['institute_id' => $instituteId, 'type' => 'lab']);
        $this->assertEquals('4300.2', $this->service->incomeAccountFor($invoice)->code);
    }

    public function test_tpa_receipt_journal_debits_insurance_receivable_not_cash(): void
    {
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'accrual');
        Setting::set("medical.billing.auto_post.{$this->institute->id}", '1');

        $medicalInvoice = Invoice::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'invoice_number' => 'TEST-'.uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'type' => 'opd',
            'subtotal' => 1000,
            'tax' => 0,
            'discount' => 0,
            'total' => 1000,
            'paid_amount' => 0,
            'due_amount' => 1000,
            'status' => 'pending',
        ]);

        // Option 4: receipts follow the INVOICE's mode — a genuine accrual
        // invoice must carry its sale journal or the receipt posts cash-style.
        $this->assertNotNull($this->service->postSaleJournal($medicalInvoice, (int) $this->owner->id));
        $this->assertNotNull($medicalInvoice->fresh()->journal_id);

        $payment = Payment::create([
            'institute_id' => $this->institute->id,
            'invoice_id' => $medicalInvoice->id,
            'amount' => 600,
            'payment_method' => 'tpa',
            'paid_at' => now(),
        ]);

        $journal = $this->service->postReceiptJournal($payment, (int) $this->owner->id);
        $this->assertNotNull($journal);

        $byCode = [];
        foreach ($journal->entries as $entry) {
            $byCode[ChartOfAccount::where('id', $entry->coa_id)->value('code')] = $entry;
        }

        $this->assertArrayNotHasKey('1000.1', $byCode, 'TPA must not touch cash');
        $this->assertEquals(600.0, round((float) $byCode['1200.4']->debit, 2));
        $this->assertEquals(600.0, round((float) $byCode['1200.1']->credit, 2));

        // Cash payments still debit cash
        $cashPayment = Payment::create([
            'institute_id' => $this->institute->id,
            'invoice_id' => $medicalInvoice->id,
            'amount' => 100,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);
        $cashJournal = $this->service->postReceiptJournal($cashPayment, (int) $this->owner->id);
        $cashByCode = [];
        foreach ($cashJournal->entries as $entry) {
            $cashByCode[ChartOfAccount::where('id', $entry->coa_id)->value('code')] = $entry;
        }
        $this->assertEquals(100.0, round((float) $cashByCode['1000.1']->debit, 2));
    }

    public function test_fail_soft_when_tenant_has_no_income_account(): void
    {
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'accrual');

        ChartOfAccount::where('institute_id', $this->institute->id)
            ->where('type', 'income')
            ->delete();

        $medicalInvoice = Invoice::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'invoice_number' => 'TEST-'.uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'type' => 'opd',
            'subtotal' => 100,
            'tax' => 0,
            'discount' => 0,
            'total' => 100,
            'paid_amount' => 0,
            'due_amount' => 100,
            'status' => 'pending',
        ]);

        $this->assertNull($this->service->postSaleJournal($medicalInvoice, (int) $this->owner->id));
        $this->assertNull($medicalInvoice->fresh()->journal_id);
    }

    public function test_fail_soft_when_tenant_has_no_receivable_account(): void
    {
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'accrual');

        ChartOfAccount::where('institute_id', $this->institute->id)
            ->where('code', '1200.1')
            ->delete();

        $medicalInvoice = Invoice::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'invoice_number' => 'TEST-'.uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'type' => 'opd',
            'subtotal' => 100,
            'tax' => 0,
            'discount' => 0,
            'total' => 100,
            'paid_amount' => 0,
            'due_amount' => 100,
            'status' => 'pending',
        ]);

        $this->assertNull($this->service->postSaleJournal($medicalInvoice, (int) $this->owner->id));
        $this->assertNull($medicalInvoice->fresh()->journal_id);
    }
}
