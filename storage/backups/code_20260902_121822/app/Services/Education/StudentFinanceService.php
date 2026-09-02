<?php

namespace App\Services\Education;

use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\Installment;
use App\Models\Invoice;
use App\Models\MonthlyFeePeriod;
use App\Models\Payment;
use App\Models\Student;
use App\Models\Training\Enrollment;
use App\Models\StudentWaiver;
use App\Services\Accounting\AccountingAuditService;
use App\Services\Accounting\InvoiceService;
use App\Services\Accounting\JournalPostingService;
use App\Services\Accounting\PaymentService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Education finance layer (Step 37).
 *
 * A thin, read/write layer over the existing finance core (InvoiceService,
 * PaymentService, JournalPostingService). It never moves money itself: billing
 * creates invoices through InvoiceService, payments/refunds go through
 * PaymentService, waivers adjust the invoice discount and rebuild the sale
 * journal through the finance core, and every money event is audited via
 * AccountingAuditService. All read models (student ledger, dashboard metrics,
 * batch/course reports) are derived — there is no parallel accounting.
 */
class StudentFinanceService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly PaymentService $payments,
        private readonly JournalPostingService $posting,
        private readonly AccountingAuditService $audit,
        private readonly FeeStructureService $structures,
        private readonly FeeHeadService $feeHeads,
    ) {}

    // --------------------------------------------------------------- Billing

    /**
     * Generate a student invoice from the most specific matching fee structure
     * (or the course fee defaults when none applies) through the finance core.
     *
     * @param  array<string, mixed>  $options
     */
    public function generateInvoice(Enrollment $enrollment, ?FeeStructure $structure = null, array $options = [], ?int $actorId = null): Invoice
    {
        $instituteId = (int) $enrollment->institute_id;
        $branchId = $options['branch_id'] ?? $enrollment->batch?->branch_id;

        if ($structure === null) {
            $structure = $this->structures->resolveForEnrollment($enrollment, $branchId, $actorId);
        }

        $this->assertNoDuplicateBilling($enrollment, $structure, (bool) ($options['allow_duplicate'] ?? false));

        if ($structure !== null) {
            $items = $this->itemsFromStructure($structure, (bool) ($options['include_optional'] ?? false));
            $invoiceType = $this->invoiceTypeForItems($items, $options['invoice_type'] ?? null);
        } else {
            $items = $this->itemsFromCourseDefaults($enrollment, $branchId);
            $invoiceType = $options['invoice_type'] ?? 'course_fee';
        }

        $total = round(array_sum(array_column($items, 'amount')), 4);
        $discount = round((float) ($options['discount'] ?? $enrollment->discount ?? 0), 4);

        if ($discount >= $total) {
            throw ValidationException::withMessages([
                'discount' => 'Discount must be lower than the total billed amount.',
            ]);
        }

        $payable = round($total - $discount, 4);
        $dueDate = $options['due_date'] ?? $enrollment->enrollment_date;
        $intervalDays = $structure !== null ? max(1, (int) $structure->installments_interval_days) : 30;
        $installmentCount = $structure !== null ? max(1, (int) $structure->installments_count) : 1;

        $installments = $payable > 0
            ? $this->installmentSchedule($payable, $installmentCount, $intervalDays, $dueDate)
            : [];

        $data = [
            'student_id' => (int) $enrollment->student_id,
            'enrollment_id' => (int) $enrollment->id,
            'invoice_type' => $invoiceType,
            'discount' => $discount,
            'due_date' => Carbon::parse($dueDate)->toDateString(),
            'note' => $structure !== null
                ? 'Billed from fee structure "'.$structure->name.'"'
                : 'Billed from course fee defaults',
            'items' => $items,
            'installments' => $installments,
        ];

        $invoice = $this->invoices->create($instituteId, $branchId, $data, $actorId);

        $invoice->forceFill([
            'invoice_meta' => array_merge($invoice->invoice_meta ?? [], [
                'source' => 'education',
                'fee_structure_id' => $structure?->id,
            ]),
        ])->save();

        return $invoice->fresh()->load(['items.feeHead', 'installments', 'party', 'student', 'journal']);
    }

    /**
     * Re-issue an invoice for an enrollment after it was cancelled, using the
     * same targeting and amounts.
     */
    public function regenerateInvoice(Enrollment $enrollment, array $options = [], ?int $actorId = null): Invoice
    {
        $structure = $this->structures->resolveForEnrollment($enrollment, $options['branch_id'] ?? null, $actorId);

        return $this->generateInvoice($enrollment, $structure, $options, $actorId);
    }

    // ------------------------------------------------- Waivers & payments

    /**
     * Apply an approved waiver to an unpaid/partial invoice.
     *
     * Accounting-safe: the existing sale journal is reversed (posted) or voided
     * (draft), the invoice discount grows by the waiver amount, the installment
     * schedule is rebalanced to the new payable and a fresh sale journal is
     * rebuilt through the finance core. The approval is recorded in
     * student_waivers and audited.
     */
    public function applyWaiver(Invoice $invoice, float $amount, ?string $reason, int $instituteId, ?int $actorId = null): Invoice
    {
        $invoice = $invoice->fresh();

        if ((int) $invoice->institute_id !== $instituteId) {
            throw ValidationException::withMessages([
                'invoice' => 'This invoice does not belong to the institute.',
            ]);
        }

        if ($invoice->status === 'cancelled') {
            throw ValidationException::withMessages([
                'invoice' => 'Waivers cannot be applied to a cancelled invoice.',
            ]);
        }

        $due = round((float) $invoice->due_amount, 4);
        $amount = round($amount, 4);

        if ($amount <= 0 || $amount > $due + 0.0001) {
            throw ValidationException::withMessages([
                'amount' => "The waiver amount must be positive and no more than the outstanding balance of {$due}.",
            ]);
        }

        $branchId = $this->invoiceBranchId($invoice);

        return DB::transaction(function () use ($invoice, $amount, $reason, $instituteId, $actorId, $branchId) {
            $invoice->load('items');

            $newDiscount = round((float) $invoice->discount + $amount, 4);
            $newPayable = round((float) $invoice->total_amount - $newDiscount, 4);
            $newDue = round($newPayable - (float) $invoice->paid_amount, 4);

            if ($newDue < 0) {
                throw ValidationException::withMessages([
                    'amount' => 'The waiver would over-discount this invoice.',
                ]);
            }

            if ($invoice->journal_id !== null) {
                $journal = $invoice->journal;

                if ($journal !== null && $journal->status === 'posted') {
                    $this->posting->reverse($journal, $instituteId, $actorId, 'Waiver of '.$amount.' applied to invoice '.$invoice->invoice_number);
                } elseif ($journal !== null && $journal->status === 'draft') {
                    $this->posting->void($journal, $instituteId, $actorId);
                }
            }

            $invoice->forceFill([
                'discount' => $newDiscount,
                'payable_amount' => $newPayable,
                'due_amount' => $newDue,
                'status' => $newDue <= 0 ? 'paid' : ($invoice->paid_amount > 0 ? 'partial' : 'unpaid'),
            ])->save();

            $this->rebalanceInstallments($invoice, $amount);

            $journalId = null;
            if ($newPayable > 0) {
                $journalId = $this->invoices->rebuildSaleJournal($invoice, $branchId, $actorId)->journal_id;
            }

            StudentWaiver::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchId,
                'invoice_id' => $invoice->id,
                'student_id' => $invoice->student_id,
                'enrollment_id' => $invoice->enrollment_id,
                'amount' => $amount,
                'reason' => $reason,
                'waived_by' => $actorId,
                'waived_at' => now(),
            ]);

            $this->audit->log($instituteId, [
                'branch_id' => $branchId,
                'actor_type' => 'user',
                'actor_id' => $actorId,
                'action' => 'waive',
                'entity_type' => 'invoice',
                'entity_id' => $invoice->id,
                'after_payload' => [
                    'invoice_number' => $invoice->invoice_number,
                    'waiver_amount' => $amount,
                    'reason' => $reason,
                    'new_due' => $newDue,
                    'journal_id' => $journalId,
                ],
            ]);

            return $invoice->fresh()->load(['items.feeHead', 'installments', 'party', 'student', 'journal']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function recordPayment(int $instituteId, ?int $branchId, array $data, ?int $actorId = null): Payment
    {
        return $this->payments->record($instituteId, $branchId, $data, $actorId);
    }

    /**
     * Refund/reverse a payment per the existing accounting rules.
     */
    public function refundPayment(Payment $payment, int $instituteId, ?int $actorId = null, ?string $reason = null): void
    {
        $this->payments->reverse($payment, $instituteId, $actorId, $reason ?? 'Student payment refunded');
    }

    // ----------------------------------------------------------- Read models

    /**
     * @return array<string, mixed>
     */
    public function ledgerForStudent(int $instituteId, int $studentId, ?int $branchId = null): array
    {
        $student = Student::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->withTrashed()
            ->findOrFail($studentId);

        $invoices = Invoice::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('student_id', $studentId)
            ->with(['items.feeHead', 'installments', 'party', 'journal', 'payments.journal'])
            ->when($branchId !== null, fn ($q) => $q->where(function ($q) use ($branchId) {
                $q->whereHas('enrollment.batch', fn ($b) => $b->where('branch_id', $branchId))
                    ->orWhereHas('student', fn ($s) => $s->where('branch_id', $branchId));
            }))
            ->orderByDesc('id')
            ->get();

        $rows = $invoices->map(function (Invoice $invoice) {
            $installments = $invoice->installments->sortBy('installment_no')->values();

            return [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_type' => $invoice->invoice_type,
                'status' => $invoice->status,
                'created_at' => $invoice->created_at?->toDateTimeString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'total_amount' => (float) $invoice->total_amount,
                'discount' => (float) $invoice->discount,
                'payable_amount' => (float) $invoice->payable_amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'due_amount' => (float) $invoice->due_amount,
                'party_id' => $invoice->party_id,
                'billing_period' => $invoice->invoice_meta['billing_period'] ?? null,
                'is_recurring' => (bool) ($invoice->invoice_meta['is_recurring'] ?? false),
                'items' => $invoice->items->map(fn ($item) => [
                    'description' => $item->description,
                    'amount' => (float) $item->amount,
                    'fee_head' => $item->feeHead?->name,
                ])->all(),
                'installments' => $installments->map(fn (Installment $inst) => [
                    'id' => (int) $inst->id,
                    'no' => $inst->installment_no,
                    'amount' => (float) $inst->amount,
                    'paid' => (float) $inst->paid_amount,
                    'due_date' => $inst->due_date?->toDateString(),
                    'status' => $inst->status,
                ])->all(),
                'payments' => $invoice->payments->map(fn (Payment $payment) => [
                    'id' => $payment->id,
                    'amount' => (float) $payment->amount,
                    'method' => $payment->payment_method,
                    'paid_at' => $payment->paid_at?->toDateTimeString(),
                    'reversed' => $this->paymentIsReversed($payment),
                ])->all(),
            ];
        })->all();

        $waivers = StudentWaiver::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('student_id', $studentId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->with('waivedBy')
            ->orderByDesc('id')
            ->get()
            ->map(fn (StudentWaiver $waiver) => [
                'id' => $waiver->id,
                'amount' => (float) $waiver->amount,
                'reason' => $waiver->reason,
                'waived_by' => $waiver->waivedBy?->full_name ?? $waiver->waived_by,
                'waived_at' => $waiver->waived_at?->toDateTimeString(),
            ])
            ->all();

        $today = now()->toDateString();

        $billed = round(collect($rows)->where('status', '!=', 'cancelled')->sum('payable_amount'), 2);
        $collected = round(collect($rows)->sum(fn ($row) => collect($row['payments'])->where('reversed', false)->sum('amount')), 2);
        $waivedTotal = round(collect($waivers)->sum('amount'), 2);
        $outstanding = round(collect($rows)->whereIn('status', ['unpaid', 'partial'])->sum('due_amount'), 2);
        $overdue = round(collect($rows)
            ->whereIn('status', ['unpaid', 'partial'])
            ->where(fn ($row) => $row['due_date'] !== null && $row['due_date'] < $today)
            ->sum('due_amount'), 2);

        return [
            'student' => [
                'id' => (int) $student->id,
                'full_name' => $student->full_name,
                'student_id_number' => $student->student_id_number,
                'phone' => $student->phone,
                'email' => $student->email,
            ],
            'invoices' => $rows,
            'waivers' => $waivers,
            'totals' => compact('billed', 'collected', 'waivedTotal', 'outstanding', 'overdue'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardMetrics(int $instituteId, ?int $branchId = null): array
    {
        $scoped = $this->scopedInvoiceQuery($instituteId, $branchId);

        $billed = (float) (clone $scoped)->where('i.status', '!=', 'cancelled')
            ->selectRaw('COALESCE(SUM(i.payable_amount), 0) AS v')->value('v');

        $outstanding = (float) (clone $scoped)->whereIn('i.status', ['unpaid', 'partial'])
            ->selectRaw('COALESCE(SUM(i.due_amount), 0) AS v')->value('v');

        $overdue = (float) (clone $scoped)
            ->whereIn('i.status', ['unpaid', 'partial'])
            ->whereDate('i.due_date', '<', now()->toDateString())
            ->selectRaw('COALESCE(SUM(i.due_amount), 0) AS v')->value('v');

        $discounts = (float) (clone $scoped)->where('i.status', '!=', 'cancelled')
            ->selectRaw('COALESCE(SUM(i.discount), 0) AS v')->value('v');

        $collected = $this->collectedTotal($instituteId, $branchId);

        $waiverQuery = StudentWaiver::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId));

        $waiverCount = (clone $waiverQuery)->count();
        $waiverAmount = (float) (clone $waiverQuery)->sum('amount');

        $recent = Invoice::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->when($branchId !== null, fn ($q) => $q->where(function ($q) use ($branchId) {
                $q->whereHas('enrollment.batch', fn ($b) => $b->where('branch_id', $branchId))
                    ->orWhereHas('student', fn ($s) => $s->where('branch_id', $branchId));
            }))
            ->with(['student', 'party'])
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'invoice_number', 'invoice_type', 'status', 'payable_amount', 'paid_amount', 'due_amount', 'due_date', 'student_id', 'party_id', 'created_at']);

        return [
            'billed' => round($billed, 2),
            'collected' => round($collected, 2),
            'outstanding' => round($outstanding, 2),
            'overdue' => round($overdue, 2),
            'discounts' => round($discounts, 2),
            'waiver_count' => $waiverCount,
            'waiver_amount' => round($waiverAmount, 2),
            'recent_invoices' => $recent,
        ];
    }

    /**
     * @return Collection<int, object>
     */
    public function batchSummary(int $instituteId, ?int $branchId = null): Collection
    {
        return $this->groupedSummary($instituteId, $branchId, 'batch');
    }

    /**
     * @return Collection<int, object>
     */
    public function courseSummary(int $instituteId, ?int $branchId = null): Collection
    {
        return $this->groupedSummary($instituteId, $branchId, 'course');
    }

    /**
     * Students with their billed/collected/outstanding figures, grouped for the
     * student-wise finance report.
     *
     * @return Collection<int, object>
     */
    public function studentSummary(int $instituteId, ?int $branchId = null, ?string $term = null): Collection
    {
        $query = $this->scopedInvoiceQuery($instituteId, $branchId)
            ->whereNotNull('i.student_id')
            ->groupBy('s.id', 's.first_name', 's.last_name', 's.student_id_number', 's.phone');

        if (filled($term)) {
            $like = '%'.trim($term).'%';
            $query->where(fn ($q) => $q
                ->where('s.first_name', 'like', $like)
                ->orWhere('s.last_name', 'like', $like)
                ->orWhere('s.student_id_number', 'like', $like));
        }

        $today = now()->toDateString();

        return $query->select(
            's.id',
            's.first_name',
            's.last_name',
            's.student_id_number',
            's.phone',
        )
            ->selectRaw('COUNT(DISTINCT CASE WHEN i.status <> \'cancelled\' THEN i.id END) AS invoice_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN i.status <> \'cancelled\' THEN i.payable_amount ELSE 0 END), 0) AS billed')
            ->selectRaw('COALESCE(SUM(CASE WHEN i.status IN (\'unpaid\', \'partial\') THEN i.due_amount ELSE 0 END), 0) AS outstanding')
            ->selectRaw('COALESCE(SUM(CASE WHEN i.status IN (\'unpaid\', \'partial\') AND i.due_date < ? THEN i.due_amount ELSE 0 END), 0) AS overdue', [$today])
            ->selectRaw('(SELECT COALESCE(SUM(p2.amount), 0) FROM payments p2 WHERE p2.student_id = s.id AND p2.institute_id = ? AND NOT EXISTS (SELECT 1 FROM journals r2 WHERE r2.reversal_of = p2.journal_id)) AS collected', [$instituteId])
            ->orderByDesc('billed')
            ->get()
            ->map(function ($row) {
                $row->billed = round((float) $row->billed, 2);
                $row->collected = round((float) $row->collected, 2);
                $row->outstanding = round((float) $row->outstanding, 2);
                $row->overdue = round((float) $row->overdue, 2);

                return $row;
            });
    }

    // ------------------------------------------------------------- Internals

    /**
     * @return Collection<int, object>
     */
    private function groupedSummary(int $instituteId, ?int $branchId, string $group): Collection
    {
        $query = $this->scopedInvoiceQuery($instituteId, $branchId)
            ->where('i.status', '!=', 'cancelled');

        if ($group === 'batch') {
            $query->groupBy('b.id', 'b.name', 'b.batch_code', 'b.course_id');
        } else {
            $query->groupBy('c.id', 'c.name', 'c.course_code');
        }

        $select = $group === 'batch'
            ? ['b.id', 'b.name', 'b.batch_code', 'b.course_id']
            : ['c.id', 'c.name', 'c.course_code'];

        $today = now()->toDateString();

        return $query->select($select)
            ->selectRaw('COUNT(DISTINCT s.id) AS student_count')
            ->selectRaw('COUNT(DISTINCT i.id) AS invoice_count')
            ->selectRaw('COALESCE(SUM(i.payable_amount), 0) AS billed')
            ->selectRaw('COALESCE(SUM(CASE WHEN i.status IN (\'unpaid\', \'partial\') THEN i.due_amount ELSE 0 END), 0) AS outstanding')
            ->selectRaw('COALESCE(SUM(CASE WHEN i.status IN (\'unpaid\', \'partial\') AND i.due_date < ? THEN i.due_amount ELSE 0 END), 0) AS overdue', [$today])
            ->selectRaw('COALESCE(SUM(i.discount), 0) AS discounts')
            ->orderByDesc('billed')
            ->get()
            ->map(function ($row) {
                $row->billed = round((float) $row->billed, 2);
                $row->outstanding = round((float) $row->outstanding, 2);
                $row->overdue = round((float) $row->overdue, 2);
                $row->discounts = round((float) $row->discounts, 2);

                return $row;
            });
    }

    /**
     * Base query over invoices joined to their enrollment/batch/course/student
     * so education reads can be scoped to an acting branch even though the
     * legacy invoices table has no branch_id.
     */
    private function scopedInvoiceQuery(int $instituteId, ?int $branchId): Builder
    {
        $query = DB::table('invoices as i')
            ->leftJoin('enrollments as e', 'e.id', '=', 'i.enrollment_id')
            ->leftJoin('batches as b', 'b.id', '=', 'e.batch_id')
            ->leftJoin('courses as c', 'c.id', '=', 'e.course_id')
            ->leftJoin('students as s', 's.id', '=', 'i.student_id')
            ->where('i.institute_id', $instituteId);

        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('b.branch_id', $branchId)
                    ->orWhere(function ($q2) use ($branchId) {
                        $q2->whereNull('b.branch_id')->where('s.branch_id', $branchId);
                    });
            });
        }

        return $query;
    }

    private function collectedTotal(int $instituteId, ?int $branchId): float
    {
        $query = DB::table('payments as p')
            ->join('invoices as i', 'i.id', '=', 'p.invoice_id')
            ->leftJoin('enrollments as e', 'e.id', '=', 'i.enrollment_id')
            ->leftJoin('batches as b', 'b.id', '=', 'e.batch_id')
            ->leftJoin('students as s', 's.id', '=', 'i.student_id')
            ->where('p.institute_id', $instituteId)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('journals as r')
                    ->whereColumn('r.reversal_of', 'p.journal_id');
            });

        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('b.branch_id', $branchId)
                    ->orWhereNull('b.branch_id')->where('s.branch_id', $branchId);
            });
        }

        return (float) $query->selectRaw('COALESCE(SUM(p.amount), 0) AS v')->value('v');
    }

    private function paymentIsReversed(Payment $payment): bool
    {
        if ($payment->journal_id === null) {
            return false;
        }

        return DB::table('journals')
            ->where('reversal_of', $payment->journal_id)
            ->exists();
    }

    private function invoiceBranchId(Invoice $invoice): ?int
    {
        if ($invoice->enrollment_id !== null) {
            $branchId = DB::table('enrollments as e')
                ->join('batches as b', 'b.id', '=', 'e.batch_id')
                ->where('e.id', $invoice->enrollment_id)
                ->value('b.branch_id');

            if ($branchId !== null) {
                return (int) $branchId;
            }
        }

        if ($invoice->student_id !== null) {
            $branchId = DB::table('students')->where('id', $invoice->student_id)->value('branch_id');

            return $branchId !== null ? (int) $branchId : null;
        }

        return null;
    }

    private function assertNoDuplicateBilling(Enrollment $enrollment, ?FeeStructure $structure, bool $allowDuplicate): void
    {
        if ($allowDuplicate) {
            return;
        }

        $existing = Invoice::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $enrollment->institute_id)
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('status', ['unpaid', 'partial'])
            ->get(['id', 'invoice_type', 'invoice_meta']);

        foreach ($existing as $invoice) {
            $sameStructure = $structure !== null
                && (int) ($invoice->invoice_meta['fee_structure_id'] ?? 0) === (int) $structure->id;

            if ($sameStructure) {
                throw ValidationException::withMessages([
                    'enrollment' => 'This enrollment already has an open invoice for this fee structure.',
                ]);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function itemsFromStructure(FeeStructure $structure, bool $includeOptional): array
    {
        $items = [];

        foreach ($structure->items as $item) {
            if ($item->is_optional && ! $includeOptional) {
                continue;
            }

            $head = $item->feeHead;

            $items[] = [
                'description' => $head?->name ?? 'Fee',
                'amount' => round((float) $item->amount, 2),
                'coa_id' => $head?->income_coa_id,
                'fee_head_id' => $head?->id,
            ];
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'structure' => 'This fee structure has no billable items.',
            ]);
        }

        return $items;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function itemsFromCourseDefaults(Enrollment $enrollment, ?int $branchId): array
    {
        $course = $enrollment->batch?->course;

        if ($course === null) {
            throw ValidationException::withMessages([
                'enrollment' => 'This enrollment has no course to bill against.',
            ]);
        }

        $instituteId = (int) $enrollment->institute_id;
        $items = [];
        $specs = [
            'Admission Fee' => [(float) $course->admission_fee, FeeHead::TYPE_ADMISSION],
            'Course / Tuition Fee' => [(float) $course->fee, FeeHead::TYPE_COURSE_TUITION],
            'Exam Fee' => [(float) $course->exam_fee, FeeHead::TYPE_EXAM],
            'Certificate Fee' => [(float) $course->certificate_fee, FeeHead::TYPE_CERTIFICATE],
        ];

        foreach ($specs as $label => [$amount, $type]) {
            if ($amount > 0) {
                $items[] = [
                    'description' => $label,
                    'amount' => round($amount, 2),
                    'coa_id' => $this->feeHeads->defaultIncomeAccount($instituteId, $branchId, $type),
                    'fee_head_id' => null,
                ];
            }
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'enrollment' => 'This course has no fees configured.',
            ]);
        }

        return $items;
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function invoiceTypeForItems(array $items, ?string $fallback): string
    {
        $headIds = array_values(array_filter(array_column($items, 'fee_head_id')));

        if ($headIds !== []) {
            $types = FeeHead::query()
                ->withoutGlobalScopes()
                ->whereIn('id', $headIds)
                ->pluck('type')
                ->unique()
                ->values();

            if ($types->count() === 1) {
                $head = FeeHead::query()
                    ->withoutGlobalScopes()
                    ->where('id', $headIds[0])
                    ->first();

                return $head !== null ? $head->invoiceType() : ($fallback ?? 'other');
            }

            return 'other';
        }

        return $fallback ?? 'course_fee';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function installmentSchedule(float $payable, int $count, int $intervalDays, mixed $dueDate): array
    {
        $start = Carbon::parse($dueDate);

        $rows = [];
        $allocated = 0.0;
        $count = min(12, max(1, $count));

        for ($i = 0; $i < $count; $i++) {
            $isLast = $i === $count - 1;
            $amount = $isLast
                ? round($payable - $allocated, 2)
                : round($payable / $count, 2);

            $rows[] = [
                'amount' => $amount,
                'due_date' => $start->copy()->addDays($i * $intervalDays)->toDateString(),
            ];

            $allocated += $amount;
        }

        $rows[count($rows) - 1]['amount'] = round($payable - $allocated + $rows[count($rows) - 1]['amount'], 2);

        return $rows;
    }

    /**
     * Reduce the installment schedule to the new payable (waivers), spreading
     * the reduction across the last open installments and never below the paid
     * portion of an installment.
     */
    private function rebalanceInstallments(Invoice $invoice, float $waiverAmount): void
    {
        $installments = $invoice->installments()
            ->orderByDesc('installment_no')
            ->get();

        $remaining = $waiverAmount;

        foreach ($installments as $installment) {
            if ($remaining <= 0.0001) {
                break;
            }

            $unpaidPortion = round((float) $installment->amount - (float) $installment->paid_amount, 4);

            if ($unpaidPortion <= 0.0001) {
                continue;
            }

            $take = min($remaining, $unpaidPortion);

            $installment->forceFill([
                'amount' => round((float) $installment->amount - $take, 4),
            ])->save();

            $remaining -= $take;
        }

        if ($remaining > 0.0001) {
            throw ValidationException::withMessages([
                'amount' => 'The installment schedule cannot absorb the waiver amount.',
            ]);
        }

        foreach ($installments as $installment) {
            $this->refreshInstallmentStatus($installment);
        }
    }

    private function refreshInstallmentStatus(Installment $installment): void
    {
        $paid = round((float) $installment->paid_amount, 4);
        $amount = round((float) $installment->amount, 4);

        $status = $paid >= $amount - 0.01
            ? 'paid'
            : ($installment->due_date !== null && $installment->due_date->isBefore(today()) ? 'overdue' : 'pending');

        $installment->forceFill(['status' => $status])->save();
    }

    // --------------------------------------------------------- Receipt Numbering

    /**
     * Allocate a unique receipt number for a payment.
     */
    public function allocateReceiptNumber(int $instituteId): string
    {
        $taken = fn (string $no) => Payment::query()
            ->where('institute_id', $instituteId)
            ->where('receipt_number', $no)
            ->exists();

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = 'RCP-' . now()->format('Ymd') . '-' . strtoupper(Str::random(5));
            if (! $taken($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Could not allocate a unique receipt number.');
    }

    // ------------------------------------------------------- Fee Collection Dues

    /**
     * Calculate Previous Due + Current Month + Total Outstanding for a student.
     *
     * @return array{student: array, previous_due: float, current_month: float, overdue: float, total_outstanding: float, invoices: list<array>}
     */
    public function feeCollectionData(int $instituteId, int $studentId, ?int $branchId = null): array
    {
        $student = Student::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->withTrashed()
            ->findOrFail($studentId);

        $currentMonth = now()->format('Y-m-01');

        $invoices = Invoice::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('student_id', $studentId)
            ->whereIn('status', ['unpaid', 'partial'])
            ->with(['items.feeHead', 'installments', 'journal'])
            ->when($branchId !== null, fn ($q) => $q->where(function ($q) use ($branchId) {
                $q->whereHas('enrollment.batch', fn ($b) => $b->where('branch_id', $branchId))
                    ->orWhereHas('student', fn ($s) => $s->where('branch_id', $branchId));
            }))
            ->orderBy('id')
            ->get();

        $previousDue = 0.0;
        $currentMonthDue = 0.0;
        $overdue = 0.0;
        $today = now()->toDateString();
        $rows = [];

        foreach ($invoices as $invoice) {
            $billingPeriod = $invoice->invoice_meta['billing_period'] ?? null;
            $dueAmount = (float) $invoice->due_amount;

            if ($billingPeriod !== null && $billingPeriod < $currentMonth) {
                $previousDue += $dueAmount;
            } elseif ($billingPeriod !== null && $billingPeriod === $currentMonth) {
                $currentMonthDue += $dueAmount;
            } else {
                $previousDue += $dueAmount;
            }

            if ($invoice->due_date !== null && $invoice->due_date->isBefore($today) && $dueAmount > 0) {
                $overdue += $dueAmount;
            }

            $rows[] = [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_type' => $invoice->invoice_type,
                'status' => $invoice->status,
                'created_at' => $invoice->created_at?->toDateTimeString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'total_amount' => round((float) $invoice->total_amount, 2),
                'discount' => round((float) $invoice->discount, 2),
                'payable_amount' => round((float) $invoice->payable_amount, 2),
                'paid_amount' => round((float) $invoice->paid_amount, 2),
                'due_amount' => round($dueAmount, 2),
                'billing_period' => $billingPeriod,
                'is_recurring' => (bool) ($invoice->invoice_meta['is_recurring'] ?? false),
                'items' => $invoice->items->map(fn ($item) => [
                    'description' => $item->description,
                    'amount' => round((float) $item->amount, 2),
                    'fee_head' => $item->feeHead?->name,
                ])->all(),
                'installments' => $invoice->installments->sortBy('installment_no')->values()->map(fn (Installment $inst) => [
                    'id' => (int) $inst->id,
                    'no' => $inst->installment_no,
                    'amount' => round((float) $inst->amount, 2),
                    'paid' => round((float) $inst->paid_amount, 2),
                    'due_date' => $inst->due_date?->toDateString(),
                    'status' => $inst->status,
                ])->all(),
            ];
        }

        $totalOutstanding = round($previousDue + $currentMonthDue, 2);

        return [
            'student' => [
                'id' => (int) $student->id,
                'full_name' => $student->full_name,
                'student_id_number' => $student->student_id_number,
                'reg_no' => $student->reg_no,
                'phone' => $student->phone,
                'email' => $student->email,
                'admission_status' => $student->admission_status,
                'status' => $student->status,
                'applied_course_id' => $student->applied_course_id,
                'preferred_batch_id' => $student->preferred_batch_id,
            ],
            'previous_due' => round($previousDue, 2),
            'current_month' => round($currentMonthDue, 2),
            'overdue' => round($overdue, 2),
            'total_outstanding' => $totalOutstanding,
            'invoices' => $rows,
        ];
    }
}
