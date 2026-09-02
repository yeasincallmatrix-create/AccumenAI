<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\InventoryCategory;
use App\Models\InventoryItem;
use App\Models\InventoryWarehouse;
use App\Services\Inventory\InventoryItemService;
use App\Services\Inventory\InventoryReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryItemController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly InventoryItemService $items,
        private readonly InventoryReportService $reports,
    ) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $query = $this->items->listItems($institute->id, $branchId, [
            'search' => $request->input('q'),
            'category_id' => $request->input('category_id'),
            'item_type' => $request->input('item_type'),
            'is_active' => $request->filled('status') ? ($request->input('status') === 'active') : null,
        ])->with('category')
          ->withSum('stockLevels as on_hand_qty', 'quantity');

        $items = $query->orderByDesc('id')->paginate(20)->withQueryString();

        $categories = InventoryCategory::where('institute_id', $institute->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('institute.inventory.items.index', [
            'institute' => $institute,
            'items' => $items,
            'categories' => $categories,
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        $categories = InventoryCategory::where('institute_id', $institute->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('institute.inventory.items.create', [
            'institute' => $institute,
            'categories' => $categories,
            'itemTypes' => InventoryItemService::ITEM_TYPES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $this->items->createItem($institute->id, $branchId, $request->all(), $this->actorId($request));

        return redirect()->route('inventory.items.index')->with('status', 'Inventory item created.');
    }

    public function show(Request $request, InventoryItem $item): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($item->institute_id !== $institute->id, 404);

        $item->load('category', 'stockLevels.warehouse');

        return view('institute.inventory.items.show', [
            'institute' => $institute,
            'item' => $item,
        ]);
    }

    public function edit(Request $request, InventoryItem $item): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($item->institute_id !== $institute->id, 404);

        $categories = InventoryCategory::where('institute_id', $institute->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('institute.inventory.items.create', [
            'institute' => $institute,
            'item' => $item,
            'categories' => $categories,
            'itemTypes' => InventoryItemService::ITEM_TYPES,
            'isEdit' => true,
        ]);
    }

    public function update(Request $request, InventoryItem $item): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($item->institute_id !== $institute->id, 404);

        $this->items->updateItem($item, $institute->id, $request->all(), $this->actorId($request));

        return redirect()->route('inventory.items.show', $item)->with('status', 'Inventory item updated.');
    }

    public function destroy(Request $request, InventoryItem $item): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($item->institute_id !== $institute->id, 404);

        $this->items->deleteItem($item, $institute->id);

        return redirect()->route('inventory.items.index')->with('status', 'Inventory item deleted.');
    }

    public function stockLedger(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $filters = array_filter([
            'warehouse_id' => $request->input('warehouse_id'),
            'item_id' => $request->input('item_id'),
            'movement_type' => $request->input('movement_type'),
            'from' => $request->input('from'),
            'to' => $request->input('to'),
        ], fn ($v) => $v !== null && $v !== '');

        $movements = $this->reports->movements($institute->id, $branchId, $filters)
            ->paginate(30)
            ->withQueryString();

        $warehouses = InventoryWarehouse::where('institute_id', $institute->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('institute.inventory.stock-ledger', [
            'institute' => $institute,
            'movements' => $movements,
            'warehouses' => $warehouses,
        ]);
    }

    public function lowStock(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $items = $this->reports->lowStock($institute->id, $branchId);

        $warehouses = InventoryWarehouse::where('institute_id', $institute->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('institute.inventory.low-stock', [
            'institute' => $institute,
            'items' => $items,
            'warehouses' => $warehouses,
        ]);
    }

    public function batchTracker(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $batches = $this->reports->batches(
            $institute->id,
            $branchId,
            $request->input('expiry_status'),
            $request->filled('warehouse_id') ? (int) $request->input('warehouse_id') : null,
        );

        $warehouses = InventoryWarehouse::where('institute_id', $institute->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('institute.inventory.batches', [
            'institute' => $institute,
            'batches' => $batches,
            'warehouses' => $warehouses,
        ]);
    }

    public function barcodeSearch(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $items = collect();

        if ($request->filled('barcode')) {
            $barcode = $request->input('barcode');
            $items = InventoryItem::where('institute_id', $institute->id)
                ->where(fn ($q) => $q->where('branch_id', $branchId)->orWhereNull('branch_id'))
                ->where('barcode', $barcode)
                ->with('stockLevels.warehouse', 'category')
                ->get();
        }

        return view('institute.inventory.barcode-search', [
            'institute' => $institute,
            'items' => $items,
        ]);
    }
}
