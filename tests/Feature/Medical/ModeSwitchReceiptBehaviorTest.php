<?php

namespace Tests\Feature\Medical;

use App\Models\ChartOfAccount;
use App\Models\Country;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\InstituteFeatureOverride;
use App\Models\Journal;
use App\Models\Medical\Patient;
use App\Models\Medical\Payment;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\Accounting\AccountingReportService;
use App\Services\Medical\BillingService;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Database\Seeders\FeatureRegistrySeeder;
use Database\Seeders\MedicalSubModuleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Gap 2 / Option 4: postReceiptJournal keys on the invoice's ORIGINAL
 * revenue treatment (sale journal present = accrual) instead of the
 * tenant's current revenue_mode — so a mid-life mode switch can neither
 * double-count revenue nor leave AR unrelieved.
 *
 * Mode switches in these tests are applied by direct Setting::set (the
 * HTTP route's FY guard is covered by MedicalBillingModeSwitchTest and
 * Settings\MedicalBillingSettingsTest); here we only need the post-switch
 * state.
 */
class ModeSwitchReceiptBehaviorTest extends TestCase
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
            'name' => 'Mode Receipt Hospital '.uniqid(),
            'slug' => 'mode-receipt-hospital-'.uniqid(),
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

        (new MedicalSubModuleSeeder)->run();
        (new FeatureRegistrySeeder)->run();
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical');
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.billing');
        app(ModuleAccessService::class)->enableModule($this->institute, 'finance');
        InstituteFeatureOverride::updateOrCreate(
            ['institute_id' => $this->institute->id, 'feature_key' => 'medical.billing'],
            ['enabled' => true]
        );

        $this->patient = Patient::create([
            'institute_id' => $this->institute->id,
            'mr_number' => 'MR-'.uniqid(),
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'phone' => '017'.rand(10000000, 99999999),
            'gender' => 'male',
            'date_of_birth' => '1990-01-01',
        ]);

        FiscalYear::firstOrCreate(
            ['institute_id' => $this->institute->id, 'branch_id' => null, 'is_current' => true],
            [
                'name' => 'FY '.date('Y'),
                'start_date' => date('Y-01-01'),
                'end_date' => date('Y-12-31'),
                'status' => 'open',
            ]
        );

        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'accrual');
        Setting::set("medical.billing.auto_post.{$this->institute->id}", '1');

        $code = Artisan::call('medical:provision-coa', [
            '--institute' => (string) $this->institute->id,
            '--execute' => true,
        ]);
        $this->assertSame(0, $code, 'CoA provisioning must succeed');

        $this->billing = app(BillingService::class);
        $this->reports = app(AccountingReportService::class);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
    }

    protected function entriesByCode(Journal $journal): array
    {
        $byCode = [];
        foreach ($journal->entries as $entry) {
            $account = ChartOfAccount::find($entry->coa_id);
            $this->assertNotNull($account, "entry {$entry->id} must reference an account");
            $byCode[$account->code] = $entry;
        }

        return $byCode;
    }

    protected function tbRow(string $code): ?object
    {
        return $this->reports->trialBalance($this->institute->id, null)->firstWhere('code', $code);
    }

    protected function receiptFor(float $amount, string $ref): Journal
    {
        $result = $this->billing->processPayment($this->currentInvoice, $amount, 'cash', $ref);
        $this->assertTrue($result['success'], $result['message']);

        $payment = Payment::where('invoice_id', $this->currentInvoice->id)
            ->where('payment_reference', $ref)
            ->firstOrFail();
        $this->assertNotNull($payment->journal_id);

        return Journal::find($payment->journal_id);
    }

    public function test_payment_on_old_accrual_invoice_after_switch_posts_cr_ar(): void
    {
        // 1. Accrual invoice: sale journal posted (Dr AR / Cr Revenue 1000).
        $this->currentInvoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Care', 'amount' => 1000, 'quantity' => 1, 'discount' => 0],
        ]);
        $this->assertNotNull($this->currentInvoice->journal_id);

        // 2. Partial payment 500 while still accrual: Dr Cash / Cr AR.
        $first = $this->receiptFor(500.0, 'REF-1');
        $byCode = $this->entriesByCode($first);
        $this->assertEqualsWithDelta(500.0, (float) $byCode['1000.1']->debit, 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $byCode['1200.1']->credit, 0.01);

        // 3. Tenant switches to cash.
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'cash');

        // 4. Second payment 500: STILL Dr Cash / Cr AR — the invoice is accrual.
        $second = $this->receiptFor(500.0, 'REF-2');
        $byCode = $this->entriesByCode($second);
        $this->assertEqualsWithDelta(500.0, (float) $byCode['1000.1']->debit, 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $byCode['1200.1']->credit, 0.01);
        $this->assertArrayNotHasKey('4300.1', $byCode, 'receipt must not re-recognize revenue');

        // 5. AR fully relieved: sale Dr 1000 - receipts Cr 1000 = 0.
        $ar = $this->tbRow('1200.1');
        $this->assertNotNull($ar);
        $this->assertEqualsWithDelta(0.0, (float) $ar->balance, 0.01, 'AR must be fully relieved');
    }

    public function test_payment_on_cash_invoice_after_switch_to_accrual_posts_cr_revenue(): void
    {
        // 1. Cash invoice: no sale journal (journal_id null).
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'cash');
        $this->currentInvoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Care', 'amount' => 1000, 'quantity' => 1, 'discount' => 0],
        ]);
        $this->assertNull($this->currentInvoice->journal_id);

        // 2. Tenant switches to accrual.
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'accrual');

        // 3. Payment 500: cash-style — Dr Cash / Cr Revenue, never Cr AR.
        $receipt = $this->receiptFor(500.0, 'REF-1');
        $byCode = $this->entriesByCode($receipt);
        $this->assertEqualsWithDelta(500.0, (float) $byCode['1000.1']->debit, 0.01);
        $this->assertEqualsWithDelta(500.0, (float) $byCode['4300.1']->credit, 0.01);
        $this->assertArrayNotHasKey('1200.1', $byCode, 'cash invoice payments must not credit AR');

        // 4. No AR activity for this invoice at all.
        $this->assertNull(
            $this->tbRow('1200.1'),
            'cash-style receipt must not create any AR balance'
        );
    }

    public function test_revenue_not_double_counted_when_switching_modes(): void
    {
        // 1. Accrual invoice 1000: Revenue credited once at sale.
        $this->currentInvoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Care', 'amount' => 1000, 'quantity' => 1, 'discount' => 0],
        ]);
        $this->assertNotNull($this->currentInvoice->journal_id);
        $this->assertEqualsWithDelta(1000.0, (float) $this->tbRow('4300.1')->credit, 0.01);

        // 2. Switch to cash.
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'cash');

        // 3. Full payment 1000.
        $receipt = $this->receiptFor(1000.0, 'REF-FULL');
        $byCode = $this->entriesByCode($receipt);
        $this->assertArrayNotHasKey('4300.1', $byCode, 'post-switch payment must not credit revenue again');

        // 4. Revenue total still 1000 (credit), not 2000.
        $revenue = $this->tbRow('4300.1');
        $this->assertEqualsWithDelta(1000.0, (float) $revenue->credit, 0.01, 'revenue must not be double-counted');
        $this->assertEqualsWithDelta(0.0, (float) $revenue->debit, 0.01);

        // AR fully relieved as a side effect of the accrual-style receipts.
        $this->assertEqualsWithDelta(0.0, (float) $this->tbRow('1200.1')->balance, 0.01);
    }

    public function test_ar_fully_relieved_after_all_payments_on_old_accrual_invoice(): void
    {
        $this->currentInvoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Care', 'amount' => 1000, 'quantity' => 1, 'discount' => 0],
        ]);
        $this->assertNotNull($this->currentInvoice->journal_id);

        // Switch to cash with the sale already posted.
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'cash');

        // Pay 400 → AR = 600.
        $this->receiptFor(400.0, 'REF-A');
        $this->assertEqualsWithDelta(600.0, (float) $this->tbRow('1200.1')->balance, 0.01, 'AR must equal remaining due');

        // Pay 600 → AR = 0.
        $this->receiptFor(600.0, 'REF-B');
        $this->assertEqualsWithDelta(0.0, (float) $this->tbRow('1200.1')->balance, 0.01, 'AR must be fully relieved');

        $this->currentInvoice->refresh();
        $this->assertSame('paid', $this->currentInvoice->status);
        $this->assertEqualsWithDelta(1000.0, (float) $this->currentInvoice->paid_amount, 0.01);
    }

    public function test_cancelled_invoice_cannot_be_paid_after_switch(): void
    {
        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Care', 'amount' => 800, 'quantity' => 1, 'discount' => 0],
        ]);
        $this->assertNotNull($invoice->journal_id);

        // Switch, then cancel (cancel reverses the sale and nulls journal_id).
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'cash');
        $this->post(route('medical.billing.invoices.cancel', $invoice))
            ->assertRedirect(route('medical.billing.invoices.index'));
        $invoice->refresh();
        $this->assertSame('cancelled', $invoice->status);
        $this->assertNull($invoice->journal_id);

        // Payments are blocked by status before any mode decision runs.
        $result = $this->billing->processPayment($invoice, 100.0, 'cash', 'REF-CANCELLED');
        $this->assertFalse($result['success']);
        $this->assertSame(0, Payment::where('invoice_id', $invoice->id)->count());
        $this->assertSame(0, Journal::where('institute_id', $this->institute->id)->where('type', 'receipt')->count());
        $this->assertSame('cancelled', $invoice->fresh()->status);
    }
}
