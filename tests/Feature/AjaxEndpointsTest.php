<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseRequest;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Models\Student;
use App\Models\Subject;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AjaxEndpointsTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    private Institute $institute;

    private InstituteUser $staff;

    private PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();

        $this->institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $this->staff = $this->makeStaff('institute-owner', 'ajax-owner@example.test');

        $this->admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'ajax-admin@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
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

    protected function makeInstitute(string $status = 'pending'): Institute
    {
        return Institute::query()->create([
            'name' => 'Ajax Test Institute',
            'slug' => 'ajax-test-'.uniqid(),
            'status' => $status,
        ]);
    }

    protected function makeCertificate(string $status = 'pending'): Certificate
    {
        $institute = $this->makeInstitute('active');
        $course = Course::query()->create(['name' => 'Ajax Cert Course', 'course_code' => 'AC-'.uniqid(), 'status' => 'active']);
        $student = Student::query()->create([
            'institute_id' => $institute->id,
            'first_name' => 'Ajax',
            'last_name' => 'Student',
            'student_id_number' => 'AJ'.uniqid(),
            'admission_date' => now(),
            'status' => 'active',
        ]);
        $batch = Batch::query()->create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
            'name' => 'Ajax Batch',
            'batch_code' => 'AJB-'.uniqid(),
            'start_date' => now()->toDateString(),
        ]);

        return Certificate::query()->create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => $status,
        ]);
    }

    public function test_batch_store_returns_json_success_when_ajax(): void
    {
        $this->actingAs($this->staff, 'institute_user');

        $response = $this->postJson('/batches', [
            'name' => 'Ajax Batch',
            'course_id' => Course::query()->firstOrFail()->id,
            'shift' => 'evening',
            'start_date' => '2026-08-20',
            'end_date' => '2026-12-20',
            'seat_capacity' => '40',
            'status' => 'upcoming',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['message', 'data' => ['id']]);

        $this->assertDatabaseHas('batches', [
            'institute_id' => $this->institute->id,
            'name' => 'Ajax Batch',
        ]);
    }

    public function test_batch_store_returns_422_with_errors_when_ajax(): void
    {
        $this->actingAs($this->staff, 'institute_user');

        $this->postJson('/batches', [
            'name' => '',
            'course_id' => '',
            'shift' => '',
            'start_date' => '',
            'seat_capacity' => '',
            'status' => '',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['message', 'errors' => ['name']]);
    }

    public function test_batch_update_returns_json_success_when_ajax(): void
    {
        $this->actingAs($this->staff, 'institute_user');

        $batch = Batch::query()
            ->where('institute_id', $this->institute->id)
            ->firstOrFail();

        $this->putJson('/batches/'.$batch->id, [
            'name' => 'Ajax Updated Batch',
            'course_id' => Course::query()->firstOrFail()->id,
            'shift' => 'day',
            'start_date' => '2026-08-20',
            'seat_capacity' => '25',
            'status' => 'ongoing',
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('Ajax Updated Batch', $batch->refresh()->name);
    }

    public function test_batch_destroy_returns_json_success_when_ajax(): void
    {
        $this->actingAs($this->staff, 'institute_user');

        $batch = Batch::query()
            ->where('institute_id', $this->institute->id)
            ->where('status', '!=', 'cancelled')
            ->firstOrFail();

        $this->deleteJson('/batches/'.$batch->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('batches', ['id' => $batch->id]);
    }

    public function test_student_destroy_returns_json_success_when_ajax(): void
    {
        $this->actingAs($this->staff, 'institute_user');

        $student = Student::query()->create([
            'institute_id' => $this->institute->id,
            'student_id_number' => Student::nextStudentNumber($this->institute->id),
            'first_name' => 'Ajax',
            'last_name' => 'Remove',
            'admission_date' => now()->format('Y-m-d'),
            'status' => 'active',
        ]);

        $this->deleteJson('/students/'.$student->id)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull(Student::find($student->id));
    }

    public function test_foreign_institute_student_destroy_is_404_even_for_json(): void
    {
        $this->actingAs($this->staff, 'institute_user');

        $other = Institute::where('name', 'Halumoni Computer training center')->firstOrFail();
        $foreign = Student::query()->withoutGlobalScopes()->where('institute_id', $other->id)->first();

        $this->assertNotNull($foreign);
        $this->deleteJson('/students/'.$foreign->id)->assertNotFound();
    }

    public function test_institute_action_approve_returns_json_when_ajax(): void
    {
        $institute = $this->makeInstitute('pending');
        $this->actingAs($this->admin, 'platform_admin');

        $this->postJson(route('admin.institutes.action', $institute), ['action' => 'approve'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['message', 'data' => ['id', 'status']]);

        $this->assertSame('active', $institute->refresh()->status);
    }

    public function test_institute_delete_wrong_password_returns_json_422(): void
    {
        $institute = $this->makeInstitute('active');
        $this->actingAs($this->admin, 'platform_admin');

        $this->postJson(route('admin.institutes.action', $institute), [
            'action' => 'delete',
            'password' => 'wrong-password',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['password']]);

        $this->assertNull($institute->refresh()->deleted_at);
    }

    public function test_institute_delete_correct_password_returns_json_success(): void
    {
        $institute = $this->makeInstitute('active');
        $this->actingAs($this->admin, 'platform_admin');

        $this->postJson(route('admin.institutes.action', $institute), [
            'action' => 'delete',
            'password' => $this->password,
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNotNull($institute->refresh()->deleted_at);
    }

    public function test_certificate_action_approve_returns_json_when_ajax(): void
    {
        $certificate = $this->makeCertificate('pending');
        $this->actingAs($this->admin, 'platform_admin');

        $this->postJson(route('admin.certificates.action', $certificate), ['action' => 'approve'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Certificate approved and issued.');

        $this->assertSame('active', $certificate->refresh()->status);
        $this->assertNotNull($certificate->refresh()->certificate_number);
    }

    public function test_certificate_action_validate_action_when_ajax(): void
    {
        $certificate = $this->makeCertificate('pending');
        $this->actingAs($this->admin, 'platform_admin');

        $this->postJson(route('admin.certificates.action', $certificate), ['action' => 'bogus'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['action']]);
    }

    public function test_certificate_destroy_returns_json_when_ajax(): void
    {
        $certificate = $this->makeCertificate('active');
        $this->actingAs($this->admin, 'platform_admin');

        $this->deleteJson(route('admin.certificates.destroy', $certificate))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSoftDeleted('certificates', ['id' => $certificate->id]);
    }

    public function test_index_pages_still_render_for_normal_navigation(): void
    {
        $this->actingAs($this->staff, 'institute_user')
            ->get('/batches')->assertOk();
        $this->actingAs($this->staff, 'institute_user')
            ->get('/students')->assertOk();

        $student = Student::query()->where('institute_id', $this->institute->id)->firstOrFail();
        $this->actingAs($this->staff, 'institute_user')
            ->get('/students/'.$student->id)->assertOk();

        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('admin.institutes.index'))->assertOk();
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('admin.institutes.bin'))->assertOk();
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('admin.certificates.index'))->assertOk();
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('admin.certificates.requests'))->assertOk();
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('admin.students.index'))->assertOk();
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('admin.courses.index'))->assertOk();
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('admin.courses.requests'))->assertOk();
        $this->actingAs($this->admin, 'platform_admin')
            ->get(route('admin.courses.assignment'))->assertOk();
    }

    public function test_batch_index_search_is_scoped_and_filterable(): void
    {
        $this->actingAs($this->staff, 'institute_user');

        $this->get('/batches?status=running')->assertOk();
        $this->get('/batches?q='.urlencode('Ajax'))->assertOk();
        $this->get('/batches?page=2')->assertOk();
    }

    public function test_student_update_returns_json_success_when_ajax(): void
    {
        $this->actingAs($this->staff, 'institute_user');

        $student = Student::query()->create([
            'institute_id' => $this->institute->id,
            'student_id_number' => Student::nextStudentNumber($this->institute->id),
            'first_name' => 'Ajax',
            'last_name' => 'Update',
            'admission_date' => now()->format('Y-m-d'),
            'status' => 'active',
        ]);

        $this->putJson('/students/'.$student->id, [
            'first_name' => 'Ajax Updated',
            'last_name' => 'Name',
            'admission_date' => now()->format('Y-m-d'),
            'status' => 'active',
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['message', 'data' => ['id']]);

        $this->assertSame('Ajax Updated', $student->refresh()->first_name);
    }

    public function test_student_update_returns_422_with_errors_when_ajax(): void
    {
        $this->actingAs($this->staff, 'institute_user');

        $student = Student::query()->create([
            'institute_id' => $this->institute->id,
            'student_id_number' => Student::nextStudentNumber($this->institute->id),
            'first_name' => 'Ajax',
            'last_name' => 'Update',
            'admission_date' => now()->format('Y-m-d'),
            'status' => 'active',
        ]);

        $this->putJson('/students/'.$student->id, [
            'first_name' => '',
            'admission_date' => '',
            'status' => 'bogus',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['first_name', 'status']]);
    }

    public function test_student_photo_upload_returns_json_success_when_ajax(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('The GD extension is required for image upload tests.');
        }

        $this->actingAs($this->staff, 'institute_user');

        $student = Student::query()->create([
            'institute_id' => $this->institute->id,
            'student_id_number' => Student::nextStudentNumber($this->institute->id),
            'first_name' => 'Ajax',
            'last_name' => 'Photo',
            'admission_date' => now()->format('Y-m-d'),
            'status' => 'active',
        ]);

        $this->postJson('/students/'.$student->id.'/photo', [
            'photo' => UploadedFile::fake()->image('photo.jpg', 80, 100),
        ])->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['message', 'data' => ['photo']]);

        $this->assertNotNull($student->refresh()->photo);
        $this->assertStringContainsString('profile-images/students/', $student->photo);
        $this->assertStringEndsWith('.jpg', $student->photo);
    }

    public function test_student_photo_upload_returns_422_without_file_when_ajax(): void
    {
        $this->actingAs($this->staff, 'institute_user');

        $student = Student::query()->create([
            'institute_id' => $this->institute->id,
            'student_id_number' => Student::nextStudentNumber($this->institute->id),
            'first_name' => 'Ajax',
            'last_name' => 'Photo',
            'admission_date' => now()->format('Y-m-d'),
            'status' => 'active',
        ]);

        $this->postJson('/students/'.$student->id.'/photo', [])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['photo']]);
    }

    public function test_student_enroll_returns_json_success_when_ajax(): void
    {
        $this->actingAs($this->staff, 'institute_user');

        $student = Student::query()->create([
            'institute_id' => $this->institute->id,
            'student_id_number' => Student::nextStudentNumber($this->institute->id),
            'first_name' => 'Ajax',
            'last_name' => 'Enroll',
            'admission_date' => now()->format('Y-m-d'),
            'status' => 'active',
        ]);

        $batch = Batch::query()
            ->where('institute_id', $this->institute->id)
            ->firstOrFail();

        $existingActive = $batch->enrollments()->where('status', 'active')->count();

        $this->postJson('/students/'.$student->id.'/enroll', [
            'batch_id' => $batch->id,
            'enrollment_date' => now()->format('Y-m-d'),
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $student->id,
            'batch_id' => $batch->id,
        ]);
        $this->assertSame($existingActive + 1, $batch->refresh()->seat_filled);
    }

    public function test_student_enroll_rejects_foreign_batch_when_ajax(): void
    {
        $this->actingAs($this->staff, 'institute_user');

        $student = Student::query()->create([
            'institute_id' => $this->institute->id,
            'student_id_number' => Student::nextStudentNumber($this->institute->id),
            'first_name' => 'Ajax',
            'last_name' => 'Enroll',
            'admission_date' => now()->format('Y-m-d'),
            'status' => 'active',
        ]);

        $other = Institute::where('name', 'Halumoni Computer training center')->firstOrFail();
        $foreignBatch = Batch::query()->withoutGlobalScopes()->where('institute_id', $other->id)->first();

        $this->assertNotNull($foreignBatch);

        $this->postJson('/students/'.$student->id.'/enroll', [
            'batch_id' => $foreignBatch->id,
            'enrollment_date' => now()->format('Y-m-d'),
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['batch_id']]);
    }

    public function test_course_request_action_approve_returns_json_when_ajax(): void
    {
        $institute = $this->makeInstitute('pending');
        $course = Course::query()->create(['name' => 'Ajax Req Course', 'course_code' => 'ARC-'.uniqid(), 'status' => 'active']);
        $courseRequest = CourseRequest::query()->create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
            'requested_by' => $this->staff->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin, 'platform_admin');

        $this->postJson(route('admin.courses.requests.action', $courseRequest), ['action' => 'approve'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('approved', $courseRequest->refresh()->status);
        $this->assertDatabaseHas('institute_courses', [
            'institute_id' => $institute->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_course_assignment_assign_and_remove_returns_json_when_ajax(): void
    {
        $institute = $this->makeInstitute('active');
        $course = Course::query()->create(['name' => 'Ajax Assign Course', 'course_code' => 'AAC-'.uniqid(), 'status' => 'active']);

        $this->actingAs($this->admin, 'platform_admin');

        $this->postJson(route('admin.courses.assignment-assign'), [
            'institute_id' => $institute->id,
            'course_id' => $course->id,
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('institute_courses', [
            'institute_id' => $institute->id,
            'course_id' => $course->id,
        ]);

        $this->postJson(route('admin.courses.assignment-remove'), [
            'institute_id' => $institute->id,
            'course_id' => $course->id,
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('institute_courses', [
            'institute_id' => $institute->id,
            'course_id' => $course->id,
        ]);
    }

    public function test_assignment_assign_syncs_category_subjects_to_institute(): void
    {
        $institute = $this->makeInstitute('active');
        $category = CourseCategory::query()->create(['name' => 'Sync Cat '.uniqid(), 'slug' => 'sync-cat-'.uniqid(), 'status' => 'active', 'institute_id' => $institute->id]);
        $subjectA = Subject::query()->create([
            'name' => 'Sync Subject A', 'slug' => 'sync-a-'.uniqid(), 'subject_code' => 'SA-'.uniqid(),
            'category_id' => $category->id, 'institute_id' => $institute->id, 'status' => 'active',
        ]);
        $subjectB = Subject::query()->create([
            'name' => 'Sync Subject B', 'slug' => 'sync-b-'.uniqid(), 'subject_code' => 'SB-'.uniqid(),
            'category_id' => $category->id, 'institute_id' => $institute->id, 'status' => 'active',
        ]);
        $course = Course::query()->create([
            'name' => 'Sync Assign Course', 'course_code' => 'SAC-'.uniqid(),
            'category_id' => $category->id, 'status' => 'active',
        ]);

        $this->actingAs($this->admin, 'platform_admin');

        $this->postJson(route('admin.courses.assignment-assign'), [
            'institute_id' => $institute->id,
            'course_id' => $course->id,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('institute_courses', [
            'institute_id' => $institute->id,
            'course_id' => $course->id,
        ]);
        $this->assertDatabaseHas('institute_subjects', [
            'institute_id' => $institute->id,
            'subject_id' => $subjectA->id,
        ]);
        $this->assertDatabaseHas('institute_subjects', [
            'institute_id' => $institute->id,
            'subject_id' => $subjectB->id,
        ]);
    }

    public function test_course_request_approval_syncs_category_subjects_to_institute(): void
    {
        $institute = $this->makeInstitute('active');
        $category = CourseCategory::query()->create(['name' => 'Req Sync Cat '.uniqid(), 'slug' => 'req-sync-cat-'.uniqid(), 'status' => 'active', 'institute_id' => $institute->id]);
        $subjectA = Subject::query()->create([
            'name' => 'Req Sync Subject A', 'slug' => 'req-sync-a-'.uniqid(), 'subject_code' => 'RSA-'.uniqid(),
            'category_id' => $category->id, 'institute_id' => $institute->id, 'status' => 'active',
        ]);
        $course = Course::query()->create([
            'name' => 'Req Sync Course', 'course_code' => 'RSC-'.uniqid(),
            'category_id' => $category->id, 'status' => 'active',
        ]);
        $courseRequest = CourseRequest::query()->create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
            'requested_by' => $this->staff->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin, 'platform_admin');

        $this->postJson(route('admin.courses.requests.action', $courseRequest), ['action' => 'approve'])
            ->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseHas('institute_subjects', [
            'institute_id' => $institute->id,
            'subject_id' => $subjectA->id,
        ]);
    }

    public function test_institute_restore_returns_json_when_ajax(): void
    {
        $institute = $this->makeInstitute('active');
        $institute->update(['deleted_at' => now(), 'deleted_by' => $this->admin->id]);

        $this->actingAs($this->admin, 'platform_admin');

        $this->postJson(route('admin.institutes.restore', $institute))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull($institute->refresh()->deleted_at);
    }

    public function test_institute_force_delete_wrong_password_returns_json_422(): void
    {
        $institute = $this->makeInstitute('active');
        $institute->update(['deleted_at' => now(), 'deleted_by' => $this->admin->id]);

        $this->actingAs($this->admin, 'platform_admin');

        $this->deleteJson(route('admin.institutes.force-delete', $institute), [
            'password' => 'wrong-password',
        ])->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['password']]);

        $this->assertNotNull($institute->refresh()->deleted_at);
    }

    public function test_institute_force_delete_correct_password_returns_json_success(): void
    {
        $institute = $this->makeInstitute('active');
        $institute->update(['deleted_at' => now(), 'deleted_by' => $this->admin->id]);

        $this->actingAs($this->admin, 'platform_admin');

        $this->deleteJson(route('admin.institutes.force-delete', $institute), [
            'password' => $this->password,
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('institutes', ['id' => $institute->id]);
    }

    public function test_certificate_restore_returns_json_when_ajax(): void
    {
        $certificate = $this->makeCertificate('active');
        $certificate->delete();

        $this->actingAs($this->admin, 'platform_admin');

        $this->postJson(route('admin.certificates.restore', $certificate))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertNull($certificate->refresh()->deleted_at);
    }

    public function test_certificate_force_delete_correct_password_returns_json_success(): void
    {
        $certificate = $this->makeCertificate('active');
        $certificate->delete();

        $this->actingAs($this->admin, 'platform_admin');

        $this->deleteJson(route('admin.certificates.force-delete', $certificate), [
            'password' => $this->password,
        ])->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('certificates', ['id' => $certificate->id]);
    }
}
