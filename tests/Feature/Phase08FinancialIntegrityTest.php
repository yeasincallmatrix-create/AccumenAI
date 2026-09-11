<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Doctor;
use App\Models\Medical\Invoice;
use App\Models\Medical\Patient;
use App\Models\Medical\TpaClaim;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\Medical\BillingService;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 08 — Billing, tax, currency & financial integrity hardening.
 *
 * Proves server-side authoritative totals, centralized tax, payment guards
 * (including the locked re-read against double-spend), TPA bounds, invoice
 * lifecycle enforcement and tenant isolation. True OS-thread parallelism is
 * unavailable in PHPUnit; race safety rests on SELECT … FOR UPDATE plus
 * sequential double-spend/double-approve tests (documented limitation).
 */
class Phase08FinancialIntegrityTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    private User $owner;

    private User $doctor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Financial Integrity Hospital',
            'slug' => 'financial-integrity-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
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

        $this->doctor = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        Doctor::create([
            'institute_id' => $this->institute->id,
            'user_id' => $this->doctor->id,
            'registration_number' => 'REG-'.strtoupper(uniqid()),
        ]);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
    }

    private function createPatient(): Patient
    {
        $this->post(route('medical.patients.store'), [
            'first_name' => 'Ledger',
            'last_name' => 'Probe',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'blood_group' => 'O+',
        ])->assertSessionHasNoErrors();

        return Patient::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function createInvoice(Patient $patient, array $items = null): Invoice
    {
        $this->post(route('medical.billing.invoices.store'), [
            'patient_id' => $patient->id,
            'type' => 'opd',
            'items' => $items ?? [
                ['description' => 'Consultation', 'amount' => 500, 'quantity' => 1, 'discount' => 0],
            ],
        ])->assertSessionHasNoErrors();

        return Invoice::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function pay(Invoice $invoice, float $amount, string $method = 'cash'): void
    {
        $this->post(route('medical.billing.invoices.payment', $invoice), [
            'amount' => $amount,
            'method' => $method,
        ])->assertSessionHasNoErrors();
    }

    // --- Invoice arithmetic -------------------------------------------------

    public function test_server_computes_authoritative_totals(): void
    {
        // 500×2 + 250×1 = 1250 subtotal; tax 62.50; discount 50 → 1262.50.
        $invoice = $this->createInvoice($this->createPatient(), [
            ['description' => 'A', 'amount' => 500, 'quantity' => 2, 'discount' => 50],
            ['description' => 'B', 'amount' => 250, 'quantity' => 1, 'discount' => 0],
        ]);

        $this->assertSame(1250.0, (float) $invoice->subtotal);
        $this->assertSame(62.5, (float) $invoice->tax);
        $this->assertSame(50.0, (float) $invoice->discount);
        $this->assertSame(1262.5, (float) $invoice->total);
        $this->assertSame(1262.5, (float) $invoice->due_amount);
        $this->assertSame(0.0, (float) $invoice->paid_amount);
        $this->assertSame('pending', $invoice->status);

        // Stored lines decode back to the submitted items.
        $lines = json_decode($invoice->items_data, true);
        $this->assertSame(1250.0, (float) collect($lines)->sum(fn ($l) => $l['amount'] * $l['quantity']));
    }

    public function test_client_submitted_totals_ignored(): void
    {
        $patient = $this->createPatient();
        $this->post(route('medical.billing.invoices.store'), [
            'patient_id' => $patient->id,
            'type' => 'opd',
            'subtotal' => 1,
            'tax' => 0,
            'discount' => 0,
            'total' => 1,
            'paid_amount' => 1,
            'due_amount' => 0,
            'status' => 'paid',
            'items' => [['description' => 'X', 'amount' => 500, 'quantity' => 1, 'discount' => 0]],
        ])->assertSessionHasNoErrors();

        $invoice = Invoice::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
        $this->assertSame(525.0, (float) $invoice->total);
        $this->assertSame(0.0, (float) $invoice->paid_amount);
        $this->assertSame('pending', $invoice->status);
    }

    public function test_discount_beyond_line_rejected(): void
    {
        $patient = $this->createPatient();
        $this->post(route('medical.billing.invoices.store'), [
            'patient_id' => $patient->id,
            'type' => 'opd',
            'items' => [['description' => 'X', 'amount' => 500, 'quantity' => 1, 'discount' => 600]],
        ])->assertSessionHasErrors(['items.0.discount']);

        $this->assertSame(0, Invoice::where('institute_id', $this->institute->id)->count());
    }

    public function test_negative_amount_and_zero_quantity_rejected(): void
    {
        $patient = $this->createPatient();
        $payload = ['patient_id' => $patient->id, 'type' => 'opd'];

        $this->post(route('medical.billing.invoices.store'), $payload + [
            'items' => [['description' => 'X', 'amount' => -5, 'quantity' => 1]],
        ])->assertSessionHasErrors(['items.0.amount']);

        $this->post(route('medical.billing.invoices.store'), $payload + [
            'items' => [['description' => 'X', 'amount' => 5, 'quantity' => 0]],
        ])->assertSessionHasErrors(['items.0.quantity']);
    }

    public function test_manipulated_tax_ignored(): void
    {
        $patient = $this->createPatient();
        $this->post(route('medical.billing.invoices.store'), [
            'patient_id' => $patient->id,
            'type' => 'opd',
            'tax' => 0,
            'tax_rate' => 0,
            'items' => [['description' => 'X', 'amount' => 1000, 'quantity' => 1, 'discount' => 0]],
        ])->assertSessionHasNoErrors();

        // Centralized 5% rule applies regardless of submitted tax fields.
        $invoice = Invoice::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
        $this->assertSame(50.0, (float) $invoice->tax);
        $this->assertSame(1050.0, (float) $invoice->total);
    }

    // --- Payments -------------------------------------------------------------

    public function test_valid_payment_updates_paid_due_status(): void
    {
        $invoice = $this->createInvoice($this->createPatient());
        $this->pay($invoice, 225.0);

        $invoice->refresh();
        $this->assertSame(225.0, (float) $invoice->paid_amount);
        $this->assertSame(300.0, (float) $invoice->due_amount);
        $this->assertSame('partial', $invoice->status);

        $this->pay($invoice, 300.0);
        $invoice->refresh();
        $this->assertSame(525.0, (float) $invoice->paid_amount);
        $this->assertSame(0.0, (float) $invoice->due_amount);
        $this->assertSame('paid', $invoice->status);
    }

    public function test_zero_negative_payment_rejected(): void
    {
        $invoice = $this->createInvoice($this->createPatient());

        $this->post(route('medical.billing.invoices.payment', $invoice), [
            'amount' => 0, 'method' => 'cash',
        ])->assertSessionHasErrors(['amount']);
        $this->post(route('medical.billing.invoices.payment', $invoice), [
            'amount' => -50, 'method' => 'cash',
        ])->assertSessionHasErrors(['amount']);

        $this->assertSame(0.0, (float) $invoice->fresh()->paid_amount);
    }

    public function test_overpayment_rejected(): void
    {
        $invoice = $this->createInvoice($this->createPatient());
        $this->post(route('medical.billing.invoices.payment', $invoice), [
            'amount' => 600, 'method' => 'cash',
        ])->assertSessionHas('error');

        $invoice->refresh();
        $this->assertSame(0.0, (float) $invoice->paid_amount);
        $this->assertSame(525.0, (float) $invoice->due_amount);
    }

    public function test_sequential_double_spend_guarded(): void
    {
        // Two rapid spends that jointly exceed the due: the second must fail
        // against the locked re-read, leaving paid+due == total.
        $invoice = $this->createInvoice($this->createPatient());
        $this->pay($invoice, 400.0);
        $this->post(route('medical.billing.invoices.payment', $invoice), [
            'amount' => 200, 'method' => 'cash',
        ])->assertSessionHas('error');

        $invoice->refresh();
        $this->assertSame(400.0, (float) $invoice->paid_amount);
        $this->assertSame(125.0, (float) $invoice->due_amount);
        $this->assertSame(
            (float) $invoice->total,
            round((float) $invoice->paid_amount + (float) $invoice->due_amount, 2)
        );
    }

    public function test_payment_on_paid_invoice_refused(): void
    {
        $invoice = $this->createInvoice($this->createPatient());
        $this->pay($invoice, 525.0);

        $this->post(route('medical.billing.invoices.payment', $invoice), [
            'amount' => 10, 'method' => 'cash',
        ])->assertSessionHas('error');
        $this->assertSame(525.0, (float) $invoice->fresh()->paid_amount);
    }

    public function test_foreign_invoice_payment_denied(): void
    {
        $invoice = $this->createInvoice($this->createPatient());

        $other = Institute::create([
            'name' => 'Ledger Rival', 'slug' => 'ledger-rival-'.uniqid(),
            'industry' => 'healthcare', 'sub_industry' => 'clinic',
            'country' => 'Bangladesh', 'status' => 'active',
        ]);
        $rival = User::factory()->create(['account_type' => 'owner', 'status' => 'active']);
        Membership::create([
            'user_id' => $rival->id, 'institution_id' => $other->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'), 'status' => 'active',
        ]);
        $this->actingAs($rival, 'web');
        Workspace::set($other->id);

        $this->post(route('medical.billing.invoices.payment', $invoice), [
            'amount' => 100, 'method' => 'cash',
        ])->assertSessionHasErrors(['invoice_id']);
        $this->assertSame(0.0, (float) $invoice->fresh()->paid_amount);
    }

    // --- Invoice lifecycle ------------------------------------------------------

    public function test_paid_invoice_cannot_be_edited_or_deleted(): void
    {
        $invoice = $this->createInvoice($this->createPatient());
        $this->pay($invoice, 525.0);

        $this->put(route('medical.billing.invoices.update', $invoice), [
            'patient_id' => $invoice->patient_id,
            'type' => 'opd',
            'items' => [['description' => 'X', 'amount' => 1, 'quantity' => 1, 'discount' => 0]],
        ])->assertSessionHas('error');
        $this->assertSame(525.0, (float) $invoice->fresh()->total);

        $this->delete(route('medical.billing.invoices.destroy', $invoice))->assertSessionHas('error');
        $this->assertDatabaseHas('medical_invoices', ['id' => $invoice->id]);
    }

    public function test_pending_invoice_edit_recomputes_totals(): void
    {
        $invoice = $this->createInvoice($this->createPatient());
        $this->put(route('medical.billing.invoices.update', $invoice), [
            'patient_id' => $invoice->patient_id,
            'type' => 'opd',
            'items' => [['description' => 'Y', 'amount' => 1000, 'quantity' => 2, 'discount' => 100]],
        ])->assertSessionHasNoErrors();

        // 2000 subtotal, 100 tax, 100 discount → 2000 total, same rule as creation.
        $invoice->refresh();
        $this->assertSame(2000.0, (float) $invoice->subtotal);
        $this->assertSame(100.0, (float) $invoice->tax);
        $this->assertSame(2000.0, (float) $invoice->total);
        $this->assertSame(2000.0, (float) $invoice->due_amount);
    }

    // --- TPA ---------------------------------------------------------------------

    public function test_claim_capped_at_outstanding_due(): void
    {
        $invoice = $this->createInvoice($this->createPatient());
        $this->post(route('medical.tpa.claims.store'), [
            'patient_id' => $invoice->patient_id,
            'invoice_id' => $invoice->id,
            'tpa_company_name' => 'Ledger TPA',
            'policy_number' => 'POL-1',
            'claim_amount' => 99999,
        ])->assertSessionHas('error');

        $this->assertSame(0, \App\Models\Medical\TpaClaim::where('institute_id', $this->institute->id)->count());
    }

    public function test_approve_credits_invoice_bounded_and_idempotent(): void
    {
        $invoice = $this->createInvoice($this->createPatient());
        $this->post(route('medical.tpa.claims.store'), [
            'patient_id' => $invoice->patient_id,
            'invoice_id' => $invoice->id,
            'tpa_company_name' => 'Ledger TPA',
            'policy_number' => 'POL-1',
            'claim_amount' => 300,
        ])->assertSessionHasNoErrors();
        $claim = \App\Models\Medical\TpaClaim::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();

        $this->post(route('medical.tpa.claims.approve', $claim), ['approved_amount' => 300])
            ->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame(300.0, (float) $invoice->paid_amount);
        $this->assertSame(225.0, (float) $invoice->due_amount);
        $this->assertSame('partial', $invoice->status);

        // Second approval is refused at both layers; no double credit.
        $this->post(route('medical.tpa.claims.approve', $claim), ['approved_amount' => 100])
            ->assertSessionHas('error');
        $this->assertSame(300.0, (float) $invoice->fresh()->paid_amount);

        try {
            app(\App\Services\Medical\TpaService::class)->approveClaim($claim->fresh(), 100);
            $this->fail('Double approval must throw.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('pending', strtolower($e->getMessage()));
        }
        $this->assertSame(300.0, (float) $invoice->fresh()->paid_amount);
    }

    public function test_foreign_invoice_claim_rejected(): void
    {
        $invoice = $this->createInvoice($this->createPatient());

        $other = Institute::create([
            'name' => 'Ledger Rival 2', 'slug' => 'ledger-rival-2-'.uniqid(),
            'industry' => 'healthcare', 'sub_industry' => 'clinic',
            'country' => 'Bangladesh', 'status' => 'active',
        ]);
        $rival = User::factory()->create(['account_type' => 'owner', 'status' => 'active']);
        Membership::create([
            'user_id' => $rival->id, 'institution_id' => $other->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'), 'status' => 'active',
        ]);
        $this->actingAs($rival, 'web');
        Workspace::set($other->id);

        $this->post(route('medical.tpa.claims.store'), [
            'patient_id' => $invoice->patient_id,
            'invoice_id' => $invoice->id,
            'tpa_company_name' => 'X',
            'policy_number' => 'Y',
            'claim_amount' => 100,
        ])->assertSessionHasErrors(['invoice_id']);
    }

    // --- Invariants -----------------------------------------------------------------

    public function test_financial_invariants_hold(): void
    {
        $invoice = $this->createInvoice($this->createPatient(), [
            ['description' => 'A', 'amount' => 333.33, 'quantity' => 3, 'discount' => 10],
        ]);
        $this->pay($invoice, 500.0);
        $invoice->refresh();

        $this->assertGreaterThanOrEqual(0, (float) $invoice->total);
        $this->assertGreaterThanOrEqual(0, (float) $invoice->paid_amount);
        $this->assertGreaterThanOrEqual(0, (float) $invoice->due_amount);
        $this->assertSame(
            round((float) $invoice->total, 2),
            round((float) $invoice->paid_amount + (float) $invoice->due_amount, 2)
        );
        $this->assertSame(BillingService::TAX_RATE, 0.05);
    }
}
