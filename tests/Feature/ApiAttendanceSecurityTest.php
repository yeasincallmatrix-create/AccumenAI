<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Country;
use App\Models\Course;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiAttendanceSecurityTest extends TestCase
{
    use DatabaseTransactions;

    private function country(): Country
    {
        return Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]);
    }

    private function institute(Country $c, string $name = 'Test Inst'): Institute
    {
        return Institute::create(['name' => $name.' '.uniqid(), 'slug' => str()->slug($name.' '.uniqid()), 'country' => $c->name, 'country_id' => $c->id, 'status' => 'active']);
    }

    private function branch(Institute $i, string $name = 'Branch'): Branch
    {
        return Branch::create(['institute_id' => $i->id, 'name' => $name.' '.uniqid(), 'status' => 'active']);
    }

    private function user(Institute $i, string $role, ?int $branchId = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $i->id,
            'role_id' => Role::where('slug', $role)->firstOrFail()->id,
            'branch_id' => $branchId,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $role.'-'.uniqid().'@example.test',
            'phone' => '017'.rand(10000000, 99999999),
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
        ]);
    }

    private function course(Institute $i): Course
    {
        return Course::create(['course_code' => 'C'.rand(1000,9999), 'name' => 'Course '.uniqid(), 'status' => 'active']);
    }

    private function batch(Institute $i, ?int $branchId = null, ?int $courseId = null): Batch
    {
        $courseId = $courseId ?? $this->course($i)->id;
        return Batch::create([
            'institute_id' => $i->id,
            'branch_id' => $branchId,
            'course_id' => $courseId,
            'name' => 'Batch '.uniqid(),
            'batch_code' => 'B'.rand(10,99),
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'status' => 'ongoing',
        ]);
    }

    private function student(Institute $i, ?int $branchId = null): Student
    {
        return Student::create([
            'institute_id' => $i->id,
            'branch_id' => $branchId,
            'student_id_number' => 'S'.rand(100000,999999),
            'first_name' => 'Test',
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => '2025-01-01',
        ]);
    }

    public function test_same_tenant_student_works(): void
    {
        $c = $this->country();
        $inst = $this->institute($c, 'Inst A');
        $branch = $this->branch($inst, 'A');
        $user = $this->user($inst, 'institute-owner', null);
        $batch = $this->batch($inst, $branch->id);
        $student = $this->student($inst, $branch->id);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/attendance', [
                'student_id' => $student->id,
                'batch_id' => $batch->id,
                'date' => '2025-06-01',
                'status' => 'present',
            ])->assertStatus(201)->assertJsonPath('success', true);

        $this->assertDatabaseHas('attendance', ['student_id' => $student->id, 'batch_id' => $batch->id, 'institute_id' => $inst->id]);
    }

    public function test_cross_tenant_student_blocked(): void
    {
        $c = $this->country();
        $instA = $this->institute($c, 'Inst A');
        $instB = $this->institute($c, 'Inst B');
        $userA = $this->user($instA, 'institute-owner');
        $batchA = $this->batch($instA);
        $studentB = $this->student($instB);

        $this->actingAs($userA, 'sanctum')
            ->postJson('/api/attendance', [
                'student_id' => $studentB->id,
                'batch_id' => $batchA->id,
                'date' => '2025-06-01',
                'status' => 'present',
            ])->assertStatus(422)->assertJsonValidationErrors(['student_id']);

        $this->assertDatabaseMissing('attendance', ['student_id' => $studentB->id]);
    }

    public function test_branch_restricted_cannot_mark_other_branch_student(): void
    {
        $c = $this->country();
        $inst = $this->institute($c, 'Inst');
        $branchA = $this->branch($inst, 'A');
        $branchB = $this->branch($inst, 'B');
        $managerA = $this->user($inst, 'branch-manager', $branchA->id);
        $batchA = $this->batch($inst, $branchA->id);
        $studentB = $this->student($inst, $branchB->id);

        $this->actingAs($managerA, 'sanctum')
            ->postJson('/api/attendance', [
                'student_id' => $studentB->id,
                'batch_id' => $batchA->id,
                'date' => '2025-06-01',
                'status' => 'present',
            ])->assertStatus(422)->assertJsonValidationErrors(['student_id']);
    }

    public function test_existing_attendance_functionality_still_works(): void
    {
        $c = $this->country();
        $inst = $this->institute($c, 'Inst');
        $user = $this->user($inst, 'institute-owner');
        $batch = $this->batch($inst);
        $student = $this->student($inst);

        // First create
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/attendance', [
                'student_id' => $student->id,
                'batch_id' => $batch->id,
                'date' => '2025-06-01',
                'status' => 'present',
            ])->assertStatus(201);

        // Update same date with different status
        $this->actingAs($user, 'sanctum')
            ->postJson('/api/attendance', [
                'student_id' => $student->id,
                'batch_id' => $batch->id,
                'date' => '2025-06-01',
                'status' => 'absent',
            ])->assertStatus(200)->assertJsonPath('data.status', 'absent');

        $this->assertDatabaseHas('attendance', ['student_id' => $student->id, 'batch_id' => $batch->id, 'status' => 'absent']);
    }

    public function test_cross_tenant_batch_blocked(): void
    {
        $c = $this->country();
        $instA = $this->institute($c, 'Inst A2');
        $instB = $this->institute($c, 'Inst B2');
        $userA = $this->user($instA, 'institute-owner');
        $batchB = $this->batch($instB);
        $studentA = $this->student($instA);

        $this->actingAs($userA, 'sanctum')
            ->postJson('/api/attendance', [
                'student_id' => $studentA->id,
                'batch_id' => $batchB->id,
                'date' => '2025-06-01',
                'status' => 'present',
            ])->assertStatus(422)->assertJsonValidationErrors(['batch_id']);
    }
}
