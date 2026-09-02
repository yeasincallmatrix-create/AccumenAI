<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\InventoryAdjustment;
use App\Models\InventoryItem;
use App\Models\InventoryWarehouse;
use App\Services\Inventory\InventoryStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryAdjustmentController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly InventoryStockService $stock) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $query = InventoryAdjustment::where('institute_id', $institute->id)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->with('warehouse');

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($qq) use ($q) {
                $qq->where('adjustment_no', 'like', "%{$q}%")
                    ->orWhere('reason', 'like', "%{$q}%")
                    ->orWhereHas('warehouse', fn ($c) => $c->where('name', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('adjustment_type')) {
            $query->where('adjustment_type', $request->input('adjustment_type'));
        }

        $adjustments = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('institute.inventory.adjustments.index', [
            'institute' => $institute,
            'adjustments' => $adjustments,
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $warehouses = InventoryWarehouse::where('institute_id', $institute->id)
            ->where('is_active', true)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->orderBy('name')
            ->get();

        $items = InventoryItem::where('institute_id', $institute->id)
            ->where('is_active', true)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->orderBy('name')
            ->get();

        return view('institute.inventory.adjustments.index', [
            'institute' => $institute,
            'createMode' => true,
            'warehouses' => $warehouses,
            'items' => $items,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $data = $request->validate([
            'warehouse_id' => 'required|integer|exists:inventory_warehouses,id',
            'adjustment_type' => 'required|string|in:adjustment,wastage',
            'reason' => 'required|string|max:500',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|integer|exists:inventory_items,id',
            'lines.*.system_qty' => 'required|numeric|min:0',
            'lines.*.counted_qty' => 'required|numeric|min:0',
        ]);

        $this->stock->postAdjustment(
            $institute->id,
            $branchId,
            $data['warehouse_id'],
            $data['adjustment_type'],
            $data['reason'],
            $data['lines'],
            $this->actorId($request),
        );

        return redirect()->route('inventory.adjustments.index')->with('status', 'Stock adjustment posted.');
    }

    public function show(Request $request, InventoryAdjustment $adjustment): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($adjustment->institute_id !== $institute->id, 404);

        $adjustment->load('items.item', 'warehouse', 'journal');

        return view('institute.inventory.adjustments.show', [
            'institute' => $institute,
            'adjustment' => $adjustment,
        ]);
    }
}
