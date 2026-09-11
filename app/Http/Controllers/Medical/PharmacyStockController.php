<?php

namespace App\Http\Controllers\Medical;

use App\Http\Requests\Medical\PharmacyStockRequest;
use App\Http\Requests\Medical\StockAdjustmentRequest;
use App\Models\Medical\Medicine;
use App\Models\Medical\PharmacyStock;
use App\Services\Medical\ExpiryAlertService;
use App\Services\Medical\PharmacyStockService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PharmacyStockController extends MedicalController implements HasMiddleware
{
    /**
     * NOTE: the resource param is {stock} (singular of `pharmacy/stock`),
     * so bound arguments must be named `$stock` for implicit binding.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:medical_pharmacy.view', only: ['index', 'show', 'expiry']),
            new Middleware('permission:medical_pharmacy.create', only: ['create', 'store']),
            new Middleware('permission:medical_pharmacy.edit', only: ['edit', 'update', 'adjust']),
            new Middleware('permission:medical_pharmacy.delete', only: ['destroy']),
        ];
    }

    protected PharmacyStockService $stockService;

    protected ExpiryAlertService $expiryService;

    public function __construct(
        PharmacyStockService $stockService,
        ExpiryAlertService $expiryService
    ) {
        $this->stockService = $stockService;
        $this->expiryService = $expiryService;
    }

    /**
     * List all stock.
     */
    public function index(Request $request)
    {
        $instituteId = $this->instituteId();
        $query = PharmacyStock::where('institute_id', $instituteId)->with('medicine');
        // Phase 18: branch fence (context branch + legacy NULLs).
        $this->scopeBranch($query);

        if ($request->filled('medicine_id')) {
            $query->where('medicine_id', $request->medicine_id);
        }

        if ($request->filled('status')) {
            if ($request->status === 'available') {
                $query->where('current_quantity', '>', 0)
                    ->where('expiry_date', '>', now());
            } elseif ($request->status === 'expired') {
                $query->where('expiry_date', '<=', now());
            } elseif ($request->status === 'empty') {
                $query->where('current_quantity', '<=', 0);
            }
        }

        $stock = $query->orderBy('expiry_date')->paginate(50)->withQueryString();
        $medicines = Medicine::where('institute_id', $instituteId)
            ->where('is_active', true)
            ->orderBy('generic_name')
            ->get();

        return view('medical.pharmacy.stock.index', compact('stock', 'medicines'));
    }

    /**
     * Show stock entry form.
     */
    public function create(Request $request)
    {
        $medicines = Medicine::where('institute_id', $this->instituteId())
            ->where('is_active', true)
            ->orderBy('generic_name')
            ->get();

        $selectedMedicine = null;
        if ($request->filled('medicine_id')) {
            $selectedMedicine = Medicine::where('institute_id', $this->instituteId())
                ->find($request->medicine_id);
        }

        return view('medical.pharmacy.stock.create', compact('medicines', 'selectedMedicine'));
    }

    /**
     * Store new stock.
     */
    public function store(PharmacyStockRequest $request)
    {
        // Phase 18.1: branch ownership resolved BEFORE the service try/catch
        // so a foreign-branch abort (403) is never masked as a 302 error.
        $branchId = $this->resolveBranchId($request->input('branch_id'));
        try {
            $this->stockService->addStock(array_merge($request->validated(), [
                'branch_id' => $branchId,
            ]));

            return redirect()->route('medical.pharmacy.stock.index')
                ->with('status', 'Stock added successfully!');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage())->withInput();
        }
    }

    /**
     * Show stock details.
     */
    public function show(PharmacyStock $stock)
    {
        $this->ensureSameInstitute($stock, 'stock');
        $this->ensureBranchAccess($stock, 'branch_id', 'stock');
        $stock->load('medicine');

        return view('medical.pharmacy.stock.show', compact('stock'));
    }

    /**
     * Show stock edit form.
     */
    public function edit(PharmacyStock $stock)
    {
        $this->ensureSameInstitute($stock, 'stock');
        $this->ensureBranchAccess($stock, 'branch_id', 'stock');
        $medicines = Medicine::where('institute_id', $stock->institute_id)
            ->where('is_active', true)
            ->orderBy('generic_name')
            ->get();

        return view('medical.pharmacy.stock.edit', compact('stock', 'medicines'));
    }

    /**
     * Update stock.
     */
    public function update(PharmacyStockRequest $request, PharmacyStock $stock)
    {
        $this->ensureSameInstitute($stock, 'stock');
        $this->ensureBranchAccess($stock, 'branch_id', 'stock');
        $data = $request->validated();
        // Phase 18: branch identity never moves between records.
        unset($data['branch_id']);
        $stock->update($data);

        return redirect()->route('medical.pharmacy.stock.show', $stock)
            ->with('status', 'Stock updated successfully!');
    }

    /**
     * Delete stock (empty batches only).
     */
    public function destroy(PharmacyStock $stock)
    {
        $this->ensureSameInstitute($stock, 'stock');
        $this->ensureBranchAccess($stock, 'branch_id', 'stock');

        if ($stock->current_quantity > 0) {
            return redirect()->back()->with('error', 'Cannot delete stock with remaining quantity.');
        }

        // Phase 03: a batch referenced by dispense rows carries dispensing
        // history (pharmacy_dispenses.stock_id cascades). Fully-dispensed
        // batches stay as history; only untouched empty batches may go.
        if (\App\Models\Medical\PharmacyDispense::where('stock_id', $stock->id)->exists()) {
            return redirect()->back()->with('error', 'Cannot delete a batch with dispensing history.');
        }

        $stock->delete();

        return redirect()->route('medical.pharmacy.stock.index')
            ->with('status', 'Stock deleted successfully!');
    }

    /**
     * Show expiry alerts dashboard.
     */
    public function expiry()
    {
        $instituteId = $this->instituteId();
        // Phase 18.1: expiry surveillance branch-filtered in SQL.
        $ctxBranch = $this->branchContextId();
        $alerts = $this->expiryService->checkAndAlert($instituteId, $ctxBranch);
        $summary = $this->expiryService->getAlertSummary($instituteId, $ctxBranch);

        return view('medical.pharmacy.stock.expiry', compact('alerts', 'summary'));
    }

    /**
     * Adjust stock (physical count).
     */
    public function adjust(StockAdjustmentRequest $request, PharmacyStock $stock)
    {
        $this->ensureSameInstitute($stock, 'stock');
        $this->ensureBranchAccess($stock, 'branch_id', 'stock');

        try {
            $this->stockService->adjustStock(
                $stock->institute_id,
                $stock->id,
                (int) $request->new_quantity,
                (string) $request->reason
            );

            return redirect()->route('medical.pharmacy.stock.show', $stock)
                ->with('status', 'Stock adjusted successfully!');
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
