<?php

namespace Tests\Feature\TrainingCenter;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Training\TrainingBatch;
use App\Models\Training\TrainingCourse;
use App\Models\Training\TrainingCourseCategory;
use App\Models\Training\TrainingEnrollment;
use App\Models\Training\TrainingExam;
use App\Models\Training\TrainingExamResult;
use App\Models\Training\TrainingStudent;
use App\Models\TrainingBatchResult;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Database\Seeders\TrainingCenterPermissionSeeder;
use Database\Seeders\TrainingCenterSubModuleSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TrainingExamPublishTest extends TestCase
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
            'name' => 'TC Exam Publish',
            'slug' => 'tc-exam-publish-'.uniqid(),
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

    public function test_exams_index_shows_publish_button_in_action_column(): void
    {
        $exam = $this->makeExam('Publish Btn Exam');

        $response = $this->get(route('training.exams.index'));

        $response->assertOk();
        $response->assertSee(route('training.exams.publish', $exam), false);
        $response->assertSee('Publish');
    }

    public function test_exams_index_shows_published_state_instead_of_publish(): void
    {
        $exam = $this->makeExam('Already Published Exam');
        $exam->update(['published_at' => now()]);

        $response = $this->get(route('training.exams.index'));

        $response->assertOk();
        $response->assertSee('Published');
        $response->assertDontSee(route('training.exams.publish', $exam), false);
    }

    public function test_publish_exam_creates_published_batch_results(): void
    {
        $exam = $this->makeExam('Marks Publish Exam');
        $student = $this->makeEnrolledStudent($exam->batch_id);
        $subjectIds = $this->courseSubjectIds($exam);

        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subjectIds[0],
            'marks_obtained' => 30,
            'result_status' => 'fail',
        ]);
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subjectIds[1],
            'marks_obtained' => 50,
            'result_status' => 'pass',
        ]);

        $response = $this->from(route('training.exams.index'))
            ->post(route('training.exams.publish', $exam));

        $response->assertRedirect(route('training.exams.index'));
        $response->assertSessionHas('status');

        $exam->refresh();
        $this->assertNotNull($exam->published_at);

        $batchResult = TrainingBatchResult::where('batch_id', $exam->batch_id)
            ->where('student_id', $student->id)
            ->first();
        $this->assertNotNull($batchResult);
        $this->assertNotNull($batchResult->published_at);
        // Avg (30 + 50) / 2 = 40 >= 40 pass marks → pass
        $this->assertSame('pass', $batchResult->status);
        $this->assertEqualsWithDelta(40.0, (float) $batchResult->obtained_marks, 0.01);
        $this->assertEqualsWithDelta(100.0, (float) $batchResult->total_marks, 0.01);
        $this->assertEqualsWithDelta(40.0, (float) $batchResult->percentage, 0.01);
    }

    public function test_publish_exam_marks_failed_student_as_fail(): void
    {
        $exam = $this->makeExam('Fail Publish Exam');
        $student = $this->makeEnrolledStudent($exam->batch_id);
        $subjectIds = $this->courseSubjectIds($exam);

        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subjectIds[0],
            'marks_obtained' => 20,
            'result_status' => 'fail',
        ]);

        $this->from(route('training.exams.index'))
            ->post(route('training.exams.publish', $exam))
            ->assertRedirect(route('training.exams.index'));

        $batchResult = TrainingBatchResult::where('batch_id', $exam->batch_id)
            ->where('student_id', $student->id)
            ->first();
        $this->assertNotNull($batchResult);
        $this->assertSame('fail', $batchResult->status);
        $this->assertNotNull($batchResult->published_at);
    }

    public function test_publish_exam_without_marks_shows_error(): void
    {
        $exam = $this->makeExam('No Marks Publish Exam');

        $response = $this->from(route('training.exams.index'))
            ->post(route('training.exams.publish', $exam));

        $response->assertRedirect(route('training.exams.index'));
        $response->assertSessionHas('error');

        $this->assertNull($exam->fresh()->published_at);
        $this->assertSame(0, TrainingBatchResult::where('batch_id', $exam->batch_id)->count());
    }

    private function makeExam(string $title): TrainingExam
    {
        $category = TrainingCourseCategory::create([
            'institute_id' => $this->institute->id,
            'name' => 'Publish Cat',
            'slug' => 'publish-cat-'.uniqid(),
            'status' => 'active',
            'subject_type' => 'professional',
        ]);

        $course = TrainingCourse::create([
            'institute_id' => $this->institute->id,
            'category_id' => $category->id,
            'course_code' => 'PBC-'.strtoupper(substr(uniqid(), -6)),
            'name' => $title.' Course',
            'slug' => str($title.'-'.uniqid())->slug()->toString(),
            'duration_type' => 'months',
            'duration_value' => 3,
            'fee' => 5000,
            'status' => 'active',
        ]);

        $batch = TrainingBatch::create([
            'institute_id' => $this->institute->id,
            'course_id' => $course->id,
            'name' => $title.' Batch',
            'status' => 'ongoing',
        ]);

        $subjectA = \App\Models\Training\TrainingSubject::create([
            'institute_id' => $this->institute->id,
            'name' => $title.' Subj A',
            'slug' => str($title.'-subj-a-'.uniqid())->slug()->toString(),
            'status' => 'active',
            'subject_type' => 'professional',
        ]);
        $subjectB = \App\Models\Training\TrainingSubject::create([
            'institute_id' => $this->institute->id,
            'name' => $title.' Subj B',
            'slug' => str($title.'-subj-b-'.uniqid())->slug()->toString(),
            'status' => 'active',
            'subject_type' => 'professional',
        ]);
        $course->subjects()->attach([$subjectA->id, $subjectB->id]);

        return TrainingExam::create([
            'institute_id' => $this->institute->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'title' => $title,
            'exam_date' => now()->addDay(),
            'full_marks' => 100,
            'pass_marks' => 40,
            'written_percent' => 0,
            'practical_percent' => 0,
            'viva_percent' => 0,
            'status' => 'scheduled',
            'created_by' => $this->owner->id,
        ]);
    }

    private function courseSubjectIds(TrainingExam $exam): array
    {
        return TrainingCourse::find($exam->course_id)
            ?->subjects()
            ->pluck('training_subjects.id')
            ->values()
            ->all() ?? [];
    }

    private function makeEnrolledStudent(int $batchId): TrainingStudent
    {
        $student = TrainingStudent::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Publish',
            'last_name' => 'Student'.uniqid(),
            'slug' => 'publish-student-'.uniqid(),
            'status' => 'active',
        ]);

        TrainingEnrollment::create([
            'institute_id' => $this->institute->id,
            'batch_id' => $batchId,
            'student_id' => $student->id,
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        return $student;
    }
}
