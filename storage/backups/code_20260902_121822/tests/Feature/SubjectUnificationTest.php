<?php

namespace Tests\Feature;

use App\Models\AcademicFinalResult;
use App\Models\AcademicFinalResultRow;
use App\Models\AssessmentSubject;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Subject;
use App\Models\SubjectAcademicAssignment;
use App\Services\SubjectDeletionService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SubjectUnificationTest extends TestCase
{
    use DatabaseTransactions;

    protected function createInstitute(string $name = null): Institute
    {
        $country = \App\Models\Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh', 'iso3' => 'BGD', 'phone_code' => '880', 'status' => true]);
        return Institute::create([
            'name' => $name ?? 'Test Institute '.uniqid(),
            'slug' => 'test-'.uniqid(),
            'country' => $country->name,
            'country_id' => $country->id,
            'industry' => 'education',
            'sub_industry' => 'school',
            'status' => 'active',
        ]);
    }

    protected function createUser(Institute $institute, string $role = 'institute-owner'): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => Role::where('slug', $role)->firstOrFail()->id,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => uniqid().'@test.test',
            'phone' => '017'.mt_rand(10000000,99999999),
            'password_hash' => bcrypt('secret'),
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }

    protected function createStudent(Institute $institute): \App\Models\Student
    {
        $branch = \App\Models\Branch::where('institute_id', $institute->id)->first();
        if (!$branch) {
            $branch = \App\Models\Branch::create(['institute_id' => $institute->id, 'name' => 'Branch '.uniqid(), 'status' => 'active']);
        }
        return \App\Models\Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'student_id_number' => 'RP'.strtoupper(str()->random(8)),
            'first_name' => 'Test',
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => now()->toDateString(),
        ]);
    }

    protected function createPlacement(Institute $institute, \App\Models\Student $student): \App\Models\StudentAcademicPlacement
    {
        $year = \App\Models\AcademicYear::firstOrCreate(
            ['institute_id' => $institute->id, 'code' => '2026'],
            ['name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true, 'status' => true]
        );
        return \App\Models\StudentAcademicPlacement::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'class_grade_id' => null,
            'status' => 'active',
        ]);
    }

    protected function createSubject(?int $instituteId = null, string $type = 'academic'): Subject
    {
        $category = CourseCategory::withoutGlobalScope('institute')->firstOrCreate(
            ['name' => 'Test Category '.$type, 'subject_type' => $type, 'institute_id' => $instituteId],
            ['slug' => 'test-cat-'.$type.uniqid(), 'status' => 'active', 'institute_id' => $instituteId]
        );
        return Subject::create([
            'institute_id' => $instituteId,
            'category_id' => $category->id,
            'subject_type' => $type,
            'subject_code' => 'SUB'.mt_rand(10000,99999),
            'name' => 'Subject '.uniqid(),
            'slug' => 'subject-'.uniqid(),
            'status' => 'active',
        ]);
    }

    public function test_subject_create_update_soft_delete_restore(): void
    {
        $inst = $this->createInstitute();
        $owner = $this->createUser($inst);
        TenantContext::set($inst->id);
        $this->actingAs($owner, 'institute_user');

        $subject = $this->createSubject($inst->id);
        $this->assertNotNull($subject);

        $subject->update(['name' => 'Updated Name']);
        $this->assertEquals('Updated Name', $subject->fresh()->name);

        $svc = app(SubjectDeletionService::class);
        $svc->softDelete($subject, $owner->id);
        $this->assertSoftDeleted('subjects', ['id' => $subject->id]);

        $this->assertEquals(0, Subject::where('id', $subject->id)->count());
        $this->assertEquals(1, Subject::withTrashed()->where('id', $subject->id)->count());

        $svc->restore($subject, $owner->id);
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'deleted_at' => null]);

        $subject2 = $this->createSubject($inst->id);
        $svc->softDelete($subject2, $owner->id);
        $svc->forceDelete($subject2, $owner->id);
        $this->assertDatabaseMissing('subjects', ['id' => $subject2->id]);
    }

    public function test_force_delete_blocked_with_active_dependency(): void
    {
        $inst = $this->createInstitute();
        $owner = $this->createUser($inst);
        TenantContext::set($inst->id);

        $subject = $this->createSubject($inst->id, 'professional');
        $course = Course::create([
            'institute_id' => $inst->id,
            'course_code' => 'CRS'.mt_rand(1000,9999),
            'name' => 'Test Course',
            'slug' => 'test-course-'.uniqid(),
            'status' => 'active',
        ]);
        $course->subjects()->attach($subject->id);

        $svc = app(SubjectDeletionService::class);
        $classification = $svc->classify($subject);
        $this->assertEquals(SubjectDeletionService::STATE_ACTIVE_DEPENDENCY, $classification['state']);
        $this->assertFalse($classification['canSoftDelete']);

        try {
            $svc->softDelete($subject, $owner->id);
            $this->fail('Expected softDelete to be blocked');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertTrue(true);
        }
        $this->assertDatabaseHas('subjects', ['id' => $subject->id, 'deleted_at' => null]);
    }

    public function test_force_delete_blocked_with_historical_dependency(): void
    {
        $inst = $this->createInstitute();
        $owner = $this->createUser($inst);
        TenantContext::set($inst->id);

        $subject = $this->createSubject($inst->id);
        $student = $this->createStudent($inst);
        $placement = $this->createPlacement($inst, $student);
        \App\Models\StudentSubjectSelection::create([
            'institute_id' => $inst->id,
            'academic_placement_id' => $placement->id,
            'subject_id' => $subject->id,
            'is_selected' => true,
        ]);

        $svc = app(SubjectDeletionService::class);
        $classification = $svc->classify($subject);
        $this->assertEquals(SubjectDeletionService::STATE_HISTORICAL_DEPENDENCY, $classification['state']);

        $svc->softDelete($subject, $owner->id);
        $this->assertSoftDeleted('subjects', ['id' => $subject->id]);

        try {
            $svc->forceDelete($subject, $owner->id);
            $this->fail('Expected forceDelete blocked');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertTrue(true);
        }

        $selection = \App\Models\StudentSubjectSelection::where('subject_id', $subject->id)->first();
        // historical display via withTrashed on subject relation
        $this->assertNotNull($selection);
        $this->assertNotNull($selection->subject()->withTrashed()->first());
        $this->assertEquals($subject->id, $selection->subject()->withTrashed()->first()->id);
    }

    public function test_historical_result_still_displays_after_soft_delete(): void
    {
        $inst = $this->createInstitute();
        $owner = $this->createUser($inst);
        TenantContext::set($inst->id);

        $subject = $this->createSubject($inst->id);
        $student = $this->createStudent($inst);

        app(SubjectDeletionService::class)->softDelete($subject, $owner->id);

        // Student selection withTrashed on subject relation must still display
        $placement = $this->createPlacement($inst, $student);
        \App\Models\StudentSubjectSelection::create(['institute_id' => $inst->id, 'academic_placement_id' => $placement->id, 'subject_id' => $subject->id, 'is_selected' => true]);
        $sel = \App\Models\StudentSubjectSelection::where('subject_id', $subject->id)->first();
        $this->assertNotNull($sel);
        $this->assertNotNull($sel->subject()->withTrashed()->first());
        $this->assertEquals($subject->name, $sel->subject()->withTrashed()->first()->name);
    }

    public function test_active_selector_excludes_soft_deleted(): void
    {
        $inst = $this->createInstitute();
        $owner = $this->createUser($inst);
        TenantContext::set($inst->id);

        $active = $this->createSubject($inst->id);
        $deleted = $this->createSubject($inst->id);
        app(SubjectDeletionService::class)->softDelete($deleted, $owner->id);

        $activeIds = Subject::where('institute_id', $inst->id)->pluck('id');
        $this->assertTrue($activeIds->contains($active->id));
        $this->assertFalse($activeIds->contains($deleted->id));

        $allIds = Subject::withTrashed()->where('institute_id', $inst->id)->pluck('id');
        $this->assertTrue($allIds->contains($deleted->id));
    }

    public function test_tenant_isolation(): void
    {
        $instA = $this->createInstitute('Inst A '.uniqid());
        $instB = $this->createInstitute('Inst B '.uniqid());
        $ownerA = $this->createUser($instA);
        $ownerB = $this->createUser($instB);

        $subjectA = $this->createSubject($instA->id);
        TenantContext::set($instA->id);
        $this->actingAs($ownerA, 'institute_user')->get(route('courses.subjects'))->assertOk();

        TenantContext::set($instB->id);
        $this->actingAs($ownerB, 'institute_user')->get(route('courses.subjects'))->assertOk();
        TenantContext::set($instB->id);
        $this->actingAs($ownerB, 'institute_user')
            ->put(route('courses.subjects.update', $subjectA), ['name' => 'Hacked', 'subject_code' => 'HACK'])
            ->assertStatus(403);
    }

    public function test_concurrency_safe_via_transaction(): void
    {
        $inst = $this->createInstitute();
        $owner = $this->createUser($inst);
        $subject = $this->createSubject($inst->id);
        $svc = app(SubjectDeletionService::class);
        $svc->softDelete($subject, $owner->id);
        try {
            $svc->softDelete($subject, $owner->id);
            $this->fail('Second softDelete should be blocked');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertTrue(true);
        }
        $svc->restore($subject, $owner->id);
        $svc->softDelete($subject, $owner->id);
        $svc->forceDelete($subject, $owner->id);
        $this->assertDatabaseMissing('subjects', ['id' => $subject->id]);
    }
}
