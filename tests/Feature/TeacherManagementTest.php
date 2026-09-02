<?php

namespace Tests\Feature;

use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\Course;
use App\Models\EducationSystem;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\TeacherAcademicAssignment;
use App\Services\TeacherProfileService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Step 36 — Teacher / Instructor Management.
 *
 * Covers the instructor lifecycle: creation, profile, employment status,
 * branch rules, course/subject/batch assignment, duplicate prevention,
 * history preservation, search/filter/pagination, tenant+branch isolation,
 * the permission matrix, teacher self-access, audit logging and the
 * read-only workload summary.
 */
class TeacherManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function tearDown(): void
    {
        TenantContext::clear();
        BranchContext::clear();
        parent::tearDown();
    }

    // ------------------------------------------------------------- Fixtures

    private function seededContext(): array
    {
        $country = Country::firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]
        );

        $system = EducationSystem::firstOrCreate(
            ['country_id' => $country->id, 'code' => 'general'],
            ['name' => 'General Education', 'display_order' => 0, 'status' => true]
        );

        $level = AcademicLevel::create([
            'country_id' => $country->id,
            'education_system_id' => $system->id,
            'name' => 'Secondary',
            'code' => 'secondary',
            'display_order' => 1,
            'status' => true,
        ]);

        $class = ClassGrade::create([
            'country_id' => $country->id,
            'education_system_id' => $system->id,
            'academic_level_id' => $level->id,
            'name' => 'Class 10',
            'code' => 'c10-'.mt_rand(10, 99),
            'display_order' => 0,
            'status' => true,
        ]);

        $group = AcademicGroup::create([
            'country_id' => $country->id,
            'education_system_id' => $system->id,
            'academic_level_id' => $level->id,
            'class_grade_id' => $class->id,
            'name' => 'General',
            'code' => 'g'.mt_rand(10, 99),
            'display_order' => 0,
            'status' => true,
        ]);

        $institute = Institute::create([
            'name' => 'Teacher Institute',
            'slug' => str()->slug('teacher-institute-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);

        $branchA = Branch::create(['institute_id' => $institute->id, 'name' => 'Campus A', 'status' => 'active']);
        $branchB = Branch::create(['institute_id' => $institute->id, 'name' => 'Campus B', 'status' => 'active']);

        $owner = $this->instituteUser($institute, null, 'institute-owner', 'Owner', 'Owner');
        $manager = $this->instituteUser($institute, $branchA->id, 'branch-manager', 'Manager', 'Manager');
        $accountant = $this->instituteUser($institute, $branchA->id, 'accountant', 'Acc', 'Countant');
        $teacher = $this->instituteUser($institute, $branchA->id, 'teacher', 'Self', 'Teacher');

        $course = Course::create([
            'institute_id' => $institute->id,
            'course_code' => 'TC'.mt_rand(1000, 9999),
            'name' => 'Teacher Course',
            'status' => 'active',
        ]);

        $subject = Subject::create([
            'institute_id' => $institute->id,
            'subject_type' => 'academic',
            'subject_code' => 'TS'.mt_rand(1000, 9999),
            'name' => 'Mathematics',
            'slug' => 'math-'.uniqid(),
            'status' => 'active',
        ]);

        $batch = Batch::create([
            'institute_id' => $institute->id,
            'branch_id' => $branchA->id,
            'course_id' => $course->id,
            'name' => 'Batch A1',
            'batch_code' => 'BA1-'.mt_rand(10, 99),
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'status' => 'ongoing',
        ]);

        $year = AcademicYear::create([
            'institute_id' => $institute->id,
            'name' => 'Session 2025',
            'code' => '2025-'.mt_rand(10, 99),
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'is_current' => true,
            'status' => true,
        ]);

        $student = Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branchA->id,
            'student_id_number' => 'ST'.mt_rand(100000, 999999),
            'first_name' => 'Learn',
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => '2025-01-02',
        ]);

        StudentEnrollment::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'roll_number' => 'R'.mt_rand(10, 99),
            'enrollment_date' => '2025-01-03',
            'fee_payable' => 10000,
            'discount' => 0,
            'status' => 'active',
        ]);

        $institute2 = Institute::create([
            'name' => 'Other Institute',
            'slug' => str()->slug('other-institute-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);

        $branch2a = Branch::create(['institute_id' => $institute2->id, 'name' => 'Other Campus', 'status' => 'active']);
        $foreignOwner = $this->instituteUser($institute2, null, 'institute-owner', 'Foreign', 'Owner');
        $foreignTeacher = $this->instituteUser($institute2, $branch2a->id, 'teacher', 'Other', 'Teacher');

        return compact(
            'institute', 'branchA', 'branchB', 'owner', 'manager', 'accountant', 'teacher',
            'course', 'subject', 'batch', 'year', 'student',
            'institute2', 'branch2a', 'foreignOwner', 'foreignTeacher', 'class', 'group'
        );
    }

    private function instituteUser(Institute $institute, ?int $branchId, string $roleSlug, string $first, string $last): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branchId,
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'first_name' => $first,
            'last_name' => $last,
            'email' => strtolower($roleSlug).'-'.$first.'-'.uniqid().'@example.test',
            'phone' => '017'.mt_rand(10000000, 99999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function teacherPayload(array $overrides = []): array
    {
        $email = 'teacher-'.uniqid().'@example.test';
        $phone = '018'.mt_rand(10000000, 99999999);

        return array_merge([
            'first_name' => 'New',
            'last_name' => 'Teacher',
            'email' => $email,
            'phone' => $phone,
            'password' => $this->password,
            'password_confirmation' => $this->password,
            'branch_id' => null,
            'designation' => 'Senior Lecturer',
            'qualification' => 'MSc',
            'specialization' => 'Algebra',
            'employment_type' => 'full_time',
            'employment_status' => 'active',
            'experience_years' => 5,
            'joining_date' => '2024-06-01',
            'skills' => 'Teaching, Mentoring',
            'languages' => 'English, Bengali',
        ], $overrides);
    }

    private function assignmentPayload(array $overrides = []): array
    {
        return array_merge([
            'branch_id' => null,
            'academic_year_id' => null,
            'course_id' => null,
            'subject_id' => null,
            'batch_id' => null,
            'class_grade_id' => null,
            'academic_group_id' => null,
            'responsibility' => 'subject_teacher',
            'assigned_at' => '2025-01-10',
        ], $overrides);
    }

    // ---------------------------------------------------------------- Tests

    public function test_guest_cannot_access_teacher_pages(): void
    {
        $this->get(route('teachers.index'))->assertStatus(302);
        $this->get(route('teachers.create'))->assertStatus(302);
        $this->get(route('teachers.me'))->assertStatus(302);
    }

    public function test_unauthorized_role_is_rejected_with_403(): void
    {
        $ctx = $this->seededContext();

        $this->actingAs($ctx['accountant'], 'institute_user')
            ->get(route('teachers.index'))->assertForbidden();
        $this->actingAs($ctx['accountant'], 'institute_user')
            ->get(route('teachers.create'))->assertForbidden();
        $this->actingAs($ctx['accountant'], 'institute_user')
            ->post(route('teachers.store'), $this->teacherPayload(['branch_id' => $ctx['branchA']->id]))->assertForbidden();
    }

    public function test_owner_creates_teacher_with_profile_code_and_audit(): void
    {
        $ctx = $this->seededContext();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('teachers.store'), $this->teacherPayload(['branch_id' => $ctx['branchA']->id]))
            ->assertRedirect();

        $teacher = InstituteUser::where('email', $this->emailOf($ctx['branchA']->id, null))->first();
        $this->assertNotNull($teacher);
        $this->assertSame('teacher', $teacher->role->slug);
        $this->assertMatchesRegularExpression('/^EMP-\d{2,}-\d{5}$/', $teacher->employee_id);
        $this->assertSame('active', $teacher->status);

        $this->assertDatabaseHas('teacher_profiles', [
            'institute_user_id' => $teacher->id,
            'specialization' => 'Algebra',
            'employment_status' => 'active',
            'employment_type' => 'full_time',
        ]);

        $audit = DB::table('audit_logs')
            ->where('module', 'teachers')
            ->where('action', 'teacher_created')
            ->where('record_id', $teacher->id)
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($audit);
        $this->assertSame($ctx['owner']->id, (int) $audit->user_id);
        $this->assertStringNotContainsString($this->password, (string) $audit->new_values);
        $this->assertStringNotContainsString('password_hash', (string) $audit->new_values);
    }

    private function emailOf(int $branchId, ?int $instituteId): string
    {
        return DB::table('institute_users')
            ->where('branch_id', $branchId)
            ->when($instituteId !== null, fn ($q) => $q->where('institute_id', $instituteId))
            ->orderByDesc('id')
            ->value('email');
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $ctx = $this->seededContext();

        $payload = $this->teacherPayload(['branch_id' => $ctx['branchA']->id, 'email' => $ctx['manager']->email]);

        $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('teachers.store'), $payload)
            ->assertSessionHasErrors('email');
    }

    public function test_institute_id_from_input_cannot_escape_tenant_scope(): void
    {
        $ctx = $this->seededContext();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('teachers.store'), $this->teacherPayload([
                'branch_id' => $ctx['branchA']->id,
                'institute_id' => $ctx['institute2']->id,
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('institute_users', [
            'email' => $this->emailOf($ctx['branchA']->id, $ctx['institute']->id),
            'institute_id' => $ctx['institute']->id,
        ]);
    }

    public function test_branch_manager_creating_teacher_is_pinned_to_own_branch(): void
    {
        $ctx = $this->seededContext();

        $this->actingAs($ctx['manager'], 'institute_user')
            ->post(route('teachers.store'), $this->teacherPayload(['branch_id' => $ctx['branchB']->id]))
            ->assertRedirect();

        $email = $this->emailOf($ctx['branchA']->id, $ctx['institute']->id);
        $this->assertDatabaseHas('institute_users', ['email' => $email, 'branch_id' => $ctx['branchA']->id]);
        $this->assertDatabaseMissing('institute_users', ['email' => $email, 'branch_id' => $ctx['branchB']->id]);
    }

    public function test_teacher_profile_update_and_audit(): void
    {
        $ctx = $this->seededContext();
        $teacher = $this->createTeacherViaHttp($ctx, $ctx['branchA']->id);

        $this->actingAs($ctx['owner'], 'institute_user')
            ->put(route('teachers.update', $teacher), $this->teacherPayload([
                'branch_id' => $ctx['branchA']->id,
                'first_name' => 'Renamed',
                'designation' => 'Professor',
                'specialization' => 'Geometry',
                'skills' => 'Research',
                'employment_status' => 'on_leave',
            ]))
            ->assertRedirect();

        $teacher->refresh();
        $this->assertSame('Renamed Teacher', $teacher->name);
        $this->assertSame('Professor', $teacher->designation);
        $this->assertSame('Geometry', $teacher->teacherProfile->specialization);
        $this->assertSame(['Research'], $teacher->teacherProfile->skills);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'teachers',
            'action' => 'teacher_updated',
            'record_id' => $teacher->id,
        ]);
    }

    public function test_status_change_syncs_account_and_preserves_history(): void
    {
        $ctx = $this->seededContext();
        $teacher = $this->createTeacherViaHttp($ctx, $ctx['branchA']->id);

        $this->profileServiceAssign($teacher, $ctx, $ctx['branchA']->id, ['responsibility' => 'class_teacher']);

        $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('teachers.status', $teacher), ['employment_status' => 'resigned'])
            ->assertRedirect();

        $teacher->refresh();
        $this->assertSame('resigned', $teacher->teacherProfile->employment_status);
        $this->assertSame('inactive', $teacher->status);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'teachers',
            'action' => 'teacher_status_changed',
            'record_id' => $teacher->id,
        ]);

        // Historical assignment must survive deactivation.
        $this->assertDatabaseHas('teacher_academic_assignments', [
            'institute_user_id' => $teacher->id,
            'status' => 'active',
        ]);
    }

    public function test_deactivated_teacher_cannot_receive_new_assignment(): void
    {
        $ctx = $this->seededContext();
        $teacher = $this->createTeacherViaHttp($ctx, $ctx['branchA']->id);

        $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('teachers.status', $teacher), ['employment_status' => 'terminated'])
            ->assertRedirect();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('teachers.assign', $teacher), $this->assignmentPayload([
                'branch_id' => $ctx['branchA']->id,
                'academic_year_id' => $ctx['year']->id,
                'subject_id' => $ctx['subject']->id,
            ]))
            ->assertStatus(422);

        $this->assertSame(0, TeacherAcademicAssignment::where('institute_user_id', $teacher->id)->count());
    }

    public function test_cross_tenant_teacher_is_not_visible(): void
    {
        $ctx = $this->seededContext();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get(route('teachers.show', $ctx['foreignTeacher']))
            ->assertNotFound();
    }

    public function test_cross_branch_access_is_denied_for_branch_manager(): void
    {
        $ctx = $this->seededContext();
        $branchTeacher = $this->createTeacherViaHttp($ctx, $ctx['branchA']->id);
        $otherBranchTeacher = $this->createTeacherViaHttp($ctx, $ctx['branchB']->id);

        $this->actingAs($ctx['manager'], 'institute_user')
            ->get(route('teachers.show', $branchTeacher))
            ->assertOk();

        $this->actingAs($ctx['manager'], 'institute_user')
            ->get(route('teachers.show', $otherBranchTeacher))
            ->assertNotFound();
    }

    public function test_branch_manager_cannot_assign_cross_branch_teacher(): void
    {
        $ctx = $this->seededContext();
        $otherBranchTeacher = $this->createTeacherViaHttp($ctx, $ctx['branchB']->id);

        $this->actingAs($ctx['manager'], 'institute_user')
            ->post(route('teachers.assign', $otherBranchTeacher), $this->assignmentPayload([
                'branch_id' => $ctx['branchB']->id,
                'academic_year_id' => $ctx['year']->id,
                'subject_id' => $ctx['subject']->id,
            ]))
            ->assertNotFound();
    }

    public function test_teacher_self_access_shows_own_profile(): void
    {
        $ctx = $this->seededContext();
        $teacher = $this->createTeacherViaHttp($ctx, $ctx['branchA']->id);
        $this->profileServiceAssign($teacher, $ctx, $ctx['branchA']->id, []);

        $this->actingAs($teacher, 'institute_user')
            ->get(route('teachers.me'))
            ->assertOk()
            ->assertSee($teacher->name);
    }

    public function test_teacher_cannot_manage_other_teacher(): void
    {
        $ctx = $this->seededContext();
        $teacherA = $this->createTeacherViaHttp($ctx, $ctx['branchA']->id);
        $teacherB = $this->createTeacherViaHttp($ctx, $ctx['branchA']->id);

        $this->actingAs($teacherA, 'institute_user')
            ->get(route('teachers.index'))->assertForbidden();
        $this->actingAs($teacherA, 'institute_user')
            ->get(route('teachers.show', $teacherB))->assertForbidden();
        $this->actingAs($teacherA, 'institute_user')
            ->post(route('teachers.assign', $teacherB), $this->assignmentPayload(['academic_year_id' => $ctx['year']->id]))
            ->assertForbidden();
    }

    public function test_search_filters_and_pagination(): void
    {
        $ctx = $this->seededContext();
        $created = $this->createTeacherViaHttp($ctx, $ctx['branchA']->id, ['first_name' => 'Alim', 'last_name' => 'Uddin']);
        $created2 = $this->createTeacherViaHttp($ctx, $ctx['branchB']->id, ['first_name' => 'Bilal', 'last_name' => 'Hossain', 'designation' => 'Lecturer']);

        for ($i = 0; $i < 21; $i++) {
            DB::table('institute_users')->insert([
                'institute_id' => $ctx['institute']->id,
                'branch_id' => $ctx['branchA']->id,
                'role_id' => Role::where('slug', 'teacher')->firstOrFail()->id,
                'first_name' => 'Bulk',
                'last_name' => 'Teacher'.$i,
                'email' => 'bulk-'.$i.'-'.uniqid().'@example.test',
                'phone' => '019'.mt_rand(10000000, 99999999),
                'password_hash' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
                'status' => 'active',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get(route('teachers.index', ['q' => 'Alim']))
            ->assertOk()
            ->assertSee('Alim Uddin')
            ->assertDontSee('Bulk Teacher');

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get(route('teachers.index', ['q' => $created->employee_id]))
            ->assertOk()
            ->assertSee($created->employee_id);

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get(route('teachers.index', ['branch_id' => $ctx['branchA']->id]))
            ->assertOk()
            ->assertDontSee('Bilal Hossain');

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get(route('teachers.index', ['status' => 'inactive']))
            ->assertOk();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get(route('teachers.index', ['designation' => 'Senior Lecturer']))
            ->assertOk()
            ->assertSee('Alim Uddin');

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get(route('teachers.index', ['employment_status' => 'active']))
            ->assertOk();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get(route('teachers.index', ['qualification' => 'MSc']))
            ->assertOk();

        $pageOne = $this->actingAs($ctx['owner'], 'institute_user')->get(route('teachers.index'));
        $pageOne->assertOk();
        $this->assertCount(20, $pageOne->viewData('teachers')->items());

        $pageTwo = $this->actingAs($ctx['owner'], 'institute_user')->get(route('teachers.index', ['page' => 2]));
        $pageTwo->assertOk();
        $this->assertCount(4, $pageTwo->viewData('teachers')->items());
        $this->assertGreaterThan(20, $pageTwo->viewData('teachers')->total());
    }

    public function test_assignment_created_with_scope_and_audit(): void
    {
        $ctx = $this->seededContext();
        $teacher = $this->createTeacherViaHttp($ctx, $ctx['branchA']->id);

        $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('teachers.assign', $teacher), $this->assignmentPayload([
                'branch_id' => $ctx['branchA']->id,
                'academic_year_id' => $ctx['year']->id,
                'course_id' => $ctx['course']->id,
                'subject_id' => $ctx['subject']->id,
                'batch_id' => $ctx['batch']->id,
                'responsibility' => 'subject_teacher',
            ]))
            ->assertRedirect();

        $this->assertDatabaseHas('teacher_academic_assignments', [
            'institute_user_id' => $teacher->id,
            'branch_id' => $ctx['branchA']->id,
            'academic_year_id' => $ctx['year']->id,
            'course_id' => $ctx['course']->id,
            'subject_id' => $ctx['subject']->id,
            'batch_id' => $ctx['batch']->id,
            'responsibility' => 'subject_teacher',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'module' => 'teachers',
            'action' => 'assignment_created',
        ]);
    }

    public function test_duplicate_active_assignment_is_prevented(): void
    {
        $ctx = $this->seededContext();
        $teacher = $this->createTeacherViaHttp($ctx, $ctx['branchA']->id);

        $payload = $this->assignmentPayload([
            'branch_id' => $ctx['branchA']->id,
            'academic_year_id' => $ctx['year']->id,
            'subject_id' => $ctx['subject']->id,
        ]);

        $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('teachers.assign', $teacher), $payload)->assertRedirect();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('teachers.assign', $teacher), $payload)
            ->assertSessionHasErrors('assignment');

        $this->assertSame(1, TeacherAcademicAssignment::where('institute_user_id', $teacher->id)->count());
    }

    public function test_multiple_distinct_assignments_are_allowed(): void
    {
        $ctx = $this->seededContext();
        $teacher = $this->createTeacherViaHttp($ctx, $ctx['branchA']->id);

        $secondSubject = Subject::create([
            'institute_id' => $ctx['institute']->id,
            'subject_type' => 'academic',
            'subject_code' => 'TS'.mt_rand(1000, 9999),
            'name' => 'Physics',
            'slug' => 'physics-'.uniqid(),
            'status' => 'active',
        ]);

        $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('teachers.assign', $teacher), $this->assignmentPayload([
                'branch_id' => $ctx['branchA']->id,
                'academic_year_id' => $ctx['year']->id,
                'subject_id' => $ctx['subject']->id,
            ]))->assertRedirect();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('teachers.assign', $teacher), $this->assignmentPayload([
                'branch_id' => $ctx['branchA']->id,
                'academic_year_id' => $ctx['year']->id,
                'subject_id' => $secondSubject->id,
                'responsibility' => 'examiner',
            ]))->assertRedirect();

        $this->assertSame(2, TeacherAcademicAssignment::where('institute_user_id', $teacher->id)->count());
    }

    public function test_assignment_completed_and_removed_with_audit(): void
    {
        $ctx = $this->seededContext();
        $teacher = $this->createTeacherViaHttp($ctx, $ctx['branchA']->id);
        $assignment = $this->profileServiceAssign($teacher, $ctx, $ctx['branchA']->id, []);

        $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('teachers.complete', $assignment))
            ->assertRedirect();

        $assignment->refresh();
        $this->assertSame('completed', $assignment->status);
        $this->assertNotNull($assignment->completed_at);

        $this->actingAs($ctx['owner'], 'institute_user')
            ->delete(route('teachers.remove', $assignment))
            ->assertRedirect();

        $this->assertDatabaseMissing('teacher_academic_assignments', ['id' => $assignment->id]);
        $this->assertDatabaseHas('audit_logs', ['module' => 'teachers', 'action' => 'assignment_removed']);
    }

    public function test_workload_summary_is_calculated_from_assignments(): void
    {
        $ctx = $this->seededContext();
        $teacher = $this->createTeacherViaHttp($ctx, $ctx['branchA']->id);

        $secondBatch = Batch::create([
            'institute_id' => $ctx['institute']->id,
            'branch_id' => $ctx['branchA']->id,
            'course_id' => $ctx['course']->id,
            'name' => 'Batch A2',
            'batch_code' => 'BA2-'.mt_rand(10, 99),
            'start_date' => '2025-02-01',
            'end_date' => '2025-12-31',
            'status' => 'ongoing',
        ]);

        StudentEnrollment::create([
            'institute_id' => $ctx['institute']->id,
            'student_id' => $ctx['student']->id,
            'course_id' => $ctx['course']->id,
            'batch_id' => $secondBatch->id,
            'roll_number' => 'R'.mt_rand(10, 99),
            'enrollment_date' => '2025-02-02',
            'fee_payable' => 10000,
            'discount' => 0,
            'status' => 'active',
        ]);

        $a1 = $this->profileServiceAssign($teacher, $ctx, $ctx['branchA']->id, ['batch_id' => $ctx['batch']->id, 'subject_id' => $ctx['subject']->id]);
        $this->profileServiceAssign($teacher, $ctx, $ctx['branchA']->id, ['batch_id' => $secondBatch->id, 'subject_id' => $ctx['subject']->id, 'responsibility' => 'batch_coordinator']);

        $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('teachers.complete', $a1))
            ->assertRedirect();

        $this->actingAs($ctx['owner'], 'institute_user')
            ->get(route('teachers.show', $teacher))
            ->assertOk()
            ->assertSee('Current workload');

        $this->assertSame(1, TeacherAcademicAssignment::where('institute_user_id', $teacher->id)->where('status', 'active')->count());
        $this->assertSame(2, TeacherAcademicAssignment::where('institute_user_id', $teacher->id)->count());
    }

    public function test_branch_scoped_assignment_not_visible_to_other_branch_manager(): void
    {
        $ctx = $this->seededContext();
        $teacher = $this->createTeacherViaHttp($ctx, $ctx['branchB']->id);
        $assignment = $this->profileServiceAssign($teacher, $ctx, $ctx['branchB']->id, []);

        $this->actingAs($ctx['manager'], 'institute_user')
            ->post(route('teachers.complete', $assignment))
            ->assertNotFound();

        $assignment->refresh();
        $this->assertSame('active', $assignment->status);
    }

    // ------------------------------------------------------------- Helpers

    private function createTeacherViaHttp(array $ctx, int $branchId, array $overrides = []): InstituteUser
    {
        $payload = $this->teacherPayload(array_merge(['branch_id' => $branchId], $overrides));
        $email = $payload['email'];

        $this->actingAs($ctx['owner'], 'institute_user')
            ->post(route('teachers.store'), $payload)
            ->assertRedirect();

        return InstituteUser::where('email', $email)->firstOrFail();
    }

    private function profileServiceAssign(InstituteUser $teacher, array $ctx, int $branchId, array $overrides): TeacherAcademicAssignment
    {
        $service = app(TeacherProfileService::class);

        return $service->assign(array_merge([
            'institute_user_id' => $teacher->id,
            'branch_id' => $branchId,
            'academic_year_id' => $ctx['year']->id,
            'subject_id' => $ctx['subject']->id,
            'responsibility' => 'subject_teacher',
        ], $overrides), $ctx['institute']->id, $ctx['owner']->id);
    }
}
