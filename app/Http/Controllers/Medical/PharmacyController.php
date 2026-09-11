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

        // Phase 18.1: dashboard widgets are branch-filtered in SQL via the
        // service layer (no PHP post-filtering).
        $ctxBranch = $this->branchContextId();
        $pending = $this->prescriptionService->getPendingPrescriptions($instituteId, $ctxBranch);
        $lowStock = $this->stockService->getLowStockItems($instituteId, $ctxBranch);
        $expirySummary = $this->expiryService->getAlertSummary($instituteId, $ctxBranch);

        $dispenseQuery = PharmacyDispense::where('institute_id', $instituteId);
        // Phase 18: dispense history follows the branch fence.
        $this->scopeBranch($dispenseQuery);
        $todayDispenses = (clone $dispenseQuery)
            ->whereDate('dispense_date', today())
            ->count();

        $recentDispenses = $dispenseQuery
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
        $ctxBranch = $this->branchContextId();
        $alerts = $this->expiryService->checkAndAlert($instituteId, $ctxBranch);
        $summary = $this->expiryService->getAlertSummary($instituteId, $ctxBranch);

        return view('medical.pharmacy.stock.expiry', compact('alerts', 'summary'));
    }

    /**
     * Dispensing queue (finalized prescriptions with pending items).
     */
    public function dispenseQueue()
    {
        $instituteId = $this->instituteId();
        // Phase 18.1: queue + availability overlay branch-filtered in SQL.
        $ctxBranch = $this->branchContextId();
        $prescriptions = $this->prescriptionService->getPendingPrescriptions($instituteId, $ctxBranch);

        // Availability overlay per pending item.
        foreach ($prescriptions as $prescription) {
            foreach ($prescription->items as $item) {
                if ($item->status === 'pending' && $item->medicine_id) {
                    $item->available_stock = $this->stockService->getAvailableStock($instituteId, $item->medicine_id, $ctxBranch);
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
        // Phase 18: dispensing follows the prescription's branch fence.
        $this->ensureBranchAccess($prescriptionItem->prescription, 'branch_id', 'prescription');

        // FEFO batches for the linked medicine (free-text items cannot be
        // dispensed from stock).
        $batches = [];
        $unmapped = ! $prescriptionItem->medicine_id;
        if (! $unmapped) {
            try {
                // Phase 18.1: FEFO batches branch-filtered in SQL.
                $batches = $this->stockService->getStockBatches(
                    $instituteId,
                    $prescriptionItem->medicine_id,
                    $prescriptionItem->quantity,
                    $this->branchContextId()
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
        // Phase 18: dispensing follows the prescription's branch fence.
        $this->ensureBranchAccess($item->prescription, 'branch_id', 'prescription');

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
        // Phase 18: a branched prescription dispenses from its own branch
        // (or legacy batches); cross-branch dispensing is rejected.
        $rxBranch = $item->prescription->branch_id;
        if ($rxBranch !== null && $stock->branch_id !== null
            && (int) $stock->branch_id !== (int) $rxBranch) {
            return redirect()->back()->with('error', 'Selected batch belongs to another branch.');
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
            // Phase 18: dispense inherits the prescription's branch
            // (deterministic), else the batch branch, else context.
            'branch_id' => $item->prescription->branch_id
                ?? $stock->branch_id
                ?? $this->branchContextId(),
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
        // Phase 18: batch dispensing follows the prescription's branch fence.
        $this->ensureBranchAccess($prescription, 'branch_id', 'prescription');

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
                // Phase 18: branched prescriptions draw own-branch + legacy
                // batches only (FEFO allocation itself stays institute-wide;
                // per-branch reservation is deferred inventory work).
                if ($prescription->branch_id !== null) {
                    $batchBranches = \App\Models\Medical\PharmacyStock::where('institute_id', $instituteId)
                        ->whereIn('id', collect($splits)->pluck('stock_id')->all())
                        ->pluck('branch_id', 'id');
                    $splits = array_values(array_filter($splits, function ($split) use ($batchBranches, $prescription) {
                        $b = $batchBranches[$split['stock_id']] ?? null;

                        return $b === null || (int) $b === (int) $prescription->branch_id;
                    }));
                    if (array_sum(array_column($splits, 'quantity')) < $item->quantity) {
                        throw new \RuntimeException('Insufficient stock in this branch for full quantity.');
                    }
                }

                foreach ($splits as $split) {
                    $this->stockService->deductStock($instituteId, $split['stock_id'], $split['quantity']);

                    PharmacyDispense::create([
                        'institute_id' => $instituteId,
                        'branch_id' => $prescription->branch_id,
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
