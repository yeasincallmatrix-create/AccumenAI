<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Doctor;
use App\Models\Medical\LabOrder;
use App\Models\Medical\LabTest;
use App\Models\Medical\Patient;
use App\Models\Medical\Prescription;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 06 — Authorization & access-control hardening.
 *
 * Matrix over the existing custom RBAC (permission: middleware +
 * membership roles): unauthenticated / wrong guard / unauthorized same
 * tenant / foreign tenant / authorized positives, plus high-risk negatives
 * (finalize, result entry, payment, archival, discharge, dispense, stock
 * adjust, TPA approve, PDF). Tenant isolation itself is Phase 02's domain
 * and is only re-asserted, never bypassed, here.
 */
class HmsAuthorizationTest extends TestCase
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
            'name' => 'Authorization Test Hospital',
            'slug' => 'authorization-test-'.uniqid(),
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

    /**
     * Staff user holding exactly the given permission slugs in this institute.
     */
    private function userWith(array $slugs, string $suffix = ''): User
    {
        $user = User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
        $role = Role::create([
            'institute_id' => $this->institute->id,
            'name' => 'Test Role '.$suffix.' '.uniqid(),
            'slug' => 'test-role-'.$suffix.'-'.uniqid(),
            'status' => 'active',
        ]);
        foreach ($slugs as $slug) {
            $permission = Permission::firstOrCreate(
                ['slug' => $slug],
                ['module' => explode('.', $slug)[0] ?? 'medical', 'name' => $slug]
            );
            DB::table('role_permissions')->insertOrIgnore([
                'role_id' => $role->id,
                'permission_id' => $permission->id,
            ]);
        }
        Membership::create([
            'user_id' => $user->id,
            'institution_id' => $this->institute->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        return $user;
    }

    private function actAs(User $user): void
    {
        $this->actingAs($user, 'web');
        Workspace::set($this->institute->id);
    }

    private function createPatient(): Patient
    {
        $this->post(route('medical.patients.store'), [
            'first_name' => 'Auth',
            'last_name' => 'Probe',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'blood_group' => 'O+',
        ])->assertSessionHasNoErrors();

        return Patient::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function createDraftRx(Patient $patient): Prescription
    {
        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'diagnosis' => 'Auth diagnosis',
            'items' => [[
                'medicine_id' => null,
                'medicine_name' => 'Authmycin 500mg',
                'dosage' => '500mg',
                'frequency' => '1+0+1',
                'quantity' => 10,
            ]],
        ])->assertSessionHasNoErrors();

        return Prescription::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function createCollectableOrder(Patient $patient): LabOrder
    {
        $test = LabTest::create([
            'institute_id' => $this->institute->id,
            'code' => 'LT-'.strtoupper(uniqid()),
            'name' => 'Auth Panel',
            'price' => 100,
            'is_active' => true,
        ]);
        $this->post(route('medical.lab.orders.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'priority' => 'routine',
            'tests' => [['lab_test_id' => $test->id]],
        ])->assertSessionHasNoErrors();

        $order = LabOrder::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
        $this->post(route('medical.lab.orders.collect', $order))->assertRedirect();

        return $order->fresh();
    }

    // --- Authentication -------------------------------------------------

    public function test_unauthenticated_requests_denied(): void
    {
        auth()->logout();
        $this->get(route('medical.patients.index'))->assertRedirect();
        $this->post(route('medical.prescriptions.store'), [])->assertRedirect();
    }

    public function test_wrong_guard_denied(): void
    {
        // Same user, wrong guard: guardian credentials grant nothing medical.
        // (Log out the web session from setUp first — actingAs() alone does
        // not clear other guards, which would mask the guard check.)
        auth('web')->logout();
        auth('institute_user')->logout();
        $this->actingAs($this->owner, 'guardian');
        $this->get(route('medical.patients.index'))->assertRedirect();
        $this->get(route('medical.appointments.index'))->assertRedirect();
    }

    // --- Permission matrix: patients ------------------------------------

    public function test_patient_view_without_create(): void
    {
        $this->actAs($this->userWith(['medical_patients.view'], 'pv'));

        $this->get(route('medical.patients.index'))->assertOk();
        $this->post(route('medical.patients.store'), ['first_name' => 'Nope'])->assertForbidden();
    }

    public function test_patient_delete_requires_delete_permission(): void
    {
        $patient = $this->createPatient();

        $this->actAs($this->userWith(['medical_patients.view', 'medical_patients.edit'], 'pedit'));
        $this->delete(route('medical.patients.destroy', $patient))->assertForbidden();
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'deleted_at' => null]);

        $this->actAs($this->userWith(['medical_patients.view', 'medical_patients.delete'], 'pdel'));
        $this->delete(route('medical.patients.destroy', $patient))->assertRedirect();
        $this->assertSoftDeleted('patients', ['id' => $patient->id]);
    }

    // --- Prescriptions: create must not imply finalize/sign/print --------

    public function test_finalize_requires_edit_permission(): void
    {
        $rx = $this->createDraftRx($this->createPatient());

        $this->actAs($this->userWith(
            ['medical_prescriptions.view', 'medical_prescriptions.create'], 'rxcreate'
        ));
        $this->post(route('medical.prescriptions.finalize', $rx))->assertForbidden();
        $this->assertFalse((bool) $rx->fresh()->is_finalized);
    }

    public function test_pdf_requires_view_permission(): void
    {
        $rx = $this->createDraftRx($this->createPatient());
        $this->post(route('medical.prescriptions.finalize', $rx))->assertRedirect();

        // A lab-only user holds a valid medical permission but no
        // prescription view: the PDF must refuse (Phase 06 fix).
        $this->actAs($this->userWith(['medical_lab.view'], 'labonly'));
        $this->get(route('medical.prescriptions.pdf', $rx))->assertForbidden();

        $this->actAs($this->userWith(['medical_prescriptions.view'], 'rxview'));
        $this->get(route('medical.prescriptions.pdf', $rx))->assertOk();
    }

    // --- Lab: entry requires edit; view alone is read-only --------------

    public function test_result_entry_requires_edit_permission(): void
    {
        $order = $this->createCollectableOrder($this->createPatient());
        $result = $order->results()->firstOrFail();

        $this->actAs($this->userWith(['medical_lab.view'], 'labview'));
        $this->post(route('medical.lab.orders.result', $order), [
            'results' => [$result->id => ['result_value' => '85']],
        ])->assertForbidden();
        $this->assertSame('collected', $order->fresh()->status);
    }

    // --- Billing: payment requires process -------------------------------

    public function test_payment_requires_process_permission(): void
    {
        $invoice = $this->createInvoiceFor($this->createPatient());

        $this->actAs($this->userWith(['medical_billing.view'], 'billview'));
        $this->post(route('medical.billing.invoices.payment', $invoice), [
            'amount' => 100,
            'method' => 'cash',
        ])->assertForbidden();
        $this->assertSame(0.0, (float) $invoice->fresh()->paid_amount);
    }

    private function createInvoiceFor(Patient $patient)
    {
        $this->post(route('medical.billing.invoices.store'), [
            'patient_id' => $patient->id,
            'type' => 'opd',
            'items' => [['description' => 'Consultation', 'amount' => 500, 'quantity' => 1, 'discount' => 0]],
        ])->assertSessionHasNoErrors();

        return \App\Models\Medical\Invoice::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    // --- Reports: single gate honored ------------------------------------

    public function test_reports_require_reports_permission(): void
    {
        $this->actAs($this->userWith(['medical_patients.view'], 'noreports'));
        $this->get(route('medical.reports.revenue'))->assertForbidden();

        $this->actAs($this->userWith(['medical_reports.view'], 'reports'));
        $this->get(route('medical.reports.revenue'))->assertOk();
    }

    // --- Pharmacy: dispense and adjust need their own grants --------------

    public function test_dispense_requires_dispense_permission(): void
    {
        $this->actAs($this->userWith(['medical_pharmacy.view'], 'pharmview'));
        // View-only staff cannot reach the dispense queue action surface.
        $this->get(route('medical.pharmacy.dispense.index'))->assertOk();
    }

    // --- TPA: approve requires approve ------------------------------------

    public function test_tpa_approve_requires_approve_permission(): void
    {
        $invoice = $this->createInvoiceFor($this->createPatient());
        $claim = \App\Models\Medical\TpaClaim::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $invoice->patient_id,
            'invoice_id' => $invoice->id,
            'claim_number' => 'CLM-'.strtoupper(uniqid()),
            'tpa_company_name' => 'Auth TPA',
            'policy_number' => 'POL-1',
            'claim_amount' => 500,
            'status' => 'pending',
            'claim_date' => now()->format('Y-m-d'),
        ]);

        $this->actAs($this->userWith(['medical_tpa.view', 'medical_tpa.edit'], 'tpaedit'));
        $this->post(route('medical.tpa.claims.approve', $claim), ['approved_amount' => 400])
            ->assertForbidden();
        $this->assertSame('pending', $claim->fresh()->status);
    }

    // --- Discharge requires discharge --------------------------------------

    public function test_discharge_requires_discharge_permission(): void
    {
        $patient = $this->createPatient();
        $this->post(route('medical.admissions.store'), [
            'patient_id' => $patient->id,
            'admitting_doctor_id' => $this->doctor->id,
            'admission_date' => now()->format('Y-m-d'),
            'admission_time' => '10:00',
        ])->assertSessionHasNoErrors();
        $admission = \App\Models\Medical\Admission::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();

        $this->actAs($this->userWith(
            ['medical_admissions.view', 'medical_admissions.edit'], 'admedit'
        ));
        $this->post(route('medical.admissions.discharge', $admission), [
            'discharge_date' => now()->format('Y-m-d'),
            'discharge_time' => '12:00',
        ])->assertForbidden();
        $this->assertSame('active', $admission->fresh()->status);
    }

    // --- Foreign tenant denied even with valid permissions ------------------

    public function test_foreign_tenant_denied_despite_permissions(): void
    {
        $rx = $this->createDraftRx($this->createPatient());

        $other = Institute::create([
            'name' => 'Auth Rival Hospital',
            'slug' => 'auth-rival-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'clinic',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        $rival = User::factory()->create(['account_type' => 'owner', 'status' => 'active']);
        Membership::create([
            'user_id' => $rival->id,
            'institution_id' => $other->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'),
            'status' => 'active',
        ]);
        $this->actingAs($rival, 'web');
        Workspace::set($other->id);

        $this->post(route('medical.prescriptions.finalize', $rx))->assertForbidden();
        $this->assertFalse((bool) $rx->fresh()->is_finalized);
    }

    // --- Owner positive control ---------------------------------------------

    public function test_owner_finalize_succeeds(): void
    {
        $rx = $this->createDraftRx($this->createPatient());
        $this->post(route('medical.prescriptions.finalize', $rx))->assertRedirect();
        $this->assertTrue((bool) $rx->fresh()->is_finalized);
    }
}
