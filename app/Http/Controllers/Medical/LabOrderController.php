<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\LabOrderRequest;
use App\Http\Requests\Medical\LabResultRequest;
use App\Models\Medical\LabOrder;
use App\Models\Medical\LabTest;
use App\Models\Medical\Patient;
use App\Models\Medical\Prescription;
use App\Models\User;
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
            new Middleware('permission:medical_lab.edit', only: ['edit', 'update', 'collect', 'enterResult']),
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

        $orders = $query->orderBy('order_date', 'desc')->paginate(20)->withQueryString();
        $patients = Patient::where('institute_id', $instituteId)
            ->active()
            ->orderBy('first_name')
            ->get();

        return view('medical.lab.orders.index', compact('orders', 'patients'));
    }

    public function create(Request $request)
    {
        $instituteId = $this->instituteId();

        $patients = Patient::where('institute_id', $instituteId)
            ->active()
            ->orderBy('first_name')
            ->get();
        $doctors = $this->doctors();
        $tests = LabTest::where('institute_id', $instituteId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();
        $prescriptions = Prescription::where('institute_id', $instituteId)
            ->where('is_finalized', true)
            ->orderBy('prescription_date', 'desc')
            ->limit(100)
            ->get();

        $selectedPatient = null;
        if ($request->filled('patient_id')) {
            $selectedPatient = Patient::where('institute_id', $instituteId)
                ->find($request->patient_id);
        }

        $selectedPrescription = null;
        if ($request->filled('prescription_id')) {
            $selectedPrescription = Prescription::where('institute_id', $instituteId)
                ->find($request->prescription_id);
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

        $order = $this->labService->createOrder($data, $tests);

        return redirect()->route('medical.lab.orders.show', $order)
            ->with('status', 'Lab order '.$order->order_number.' created successfully!');
    }

    public function show(LabOrder $order)
    {
        $this->ensureSameInstitute($order, 'order');
        $order->load(['patient', 'doctor', 'prescription', 'results.labTest', 'collectedBy', 'completedBy']);

        return view('medical.lab.orders.show', compact('order'));
    }

    public function edit(LabOrder $order)
    {
        $this->ensureSameInstitute($order, 'order');

        if ($order->status !== 'ordered') {
            return redirect()->back()->with('error', 'Only orders with status "ordered" can be edited.');
        }

        $instituteId = $this->instituteId();
        $patients = Patient::where('institute_id', $instituteId)
            ->active()
            ->orderBy('first_name')
            ->get();
        $doctors = $this->doctors();
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

        if ($order->status !== 'ordered') {
            return redirect()->back()->with('error', 'Only orders with status "ordered" can be updated.');
        }

        $data = $request->validated();
        $tests = $data['tests'];
        unset($data['tests'], $data['doctor_id']);

        // Header update; test set replaced wholesale (results still pending
        // at this stage, so no entered values are lost).
        $order->update($data);
        $order->results()->delete();
        foreach ($tests as $test) {
            $order->results()->create([
                'lab_test_id' => $test['lab_test_id'],
                'status' => 'pending',
            ]);
        }

        return redirect()->route('medical.lab.orders.show', $order)
            ->with('status', 'Lab order updated successfully!');
    }

    public function destroy(LabOrder $order)
    {
        $this->ensureSameInstitute($order, 'order');

        if ($order->status !== 'ordered') {
            return redirect()->back()->with('error', 'Cannot delete orders that are being processed.');
        }

        $order->results()->delete();
        $order->delete();

        return redirect()->route('medical.lab.orders.index')
            ->with('status', 'Lab order deleted successfully!');
    }

    public function collect(LabOrder $order)
    {
        $this->ensureSameInstitute($order, 'order');

        if ($order->status !== 'ordered') {
            return redirect()->back()->with('error', 'This order cannot be collected.');
        }

        $this->labService->collectSample($order);

        return redirect()->route('medical.lab.orders.show', $order)
            ->with('status', 'Sample collected successfully!');
    }

    /**
     * Result entry form (GET is served by the same URI pattern through the
     * `medical.lab.orders.result` POST route's companion view link — the
     * show page links here via query; see routes/medical.php).
     */
    public function resultForm(LabOrder $order)
    {
        $this->ensureSameInstitute($order, 'order');

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

        if (! $order->readyForResults()) {
            return redirect()->back()->with('error', 'This order is not ready for results.');
        }

        $this->labService->enterResults($order, $request->validated()['results']);

        return redirect()->route('medical.lab.orders.show', $order)
            ->with('status', 'Results entered successfully!');
    }

    public function report(LabOrder $order)
    {
        $this->ensureSameInstitute($order, 'order');

        if ($order->status !== 'completed') {
            return redirect()->back()->with('error', 'Report is only available for completed orders.');
        }

        $pdf = $this->labService->generateReport($order);

        return $pdf->download('lab-report-'.$order->order_number.'.pdf');
    }

    /**
     * Doctors available for ordering (same documented limitation as
     * OPD/IPD: no institute↔doctor mapping exists yet).
     */
    private function doctors()
    {
        return User::where('status', 'active')->orderBy('name')->get();
    }
}
