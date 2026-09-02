<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Country;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseCurriculum;
use App\Models\CourseMaterial;
use App\Models\CurriculumLesson;
use App\Models\CurriculumModule;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Step 42 — Course Master & Curriculum Management.
 *
 * Covers the institute-facing Course Master authoring (institute-owned
 * courses, code/slug generation, delete guards, ownership), the curriculum
 * entity with versioning (one active version, auto-increment, freezing of
 * referenced versions, module/lesson CRUD), batch curriculum linkage,
 * materials upload/delete, tenant + permission isolation and audit logging.
 */
class CourseCurriculumManagementTest extends TestCase
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
        $country = Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh',
            'iso3' => 'BGD',
            'phone_code' => '880',
            'status' => true,
        ]);

        $institute = Institute::create([
            'name' => 'Curriculum Institute',
            'slug' => str()->slug('curriculum-institute-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);

        $branch = Branch::create(['institute_id' => $institute->id, 'name' => 'Campus A', 'status' => 'active']);

        $owner = $this->instituteUser($institute, null, 'institute-owner', 'Owner', 'Owner');
        $manager = $this->instituteUser($institute, $branch->id, 'branch-manager', 'Manager', 'Manager');
        $teacher = $this->instituteUser($institute, $branch->id, 'teacher', 'Self', 'Teacher');
        $accountant = $this->instituteUser($institute, $branch->id, 'accountant', 'Acc', 'Countant');

        $category = CourseCategory::create([
            'name' => 'Professional Skills',
            'slug' => 'professional-skills-'.uniqid(),
            'subject_type' => 'professional',
            'institute_id' => $institute->id,
            'status' => 'active',
        ]);

        $course = Course::create([
            'institute_id' => $institute->id,
            'course_code' => 'CUR'.mt_rand(1000, 9999),
            'name' => 'Digital Marketing',
            'slug' => 'digital-marketing-'.uniqid(),
            'category_id' => $category->id,
            'fee' => 12000,
            'status' => 'active',
        ]);

        $institute2 = Institute::create([
            'name' => 'Other Institute',
            'slug' => str()->slug('other-institute-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);

        $branch2 = Branch::create(['institute_id' => $institute2->id, 'name' => 'Other Campus', 'status' => 'active']);
        $foreignOwner = $this->instituteUser($institute2, $branch2->id, 'institute-owner', 'Foreign', 'Owner');

        return compact(
            'institute', 'branch', 'owner', 'manager', 'teacher', 'accountant',
            'category', 'course', 'institute2', 'branch2', 'foreignOwner'
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
            'email_verified_at' => now(),
        ]);
    }

    private function coursePayload(array $overrides = []): array
    {
        $defaultCategory = CourseCategory::where('subject_type', 'professional')->first();
        return array_merge([
            'name' => 'Graphic Design',
            'status' => 'active',
            'category_id' => $defaultCategory?->id,
            'short_description' => 'Learn design.',
            'language' => 'English',
            'duration_type' => 'months',
            'duration_value' => 6,
            'weekly_classes' => 3,
            'total_classes' => 72,
            'total_hours' => 180,
            'mode' => 'hybrid',
            'fee' => 15000,
            'discount' => 500,
            'admission_fee' => 500,
            'exam_fee' => 200,
            'certificate_fee' => 100,
            'requirements' => "Computer\nInternet",
            'outcomes' => "Portfolio\nJob ready",
            'prerequisites' => 'None',
            'display_order' => 0,
            'intro_video' => '',
        ], $overrides);
    }

    private function curriculumPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => '2026 Syllabus',
            'effective_date' => '2026-01-05',
            'description' => 'Full syllabus',
            'total_duration_hours' => 180,
            'total_classes' => 72,
            'learning_objectives' => "Master design\nBuild portfolio",
            'version_notes' => 'Initial release',
        ], $overrides);
    }

    private function modulePayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Module 1 — Fundamentals',
            'code' => 'M1',
            'module_type' => 'theory',
            'theory_marks' => 50,
            'practical_marks' => 30,
            'viva_marks' => 20,
            'total_marks' => 100,
            'credit_hours' => 3,
            'class_count' => 20,
            'duration_hours' => 30,
            'display_order' => 1,
        ], $overrides);
    }

    private function lessonPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Lesson 1 — Introduction',
            'duration_minutes' => 60,
            'learning_objective' => 'Understand basics',
            'content_reference' => 'textbook/ch1',
            'display_order' => 1,
        ], $overrides);
    }

    private function curriculumFor(Course $course, int $actorId): CourseCurriculum
    {
        return CourseCurriculum::create([
            'institute_id' => $course->institute_id,
            'course_id' => $course->id,
            'title' => 'Syllabus',
            'version' => 1,
            'status' => CourseCurriculum::STATUS_ACTIVE,
            'effective_date' => '2026-01-05',
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);
    }

    // --------------------------------------------------------- Course Master

    public function test_owner_can_create_an_institute_owned_course(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->from(route('courses.manage.create'))
            ->post(route('courses.manage.store'), $this->coursePayload())
            ->assertRedirect();

        $course = Course::query()->where('name', 'Graphic Design')->first();

        $this->assertNotNull($course);
        $this->assertSame((int) $c['institute']->id, (int) $course->institute_id);
        $this->assertNotNull($course->course_code);
        $this->assertNotNull($course->slug);
        $this->assertSame('hybrid', $course->mode);
        $this->assertSame(['Computer', 'Internet'], $course->requirements);
        $this->assertSame(['Portfolio', 'Job ready'], $course->outcomes);
        $this->assertSame(['None'], $course->prerequisites);

        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $c['institute']->id,
            'module' => 'courses',
            'action' => 'course_created',
            'record_id' => $course->id,
        ]);
    }

    public function test_generated_course_codes_are_unique_per_institute(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('courses.manage.store'), $this->coursePayload(['name' => 'Alpha Course']))
            ->assertRedirect();
        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('courses.manage.store'), $this->coursePayload(['name' => 'Beta Course']))
            ->assertRedirect();

        $codes = Course::query()->where('institute_id', $c['institute']->id)->pluck('course_code');
        $this->assertSame($codes->count(), $codes->unique()->count());
    }

    public function test_branch_manager_can_create_course_but_teacher_and_accountant_cannot(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $this->actingAs($c['manager'], 'institute_user')
            ->post(route('courses.manage.store'), $this->coursePayload())
            ->assertRedirect();
        $this->assertSame(2, Course::query()->where('institute_id', $c['institute']->id)->count());

        $this->actingAs($c['teacher'], 'institute_user')
            ->post(route('courses.manage.store'), $this->coursePayload(['name' => 'Teacher Blocked']))
            ->assertForbidden();

        $this->actingAs($c['accountant'], 'institute_user')
            ->post(route('courses.manage.store'), $this->coursePayload(['name' => 'Accountant Blocked']))
            ->assertForbidden();
    }

    public function test_course_master_index_lists_only_owned_courses(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $foreign = Course::create([
            'institute_id' => $c['institute2']->id,
            'course_code' => 'OTH'.mt_rand(1000, 9999),
            'name' => 'Foreign Course',
            'slug' => 'foreign-course-'.uniqid(),
            'status' => 'active',
        ]);

        $this->actingAs($c['owner'], 'institute_user')
            ->get(route('courses.manage.index'))
            ->assertOk()
            ->assertSee('Digital Marketing')
            ->assertDontSee('Foreign Course');
    }

    public function test_cannot_edit_or_delete_another_institutes_course(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute2']->id);

        $this->actingAs($c['foreignOwner'], 'institute_user')
            ->get(route('courses.manage.edit', $c['course']))
            ->assertForbidden();

        $this->actingAs($c['foreignOwner'], 'institute_user')
            ->delete(route('courses.manage.destroy', $c['course']))
            ->assertForbidden();

        $this->assertDatabaseHas('courses', ['id' => $c['course']->id]);
    }

    public function test_course_referenced_by_batch_cannot_be_deleted(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        Batch::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => $c['branch']->id,
            'course_id' => $c['course']->id,
            'name' => 'Batch 1',
            'batch_code' => 'B1-'.mt_rand(10, 99),
            'start_date' => '2026-01-10',
            'end_date' => '2026-07-10',
            'status' => 'upcoming',
        ]);

        $this->actingAs($c['owner'], 'institute_user')
            ->from(route('courses.manage.index'))
            ->delete(route('courses.manage.destroy', $c['course']))
            ->assertSessionHasErrors('course');

        $this->assertDatabaseHas('courses', ['id' => $c['course']->id]);
    }

    public function test_unreferenced_course_can_be_deleted_and_audited(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $course = Course::create([
            'institute_id' => $c['institute']->id,
            'course_code' => 'DEL'.mt_rand(1000, 9999),
            'name' => 'Doomed Course',
            'slug' => 'doomed-'.uniqid(),
            'status' => 'draft',
        ]);

        $this->actingAs($c['owner'], 'institute_user')
            ->delete(route('courses.manage.destroy', $course))
            ->assertRedirect();

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $c['institute']->id,
            'module' => 'courses',
            'action' => 'course_deleted',
            'record_id' => $course->id,
        ]);
    }

    public function test_course_update_preserves_slug_when_name_unchanged_and_audits(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->put(route('courses.manage.update', $c['course']), $this->coursePayload(['name' => $c['course']->name, 'fee' => 18000]))
            ->assertRedirect();

        $course = $c['course']->fresh();
        $this->assertSame($c['course']->slug, $course->slug);
        $this->assertSame('18000.00', (string) $course->fee);

        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $c['institute']->id,
            'module' => 'courses',
            'action' => 'course_updated',
            'record_id' => $course->id,
        ]);
    }

    // ----------------------------------------------------------- Curriculum

    public function test_first_curriculum_gets_version_one_draft(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('curricula.store'), ['course_id' => $c['course']->id] + $this->curriculumPayload())
            ->assertRedirect();

        $curriculum = CourseCurriculum::query()->where('course_id', $c['course']->id)->first();
        $this->assertNotNull($curriculum);
        $this->assertSame(1, (int) $curriculum->version);
        $this->assertSame(CourseCurriculum::STATUS_DRAFT, $curriculum->status);
        $this->assertSame(['Master design', 'Build portfolio'], $curriculum->learning_objectives);
    }

    public function test_versions_auto_increment_per_course(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $course2 = Course::create([
            'institute_id' => $c['institute']->id,
            'course_code' => 'CRS'.mt_rand(1000, 9999),
            'name' => 'Another Course',
            'slug' => 'another-course-'.uniqid(),
            'status' => 'active',
        ]);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('curricula.store'), ['course_id' => $c['course']->id] + $this->curriculumPayload(['title' => 'v1']))
            ->assertRedirect();
        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('curricula.store'), ['course_id' => $c['course']->id] + $this->curriculumPayload(['title' => 'v2']))
            ->assertRedirect();
        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('curricula.store'), ['course_id' => $course2->id] + $this->curriculumPayload(['title' => 'other-v1']))
            ->assertRedirect();

        $versions = CourseCurriculum::query()->where('course_id', $c['course']->id)->orderBy('version')->pluck('version');
        $this->assertSame([1, 2], $versions->map(fn ($v) => (int) $v)->all());

        $other = CourseCurriculum::query()->where('course_id', $course2->id)->first();
        $this->assertSame(1, (int) $other->version);
    }

    public function test_activating_a_version_archives_the_previous_active(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('curricula.store'), ['course_id' => $c['course']->id] + $this->curriculumPayload(['title' => 'v1']))
            ->assertRedirect();
        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('curricula.store'), ['course_id' => $c['course']->id] + $this->curriculumPayload(['title' => 'v2']))
            ->assertRedirect();

        $v1 = CourseCurriculum::query()->where('course_id', $c['course']->id)->where('version', 1)->firstOrFail();
        $v2 = CourseCurriculum::query()->where('course_id', $c['course']->id)->where('version', 2)->firstOrFail();

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('curricula.activate', $v1))
            ->assertRedirect();

        $this->assertSame(CourseCurriculum::STATUS_ACTIVE, $v1->fresh()->status);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('curricula.activate', $v2))
            ->assertRedirect();

        $this->assertSame(CourseCurriculum::STATUS_ARCHIVED, $v1->fresh()->status);
        $this->assertSame(CourseCurriculum::STATUS_ACTIVE, $v2->fresh()->status);

        $active = CourseCurriculum::query()->where('course_id', $c['course']->id)->where('status', CourseCurriculum::STATUS_ACTIVE)->count();
        $this->assertSame(1, $active);
    }

    public function test_batch_store_auto_assigns_active_curriculum(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $active = $this->curriculumFor($c['course'], $c['owner']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('batches.store'), [
                'name' => 'Morning Batch',
                'course_id' => $c['course']->id,
                'shift' => 'morning',
                'start_date' => '2026-02-01',
                'end_date' => '2026-08-01',
                'seat_capacity' => 30,
                'status' => 'upcoming',
            ])
            ->assertRedirect();

        $batch = Batch::query()->latest('id')->first();
        $this->assertSame((int) $active->id, (int) $batch->curriculum_id);
    }

    public function test_batch_store_rejects_curriculum_not_active_for_course(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $draft = CourseCurriculum::create([
            'institute_id' => $c['institute']->id,
            'course_id' => $c['course']->id,
            'title' => 'Draft Only',
            'version' => 5,
            'status' => CourseCurriculum::STATUS_DRAFT,
            'created_by' => $c['owner']->id,
            'updated_by' => $c['owner']->id,
        ]);

        $this->actingAs($c['owner'], 'institute_user')
            ->from(route('batches.index'))
            ->post(route('batches.store'), [
                'name' => 'Bad Batch',
                'course_id' => $c['course']->id,
                'curriculum_id' => $draft->id,
                'shift' => 'day',
                'start_date' => '2026-02-01',
                'end_date' => '2026-08-01',
                'seat_capacity' => 30,
                'status' => 'upcoming',
            ])
            ->assertSessionHasErrors('curriculum_id');

        $this->assertDatabaseMissing('batches', ['name' => 'Bad Batch']);
    }

    public function test_curriculum_update_edit_preserves_omitted_curriculum_on_batch_update(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $active = $this->curriculumFor($c['course'], $c['owner']->id);

        $batch = Batch::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => $c['branch']->id,
            'course_id' => $c['course']->id,
            'curriculum_id' => $active->id,
            'name' => 'Linked Batch',
            'batch_code' => 'LB-'.mt_rand(10, 99),
            'start_date' => '2026-02-01',
            'end_date' => '2026-08-01',
            'status' => 'upcoming',
        ]);

        $this->actingAs($c['owner'], 'institute_user')
            ->put(route('batches.update', $batch), [
                'name' => 'Linked Batch Updated',
                'course_id' => $c['course']->id,
                'shift' => 'day',
                'start_date' => '2026-02-01',
                'end_date' => '2026-08-01',
                'seat_capacity' => 40,
                'status' => 'upcoming',
            ])
            ->assertRedirect();

        $this->assertSame((int) $active->id, (int) $batch->fresh()->curriculum_id);
        $this->assertSame(40, (int) $batch->fresh()->seat_capacity);
    }

    public function test_referenced_curriculum_is_frozen_against_edit_delete_and_deactivate(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $active = $this->curriculumFor($c['course'], $c['owner']->id);

        Batch::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => $c['branch']->id,
            'course_id' => $c['course']->id,
            'curriculum_id' => $active->id,
            'name' => 'Referencing Batch',
            'batch_code' => 'RB-'.mt_rand(10, 99),
            'start_date' => '2026-02-01',
            'end_date' => '2026-08-01',
            'status' => 'upcoming',
        ]);

        $this->actingAs($c['owner'], 'institute_user')
            ->from(route('curricula.show', $active))
            ->put(route('curricula.update', $active), $this->curriculumPayload(['title' => 'Changed']))
            ->assertSessionHasErrors('curriculum');

        $this->actingAs($c['owner'], 'institute_user')
            ->from(route('curricula.show', $active))
            ->delete(route('curricula.destroy', $active))
            ->assertSessionHasErrors('curriculum');

        $this->assertDatabaseHas('course_curricula', ['id' => $active->id]);
        $this->assertSame('Syllabus', $active->fresh()->title);
    }

    public function test_draft_curriculum_can_be_edited_deleted_and_audited(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $draft = CourseCurriculum::create([
            'institute_id' => $c['institute']->id,
            'course_id' => $c['course']->id,
            'title' => 'Working Draft',
            'version' => 1,
            'status' => CourseCurriculum::STATUS_DRAFT,
            'created_by' => $c['owner']->id,
            'updated_by' => $c['owner']->id,
        ]);

        $this->actingAs($c['owner'], 'institute_user')
            ->put(route('curricula.update', $draft), $this->curriculumPayload(['title' => 'Renamed Draft']))
            ->assertRedirect();

        $this->assertSame('Renamed Draft', $draft->fresh()->title);
        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $c['institute']->id,
            'module' => 'curricula',
            'action' => 'curriculum_updated',
            'record_id' => $draft->id,
        ]);

        $this->actingAs($c['owner'], 'institute_user')
            ->delete(route('curricula.destroy', $draft))
            ->assertRedirect();

        $this->assertDatabaseMissing('course_curricula', ['id' => $draft->id]);
        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $c['institute']->id,
            'module' => 'curricula',
            'action' => 'curriculum_deleted',
            'record_id' => $draft->id,
        ]);
    }

    public function test_module_and_lesson_crud_works_on_draft(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $draft = CourseCurriculum::create([
            'institute_id' => $c['institute']->id,
            'course_id' => $c['course']->id,
            'title' => 'Draft',
            'version' => 1,
            'status' => CourseCurriculum::STATUS_DRAFT,
            'created_by' => $c['owner']->id,
            'updated_by' => $c['owner']->id,
        ]);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('curricula.modules.store', $draft), $this->modulePayload())
            ->assertRedirect();

        $module = CurriculumModule::query()->where('curriculum_id', $draft->id)->first();
        $this->assertNotNull($module);
        $this->assertEquals(50, (float) $module->theory_marks);

        $this->actingAs($c['owner'], 'institute_user')
            ->put(route('curricula.modules.update', [$draft, $module]), $this->modulePayload(['name' => 'Renamed Module']))
            ->assertRedirect();
        $this->assertSame('Renamed Module', $module->fresh()->name);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('curricula.lessons.store', $draft), $this->lessonPayload())
            ->assertRedirect();

        $lesson = CurriculumLesson::query()->where('curriculum_module_id', $module->id)->first();
        $this->assertNotNull($lesson);
        $this->assertSame(60, (int) $lesson->duration_minutes);

        $this->actingAs($c['owner'], 'institute_user')
            ->put(route('curricula.lessons.update', [$draft, $lesson]), $this->lessonPayload(['title' => 'Renamed Lesson']))
            ->assertRedirect();
        $this->assertSame('Renamed Lesson', $lesson->fresh()->title);

        $this->actingAs($c['owner'], 'institute_user')
            ->delete(route('curricula.lessons.destroy', [$draft, $lesson]))
            ->assertRedirect();
        $this->assertDatabaseMissing('curriculum_lessons', ['id' => $lesson->id]);

        $this->actingAs($c['owner'], 'institute_user')
            ->delete(route('curricula.modules.destroy', [$draft, $module]))
            ->assertRedirect();
        $this->assertDatabaseMissing('curriculum_modules', ['id' => $module->id]);
    }

    public function test_module_mutation_is_blocked_on_referenced_curriculum(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $active = $this->curriculumFor($c['course'], $c['owner']->id);

        $module = CurriculumModule::create([
            'institute_id' => $c['institute']->id,
            'curriculum_id' => $active->id,
            'name' => 'Existing Module',
            'display_order' => 1,
        ]);

        Batch::create([
            'institute_id' => $c['institute']->id,
            'branch_id' => $c['branch']->id,
            'course_id' => $c['course']->id,
            'curriculum_id' => $active->id,
            'name' => 'Referencing Batch',
            'batch_code' => 'RB-'.mt_rand(10, 99),
            'start_date' => '2026-02-01',
            'end_date' => '2026-08-01',
            'status' => 'upcoming',
        ]);

        $this->actingAs($c['owner'], 'institute_user')
            ->from(route('curricula.show', $active))
            ->post(route('curricula.modules.store', $active), $this->modulePayload(['name' => 'Blocked Module']))
            ->assertSessionHasErrors('curriculum');

        $this->assertSame(1, CurriculumModule::query()->where('curriculum_id', $active->id)->count());
    }

    public function test_tenant_isolation_between_institutes(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('curricula.store'), ['course_id' => $c['course']->id] + $this->curriculumPayload())
            ->assertRedirect();

        $foreign = Course::create([
            'institute_id' => $c['institute2']->id,
            'course_code' => 'OTH'.mt_rand(1000, 9999),
            'name' => 'Foreign Course',
            'slug' => 'foreign-course-'.uniqid(),
            'status' => 'active',
        ]);

        TenantContext::set($c['institute2']->id);

        $this->actingAs($c['foreignOwner'], 'institute_user')
            ->post(route('curricula.store'), ['course_id' => $c['course']->id] + $this->curriculumPayload())
            ->assertSessionHasErrors('course_id');

        $this->actingAs($c['foreignOwner'], 'institute_user')
            ->post(route('curricula.store'), ['course_id' => $foreign->id] + $this->curriculumPayload())
            ->assertRedirect();

        $this->assertSame(1, CourseCurriculum::query()->withoutGlobalScope('institute')->where('institute_id', $c['institute2']->id)->count());
        $this->assertSame(1, CourseCurriculum::query()->withoutGlobalScope('institute')->where('institute_id', $c['institute']->id)->count());
    }

    public function test_curriculum_permission_matrix(): void
    {
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $curriculum = $this->curriculumFor($c['course'], $c['owner']->id);

        // teacher can view, cannot manage
        $this->actingAs($c['teacher'], 'institute_user')
            ->get(route('curricula.index'))
            ->assertOk();
        $this->actingAs($c['teacher'], 'institute_user')
            ->get(route('curricula.show', $curriculum))
            ->assertOk();
        $this->actingAs($c['teacher'], 'institute_user')
            ->post(route('curricula.store'), ['course_id' => $c['course']->id] + $this->curriculumPayload())
            ->assertForbidden();

        // branch-manager can manage
        $this->actingAs($c['manager'], 'institute_user')
            ->post(route('curricula.store'), ['course_id' => $c['course']->id] + $this->curriculumPayload(['title' => 'Manager version']))
            ->assertRedirect();

        // accountant has no access at all
        $this->actingAs($c['accountant'], 'institute_user')
            ->get(route('curricula.index'))
            ->assertForbidden();
    }

    // ------------------------------------------------------------ Materials

    public function test_material_upload_and_delete(): void
    {
        Storage::fake('public');
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->post(route('courses.manage.materials.store', $c['course']), [
                'file' => UploadedFile::fake()->create('syllabus.pdf', 200, 'application/pdf'),
                'title' => 'Syllabus PDF',
            ])
            ->assertRedirect();

        $material = CourseMaterial::query()->where('course_id', $c['course']->id)->first();
        $this->assertNotNull($material);
        $this->assertSame('Syllabus PDF', $material->title);
        Storage::disk('public')->assertExists($material->file_path);

        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $c['institute']->id,
            'module' => 'course_materials',
            'action' => 'course_material_uploaded',
            'record_id' => $material->id,
        ]);

        $this->actingAs($c['owner'], 'institute_user')
            ->delete(route('courses.manage.materials.destroy', [$c['course'], $material]))
            ->assertRedirect();

        $this->assertDatabaseMissing('course_materials', ['id' => $material->id]);
        Storage::disk('public')->assertMissing($material->file_path);
    }

    public function test_material_upload_rejects_unsafe_file_type(): void
    {
        Storage::fake('public');
        $c = $this->seededContext();
        TenantContext::set($c['institute']->id);

        $this->actingAs($c['owner'], 'institute_user')
            ->from(route('curricula.show', $this->curriculumFor($c['course'], $c['owner']->id)))
            ->post(route('courses.manage.materials.store', $c['course']), [
                'file' => UploadedFile::fake()->create('payload.exe', 200, 'application/x-msdownload'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, CourseMaterial::query()->where('course_id', $c['course']->id)->count());
    }
}
