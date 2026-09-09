<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Admission;
use App\Models\Medical\Bed;
use App\Models\Medical\Doctor;
use App\Models\Medical\NursingNote;
use App\Models\Medical\Patient;
use App\Models\Medical\VitalSign;
use App\Models\Medical\Ward;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 2 — HMS IPD & Bed Management verification.
 *
 * Same pattern as MedicalPhase1Test: DatabaseTransactions on the dev DB,
 * web guard + Workspace context, institute-owner membership. CSRF disabled
 * for HTTP calls only; all other middleware still runs.
 */
class MedicalPhase2Test extends TestCase
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
            'name' => 'Medical IPD Test Hospital',
            'slug' => 'medical-ipd-test-'.uniqid(),
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

    private function createWard(array $overrides = []): Ward
    {
        return Ward::create(array_merge([
            'institute_id' => $this->institute->id,
            'name' => 'Test Ward '.uniqid(),
            'type' => 'general',
            'total_beds' => 4,
            'available_beds' => 4,
            'daily_rate' => 500,
            'is_active' => true,
        ], $overrides));
    }

    private function createBed(Ward $ward, string $number = null): Bed
    {
        return Bed::create([
            'institute_id' => $this->institute->id,
            'ward_id' => $ward->id,
            'bed_number' => $number ?? 'B-'.uniqid(),
            'status' => 'available',
        ]);
    }

    private function createPatient(): Patient
    {
        $response = $this->post(route('medical.patients.store'), [
            'first_name' => 'IPD',
            'last_name' => 'Patient',
            'date_of_birth' => '1985-03-10',
            'gender' => 'female',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
        ]);
        $response->assertSessionHasNoErrors();

        return Patient::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function createAdmission(Patient $patient, ?Bed $bed = null): Admission
    {
        $payload = [
            'patient_id' => $patient->id,
            'admitting_doctor_id' => $this->doctor->id,
            'admission_date' => now()->format('Y-m-d'),
            'admission_time' => '10:00',
            'primary_diagnosis' => 'Test diagnosis',
        ];
        if ($bed) {
            $payload['bed_id'] = $bed->id;
        }

        $response = $this->post(route('medical.admissions.store'), $payload);
        $response->assertSessionHasNoErrors();

        return Admission::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    public function test_ward_crud(): void
    {
        $response = $this->post(route('medical.wards.store'), [
            'name' => 'CRUD Ward',
            'type' => 'icu',
            'total_beds' => 6,
            'daily_rate' => 2000,
        ]);
        $response->assertSessionHasNoErrors()->assertRedirect();

        $ward = Ward::where('institute_id', $this->institute->id)
            ->where('name', 'CRUD Ward')->firstOrFail();
        $this->assertSame(6, (int) $ward->available_beds);

        $this->get(route('medical.wards.index'))->assertOk()->assertSee('CRUD Ward');
        $this->get(route('medical.wards.show', $ward))->assertOk();
        $this->get(route('medical.wards.edit', $ward))->assertOk();

        $this->put(route('medical.wards.update', $ward), [
            'name' => 'CRUD Ward Renamed',
            'type' => 'icu',
            'total_beds' => 8,
            'daily_rate' => 2000,
        ])->assertSessionHasNoErrors();

        $ward->refresh();
        $this->assertSame('CRUD Ward Renamed', $ward->name);
        $this->assertSame(8, (int) $ward->available_beds);

        $this->delete(route('medical.wards.destroy', $ward))->assertRedirect();
        $this->assertDatabaseMissing('wards', ['id' => $ward->id]);
    }

    public function test_ward_validation(): void
    {
        $this->post(route('medical.wards.store'), ['name' => 'Bad'])
            ->assertSessionHasErrors(['type', 'total_beds', 'daily_rate']);
    }

    public function test_ward_with_beds_cannot_be_deleted(): void
    {
        $ward = $this->createWard();
        $this->createBed($ward);

        $this->delete(route('medical.wards.destroy', $ward))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('wards', ['id' => $ward->id]);
    }

    public function test_bed_crud_and_counters(): void
    {
        $ward = $this->createWard(['total_beds' => 4, 'available_beds' => 4]);

        $this->post(route('medical.beds.store'), [
            'ward_id' => $ward->id,
            'bed_number' => 'B-101',
            'status' => 'available',
        ])->assertSessionHasNoErrors();

        $ward->refresh();
        $this->assertSame(5, (int) $ward->total_beds);
        $this->assertSame(5, (int) $ward->available_beds);

        $bed = Bed::where('ward_id', $ward->id)->where('bed_number', 'B-101')->firstOrFail();

        // Duplicate bed number in the same ward is rejected.
        $this->post(route('medical.beds.store'), [
            'ward_id' => $ward->id,
            'bed_number' => 'B-101',
            'status' => 'available',
        ])->assertSessionHasErrors(['bed_number']);

        $this->get(route('medical.beds.index'))->assertOk()->assertSee('B-101');
        $this->get(route('medical.beds.show', $bed))->assertOk();
        $this->get(route('medical.beds.available'))->assertOk()->assertSee('B-101');

        $this->delete(route('medical.beds.destroy', $bed))->assertRedirect();
        $ward->refresh();
        $this->assertSame(4, (int) $ward->total_beds);
        $this->assertSame(4, (int) $ward->available_beds);
    }

    public function test_admission_allocates_bed_and_updates_counts(): void
    {
        $ward = $this->createWard(['total_beds' => 2, 'available_beds' => 2]);
        $bed = $this->createBed($ward, 'B-201');
        $patient = $this->createPatient();

        $admission = $this->createAdmission($patient, $bed);

        $this->assertSame($bed->id, (int) $admission->fresh()->bed_id);
        $this->assertSame('occupied', $bed->fresh()->status);
        $this->assertSame(1, (int) $ward->fresh()->available_beds);

        $this->get(route('medical.admissions.show', $admission))->assertOk();
        $this->get(route('medical.admissions.current'))->assertOk()->assertSee($patient->full_name);
    }

    public function test_admission_validation(): void
    {
        $this->post(route('medical.admissions.store'), [])
            ->assertSessionHasErrors(['patient_id', 'admitting_doctor_id', 'admission_date', 'admission_time']);
    }

    public function test_transfer_moves_patient_between_beds(): void
    {
        $ward = $this->createWard(['total_beds' => 2, 'available_beds' => 2]);
        $from = $this->createBed($ward, 'B-301');
        $to = $this->createBed($ward, 'B-302');
        $admission = $this->createAdmission($this->createPatient(), $from);

        $this->get(route('medical.admissions.transfer.form', $admission))->assertOk();

        $this->post(route('medical.admissions.transfer', $admission), ['bed_id' => $to->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($to->id, (int) $admission->fresh()->bed_id);
        $this->assertSame('available', $from->fresh()->status);
        $this->assertSame('occupied', $to->fresh()->status);
        // Net occupancy unchanged: one bed freed, one taken.
        $this->assertSame(1, (int) $ward->fresh()->available_beds);
    }

    public function test_discharge_releases_bed(): void
    {
        $ward = $this->createWard(['total_beds' => 1, 'available_beds' => 1]);
        $bed = $this->createBed($ward, 'B-401');
        $admission = $this->createAdmission($this->createPatient(), $bed);

        $this->get(route('medical.admissions.discharge.form', $admission))->assertOk();

        $this->post(route('medical.admissions.discharge', $admission), [
            'discharge_date' => now()->format('Y-m-d'),
            'discharge_time' => '12:00',
            'discharge_summary' => 'Recovered fully.',
        ])->assertSessionHasNoErrors();

        $admission->refresh();
        $this->assertSame('discharged', $admission->status);
        $this->assertSame('Recovered fully.', $admission->discharge_summary);
        $this->assertSame('available', $bed->fresh()->status);
        $this->assertSame(1, (int) $ward->fresh()->available_beds);
    }

    public function test_vitals_entry_and_validation(): void
    {
        $admission = $this->createAdmission($this->createPatient());

        $this->get(route('medical.vitals.create', ['admission_id' => $admission->id]))->assertOk();

        $this->post(route('medical.vitals.store'), [
            'admission_id' => $admission->id,
            'temperature' => 37.5,
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 80,
            'pulse' => 72,
            'spo2' => 98,
        ])->assertSessionHasNoErrors();

        $vital = VitalSign::where('admission_id', $admission->id)->firstOrFail();
        $this->assertSame('120/80', $vital->blood_pressure);
        $this->assertSame($this->owner->id, (int) $vital->recorded_by);

        // Out-of-range temperature is rejected.
        $this->post(route('medical.vitals.store'), [
            'admission_id' => $admission->id,
            'temperature' => 50,
        ])->assertSessionHasErrors(['temperature']);

        $this->get(route('medical.vitals.index', ['admission_id' => $admission->id]))
            ->assertOk()->assertSee('37.5');
        $this->get(route('medical.vitals.show', $vital))->assertOk();

        $this->get(route('medical.admissions.show', $admission))->assertOk()->assertSee('37.5');
    }

    public function test_vitals_rejected_for_discharged_admission(): void
    {
        $admission = $this->createAdmission($this->createPatient());
        $admission->update(['status' => 'discharged']);

        $this->post(route('medical.vitals.store'), [
            'admission_id' => $admission->id,
            'temperature' => 37,
        ])->assertSessionHasErrors(['admission_id']);
    }

    public function test_nursing_note_lifecycle(): void
    {
        $admission = $this->createAdmission($this->createPatient());

        $this->post(route('medical.admissions.notes.store', $admission), [
            'note' => 'Patient resting comfortably.',
        ])->assertSessionHasNoErrors();

        $note = NursingNote::where('admission_id', $admission->id)->firstOrFail();
        $this->assertSame('Patient resting comfortably.', $note->note);

        $this->get(route('medical.admissions.show', $admission))
            ->assertOk()->assertSee('Patient resting comfortably.');

        $this->delete(route('medical.notes.destroy', $note))->assertRedirect();
        $this->assertDatabaseMissing('nursing_notes', ['id' => $note->id]);
    }

    public function test_discharge_summary_pdf(): void
    {
        $admission = $this->createAdmission($this->createPatient());

        // Not available before discharge.
        $this->get(route('medical.admissions.discharge-summary', $admission))->assertRedirect();

        $admission->update([
            'status' => 'discharged',
            'discharge_date' => now()->format('Y-m-d'),
            'discharge_time' => '12:00',
            'discharge_summary' => 'Stable on discharge.',
        ]);

        $response = $this->get(route('medical.admissions.discharge-summary', $admission));
        $response->assertOk();
        $this->assertStringContainsString('pdf', strtolower($response->headers->get('Content-Type')));
    }

    public function test_cross_institute_ward_is_forbidden(): void
    {
        $other = Institute::create([
            'name' => 'Other Hospital',
            'slug' => 'other-ipd-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'clinic',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $foreign = Ward::create([
            'institute_id' => $other->id,
            'name' => 'Foreign Ward',
            'type' => 'general',
            'total_beds' => 2,
            'available_beds' => 2,
            'daily_rate' => 100,
            'is_active' => true,
        ]);

        $this->get(route('medical.wards.show', $foreign))->assertForbidden();
    }

    public function test_bed_release_route(): void
    {
        $ward = $this->createWard(['total_beds' => 1, 'available_beds' => 1]);
        $bed = $this->createBed($ward, 'B-501');
        $this->createAdmission($this->createPatient(), $bed);

        $this->assertSame('occupied', $bed->fresh()->status);

        $this->post(route('medical.beds.release', $bed))->assertRedirect();
        $this->assertSame('available', $bed->fresh()->status);
        $this->assertSame(1, (int) $ward->fresh()->available_beds);
    }
}
