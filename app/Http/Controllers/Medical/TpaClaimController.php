<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\TpaClaimRequest;
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

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        if ($request->filled('tpa_company')) {
            $query->where('tpa_company_name', 'LIKE', '%'.$request->tpa_company.'%');
        }

        $claims = $query->orderBy('claim_date', 'desc')->paginate(20)->withQueryString();
        $patients = Patient::where('institute_id', $instituteId)
            ->active()
            ->orderBy('first_name')
            ->get();
        $stats = $this->tpaService->getClaimStats($instituteId);

        return view('medical.tpa.claims.index', compact('claims', 'patients', 'stats'));
    }

    public function create(Request $request)
    {
        $instituteId = $this->instituteId();
        $patients = Patient::where('institute_id', $instituteId)
            ->active()
            ->orderBy('first_name')
            ->get();
        $invoices = Invoice::where('institute_id', $instituteId)
            ->whereIn('status', ['pending', 'partial'])
            ->with(['patient'])
            ->orderBy('invoice_date', 'desc')
            ->get();

        $selectedPatient = null;
        if ($request->filled('patient_id')) {
            $selectedPatient = Patient::where('institute_id', $instituteId)
                ->find($request->patient_id);
        }

        $selectedInvoice = null;
        if ($request->filled('invoice_id')) {
            $selectedInvoice = Invoice::where('institute_id', $instituteId)
                ->find($request->invoice_id);
        }

        return view('medical.tpa.claims.create', compact(
            'patients', 'invoices', 'selectedPatient', 'selectedInvoice'
        ));
    }

    public function store(TpaClaimRequest $request)
    {
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
        $claim->load(['patient', 'invoice']);

        return view('medical.tpa.claims.show', compact('claim'));
    }

    public function edit(TpaClaim $claim)
    {
        $this->ensureSameInstitute($claim, 'claim');

        if ($claim->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending claims can be edited.');
        }

        $instituteId = $this->instituteId();
        $patients = Patient::where('institute_id', $instituteId)
            ->active()
            ->orderBy('first_name')
            ->get();
        $invoices = Invoice::where('institute_id', $instituteId)
            ->whereIn('status', ['pending', 'partial'])
            ->with(['patient'])
            ->orderBy('invoice_date', 'desc')
            ->get();

        return view('medical.tpa.claims.edit', compact('claim', 'patients', 'invoices'));
    }

    public function update(TpaClaimRequest $request, TpaClaim $claim)
    {
        $this->ensureSameInstitute($claim, 'claim');

        if ($claim->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending claims can be updated.');
        }

        $claim->update($request->validated());

        return redirect()->route('medical.tpa.claims.show', $claim)
            ->with('status', 'TPA claim updated successfully!');
    }

    public function approve(Request $request, TpaClaim $claim)
    {
        $this->ensureSameInstitute($claim, 'claim');

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

        if ($claim->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending claims can be deleted.');
        }

        $claim->delete();

        return redirect()->route('medical.tpa.claims.index')
            ->with('status', 'TPA claim deleted successfully!');
    }
}
