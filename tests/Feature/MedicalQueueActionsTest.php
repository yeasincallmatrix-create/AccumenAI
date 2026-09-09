<?php

namespace Tests\Feature;

use App\Livewire\Medical\QueueManager;
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
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Live Queue widget actions — regression cover for the "action works but
 * UI hangs" report. Each action must update the DB, refresh the item list,
 * set a status message and broadcast fresh header counts (queue-updated).
 */
class MedicalQueueActionsTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    private User $owner;

    private User $doctor;

    private string $date;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->institute = Institute::create([
            'name' => 'Queue Test Hospital',
            'slug' => 'queue-test-hospital-'.uniqid(),
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

        Doctor::create([
            'institute_id' => $this->institute->id,
            'user_id' => $this->doctor->id,
            'registration_number' => 'REG-'.strtoupper(uniqid()),
        ]);

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);

        $this->date = today()->format('Y-m-d');
    }

    private function checkedInAppointment(): Appointment
    {
        $patient = Patient::create([
            'institute_id' => $this->institute->id,
            'mr_number' => 'MR-'.uniqid(),
            'first_name' => 'Queue',
            'last_name' => 'Patient',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
        ]);

        return Appointment::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => $this->date,
            'appointment_time' => '09:00',
            'serial_number' => 1,
            'status' => 'checked_in',
        ]);
    }

    private function queue(): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(QueueManager::class, [
            'doctorUserId' => $this->doctor->id,
            'date' => $this->date,
            'instituteId' => $this->institute->id,
        ]);
    }

    public function test_cancel_removes_item_and_broadcasts_counts(): void
    {
        $appointment = $this->checkedInAppointment();

        $this->queue()
            ->assertCount('items', 1)
            ->call('cancel', $appointment->id)
            ->assertSet('statusMessage', 'Appointment cancelled.')
            ->assertCount('items', 0)
            ->assertDispatched('queue-updated');

        $this->assertSame('cancelled', $appointment->fresh()->status);
    }

    public function test_start_and_complete_flow_updates_queue(): void
    {
        $appointment = $this->checkedInAppointment();

        $component = $this->queue()
            ->call('startProgress', $appointment->id)
            ->assertSet('statusMessage', 'Consultation started!')
            ->assertDispatched('queue-updated');

        $this->assertSame('in_progress', $appointment->fresh()->status);
        $this->assertSame('in_progress', $component->get('items')[0]['status']);

        $component
            ->call('complete', $appointment->id)
            ->assertSet('statusMessage', 'Appointment completed!')
            ->assertCount('items', 0)
            ->assertDispatched('queue-updated');

        $this->assertSame('completed', $appointment->fresh()->status);
    }

    public function test_load_queue_broadcasts_status_payload(): void
    {
        $this->checkedInAppointment();

        $this->queue()
            ->call('loadQueue')
            ->assertDispatched('queue-updated', status: [
                'total' => 1,
                'checked_in' => 1,
                'in_progress' => 0,
                'estimated_wait_minutes' => 10,
            ]);
    }

    public function test_owner_can_delete_completed_appointment(): void
    {
        $appointment = $this->checkedInAppointment();
        $appointment->update(['status' => 'completed']);

        $this->delete(route('medical.appointments.destroy', $appointment))
            ->assertRedirect(route('medical.appointments.index'))
            ->assertSessionHas('status');

        $this->assertSame('cancelled', $appointment->fresh()->status);

        $this->assertDatabaseHas('queue_audit_logs', [
            'appointment_id' => $appointment->id,
            'action' => 'deleted',
        ]);
    }

    public function test_own_doctor_can_delete_own_completed_appointment(): void
    {
        $this->grantDeleteToRole('receptionist', $this->doctor->id);

        $this->actingAs($this->doctor, 'web');
        Workspace::set($this->institute->id);

        $appointment = $this->checkedInAppointment();
        $appointment->update(['status' => 'completed']);

        $this->delete(route('medical.appointments.destroy', $appointment))
            ->assertRedirect(route('medical.appointments.index'))
            ->assertSessionHas('status');

        $this->assertSame('cancelled', $appointment->fresh()->status);

        $this->assertDatabaseHas('queue_audit_logs', [
            'appointment_id' => $appointment->id,
            'action' => 'deleted',
            'user_type' => 'doctor',
        ]);
    }

    public function test_own_doctor_can_delete_own_paid_appointment_with_reversal(): void
    {
        $this->grantDeleteToRole('receptionist', $this->doctor->id);

        $this->actingAs($this->doctor, 'web');
        Workspace::set($this->institute->id);

        $paid = $this->checkedInAppointment();
        $paid->update([
            'fee_collected_amount' => 300,
            'fee_collected_by_name' => 'Counter Staff',
            'fee_collected_at' => now(),
        ]);

        $this->delete(route('medical.appointments.destroy', $paid))
            ->assertSessionHas('status', 'Appointment deleted and payment of ৳300.00 reversed.');

        $fresh = $paid->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertNull($fresh->fee_collected_at);

        $this->assertDatabaseHas('queue_audit_logs', [
            'appointment_id' => $paid->id,
            'action' => 'fee_reversed',
            'amount' => 300,
        ]);
    }

    public function test_doctor_cannot_delete_other_doctors_completed_appointment(): void
    {
        $this->grantDeleteToRole('receptionist', $this->doctor->id);

        $other = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);
        Doctor::create([
            'institute_id' => $this->institute->id,
            'user_id' => $other->id,
            'registration_number' => 'REG-'.strtoupper(uniqid()),
        ]);

        $patient = Patient::create([
            'institute_id' => $this->institute->id,
            'mr_number' => 'MR-'.uniqid(),
            'first_name' => 'Other',
            'last_name' => 'Doctor',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
        ]);
        $foreign = Appointment::create([
            'institute_id' => $this->institute->id,
            'patient_id' => $patient->id,
            'doctor_id' => $other->id,
            'appointment_date' => $this->date,
            'appointment_time' => '10:00',
            'serial_number' => 1,
            'status' => 'completed',
        ]);

        $this->actingAs($this->doctor, 'web');
        Workspace::set($this->institute->id);

        // Fenced doctors cannot even reach another doctor's record.
        $this->delete(route('medical.appointments.destroy', $foreign))->assertForbidden();
        $this->assertSame('completed', $foreign->fresh()->status);
    }

    private function grantDeletePermission(string $roleSlug): void
    {
        $this->grantDeleteToRole($roleSlug, $this->doctor->id);
    }

    private function grantDeleteToRole(string $roleSlug, int $userId): void
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();
        $permission = \App\Models\Permission::firstOrCreate(
            ['slug' => 'medical_appointments.delete'],
            ['module' => 'medical_appointments', 'name' => 'Cancel Appointments']
        );
        \Illuminate\Support\Facades\DB::table('role_permissions')->insertOrIgnore([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);

        Membership::create([
            'user_id' => $userId,
            'institution_id' => $this->institute->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    public function test_owner_delete_of_paid_appointment_reverses_payment(): void
    {
        $paid = $this->checkedInAppointment();
        $paid->update([
            'status' => 'completed',
            'fee_collected_amount' => 500,
            'fee_collected_by_name' => 'Counter Staff',
            'fee_collected_at' => now(),
        ]);

        $this->delete(route('medical.appointments.destroy', $paid))
            ->assertRedirect(route('medical.appointments.index'))
            ->assertSessionHas('status', 'Appointment deleted and payment of ৳500.00 reversed.');

        $fresh = $paid->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertNull($fresh->fee_collected_amount);
        $this->assertNull($fresh->fee_collected_by_name);
        $this->assertNull($fresh->fee_collected_at);

        $this->assertDatabaseHas('queue_audit_logs', [
            'appointment_id' => $paid->id,
            'action' => 'fee_reversed',
            'amount' => 500,
        ]);
    }

    public function test_owner_queue_cancel_of_paid_appointment_reverses_payment(): void
    {
        $paid = $this->checkedInAppointment();
        $paid->update([
            'fee_collected_amount' => 500,
            'fee_collected_by_name' => 'Counter Staff',
            'fee_collected_at' => now(),
        ]);

        $this->queue()
            ->call('cancel', $paid->id)
            ->assertSet('statusMessage', 'Appointment cancelled and payment of ৳500.00 reversed.')
            ->assertCount('items', 0);

        $fresh = $paid->fresh();
        $this->assertSame('cancelled', $fresh->status);
        $this->assertNull($fresh->fee_collected_at);

        $this->assertDatabaseHas('queue_audit_logs', [
            'appointment_id' => $paid->id,
            'action' => 'fee_reversed',
        ]);
    }

    public function test_staff_cannot_delete_completed_or_paid_appointment(): void
    {
        $staff = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $role = Role::where('slug', 'receptionist')->firstOrFail();
        Membership::create([
            'user_id' => $staff->id,
            'institution_id' => $this->institute->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        // Grant the delete permission so the request reaches the
        // finalized-record guard (rather than failing on middleware).
        $permission = \App\Models\Permission::firstOrCreate(
            ['slug' => 'medical_appointments.delete'],
            ['module' => 'medical_appointments', 'name' => 'Cancel Appointments']
        );
        \Illuminate\Support\Facades\DB::table('role_permissions')->insertOrIgnore([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);

        $this->actingAs($staff, 'web');
        Workspace::set($this->institute->id);

        $completed = $this->checkedInAppointment();
        $completed->update(['status' => 'completed']);

        $this->delete(route('medical.appointments.destroy', $completed))
            ->assertSessionHasErrors('appointment');
        $this->assertSame('completed', $completed->fresh()->status);

        $paid = $this->checkedInAppointment();
        $paid->update([
            'fee_collected_amount' => 500,
            'fee_collected_by_name' => 'Counter Staff',
            'fee_collected_at' => now(),
        ]);

        $this->delete(route('medical.appointments.destroy', $paid))
            ->assertSessionHasErrors('appointment');
        $this->assertSame('checked_in', $paid->fresh()->status);
    }

    public function test_queue_cancel_blocks_paid_appointment_for_staff(): void
    {
        $staff = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $role = Role::where('slug', 'receptionist')->firstOrFail();
        Membership::create([
            'user_id' => $staff->id,
            'institution_id' => $this->institute->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        // Queue manage permission (but NOT admin) so the request reaches
        // the paid-record guard inside the component.
        $permission = \App\Models\Permission::firstOrCreate(
            ['slug' => 'medical_appointments.edit'],
            ['module' => 'medical_appointments', 'name' => 'Edit Appointments']
        );
        \Illuminate\Support\Facades\DB::table('role_permissions')->insertOrIgnore([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);

        $this->actingAs($staff, 'web');
        Workspace::set($this->institute->id);

        $paid = $this->checkedInAppointment();
        $paid->update([
            'fee_collected_amount' => 500,
            'fee_collected_by_name' => 'Counter Staff',
            'fee_collected_at' => now(),
        ]);

        $this->queue()
            ->assertCount('items', 1)
            ->call('cancel', $paid->id)
            ->assertSet('errorMessage', 'Only the treating doctor or an administrator may cancel a paid appointment.')
            ->assertCount('items', 1);

        $this->assertSame('checked_in', $paid->fresh()->status);
    }

    public function test_load_queue_uses_constant_query_count(): void
    {
        // Pre-visit fee collection so every card takes the fee path (the
        // old N+1 hotspot: 2 extra queries per card).
        \App\Models\Medical\Doctor::where('institute_id', $this->institute->id)
            ->where('user_id', $this->doctor->id)
            ->update(['collect_fee_before_visit' => true]);

        for ($i = 0; $i < 5; $i++) {
            $patient = Patient::create([
                'institute_id' => $this->institute->id,
                'mr_number' => 'MR-Q-'.uniqid().$i,
                'first_name' => 'Queue'.$i,
                'last_name' => 'Patient',
                'date_of_birth' => '1990-01-01',
                'gender' => 'male',
                'phone' => '01'.str_pad((string) random_int(0, 999999999), 9, '0', STR_PAD_LEFT),
            ]);
            Appointment::create([
                'institute_id' => $this->institute->id,
                'patient_id' => $patient->id,
                'doctor_id' => $this->doctor->id,
                'appointment_date' => $this->date,
                'appointment_time' => '09:0'.$i,
                'serial_number' => $i + 1,
                'status' => 'checked_in',
            ]);
        }

        $component = $this->queue();
        $this->assertCount(5, $component->get('items'));
        $this->assertTrue(collect($component->get('items'))->every(fn ($it) => $it['fee_required'] === true));

        \Illuminate\Support\Facades\DB::enableQueryLog();
        \Illuminate\Support\Facades\DB::flushQueryLog();
        $component->call('loadQueue');
        $queries = \Illuminate\Support\Facades\DB::getQueryLog();
        $queueQueries = array_values(array_filter(
            $queries,
            fn ($q) => str_contains($q['query'], '`appointments`')
                || str_contains($q['query'], '`patients`')
                || str_contains($q['query'], '`medical_doctors`')
        ));

        // Exactly 4 data queries regardless of card count: queue select +
        // patient eager load + doctor profile + batched last-visit lookup.
        // (Layout/middleware noise such as notifications is excluded.)
        $this->assertCount(4, $queueQueries, 'loadQueue data queries: '.count($queueQueries));
    }
}
