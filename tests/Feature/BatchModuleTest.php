<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BatchModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    private Institute $institute;

    private InstituteUser $staff;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();

        $this->institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $this->staff = $this->makeStaff('institute-owner', 'batches-owner@example.test');
    }

    protected function makeStaff(string $roleSlug, string $email): InstituteUser
    {
        $role = Role::where('slug', $roleSlug)->firstOrFail();

        return InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'email' => $email,
            'phone' => '0170000'.substr(md5($email), 0, 4),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    public function test_guest_is_redirected_from_batches_index(): void
    {
        $this->get('/batches')->assertRedirect('/admin/login');
    }

    public function test_index_shows_only_own_institutes_batches(): void
    {
        $this->actingAs($this->staff, 'institute_user')->get('/batches')->assertOk();

        $scoped = Batch::count();
        $all = Batch::withoutGlobalScopes()->count();
        $this->assertLessThan($all, $scoped);
        $this->assertSame(
            (int) DB::table('batches')->where('institute_id', $this->institute->id)->count(),
            $scoped
        );
    }

    public function test_index_search_filters_by_name(): void
    {
        $target = DB::table('batches')->where('institute_id', $this->institute->id)->first();

        $this->actingAs($this->staff, 'institute_user')
            ->get('/batches?q='.urlencode($target->name))
            ->assertOk()
            ->assertSee($target->name);
    }

    public function test_index_status_filter(): void
    {
        $this->actingAs($this->staff, 'institute_user')
            ->get('/batches?status=running')
            ->assertOk();
    }

    public function test_show_displays_batch_details(): void
    {
        $batch = $this->createBatch('Show Me');

        $this->actingAs($this->staff, 'institute_user')
            ->get('/batches/'.$batch->id)
            ->assertOk()
            ->assertSee('Show Me')
            ->assertSee($batch->batch_code)
            ->assertSee('colspan="9"', false);
    }

    public function test_show_batch_from_other_institute_is_404(): void
    {
        $other = Institute::where('name', 'Halumoni Computer training center')->firstOrFail();
        $foreign = DB::table('batches')->where('institute_id', $other->id)->first();
        $this->assertNotNull($foreign);

        $this->actingAs($this->staff, 'institute_user')
            ->get('/batches/'.$foreign->id)
            ->assertNotFound();
    }

    public function test_guest_is_redirected_from_batches_show(): void
    {
        $batch = $this->createBatch('Guest Check');

        $this->get('/batches/'.$batch->id)->assertRedirect('/admin/login');
    }

    public function test_create_store_generates_incremental_code(): void
    {
        $before = (int) Batch::withoutGlobalScope('institute')
            ->where('institute_id', $this->institute->id)
            ->count();

        $this->actingAs($this->staff, 'institute_user')
            ->post('/batches', [
                'name' => 'New Batch',
                'course_id' => Course::query()->firstOrFail()->id,
                'shift' => 'evening',
                'start_date' => '2026-08-20',
                'end_date' => '2026-12-20',
                'seat_capacity' => '40',
                'status' => 'upcoming',
            ])
            ->assertRedirect(route('batches.index'));

        $created = Batch::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->where('name', 'New Batch')
            ->first();

        $this->assertNotNull($created);
        $this->assertSame(
            'B'.str_pad((string) ($before + 1), 3, '0', STR_PAD_LEFT),
            $created->batch_code
        );
        $this->assertSame($this->institute->id, (int) $created->institute_id);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->staff, 'institute_user')
            ->post('/batches', [
                'name' => '',
                'course_id' => '',
                'shift' => '',
                'start_date' => '',
                'seat_capacity' => '',
                'status' => '',
            ])
            ->assertSessionHasErrors(['name', 'course_id', 'shift', 'start_date', 'seat_capacity', 'status']);
    }

    public function test_update_changes_details_but_not_institute(): void
    {
        $batch = $this->createBatch('Update');

        $this->actingAs($this->staff, 'institute_user')
            ->put('/batches/'.$batch->id, [
                'name' => 'Updated Batch',
                'course_id' => Course::query()->firstOrFail()->id,
                'shift' => 'day',
                'start_date' => '2026-08-20',
                'seat_capacity' => '25',
                'status' => 'ongoing',
            ])
            ->assertRedirect(route('batches.index'));

        $batch->refresh();
        $this->assertSame('Updated Batch', $batch->name);
        $this->assertSame('25', (string) $batch->seat_capacity);
        $this->assertSame('ongoing', $batch->status);
        $this->assertSame($this->institute->id, (int) $batch->institute_id);
    }

    public function test_other_institute_batch_is_404(): void
    {
        $other = Institute::where('name', 'Halumoni Computer training center')->firstOrFail();
        $foreign = DB::table('batches')->where('institute_id', $other->id)->first();

        $this->assertNotNull($foreign);

        $this->actingAs($this->staff, 'institute_user')
            ->get('/batches')
            ->assertOk();

        $this->actingAs($this->staff, 'institute_user')
            ->put('/batches/'.$foreign->id, [
                'name' => 'Hijack',
                'course_id' => Course::query()->firstOrFail()->id,
                'shift' => 'day',
                'start_date' => '2026-08-20',
                'seat_capacity' => '10',
                'status' => 'upcoming',
            ])
            ->assertNotFound();
    }

    public function test_destroy_soft_deletes_batch_into_recycle_bin(): void
    {
        $batch = $this->createBatch('Cancel Me');

        $this->actingAs($this->staff, 'institute_user')
            ->delete('/batches/'.$batch->id)
            ->assertRedirect(route('batches.index'));

        $this->assertSoftDeleted('batches', ['id' => $batch->id]);
    }

    public function test_teacher_has_view_but_not_manage(): void
    {
        $teacher = $this->makeStaff('teacher', 'batches-teacher@example.test');

        $this->actingAs($teacher, 'institute_user')->get('/batches')->assertOk();
        $this->actingAs($teacher, 'institute_user')
            ->post('/batches', [
                'name' => 'Nope',
                'course_id' => Course::query()->firstOrFail()->id,
                'shift' => 'day',
                'start_date' => '2026-08-20',
                'seat_capacity' => '10',
                'status' => 'upcoming',
            ])
            ->assertForbidden();
    }

    public function test_transfer_moves_student_to_another_batch(): void
    {
        $source = $this->createBatch('Transfer Source');
        $target = $this->createBatch('Transfer Target');

        $student = Student::query()->where('institute_id', $this->institute->id)->first();
        $this->assertNotNull($student);

        StudentEnrollment::create([
            'institute_id' => $this->institute->id,
            'student_id' => $student->id,
            'course_id' => $source->course_id,
            'batch_id' => $source->id,
            'roll_number' => 'R-01',
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $this->actingAs($this->staff, 'institute_user')
            ->post('/batches/'.$source->id.'/transfer', [
                'student_id' => $student->id,
                'target_batch_id' => $target->id,
            ])
            ->assertRedirect(route('batches.show', $source));

        $this->assertSame(
            'transferred',
            DB::table('student_enrollments')
                ->where('batch_id', $source->id)
                ->where('student_id', $student->id)
                ->value('status')
        );

        $this->assertSame(
            'active',
            DB::table('student_enrollments')
                ->where('batch_id', $target->id)
                ->where('student_id', $student->id)
                ->value('status')
        );
    }

    public function test_transfer_to_other_institute_batch_is_404(): void
    {
        $source = $this->createBatch('Transfer Guard');
        $other = Institute::where('name', 'Halumoni Computer training center')->firstOrFail();
        $foreign = DB::table('batches')->where('institute_id', $other->id)->first();
        $this->assertNotNull($foreign);

        $student = Student::query()->where('institute_id', $this->institute->id)->first();
        $this->assertNotNull($student);

        $this->actingAs($this->staff, 'institute_user')
            ->post('/batches/'.$source->id.'/transfer', [
                'student_id' => $student->id,
                'target_batch_id' => $foreign->id,
            ])
            ->assertSessionHasErrors('target_batch_id');
    }

    public function test_remove_student_marks_enrollment_dropped(): void
    {
        $batch = $this->createBatch('Remove Batch');

        $student = Student::query()->where('institute_id', $this->institute->id)->first();
        $this->assertNotNull($student);

        StudentEnrollment::create([
            'institute_id' => $this->institute->id,
            'student_id' => $student->id,
            'course_id' => $batch->course_id,
            'batch_id' => $batch->id,
            'roll_number' => 'R-01',
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $batch->increment('seat_filled');
        $filledBefore = (int) $batch->fresh()->seat_filled;

        $this->actingAs($this->staff, 'institute_user')
            ->delete('/batches/'.$batch->id.'/students/'.$student->id)
            ->assertRedirect(route('batches.show', $batch));

        $this->assertSame(
            'dropped',
            DB::table('student_enrollments')
                ->where('batch_id', $batch->id)
                ->where('student_id', $student->id)
                ->value('status')
        );

        $this->assertSame($filledBefore - 1, (int) $batch->fresh()->seat_filled);
    }

    private function createBatch(string $name): Batch
    {
        $count = (int) Batch::withoutGlobalScope('institute')
            ->where('institute_id', $this->institute->id)
            ->count();

        return Batch::create([
            'institute_id' => $this->institute->id,
            'course_id' => Course::query()->firstOrFail()->id,
            'name' => $name,
            'batch_code' => 'B'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT),
            'shift' => 'day',
            'start_date' => '2026-08-20',
            'seat_capacity' => 30,
            'status' => 'upcoming',
        ]);
    }
}
