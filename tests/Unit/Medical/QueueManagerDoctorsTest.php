<?php

namespace Tests\Unit\Medical;

use App\Models\Branch;
use App\Models\Institute;
use App\Models\Medical\Appointment;
use App\Models\Medical\Doctor;
use App\Models\Medical\Patient;
use App\Models\User;
use App\Services\Medical\QueueManager;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * QueueManager::getQueueForDoctors() — the multi-doctor reader behind the
 * OPD Queue Display board. Covers grouping, the branch fence, the
 * today-only constraint and the empty-doctor shape.
 */
class QueueManagerDoctorsTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    private User $doctorA;

    private User $doctorB;

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

        $this->doctorA = $this->makeDoctor();
        $this->doctorB = $this->makeDoctor();
        $this->date = today()->format('Y-m-d');
    }

    private function makeDoctor(): User
    {
        $user = User::factory()->create([
            'account_type' => 'staff',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        Doctor::create([
            'institute_id' => $this->institute->id,
            'user_id' => $user->id,
            'registration_number' => 'REG-'.strtoupper(uniqid()),
        ]);

        return $user;
    }

    private function makePatient(): Patient
    {
        return Patient::create([
            'institute_id' => $this->institute->id,
            'mr_number' => 'MR-'.strtoupper(uniqid()),
            'first_name' => 'Queue',
            'last_name' => 'Patient',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'phone' => '01'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
        ]);
    }

    private function makeAppointment(
        User $doctor,
        int $serial,
        string $status = 'checked_in',
        ?string $date = null,
        ?int $branchId = null,
    ): Appointment {
        return Appointment::create([
            'institute_id' => $this->institute->id,
            'branch_id' => $branchId,
            'patient_id' => $this->makePatient()->id,
            'doctor_id' => $doctor->id,
            'appointment_date' => $date ?? $this->date,
            'appointment_time' => '09:'.str_pad((string) ($serial % 60), 2, '0', STR_PAD_LEFT),
            'serial_number' => $serial,
            'status' => $status,
        ]);
    }

    private function read(array $doctorIds, ?int $branchId = null, ?string $date = null): array
    {
        return (new QueueManager)->getQueueForDoctors(
            $this->institute->id,
            $branchId,
            $doctorIds,
            $date ?? $this->date,
        );
    }

    public function test_returns_empty_arrays_for_doctor_without_appointments(): void
    {
        $result = $this->read([$this->doctorA->id]);

        $this->assertArrayHasKey($this->doctorA->id, $result);

        $row = $result[$this->doctorA->id];
        $this->assertSame($this->doctorA->id, $row['doctor_id']);
        $this->assertSame($this->doctorA->name, $row['doctor_name']);
        $this->assertNull($row['now_serving']);
        $this->assertSame([], $row['up_next']);
        $this->assertSame(0, $row['waiting_count']);
        $this->assertSame(0, $row['total_today']);
    }

    public function test_groups_by_doctor_id(): void
    {
        $this->makeAppointment($this->doctorA, 1, 'checked_in');
        $this->makeAppointment($this->doctorA, 2, 'in_progress');
        $this->makeAppointment($this->doctorA, 3, 'completed');
        $this->makeAppointment($this->doctorB, 1, 'scheduled');

        $result = $this->read([$this->doctorA->id, $this->doctorB->id]);

        $this->assertSame([$this->doctorA->id, $this->doctorB->id], array_keys($result));

        $a = $result[$this->doctorA->id];
        $this->assertSame(2, $a['now_serving']['serial_number']);
        $this->assertCount(1, $a['up_next']);
        $this->assertSame(1, $a['up_next'][0]['serial_number']);
        $this->assertSame(1, $a['waiting_count']);
        $this->assertSame(3, $a['total_today']);

        $b = $result[$this->doctorB->id];
        $this->assertNull($b['now_serving']);
        $this->assertCount(1, $b['up_next']);
        $this->assertSame(1, $b['up_next'][0]['serial_number']);
        $this->assertSame(1, $b['total_today']);
    }

    public function test_respects_branch_filter(): void
    {
        $branchA = Branch::create([
            'institute_id' => $this->institute->id,
            'name' => 'Branch A',
            'status' => 'active',
        ]);
        $branchB = Branch::create([
            'institute_id' => $this->institute->id,
            'name' => 'Branch B',
            'status' => 'active',
        ]);

        $inA = $this->makeAppointment($this->doctorA, 1, 'checked_in', $this->date, $branchA->id);
        $this->makeAppointment($this->doctorA, 2, 'checked_in', $this->date, $branchB->id);
        $noBranch = $this->makeAppointment($this->doctorA, 3, 'checked_in', $this->date);

        $filtered = $this->read([$this->doctorA->id], $branchA->id);
        $serials = array_column($filtered[$this->doctorA->id]['up_next'], 'serial_number');

        $this->assertSame(2, $filtered[$this->doctorA->id]['waiting_count']);
        $this->assertSame([$inA->serial_number, $noBranch->serial_number], $serials);
        $this->assertNotContains(2, $serials);

        // Institute-wide read still sees every branch row.
        $all = $this->read([$this->doctorA->id], null);
        $this->assertSame(3, $all[$this->doctorA->id]['waiting_count']);
    }

    public function test_only_includes_today(): void
    {
        $yesterday = now()->subDay()->format('Y-m-d');
        $this->makeAppointment($this->doctorA, 1, 'checked_in', $yesterday);
        $today = $this->makeAppointment($this->doctorA, 2, 'checked_in', $this->date);

        $result = $this->read([$this->doctorA->id]);

        $row = $result[$this->doctorA->id];
        $this->assertSame(1, $row['total_today']);
        $this->assertSame(1, $row['waiting_count']);
        $this->assertSame([$today->serial_number], array_column($row['up_next'], 'serial_number'));
    }

    public function test_no_doctor_ids_returns_empty_array(): void
    {
        $this->assertSame([], $this->read([]));
    }

    public function test_empty_date_defaults_to_today(): void
    {
        $this->makeAppointment($this->doctorA, 1, 'checked_in');

        $result = (new QueueManager)->getQueueForDoctors(
            $this->institute->id,
            null,
            [$this->doctorA->id],
            '',
        );

        $this->assertSame(1, $result[$this->doctorA->id]['total_today']);
    }
}
