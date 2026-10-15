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
use App\Services\Accounting\AccountingSetupService;
use App\Services\Medical\BillingService;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Database\Seeders\FeatureRegistrySeeder;
use Database\Seeders\MedicalSubModuleSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Task 9.1 — end-to-end billing lifecycles (gap analysis: existing suites
 * cover each operation in isolation; this covers the full arcs).
 *
 * CoA is provisioned with the real medical:provision-coa command (Task 8),
 * so the lifecycle runs on exactly what production recovery would produce.
 */
class MedicalBillingLifecycleTest extends TestCase
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
            'name' => 'Lifecycle Hospital '.uniqid(),
            'slug' => 'lifecycle-hospital-'.uniqid(),
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

        // Full billing gate stack for the HTTP cancel calls.
        (new MedicalSubModuleSeeder)->run();
        (new FeatureRegistrySeeder)->run();
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical');
        app(ModuleAccessService::class)->enableModule($this->institute, 'medical.billing');
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

        // Journal posting requires a current open fiscal year.
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

        // Lifecycle step 1: provision CoA with the real command.
        $code = Artisan::call('medical:provision-coa', [
            '--institute' => (string) $this->institute->id,
            '--execute' => true,
        ]);
        $this->assertSame(0, $code, 'CoA provisioning must succeed');

        $this->billing = app(BillingService::class);

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

    public function test_full_accrual_lifecycle(): void
    {
        // 20% VAT so the sale journal carries a VAT line; items: subtotal
        // 1000, discount 50, tax 100, total 1050.
        app(AccountingSetupService::class)->setSetting(
            $this->institute->id,
            BillingService::VAT_RATE_SETTING,
            0.10
        );

        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Consultation', 'amount' => 400, 'quantity' => 1, 'discount' => 0],
            ['description' => 'Diagnostics', 'amount' => 600, 'quantity' => 1, 'discount' => 50],
        ]);

        // --- invoice saved + sale journal posted ---
        $this->assertNotNull($invoice->id);
        $this->assertSame('pending', $invoice->status);
        $this->assertNotNull($invoice->journal_id);
        $this->assertEqualsWithDelta(1050.0, (float) $invoice->total, 0.01);

        $sale = Journal::find($invoice->journal_id);
        $this->assertSame('posted', $sale->status);
        $this->assertSame('sale', $sale->type);
        $this->assertSame((int) $invoice->id, (int) $sale->ref_id);

        $byCode = $this->entriesByCode($sale);
        // Dr AR = total
        $this->assertEqualsWithDelta(1050.0, (float) $byCode['1200.1']->debit, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $byCode['1200.1']->credit, 0.01);
        // Cr income = subtotal; the discount is a separate contra-revenue
        // Dr leg (4000.5), NOT a reduction of the income line — documented
        // design in MedicalBillingJournalService::postSaleJournal.
        $this->assertEqualsWithDelta(1000.0, (float) $byCode['4300.1']->credit, 0.01);
        $this->assertEqualsWithDelta(50.0, (float) $byCode['4000.5']->debit, 0.01);
        // VAT line because tax > 0
        $this->assertEqualsWithDelta(100.0, (float) $byCode['2100.1']->credit, 0.01);
        $this->assertEqualsWithDelta(
            (float) $sale->entries->sum('debit'),
            (float) $sale->entries->sum('credit'),
            0.01
        );

        // --- partial payment 500 ---
        $result = $this->billing->processPayment($invoice->fresh(), 500.0, 'cash', 'REF-P1');
        $this->assertTrue($result['success'], $result['message']);

        $payment = Payment::where('invoice_id', $invoice->id)->orderBy('id')->firstOrFail();
        $this->assertNotNull($payment->journal_id, 'receipt journal must be linked to the payment');

        $receipt = Journal::find($payment->journal_id);
        $this->assertSame('receipt', $receipt->type);
        $this->assertSame('posted', $receipt->status);

        $byCode = $this->entriesByCode($receipt);
        $this->assertEqualsWithDelta(500.0, (float) $byCode['1000.1']->debit, 0.01);  // Dr Cash
        $this->assertEqualsWithDelta(500.0, (float) $byCode['1200.1']->credit, 0.01); // Cr AR
        $this->assertEqualsWithDelta(
            (float) $receipt->entries->sum('debit'),
            (float) $receipt->entries->sum('credit'),
            0.01
        );

        $invoice->refresh();
        $this->assertEqualsWithDelta(500.0, (float) $invoice->paid_amount, 0.01);
        $this->assertEqualsWithDelta(550.0, (float) $invoice->due_amount, 0.01);
        $this->assertSame('partial', $invoice->status);

        // --- full payment (remaining 550) ---
        $result = $this->billing->processPayment($invoice->fresh(), 550.0, 'cash', 'REF-P2');
        $this->assertTrue($result['success'], $result['message']);

        $payments = Payment::where('invoice_id', $invoice->id)->orderBy('id')->get();
        $this->assertCount(2, $payments);
        foreach ($payments as $p) {
            $this->assertNotNull($p->journal_id, 'every payment carries a receipt journal');
        }

        $second = Journal::find($payments->last()->journal_id);
        $byCode = $this->entriesByCode($second);
        $this->assertEqualsWithDelta(550.0, (float) $byCode['1000.1']->debit, 0.01);
        $this->assertEqualsWithDelta(550.0, (float) $byCode['1200.1']->credit, 0.01);

        $invoice->refresh();
        $this->assertEqualsWithDelta(1050.0, (float) $invoice->paid_amount, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $invoice->due_amount, 0.01);
        $this->assertSame('paid', $invoice->status);

        // --- cancel blocked: invoice has payments ---
        $this->post(route('medical.billing.invoices.cancel', $invoice->fresh()))
            ->assertRedirect()
            ->assertSessionHasErrors('cancel');
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame('posted', Journal::find($invoice->fresh()->journal_id)->status);
    }

    public function test_full_cash_lifecycle(): void
    {
        Setting::set("medical.billing.revenue_mode.{$this->institute->id}", 'cash');

        // 1. Cash mode: invoice saves, NO sale journal at creation.
        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Care', 'amount' => 1000, 'quantity' => 1, 'discount' => 0],
        ]);
        $this->assertNotNull($invoice->id);
        $this->assertNull($invoice->journal_id, 'cash mode creates no sale journal');
        $this->assertSame('pending', $invoice->status);
        $this->assertEqualsWithDelta(1000.0, (float) $invoice->total, 0.01);

        // 2. Payment 500 -> receipt journal Dr Cash / Cr Revenue (no VAT:
        // this test does not set a VAT rate).
        $result = $this->billing->processPayment($invoice->fresh(), 500.0, 'cash', 'REF-C1');
        $this->assertTrue($result['success'], $result['message']);

        $payment = Payment::where('invoice_id', $invoice->id)->orderBy('id')->firstOrFail();
        $this->assertNotNull($payment->journal_id);
        $receipt = Journal::find($payment->journal_id);
        $this->assertSame('receipt', $receipt->type);

        $byCode = $this->entriesByCode($receipt);
        $this->assertEqualsWithDelta(500.0, (float) $byCode['1000.1']->debit, 0.01);  // Dr Cash
        $this->assertEqualsWithDelta(500.0, (float) $byCode['4300.1']->credit, 0.01); // Cr Revenue
        $this->assertArrayNotHasKey('2100.1', $byCode, 'no VAT line without tax');

        $invoice->refresh();
        $this->assertEqualsWithDelta(500.0, (float) $invoice->paid_amount, 0.01);
        $this->assertSame('partial', $invoice->status);

        // 3. Second payment settles the invoice.
        $result = $this->billing->processPayment($invoice->fresh(), 500.0, 'cash', 'REF-C2');
        $this->assertTrue($result['success'], $result['message']);

        $payments = Payment::where('invoice_id', $invoice->id)->orderBy('id')->get();
        $this->assertCount(2, $payments);
        foreach ($payments as $p) {
            $this->assertNotNull($p->journal_id);
        }

        $invoice->refresh();
        $this->assertEqualsWithDelta(1000.0, (float) $invoice->paid_amount, 0.01);
        $this->assertSame('paid', $invoice->status);

        // 4. Cancel still blocked on a paid invoice.
        $this->post(route('medical.billing.invoices.cancel', $invoice->fresh()))
            ->assertRedirect()
            ->assertSessionHasErrors('cancel');
        $this->assertSame('paid', $invoice->fresh()->status);
    }

    public function test_full_cancel_lifecycle_unpaid_invoice(): void
    {
        // Accrual + auto-post: invoice posts its sale journal immediately.
        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Care', 'amount' => 800, 'quantity' => 1, 'discount' => 0],
        ]);
        $saleId = $invoice->journal_id;
        $this->assertNotNull($saleId);
        $this->assertSame('posted', Journal::find($saleId)->status);

        // Cancel: reversal created, original reversed, FK cleared.
        $this->post(route('medical.billing.invoices.cancel', $invoice))
            ->assertRedirect(route('medical.billing.invoices.index'));

        $invoice->refresh();
        $this->assertSame('cancelled', $invoice->status);
        $this->assertNull($invoice->journal_id);

        $original = Journal::find($saleId);
        $this->assertSame('reversed', $original->status);

        $reversal = Journal::where('institute_id', $this->institute->id)
            ->where('reversal_of', $saleId)
            ->first();
        $this->assertNotNull($reversal, 'a reversal journal row must exist');
        $this->assertSame('posted', $reversal->status);
        $this->assertSame('reversal', $reversal->ref_type);

        // Reversal entries mirror the sale with debits/credits swapped.
        $sale = Journal::with('entries')->find($saleId);
        $this->assertEqualsWithDelta(
            (float) $sale->entries->sum('debit'),
            (float) $reversal->entries()->sum('credit'),
            0.01
        );

        // Re-cancel is blocked as "already cancelled".
        $this->post(route('medical.billing.invoices.cancel', $invoice->fresh()))
            ->assertRedirect()
            ->assertSessionHasErrors('cancel');
        $this->assertSame('cancelled', $invoice->fresh()->status);
    }
}
