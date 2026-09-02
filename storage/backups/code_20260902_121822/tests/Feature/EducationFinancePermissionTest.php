<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Branch;
use App\Models\Country;
use App\Models\Course;
use App\Models\Institute;
use App\Models\InstituteCourse;
use App\Models\InstituteUser;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Education\FeeHeadService;
use App\Services\Education\FeeStructureService;
use App\Services\Education\StudentFinanceService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EducationFinancePermissionTest extends TestCase
{
    use DatabaseTransactions;

    private string $password = 'password123';

    private function country(): Country
    {
        return Country::firstOrCreate(['iso2' => 'BD'], ['name' => 'Bangladesh',
            'iso3' => 'BGD',
            'phone_code' => '+880',
            'status' => true,
        ]);
    }

    private function institute(Country $country, string $name): Institute
    {
        return Institute::create([
            'name' => $name,
            'slug' => str()->slug($name.'-'.uniqid()),
            'country' => $country->name,
            'country_id' => $country->id,
            'status' => 'active',
        ]);
    }

    private function branch(Institute $institute, string $name): Branch
    {
        return Branch::create([
            'institute_id' => $institute->id,
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function user(Institute $institute, string $roleSlug, string $prefix, ?Branch $branch = null): InstituteUser
    {
        return InstituteUser::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch?->id,
            'role_id' => Role::where('slug', $roleSlug)->firstOrFail()->id,
            'first_name' => 'Staff',
            'last_name' => 'User',
            'email' => $prefix.'-'.uniqid().'@example.test',
            'phone' => '01700'.mt_rand(100000, 999999),
            'password_hash' => bcrypt($this->password),
            'status' => 'active',
        ]);
    }

    private function course(Institute $institute, string $name): Course
    {
        $course = Course::create([
            'course_code' => 'C'.mt_rand(1000, 9999),
            'name' => $name,
            'fee' => 5000,
        ]);

        InstituteCourse::create([
            'institute_id' => $institute->id,
            'course_id' => $course->id,
        ]);

        return $course;
    }

    private function batch(Institute $institute, Branch $branch, Course $course, string $name): Batch
    {
        return Batch::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'course_id' => $course->id,
            'name' => $name,
            'batch_code' => 'B'.mt_rand(1000, 9999),
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'status' => 'ongoing',
        ]);
    }

    private function student(Institute $institute, Branch $branch, string $name): Student
    {
        return Student::create([
            'institute_id' => $institute->id,
            'branch_id' => $branch->id,
            'student_id_number' => 'ST'.mt_rand(100000, 999999),
            'first_name' => $name,
            'last_name' => 'Student',
            'status' => 'active',
            'admission_date' => '2026-01-01',
        ]);
    }

    private function enroll(Student $student, Batch $batch): StudentEnrollment
    {
        return StudentEnrollment::create([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'course_id' => $batch->course_id,
            'enrollment_date' => '2026-01-05',
            'roll_number' => 'R'.mt_rand(100, 999),
            'status' => 'active',
        ]);
    }

    private function setUpWorld(): array
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Perm Institute');
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branchA->id);
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branchB->id);
        app(AccountingSetupService::class)->setSetting($institute->id, 'invoice_auto_post', true, $branchA->id);

        $course = $this->course($institute, 'Course A');
        $head = app(FeeHeadService::class)->create($institute->id, $branchA->id, [
            'type' => 'course_tuition',
            'name' => 'Tuition Fee',
        ], null);
        $structure = app(FeeStructureService::class)->create($institute->id, $branchA->id, [
            'name' => 'S1',
            'academic_year_id' => null,
            'course_id' => $course->id,
            'batch_id' => null,
            'installments_count' => 1,
            'installments_interval_days' => 30,
            'status' => 'active',
            'items' => [['fee_head_id' => $head->id, 'amount' => 5000]],
        ], null);

        $batchA = $this->batch($institute, $branchA, $course, 'Batch A1');
        $batchB = $this->batch($institute, $branchB, $course, 'Batch B1');
        $studentA = $this->student($institute, $branchA, 'S1');
        $studentB = $this->student($institute, $branchB, 'S2');
        $enrollA = $this->enroll($studentA, $batchA);
        $this->enroll($studentB, $batchB);

        $invoice = app(StudentFinanceService::class)->generateInvoice(
            $enrollA,
            $structure,
            ['due_date' => now()->addDays(30)->toDateString()],
        );
        $payment = app(StudentFinanceService::class)->recordPayment($institute->id, $branchA->id, [
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'payment_method' => 'cash',
        ]);

        return compact(
            'institute',
            'branchA',
            'branchB',
            'course',
            'head',
            'structure',
            'studentA',
            'studentB',
            'enrollA',
            'invoice',
            'payment',
            'country',
        );
    }

    public function test_permission_matrix_across_roles(): void
    {
        $w = $this->setUpWorld();
        $institute = $w['institute'];
        $branchA = $w['branchA'];
        $student = $w['studentA'];
        $invoice = $w['invoice'];

        $owner = $this->user($institute, 'institute-owner', 'own');
        $accountant = $this->user($institute, 'accountant', 'acc', $branchA);
        $manager = $this->user($institute, 'branch-manager', 'mgr', $branchA);
        $receptionist = $this->user($institute, 'receptionist', 'rec', $branchA);
        $teacher = $this->user($institute, 'teacher', 'tea', $branchA);

        $dashboard = route('finance.education.dashboard');
        $students = route('finance.education.students.index');
        $show = route('finance.education.students.show', $student);
        $reports = route('finance.education.reports.batches');
        $feeHeads = route('finance.education.fee-heads.index');
        $invoiceUrl = route('finance.education.students.invoice', $student);
        $paymentUrl = route('finance.education.students.payments', $student);
        $waiveUrl = route('finance.education.invoices.waive', $invoice);
        $reverseUrl = route('finance.education.payments.reverse', $w['payment']);

        // Owner: full access.
        $this->actingAs($owner, 'institute_user');
        $this->get($dashboard)->assertOk();
        $this->get($reports)->assertOk();
        $this->post($invoiceUrl, ['enrollment_id' => $w['enrollA']->id])->assertRedirect();
        $this->post($waiveUrl, ['amount' => 100, 'reason' => 'Owner waiver'])->assertRedirect();

        // Accountant: full operational + waiver + refund.
        $this->actingAs($accountant, 'institute_user');
        $this->get($dashboard)->assertOk();
        $this->get($students)->assertOk();
        $this->get($feeHeads)->assertOk();
        $this->post($paymentUrl, [
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'payment_method' => 'cash',
        ])->assertRedirect();
        $this->post($waiveUrl, ['amount' => 100, 'reason' => 'Accountant waiver'])->assertRedirect();
        $this->post($reverseUrl, ['reason' => 'Refund by accountant'])->assertRedirect();

        // Branch manager: view + reports, no writes/waivers/refunds.
        $this->actingAs($manager, 'institute_user');
        $this->get($dashboard)->assertOk();
        $this->get($reports)->assertOk();
        $this->get($show)->assertOk();
        $this->get($feeHeads)->assertOk();
        $this->post($invoiceUrl, ['enrollment_id' => $w['enrollA']->id])->assertForbidden();
        $this->post($paymentUrl, ['invoice_id' => $invoice->id, 'amount' => 100, 'payment_method' => 'cash'])->assertForbidden();
        $this->post($waiveUrl, ['amount' => 100])->assertForbidden();
        $this->post($reverseUrl, [])->assertForbidden();

        // Receptionist: view ledger only, no dashboard, no writes.
        $this->actingAs($receptionist, 'institute_user');
        $this->get($students)->assertOk();
        $this->get($show)->assertOk();
        $this->get($dashboard)->assertForbidden();
        $this->get($reports)->assertForbidden();
        $this->post($invoiceUrl, ['enrollment_id' => $w['enrollA']->id])->assertForbidden();
        $this->post($paymentUrl, ['invoice_id' => $invoice->id, 'amount' => 100, 'payment_method' => 'cash'])->assertForbidden();
        $this->post($waiveUrl, ['amount' => 100])->assertForbidden();

        // Teacher: no finance access at all.
        $this->actingAs($teacher, 'institute_user');
        $this->get($dashboard)->assertForbidden();
        $this->get($students)->assertForbidden();
        $this->get($show)->assertForbidden();
        $this->get($feeHeads)->assertForbidden();
        $this->post($invoiceUrl, ['enrollment_id' => $w['enrollA']->id])->assertForbidden();
    }

    public function test_unauthenticated_user_is_redirected(): void
    {
        $w = $this->setUpWorld();

        $this->get(route('finance.education.dashboard'))->assertRedirect();
        $this->get(route('finance.education.students.index'))->assertRedirect();
        $this->post(route('finance.education.invoices.waive', $w['invoice']), ['amount' => 10])->assertRedirect();
    }

    public function test_cross_tenant_access_returns_404(): void
    {
        $w = $this->setUpWorld();

        $other = Institute::create([
            'name' => 'Other Institute',
            'slug' => str()->slug('Other Institute-'.uniqid()),
            'country' => $w['country']->name,
            'country_id' => $w['country']->id,
            'status' => 'active',
        ]);
        $otherBranch = $this->branch($other, 'Main');
        app(AccountingSetupService::class)->setupForInstitute($other->id, $otherBranch->id);
        $outsider = $this->user($other, 'accountant', 'ext', $otherBranch);

        $this->actingAs($outsider, 'institute_user');

        $this->get(route('finance.education.students.show', $w['studentA']))->assertNotFound();
        $this->post(route('finance.education.students.invoice', $w['studentA']), [
            'enrollment_id' => $w['enrollA']->id,
        ])->assertNotFound();
    }

    public function test_branch_manager_cannot_view_other_branch_student(): void
    {
        $w = $this->setUpWorld();
        $manager = $this->user($w['institute'], 'branch-manager', 'mgr', $w['branchA']);

        $this->actingAs($manager, 'institute_user');
        $this->get(route('finance.education.students.show', $w['studentA']))->assertOk();
        $this->get(route('finance.education.students.show', $w['studentB']))->assertNotFound();
    }

    public function test_education_routes_expose_no_direct_journal_mutation(): void
    {
        $w = $this->setUpWorld();

        $educationRoutes = collect(Route::getRoutes()->getRoutesByName())
            ->filter(fn ($route, $name) => str_starts_with($name, 'finance.education.'))
            ->keys()
            ->all();

        $this->assertContains('finance.education.invoices.waive', $educationRoutes);
        $this->assertContains('finance.education.payments.reverse', $educationRoutes);
        $this->assertSame([], array_values(array_filter($educationRoutes, fn ($name) => str_contains($name, 'journals'))));
    }
}
