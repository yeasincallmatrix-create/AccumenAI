<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Doctor;
use App\Models\Medical\Invoice;
use App\Models\Medical\LabOrder;
use App\Models\Medical\LabTest;
use App\Models\Medical\Patient;
use App\Models\Medical\TpaClaim;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 4 — HMS Lab, Billing & TPA verification.
 *
 * Same pattern as earlier medical suites: DatabaseTransactions on the dev
 * DB, web guard + Workspace context, institute-owner membership. CSRF
 * disabled for HTTP calls only; all other middleware still runs.
 */
class MedicalPhase4Test extends TestCase
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
            'name' => 'Medical Lab Test Hospital',
            'slug' => 'medical-lab-test-'.uniqid(),
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

        $roleId = Role::where('slug', 'institute-owner')->value('id');
        Membership::create([
            'user_id' => $this->owner->id,
            'institution_id' => $this->institute->id,
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        $this->doctor = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        // Phase 02 security contract: selectable doctors hold a Doctor
        // profile in the institute (mirrors production onboarding, where
        // staff invite / quickUser provisions institute linkage).
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
        $response = $this->post(route('medical.patients.store'), [
            'first_name' => 'Lab',
            'last_name' => 'Patient',
            'date_of_birth' => '1988-11-05',
            'gender' => 'female',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
        ]);
        $response->assertSessionHasNoErrors();

        return Patient::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function createLabTest(array $overrides = []): LabTest
    {
        return LabTest::create(array_merge([
            'institute_id' => $this->institute->id,
            'code' => 'LT-'.strtoupper(uniqid()),
            'name' => 'Test Panel',
            'category' => 'Biochemistry',
            'normal_range' => '70-100',
            'unit' => 'mg/dL',
            'price' => 300,
            'is_active' => true,
        ], $overrides));
    }

    private function createOrder(Patient $patient, array $tests): LabOrder
    {
        $response = $this->post(route('medical.lab.orders.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'priority' => 'routine',
            'tests' => array_map(fn ($t) => ['lab_test_id' => $t->id], $tests),
        ]);
        $response->assertSessionHasNoErrors();

        return LabOrder::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function createInvoice(Patient $patient, array $items = null): Invoice
    {
        $response = $this->post(route('medical.billing.invoices.store'), [
            'patient_id' => $patient->id,
            'type' => 'opd',
            'items' => $items ?? [
                ['description' => 'Consultation', 'amount' => 500, 'quantity' => 1, 'discount' => 0],
            ],
        ]);
        $response->assertSessionHasNoErrors();

        return Invoice::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    public function test_lab_test_crud(): void
    {
        $code = 'CRUD-'.strtoupper(uniqid());

        $this->post(route('medical.lab.tests.store'), [
            'code' => $code,
            'name' => 'Crud Panel',
            'category' => 'Hematology',
            'normal_range' => '4-11',
            'price' => 450,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $test = LabTest::where('code', $code)->firstOrFail();
        $this->assertSame($this->institute->id, (int) $test->institute_id);

        // Duplicate code rejected.
        $this->post(route('medical.lab.tests.store'), [
            'code' => $code,
            'name' => 'Other',
            'price' => 100,
        ])->assertSessionHasErrors(['code']);

        $this->get(route('medical.lab.tests.index'))->assertOk()->assertSee($code);
        $this->get(route('medical.lab.tests.show', $test))->assertOk();

        $this->put(route('medical.lab.tests.update', $test), [
            'code' => $code,
            'name' => 'Crud Panel Renamed',
            'price' => 500,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Crud Panel Renamed', $test->fresh()->name);

        $this->delete(route('medical.lab.tests.destroy', $test))->assertRedirect();
        $this->assertDatabaseMissing('lab_tests', ['id' => $test->id]);
    }

    public function test_lab_order_full_pipeline(): void
    {
        $patient = $this->createPatient();
        $test = $this->createLabTest(['normal_range' => '70-100']);

        $order = $this->createOrder($patient, [$test]);
        $this->assertMatchesRegularExpression(
            '/^LAB-\d{4}-\d{5}$/',
            $order->order_number
        );
        $this->assertSame('ordered', $order->status);
        $this->assertSame(1, $order->results()->count());

        $this->get(route('medical.lab.orders.show', $order))->assertOk();

        // Collect sample.
        $this->post(route('medical.lab.orders.collect', $order))->assertRedirect();
        $this->assertSame('collected', $order->fresh()->status);

        // Result form renders.
        $this->get(route('medical.lab.orders.result.form', $order))->assertOk();

        // Enter an in-range result → normal + completed.
        $result = $order->results()->firstOrFail();
        $this->post(route('medical.lab.orders.result', $order), [
            'results' => [$result->id => ['result_value' => '85']],
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('completed', $order->status);
        $this->assertSame('normal', $result->fresh()->status);

        // Report PDF downloads.
        $response = $this->get(route('medical.lab.orders.report', $order));
        $response->assertOk();
        $this->assertStringContainsString('pdf', strtolower($response->headers->get('Content-Type')));

        // Lab dashboard renders.
        $this->get(route('medical.lab.index'))->assertOk()->assertSee(clinical_no($order->order_number));
    }

    public function test_abnormal_result_flagged(): void
    {
        $order = $this->createOrder($this->createPatient(), [
            $this->createLabTest(['normal_range' => '70-100']),
        ]);
        $this->post(route('medical.lab.orders.collect', $order))->assertRedirect();

        $result = $order->results()->firstOrFail();
        $this->post(route('medical.lab.orders.result', $order), [
            'results' => [$result->id => ['result_value' => '250']],
        ])->assertSessionHasNoErrors();

        $this->assertSame('abnormal', $result->fresh()->status);
        $this->assertTrue($result->fresh()->is_abnormal);
    }

    public function test_results_rejected_before_collection(): void
    {
        $order = $this->createOrder($this->createPatient(), [$this->createLabTest()]);
        $result = $order->results()->firstOrFail();

        $this->get(route('medical.lab.orders.result.form', $order))->assertRedirect();
        $this->post(route('medical.lab.orders.result', $order), [
            'results' => [$result->id => ['result_value' => '80']],
        ])->assertSessionHas('error');
        $this->assertSame('ordered', $order->fresh()->status);
    }

    public function test_invoice_create_payment_flow(): void
    {
        $patient = $this->createPatient();
        $invoice = $this->createInvoice($patient);

        $this->assertMatchesRegularExpression(
            '/^INV-\d{4}-\d{5}$/',
            $invoice->invoice_number
        );
        // 500 + 5% tax.
        $this->assertEqualsWithDelta(525.0, (float) $invoice->total, 0.01);
        $this->assertSame('pending', $invoice->status);

        $this->get(route('medical.billing.invoices.show', $invoice))->assertOk();

        // Partial payment.
        $this->post(route('medical.billing.invoices.payment', $invoice), [
            'amount' => 200,
            'method' => 'cash',
        ])->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame('partial', $invoice->status);
        $this->assertEqualsWithDelta(325.0, (float) $invoice->due_amount, 0.01);

        // Overpayment refused.
        $this->post(route('medical.billing.invoices.payment', $invoice), [
            'amount' => 99999,
            'method' => 'cash',
        ])->assertSessionHas('error');

        // Settle in full.
        $this->post(route('medical.billing.invoices.payment', $invoice), [
            'amount' => 325,
            'method' => 'mobile_banking',
            'reference' => 'TXN-1',
        ])->assertSessionHasNoErrors();
        $invoice->refresh();
        $this->assertSame('paid', $invoice->status);
        $this->assertSame('TXN-1', $invoice->payment_reference);

        // Paid invoices cannot be edited or deleted.
        $this->put(route('medical.billing.invoices.update', $invoice), [
            'patient_id' => $patient->id,
            'type' => 'opd',
            'items' => [['description' => 'X', 'amount' => 10, 'quantity' => 1]],
        ])->assertSessionHas('error');
        $this->delete(route('medical.billing.invoices.destroy', $invoice))->assertSessionHas('error');

        // Invoice PDF downloads.
        $response = $this->get(route('medical.billing.invoices.print', $invoice));
        $response->assertOk();
        $this->assertStringContainsString('pdf', strtolower($response->headers->get('Content-Type')));

        // Payments + dashboard pages render.
        $this->get(route('medical.billing.payments.index'))->assertOk()->assertSee(clinical_no($invoice->invoice_number));
        $this->get(route('medical.billing.index'))->assertOk();
    }

    public function test_invoice_validation(): void
    {
        $this->post(route('medical.billing.invoices.store'), [])
            ->assertSessionHasErrors(['patient_id', 'type', 'items']);
    }

    public function test_tpa_claim_full_lifecycle(): void
    {
        $patient = $this->createPatient();
        $invoice = $this->createInvoice($patient);

        $this->post(route('medical.tpa.claims.store'), [
            'patient_id' => $patient->id,
            'invoice_id' => $invoice->id,
            'tpa_company_name' => 'Test TPA Ltd',
            'policy_number' => 'POL-1',
            'claim_amount' => 525,
        ])->assertSessionHasNoErrors();

        $claim = TpaClaim::where('institute_id', $this->institute->id)->firstOrFail();
        $this->assertMatchesRegularExpression(
            '/^TPA-\d{4}-\d{5}$/',
            $claim->claim_number
        );
        $this->assertSame('pending', $claim->status);

        $this->get(route('medical.tpa.claims.show', $claim))->assertOk();
        $this->get(route('medical.tpa.index'))->assertOk();

        // Approve for less than the total → invoice goes partial + tpa method.
        $this->post(route('medical.tpa.claims.approve', $claim), [
            'approved_amount' => 300,
        ])->assertSessionHasNoErrors();

        $claim->refresh();
        $invoice->refresh();
        $this->assertSame('approved', $claim->status);
        $this->assertSame('partial', $invoice->status);
        $this->assertSame('tpa', $invoice->payment_method);
        $this->assertEqualsWithDelta(225.0, (float) $invoice->due_amount, 0.01);

        // Settle.
        $this->post(route('medical.tpa.claims.settle', $claim))->assertRedirect();
        $this->assertSame('settled', $claim->fresh()->status);
    }

    public function test_tpa_reject_path(): void
    {
        $patient = $this->createPatient();
        $invoice = $this->createInvoice($patient);

        $this->post(route('medical.tpa.claims.store'), [
            'patient_id' => $patient->id,
            'invoice_id' => $invoice->id,
            'tpa_company_name' => 'Test TPA Ltd',
            'policy_number' => 'POL-2',
            'claim_amount' => 100,
        ])->assertSessionHasNoErrors();

        $claim = TpaClaim::where('institute_id', $this->institute->id)->firstOrFail();

        $this->post(route('medical.tpa.claims.reject', $claim), [
            'remarks' => 'Policy expired.',
        ])->assertRedirect();
        $this->assertSame('rejected', $claim->fresh()->status);
        // Invoice untouched by a rejection.
        $this->assertSame('pending', $invoice->fresh()->status);
    }

    public function test_cross_institute_lab_order_is_forbidden(): void
    {
        $other = Institute::create([
            'name' => 'Other Lab Hospital',
            'slug' => 'other-lab-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'diagnostic_center',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $foreign = LabOrder::create([
            'institute_id' => $other->id,
            'patient_id' => $this->createPatient()->id,
            'doctor_id' => $this->doctor->id,
            'order_number' => 'LAB-2000-999-00001',
            'order_date' => now()->format('Y-m-d'),
        ]);

        $this->get(route('medical.lab.orders.show', $foreign))->assertForbidden();
    }

    public function test_report_pages_render(): void
    {
        $patient = $this->createPatient();
        $this->createInvoice($patient);

        $this->get(route('medical.reports.daily'))->assertOk();
        $this->get(route('medical.reports.monthly'))->assertOk();
        $this->get(route('medical.reports.revenue'))->assertOk();
        $this->get(route('medical.reports.clinical'))->assertOk();
        $this->get(route('medical.reports.pharmacy'))->assertOk();
        $this->get(route('medical.reports.lab'))->assertOk();
        $this->get(route('medical.reports.tpa'))->assertOk();
        $this->get(route('medical.reports.regulatory'))->assertOk();
    }
}
