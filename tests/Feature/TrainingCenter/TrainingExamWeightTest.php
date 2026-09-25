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

class TrainingExamWeightTest extends TestCase
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
            'name' => 'TC Exam Weight',
            'slug' => 'tc-exam-weight-'.uniqid(),
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

    public function test_store_saves_weight_percent(): void
    {
        $batch = $this->makeBatch('Weight Store Batch');

        $response = $this->post(route('training.exams.store'), [
            'title' => 'Weight Store Exam',
            'batch_id' => $batch->id,
            'full_marks' => 100,
            'pass_marks' => 40,
            'weight_percent' => 40.5,
        ]);

        $response->assertRedirect(route('training.exams.show', TrainingExam::where('title', 'Weight Store Exam')->firstOrFail()->id));

        $exam = TrainingExam::where('title', 'Weight Store Exam')->firstOrFail();
        $this->assertSame(40.5, (float) $exam->weight_percent);
    }

    public function test_store_rejects_weight_over_100(): void
    {
        $batch = $this->makeBatch('Weight Invalid Batch');

        $this->from(route('training.exams.create'))
            ->post(route('training.exams.store'), [
                'title' => 'Weight Invalid Exam',
                'batch_id' => $batch->id,
                'full_marks' => 100,
                'pass_marks' => 40,
                'weight_percent' => 150,
            ])
            ->assertSessionHasErrors('weight_percent');

        $this->assertNull(TrainingExam::where('title', 'Weight Invalid Exam')->first());
    }

    public function test_show_page_lists_batch_weights_and_hides_component_labels(): void
    {
        $batch = $this->makeBatch('Weight Show Batch');
        $first = $this->makeExam('Weight Show First', $batch, 30);
        $this->makeExam('Weight Show Second', $batch, 70);

        $response = $this->get(route('training.exams.show', $first->id));

        $response->assertOk();
        $response->assertSee('Weight Distribution');
        $response->assertSee('Weight Show First');
        $response->assertSee('Weight Show Second');
        $response->assertSee('30%');
        $response->assertSee('70%');
        $response->assertSee('100%');
        $response->assertSee('>this<', false);
        $response->assertSee('Weighted final result');
        $response->assertDontSee('Written %', false);
        $response->assertDontSee('Practical %', false);
        $response->assertDontSee('Viva %', false);
    }

    public function test_show_page_without_weights_says_plain_sum(): void
    {
        $batch = $this->makeBatch('Weight None Batch');
        $exam = $this->makeExam('Weight None Exam', $batch, null);

        $response = $this->get(route('training.exams.show', $exam->id));

        $response->assertOk();
        $response->assertSee('Weight Distribution');
        $response->assertSee('plain marks sum');
    }

    public function test_publish_exam_with_weights_uses_weighted_percentage(): void
    {
        $batch = $this->makeBatch('Weighted Publish Batch');
        $exam1 = $this->makeExam('Weighted Publish First', $batch, 30);
        $exam2 = $this->makeExam('Weighted Publish Second', $batch, 70);
        $student = $this->makeEnrolledStudent($batch->id);
        $subjectIds = $this->courseSubjectIds($exam1);

        // Subject rows → subject average 60% for exam 1.
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam1->id,
            'student_id' => $student->id,
            'subject_id' => $subjectIds[0],
            'marks_obtained' => 50,
            'result_status' => 'pass',
        ]);
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam1->id,
            'student_id' => $student->id,
            'subject_id' => $subjectIds[1],
            'marks_obtained' => 70,
            'result_status' => 'pass',
        ]);
        // Overall row → 80% for exam 2.
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam2->id,
            'student_id' => $student->id,
            'subject_id' => null,
            'marks_obtained' => 80,
            'result_status' => 'pass',
        ]);

        $this->from(route('training.exams.index'))
            ->post(route('training.exams.publish', $exam1))
            ->assertRedirect(route('training.exams.index'));

        $batchResult = TrainingBatchResult::where('batch_id', $batch->id)
            ->where('student_id', $student->id)
            ->first();
        $this->assertNotNull($batchResult);
        $this->assertNotNull($batchResult->published_at);
        // (60% × 30 + 80% × 70) / 100 = 74
        $this->assertEqualsWithDelta(74.0, (float) $batchResult->percentage, 0.01);
        $this->assertEqualsWithDelta(200.0, (float) $batchResult->total_marks, 0.01);
        $this->assertEqualsWithDelta(148.0, (float) $batchResult->obtained_marks, 0.01);
        $this->assertSame('pass', $batchResult->status);
    }

    public function test_publish_exam_without_weights_keeps_plain_sum(): void
    {
        $batch = $this->makeBatch('Plain Publish Batch');
        $exam1 = $this->makeExam('Plain Publish First', $batch, null);
        $exam2 = $this->makeExam('Plain Publish Second', $batch, null);
        $student = $this->makeEnrolledStudent($batch->id);
        $subjectIds = $this->courseSubjectIds($exam1);

        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam1->id,
            'student_id' => $student->id,
            'subject_id' => $subjectIds[0],
            'marks_obtained' => 50,
            'result_status' => 'pass',
        ]);
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam1->id,
            'student_id' => $student->id,
            'subject_id' => $subjectIds[1],
            'marks_obtained' => 70,
            'result_status' => 'pass',
        ]);
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam2->id,
            'student_id' => $student->id,
            'subject_id' => null,
            'marks_obtained' => 80,
            'result_status' => 'pass',
        ]);

        $this->from(route('training.exams.index'))
            ->post(route('training.exams.publish', $exam1))
            ->assertRedirect(route('training.exams.index'));

        $batchResult = TrainingBatchResult::where('batch_id', $batch->id)
            ->where('student_id', $student->id)
            ->first();
        $this->assertNotNull($batchResult);
        // Plain sum: (60 + 80) / 200 = 70
        $this->assertEqualsWithDelta(70.0, (float) $batchResult->percentage, 0.01);
        $this->assertEqualsWithDelta(200.0, (float) $batchResult->total_marks, 0.01);
        $this->assertEqualsWithDelta(140.0, (float) $batchResult->obtained_marks, 0.01);
        $this->assertSame('pass', $batchResult->status);
    }

    public function test_results_publish_with_weights_uses_weighted_percentage(): void
    {
        $batch = $this->makeBatch('Weighted Results Batch', 'completed');
        $exam1 = $this->makeExam('Weighted Results First', $batch, 30);
        $exam2 = $this->makeExam('Weighted Results Second', $batch, 70);
        $student = $this->makeEnrolledStudent($batch->id);

        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam1->id,
            'student_id' => $student->id,
            'subject_id' => null,
            'marks_obtained' => 60,
            'result_status' => 'pass',
        ]);
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam2->id,
            'student_id' => $student->id,
            'subject_id' => null,
            'marks_obtained' => 80,
            'result_status' => 'pass',
        ]);

        $this->from(route('training.results.index'))
            ->post(route('training.results.publish', $batch))
            ->assertRedirect(route('training.results.index'));

        $batchResult = TrainingBatchResult::where('batch_id', $batch->id)
            ->where('student_id', $student->id)
            ->first();
        $this->assertNotNull($batchResult);
        // (60% × 30 + 80% × 70) / 100 = 74
        $this->assertEqualsWithDelta(74.0, (float) $batchResult->percentage, 0.01);
        $this->assertEqualsWithDelta(148.0, (float) $batchResult->obtained_marks, 0.01);
        $this->assertSame('pass', $batchResult->status);
    }

    public function test_results_publish_without_weights_keeps_plain_sum(): void
    {
        $batch = $this->makeBatch('Plain Results Batch', 'completed');
        $exam1 = $this->makeExam('Plain Results First', $batch, null);
        $exam2 = $this->makeExam('Plain Results Second', $batch, null);
        $student = $this->makeEnrolledStudent($batch->id);

        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam1->id,
            'student_id' => $student->id,
            'subject_id' => null,
            'marks_obtained' => 60,
            'result_status' => 'pass',
        ]);
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam2->id,
            'student_id' => $student->id,
            'subject_id' => null,
            'marks_obtained' => 80,
            'result_status' => 'pass',
        ]);

        $this->from(route('training.results.index'))
            ->post(route('training.results.publish', $batch))
            ->assertRedirect(route('training.results.index'));

        $batchResult = TrainingBatchResult::where('batch_id', $batch->id)
            ->where('student_id', $student->id)
            ->first();
        $this->assertNotNull($batchResult);
        // Plain sum: (60 + 80) / 200 = 70
        $this->assertEqualsWithDelta(70.0, (float) $batchResult->percentage, 0.01);
        $this->assertEqualsWithDelta(140.0, (float) $batchResult->obtained_marks, 0.01);
        $this->assertSame('pass', $batchResult->status);
    }

    public function test_re_evaluate_with_weights_uses_weighted_percentage(): void
    {
        $batch = $this->makeBatch('Weighted Reeval Batch', 'completed');
        $exam1 = $this->makeExam('Weighted Reeval First', $batch, 30);
        $exam2 = $this->makeExam('Weighted Reeval Second', $batch, 70);
        $student = $this->makeEnrolledStudent($batch->id);

        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam1->id,
            'student_id' => $student->id,
            'subject_id' => null,
            'marks_obtained' => 60,
            'result_status' => 'pass',
        ]);
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam2->id,
            'student_id' => $student->id,
            'subject_id' => null,
            'marks_obtained' => 80,
            'result_status' => 'pass',
        ]);

        $this->post(route('training.results.re-evaluate', $batch))
            ->assertRedirect(route('training.results.index'));

        $batchResult = TrainingBatchResult::where('batch_id', $batch->id)
            ->where('student_id', $student->id)
            ->first();
        $this->assertNotNull($batchResult);
        $this->assertEqualsWithDelta(74.0, (float) $batchResult->percentage, 0.01);
        $this->assertEqualsWithDelta(148.0, (float) $batchResult->obtained_marks, 0.01);
        $this->assertSame('pass', $batchResult->status);
    }

    private function makeBatch(string $name, string $status = 'ongoing'): TrainingBatch
    {
        $category = TrainingCourseCategory::create([
            'institute_id' => $this->institute->id,
            'name' => $name.' Cat',
            'slug' => str($name.'-cat-'.uniqid())->slug()->toString(),
            'status' => 'active',
            'subject_type' => 'professional',
        ]);

        $course = TrainingCourse::create([
            'institute_id' => $this->institute->id,
            'category_id' => $category->id,
            'course_code' => 'WTC-'.strtoupper(substr(uniqid(), -6)),
            'name' => $name.' Course',
            'slug' => str($name.'-course-'.uniqid())->slug()->toString(),
            'duration_type' => 'months',
            'duration_value' => 3,
            'fee' => 5000,
            'status' => 'active',
        ]);

        $subjectA = \App\Models\Training\TrainingSubject::create([
            'institute_id' => $this->institute->id,
            'name' => $name.' Subj A',
            'slug' => str($name.'-subj-a-'.uniqid())->slug()->toString(),
            'status' => 'active',
            'subject_type' => 'professional',
        ]);
        $subjectB = \App\Models\Training\TrainingSubject::create([
            'institute_id' => $this->institute->id,
            'name' => $name.' Subj B',
            'slug' => str($name.'-subj-b-'.uniqid())->slug()->toString(),
            'status' => 'active',
            'subject_type' => 'professional',
        ]);
        $course->subjects()->attach([$subjectA->id, $subjectB->id]);

        return TrainingBatch::create([
            'institute_id' => $this->institute->id,
            'course_id' => $course->id,
            'name' => $name,
            'status' => $status,
        ]);
    }

    private function makeExam(string $title, TrainingBatch $batch, ?float $weight): TrainingExam
    {
        return TrainingExam::create([
            'institute_id' => $this->institute->id,
            'course_id' => $batch->course_id,
            'batch_id' => $batch->id,
            'title' => $title,
            'exam_date' => now()->addDay(),
            'full_marks' => 100,
            'pass_marks' => 40,
            'written_percent' => 0,
            'practical_percent' => 0,
            'viva_percent' => 0,
            'weight_percent' => $weight,
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
            'first_name' => 'Weight',
            'last_name' => 'Student'.uniqid(),
            'slug' => 'weight-student-'.uniqid(),
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
