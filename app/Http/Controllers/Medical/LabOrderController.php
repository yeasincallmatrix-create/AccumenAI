<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\LabOrderRequest;
use App\Http\Requests\Medical\LabResultRequest;
use App\Models\Medical\ClinicalAuditLog;
use App\Models\Medical\LabOrder;
use App\Models\Medical\LabTest;
use App\Models\Medical\Patient;
use App\Models\Medical\Prescription;
use App\Services\Medical\LabService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class LabOrderController extends MedicalController implements HasMiddleware
{
    /**
     * NOTE: the resource param is {order} (singular of `lab/orders`), so
     * bound arguments must be named `$order`. The result-entry route calls
     * this controller's `enterResult` method (see routes/medical.php).
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_lab.view', only: ['index', 'show', 'report']),
            new Middleware('permission:medical_lab.create', only: ['create', 'store']),
            new Middleware('permission:medical_lab.edit', only: ['edit', 'update', 'collect', 'enterResult', 'cancel']),
            new Middleware('permission:medical_lab.delete', only: ['destroy']),
        ];
    }

    protected LabService $labService;

    public function __construct(LabService $labService)
    {
        $this->labService = $labService;
    }

    public function index(Request $request)
    {
        $instituteId = $this->instituteId();
        $query = LabOrder::where('institute_id', $instituteId)
            ->with(['patient', 'doctor']);
        // Phase 18: branch fence (context branch + legacy NULLs).
        $this->scopeBranch($query);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('order_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('order_date', '<=', $request->to_date);
        }

        if (($fence = $this->doctorFenceId()) !== null) {
            $query->where('doctor_id', $fence);
        }

        $orders = $query->orderBy('order_date', 'desc')->paginate(20)->withQueryString();
        $patients = $this->ownPatientOptions($instituteId);

        return view('medical.lab.orders.index', compact('orders', 'patients'));
    }

    public function create(Request $request)
    {
        $instituteId = $this->instituteId();
        $fence = $this->doctorFenceId();

        $patients = $this->ownPatientOptions($instituteId, $fence);
        $doctors = $this->doctors();
        if ($fence !== null) {
            $doctors = $doctors->where('id', $fence)->values();
        }
        // Phase 18: hide clinicians assigned exclusively to other branches.
        if ($this->branchContextId() !== null) {
            $allowed = $this->branchDoctorUserIds($this->branchContextId(), $instituteId);
            $doctors = $doctors->whereIn('id', $allowed)->values();
        }
        $tests = LabTest::where('institute_id', $instituteId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $prescriptionQuery = Prescription::where('institute_id', $instituteId)
            ->where('is_finalized', true);
        // Phase 18: prescription picker follows the branch fence.
        $this->scopeBranch($prescriptionQuery);
        if ($fence !== null) {
            $prescriptionQuery->where('doctor_id', $fence);
        }
        $prescriptions = $prescriptionQuery
            ->orderBy('prescription_date', 'desc')
            ->limit(100)
            ->get();

        $selectedPatient = null;
        if ($request->filled('patient_id')) {
            $selectedPatient = Patient::where('institute_id', $instituteId)
                ->find($request->patient_id);
            if ($selectedPatient && ! $this->mayActOnPatient($selectedPatient, $fence)) {
                abort(403, 'You do not have permission to book for this patient.');
            }
        }

        $selectedPrescription = null;
        if ($request->filled('prescription_id')) {
            $selectedPrescription = Prescription::where('institute_id', $instituteId)
                ->find($request->prescription_id);
            if ($selectedPrescription && $fence !== null
                && (int) $selectedPrescription->doctor_id !== $fence) {
                abort(403, 'You do not have permission to access this prescription.');
            }
            // Phase 18: never pre-select a foreign-branch prescription.
            if ($selectedPrescription
                && ! \App\Support\BranchContext::allows($selectedPrescription->branch_id ?? null)) {
                $selectedPrescription = null;
            }
        }

        return view('medical.lab.orders.create', compact(
            'patients', 'doctors', 'tests', 'prescriptions', 'selectedPatient', 'selectedPrescription'
        ));
    }

    public function store(LabOrderRequest $request)
    {
        $data = $request->validated();
        $tests = $data['tests'];
        unset($data['tests']);

        if (($fence = $this->doctorFenceId()) !== null) {
            $data['doctor_id'] = $fence;
            $patient = Patient::where('institute_id', $this->instituteId())->findOrFail($data['patient_id']);
            if (! $this->mayActOnPatient($patient, $fence)) {
                abort(403, 'You do not have permission to book for this patient.');
            }
            if (! empty($data['prescription_id'])) {
                $linked = Prescription::where('institute_id', $this->instituteId())->find($data['prescription_id']);
                if (! $linked || (int) $linked->doctor_id !== $fence) {
                    abort(403, 'You do not have permission to access this prescription.');
                }
            }
        }

        // Optional encounter link: same institute + same patient (legacy and
        // encounter-less orders keep NULL).
        $encounter = null;
        if (! empty($data['encounter_id'])) {
            $encounter = \App\Models\Medical\Encounter::where('institute_id', $this->instituteId())
                ->findOrFail($data['encounter_id']);
            if ((int) $encounter->patient_id !== (int) $data['patient_id']) {
                return redirect()->back()
                    ->with('error', 'The selected encounter belongs to a different patient.')
                    ->withInput();
            }
            if (($fence ?? null) !== null && (int) $encounter->doctor_id !== (int) $fence) {
                abort(403, 'You do not have permission to use this encounter.');
            }
            $this->ensureBranchAccess($encounter, 'branch_id', 'encounter');
        }
        // Phase 18: branch ownership inherits the linked encounter's branch
        // (deterministic); an explicit contradicting branch is rejected.
        $requestedBranch = $request->input('branch_id');
        if ($encounter && $encounter->branch_id !== null) {
            if ($requestedBranch !== null && $requestedBranch !== ''
                && (int) $requestedBranch !== (int) $encounter->branch_id) {
                return redirect()->back()
                    ->with('error', 'The lab order branch must match its encounter branch.')
                    ->withInput();
            }
            $data['branch_id'] = $encounter->branch_id;
        } else {
            $data['branch_id'] = $this->resolveBranchId($requestedBranch);
        }
        if (! $this->doctorBranchOk((int) $data['doctor_id'], $data['branch_id'], $this->instituteId())) {
            return redirect()->back()
                ->with('error', 'The selected doctor is not assigned to this branch.')
                ->withInput();
        }
        // Phase 18: a linked prescription must be branch-compatible.
        if (! empty($data['prescription_id'])) {
            $linkedRx = Prescription::where('institute_id', $this->instituteId())->find($data['prescription_id']);
            if ($linkedRx) {
                $this->ensureBranchAccess($linkedRx, 'branch_id', 'prescription');
                if ($data['branch_id'] !== null && $linkedRx->branch_id !== null
                    && (int) $linkedRx->branch_id !== (int) $data['branch_id']) {
                    return redirect()->back()
                        ->with('error', 'The prescription belongs to another branch.')
                        ->withInput();
                }
            }
        }

        $order = $this->labService->createOrder($data, $tests);

        return redirect()->route('medical.lab.orders.show', $order)
            ->with('status', 'Lab order '.$order->order_number.' created successfully!');
    }

    public function show(LabOrder $order)
    {
        $this->ensureSameInstitute($order, 'order');
        $this->ensureDoctorOwns($order, 'doctor_id', 'order');
        $this->ensureBranchAccess($order, 'branch_id', 'order');
        $order->load(['patient', 'doctor', 'prescription', 'results.labTest', 'collectedBy', 'completedBy']);

        return view('medical.lab.orders.show', compact('order'));
    }

    public function edit(LabOrder $order)
    {
        $this->ensureSameInstitute($order, 'order');
        $this->ensureDoctorOwns($order, 'doctor_id', 'order');
        $this->ensureBranchAccess($order, 'branch_id', 'order');

        if ($order->status !== 'ordered') {
            return redirect()->back()->with('error', 'Only orders with status "ordered" can be edited.');
        }

        $instituteId = $this->instituteId();
        $patients = $this->ownPatientOptions($instituteId);
        $doctors = $this->doctors();
        if (($fence = $this->doctorFenceId()) !== null) {
            $doctors = $doctors->where('id', $fence)->values();
        }
        // Phase 18: hide clinicians assigned exclusively to other branches.
        if ($this->branchContextId() !== null) {
            $allowed = $this->branchDoctorUserIds($this->branchContextId(), $instituteId);
            $doctors = $doctors->whereIn('id', $allowed)->values();
        }
        $tests = LabTest::where('institute_id', $instituteId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $order->load('results');

        return view('medical.lab.orders.edit', compact('order', 'patients', 'doctors', 'tests'));
    }

    public function update(LabOrderRequest $request, LabOrder $order)
    {
        $this->ensureSameInstitute($order, 'order');
        $this->ensureDoctorOwns($order, 'doctor_id', 'order');
        $this->ensureBranchAccess($order, 'branch_id', 'order');

        if ($order->status !== 'ordered') {
            return redirect()->back()->with('error', 'Only orders with status "ordered" can be updated.');
        }

        $data = $request->validated();
        $tests = $data['tests'];
        unset($data['tests'], $data['doctor_id']);
        // Phase 18: branch identity never moves between records.
        unset($data['branch_id']);

        // Header update; test set replaced wholesale (results still pending
        // at this stage, so no entered values are lost).
        // Phase 01: snapshot header + test set for the amendment audit.
        $original = ClinicalAuditLog::snapshot($order);
        $originalTests = $order->results()->pluck('lab_test_id')->sort()->values()->all();
        $order->update($data);
        $order->results()->delete();
        foreach ($tests as $test) {
            $order->results()->create([
                'lab_test_id' => $test['lab_test_id'],
                'status' => 'pending',
            ]);
        }
        [$old, $new] = ClinicalAuditLog::diff($original, ClinicalAuditLog::snapshot($order->refresh()));
        $newTests = collect($tests)->pluck('lab_test_id')->sort()->values()->all();
        if ($originalTests !== $newTests) {
            $old['tests'] = $originalTests;
            $new['tests'] = $newTests;
        }
        if ($old !== [] || $new !== []) {
            ClinicalAuditLog::record($order, 'updated', ['old' => $old, 'new' => $new]);
        }

        return redirect()->route('medical.lab.orders.show', $order)
            ->with('status', 'Lab order updated successfully!');
    }

    public function destroy(LabOrder $order)
    {
        $this->ensureSameInstitute($order, 'order');
        $this->ensureDoctorOwns($order, 'doctor_id', 'order');
        $this->ensureBranchAccess($order, 'branch_id', 'order');

        if ($order->status !== 'ordered') {
            return redirect()->back()->with('error', 'Cannot delete orders that are being processed.');
        }

        // Phase 03: deletion is archival (soft delete) so result rows are
        // never vaporized by the database cascade; the audit keeps context.
        $order->results()->delete();
        ClinicalAuditLog::record($order, 'deleted', [
            'old' => ClinicalAuditLog::snapshot($order),
        ]);
        $order->delete();

        return redirect()->route('medical.lab.orders.index')
            ->with('status', 'Lab order deleted successfully!');
    }

    /**
     * Phase 15 — Cancel a lab order (ordered→cancelled). Cancellation is a
     * status with audit, never a delete: the row stays historically visible.
     * Only untouched (ordered) rows can be cancelled; collected/processing
     * rows keep flowing through results so lab history is never rewritten.
     */
    public function cancel(Request $request, LabOrder $order)
    {
        $this->ensureSameInstitute($order, 'order');
        $this->ensureDoctorOwns($order, 'doctor_id', 'order');
        $this->ensureBranchAccess($order, 'branch_id', 'order');

        if ($order->status !== 'ordered') {
            return redirect()->back()->with('error', 'Only uncollected orders can be cancelled.');
        }

        $reason = trim((string) $request->input('reason', ''));
        if ($reason === '') {
            return redirect()->back()->with('error', 'A cancellation reason is required.');
        }

        $order->update(['status' => 'cancelled']);
        ClinicalAuditLog::record($order, 'cancelled', [
            'old' => ['status' => 'ordered'],
            'new' => ['status' => 'cancelled'],
            'reason' => $reason,
        ]);

        return redirect()->route('medical.lab.orders.show', $order)
            ->with('status', 'Lab order cancelled (history preserved).');
    }

    public function collect(LabOrder $order)
    {
        $this->ensureSameInstitute($order, 'order');
        $this->ensureDoctorOwns($order, 'doctor_id', 'order');
        $this->ensureBranchAccess($order, 'branch_id', 'order');

        if ($order->status !== 'ordered') {
            return redirect()->back()->with('error', 'This order cannot be collected.');
        }

        $this->labService->collectSample($order);

        return redirect()->route('medical.lab.orders.show', $order)
            ->with('status', 'Sample collected successfully!');
    }

    /**
     * Result entry form (GET is served by the same URI pattern through the
     * `medical.lab.orders.result` POST route's companion view link â€” the
     * show page links here via query; see routes/medical.php).
     */
    public function resultForm(LabOrder $order)
    {
        $this->ensureSameInstitute($order, 'order');
        $this->ensureDoctorOwns($order, 'doctor_id', 'order');
        $this->ensureBranchAccess($order, 'branch_id', 'order');

        if (! $order->readyForResults()) {
            return redirect()->back()->with('error', 'This order is not ready for results.');
        }

        $order->load(['patient', 'results.labTest']);

        return view('medical.lab.orders.result', compact('order'));
    }

    /**
     * Enter results (route: POST lab/orders/{order}/result).
     */
    public function enterResult(LabResultRequest $request, LabOrder $order)
    {
        $this->ensureSameInstitute($order, 'order');
        $this->ensureDoctorOwns($order, 'doctor_id', 'order');
        $this->ensureBranchAccess($order, 'branch_id', 'order');

        if (! $order->readyForResults()) {
            return redirect()->back()->with('error', 'This order is not ready for results.');
        }

        // Phase 01: snapshot pending rows; entered values must stay attributable.
        $before = $order->results()->get()->mapWithKeys(fn ($r) => [
            $r->getKey() => ClinicalAuditLog::snapshot($r),
        ])->all();

        $this->labService->enterResults($order, $request->validated()['results']);

        $after = $order->results()->get()->mapWithKeys(fn ($r) => [
            $r->getKey() => ClinicalAuditLog::snapshot($r->refresh()),
        ])->all();
        ClinicalAuditLog::record($order, 'result_entered', ['old' => $before, 'new' => $after]);

        return redirect()->route('medical.lab.orders.show', $order)
            ->with('status', 'Results entered successfully!');
    }

    public function report(LabOrder $order)
    {
        $this->ensureSameInstitute($order, 'order');
        $this->ensureDoctorOwns($order, 'doctor_id', 'order');
        $this->ensureBranchAccess($order, 'branch_id', 'order');

        if ($order->status !== 'completed') {
            return redirect()->back()->with('error', 'Report is only available for completed orders.');
        }

        $pdf = $this->labService->generateReport($order);

        return $pdf->download('lab-report-'.$order->order_number.'.pdf');
    }

    /**
     * Doctors available for ordering â€” tenant-scoped to members/profile
     * holders of this institute (Phase 02; validation enforces the same).
     */
    private function doctors()
    {
        return \App\Support\MedicalScope::instituteDoctors($this->instituteId());
    }
}
