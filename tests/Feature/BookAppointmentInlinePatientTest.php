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
 * Book Appointment popup registers the patient inline: with no patient chosen
 * the popup's own fields (name, gender, age, blood group, relation, phone)
 * become a new patient and the appointment books against it in one submit.
 */
class BookAppointmentInlinePatientTest extends TestCase
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
            'name' => 'Inline Booking Hospital',
            'slug' => 'inline-booking-hospital-'.uniqid(),
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

    private function bookingPayload(array $overrides = []): array
    {
        return array_merge([
            'doctor_id' => $this->doctor->id,
            'appointment_date' => now()->addDay()->format('Y-m-d'),
            'appointment_time' => '10:30',
        ], $overrides);
    }

    public function test_inline_patient_and_appointment_are_created_together(): void
    {
        $payload = $this->bookingPayload([
            'phone' => '01712345678',
            'first_name' => 'Walk',
            'last_name' => 'In',
            'gender' => 'female',
            'age' => 30,
            'age_unit' => 'years',
            'blood_group' => 'A+',
            'relation_to_primary' => 'Self',
        ]);

        $before = Patient::where('institute_id', $this->institute->id)->count();

        $this->post(route('medical.appointments.store'), $payload)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame($before + 1, Patient::where('institute_id', $this->institute->id)->count());

        $patient = Patient::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
        $this->assertSame('Walk', $patient->first_name);
        $this->assertSame('In', $patient->last_name);
        $this->assertSame('female', $patient->gender);
        $this->assertSame('A+', $patient->blood_group);
        $this->assertSame('Self', $patient->relation_to_primary);
        $this->assertMatchesRegularExpression('/^MR-\d{4}-\d{5}$/', $patient->mr_number);
        // Stored E.164 with the institute country code.
        $this->assertSame('+8801712345678', $patient->phone);

        $appointment = Appointment::where('institute_id', $this->institute->id)->firstOrFail();
        $this->assertSame($patient->id, (int) $appointment->patient_id);
        $this->assertSame($this->doctor->id, (int) $appointment->doctor_id);
        $this->assertStringStartsWith('10:30', (string) $appointment->appointment_time);
    }

    public function test_age_alone_derives_the_date_of_birth(): void
    {
        $this->post(route('medical.appointments.store'), $this->bookingPayload([
            'first_name' => 'Age',
            'last_name' => 'Only',
            'gender' => 'male',
            'age' => 30,
            'age_unit' => 'years',
        ]))->assertSessionHasNoErrors();

        $patient = Patient::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
        $this->assertSame(now()->subYears(30)->toDateString(), $patient->date_of_birth->toDateString());
    }

    public function test_existing_patient_booking_ignores_the_inline_fields(): void
    {
        $existing = Patient::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Existing',
            'last_name' => 'Case',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'phone' => 'NA-'.uniqid(),
            'mr_number' => 'MR-2026-00001',
        ]);

        $before = Patient::where('institute_id', $this->institute->id)->count();

        $this->post(route('medical.appointments.store'), $this->bookingPayload([
            'patient_id' => $existing->id,
            'first_name' => 'Should',
            'last_name' => 'BeIgnored',
            'gender' => 'female',
            'age' => 22,
        ]))->assertSessionHasNoErrors();

        $this->assertSame($before, Patient::where('institute_id', $this->institute->id)->count());

        $appointment = Appointment::where('institute_id', $this->institute->id)->firstOrFail();
        $this->assertSame($existing->id, (int) $appointment->patient_id);
    }

    public function test_booking_without_patient_or_inline_details_is_rejected(): void
    {
        $this->post(route('medical.appointments.store'), $this->bookingPayload())
            ->assertSessionHasErrors(['first_name', 'gender', 'age']);

        $this->assertSame(0, Appointment::where('institute_id', $this->institute->id)->count());
        $this->assertSame(0, Patient::where('institute_id', $this->institute->id)->count());
    }

    public function test_book_popup_renders_the_inline_patient_fields(): void
    {
        $response = $this->get(route('medical.appointments.index'));
        $response->assertOk();

        $response->assertSee('id="bk_phone"', false);
        $response->assertSee('id="bk_mr_number"', false);
        $response->assertSee('Auto preview');
        $response->assertSee('id="bk_patient_combo"', false);
        $response->assertSee('Search by name or mobile no');
        $response->assertSee('id="bk_first_name"', false);
        $response->assertSee('id="bk_last_name"', false);
        $response->assertSee('id="bk_gender_male"', false);
        $response->assertSee('id="bk_dob"', false);
        $response->assertSee('id="bk_age"', false);
        $response->assertSee('id="bk_blood_group"', false);
        $response->assertSee('id="bk_relation"', false);
        $response->assertSee('Add Family Member');
        $response->assertSee('Same phone? Choose the relation');
    }

    public function test_book_popup_is_wide_and_responsive(): void
    {
        $response = $this->get(route('medical.appointments.index'));
        $response->assertOk();

        $response->assertSee('modal-xl modal-dialog-scrollable modal-fullscreen-lg-down', false);
        $response->assertSee('max-width: min(1320px, calc(100vw - 3rem))', false);
        $response->assertSee('col-lg-4 col-md-6', false);
        $response->assertSee('col-lg-3 col-md-6', false);
        $response->assertSee('modal-footer flex-wrap', false);
        $response->assertSee('#bookAppointmentModal #bk_patient_combo', false);
        $response->assertSee('padding-left: 1.25rem', false);
    }

    public function test_family_member_links_to_the_primary_contact(): void
    {
        $primary = Patient::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Primary',
            'last_name' => 'Contact',
            'date_of_birth' => '1985-04-04',
            'gender' => 'male',
            'phone' => '+8801799999999',
            'mr_number' => 'MR-2026-00002',
            'relation_to_primary' => 'Self',
        ]);

        $this->post(route('medical.appointments.store'), $this->bookingPayload([
            'phone' => '01799999999',
            'first_name' => 'Son',
            'last_name' => 'Of Primary',
            'gender' => 'male',
            'age' => 12,
            'relation_to_primary' => 'Son',
            'primary_contact_id' => $primary->id,
        ]))->assertSessionHasNoErrors();

        $child = Patient::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
        $this->assertSame('Son', $child->relation_to_primary);
        $this->assertTrue((bool) $child->is_dependent);
        $this->assertSame($primary->id, (int) $child->primary_contact_id);
    }
}
