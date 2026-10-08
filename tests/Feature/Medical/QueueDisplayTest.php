<?php

namespace Tests\Feature\Medical;

use App\Models\Institute;
use App\Models\Medical\Appointment;
use App\Models\Medical\Doctor;
use App\Models\Medical\Patient;
use App\Models\Membership;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Workspace;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * OPD Queue Display â€” read-only fullscreen board + its JSON feed.
 *
 * Covers the contract the display depends on: feed shape, cross-institute
 * isolation (403 + doctor id filtering), the serial_only name format never
 * leaking a patient name, and the two-doctor cap.
 */
class QueueDisplayTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    private User $owner;

    private User $doctor;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->institute = Institute::create([
            'name' => 'Queue Display Hospital',
            'slug' => 'queue-display-hospital-'.uniqid(),
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

        $this->doctor = $this->makeDoctor('Display Doc');

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);

        $this->date = today()->format('Y-m-d');
    }

    private function makeDoctor(string $name, ?Institute $institute = null): User
    {
        $institute ??= $this->institute;

        $user = User::factory()->create([
            'name' => $name,
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        Doctor::create([
            'institute_id' => $institute->id,
            'user_id' => $user->id,
            'registration_number' => 'REG-'.strtoupper(uniqid()),
        ]);

        return $user;
    }

    private function makeAppointment(User $doctor, string $status, int $serial, string $firstName): Appointment
    {
        $patient = Patient::create([
            'institute_id' => $this->institute->id,
            'mr_number' => 'MR-'.uniqid().'-'.$serial,
            'first_name' => $firstName,
            'last_name' => 'Nametest',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
        ]);

        return Appointment::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => $this->date,
            'appointment_time' => sprintf('%02d:00', 9 + ($serial % 8)),
            'serial_number' => $serial,
            'status' => $status,
        ]);
    }

    private function feed(array $query = []): TestResponse
    {
        return $this->get(route('medical.queue.display.data', $query + [
            'doctors' => [(string) $this->doctor->id],
            'date' => $this->date,
        ]));
    }

    public function test_selector_board_and_feed_open_for_the_owner(): void
    {
        $this->get(route('medical.queue.display.selector'))
            ->assertOk()
            ->assertSee($this->doctor->name);

        $board = $this->get(route('medical.queue.display', [
            'doctors' => [$this->doctor->id],
            'date' => $this->date,
        ]));
        $board->assertOk()
            ->assertSee('Now Serving')
            ->assertSee($this->doctor->name)
            // One doctor on screen: the merged view picks the giant layout.
            ->assertSee('data-layout="tv"', false);

        $this->feed()->assertOk()->assertHeader('Content-Type', 'application/json');
    }

    public function test_feed_returns_the_expected_shape(): void
    {
        Setting::set('medical.queue_display.patient_name_format', 'first_name');

        $this->makeAppointment($this->doctor, 'in_progress', 1, 'Serving');
        $this->makeAppointment($this->doctor, 'checked_in', 2, 'Waiting');

        $json = $this->feed()->assertOk()->json();

        $this->assertSame(['date', 'name_format', 'generated_at', 'doctors'], array_keys($json));
        $this->assertSame($this->date, $json['date']);
        $this->assertSame('first_name', $json['name_format']);
        $this->assertNotEmpty($json['generated_at']);

        $this->assertArrayHasKey($this->doctor->id, $json['doctors']);
        $doctor = $json['doctors'][$this->doctor->id];

        $this->assertEqualsCanonicalizing(
            ['doctor_id', 'doctor_name', 'department_name', 'now_serving', 'up_next', 'waiting_count', 'total_today'],
            array_keys($doctor)
        );
        $this->assertSame($this->doctor->id, (int) $doctor['doctor_id']);
        $this->assertSame($this->doctor->name, $doctor['doctor_name']);
        $this->assertSame(1, $doctor['waiting_count']);
        $this->assertSame(2, $doctor['total_today']);

        // The patient payload is reduced to exactly these three fields.
        $this->assertSame(['serial_number', 'display_name', 'time'], array_keys($doctor['now_serving']));
        $this->assertSame(1, $doctor['now_serving']['serial_number']);
        $this->assertSame('Serving', $doctor['now_serving']['display_name']);
        $this->assertNotSame('', $doctor['now_serving']['time']);

        $this->assertCount(1, $doctor['up_next']);
        $this->assertSame(['serial_number', 'display_name', 'time'], array_keys($doctor['up_next'][0]));
        $this->assertSame('Waiting', $doctor['up_next'][0]['display_name']);
    }

    public function test_serial_only_never_leaks_a_patient_name(): void
    {
        Setting::set('medical.queue_display.patient_name_format', 'serial_only');

        $this->makeAppointment($this->doctor, 'in_progress', 1, 'Zebraquill');
        $this->makeAppointment($this->doctor, 'checked_in', 2, 'Hiddenperson');

        $content = $this->feed()->assertOk()->getContent();
        $json = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

        $doctor = $json['doctors'][$this->doctor->id];
        $this->assertSame('serial_only', $json['name_format']);
        $this->assertSame('', $doctor['now_serving']['display_name']);
        $this->assertSame('', $doctor['up_next'][0]['display_name']);

        $this->assertStringNotContainsString('"patient_name"', $content);
        $this->assertStringNotContainsString('"patient_id"', $content);
        $this->assertStringNotContainsString('Zebraquill', $content);
        $this->assertStringNotContainsString('Hiddenperson', $content);

        $this->get(route('medical.queue.display', [
            'doctors' => [$this->doctor->id],
            'date' => $this->date,
        ]))
            ->assertOk()
            ->assertDontSee('Zebraquill')
            ->assertDontSee('Hiddenperson');
    }

    public function test_doctor_selection_is_capped_at_two(): void
    {
        $third = $this->makeDoctor('Third Display Doc');
        $fourth = $this->makeDoctor('Fourth Display Doc');

        $json = $this->feed([
            'doctors' => [$this->doctor->id, $third->id, $fourth->id],
        ])->assertOk()->json();

        $this->assertCount(2, $json['doctors']);
        $this->assertSame(
            [$this->doctor->id, $third->id],
            array_map('intval', array_keys($json['doctors']))
        );

        $this->get(route('medical.queue.display', [
            'doctors' => [$this->doctor->id, $third->id, $fourth->id],
            'date' => $this->date,
        ]))
            ->assertOk()
            ->assertSee($third->name)
            ->assertDontSee($fourth->name)
            // Two doctors: the regular two-up cards, not the giant layout.
            ->assertSee('data-layout="cards"', false);
    }

    public function test_doctor_from_another_institute_is_never_displayed(): void
    {
        $rival = Institute::create([
            'name' => 'Rival Display Hospital',
            'slug' => 'rival-display-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'clinic',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        $rivalDoctor = $this->makeDoctor('Rival Display Doc', $rival);

        $json = $this->feed([
            'doctors' => [$this->doctor->id, $rivalDoctor->id],
        ])->assertOk()->json();

        $this->assertSame([$this->doctor->id], array_map('intval', array_keys($json['doctors'])));
        $this->assertStringNotContainsString('Rival Display Doc', $this->feed()->getContent());
    }

    public function test_workspace_outside_the_institute_is_forbidden(): void
    {
        $rival = Institute::create([
            'name' => 'Foreign Workspace Hospital',
            'slug' => 'foreign-workspace-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'clinic',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        // The owner holds no membership in $rival: the workspace cannot be
        // verified, so the tenant middleware rejects the whole board.
        // Each rejection clears the forged workspace, so re-forge per request.
        Workspace::set($rival->id);
        $this->feed()->assertForbidden();

        Workspace::set($rival->id);
        $this->get(route('medical.queue.display', [
            'doctors' => [$this->doctor->id],
            'date' => $this->date,
        ]))->assertForbidden();

        Workspace::set($rival->id);
        $this->get(route('medical.queue.display.selector'))->assertForbidden();
    }

    public function test_feed_requires_medical_appointments_view_permission(): void
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'medical_appointments.view'],
            ['name' => 'View Medical Appointments', 'module' => 'medical.appointments']
        );

        $limitedRole = Role::create([
            'name' => 'Queue Display No Access',
            'slug' => 'queue-display-no-access-'.uniqid(),
            'status' => 'active',
        ]);

        $staff = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        Membership::create([
            'user_id' => $staff->id,
            'institution_id' => $this->institute->id,
            'role_id' => $limitedRole->id,
            'status' => 'active',
        ]);

        $this->assertFalse($staff->hasPermission($permission->slug));

        $this->actingAs($staff, 'web');
        Workspace::set($this->institute->id);

        $this->feed()->assertForbidden();
        $this->get(route('medical.queue.display.selector'))->assertForbidden();
        $this->get(route('medical.queue.display', [
            'doctors' => [$this->doctor->id],
            'date' => $this->date,
        ]))->assertForbidden();
    }

    public function test_board_without_doctors_redirects_to_the_selector(): void
    {
        $this->get(route('medical.queue.display'))
            ->assertRedirect(route('medical.queue.display.selector'));
    }
}
