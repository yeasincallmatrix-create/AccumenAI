<?php

namespace App\Services\Education;

use App\Models\FeeHead;
use App\Models\FeeStructure;
use App\Models\Institute;
use App\Models\MonthlyFeePeriod;
use App\Models\Student;
use App\Models\Training\Enrollment;
use App\Services\Accounting\AccountingAuditService;
use App\Services\Accounting\InvoiceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class MonthlyFeeGenerationService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly AccountingAuditService $audit,
    ) {}

    /**
     * Generate monthly recurring invoices for all eligible students across all
     * active auto-generate fee structures for a given billing period.
     *
     * @return array{structures_processed: int, students_checked: int, invoices_generated: int, skipped: int, ineligible: int, errors: list<string>}
     */
    public function generate(
        int $instituteId,
        ?int $branchId = null,
        ?string $month = null,
        bool $dryRun = false,
    ): array {
        $month = $month ?? now()->format('Y-m-01');
        $periodMonth = Carbon::parse($month)->startOfMonth()->toDateString();

        $summary = [
            'structures_processed' => 0,
            'students_checked' => 0,
            'invoices_generated' => 0,
            'skipped' => 0,
            'ineligible' => 0,
            'errors' => [],
        ];

        $structures = $this->eligibleStructures($instituteId, $branchId);

        foreach ($structures as $structure) {
            $summary['structures_processed']++;

            $this->eligibleEnrollmentsQuery($instituteId, $structure, $branchId)
                ->chunkById(200, function ($enrollments) use ($instituteId, $branchId, $structure, $periodMonth, $dryRun, &$summary) {
                    foreach ($enrollments as $enrollment) {
                        $summary['students_checked']++;

                        try {
                            $result = $this->generateForEnrollment(
                                $instituteId,
                                $branchId,
                                $structure,
                                $enrollment,
                                $periodMonth,
                                $dryRun,
                            );

                            if ($result === 'generated') {
                                $summary['invoices_generated']++;
                            } elseif ($result === 'skipped') {
                                $summary['skipped']++;
                            }
                        } catch (\Throwable $e) {
                            $summary['errors'][] = "Structure {$structure->id} Enrollment {$enrollment->id}: {$e->getMessage()}";
                        }
                    }
                });
        }

        return $summary;
    }

    /**
     * Generate a single invoice for one enrollment + fee structure + period.
     *
     * @return string 'generated'|'skipped'
     */
    private function generateForEnrollment(
        int $instituteId,
        ?int $branchId,
        FeeStructure $structure,
        Enrollment $enrollment,
        string $periodMonth,
        bool $dryRun,
    ): string {
        $studentId = (int) $enrollment->student_id;

        $existing = MonthlyFeePeriod::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('fee_structure_id', $structure->id)
            ->where('student_id', $studentId)
            ->where('enrollment_id', $enrollment->id)
            ->where('period_month', $periodMonth)
            ->first();

        if ($existing !== null) {
            return 'skipped';
        }

        if ($dryRun) {
            return 'generated';
        }

        return DB::transaction(function () use ($instituteId, $branchId, $structure, $enrollment, $periodMonth, $studentId) {
            $branchIdForInvoice = $branchId ?? $enrollment->batch?->branch_id;

            $items = $this->buildInvoiceItems($structure);

            $totalAmount = round(array_sum(array_column($items, 'amount')), 2);

            $invoiceData = [
                'student_id' => $studentId,
                'enrollment_id' => $enrollment->id,
                'invoice_type' => 'course_fee',
                'discount' => 0,
                'due_date' => Carbon::parse($periodMonth)->addDays(30)->toDateString(),
                'note' => 'Recurring fee — ' . $structure->name . ' — ' . $periodMonth,
                'items' => $items,
            ];

            $invoice = $this->invoices->create(
                $instituteId,
                $branchIdForInvoice,
                $invoiceData,
            );

            $invoice->forceFill([
                'invoice_meta' => array_merge($invoice->invoice_meta ?? [], [
                    'source' => 'education_recurring',
                    'fee_structure_id' => $structure->id,
                    'billing_period' => $periodMonth,
                    'is_recurring' => true,
                ]),
            ])->save();

            MonthlyFeePeriod::create([
                'institute_id' => $instituteId,
                'branch_id' => $branchIdForInvoice,
                'fee_structure_id' => $structure->id,
                'student_id' => $studentId,
                'enrollment_id' => $enrollment->id,
                'period_month' => $periodMonth,
                'invoice_id' => $invoice->id,
                'status' => MonthlyFeePeriod::STATUS_GENERATED,
            ]);

            $this->audit->log($instituteId, [
                'branch_id' => $branchIdForInvoice,
                'actor_type' => 'system',
                'action' => 'recurring_fee_generated',
                'entity_type' => 'invoice',
                'entity_id' => $invoice->id,
                'after_payload' => [
                    'fee_structure_id' => $structure->id,
                    'student_id' => $studentId,
                    'enrollment_id' => $enrollment->id,
                    'period_month' => $periodMonth,
                    'amount' => $totalAmount,
                    'invoice_number' => $invoice->invoice_number,
                ],
            ]);

            return 'generated';
        });
    }

    /**
     * Build invoice items from a fee structure's items (only recurring heads).
     */
    private function buildInvoiceItems(FeeStructure $structure): array
    {
        $items = [];

        foreach ($structure->items as $item) {
            if ($item->is_optional) {
                continue;
            }

            $head = $item->feeHead;
            if ($head === null || ! $head->is_recurring) {
                continue;
            }

            $items[] = [
                'description' => $head->name ?? 'Fee',
                'amount' => round((float) $item->amount, 2),
                'coa_id' => $head->income_coa_id,
                'fee_head_id' => $head->id,
            ];
        }

        if ($items === []) {
            throw new \RuntimeException('Fee structure has no recurring fee heads with amounts.');
        }

        return $items;
    }

    /**
     * Active fee structures with auto_generate_monthly enabled.
     */
    private function eligibleStructures(int $instituteId, ?int $branchId): Collection
    {
        $query = FeeStructure::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('status', FeeStructure::STATUS_ACTIVE)
            ->where('auto_generate_monthly', true)
            ->with('items.feeHead');

        if ($branchId !== null) {
            $query->where(fn ($q) => $q->whereNull('branch_id')->orWhere('branch_id', $branchId));
        }

        return $query->get();
    }

    /**
     * Active enrollments with eligible students (memory-safe query builder).
     */
    private function eligibleEnrollmentsQuery(int $instituteId, FeeStructure $structure, ?int $branchId)
    {
        $query = Enrollment::query()
            ->withoutGlobalScopes()
            ->where('institute_id', $instituteId)
            ->where('status', 'active')
            ->with(['student', 'batch'])
            ->whereHas('student', function ($q) {
                $q->where('admission_status', 'enrolled')
                    ->where('status', 'active');
            });

        if ($structure->course_id !== null) {
            $query->whereHas('batch', fn ($q) => $q->where('course_id', $structure->course_id));
        }

        if ($structure->batch_id !== null) {
            $query->where('batch_id', $structure->batch_id);
        }

        if ($branchId !== null) {
            $query->whereHas('batch', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        if ($structure->academic_year_id !== null) {
            $query->whereHas('batch', function ($q) use ($structure) {
                $q->where('academic_year_id', $structure->academic_year_id);
            });
        }

        return $query->orderBy('id');
    }

    /**
     * @deprecated use eligibleEnrollmentsQuery for chunked processing
     */
    private function eligibleEnrollments(int $instituteId, FeeStructure $structure, ?int $branchId): Collection
    {
        return $this->eligibleEnrollmentsQuery($instituteId, $structure, $branchId)->get();
    }
}
