<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Appointment;
use App\Models\Medical\Doctor;
use App\Models\Medical\Patient;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 1 — HMS Patient & OPD verification.
 *
 * Follows the repo's de-facto test pattern (DatabaseTransactions, web guard
 * + Workspace context, institute-owner membership). CSRF is disabled for the
 * HTTP calls only; auth, tenant, medical-domain and permission middleware
 * all still run.
 */
class MedicalPhase1Test extends TestCase
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
            'name' => 'Medical Test Hospital',
            'slug' => 'medical-test-hospital-'.uniqid(),
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

    private function patientPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'blood_group' => 'O+',
        ], $overrides);
    }

    private function createPatient(array $overrides = []): Patient
    {
        $response = $this->post(route('medical.patients.store'), $this->patientPayload($overrides));
        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status');
        $response->assertRedirect();

        return Patient::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    public function test_patient_store_generates_mr_number(): void
    {
        $patient = $this->createPatient();

        // Stored contract: MR-YYYY-NNNNN (database-backed per-institute
        // yearly sequence, no tenant segment); humans read MR-YY-NNNNN.
        $this->assertMatchesRegularExpression(
            '/^MR-\d{4}-\d{5}$/',
            $patient->mr_number
        );
        $this->assertSame($this->institute->id, (int) $patient->institute_id);
    }

    public function test_mr_numbers_are_unique_and_sequential(): void
    {
        $first = $this->createPatient();
        $second = $this->createPatient();

        $this->assertNotSame($first->mr_number, $second->mr_number);

        // Same institute + year: contiguous allocation, no gaps in clean flow.
        $firstSeq = (int) substr($first->mr_number, -5);
        $secondSeq = (int) substr($second->mr_number, -5);
        $this->assertSame($firstSeq + 1, $secondSeq);

        // Display shape shortens the year (MR-2026-00002 → MR-26-00002).
        $this->assertSame(
            'MR-'.substr(now()->format('Y'), 2).'-'.substr($second->mr_number, -5),
            \App\Services\Medical\NumberSequenceService::display($second->mr_number)
        );
    }

    public function test_patient_store_validation(): void
    {
        // Only name + age are mandatory now.
        $response = $this->post(route('medical.patients.store'), ['last_name' => 'X']);
        $response->assertSessionHasErrors(['first_name', 'age', 'date_of_birth']);

        // Age alone (with first name) should pass and auto-derive DOB.
        $response = $this->post(route('medical.patients.store'), [
            'first_name' => 'AgeOnly',
            'age' => 30,
        ]);
        $response->assertSessionHasNoErrors();
    }

    public function test_patient_index_search_and_show(): void
    {
        $patient = $this->createPatient(['first_name' => 'Searchable']);

        $this->get(route('medical.patients.index', ['search' => 'Searchable']))
            ->assertOk()
            ->assertSee(clinical_no($patient->mr_number));

        $this->get(route('medical.patients.show', $patient))->assertOk()->assertSee('Searchable');
        $this->get(route('medical.patients.history', $patient))->assertOk();
        $this->get(route('medical.patients.edit', $patient))->assertOk();
    }

    public function test_patient_update_and_soft_delete(): void
    {
        $patient = $this->createPatient();

        $this->put(route('medical.patients.update', $patient), $this->patientPayload([
            'first_name' => 'Updated',
            'phone' => $patient->phone,
        ]))->assertRedirect();

        $this->assertSame('Updated', $patient->fresh()->first_name);

        $this->delete(route('medical.patients.destroy', $patient))->assertRedirect();
        $this->assertSoftDeleted('patients', ['id' => $patient->id]);
    }

    public function test_cross_institute_patient_is_forbidden(): void
    {
        $other = Institute::create([
            'name' => 'Other Hospital',
            'slug' => 'other-hospital-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'clinic',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $foreign = Patient::create($this->patientPayload() + [
            'institute_id' => $other->id,
            'mr_number' => 'MR-2000-999-00001',
        ]);

        $this->get(route('medical.patients.show', $foreign))->assertForbidden();
    }

    public function test_appointment_store_assigns_serials(): void
    {
        $patient = $this->createPatient();
        $date = now()->addDay()->format('Y-m-d');

        $payload = [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $date,
            'appointment_time' => '09:00',
        ];

        $this->post(route('medical.appointments.store'), $payload)->assertRedirect();
        $this->post(route('medical.appointments.store'), $payload + ['appointment_time' => '09:10'])
            ->assertRedirect();

        $serials = Appointment::where('institute_id', $this->institute->id)
            ->orderBy('id')
            ->pluck('serial_number')
            ->all();

        $this->assertSame([1, 2], array_map('intval', $serials));
    }

    public function test_appointment_lifecycle_and_queue(): void
    {
        $patient = $this->createPatient();
        $date = now()->addDay()->format('Y-m-d');

        $this->post(route('medical.appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $date,
            'appointment_time' => '09:00',
        ])->assertRedirect();

        $appointment = Appointment::where('institute_id', $this->institute->id)->firstOrFail();

        $this->get(route('medical.appointments.index', ['date' => $date]))->assertOk();
        $this->get(route('medical.appointments.show', $appointment))->assertOk();

        $this->get(route('medical.appointments.queue', ['doctor_id' => $this->doctor->id, 'date' => $date]))
            ->assertOk()
            ->assertSee('Total: 1');

        $this->post(route('medical.appointments.checkin', $appointment))->assertRedirect();
        $this->assertSame('checked_in', $appointment->fresh()->status);

        $this->post(route('medical.appointments.complete', $appointment))->assertRedirect();
        $this->assertSame('completed', $appointment->fresh()->status);

        $this->delete(route('medical.appointments.destroy', $appointment))->assertRedirect();
        $this->assertSame('cancelled', $appointment->fresh()->status);
    }

    public function test_appointment_validation_rejects_foreign_patient(): void
    {
        $response = $this->post(route('medical.appointments.store'), [
            'patient_id' => 999999999,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => now()->addDay()->format('Y-m-d'),
            'appointment_time' => '09:00',
        ]);

        $response->assertSessionHasErrors(['patient_id']);
    }
}
