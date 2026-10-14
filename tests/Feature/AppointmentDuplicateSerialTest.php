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
 * One individual = one serial per doctor per day.
 *
 * The uniqueness key is the patient (MR number), NEVER the phone number:
 * relatives legitimately share one number and are separate patients, each
 * with their own serial. A cancelled appointment frees the slot again.
 * Covers booking, editing, transferring, walk-in and the double-submit path.
 */
class AppointmentDuplicateSerialTest extends TestCase
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
            'name' => 'Duplicate Serial Hospital',
            'slug' => 'duplicate-serial-'.uniqid(),
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

    private function createPatient(array $overrides = []): Patient
    {
        $this->post(route('medical.patients.store'), array_merge([
            'first_name' => 'Serial',
            'last_name' => 'Holder',
            'date_of_birth' => '1990-05-15',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'blood_group' => 'O+',
        ], $overrides))->assertSessionHasNoErrors();

        return Patient::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    private function book(Patient $patient, string $date, string $time = '09:00')
    {
        return $this->post(route('medical.appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $date,
            'appointment_time' => $time,
        ]);
    }

    public function test_same_patient_cannot_hold_two_serials_with_one_doctor_on_one_day(): void
    {
        $patient = $this->createPatient();
        $date = now()->addDay()->format('Y-m-d');

        $this->book($patient, $date)->assertSessionHasNoErrors();

        $this->book($patient, $date, '09:10')->assertSessionHasErrors('appointment_date');
        $this->assertStringContainsString(
            'already has serial #1',
            session('errors')->first('appointment_date')
        );

        // The rejected submit created nothing: one row, serial #1 intact.
        $this->assertSame(1, Appointment::where('institute_id', $this->institute->id)->count());
        $this->assertSame(
            1,
            (int) Appointment::where('institute_id', $this->institute->id)->firstOrFail()->serial_number
        );
    }

    public function test_relatives_sharing_one_phone_number_each_get_their_own_serial(): void
    {
        $date = now()->addDay()->format('Y-m-d');
        $sharedPhone = '017'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);

        $father = $this->createPatient(['phone' => $sharedPhone, 'first_name' => 'Father']);
        $son = $this->createPatient(['phone' => $sharedPhone, 'first_name' => 'Son']);

        $this->book($father, $date)->assertSessionHasNoErrors();
        $this->book($son, $date)->assertSessionHasNoErrors();

        $serials = Appointment::where('institute_id', $this->institute->id)
            ->orderBy('id')
            ->pluck('serial_number')
            ->all();

        $this->assertSame([1, 2], array_map('intval', $serials));
    }

    public function test_cancelled_appointment_frees_the_slot_again(): void
    {
        $patient = $this->createPatient();
        $date = now()->addDay()->format('Y-m-d');

        $this->book($patient, $date)->assertSessionHasNoErrors();
        $appointment = Appointment::where('institute_id', $this->institute->id)->firstOrFail();

        $this->delete(route('medical.appointments.destroy', $appointment))->assertRedirect();

        // The slot is free again — and the cancelled row keeps its serial.
        $this->book($patient, $date, '09:10')->assertSessionHasNoErrors();
        $this->assertSame(
            1,
            Appointment::where('institute_id', $this->institute->id)
                ->where('status', '!=', 'cancelled')
                ->count()
        );
    }

    public function test_walk_in_reuses_the_serial_the_patient_already_holds_today(): void
    {
        $patient = $this->createPatient();
        $today = now()->format('Y-m-d');

        $this->book($patient, $today)->assertSessionHasNoErrors();

        $this->post(route('medical.prescriptions.walk-in'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
        ])->assertRedirect();

        $this->assertSame(1, Appointment::where('institute_id', $this->institute->id)->count());
        $visit = Appointment::where('institute_id', $this->institute->id)->firstOrFail();
        $this->assertSame(1, (int) $visit->serial_number);
        $this->assertStringContainsString('already has serial #1', (string) session('status'));
    }

    public function test_edit_cannot_move_a_patient_onto_a_slot_they_already_hold(): void
    {
        $patient = $this->createPatient();
        $dayOne = now()->addDay()->format('Y-m-d');
        $dayTwo = now()->addDays(2)->format('Y-m-d');

        $this->book($patient, $dayOne)->assertSessionHasNoErrors();
        $this->book($patient, $dayTwo, '10:00')->assertSessionHasNoErrors();
        $second = Appointment::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();

        $this->put(route('medical.appointments.update', $second), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $dayOne,
            'appointment_time' => '10:00',
        ])->assertSessionHasErrors('appointment_date');

        $this->assertSame($dayTwo, $second->fresh()->appointment_date->format('Y-m-d'));
    }

    public function test_transfer_cannot_land_on_a_day_the_patient_already_holds(): void
    {
        $patient = $this->createPatient();
        $dayOne = now()->addDay()->format('Y-m-d');
        $dayTwo = now()->addDays(2)->format('Y-m-d');

        $this->book($patient, $dayOne)->assertSessionHasNoErrors();
        $this->book($patient, $dayTwo, '10:00')->assertSessionHasNoErrors();
        $second = Appointment::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();

        $this->post(route('medical.appointments.transfer', $second), [
            'appointment_date' => $dayOne,
        ])->assertSessionHasErrors('appointment_date');

        $this->assertSame($dayTwo, $second->fresh()->appointment_date->format('Y-m-d'));
    }
}
