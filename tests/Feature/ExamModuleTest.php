<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Course;
use App\Models\Exam;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExamModuleTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    private Institute $institute;

    private InstituteUser $staff;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();

        $this->institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $this->staff = $this->makeStaff('institute-owner', 'exams-owner@example.test');
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

    public function test_guest_is_redirected_from_exams_index(): void
    {
        $this->get('/exams')->assertRedirect('/admin/login');
    }

    public function test_send_batch_to_exam_creates_scheduled_exam_and_redirects(): void
    {
        $batch = $this->createBatch('Exam Batch');
        $subject = Course::query()->firstOrFail()->subjects()->first();
        $this->assertNotNull($subject);

        $this->actingAs($this->staff, 'institute_user')
            ->post("/exams/{$batch->id}/send-to-exam", [
                'title' => 'Batch Exam',
                'subjects' => [$subject->id],
                'marks' => [
                    $subject->id => ['practical' => 80, 'viva' => 20],
                ],
            ])
            ->assertRedirect();

        $exam = Exam::withoutGlobalScopes()
            ->where('institute_id', $this->institute->id)
            ->where('batch_id', $batch->id)
            ->first();

        $this->assertNotNull($exam);
        $this->assertSame('scheduled', $exam->status);
        $this->assertSame((string) $batch->course_id, (string) $exam->course_id);
        $this->assertSame((string) $batch->id, (string) $exam->batch_id);
        $this->assertSame(100.0, (float) $exam->full_marks);
        $this->assertSame(40.0, (float) $exam->pass_marks);

        $subjectRow = $exam->subjects()->where('subject_id', $subject->id)->first();
        $this->assertNotNull($subjectRow);
        $this->assertSame(40.0, (float) $subjectRow->pass_marks);
    }

    public function test_exam_show_lists_enrolled_students(): void
    {
        $batch = $this->createBatch('Exam Roster');
        $student = Student::query()->where('institute_id', $this->institute->id)->first();
        $this->assertNotNull($student);

        StudentEnrollment::create([
            'institute_id' => $this->institute->id,
            'student_id' => $student->id,
            'course_id' => $batch->course_id,
            'batch_id' => $batch->id,
            'roll_number' => 'R-01',
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $exam = Exam::create([
            'institute_id' => $this->institute->id,
            'course_id' => $batch->course_id,
            'batch_id' => $batch->id,
            'title' => 'Roster Exam',
            'exam_date' => now()->toDateString(),
            'full_marks' => 100,
            'pass_marks' => 40,
            'written_percent' => 100,
            'practical_percent' => 0,
            'viva_percent' => 0,
            'status' => 'scheduled',
        ]);

        $this->actingAs($this->staff, 'institute_user')
            ->get('/exams/'.$exam->id)
            ->assertOk()
            ->assertSee('Roster Exam')
            ->assertSee($student->full_name);
    }

    public function test_save_marks_marks_pass_and_fail(): void
    {
        $batch = $this->createBatch('Marks Batch');
        $studentA = Student::query()->where('institute_id', $this->institute->id)->orderBy('id')->first();
        $studentB = Student::query()->where('institute_id', $this->institute->id)->orderByDesc('id')->first();
        $this->assertNotNull($studentA);
        $this->assertNotNull($studentB);

        foreach ([$studentA, $studentB] as $i => $student) {
            StudentEnrollment::create([
                'institute_id' => $this->institute->id,
                'student_id' => $student->id,
                'course_id' => $batch->course_id,
                'batch_id' => $batch->id,
                'roll_number' => 'R-0'.($i + 1),
                'enrollment_date' => now()->toDateString(),
                'status' => 'active',
            ]);
        }

        $exam = Exam::create([
            'institute_id' => $this->institute->id,
            'course_id' => $batch->course_id,
            'batch_id' => $batch->id,
            'title' => 'Marks Exam',
            'exam_date' => now()->toDateString(),
            'full_marks' => 100,
            'pass_marks' => 40,
            'written_percent' => 100,
            'practical_percent' => 0,
            'viva_percent' => 0,
            'status' => 'ongoing',
        ]);

        $this->actingAs($this->staff, 'institute_user')
            ->post('/exams/'.$exam->id.'/marks', [
                'marks' => [
                    $studentA->id => '80',
                    $studentB->id => '20',
                ],
            ])
            ->assertRedirect();

        $this->assertSame('pass', DB::table('exam_results')->where('exam_id', $exam->id)->where('student_id', $studentA->id)->value('result_status'));
        $this->assertSame('fail', DB::table('exam_results')->where('exam_id', $exam->id)->where('student_id', $studentB->id)->value('result_status'));
    }

    public function test_save_marks_uses_per_subject_pass_mark(): void
    {
        $batch = $this->createBatch('Per Subject Pass');
        $student = Student::query()->where('institute_id', $this->institute->id)->orderBy('id')->first();
        $this->assertNotNull($student);

        StudentEnrollment::create([
            'institute_id' => $this->institute->id,
            'student_id' => $student->id,
            'course_id' => $batch->course_id,
            'batch_id' => $batch->id,
            'roll_number' => 'R-01',
            'enrollment_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        $subjects = Course::query()->firstOrFail()->subjects()->orderBy('id')->limit(2)->get();
        $this->assertCount(2, $subjects);
        $subA = $subjects->get(0);
        $subB = $subjects->get(1);

        $exam = Exam::create([
            'institute_id' => $this->institute->id,
            'course_id' => $batch->course_id,
            'batch_id' => $batch->id,
            'title' => 'Per Subject Exam',
            'exam_date' => now()->toDateString(),
            'full_marks' => 100,
            'pass_marks' => 40,
            'written_percent' => 0,
            'practical_percent' => 100,
            'viva_percent' => 0,
            'status' => 'ongoing',
        ]);

        $exam->subjects()->create(['subject_id' => $subA->id, 'practical_marks' => 80, 'viva_marks' => 20, 'pass_marks' => 30]);
        $exam->subjects()->create(['subject_id' => $subB->id, 'practical_marks' => 80, 'viva_marks' => 20, 'pass_marks' => 20]);

        $this->actingAs($this->staff, 'institute_user')
            ->post('/exams/'.$exam->id.'/marks', [
                'practical' => [
                    $subA->id => [$student->id => '35'],
                    $subB->id => [$student->id => '35'],
                ],
                'viva' => [
                    $subA->id => [$student->id => '0'],
                    $subB->id => [$student->id => '0'],
                ],
                'pass_marks' => [
                    $subA->id => '30',
                    $subB->id => '50',
                ],
            ])
            ->assertRedirect();

        // Per-subject thresholds override the exam-wide 40: 35 >= 30 -> pass, 35 < 50 -> fail.
        $this->assertSame('pass', DB::table('exam_results')->where('exam_id', $exam->id)->where('subject_id', $subA->id)->value('result_status'));
        $this->assertSame('fail', DB::table('exam_results')->where('exam_id', $exam->id)->where('subject_id', $subB->id)->value('result_status'));

        // The submitted per-subject pass mark is persisted on exam_subjects.
        $this->assertSame(50.0, (float) DB::table('exam_subjects')->where('exam_id', $exam->id)->where('subject_id', $subB->id)->value('pass_marks'));
    }

    public function test_other_institute_batch_send_to_exam_is_404(): void
    {
        $other = Institute::where('name', 'Halumoni Computer training center')->firstOrFail();
        $foreign = DB::table('batches')->where('institute_id', $other->id)->first();
        $this->assertNotNull($foreign);

        $this->actingAs($this->staff, 'institute_user')
            ->post("/exams/{$foreign->id}/send-to-exam")
            ->assertNotFound();
    }

    private function createBatch(string $name): Batch
    {
        $count = (int) Batch::withoutGlobalScope('institute')
            ->where('institute_id', $this->institute->id)
            ->count();

        return Batch::create([
            'institute_id' => $this->institute->id,
            'course_id' => Course::query()->firstOrFail()->id,
            'name' => $name,
            'batch_code' => 'B'.str_pad((string) ($count + 1), 3, '0', STR_PAD_LEFT),
            'shift' => 'day',
            'start_date' => now()->toDateString(),
            'seat_capacity' => 30,
            'status' => 'ongoing',
        ]);
    }
}