<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\TpaClaimRequest;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\Invoice;
use App\Models\Medical\Patient;
use App\Models\Medical\TpaClaim;
use App\Services\Medical\TpaService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class TpaClaimController extends MedicalController implements HasMiddleware
{
    /**
     * NOTE: the resource param is {claim} (singular of `tpa/claims`), so
     * bound arguments must be named `$claim` for implicit binding.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_tpa.view', only: ['index', 'show']),
            new Middleware('permission:medical_tpa.create', only: ['create', 'store']),
            // No medical_tpa.delete slug exists (Phase 1 set); pending-claim
            // removal is covered by the edit permission instead.
            new Middleware('permission:medical_tpa.edit', only: ['edit', 'update', 'destroy']),
            new Middleware('permission:medical_tpa.approve', only: ['approve', 'reject']),
            new Middleware('permission:medical_tpa.settle', only: ['settle']),
        ];
    }

    protected TpaService $tpaService;

    public function __construct(TpaService $tpaService)
    {
        $this->tpaService = $tpaService;
    }

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();
        $query = TpaClaim::where('institute_id', $instituteId)
            ->with(['patient', 'invoice']);
        // Phase 18: claims follow their invoice's branch (derived).
        if ($this->branchContextId() !== null) {
            $ctx = $this->branchContextId();
            $query->whereHas('invoice', function ($q) use ($ctx) {
                $q->where('branch_id', $ctx)->orWhereNull('branch_id');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        if ($request->filled('tpa_company')) {
            $query->where('tpa_company_name', 'LIKE', '%'.$request->tpa_company.'%');
        }

        if (($fence = $this->doctorFenceId()) !== null) {
            $query->whereHas('invoice', fn ($q) => $q->visibleToDoctor($instituteId, $fence));
        }

        $claims = $query->orderBy('claim_date', 'desc')->paginate(20)->withQueryString();
        $patients = $this->ownPatientOptions($instituteId);
        $stats = $this->tpaService->getClaimStats($instituteId, $fence);

        return view('medical.tpa.claims.index', compact('claims', 'patients', 'stats'));
    }

    public function create(Request $request)
    {
        $instituteId = $this->instituteId();
        $fence = $this->doctorFenceId();
        $patients = $this->ownPatientOptions($instituteId, $fence);
        $invoiceQuery = Invoice::where('institute_id', $instituteId)
            ->whereIn('status', ['pending', 'partial'])
            ->visibleToDoctor($instituteId, $fence);
        // Phase 18: invoice picker follows the branch fence.
        $this->scopeBranch($invoiceQuery);
        $invoices = $invoiceQuery
            ->with(['patient'])
            ->orderBy('invoice_date', 'desc')
            ->get();

        $selectedPatient = null;
        if ($request->filled('patient_id')) {
            $selectedPatient = Patient::where('institute_id', $instituteId)
                ->find($request->patient_id);
            if ($selectedPatient && ! $this->mayActOnPatient($selectedPatient, $fence)) {
                abort(403, 'You do not have permission to book for this patient.');
            }
        }

        $selectedInvoice = null;
        if ($request->filled('invoice_id')) {
            $selectedInvoice = Invoice::where('institute_id', $instituteId)
                ->find($request->invoice_id);
            if ($selectedInvoice) {
                $this->ensureInvoiceVisible($selectedInvoice);
                $this->ensureBranchAccess($selectedInvoice, 'branch_id', 'invoice');
            }
        }

        return view('medical.tpa.claims.create', compact(
            'patients', 'invoices', 'selectedPatient', 'selectedInvoice'
        ));
    }

    public function store(TpaClaimRequest $request)
    {
        $data = $request->validated();
        if (! empty($data['invoice_id'])) {
            // Phase 18: claims follow their invoice's branch (derived).
            $invoice = \App\Models\Medical\Invoice::where('institute_id', $this->instituteId())
                ->find($data['invoice_id']);
            if (! $invoice) {
                abort(404);
            }
            $this->ensureBranchAccess($invoice, 'branch_id', 'invoice');
            if (($fence = $this->doctorFenceId()) !== null) {
                $this->ensureInvoiceVisible($invoice);
            }
        }
        try {
            $claim = $this->tpaService->createClaim($request->validated());
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }

        return redirect()->route('medical.tpa.claims.show', $claim)
            ->with('status', 'TPA claim '.$claim->claim_number.' created successfully!');
    }

    public function show(TpaClaim $claim)
    {
        $this->ensureSameInstitute($claim, 'claim');
        $this->ensureClaimVisible($claim);
        // Phase 18: claims follow their invoice's branch (derived).
        if ($claim->relationLoaded('invoice') || $claim->invoice_id) {
            $invoice = $claim->relationLoaded('invoice') ? $claim->invoice : $claim->invoice()->first();
            if ($invoice) {
                $this->ensureBranchAccess($invoice, 'branch_id', 'invoice');
            }
        }
        $claim->load(['patient', 'invoice']);

        return view('medical.tpa.claims.show', compact('claim'));
    }

    public function edit(TpaClaim $claim)
    {
        $this->ensureSameInstitute($claim, 'claim');
        $this->ensureClaimVisible($claim);
        // Phase 18: claims follow their invoice's branch (derived).
        if ($claim->relationLoaded('invoice') || $claim->invoice_id) {
            $invoice = $claim->relationLoaded('invoice') ? $claim->invoice : $claim->invoice()->first();
            if ($invoice) {
                $this->ensureBranchAccess($invoice, 'branch_id', 'invoice');
            }
        }

        if ($claim->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending claims can be edited.');
        }

        $instituteId = $this->instituteId();
        $patients = $this->ownPatientOptions($instituteId);
        $invoiceQuery = Invoice::where('institute_id', $instituteId)
            ->whereIn('status', ['pending', 'partial'])
            ->visibleToDoctor($instituteId, $this->doctorFenceId());
        $invoices = $invoiceQuery
            ->with(['patient'])
            ->orderBy('invoice_date', 'desc')
            ->get();

        return view('medical.tpa.claims.edit', compact('claim', 'patients', 'invoices'));
    }

    public function update(TpaClaimRequest $request, TpaClaim $claim)
    {
        $this->ensureSameInstitute($claim, 'claim');
        $this->ensureClaimVisible($claim);
        // Phase 18: claims follow their invoice's branch (derived).
        if ($claim->relationLoaded('invoice') || $claim->invoice_id) {
            $invoice = $claim->relationLoaded('invoice') ? $claim->invoice : $claim->invoice()->first();
            if ($invoice) {
                $this->ensureBranchAccess($invoice, 'branch_id', 'invoice');
            }
        }

        if ($claim->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending claims can be updated.');
        }

        $data = $request->validated();
        if (($fence = $this->doctorFenceId()) !== null) {
            if (! empty($data['invoice_id']) && (int) $data['invoice_id'] !== (int) $claim->invoice_id) {
                $invoice = \App\Models\Medical\Invoice::where('institute_id', $claim->institute_id)
                    ->find($data['invoice_id']);
                if (! $invoice) {
                    abort(404);
                }
                $this->ensureInvoiceVisible($invoice);
            }
            if (! empty($data['patient_id']) && (int) $data['patient_id'] !== (int) $claim->patient_id) {
                $patient = \App\Models\Medical\Patient::where('institute_id', $claim->institute_id)
                    ->find($data['patient_id']);
                if (! $patient || ! $this->mayActOnPatient($patient, $fence)) {
                    abort(403, 'You do not have permission to book for this patient.');
                }
            }
        }

        $claim->update($data);

        return redirect()->route('medical.tpa.claims.show', $claim)
            ->with('status', 'TPA claim updated successfully!');
    }

    public function approve(Request $request, TpaClaim $claim)
    {
        $this->ensureSameInstitute($claim, 'claim');
        $this->ensureClaimVisible($claim);
        // Phase 18: claims follow their invoice's branch (derived).
        if ($claim->relationLoaded('invoice') || $claim->invoice_id) {
            $invoice = $claim->relationLoaded('invoice') ? $claim->invoice : $claim->invoice()->first();
            if ($invoice) {
                $this->ensureBranchAccess($invoice, 'branch_id', 'invoice');
            }
        }

        if ($claim->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending claims can be approved.');
        }

        $request->validate([
            'approved_amount' => 'required|numeric|min:0.01|max:'.$claim->claim_amount,
        ]);

        try {
            $this->tpaService->approveClaim($claim, (float) $request->approved_amount);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('medical.tpa.claims.show', $claim)
            ->with('status', 'TPA claim approved successfully!');
    }

    public function reject(Request $request, TpaClaim $claim)
    {
        $this->ensureSameInstitute($claim, 'claim');
        $this->ensureClaimVisible($claim);
        // Phase 18: claims follow their invoice's branch (derived).
        if ($claim->relationLoaded('invoice') || $claim->invoice_id) {
            $invoice = $claim->relationLoaded('invoice') ? $claim->invoice : $claim->invoice()->first();
            if ($invoice) {
                $this->ensureBranchAccess($invoice, 'branch_id', 'invoice');
            }
        }

        if ($claim->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending claims can be rejected.');
        }

        $request->validate([
            'remarks' => 'required|string|max:2000',
        ]);

        $this->tpaService->rejectClaim($claim, (string) $request->remarks);

        return redirect()->route('medical.tpa.claims.show', $claim)
            ->with('status', 'TPA claim rejected.');
    }

    public function settle(TpaClaim $claim)
    {
        $this->ensureSameInstitute($claim, 'claim');
        $this->ensureClaimVisible($claim);
        // Phase 18: claims follow their invoice's branch (derived).
        if ($claim->relationLoaded('invoice') || $claim->invoice_id) {
            $invoice = $claim->relationLoaded('invoice') ? $claim->invoice : $claim->invoice()->first();
            if ($invoice) {
                $this->ensureBranchAccess($invoice, 'branch_id', 'invoice');
            }
        }

        try {
            $this->tpaService->settleClaim($claim);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('medical.tpa.claims.show', $claim)
            ->with('status', 'TPA claim settled successfully!');
    }

    public function destroy(TpaClaim $claim)
    {
        $this->ensureSameInstitute($claim, 'claim');
        $this->ensureClaimVisible($claim);
        // Phase 18: claims follow their invoice's branch (derived).
        if ($claim->relationLoaded('invoice') || $claim->invoice_id) {
            $invoice = $claim->relationLoaded('invoice') ? $claim->invoice : $claim->invoice()->first();
            if ($invoice) {
                $this->ensureBranchAccess($invoice, 'branch_id', 'invoice');
            }
        }

        if ($claim->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending claims can be deleted.');
        }

        // Phase 03: pending-claim removal leaves an attributable trail.
        ClinicalAuditLog::record($claim, 'deleted', [
            'old' => ClinicalAuditLog::snapshot($claim),
        ]);
        $claim->delete();

        return redirect()->route('medical.tpa.claims.index')
            ->with('status', 'TPA claim deleted successfully!');
    }
}
