<?php

namespace App\Services\Medical;

use App\Models\Institute;
use App\Models\Medical\Admission;
use App\Models\Medical\Invoice;
use App\Models\Medical\NumberSequence;
use App\Models\Medical\Patient;
use App\Support\MedicalScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/**
 * Medical invoicing on the `medical_invoices` table (never the finance
 * `invoices` table): numbered invoice creation with JSON line items,
 * payment application, revenue summaries and invoice PDFs.
 */
class BillingService
{
    /**
     * Phase 08 — single authoritative tax rule for medical billing.
     * Bangladesh default 5%, applied server-side everywhere (creation and
     * pending-invoice edits share computeTotals()); never client-submitted.
     * Multi-jurisdiction VAT/GST is explicitly Phase 25 territory.
     */
    public const TAX_RATE = 0.05;

    /**
     * Phase 08 — authoritative totals from line items. Math is identical to
     * the historical implementation (raw subtotal sum; tax/discount/total
     * rounded to 2dp to match DECIMAL(15,2) storage); it is centralized here
     * so creation, edits, PDFs and receipts can never diverge.
     * Items: [{amount, quantity, discount}].
     */
    public static function computeTotals(array $items): array
    {
        $subtotal = collect($items)->sum(fn ($i) => ((float) ($i['amount'] ?? 0)) * ((int) ($i['quantity'] ?? 1)));
        $tax = round($subtotal * self::TAX_RATE, 2);
        $discount = round((float) collect($items)->sum('discount'), 2);

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'total' => round($subtotal + $tax - $discount, 2),
        ];
    }
    /**
     * Generate a unique invoice number (INV-YYYY-III-XXXXX) via the
     * database-backed sequence (Phase 04). Format unchanged.
     */
    public function generateInvoiceNumber(int $instituteId): string
    {
        return app(NumberSequenceService::class)->next(NumberSequence::TYPE_INVOICE, $instituteId);
    }

    /**
     * Generate OPD invoice.
     */
    public function generateOpdInvoice(Patient $patient, array $items, ?int $branchId = null): Invoice
    {
        return $this->createInvoice($patient, 'opd', $items, null, $branchId);
    }

    /**
     * Generate IPD invoice.
     */
    public function generateIpdInvoice(Admission $admission, array $items, ?int $branchId = null): Invoice
    {
        return $this->createInvoice($admission->patient, 'ipd', $items, $admission->id, $branchId);
    }

    /**
     * Generate pharmacy invoice.
     */
    public function generatePharmacyInvoice(Patient $patient, array $items, ?int $branchId = null): Invoice
    {
        return $this->createInvoice($patient, 'pharmacy', $items, null, $branchId);
    }

    /**
     * Generate lab invoice.
     */
    public function generateLabInvoice(Patient $patient, array $items, ?int $branchId = null): Invoice
    {
        return $this->createInvoice($patient, 'lab', $items, null, $branchId);
    }

    /**
     * Create an invoice. Items: [{description, amount, quantity, discount}].
     * Phase 18 adds an optional branch owner (no calculation change).
     */
    public function createInvoice(Patient $patient, string $type, array $items, ?int $admissionId = null, ?int $branchId = null): Invoice
    {
        return DB::transaction(function () use ($patient, $type, $items, $admissionId, $branchId) {
            $instituteId = (int) $patient->institute_id;

            $totals = self::computeTotals($items);

            return Invoice::create([
                'institute_id' => $instituteId,
                'patient_id' => $patient->id,
                'admission_id' => $admissionId,
                'branch_id' => $branchId,
                'invoice_number' => $this->generateInvoiceNumber($instituteId),
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(15)->format('Y-m-d'),
                'type' => $type,
                'subtotal' => $totals['subtotal'],
                'tax' => $totals['tax'],
                'discount' => $totals['discount'],
                'total' => $totals['total'],
                'paid_amount' => 0,
                'due_amount' => $totals['total'],
                'status' => 'pending',
                'items_data' => json_encode(array_values($items)),
            ]);
        });
    }

    /**
     * Process a payment against an invoice.
     */
    public function processPayment(Invoice $invoice, float $amount, string $method, ?string $reference = null): array
    {
        return DB::transaction(function () use ($invoice, $amount, $method, $reference) {
            // Phase 08: row-lock the invoice so two concurrent payments both
            // validate against the same due and cannot jointly overpay.
            $invoice = Invoice::whereKey($invoice->getKey())->lockForUpdate()->firstOrFail();

            if ($invoice->status === 'paid') {
                return ['success' => false, 'message' => 'Invoice already paid.'];
            }

            if ($invoice->status === 'cancelled') {
                return ['success' => false, 'message' => 'Cannot take payment on a cancelled invoice.'];
            }

            if ($amount <= 0) {
                return ['success' => false, 'message' => 'Amount must be greater than zero.'];
            }

            if (round($amount, 2) > round((float) $invoice->due_amount, 2)) {
                return ['success' => false, 'message' => 'Amount exceeds due amount (৳'.number_format($invoice->due_amount, 2).').'];
            }

            $newPaid = round((float) $invoice->paid_amount + $amount, 2);
            $newDue = round((float) $invoice->total - $newPaid, 2);

            $invoice->update([
                'paid_amount' => $newPaid,
                'due_amount' => max(0, $newDue),
                'status' => $newDue <= 0 ? 'paid' : 'partial',
                'payment_method' => $method,
                'payment_reference' => $reference,
            ]);

            return [
                'success' => true,
                'message' => 'Payment of ৳'.number_format($amount, 2).' recorded successfully.',
                'invoice' => $invoice->fresh(),
            ];
        });
    }

    /**
     * Generate invoice PDF.
     */
    public function generateInvoicePdf(Invoice $invoice): \Barryvdh\DomPDF\PDF
    {
        $invoice->load(['patient', 'admission']);

        $institute = Institute::find($invoice->institute_id);

        $data = [
            'invoice' => $invoice,
            'patient' => $invoice->patient,
            'admission' => $invoice->admission,
            'items' => json_decode($invoice->items_data ?? '[]', true),
            'hospital_name' => $institute->name ?? 'Hospital',
            'hospital_address' => $institute->address ?? '',
        ];

        return Pdf::loadView('medical.billing.invoices.print', $data);
    }

    /**
     * Get revenue summary for a period of paid invoices. Optional doctor
     * fence restricts to that doctor's own billing (null = institute-wide).
     */
    public function getRevenueSummary(int $instituteId, string $period = 'today', ?int $doctorUserId = null, ?int $branchId = null): array
    {
        $query = Invoice::where('institute_id', $instituteId)->where('status', 'paid');
        $query->visibleToDoctor($instituteId, $doctorUserId);
        // Phase 18: optional branch limitation (context branch + legacy).
        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        if ($period === 'today') {
            $query->whereDate('invoice_date', today());
        } elseif ($period === 'week') {
            $query->whereDate('invoice_date', '>=', now()->subDays(7));
        } elseif ($period === 'month') {
            $query->whereDate('invoice_date', '>=', now()->subDays(30));
        }

        $invoices = $query->get();

        return [
            'total_revenue' => $invoices->sum('total'),
            'total_paid' => $invoices->sum('paid_amount'),
            'count' => $invoices->count(),
            'by_type' => $invoices->groupBy('type')->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'revenue' => $group->sum('total'),
                ];
            }),
        ];
    }

    /**
     * Current institute id (services resolve scope through MedicalScope).
     */
    public function currentInstituteId(): int
    {
        return MedicalScope::instituteIdOrFail();
    }
}
