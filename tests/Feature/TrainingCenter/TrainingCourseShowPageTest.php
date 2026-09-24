<?php

namespace Tests\Feature\TrainingCenter;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Training\TrainingCourse;
use App\Models\Training\TrainingCourseCategory;
use App\Models\Training\TrainingSubject;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Database\Seeders\TrainingCenterPermissionSeeder;
use Database\Seeders\TrainingCenterSubModuleSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TrainingCourseShowPageTest extends TestCase
{
    use DatabaseTransactions;

    private Institute $institute;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);

        (new TrainingCenterSubModuleSeeder)->run();
        (new TrainingCenterPermissionSeeder)->run();

        $this->institute = Institute::create([
            'name' => 'TC Course Show',
            'slug' => 'tc-course-show-'.uniqid(),
            'industry' => 'training_center',
            'sub_industry' => 'training_institute',
            'status' => 'active',
        ]);

        $this->owner = User::factory()->create([
            'account_type' => 'owner',
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $roleId = Role::where('slug', 'institute-owner')->whereNull('institute_id')->value('id')
            ?? Role::where('slug', 'staff')->whereNull('institute_id')->value('id');

        Membership::create([
            'user_id' => $this->owner->id,
            'institution_id' => $this->institute->id,
            'role_id' => $roleId,
            'status' => 'active',
        ]);

        app(ModuleAccessService::class)->enableModule($this->institute, 'training_center');

        $this->actingAs($this->owner, 'web');
        Workspace::set($this->institute->id);
    }

    public function test_course_index_links_to_show_page(): void
    {
        $course = $this->makeCourse('Video Editing');

        $response = $this->get(route('training.courses.index'));

        $response->assertOk();
        $response->assertSee('href="'.route('training.courses.show', $course).'"', false);
    }

    public function test_course_show_page_renders_dedicated_detail(): void
    {
        $course = $this->makeCourse('Video Editing Master');

        $response = $this->get(route('training.courses.show', $course));

        $response->assertOk();
        $response->assertSee('Video Editing Master');
        $response->assertSee($course->course_code, false);
        $response->assertSee('Course Details');
        $response->assertSee('Course Materials');
        $response->assertSee(route('training.courses.edit', $course), false);
        $response->assertSee('Add Subject');
        $response->assertSee('id="addSubjectModal"', false);
        $response->assertSee('data-bs-target="#addSubjectModal"', false);
        $response->assertSee('Attach Existing');
        $response->assertSee('Create New');
    }

    public function test_add_subjects_page_renders(): void
    {
        $course = $this->makeCourse('Video Editing Pro');

        $response = $this->get(route('training.courses.course-subjects.add', $course));

        $response->assertOk();
        $response->assertSee('Add Subjects');
        $response->assertSee('Attach Existing Subjects');
        $response->assertSee('Create New Subject');
        $response->assertSee($course->name);
    }

    public function test_attach_existing_subject_to_course(): void
    {
        $course = $this->makeCourse('Motion Graphics');
        $category = $course->category;
        $subject = TrainingSubject::create([
            'institute_id' => $this->institute->id,
            'category_id' => $category->id,
            'subject_type' => 'professional',
            'name' => 'After Effects',
            'slug' => 'after-effects-'.uniqid(),
            'subject_code' => 'AE-01',
            'status' => 'active',
        ]);

        $response = $this->post(route('training.courses.course-subjects.attach', $course), [
            'subjects' => [$subject->id],
        ]);

        $response->assertRedirect(route('training.courses.show', $course));
        $this->assertDatabaseHas('training_course_subjects', [
            'course_id' => $course->id,
            'subject_id' => $subject->id,
        ]);
    }

    public function test_create_and_attach_new_subject(): void
    {
        $course = $this->makeCourse('Color Grading');
        $category = $course->category;

        $response = $this->post(route('training.courses.course-subjects.create', $course), [
            'name' => 'DaVinci Resolve',
            'short_name' => 'DVR',
            'subject_code' => 'DVR-01',
            'category_id' => $category->id,
            'description' => 'Color grading fundamentals',
        ]);

        $response->assertRedirect(route('training.courses.show', $course));

        $subject = TrainingSubject::where('name', 'DaVinci Resolve')->firstOrFail();
        $this->assertSame($this->institute->id, (int) $subject->institute_id);
        $this->assertSame('professional', $subject->subject_type);
        $this->assertDatabaseHas('training_course_subjects', [
            'course_id' => $course->id,
            'subject_id' => $subject->id,
        ]);
    }

    public function test_detach_subject_from_course(): void
    {
        $course = $this->makeCourse('Audio Editing');
        $category = $course->category;
        $subject = TrainingSubject::create([
            'institute_id' => $this->institute->id,
            'category_id' => $category->id,
            'subject_type' => 'professional',
            'name' => 'Audacity',
            'slug' => 'audacity-'.uniqid(),
            'status' => 'active',
        ]);
        $course->subjects()->attach($subject->id);

        $response = $this->delete(route('training.courses.course-subjects.detach', [$course, $subject]));

        $response->assertRedirect(route('training.courses.show', $course));
        $this->assertDatabaseMissing('training_course_subjects', [
            'course_id' => $course->id,
            'subject_id' => $subject->id,
        ]);
    }

    private function makeCourse(string $name): TrainingCourse
    {
        $category = TrainingCourseCategory::create([
            'institute_id' => $this->institute->id,
            'name' => 'Design',
            'slug' => 'design-'.uniqid(),
            'status' => 'active',
            'subject_type' => 'professional',
        ]);

        return TrainingCourse::create([
            'institute_id' => $this->institute->id,
            'category_id' => $category->id,
            'course_code' => 'TCS-'.strtoupper(substr(uniqid(), -6)),
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'duration_type' => 'months',
            'duration_value' => 3,
            'fee' => 5000,
            'status' => 'active',
        ]);
    }
}
