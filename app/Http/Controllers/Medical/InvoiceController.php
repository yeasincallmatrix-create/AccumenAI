<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\InvoiceRequest;
use App\Http\Requests\Medical\PaymentRequest;
use App\Models\Medical\Admission;
use App\Models\Medical\Invoice;
use App\Models\Medical\Patient;
use App\Services\Medical\BillingService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class InvoiceController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_billing.view', only: ['index', 'show', 'print', 'payments']),
            new Middleware('permission:medical_billing.create', only: ['create', 'store']),
            new Middleware('permission:medical_billing.edit', only: ['edit', 'update']),
            new Middleware('permission:medical_billing.delete', only: ['destroy']),
            new Middleware('permission:medical_billing.process', only: ['payment']),
        ];
    }

    protected BillingService $billingService;

    public function __construct(BillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();
        $query = Invoice::where('institute_id', $instituteId)
            ->with(['patient', 'admission']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('invoice_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('invoice_date', '<=', $request->to_date);
        }

        $query = $this->scopeOwnInvoices($query);

        $invoices = $query->orderBy('invoice_date', 'desc')->paginate(20)->withQueryString();
        $patients = $this->ownPatientOptions($instituteId);

        return view('medical.billing.invoices.index', compact('invoices', 'patients'));
    }

    public function create(Request $request)
    {
        $instituteId = $this->instituteId();
        $fence = $this->doctorFenceId();
        $patients = $this->ownPatientOptions($instituteId, $fence);
        $admissionQuery = Admission::where('institute_id', $instituteId)
            ->where('status', 'active');
        if ($fence !== null) {
            $admissionQuery->where('admitting_doctor_id', $fence);
        }
        $admissions = $admissionQuery
            ->with('patient')
            ->get();

        $selectedPatient = null;
        if ($request->filled('patient_id')) {
            $selectedPatient = Patient::where('institute_id', $instituteId)
                ->find($request->patient_id);
            if ($selectedPatient && ! $this->mayActOnPatient($selectedPatient, $fence)) {
                abort(403, 'You do not have permission to book for this patient.');
            }
        }

        $selectedAdmission = null;
        if ($request->filled('admission_id')) {
            $selectedAdmission = Admission::where('institute_id', $instituteId)
                ->find($request->admission_id);
            if ($selectedAdmission && $fence !== null
                && (int) $selectedAdmission->admitting_doctor_id !== $fence) {
                abort(403, 'You do not have permission to access this admission.');
            }
        }

        $invoiceType = in_array($request->get('type'), ['opd', 'ipd', 'pharmacy', 'lab', 'surgery'], true)
            ? $request->get('type')
            : 'opd';

        return view('medical.billing.invoices.create', compact(
            'patients', 'admissions', 'selectedPatient', 'selectedAdmission', 'invoiceType'
        ));
    }

    public function store(InvoiceRequest $request)
    {
        $instituteId = $this->instituteId();
        $data = $request->validated();

        $patient = Patient::where('institute_id', $instituteId)->findOrFail($data['patient_id']);
        if (! $this->mayActOnPatient($patient)) {
            abort(403, 'You do not have permission to book for this patient.');
        }

        $items = collect($data['items'])->map(function ($item) {
            return [
                'description' => $item['description'],
                'amount' => (float) $item['amount'],
                'quantity' => (int) $item['quantity'],
                'discount' => (float) ($item['discount'] ?? 0),
            ];
        })->toArray();

        if ($data['type'] === 'ipd') {
            $admission = Admission::where('institute_id', $instituteId)
                ->findOrFail($data['admission_id']);

            if ((int) $admission->patient_id !== (int) $patient->id) {
                return redirect()->back()
                    ->with('error', 'The selected admission belongs to a different patient.')
                    ->withInput();
            }

            if (($fence = $this->doctorFenceId()) !== null
                && (int) $admission->admitting_doctor_id !== $fence) {
                abort(403, 'You do not have permission to access this admission.');
            }

            $invoice = $this->billingService->generateIpdInvoice($admission, $items);
        } else {
            $invoice = match ($data['type']) {
                'opd' => $this->billingService->generateOpdInvoice($patient, $items),
                'pharmacy' => $this->billingService->generatePharmacyInvoice($patient, $items),
                'lab' => $this->billingService->generateLabInvoice($patient, $items),
                default => $this->billingService->generateOpdInvoice($patient, $items),
            };
            // 'surgery' has no dedicated generator yet — booked as OPD-type
            // line items with the surgery label preserved below.
            if ($data['type'] === 'surgery') {
                $invoice->update(['type' => 'surgery']);
            }
        }

        return redirect()->route('medical.billing.invoices.show', $invoice)
            ->with('status', 'Invoice '.$invoice->invoice_number.' created successfully!');
    }

    public function show(Invoice $invoice)
    {
        $this->ensureSameInstitute($invoice, 'invoice');
        $this->ensureInvoiceVisible($invoice);
        $invoice->load(['patient', 'admission', 'tpaClaims']);
        $items = json_decode($invoice->items_data ?? '[]', true);

        return view('medical.billing.invoices.show', compact('invoice', 'items'));
    }

    public function edit(Invoice $invoice)
    {
        $this->ensureSameInstitute($invoice, 'invoice');
        $this->ensureInvoiceVisible($invoice);

        if ($invoice->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending invoices can be edited.');
        }

        $instituteId = $this->instituteId();
        $patients = $this->ownPatientOptions($instituteId);
        $admissionQuery = Admission::where('institute_id', $instituteId)
            ->where('status', 'active');
        if (($fence = $this->doctorFenceId()) !== null) {
            $admissionQuery->where('admitting_doctor_id', $fence);
        }
        $admissions = $admissionQuery
            ->with('patient')
            ->get();
        $items = json_decode($invoice->items_data ?? '[]', true);

        return view('medical.billing.invoices.edit', compact('invoice', 'patients', 'admissions', 'items'));
    }

    public function update(InvoiceRequest $request, Invoice $invoice)
    {
        $this->ensureSameInstitute($invoice, 'invoice');
        $this->ensureInvoiceVisible($invoice);

        if ($invoice->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending invoices can be updated.');
        }

        $data = $request->validated();
        $items = collect($data['items'])->map(function ($item) {
            return [
                'description' => $item['description'],
                'amount' => (float) $item['amount'],
                'quantity' => (int) $item['quantity'],
                'discount' => (float) ($item['discount'] ?? 0),
            ];
        })->toArray();

        if (($fence = $this->doctorFenceId()) !== null
            && (int) ($data['patient_id'] ?? 0) !== (int) $invoice->patient_id) {
            $next = Patient::where('institute_id', $invoice->institute_id)->find($data['patient_id']);
            if (! $next || ! $this->mayActOnPatient($next, $fence)) {
                abort(403, 'You do not have permission to book for this patient.');
            }
        }

        $subtotal = collect($items)->sum(fn ($i) => $i['amount'] * $i['quantity']);
        $tax = round($subtotal * 0.05, 2);
        $discount = round((float) collect($items)->sum('discount'), 2);
        $total = round($subtotal + $tax - $discount, 2);

        $invoice->update([
            'patient_id' => $data['patient_id'],
            'admission_id' => $data['admission_id'] ?? null,
            'type' => $data['type'],
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'total' => $total,
            'due_amount' => round($total - (float) $invoice->paid_amount, 2),
            'items_data' => json_encode(array_values($items)),
            'notes' => $data['notes'] ?? null,
        ]);

        return redirect()->route('medical.billing.invoices.show', $invoice)
            ->with('status', 'Invoice updated successfully!');
    }

    public function destroy(Invoice $invoice)
    {
        $this->ensureSameInstitute($invoice, 'invoice');
        $this->ensureInvoiceVisible($invoice);

        if (in_array($invoice->status, ['paid', 'partial'], true)) {
            return redirect()->back()->with('error', 'Cannot delete an invoice with recorded payments.');
        }

        // Phase 03: a linked TPA claim is a submitted financial request —
        // deleting the invoice must not vaporize it via cascade. Settle or
        // remove the pending claim first.
        if ($invoice->tpaClaims()->exists()) {
            return redirect()->back()->with('error', 'Cannot delete an invoice with linked TPA claims. Resolve the claims first.');
        }

        // Phase 03: financial record removal leaves an attributable trail.
        \App\Models\Medical\ClinicalAuditLog::record($invoice, 'deleted', [
            'old' => \App\Models\Medical\ClinicalAuditLog::snapshot($invoice),
        ]);
        $invoice->delete();

        return redirect()->route('medical.billing.invoices.index')
            ->with('status', 'Invoice deleted successfully!');
    }

    public function payment(PaymentRequest $request, Invoice $invoice)
    {
        $this->ensureSameInstitute($invoice, 'invoice');
        $this->ensureInvoiceVisible($invoice);

        $result = $this->billingService->processPayment(
            $invoice,
            (float) $request->amount,
            (string) $request->method,
            $request->reference
        );

        if (! $result['success']) {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->route('medical.billing.invoices.show', $invoice)
            ->with('status', $result['message']);
    }

    /**
     * Payment history (derived — payments live on the invoice rows; there
     * is no separate payments table in Phase 0 scope).
     */
    public function payments(Request $request)
    {
        $query = Invoice::where('institute_id', $this->instituteId())
            ->where('paid_amount', '>', 0)
            ->with(['patient']);
        $query = $this->scopeOwnInvoices($query);

        if ($request->filled('method')) {
            $query->where('payment_method', $request->method);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('invoice_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('invoice_date', '<=', $request->to_date);
        }

        $invoices = $query->orderBy('invoice_date', 'desc')->paginate(20)->withQueryString();

        return view('medical.billing.payments.index', compact('invoices'));
    }

    public function print(Invoice $invoice)
    {
        $this->ensureSameInstitute($invoice, 'invoice');
        $this->ensureInvoiceVisible($invoice);

        if ($invoice->status === 'draft') {
            return redirect()->back()->with('error', 'Cannot print a draft invoice.');
        }

        $pdf = $this->billingService->generateInvoicePdf($invoice);

        return $pdf->download('invoice-'.$invoice->invoice_number.'.pdf');
    }
}
