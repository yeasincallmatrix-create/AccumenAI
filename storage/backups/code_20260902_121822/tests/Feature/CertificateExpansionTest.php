<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Certificate;
use App\Models\CertificateType;
use App\Models\Course;
use App\Models\Institute;
use App\Models\InstituteUser;
use App\Models\PlatformAdmin;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Step 49 — Certificate Expansion tests.
 *
 * Covers:
 * - Certificate type CRUD (create, edit, delete, tenant isolation, permissions)
 * - Certificate type display on certificates and verification
 * - Rejected certificate resubmission
 * - Type assignment on certificate creation
 * - Read-only academic source-of-truth safety
 */
class CertificateExpansionTest extends TestCase
{
    use DatabaseTransactions;

    protected string $password = 'secret12345';

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function institute(string $name = 'CertExpand Inst'): Institute
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
            'first_name' => 'Owner',
            'last_name' => 'User',
            'email' => 'ce-owner-'.uniqid().'@example.test',
            'phone' => '01700'.mt_rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function teacher(Institute $institute): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'role_id' => Role::where('slug', 'teacher')->firstOrFail()->id,
            'first_name' => 'Teacher',
            'last_name' => 'User',
            'email' => 'ce-teacher-'.uniqid().'@example.test',
            'phone' => '01700'.mt_rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function student(Institute $institute, string $name = 'Graduate'): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'student_id_number' => 'CES'.mt_rand(100000, 999999),
            'first_name' => $name,
            'last_name' => 'Student',
            'admission_date' => now()->toDateString(),
            'status' => 'active',
        ]);
    }

    private function course(string $name = 'Certificate Course'): Course
    {
        return Course::create([
            'name' => $name,
            'course_code' => 'CE-'.uniqid(),
        ]);
    }

    private function batch(Institute $institute, Course $course): Batch
    {
        return Batch::create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
            'name' => 'CE Batch',
            'batch_code' => 'CEB-'.mt_rand(10, 99),
            'start_date' => now()->toDateString(),
        ]);
    }

    private function makeStudent(Institute $institute, string $name = 'Graduate'): Student
    {
        $student = $this->student($institute, $name);
        $course = $this->course();
        $batch = $this->batch($institute, $course);
        StudentEnrollment::create([
            'institute_id' => $institute->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'roll_number' => 'R'.mt_rand(10, 99),
            'enrollment_date' => now()->toDateString(),
            'fee_payable' => 10000,
            'discount' => 0,
            'status' => 'active',
        ]);

        return $student;
    }

    // ------------------------------------------------------- Certificate Types

    public function test_owner_can_create_certificate_type(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);
        TenantContext::set($inst->id);

        $response = $this->actingAs($owner, 'institute_user')
            ->post(route('certificate-types.store'), [
                'name' => 'Course Completion',
                'description' => 'Awarded upon course completion',
                'is_active' => '1',
                'display_order' => '1',
            ]);

        $response->assertRedirect(route('certificate-types.index'));
        $this->assertDatabaseHas('certificate_types', [
            'institute_id' => $inst->id,
            'name' => 'Course Completion',
            'is_active' => true,
        ]);
    }

    public function test_teacher_without_permission_cannot_manage_types(): void
    {
        $inst = $this->institute();
        $teacher = $this->teacher($inst);
        TenantContext::set($inst->id);

        $this->actingAs($teacher, 'institute_user')
            ->get(route('certificate-types.index'))
            ->assertForbidden();
    }

    public function test_certificate_type_is_tenant_scoped(): void
    {
        $instA = $this->institute('Inst A');
        $instB = $this->institute('Inst B');
        $ownerA = $this->owner($instA);

        CertificateType::create([
            'institute_id' => $instA->id,
            'name' => 'Graduation',
            'slug' => 'graduation',
        ]);

        CertificateType::create([
            'institute_id' => $instB->id,
            'name' => 'Z-Training-Foreign',
            'slug' => 'training',
        ]);

        TenantContext::set($instA->id);

        $this->actingAs($ownerA, 'institute_user')
            ->get(route('certificate-types.index'))
            ->assertOk()
            ->assertSee('Graduation')
            ->assertDontSee('Z-Training-Foreign');
    }

    public function test_certificate_type_slug_is_unique_per_institute(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);
        TenantContext::set($inst->id);

        CertificateType::create([
            'institute_id' => $inst->id,
            'name' => 'Completion',
            'slug' => 'completion',
        ]);

        $this->actingAs($owner, 'institute_user')
            ->post(route('certificate-types.store'), [
                'name' => 'Completion',
            ]);

        $this->assertSame(1, CertificateType::where('institute_id', $inst->id)->where('slug', 'completion')->count());
        $this->assertSame(1, CertificateType::where('institute_id', $inst->id)->where('slug', 'completion-1')->count());
    }

    public function test_certificate_type_update_and_delete(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);
        TenantContext::set($inst->id);

        $type = CertificateType::create([
            'institute_id' => $inst->id,
            'name' => 'Achievement',
            'slug' => 'achievement',
        ]);

        $this->actingAs($owner, 'institute_user')
            ->put(route('certificate-types.update', $type), [
                'name' => 'Special Achievement',
                'description' => 'Updated',
                'is_active' => '1',
                'display_order' => '0',
            ]);

        $type->refresh();
        $this->assertSame('Special Achievement', $type->name);
        $this->assertSame('special-achievement', $type->slug);

        $this->actingAs($owner, 'institute_user')
            ->delete(route('certificate-types.destroy', $type));

        $this->assertDatabaseMissing('certificate_types', ['id' => $type->id]);
    }

    public function test_cross_institute_type_edit_is_forbidden(): void
    {
        $instA = $this->institute('Inst A');
        $instB = $this->institute('Inst B');
        $ownerA = $this->owner($instA);

        $typeB = CertificateType::create([
            'institute_id' => $instB->id,
            'name' => 'Foreign Type',
            'slug' => 'foreign-type',
        ]);

        TenantContext::set($instA->id);

        $this->actingAs($ownerA, 'institute_user')
            ->put(route('certificate-types.update', $typeB), [
                'name' => 'Hacked',
            ])
            ->assertNotFound();
    }

    // ----------------------------------------- Certificate Type on Certificates

    public function test_certificate_type_is_shown_on_academic_history(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);
        $student = $this->student($inst);
        $course = $this->course();
        $batch = $this->batch($inst, $course);

        $type = CertificateType::create([
            'institute_id' => $inst->id,
            'name' => 'Graduation Certificate',
            'slug' => 'graduation-certificate',
        ]);

        Certificate::create([
            'institute_id' => $inst->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'certificate_type_id' => $type->id,
            'status' => 'active',
            'certificate_number' => 'MNT-2026-CE0001',
            'issue_date' => now()->toDateString(),
        ]);

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->get(route('students.academic-history', $student))
            ->assertOk()
            ->assertSee('Graduation Certificate')
            ->assertSee('MNT-2026-CE0001');
    }

    public function test_certificate_type_shown_on_public_verification(): void
    {
        $inst = $this->institute();
        $student = $this->student($inst);
        $course = $this->course();
        $batch = $this->batch($inst, $course);

        $type = CertificateType::create([
            'institute_id' => $inst->id,
            'name' => 'Training Certificate',
            'slug' => 'training-certificate',
        ]);

        $certificate = Certificate::create([
            'institute_id' => $inst->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'certificate_type_id' => $type->id,
            'status' => 'active',
            'certificate_number' => 'MNT-2026-CE0002',
            'issue_date' => now()->toDateString(),
        ]);

        $this->get(route('verify.certificate', $certificate->certificate_number))
            ->assertOk()
            ->assertSee('Training Certificate')
            ->assertSee('VALID CERTIFICATE');
    }

    public function test_certificate_without_type_shows_dash_on_verification(): void
    {
        $inst = $this->institute();
        $student = $this->student($inst);
        $course = $this->course();
        $batch = $this->batch($inst, $course);

        $certificate = Certificate::create([
            'institute_id' => $inst->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
            'certificate_number' => 'MNT-2026-CE0003',
            'issue_date' => now()->toDateString(),
        ]);

        $this->get(route('verify.certificate', $certificate->certificate_number))
            ->assertOk()
            ->assertSee('Certificate Type')
            ->assertSee('—');
    }

    // ------------------------------------------------- Rejected Resubmission

    public function test_rejected_certificate_can_be_resubmitted(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);
        $student = $this->student($inst);
        $course = $this->course();
        $batch = $this->batch($inst, $course);

        $certificate = Certificate::create([
            'institute_id' => $inst->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'rejected',
            'review_note' => 'Missing documents',
        ]);

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->post(route('certificates.resubmit', $certificate))
            ->assertRedirect();

        $certificate->refresh();
        $this->assertSame('pending', $certificate->status);
        $this->assertNull($certificate->reviewed_by);
        $this->assertNull($certificate->reviewed_at);
        $this->assertNull($certificate->review_note);
    }

    public function test_pending_certificate_cannot_be_resubmitted(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);
        $student = $this->student($inst);
        $course = $this->course();
        $batch = $this->batch($inst, $course);

        $certificate = Certificate::create([
            'institute_id' => $inst->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'pending',
        ]);

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->post(route('certificates.resubmit', $certificate))
            ->assertRedirect();

        $certificate->refresh();
        $this->assertSame('pending', $certificate->status);
    }

    public function test_active_certificate_cannot_be_resubmitted(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);
        $student = $this->student($inst);
        $course = $this->course();
        $batch = $this->batch($inst, $course);

        $certificate = Certificate::create([
            'institute_id' => $inst->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'active',
            'certificate_number' => 'MNT-2026-CE0004',
            'issue_date' => now()->toDateString(),
        ]);

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->post(route('certificates.resubmit', $certificate))
            ->assertRedirect();

        $certificate->refresh();
        $this->assertSame('active', $certificate->status);
    }

    public function test_resubmit_is_tenant_scoped(): void
    {
        $instA = $this->institute('Inst A');
        $instB = $this->institute('Inst B');
        $ownerA = $this->owner($instA);
        $studentB = $this->student($instB, 'Foreign');
        $course = $this->course();
        $batch = $this->batch($instB, $course);

        $certB = Certificate::create([
            'institute_id' => $instB->id,
            'student_id' => $studentB->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'rejected',
        ]);

        TenantContext::set($instA->id);

        $this->actingAs($ownerA, 'institute_user')
            ->post(route('certificates.resubmit', $certB))
            ->assertNotFound();

        $certB->refresh();
        $this->assertSame('rejected', $certB->status);
    }

    public function test_resubmit_requires_certificates_manage_permission(): void
    {
        $inst = $this->institute();
        $teacher = $this->teacher($inst);
        $student = $this->student($inst);
        $course = $this->course();
        $batch = $this->batch($inst, $course);

        $certificate = Certificate::create([
            'institute_id' => $inst->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'rejected',
        ]);

        TenantContext::set($inst->id);

        $this->actingAs($teacher, 'institute_user')
            ->post(route('certificates.resubmit', $certificate))
            ->assertForbidden();
    }

    // ----------------------------------------- Academic Source-of-Truth Safety

    public function test_certificate_type_crud_does_not_mutate_academic_data(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);
        TenantContext::set($inst->id);

        $tables = [
            'students',
            'student_enrollments',
            'academic_final_results',
            'promotion_decisions',
            'promotion_decision_items',
            'student_academic_placements',
        ];

        $before = [];
        foreach ($tables as $table) {
            $before[$table] = collect(DB::table($table)->get()->map(fn ($row) => (array) $row));
        }

        $type = CertificateType::create([
            'institute_id' => $inst->id,
            'name' => 'Test Type',
            'slug' => 'test-type',
        ]);

        $this->actingAs($owner, 'institute_user')
            ->put(route('certificate-types.update', $type), ['name' => 'Updated Type']);

        $this->actingAs($owner, 'institute_user')
            ->delete(route('certificate-types.destroy', $type));

        foreach ($tables as $table) {
            $after = collect(DB::table($table)->get()->map(fn ($row) => (array) $row));
            $this->assertEquals($before[$table]->all(), $after->all(), "{$table} was mutated by certificate type operations.");
        }
    }

    public function test_resubmit_does_not_mutate_academic_data(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);
        $student = $this->student($inst);
        $course = $this->course();
        $batch = $this->batch($inst, $course);

        $tables = [
            'students',
            'student_enrollments',
            'academic_final_results',
            'promotion_decisions',
            'promotion_decision_items',
            'student_academic_placements',
        ];

        $before = [];
        foreach ($tables as $table) {
            $before[$table] = collect(DB::table($table)->get()->map(fn ($row) => (array) $row));
        }

        $certificate = Certificate::create([
            'institute_id' => $inst->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'status' => 'rejected',
        ]);

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->post(route('certificates.resubmit', $certificate))
            ->assertRedirect();

        foreach ($tables as $table) {
            $after = collect(DB::table($table)->get()->map(fn ($row) => (array) $row));
            $this->assertEquals($before[$table]->all(), $after->all(), "{$table} was mutated by certificate resubmit.");
        }
    }

    // ----------------------------------------- Certificate Numbering Preserved

    public function test_certificate_numbering_is_unchanged_after_expansion(): void
    {
        $inst = $this->institute();
        $student = $this->student($inst);
        $course = $this->course();
        $batch = $this->batch($inst, $course);

        $type = CertificateType::create([
            'institute_id' => $inst->id,
            'name' => 'Graduation',
            'slug' => 'graduation',
        ]);

        $certificate = Certificate::create([
            'institute_id' => $inst->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'certificate_type_id' => $type->id,
            'status' => 'pending',
        ]);

        $admin = PlatformAdmin::firstOrReuseForTests([
            'email' => 'ce-admin-'.uniqid().'@example.test',
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'platform_admin');

        $this->post(route('admin.certificates.action', $certificate), ['action' => 'approve']);

        $certificate->refresh();

        $this->assertSame('active', $certificate->status);
        $this->assertSame(Certificate::numberFor($certificate), $certificate->certificate_number);
        $this->assertMatchesRegularExpression('/^MNT-\d{4}-[0-9]{5}$/', (string) $certificate->certificate_number);
        $this->assertSame($type->id, $certificate->certificate_type_id);
    }

    // ----------------------------------------- Certificate Index Shows Type

    public function test_institute_certificate_index_loads_with_type(): void
    {
        $inst = $this->institute();
        $owner = $this->owner($inst);
        $student = $this->student($inst);
        $course = $this->course();
        $batch = $this->batch($inst, $course);

        $type = CertificateType::create([
            'institute_id' => $inst->id,
            'name' => 'Achievement',
            'slug' => 'achievement',
        ]);

        Certificate::create([
            'institute_id' => $inst->id,
            'student_id' => $student->id,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'certificate_type_id' => $type->id,
            'status' => 'active',
            'certificate_number' => 'MNT-2026-CE0005',
            'issue_date' => now()->toDateString(),
        ]);

        TenantContext::set($inst->id);

        $this->actingAs($owner, 'institute_user')
            ->get(route('certificates.index'))
            ->assertOk()
            ->assertSee('Achievement')
            ->assertSee('MNT-2026-CE0005');
    }
}
