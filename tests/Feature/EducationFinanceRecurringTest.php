<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\Batch;
use App\Models\Branch;
use App\Models\AccountGroup;
use App\Models\ChartOfAccount;
use App\Models\Course;
use App\Models\Currency;
use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\FeeStructureItem;
use App\Models\FiscalYear;
use App\Models\Institute;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MonthlyFeePeriod;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\Education\MonthlyFeeGenerationService;
use App\Services\Education\StudentFinanceService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EducationFinanceRecurringTest extends TestCase
{
    use DatabaseTransactions;

    protected Institute $institute;
    protected Branch $branch;
    protected AcademicYear $year;
    protected Course $course;
    protected Batch $batch;
    protected Student $student;
    protected StudentEnrollment $enrollment;
    protected FeeHead $recurringHead;
    protected FeeHead $oneTimeHead;
    protected FeeStructure $structure;

    protected function setUp(): void
    {
        parent::setUp();

        TenantContext::clear();
        BranchContext::clear();

        $this->institute = Institute::firstOrCreate(
            ['slug' => 'test-finance-recurring'],
            ['name' => 'Test Finance Recurring', 'slug' => 'test-finance-recurring', 'status' => 'active']
        );

        $this->branch = Branch::firstOrCreate(
            ['institute_id' => $this->institute->id, 'name' => 'Main Branch'],
            ['institute_id' => $this->institute->id, 'name' => 'Main Branch', 'status' => 'active']
        );

        TenantContext::set($this->institute->id);
        BranchContext::set($this->branch->id);

        $this->year = AcademicYear::firstOrCreate(
            ['institute_id' => $this->institute->id, 'code' => '2026'],
            ['name' => '2026', 'start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_current' => true, 'status' => true]
        );

        FiscalYear::firstOrCreate(
            ['institute_id' => $this->institute->id, 'name' => 'FY2026'],
            [
                'institute_id' => $this->institute->id,
                'branch_id' => $this->branch->id,
                'name' => 'FY2026',
                'start_date' => '2026-01-01',
                'end_date' => '2026-12-31',
                'status' => 'open',
                'is_current' => true,
            ]
        );

        $this->course = Course::firstOrCreate(
            ['institute_id' => $this->institute->id, 'course_code' => 'CS-REC'],
            ['institute_id' => $this->institute->id, 'name' => 'Computer Science Recurring', 'course_code' => 'CS-REC', 'status' => 'active']
        );

        $this->batch = Batch::firstOrCreate(
            ['institute_id' => $this->institute->id, 'course_id' => $this->course->id, 'name' => 'Morning Batch Recurring'],
            ['institute_id' => $this->institute->id, 'course_id' => $this->course->id, 'name' => 'Morning Batch Recurring', 'status' => 'ongoing', 'academic_year_id' => $this->year->id, 'branch_id' => $this->branch->id, 'batch_code' => 'MBR-REC-01', 'start_date' => '2026-01-01']
        );

        $this->student = Student::create([
            'institute_id' => $this->institute->id,
            'branch_id' => $this->branch->id,
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => 'test-student-recurring-'.time().'@example.com',
            'phone' => '0170000'.rand(1000, 9999),
            'status' => 'active',
            'admission_status' => 'enrolled',
            'application_date' => '2026-01-01',
            'student_id_number' => 'STU-REC-'.rand(10000, 99999),
            'admission_date' => '2026-01-01',
        ]);

        $this->enrollment = StudentEnrollment::create([
            'institute_id' => $this->institute->id,
            'student_id' => $this->student->id,
            'course_id' => $this->course->id,
            'batch_id' => $this->batch->id,
            'status' => 'active',
            'roll_number' => 'R001',
            'enrollment_date' => '2026-01-01',
        ]);

        $incomeAccount = ChartOfAccount::firstOrCreate(
            ['institute_id' => $this->institute->id, 'code' => '4001-REC'],
            ['name' => 'Course Fees Income Recurring', 'type' => 'income', 'is_active' => true, 'account_group_id' => $this->getOrCreateAccountGroup($this->institute->id, 'income'), 'branch_id' => $this->branch->id]
        );

        $cashAccount = ChartOfAccount::firstOrCreate(
            ['institute_id' => $this->institute->id, 'code' => '1010-REC'],
            ['name' => 'Cash in Hand Recurring', 'type' => 'asset', 'is_active' => true, 'account_group_id' => $this->getOrCreateAccountGroup($this->institute->id, 'asset'), 'branch_id' => $this->branch->id]
        );

        ChartOfAccount::firstOrCreate(
            ['institute_id' => $this->institute->id, 'code' => '1100'],
            ['name' => 'Accounts Receivable Recurring', 'type' => 'asset', 'is_active' => true, 'is_receivable' => true, 'account_group_id' => $this->getOrCreateAccountGroup($this->institute->id, 'asset'), 'branch_id' => $this->branch->id]
        );

        ChartOfAccount::firstOrCreate(
            ['institute_id' => $this->institute->id, 'code' => '4100-REC'],
            ['name' => 'Tuition Income Recurring', 'type' => 'income', 'is_active' => true, 'account_group_id' => $this->getOrCreateAccountGroup($this->institute->id, 'income'), 'branch_id' => $this->branch->id]
        );

        \App\Models\PaymentMethod::firstOrCreate(
            ['institute_id' => $this->institute->id, 'name' => 'Cash Recurring'],
            ['coa_id' => $cashAccount->id, 'is_active' => true]
        );

        $this->recurringHead = FeeHead::firstOrCreate(
            ['institute_id' => $this->institute->id, 'code' => 'CF-REC'],
            [
                'name' => 'Course Fee Recurring',
                'code' => 'CF-REC',
                'type' => 'course_tuition',
                'default_amount' => 1000,
                'is_recurring' => true,
                'billing_frequency' => 'monthly',
                'is_active' => true,
            ]
        );

        $this->oneTimeHead = FeeHead::firstOrCreate(
            ['institute_id' => $this->institute->id, 'code' => 'AF-REC'],
            [
                'name' => 'Admission Fee Recurring',
                'code' => 'AF-REC',
                'type' => 'admission',
                'default_amount' => 500,
                'is_recurring' => false,
                'billing_frequency' => 'one_time',
                'is_active' => true,
            ]
        );

        $this->structure = FeeStructure::firstOrCreate(
            ['institute_id' => $this->institute->id, 'name' => 'Standard Plan Recurring'],
            [
                'course_id' => $this->course->id,
                'academic_year_id' => $this->year->id,
                'status' => 'active',
                'auto_generate_monthly' => true,
                'billing_frequency' => 'monthly',
                'installments_count' => 1,
                'installments_interval_days' => 30,
            ]
        );

        FeeStructureItem::firstOrCreate(
            ['fee_structure_id' => $this->structure->id, 'fee_head_id' => $this->recurringHead->id],
            ['amount' => 1000, 'is_optional' => false]
        );

        FeeStructureItem::firstOrCreate(
            ['fee_structure_id' => $this->structure->id, 'fee_head_id' => $this->oneTimeHead->id],
            ['amount' => 500, 'is_optional' => false]
        );
    }

    protected function getOrCreateAccountGroup(int $instituteId, string $category): int
    {
        $group = \App\Models\AccountGroup::firstOrCreate(
            ['institute_id' => $instituteId, 'code' => strtoupper($category).'-GRP'],
            ['name' => ucfirst($category).' Group', 'category' => $category]
        );
        return $group->id;
    }

    public function test_fee_head_recurring_fields(): void
    {
        $this->assertTrue($this->recurringHead->is_recurring);
        $this->assertEquals('monthly', $this->recurringHead->billing_frequency);
        $this->assertEquals('Monthly', $this->recurringHead->billingFrequencyLabel());
    }

    public function test_fee_structure_auto_generate_monthly(): void
    {
        $this->assertTrue($this->structure->auto_generate_monthly);
        $this->assertEquals('monthly', $this->structure->billing_frequency);
        $this->assertEquals('Monthly', $this->structure->billingFrequencyLabel());
    }

    public function test_monthly_fee_period_unique_constraint(): void
    {
        MonthlyFeePeriod::create([
            'institute_id' => $this->institute->id,
            'branch_id' => $this->branch->id,
            'fee_structure_id' => $this->structure->id,
            'student_id' => $this->student->id,
            'enrollment_id' => $this->enrollment->id,
            'period_month' => '2026-01',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('monthly_fee_periods', [
            'institute_id' => $this->institute->id,
            'period_month' => '2026-01-01',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        MonthlyFeePeriod::create([
            'institute_id' => $this->institute->id,
            'branch_id' => $this->branch->id,
            'fee_structure_id' => $this->structure->id,
            'student_id' => $this->student->id,
            'enrollment_id' => $this->enrollment->id,
            'period_month' => '2026-01-01',
            'status' => 'pending',
        ]);
    }

    public function test_generate_monthly_invoices(): void
    {
        $this->assertEquals('active', $this->structure->status);
        $this->assertTrue((bool) $this->structure->auto_generate_monthly);

        $service = app(MonthlyFeeGenerationService::class);
        $result = $service->generate($this->institute->id, $this->branch->id, '2026-01');

        $this->assertArrayHasKey('invoices_generated', $result);
        $this->assertGreaterThan(0, $result['invoices_generated'], 'structures_processed=' . ($result['structures_processed'] ?? '?') . ' students_checked=' . ($result['students_checked'] ?? '?') . ' errors=' . json_encode($result['errors'] ?? []));

        $this->assertDatabaseHas('monthly_fee_periods', [
            'institute_id' => $this->institute->id,
            'period_month' => '2026-01-01',
            'status' => 'generated',
        ]);

        $this->assertDatabaseHas('invoices', [
            'institute_id' => $this->institute->id,
            'student_id' => $this->student->id,
            'status' => 'unpaid',
        ]);
    }

    public function test_generate_invoices_is_idempotent(): void
    {
        $service = app(MonthlyFeeGenerationService::class);
        $result1 = $service->generate($this->institute->id, $this->branch->id, '2026-01');
        $result2 = $service->generate($this->institute->id, $this->branch->id, '2026-01');

        $this->assertGreaterThan(0, $result1['invoices_generated']);
        $this->assertEquals(0, $result2['invoices_generated']);

        $invoiceCount = Invoice::where('institute_id', $this->institute->id)
            ->where('student_id', $this->student->id)
            ->count();
        $this->assertEquals(1, $invoiceCount);
    }

    public function test_generate_multiple_months_carry_forward(): void
    {
        $service = app(MonthlyFeeGenerationService::class);
        $service->generate($this->institute->id, $this->branch->id, '2026-01');
        $service->generate($this->institute->id, $this->branch->id, '2026-02');
        $service->generate($this->institute->id, $this->branch->id, '2026-03');

        $invoices = Invoice::where('institute_id', $this->institute->id)
            ->where('student_id', $this->student->id)
            ->orderBy('id')
            ->get();

        $this->assertEquals(3, $invoices->count());

        foreach ($invoices as $invoice) {
            $this->assertEquals(1000.00, (float) $invoice->payable_amount);
            $this->assertEquals(0.00, (float) $invoice->paid_amount);
            $this->assertEquals(1000.00, (float) $invoice->due_amount);
        }
    }

    public function test_carry_forward_payment_oldest_first(): void
    {
        $service = app(MonthlyFeeGenerationService::class);
        $service->generate($this->institute->id, $this->branch->id, '2026-01');
        $service->generate($this->institute->id, $this->branch->id, '2026-02');
        $service->generate($this->institute->id, $this->branch->id, '2026-03');

        $janInvoice = Invoice::where('institute_id', $this->institute->id)
            ->where('student_id', $this->student->id)
            ->first();

        $this->assertNotNull($janInvoice);

        $janInvoice->update([
            'paid_amount' => 500,
            'due_amount' => 500,
            'status' => 'partial',
        ]);

        $janInvoice->refresh();
        $this->assertEquals(500.00, (float) $janInvoice->paid_amount);
        $this->assertEquals('partial', $janInvoice->status);

        $allInvoices = Invoice::where('institute_id', $this->institute->id)
            ->where('student_id', $this->student->id)
            ->orderBy('id')
            ->get();

        $totalPaid = $allInvoices->sum('paid_amount');
        $this->assertEquals(500.00, (float) $totalPaid);
    }

    public function test_receipt_number_generation(): void
    {
        $financeService = app(StudentFinanceService::class);
        $receipt = $financeService->allocateReceiptNumber($this->institute->id);

        $this->assertMatchesRegularExpression('/^RCP-\d{8}-[A-Z0-9]{5}$/', $receipt);
    }

    public function test_receipt_numbers_are_unique(): void
    {
        $financeService = app(StudentFinanceService::class);
        $receipts = [];
        for ($i = 0; $i < 50; $i++) {
            $receipts[] = $financeService->allocateReceiptNumber($this->institute->id);
        }
        $this->assertEquals(50, count(array_unique($receipts)));
    }

    public function test_fee_collection_data(): void
    {
        $service = app(MonthlyFeeGenerationService::class);
        $currentMonth = now()->format('Y-m-01');
        $service->generate($this->institute->id, $this->branch->id, $currentMonth);

        $financeService = app(StudentFinanceService::class);
        $data = $financeService->feeCollectionData(
            $this->institute->id,
            $this->student->id,
            $this->branch->id
        );

        $this->assertEquals(1000.00, $data['current_month']);
        $this->assertEquals(0.00, $data['previous_due']);
        $this->assertEquals(1000.00, $data['total_outstanding']);
        $this->assertCount(1, $data['invoices']);
    }

    public function test_fee_structure_auto_generate_disabled(): void
    {
        $this->structure->update(['auto_generate_monthly' => false]);

        $manualStructure = FeeStructure::create([
            'institute_id' => $this->institute->id,
            'name' => 'Manual Plan',
            'course_id' => $this->course->id,
            'academic_year_id' => $this->year->id,
            'status' => 'active',
            'auto_generate_monthly' => false,
            'billing_frequency' => 'monthly',
            'installments_count' => 1,
            'installments_interval_days' => 30,
        ]);

        FeeStructureItem::create([
            'fee_structure_id' => $manualStructure->id,
            'fee_head_id' => $this->recurringHead->id,
            'amount' => 1000,
            'is_optional' => false,
        ]);

        $service = app(MonthlyFeeGenerationService::class);
        $service->generate($this->institute->id, $this->branch->id, '2026-01');

        $invoiceCount = Invoice::where('institute_id', $this->institute->id)
            ->where('student_id', $this->student->id)
            ->count();
        $this->assertEquals(0, $invoiceCount);
    }

    public function test_one_time_heads_excluded_from_recurring(): void
    {
        $service = app(MonthlyFeeGenerationService::class);
        $service->generate($this->institute->id, $this->branch->id, '2026-01');

        $invoice = Invoice::where('institute_id', $this->institute->id)
            ->where('student_id', $this->student->id)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertEquals(1000.00, (float) $invoice->payable_amount);

        $items = InvoiceItem::where('invoice_id', $invoice->id)->get();
        $this->assertEquals(1, $items->count());
        $this->assertEquals($this->recurringHead->id, $items->first()->fee_head_id);
    }

    public function test_monthly_fee_period_status_transitions(): void
    {
        $period = MonthlyFeePeriod::create([
            'institute_id' => $this->institute->id,
            'branch_id' => $this->branch->id,
            'fee_structure_id' => $this->structure->id,
            'student_id' => $this->student->id,
            'enrollment_id' => $this->enrollment->id,
            'period_month' => '2026-01',
            'status' => 'pending',
        ]);

        $this->assertEquals('pending', $period->status);

        $period->status = 'generated';
        $period->save();
        $this->assertEquals('generated', $period->fresh()->status);
    }

    public function test_invoice_meta_billing_period(): void
    {
        $service = app(MonthlyFeeGenerationService::class);
        $service->generate($this->institute->id, $this->branch->id, '2026-01');

        $invoice = Invoice::where('institute_id', $this->institute->id)
            ->where('student_id', $this->student->id)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertEquals('2026-01-01', $invoice->invoice_meta['billing_period']);
        $this->assertTrue((bool) $invoice->invoice_meta['is_recurring']);
    }

    public function test_different_students_get_separate_invoices(): void
    {
        $student2 = Student::create([
            'institute_id' => $this->institute->id,
            'branch_id' => $this->branch->id,
            'first_name' => 'Second',
            'last_name' => 'Student',
            'email' => 'second-student-recurring-'.time().'@example.com',
            'phone' => '0170000'.rand(1000, 9999),
            'status' => 'active',
            'admission_status' => 'enrolled',
            'application_date' => '2026-01-01',
            'student_id_number' => 'STU-REC2-'.rand(10000, 99999),
            'admission_date' => '2026-01-01',
        ]);

        StudentEnrollment::create([
            'institute_id' => $this->institute->id,
            'student_id' => $student2->id,
            'course_id' => $this->course->id,
            'batch_id' => $this->batch->id,
            'status' => 'active',
            'roll_number' => 'R002',
            'enrollment_date' => '2026-01-01',
        ]);

        $service = app(MonthlyFeeGenerationService::class);
        $service->generate($this->institute->id, $this->branch->id, '2026-01');

        $invoice1 = Invoice::where('student_id', $this->student->id)->first();
        $invoice2 = Invoice::where('student_id', $student2->id)->first();

        $this->assertNotNull($invoice1);
        $this->assertNotNull($invoice2);
        $this->assertNotEquals($invoice1->id, $invoice2->id);
    }

    public function test_inactive_enrollment_excluded(): void
    {
        $student2 = Student::create([
            'institute_id' => $this->institute->id,
            'branch_id' => $this->branch->id,
            'first_name' => 'Inactive',
            'last_name' => 'Enrollment',
            'email' => 'inactive-enroll-recurring-'.time().'@example.com',
            'phone' => '0170000'.rand(1000, 9999),
            'status' => 'active',
            'admission_status' => 'enrolled',
            'application_date' => '2026-01-01',
            'student_id_number' => 'STU-REC3-'.rand(10000, 99999),
            'admission_date' => '2026-01-01',
        ]);

        StudentEnrollment::create([
            'institute_id' => $this->institute->id,
            'student_id' => $student2->id,
            'course_id' => $this->course->id,
            'batch_id' => $this->batch->id,
            'status' => 'dropped',
            'roll_number' => 'R003',
            'enrollment_date' => '2026-01-01',
        ]);

        $service = app(MonthlyFeeGenerationService::class);
        $service->generate($this->institute->id, $this->branch->id, '2026-01');

        $invoice = Invoice::where('student_id', $student2->id)->first();
        $this->assertNull($invoice);
    }

    public function test_tenant_isolation(): void
    {
        $otherInstitute = Institute::create([
            'name' => 'Other Test Institute',
            'slug' => 'other-test-recurring-'.time(),
            'status' => 'active',
        ]);

        $otherBranch = Branch::create([
            'institute_id' => $otherInstitute->id,
            'name' => 'Other Branch',
            'status' => 'active',
        ]);

        $otherStudent = Student::create([
            'institute_id' => $otherInstitute->id,
            'branch_id' => $otherBranch->id,
            'first_name' => 'Other',
            'last_name' => 'Student',
            'email' => 'other-tenant-recurring-'.time().'@example.com',
            'phone' => '0170000'.rand(1000, 9999),
            'status' => 'active',
            'admission_status' => 'enrolled',
            'application_date' => '2026-01-01',
            'student_id_number' => 'STU-REC4-'.rand(10000, 99999),
            'admission_date' => '2026-01-01',
        ]);

        $service = app(MonthlyFeeGenerationService::class);
        $result = $service->generate($this->institute->id, $this->branch->id, '2026-01');

        $otherInvoice = Invoice::where('student_id', $otherStudent->id)->first();
        $this->assertNull($otherInvoice);
    }

    public function test_invoice_number_format(): void
    {
        $service = app(MonthlyFeeGenerationService::class);
        $service->generate($this->institute->id, $this->branch->id, '2026-01');

        $invoice = Invoice::where('institute_id', $this->institute->id)->first();
        $this->assertMatchesRegularExpression('/^INV-\d{8}-[A-Z0-9]{5}$/', $invoice->invoice_number);
    }

    public function test_structure_course_match_required(): void
    {
        $service = app(MonthlyFeeGenerationService::class);
        $result = $service->generate($this->institute->id, $this->branch->id, '2026-01');

        $invoice = Invoice::where('institute_id', $this->institute->id)
            ->where('student_id', $this->student->id)
            ->first();
        $this->assertNotNull($invoice);
    }
}
