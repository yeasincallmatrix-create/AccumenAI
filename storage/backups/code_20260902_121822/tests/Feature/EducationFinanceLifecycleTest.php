<?php

namespace Tests\Feature;

use App\Models\AccountingAuditTrail;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\Country;
use App\Models\Course;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\Institute;
use App\Models\InstituteCourse;
use App\Models\InstituteUser;
use App\Models\Invoice;
use App\Models\Journal;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentWaiver;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Accounting\ReceivablesPayablesService;
use App\Services\Education\FeeHeadService;
use App\Services\Education\FeeStructureService;
use App\Services\Education\StudentFinanceService;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class EducationFinanceLifecycleTest extends TestCase
{
    use DatabaseTransactions;

    private string $password = 'password123';

    protected function tearDown(): void
    {
        TenantContext::clear();
        parent::tearDown();
    }

    private function context(Institute $institute): void
    {
        TenantContext::set($institute->id);
    }

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

    private function course(Institute $institute, string $name, array $fees = []): Course
    {
        $course = Course::create(array_merge([
            'course_code' => 'C'.mt_rand(1000, 9999),
            'name' => $name,
        ], $fees));

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

    private function enroll(Student $student, Batch $batch, array $extra = []): StudentEnrollment
    {
        return StudentEnrollment::create(array_merge([
            'institute_id' => $student->institute_id,
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'course_id' => $batch->course_id,
            'enrollment_date' => '2026-01-05',
            'roll_number' => 'R'.mt_rand(100, 999),
            'status' => 'active',
        ], $extra));
    }

    private function setupAccounting(Institute $institute, ?Branch $branch = null): void
    {
        app(AccountingSetupService::class)->setupForInstitute($institute->id, $branch?->id);
    }

    private function autoPost(Institute $institute, ?Branch $branch, bool $enabled): void
    {
        app(AccountingSetupService::class)->setSetting($institute->id, 'invoice_auto_post', $enabled, $branch?->id);
    }

    private function feeHead(Institute $institute, Branch $branch, string $type, string $name): FeeHead
    {
        return app(FeeHeadService::class)->create(
            $institute->id,
            $branch->id,
            [
                'type' => $type,
                'name' => $name,
                'description' => 'Test head',
            ],
            null,
        );
    }

    private function structure(
        Institute $institute,
        Branch $branch,
        Course $course,
        array $items,
        array $extra = []
    ): FeeStructure {
        $payload = array_merge([
            'name' => 'Structure '.uniqid(),
            'academic_year_id' => null,
            'course_id' => $course->id,
            'batch_id' => null,
            'installments_count' => 1,
            'installments_interval_days' => 30,
            'status' => 'active',
            'items' => $items,
        ], $extra);

        return app(FeeStructureService::class)->create($institute->id, $branch->id, $payload, null);
    }

    private function finance(): StudentFinanceService
    {
        return app(StudentFinanceService::class);
    }

    private function ar(Institute $institute, ?Branch $branch): float
    {
        return (float) app(ReceivablesPayablesService::class)->totals($institute->id, $branch->id)['receivable'];
    }

    // ------------------------------------------------------------- Billing

    public function test_generate_invoice_from_structure_with_installments_and_party(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Lifecycle Institute');
        $branch = $this->branch($institute, 'Main');
        $this->setupAccounting($institute, $branch);
        $this->autoPost($institute, $branch, true);
        $this->context($institute);

        $course = $this->course($institute, 'Course A');
        $head = $this->feeHead($institute, $branch, 'course_tuition', 'Tuition Fee');
        $batch = $this->batch($institute, $branch, $course, 'Batch A1');
        $student = $this->student($institute, $branch, 'Rahim');
        $enrollment = $this->enroll($student, $batch);

        $structure = $this->structure($institute, $branch, $course, [
            ['fee_head_id' => $head->id, 'amount' => 6000],
        ], [
            'installments_count' => 3,
        ]);

        $invoice = $this->finance()->generateInvoice(
            $enrollment,
            $structure,
            ['due_date' => now()->addDays(30)->toDateString()],
            $this->user($institute, 'accountant', 'acc')->id,
        );

        $this->assertSame('course_fee', $invoice->invoice_type);
        $this->assertSame(6000.0, (float) $invoice->total_amount);
        $this->assertSame(6000.0, (float) $invoice->payable_amount);
        $this->assertSame(6000.0, (float) $invoice->due_amount);
        $this->assertSame((int) $student->id, (int) $invoice->student_id);
        $this->assertSame((int) $enrollment->id, (int) $invoice->enrollment_id);
        $this->assertNotNull($invoice->party_id);
        $this->assertSame('education', $invoice->invoice_meta['source'] ?? null);
        $this->assertSame((int) $structure->id, (int) ($invoice->invoice_meta['fee_structure_id'] ?? 0));

        $this->assertCount(1, $invoice->items);
        $this->assertSame((int) $head->id, (int) $invoice->items->first()->fee_head_id);
        $this->assertSame((int) $head->income_coa_id, (int) $invoice->items->first()->coa_id);

        $this->assertCount(3, $invoice->installments);
        $this->assertSame(6000.0, (float) $invoice->installments->sum('amount'));
        $this->assertSame('posted', $invoice->journal->status);

        $this->assertSame(6000.0, $this->ar($institute, $branch));
    }

    public function test_generate_invoice_uses_course_defaults_when_no_structure(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Lifecycle Institute');
        $branch = $this->branch($institute, 'Main');
        $this->setupAccounting($institute, $branch);
        $this->autoPost($institute, $branch, true);
        $this->context($institute);

        $course = $this->course($institute, 'Course A', [
            'fee' => 5000,
            'admission_fee' => 1000,
            'exam_fee' => 500,
            'certificate_fee' => 0,
        ]);
        $batch = $this->batch($institute, $branch, $course, 'Batch A1');
        $student = $this->student($institute, $branch, 'Karim');
        $enrollment = $this->enroll($student, $batch);

        $invoice = $this->finance()->generateInvoice(
            $enrollment,
            null,
            ['due_date' => now()->addDays(30)->toDateString()],
        );

        $this->assertSame('course_fee', $invoice->invoice_type);
        $this->assertSame(6500.0, (float) $invoice->payable_amount);
        $this->assertCount(3, $invoice->items);
        $this->assertNull($invoice->items->first()->fee_head_id);
        $this->assertSame('Admission Fee', $invoice->items[0]->description);
        $this->assertSame(1000.0, (float) $invoice->items[0]->amount);
        $this->assertSame('Course / Tuition Fee', $invoice->items[1]->description);
        $this->assertSame(5000.0, (float) $invoice->items[1]->amount);
        $this->assertSame(6500.0, $this->ar($institute, $branch));
    }

    public function test_generate_invoice_applies_enrollment_discount(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Lifecycle Institute');
        $branch = $this->branch($institute, 'Main');
        $this->setupAccounting($institute, $branch);
        $this->autoPost($institute, $branch, true);
        $this->context($institute);

        $course = $this->course($institute, 'Course A');
        $head = $this->feeHead($institute, $branch, 'course_tuition', 'Tuition Fee');
        $batch = $this->batch($institute, $branch, $course, 'Batch A1');
        $student = $this->student($institute, $branch, 'Sabbir');
        $enrollment = $this->enroll($student, $batch, ['discount' => 500]);

        $structure = $this->structure($institute, $branch, $course, [
            ['fee_head_id' => $head->id, 'amount' => 5000],
        ]);

        $invoice = $this->finance()->generateInvoice(
            $enrollment,
            $structure,
            ['due_date' => now()->addDays(30)->toDateString()],
        );

        $this->assertSame(500.0, (float) $invoice->discount);
        $this->assertSame(4500.0, (float) $invoice->payable_amount);
        $this->assertSame(4500.0, (float) $invoice->due_amount);
        $this->assertSame(4500.0, $this->ar($institute, $branch));
    }

    public function test_generate_invoice_blocks_duplicate_billing_unless_allowed(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Lifecycle Institute');
        $branch = $this->branch($institute, 'Main');
        $this->setupAccounting($institute, $branch);
        $this->context($institute);

        $course = $this->course($institute, 'Course A');
        $head = $this->feeHead($institute, $branch, 'course_tuition', 'Tuition Fee');
        $batch = $this->batch($institute, $branch, $course, 'Batch A1');
        $student = $this->student($institute, $branch, 'Nadia');
        $enrollment = $this->enroll($student, $batch);

        $structure = $this->structure($institute, $branch, $course, [
            ['fee_head_id' => $head->id, 'amount' => 5000],
        ]);

        $this->finance()->generateInvoice($enrollment, $structure);

        try {
            $this->finance()->generateInvoice($enrollment, $structure);
            $this->fail('Duplicate open billing for the same structure must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('enrollment', $e->errors());
        }

        $second = $this->finance()->generateInvoice($enrollment, $structure, ['allow_duplicate' => true]);
        $this->assertNotSame(0, (int) $second->id);
        $this->assertSame(2, Invoice::query()->where('enrollment_id', $enrollment->id)->count());
    }

    // ------------------------------------------------------------- Payments

    public function test_partial_full_and_over_payment(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Lifecycle Institute');
        $branch = $this->branch($institute, 'Main');
        $this->setupAccounting($institute, $branch);
        $this->autoPost($institute, $branch, true);
        $this->context($institute);

        $course = $this->course($institute, 'Course A');
        $head = $this->feeHead($institute, $branch, 'course_tuition', 'Tuition Fee');
        $batch = $this->batch($institute, $branch, $course, 'Batch A1');
        $student = $this->student($institute, $branch, 'Jannat');
        $enrollment = $this->enroll($student, $batch);

        $structure = $this->structure($institute, $branch, $course, [
            ['fee_head_id' => $head->id, 'amount' => 10000],
        ]);

        $invoice = $this->finance()->generateInvoice(
            $enrollment,
            $structure,
            ['due_date' => now()->addDays(30)->toDateString()],
        );

        $this->finance()->recordPayment($institute->id, $branch->id, [
            'invoice_id' => $invoice->id,
            'amount' => 4000,
            'payment_method' => 'cash',
        ], $this->user($institute, 'receptionist', 'rec')->id);

        $partial = $invoice->fresh();
        $this->assertSame('partial', $partial->status);
        $this->assertSame(4000.0, (float) $partial->paid_amount);
        $this->assertSame(6000.0, (float) $partial->due_amount);

        try {
            $this->finance()->recordPayment($institute->id, $branch->id, [
                'invoice_id' => $invoice->id,
                'amount' => 6001,
                'payment_method' => 'cash',
            ]);
            $this->fail('Overpayment must be rejected.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }

        $this->finance()->recordPayment($institute->id, $branch->id, [
            'invoice_id' => $invoice->id,
            'amount' => 6000,
            'payment_method' => 'cash',
        ]);

        $paid = $invoice->fresh();
        $this->assertSame('paid', $paid->status);
        $this->assertSame(0.0, (float) $paid->due_amount);
        $this->assertSame(0.0, $this->ar($institute, $branch));

        $metrics = $this->finance()->dashboardMetrics($institute->id, $branch->id);
        $this->assertSame(10000.0, $metrics['billed']);
        $this->assertSame(10000.0, $metrics['collected']);
        $this->assertSame(0.0, $metrics['outstanding']);
    }

    // -------------------------------------------------------------- Waivers

    public function test_waiver_on_draft_journal_voids_and_rebuilds(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Lifecycle Institute');
        $branch = $this->branch($institute, 'Main');
        $this->setupAccounting($institute, $branch);
        $this->autoPost($institute, $branch, false);
        $this->context($institute);

        $course = $this->course($institute, 'Course A');
        $head = $this->feeHead($institute, $branch, 'course_tuition', 'Tuition Fee');
        $batch = $this->batch($institute, $branch, $course, 'Batch A1');
        $student = $this->student($institute, $branch, 'Tanjila');
        $enrollment = $this->enroll($student, $batch);

        $structure = $this->structure($institute, $branch, $course, [
            ['fee_head_id' => $head->id, 'amount' => 5000],
        ], [
            'installments_count' => 3,
        ]);

        $invoice = $this->finance()->generateInvoice(
            $enrollment,
            $structure,
            ['due_date' => now()->addDays(30)->toDateString()],
            $this->user($institute, 'accountant', 'acc')->id,
        );

        $originalJournal = $invoice->journal;
        $this->assertSame('draft', $originalJournal->status);

        $updated = $this->finance()->applyWaiver(
            $invoice,
            1000,
            'Scholarship for meritorious student',
            $institute->id,
            $this->user($institute, 'accountant', 'acc2')->id,
        );

        $this->assertSame('void', $originalJournal->fresh()->status);
        $this->assertSame('draft', $updated->journal->status);
        $this->assertNotSame((int) $originalJournal->id, (int) $updated->journal_id);

        $this->assertSame(1000.0, (float) $updated->discount);
        $this->assertSame(4000.0, (float) $updated->payable_amount);
        $this->assertSame(4000.0, (float) $updated->due_amount);
        $this->assertSame(4000.0, (float) $updated->installments->sum('amount'));

        $waiver = StudentWaiver::query()->where('invoice_id', $invoice->id)->firstOrFail();
        $this->assertSame(1000.0, (float) $waiver->amount);
        $this->assertSame('Scholarship for meritorious student', $waiver->reason);

        $this->assertTrue(
            AccountingAuditTrail::query()->where('institute_id', $institute->id)
                ->where('action', 'waive')
                ->where('entity_type', 'invoice')
                ->where('entity_id', $invoice->id)
                ->exists()
        );

        $this->assertSame(0.0, $this->ar($institute, $branch));
    }

    public function test_waiver_on_posted_journal_reverses_and_reposts(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Lifecycle Institute');
        $branch = $this->branch($institute, 'Main');
        $this->setupAccounting($institute, $branch);
        $this->autoPost($institute, $branch, true);
        $this->context($institute);

        $course = $this->course($institute, 'Course A');
        $head = $this->feeHead($institute, $branch, 'course_tuition', 'Tuition Fee');
        $batch = $this->batch($institute, $branch, $course, 'Batch A1');
        $student = $this->student($institute, $branch, 'Rana');
        $enrollment = $this->enroll($student, $batch);

        $structure = $this->structure($institute, $branch, $course, [
            ['fee_head_id' => $head->id, 'amount' => 5000],
        ], [
            'installments_count' => 3,
        ]);

        $invoice = $this->finance()->generateInvoice(
            $enrollment,
            $structure,
            ['due_date' => now()->addDays(30)->toDateString()],
        );

        $this->assertSame(5000.0, $this->ar($institute, $branch));

        $originalJournal = $invoice->journal;
        $this->assertSame('posted', $originalJournal->status);

        $updated = $this->finance()->applyWaiver(
            $invoice,
            1000,
            'Approved waiver',
            $institute->id,
            $this->user($institute, 'accountant', 'acc')->id,
        );

        $this->assertSame('reversed', $originalJournal->fresh()->status);
        $this->assertTrue(
            Journal::query()
                ->where('institute_id', $institute->id)
                ->where('reversal_of', $originalJournal->id)
                ->where('status', 'posted')
                ->exists()
        );
        $this->assertSame('posted', $updated->journal->status);
        $this->assertNotSame((int) $originalJournal->id, (int) $updated->journal_id);

        $this->assertSame(1000.0, (float) $updated->discount);
        $this->assertSame(4000.0, (float) $updated->payable_amount);
        $this->assertSame(4000.0, (float) $updated->due_amount);
        $this->assertSame(4000.0, (float) $updated->installments->sum('amount'));

        $this->assertSame(4000.0, $this->ar($institute, $branch));
    }

    public function test_waiver_rejected_when_fully_paid(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Lifecycle Institute');
        $branch = $this->branch($institute, 'Main');
        $this->setupAccounting($institute, $branch);
        $this->autoPost($institute, $branch, true);
        $this->context($institute);

        $course = $this->course($institute, 'Course A');
        $head = $this->feeHead($institute, $branch, 'course_tuition', 'Tuition Fee');
        $batch = $this->batch($institute, $branch, $course, 'Batch A1');
        $student = $this->student($institute, $branch, 'Mim');
        $enrollment = $this->enroll($student, $batch);

        $structure = $this->structure($institute, $branch, $course, [
            ['fee_head_id' => $head->id, 'amount' => 1000],
        ]);

        $invoice = $this->finance()->generateInvoice(
            $enrollment,
            $structure,
            ['due_date' => now()->addDays(30)->toDateString()],
        );

        $this->finance()->recordPayment($institute->id, $branch->id, [
            'invoice_id' => $invoice->id,
            'amount' => 1000,
            'payment_method' => 'cash',
        ]);

        try {
            $this->finance()->applyWaiver($invoice, 100, 'Too late', $institute->id);
            $this->fail('Waivers must be rejected on fully paid invoices.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('amount', $e->errors());
        }
    }

    // --------------------------------------------------------------- Refunds

    public function test_refund_reverses_payment_and_excludes_from_collected(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Lifecycle Institute');
        $branch = $this->branch($institute, 'Main');
        $this->setupAccounting($institute, $branch);
        $this->autoPost($institute, $branch, true);
        $this->context($institute);

        $course = $this->course($institute, 'Course A');
        $head = $this->feeHead($institute, $branch, 'course_tuition', 'Tuition Fee');
        $batch = $this->batch($institute, $branch, $course, 'Batch A1');
        $student = $this->student($institute, $branch, 'Sujon');
        $enrollment = $this->enroll($student, $batch);

        $structure = $this->structure($institute, $branch, $course, [
            ['fee_head_id' => $head->id, 'amount' => 10000],
        ]);

        $invoice = $this->finance()->generateInvoice(
            $enrollment,
            $structure,
            ['due_date' => now()->addDays(30)->toDateString()],
        );

        $payment = $this->finance()->recordPayment($institute->id, $branch->id, [
            'invoice_id' => $invoice->id,
            'amount' => 4000,
            'payment_method' => 'cash',
        ], $this->user($institute, 'accountant', 'acc')->id);

        $this->assertSame(6000.0, (float) $invoice->fresh()->due_amount);
        $this->assertSame(6000.0, $this->ar($institute, $branch));

        $this->finance()->refundPayment(
            $payment,
            $institute->id,
            $this->user($institute, 'accountant', 'acc2')->id,
            'Wrong payment received',
        );

        $restored = $invoice->fresh();
        $this->assertSame(0.0, (float) $restored->paid_amount);
        $this->assertSame(10000.0, (float) $restored->due_amount);
        $this->assertSame('unpaid', $restored->status);

        $this->assertSame('reversed', $payment->journal->fresh()->status);
        $this->assertSame(10000.0, $this->ar($institute, $branch));

        $metrics = $this->finance()->dashboardMetrics($institute->id, $branch->id);
        $this->assertSame(0.0, $metrics['collected']);
        $this->assertSame(10000.0, $metrics['outstanding']);
    }

    // ------------------------------------------------------- Ledger + reports

    public function test_student_ledger_aggregates_and_overdue(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Lifecycle Institute');
        $branch = $this->branch($institute, 'Main');
        $this->setupAccounting($institute, $branch);
        $this->autoPost($institute, $branch, true);
        $this->context($institute);

        $course = $this->course($institute, 'Course A');
        $head = $this->feeHead($institute, $branch, 'course_tuition', 'Tuition Fee');
        $batch = $this->batch($institute, $branch, $course, 'Batch A1');
        $student = $this->student($institute, $branch, 'Shanta');
        $enrollment = $this->enroll($student, $batch);

        $structure = $this->structure($institute, $branch, $course, [
            ['fee_head_id' => $head->id, 'amount' => 5000],
        ]);

        $invoiceA = $this->finance()->generateInvoice(
            $enrollment,
            $structure,
            ['due_date' => now()->addDays(30)->toDateString()],
        );
        $this->finance()->recordPayment($institute->id, $branch->id, [
            'invoice_id' => $invoiceA->id,
            'amount' => 2000,
            'payment_method' => 'cash',
        ]);

        $this->finance()->applyWaiver($invoiceA, 1000, 'Merit waiver', $institute->id);

        $invoiceB = $this->finance()->generateInvoice(
            $enrollment,
            $structure,
            ['due_date' => now()->subDays(5)->toDateString(), 'allow_duplicate' => true],
        );

        $ledger = $this->finance()->ledgerForStudent($institute->id, $student->id, $branch->id);

        $this->assertCount(2, $ledger['invoices']);
        $this->assertCount(1, $ledger['waivers']);
        $this->assertSame(1000.0, (float) $ledger['waivers'][0]['amount']);

        $totals = $ledger['totals'];
        $this->assertSame(9000.0, $totals['billed']);
        $this->assertSame(2000.0, $totals['collected']);
        $this->assertSame(1000.0, $totals['waivedTotal']);

        $invoiceARow = collect($ledger['invoices'])->firstWhere('id', $invoiceA->id);
        $this->assertSame(2000.0, $invoiceARow['due_amount']);
        $this->assertFalse($invoiceARow['payments'][0]['reversed']);

        $this->assertSame(7000.0, $totals['outstanding']);
        $this->assertSame(5000.0, $totals['overdue']);
    }

    public function test_dashboard_metrics_are_branch_scoped(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Lifecycle Institute');
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $this->setupAccounting($institute, $branchA);
        $this->setupAccounting($institute, $branchB);
        $this->autoPost($institute, $branchA, true);
        $this->autoPost($institute, $branchB, true);
        $this->context($institute);

        $course = $this->course($institute, 'Course A');
        $head = $this->feeHead($institute, $branchA, 'course_tuition', 'Tuition Fee');
        $headB = $this->feeHead($institute, $branchB, 'course_tuition', 'Tuition Fee B');

        $batchA = $this->batch($institute, $branchA, $course, 'Batch A1');
        $batchB = $this->batch($institute, $branchB, $course, 'Batch B1');
        $studentA = $this->student($institute, $branchA, 'S1');
        $studentB = $this->student($institute, $branchB, 'S2');
        $enrollA = $this->enroll($studentA, $batchA);
        $enrollB = $this->enroll($studentB, $batchB);

        $structureA = $this->structure($institute, $branchA, $course, [
            ['fee_head_id' => $head->id, 'amount' => 5000],
        ]);
        $structureB = $this->structure($institute, $branchB, $course, [
            ['fee_head_id' => $headB->id, 'amount' => 3000],
        ]);

        $invoiceA = $this->finance()->generateInvoice($enrollA, $structureA, ['due_date' => now()->addDays(30)->toDateString()]);
        $this->finance()->recordPayment($institute->id, $branchA->id, [
            'invoice_id' => $invoiceA->id,
            'amount' => 1000,
            'payment_method' => 'cash',
        ]);
        $this->finance()->applyWaiver($invoiceA, 500, 'Waiver A', $institute->id);

        $invoiceB = $this->finance()->generateInvoice($enrollB, $structureB, ['due_date' => now()->subDays(3)->toDateString()]);

        $metricsA = $this->finance()->dashboardMetrics($institute->id, $branchA->id);
        $this->assertSame(4500.0, $metricsA['billed']);
        $this->assertSame(1000.0, $metricsA['collected']);
        $this->assertSame(3500.0, $metricsA['outstanding']);
        $this->assertSame(0.0, $metricsA['overdue']);
        $this->assertSame(500.0, $metricsA['discounts']);
        $this->assertSame(1, $metricsA['waiver_count']);
        $this->assertSame(500.0, $metricsA['waiver_amount']);

        $metricsB = $this->finance()->dashboardMetrics($institute->id, $branchB->id);
        $this->assertSame(3000.0, $metricsB['billed']);
        $this->assertSame(0.0, $metricsB['collected']);
        $this->assertSame(3000.0, $metricsB['outstanding']);
        $this->assertSame(3000.0, $metricsB['overdue']);
        $this->assertSame(0, $metricsB['waiver_count']);

        $metricsAll = $this->finance()->dashboardMetrics($institute->id);
        $this->assertSame(7500.0, $metricsAll['billed']);
    }

    public function test_batch_and_course_reports(): void
    {
        $country = $this->country();
        $institute = $this->institute($country, 'Lifecycle Institute');
        $branchA = $this->branch($institute, 'Branch A');
        $branchB = $this->branch($institute, 'Branch B');
        $this->setupAccounting($institute, $branchA);
        $this->setupAccounting($institute, $branchB);
        $this->autoPost($institute, $branchA, true);
        $this->autoPost($institute, $branchB, true);
        $this->context($institute);

        $course = $this->course($institute, 'Course A');
        $headA = $this->feeHead($institute, $branchA, 'course_tuition', 'Tuition A');
        $headB = $this->feeHead($institute, $branchB, 'course_tuition', 'Tuition B');

        $batchA = $this->batch($institute, $branchA, $course, 'Batch A1');
        $batchB = $this->batch($institute, $branchB, $course, 'Batch B1');
        $studentA = $this->student($institute, $branchA, 'S1');
        $studentB = $this->student($institute, $branchB, 'S2');
        $enrollA = $this->enroll($studentA, $batchA);
        $enrollB = $this->enroll($studentB, $batchB);

        $structureA = $this->structure($institute, $branchA, $course, [
            ['fee_head_id' => $headA->id, 'amount' => 5000],
        ]);
        $structureB = $this->structure($institute, $branchB, $course, [
            ['fee_head_id' => $headB->id, 'amount' => 4000],
        ]);

        $invoiceA = $this->finance()->generateInvoice($enrollA, $structureA, ['due_date' => now()->addDays(30)->toDateString()]);
        $this->finance()->recordPayment($institute->id, $branchA->id, [
            'invoice_id' => $invoiceA->id,
            'amount' => 2000,
            'payment_method' => 'cash',
        ]);

        $this->finance()->generateInvoice($enrollB, $structureB, ['due_date' => now()->addDays(10)->toDateString()]);

        $batches = $this->finance()->batchSummary($institute->id);
        $this->assertCount(2, $batches);

        $rowA = $batches->firstWhere('id', $batchA->id);
        $this->assertSame(5000.0, (float) $rowA->billed);
        $this->assertSame(3000.0, (float) $rowA->outstanding);
        $this->assertSame(1, (int) $rowA->student_count);
        $this->assertSame(1, (int) $rowA->invoice_count);

        $rowB = $batches->firstWhere('id', $batchB->id);
        $this->assertSame(4000.0, (float) $rowB->billed);
        $this->assertSame(4000.0, (float) $rowB->outstanding);

        $courses = $this->finance()->courseSummary($institute->id);
        $this->assertCount(1, $courses);
        $this->assertSame(2, (int) $courses->first()->student_count);
        $this->assertSame(9000.0, (float) $courses->first()->billed);
        $this->assertSame(7000.0, (float) $courses->first()->outstanding);

        $branchAOnly = $this->finance()->batchSummary($institute->id, $branchA->id);
        $this->assertCount(1, $branchAOnly);
        $this->assertSame((int) $batchA->id, (int) $branchAOnly->first()->id);
    }
}
