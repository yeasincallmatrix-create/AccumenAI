<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Admission;
use App\Models\Medical\Bed;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Doctor;
use App\Models\Medical\Encounter;
use App\Models\Medical\EncounterDiagnosis;
use App\Models\Medical\LabOrder;
use App\Models\Medical\NursingNote;
use App\Models\Medical\Patient;
use App\Models\Medical\Prescription;
use App\Models\Medical\VitalSign;
use App\Models\Medical\Ward;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Medical\PatientTimelineService;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 16 — Patient longitudinal timeline (read-only aggregation).
 *
 * The timeline renders existing authoritative records through
 * PatientTimelineService: no new tables, no writes, no inference.
 */
class Phase16PatientLongitudinalTimelineTest extends TestCase
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
            'name' => 'Timeline Test Hospital',
            'slug' => 'timeline-test-'.uniqid(),
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

    private function createPatient(string $first = 'Timeline'): Patient
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
            'chief_complaint' => 'Timeline review',
        ])->assertSessionHasNoErrors();

        return Encounter::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function addDiagnosis(Encounter $encounter, string $label): EncounterDiagnosis
    {
        $this->post(route('medical.encounters.diagnoses.store', $encounter), [
            'label' => $label,
            'diagnosis_type' => 'primary',
        ])->assertSessionHasNoErrors();

        return EncounterDiagnosis::where('encounter_id', $encounter->id)->latest('id')->firstOrFail();
    }

    private function createLabOrder(Patient $patient, ?Encounter $encounter = null): LabOrder
    {
        $test = \App\Models\Medical\LabTest::create([
            'institute_id' => $this->institute->id,
            'code' => 'LT-'.strtoupper(uniqid()),
            'name' => 'Timeline Panel',
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

    private function createPrescription(Patient $patient, ?Encounter $encounter = null): Prescription
    {
        $payload = [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'items' => [[
                'medicine_id' => null,
                'medicine_name' => 'Timeline 100mg',
                'dosage' => '100mg',
                'frequency' => '1+0+0',
                'quantity' => 5,
            ]],
        ];
        if ($encounter) {
            $payload['encounter_id'] = $encounter->id;
        }
        $this->post(route('medical.prescriptions.store'), $payload)->assertSessionHasNoErrors();

        return Prescription::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function createAdmission(Patient $patient, array $overrides = []): Admission
    {
        $this->post(route('medical.admissions.store'), array_merge([
            'patient_id' => $patient->id,
            'admitting_doctor_id' => $this->doctor->id,
            'admission_date' => now()->format('Y-m-d'),
            'admission_time' => '10:00',
            'primary_diagnosis' => 'Timeline IPD',
        ], $overrides))->assertSessionHasNoErrors();

        return Admission::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function roleWith(array $slugs): User
    {
        $user = User::factory()->create(['account_type' => 'staff', 'status' => 'active']);
        $role = Role::create([
            'institute_id' => $this->institute->id,
            'name' => 'Timeline Role '.uniqid(),
            'slug' => 'timeline-role-'.uniqid(),
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

    private function rivalContext(): Institute
    {
        $other = Institute::create([
            'name' => 'Timeline Rival', 'slug' => 'timeline-rival-'.uniqid(),
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

        return $other;
    }

    // --- Security -------------------------------------------------------------------------------

    public function test_same_institute_timeline_renders(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);

        $this->get(route('medical.patients.history', $patient))
            ->assertOk()
            ->assertSee('Clinical Timeline')
            ->assertSee($encounter->encounter_number);
    }

    public function test_foreign_patient_timeline_rejected_without_leakage(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $this->addDiagnosis($encounter, 'Secret diagnosis label');
        $this->createPrescription($patient, $encounter);

        $this->rivalContext();

        $response = $this->get(route('medical.patients.history', $patient));
        $response->assertForbidden();
        $response->assertDontSee($patient->mr_number);
        $response->assertDontSee($encounter->encounter_number);
        $response->assertDontSee('Secret diagnosis label');
    }

    public function test_foreign_event_links_rejected(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $order = $this->createLabOrder($patient, $encounter);
        $rx = $this->createPrescription($patient, $encounter);

        $this->rivalContext();

        $this->get(route('medical.encounters.show', $encounter))->assertForbidden();
        $this->get(route('medical.lab.orders.show', $order))->assertForbidden();
        $this->get(route('medical.prescriptions.show', $rx))->assertForbidden();
    }

    // --- Construction -----------------------------------------------------------------------------

    public function test_all_source_types_appear(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $this->addDiagnosis($encounter, 'Timeline fever');
        $order = $this->createLabOrder($patient, $encounter);
        $this->post(route('medical.lab.orders.collect', $order))->assertRedirect();
        $result = $order->results()->firstOrFail();
        $this->post(route('medical.lab.orders.result', $order), [
            'results' => [$result->id => ['result_value' => '77']],
        ])->assertSessionHasNoErrors();
        $rx = $this->createPrescription($patient, $encounter);
        $admission = $this->createAdmission($patient);
        VitalSign::create([
            'institute_id' => $this->institute->id,
            'admission_id' => $admission->id,
            'patient_id' => $patient->id,
            'temperature' => 101.2,
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 80,
            'pulse' => 88,
            'recorded_at' => now(),
        ]);
        NursingNote::create([
            'institute_id' => $this->institute->id,
            'admission_id' => $admission->id,
            'note' => 'Timeline night note',
            'recorded_at' => now(),
        ]);
        $this->post(route('medical.admissions.discharge', $admission), [
            'discharge_date' => now()->format('Y-m-d'),
            'discharge_time' => '18:00',
            'discharge_summary' => 'Recovered',
        ])->assertRedirect();

        $response = $this->get(route('medical.patients.history', $patient))->assertOk();
        foreach ([
            $encounter->encounter_number, 'Timeline fever', 'Terminology: Unresolved',
            $order->order_number, '77', $rx->prescription_number,
            '101.2', 'Timeline night note', 'Discharge',
        ] as $needle) {
            $response->assertSee($needle);
        }
    }

    public function test_transfer_event_from_audit(): void
    {
        $ward = Ward::create([
            'institute_id' => $this->institute->id,
            'name' => 'Transfer Ward '.uniqid(),
            'type' => 'general',
            'total_beds' => 4,
            'available_beds' => 4,
            'daily_rate' => 500,
            'is_active' => true,
        ]);
        $from = Bed::create([
            'institute_id' => $this->institute->id, 'ward_id' => $ward->id,
            'bed_number' => 'T-'.uniqid(), 'status' => 'available',
        ]);
        $to = Bed::create([
            'institute_id' => $this->institute->id, 'ward_id' => $ward->id,
            'bed_number' => 'T-'.uniqid(), 'status' => 'available',
        ]);

        $patient = $this->createPatient('Transfer');
        $admission = $this->createAdmission($patient, ['bed_id' => $from->id]);
        $this->post(route('medical.admissions.transfer', $admission), ['bed_id' => $to->id])
            ->assertSessionHasNoErrors();

        $this->get(route('medical.patients.history', $patient))
            ->assertOk()
            ->assertSee('Bed Transfer');
    }

    // --- Ordering + filtering ---------------------------------------------------------------------------

    public function test_chronological_order_and_stability(): void
    {
        $patient = $this->createPatient();
        $this->createAdmission($patient, ['admission_date' => now()->subDays(5)->format('Y-m-d')]);
        $encounter = $this->openEncounter($patient);
        $rx = $this->createPrescription($patient);
        $this->get(route('medical.patients.show', $patient))->assertOk(); // drain store flashes

        $first = $this->get(route('medical.patients.history', $patient))->assertOk()->getContent();
        $second = $this->get(route('medical.patients.history', $patient))->assertOk()->getContent();

        // Newest first: today's records before the 5-day-old admission
        // (em-dash needle targets the event card, not the filter option).
        $this->assertTrue(strpos($first, $encounter->encounter_number) < strpos($first, 'Admission —'));
        $this->assertTrue(strpos($first, $rx->prescription_number) < strpos($first, 'Admission —'));
        // Deterministic: identical order across requests.
        $this->assertSame($first, $second);
    }

    public function test_date_and_type_filters(): void
    {
        $patient = $this->createPatient();
        $this->createAdmission($patient, ['admission_date' => now()->subDays(10)->format('Y-m-d')]);
        $encounter = $this->openEncounter($patient);
        $rx = $this->createPrescription($patient);
        $this->get(route('medical.patients.show', $patient))->assertOk(); // drain store flashes

        // Date window excludes the old admission.
        $response = $this->get(route('medical.patients.history', [$patient, 'from' => now()->subDays(2)->format('Y-m-d')]))->assertOk();
        $response->assertSee($encounter->encounter_number);
        $response->assertDontSee('Timeline IPD');

        // Type filter isolates prescriptions.
        $response = $this->get(route('medical.patients.history', [$patient, 'type' => 'prescription']))->assertOk();
        $response->assertSee($rx->prescription_number);
        $response->assertDontSee($encounter->encounter_number);
    }

    public function test_empty_state(): void
    {
        $patient = $this->createPatient();

        $this->get(route('medical.patients.history', $patient))
            ->assertOk()
            ->assertSee('No clinical history found for the selected period.');
    }

    // --- Pagination + bounds -----------------------------------------------------------------------------------

    public function test_pagination_pages_and_counts(): void
    {
        $patient = $this->createPatient();
        for ($i = 0; $i < 18; $i++) {
            $this->openEncounter($patient);
        }

        $page1 = $this->get(route('medical.patients.history', $patient))->assertOk();
        $page1->assertSee('18 events');
        $page2 = $this->get(route('medical.patients.history', [$patient, 'page' => 2]))->assertOk();
        $this->assertNotSame($page1->getContent(), $page2->getContent());
    }

    public function test_per_type_cap_bounds_memory(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        for ($i = 0; $i < 105; $i++) {
            EncounterDiagnosis::create([
                'institute_id' => $this->institute->id,
                'encounter_id' => $encounter->id,
                'label' => "Cap diagnosis $i",
                'diagnosis_type' => 'symptom',
            ]);
        }

        $service = app(PatientTimelineService::class);
        $events = $service->collect(
            $patient,
            [],
            PatientTimelineService::TYPES,
            null
        );
        $diagnoses = array_filter($events, fn ($e) => $e['type'] === 'diagnosis');
        $this->assertLessThanOrEqual(PatientTimelineService::PER_TYPE_LIMIT, count($diagnoses));
        $this->assertSame(105, $encounter->diagnoses()->count());
    }

    public function test_n_plus_one_bounded_queries(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $this->addDiagnosis($encounter, 'Query probe');
        $order = $this->createLabOrder($patient, $encounter);
        $this->createPrescription($patient, $encounter);
        $admission = $this->createAdmission($patient);
        VitalSign::create([
            'institute_id' => $this->institute->id,
            'admission_id' => $admission->id,
            'patient_id' => $patient->id,
            'temperature' => 99.1,
            'recorded_at' => now(),
        ]);

        // The service's own query count must not grow with the event count
        // (fixed bounded source queries — no event-per-query pattern). Page
        // rendering carries the app-wide layout baseline either way.
        $service = app(PatientTimelineService::class);
        DB::enableQueryLog();
        $service->collect($patient, [], PatientTimelineService::TYPES, null);
        $small = count(DB::getQueryLog());
        DB::flushQueryLog();

        for ($i = 0; $i < 18; $i++) {
            $this->openEncounter($patient);
        }
        DB::flushQueryLog(); // exclude fixture writes from the comparison
        $service->collect($patient, [], PatientTimelineService::TYPES, null);
        $large = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($small, $large);
        $this->assertLessThanOrEqual(20, $small);

        // Source identity preserved for every rendered event.
        $events = $service->collect($patient, [], PatientTimelineService::TYPES, null);
        $this->assertGreaterThanOrEqual(7, count($events));
        foreach ($events as $event) {
            $this->assertNotEmpty($event['source_type']);
            $this->assertNotEmpty($event['source_id']);
            $this->assertNotEmpty($event['route_name']);
        }
        $this->assertTrue(collect($events)->contains(
            fn ($e) => $e['source_type'] === LabOrder::class && $e['source_id'] === $order->id
        ));
    }

    // --- Authorization --------------------------------------------------------------------------------------------------

    public function test_patient_viewer_without_clinical_grants_sees_no_events(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $this->createPrescription($patient, $encounter);

        $viewer = $this->roleWith(['medical_patients.view']);
        $this->actingAs($viewer, 'web');
        Workspace::set($this->institute->id);

        $response = $this->get(route('medical.patients.history', $patient))->assertOk();
        $response->assertSee('0 events');
        $response->assertDontSee($encounter->encounter_number);
    }

    public function test_event_visibility_follows_existing_grants(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $rx = $this->createPrescription($patient, $encounter);
        $this->get(route('medical.patients.show', $patient))->assertOk(); // drain store flashes

        $partial = $this->roleWith(['medical_patients.view', 'medical_encounters.view']);
        $this->actingAs($partial, 'web');
        Workspace::set($this->institute->id);

        $response = $this->get(route('medical.patients.history', $patient))->assertOk();
        $response->assertSee($encounter->encounter_number);
        $response->assertDontSee($rx->prescription_number);
    }

    public function test_no_patient_permission_rejected(): void
    {
        $patient = $this->createPatient();

        $outsider = $this->roleWith(['medical_lab.view']);
        $this->actingAs($outsider, 'web');
        Workspace::set($this->institute->id);

        $this->get(route('medical.patients.history', $patient))->assertForbidden();
    }

    // --- Source integrity -----------------------------------------------------------------------------------------------------

    public function test_rendering_mutates_nothing(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $diagnosis = $this->addDiagnosis($encounter, 'Integrity probe');
        $order = $this->createLabOrder($patient, $encounter);
        $rx = $this->createPrescription($patient, $encounter);
        $admission = $this->createAdmission($patient);
        $vital = VitalSign::create([
            'institute_id' => $this->institute->id,
            'admission_id' => $admission->id,
            'patient_id' => $patient->id,
            'temperature' => 98.6,
            'recorded_at' => now(),
        ]);
        $note = NursingNote::create([
            'institute_id' => $this->institute->id,
            'admission_id' => $admission->id,
            'note' => 'Integrity note',
            'recorded_at' => now(),
        ]);

        $stamps = [];
        foreach ([
            $patient, $encounter, $diagnosis, $order, $rx, $admission, $vital, $note,
        ] as $row) {
            $stamps[$row::class.':'.$row->id] = $row->updated_at->toDateTimeString();
        }
        $auditCount = ClinicalAuditLog::where('institute_id', $this->institute->id)->count();

        $this->get(route('medical.patients.history', $patient))->assertOk();
        $this->get(route('medical.patients.history', $patient, ['type' => 'lab_result']))->assertOk();

        foreach ([
            $patient, $encounter, $diagnosis, $order, $rx, $admission, $vital, $note,
        ] as $row) {
            $this->assertSame(
                $stamps[$row::class.':'.$row->id],
                $row->fresh()->updated_at->toDateTimeString(),
                $row::class.' was mutated by timeline rendering'
            );
        }
        $this->assertSame($auditCount, ClinicalAuditLog::where('institute_id', $this->institute->id)->count());
        $this->assertSame('active', $diagnosis->fresh()->status);
        $this->assertSame('ordered', $order->fresh()->status);
    }

    // --- Existing safety still active -----------------------------------------------------------------------------------------------

    public function test_encounter_prescription_safety_intact(): void
    {
        $patient = $this->createPatient();
        $encounter = $this->openEncounter($patient);
        $allergic = $this->createPatient('Allergic');
        $allergic->update(['allergies' => 'Timeline 100mg']);

        $this->post(route('medical.prescriptions.store'), [
            'patient_id' => $allergic->id,
            'doctor_id' => $this->doctor->id,
            'prescription_date' => now()->format('Y-m-d'),
            'encounter_id' => $encounter->id,
            'items' => [[
                'medicine_id' => null,
                'medicine_name' => 'Timeline 100mg',
                'dosage' => '100mg',
                'frequency' => '1+0+0',
                'quantity' => 5,
            ]],
        ])->assertSessionHas('error');
    }
}
