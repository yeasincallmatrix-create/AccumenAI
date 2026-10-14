<?php

namespace Tests\Feature\Medical;

use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Country;
use App\Models\Institute;
use App\Models\Journal;
use App\Models\Medical\Payment;
use App\Models\Role;
use App\Models\Setting;
use App\Services\Medical\BillingService;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BillingServiceTest extends TestCase
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
            'name' => 'Test Hospital ' . uniqid(),
            'slug' => 'test-hospital-' . uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'healthcare',
            'status' => 'active',
        ]);

        app(ModuleAccessService::class)->enableModule($this->institute, 'medical');

        $role = Role::where('slug', 'institute-owner')->firstOrFail();
        $this->owner = \App\Models\InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'first_name' => 'Test',
            'last_name' => 'Owner',
            'email' => 'test-owner-' . uniqid() . '@example.test',
            'phone' => '01700' . rand(100000, 999999),
            'password_hash' => bcrypt('secret12345'),
            'status' => 'active',
        ]);

        $this->patient = \App\Models\Medical\Patient::create([
            'institute_id' => $this->institute->id,
            'mr_number' => 'MR-' . uniqid(),
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'phone' => '017' . rand(10000000, 99999999),
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
        ]);

        $this->createMedicalAccounts();

        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'accrual');
        Setting::set("medical.billing.auto_post.{$this->institute->id}", '1');

        $this->billing = app(BillingService::class);
    }

    protected function createMedicalAccounts(): void
    {
        \App\Models\FiscalYear::firstOrCreate(
            ['institute_id' => $this->institute->id, 'branch_id' => null, 'is_current' => true],
            [
                'name' => 'FY ' . date('Y'),
                'start_date' => date('Y-01-01'),
                'end_date' => date('Y-12-31'),
                'status' => 'open',
            ]
        );

        $assetGroup = AccountGroup::firstOrCreate([
            'institute_id' => $this->institute->id,
            'code' => '1',
        ], ['name' => 'Assets', 'category' => 'asset', 'is_system' => true]);

        $liabilityGroup = AccountGroup::firstOrCreate([
            'institute_id' => $this->institute->id,
            'code' => '2',
        ], ['name' => 'Liabilities', 'category' => 'liability', 'is_system' => true]);

        $incomeGroup = AccountGroup::firstOrCreate([
            'institute_id' => $this->institute->id,
            'code' => '4',
        ], ['name' => 'Income', 'category' => 'income', 'is_system' => true]);

        foreach ([
            ['code' => '1000.1', 'name' => 'Cash in Hand', 'type' => 'asset', 'group' => $assetGroup, 'flags' => ['is_cash' => true]],
            ['code' => '1200.1', 'name' => 'Trade Receivable', 'type' => 'asset', 'group' => $assetGroup, 'flags' => ['is_receivable' => true, 'cash_flow_category' => 'operating']],
            ['code' => '1200.4', 'name' => 'Insurance / TPA Receivable', 'type' => 'asset', 'group' => $assetGroup, 'flags' => ['is_receivable' => true, 'cash_flow_category' => 'operating']],
            ['code' => '2100.1', 'name' => 'VAT Output Payable', 'type' => 'liability', 'group' => $liabilityGroup, 'flags' => ['cash_flow_category' => 'operating']],
            ['code' => '4300.1', 'name' => 'Consultation Fees', 'type' => 'income', 'group' => $incomeGroup, 'flags' => []],
            ['code' => '4300.2', 'name' => 'Diagnostic Fees', 'type' => 'income', 'group' => $incomeGroup, 'flags' => []],
            ['code' => '4300.3', 'name' => 'Pharmacy Sales', 'type' => 'income', 'group' => $incomeGroup, 'flags' => []],
            ['code' => '4300.4', 'name' => 'Surgery & IPD Room Revenue', 'type' => 'income', 'group' => $incomeGroup, 'flags' => []],
            ['code' => '4000.5', 'name' => 'Discount Allowed', 'type' => 'income', 'group' => $incomeGroup, 'flags' => []],
        ] as $acc) {
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

    public function test_create_invoice_posts_sale_journal_in_accrual_mode(): void
    {
        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Consultation', 'amount' => 500, 'quantity' => 1, 'discount' => 0],
        ]);

        $this->assertEquals(500.0, (float) $invoice->total);
        $this->assertNotNull($invoice->journal_id, 'Accrual invoice must carry its sale journal');

        $journal = Journal::find($invoice->journal_id);
        $this->assertEquals('sale', $journal->type);
        $this->assertEquals('posted', $journal->status);

        $byCode = [];
        foreach ($journal->entries as $entry) {
            $byCode[ChartOfAccount::where('id', $entry->coa_id)->value('code')] = $entry;
        }
        $this->assertEquals(500.0, round((float) $byCode['1200.1']->debit, 2));
        $this->assertEquals(500.0, round((float) $byCode['4300.1']->credit, 2));
    }

    public function test_create_invoice_skips_journal_in_cash_mode(): void
    {
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'cash');

        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Consultation', 'amount' => 500, 'quantity' => 1, 'discount' => 0],
        ]);

        $this->assertEquals('pending', $invoice->status);
        $this->assertNull($invoice->journal_id, 'Cash-mode invoice must not post a sale journal');
    }

    public function test_payment_posts_receipt_journal_in_both_modes(): void
    {
        // Accrual: receipt relieves AR
        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Consultation', 'amount' => 500, 'quantity' => 1, 'discount' => 0],
        ]);

        $result = $this->billing->processPayment($invoice, 200, 'cash', 'REF-1');

        $this->assertTrue($result['success']);
        $this->assertEquals('partial', $result['invoice']->status);
        $this->assertEquals(200.0, (float) $result['invoice']->paid_amount);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertNotNull($payment->journal_id);

        $receipt = Journal::find($payment->journal_id);
        $this->assertEquals('receipt', $receipt->type);
        $this->assertEquals('posted', $receipt->status);

        $byCode = [];
        foreach ($receipt->entries as $entry) {
            $byCode[ChartOfAccount::where('id', $entry->coa_id)->value('code')] = $entry;
        }
        $this->assertEquals(200.0, round((float) $byCode['1000.1']->debit, 2));
        $this->assertEquals(200.0, round((float) $byCode['1200.1']->credit, 2));

        // Cash: receipt recognizes revenue directly
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'cash');

        $cashInvoice = $this->billing->createInvoice($this->patient, 'lab', [
            ['description' => 'Blood Test', 'amount' => 300, 'quantity' => 1, 'discount' => 0],
        ]);
        $this->assertNull($cashInvoice->journal_id);

        $cashResult = $this->billing->processPayment($cashInvoice, 300, 'cash', 'REF-2');
        $this->assertTrue($cashResult['success']);
        $this->assertEquals('paid', $cashResult['invoice']->status);

        $cashPayment = Payment::where('invoice_id', $cashInvoice->id)->firstOrFail();
        $cashReceipt = Journal::find($cashPayment->journal_id);
        $this->assertEquals('receipt', $cashReceipt->type);
        $this->assertEquals('posted', $cashReceipt->status);

        $cashByCode = [];
        foreach ($cashReceipt->entries as $entry) {
            $cashByCode[ChartOfAccount::where('id', $entry->coa_id)->value('code')] = $entry;
        }
        $this->assertEquals(300.0, round((float) $cashByCode['1000.1']->debit, 2));
        $this->assertEquals(300.0, round((float) $cashByCode['4300.2']->credit, 2));
    }

    public function test_create_invoice_fails_soft_when_coA_missing(): void
    {
        ChartOfAccount::where('institute_id', $this->institute->id)
            ->where('type', 'income')
            ->delete();

        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Consultation', 'amount' => 500, 'quantity' => 1, 'discount' => 0],
        ]);

        // Invoice creation must never be blocked by ledger gaps
        $this->assertEquals('pending', $invoice->status);
        $this->assertEquals(500.0, (float) $invoice->total);
        $this->assertNull($invoice->journal_id);
    }
}
