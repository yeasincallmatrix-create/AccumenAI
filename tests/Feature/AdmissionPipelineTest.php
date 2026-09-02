<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Country;
use App\Models\Course;
use App\Models\CrmLead;
use App\Models\CrmLeadSource;
use App\Models\CrmLeadStatus;
use App\Models\CrmNote;
use App\Models\Institute;
use App\Models\InstituteCourse;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\Invoice;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Education\FeeHeadService;
use App\Services\Education\FeeStructureService;
use App\Services\Education\StudentFinanceService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Step 38 — CRM → Education admission pipeline: lead conversion into an
 * application, existing-student reuse, duplicate prevention, tenant + branch
 * isolation, permission gating, and the end-to-end journey through enrollment
 * and Step 37 education billing.
 */
class AdmissionPipelineTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
    }

    // ------------------------------------------------------------ Fixtures

    protected function freshInstitute(string $name = 'Pipeline Institute'): Institute
    {
        $country = Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );

        $institute = Institute::create([
            'name' => $name.' '.mt_rand(1000, 9999),
            'slug' => 'pipeline-'.mt_rand(1000, 9999),
            'industry' => 'education',
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);

        InstituteSetting::withoutGlobalScopes()->create([
            'institute_id' => $institute->id,
            'ai_config' => [
                'enabled' => true,
                'features' => ['assistant'],
                'daily_limit' => 0,
                'monthly_limit' => 0,
            ],
        ]);

        return $institute;
    }

    protected function branch(Institute $institute, string $name): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    protected function userFor(Institute $institute, string $roleSlug, string $prefix, ?Branch $branch = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'first_name' => $prefix,
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    protected function ownerFor(Institute $institute, string $prefix): InstituteUser
    {
        return $this->userFor($institute, 'institute-owner', $prefix);
    }

    protected function courseFor(Institute $institute, string $name = 'Welding'): Course
    {
        $course = Course::create([
            'course_code' => 'C'.mt_rand(1000, 9999),
            'name' => $name,
        ]);

        InstituteCourse::create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
        ]);

        return $course;
    }

    protected function academicYear(Institute $institute): AcademicYear
    {
        return AcademicYear::firstOrCreate(
            ['institute_id' => $institute->id, 'code' => 'Y'.$institute->id],
            [
                'name' => 'Session '.$institute->id,
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'is_current' => true,
                'status' => true,
            ]
        );
    }

    protected function batch(Institute $institute, Branch $branch, Course $course, string $name = 'Batch A'): Batch
    {
        return Batch::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'name' => $name,
            'batch_code' => 'B'.mt_rand(1000, 9999),
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'seat_capacity' => 40,
            'status' => 'ongoing',
        ]);
    }

    protected function statusId(string $slug): int
    {
        return (int) CrmLeadStatus::where('slug', $slug)->value('id');
    }

    protected function sourceId(string $slug): int
    {
        return (int) CrmLeadSource::where('slug', $slug)->value('id');
    }

    protected function lead(Institute $institute, ?Branch $branch, string $name = 'New Lead', string $status = 'new', array $extra = []): CrmLead
    {
        return CrmLead::create(array_merge([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'status_id' => $this->statusId($status),
            'source_id' => $this->sourceId('walk_in'),
            'first_name' => $name,
            'last_name' => 'Prospect',
            'phone' => '017'.rand(10000000, 99999999),
            'email' => 'lead-'.uniqid().'@example.test',
            'created_by' => null,
        ], $extra));
    }

    protected function student(Institute $institute, Branch $branch, string $name = 'Existing', array $extra = []): Student
    {
        return Student::create(array_merge([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'student_id_number' => Student::nextStudentNumber($institute->id),
            'first_name' => $name,
            'last_name' => 'Student',
            'phone' => '019'.rand(10000000, 99999999),
            'status' => 'active',
            'admission_date' => '2026-01-01',
        ], $extra));
    }

    protected function convertPayload(Course $course, Branch $branch, array $overrides = []): array
    {
        return array_merge([
            'full_name' => 'Rahim Uddin',
            'gender' => 'male',
            'dob' => '2008-03-14',
            'phone' => '018'.rand(10000000, 99999999),
            'guardian_phone' => '016'.rand(10000000, 99999999),
            'email' => 'rahim-'.uniqid().'@example.test',
            'branch_id' => $branch->id,
            'applied_course_id' => $course->id,
            'applied_academic_year_id' => $this->academicYear($branch->institute)->id,
            'application_date' => '2026-02-10',
            'admission_source' => 'walk_in',
            'present_zip_code' => '7830',
        ], $overrides);
    }

    // ------------------------------------------------------------ Conversion

    public function test_converting_lead_creates_submitted_application_linked_to_lead(): void
    {
        $institute = $this->freshInstitute();
        $branch = $this->branch($institute, 'Downtown');
        $manager = $this->userFor($institute, 'branch-manager', 'mgr', $branch);
        $course = $this->courseFor($institute);
        $lead = $this->lead($institute, $branch, 'Rahim');

        $this->actingAs($manager, 'institute_user')
            ->post(route('admissions.pipeline.store', $lead), $this->convertPayload($course, $branch))
            ->assertRedirect();

        $student = Student::withoutGlobalScopes()
            ->where('institute_id', $institute->id)
            ->where('crm_lead_id', $lead->id)
            ->firstOrFail();

        $this->assertSame(Student::ADMISSION_STATUS_SUBMITTED, $student->admission_status);
        $this->assertSame(Student::STATUS_ACTIVE, $student->status);
        $this->assertSame('AP-'.str_pad((string) $student->id, 6, '0', STR_PAD_LEFT), $student->application_number);
        $this->assertSame((int) $lead->id, (int) $student->crm_lead_id);
        $this->assertSame($course->id, $student->applied_course_id);
        $this->assertSame('Rahim', $student->first_name);

        // The lead moves to the "interested" (qualified) stage, not won.
        $lead->refresh();
        $this->assertSame('qualified', CrmLeadStatus::where('id', $lead->status_id)->value('slug'));

        // A CRM note records the conversion.
        $this->assertDatabaseHas('crm_notes', [
            'institute_id' => $institute->id,
            'subject_type' => 'lead',
            'subject_id' => $lead->id,
        ]);

        // The admission audit trail records the conversion.
        $this->assertDatabaseHas('audit_logs', [
            'institute_id' => $institute->id,
            'module' => 'admission',
            'record_id' => $lead->id,
        ]);
    }

    public function test_converting_already_converted_lead_is_idempotent(): void
    {
        $institute = $this->freshInstitute();
        $branch = $this->branch($institute, 'Downtown');
        $manager = $this->userFor($institute, 'branch-manager', 'mgr', $branch);
        $course = $this->courseFor($institute);
        $lead = $this->lead($institute, $branch, 'Idem');

        $payload = $this->convertPayload($course, $branch);

        $this->actingAs($manager, 'institute_user')
            ->post(route('admissions.pipeline.store', $lead), $payload)
            ->assertRedirect();

        $first = Student::withoutGlobalScopes()->where('crm_lead_id', $lead->id)->firstOrFail();

        $this->actingAs($manager, 'institute_user')
            ->post(route('admissions.pipeline.store', $lead), $payload)
            ->assertRedirect();

        $this->assertSame(1, Student::withoutGlobalScopes()
            ->where('institute_id', $institute->id)
            ->where('crm_lead_id', $lead->id)
            ->count());

        $this->assertSame($first->id, Student::withoutGlobalScopes()->where('crm_lead_id', $lead->id)->first()->id);
    }

    public function test_conversion_blocked_when_student_with_same_phone_exists(): void
    {
        $institute = $this->freshInstitute();
        $branch = $this->branch($institute, 'Downtown');
        $manager = $this->userFor($institute, 'branch-manager', 'mgr', $branch);
        $course = $this->courseFor($institute);

        $existing = $this->student($institute, $branch, 'Existing');
        $lead = $this->lead($institute, $branch, 'Duplicate', 'new', ['phone' => $existing->phone]);

        $this->actingAs($manager, 'institute_user')
            ->post(route('admissions.pipeline.store', $lead), $this->convertPayload($course, $branch))
            ->assertSessionHasErrors('existing_student');

        $this->assertNull(Student::withoutGlobalScopes()
            ->where('crm_lead_id', $lead->id)
            ->first());
    }

    public function test_link_existing_student_reuses_record_without_duplicate(): void
    {
        $institute = $this->freshInstitute();
        $branch = $this->branch($institute, 'Downtown');
        $manager = $this->userFor($institute, 'branch-manager', 'mgr', $branch);
        $lead = $this->lead($institute, $branch, 'Reuse');
        $existing = $this->student($institute, $branch, 'Reuse');

        $before = Student::withoutGlobalScopes()->where('institute_id', $institute->id)->count();

        $this->actingAs($manager, 'institute_user')
            ->post(route('admissions.pipeline.link', $lead), ['student_id' => $existing->id])
            ->assertRedirect();

        $this->assertSame($before, Student::withoutGlobalScopes()->where('institute_id', $institute->id)->count());
        $this->assertSame((int) $lead->id, (int) $existing->refresh()->crm_lead_id);

        $lead->refresh();
        $this->assertSame('qualified', CrmLeadStatus::where('id', $lead->status_id)->value('slug'));
    }

    public function test_conversion_persists_preferred_batch_and_assigned_staff(): void
    {
        $institute = $this->freshInstitute();
        $branch = $this->branch($institute, 'Downtown');
        $owner = $this->ownerFor($institute, 'owner');
        $course = $this->courseFor($institute);
        $year = $this->academicYear($institute);
        $batch = $this->batch($institute, $branch, $course, 'Preferred Batch');
        $staff = $this->userFor($institute, 'receptionist', 'staff');

        $lead = $this->lead($institute, $branch, 'Pref');

        $this->actingAs($owner, 'institute_user')
            ->post(route('admissions.pipeline.store', $lead), $this->convertPayload($course, $branch, [
                'preferred_batch_id' => $batch->id,
                'admission_assigned_user_id' => $staff->id,
                'applied_academic_year_id' => $year->id,
            ]))
            ->assertRedirect();

        $student = Student::withoutGlobalScopes()->where('crm_lead_id', $lead->id)->firstOrFail();

        $this->assertSame($batch->id, $student->preferred_batch_id);
        $this->assertSame($staff->id, $student->admission_assigned_user_id);
        $this->assertSame($year->id, $student->applied_academic_year_id);
    }

    // ------------------------------------------------- CRM permission safety

    public function test_conversion_without_crm_permission_skips_crm_mutations(): void
    {
        $institute = $this->freshInstitute();
        $branch = $this->branch($institute, 'Downtown');
        $course = $this->courseFor($institute);

        $clerkRole = Role::create([
            'name' => 'Admission Clerk',
            'slug' => 'admission-clerk-'.uniqid(),
            'status' => 'active',
        ]);
        $clerkRole->permissions()->attach(
            Permission::whereIn('slug', ['students.view', 'students.manage'])->pluck('id')
        );

        $clerk = InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'role_id' => $clerkRole->id,
            'first_name' => 'Clerk',
            'last_name' => 'User',
            'email' => 'clerk-'.uniqid().'@example.test',
            'phone' => '017'.rand(10000000, 99999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        $lead = $this->lead($institute, $branch, 'No CRM');

        $this->actingAs($clerk, 'institute_user')
            ->post(route('admissions.pipeline.store', $lead), $this->convertPayload($course, $branch))
            ->assertRedirect();

        $student = Student::withoutGlobalScopes()->where('crm_lead_id', $lead->id)->firstOrFail();
        $this->assertNotNull($student);
        $this->assertSame(Student::ADMISSION_STATUS_SUBMITTED, $student->admission_status);

        $lead->refresh();
        $this->assertSame('new', CrmLeadStatus::where('id', $lead->status_id)->value('slug'));
        $this->assertSame(0, CrmNote::where('subject_type', 'lead')->where('subject_id', $lead->id)->count());
    }

    // ----------------------------------------------------- Isolation + access

    public function test_pipeline_board_is_tenant_isolated(): void
    {
        $instA = $this->freshInstitute('Tenant A');
        $instB = $this->freshInstitute('Tenant B');
        $ownerB = $this->ownerFor($instB, 'owner-b');
        $branchB = $this->branch($instB, 'B Branch');
        $leadB = $this->lead($instB, $branchB, 'Foreign');

        $this->actingAs($ownerB, 'institute_user')
            ->get(route('admissions.pipeline.convert', $leadB))
            ->assertOk();

        // A user from a different institute can never resolve a foreign lead.
        $instAOwner = $this->ownerFor($instA, 'owner-a');
        $this->actingAs($instAOwner, 'institute_user')
            ->get(route('admissions.pipeline.convert', $leadB))
            ->assertNotFound();
    }

    public function test_branch_manager_only_sees_and_converts_own_branch(): void
    {
        $institute = $this->freshInstitute();
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $managerA = $this->userFor($institute, 'branch-manager', 'brm-a', $branchA);
        $course = $this->courseFor($institute);

        $leadA = $this->lead($institute, $branchA, 'Own Branch');
        $leadB = $this->lead($institute, $branchB, 'Other Branch');

        $this->actingAs($managerA, 'institute_user')
            ->get(route('admissions.pipeline.convert', $leadA))
            ->assertOk();

        // Cross-branch lead is invisible to the branch-A manager → 404.
        $this->actingAs($managerA, 'institute_user')
            ->get(route('admissions.pipeline.convert', $leadB))
            ->assertNotFound();
    }

    public function test_teacher_can_view_board_but_not_convert(): void
    {
        $institute = $this->freshInstitute();
        $branch = $this->branch($institute, 'Downtown');
        $teacher = $this->userFor($institute, 'teacher', 'teacher', $branch);
        $lead = $this->lead($institute, $branch, 'View Only');

        $this->actingAs($teacher, 'institute_user')
            ->get(route('admissions.pipeline'))
            ->assertOk();

        $this->actingAs($teacher, 'institute_user')
            ->get(route('admissions.pipeline.convert', $lead))
            ->assertForbidden();

        $this->actingAs($teacher, 'institute_user')
            ->post(route('admissions.pipeline.link', $lead), ['student_id' => 1])
            ->assertForbidden();
    }

    // ---------------------------------------------------------------- Board

    public function test_pipeline_board_groups_leads_and_applications_by_stage(): void
    {
        $institute = $this->freshInstitute();
        $branch = $this->branch($institute, 'Downtown');
        $owner = $this->ownerFor($institute, 'owner');
        $course = $this->courseFor($institute);

        $cold = $this->lead($institute, $branch, 'Cold Lead', 'new');
        $warm = $this->lead($institute, $branch, 'Warm Lead', 'qualified');
        $won = $this->lead($institute, $branch, 'Won Lead', 'won');
        $lost = $this->lead($institute, $branch, 'Lost Lead', 'lost');

        // One lead converts → appears in the applicants column, not leads.
        $applicantLead = $this->lead($institute, $branch, 'Applicant Lead');
        $this->actingAs($owner, 'institute_user')
            ->post(route('admissions.pipeline.store', $applicantLead), $this->convertPayload($course, $branch))
            ->assertRedirect();

        $response = $this->actingAs($owner, 'institute_user')
            ->get(route('admissions.pipeline'))
            ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('Cold Lead', $html);
        $this->assertStringContainsString('Warm Lead', $html);
        $this->assertStringContainsString('Won Lead', $html);
        $this->assertStringContainsString('Lost Lead', $html);
        $this->assertStringContainsString('Applicant Lead', $html);
    }

    public function test_existing_student_search_returns_reusable_records(): void
    {
        $institute = $this->freshInstitute();
        $branch = $this->branch($institute, 'Downtown');
        $owner = $this->ownerFor($institute, 'owner');
        $existing = $this->student($institute, $branch, 'Searchable');

        $this->actingAs($owner, 'institute_user')
            ->getJson(route('admissions.pipeline.students', ['q' => 'Searchable']))
            ->assertOk()
            ->assertJsonFragment(['id' => $existing->id, 'full_name' => 'Searchable Student']);

        // Students already linked to a lead are not offered for reuse.
        $linked = $this->student($institute, $branch, 'Linked');
        $linked->forceFill(['crm_lead_id' => $this->lead($institute, $branch, 'LinkedLead')->id])->save();

        $this->actingAs($owner, 'institute_user')
            ->getJson(route('admissions.pipeline.students', ['q' => 'Linked']))
            ->assertOk()
            ->assertJsonMissing(['full_name' => 'Linked Student']);
    }

    // -------------------------------------------- End-to-end: enrollment + finance

    public function test_full_pipeline_lead_to_enrollment_and_billing(): void
    {
        $institute = $this->freshInstitute();
        $branch = $this->branch($institute, 'Downtown');
        $manager = $this->userFor($institute, 'branch-manager', 'mgr', $branch);
        $course = $this->courseFor($institute);
        $batch = $this->batch($institute, $branch, $course, 'Enroll Batch');

        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch->id);
        app(AccountingSetupService::class)->setSetting($institute->id, 'invoice_auto_post', true, $branch->id);

        $head = app(FeeHeadService::class)->create($institute->id, $branch->id, [
            'type' => 'course_tuition',
            'name' => 'Tuition',
            'description' => 'Test head',
        ], null);

        app(FeeStructureService::class)->create($institute->id, $branch->id, [
            'name' => 'Course Fee '.uniqid(),
            'course_id' => $course->id,
            'installments_count' => 1,
            'installments_interval_days' => 30,
            'status' => 'active',
            'items' => [['fee_head_id' => $head->id, 'amount' => 6000]],
        ], null);

        $lead = $this->lead($institute, $branch, 'Journey');

        // 1. Lead → application (submitted).
        $this->actingAs($manager, 'institute_user')
            ->post(route('admissions.pipeline.store', $lead), $this->convertPayload($course, $branch))
            ->assertRedirect();

        $student = Student::withoutGlobalScopes()->where('crm_lead_id', $lead->id)->firstOrFail();

        // 2. Review → approve (issues the registration number).
        foreach (['submitted', 'under_review', 'approved'] as $status) {
            $this->actingAs($manager, 'institute_user')
                ->post(route('admissions.transition', $student), ['status' => $status])
                ->assertRedirect();
        }

        $student->refresh();
        $this->assertSame(Student::ADMISSION_STATUS_APPROVED, $student->admission_status);
        $this->assertMatchesRegularExpression('/^\d{12}$/', $student->registration_number);

        // 3. Enroll → admission becomes enrolled; the lead converts to a contact.
        $this->actingAs($manager, 'institute_user')
            ->post(route('students.enroll', $student), [
                'batch_id' => $batch->id,
                'enrollment_date' => '2026-02-20',
                'fee_payable' => 6000,
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertSame(Student::ADMISSION_STATUS_ENROLLED, $student->admission_status);

        $lead->refresh();
        $this->assertSame('won', CrmLeadStatus::where('id', $lead->status_id)->value('slug'));
        $this->assertNotNull($lead->converted_contact_id);
        $this->assertSame((int) $student->crm_contact_id, (int) $lead->converted_contact_id);

        $enrollment = StudentEnrollment::where('student_id', $student->id)->firstOrFail();

        // 4. Generate the course invoice through Step 37 education finance.
        $invoice = app(StudentFinanceService::class)->generateInvoice($enrollment, null, [], null);

        $this->assertSame((int) $enrollment->id, (int) $invoice->enrollment_id);
        $this->assertSame('education', $invoice->invoice_meta['source'] ?? null);
        $this->assertSame(6000.0, (float) $invoice->total_amount);
        $this->assertSame(1, Invoice::withoutGlobalScopes()
            ->where('institute_id', $institute->id)
            ->where('student_id', $student->id)
            ->count());
    }

    public function test_pipeline_report_shows_funnel_counts(): void
    {
        $institute = $this->freshInstitute();
        $branch = $this->branch($institute, 'Downtown');
        $owner = $this->ownerFor($institute, 'owner');
        $course = $this->courseFor($institute);

        $this->lead($institute, $branch, 'Cold One', 'new');
        $this->lead($institute, $branch, 'Warm One', 'qualified');
        $applicantLead = $this->lead($institute, $branch, 'App One');

        $this->actingAs($owner, 'institute_user')
            ->post(route('admissions.pipeline.store', $applicantLead), $this->convertPayload($course, $branch))
            ->assertRedirect();

        $response = $this->actingAs($owner, 'institute_user')
            ->get(route('admissions.pipeline.report'))
            ->assertOk();

        $html = $response->getContent();
        $this->assertStringContainsString('Funnel', $html);
        $this->assertStringContainsString($course->name, $html);
    }
}
