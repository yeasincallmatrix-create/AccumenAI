<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Institute;
use App\Models\PlatformAdmin;
use App\Models\Student;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CertificateVerificationTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    private function institute(string $name = 'Verify Inst'): Institute
    {
        return Institute::create([
            'name' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'country' => 'Bangladesh',
            'status' => 'active',
        ]);
    }

    private function course(string $name = 'Graphic Design'): Course
    {
        return Course::create([
            'name' => $name,
            'course_code' => 'CV-'.uniqid(),
            'status' => 'active',
        ]);
    }

    private function batch(Institute $institute, Course $course, string $name = 'Batch A'): Batch
    {
        return Batch::create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
            'name' => $name,
            'batch_code' => 'VB-'.uniqid(),
            'start_date' => now()->toDateString(),
        ]);
    }

    private function student(Institute $institute, string $name = 'Tanvir', array $extra = []): Student
    {
        return Student::create(array_merge([
            'institute_id' => $institute->id,
            'student_id_number' => 'SID'.mt_rand(100000, 999999),
            'first_name' => $name,
            'last_name' => 'Hasan',
            'admission_date' => now()->toDateString(),
            'status' => 'active',
        ], $extra));
    }

    private function certificate(Student $student, Course $course, Batch $batch, string $status, string $number, ?string $reason = null): Certificate
    {
        return Certificate::create([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => $status,
            'certificate_number' => $number,
            'issue_date' => now()->subDays(3)->toDateString(),
            'revoked_reason' => $reason,
            'verification_url' => 'https://example.test/verify/certificate/'.$number,
        ]);
    }

    private function admin(): PlatformAdmin
    {
        return PlatformAdmin::firstOrReuseForTests([
            'email' => 'verify-admin-'.uniqid().'@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    public function test_active_certificate_verifies_successfully(): void
    {
        $institute = $this->institute();
        $student = $this->student($institute, 'Tanvir');
        $course = $this->course();
        $batch = $this->batch($institute, $course);
        $certificate = $this->certificate($student, $course, $batch, 'active', 'MNT-2026-V00001');

        $this->get(route('verify.certificate', $certificate->certificate_number))
            ->assertOk()
            ->assertSee('Certificate Verified')
            ->assertSee('VALID CERTIFICATE')
            ->assertSee('MNT-2026-V00001')
            ->assertSee('Tanvir Hasan')
            ->assertSee('Graphic Design')
            ->assertSee('Batch A')
            ->assertSee('Verify Inst');
    }

    public function test_unknown_certificate_shows_generic_not_found_message(): void
    {
        $this->get(route('verify.certificate', 'MNT-2026-NOPE00'))
            ->assertNotFound()
            ->assertSee('Certificate not found or invalid.');
    }

    public function test_revoked_certificate_is_shown_as_revoked(): void
    {
        $institute = $this->institute();
        $student = $this->student($institute, 'Tanvir');
        $course = $this->course();
        $batch = $this->batch($institute, $course);
        $certificate = $this->certificate($student, $course, $batch, 'revoked', 'MNT-2026-V00002', 'Issued in error');

        $this->get(route('verify.certificate', $certificate->certificate_number))
            ->assertOk()
            ->assertSee('REVOKED')
            ->assertDontSee('VALID CERTIFICATE')
            ->assertSee('Issued in error')
            ->assertSee($student->institute->name)
            ->assertSee($certificate->issue_date->format('d M Y'));
    }

    public function test_pending_certificate_is_not_presented_as_valid(): void
    {
        $institute = $this->institute();
        $student = $this->student($institute);
        $course = $this->course();
        $batch = $this->batch($institute, $course);
        $certificate = $this->certificate($student, $course, $batch, 'pending', 'MNT-2026-V00003');

        $this->get(route('verify.certificate', $certificate->certificate_number))
            ->assertOk()
            ->assertSee('PENDING REVIEW')
            ->assertDontSee('VALID CERTIFICATE')
            ->assertDontSee('Certificate Verified');
    }

    public function test_rejected_certificate_is_not_presented_as_valid(): void
    {
        $institute = $this->institute();
        $student = $this->student($institute);
        $course = $this->course();
        $batch = $this->batch($institute, $course);
        $certificate = $this->certificate($student, $course, $batch, 'rejected', 'MNT-2026-V00004');

        $this->get(route('verify.certificate', $certificate->certificate_number))
            ->assertOk()
            ->assertSee('REJECTED')
            ->assertDontSee('VALID CERTIFICATE')
            ->assertDontSee('Certificate Verified');
    }

    public function test_certificate_lookup_is_exact(): void
    {
        $institute = $this->institute();
        $course = $this->course();
        $batch = $this->batch($institute, $course);
        $studentA = $this->student($institute, 'Ahmed');
        $studentB = $this->student($institute, 'Karim');
        $certificateA = $this->certificate($studentA, $course, $batch, 'active', 'MNT-2026-54321');
        $certificateB = $this->certificate($studentB, $course, $batch, 'active', 'MNT-2026-543210');

        $this->get(route('verify.certificate', $certificateA->certificate_number))
            ->assertOk()
            ->assertSee('Ahmed Hasan')
            ->assertDontSee('Karim Hasan');

        $this->get(route('verify.certificate', $certificateB->certificate_number))
            ->assertOk()
            ->assertSee('Karim Hasan')
            ->assertDontSee('Ahmed Hasan');
    }

    public function test_partial_certificate_number_does_not_search(): void
    {
        $institute = $this->institute();
        $student = $this->student($institute, 'Tanvir');
        $course = $this->course();
        $batch = $this->batch($institute, $course);
        $this->certificate($student, $course, $batch, 'active', 'MNT-2026-54321');

        $this->get(route('verify.certificate', 'MNT-2026-543'))
            ->assertNotFound()
            ->assertSee('Certificate not found or invalid.')
            ->assertDontSee('Tanvir Hasan');
    }

    public function test_sensitive_student_fields_are_not_exposed(): void
    {
        $institute = $this->institute();
        $student = $this->student($institute, 'Tanvir', [
            'phone' => '+8801770012345',
            'email' => 'tanvir.secret@example.com',
            'dob' => '2005-05-05',
            'father_name' => 'Father Secret',
            'mother_name' => 'Mother Secret',
            'present_address' => '12 Secret Street, Dhaka',
            'nid_number' => 'NID987654321',
        ]);
        $course = $this->course();
        $batch = $this->batch($institute, $course);
        $certificate = $this->certificate($student, $course, $batch, 'active', 'MNT-2026-V00010');

        $response = $this->get(route('verify.certificate', $certificate->certificate_number))
            ->assertOk();

        $response->assertDontSee('+8801770012345')
            ->assertDontSee('tanvir.secret@example.com')
            ->assertDontSee('2005-05-05')
            ->assertDontSee('Father Secret')
            ->assertDontSee('Mother Secret')
            ->assertDontSee('12 Secret Street, Dhaka')
            ->assertDontSee('NID987654321');
    }

    public function test_internal_identifiers_are_not_exposed(): void
    {
        $institute = $this->institute();
        $student = $this->student($institute, 'Tanvir', ['student_id_number' => 'SID999888']);
        $course = $this->course();
        $batch = $this->batch($institute, $course);
        $certificate = $this->certificate($student, $course, $batch, 'active', 'MNT-2026-V00011')->refresh();

        $this->get(route('verify.certificate', $certificate->certificate_number))
            ->assertOk()
            ->assertDontSee('SID999888')
            ->assertDontSee((string) $certificate->uuid)
            ->assertDontSee('institute_id')
            ->assertDontSee('student_id')
            ->assertDontSee('branch_id')
            ->assertDontSee('course_id')
            ->assertDontSee('batch_id');
    }

    public function test_academic_and_financial_data_is_not_exposed(): void
    {
        $institute = $this->institute();
        $student = $this->student($institute, 'Tanvir');
        $course = $this->course();
        $batch = $this->batch($institute, $course);
        $certificate = $this->certificate($student, $course, $batch, 'active', 'MNT-2026-V00012');

        $this->get(route('verify.certificate', $certificate->certificate_number))
            ->assertOk()
            ->assertDontSee('GPA')
            ->assertDontSee('Attendance')
            ->assertDontSee('Fee')
            ->assertDontSee('Payment')
            ->assertDontSee('Marks');
    }

    public function test_cross_tenant_certificates_are_not_enumerable(): void
    {
        $instituteA = $this->institute('Inst A');
        $studentA = $this->student($instituteA, 'Rahim');
        $courseA = $this->course();
        $batchA = $this->batch($instituteA, $courseA);
        $certificateA = $this->certificate($studentA, $courseA, $batchA, 'active', 'MNT-2026-T00001');

        $instituteB = $this->institute('Inst B');
        $studentB = $this->student($instituteB, 'Karim');
        $courseB = $this->course('Web Development');
        $batchB = $this->batch($instituteB, $courseB);
        $certificateB = $this->certificate($studentB, $courseB, $batchB, 'active', 'MNT-2026-T00002');

        $this->get(route('verify.certificate', $certificateA->certificate_number))
            ->assertOk()
            ->assertSee('Rahim')
            ->assertDontSee('Karim');

        $this->get(route('verify.certificate', $certificateB->certificate_number))
            ->assertOk()
            ->assertSee('Karim')
            ->assertDontSee('Rahim');

        $this->get(route('verify.certificate', 'MNT-2026-T0000'))
            ->assertNotFound()
            ->assertSee('Certificate not found or invalid.');
    }

    public function test_certificate_number_uses_centralized_format(): void
    {
        $institute = $this->institute();
        $student = $this->student($institute);
        $course = $this->course();
        $batch = $this->batch($institute, $course);
        $certificate = Certificate::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin(), 'platform_admin');

        $this->post(route('admin.certificates.action', $certificate), ['action' => 'approve']);

        $certificate->refresh();

        $this->assertSame(Certificate::numberFor($certificate), $certificate->certificate_number);
        $this->assertMatchesRegularExpression('/^MNT-\d{4}-[0-9]{5}$/', (string) $certificate->certificate_number);

        $this->get(route('verify.certificate', $certificate->certificate_number))
            ->assertOk()
            ->assertSee($certificate->certificate_number)
            ->assertSee('VALID CERTIFICATE');
    }

    public function test_verification_is_read_only(): void
    {
        $institute = $this->institute();
        $student = $this->student($institute);
        $course = $this->course();
        $batch = $this->batch($institute, $course);
        $certificate = $this->certificate($student, $course, $batch, 'active', 'MNT-2026-V00020');

        $tables = [
            'certificates',
            'students',
            'courses',
            'batches',
            'results',
            'academic_final_results',
            'academic_final_result_students',
            'academic_final_result_rows',
            'student_academic_placements',
            'attendance',
            'promotion_decisions',
            'promotion_decision_items',
        ];

        $before = [];
        foreach ($tables as $table) {
            $before[$table] = collect(DB::table($table)->get()->map(fn ($row) => (array) $row));
        }

        $this->get(route('verify.certificate', $certificate->certificate_number))
            ->assertOk();

        $this->get(route('verify.certificate', 'MNT-2026-NOPE00'))
            ->assertNotFound();

        foreach ($tables as $table) {
            $after = collect(DB::table($table)->get()->map(fn ($row) => (array) $row));
            $this->assertEquals($before[$table]->all(), $after->all(), "{$table} was mutated by certificate verification.");
        }
    }

    public function test_public_verification_does_not_require_login(): void
    {
        $institute = $this->institute();
        $student = $this->student($institute);
        $course = $this->course();
        $batch = $this->batch($institute, $course);
        $certificate = $this->certificate($student, $course, $batch, 'active', 'MNT-2026-V00030');

        $this->get(route('verify.certificate.index'))
            ->assertOk()
            ->assertSee('Verify a Certificate')
            ->assertSee('Check Certificate');

        $this->get(route('verify.certificate', $certificate->certificate_number))
            ->assertOk()
            ->assertSee('Certificate Verified');
    }

    public function test_check_redirects_to_uppercased_verification_url(): void
    {
        $this->get(route('verify.certificate.index'));

        $this->post(route('verify.certificate.check'), ['certificate_number' => '  mnt-2026-test12  '])
            ->assertRedirect(route('verify.certificate', 'MNT-2026-TEST12'));
    }

    public function test_invalid_input_is_rejected_safely(): void
    {
        $this->post(route('verify.certificate.check'), ['certificate_number' => ''])
            ->assertSessionHasErrors('certificate_number');

        $this->post(route('verify.certificate.check'), ['certificate_number' => 'MNT 2026 !@#'])
            ->assertSessionHasErrors('certificate_number');

        $this->post(route('verify.certificate.check'), ['certificate_number' => str_repeat('A', 41)])
            ->assertSessionHasErrors('certificate_number');

        $this->post(route('verify.certificate.check'), ['certificate_number' => 'payload%00sql_wildcard_%'])
            ->assertSessionHasErrors('certificate_number');

        $this->get(route('verify.certificate.index').'/MNT-2026#fragment')
            ->assertStatus(404);
    }
}
