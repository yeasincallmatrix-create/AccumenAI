<?php

namespace Tests\Feature\Medical;

use App\Models\Institute;
use App\Models\Medical\Appointment;
use App\Models\Medical\Doctor;
use App\Models\Medical\Patient;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Services\Medical\AppointmentFeeService;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Phase 3 — first-visit vs follow-up OPD fee quoting at booking time.
 *
 * AppointmentFeeService::calculateFor resolves the rate off the doctor's
 * profile (first_visit_fee / follow_up_fee / follow_up_days) and the
 * patient's most recent COMPLETED visit with the same doctor in the same
 * institute; appointments.store stamps the quote as fee_applied (null when
 * the doctor user has no Doctor profile — booking then continues unchanged).
 */
class AppointmentFeeQuoteTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    private User $owner;

    private User $doctorUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'OPD Fee Quote Hospital',
            'slug' => 'opd-fee-quote-'.uniqid(),
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

        $this->doctorUser = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
    }

    private function createDoctorProfile(array $overrides = []): Doctor
    {
        return Doctor::create(array_merge([
            'institute_id' => $this->institute->id,
            'user_id' => $this->doctorUser->id,
            'registration_number' => 'REG-'.strtoupper(uniqid()),
            'first_visit_fee' => 700,
            'follow_up_fee' => 300,
            'follow_up_days' => 30,
            'collect_fee_before_visit' => false,
        ], $overrides));
    }

    private function createPatient(): Patient
    {
        return Patient::create([
            'institute_id' => $this->institute->id,
            'mr_number' => 'MR-'.uniqid(),
            'first_name' => 'OPD',
            'last_name' => 'Quote',
            'date_of_birth' => '1992-04-12',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
        ]);
    }

    private function completedVisit(Patient $patient, int $daysAgo, ?User $doctorUser = null): void
    {
        Appointment::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $patient->id,
            'doctor_id' => ($doctorUser ?? $this->doctorUser)->id,
            'appointment_date' => today()->subDays($daysAgo)->format('Y-m-d'),
            'appointment_time' => '10:00',
            'serial_number' => 1,
            'status' => 'completed',
        ]);
    }

    private function book(Patient $patient): Appointment
    {
        $date = today()->addDay()->format('Y-m-d');

        $this->post(route('medical.appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctorUser->id,
            'appointment_date' => $date,
            'appointment_time' => '11:00',
        ])->assertSessionHasNoErrors();

        return Appointment::where('institute_id', $this->institute->id)
            ->where('patient_id', $patient->id)
            ->whereDate('appointment_date', $date)
            ->firstOrFail();
    }

    public function test_never_visited_patient_quotes_first_visit_fee(): void
    {
        $this->createDoctorProfile();
        $patient = $this->createPatient();

        $booked = $this->book($patient);

        $this->assertSame(700.0, (float) $booked->fee_applied);
    }

    public function test_completed_visit_inside_window_quotes_follow_up_fee(): void
    {
        $this->createDoctorProfile(); // follow_up_days = 30
        $patient = $this->createPatient();
        $this->completedVisit($patient, daysAgo: 5);

        $booked = $this->book($patient);

        $this->assertSame(300.0, (float) $booked->fee_applied);
    }

    public function test_visit_outside_window_quotes_first_visit_fee(): void
    {
        $this->createDoctorProfile(); // 60 days > 30-day window
        $patient = $this->createPatient();
        $this->completedVisit($patient, daysAgo: 60);

        $booked = $this->book($patient);

        $this->assertSame(700.0, (float) $booked->fee_applied);
    }

    public function test_boundary_day_is_still_follow_up(): void
    {
        // Exactly follow_up_days ago: hasFollowUpRateFor is inclusive
        // (<= max(1, follow_up_days)) so day 30 still earns the rate.
        $this->createDoctorProfile();
        $patient = $this->createPatient();
        $this->completedVisit($patient, daysAgo: 30);

        $booked = $this->book($patient);

        $this->assertSame(300.0, (float) $booked->fee_applied);
    }

    public function test_completed_visit_with_another_doctor_does_not_count(): void
    {
        // Follow-up is per doctor: a completed visit with a DIFFERENT
        // doctor must not unlock this doctor's follow-up rate.
        $this->createDoctorProfile();
        $otherDoctor = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        Doctor::create([
            'institute_id' => $this->institute->id,
            'user_id' => $otherDoctor->id,
            'registration_number' => 'REG-'.strtoupper(uniqid()),
            'first_visit_fee' => 700,
            'follow_up_fee' => 300,
            'follow_up_days' => 30,
            'collect_fee_before_visit' => false,
        ]);
        $patient = $this->createPatient();
        $this->completedVisit($patient, daysAgo: 5, doctorUser: $otherDoctor);

        $booked = $this->book($patient);

        $this->assertSame(700.0, (float) $booked->fee_applied);
    }

    public function test_doctor_without_profile_books_without_fee(): void
    {
        // Institute member WITHOUT a Doctor profile: booking must still
        // validate (membership satisfies the doctor-in-institute contract)
        // while calculateFor resolves no profile → fee_applied stays null.
        Membership::create([
            'user_id' => $this->doctorUser->id,
            'institution_id' => $this->institute->id,
            'role_id' => Role::where('slug', 'receptionist')->value('id'),
            'status' => 'active',
        ]);
        $patient = $this->createPatient();

        $booked = $this->book($patient);

        $this->assertNull($booked->fee_applied);
    }

    public function test_custom_follow_up_fee_overrides_the_default(): void
    {
        // Doctor's own follow_up_fee (not the model's 500 fallback) is what
        // an in-window booking stamps as fee_applied.
        $this->createDoctorProfile(['follow_up_fee' => 250]);
        $patient = $this->createPatient();
        $this->completedVisit($patient, daysAgo: 5);

        $booked = $this->book($patient);

        $this->assertSame(250.0, (float) $booked->fee_applied);
    }

    public function test_fee_service_reports_follow_up_flag(): void
    {
        $this->createDoctorProfile();
        $patient = $this->createPatient();
        $this->completedVisit($patient, daysAgo: 5);

        $quote = app(AppointmentFeeService::class)->calculateFor(
            (int) $this->doctorUser->id,
            $patient,
            $this->institute->id
        );

        $this->assertSame(300.0, (float) $quote['fee']);
        $this->assertTrue($quote['is_follow_up']);
    }
}
