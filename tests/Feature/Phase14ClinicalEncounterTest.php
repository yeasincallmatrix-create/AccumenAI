<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Appointment;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Doctor;
use App\Models\Medical\Encounter;
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
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Phase 14 — Clinical encounter & care continuity foundation.
 *
 * Proves the visit→documentation→orders→completion workflow with tenant
 * isolation, numbering, lifecycle guards, prescription/lab linkage without
 * history rewrites, audited amendments, and permission enforcement.
 */
class Phase14ClinicalEncounterTest extends TestCase
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
            'name' => 'Encounter Test Hospital',
            'slug' => 'encounter-test-'.uniqid(),
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

    private function createPatient(string $first = 'Encounter'): Patient
    {
        $this->post(route('medical.patients.store'), [
            'first_name' => $first,
            'last_name' => 'Probe',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'blood_group' => 'O+',
        ])->assertSessionHasNoErrors();

        return Patient::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function bookAppointment(Patient $patient, string $date = null): Appointment
    {
        $this->post(route('medical.appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $date ?? now()->addDay()->format('Y-m-d'),
            'appointment_time' => '09:00',
        ])->assertSessionHasNoErrors();

        return Appointment::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function openEncounter(Patient $patient, array $overrides = []): Encounter
    {
        $response = $this->post(route('medical.encounters.store'), array_merge([
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'encounter_type' => 'OPD',
            'chief_complaint' => 'Fever for two days',
        ], $overrides));
        $response->assertSessionHasNoErrors();

        return Encounter::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    // --- Creation -----------------------------------------------------------------

    public function test_walk_in_creation(): void
    {
        $encounter = $this->openEncounter($this->createPatient());

        $this->assertSame('open', $encounter->status);
        $this->assertNull($encounter->appointment_id);
        $this->assertMatchesRegularExpression(
            '/^ENC-\d{4}-\d{5}$/',
            $encounter->encounter_number
        );
        $this->get(route('medical.encounters.show', $encounter))->assertOk();
    }

    public function test_appointment_linked_creation_requires_checkin(): void
    {
        $patient = $this->createPatient();
        $appointment = $this->bookAppointment($patient);

        // Scheduled (not checked in): refused.
        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'encounter_type' => 'OPD',
            'appointment_id' => $appointment->id,
        ])->assertSessionHas('error');
        $this->assertSame(0, Encounter::where('institute_id', $this->institute->id)->count());

        // Checked in: linked.
        $this->post(route('medical.appointments.checkin', $appointment))->assertRedirect();
        $encounter = $this->openEncounter($patient, ['appointment_id' => $appointment->id]);
        $this->assertSame($appointment->id, (int) $encounter->appointment_id);
    }

    public function test_duplicate_encounter_for_appointment_prevented(): void
    {
        $patient = $this->createPatient();
        $appointment = $this->bookAppointment($patient);
        $this->post(route('medical.appointments.checkin', $appointment))->assertRedirect();
        $this->openEncounter($patient, ['appointment_id' => $appointment->id]);

        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'encounter_type' => 'OPD',
            'appointment_id' => $appointment->id,
        ])->assertSessionHas('error');
        $this->assertSame(1, Encounter::where('appointment_id', $appointment->id)->count());

        // DB-level backstop holds too.
        $this->expectException(\Illuminate\Database\QueryException::class);
        Encounter::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'encounter_number' => 'ENC-DUP-1',
        ]);
    }

    public function test_wrong_patient_appointment_rejected(): void
    {
        $patient = $this->createPatient('Owner');
        $other = $this->createPatient('Other');
        $appointment = $this->bookAppointment($other);
        $this->post(route('medical.appointments.checkin', $appointment))->assertRedirect();

        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'encounter_type' => 'OPD',
            'appointment_id' => $appointment->id,
        ])->assertSessionHas('error');
    }

    public function test_foreign_doctor_rejected(): void
    {
        $outsider = User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
        $patient = $this->createPatient();

        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $outsider->id,
            'encounter_type' => 'OPD',
        ])->assertSessionHasErrors(['doctor_id']);
    }

    // --- Numbering --------------------------------------------------------------------

    public function test_numbering_sequential_and_tenant_isolated(): void
    {
        $patient = $this->createPatient();
        $first = $this->openEncounter($patient);
        $second = $this->openEncounter($patient);

        $this->assertSame(
            (int) substr($first->encounter_number, -5) + 1,
            (int) substr($second->encounter_number, -5)
        );

        $other = Institute::create([
            'name' => 'Encounter Rival', 'slug' => 'encounter-rival-'.uniqid(),
            'industry' => 'healthcare', 'sub_industry' => 'clinic',
            'country' => 'Bangladesh', 'status' => 'active',
        ]);
        $this->assertSame(
            0,
            Encounter::where('institute_id', $other->id)->count()
        );
        // Same suffix space, different tenant segment: globally unique.
        $this->assertSame(
            1,
            Encounter::where('encounter_number', $first->encounter_number)->count()
        );
    }

    // --- Documentation & lifecycle ----------------------------------------------------------

    public function test_notes_saved_and_editable_while_open(): void
    {
        $encounter = $this->openEncounter($this->createPatient());

        $this->put(route('medical.encounters.update', $encounter), [
            'patient_id' => $encounter->patient_id,
            'doctor_id' => $encounter->doctor_id,
            'encounter_type' => 'OPD',
            'chief_complaint' => 'Fever',
            'assessment_notes' => 'Viral fever, advise rest',
            'plan_notes' => 'Paracetamol SOS',
        ])->assertSessionHasNoErrors();

        $encounter->refresh();
        $this->assertSame('Viral fever, advise rest', $encounter->assessment_notes);
        $this->get(route('medical.encounters.edit', $encounter))->assertOk();
    }

    public function test_start_complete_lifecycle_with_guards(): void
    {
        $encounter = $this->openEncounter($this->createPatient());

        // Open → completed directly is illegal; must start first.
        $this->post(route('medical.encounters.complete', $encounter))->assertSessionHas('error');
        $this->assertSame('open', $encounter->fresh()->status);

        $this->post(route('medical.encounters.start', $encounter))->assertRedirect();
        $this->assertSame('in_progress', $encounter->fresh()->status);
        $this->assertNotNull($encounter->fresh()->started_at);

        $this->post(route('medical.encounters.complete', $encounter))->assertRedirect();
        $encounter->refresh();
        $this->assertSame('completed', $encounter->status);
        $this->assertNotNull($encounter->completed_at);

        // Completed rows reject edits, completion and cancellation alike.
        $this->put(route('medical.encounters.update', $encounter), [
            'patient_id' => $encounter->patient_id,
            'doctor_id' => $encounter->doctor_id,
            'encounter_type' => 'OPD',
        ])->assertSessionHas('error');
        $this->post(route('medical.encounters.cancel', $encounter))->assertSessionHas('error');
        $this->assertSame('completed', $encounter->fresh()->status);
    }

    public function test_cancel_open_encounter(): void
    {
        $encounter = $this->openEncounter($this->createPatient());
        $this->post(route('medical.encounters.cancel', $encounter))->assertRedirect();

        $this->assertSame('cancelled', $encounter->fresh()->status);
        $this->assertDatabaseHas('medical_encounters', ['id' => $encounter->id]);
    }

    // --- Amendment -------------------------------------------------------------------------------

    public function test_amend_requires_reason_and_audits(): void
    {
        $encounter = $this->openEncounter($this->createPatient());
        $this->post(route('medical.encounters.start', $encounter))->assertRedirect();
        $this->post(route('medical.encounters.complete', $encounter))->assertRedirect();

        $this->post(route('medical.encounters.amend', $encounter), [
            'assessment_notes' => 'Changed without reason',
        ])->assertSessionHasErrors(['reason']);
        $this->assertNull($encounter->fresh()->assessment_notes);

        $this->post(route('medical.encounters.amend', $encounter), [
            'reason' => 'Typo in assessment',
            'assessment_notes' => 'Viral fever confirmed',
        ])->assertRedirect();

        $encounter->refresh();
        $this->assertSame('Viral fever confirmed', $encounter->assessment_notes);
        $this->assertSame('completed', $encounter->status);
        $row = ClinicalAuditLog::where('auditable_type', Encounter::class)
            ->where('auditable_id', $encounter->id)
            ->where('action', 'amended')
            ->firstOrFail();
        $this->assertSame('Typo in assessment', $row->reason);
        $this->assertArrayHasKey('assessment_notes', $row->new_values);
    }

    // --- Prescription integration --------------------------------------------------------------------------

    public function test_prescription_links_and_safety_intact(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);

        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'encounter_id' => $encounter->id,
            'items' => [[
                'medicine_id' => null,
                'medicine_name' => 'Encountermycin 500mg',
                'dosage' => '500mg',
                'frequency' => '1+0+1',
                'quantity' => 10,
            ]],
        ])->assertSessionHasNoErrors();

        $rx = Prescription::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
        $this->assertSame($encounter->id, (int) $rx->encounter_id);
        $this->assertSame(1, $encounter->prescriptions()->count());
        $this->assertNotNull($rx->items()->firstOrFail()->medicine_name);

        // Safety pipeline still guards encounter-linked scripts: allergic
        // patient + matching allergen is refused, nothing persisted.
        $rx->delete();
        $allergic = $this->createPatient('Allergic');
        $allergic->update(['allergies' => 'Encountermycin 500mg']);
        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $allergic->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'encounter_id' => $encounter->id,
            'items' => [[
                'medicine_id' => null,
                'medicine_name' => 'Encountermycin 500mg',
                'dosage' => '500mg',
                'frequency' => '1+0+1',
                'quantity' => 10,
            ]],
        ])->assertSessionHas('error');
    }

    public function test_prescription_wrong_patient_encounter_rejected(): void
    {
        $patient = $this->createPatient('Owner2');
        $other = $this->createPatient('Other2');
        $encounter = $this->openEncounter($other);

        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'encounter_id' => $encounter->id,
            'items' => [[
                'medicine_id' => null,
                'medicine_name' => 'X 10mg',
                'dosage' => '10mg',
                'frequency' => '1+0+0',
                'quantity' => 1,
            ]],
        ])->assertSessionHas('error');
    }

    // --- Lab integration ---------------------------------------------------------------------------------------

    public function test_lab_order_links_and_results_intact(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $test = \App\Models\Medical\LabTest::create([
            'institute_id' => $this->institute->id,
            'code' => 'LT-'.strtoupper(uniqid()),
            'name' => 'Encounter Panel',
            'price' => 100,
            'is_active' => true,
        ]);

        $this->post(route('medical.lab.orders.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'priority' => 'routine',
            'encounter_id' => $encounter->id,
            'tests' => [['lab_test_id' => $test->id]],
        ])->assertSessionHasNoErrors();

        $order = \App\Models\Medical\LabOrder::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
        $this->assertSame($encounter->id, (int) $order->encounter_id);
        $this->assertSame(1, $encounter->labOrders()->count());

        $this->post(route('medical.lab.orders.collect', $order))->assertRedirect();
        $result = $order->results()->firstOrFail();
        $this->post(route('medical.lab.orders.result', $order), [
            'results' => [$result->id => ['result_value' => '80']],
        ])->assertSessionHasNoErrors();
        $this->assertSame('completed', $order->fresh()->status);
    }

    // --- IPD ---------------------------------------------------------------------------------------------------------

    public function test_ipd_encounter_requires_admission(): void
    {
        $patient = $this->createPatient();

        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'encounter_type' => 'IPD',
        ])->assertSessionHasErrors(['admission_id']);

        $admission = $this->postAndGetAdmission($patient);
        $encounter = $this->openEncounter($patient, [
            'encounter_type' => 'IPD',
            'admission_id' => $admission->id,
        ]);
        $this->assertSame($admission->id, (int) $encounter->admission_id);
        // Admission itself is untouched by encounter creation.
        $this->assertDatabaseHas('admissions', ['id' => $admission->id, 'deleted_at' => null]);
    }

    private function postAndGetAdmission(Patient $patient): \App\Models\Medical\Admission
    {
        $this->post(route('medical.admissions.store'), [
            'patient_id' => $patient->id,
            'admitting_doctor_id' => $this->doctor->id,
            'admission_date' => now()->format('Y-m-d'),
            'admission_time' => '10:00',
            'primary_diagnosis' => 'Encounter IPD',
        ])->assertSessionHasNoErrors();

        return \App\Models\Medical\Admission::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    // --- Tenant security -----------------------------------------------------------------------------------------------

    public function test_cross_tenant_encounter_barrier(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);

        $other = Institute::create([
            'name' => 'Encounter Rival', 'slug' => 'encounter-rival-'.uniqid(),
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

        // Detail, completion and patient linkage all refuse foreign rows.
        $this->get(route('medical.encounters.show', $encounter))->assertForbidden();
        $this->post(route('medical.encounters.complete', $encounter))->assertForbidden();
        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'encounter_type' => 'OPD',
        ])->assertSessionHasErrors(['patient_id']);
        $this->assertSame('open', $encounter->fresh()->status);
    }

    // --- Authorization ------------------------------------------------------------------------------------------------------

    public function test_encounter_permissions_enforced(): void
    {
        $encounter = $this->openEncounter($this->createPatient());

        $viewer = $this->roleWith(['medical_encounters.view']);
        $this->actingAs($viewer, 'web');
        Workspace::set($this->institute->id);
        $this->get(route('medical.encounters.index'))->assertOk();
        $this->get(route('medical.encounters.show', $encounter))->assertOk();
        $this->post(route('medical.encounters.store'), [
            'patient_id' => $encounter->patient_id,
            'doctor_id' => $this->doctor->id,
            'encounter_type' => 'OPD',
        ])->assertForbidden();
        $this->post(route('medical.encounters.complete', $encounter))->assertForbidden();

        $completer = $this->roleWith([
            'medical_encounters.view', 'medical_encounters.create',
            'medical_encounters.edit', 'medical_encounters.complete',
        ]);
        $this->actingAs($completer, 'web');
        Workspace::set($this->institute->id);
        $this->post(route('medical.encounters.start', $encounter))->assertRedirect();
        $this->post(route('medical.encounters.complete', $encounter))->assertRedirect();
        $this->assertSame('completed', $encounter->fresh()->status);

        // Amend needs its own grant.
        $this->post(route('medical.encounters.amend', $encounter), [
            'reason' => 'No grant',
        ])->assertForbidden();
    }

    private function roleWith(array $slugs): User
    {
        $user = User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
        $role = Role::create([
            'institute_id' => $this->institute->id,
            'name' => 'Encounter Role '.uniqid(),
            'slug' => 'encounter-role-'.uniqid(),
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

    // --- Audit --------------------------------------------------------------------------------------------------------------------

    public function test_lifecycle_audited(): void
    {
        $encounter = $this->openEncounter($this->createPatient());
        $this->post(route('medical.encounters.start', $encounter))->assertRedirect();
        $this->post(route('medical.encounters.complete', $encounter))->assertRedirect();
        $this->post(route('medical.encounters.amend', $encounter), [
            'reason' => 'Audit probe',
            'plan_notes' => 'Amended plan',
        ])->assertRedirect();

        $actions = ClinicalAuditLog::where('auditable_type', Encounter::class)
            ->where('auditable_id', $encounter->id)
            ->orderBy('id')
            ->pluck('action')
            ->all();
        foreach (['created', 'started', 'completed', 'amended'] as $expected) {
            $this->assertContains($expected, $actions);
        }
        $amend = ClinicalAuditLog::where('auditable_type', Encounter::class)
            ->where('auditable_id', $encounter->id)
            ->where('action', 'amended')
            ->firstOrFail();
        $this->assertSame('Audit probe', $amend->reason);
        $this->assertSame($this->institute->id, (int) $amend->institute_id);
    }

    // --- Delete safety & timeline ------------------------------------------------------------------------------------------------------

    public function test_no_destroy_route_and_history_survives_cancel(): void
    {
        $this->assertFalse(Route::has('medical.encounters.destroy'));

        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $this->post(route('medical.encounters.cancel', $encounter))->assertRedirect();

        // Cancelled rows persist (soft-delete-free archival by status).
        $this->assertDatabaseHas('medical_encounters', ['id' => $encounter->id, 'status' => 'cancelled']);

        // Patient timeline shows only this patient's encounters (the filter
        // dropdown still lists every patient, so the negative assertion
        // targets the other patient's encounter row, not their MR number).
        $other = $this->createPatient('TimelineOther');
        $otherEncounter = $this->openEncounter($other);
        $this->get(route('medical.encounters.index'))->assertOk(); // drain store flash
        $response = $this->get(route('medical.encounters.index', ['patient_id' => $patient->id]))->assertOk();
        $response->assertSee(clinical_no($encounter->encounter_number));
        $response->assertDontSee(clinical_no($otherEncounter->encounter_number));
    }
}
