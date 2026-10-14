<?php

namespace Tests\Feature\Medical;

use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Country;
use App\Models\Institute;
use App\Models\Journal;
use App\Models\Medical\Payment;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Medical\BillingService;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InvoiceCancelTest extends TestCase
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
            'sub_industry' => 'hospital',
            'status' => 'active',
        ]);

        $this->owner = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        Membership::create([
            'user_id' => $this->owner->id,
            'institution_id' => $this->institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'),
            'status' => 'active',
        ]);

        // Full billing gate stack: submodule registry row + feature
        // registry row + module override + feature override. Without these
        // the medical.module / feature middleware 403s every billing route.
        (new \Database\Seeders\MedicalSubModuleSeeder)->run();
        (new \Database\Seeders\FeatureRegistrySeeder)->run();
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical');
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.billing');
        \App\Models\InstituteFeatureOverride::updateOrCreate(
            ['institute_id' => $this->institute->id, 'feature_key' => 'medical.billing'],
            ['enabled' => true]
        );

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

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
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

    protected function unpaidInvoice(string $type = 'opd', float $amount = 1000)
    {
        return $this->billing->createInvoice($this->patient, $type, [
            ['description' => 'Care', 'amount' => $amount, 'quantity' => 1, 'discount' => 0],
        ]);
    }

    public function test_cancel_sets_status_cancelled_and_reverses_sale_journal(): void
    {
        $invoice = $this->unpaidInvoice();
        $saleId = $invoice->journal_id;
        $this->assertNotNull($saleId);

        $response = $this->post(route('medical.billing.invoices.cancel', $invoice));

        $response->assertRedirect(route('medical.billing.invoices.index'));
        $this->assertEquals('cancelled', $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->journal_id);
        $this->assertEquals('reversed', Journal::find($saleId)->status);
    }

    public function test_cancel_reverses_all_receipt_journals(): void
    {
        // Legacy-style rows: receipt journals exist while paid_amount is
        // still zero — cancel must reverse every one of them (VERIFY 1b).
        $invoice = $this->unpaidInvoice();
        $saleId = $invoice->journal_id;

        $receiptIds = [];
        foreach ([200, 300] as $amount) {
            $payment = Payment::create([
                'institute_id' => $this->institute->id,
                'invoice_id' => $invoice->id,
                'amount' => $amount,
                'payment_method' => 'cash',
                'paid_at' => now(),
            ]);
            $journal = app(\App\Services\Medical\MedicalBillingJournalService::class)
                ->postReceiptJournal($payment);
            $this->assertNotNull($journal);
            $receiptIds[] = $payment->fresh()->journal_id;
        }

        $this->post(route('medical.billing.invoices.cancel', $invoice))
            ->assertRedirect(route('medical.billing.invoices.index'));

        $this->assertEquals('reversed', Journal::find($saleId)->status);
        foreach ($receiptIds as $receiptId) {
            $this->assertEquals('reversed', Journal::find($receiptId)->status);
        }
        $this->assertEquals('cancelled', $invoice->fresh()->status);
        $this->assertEquals(0, Payment::where('invoice_id', $invoice->id)->whereNotNull('journal_id')->count());
    }

    public function test_cancel_blocked_when_paid_amount_positive(): void
    {
        $invoice = $this->unpaidInvoice();
        $this->billing->processPayment($invoice->fresh(), 200, 'cash', 'REF-1');

        $saleId = $invoice->fresh()->journal_id;

        $response = $this->post(route('medical.billing.invoices.cancel', $invoice->fresh()));

        $response->assertRedirect();
        $response->assertSessionHasErrors('cancel');
        $this->assertNotEquals('cancelled', $invoice->fresh()->status);
        $this->assertEquals('posted', Journal::find($saleId)->status);
    }

    public function test_cancel_blocked_when_already_cancelled(): void
    {
        $invoice = $this->unpaidInvoice();

        $this->post(route('medical.billing.invoices.cancel', $invoice))
            ->assertRedirect(route('medical.billing.invoices.index'));
        $this->assertEquals('cancelled', $invoice->fresh()->status);

        $response = $this->post(route('medical.billing.invoices.cancel', $invoice->fresh()));
        $response->assertRedirect();
        $response->assertSessionHasErrors('cancel');
    }

    public function test_cancel_requires_permission(): void
    {
        $invoice = $this->unpaidInvoice();

        $staff = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        Membership::create([
            'user_id' => $staff->id,
            'institution_id' => $this->institute->id,
            'role_id' => Role::where('slug', 'receptionist')->whereNull('institute_id')->value('id'),
            'status' => 'active',
        ]);

        $this->actingAs($staff, 'web');
        Workspace::set($this->institute->id);

        $this->post(route('medical.billing.invoices.cancel', $invoice))->assertForbidden();
        $this->assertNotEquals('cancelled', $invoice->fresh()->status);
    }

    public function test_cancel_requires_same_institute(): void
    {
        $other = Institute::create([
            'name' => 'Other Hospital ' . uniqid(),
            'slug' => 'other-hospital-' . uniqid(),
            'country' => 'Bangladesh',
            'industry' => 'healthcare',
            'status' => 'active',
        ]);

        $foreign = \App\Models\Medical\Invoice::create([
            'institute_id' => $other->id,
            'patient_id' => $this->patient->id,
            'invoice_number' => 'TEST-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'type' => 'opd',
            'subtotal' => 100,
            'tax' => 0,
            'discount' => 0,
            'total' => 100,
            'paid_amount' => 0,
            'due_amount' => 100,
            'status' => 'pending',
        ]);

        $this->post(route('medical.billing.invoices.cancel', $foreign))->assertForbidden();
        $this->assertNotEquals('cancelled', $foreign->fresh()->status);
    }

    public function test_cancel_in_cash_mode_only_reverses_receipts(): void
    {
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'cash');

        $invoice = \App\Models\Medical\Invoice::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $this->patient->id,
            'invoice_number' => 'TEST-' . uniqid(),
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(15)->toDateString(),
            'type' => 'opd',
            'subtotal' => 500,
            'tax' => 0,
            'discount' => 0,
            'total' => 500,
            'paid_amount' => 0,
            'due_amount' => 500,
            'status' => 'pending',
        ]);
        $this->assertNull($invoice->journal_id);

        $payment = Payment::create([
            'institute_id' => $this->institute->id,
            'invoice_id' => $invoice->id,
            'amount' => 200,
            'payment_method' => 'cash',
            'paid_at' => now(),
        ]);
        $receipt = app(\App\Services\Medical\MedicalBillingJournalService::class)
            ->postReceiptJournal($payment);
        $this->assertNotNull($receipt);
        $receiptId = $payment->fresh()->journal_id;

        $this->post(route('medical.billing.invoices.cancel', $invoice))
            ->assertRedirect(route('medical.billing.invoices.index'));

        $this->assertEquals('cancelled', $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->journal_id);
        $this->assertEquals('reversed', Journal::find($receiptId)->status);
    }

    public function test_cancel_visible_in_index_view(): void
    {
        $invoice = $this->unpaidInvoice();

        $this->get(route('medical.billing.invoices.index'))
            ->assertOk()
            ->assertSee("invoices/{$invoice->id}/cancel", false);
    }

    public function test_cancel_hidden_when_payment_exists(): void
    {
        $invoice = $this->unpaidInvoice();
        $this->billing->processPayment($invoice->fresh(), 200, 'cash', 'REF-1');

        $cancelUrl = "invoices/{$invoice->id}/cancel";
        $this->get(route('medical.billing.invoices.index'))->assertDontSee($cancelUrl, false);
        $this->get(route('medical.billing.invoices.show', $invoice->fresh()))->assertDontSee($cancelUrl, false);
    }
}
