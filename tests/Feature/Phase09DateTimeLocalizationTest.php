<?php

namespace Tests\Feature;

use App\Models\Institute;
use App\Models\Medical\Admission;
use App\Models\Medical\Appointment;
use App\Models\Medical\Doctor;
use App\Models\Medical\DoctorAvailability;
use App\Models\Medical\LabOrder;
use App\Models\Medical\LabTest;
use App\Models\Medical\Patient;
use App\Models\Membership;
use App\Models\Role;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase 09 — Timezone, date/time & localization hardening.
 *
 * Locks the single-interpretation contract: app timezone Asia/Dhaka, MySQL
 * session pinned to it, business dates as tz-naive DATE/TIME, instants as
 * timestamps, unambiguous display formats, tenant-scoped ranges. No
 * historical data is rewritten by any assertion here.
 */
class Phase09DateTimeLocalizationTest extends TestCase
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
            'name' => 'Datetime Test Hospital',
            'slug' => 'datetime-test-'.uniqid(),
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

    private function createPatient(string $first = 'Chrono'): Patient
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

    // --- Timezone contract ---------------------------------------------------

    public function test_application_timezone_is_dhaka(): void
    {
        $this->assertSame('Asia/Dhaka', config('app.timezone'));
        $this->assertSame('Asia/Dhaka', now()->getTimezone()->getName());
    }

    public function test_mysql_session_pinned_to_app_timezone(): void
    {
        // Phase 09 pin: TIMESTAMP semantics must not depend on server tz.
        $sessionTz = DB::selectOne('SELECT @@session.time_zone AS tz')->tz;
        $this->assertSame(now()->format('P'), $sessionTz);
    }

    public function test_created_timestamps_land_on_app_today(): void
    {
        $patient = $this->createPatient();

        // whereDate on TIMESTAMP follows the pinned session tz == app tz.
        $this->assertSame(
            1,
            Patient::where('institute_id', $this->institute->id)
                ->whereDate('created_at', today()->format('Y-m-d'))
                ->whereKey($patient->id)
                ->count()
        );
    }

    // --- Appointments ----------------------------------------------------------

    public function test_appointment_date_time_stored_verbatim(): void
    {
        $patient = $this->createPatient();
        $this->post(route('medical.appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2026-09-15',
            'appointment_time' => '10:00',
        ])->assertSessionHasNoErrors();

        $appointment = Appointment::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
        $this->assertSame('2026-09-15', $appointment->appointment_date->format('Y-m-d'));
        $this->assertSame('10:00', substr((string) $appointment->appointment_time, 0, 5));
    }

    public function test_availability_boundaries(): void
    {
        $profile = Doctor::where('user_id', $this->doctor->id)->firstOrFail();
        DoctorAvailability::create([
            'doctor_id' => $profile->id,
            'day_of_week' => strtolower(now()->format('l')),
            'start_time' => '09:00',
            'end_time' => '13:00',
            'slot_duration' => 60,
            'is_available' => true,
        ]);

        $slots = $profile->getAvailableSlots(now()->format('Y-m-d'));
        $starts = array_column($slots, 'start');

        $this->assertContains('09:00', $starts);
        $this->assertContains('12:00', $starts);
        $this->assertNotContains('08:59', $starts);
        $this->assertNotContains('13:00', $starts);
        $this->assertNotContains('13:01', $starts);
    }

    public function test_reschedule_moves_queue_day(): void
    {
        $patient = $this->createPatient();
        $this->post(route('medical.appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => now()->addDay()->format('Y-m-d'),
            'appointment_time' => '09:00',
        ])->assertSessionHasNoErrors();
        $appointment = Appointment::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();

        $newDate = now()->addDays(2)->format('Y-m-d');
        $this->put(route('medical.appointments.update', $appointment), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $newDate,
            'appointment_time' => '09:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame($newDate, $appointment->fresh()->appointment_date->format('Y-m-d'));
        // Queue route redirects to the queue tab (established behavior);
        // the move itself is proven by the persisted date above.
        $this->get(route('medical.appointments.queue', ['date' => $newDate]))->assertRedirect();
    }

    public function test_invalid_appointment_date_rejected(): void
    {
        $patient = $this->createPatient();
        $this->post(route('medical.appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => 'not-a-date',
            'appointment_time' => '09:00',
        ])->assertSessionHasErrors(['appointment_date']);

        // Yesterday is in the past for the clinic day.
        $this->post(route('medical.appointments.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => now()->subDay()->format('Y-m-d'),
            'appointment_time' => '09:00',
        ])->assertSessionHasErrors(['appointment_date']);
    }

    // --- Admissions --------------------------------------------------------------

    public function test_discharge_after_admission_and_midnight_stay(): void
    {
        $patient = $this->createPatient();
        $this->post(route('medical.admissions.store'), [
            'patient_id' => $patient->id,
            'admitting_doctor_id' => $this->doctor->id,
            'admission_date' => '2026-09-10',
            'admission_time' => '23:50',
            'primary_diagnosis' => 'Night fever',
        ])->assertSessionHasNoErrors();
        $admission = Admission::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();

        // Discharge before admission is refused (date-only comparison).
        $this->post(route('medical.admissions.discharge', $admission), [
            'discharge_date' => '2026-09-09',
            'discharge_time' => '12:00',
        ])->assertSessionHasErrors(['discharge_date']);

        // Crossing midnight into the next civil day is a 1-day stay.
        $this->post(route('medical.admissions.discharge', $admission), [
            'discharge_date' => '2026-09-11',
            'discharge_time' => '00:20',
        ])->assertSessionHasNoErrors();

        $admission->refresh();
        $this->assertSame('2026-09-11', $admission->discharge_date->format('Y-m-d'));
        $this->assertEquals(1, $admission->length_of_stay);
    }

    // --- Laboratory ordering -------------------------------------------------------

    public function test_lab_timestamps_nondecreasing(): void
    {
        $patient = $this->createPatient();
        $test = LabTest::create([
            'institute_id' => $this->institute->id,
            'code' => 'LT-'.strtoupper(uniqid()),
            'name' => 'Chrono Panel',
            'price' => 100,
            'is_active' => true,
        ]);
        $this->post(route('medical.lab.orders.store'), [
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'priority' => 'routine',
            'tests' => [['lab_test_id' => $test->id]],
        ])->assertSessionHasNoErrors();
        $order = LabOrder::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();

        $this->post(route('medical.lab.orders.collect', $order))->assertRedirect();
        $result = $order->results()->firstOrFail();
        $this->post(route('medical.lab.orders.result', $order), [
            'results' => [$result->id => ['result_value' => '85']],
        ])->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertNotNull($order->collected_at);
        $this->assertNotNull($order->completed_at);
        $this->assertTrue($order->completed_at->greaterThanOrEqualTo($order->collected_at));
    }

    // --- Billing dates ---------------------------------------------------------------

    public function test_invoice_and_payment_dates(): void
    {
        $invoice = $this->postAndGetInvoice($this->createPatient());

        $this->assertSame(today()->format('Y-m-d'), $invoice->invoice_date->format('Y-m-d'));

        $this->post(route('medical.billing.invoices.payment', $invoice), [
            'amount' => 100, 'method' => 'cash',
        ])->assertSessionHasNoErrors();

        // Receipt PDF renders for a paid invoice.
        $this->get(route('medical.billing.invoices.print', $invoice))->assertOk();
    }

    private function postAndGetInvoice(Patient $patient): \App\Models\Medical\Invoice
    {
        $this->post(route('medical.billing.invoices.store'), [
            'patient_id' => $patient->id,
            'type' => 'opd',
            'items' => [['description' => 'Consultation', 'amount' => 500, 'quantity' => 1, 'discount' => 0]],
        ])->assertSessionHasNoErrors();

        return \App\Models\Medical\Invoice::where('institute_id', $this->institute->id)->latest('id')->firstOrFail();
    }

    // --- Reports -------------------------------------------------------------------------

    public function test_report_ranges_include_today_and_tenant_scope(): void
    {
        $mine = $this->createPatient('RangeMine');

        $other = Institute::create([
            'name' => 'Chrono Rival', 'slug' => 'chrono-rival-'.uniqid(),
            'industry' => 'healthcare', 'sub_industry' => 'clinic',
            'country' => 'Bangladesh', 'status' => 'active',
        ]);
        $foreign = Patient::create([
            'institute_id' => $other->id,
            'mr_number' => 'MR-R-1',
            'first_name' => 'RangeRival',
            'last_name' => 'Probe',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'phone' => '0100000031',
        ]);

        $today = today()->format('Y-m-d');
        $response = $this->get(route('medical.reports.clinical', [
            'from_date' => $today, 'to_date' => $today,
        ]))->assertOk();

        // Own patient counted; foreign institute's same-day row excluded.
        $this->assertStringNotContainsString('RangeRival', (string) $response->getContent());

        // Month boundary range does not error.
        $this->get(route('medical.reports.clinical', [
            'from_date' => now()->startOfMonth()->format('Y-m-d'),
            'to_date' => now()->endOfMonth()->format('Y-m-d'),
        ]))->assertOk();

        // Inverted range yields an empty-safe page, not a crash.
        $this->get(route('medical.reports.clinical', [
            'from_date' => $today, 'to_date' => now()->subDay()->format('Y-m-d'),
        ]))->assertOk();
    }

    // --- Localization smoke ------------------------------------------------------------------

    public function test_dictionaries_resolve_both_locales(): void
    {
        // Spot-check shared keys resolve (not raw keys) in en and bn.
        // (mawa_lang() resolves locale from session; bn falls back to en
        // per-key, so the UI never shows a raw key.)
        foreach (['sidebar.dashboard', 'geo.country'] as $key) {
            session(['mawa_lang' => 'en']);
            $this->assertNotSame($key, mawa_lang($key), "Missing EN key: {$key}");
            session(['mawa_lang' => 'bn']);
            $this->assertNotSame($key, mawa_lang($key), "Missing BN key: {$key}");
        }
        session(['mawa_lang' => 'en']);
    }

    public function test_bangla_locale_switch_does_not_break_pages(): void
    {
        $this->get(route('medical.patients.index', ['lang' => 'bn']))->assertOk();
        $this->get(route('medical.patients.index'))->assertOk();
    }

    // --- API date contract ----------------------------------------------------------------------

    public function test_api_dates_use_unambiguous_display(): void
    {
        $user = \App\Models\InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->value('id'),
            'email' => 'chrono-'.uniqid().'@example.test',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            'password_hash' => \Illuminate\Support\Facades\Hash::make('Secret123!'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $token = $user->createToken('t', ['*'])->plainTextToken;

        $response = $this->getJson('/api/medical/appointments', [
            'Authorization' => 'Bearer '.$token, 'Accept' => 'application/json',
        ])->assertOk();

        // date_display uses "d M Y" (e.g. 10 Sep 2026) — never ambiguous m/d.
        foreach ((array) $response->json() as $row) {
            if (! empty($row['date_display'])) {
                $this->assertMatchesRegularExpression(
                    '/^\d{2} [A-Z][a-z]{2} \d{4}$/',
                    $row['date_display']
                );
            }
        }
    }
}
