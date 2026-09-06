<?php

namespace App\Services\Medical;

use App\Models\Institute;
use App\Models\Medical\Admission;
use App\Models\Medical\Invoice;
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
     * Generate a unique invoice number (INV-YYYY-III-XXXXX).
     */
    public function generateInvoiceNumber(int $instituteId): string
    {
        $year = date('Y');
        $prefix = 'INV-'.$year.'-'.str_pad((string) $instituteId, 3, '0', STR_PAD_LEFT).'-';

        return DB::transaction(function () use ($instituteId, $year, $prefix) {
            $last = Invoice::where('institute_id', $instituteId)
                ->whereYear('created_at', $year)
                ->orderBy('id', 'desc')
                ->lockForUpdate()
                ->first();

            $nextNumber = 1;
            if ($last && preg_match('/(\d{5})$/', (string) $last->invoice_number, $m)) {
                $nextNumber = ((int) $m[1]) + 1;
            } elseif ($last) {
                $nextNumber = Invoice::where('institute_id', $instituteId)
                    ->whereYear('created_at', $year)
                    ->count() + 1;
            }

            $candidate = $prefix.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);

            while (Invoice::where('invoice_number', $candidate)->exists()) {
                $nextNumber++;
                $candidate = $prefix.str_pad((string) $nextNumber, 5, '0', STR_PAD_LEFT);
            }

            return $candidate;
        });
    }

    /**
     * Generate OPD invoice.
     */
    public function generateOpdInvoice(Patient $patient, array $items): Invoice
    {
        return $this->createInvoice($patient, 'opd', $items);
    }

    /**
     * Generate IPD invoice.
     */
    public function generateIpdInvoice(Admission $admission, array $items): Invoice
    {
        return $this->createInvoice($admission->patient, 'ipd', $items, $admission->id);
    }

    /**
     * Generate pharmacy invoice.
     */
    public function generatePharmacyInvoice(Patient $patient, array $items): Invoice
    {
        return $this->createInvoice($patient, 'pharmacy', $items);
    }

    /**
     * Generate lab invoice.
     */
    public function generateLabInvoice(Patient $patient, array $items): Invoice
    {
        return $this->createInvoice($patient, 'lab', $items);
    }

    /**
     * Create an invoice. Items: [{description, amount, quantity, discount}].
     */
    public function createInvoice(Patient $patient, string $type, array $items, ?int $admissionId = null): Invoice
    {
        return DB::transaction(function () use ($patient, $type, $items, $admissionId) {
            $instituteId = (int) $patient->institute_id;

            $subtotal = collect($items)->sum(fn ($i) => ((float) ($i['amount'] ?? 0)) * ((int) ($i['quantity'] ?? 1)));
            $tax = round($subtotal * 0.05, 2); // 5% tax
            $discount = round((float) collect($items)->sum('discount'), 2);
            $total = round($subtotal + $tax - $discount, 2);

            return Invoice::create([
                'institute_id' => $instituteId,
                'patient_id' => $patient->id,
                'admission_id' => $admissionId,
                'invoice_number' => $this->generateInvoiceNumber($instituteId),
                'invoice_date' => now()->format('Y-m-d'),
                'due_date' => now()->addDays(15)->format('Y-m-d'),
                'type' => $type,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'discount' => $discount,
                'total' => $total,
                'paid_amount' => 0,
                'due_amount' => $total,
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
            $invoice->refresh();

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
     * Get revenue summary for a period of paid invoices.
     */
    public function getRevenueSummary(int $instituteId, string $period = 'today'): array
    {
        $query = Invoice::where('institute_id', $instituteId)->where('status', 'paid');

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
