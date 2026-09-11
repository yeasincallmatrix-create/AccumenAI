<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Appointment;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Doctor;
use App\Models\Medical\Encounter;
use App\Models\Medical\EncounterDiagnosis;
use App\Models\Medical\LabOrder;
use App\Models\Medical\Medicine;
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
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase 15 — Structured diagnosis + clinical orders foundation.
 *
 * Diagnoses are clinician-entered documentation attached to encounters:
 * free-text labels preserved verbatim, codes stored only as supplied,
 * nothing inferred or fabricated. Orders reuse the authoritative
 * LabOrder/Prescription models (no generic order table); lab cancellation
 * is a new audited status, never a delete.
 */
class Phase15DiagnosisAndClinicalOrdersTest extends TestCase
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
            'name' => 'Diagnosis Test Hospital',
            'slug' => 'diagnosis-test-'.uniqid(),
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

    private function createPatient(string $first = 'Diagnosis'): Patient
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

    private function openEncounter(Patient $patient): Encounter
    {
        $this->post(route('medical.encounters.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'encounter_type' => 'OPD',
            'chief_complaint' => 'Fever and cough',
        ])->assertSessionHasNoErrors();

        return Encounter::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function addDiagnosis(Encounter $encounter, array $overrides = []): EncounterDiagnosis
    {
        $response = $this->post(route('medical.encounters.diagnoses.store', $encounter), array_merge([
            'label' => 'Acute viral fever',
            'diagnosis_type' => 'primary',
        ], $overrides));
        $response->assertSessionHasNoErrors();

        return EncounterDiagnosis::where('encounter_id', $encounter->id)->latest('id')->firstOrFail();
    }

    private function createLabOrder(Patient $patient, ?Encounter $encounter = null): LabOrder
    {
        $test = \App\Models\Medical\LabTest::create([
            'institute_id' => $this->institute->id,
            'code' => 'LT-'.strtoupper(uniqid()),
            'name' => 'Phase15 Panel',
            'price' => 100,
            'is_active' => true,
        ]);
        $payload = [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'priority' => 'routine',
            'tests' => [['lab_test_id' => $test->id]],
        ];
        if ($encounter) {
            $payload['encounter_id'] = $encounter->id;
        }
        $this->post(route('medical.lab.orders.store'), $payload)->assertSessionHasNoErrors();

        return LabOrder::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function roleWith(array $slugs): User
    {
        $user = User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
        $role = Role::create([
            'institute_id' => $this->institute->id,
            'name' => 'Diagnosis Role '.uniqid(),
            'slug' => 'diagnosis-role-'.uniqid(),
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

    // --- Diagnosis creation -------------------------------------------------------------

    public function test_free_text_diagnosis_stays_unresolved(): void
    {
        $encounter = $this->openEncounter($this->createPatient());
        $diagnosis = $this->addDiagnosis($encounter, [
            'diagnosis_type' => 'secondary',
        ]);

        $this->assertSame('active', $diagnosis->status);
        $this->assertSame('free_text', $diagnosis->source);
        $this->assertSame('unresolved', $diagnosis->mapping_status);
        $this->assertNull($diagnosis->code);
        $this->get(route('medical.encounters.show', $encounter))
            ->assertOk()
            ->assertSee('Acute viral fever')
            ->assertSee('unresolved');
    }

    public function test_supplied_code_without_authority_stays_unresolved(): void
    {
        $encounter = $this->openEncounter($this->createPatient());
        $diagnosis = $this->addDiagnosis($encounter, [
            'label' => 'Typhoid fever',
            'code' => 'A01.0',
            'code_system' => 'ICD-10',
        ]);

        // No terminology authority is registered, so even a plausible
        // clinician-supplied code is preserved as text, never resolved.
        $this->assertSame('A01.0', $diagnosis->code);
        $this->assertSame('unresolved', $diagnosis->mapping_status);
        $this->assertSame([], EncounterDiagnosis::RECOGNIZED_CODE_SYSTEMS);
    }

    public function test_clinician_selected_classification_stored(): void
    {
        $encounter = $this->openEncounter($this->createPatient());
        foreach (['primary', 'secondary', 'differential', 'symptom'] as $i => $type) {
            $this->addDiagnosis($encounter, ['label' => "Label $i $type", 'diagnosis_type' => $type]);
        }

        $this->assertSame(
            ['differential', 'primary', 'secondary', 'symptom'],
            $encounter->diagnoses()->pluck('diagnosis_type')->sort()->values()->all()
        );
    }

    public function test_classification_is_required(): void
    {
        $encounter = $this->openEncounter($this->createPatient());
        $this->post(route('medical.encounters.diagnoses.store', $encounter), [
            'label' => 'No type given',
        ])->assertSessionHasErrors(['diagnosis_type']);
        $this->assertSame(0, $encounter->diagnoses()->count());
    }

    // --- Duplicate control ------------------------------------------------------------------

    public function test_duplicate_diagnosis_prevented(): void
    {
        $encounter = $this->openEncounter($this->createPatient());
        $this->addDiagnosis($encounter);

        $this->post(route('medical.encounters.diagnoses.store', $encounter), [
            'label' => 'ACUTE VIRAL FEVER',
            'diagnosis_type' => 'secondary',
        ])->assertSessionHas('error');
        $this->assertSame(1, $encounter->diagnoses()->where('status', 'active')->count());
    }

    public function test_readmission_after_removal_allowed_and_history_kept(): void
    {
        $encounter = $this->openEncounter($this->createPatient());
        $diagnosis = $this->addDiagnosis($encounter);

        $this->patch(route('medical.diagnoses.remove', $diagnosis), [])
            ->assertSessionHasNoErrors();
        $this->assertSame('removed', $diagnosis->fresh()->status);

        $readmission = $this->addDiagnosis($encounter);
        $this->assertNotSame($diagnosis->id, $readmission->id);
        $this->assertSame(2, $encounter->diagnoses()->count());
        $this->assertSame(1, $encounter->diagnoses()->where('status', 'active')->count());
    }

    // --- Patient fence (structural) -----------------------------------------------------------------

    public function test_no_patient_column_diagnosis_resolves_via_encounter(): void
    {
        $this->assertFalse(Schema::hasColumn('encounter_diagnoses', 'patient_id'));

        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $diagnosis = $this->addDiagnosis($encounter);

        $this->assertSame($patient->id, (int) $diagnosis->encounter->patient_id);
        $this->assertSame($this->institute->id, (int) $diagnosis->institute_id);
    }

    // --- Removal + amendment ------------------------------------------------------------------------------

    public function test_removal_preserves_row_and_audits(): void
    {
        $encounter = $this->openEncounter($this->createPatient());
        $diagnosis = $this->addDiagnosis($encounter);

        $this->patch(route('medical.diagnoses.remove', $diagnosis), [])
            ->assertSessionHasNoErrors();

        $diagnosis->refresh();
        $this->assertSame('removed', $diagnosis->status);
        $this->assertNotNull($diagnosis->removed_at);
        $this->assertDatabaseHas('encounter_diagnoses', ['id' => $diagnosis->id]);

        $row = ClinicalAuditLog::where('auditable_type', EncounterDiagnosis::class)
            ->where('auditable_id', $diagnosis->id)
            ->where('action', 'diagnosis_removed')
            ->firstOrFail();
        $this->assertSame('removed', $row->new_values['status']);
        $this->assertSame($this->institute->id, (int) $row->institute_id);
        $this->assertSame($encounter->patient_id, (int) $row->patient_id);
    }

    public function test_double_removal_rejected(): void
    {
        $encounter = $this->openEncounter($this->createPatient());
        $diagnosis = $this->addDiagnosis($encounter);
        $this->patch(route('medical.diagnoses.remove', $diagnosis), [])->assertSessionHasNoErrors();

        $this->patch(route('medical.diagnoses.remove', $diagnosis), [])
            ->assertSessionHas('error');
        $this->assertSame(1, ClinicalAuditLog::where('auditable_type', EncounterDiagnosis::class)
            ->where('auditable_id', $diagnosis->id)
            ->where('action', 'diagnosis_removed')
            ->count());
    }

    public function test_closed_encounter_mutation_needs_reason(): void
    {
        $encounter = $this->openEncounter($this->createPatient());
        $open = $this->addDiagnosis($encounter);
        $this->post(route('medical.encounters.start', $encounter))->assertRedirect();
        $this->post(route('medical.encounters.complete', $encounter))->assertRedirect();

        // Add without reason: blocked, nothing persisted.
        $this->post(route('medical.encounters.diagnoses.store', $encounter), [
            'label' => 'Late entry',
            'diagnosis_type' => 'secondary',
        ])->assertSessionHas('error');
        $this->assertSame(1, $encounter->diagnoses()->count());

        // Add with reason: allowed, audited as a completed-record amendment.
        $late = $this->addDiagnosis($encounter, [
            'label' => 'Late entry',
            'diagnosis_type' => 'secondary',
            'reason' => 'Forgot to record during visit',
        ]);
        $row = ClinicalAuditLog::where('auditable_type', EncounterDiagnosis::class)
            ->where('auditable_id', $late->id)
            ->where('action', 'diagnosis_added_completed')
            ->firstOrFail();
        $this->assertSame('Forgot to record during visit', $row->reason);

        // Remove without reason: blocked; with reason: allowed + audited.
        $this->patch(route('medical.diagnoses.remove', $open), [])
            ->assertSessionHas('error');
        $this->assertSame('active', $open->fresh()->status);
        $this->patch(route('medical.diagnoses.remove', $open), [
            'reason' => 'Entered in error',
        ])->assertSessionHasNoErrors();
        $row = ClinicalAuditLog::where('auditable_type', EncounterDiagnosis::class)
            ->where('auditable_id', $open->id)
            ->where('action', 'diagnosis_removed_completed')
            ->firstOrFail();
        $this->assertSame('Entered in error', $row->reason);
    }

    // --- Orders: lab cancel lifecycle --------------------------------------------------------------------------

    public function test_lab_cancel_requires_reason_and_audits(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $order = $this->createLabOrder($patient, $encounter);

        $this->post(route('medical.lab.orders.cancel', $order), [])
            ->assertSessionHas('error');
        $this->assertSame('ordered', $order->fresh()->status);

        $this->post(route('medical.lab.orders.cancel', $order), ['reason' => 'Duplicate order'])
            ->assertRedirect();
        $this->assertSame('cancelled', $order->fresh()->status);
        // Cancelled rows stay visible, never vaporized.
        $this->assertDatabaseHas('lab_orders', ['id' => $order->id, 'status' => 'cancelled']);
        $this->get(route('medical.lab.orders.show', $order))->assertOk();

        $row = ClinicalAuditLog::where('auditable_type', LabOrder::class)
            ->where('auditable_id', $order->id)
            ->where('action', 'cancelled')
            ->firstOrFail();
        $this->assertSame('Duplicate order', $row->reason);
        $this->assertSame('ordered', $row->old_values['status']);
    }

    public function test_lab_invalid_transitions_rejected(): void
    {
        $patient = $this->createPatient();
        $order = $this->createLabOrder($patient);

        $this->post(route('medical.lab.orders.cancel', $order), ['reason' => 'x'])->assertRedirect();
        // Second cancel: already terminal, refused, no second audit.
        $this->post(route('medical.lab.orders.cancel', $order), ['reason' => 'again'])
            ->assertSessionHas('error');
        $this->assertSame(1, ClinicalAuditLog::where('auditable_type', LabOrder::class)
            ->where('auditable_id', $order->id)->where('action', 'cancelled')->count());

        // Cancelled rows leave the workflow: collect + results refused.
        $this->post(route('medical.lab.orders.collect', $order))->assertSessionHas('error');
        $result = $order->results()->first();
        if ($result) {
            $this->post(route('medical.lab.orders.result', $order), [
                'results' => [$result->id => ['result_value' => '5']],
            ])->assertSessionHas('error');
        }
        $this->assertSame('cancelled', $order->fresh()->status);
    }

    public function test_lab_results_untouched_by_cancel_path(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $order = $this->createLabOrder($patient, $encounter);

        $this->post(route('medical.lab.orders.collect', $order))->assertRedirect();
        $result = $order->results()->firstOrFail();
        $this->post(route('medical.lab.orders.result', $order), [
            'results' => [$result->id => ['result_value' => '80']],
        ])->assertSessionHasNoErrors();
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('80', $result->fresh()->result_value);

        // Completed rows cannot be cancelled; the verified result stands.
        $this->post(route('medical.lab.orders.cancel', $order), ['reason' => 'too late'])
            ->assertSessionHas('error');
        $this->assertSame('80', $result->fresh()->result_value);
    }

    // --- Orders: prescription integrity -------------------------------------------------------------------------------

    public function test_encounter_prescription_keeps_safety_and_snapshots(): void
    {
        $medicine = Medicine::where('institute_id', $this->institute->id)->first();
        if (! $medicine) {
            $medicine = Medicine::create([
                'institute_id' => $this->institute->id,
                'code' => 'MED-'.strtoupper(uniqid()),
                'generic_name' => 'Phase15 Test Salt',
                'brand_name' => 'Phase15 Test Salt 500',
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

        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $itemPayload = [[
            'medicine_id' => $medicine->id,
            'medicine_name' => $medicine->display_name,
            'dosage' => '500mg',
            'frequency' => '1+0+1',
            'quantity' => 10,
        ]];
        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'encounter_id' => $encounter->id,
            'items' => $itemPayload,
        ])->assertSessionHasNoErrors();

        $rx = Prescription::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
        $this->assertSame($encounter->id, (int) $rx->encounter_id);
        $item = $rx->items()->firstOrFail();
        // Phase 10–12 snapshots written by the untouched pipeline.
        $this->assertSame($medicine->display_name, $item->display_name_snapshot);

        // Pipeline parity: the same medicine without an encounter link
        // produces identical snapshots (link adds relation, changes nothing).
        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'items' => $itemPayload,
        ])->assertSessionHasNoErrors();
        $plain = Prescription::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
        $plainItem = $plain->items()->firstOrFail();
        foreach (['display_name_snapshot', 'strength_snapshot', 'dosage_form_snapshot', 'route_snapshot', 'rxnorm_code_snapshot', 'dgda_code'] as $column) {
            $this->assertSame($plainItem->{$column}, $item->{$column}, "snapshot parity: {$column}");
        }
        $plain->delete();

        // Safety pipeline still guards encounter-linked scripts.
        $rx->delete();
        $allergic = $this->createPatient('Allergic');
        $allergic->update(['allergies' => $medicine->display_name]);
        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $allergic->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'encounter_id' => $encounter->id,
            'items' => $itemPayload,
        ])->assertSessionHas('error');
    }

    public function test_prescription_wrong_encounter_patient_rejected(): void
    {
        $patient = $this->createPatient('OwnerP');
        $other = $this->createPatient('OtherP');
        $encounter = $this->openEncounter($other);

        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'encounter_id' => $encounter->id,
            'items' => [[
                'medicine_id' => null,
                'medicine_name' => 'Mismatch 10mg',
                'dosage' => '10mg',
                'frequency' => '1+0+0',
                'quantity' => 1,
            ]],
        ])->assertSessionHas('error');
    }

    // --- Cross-institute matrix ---------------------------------------------------------------------------------------------

    public function test_cross_institute_matrix_fails_safely(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $diagnosis = $this->addDiagnosis($encounter);
        $order = $this->createLabOrder($patient, $encounter);

        $other = Institute::create([
            'name' => 'Diagnosis Rival', 'slug' => 'diagnosis-rival-'.uniqid(),
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

        // Institute A → Institute B diagnosis / order / encounter reads.
        $this->post(route('medical.encounters.diagnoses.store', $encounter), [
            'label' => 'Foreign entry', 'diagnosis_type' => 'primary',
        ])->assertForbidden();
        $this->patch(route('medical.diagnoses.remove', $diagnosis), [])
            ->assertForbidden();
        $this->get(route('medical.encounters.diagnoses.index', $encounter))
            ->assertForbidden();
        $this->post(route('medical.lab.orders.cancel', $order), ['reason' => 'x'])
            ->assertForbidden();
        $this->get(route('medical.encounters.show', $encounter))->assertForbidden();

        // Institute A encounter → Institute B patient/order linkage refused.
        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'encounter_id' => $encounter->id,
            'items' => [[
                'medicine_id' => null, 'medicine_name' => 'X 1mg',
                'dosage' => '1mg', 'frequency' => '1+0+0', 'quantity' => 1,
            ]],
        ])->assertSessionHasErrors(['patient_id']);

        $this->assertSame('active', $diagnosis->fresh()->status);
        $this->assertSame('ordered', $order->fresh()->status);
    }

    // --- Authorization --------------------------------------------------------------------------------------------------------------

    public function test_diagnosis_permissions_enforced(): void
    {
        $encounter = $this->openEncounter($this->createPatient());
        $diagnosis = $this->addDiagnosis($encounter);

        $cancelTarget = $this->createLabOrder($this->createPatient());

        $viewer = $this->roleWith(['medical_diagnoses.view']);
        $this->actingAs($viewer, 'web');
        Workspace::set($this->institute->id);

        $this->get(route('medical.encounters.diagnoses.index', $encounter))->assertOk();
        $this->post(route('medical.encounters.diagnoses.store', $encounter), [
            'label' => 'No grant', 'diagnosis_type' => 'primary',
        ])->assertForbidden();
        $this->patch(route('medical.diagnoses.remove', $diagnosis), [])
            ->assertForbidden();
        $this->post(route('medical.lab.orders.cancel', $cancelTarget), ['reason' => 'x'])
            ->assertForbidden();
        $this->assertSame('ordered', $cancelTarget->fresh()->status);

        $manager = $this->roleWith([
            'medical_diagnoses.view', 'medical_diagnoses.create', 'medical_diagnoses.remove',
            'medical_encounters.view',
        ]);
        $this->actingAs($manager, 'web');
        Workspace::set($this->institute->id);
        $this->post(route('medical.encounters.diagnoses.store', $encounter), [
            'label' => 'Granted entry', 'diagnosis_type' => 'secondary',
        ])->assertSessionHasNoErrors();
    }

    // --- Timeline -----------------------------------------------------------------------------------------------------------------------

    public function test_encounter_timeline_shows_events_and_stays_bounded(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $this->addDiagnosis($encounter);
        $order = $this->createLabOrder($patient, $encounter);
        $this->post(route('medical.lab.orders.cancel', $order), ['reason' => 'timeline'])->assertRedirect();
        $this->post(route('medical.encounters.start', $encounter))->assertRedirect();
        $this->post(route('medical.encounters.complete', $encounter))->assertRedirect();

        $response = $this->get(route('medical.encounters.show', $encounter))->assertOk();
        foreach (['Acute viral fever', $order->order_number, 'diagnosis added', 'cancelled', 'started', 'completed'] as $needle) {
            $response->assertSee($needle);
        }
    }

    public function test_encounter_timeline_bounded_at_fifty(): void
    {
        $encounter = $this->openEncounter($this->createPatient());
        for ($i = 0; $i < 55; $i++) {
            $this->addDiagnosis($encounter, [
                'label' => "Bounded diagnosis $i",
                'diagnosis_type' => 'symptom',
            ]);
        }

        $response = $this->get(route('medical.encounters.show', $encounter))->assertOk();
        $count = substr_count($response->getContent(), 'diagnosis added');
        $this->assertLessThanOrEqual(50, $count);
        $this->assertGreaterThan(0, $count);
    }

    // --- Audit completeness -----------------------------------------------------------------------------------------------------------------

    public function test_lifecycle_audited_end_to_end(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $diagnosis = $this->addDiagnosis($encounter);
        $order = $this->createLabOrder($patient, $encounter);
        $this->post(route('medical.lab.orders.cancel', $order), ['reason' => 'audit probe'])->assertRedirect();
        $this->patch(route('medical.diagnoses.remove', $diagnosis), [])->assertSessionHasNoErrors();

        $byType = ClinicalAuditLog::where('institute_id', $this->institute->id)
            ->where('patient_id', $patient->id)
            ->pluck('action')
            ->all();
        foreach (['created', 'diagnosis_added', 'cancelled', 'diagnosis_removed'] as $expected) {
            $this->assertContains($expected, $byType);
        }
        // Every audit row carries tenant + patient attribution.
        $this->assertSame(0, ClinicalAuditLog::where('institute_id', $this->institute->id)
            ->where('patient_id', $patient->id)
            ->whereNull('institute_id')
            ->count());
    }

    // --- Delete safety ----------------------------------------------------------------------------------------------------------------------------

    public function test_no_destructive_diagnosis_or_cancel_paths(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('medical.diagnoses.destroy'));

        $encounter = $this->openEncounter($this->createPatient());
        $diagnosis = $this->addDiagnosis($encounter);
        $this->patch(route('medical.diagnoses.remove', $diagnosis), [])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('encounter_diagnoses', ['id' => $diagnosis->id, 'status' => 'removed']);
    }
}
