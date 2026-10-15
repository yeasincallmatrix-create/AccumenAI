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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Task 9.5 — fail-soft contract of the billing pipeline: a missing account
 * must NEVER block a business operation (invoice, payment, cancel, UI post),
 * only leave the journal link null with an actionable warning.
 *
 * Deliberately non-duplicative with MedicalProvisionCoaTest, which owns the
 * SALE-side fail-soft + provision + recovery e2e; this class covers receipts,
 * DB atomics, cancel, the VAT-specific branch, and the manual-post endpoint.
 *
 * Fixture institutes are created WITHOUT CoA accounts — that absence is the
 * point (medical:provision-coa is only called explicitly, in test 4).
 */
class MedicalBillingFailSoftTest extends TestCase
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
            'name' => 'Failsoft Hospital '.uniqid(),
            'slug' => 'failsoft-hospital-'.uniqid(),
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

        // Gate stack for the HTTP tests (cancel, manual post).
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

        // Journal posting requires a current open fiscal year. NO CoA accounts
        // are provisioned here — the missing accounts drive every test.
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

        $this->billing = app(BillingService::class);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
    }

    public function test_receipt_journal_fails_soft_but_payment_still_completes(): void
    {
        // Invoice first (sale also fails soft — outside the spy window).
        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Care', 'amount' => 500, 'quantity' => 1, 'discount' => 0],
        ]);
        $this->assertNull($invoice->journal_id);

        Log::spy();
        $result = $this->billing->processPayment($invoice->fresh(), 200.0, 'cash', 'REF-FS1');
        $this->assertTrue($result['success'], $result['message']);

        // Business state advanced fully despite the missing accounts.
        $invoice->refresh();
        $this->assertEqualsWithDelta(200.0, (float) $invoice->paid_amount, 0.01);
        $this->assertEqualsWithDelta(300.0, (float) $invoice->due_amount, 0.01);
        $this->assertSame('partial', $invoice->status);

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertNull($payment->journal_id, 'receipt journal link must stay null on fail-soft');
        $this->assertNull($payment->journal);

        // Actionable warning: cash account missing, with payment + invoice ids.
        Log::shouldHaveReceived('warning')
            ->withArgs(function ($message, $context) use ($invoice, $payment) {
                return str_contains((string) $message, 'required account missing')
                    && ($context['code'] ?? null) === '1000.1'
                    && ($context['invoice_id'] ?? null) === (int) $invoice->id
                    && ($context['payment_id'] ?? null) === (int) $payment->id
                    && str_contains(
                        (string) ($context['action_required'] ?? ''),
                        "medical:provision-coa --institute={$this->institute->id} --execute"
                    );
            })
            ->once();

        // And no receipt journal row was half-written. Receipts carry
        // type='receipt' (their ref_type is null by design — see report gaps).
        $this->assertSame(
            0,
            Journal::where('institute_id', $this->institute->id)
                ->where('type', 'receipt')
                ->count(),
            'no orphan receipt journal may exist'
        );
    }

    public function test_fail_soft_leaves_invoice_intact_without_any_journal_rows(): void
    {
        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Care', 'amount' => 800, 'quantity' => 1, 'discount' => 0],
        ]);

        // Invoice row is complete: sequence-allocated number, totals, status.
        $this->assertNotNull($invoice->id);
        $this->assertNotEmpty($invoice->invoice_number);
        $this->assertSame('pending', $invoice->status);
        $this->assertEqualsWithDelta(800.0, (float) $invoice->subtotal, 0.01);
        $this->assertEqualsWithDelta(800.0, (float) $invoice->total, 0.01);
        $this->assertEqualsWithDelta(800.0, (float) $invoice->due_amount, 0.01);
        $this->assertNull($invoice->journal_id);

        // Atomically: not even a draft/partial journal or entry rows exist.
        $this->assertSame(
            0,
            Journal::where('institute_id', $this->institute->id)
                ->where('ref_type', 'medical_invoice')
                ->where('ref_id', $invoice->id)
                ->count(),
            'fail-soft must not leave a partial journal behind'
        );
        $this->assertSame(0, DB::table('journal_entries')->where('institute_id', $this->institute->id)->count());
    }

    public function test_cancel_succeeds_for_invoice_that_never_got_a_journal(): void
    {
        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Care', 'amount' => 600, 'quantity' => 1, 'discount' => 0],
        ]);
        $this->assertNull($invoice->journal_id);

        $this->post(route('medical.billing.invoices.cancel', $invoice))
            ->assertRedirect(route('medical.billing.invoices.index'))
            ->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('cancelled', $invoice->status);
        $this->assertNull($invoice->journal_id);

        // No reversal machinery engaged for a journal-less cancel.
        $this->assertSame(0, Journal::where('institute_id', $this->institute->id)->count());

        // Re-cancel still blocked as already cancelled.
        $this->post(route('medical.billing.invoices.cancel', $invoice->fresh()))
            ->assertRedirect()
            ->assertSessionHasErrors('cancel');
        $this->assertSame('cancelled', $invoice->fresh()->status);
    }

    public function test_missing_vat_account_fails_soft_on_taxed_invoice_with_totals_intact(): void
    {
        $code = Artisan::call('medical:provision-coa', [
            '--institute' => (string) $this->institute->id,
            '--execute' => true,
        ]);
        $this->assertSame(0, $code);

        // Full install, then remove ONLY the VAT output account: AR and income
        // resolve fine, the tax-specific requireAccount branch must abort the
        // whole sale journal before any row is written.
        ChartOfAccount::where('institute_id', $this->institute->id)
            ->where('code', '2100.1')
            ->delete();

        app(AccountingSetupService::class)->setSetting(
            $this->institute->id,
            BillingService::VAT_RATE_SETTING,
            0.10
        );

        Log::spy();
        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Care', 'amount' => 1000, 'quantity' => 1, 'discount' => 0],
        ]);

        // Fail-soft: no journal, but the invoice's own numbers are complete.
        $this->assertNull($invoice->journal_id);
        $this->assertEqualsWithDelta(100.0, (float) $invoice->tax, 0.01);
        $this->assertEqualsWithDelta(1100.0, (float) $invoice->total, 0.01);
        $this->assertSame(
            0,
            Journal::where('institute_id', $this->institute->id)
                ->where('ref_type', 'medical_invoice')
                ->where('ref_id', $invoice->id)
                ->count()
        );

        Log::shouldHaveReceived('warning')
            ->withArgs(function ($message, $context) use ($invoice) {
                return str_contains((string) $message, 'required account missing')
                    && ($context['code'] ?? null) === '2100.1'
                    && ($context['invoice_id'] ?? null) === (int) $invoice->id
                    && str_contains(
                        (string) ($context['action_required'] ?? ''),
                        "medical:provision-coa --institute={$this->institute->id} --execute"
                    );
            })
            ->once();
    }

    public function test_manual_post_endpoint_rejects_journalless_invoice_gracefully(): void
    {
        $invoice = $this->billing->createInvoice($this->patient, 'opd', [
            ['description' => 'Care', 'amount' => 450, 'quantity' => 1, 'discount' => 0],
        ]);
        $this->assertNull($invoice->journal_id);

        // Documented contract: fail-soft leftovers are recovered by
        // cancel + reissue, NOT by the Post Journal button — the endpoint
        // must answer with a validation error, never a 500.
        $this->post(route('medical.billing.invoices.post-journal', $invoice))
            ->assertRedirect()
            ->assertSessionHasErrors('journal');

        $invoice->refresh();
        $this->assertNull($invoice->journal_id);
        $this->assertSame('pending', $invoice->status);
    }
}
