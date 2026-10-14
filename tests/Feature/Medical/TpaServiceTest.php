<?php

namespace Tests\Feature\Medical;

use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Country;
use App\Models\Institute;
use App\Models\Journal;
use App\Models\Medical\Payment;
use App\Models\Medical\TpaClaim;
use App\Models\Role;
use App\Models\Setting;
use App\Services\Medical\BillingService;
use App\Services\Medical\TpaService;
use App\Services\ModuleAccessService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TpaServiceTest extends TestCase
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
        $this->tpa = app(TpaService::class);
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
            ['code' => '2100.1', 'name' => 'VAT Output Payable', 'type' => 'liability', 'group' => $liabilityGroup, 'flags' => ['cash_flow_category' => 'operating']],
            ['code' => '4300.1', 'name' => 'Consultation Fees', 'type' => 'income', 'group' => $incomeGroup, 'flags' => []],
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

    protected function pendingClaim($invoice, float $amount): TpaClaim
    {
        return TpaClaim::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'invoice_id' => $invoice->id,
            'claim_number' => 'TPA-' . uniqid(),
            'tpa_company_name' => 'Test Insurance',
            'policy_number' => 'POL-' . uniqid(),
            'claim_amount' => $amount,
            'status' => 'pending',
            'claim_date' => now()->toDateString(),
        ]);
    }

    public function test_tpa_approval_creates_payment_and_receipt_journal(): void
    {
        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Consultation', 'amount' => 1000, 'quantity' => 1, 'discount' => 0],
        ]);

        $claim = $this->pendingClaim($invoice, 600);

        $this->assertTrue($this->tpa->approveClaim($claim, 600));

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertEquals(600.0, (float) $payment->amount);
        $this->assertEquals('tpa', $payment->payment_method);
        $this->assertEquals($claim->claim_number, $payment->payment_reference);
        $this->assertNotNull($payment->journal_id);

        $receipt = Journal::find($payment->journal_id);
        $this->assertEquals('receipt', $receipt->type);
        $this->assertEquals('posted', $receipt->status);

        $invoice->refresh();
        $this->assertEquals(600.0, (float) $invoice->paid_amount);
        $this->assertEquals('partial', $invoice->status);
    }

    public function test_tpa_approval_in_cash_mode_credits_revenue(): void
    {
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'cash');

        $invoice = \App\Models\Medical\Invoice::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'invoice_number' => 'TEST-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'type' => 'opd',
            'subtotal' => 1000,
            'tax' => 50,
            'discount' => 0,
            'total' => 1050,
            'paid_amount' => 0,
            'due_amount' => 1050,
            'status' => 'pending',
        ]);

        $claim = $this->pendingClaim($invoice, 1050);
        $this->assertTrue($this->tpa->approveClaim($claim, 1050));

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $receipt = Journal::find($payment->journal_id);

        $byCode = [];
        foreach ($receipt->entries as $entry) {
            $byCode[ChartOfAccount::where('id', $entry->coa_id)->value('code')] = $entry;
        }

        $this->assertEquals(1050.0, round((float) $byCode['1000.1']->debit, 2));
        $this->assertEquals(1000.0, round((float) $byCode['4300.1']->credit, 2));
        $this->assertEquals(50.0, round((float) $byCode['2100.1']->credit, 2));
        $this->assertEquals('paid', $invoice->fresh()->status);
    }

    public function test_tpa_approval_in_accrual_mode_credits_ar(): void
    {
        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Consultation', 'amount' => 1000, 'quantity' => 1, 'discount' => 0],
        ]);

        $claim = $this->pendingClaim($invoice, 400);
        $this->assertTrue($this->tpa->approveClaim($claim, 400));

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $receipt = Journal::find($payment->journal_id);

        $byCode = [];
        foreach ($receipt->entries as $entry) {
            $byCode[ChartOfAccount::where('id', $entry->coa_id)->value('code')] = $entry;
        }

        $this->assertEquals(400.0, round((float) $byCode['1000.1']->debit, 2));
        $this->assertEquals(400.0, round((float) $byCode['1200.1']->credit, 2));
    }

    public function test_tpa_approval_idempotency(): void
    {
        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Consultation', 'amount' => 1000, 'quantity' => 1, 'discount' => 0],
        ]);

        $claim = $this->pendingClaim($invoice, 500);
        $this->assertTrue($this->tpa->approveClaim($claim, 500));
        $this->assertEquals(1, Payment::where('invoice_id', $invoice->id)->count());

        try {
            $this->tpa->approveClaim($claim->fresh(), 500);
            $this->fail('Second approval of a non-pending claim must throw.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('pending', $e->getMessage());
        }

        $this->assertEquals(1, Payment::where('invoice_id', $invoice->id)->count());
        $this->assertEquals(500.0, (float) $invoice->fresh()->paid_amount);
    }
}
