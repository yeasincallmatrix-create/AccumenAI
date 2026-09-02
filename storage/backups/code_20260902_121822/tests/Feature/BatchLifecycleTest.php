<?php

namespace Tests\Feature;

use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultPolicy;
use App\Models\AcademicFinalResultStudent;
use App\Models\AcademicGroup;
use App\Models\AcademicLevel;
use App\Models\AcademicResultAggregationScheme;
use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\ClassGrade;
use App\Models\Country;
use App\Models\Course;
use App\Models\EducationSystem;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PromotionDecision;
use App\Models\PromotionDecisionItem;
use App\Models\PromotionPolicy;
use App\Models\Result;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentAcademicPlacement;
use App\Models\StudentEnrollment;
use App\Models\TeacherAcademicAssignment;
use App\Services\Education\BatchLifecycleService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * STEP 41 — class / batch / academic-session management lifecycle.
 *
 * Covers batch creation with academic year + capacity, seat-capacity
 * enforcement, auto roll numbers, status transitions, instructor filters,
 * tenant/branch isolation, permission matrix, exited-student protection,
 * historical data preservation and the audit trail.
 */
class BatchLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    // ------------------------------------------------------------- Fixtures

    private function country(string $iso2 = 'BD', string $name = 'Bangladesh'): Country
    {
        return Country::firstOrCreate(['iso2' => $iso2], ['name' => $name,
            'iso3' => strtoupper($iso2).'D',
            'phone_code' => '880',
            'status' => true,
        ]);
    }

    private function system(Country $country, string $code = 'general'): EducationSystem
    {
        return EducationSystem::firstOrCreate(
            ['country_id' => $country->id, 'code' => $code],
            ['name' => 'General Education', 'display_order' => 0, 'status' => true]
        );
    }

    private function level(EducationSystem $system, string $code = 'secondary'): AcademicLevel
    {
        return AcademicLevel::create([
            'country_id' => $system->country_id,
            'education_system_id' => $system->id,
            'name' => 'Secondary',
            'code' => $code,
            'display_order' => 1,
            'status' => true,
        ]);
    }

    private function classGrade(AcademicLevel $level, string $code = 'c8'): ClassGrade
    {
        return ClassGrade::create([
            'country_id' => $level->country_id,
            'education_system_id' => $level->education_system_id,
            'academic_level_id' => $level->id,
            'name' => 'Class 8',
            'code' => $code,
            'display_order' => 0,
            'status' => true,
        ]);
    }

    private function group(ClassGrade $classGrade, string $code = 'sci'): AcademicGroup
    {
        return AcademicGroup::create([
            'country_id' => $classGrade->country_id,
            'education_system_id' => $classGrade->education_system_id,
            'academic_level_id' => $classGrade->academic_level_id,
            'class_grade_id' => $classGrade->id,
            'name' => 'Science',
            'code' => $code,
            'display_order' => 0,
            'status' => true,
        ]);
    }

    private function institute(Country $country, string $name = 'Batch Inst'): Institute
    {
        return Institute::create([
            'name' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute, string $name = 'Main Branch'): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function user(Institute $institute, string $roleSlug, string $prefix, ?Branch $branch = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'first_name' => 'Staff',
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function student(Institute $institute, string $name = 'Rahim', ?Branch $branch = null): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'student_id_number' => Student::nextStudentNumber($institute->id),
            'first_name' => $name,
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => now()->toDateString(),
        ]);
    }

    private function year(Institute $institute, ?string $code = null): AcademicYear
    {
        $code ??= 'Y'.uniqid();

        return AcademicYear::create([
            'institute_id' => $institute->id,
            'name' => 'Academic Year '.$code,
            'code' => $code,
            'is_current' => false,
            'status' => true,
        ]);
    }

    private function course(): Course
    {
        return Course::create([
            'institute_id' => null,
            'course_code' => 'C'.mt_rand(100000, 999999),
            'name' => 'Catalog Course '.mt_rand(1000, 9999),
            'status' => 'active',
        ]);
    }

    private function batch(Institute $institute, array $attrs = []): Batch
    {
        $courseId = $attrs['course_id'] ?? $this->course()->id;
        $yearId = $attrs['academic_year_id'] ?? $this->year($institute)->id;

        return Batch::create(array_merge([
            'institute_id' => $institute->id,
            'course_id' => $courseId,
            'academic_year_id' => $yearId,
            'name' => 'Batch '.mt_rand(1000, 9999),
            'batch_code' => 'B'.mt_rand(100000, 999999),
            'shift' => 'day',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(4)->toDateString(),
            'seat_capacity' => 30,
            'seat_filled' => 0,
            'status' => 'upcoming',
        ], $attrs));
    }

    private function enroll(Student $student, Batch $batch, ?string $roll = null): StudentEnrollment
    {
        $roll ??= 'R'.mt_rand(100000, 999999);

        return StudentEnrollment::create([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'course_id' => $batch->course_id,
            'batch_id' => $batch->id,
            'roll_number' => $roll,
            'enrollment_date' => now()->toDateString(),
            'fee_payable' => 0,
            'discount' => 0,
            'status' => 'active',
        ]);
    }

    private function assign(InstituteUser $teacher, Batch $batch, string $responsibility = 'course_instructor'): TeacherAcademicAssignment
    {
        return TeacherAcademicAssignment::create([
            'institute_id' => $batch->institute_id,
            'branch_id' => $teacher->branch_id,
            'institute_user_id' => $teacher->id,
            'batch_id' => $batch->id,
            'responsibility' => $responsibility,
            'status' => 'active',
            'assigned_at' => now()->toDateString(),
        ]);
    }

    private function placement(Student $student, AcademicYear $year, ClassGrade $classGrade, ?AcademicGroup $academicGroup = null, string $status = 'active'): StudentAcademicPlacement
    {
        return StudentAcademicPlacement::create([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => $academicGroup?->id,
            'status' => $status,
        ]);
    }

    private function frozenResultChain(Institute $institute, Student $student, AcademicYear $year, ClassGrade $classGrade, ?AcademicGroup $academicGroup = null): array
    {
        $placement = $this->placement($student, $year, $classGrade, $academicGroup);

        $scheme = AcademicResultAggregationScheme::create([
            'institute_id' => $institute->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => $classGrade->id,
            'academic_group_id' => $academicGroup?->id,
            'name' => 'Aggregation Scheme',
            'status' => 'active',
            'display_order' => 0,
        ]);

        $policy = AcademicFinalResultPolicy::create([
            'institute_id' => $institute->id,
            'scheme_id' => $scheme->id,
            'name' => 'Final Result Policy',
            'status' => 'active',
        ]);

        $finalResult = AcademicFinalResult::create([
            'institute_id' => $institute->id,
            'policy_id' => $policy->id,
            'scheme_id' => $scheme->id,
            'name' => 'Final Result',
            'status' => 'locked',
        ]);

        $finalResultStudent = AcademicFinalResultStudent::create([
            'result_id' => $finalResult->id,
            'placement_id' => $placement->id,
            'gpa' => 3.5,
            'gpa_status' => 'computed',
            'passed_count' => 5,
            'failed_count' => 0,
        ]);

        $promotionPolicy = PromotionPolicy::create([
            'institute_id' => $institute->id,
            'name' => 'Promotion Policy',
            'academic_year_id' => $year->id,
            'class_grade_id' => $classGrade->id,
            'status' => 'active',
        ]);

        $decision = PromotionDecision::create([
            'institute_id' => $institute->id,
            'policy_id' => $promotionPolicy->id,
            'result_id' => $finalResult->id,
            'academic_year_id' => $year->id,
            'status' => 'approved',
        ]);

        $item = PromotionDecisionItem::create([
            'decision_id' => $decision->id,
            'placement_id' => $placement->id,
            'student_id' => $student->id,
            'decision' => 'promoted',
            'target_class_grade_id' => $classGrade->id,
        ]);

        return compact('placement', 'scheme', 'policy', 'finalResult', 'finalResultStudent', 'promotionPolicy', 'decision', 'item');
    }

    // ------------------------------------------------------------- Batch create

    public function test_owner_creates_batch_with_academic_year_and_capacity(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $course = $this->course();
        $year = $this->year($institute, '2026');

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->post('/batches', [
                'name' => 'Computer Batch',
                'course_id' => $course->id,
                'academic_year_id' => $year->id,
                'shift' => 'evening',
                'start_date' => now()->toDateString(),
                'end_date' => now()->addMonths(4)->toDateString(),
                'seat_capacity' => '40',
                'status' => 'upcoming',
            ])
            ->assertRedirect(route('batches.index'));

        $this->assertDatabaseHas('batches', [
            'institute_id' => $institute->id,
            'name' => 'Computer Batch',
            'course_id' => $course->id,
            'academic_year_id' => $year->id,
            'seat_capacity' => 40,
            'seat_filled' => 0,
            'status' => 'upcoming',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $institute->id,
            'user_type' => 'institute_user',
            'user_id' => $owner->id,
            'action' => 'batch_created',
            'module' => 'batches',
        ]);
    }

    public function test_cross_tenant_course_rejected_on_create(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $other = $this->institute($country, 'Other Inst');
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $foreignCourse = Course::create([
            'institute_id' => $other->id,
            'course_code' => 'C'.mt_rand(100000, 999999),
            'name' => 'Private Course',
            'status' => 'active',
        ]);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->post('/batches', [
                'name' => 'Bad Course Batch',
                'course_id' => $foreignCourse->id,
                'shift' => 'day',
                'start_date' => now()->toDateString(),
                'seat_capacity' => '30',
                'status' => 'upcoming',
            ])
            ->assertSessionHasErrors('course_id');

        $this->assertSame(0, Batch::where('institute_id', $institute->id)->count());
    }

    public function test_foreign_academic_year_rejected_on_create(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $other = $this->institute($country, 'Other Inst');
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $course = $this->course();
        $foreignYear = $this->year($other, '2026');

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->post('/batches', [
                'name' => 'Bad Year Batch',
                'course_id' => $course->id,
                'academic_year_id' => $foreignYear->id,
                'shift' => 'day',
                'start_date' => now()->toDateString(),
                'seat_capacity' => '30',
                'status' => 'upcoming',
            ])
            ->assertSessionHasErrors('academic_year_id');

        $this->assertSame(0, Batch::where('institute_id', $institute->id)->count());
    }

    // ------------------------------------------------------------- Batch update

    public function test_owner_updates_batch_and_audits(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $course = $this->course();
        $batch = $this->batch($institute, ['course_id' => $course->id, 'status' => 'upcoming']);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->put('/batches/'.$batch->id, [
                'name' => 'Renamed Batch',
                'course_id' => $course->id,
                'shift' => 'day',
                'start_date' => now()->toDateString(),
                'seat_capacity' => '45',
                'status' => 'ongoing',
            ])
            ->assertRedirect(route('batches.index'));

        $batch->refresh();
        $this->assertSame('Renamed Batch', $batch->name);
        $this->assertSame(45, (int) $batch->seat_capacity);
        $this->assertSame('ongoing', $batch->status);
        $this->assertSame($institute->id, (int) $batch->institute_id);

        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $institute->id,
            'action' => 'batch_updated',
            'module' => 'batches',
        ]);
    }

    public function test_update_cannot_change_institute_or_branch(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $course = $this->course();
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $batch = $this->batch($institute, ['course_id' => $course->id, 'branch_id' => $branchA->id]);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->put('/batches/'.$batch->id, [
                'name' => 'Forge Attempt',
                'course_id' => $course->id,
                'shift' => 'day',
                'start_date' => now()->toDateString(),
                'seat_capacity' => '30',
                'status' => 'upcoming',
                'institute_id' => 999999,
                'branch_id' => $branchB->id,
            ])
            ->assertRedirect(route('batches.index'));

        $batch->refresh();
        $this->assertSame($institute->id, (int) $batch->institute_id);
        $this->assertSame($branchA->id, (int) $batch->branch_id);
    }

    // ------------------------------------------------------------- Status lifecycle

    public function test_status_lifecycle_transitions(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $batch = $this->batch($institute, ['status' => 'upcoming']);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->post('/batches/'.$batch->id.'/status', ['status' => 'ongoing'])
            ->assertRedirect();

        $this->assertSame('ongoing', $batch->refresh()->status);

        $this->actingAs($owner, 'institute_user')
            ->post('/batches/'.$batch->id.'/status', ['status' => 'completed'])
            ->assertRedirect();

        $this->assertSame('completed', $batch->refresh()->status);

        $this->actingAs($owner, 'institute_user')
            ->post('/batches/'.$batch->id.'/status', ['status' => 'archived'])
            ->assertRedirect();

        $this->assertSame('archived', $batch->refresh()->status);

        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $institute->id,
            'action' => 'batch_status_changed',
            'module' => 'batches',
        ]);
    }

    public function test_invalid_status_transition_rejected(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $batch = $this->batch($institute, ['status' => 'ongoing']);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->post('/batches/'.$batch->id.'/status', ['status' => 'completed'])
            ->assertRedirect();

        $this->assertSame('completed', $batch->refresh()->status);

        $this->actingAs($owner, 'institute_user')
            ->post('/batches/'.$batch->id.'/status', ['status' => 'ongoing'])
            ->assertSessionHasErrors('status');

        $this->assertSame('completed', $batch->refresh()->status);

        $cancelled = $this->batch($institute, ['status' => 'cancelled']);

        $this->actingAs($owner, 'institute_user')
            ->post('/batches/'.$cancelled->id.'/status', ['status' => 'ongoing'])
            ->assertSessionHasErrors('status');

        $this->assertSame('cancelled', $cancelled->refresh()->status);
    }

    public function test_update_cannot_skip_transition(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $course = $this->course();
        $batch = $this->batch($institute, ['course_id' => $course->id, 'status' => 'upcoming']);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->put('/batches/'.$batch->id, [
                'name' => 'Skip Attempt',
                'course_id' => $course->id,
                'shift' => 'day',
                'start_date' => now()->toDateString(),
                'seat_capacity' => '30',
                'status' => 'completed',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame('upcoming', $batch->refresh()->status);
    }

    // ------------------------------------------------------------- Capacity & rolls

    public function test_enrollment_capacity_is_enforced(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $batch = $this->batch($institute, ['seat_capacity' => 2]);
        $student1 = $this->student($institute, 'One');
        $student2 = $this->student($institute, 'Two');
        $student3 = $this->student($institute, 'Three');

        TenantContext::set($institute->id);

        foreach ([$student1, $student2] as $student) {
            $this->actingAs($owner, 'institute_user')
                ->postJson('/students/'.$student->id.'/enroll', [
                    'batch_id' => $batch->id,
                    'enrollment_date' => now()->toDateString(),
                ])
                ->assertOk()
                ->assertJsonPath('success', true);
        }

        $this->assertSame(2, (int) $batch->refresh()->seat_filled);

        $this->actingAs($owner, 'institute_user')
            ->postJson('/students/'.$student3->id.'/enroll', [
                'batch_id' => $batch->id,
                'enrollment_date' => now()->toDateString(),
            ])
            ->assertStatus(422);

        $this->assertSame(2, (int) $batch->refresh()->seat_filled);
        $this->assertSame(2, $batch->enrollments()->where('status', 'active')->count());
    }

    public function test_auto_roll_numbers_are_generated(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $batch = $this->batch($institute, ['seat_capacity' => 10]);
        $student1 = $this->student($institute, 'One');
        $student2 = $this->student($institute, 'Two');

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->postJson('/students/'.$student1->id.'/enroll', [
                'batch_id' => $batch->id,
                'enrollment_date' => now()->toDateString(),
            ])
            ->assertOk();

        $this->actingAs($owner, 'institute_user')
            ->postJson('/students/'.$student2->id.'/enroll', [
                'batch_id' => $batch->id,
                'enrollment_date' => now()->toDateString(),
            ])
            ->assertOk();

        $rolls = $batch->enrollments()
            ->orderBy('roll_number')
            ->pluck('roll_number')
            ->map(fn ($r) => (string) $r)
            ->all();

        $this->assertSame(['001', '002'], $rolls);
    }

    public function test_transfer_respects_target_capacity(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $source = $this->batch($institute, ['seat_capacity' => 2]);
        $target = $this->batch($institute, ['seat_capacity' => 1]);
        $student1 = $this->student($institute, 'One');
        $student2 = $this->student($institute, 'Two');

        $e1 = $this->enroll($student1, $source);
        $this->enroll($student2, $source);

        $batch = app(BatchLifecycleService::class);
        $batch->recount($source);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->post('/batches/'.$source->id.'/transfer', [
                'student_id' => $student1->id,
                'target_batch_id' => $target->id,
            ])
            ->assertRedirect(route('batches.show', $source));

        $this->assertSame('transferred', $e1->refresh()->status);
        $this->assertSame(1, (int) $target->refresh()->seat_filled);

        $this->actingAs($owner, 'institute_user')
            ->post('/batches/'.$source->id.'/transfer', [
                'student_id' => $student2->id,
                'target_batch_id' => $target->id,
            ])
            ->assertSessionHasErrors('target_batch_id');

        $this->assertSame(1, (int) $target->refresh()->seat_filled);
    }

    // ------------------------------------------------------------- Filters & UI

    public function test_index_filters_by_academic_year(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $year2026 = $this->year($institute, '2026');
        $year2027 = $this->year($institute, '2027');
        $batch26 = $this->batch($institute, ['name' => 'Alpha Batch', 'academic_year_id' => $year2026->id]);
        $batch27 = $this->batch($institute, ['name' => 'Beta Batch', 'academic_year_id' => $year2027->id]);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->get('/batches?academic_year_id='.$year2026->id)
            ->assertOk()
            ->assertSee('Alpha Batch')
            ->assertDontSee('Beta Batch');

        $this->assertSame($year2026->id, (int) $batch26->refresh()->academic_year_id);
        $this->assertSame($year2027->id, (int) $batch27->refresh()->academic_year_id);
    }

    public function test_index_filters_by_instructor(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $teacher = $this->user($institute, 'teacher', 'bl-teacher', $this->branch($institute));
        $assignedBatch = $this->batch($institute, ['name' => 'Instructed Batch']);
        $otherBatch = $this->batch($institute, ['name' => 'Lonely Batch']);
        $this->assign($teacher, $assignedBatch);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->get('/batches?instructor_id='.$teacher->id)
            ->assertOk()
            ->assertSee('Instructed Batch')
            ->assertDontSee('Lonely Batch');
    }

    public function test_show_lists_instructors_capacity_and_year(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $year = $this->year($institute, '2026');
        $teacher1 = $this->user($institute, 'teacher', 'bl-t1', $this->branch($institute));
        $teacher2 = $this->user($institute, 'teacher', 'bl-t2', $this->branch($institute));
        $batch = $this->batch($institute, [
            'name' => 'Display Batch',
            'academic_year_id' => $year->id,
            'seat_capacity' => 20,
            'status' => 'ongoing',
        ]);
        $this->assign($teacher1, $batch, 'course_instructor');
        $this->assign($teacher2, $batch, 'batch_coordinator');

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->get('/batches/'.$batch->id)
            ->assertOk()
            ->assertSee('Display Batch')
            ->assertSee($year->name)
            ->assertSee($teacher1->first_name)
            ->assertSee($teacher2->first_name)
            ->assertSee('colspan="9"', false);
    }

    public function test_index_paginates(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');

        for ($i = 0; $i < 25; $i++) {
            $this->batch($institute);
        }

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->get('/batches?page=2')
            ->assertOk();
    }

    // ------------------------------------------------------------- Isolation

    public function test_tenant_isolation_for_batches(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $other = $this->institute($country, 'Other Inst');
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $foreignBatch = $this->batch($other);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->get('/batches/'.$foreignBatch->id)
            ->assertNotFound();

        $this->actingAs($owner, 'institute_user')
            ->post('/batches/'.$foreignBatch->id.'/status', ['status' => 'ongoing'])
            ->assertNotFound();
    }

    public function test_branch_manager_sees_only_own_branch_batches(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $manager = $this->user($institute, 'branch-manager', 'bl-manager', $branchA);
        $batchA = $this->batch($institute, ['name' => 'Branch A Batch', 'branch_id' => $branchA->id]);
        $batchB = $this->batch($institute, ['name' => 'Branch B Batch', 'branch_id' => $branchB->id]);

        TenantContext::set($institute->id);
        BranchContext::set($branchA->id);

        $this->actingAs($manager, 'institute_user')
            ->get('/batches')
            ->assertOk()
            ->assertSee('Branch A Batch')
            ->assertDontSee('Branch B Batch');

        $this->actingAs($manager, 'institute_user')
            ->get('/batches/'.$batchB->id)
            ->assertNotFound();

        BranchContext::clear();
    }

    // ------------------------------------------------------------- Permissions

    public function test_teacher_has_view_but_not_manage(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $teacher = $this->user($institute, 'teacher', 'bl-teacher', $this->branch($institute));
        $course = $this->course();

        TenantContext::set($institute->id);

        $this->actingAs($teacher, 'institute_user')->get('/batches')->assertOk();

        $this->actingAs($teacher, 'institute_user')
            ->post('/batches', [
                'name' => 'Nope',
                'course_id' => $course->id,
                'shift' => 'day',
                'start_date' => now()->toDateString(),
                'seat_capacity' => '10',
                'status' => 'upcoming',
            ])
            ->assertForbidden();
    }

    // ------------------------------------------------------------- Exited students

    public function test_exited_student_cannot_enroll(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);
        $group = $this->group($classGrade);
        $year = $this->year($institute, '2026');
        $batch = $this->batch($institute, ['academic_year_id' => $year->id]);
        $student = $this->student($institute, 'Exited');
        $this->placement($student, $year, $classGrade, $group, 'dropped');

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->postJson('/students/'.$student->id.'/enroll', [
                'batch_id' => $batch->id,
                'enrollment_date' => now()->toDateString(),
            ])
            ->assertStatus(422);

        $this->assertSame(0, $batch->enrollments()->count());
    }

    // ------------------------------------------------------------- Historical data

    public function test_historical_attendance_preserved_on_status_change(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $batch = $this->batch($institute, ['status' => 'ongoing']);
        $student = $this->student($institute);
        $this->enroll($student, $batch);

        Attendance::create([
            'institute_id' => $institute->id,
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'class_date' => now()->toDateString(),
            'status' => 'present',
            'marked_by' => $owner->id,
        ]);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->post('/batches/'.$batch->id.'/status', ['status' => 'completed'])
            ->assertRedirect();

        $this->assertDatabaseHas('attendance', [
            'institute_id' => $institute->id,
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'status' => 'present',
        ]);
    }

    public function test_historical_marks_preserved_on_batch_update(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $course = $this->course();
        $batch = $this->batch($institute, ['course_id' => $course->id]);
        $student = $this->student($institute);

        Result::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'total_marks' => 100,
            'obtained_marks' => 85,
            'percentage' => 85.0,
            'result_status' => 'pass',
        ]);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->put('/batches/'.$batch->id, [
                'name' => 'Renamed',
                'course_id' => $course->id,
                'shift' => 'day',
                'start_date' => now()->toDateString(),
                'seat_capacity' => '30',
                'status' => 'ongoing',
            ])
            ->assertRedirect(route('batches.index'));

        $this->assertDatabaseHas('results', [
            'institute_id' => $institute->id,
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'obtained_marks' => '85.00',
            'result_status' => 'pass',
        ]);
    }

    public function test_final_result_and_promotion_history_preserved(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);
        $group = $this->group($classGrade);
        $year = $this->year($institute, '2026');
        $student = $this->student($institute);
        $chain = $this->frozenResultChain($institute, $student, $year, $classGrade, $group);
        $batch = $this->batch($institute, ['academic_year_id' => $year->id]);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->put('/batches/'.$batch->id, [
                'name' => 'History Safe',
                'course_id' => $batch->course_id,
                'shift' => 'day',
                'start_date' => now()->toDateString(),
                'seat_capacity' => '30',
                'status' => 'ongoing',
            ])
            ->assertRedirect(route('batches.index'));

        $this->assertSame('locked', $chain['finalResult']->refresh()->status);
        $this->assertSame(3.5, (float) $chain['finalResultStudent']->refresh()->gpa);
        $this->assertSame('approved', $chain['decision']->refresh()->status);
        $this->assertSame('promoted', $chain['item']->refresh()->decision);
    }

    public function test_year_with_final_result_history_cannot_be_deleted(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $system = $this->system($country);
        $level = $this->level($system);
        $classGrade = $this->classGrade($level);
        $group = $this->group($classGrade);
        $year = $this->year($institute, '2026');
        $student = $this->student($institute);
        $this->frozenResultChain($institute, $student, $year, $classGrade, $group);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->delete(route('settings.academic.academic-years.destroy', $year))
            ->assertSessionHasErrors();

        $this->assertDatabaseHas('academic_years', ['id' => $year->id]);
    }

    // ------------------------------------------------------------- Academic session

    public function test_academic_year_lifecycle_is_audited_and_close_unsets_current(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->post(route('settings.academic.academic-years.store'), [
                'name' => 'Session 2026',
                'code' => '2026',
                'is_current' => true,
                'status' => true,
            ])
            ->assertRedirect();

        $year = AcademicYear::where('institute_id', $institute->id)->where('code', '2026')->firstOrFail();
        $this->assertSame(1, (int) $year->is_current);

        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $institute->id,
            'action' => 'academic_year_created',
            'module' => 'academic-sessions',
        ]);

        $this->actingAs($owner, 'institute_user')
            ->put(route('settings.academic.academic-years.update', $year), [
                'name' => 'Session 2026',
                'code' => '2026',
                'is_current' => true,
                'status' => false,
            ])
            ->assertRedirect();

        $this->assertSame(0, (int) $year->refresh()->is_current);

        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $institute->id,
            'action' => 'academic_year_updated',
            'module' => 'academic-sessions',
        ]);

        $this->actingAs($owner, 'institute_user')
            ->delete(route('settings.academic.academic-years.destroy', $year))
            ->assertRedirect();

        $this->assertDatabaseMissing('academic_years', ['id' => $year->id]);

        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $institute->id,
            'action' => 'academic_year_deleted',
            'module' => 'academic-sessions',
        ]);
    }

    public function test_audit_trail_for_enroll_transfer_remove(): void
    {
        $country = $this->country();
        $institute = $this->institute($country);
        $owner = $this->user($institute, 'institute-owner', 'bl-owner');
        $source = $this->batch($institute);
        $target = $this->batch($institute);
        $student = $this->student($institute);

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->postJson('/students/'.$student->id.'/enroll', [
                'batch_id' => $source->id,
                'enrollment_date' => now()->toDateString(),
            ])
            ->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $institute->id,
            'action' => 'student_enrolled',
            'module' => 'batches',
        ]);

        $this->actingAs($owner, 'institute_user')
            ->post('/batches/'.$source->id.'/transfer', [
                'student_id' => $student->id,
                'target_batch_id' => $target->id,
            ])
            ->assertRedirect(route('batches.show', $source));

        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $institute->id,
            'action' => 'student_transferred',
            'module' => 'batches',
        ]);

        $this->actingAs($owner, 'institute_user')
            ->delete('/batches/'.$target->id.'/students/'.$student->id)
            ->assertRedirect(route('batches.show', $target));

        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $institute->id,
            'action' => 'student_removed',
            'module' => 'batches',
        ]);
    }
}
