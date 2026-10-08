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
 * Live Broadcast — one doctor on the shared queue display + its JSON feed.
 *
 * Merged with the OPD Queue Display: same view, same payload. Pins the
 * contract the board depends on: the feed shape, {doctor} being a
 * users.id (not a medical_doctors.id), cross-institute isolation, the
 * permission gate, the doctor fence and the single-doctor giant layout.
 */
class LiveBroadcastTest extends TestCase
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
            'name' => 'Live Broadcast Hospital',
            'slug' => 'live-broadcast-hospital-'.uniqid(),
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

        $this->doctor = $this->makeDoctor('Broadcast Doc');

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

    private function makeAppointment(User $doctor, string $status, int $serial, string $firstName, ?Institute $institute = null): Appointment
    {
        $institute ??= $this->institute;

        $patient = Patient::create([
            'institute_id' => $institute->id,
            'mr_number' => 'MR-'.uniqid().'-'.$serial,
            'first_name' => $firstName,
            'last_name' => 'Nametest',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
        ]);

        return Appointment::create([
            'institute_id' => $institute->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => $this->date,
            'appointment_time' => sprintf('%02d:00', 9 + ($serial % 8)),
            'serial_number' => $serial,
            'status' => $status,
        ]);
    }

    private function broadcast(?User $doctor = null): TestResponse
    {
        return $this->get(route('medical.appointments.live', [
            'doctor' => ($doctor ?? $this->doctor)->id,
        ]));
    }

    private function feed(?User $doctor = null): TestResponse
    {
        return $this->get(route('medical.appointments.live.data', [
            'doctor' => ($doctor ?? $this->doctor)->id,
        ]));
    }

    public function test_broadcast_view_and_feed_open_for_the_owner(): void
    {
        // One doctor on screen: the merged view picks the giant layout.
        $this->broadcast()
            ->assertOk()
            ->assertSee('Now Serving')
            ->assertSee('Broadcast Doc')
            ->assertSee('Live Broadcast Hospital')
            ->assertSee('data-layout="tv"', false);

        $this->feed()
            ->assertOk()
            ->assertHeader('Content-Type', 'application/json');
    }

    public function test_feed_returns_users_id_and_the_queue_counts(): void
    {
        Setting::set('medical.queue_display.patient_name_format', 'first_name');

        $this->makeAppointment($this->doctor, 'in_progress', 1, 'Serving');
        $this->makeAppointment($this->doctor, 'checked_in', 2, 'Waiting');
        $this->makeAppointment($this->doctor, 'scheduled', 3, 'Queued');

        // Another doctor's visit must never leak into this board.
        $other = $this->makeDoctor('Other Broadcast Doc');
        $this->makeAppointment($other, 'scheduled', 4, 'Elsewhere');

        $json = $this->feed()->assertOk()->json();

        // Merged with the queue display: one payload shape for both views.
        $this->assertSame(['date', 'name_format', 'generated_at', 'doctors'], array_keys($json));
        $this->assertSame($this->date, $json['date']);
        $this->assertSame('first_name', $json['name_format']);
        $this->assertNotEmpty($json['generated_at']);

        // {doctor} in the URL is users.id, and so is the key the feed echoes.
        $this->assertArrayHasKey($this->doctor->id, $json['doctors']);
        $this->assertArrayNotHasKey($other->id, $json['doctors']);
        $this->assertCount(1, $json['doctors']);

        $row = $json['doctors'][$this->doctor->id];
        $this->assertEqualsCanonicalizing(
            ['doctor_id', 'doctor_name', 'department_name', 'now_serving', 'up_next', 'waiting_count', 'total_today'],
            array_keys($row)
        );
        $this->assertSame($this->doctor->id, (int) $row['doctor_id']);
        $this->assertSame($this->doctor->name, $row['doctor_name']);
        $this->assertSame(3, $row['total_today']);
        $this->assertSame(2, $row['waiting_count']);

        // The patient payload is reduced to exactly these three fields.
        $this->assertSame(['serial_number', 'display_name', 'time'], array_keys($row['now_serving']));
        $this->assertSame(1, $row['now_serving']['serial_number']);
        $this->assertSame('Serving', $row['now_serving']['display_name']);
        $this->assertNotSame('', $row['now_serving']['time']);

        $this->assertCount(2, $row['up_next']);
        $this->assertSame(['serial_number', 'display_name', 'time'], array_keys($row['up_next'][0]));
        $this->assertSame(['Waiting', 'Queued'], collect($row['up_next'])->pluck('display_name')->all());
    }

    public function test_doctor_from_another_institute_is_forbidden(): void
    {
        $rival = Institute::create([
            'name' => 'Rival Live Hospital',
            'slug' => 'rival-live-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'clinic',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
        $rivalDoctor = $this->makeDoctor('Rival Live Doc', $rival);

        $this->broadcast($rivalDoctor)->assertForbidden();
        $this->feed($rivalDoctor)->assertForbidden();
    }

    public function test_broadcast_requires_medical_appointments_view_permission(): void
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'medical_appointments.view'],
            ['name' => 'View Medical Appointments', 'module' => 'medical.appointments']
        );

        $limitedRole = Role::create([
            'name' => 'Live Broadcast No Access',
            'slug' => 'live-broadcast-no-access-'.uniqid(),
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

        $this->broadcast()->assertForbidden();
        $this->feed()->assertForbidden();
    }

    public function test_fenced_doctor_cannot_open_another_doctors_broadcast(): void
    {
        $permission = Permission::firstOrCreate(
            ['slug' => 'medical_appointments.view'],
            ['name' => 'View Medical Appointments', 'module' => 'medical.appointments']
        );

        $doctorRole = Role::create([
            'name' => 'Live Broadcast Doctor',
            'slug' => 'live-broadcast-doctor-'.uniqid(),
            'status' => 'active',
        ]);
        $doctorRole->permissions()->attach($permission->id);

        Membership::create([
            'user_id' => $this->doctor->id,
            'institution_id' => $this->institute->id,
            'role_id' => $doctorRole->id,
            'status' => 'active',
        ]);

        $colleague = $this->makeDoctor('Colleague Broadcast Doc');
        Membership::create([
            'user_id' => $colleague->id,
            'institution_id' => $this->institute->id,
            'role_id' => $doctorRole->id,
            'status' => 'active',
        ]);

        // A linked non-owner doctor is fenced to their own queue.
        $this->actingAs($this->doctor, 'web');
        Workspace::set($this->institute->id);

        $this->broadcast($this->doctor)->assertOk();
        $this->broadcast($colleague)->assertForbidden();
        $this->feed($colleague)->assertForbidden();
    }

    public function test_workspace_outside_the_institute_is_forbidden(): void
    {
        $rival = Institute::create([
            'name' => 'Foreign Live Hospital',
            'slug' => 'foreign-live-'.uniqid(),
            'industry' => 'healthcare',
            'sub_industry' => 'clinic',
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        // Each rejection clears the forged workspace, so re-forge per request.
        Workspace::set($rival->id);
        $this->broadcast()->assertForbidden();

        Workspace::set($rival->id);
        $this->feed()->assertForbidden();
    }

    public function test_queue_tab_offers_the_live_broadcast_link_for_the_selected_doctor(): void
    {
        $url = route('medical.appointments.index', [
            'tab' => 'queue',
            'q_doctor' => $this->doctor->id,
            'q_date' => $this->date,
        ]);

        $this->get($url)
            ->assertOk()
            ->assertSee('Live Broadcast')
            ->assertSee('appointments/live/'.$this->doctor->id, false);
    }
}
