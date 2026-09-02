<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Course;
use App\Models\CrmLead;
use App\Models\Institute;
use App\Models\InstituteCourse;
use App\Models\InstituteUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Services\RegistrationNumberService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdmissionWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    private Institute $institute;

    private InstituteUser $owner;

    private Branch $branch;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();

        $this->institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $this->owner = $this->makeStaff('institute-owner', 'admissions-owner@example.test');
        $this->branch = Branch::create([
            'institute_id' => $this->institute->id,
            'name' => 'Head Office',
        ]);
        $this->course = Course::findOrFail(
            InstituteCourse::where('institute_id', $this->institute->id)->firstOrFail()->course_id
        );
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

    protected function academicYear(): AcademicYear
    {
        return AcademicYear::firstOrCreate(
            ['institute_id' => $this->institute->id, 'code' => '2026'],
            [
                'name' => '2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'is_current' => true,
                'status' => true,
            ]
        );
    }

    protected function applicationPayload(array $overrides = []): array
    {
        $phone = '017'.random_int(10000000, 99999999);

        return array_merge([
            'full_name' => 'Rahima Akter',
            'gender' => 'female',
            'dob' => '2008-03-14',
            'phone' => $phone,
            'email' => 'rahima-'.$phone.'@example.test',
            'guardian_name' => 'Md. Karim Uddin',
            'guardian_phone' => '018'.random_int(10000000, 99999999),
            'branch_id' => $this->branch->id,
            'applied_course_id' => $this->course->id,
            'applied_academic_year_id' => $this->academicYear()->id,
            'application_date' => '2026-02-10',
            'admission_source' => 'walk_in',
            'present_zip_code' => '7830',
        ], $overrides);
    }

    protected function application(): Student
    {
        $this->actingAs($this->owner, 'institute_user')
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertRedirect();

        return Student::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->where('phone', '!=', '')
            ->latest('id')
            ->firstOrFail();
    }

    // ------------------------------------------------------------ Permissions

    public function test_guest_is_redirected_from_admissions_index(): void
    {
        $this->get(route('admissions.index'))->assertRedirect('/login');
    }

    public function test_teacher_can_view_but_not_manage_admissions(): void
    {
        $teacher = $this->makeStaff('teacher', 'admissions-teacher@example.test');

        $this->actingAs($teacher, 'institute_user')->get(route('admissions.index'))->assertOk();
        $this->actingAs($teacher, 'institute_user')->get(route('admissions.create'))->assertOk();
        $this->actingAs($teacher, 'institute_user')->get(route('admissions.pending'))->assertForbidden();

        $student = $this->submittedApplication();
        $this->actingAs($teacher, 'institute_user')->get(route('admissions.show', $student))->assertOk();
    }

    public function test_receptionist_and_branch_manager_can_manage_admissions(): void
    {
        $receptionist = $this->makeStaff('receptionist', 'admissions-reception@example.test');
        $manager = $this->makeStaff('branch-manager', 'admissions-manager@example.test');

        $this->actingAs($receptionist, 'institute_user')->get(route('admissions.create'))->assertOk();
        $this->actingAs($manager, 'institute_user')->get(route('admissions.create'))->assertOk();
    }

    // ------------------------------------------------------------ Application

    public function test_store_creates_application_as_approved_for_owner(): void
    {
        $this->application();

        $created = Student::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->where('first_name', 'Rahima')
            ->where('last_name', 'Akter')
            ->firstOrFail();

        // Owner has admission.approve → auto-approved
        $this->assertSame(Student::ADMISSION_STATUS_APPROVED, $created->admission_status);
        $this->assertSame(Student::STATUS_ACTIVE, $created->status);
        $this->assertSame('2026-02-10', $created->application_date->format('Y-m-d'));
        $this->assertSame('2026-02-10', $created->admission_date->format('Y-m-d'));
        $this->assertSame('AP-'.str_pad((string) $created->id, 6, '0', STR_PAD_LEFT), $created->application_number);
        $this->assertSame($this->course->id, $created->applied_course_id);
        $this->assertNotNull($created->registration_number);
        $this->assertNotNull($created->approved_by);
        $this->assertNotNull($created->approved_at);
    }

    public function test_store_creates_submitted_admission_for_receptionist(): void
    {
        $receptionist = $this->makeStaff('receptionist', 'adm-recv-submitted@example.test');

        $this->actingAs($receptionist, 'institute_user')
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertRedirect();

        $created = Student::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->where('first_name', 'Rahima')
            ->latest('id')
            ->first();

        // Receptionist lacks admission.approve → submitted for approval
        $this->assertSame(Student::ADMISSION_STATUS_SUBMITTED, $created->admission_status);
        $this->assertNull($created->registration_number);
        $this->assertNull($created->approved_by);
        $this->assertSame($receptionist->id, $created->created_by);
    }

    public function test_store_validates_required_fields(): void
    {
        $this->actingAs($this->owner, 'institute_user')
            ->post(route('admissions.store'), [])
            ->assertSessionHasErrors(['full_name', 'phone', 'branch_id', 'applied_course_id', 'application_date']);
    }

    public function test_store_rejects_duplicate_phone_in_same_institute(): void
    {
        $payload = $this->applicationPayload();
        $this->actingAs($this->owner, 'institute_user')
            ->post(route('admissions.store'), $payload)
            ->assertRedirect();

        $this->actingAs($this->owner, 'institute_user')
            ->post(route('admissions.store'), array_merge($payload, [
                'full_name' => 'Duplicate Phone',
                'email' => 'other-'.random_int(1000, 9999).'@example.test',
            ]))
            ->assertSessionHasErrors('phone');
    }

    public function test_store_rejects_course_not_offered_by_institute(): void
    {
        $foreignCourse = Course::withoutGlobalScopes()
            ->whereNotIn('id', InstituteCourse::where('institute_id', $this->institute->id)->pluck('course_id'))
            ->first();

        if ($foreignCourse === null) {
            $this->markTestSkipped('No foreign course available for the fixture.');
        }

        $this->actingAs($this->owner, 'institute_user')
            ->post(route('admissions.store'), $this->applicationPayload(['applied_course_id' => $foreignCourse->id]))
            ->assertSessionHasErrors('applied_course_id');
    }

    public function test_update_changes_application_details(): void
    {
        $student = $this->application();

        $this->actingAs($this->owner, 'institute_user')
            ->put(route('admissions.update', $student), $this->applicationPayload([
                'full_name' => 'Updated Name',
                'phone' => '017'.random_int(10000000, 99999999),
                'email' => null,
            ]))
            ->assertRedirect();

        $student->refresh();
        $this->assertSame('Updated', $student->first_name);
        $this->assertSame('Name', $student->last_name);
    }

    // --------------------------------------------------------------- Filters

    public function test_index_filters_by_status_course_and_branch(): void
    {
        $first = $this->application();
        $second = $this->application();

        $this->actingAs($this->owner, 'institute_user')
            ->get(route('admissions.index', ['admission_status' => 'approved']))
            ->assertOk()
            ->assertSee($first->application_number)
            ->assertSee($second->application_number);

        $this->actingAs($this->owner, 'institute_user')
            ->get(route('admissions.index', ['branch_id' => $this->branch->id]))
            ->assertOk()
            ->assertSee($first->application_number);

        $this->actingAs($this->owner, 'institute_user')
            ->get(route('admissions.index', ['course_id' => $this->course->id]))
            ->assertOk()
            ->assertSee($first->application_number);
    }

    public function test_index_search_by_application_number_and_phone(): void
    {
        $student = $this->application();

        $this->actingAs($this->owner, 'institute_user')
            ->get(route('admissions.index', ['q' => $student->application_number]))
            ->assertOk()
            ->assertSee($student->application_number);

        $this->actingAs($this->owner, 'institute_user')
            ->get(route('admissions.index', ['q' => $student->phone]))
            ->assertOk()
            ->assertSee($student->application_number);
    }

    // ---------------------------------------------------------- Transitions

    public function test_full_transition_chain_to_enrolled(): void
    {
        $student = $this->submittedApplication();
        $this->assertSame(Student::ADMISSION_STATUS_SUBMITTED, $student->admission_status);

        $this->transition($student, 'under_review');
        $this->transition($student, 'approved');

        $student->refresh();
        $this->assertSame(Student::ADMISSION_STATUS_APPROVED, $student->admission_status);
        $this->assertNotNull($student->registration_number);
        $this->assertMatchesRegularExpression('/^\d{12}$/', $student->registration_number);
    }

    public function test_approved_application_shows_no_enrolled_button(): void
    {
        $student = $this->submittedApplication();
        $this->transition($student, 'under_review');
        $this->transition($student, 'approved');

        $this->actingAs($this->owner, 'institute_user')
            ->get(route('admissions.show', $student))
            ->assertOk()
            ->assertDontSee('name="status" value="enrolled"', false);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $student = $this->submittedApplication();

        $this->actingAs($this->owner, 'institute_user')
            ->post(route('admissions.transition', $student), ['status' => 'approved'])
            ->assertSessionHasErrors('status');

        $student->refresh();
        $this->assertSame(Student::ADMISSION_STATUS_SUBMITTED, $student->admission_status);
    }

    public function test_rejected_and_cancelled_are_terminal(): void
    {
        $student = $this->submittedApplication();
        $this->transition($student, 'cancelled', 'Applicant withdrew interest');

        $this->actingAs($this->owner, 'institute_user')
            ->post(route('admissions.transition', $student), ['status' => 'submitted'])
            ->assertSessionHasErrors('status');

        $student->refresh();
        $this->assertSame(Student::ADMISSION_STATUS_CANCELLED, $student->admission_status);
    }

    public function test_reject_requires_reason(): void
    {
        $student = $this->submittedApplication();

        $this->actingAs($this->owner, 'institute_user')
            ->post(route('admissions.transition', $student), ['status' => 'rejected'])
            ->assertSessionHasErrors('reason');

        $this->transition($student, 'rejected', 'Incomplete documents');
        $student->refresh();
        $this->assertSame(Student::ADMISSION_STATUS_REJECTED, $student->admission_status);
        $this->assertSame('Incomplete documents', $student->admission_reject_reason);
    }

    // ------------------------------------------------------ Registration no.

    public function test_registration_number_format_sequence_and_uniqueness(): void
    {
        $service = app(RegistrationNumberService::class);

        $one = $this->studentWithZip('7830', '2026-05-01');
        $service->ensureFor($one);
        $this->assertSame('267830001001', $one->refresh()->registration_number);

        $two = $this->studentWithZip('7830', '2026-05-01');
        $service->ensureFor($two);
        $this->assertSame('267830001002', $two->refresh()->registration_number);

        // A different trade (course) gets its own sequence.
        $other = $this->studentWithZip('7830', '2026-05-01', true);
        $service->ensureFor($other);
        $this->assertSame(12, strlen($other->refresh()->registration_number));
        $this->assertNotSame($one->registration_number, $other->registration_number);

        // Different year resets the sequence too.
        $later = $this->studentWithZip('7830', '2027-05-01');
        $service->ensureFor($later);
        $this->assertSame('277830001001', $later->refresh()->registration_number);

        $regs = [$one->registration_number, $two->registration_number, $other->registration_number, $later->registration_number];
        $this->assertSame(count($regs), count(array_unique($regs)));
    }

    public function test_registration_number_skips_taken_values(): void
    {
        $service = app(RegistrationNumberService::class);

        $first = $this->studentWithZip('7830', '2026-05-01');
        $service->ensureFor($first);
        $taken = $first->refresh()->registration_number;

        // Simulate a legacy manually-entered number occupying the next slot.
        $blocker = $this->studentWithZip('7830', '2026-05-01');
        $blocker->forceFill(['registration_number' => '267830001002'])->save();

        $third = $this->studentWithZip('7830', '2026-05-01');
        $service->ensureFor($third);
        $this->assertSame('267830001003', $third->refresh()->registration_number);

        $this->assertNotSame($taken, $third->registration_number);
    }

    public function test_registration_number_never_overwrites_existing(): void
    {
        $student = $this->studentWithZip('7830', '2026-05-01');
        $student->forceFill(['registration_number' => '000000000001'])->save();

        app(RegistrationNumberService::class)->ensureFor($student);

        $this->assertSame('000000000001', $student->refresh()->registration_number);
    }

    // -------------------------------------------------- Tenant + enrollment

    public function test_cross_tenant_student_application_is_404(): void
    {
        $other = Institute::where('name', 'Halumoni Computer training center')->firstOrFail();
        $foreign = DB::table('students')->where('institute_id', $other->id)->first();

        if (! $foreign) {
            $this->markTestSkipped('No foreign student available in the test database.');
        }

        $this->actingAs($this->owner, 'institute_user')
            ->get(route('admissions.show', $foreign->id))
            ->assertNotFound();
    }

    public function test_enroll_marks_approved_application_as_enrolled(): void
    {
        $student = $this->submittedApplication();
        $this->transition($student, 'under_review');
        $this->transition($student, 'approved');

        $this->actingAs($this->owner, 'institute_user')
            ->post(route('students.enroll', $student), [
                'batch_id' => $this->batch()->id,
                'enrollment_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertSame(Student::ADMISSION_STATUS_ENROLLED, $student->admission_status);
        $this->assertMatchesRegularExpression('/^\d{12}$/', $student->registration_number);
        $this->assertDatabaseHas('student_enrollments', ['student_id' => $student->id]);
    }

    public function test_enroll_blocked_for_not_yet_approved_application(): void
    {
        // Create a staff admission that is in submitted state (not approved)
        $receptionist = $this->makeStaff('receptionist', 'adm-enroll-block@example.test');

        $this->actingAs($receptionist, 'institute_user')
            ->post(route('admissions.store'), $this->applicationPayload([
                'phone' => '017'.random_int(10000000, 99999999),
            ]))
            ->assertRedirect();

        $student = Student::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->where('first_name', 'Rahima')
            ->latest('id')
            ->first();

        $this->actingAs($this->owner, 'institute_user')
            ->post(route('students.enroll', $student), [
                'batch_id' => $this->batch()->id,
                'enrollment_date' => now()->format('Y-m-d'),
            ])
            ->assertSessionHasErrors('batch_id');

        $this->assertDatabaseMissing('student_enrollments', ['student_id' => $student->id]);
        $student->refresh();
        $this->assertSame(Student::ADMISSION_STATUS_SUBMITTED, $student->admission_status);
    }

    public function test_legacy_student_defaults_to_enrolled(): void
    {
        $legacy = Student::create([
            'institute_id' => $this->institute->id,
            'student_id_number' => Student::nextStudentNumber($this->institute->id),
            'first_name' => 'Legacy',
            'last_name' => 'Student',
            'admission_date' => now()->format('Y-m-d'),
            'status' => Student::STATUS_ACTIVE,
        ])->refresh();

        $this->assertSame(Student::ADMISSION_STATUS_ENROLLED, $legacy->admission_status);

        // Legacy students can still be enrolled without hitting the new guard.
        $this->actingAs($this->owner, 'institute_user')
            ->post(route('students.enroll', $legacy), [
                'batch_id' => $this->batch()->id,
                'enrollment_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();
    }

    // ---------------------------------------------------------------- CRM

    public function test_store_captures_crm_lead_and_contact_for_owner(): void
    {
        $student = $this->application();

        $student->refresh();
        $this->assertNotNull($student->crm_lead_id);
        $this->assertNotNull($student->crm_contact_id);

        $lead = CrmLead::withoutGlobalScopes()->find($student->crm_lead_id);
        $this->assertSame($this->institute->id, $lead->institute_id);
        $this->assertSame('Rahima', $lead->first_name);
    }

    public function test_store_skips_crm_for_clerk_without_crm_permission(): void
    {
        $role = Role::create([
            'name' => 'Admission Clerk',
            'slug' => 'admission-clerk-'.uniqid(),
            'status' => 'active',
        ]);
        $role->permissions()->attach(
            Permission::whereIn('slug', ['students.view', 'students.manage'])->pluck('id')
        );

        $clerk = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'email' => 'clerk-'.uniqid().'@example.test',
            'phone' => '017'.random_int(10000000, 99999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        $this->actingAs($clerk, 'institute_user')
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertRedirect();

        $created = Student::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->where('first_name', 'Rahima')
            ->latest('id')
            ->first();

        $this->assertNull($created->crm_lead_id);
        $this->assertNull($created->crm_contact_id);
    }

    // --------------------------------------------------------------- Audit

    public function test_transitions_are_audited(): void
    {
        $student = $this->submittedApplication();
        $before = AuditLog::withoutGlobalScopes()
            ->where('module', 'admission')
            ->where('record_id', $student->id)
            ->count();

        $this->transition($student, 'under_review');

        $logs = AuditLog::withoutGlobalScopes()
            ->where('module', 'admission')
            ->where('record_id', $student->id)
            ->orderBy('id')
            ->get();

        $this->assertGreaterThan($before, $logs->count());
        $this->assertContains('Admission submitted → under_review', $logs->pluck('action')->all());
        $this->assertSame($this->institute->id, $logs->last()->institute_id);
    }

    // ------------------------------------------------------------ Helpers

    // ------------------------------------------------- Approval Workflow Tests

    public function test_owner_creates_admission_with_auto_approval(): void
    {
        $this->application();

        $student = Student::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->where('first_name', 'Rahima')
            ->latest('id')
            ->first();

        $this->assertSame(Student::ADMISSION_STATUS_APPROVED, $student->admission_status);
        $this->assertNotNull($student->approved_by);
        $this->assertNotNull($student->approved_at);
        $this->assertNotNull($student->registration_number);
    }

    public function test_admin_creates_admission_with_auto_approval(): void
    {
        $admin = $this->makeStaff('institute-admin', 'adm-admin-auto@example.test');

        $this->actingAs($admin, 'institute_user')
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertRedirect();

        $student = Student::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->where('first_name', 'Rahima')
            ->latest('id')
            ->first();

        $this->assertSame(Student::ADMISSION_STATUS_APPROVED, $student->admission_status);
        $this->assertSame($admin->id, $student->approved_by);
    }

    public function test_receptionist_cannot_approve(): void
    {
        $receptionist = $this->makeStaff('receptionist', 'adm-recv-noapprove@example.test');
        $this->assertFalse($receptionist->hasPermission('admission.approve'));
    }

    public function test_teacher_cannot_approve(): void
    {
        $teacher = $this->makeStaff('teacher', 'adm-teach-noapprove@example.test');
        $this->assertFalse($teacher->hasPermission('admission.approve'));
    }

    public function test_branch_manager_can_approve(): void
    {
        $manager = $this->makeStaff('branch-manager', 'adm-mgr-approve@example.test');
        $this->assertTrue($manager->hasPermission('admission.approve'));
    }

    public function test_staff_admission_goes_to_pending(): void
    {
        $receptionist = $this->makeStaff('receptionist', 'adm-pending-test@example.test');

        $this->actingAs($receptionist, 'institute_user')
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertRedirect();

        $student = Student::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->where('first_name', 'Rahima')
            ->latest('id')
            ->first();

        $this->assertSame(Student::ADMISSION_STATUS_SUBMITTED, $student->admission_status);
        $this->assertNull($student->registration_number);
        $this->assertNull($student->approved_by);
    }

    public function test_pending_queue_visible_to_approver(): void
    {
        $this->actingAs($this->owner, 'institute_user')
            ->get(route('admissions.pending'))
            ->assertOk();
    }

    public function test_pending_queue_hidden_from_teacher(): void
    {
        $teacher = $this->makeStaff('teacher', 'adm-pending-hide@example.test');

        $this->actingAs($teacher, 'institute_user')
            ->get(route('admissions.pending'))
            ->assertForbidden();
    }

    public function test_review_page_visible_to_approver(): void
    {
        $student = $this->application();

        $this->actingAs($this->owner, 'institute_user')
            ->get(route('admissions.review', $student))
            ->assertOk();
    }

    public function test_approve_pending_admission(): void
    {
        $receptionist = $this->makeStaff('receptionist', 'adm-approve-test@example.test');

        $this->actingAs($receptionist, 'institute_user')
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertRedirect();

        $student = Student::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->where('first_name', 'Rahima')
            ->latest('id')
            ->first();

        $this->assertSame(Student::ADMISSION_STATUS_SUBMITTED, $student->admission_status);

        $this->actingAs($this->owner, 'institute_user')
            ->post(route('admissions.approve', $student))
            ->assertRedirect();

        $student->refresh();
        $this->assertSame(Student::ADMISSION_STATUS_APPROVED, $student->admission_status);
        $this->assertSame($this->owner->id, $student->approved_by);
        $this->assertNotNull($student->approved_at);
        $this->assertNotNull($student->registration_number);
    }

    public function test_reject_pending_admission(): void
    {
        $receptionist = $this->makeStaff('receptionist', 'adm-reject-test@example.test');

        $this->actingAs($receptionist, 'institute_user')
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertRedirect();

        $student = Student::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->where('first_name', 'Rahima')
            ->latest('id')
            ->first();

        $this->actingAs($this->owner, 'institute_user')
            ->post(route('admissions.reject', $student), ['reason' => 'Incomplete documents'])
            ->assertRedirect();

        $student->refresh();
        $this->assertSame(Student::ADMISSION_STATUS_REJECTED, $student->admission_status);
        $this->assertSame($this->owner->id, $student->rejected_by);
        $this->assertNotNull($student->rejected_at);
        $this->assertSame('Incomplete documents', $student->admission_reject_reason);
        $this->assertNull($student->registration_number);
    }

    public function test_rejection_requires_reason(): void
    {
        $receptionist = $this->makeStaff('receptionist', 'adm-reject-reason@example.test');

        $this->actingAs($receptionist, 'institute_user')
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertRedirect();

        $student = Student::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->where('first_name', 'Rahima')
            ->latest('id')
            ->first();

        $this->actingAs($this->owner, 'institute_user')
            ->post(route('admissions.reject', $student), [])
            ->assertSessionHasErrors('reason');
    }

    public function test_approve_creates_enrollment_eligibility(): void
    {
        $receptionist = $this->makeStaff('receptionist', 'adm-enroll-eligible@example.test');

        $this->actingAs($receptionist, 'institute_user')
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertRedirect();

        $student = Student::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->where('first_name', 'Rahima')
            ->latest('id')
            ->first();

        $this->actingAs($this->owner, 'institute_user')
            ->post(route('admissions.approve', $student))
            ->assertRedirect();

        $student->refresh();
        $this->assertSame(Student::ADMISSION_STATUS_APPROVED, $student->admission_status);

        // After approval, enrollment should be possible
        $this->actingAs($this->owner, 'institute_user')
            ->post(route('students.enroll', $student), [
                'batch_id' => $this->batch()->id,
                'enrollment_date' => now()->format('Y-m-d'),
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertSame(Student::ADMISSION_STATUS_ENROLLED, $student->admission_status);
        $this->assertDatabaseHas('student_enrollments', ['student_id' => $student->id]);
    }

    public function test_approval_creates_audit_log(): void
    {
        $receptionist = $this->makeStaff('receptionist', 'adm-audit-test@example.test');

        $this->actingAs($receptionist, 'institute_user')
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertRedirect();

        $student = Student::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->where('first_name', 'Rahima')
            ->latest('id')
            ->first();

        $before = AuditLog::withoutGlobalScopes()
            ->where('module', 'admission')
            ->where('record_id', $student->id)
            ->count();

        $this->actingAs($this->owner, 'institute_user')
            ->post(route('admissions.approve', $student))
            ->assertRedirect();

        $logs = AuditLog::withoutGlobalScopes()
            ->where('module', 'admission')
            ->where('record_id', $student->id)
            ->orderBy('id')
            ->get();

        $this->assertGreaterThan($before, $logs->count());
        $this->assertTrue($logs->pluck('action')->contains(fn ($a) => str_contains($a, 'approved')));
    }

    public function test_unauthorized_user_cannot_approve(): void
    {
        $teacher = $this->makeStaff('teacher', 'adm-unauth-approve@example.test');
        $student = $this->application();

        $this->actingAs($teacher, 'institute_user')
            ->post(route('admissions.approve', $student))
            ->assertForbidden();
    }

    public function test_unauthorized_user_cannot_reject(): void
    {
        $teacher = $this->makeStaff('teacher', 'adm-unauth-reject@example.test');
        $student = $this->application();

        $this->actingAs($teacher, 'institute_user')
            ->post(route('admissions.reject', $student), ['reason' => 'test'])
            ->assertForbidden();
    }

    protected function submittedApplication(): Student
    {
        $receptionist = $this->makeStaff('receptionist', 'adm-submitted-'.uniqid().'@example.test');

        $this->actingAs($receptionist, 'institute_user')
            ->post(route('admissions.store'), $this->applicationPayload())
            ->assertRedirect();

        return Student::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->where('first_name', 'Rahima')
            ->latest('id')
            ->firstOrFail();
    }

    protected function transition(Student $student, string $status, ?string $reason = null): void
    {
        $this->actingAs($this->owner, 'institute_user')
            ->post(route('admissions.transition', $student), [
                'status' => $status,
                'reason' => $reason,
            ])
            ->assertRedirect();
    }

    protected function studentWithZip(string $zip, string $admissionDate, bool $otherCourse = false): Student
    {
        $course = $otherCourse ? $this->differentCourse() : $this->course;

        return Student::create([
            'institute_id' => $this->institute->id,
            'branch_id' => $this->branch->id,
            'student_id_number' => Student::nextStudentNumber($this->institute->id),
            'first_name' => 'Seq',
            'last_name' => 'Student '.substr(md5(uniqid()), 0, 4),
            'phone' => '019'.random_int(10000000, 99999999),
            'present_zip_code' => $zip,
            'applied_course_id' => $course->id,
            'admission_date' => $admissionDate,
            'admission_status' => Student::ADMISSION_STATUS_APPROVED,
            'status' => Student::STATUS_ACTIVE,
        ]);
    }

    protected function differentCourse(): Course
    {
        $tradeOf = fn (Course $c) => substr(str_pad((string) preg_replace('/\D/', '', (string) $c->course_code), 3, '0', STR_PAD_LEFT), -3);

        return Course::findOrFail(
            InstituteCourse::where('institute_id', $this->institute->id)
                ->where('course_id', '!=', $this->course->id)
                ->get()
                ->pluck('course_id')
                ->first(fn ($id) => $tradeOf(Course::find($id)) !== $tradeOf($this->course))
        );
    }

    protected function batch(): Batch
    {
        return Batch::create([
            'institute_id' => $this->institute->id,
            'branch_id' => $this->branch->id,
            'course_id' => $this->course->id,
            'name' => 'Admission Test Batch',
            'batch_code' => 'AD'.random_int(1000, 9999),
            'start_date' => '2026-01-01',
            'seat_capacity' => 30,
            'status' => 'ongoing',
        ]);
    }
}
