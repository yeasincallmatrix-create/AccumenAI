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
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Database\Seeders\TrainingCenterPermissionSeeder;
use Database\Seeders\TrainingCenterSubModuleSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TrainingMarksComponentEntryTest extends TestCase
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
            'name' => 'TC Marks Entry',
            'slug' => 'tc-marks-entry-'.uniqid(),
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

    public function test_marks_entry_shows_component_fields_from_exam_weights(): void
    {
        $exam = $this->makeExam('Weighted Exam', [
            'written_percent' => 50,
            'practical_percent' => 30,
            'viva_percent' => 20,
        ]);
        $this->makeEnrolledStudent($exam->batch_id);

        $response = $this->get(route('training.marks.index', ['exam_id' => $exam->id]));

        $response->assertOk();
        $response->assertSee('Written');
        $response->assertSee('Practical');
        $response->assertSee('Viva');
        $response->assertSee('name="written[', false);
        $response->assertSee('name="practical[', false);
        $response->assertSee('name="viva[', false);
        $response->assertSee('total-display', false);
        $response->assertDontSee('name="marks[', false);
    }

    public function test_marks_entry_hides_zero_weight_components(): void
    {
        $exam = $this->makeExam('Written Only Exam', [
            'written_percent' => 100,
            'practical_percent' => 0,
            'viva_percent' => 0,
        ]);
        $this->makeEnrolledStudent($exam->batch_id);

        $response = $this->get(route('training.marks.index', ['exam_id' => $exam->id]));

        $response->assertOk();
        $response->assertSee('name="written[', false);
        $response->assertDontSee('name="practical[', false);
        $response->assertDontSee('name="viva[', false);
    }

    public function test_marks_entry_falls_back_to_single_field_when_no_weights(): void
    {
        $exam = $this->makeExam('Plain Exam', [
            'written_percent' => 0,
            'practical_percent' => 0,
            'viva_percent' => 0,
        ]);
        $this->makeEnrolledStudent($exam->batch_id);

        $response = $this->get(route('training.marks.index', ['exam_id' => $exam->id]));

        $response->assertOk();
        $response->assertSee('name="marks[', false);
        $response->assertDontSee('name="written[', false);
        $response->assertDontSee('name="practical[', false);
        $response->assertDontSee('name="viva[', false);
    }

    public function test_store_component_marks_sums_total_and_status(): void
    {
        $exam = $this->makeExam('Store Components', [
            'full_marks' => 100,
            'pass_marks' => 40,
            'written_percent' => 50,
            'practical_percent' => 30,
            'viva_percent' => 20,
        ]);
        $student = $this->makeEnrolledStudent($exam->batch_id);

        $response = $this->post(route('training.marks.store'), [
            'exam_id' => $exam->id,
            'written' => [$student->id => 80],
            'practical' => [$student->id => 90],
            'viva' => [$student->id => 100],
        ]);

        $response->assertRedirect(route('training.marks.index', ['exam_id' => $exam->id]));

        $result = TrainingExamResult::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        // Real component marks stored as entered; Total = subject individual sum.
        // 80 + 90 + 100 = 270; pass/fail vs pass_marks using that sum.
        $this->assertSame(270.0, (float) $result->marks_obtained);
        $this->assertSame(80.0, (float) $result->written_marks);
        $this->assertSame(90.0, (float) $result->practical_marks);
        $this->assertSame(100.0, (float) $result->viva_marks);
        $this->assertSame('pass', $result->result_status);
    }

    public function test_store_component_marks_passes_when_total_meets_pass(): void
    {
        $exam = $this->makeExam('Pass Components', [
            'full_marks' => 100,
            'pass_marks' => 40,
            'written_percent' => 50,
            'practical_percent' => 50,
            'viva_percent' => 0,
        ]);
        $student = $this->makeEnrolledStudent($exam->batch_id);

        $this->post(route('training.marks.store'), [
            'exam_id' => $exam->id,
            'written' => [$student->id => 30],
            'practical' => [$student->id => 20],
        ]);

        $result = TrainingExamResult::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        // Individual sum = 30 + 20 = 50 → pass (pass_marks = 40)
        $this->assertSame(50.0, (float) $result->marks_obtained);
        $this->assertSame('pass', $result->result_status);
    }

    public function test_store_clamps_component_marks_to_full_marks(): void
    {
        $exam = $this->makeExam('Clamp Components', [
            'full_marks' => 100,
            'pass_marks' => 40,
            'written_percent' => 50,
            'practical_percent' => 50,
            'viva_percent' => 0,
        ]);
        $student = $this->makeEnrolledStudent($exam->batch_id);

        $this->post(route('training.marks.store'), [
            'exam_id' => $exam->id,
            'written' => [$student->id => 999],
            'practical' => [$student->id => 999],
        ]);

        $result = TrainingExamResult::where('exam_id', $exam->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        // Real marks clamp to full_marks (100); Total = 100 + 100 = 200
        $this->assertSame(100.0, (float) $result->written_marks);
        $this->assertSame(100.0, (float) $result->practical_marks);
        $this->assertSame(200.0, (float) $result->marks_obtained);
    }

    public function test_overall_view_averages_subject_totals(): void
    {
        $exam = $this->makeExam('Overall Avg Exam', [
            'full_marks' => 100,
            'pass_marks' => 40,
            'written_percent' => 0,
            'practical_percent' => 80,
            'viva_percent' => 20,
        ]);
        $student = $this->makeEnrolledStudent($exam->batch_id);

        $subjects = $exam->course_id
            ? \App\Models\Training\TrainingCourse::find($exam->course_id)?->subjects()->get()
            : collect();
        if ($subjects === null || $subjects->count() < 2) {
            $this->markTestSkipped('Need at least 2 course subjects for overall average test.');
        }

        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subjects[0]->id,
            'marks_obtained' => 100,
            'result_status' => 'pass',
        ]);
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subjects[1]->id,
            'marks_obtained' => 80,
            'result_status' => 'pass',
        ]);

        $response = $this->get(route('training.marks.index', [
            'exam_id' => $exam->id,
            'subject_id' => '0',
        ]));

        $response->assertOk();
        // (100 + 80) / 2 = 90
        $response->assertSee('value="90"', false);
        $response->assertSee('Average');
        $response->assertSee('Pass');
    }

    public function test_existing_component_results_prefill_marks_page(): void
    {
        $exam = $this->makeExam('Prefill Exam', [
            'full_marks' => 100,
            'pass_marks' => 40,
            'written_percent' => 50,
            'practical_percent' => 30,
            'viva_percent' => 20,
        ]);
        $student = $this->makeEnrolledStudent($exam->batch_id);
        $firstSubjectId = $exam->course_id
            ? (int) (\App\Models\Training\TrainingCourse::find($exam->course_id)?->subjects()->value('training_subjects.id'))
            : null;
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $firstSubjectId,
            'marks_obtained' => 70,
            'written_marks' => 40,
            'practical_marks' => 20,
            'viva_marks' => 10,
            'result_status' => 'pass',
        ]);

        $response = $this->get(route('training.marks.index', ['exam_id' => $exam->id]));

        $response->assertOk();
        $response->assertSee('value="40"', false);
        $response->assertSee('value="20"', false);
        $response->assertSee('value="10"', false);
        $response->assertSee('Pass');
    }

    private function makeExam(string $title, array $overrides = []): TrainingExam
    {
        $category = TrainingCourseCategory::create([
            'institute_id' => $this->institute->id,
            'name' => 'Marks Cat',
            'slug' => 'marks-cat-'.uniqid(),
            'status' => 'active',
            'subject_type' => 'professional',
        ]);

        $course = TrainingCourse::create([
            'institute_id' => $this->institute->id,
            'category_id' => $category->id,
            'course_code' => 'MKC-'.strtoupper(substr(uniqid(), -6)),
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

        return TrainingExam::create(array_merge([
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
        ], $overrides));
    }

    private function makeEnrolledStudent(int $batchId): TrainingStudent
    {
        $student = TrainingStudent::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Marks',
            'last_name' => 'Student'.uniqid(),
            'slug' => 'marks-student-'.uniqid(),
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
