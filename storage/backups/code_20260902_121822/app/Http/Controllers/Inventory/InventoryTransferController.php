<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\InventoryTransfer;
use App\Models\InventoryWarehouse;
use App\Services\Inventory\InventoryStockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryTransferController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly InventoryStockService $stock) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $query = InventoryTransfer::where('institute_id', $institute->id)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')))
            ->with('sourceWarehouse', 'destinationWarehouse');

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($qq) use ($q) {
                $qq->where('transfer_no', 'like', "%{$q}%")
                    ->orWhereHas('sourceWarehouse', fn ($c) => $c->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('destinationWarehouse', fn ($c) => $c->where('name', 'like', "%{$q}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $transfers = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('institute.inventory.transfers.index', [
            'institute' => $institute,
            'transfers' => $transfers,
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

        return view('institute.inventory.transfers.index', [
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
            'source_warehouse_id' => 'required|integer|exists:inventory_warehouses,id',
            'destination_warehouse_id' => 'required|integer|exists:inventory_warehouses,id',
            'notes' => 'nullable|string|max:2000',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|integer|exists:inventory_items,id',
            'lines.*.quantity' => 'required|numeric|min:0.0001',
        ]);

        $this->stock->transfer(
            $institute->id,
            $branchId,
            $data['source_warehouse_id'],
            $data['destination_warehouse_id'],
            $data['lines'],
            $this->actorId($request),
            ['notes' => $data['notes'] ?? null],
        );

        return redirect()->route('inventory.transfers.index')->with('status', 'Stock transfer completed.');
    }

    public function show(Request $request, InventoryTransfer $transfer): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($transfer->institute_id !== $institute->id, 404);

        $transfer->load('items.item', 'sourceWarehouse', 'destinationWarehouse');

        return view('institute.inventory.transfers.show', [
            'institute' => $institute,
            'transfer' => $transfer,
        ]);
    }
}
