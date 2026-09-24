<?php

namespace Tests\Feature\TrainingCenter;

use App\Models\Institute;
use App\Models\Membership;
use App\Models\Role;
use App\Models\Training\TrainingBatch;
use App\Models\Training\TrainingCourse;
use App\Models\Training\TrainingCourseCategory;
use App\Models\Training\TrainingExam;
use App\Models\User;
use App\Services\ModuleAccessService;
use App\Support\Workspace;
use Database\Seeders\TrainingCenterPermissionSeeder;
use Database\Seeders\TrainingCenterSubModuleSeeder;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TrainingExamCreateTest extends TestCase
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
            'name' => 'TC Exam Create',
            'slug' => 'tc-exam-create-'.uniqid(),
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

    public function test_exams_index_links_to_create_page(): void
    {
        $response = $this->get(route('training.exams.index'));

        $response->assertOk();
        $response->assertSee('id="createExamModal"', false);
        $response->assertSee('data-bs-target="#createExamModal"', false);
        $response->assertSee('Create Exam');
        $response->assertSee('id="gpaModelModal"', false);
        $response->assertSee('data-bs-target="#gpaModelModal"', false);
        $response->assertSee('Configure GPA Model');
        $response->assertSee('name="rows[0][grade]"', false);
        $response->assertSee('name="rows[0][min_score]"', false);
        $response->assertSee('name="rows[0][max_score]"', false);
        $response->assertSee('gpaAddRow', false);
        $response->assertSee('gpa-remove-row', false);
    }

    public function test_store_gpa_model_scales(): void
    {
        $response = $this->post(route('training.exams.gpa-model'), [
            'tab' => 'exams',
            'rows' => [
                ['grade' => 'A+', 'min_score' => 80, 'max_score' => 100],
                ['grade' => 'B', 'min_score' => 60, 'max_score' => 79],
                ['grade' => 'F', 'min_score' => 0, 'max_score' => 59],
            ],
        ]);

        $response->assertRedirect(route('training.exams.index', ['tab' => 'exams']));

        $settings = \App\Models\InstituteSetting::where('institute_id', $this->institute->id)->first();
        $this->assertNotNull($settings);
        $gpa = $settings->training_config['gpa_model'] ?? [];
        $this->assertCount(3, $gpa);
        $this->assertSame('A+', $gpa[0]['grade']);
        $this->assertSame(80.0, (float) $gpa[0]['min_score']);
        $this->assertSame(100.0, (float) $gpa[0]['max_score']);
        $this->assertSame('F', $gpa[2]['grade']);
    }

    public function test_store_gpa_model_requires_valid_ranges(): void
    {
        $response = $this->post(route('training.exams.gpa-model'), [
            'rows' => [
                ['grade' => '', 'min_score' => 120, 'max_score' => 100],
            ],
        ]);

        $response->assertSessionHasErrors([
            'rows.0.grade',
            'rows.0.max_score',
        ]);
    }

    public function test_exams_index_shows_saved_gpa_model_rows(): void
    {
        $settings = \App\Models\InstituteSetting::where('institute_id', $this->institute->id)->first();
        if (!$settings) {
            $settings = \App\Models\InstituteSetting::create([
                'institute_id' => $this->institute->id,
                'training_config' => [],
            ]);
        }
        $config = $settings->training_config ?? [];
        $config['gpa_model'] = [
            ['grade' => 'O', 'min_score' => 90, 'max_score' => 100],
        ];
        $settings->update(['training_config' => $config]);

        $response = $this->get(route('training.exams.index'));

        $response->assertOk();
        $response->assertSee('name="rows[0][grade]"', false);
        $response->assertSee('value="O"', false);
        $response->assertSee('value="90"', false);
        $response->assertSee('value="100"', false);
    }

    public function test_create_exam_opens_as_modal_on_results_tab(): void
    {
        $batch = $this->makeBatch('Popup Batch');

        $response = $this->get(route('training.exams.index', ['tab' => 'results']));

        $response->assertOk();
        $response->assertSee('id="createExamModal"', false);
        $response->assertSee('name="from_modal"', false);
        $response->assertSee('name="batch_id"', false);
        $response->assertSee($batch->name);
        $response->assertSee('data-bs-target="#createExamModal"', false);
    }

    public function test_store_from_modal_redirects_back_to_index(): void
    {
        $batch = $this->makeBatch('Modal Save Batch');

        $response = $this->post(route('training.exams.store'), [
            'title' => 'Popup Exam',
            'batch_id' => $batch->id,
            'full_marks' => 100,
            'pass_marks' => 40,
            'from_modal' => 1,
            'tab' => 'results',
        ]);

        $response->assertRedirect(route('training.exams.index', ['tab' => 'results']));
        $this->assertDatabaseHas('training_exams', [
            'title' => 'Popup Exam',
            'institute_id' => $this->institute->id,
        ]);
    }

    public function test_store_from_modal_validation_error_returns_to_index(): void
    {
        $batch = $this->makeBatch('Modal Error Batch');

        $response = $this->post(route('training.exams.store'), [
            'title' => '',
            'batch_id' => $batch->id,
            'full_marks' => 100,
            'pass_marks' => 40,
            'from_modal' => 1,
            'tab' => 'exams',
        ]);

        $response->assertSessionHasErrors(['title']);
        $response->assertRedirect();
    }

    public function test_create_exam_page_renders_with_batch_dropdown(): void
    {
        $batch = $this->makeBatch('Morning Batch');

        $response = $this->get(route('training.exams.create', ['batch_id' => $batch->id]));

        $response->assertOk();
        $response->assertSee('Create Exam');
        $response->assertSee('Exam Title');
        $response->assertSee('Batch');
        $response->assertSee($batch->name);
        $response->assertSee('selected', false);
    }

    public function test_create_exam_page_requires_active_batch(): void
    {
        $this->makeBatch('Gone Batch', 'archived');

        $response = $this->get(route('training.exams.create'));

        $response->assertOk();
        $response->assertSee('No active batches found');
    }

    public function test_store_exam_for_batch(): void
    {
        $batch = $this->makeBatch('Exam Ready Batch');

        $response = $this->post(route('training.exams.store'), [
            'title' => 'Midterm Assessment',
            'batch_id' => $batch->id,
            'exam_date' => '2026-10-15 10:00:00',
            'full_marks' => 100,
            'pass_marks' => 40,
            'written_percent' => 50,
            'practical_percent' => 30,
            'viva_percent' => 20,
            'status' => 'scheduled',
        ]);

        $exam = TrainingExam::where('title', 'Midterm Assessment')->first();
        $this->assertNotNull($exam);
        $response->assertRedirect(route('training.exams.show', $exam->id));

        $this->assertDatabaseHas('training_exams', [
            'id' => $exam->id,
            'institute_id' => $this->institute->id,
            'batch_id' => $batch->id,
            'course_id' => $batch->course_id,
            'title' => 'Midterm Assessment',
            'status' => 'scheduled',
        ]);
    }

    public function test_store_exam_requires_batch_and_title(): void
    {
        $response = $this->post(route('training.exams.store'), [
            'title' => '',
            'batch_id' => '',
        ]);

        $response->assertSessionHasErrors(['title', 'batch_id']);
    }

    public function test_store_exam_rejects_pass_marks_above_full_marks(): void
    {
        $batch = $this->makeBatch('Invalid Marks Batch');

        $response = $this->post(route('training.exams.store'), [
            'title' => 'Bad Marks Exam',
            'batch_id' => $batch->id,
            'full_marks' => 50,
            'pass_marks' => 80,
        ]);

        $response->assertSessionHasErrors(['pass_marks']);
    }

    public function test_store_exam_rejects_cross_tenant_batch(): void
    {
        $other = Institute::create([
            'name' => 'Other Tenant',
            'slug' => 'other-tenant-'.uniqid(),
            'industry' => 'training_center',
            'sub_industry' => 'training_institute',
            'status' => 'active',
        ]);

        $currentTenant = \App\Support\TenantContext::id();
        \App\Support\TenantContext::clear();

        $category = TrainingCourseCategory::create([
            'institute_id' => $other->id,
            'name' => 'Other',
            'slug' => 'other-'.uniqid(),
            'status' => 'active',
            'subject_type' => 'professional',
        ]);
        $course = TrainingCourse::create([
            'institute_id' => $other->id,
            'category_id' => $category->id,
            'course_code' => 'OT-01',
            'name' => 'Other Course',
            'slug' => 'other-course-'.uniqid(),
            'duration_type' => 'months',
            'duration_value' => 1,
            'fee' => 1000,
            'status' => 'active',
        ]);
        $foreignBatch = TrainingBatch::create([
            'institute_id' => $other->id,
            'course_id' => $course->id,
            'name' => 'Foreign Batch',
            'status' => 'upcoming',
        ]);

        \App\Support\TenantContext::set($currentTenant);

        $response = $this->post(route('training.exams.store'), [
            'title' => 'Cross Tenant Exam',
            'batch_id' => $foreignBatch->id,
        ]);

        $response->assertSessionHasErrors(['batch_id']);
        $this->assertDatabaseMissing('training_exams', [
            'title' => 'Cross Tenant Exam',
        ]);
    }

    private function makeBatch(string $name, string $status = 'upcoming'): TrainingBatch
    {
        $category = TrainingCourseCategory::create([
            'institute_id' => $this->institute->id,
            'name' => 'Exam Design',
            'slug' => 'exam-design-'.uniqid(),
            'status' => 'active',
            'subject_type' => 'professional',
        ]);

        $course = TrainingCourse::create([
            'institute_id' => $this->institute->id,
            'category_id' => $category->id,
            'course_code' => 'EXC-'.strtoupper(substr(uniqid(), -6)),
            'name' => $name.' Course',
            'slug' => str($name.'-'.uniqid())->slug()->toString(),
            'duration_type' => 'months',
            'duration_value' => 3,
            'fee' => 5000,
            'status' => 'active',
        ]);

        return TrainingBatch::create([
            'institute_id' => $this->institute->id,
            'course_id' => $course->id,
            'name' => $name,
            'status' => $status,
        ]);
    }
}
