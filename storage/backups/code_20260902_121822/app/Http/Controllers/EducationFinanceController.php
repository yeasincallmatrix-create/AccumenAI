<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\FeeStructure;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\Student;
use App\Models\Training\Enrollment;
use App\Services\Accounting\AccountingSetupService;
use App\Services\Education\StudentFinanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Education finance (Step 37): dashboard metrics, the student finance ledger,
 * billing/payment/waiver/refund actions and the batch/course reports. All
 * money movements are delegated to the finance core services; this controller
 * only shapes requests and responses.
 */
class EducationFinanceController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly StudentFinanceService $service) {}

    public function dashboard(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.education.dashboard', [
            'institute' => $institute,
            'metrics' => $this->service->dashboardMetrics($institute->id, $this->actingBranchId($request)),
            'baseCurrency' => $this->baseCurrency($request),
        ]);
    }

    public function students(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.education.students.index', [
            'institute' => $institute,
            'students' => $this->service->studentSummary(
                $institute->id,
                $this->actingBranchId($request),
                $request->query('q'),
            ),
            'filters' => $request->query(),
        ]);
    }

    public function studentShow(Request $request, Student $student): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $enrollments = $student->enrollments()
            ->with(['course', 'batch'])
            ->orderByDesc('id')
            ->get();

        $enrollment = $enrollments->first();

        $structures = collect();
        if ($enrollment !== null) {
            $structures = FeeStructure::query()
                ->where('status', FeeStructure::STATUS_ACTIVE)
                ->with(['items.feeHead'])
                ->orderByDesc('id')
                ->get()
                ->filter(fn (FeeStructure $structure) => $this->structureAppliesTo($structure, $enrollment));
        }

        return view('institute.finance.education.students.show', [
            'institute' => $institute,
            'student' => $student,
            'ledger' => $this->service->ledgerForStudent($institute->id, (int) $student->id, $branchId),
            'enrollments' => $enrollments,
            'structures' => $structures,
            'paymentMethods' => PaymentMethod::query()
                ->where('institute_id', $institute->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'baseCurrency' => $this->baseCurrency($request),
        ]);
    }

    public function generateInvoice(Request $request, Student $student): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $data = $request->validate([
            'enrollment_id' => ['required', 'integer'],
            'fee_structure_id' => ['nullable', 'integer'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'include_optional' => ['nullable', 'boolean'],
            'allow_duplicate' => ['nullable', 'boolean'],
            'due_date' => ['nullable', 'date'],
        ]);

        $enrollment = Enrollment::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $institute->id)
            ->where('student_id', $student->id)
            ->findOrFail((int) $data['enrollment_id']);

        $structure = null;
        if (filled($data['fee_structure_id'] ?? null)) {
            $structure = FeeStructure::query()
                ->withoutGlobalScopes()
                ->where('institute_id', $institute->id)
                ->findOrFail((int) $data['fee_structure_id']);
        }

        $invoice = $this->service->generateInvoice($enrollment, $structure, [
            'branch_id' => $branchId ?? $enrollment->batch?->branch_id,
            'discount' => $data['discount'] ?? null,
            'include_optional' => (bool) ($data['include_optional'] ?? false),
            'allow_duplicate' => (bool) ($data['allow_duplicate'] ?? false),
            'due_date' => $data['due_date'] ?? null,
        ], (int) $this->actorId($request));

        return redirect()
            ->route('finance.education.students.show', $student)
            ->with('status', 'Invoice '.$invoice->invoice_number.' generated.');
    }

    public function recordPayment(Request $request, Student $student): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(['cash', 'bkash', 'nagad', 'rocket', 'bank', 'card', 'other'])],
            'payment_method_id' => ['nullable', 'integer'],
            'installment_id' => ['nullable', 'integer'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
        ]);

        $this->assertInvoiceOwnedByStudent($institute->id, $student, (int) $data['invoice_id']);

        $payment = $this->service->recordPayment(
            $institute->id,
            $this->actingBranchId($request),
            $data,
            (int) $this->actorId($request),
        );

        return redirect()
            ->route('finance.education.students.show', $student)
            ->with('status', 'Payment of '.number_format((float) $payment->amount, 2).' recorded.');
    }

    public function reversePayment(Request $request, Payment $payment): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $reason = $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null;

        $this->service->refundPayment(
            $payment,
            $institute->id,
            (int) $this->actorId($request),
            $reason,
        );

        return redirect()
            ->route('finance.education.students.show', $payment->student_id)
            ->with('status', 'Payment reversed and refunded (receipt journal reversed).');
    }

    public function applyWaiver(Request $request, Invoice $invoice): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $invoice = $this->service->applyWaiver(
            $invoice,
            (float) $data['amount'],
            $data['reason'] ?? null,
            $institute->id,
            (int) $this->actorId($request),
        );

        return redirect()
            ->route('finance.education.students.show', $invoice->student_id)
            ->with('status', 'Waiver of '.number_format((float) $data['amount'], 2).' approved and applied.');
    }

    public function batches(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.education.reports.batches', [
            'institute' => $institute,
            'rows' => $this->service->batchSummary($institute->id, $this->actingBranchId($request)),
        ]);
    }

    public function courses(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.education.reports.courses', [
            'institute' => $institute,
            'rows' => $this->service->courseSummary($institute->id, $this->actingBranchId($request)),
        ]);
    }

    // --------------------------------------------------------- Receipt / Slip

    public function printReceipt(Request $request, Payment $payment): View
    {
        $institute = $this->requireInstitute($request);

        if ((int) $payment->institute_id !== $institute->id) {
            abort(403, 'Unauthorized access to payment receipt.');
        }

        if ($payment->receipt_number === null) {
            \Illuminate\Support\Facades\DB::transaction(function () use ($payment, $institute) {
                $payment->refresh();
                if ($payment->receipt_number === null) {
                    $receiptNumber = $this->service->allocateReceiptNumber($institute->id);
                    $payment->forceFill([
                        'receipt_number' => $receiptNumber,
                        'receipt_printed_at' => now(),
                    ])->save();
                } else {
                    $payment->forceFill(['receipt_printed_at' => now()])->save();
                }
            });
        } else {
            \Illuminate\Support\Facades\DB::transaction(function () use ($payment) {
                $payment->refresh();
                $payment->forceFill(['receipt_printed_at' => now()])->save();
            });
        }

        $payment->refresh();
        $payment->load(['invoice.items.feeHead', 'student', 'invoice.enrollment.course', 'invoice.enrollment.batch.academicYear']);

        $this->audit->log($institute->id, [
            'branch_id' => $this->actingBranchId($request),
            'actor_type' => 'user',
            'actor_id' => $this->actorId($request),
            'action' => 'receipt_generated',
            'entity_type' => 'payment',
            'entity_id' => $payment->id,
            'after_payload' => [
                'receipt_number' => $payment->receipt_number,
            ],
        ]);

        return view('institute.finance.education.receipts.payment-slip', [
            'institute' => $institute,
            'payment' => $payment,
            'baseCurrency' => $this->baseCurrency($request),
        ]);
    }

    // ------------------------------------------------------- Fee Collection

    public function feeCollection(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.finance.education.fee-collection', [
            'institute' => $institute,
            'paymentMethods' => PaymentMethod::query()
                ->where('institute_id', $institute->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'baseCurrency' => $this->baseCurrency($request),
        ]);
    }

    public function feeCollectionStudent(Request $request, Student $student): View
    {
        $institute = $this->requireInstitute($request);

        $data = $this->service->feeCollectionData(
            $institute->id,
            (int) $student->id,
            $this->actingBranchId($request),
        );

        $data['paymentMethods'] = PaymentMethod::query()
            ->where('institute_id', $institute->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        $data['baseCurrency'] = $this->baseCurrency($request);
        $data['institute'] = $institute;

        return view('institute.finance.education.fee-collection', $data);
    }

    public function collectFee(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);

        $data = $request->validate([
            'student_id' => ['required', 'integer'],
            'invoice_id' => ['nullable', 'integer'],
            'invoice_ids' => ['nullable', 'array'],
            'invoice_ids.*' => ['integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'payment_method' => ['required', Rule::in(['cash', 'bkash', 'nagad', 'rocket', 'bank', 'card', 'online', 'other'])],
            'payment_method_id' => ['nullable', 'integer'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'paid_at' => ['nullable', 'date'],
        ]);

        if (empty($data['invoice_id']) && empty($data['invoice_ids'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'invoice_id' => 'Please select at least one invoice to pay.',
            ]);
        }

        $student = Student::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $institute->id)
            ->findOrFail((int) $data['student_id']);

        $invoiceIds = ! empty($data['invoice_ids'])
            ? array_map('intval', $data['invoice_ids'])
            : [(int) $data['invoice_id']];

        $remainingAmount = (float) $data['amount'];
        $firstPayment = null;

        foreach ($invoiceIds as $invoiceId) {
            if ($remainingAmount <= 0) {
                break;
            }

            $this->assertInvoiceOwnedByStudent($institute->id, $student, $invoiceId);

            $invoiceData = array_merge($data, [
                'invoice_id' => $invoiceId,
                'amount' => $remainingAmount,
            ]);

            $payment = $this->service->recordPayment(
                $institute->id,
                $this->actingBranchId($request),
                $invoiceData,
                (int) $this->actorId($request),
            );

            $remainingAmount -= (float) $payment->amount;

            if ($firstPayment === null) {
                $firstPayment = $payment;
            }
        }

        $receiptNumber = $this->service->allocateReceiptNumber($institute->id);
        $firstPayment->forceFill(['receipt_number' => $receiptNumber])->save();

        return redirect()
            ->route('finance.education.receipt', $firstPayment)
            ->with('status', 'Payment recorded. Receipt ' . $receiptNumber . ' generated.');
    }

    // ------------------------------------------------------------- Internals

    private function baseCurrency(Request $request): string
    {
        return app(AccountingSetupService::class)
            ->getSetting($this->requireInstitute($request)->id, 'base_currency', 'USD', $this->actingBranchId($request));
    }

    private function assertInvoiceOwnedByStudent(int $instituteId, Student $student, int $invoiceId): void
    {
        $owns = Invoice::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('student_id', $student->id)
            ->where('id', $invoiceId)
            ->exists();

        if (! $owns) {
            throw ValidationException::withMessages([
                'invoice_id' => 'The selected invoice does not belong to this student.',
            ]);
        }
    }

    private function structureAppliesTo(FeeStructure $structure, Enrollment $enrollment): bool
    {
        if ($structure->batch_id !== null && (int) $structure->batch_id !== (int) $enrollment->batch_id) {
            return false;
        }

        $enrollmentCourseId = $enrollment->batch?->course_id ?? $enrollment->course_id ?? null;
        if ($structure->course_id !== null && (int) $structure->course_id !== (int) $enrollmentCourseId) {
            return false;
        }

        if ($structure->branch_id !== null && $enrollment->batch?->branch_id !== null
            && (int) $structure->branch_id !== (int) $enrollment->batch->branch_id) {
            return false;
        }

        return true;
    }
}
