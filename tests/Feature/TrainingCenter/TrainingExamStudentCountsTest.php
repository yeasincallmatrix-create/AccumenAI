<?php

namespace Tests\Feature\TrainingCenter;

use App\Livewire\Training\ExamList;
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
use Livewire\Livewire;
use Tests\TestCase;

class TrainingExamStudentCountsTest extends TestCase
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
            'name' => 'TC Student Counts',
            'slug' => 'tc-student-counts-'.uniqid(),
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

    public function test_multiple_result_rows_count_as_one_student(): void
    {
        $exam = $this->makeExam('Multi Row Exam', 40);
        $student = $this->makeEnrolledStudent($exam->batch_id);
        $subjectIds = $this->courseSubjectIds($exam);

        // Overall row (legacy) + one row per subject — 3 rows for 1 student.
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => null,
            'marks_obtained' => 82,
            'result_status' => 'pass',
        ]);
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subjectIds[0],
            'marks_obtained' => 39,
            'result_status' => 'fail',
        ]);
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subjectIds[1],
            'marks_obtained' => 64,
            'result_status' => 'pass',
        ]);

        $summary = $exam->studentResultSummary();

        $this->assertSame(1, $summary['students']);
        // (39 + 64) / 2 = 51.5 >= 40 → pass (matches Overall marks view)
        $this->assertSame(1, $summary['pass']);
        $this->assertSame(0, $summary['fail']);
    }

    public function test_exam_list_shows_student_counts_not_row_counts(): void
    {
        $exam = $this->makeExam('List Count Exam', 40);
        $student = $this->makeEnrolledStudent($exam->batch_id);
        $subjectIds = $this->courseSubjectIds($exam);

        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => null,
            'marks_obtained' => 82,
            'result_status' => 'pass',
        ]);
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subjectIds[0],
            'marks_obtained' => 39,
            'result_status' => 'fail',
        ]);
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subjectIds[1],
            'marks_obtained' => 64,
            'result_status' => 'pass',
        ]);

        $component = Livewire::test(ExamList::class);

        $rows = $component->viewData('exams');
        $listed = $rows->firstWhere('id', $exam->id);
        $this->assertNotNull($listed);
        $this->assertSame(1, (int) $listed->students_count);
        $this->assertSame(1, (int) $listed->pass_count);
        $this->assertSame(0, (int) $listed->fail_count);
    }

    public function test_exam_show_page_uses_student_counts(): void
    {
        $exam = $this->makeExam('Show Count Exam', 40);
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
            'marks_obtained' => 30,
            'result_status' => 'fail',
        ]);

        $response = $this->get(route('training.exams.show', $exam->id));

        $response->assertOk();
        $response->assertSee('Students');
        // Average 30 < 40 → fail for the single student (not 2 fail rows)
        $summary = $exam->fresh()->studentResultSummary();
        $this->assertSame(1, $summary['students']);
        $this->assertSame(0, $summary['pass']);
        $this->assertSame(1, $summary['fail']);
    }

    public function test_exam_show_hides_legacy_overall_row_when_subject_rows_exist(): void
    {
        $exam = $this->makeExam('Show Subject Rows Exam', 40);
        $student = $this->makeEnrolledStudent($exam->batch_id);
        $subjectIds = $this->courseSubjectIds($exam);

        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => null,
            'marks_obtained' => 82,
            'result_status' => 'pass',
        ]);
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subjectIds[0],
            'marks_obtained' => 39,
            'result_status' => 'fail',
        ]);
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subjectIds[1],
            'marks_obtained' => 64,
            'result_status' => 'pass',
        ]);

        $response = $this->get(route('training.exams.show', $exam->id));

        $response->assertOk();
        $response->assertSee('Subject', false);
        $response->assertSee('Subj A');
        $response->assertSee('Subj B');
        $response->assertSee('39 / 100', false);
        $response->assertSee('64 / 100', false);
        $response->assertDontSee('82 / 100', false);
    }

    public function test_exam_show_renders_grade_column_from_default_bands(): void
    {
        $exam = $this->makeExam('Grade Column Exam', 40);
        $student = $this->makeEnrolledStudent($exam->batch_id);
        $subjectIds = $this->courseSubjectIds($exam);

        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subjectIds[0],
            'marks_obtained' => 85,
            'result_status' => 'pass',
        ]);
        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subjectIds[1],
            'marks_obtained' => 45,
            'result_status' => 'fail',
        ]);

        $response = $this->get(route('training.exams.show', $exam->id));

        $response->assertOk();
        $response->assertSee('>Grade</th>', false);
        // Default bands: 85% → A+, 45% → F
        $response->assertSee('>A+</span>', false);
        $response->assertSee('>F</span>', false);
    }

    public function test_exam_show_uses_saved_gpa_model_bands(): void
    {
        $settings = \App\Models\InstituteSetting::where('institute_id', $this->institute->id)->first()
            ?? \App\Models\InstituteSetting::create([
                'institute_id' => $this->institute->id,
                'training_config' => [],
            ]);
        $config = $settings->training_config ?? [];
        $config['gpa_model'] = [
            ['grade' => 'DIST', 'min_score' => 80, 'max_score' => 100],
            ['grade' => 'FAIL', 'min_score' => 0, 'max_score' => 79],
        ];
        $settings->update(['training_config' => $config]);

        $exam = $this->makeExam('Custom Bands Exam', 40);
        $student = $this->makeEnrolledStudent($exam->batch_id);
        $subjectIds = $this->courseSubjectIds($exam);

        TrainingExamResult::create([
            'institute_id' => $this->institute->id,
            'exam_id' => $exam->id,
            'student_id' => $student->id,
            'subject_id' => $subjectIds[0],
            'marks_obtained' => 75,
            'result_status' => 'pass',
        ]);

        $response = $this->get(route('training.exams.show', $exam->id));

        $response->assertOk();
        // 75% falls under custom FAIL band, not default C
        $response->assertSee('>FAIL</span>', false);
        $response->assertDontSee('>C</span>', false);
    }

    private function makeExam(string $title, float $passMarks): TrainingExam
    {
        $category = TrainingCourseCategory::create([
            'institute_id' => $this->institute->id,
            'name' => 'Counts Cat',
            'slug' => 'counts-cat-'.uniqid(),
            'status' => 'active',
            'subject_type' => 'professional',
        ]);

        $course = TrainingCourse::create([
            'institute_id' => $this->institute->id,
            'category_id' => $category->id,
            'course_code' => 'CTC-'.strtoupper(substr(uniqid(), -6)),
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
            'pass_marks' => $passMarks,
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
            'first_name' => 'Count',
            'last_name' => 'Student'.uniqid(),
            'slug' => 'count-student-'.uniqid(),
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
