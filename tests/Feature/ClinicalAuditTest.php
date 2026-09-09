<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Admission;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Doctor;
use App\Models\Medical\LabOrder;
use App\Models\Medical\LabTest;
use App\Models\Medical\NursingNote;
use App\Models\Medical\Patient;
use App\Models\Medical\Prescription;
use App\Models\Medical\VitalSign;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 01 — Clinical record integrity verification.
 *
 * Same de-facto pattern as the MedicalPhase suites: DatabaseTransactions on
 * the disposable test DB, web guard + Workspace context, institute-owner
 * membership. CSRF disabled for HTTP calls only; auth, tenant, medical
 * domain and permission middleware all still run.
 *
 * Existing tests are NOT modified; drift (if any) is reported, not hidden.
 */
class ClinicalAuditTest extends TestCase
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
            'name' => 'Clinical Audit Test Hospital',
            'slug' => 'clinical-audit-test-'.uniqid(),
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
        // profile in the institute (mirrors production onboarding).
        Doctor::create([
            'institute_id' => $this->institute->id,
            'user_id' => $this->doctor->id,
            'registration_number' => 'REG-'.strtoupper(uniqid()),
        ]);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
    }

    private function patientPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Audit',
            'last_name' => 'Patient',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'blood_group' => 'O+',
        ], $overrides);
    }

    private function createPatient(array $overrides = []): Patient
    {
        $this->post(route('medical.patients.store'), $this->patientPayload($overrides))
            ->assertSessionHasNoErrors();

        return Patient::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function createAdmission(Patient $patient): Admission
    {
        $this->post(route('medical.admissions.store'), [
            'patient_id' => $patient->id,
            'admitting_doctor_id' => $this->doctor->id,
            'admission_date' => now()->format('Y-m-d'),
            'admission_time' => '10:00',
            'primary_diagnosis' => 'Audit diagnosis',
        ])->assertSessionHasNoErrors();

        return Admission::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function createLabOrder(Patient $patient): LabOrder
    {
        $test = LabTest::create([
            'institute_id' => $this->institute->id,
            'code' => 'LT-'.strtoupper(uniqid()),
            'name' => 'Audit Panel',
            'category' => 'Biochemistry',
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

    private function createPrescription(Patient $patient): Prescription
    {
        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'diagnosis' => 'Audit diagnosis',
            'items' => [[
                'medicine_id' => null,
                'medicine_name' => 'Auditmycin 500mg',
                'dosage' => '500mg',
                'frequency' => '1+0+1',
                'duration_days' => 5,
                'quantity' => 10,
            ]],
        ])->assertSessionHasNoErrors();

        return Prescription::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function auditFor(string $type, int $id)
    {
        return ClinicalAuditLog::where('auditable_type', $type)
            ->where('auditable_id', $id);
    }

    public function test_patient_update_creates_audit_with_old_and_new(): void
    {
        $patient = $this->createPatient(['allergies' => 'Penicillin', 'chronic_conditions' => 'Asthma']);

        $this->put(route('medical.patients.update', $patient), $this->patientPayload([
            'phone' => $patient->phone,
            'allergies' => 'Penicillin, Sulfa',
            'chronic_conditions' => 'Asthma',
        ]))->assertSessionHasNoErrors();

        $row = $this->auditFor(Patient::class, $patient->id)->where('action', 'updated')->firstOrFail();
        $this->assertSame($this->institute->id, (int) $row->institute_id);
        $this->assertSame($patient->id, (int) $row->patient_id);
        $this->assertSame('Penicillin', $row->old_values['allergies']);
        $this->assertSame('Penicillin, Sulfa', $row->new_values['allergies']);
    }

    public function test_audit_rows_are_tenant_scoped(): void
    {
        $patient = $this->createPatient();
        $this->put(route('medical.patients.update', $patient), $this->patientPayload([
            'first_name' => 'Scoped',
            'phone' => $patient->phone,
        ]))->assertSessionHasNoErrors();

        // A second institute sees none of the first institute's audit trail.
        $other = Institute::create([
            'name' => 'Other Audit Hospital',
            'slug' => 'other-audit-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'clinic',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        $this->assertSame(0, ClinicalAuditLog::where('institute_id', $other->id)->count());
        $this->assertSame(1, ClinicalAuditLog::where('institute_id', $this->institute->id)->where('action', 'updated')->count());
    }

    public function test_patient_delete_keeps_attributable_snapshot(): void
    {
        $patient = $this->createPatient(['first_name' => 'Vanishing']);

        $this->delete(route('medical.patients.destroy', $patient))->assertRedirect();
        $this->assertSoftDeleted('patients', ['id' => $patient->id]);

        $row = $this->auditFor(Patient::class, $patient->id)->where('action', 'deleted')->firstOrFail();
        $this->assertSame('Vanishing', $row->old_values['first_name']);
        $this->assertSame($patient->phone, $row->old_values['phone']);
    }

    public function test_finalized_prescription_update_blocked_and_unaudited(): void
    {
        $rx = $this->createPrescription($this->createPatient());
        $this->post(route('medical.prescriptions.finalize', $rx))->assertRedirect();
        $this->assertTrue((bool) $rx->fresh()->is_finalized);

        $this->put(route('medical.prescriptions.update', $rx), [
            'patient_id' => $rx->patient_id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'diagnosis' => 'Tampered diagnosis',
            'items' => [[
                'medicine_id' => null,
                'medicine_name' => 'Tampered',
                'dosage' => '1mg',
                'frequency' => '1+0+0',
                'quantity' => 1,
            ]],
        ])->assertSessionHas('error');

        $this->assertSame('Audit diagnosis', $rx->fresh()->diagnosis);
        $this->assertSame(
            0,
            $this->auditFor(Prescription::class, $rx->id)->where('action', 'draft_updated')->count()
        );
    }

    public function test_completed_lab_result_reentry_blocked_and_entered_values_audited(): void
    {
        $order = $this->createLabOrder($this->createPatient());
        $this->post(route('medical.lab.orders.collect', $order))->assertRedirect();

        $result = $order->results()->firstOrFail();
        $this->post(route('medical.lab.orders.result', $order), [
            'results' => [$result->id => ['result_value' => '85']],
        ])->assertSessionHasNoErrors();
        $this->assertSame('completed', $order->fresh()->status);

        $entry = $this->auditFor(LabOrder::class, $order->id)->where('action', 'result_entered')->firstOrFail();
        $this->assertSame('85', (string) $entry->new_values[$result->id]['result_value']);
        $this->assertSame('normal', $entry->new_values[$result->id]['status']);

        // Second entry is refused and changes nothing.
        $this->post(route('medical.lab.orders.result', $order), [
            'results' => [$result->id => ['result_value' => '250']],
        ])->assertSessionHas('error');
        $this->assertSame('85', (string) $result->fresh()->result_value);
        $this->assertSame(
            1,
            $this->auditFor(LabOrder::class, $order->id)->where('action', 'result_entered')->count()
        );
    }

    public function test_finalized_prescription_delete_blocked(): void
    {
        $rx = $this->createPrescription($this->createPatient());
        $this->post(route('medical.prescriptions.finalize', $rx))->assertRedirect();

        $this->delete(route('medical.prescriptions.destroy', $rx))->assertSessionHas('error');
        $this->assertDatabaseHas('prescriptions', ['id' => $rx->id]);
    }

    public function test_unauthorized_update_creates_no_audit(): void
    {
        $patient = $this->createPatient();

        $outsider = User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
        $this->actingAs($outsider, 'web');

        $this->put(route('medical.patients.update', $patient), $this->patientPayload([
            'first_name' => 'Intruder',
            'phone' => $patient->phone,
        ]))->assertForbidden();

        $this->assertSame('Audit', $patient->fresh()->first_name);
        $this->assertSame(0, $this->auditFor(Patient::class, $patient->id)->count());
    }

    public function test_audit_attributes_actor(): void
    {
        $patient = $this->createPatient();
        $this->put(route('medical.patients.update', $patient), $this->patientPayload([
            'first_name' => 'Attributed',
            'phone' => $patient->phone,
        ]))->assertSessionHasNoErrors();

        $row = $this->auditFor(Patient::class, $patient->id)->where('action', 'updated')->firstOrFail();
        $this->assertSame($this->owner->id, (int) $row->user_id);
        $this->assertSame($this->owner->name, $row->actor_name);
        $this->assertSame('user', $row->user_type);
        $this->assertArrayHasKey('created_at', $row->toArray());
    }

    public function test_admission_delete_requires_reason_and_preserves_history(): void
    {
        $admission = $this->createAdmission($this->createPatient());
        $vital = VitalSign::create([
            'admission_id' => $admission->id,
            'temperature' => 98.6,
            'recorded_at' => now(),
        ]);
        $note = NursingNote::create([
            'admission_id' => $admission->id,
            'note' => 'History note',
            'recorded_at' => now(),
        ]);

        // Discharge first (delete is discharged-only).
        $this->post(route('medical.admissions.discharge', $admission), [
            'discharge_date' => now()->format('Y-m-d'),
            'discharge_time' => '12:00',
        ])->assertSessionHasNoErrors();
        $this->assertSame(
            1,
            $this->auditFor(Admission::class, $admission->id)->where('action', 'discharged')->count()
        );

        // No reason → refused, record intact.
        $this->delete(route('medical.admissions.destroy', $admission))->assertSessionHasErrors(['reason']);
        $this->assertDatabaseHas('admissions', ['id' => $admission->id, 'deleted_at' => null]);

        // With reason → archived, audit carries the reason, history survives.
        $this->delete(route('medical.admissions.destroy', $admission), [
            'reason' => 'Duplicate admission entry',
        ])->assertRedirect();
        $this->assertSoftDeleted('admissions', ['id' => $admission->id]);
        $this->assertDatabaseHas('vital_signs', ['id' => $vital->id]);
        $this->assertDatabaseHas('nursing_notes', ['id' => $note->id]);

        $row = $this->auditFor(Admission::class, $admission->id)->where('action', 'deleted')->firstOrFail();
        $this->assertSame('Duplicate admission entry', $row->reason);
        $this->assertSame(1, (int) $row->old_values['vital_signs_count']);
        $this->assertSame(1, (int) $row->old_values['nursing_notes_count']);
    }

    public function test_cross_tenant_update_forbidden_and_unaudited(): void
    {
        $patient = $this->createPatient();

        $other = Institute::create([
            'name' => 'Rival Hospital',
            'slug' => 'rival-'.uniqid(),
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

        $this->put(route('medical.patients.update', $patient), $this->patientPayload([
            'first_name' => 'Rival',
            'phone' => $patient->phone,
        ]))->assertForbidden();

        $this->assertSame('Audit', $patient->fresh()->first_name);
        $this->assertSame(0, $this->auditFor(Patient::class, $patient->id)->count());
    }
}
