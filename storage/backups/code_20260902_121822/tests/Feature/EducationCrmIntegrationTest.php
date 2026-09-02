<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Country;
use App\Models\Course;
use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\CrmLeadStatus;
use App\Models\Institute;
use App\Models\InstituteSetting;
use App\Models\InstituteUser;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Student;
use App\Services\EducationCrmIntegrationService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Step 34 — Education ↔ AccumenAI Core (CRM): admission captures a lead,
 * enrollment converts it into a contact, and every link stays tenant-safe,
 * branch-safe, permission-gated, idempotent and non-destructive to existing
 * education workflows / historical students.
 */
class EducationCrmIntegrationTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
    }

    // ------------------------------------------------------------ Fixtures

    protected function freshInstitute(string $industry = 'education'): Institute
    {
        $country = Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );

        $institute = Institute::create([
            'name' => 'Edu CRM '.mt_rand(1000, 9999),
            'slug' => 'edu-crm-'.mt_rand(1000, 9999),
            'industry' => $industry,
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

    protected function student(Institute $institute, string $name = 'Rahim', ?Branch $branch = null): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'student_id_number' => (string) mt_rand(100000, 999999),
            'first_name' => $name,
            'last_name' => 'Student',
            'phone' => '01711'.rand(100000, 999999),
            'guardian_phone' => '01811'.rand(100000, 999999),
            'email' => strtolower($name).'-'.uniqid().'@example.com',
            'status' => 'active',
            'admission_date' => '2026-01-01',
        ]);
    }

    protected function batch(Institute $institute, string $code = 'B1', ?Branch $branch = null): Batch
    {
        return Batch::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'course_id' => Course::create([
                'course_code' => 'C'.mt_rand(1000, 9999),
                'name' => 'Course',
            ])->id,
            'name' => 'Batch '.$code,
            'batch_code' => $code.'-'.mt_rand(100, 999),
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'ongoing',
        ]);
    }

    protected function admissionPayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Rahim',
            'last_name' => 'Uddin',
            'phone' => '01712'.rand(100000, 999999),
            'guardian_phone' => '01812'.rand(100000, 999999),
            'admission_date' => '2026-02-01',
            'status' => 'active',
        ], $overrides);
    }

    protected function integration(): EducationCrmIntegrationService
    {
        return app(EducationCrmIntegrationService::class);
    }

    // ------------------------------------------------------- Admission flow

    public function test_admission_captures_crm_lead_and_links_student(): void
    {
        $institute = $this->freshInstitute();
        $branch = $this->branch($institute, 'Downtown');
        $receptionist = $this->userFor($institute, 'receptionist', 'recp', $branch);

        $this->actingAs($receptionist, 'institute_user')
            ->post(route('students.store'), $this->admissionPayload(['branch_id' => $branch->id]))
            ->assertRedirect();

        $student = Student::withoutGlobalScopes()->where('institute_id', $institute->id)->first();
        $this->assertNotNull($student);
        $this->assertNotNull($student->crm_lead_id);
        $this->assertNotNull($student->crm_contact_id);

        $lead = CrmLead::withoutGlobalScopes()->find($student->crm_lead_id);
        $this->assertSame($institute->id, $lead->institute_id);
        $this->assertSame($branch->id, $lead->branch_id);
        $this->assertSame('Rahim', $lead->first_name);

        $contact = CrmContact::withoutGlobalScopes()->find($student->crm_contact_id);
        $this->assertSame($institute->id, $contact->institute_id);
        $this->assertSame('Rahim', $contact->first_name);
        $this->assertTrue($contact->is_customer);
    }

    public function test_admission_skips_crm_when_actor_lacks_crm_create_permission(): void
    {
        $institute = $this->freshInstitute();

        $clerkRole = Role::create([
            'name' => 'Student Clerk',
            'slug' => 'student-clerk-'.uniqid(),
            'status' => 'active',
        ]);
        $clerkRole->permissions()->attach(
            Permission::whereIn('slug', ['students.view', 'students.manage'])->pluck('id')
        );

        $clerk = InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => null,
            'role_id' => $clerkRole->id,
            'first_name' => 'Clerk',
            'last_name' => 'User',
            'email' => 'clerk-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        $this->actingAs($clerk, 'institute_user')
            ->post(route('students.store'), $this->admissionPayload())
            ->assertRedirect();

        $student = Student::withoutGlobalScopes()->where('institute_id', $institute->id)->first();
        $this->assertNotNull($student);
        $this->assertNull($student->crm_lead_id);
        $this->assertNull($student->crm_contact_id);
        $this->assertSame(0, CrmLead::withoutGlobalScopes()->where('institute_id', $institute->id)->count());
        $this->assertSame(0, CrmContact::withoutGlobalScopes()->where('institute_id', $institute->id)->count());
    }

    // ----------------------------------------------------- Enrollment flow

    public function test_enrollment_converts_admission_lead_into_contact(): void
    {
        $institute = $this->freshInstitute();
        $branch = $this->branch($institute, 'Downtown');
        $manager = $this->userFor($institute, 'branch-manager', 'mgr', $branch);
        $student = $this->student($institute, 'Karim', $branch);
        $batch = $this->batch($institute, 'B1', $branch);

        TenantContext::set($institute->id);
        $this->integration()->captureAdmissionLead($student, $branch->id, (int) $manager->id);

        $this->actingAs($manager, 'institute_user')
            ->withoutExceptionHandling()
            ->post(route('students.enroll', $student), [
                'batch_id' => $batch->id,
                'enrollment_date' => '2026-02-10',
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertNotNull($student->crm_contact_id);

        $lead = CrmLead::withoutGlobalScopes()->find($student->crm_lead_id);
        $this->assertNotNull($lead->converted_contact_id);
        $this->assertSame((int) $lead->converted_contact_id, (int) $student->crm_contact_id);
        $this->assertSame('won', CrmLeadStatus::query()->where('id', $lead->status_id)->value('slug'));
        $this->assertNotNull($lead->converted_at);

        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $student->id,
            'batch_id' => $batch->id,
        ]);
    }

    public function test_enrolling_historical_student_without_lead_touches_nothing_in_crm(): void
    {
        $institute = $this->freshInstitute();
        $branch = $this->branch($institute, 'Main');
        $manager = $this->userFor($institute, 'branch-manager', 'hist', $branch);
        $student = $this->student($institute, 'Legacy', $branch);
        $batch = $this->batch($institute, 'B1', $branch);

        $this->actingAs($manager, 'institute_user')
            ->post(route('students.enroll', $student), [
                'batch_id' => $batch->id,
                'enrollment_date' => '2026-03-01',
            ])
            ->assertRedirect();

        $student->refresh();
        $this->assertNull($student->crm_lead_id);
        $this->assertNull($student->crm_contact_id);
        $this->assertSame(0, CrmLead::withoutGlobalScopes()->where('institute_id', $institute->id)->count());
        $this->assertSame(0, CrmContact::withoutGlobalScopes()->where('institute_id', $institute->id)->count());
        $this->assertDatabaseHas('student_enrollments', [
            'student_id' => $student->id,
            'batch_id' => $batch->id,
        ]);
    }

    // -------------------------------------------------- Isolation & safety

    public function test_crm_links_are_tenant_isolated(): void
    {
        $instA = $this->freshInstitute();
        $instB = $this->freshInstitute();
        $ownerA = $this->ownerFor($instA, 'iso-a');
        $ownerB = $this->ownerFor($instB, 'iso-b');
        $studentA = $this->student($instA, 'Tenant A');
        $studentB = $this->student($instB, 'Tenant B');

        TenantContext::set($instA->id);
        $this->integration()->captureAdmissionLead($studentA, null, (int) $ownerA->id);

        TenantContext::set($instB->id);
        $this->integration()->captureAdmissionLead($studentB, null, (int) $ownerB->id);

        $this->assertSame(1, CrmLead::withoutGlobalScopes()->where('institute_id', $instA->id)->count());
        $this->assertSame(1, CrmLead::withoutGlobalScopes()->where('institute_id', $instB->id)->count());

        $contact = $this->integration()->convertAdmissionLead($studentB, (int) $instB->id, (int) $ownerB->id);
        $this->assertNotNull($contact);
        $this->assertSame($instB->id, $contact->institute_id);
        $this->assertNull($studentA->fresh()->crm_contact_id);
    }

    public function test_crm_links_are_branch_isolated(): void
    {
        $institute = $this->freshInstitute();
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $managerA = $this->userFor($institute, 'branch-manager', 'brm-a', $branchA);
        $studentA = $this->student($institute, 'Branch A Kid', $branchA);

        TenantContext::set($institute->id);
        $this->integration()->captureAdmissionLead($studentA, $branchA->id, (int) $managerA->id);

        $this->assertSame(1, CrmLead::withoutGlobalScopes()
            ->where('institute_id', $institute->id)
            ->where('branch_id', $branchA->id)
            ->count());

        // A branch-A user must not be able to convert a lead they cannot see.
        $this->assertSame(0, CrmLead::withoutGlobalScopes()
            ->where('institute_id', $institute->id)
            ->where('branch_id', $branchB->id)
            ->count());
    }

    public function test_admission_lead_and_contact_are_idempotent(): void
    {
        $institute = $this->freshInstitute();
        $owner = $this->ownerFor($institute, 'idem');
        $student = $this->student($institute, 'Idem');

        TenantContext::set($institute->id);

        $lead1 = $this->integration()->captureAdmissionLead($student, null, (int) $owner->id);
        $lead2 = $this->integration()->captureAdmissionLead($student, null, (int) $owner->id);
        $contact1 = $this->integration()->ensureStudentCrmLink($student, null, (int) $owner->id);
        $contact2 = $this->integration()->ensureStudentCrmLink($student, null, (int) $owner->id);

        $this->assertSame($lead1->id, $lead2->id);
        $this->assertSame($contact1->id, $contact2->id);
        $this->assertSame(1, CrmLead::withoutGlobalScopes()->where('institute_id', $institute->id)->count());
        $this->assertSame(1, CrmContact::withoutGlobalScopes()->where('institute_id', $institute->id)->count());
    }

    public function test_guardian_phone_is_carried_onto_the_crm_contact(): void
    {
        $institute = $this->freshInstitute();
        $owner = $this->ownerFor($institute, 'guardian');
        $student = $this->student($institute, 'Gd');
        $student->update(['guardian_phone' => '01822'.rand(100000, 999999)]);

        TenantContext::set($institute->id);

        $contact = $this->integration()->ensureStudentCrmLink($student, null, (int) $owner->id);
        $this->assertSame($student->guardian_phone, $contact->phone_alt);
        $this->assertTrue($contact->is_customer);
    }
}
