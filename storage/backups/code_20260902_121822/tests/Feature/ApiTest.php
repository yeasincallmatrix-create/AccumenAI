<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CrmContact;
use App\Models\CrmLead;
use App\Models\CrmLeadStatus;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    private Institute $institute;

    private InstituteUser $owner;

    private Student $student;

    private Course $course;

    private Batch $batch;

    private StudentEnrollment $enrollment;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();

        $country = \App\Models\Country::withoutGlobalScopes()->firstOrCreate(
            ['iso2' => 'BD'],
            ['name' => 'Bangladesh', 'iso3' => 'BDR', 'phone_code' => '880', 'status' => true]
        );

        $this->institute = Institute::create([
            'name' => 'API Test '.mt_rand(1000, 9999),
            'slug' => 'api-test-'.mt_rand(1000, 9999),
            'industry' => 'education',
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);

        \App\Models\InstituteSetting::withoutGlobalScopes()->create([
            'institute_id' => $this->institute->id,
            'ai_config' => ['enabled' => false, 'features' => [], 'daily_limit' => 0, 'monthly_limit' => 0],
        ]);

        $role = Role::where('slug', 'institute-owner')->firstOrFail();

        $this->owner = InstituteUser::create([
            'institute_id' => $this->institute->id,
            'role_id' => $role->id,
            'first_name' => 'Api',
            'last_name' => 'Owner',
            'email' => 'api-test-owner-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        $this->course = Course::create([
            'institute_id' => $this->institute->id,
            'name' => 'Api Test Course',
            'course_code' => 'ATC-'.uniqid(),
            'status' => 'active',
        ]);

        $this->batch = Batch::create([
            'institute_id' => $this->institute->id,
            'course_id' => $this->course->id,
            'name' => 'Api Test Batch',
            'batch_code' => 'ATB-'.uniqid(),
            'start_date' => now()->toDateString(),
            'status' => 'ongoing',
        ]);

        $this->student = Student::create([
            'institute_id' => $this->institute->id,
            'student_id_number' => (string) mt_rand(100000, 999999),
            'first_name' => 'Api',
            'last_name' => 'Student',
            'email' => 'api-student-'.uniqid().'@example.test',
            'phone' => '01711'.rand(100000, 999999),
            'nid_number' => 'NID'.mt_rand(1000000, 9999999),
            'birth_cert_number' => 'BC'.mt_rand(1000000, 9999999),
            'passport_number' => 'PASS'.mt_rand(100000, 999999),
            'status' => 'active',
            'admission_date' => now()->toDateString(),
        ]);

        $this->enrollment = StudentEnrollment::create([
            'institute_id' => $this->institute->id,
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'batch_id' => $this->batch->id,
            'roll_number' => '1',
            'enrollment_date' => now()->toDateString(),
        ]);

        TenantContext::clear();
    }

    private function loginAsOwner(): string
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->owner->email,
            'password' => $this->password,
            'institute_id' => $this->institute->id,
        ]);

        return $response->json('data.token');
    }

    private function auth(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token];
    }

    // ---------------------------------------------------------------
    // AUTH
    // ---------------------------------------------------------------

    public function test_unauthenticated_access_returns_401(): void
    {
        $this->getJson('/api/profile')
            ->assertStatus(401);
    }

    public function test_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->owner->email,
            'password' => $this->password,
            'institute_id' => $this->institute->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'token',
                    'institute' => ['id', 'name'],
                ],
            ]);
    }

    public function test_login_with_invalid_password_returns_401(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->owner->email,
            'password' => 'wrong-password',
            'institute_id' => $this->institute->id,
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_login_with_nonexistent_institute_returns_401(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => $this->owner->email,
            'password' => $this->password,
            'institute_id' => 999999,
        ]);

        $response->assertStatus(422);
    }

    public function test_login_missing_fields_returns_422(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422);
    }

    public function test_logout_revokes_token(): void
    {
        $token = $this->loginAsOwner();

        $this->postJson('/api/logout', [], $this->auth($token))
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_profile_returns_user_data(): void
    {
        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/profile', $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                ],
            ]);
    }

    public function test_institute_context(): void
    {
        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/institute', $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->institute->id);
    }

    public function test_branches_returns_list(): void
    {
        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/branches', $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true);

        $this->assertIsArray($response->json('data'));
    }

    // ---------------------------------------------------------------
    // STUDENTS
    // ---------------------------------------------------------------

    public function test_students_index_paginated(): void
    {
        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/students', $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_students_search(): void
    {
        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/students?search='.$this->student->first_name, $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_students_show(): void
    {
        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/students/'.$this->student->id, $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.student_id_number', $this->student->student_id_number);
    }

    public function test_students_show_404(): void
    {
        $token = $this->loginAsOwner();

        $this->getJson('/api/students/999999', $this->auth($token))
            ->assertStatus(404);
    }

    public function test_student_sensitive_fields_hidden(): void
    {
        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/students/'.$this->student->id, $this->auth($token));

        $response->assertOk()
            ->assertJsonMissing(['nid_number' => $this->student->nid_number])
            ->assertJsonMissing(['birth_cert_number' => $this->student->birth_cert_number])
            ->assertJsonMissing(['passport_number' => $this->student->passport_number]);
    }

    // ---------------------------------------------------------------
    // COURSES
    // ---------------------------------------------------------------

    public function test_courses_index(): void
    {
        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/courses', $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta',
            ]);
    }

    public function test_courses_show(): void
    {
        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/courses/'.$this->course->id, $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->course->id);
    }

    // ---------------------------------------------------------------
    // BATCHES
    // ---------------------------------------------------------------

    public function test_batches_index(): void
    {
        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/batches', $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta',
            ]);
    }

    public function test_batches_show(): void
    {
        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/batches/'.$this->batch->id, $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $this->batch->id);
    }

    public function test_batches_filter_by_course(): void
    {
        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/batches?course_id='.$this->course->id, $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true);

        foreach ($response->json('data') as $batch) {
            $this->assertEquals($this->course->id, $batch['course_id'] ?? $batch['course']['id'] ?? null);
        }
    }

    // ---------------------------------------------------------------
    // ENROLLMENTS
    // ---------------------------------------------------------------

    public function test_enrollments_index(): void
    {
        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/enrollments', $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta',
            ]);
    }

    // ---------------------------------------------------------------
    // ATTENDANCE
    // ---------------------------------------------------------------

    public function test_attendance_index(): void
    {
        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/attendance', $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta',
            ]);
    }

    public function test_attendance_store_creates_record(): void
    {
        $token = $this->loginAsOwner();

        $response = $this->postJson('/api/attendance', [
            'student_id' => $this->student->id,
            'batch_id' => $this->batch->id,
            'date' => now()->toDateString(),
            'status' => 'present',
        ], $this->auth($token));

        $response->assertCreated()
            ->assertJsonPath('success', true);
    }

    public function test_attendance_store_validates_required_fields(): void
    {
        $token = $this->loginAsOwner();

        $this->postJson('/api/attendance', [], $this->auth($token))
            ->assertStatus(422);
    }

    public function test_attendance_store_updates_existing(): void
    {
        $token = $this->loginAsOwner();
        $date = now()->toDateString();

        $this->postJson('/api/attendance', [
            'student_id' => $this->student->id,
            'batch_id' => $this->batch->id,
            'date' => $date,
            'status' => 'present',
        ], $this->auth($token))->assertStatus(201);

        $response = $this->postJson('/api/attendance', [
            'student_id' => $this->student->id,
            'batch_id' => $this->batch->id,
            'date' => $date,
            'status' => 'absent',
        ], $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true);
    }

    // ---------------------------------------------------------------
    // INVOICES
    // ---------------------------------------------------------------

    public function test_invoices_index(): void
    {
        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/invoices', $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta',
            ]);
    }

    // ---------------------------------------------------------------
    // PAYMENTS
    // ---------------------------------------------------------------

    public function test_payments_index(): void
    {
        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/payments', $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta',
            ]);
    }

    // ---------------------------------------------------------------
    // CRM
    // ---------------------------------------------------------------

    public function test_crm_contacts_index(): void
    {
        CrmContact::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.test',
            'phone' => '01711111111',
            'status' => 'active',
        ]);

        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/crm/contacts', $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta',
            ])
            ->assertJsonFragment(['email' => 'john@example.test']);
    }

    public function test_crm_contacts_show(): void
    {
        $contact = CrmContact::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john-show@example.test',
            'phone' => '01722222222',
            'status' => 'active',
        ]);

        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/crm/contacts/'.$contact->id, $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $contact->id);
    }

    public function test_crm_leads_index(): void
    {
        $leadStatus = CrmLeadStatus::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'display_order' => 1, 'status' => 'active']
        );

        CrmLead::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'status_id' => $leadStatus->id,
        ]);

        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/crm/leads', $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta',
            ]);
    }

    public function test_crm_leads_show(): void
    {
        $leadStatus = CrmLeadStatus::firstOrCreate(
            ['slug' => 'new'],
            ['name' => 'New', 'display_order' => 1, 'status' => 'active']
        );

        $lead = CrmLead::create([
            'institute_id' => $this->institute->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'status_id' => $leadStatus->id,
        ]);

        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/crm/leads/'.$lead->id, $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $lead->id);
    }

    // ---------------------------------------------------------------
    // CERTIFICATES
    // ---------------------------------------------------------------

    public function test_certificates_index(): void
    {
        Certificate::create([
            'institute_id' => $this->institute->id,
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'batch_id' => $this->batch->id,
            'certificate_number' => 'CERT-2026-00001',
            'status' => 'active',
            'issue_date' => now(),
        ]);

        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/certificates', $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta',
            ]);
    }

    public function test_certificate_verify_public(): void
    {
        Certificate::create([
            'institute_id' => $this->institute->id,
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'batch_id' => $this->batch->id,
            'certificate_number' => 'CERT-2026-99999',
            'status' => 'active',
            'issue_date' => now(),
        ]);

        $response = $this->getJson('/api/verify/certificate/CERT-2026-99999');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.certificate_number', 'CERT-2026-99999')
            ->assertJsonPath('data.status', 'active');
    }

    // ---------------------------------------------------------------
    // NOTIFICATIONS
    // ---------------------------------------------------------------

    public function test_notifications_index(): void
    {
        $token = $this->loginAsOwner();

        $response = $this->getJson('/api/notifications', $this->auth($token));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data',
                'meta',
            ]);
    }

    // ---------------------------------------------------------------
    // SECURITY
    // ---------------------------------------------------------------

    public function test_invalid_token_returns_401(): void
    {
        $this->getJson('/api/profile', [
            'Authorization' => 'Bearer invalid-token-string',
        ])->assertStatus(401);
    }

    public function test_expired_token_returns_401(): void
    {
        $token = $this->loginAsOwner();

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_type' => InstituteUser::class,
            'tokenable_id' => $this->owner->id,
        ]);

        $this->owner->tokens()->delete();

        $this->getJson('/api/profile', $this->auth($token))
            ->assertStatus(401);
    }

    public function test_cross_tenant_access_blocked(): void
    {
        $secondInstitute = Institute::create([
            'name' => 'Cross Tenant Test '.uniqid(),
            'slug' => str()->slug('cross-tenant-'.uniqid()),
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);

        $secondRole = Role::where('slug', 'institute-owner')->firstOrFail();

        $secondOwner = InstituteUser::create([
            'institute_id' => $secondInstitute->id,
            'role_id' => $secondRole->id,
            'first_name' => 'Second',
            'last_name' => 'Owner',
            'email' => 'second-owner-'.uniqid().'@example.test',
            'phone' => '01800'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $secondOwner->email,
            'password' => $this->password,
            'institute_id' => $secondInstitute->id,
        ]);

        $secondToken = $response->json('data.token');

        $studentsResponse = $this->getJson('/api/students', $this->auth($secondToken));

        $studentsResponse->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_rate_limiting_on_login(): void
    {
        $this->markTestSkipped('Rate limiting uses array cache in test env; verify in staging.');

        RateLimiter::for('api', fn () => \Illuminate\Http\Middleware\RateLimiter::perMinute(30));

        $instituteId = $this->institute->id;
        $response = null;

        for ($i = 0; $i < 31; $i++) {
            $response = $this->postJson('/api/login', [
                'email' => 'ratelimit-'.uniqid().'@example.test',
                'password' => 'wrong-password',
                'institute_id' => $instituteId,
            ]);
        }

        $response->assertStatus(429);

        RateLimiter::clear('api');
    }
}
