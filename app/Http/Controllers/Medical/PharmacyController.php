<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\DispenseRequest;
use App\Models\Medical\PharmacyDispense;
use App\Models\Medical\Prescription;
use App\Models\Medical\PrescriptionItem;
use App\Services\Medical\ExpiryAlertService;
use App\Services\Medical\PharmacyStockService;
use App\Services\Medical\PrescriptionService;
use App\Support\MedicalScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PharmacyController extends MedicalController implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_pharmacy.view', only: [
                'index', 'expiryAlerts', 'dispenseQueue', 'dispenseShow',
            ]),
            new Middleware('permission:medical_pharmacy.dispense', only: [
                'dispense', 'batchDispense',
            ]),
        ];
    }

    protected PharmacyStockService $stockService;

    protected PrescriptionService $prescriptionService;

    protected ExpiryAlertService $expiryService;

    public function __construct(
        PharmacyStockService $stockService,
        PrescriptionService $prescriptionService,
        ExpiryAlertService $expiryService
    ) {
        $this->stockService = $stockService;
        $this->prescriptionService = $prescriptionService;
        $this->expiryService = $expiryService;
    }

    /**
     * Pharmacy dashboard.
     */
    public function index()
    {
        $instituteId = $this->instituteId();

        $pending = $this->prescriptionService->getPendingPrescriptions($instituteId);
        $lowStock = $this->stockService->getLowStockItems($instituteId);
        $expirySummary = $this->expiryService->getAlertSummary($instituteId);

        $todayDispenses = PharmacyDispense::where('institute_id', $instituteId)
            ->whereDate('dispense_date', today())
            ->count();

        $recentDispenses = PharmacyDispense::where('institute_id', $instituteId)
            ->with(['prescriptionItem.prescription.patient', 'stock.medicine'])
            ->orderBy('id', 'desc')
            ->limit(10)
            ->get();

        return view('medical.pharmacy.dashboard', compact(
            'pending',
            'lowStock',
            'expirySummary',
            'todayDispenses',
            'recentDispenses'
        ));
    }

    /**
     * Expiry alerts dashboard.
     */
    public function expiryAlerts()
    {
        $instituteId = $this->instituteId();
        $alerts = $this->expiryService->checkAndAlert($instituteId);
        $summary = $this->expiryService->getAlertSummary($instituteId);

        return view('medical.pharmacy.stock.expiry', compact('alerts', 'summary'));
    }

    /**
     * Dispensing queue (finalized prescriptions with pending items).
     */
    public function dispenseQueue()
    {
        $instituteId = $this->instituteId();
        $prescriptions = $this->prescriptionService->getPendingPrescriptions($instituteId);

        // Availability overlay per pending item.
        foreach ($prescriptions as $prescription) {
            foreach ($prescription->items as $item) {
                if ($item->status === 'pending' && $item->medicine_id) {
                    $item->available_stock = $this->stockService->getAvailableStock($instituteId, $item->medicine_id);
                    $item->is_available = $item->available_stock >= $item->quantity;
                }
            }
        }

        return view('medical.pharmacy.dispense.index', compact('prescriptions'));
    }

    /**
     * Dispense interface for one prescription item.
     *
     * NOTE: the route param is {prescription_item} (snake), so the id is
     * taken as a scalar and the record is loaded institute-scoped —
     * implicit model binding would not match a camelCase argument here.
     */
    public function dispenseShow(int $prescription_item)
    {
        $instituteId = $this->instituteId();

        $prescriptionItem = PrescriptionItem::whereHas('prescription', function ($q) use ($instituteId) {
            $q->where('institute_id', $instituteId);
        })->findOrFail($prescription_item);

        if ($prescriptionItem->status === 'dispensed') {
            return redirect()->back()->with('error', 'This item has already been dispensed.');
        }

        $prescriptionItem->load(['prescription.patient', 'prescription.doctor', 'medicine']);

        if (! $prescriptionItem->prescription->is_finalized) {
            return redirect()->back()->with('error', 'Only finalized prescriptions can be dispensed.');
        }

        // FEFO batches for the linked medicine (free-text items cannot be
        // dispensed from stock).
        $batches = [];
        $unmapped = ! $prescriptionItem->medicine_id;
        if (! $unmapped) {
            try {
                $batches = $this->stockService->getStockBatches(
                    $instituteId,
                    $prescriptionItem->medicine_id,
                    $prescriptionItem->quantity
                );
            } catch (\Throwable) {
                $batches = [];
            }
        }

        return view('medical.pharmacy.dispense.show', compact('prescriptionItem', 'batches', 'unmapped'));
    }

    /**
     * Dispense a prescription item (existing POST route).
     */
    public function dispense(DispenseRequest $request, int $prescription_item)
    {
        $instituteId = $this->instituteId();

        $item = PrescriptionItem::whereHas('prescription', function ($q) use ($instituteId) {
            $q->where('institute_id', $instituteId);
        })->findOrFail($prescription_item);

        if ($item->status === 'dispensed') {
            return redirect()->back()->with('error', 'This item has already been dispensed.');
        }

        $item->load('prescription');

        if (! $item->prescription->is_finalized) {
            return redirect()->back()->with('error', 'Only finalized prescriptions can be dispensed.');
        }

        if (! $item->medicine_id) {
            return redirect()->back()->with('error', 'Free-text items cannot be dispensed from stock.');
        }

        $data = $request->validated();

        // The batch must belong to this institute AND this item's medicine.
        $stock = \App\Models\Medical\PharmacyStock::where('institute_id', $instituteId)
            ->where('medicine_id', $item->medicine_id)
            ->find($data['stock_id']);

        if (! $stock) {
            return redirect()->back()->with('error', 'Selected batch does not match this medicine.');
        }

        // Single-dispense semantics: the full prescribed quantity at once.
        if ((int) $data['quantity_dispensed'] !== (int) $item->quantity) {
            return redirect()->back()->with(
                'error',
                "Dispense the full prescribed quantity ({$item->quantity}). Partial dispensing is not supported."
            );
        }

        try {
            $this->stockService->deductStock($instituteId, $stock->id, (int) $data['quantity_dispensed']);
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        PharmacyDispense::create([
            'institute_id' => $instituteId,
            'prescription_item_id' => $item->id,
            'stock_id' => $stock->id,
            'quantity_dispensed' => (int) $data['quantity_dispensed'],
            'dispensed_by' => MedicalScope::recorderId(),
            'dispense_date' => now()->format('Y-m-d'),
            'notes' => $data['notes'] ?? null,
        ]);

        $item->update(['status' => 'dispensed']);

        return redirect()->route('medical.pharmacy.dispense.index')
            ->with('status', 'Medicine dispensed successfully!');
    }

    /**
     * Batch-dispense every pending item of a prescription, splitting
     * across FEFO batches where one batch is short.
     */
    public function batchDispense(Request $request, Prescription $prescription)
    {
        $this->ensureSameInstitute($prescription, 'prescription');

        if (! $this->prescriptionService->canDispense($prescription)) {
            return redirect()->back()->with('error', 'No pending items to dispense.');
        }

        $instituteId = $prescription->institute_id;
        $pendingItems = $prescription->items()->where('status', 'pending')->get();
        $errors = [];
        $dispensed = 0;

        foreach ($pendingItems as $item) {
            if (! $item->medicine_id) {
                $errors[] = "Item '{$item->medicine_name}' is free-text and cannot be dispensed from stock.";
                continue;
            }

            try {
                $splits = $this->stockService->getStockBatches($instituteId, $item->medicine_id, $item->quantity);

                foreach ($splits as $split) {
                    $this->stockService->deductStock($instituteId, $split['stock_id'], $split['quantity']);

                    PharmacyDispense::create([
                        'institute_id' => $instituteId,
                        'prescription_item_id' => $item->id,
                        'stock_id' => $split['stock_id'],
                        'quantity_dispensed' => $split['quantity'],
                        'dispensed_by' => MedicalScope::recorderId(),
                        'dispense_date' => now()->format('Y-m-d'),
                        'notes' => $request->notes ?? 'Batch dispense',
                    ]);
                }

                $item->update(['status' => 'dispensed']);
                $dispensed++;
            } catch (\Throwable $e) {
                $errors[] = "Error dispensing '{$item->medicine_name}': ".$e->getMessage();
            }
        }

        $message = "{$dispensed} item(s) dispensed successfully.";
        if (! empty($errors)) {
            $message .= ' Errors: '.implode('; ', $errors);
        }

        return redirect()->route('medical.pharmacy.dispense.index')
            ->with('status', $message);
    }
}
