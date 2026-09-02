<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Models\Student;
use App\Services\StudentAcademicCertificateService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StudentAcademicCertificateTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    private function institute(string $name = 'Cert Inst'): Institute
    {
        return Institute::create([
            'name' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
    }

    private function owner(Institute $institute): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => Role::where('slug', 'institute-owner')->firstOrFail()->id,
            'first_name' => 'Cert',
            'last_name' => 'Owner',
            'email' => 'cert-owner-'.uniqid().'@example.test',
            'phone' => '01700'.rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function student(Institute $institute, string $name = 'Rahim'): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'student_id_number' => 'SC'.mt_rand(100000, 999999),
            'first_name' => $name,
            'last_name' => 'Student',
            'admission_date' => now()->toDateString(),
            'status' => 'active',
        ]);
    }

    private function course(string $name = 'Web Development'): Course
    {
        return Course::create([
            'name' => $name,
            'course_code' => 'CO-'.uniqid(),
            'status' => 'active',
        ]);
    }

    private function batch(Institute $institute, Course $course, string $name = 'Batch A'): Batch
    {
        return Batch::create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
            'name' => $name,
            'batch_code' => 'BA-'.uniqid(),
            'start_date' => now()->toDateString(),
        ]);
    }

    private function certificate(Student $student, Course $course, Batch $batch, string $status, int $daysAgo, ?string $number = null): Certificate
    {
        return Certificate::create([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => $status,
            'certificate_number' => $number,
            'issue_date' => now()->subDays($daysAgo)->toDateString(),
            'verification_url' => $number ? 'https://example.test/verify/certificate/'.$number : null,
        ]);
    }

    private function historyRoute(Student $student): string
    {
        return route('students.academic-history', $student);
    }

    public function test_authorized_user_sees_official_certificates_on_academic_history(): void
    {
        $institute = $this->institute();
        $owner = $this->owner($institute);
        $student = $this->student($institute);
        $course = $this->course();
        $batch = $this->batch($institute, $course);
        $this->certificate($student, $course, $batch, 'active', 5, 'MNT-2026-12345');

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->get($this->historyRoute($student))
            ->assertOk()
            ->assertSee('Certificates')
            ->assertSee('MNT-2026-12345')
            ->assertSee('Web Development')
            ->assertSee('Batch A')
            ->assertSee('Active')
            ->assertSee('View Certificate');
    }

    public function test_pending_and_rejected_requests_are_excluded_from_certificate_history(): void
    {
        $institute = $this->institute();
        $owner = $this->owner($institute);
        $student = $this->student($institute);
        $course = $this->course();
        $batch = $this->batch($institute, $course);
        $this->certificate($student, $course, $batch, 'pending', 1, 'MNT-2026-00001');
        $this->certificate($student, $course, $batch, 'rejected', 2, 'MNT-2026-00002');

        TenantContext::set($institute->id);

        $this->actingAs($owner, 'institute_user')
            ->get($this->historyRoute($student))
            ->assertOk()
            ->assertDontSee('MNT-2026-00001')
            ->assertDontSee('MNT-2026-00002')
            ->assertSee('No certificates issued for this student yet.');
    }

    public function test_certificate_service_returns_official_records_newest_first(): void
    {
        $institute = $this->institute();
        $student = $this->student($institute);
        $course = $this->course();
        $batch = $this->batch($institute, $course);
        $oldest = $this->certificate($student, $course, $batch, 'active', 30, 'MNT-2026-00001');
        $revoked = $this->certificate($student, $course, $batch, 'revoked', 10, 'MNT-2026-00002');
        $newest = $this->certificate($student, $course, $batch, 'active', 2, 'MNT-2026-00003');

        TenantContext::set($institute->id);

        $certificates = app(StudentAcademicCertificateService::class)->forStudent($student);

        $this->assertCount(3, $certificates);
        $this->assertSame([$newest->id, $revoked->id, $oldest->id], $certificates->pluck('id')->all());
        $this->assertSame(['active', 'revoked', 'active'], $certificates->pluck('status')->all());
        $this->assertEquals('Web Development', $certificates->first()->course->name);
        $this->assertTrue($certificates->every(fn ($certificate) => in_array($certificate->status, ['active', 'revoked'], true)));
    }

    public function test_certificate_history_is_tenant_scoped(): void
    {
        $instituteA = $this->institute('Inst A');
        $studentA = $this->student($instituteA, 'Rahim');
        $courseA = $this->course();
        $batchA = $this->batch($instituteA, $courseA);
        $this->certificate($studentA, $courseA, $batchA, 'active', 1, 'MNT-2026-10001');

        $instituteB = $this->institute('Inst B');
        $ownerB = $this->owner($instituteB);
        $studentB = $this->student($instituteB, 'Karim');
        $courseB = $this->course();
        $batchB = $this->batch($instituteB, $courseB);
        $this->certificate($studentB, $courseB, $batchB, 'active', 1, 'MNT-2026-20001');

        TenantContext::set($instituteA->id);

        $this->assertCount(1, app(StudentAcademicCertificateService::class)->forStudent($studentA));
        $this->assertCount(0, app(StudentAcademicCertificateService::class)->forStudent($studentB));

        $this->actingAs($ownerB, 'institute_user')
            ->get($this->historyRoute($studentB))
            ->assertOk();

        $this->actingAs($ownerB, 'institute_user')
            ->get($this->historyRoute($studentA))
            ->assertStatus(404);
    }

    public function test_approval_persists_the_shared_certificate_number(): void
    {
        $institute = $this->institute();
        $student = $this->student($institute);
        $course = $this->course();
        $batch = $this->batch($institute, $course);
        $certificate = $this->certificate($student, $course, $batch, 'pending', 1);

        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'cert-admin-'.uniqid().'@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'platform_admin');

        $this->post(route('admin.certificates.action', $certificate), ['action' => 'approve'])
            ->assertRedirect(route('admin.certificates.requests'));

        $certificate->refresh();

        $this->assertSame('active', $certificate->status);
        $this->assertSame(Certificate::numberFor($certificate), $certificate->certificate_number);
        $this->assertMatchesRegularExpression('/^MNT-\d{4}-[0-9]{5}$/', (string) $certificate->certificate_number);
    }
}
