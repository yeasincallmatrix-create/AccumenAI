<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Appointment;
use App\Models\Medical\Doctor;
use App\Models\Medical\LabOrder;
use App\Models\Medical\LabTest;
use App\Models\Medical\Medicine;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\Patient;
use App\Models\Medical\PatientAllergy;
use App\Models\Medical\PrescriptionAuditLog;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 05 — Constraint, index & schema integrity hardening.
 *
 * Proves the database (not just application code) enforces: new FKs,
 * the appointment serial unique, per-tenant clinical-number uniques,
 * sequence-row isolation, and that index-sensitive lookup workflows still
 * function. Runs against the disposable monetix_test database only.
 */
class SchemaIntegrityTest extends TestCase
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
            'name' => 'Schema Integrity Hospital',
            'slug' => 'schema-integrity-'.uniqid(),
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

    private function createPatient(string $phone): Patient    {
        $this->post(route('medical.patients.store'), [
            'first_name' => 'Schema',
            'last_name' => 'Probe',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'phone' => $phone,
            'blood_group' => 'O+',
        ])->assertSessionHasNoErrors();

        return Patient::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    // FK: valid allergy row succeeds.
    public function test_allergy_valid_fk_succeeds(): void
    {
        $patient = $this->createPatient('01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT));

        $allergy = PatientAllergy::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $patient->id,
            'medicine_id' => null,
            'allergen_type' => 'drug',
            'allergen_name' => 'Penicillin',
            'severity' => 'severe',
        ]);

        $this->assertDatabaseHas('patient_allergies', ['id' => $allergy->id]);
    }

    // FK: bogus institute rejected at the database level.
    public function test_allergy_invalid_institute_rejected(): void
    {
        $patient = $this->createPatient('01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT));

        $this->expectException(QueryException::class);
        PatientAllergy::create([
            'institute_id' => 999999999,
            'patient_id' => $patient->id,
            'allergen_type' => 'drug',
            'allergen_name' => 'Penicillin',
        ]);
    }

    // FK: bogus medicine rejected at the database level.
    public function test_allergy_invalid_medicine_rejected(): void
    {
        $patient = $this->createPatient('01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT));

        $this->expectException(QueryException::class);
        PatientAllergy::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $patient->id,
            'medicine_id' => 999999999,
            'allergen_type' => 'drug',
            'allergen_name' => 'Penicillin',
        ]);
    }

    // FK: audit row with bogus institute rejected.
    public function test_audit_invalid_institute_rejected(): void
    {
        $this->expectException(QueryException::class);
        PrescriptionAuditLog::create([
            'institute_id' => 999999999,
            'prescription_id' => 999999999,
            'action' => 'probe',
        ]);
    }

    // SET NULL: medicine deletion nulls allergy linkage, row survives.
    public function test_medicine_delete_nulls_allergy_link(): void
    {
        $patient = $this->createPatient('01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT));
        $medicine = Medicine::create([
            'institute_id' => $this->institute->id,
            'code' => 'S-'.strtoupper(uniqid()),
            'generic_name' => 'Nullmycin',
            'dosage_form' => 'Tablet',
            'unit' => 'Strip',
            'pack_size' => 10,
            'purchase_price' => 1,
            'selling_price' => 2,
            'reorder_level' => 1,
            'reorder_quantity' => 5,
            'is_active' => true,
        ]);
        $allergy = PatientAllergy::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $patient->id,
            'medicine_id' => $medicine->id,
            'allergen_type' => 'drug',
            'allergen_name' => 'Nullmycin',
        ]);

        $this->delete(route('medical.pharmacy.medicines.destroy', $medicine))->assertRedirect();

        $this->assertDatabaseHas('patient_allergies', ['id' => $allergy->id, 'medicine_id' => null]);
    }

    // Unique: duplicate serial for same doctor/day refused.
    public function test_duplicate_serial_refused(): void
    {
        $patient = $this->createPatient('01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT));
        $base = [
            'institute_id' => $this->institute->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => now()->addDay()->format('Y-m-d'),
            'appointment_time' => '09:00',
            'serial_number' => 7,
            'status' => 'scheduled',
        ];
        Appointment::create($base);

        $this->expectException(QueryException::class);
        Appointment::create($base);
    }

    // Unique is correctly scoped: other doctors/days may reuse the serial.
    public function test_serial_unique_scoped_correctly(): void
    {
        $patient = $this->createPatient('01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT));
        $otherDoctor = User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
        Doctor::create([
            'institute_id' => $this->institute->id,
            'user_id' => $otherDoctor->id,
            'registration_number' => 'REG-'.strtoupper(uniqid()),
        ]);

        Appointment::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => now()->addDay()->format('Y-m-d'),
            'appointment_time' => '09:00',
            'serial_number' => 3,
            'status' => 'scheduled',
        ]);
        // Same serial, different doctor → allowed.
        Appointment::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $patient->id,
            'doctor_id' => $otherDoctor->id,
            'appointment_date' => now()->addDay()->format('Y-m-d'),
            'appointment_time' => '09:00',
            'serial_number' => 3,
            'status' => 'scheduled',
        ]);
        // Same serial, different day → allowed.
        Appointment::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => now()->addDays(2)->format('Y-m-d'),
            'appointment_time' => '09:00',
            'serial_number' => 3,
            'status' => 'scheduled',
        ]);

        $this->assertSame(3, Appointment::where('institute_id', $this->institute->id)->count());
    }

    // MR uniqueness is per tenant (composite institute_id + mr_number):
    // duplicates inside one tenant are refused, the same number in a
    // different tenant is allowed.
    public function test_mr_unique_per_tenant(): void
    {
        $patient = $this->createPatient('01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT));

        try {
            Patient::create([
                'institute_id' => $this->institute->id,
                'mr_number' => $patient->mr_number,
                'first_name' => 'Duplicate',
                'gender' => 'male',
                'phone' => '0100000099',
            ]);
            $this->fail('Same-tenant duplicate MR number was accepted.');
        } catch (QueryException) {
            // Expected: composite unique enforced.
        }

        $other = Institute::create([
            'name' => 'Schema Integrity Hospital B',
            'slug' => 'schema-integrity-b-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'hospital',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        $cross = Patient::create([
            'institute_id' => $other->id,
            'mr_number' => $patient->mr_number,
            'first_name' => 'Cross',
            'last_name' => 'Tenant',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'phone' => '0100000098',
        ]);
        $this->assertSame($patient->mr_number, $cross->mr_number);
    }

    // Legacy MR format coexists with the new sequences.
    public function test_legacy_mr_remains_valid(): void
    {
        $legacy = Patient::create([
            'institute_id' => $this->institute->id,
            'mr_number' => '26474',
            'first_name' => 'Legacy',
            'last_name' => 'Row',
            'date_of_birth' => '1980-01-01',
            'gender' => 'male',
            'phone' => '0100000098',
        ]);

        $this->assertDatabaseHas('patients', ['id' => $legacy->id, 'mr_number' => '26474']);
    }

    // Sequence rows: same tenant/type/year duplicates refused.
    public function test_sequence_row_unique(): void
    {
        $year = (int) now()->format('Y');
        NumberSequence::create([
            'institute_id' => $this->institute->id,
            'sequence_type' => NumberSequence::TYPE_MR,
            'year' => $year,
            'last_number' => 0,
        ]);

        $this->expectException(QueryException::class);
        NumberSequence::create([
            'institute_id' => $this->institute->id,
            'sequence_type' => NumberSequence::TYPE_MR,
            'year' => $year,
            'last_number' => 0,
        ]);
    }

    // Index-sensitive lookup workflows still function.
    public function test_lookup_workflows_function(): void
    {
        $patient = $this->createPatient('01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT));

        $this->get(route('medical.patients.index', ['search' => $patient->mr_number]))->assertOk();
        $this->get(route('medical.appointments.index'))->assertOk();
        $this->get(route('medical.appointments.create'))->assertOk();
        $this->get(route('medical.prescriptions.create'))->assertOk();
        $this->get(route('medical.lab.orders.index'))->assertOk();
        $this->get(route('medical.billing.invoices.index'))->assertOk();
        $this->get(route('medical.pharmacy.medicines.index'))->assertOk();
        $this->get(route('medical.pharmacy.dispense.index'))->assertOk();
    }

    // Tenant negatives hold after schema changes (lab order show).
    public function test_cross_tenant_lab_order_denied(): void
    {
        $other = Institute::create([
            'name' => 'Schema Rival Hospital',
            'slug' => 'schema-rival-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'clinic',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $foreignOrder = LabOrder::create([
            'institute_id' => $other->id,
            'patient_id' => $this->createPatient('01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT))->id,
            'doctor_id' => $this->doctor->id,
            'order_number' => 'LAB-X-1',
            'order_date' => now()->format('Y-m-d'),
            'status' => 'ordered',
        ]);
        // Repair the deliberately cross-wired patient link for the negative:
        // order belongs to B, patient to A — show must refuse via order scope.
        $this->get(route('medical.lab.orders.show', $foreignOrder))->assertForbidden();
    }
}
