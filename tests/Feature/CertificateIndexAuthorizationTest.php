<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * STEP 10 — certificate index authorization.
 *
 * The certificates.index route historically referenced a permission slug
 * (certificates.view) that did not exist in the role/permission matrix, so
 * only the owner bypass could open the page. It is now aligned to the granted
 * certificates.manage permission so every role that manages certificates can
 * view the registry while roles without the permission stay blocked.
 */
class CertificateIndexAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected Institute $institute;

    protected Course $course;

    protected Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();

        $this->institute = Institute::where('name', 'MAWA ACADEMY')->firstOrFail();
        $this->course = Course::create(['course_code' => 'CAZ'.mt_rand(1000, 9999), 'name' => 'Cert Auth Course']);
        $this->batch = Batch::create([
            'institute_id' => $this->institute->id,
            'course_id' => $this->course->id,
            'name' => 'Cert Auth Batch',
            'batch_code' => 'CAB'.mt_rand(1000, 9999),
            'start_date' => now(),
        ]);
    }

    protected function staff(string $roleSlug, string $email): InstituteUser
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

    public function test_certificates_index_requires_certificates_manage_permission(): void
    {
        $teacher = $this->staff('teacher', 'cert-auth-teacher@example.test');

        $this->actingAs($teacher, 'institute_user')
            ->get(route('certificates.index'))
            ->assertForbidden();
    }

    public function test_role_with_certificates_manage_can_view_index(): void
    {
        $manager = $this->staff('branch-manager', 'cert-auth-manager@example.test');

        $this->actingAs($manager, 'institute_user')
            ->get(route('certificates.index'))
            ->assertOk()
            ->assertSee('Certificates');
    }

    public function test_exam_controller_can_view_index(): void
    {
        $controller = $this->staff('exam-controller', 'cert-auth-exam@example.test');

        $this->actingAs($controller, 'institute_user')
            ->get(route('certificates.index'))
            ->assertOk()
            ->assertSee('Certificates');
    }

    public function test_certificates_index_is_tenant_scoped(): void
    {
        $other = Institute::where('name', 'Tutu Center')->firstOrFail();

        $foreignCertificate = Certificate::create([
            'institute_id' => $other->id,
            'course_id' => $this->course->id,
            'batch_id' => $this->batch->id,
            'student_id' => $this->makeStudent($other)->id,
            'certificate_number' => 'MNT-CROSS-'.mt_rand(10000, 99999),
            'status' => 'active',
            'issue_date' => now()->toDateString(),
        ]);

        $manager = $this->staff('branch-manager', 'cert-auth-scope@example.test');

        $this->actingAs($manager, 'institute_user')
            ->get(route('certificates.index'))
            ->assertOk()
            ->assertDontSee($foreignCertificate->certificate_number);
    }

    protected function makeStudent(Institute $institute): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'student_id_number' => 'SIDCAZ'.mt_rand(10000, 99999),
            'first_name' => 'CertAuth',
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => now(),
        ]);
    }
}