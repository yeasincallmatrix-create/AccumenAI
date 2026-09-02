<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseRequest;
use App\Models\Institute;
use App\Models\Notification;
use App\Models\PlatformAdmin;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Support\NotificationCenter;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class AdminActionsTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected PlatformAdmin $admin;

    protected function setUp(): void
    {
        parent::setUp();
        TenantContext::clear();
        $this->admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'action-admin@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    protected function makeInstitute(string $status = 'pending'): Institute
    {
        return Institute::query()->create([
            'name' => 'Action Test Institute',
            'slug' => 'action-test-'.uniqid(),
            'status' => $status,
        ]);
    }

    public function test_platform_admin_approves_institute(): void
    {
        $institute = $this->makeInstitute('pending');
        $this->actingAs($this->admin, 'platform_admin');

        $this->post(route('admin.institutes.action', ['institute' => $institute, 'action' => 'approve']))
            ->assertRedirect(route('admin.institutes.index'));

        $this->assertSame('active', $institute->refresh()->status);
    }

    public function test_delete_institute_requires_correct_password(): void
    {
        $institute = $this->makeInstitute('active');
        $this->actingAs($this->admin, 'platform_admin');

        $this->post(route('admin.institutes.action', $institute), ['action' => 'delete', 'password' => 'wrong-password'])
            ->assertSessionHasErrors('password');

        $this->assertNull($institute->refresh()->deleted_at);

        $this->post(route('admin.institutes.action', $institute), ['action' => 'delete', 'password' => $this->password])
            ->assertRedirect(route('admin.institutes.index'));

        $this->assertNotNull($institute->refresh()->deleted_at);
        $this->assertSame('cancelled', $institute->refresh()->status);
    }

    public function test_restore_brings_institute_back(): void
    {
        $institute = $this->makeInstitute('active');
        $institute->update(['deleted_at' => now(), 'deleted_by' => $this->admin->id, 'status' => 'cancelled']);
        $this->actingAs($this->admin, 'platform_admin');

        $this->post(route('admin.institutes.restore', $institute))
            ->assertRedirect(route('admin.institutes.bin'));

        $this->assertNull($institute->refresh()->deleted_at);
        $this->assertSame('active', $institute->refresh()->status);
    }

    public function test_force_delete_requires_password(): void
    {
        $institute = $this->makeInstitute('active');
        $institute->update(['deleted_at' => now()]);
        $this->actingAs($this->admin, 'platform_admin');

        $this->delete(route('admin.institutes.force-delete', $institute), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');
        $this->assertDatabaseHas('institutes', ['id' => $institute->id]);

        $this->delete(route('admin.institutes.force-delete', $institute), ['password' => $this->password])
            ->assertRedirect(route('admin.institutes.bin'));
        $this->assertDatabaseMissing('institutes', ['id' => $institute->id]);
    }

    public function test_course_request_approval_assigns_course(): void
    {
        $institute = $this->makeInstitute('active');
        $course = Course::query()->create(['name' => 'Action Course', 'course_code' => 'C-'.uniqid(), 'status' => 'active']);
        $request = CourseRequest::query()->create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
            'requested_by' => 1,
            'status' => 'pending',
        ]);
        $this->actingAs($this->admin, 'platform_admin');

        $this->post(route('admin.courses.requests.action', $request), ['action' => 'approve'])
            ->assertRedirect(route('admin.courses.requests', ['industry' => 'education']));

        $this->assertSame('approved', $request->refresh()->status);
        $this->assertDatabaseHas('institute_courses', [
            'institute_id' => $institute->id,
            'course_id' => $course->id,
        ]);
        $this->assertDatabaseHas('notifications', ['institute_id' => $institute->id, 'category' => 'course_request']);
    }

    public function test_course_request_rejection_sets_status(): void
    {
        $institute = $this->makeInstitute('active');
        $course = Course::query()->create(['name' => 'Reject Course', 'course_code' => 'R-'.uniqid(), 'status' => 'active']);
        $request = CourseRequest::query()->create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
            'requested_by' => 1,
            'status' => 'pending',
        ]);
        $this->actingAs($this->admin, 'platform_admin');

        $this->post(route('admin.courses.requests.action', $request), ['action' => 'reject'])
            ->assertRedirect(route('admin.courses.requests', ['industry' => 'education']));

        $this->assertSame('rejected', $request->refresh()->status);
    }

    public function test_dashboard_shows_pending_course_requests(): void
    {
        $institute = $this->makeInstitute('active');
        $course = Course::query()->create(['name' => 'Dashboard Pending Course', 'course_code' => 'DPC-'.uniqid(), 'status' => 'active']);
        CourseRequest::query()->create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
            'requested_by' => 1,
            'status' => 'pending',
        ]);
        $this->actingAs($this->admin, 'platform_admin');

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Course Requests')
            ->assertSee('Dashboard Pending Course');
    }

    public function test_dashboard_sub_industry_dropdown_shows_for_education_and_filters(): void
    {
        Institute::query()->create([
            'name' => 'Sub Industry College',
            'slug' => 'sub-college-'.uniqid(),
            'industry' => 'education',
            'sub_industry' => 'college',
            'status' => 'active',
        ]);
        Institute::query()->create([
            'name' => 'Sub Industry School',
            'slug' => 'sub-school-'.uniqid(),
            'industry' => 'education',
            'sub_industry' => 'school',
            'status' => 'active',
        ]);
        $this->actingAs($this->admin, 'platform_admin');

        $this->get(route('dashboard', ['industry' => 'education']))
            ->assertOk()
            ->assertSee('All Sub Industries', false)
            ->assertSee('Institution', false)
            ->assertSee('Madrasha', false)
            ->assertSee('Sub Industry College')
            ->assertSee('Sub Industry School');

        $this->get(route('dashboard', ['industry' => 'education', 'sub_industry' => 'college']))
            ->assertOk()
            ->assertSee('College', false)
            ->assertSee('Sub Industry College')
            ->assertDontSee('Sub Industry School');
    }

    public function test_dashboard_sub_industry_dropdown_hidden_for_industry_without_sub_industries(): void
    {
        Institute::query()->create([
            'name' => 'Sub Industry Finance Bank',
            'slug' => 'sub-finance-'.uniqid(),
            'industry' => 'finance',
            'status' => 'active',
        ]);
        $this->actingAs($this->admin, 'platform_admin');

        $this->get(route('dashboard', ['industry' => 'finance']))
            ->assertOk()
            ->assertDontSee('All Sub Industries', false)
            ->assertDontSee('sub-industry-filter', false);
    }

    public function test_student_registration_page_is_standard_list_view(): void
    {
        $institute = $this->makeInstitute('active');
        $student = Student::query()->create([
            'institute_id' => $institute->id,
            'first_name' => 'SLP',
            'last_name' => 'Student',
            'student_id_number' => 'SLP'.uniqid(),
            'phone' => '01900000000',
            'email' => 'slp@example.test',
            'admission_date' => now(),
            'status' => 'active',
        ]);
        $this->actingAs($this->admin, 'platform_admin');

        $this->get(route('admin.students.index'))
            ->assertOk()
            ->assertSee('filter-search-row', false)
            ->assertSee('studentsTable', false)
            ->assertSee('studentsTablePrint', false)
            ->assertSee('exportCsvBtn', false)
            ->assertSee('SLP Student')
            ->assertSee($student->student_id_number)
            ->assertSee('All Status');
    }

    public function test_student_registration_filters_by_institute_and_status(): void
    {
        $instituteA = $this->makeInstitute('active');
        $instituteB = $this->makeInstitute('active');
        Student::query()->create([
            'institute_id' => $instituteA->id,
            'first_name' => 'Filter',
            'last_name' => 'Active Student',
            'student_id_number' => 'FAS-'.uniqid(),
            'admission_date' => now(),
            'status' => 'active',
        ]);
        Student::query()->create([
            'institute_id' => $instituteB->id,
            'first_name' => 'Filter',
            'last_name' => 'Dropped Student',
            'student_id_number' => 'FDS-'.uniqid(),
            'admission_date' => now(),
            'status' => 'dropped',
        ]);
        $this->actingAs($this->admin, 'platform_admin');

        $this->get(route('admin.students.index', ['institute_id' => $instituteA->id]))
            ->assertOk()
            ->assertSee('Filter Active Student')
            ->assertDontSee('Filter Dropped Student');

        $this->get(route('admin.students.index', ['status' => 'dropped']))
            ->assertOk()
            ->assertSee('Filter Dropped Student')
            ->assertDontSee('Filter Active Student');
    }

    public function test_student_registration_columns_preference_is_saved(): void
    {
        $this->actingAs($this->admin, 'platform_admin');

        $this->post(route('admin.students.columns'), ['columns' => ['serial', 'name', 'status', 'action']])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(['serial', 'name', 'status', 'action'], $this->admin->preference('students_columns'));
    }

    public function test_student_registration_filters_by_type(): void
    {
        $institute = $this->makeInstitute('active');
        $professionalCategory = CourseCategory::query()->create([
            'name' => 'Student Type Prof Category',
            'slug' => 'student-type-prof-'.uniqid(),
            'institute_id' => $institute->id,
            'subject_type' => 'professional',
        ]);
        $academicCategory = CourseCategory::query()->create([
            'name' => 'Student Type Academic Category',
            'slug' => 'student-type-academic-'.uniqid(),
            'institute_id' => $institute->id,
            'subject_type' => 'academic',
        ]);
        $professionalCourse = Course::query()->create([
            'name' => 'Student Type Prof Course',
            'course_code' => 'STP-'.uniqid(),
            'category_id' => $professionalCategory->id,
            'status' => 'active',
        ]);
        $academicCourse = Course::query()->create([
            'name' => 'Student Type Academic Course',
            'course_code' => 'STA-'.uniqid(),
            'category_id' => $academicCategory->id,
            'status' => 'active',
        ]);
        $professionalStudent = Student::query()->create([
            'institute_id' => $institute->id,
            'first_name' => 'Type',
            'last_name' => 'Professional Student',
            'student_id_number' => 'TPS-'.uniqid(),
            'admission_date' => now(),
            'status' => 'active',
        ]);
        $academicStudent = Student::query()->create([
            'institute_id' => $institute->id,
            'first_name' => 'Type',
            'last_name' => 'Academic Student',
            'student_id_number' => 'TAS-'.uniqid(),
            'admission_date' => now(),
            'status' => 'active',
        ]);
        $batch = Batch::query()->create([
            'institute_id' => $institute->id,
            'course_id' => $professionalCourse->id,
            'name' => 'Type Batch A',
            'batch_code' => 'TB-'.uniqid(),
            'start_date' => now()->toDateString(),
        ]);
        StudentEnrollment::query()->create([
            'institute_id' => $institute->id,
            'student_id' => $professionalStudent->id,
            'course_id' => $professionalCourse->id,
            'batch_id' => $batch->id,
            'roll_number' => 'RN-'.uniqid(),
            'enrollment_date' => now(),
        ]);
        $batchB = Batch::query()->create([
            'institute_id' => $institute->id,
            'course_id' => $academicCourse->id,
            'name' => 'Type Batch B',
            'batch_code' => 'TB-'.uniqid(),
            'start_date' => now()->toDateString(),
        ]);
        StudentEnrollment::query()->create([
            'institute_id' => $institute->id,
            'student_id' => $academicStudent->id,
            'course_id' => $academicCourse->id,
            'batch_id' => $batchB->id,
            'roll_number' => 'RN-'.uniqid(),
            'enrollment_date' => now(),
        ]);
        $this->actingAs($this->admin, 'platform_admin');

        $this->get(route('admin.students.index', ['type' => 'professional']))
            ->assertOk()
            ->assertSee('All Types')
            ->assertSee('Type Professional Student')
            ->assertDontSee('Type Academic Student');

        $this->get(route('admin.students.index', ['type' => 'academic']))
            ->assertOk()
            ->assertSee('Type Academic Student')
            ->assertDontSee('Type Professional Student');
    }

    public function test_institutes_list_page_is_standard_list_view(): void
    {
        $institute = $this->makeInstitute('active');
        $institute->update(['industry' => 'education', 'sub_industry' => 'college', 'institute_code' => 'SLP-INST']);
        $this->actingAs($this->admin, 'platform_admin');

        $this->get(route('admin.institutes.index'))
            ->assertOk()
            ->assertSee('filter-search-row', false)
            ->assertSee('institutesTable', false)
            ->assertSee('institutesTablePrint', false)
            ->assertSee('exportCsvBtn', false)
            ->assertSee('All Industries')
            ->assertSee('All Status')
            ->assertSee('Action Test Institute');
    }

    public function test_institutes_list_filters_by_industry_status_and_sub_industry(): void
    {
        $education = $this->makeInstitute('active');
        $education->update(['name' => 'SLP Edu College', 'industry' => 'education', 'sub_industry' => 'college']);
        $healthcare = $this->makeInstitute('active');
        $healthcare->update(['name' => 'SLP Health Clinic', 'industry' => 'healthcare']);
        $pending = $this->makeInstitute('pending');
        $pending->update(['name' => 'SLP Pending School', 'industry' => 'education', 'sub_industry' => 'school']);
        $this->actingAs($this->admin, 'platform_admin');

        $this->get(route('admin.institutes.index', ['industry' => 'education']))
            ->assertOk()
            ->assertSee('SLP Edu College')
            ->assertSee('SLP Pending School')
            ->assertSee('All Sub Industries')
            ->assertSee('School')
            ->assertDontSee('SLP Health Clinic');

        $this->get(route('admin.institutes.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('SLP Pending School')
            ->assertDontSee('SLP Edu College');

        $this->get(route('admin.institutes.index', ['sub_industry' => 'college']))
            ->assertOk()
            ->assertSee('SLP Edu College')
            ->assertDontSee('SLP Pending School');
    }

    public function test_institutes_list_columns_preference_is_saved(): void
    {
        $this->actingAs($this->admin, 'platform_admin');

        $this->post(route('admin.institutes.columns'), [
            'columns' => ['serial', 'institute', 'status', 'bogus-column'],
        ])->assertOk()->assertJson(['ok' => true, 'columns' => ['serial', 'institute', 'status']]);

        $this->assertSame(['serial', 'institute', 'status'], $this->admin->preference('institutes_columns'));
    }

    public function test_requests_page_filters_by_status_and_search(): void
    {
        $institute = $this->makeInstitute('active');
        $pendingCourse = Course::query()->create(['name' => 'Request Filter Pending', 'course_code' => 'RFP-'.uniqid(), 'status' => 'active']);
        $approvedCourse = Course::query()->create(['name' => 'Request Filter Approved', 'course_code' => 'RFA-'.uniqid(), 'status' => 'active']);
        CourseRequest::query()->create([
            'institute_id' => $institute->id,
            'course_id' => $pendingCourse->id,
            'requested_by' => 1,
            'status' => 'pending',
        ]);
        CourseRequest::query()->create([
            'institute_id' => $institute->id,
            'course_id' => $approvedCourse->id,
            'requested_by' => 1,
            'status' => 'approved',
        ]);
        $this->actingAs($this->admin, 'platform_admin');

        $this->get(route('admin.courses.requests'))
            ->assertOk()
            ->assertSee('Course Requests')
            ->assertSee('Request Filter Pending')
            ->assertSee('Request Filter Approved');

        $this->get(route('admin.courses.requests', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('Request Filter Pending')
            ->assertDontSee('Request Filter Approved');

        $this->get(route('admin.courses.requests', ['q' => 'Request Filter Approved']))
            ->assertOk()
            ->assertSee('Request Filter Approved')
            ->assertDontSee('Request Filter Pending');
    }

    public function test_requests_columns_preference_is_saved(): void
    {
        $this->actingAs($this->admin, 'platform_admin');

        $this->post(route('admin.courses.requests-columns'), ['columns' => ['institute', 'course', 'status']])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(['institute', 'course', 'status'], $this->admin->preference('requests_columns'));

        $this->get(route('admin.courses.requests'))
            ->assertOk()
            ->assertSee('Course Requests');
    }

    public function test_subjects_page_filters_by_search_and_status(): void
    {
        $institute = $this->makeInstitute('active');
        $category = CourseCategory::query()->create(['name' => 'Subject Filter Category', 'slug' => 'subject-filter-category-'.uniqid(), 'institute_id' => $institute->id]);
        Subject::query()->create(['name' => 'Subject Filter Active', 'slug' => 'subject-filter-active-'.uniqid(), 'subject_code' => 'SFA-'.uniqid(), 'subject_type' => 'professional', 'category_id' => $category->id, 'status' => 'active']);
        Subject::query()->create(['name' => 'Subject Filter Draft', 'slug' => 'subject-filter-draft-'.uniqid(), 'subject_code' => 'SFD-'.uniqid(), 'subject_type' => 'professional', 'category_id' => $category->id, 'status' => 'draft']);
        $this->actingAs($this->admin, 'platform_admin');

        $this->get(route('admin.courses.subjects'))
            ->assertOk()
            ->assertSee('Subjects')
            ->assertSee('Subject Filter Active')
            ->assertSee('Subject Filter Draft');

        $this->get(route('admin.courses.subjects', ['q' => 'Subject Filter Draft']))
            ->assertOk()
            ->assertSee('Subject Filter Draft')
            ->assertDontSee('Subject Filter Active');

        $this->get(route('admin.courses.subjects', ['status' => 'active']))
            ->assertOk()
            ->assertSee('Subject Filter Active')
            ->assertDontSee('Subject Filter Draft');
    }

    public function test_subjects_columns_preference_is_saved(): void
    {
        $this->actingAs($this->admin, 'platform_admin');

        $this->post(route('admin.courses.subjects-columns'), ['columns' => ['serial', 'name', 'status']])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame(['serial', 'name', 'status'], $this->admin->preference('subjects_columns'));

        $this->get(route('admin.courses.subjects'))
            ->assertOk()
            ->assertSee('Subjects');
    }

    public function test_courses_index_filters_by_type(): void
    {
        $institute = $this->makeInstitute('active');
        $professionalCategory = CourseCategory::query()->create([
            'name' => 'Type Prof Category',
            'slug' => 'type-prof-'.uniqid(),
            'institute_id' => $institute->id,
            'subject_type' => 'professional',
        ]);
        $academicCategory = CourseCategory::query()->create([
            'name' => 'Type Academic Category',
            'slug' => 'type-academic-'.uniqid(),
            'institute_id' => $institute->id,
            'subject_type' => 'academic',
        ]);
        Course::query()->create([
            'name' => 'Type Filter Professional Course',
            'course_code' => 'TFP-'.uniqid(),
            'category_id' => $professionalCategory->id,
            'status' => 'active',
        ]);
        Course::query()->create([
            'name' => 'Type Filter Academic Course',
            'course_code' => 'TFA-'.uniqid(),
            'category_id' => $academicCategory->id,
            'status' => 'active',
        ]);
        $this->actingAs($this->admin, 'platform_admin');

        $this->get(route('admin.courses.index', ['type' => 'professional']))
            ->assertOk()
            ->assertSee('Type Filter Professional Course')
            ->assertDontSee('Type Filter Academic Course');

        $this->get(route('admin.courses.index', ['type' => 'academic']))
            ->assertOk()
            ->assertSee('Type Filter Academic Course')
            ->assertDontSee('Type Filter Professional Course');

        $this->get(route('admin.courses.assignment', ['type' => 'academic']))
            ->assertOk()
            ->assertSee('Type Filter Academic Course')
            ->assertDontSee('Type Filter Professional Course');
    }

    public function test_certificate_approve_and_revoke(): void
    {
        $institute = $this->makeInstitute('active');
        $course = Course::query()->create(['name' => 'Cert Course', 'course_code' => 'GC-'.uniqid(), 'status' => 'active']);
        $student = Student::query()->create([
            'institute_id' => $institute->id,
            'first_name' => 'Cert',
            'last_name' => 'Student',
            'student_id_number' => 'AT'.uniqid(),
            'admission_date' => now(),
            'status' => 'active',
        ]);
        $batch = Batch::query()->create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
            'name' => 'Batch A',
            'batch_code' => 'BA-'.uniqid(),
            'start_date' => now()->toDateString(),
        ]);
        $certificate = Certificate::query()->create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'pending',
        ]);
        $this->actingAs($this->admin, 'platform_admin');

        $this->post(route('admin.certificates.action', $certificate), ['action' => 'approve'])
            ->assertRedirect(route('admin.certificates.requests'));
        $this->assertSame('active', $certificate->refresh()->status);
        $this->assertNotNull($certificate->refresh()->certificate_number);

        $this->post(route('admin.certificates.action', $certificate), ['action' => 'revoke', 'reason' => 'Fraud'])
            ->assertRedirect(route('admin.certificates.index'));
        $this->assertSame('revoked', $certificate->refresh()->status);
        $this->assertSame('Fraud', (new Certificate)->find($certificate->id)->revoked_reason);
    }

    public function test_certificate_revoke_can_be_cancelled(): void
    {
        $institute = $this->makeInstitute('active');
        $course = Course::query()->create(['name' => 'Undo Course', 'course_code' => 'UC-'.uniqid(), 'status' => 'active']);
        $student = Student::query()->create([
            'institute_id' => $institute->id,
            'first_name' => 'Undo',
            'last_name' => 'Student',
            'student_id_number' => 'US'.uniqid(),
            'admission_date' => now(),
            'status' => 'active',
        ]);
        $batch = Batch::query()->create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
            'name' => 'Undo Batch',
            'batch_code' => 'UB-'.uniqid(),
            'start_date' => now()->toDateString(),
        ]);
        $certificate = Certificate::query()->create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);
        $this->actingAs($this->admin, 'platform_admin');

        $this->post(route('admin.certificates.action', $certificate), ['action' => 'revoke', 'reason' => 'Mistake'])
            ->assertRedirect(route('admin.certificates.index'));
        $this->assertSame('revoked', $certificate->refresh()->status);

        $this->post(route('admin.certificates.action', $certificate), ['action' => 'revoke-cancel'])
            ->assertRedirect(route('admin.certificates.index'));
        $certificate->refresh();
        $this->assertSame('active', $certificate->status);
        $this->assertNull($certificate->revoked_reason);
    }

    public function test_admin_does_not_see_own_action_notification(): void
    {
        $institute = $this->makeInstitute('active');
        $course = Course::query()->create(['name' => 'Notif Course', 'course_code' => 'NC-'.uniqid(), 'status' => 'active']);
        $student = Student::query()->create([
            'institute_id' => $institute->id,
            'first_name' => 'Notif',
            'last_name' => 'Student',
            'student_id_number' => 'NS'.uniqid(),
            'admission_date' => now(),
            'status' => 'active',
        ]);
        $batch = Batch::query()->create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
            'name' => 'Notif Batch',
            'batch_code' => 'NB-'.uniqid(),
            'start_date' => now()->toDateString(),
        ]);
        $certificate = Certificate::query()->create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);
        $this->actingAs($this->admin, 'platform_admin');
        Auth::shouldUse('platform_admin');

        $this->post(route('admin.certificates.action', $certificate), ['action' => 'revoke', 'reason' => 'Fraud'])
            ->assertRedirect(route('admin.certificates.index'));

        $notification = Notification::query()
            ->where('scope', 'institute')
            ->where('institute_id', $institute->id)
            ->where('title', 'Certificate revoked')
            ->latest('id')
            ->first();

        $this->assertNotNull($notification, 'Institute-scoped revoke notification should still be created.');
        $this->assertFalse(NotificationCenter::isVisible($notification));
        $this->assertEmpty(NotificationCenter::visibleQuery()->whereKey($notification->id)->get());
        $this->get(route('admin.notifications.index'))->assertOk()->assertDontSee('has been revoked.');

        // A notification the admin did NOT create stays visible.
        $external = Notification::create([
            'scope' => 'institute',
            'institute_id' => $institute->id,
            'category' => 'certificate',
            'title' => 'Certificate submitted',
            'message' => 'A certificate was submitted for review.',
            'created_by_type' => 'institute_user',
            'created_by_id' => 999,
            'created_at' => now(),
        ]);
        $this->assertTrue(NotificationCenter::isVisible($external));
        $this->get(route('admin.notifications.index'))->assertOk()->assertSee('Certificate submitted');
    }

    public function test_admin_can_view_student_profile(): void
    {
        $institute = $this->makeInstitute('active');
        $student = Student::query()->create([
            'institute_id' => $institute->id,
            'first_name' => 'View',
            'last_name' => 'Me',
            'student_id_number' => 'VS'.uniqid(),
            'admission_date' => now(),
            'status' => 'active',
        ]);
        $this->actingAs($this->admin, 'platform_admin');

        $this->get(route('admin.students.show', $student))
            ->assertOk()
            ->assertSee($student->full_name);
        $this->get(route('admin.institutes.show', $institute))->assertOk()->assertSee($institute->name);
        $this->get(route('admin.institutes.edit', $institute))->assertOk()->assertSee('Edit Institute');
    }

    public function test_admin_update_institute_changes_fields(): void
    {
        $institute = $this->makeInstitute('active');
        $this->actingAs($this->admin, 'platform_admin');

        $this->put(route('admin.institutes.update', $institute), [
            'name' => 'Renamed Institute',
            'slug' => $institute->slug,
            'status' => 'suspended',
            'verified' => 1,
        ])->assertRedirect(route('admin.institutes.show', $institute));

        $institute->refresh();
        $this->assertSame('Renamed Institute', $institute->name);
        $this->assertSame('suspended', $institute->status);
        $this->assertSame(1, $institute->verified);
    }

    public function test_certificates_page_renders_and_filters(): void
    {
        $institute = $this->makeInstitute('active');
        $course = Course::query()->create(['name' => 'SLP Course', 'course_code' => 'SC-'.uniqid(), 'status' => 'active']);
        $student = Student::query()->create([
            'institute_id' => $institute->id,
            'first_name' => 'SLP',
            'last_name' => 'Cert',
            'student_id_number' => 'AT'.uniqid(),
            'admission_date' => now(),
            'status' => 'active',
        ]);
        $batch = Batch::query()->create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
            'name' => 'Batch S',
            'batch_code' => 'BS-'.uniqid(),
            'start_date' => now()->toDateString(),
        ]);
        $certificate = Certificate::query()->create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
        ]);
        $this->actingAs($this->admin, 'platform_admin');

        $this->get(route('admin.certificates.index'))
            ->assertOk()
            ->assertSee('Certificates')
            ->assertSee('QR Code')
            ->assertSee('Remarks')
            ->assertSee($student->full_name)
            ->assertSee($course->name);

        $this->get(route('admin.certificates.index', ['q' => 'SLP']))
            ->assertOk()
            ->assertSee($student->full_name);

        $this->get(route('admin.certificates.index', ['q' => 'NoMatchXYZ']))
            ->assertOk()
            ->assertDontSee($student->full_name);

        $this->get(route('admin.certificates.index', ['status' => 'rejected']))
            ->assertOk()
            ->assertDontSee($student->full_name);

        $this->get(route('admin.certificates.index', ['institute_id' => $institute->id]))
            ->assertOk()
            ->assertSee($student->full_name);

        $this->assertNotNull($certificate);
    }

    public function test_certificates_columns_preference_is_saved(): void
    {
        $this->actingAs($this->admin, 'platform_admin');

        $this->post(route('admin.certificates.columns'), [
            'columns' => ['serial', 'bogus-column', 'status'],
        ])->assertOk()->assertJson(['ok' => true, 'columns' => ['serial', 'status']]);

        $this->assertSame(['serial', 'status'], $this->admin->preference('certificates_columns'));
    }

    protected function makeCertificate(string $status = 'pending'): Certificate
    {
        $institute = $this->makeInstitute('active');
        $course = Course::query()->create(['name' => 'QR Course', 'course_code' => 'QC-'.uniqid(), 'status' => 'active']);
        $student = Student::query()->create([
            'institute_id' => $institute->id,
            'first_name' => 'QR',
            'last_name' => 'Student',
            'student_id_number' => 'QR'.uniqid(),
            'admission_date' => now(),
            'status' => 'active',
        ]);
        $batch = Batch::query()->create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
            'name' => 'QR Batch',
            'batch_code' => 'QB-'.uniqid(),
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

    public function test_public_certificate_verification_check_page(): void
    {
        $this->get(route('verify.certificate.index'))
            ->assertOk()
            ->assertSee('Verify a Certificate')
            ->assertSee('Check Certificate');

        $this->post(route('verify.certificate.check'), ['certificate_number' => ''])
            ->assertSessionHasErrors('certificate_number');

        $certificate = $this->makeCertificate();
        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.certificates.action', $certificate), ['action' => 'approve'])
            ->assertRedirect(route('admin.certificates.requests'));

        $number = $certificate->refresh()->certificate_number;

        $this->post(route('verify.certificate.check'), ['certificate_number' => strtolower($number)])
            ->assertRedirect(route('verify.certificate', strtoupper($number)));
    }

    public function test_public_certificate_verification_page(): void
    {
        $certificate = $this->makeCertificate();
        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.certificates.action', $certificate), ['action' => 'approve'])
            ->assertRedirect(route('admin.certificates.requests'));

        $number = $certificate->refresh()->certificate_number;
        $this->assertNotNull($number);

        $this->get(route('verify.certificate', $number))
            ->assertOk()
            ->assertSee('Certificate Verified')
            ->assertSee('VALID CERTIFICATE')
            ->assertSee($certificate->student->full_name)
            ->assertSee($number);

        $this->get(route('verify.certificate', 'MNT-0000-UNKNOWN'))
            ->assertNotFound();

        $this->post(route('admin.certificates.action', $certificate), ['action' => 'revoke', 'reason' => 'Fraud'])
            ->assertRedirect(route('admin.certificates.index'));

        $this->get(route('verify.certificate', $number))
            ->assertOk()
            ->assertSee('REVOKED')
            ->assertSee('Fraud');
    }

    public function test_admin_certificate_show_page_renders_qr(): void
    {
        $certificate = $this->makeCertificate();
        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.certificates.action', $certificate), ['action' => 'approve']);

        $res = $this->get(route('admin.certificates.show', $certificate));
        if ($res->status() === 302) {
            dump('REDIRECT to '.$res->headers->get('Location'));
            dump($res->getContent());
        }
        $res->assertOk()
            ->assertSee(strtoupper($certificate->student->full_name))
            ->assertSee('Scan to verify')
            ->assertSee('svg', false)
            ->assertSee($certificate->refresh()->certificate_number)
            ->assertSee('Template 1 of 3');

        $this->get(route('admin.certificates.show', ['certificate' => $certificate, 'template' => 2]))
            ->assertOk()
            ->assertSee(strtoupper($certificate->student->full_name))
            ->assertSee('Scan to verify')
            ->assertSee($certificate->refresh()->certificate_number)
            ->assertSee('Template 2 of 3');

        $this->get(route('admin.certificates.show', ['certificate' => $certificate, 'template' => 3]))
            ->assertOk()
            ->assertSee(strtoupper($certificate->student->full_name))
            ->assertSee('Scan to verify')
            ->assertSee($certificate->refresh()->certificate_number)
            ->assertSee('Template 3 of 3')
            ->assertSee('Authorized Signatory')
            ->assertSee('Director');

        $this->get(route('admin.certificates.show', ['certificate' => $certificate, 'template' => 9]))
            ->assertOk()
            ->assertSee('Template 1 of 3');

        $pending = $this->makeCertificate();
        $this->get(route('admin.certificates.show', $pending))
            ->assertOk()
            ->assertDontSee('Scan to verify');
    }

    public function test_admin_certificate_show_page_requires_auth(): void
    {
        $certificate = $this->makeCertificate();
        Auth::logout();

        $this->get(route('admin.certificates.show', $certificate))
            ->assertStatus(302);
    }

    public function test_admin_certificate_qr_download(): void
    {
        $certificate = $this->makeCertificate();
        $this->actingAs($this->admin, 'platform_admin');
        $this->post(route('admin.certificates.action', $certificate), ['action' => 'approve']);

        $this->get(route('admin.certificates.qr', $certificate))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml')
            ->assertHeader('Content-Disposition', 'attachment; filename="'.$certificate->refresh()->certificate_number.'.svg"')
            ->assertSee('svg', false);

        $pending = $this->makeCertificate();
        $this->get(route('admin.certificates.qr', $pending))
            ->assertNotFound();
    }

    public function test_certificate_requests_page_shows_requester_data(): void
    {
        $pending = $this->makeCertificate('pending');
        $issued = $this->makeCertificate('active');
        $issued->student->update(['first_name' => 'Issued', 'last_name' => 'Person']);
        $this->actingAs($this->admin, 'platform_admin');

        $this->get(route('admin.certificates.requests'))
            ->assertOk()
            ->assertSee('Certificate Request')
            ->assertSee('QR Student')
            ->assertSee($pending->course->name)
            ->assertDontSee('Issued Person');

        $this->get(route('admin.certificates.index'))
            ->assertOk()
            ->assertSee('Issued Person')
            ->assertDontSee('QR Student');
    }

    public function test_certificate_requests_columns_preference_is_saved(): void
    {
        $this->actingAs($this->admin, 'platform_admin');

        $this->post(route('admin.certificates.requests-columns'), [
            'columns' => ['serial', 'bogus-column', 'institute'],
        ])->assertOk()->assertJson(['ok' => true, 'columns' => ['serial', 'institute']]);

        $this->assertSame(['serial', 'institute'], $this->admin->preference('certificate_requests_columns'));
    }

    public function test_admin_certificate_soft_delete_restore_and_force_delete(): void
    {
        $certificate = $this->makeCertificate('active');
        $this->actingAs($this->admin, 'platform_admin');

        $this->delete(route('admin.certificates.destroy', $certificate))
            ->assertRedirect();
        $this->assertSoftDeleted('certificates', ['id' => $certificate->id]);
        $this->get(route('admin.certificates.index'))->assertDontSee($certificate->student->full_name);

        $this->get(route('admin.institutes.bin'))
            ->assertOk()
            ->assertSee($certificate->student->full_name);

        $this->post(route('admin.certificates.restore', $certificate))
            ->assertRedirect(route('admin.institutes.bin'));
        $this->assertDatabaseHas('certificates', ['id' => $certificate->id, 'deleted_at' => null]);
        $this->get(route('admin.certificates.index'))->assertSee($certificate->student->full_name);

        $this->delete(route('admin.certificates.destroy', $certificate));
        $this->delete(route('admin.certificates.force-delete', $certificate), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');
        $this->assertSoftDeleted('certificates', ['id' => $certificate->id]);

        $this->delete(route('admin.certificates.force-delete', $certificate), ['password' => $this->password])
            ->assertRedirect(route('admin.institutes.bin'));
        $this->assertDatabaseMissing('certificates', ['id' => $certificate->id]);
    }
}
