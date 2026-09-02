<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\InventoryWarehouse;
use App\Services\Inventory\InventoryItemService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InventoryWarehouseController extends Controller
{
    use ResolvesInstitute;

    public function __construct(private readonly InventoryItemService $items) {}

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $query = InventoryWarehouse::where('institute_id', $institute->id)
            ->when($branchId !== null, fn ($q) => $q->where(fn ($qq) => $qq->where('branch_id', $branchId)->orWhereNull('branch_id')));

        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function ($qq) use ($q) {
                $qq->where('name', 'like', "%{$q}%")
                    ->orWhere('code', 'like', "%{$q}%")
                    ->orWhere('location', 'like', "%{$q}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        $warehouses = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('institute.inventory.warehouses.index', [
            'institute' => $institute,
            'warehouses' => $warehouses,
        ]);
    }

    public function create(Request $request): View
    {
        $institute = $this->requireInstitute($request);

        return view('institute.inventory.warehouses.index', [
            'institute' => $institute,
            'createMode' => true,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $this->items->createWarehouse($institute->id, $branchId, $request->all(), $this->actorId($request));

        return redirect()->route('inventory.warehouses.index')->with('status', 'Warehouse created.');
    }

    public function show(Request $request, InventoryWarehouse $warehouse): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($warehouse->institute_id !== $institute->id, 404);

        $warehouse->load('stockLevels.item');

        return view('institute.inventory.warehouses.index', [
            'institute' => $institute,
            'warehouse' => $warehouse,
        ]);
    }

    public function edit(Request $request, InventoryWarehouse $warehouse): View
    {
        $institute = $this->requireInstitute($request);
        abort_if($warehouse->institute_id !== $institute->id, 404);

        return view('institute.inventory.warehouses.index', [
            'institute' => $institute,
            'editWarehouse' => $warehouse,
            'createMode' => true,
        ]);
    }

    public function update(Request $request, InventoryWarehouse $warehouse): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($warehouse->institute_id !== $institute->id, 404);

        $this->items->updateWarehouse($warehouse, $institute->id, $request->all(), $this->actorId($request));

        return redirect()->route('inventory.warehouses.index')->with('status', 'Warehouse updated.');
    }

    public function destroy(Request $request, InventoryWarehouse $warehouse): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if($warehouse->institute_id !== $institute->id, 404);

        $this->items->deleteWarehouse($warehouse, $institute->id);

        return redirect()->route('inventory.warehouses.index')->with('status', 'Warehouse deleted.');
    }
}
