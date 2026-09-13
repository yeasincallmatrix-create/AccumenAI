<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Admission;
use App\Models\Medical\Appointment;
use App\Models\Medical\Bed;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Doctor;
use App\Models\Medical\Invoice;
use App\Models\Medical\LabOrder;
use App\Models\Medical\LabTest;
use App\Models\Medical\Medicine;
use App\Models\Medical\NursingNote;
use App\Models\Medical\Patient;
use App\Models\Medical\PharmacyDispense;
use App\Models\Medical\PharmacyStock;
use App\Models\Medical\Prescription;
use App\Models\Medical\TpaClaim;
use App\Models\Medical\VitalSign;
use App\Models\Medical\Ward;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 03 — Deletion, cascade & clinical history safety.
 *
 * Proves destructive paths archive or refuse instead of vaporizing history.
 * Same de-facto pattern as the MedicalPhase suites (DatabaseTransactions on
 * the disposable test DB, web guard + Workspace context, institute-owner
 * membership; CSRF disabled only).
 */
class DeletionSafetyTest extends TestCase
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
            'name' => 'Deletion Safety Hospital',
            'slug' => 'deletion-safety-'.uniqid(),
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

    private function phone(): string
    {
        return '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT);
    }

    private function createPatient(): Patient
    {
        $this->post(route('medical.patients.store'), [
            'first_name' => 'Safety',
            'last_name' => 'Patient',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'phone' => $this->phone(),
            'blood_group' => 'O+',
        ])->assertSessionHasNoErrors();

        return Patient::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function createAdmission(Patient $patient, ?Bed $bed = null): Admission
    {
        $payload = [
            'patient_id' => $patient->id,
            'admitting_doctor_id' => $this->doctor->id,
            'admission_date' => now()->format('Y-m-d'),
            'admission_time' => '10:00',
            'primary_diagnosis' => 'Safety diagnosis',
        ];
        if ($bed) {
            $payload['bed_id'] = $bed->id;
        }
        $this->post(route('medical.admissions.store'), $payload)->assertSessionHasNoErrors();

        return Admission::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function createWardBed(): array
    {
        $ward = Ward::create([
            'institute_id' => $this->institute->id,
            'name' => 'Safety Ward '.uniqid(),
            'type' => 'general',
            'total_beds' => 4,
            'available_beds' => 4,
            'daily_rate' => 500,
            'is_active' => true,
        ]);
        $bed = Bed::create([
            'institute_id' => $this->institute->id,
            'ward_id' => $ward->id,
            'bed_number' => 'B-'.uniqid(),
            'status' => 'available',
        ]);

        return [$ward, $bed];
    }

    private function createLabOrder(Patient $patient): LabOrder
    {
        $test = LabTest::create([
            'institute_id' => $this->institute->id,
            'code' => 'LT-'.strtoupper(uniqid()),
            'name' => 'Safety Panel',
            'normal_range' => '70-100',
            'unit' => 'mg/dL',
            'price' => 300,
            'is_active' => true,
        ]);
        $this->post(route('medical.lab.orders.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'priority' => 'routine',
            'tests' => [['lab_test_id' => $test->id]],
        ])->assertSessionHasNoErrors();

        return LabOrder::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function createPrescription(Patient $patient, ?Medicine $medicine = null): Prescription
    {
        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'diagnosis' => 'Safety diagnosis',
            'items' => [[
                'medicine_id' => $medicine?->id,
                'medicine_name' => $medicine?->brand_name ?? 'Safety Free Text',
                'dosage' => '500mg',
                'frequency' => '1+0+1',
                'duration_days' => 5,
                'quantity' => 10,
            ]],
        ])->assertSessionHasNoErrors();

        return Prescription::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function createMedicine(): Medicine
    {
        return Medicine::create([
            'institute_id' => $this->institute->id,
            'code' => 'S-'.strtoupper(uniqid()),
            'generic_name' => 'Safetymycin',
            'brand_name' => 'Safetymycin 500',
            'dosage_form' => 'Tablet',
            'strength' => '500mg',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 5,
            'selling_price' => 8,
            'reorder_level' => 10,
            'reorder_quantity' => 50,
            'is_active' => true,
        ]);
    }

    private function createInvoice(Patient $patient): Invoice
    {
        $this->post(route('medical.billing.invoices.store'), [
            'patient_id' => $patient->id,
            'type' => 'opd',
            'items' => [['description' => 'Consultation', 'amount' => 500, 'quantity' => 1, 'discount' => 0]],
        ])->assertSessionHasNoErrors();

        return Invoice::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    // 1. Patient deletion preserves the whole clinical chain.
    public function test_patient_delete_preserves_clinical_chain(): void
    {
        $patient = $this->createPatient();
        $this->createAdmission($patient);
        $this->createLabOrder($patient);
        $this->createPrescription($patient);
        $this->createInvoice($patient);

        $this->delete(route('medical.patients.destroy', $patient))->assertRedirect();
        $this->assertSoftDeleted('patients', ['id' => $patient->id]);

        $this->assertSame(1, Admission::where('patient_id', $patient->id)->count());
        $this->assertSame(1, LabOrder::where('patient_id', $patient->id)->count());
        $this->assertSame(1, Prescription::where('patient_id', $patient->id)->count());
        $this->assertSame(1, Invoice::where('patient_id', $patient->id)->count());
    }

    // 2. Prescription items survive patient archival.
    public function test_patient_delete_preserves_prescription_items(): void
    {
        $rx = $this->createPrescription($this->createPatient());
        $itemId = $rx->items()->firstOrFail()->id;

        $this->delete(route('medical.patients.destroy', $rx->patient))->assertRedirect();

        $this->assertDatabaseHas('prescription_items', ['id' => $itemId]);
    }

    // 3. Lab results survive patient archival.
    public function test_patient_delete_preserves_lab_results(): void
    {
        $order = $this->createLabOrder($this->createPatient());
        $resultId = $order->results()->firstOrFail()->id;

        $this->delete(route('medical.patients.destroy', $order->patient))->assertRedirect();

        $this->assertDatabaseHas('lab_results', ['id' => $resultId]);
    }

    // 4. Admission chain (incl. vitals) survives patient archival.
    public function test_patient_delete_preserves_admission_chain(): void
    {
        $admission = $this->createAdmission($this->createPatient());
        VitalSign::create(['admission_id' => $admission->id, 'temperature' => 98.6, 'recorded_at' => now()]);

        $this->delete(route('medical.patients.destroy', $admission->patient))->assertRedirect();

        $this->assertDatabaseHas('admissions', ['id' => $admission->id]);
        $this->assertSame(1, VitalSign::where('admission_id', $admission->id)->count());
    }

    // 5. Completed orders refuse deletion; ordered orders archive with history.
    public function test_lab_order_deletion_safety(): void
    {
        // Completed order: deletion refused, everything intact.
        $done = $this->createLabOrder($this->createPatient());
        $this->post(route('medical.lab.orders.collect', $done))->assertRedirect();
        $result = $done->results()->firstOrFail();
        $this->post(route('medical.lab.orders.result', $done), [
            'results' => [$result->id => ['result_value' => '85']],
        ])->assertSessionHasNoErrors();

        $this->delete(route('medical.lab.orders.destroy', $done))->assertSessionHas('error');
        $this->assertDatabaseHas('lab_orders', ['id' => $done->id, 'deleted_at' => null]);
        $this->assertSame('85', (string) $result->fresh()->result_value);

        // Ordered order: archival, results survive and stay resolvable.
        $ordered = $this->createLabOrder($this->createPatient());
        $pendingId = $ordered->results()->firstOrFail()->id;
        $this->delete(route('medical.lab.orders.destroy', $ordered))->assertRedirect();

        $this->assertSoftDeleted('lab_orders', ['id' => $ordered->id]);
        $this->assertSame(1, \App\Models\Medical\LabResult::withTrashed()->where('lab_order_id', $ordered->id)->count());
        $this->assertDatabaseHas('lab_results', ['id' => $pendingId]);
        $this->assertSame(
            1,
            ClinicalAuditLog::where('auditable_type', LabOrder::class)
                ->where('auditable_id', $ordered->id)->where('action', 'deleted')->count()
        );
        // Hidden from the operational list, like a deleted order.
        $this->get(route('medical.lab.orders.index'))->assertOk()->assertDontSee(clinical_no($ordered->order_number));
    }

    // 6+7. Admission archival preserves vitals and nursing notes.
    public function test_admission_delete_preserves_vitals_and_notes(): void
    {
        $admission = $this->createAdmission($this->createPatient());
        $vital = VitalSign::create(['admission_id' => $admission->id, 'temperature' => 99.1, 'recorded_at' => now()]);
        $note = NursingNote::create(['admission_id' => $admission->id, 'note' => 'Safety note', 'recorded_at' => now()]);

        $this->post(route('medical.admissions.discharge', $admission), [
            'discharge_date' => now()->format('Y-m-d'),
            'discharge_time' => '12:00',
        ])->assertSessionHasNoErrors();

        $this->delete(route('medical.admissions.destroy', $admission), ['reason' => 'Safety test'])
            ->assertRedirect();

        $this->assertSoftDeleted('admissions', ['id' => $admission->id]);
        $this->assertDatabaseHas('vital_signs', ['id' => $vital->id]);
        $this->assertDatabaseHas('nursing_notes', ['id' => $note->id]);
    }

    // 8+9. Finalized prescriptions (and items) cannot be destroyed.
    public function test_finalized_prescription_delete_blocked_with_items_intact(): void
    {
        $rx = $this->createPrescription($this->createPatient());
        $itemId = $rx->items()->firstOrFail()->id;
        $this->post(route('medical.prescriptions.finalize', $rx))->assertRedirect();

        $this->delete(route('medical.prescriptions.destroy', $rx))->assertSessionHas('error');

        $this->assertDatabaseHas('prescriptions', ['id' => $rx->id]);
        $this->assertDatabaseHas('prescription_items', ['id' => $itemId, 'status' => 'pending']);
    }

    // 10. Dispense history survives; batches with dispenses cannot vanish.
    public function test_dispense_history_survives_and_batch_protected(): void
    {
        $patient = $this->createPatient();
        $medicine = $this->createMedicine();
        $stock = PharmacyStock::create([
            'institute_id' => $this->institute->id,
            'medicine_id' => $medicine->id,
            'batch_number' => 'B-'.strtoupper(uniqid()),
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'quantity_received' => 100,
            'current_quantity' => 100,
            'purchase_price' => 5,
            'selling_price' => 8,
        ]);
        $rx = $this->createPrescription($patient, $medicine);
        $this->post(route('medical.prescriptions.finalize', $rx))->assertRedirect();
        $item = $rx->items()->firstOrFail();

        $this->post(route('medical.pharmacy.dispense', $item->id), [
            'prescription_item_id' => $item->id,
            'stock_id' => $stock->id,
            'quantity_dispensed' => 10,
        ])->assertSessionHasNoErrors();
        $dispenseId = PharmacyDispense::where('prescription_item_id', $item->id)->firstOrFail()->id;

        // Prescription untouchable; dispense row intact.
        $this->delete(route('medical.prescriptions.destroy', $rx))->assertSessionHas('error');
        $this->assertDatabaseHas('pharmacy_dispenses', ['id' => $dispenseId]);

        // Batch with dispensing history cannot be deleted even at zero qty.
        $stock->update(['current_quantity' => 0]);
        $this->delete(route('medical.pharmacy.stock.destroy', $stock))->assertSessionHas('error');
        $this->assertDatabaseHas('pharmacy_stock', ['id' => $stock->id]);
        $this->assertDatabaseHas('pharmacy_dispenses', ['id' => $dispenseId]);
    }

    // 11. User purge nulls authorship instead of vaporizing clinical rows.
    public function test_user_force_delete_nulls_instead_of_vaporizing(): void
    {
        $patient = $this->createPatient();
        $this->post(route('medical.appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => now()->addDay()->format('Y-m-d'),
            'appointment_time' => '09:00',
        ])->assertSessionHasNoErrors();
        $appointmentId = Appointment::where('institute_id', $this->institute->id)->firstOrFail()->id;
        $profileId = Doctor::where('user_id', $this->doctor->id)->firstOrFail()->id;

        // Faithful simulation of the purge's final step (forceDelete fires
        // the same database-level cascades AccountDeletionService relies on).
        $this->doctor->forceDelete();

        $this->assertDatabaseHas('appointments', ['id' => $appointmentId, 'doctor_id' => null]);
        $this->assertDatabaseHas('medical_doctors', ['id' => $profileId, 'user_id' => null]);
    }

    // 12. No department/specialty destroy path exists to trigger the cascade.
    public function test_no_department_destroy_route_exists(): void
    {
        $this->assertFalse(Route::has('medical.departments.destroy'));
        $this->assertFalse(Route::has('medical.specialties.destroy'));
    }

    // 12b. Doctor profiles with clinical history cannot vanish; clean ones can.
    public function test_doctor_with_history_protected(): void
    {
        $patient = $this->createPatient();
        $this->post(route('medical.appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => now()->addDay()->format('Y-m-d'),
            'appointment_time' => '09:00',
        ])->assertSessionHasNoErrors();
        $profile = Doctor::where('user_id', $this->doctor->id)->firstOrFail();

        $this->delete(route('medical.doctors.destroy', $profile))->assertSessionHas('error');
        $this->assertDatabaseHas('medical_doctors', ['id' => $profile->id]);

        $freshUser = $this->makeUserForDoctor();
        $clean = Doctor::create([
            'institute_id' => $this->institute->id,
            'user_id' => $freshUser->id,
            'registration_number' => 'REG-C-'.strtoupper(uniqid()),
        ]);
        $this->delete(route('medical.doctors.destroy', $clean))->assertRedirect();
        $this->assertDatabaseMissing('medical_doctors', ['id' => $clean->id]);
    }

    private function makeUserForDoctor(): User
    {
        // No membership needed: profile deletion checks clinical history by
        // user id, not membership, so a bare active account suffices here.
        return User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
    }

    // 13. Beds with admission history (and wards with beds) cannot vanish.
    public function test_bed_and_ward_with_history_protected(): void
    {
        [$ward, $bed] = $this->createWardBed();
        $admission = $this->createAdmission($this->createPatient(), $bed);
        $this->post(route('medical.admissions.discharge', $admission), [
            'discharge_date' => now()->format('Y-m-d'),
            'discharge_time' => '12:00',
        ])->assertSessionHasNoErrors();

        // Bed now available but historically assigned → refused.
        $this->delete(route('medical.beds.destroy', $bed))->assertSessionHas('error');
        $this->assertDatabaseHas('beds', ['id' => $bed->id]);
        $this->assertSame($bed->id, (int) $admission->fresh()->bed_id);

        // Ward still holding beds → refused (pre-existing guard).
        $this->delete(route('medical.wards.destroy', $ward))->assertSessionHas('error');
        $this->assertDatabaseHas('wards', ['id' => $ward->id]);
    }

    // 14. Cross-tenant deletion is impossible.
    public function test_cross_tenant_delete_forbidden(): void
    {
        $patient = $this->createPatient();

        $other = Institute::create([
            'name' => 'Rival Safety Hospital',
            'slug' => 'rival-safety-'.uniqid(),
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

        $this->delete(route('medical.patients.destroy', $patient))->assertForbidden();
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'deleted_at' => null]);
        $this->assertSame(
            0,
            ClinicalAuditLog::where('auditable_type', Patient::class)->where('auditable_id', $patient->id)->count()
        );
    }

    // 15. Unauthorized deletion is denied without side effects.
    public function test_unauthorized_delete_denied(): void
    {
        $patient = $this->createPatient();
        $outsider = User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
        $this->actingAs($outsider, 'web');

        $this->delete(route('medical.patients.destroy', $patient))->assertForbidden();
        $this->assertDatabaseHas('patients', ['id' => $patient->id, 'deleted_at' => null]);
    }

    // 16. Destructive admission archival requires a reason.
    public function test_admission_delete_requires_reason(): void
    {
        $admission = $this->createAdmission($this->createPatient());
        $this->post(route('medical.admissions.discharge', $admission), [
            'discharge_date' => now()->format('Y-m-d'),
            'discharge_time' => '12:00',
        ])->assertSessionHasNoErrors();

        $this->delete(route('medical.admissions.destroy', $admission))->assertSessionHasErrors(['reason']);
        $this->assertDatabaseHas('admissions', ['id' => $admission->id, 'deleted_at' => null]);
    }

    // 17. Archival/administrative deletions are audited.
    public function test_archival_actions_audited(): void
    {
        $order = $this->createLabOrder($this->createPatient());
        $this->delete(route('medical.lab.orders.destroy', $order))->assertRedirect();
        $this->assertSame(
            1,
            ClinicalAuditLog::where('auditable_type', LabOrder::class)
                ->where('auditable_id', $order->id)->where('action', 'deleted')->count()
        );

        $medicine = $this->createMedicine();
        $this->delete(route('medical.pharmacy.medicines.destroy', $medicine))->assertRedirect();
        $row = ClinicalAuditLog::where('auditable_type', Medicine::class)
            ->where('auditable_id', $medicine->id)->where('action', 'deleted')->firstOrFail();
        $this->assertSame(0, (int) ($row->old_values['batch_count'] ?? -1));
    }

    // 18. Soft-deleted rows stay out of operational queries.
    public function test_soft_deleted_hidden_operationally(): void
    {
        $admission = $this->createAdmission($this->createPatient());
        $vital = VitalSign::create(['admission_id' => $admission->id, 'temperature' => 98.6, 'recorded_at' => now()]);
        $vital->delete();

        $this->assertSame(0, VitalSign::where('admission_id', $admission->id)->count());
        $this->assertSame(1, VitalSign::withTrashed()->where('admission_id', $admission->id)->count());
        $this->get(route('medical.vitals.index', ['admission_id' => $admission->id]))
            ->assertOk()->assertDontSee('98.6');
    }

    // 19. Restoration is technically possible; no HTTP restore exists by design.
    public function test_restore_capability_documented(): void
    {
        $this->assertFalse(Route::has('medical.vitals.restore'));
        $this->assertFalse(Route::has('medical.admissions.restore'));
        $this->assertFalse(Route::has('medical.lab.orders.restore'));

        // Model-level restore works (disaster-recovery path via console/tinker
        // with audit); the application intentionally offers no restore button
        // yet — archival is the terminal operational state.
        $admission = $this->createAdmission($this->createPatient());
        $admission->delete();
        $this->assertTrue((bool) $admission->restore());
        $this->assertDatabaseHas('admissions', ['id' => $admission->id, 'deleted_at' => null]);
    }

    // 20. Invoice with linked claim resists deletion; paid invoices resist too.
    public function test_invoice_with_claim_delete_blocked(): void
    {
        $invoice = $this->createInvoice($this->createPatient());
        TpaClaim::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $invoice->patient_id,
            'invoice_id' => $invoice->id,
            'claim_number' => 'CLM-'.strtoupper(uniqid()),
            'tpa_company_name' => 'Safety TPA',
            'policy_number' => 'POL-1',
            'claim_amount' => 500,
            'status' => 'pending',
            'claim_date' => now()->format('Y-m-d'),
        ]);

        $this->delete(route('medical.billing.invoices.destroy', $invoice))->assertSessionHas('error');
        $this->assertDatabaseHas('medical_invoices', ['id' => $invoice->id]);
        $this->assertSame(1, TpaClaim::where('invoice_id', $invoice->id)->count());
    }
}
