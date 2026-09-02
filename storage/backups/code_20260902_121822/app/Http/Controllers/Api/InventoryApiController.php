<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Resources\InventoryItemResource;
use App\Models\InventoryItem;
use App\Models\InventoryMovement;
use App\Models\InventoryStockLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InventoryApiController extends Controller
{
    use ApiResponse, ResolvesInstitute;

    public function items(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $query = InventoryItem::with(['category'])
            ->where('institute_id', $institute->id);

        $branchId = $this->actingBranchId($request);
        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->input('status') === 'active');
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $items = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse(InventoryItemResource::collection($items));
    }

    public function stock(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $query = InventoryStockLevel::with(['item', 'warehouse', 'batch'])
            ->where('institute_id', $institute->id);

        $branchId = $this->actingBranchId($request);
        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->input('item_id'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $stock = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($stock);
    }

    public function movements(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $query = InventoryMovement::with(['item', 'warehouse', 'batch'])
            ->where('institute_id', $institute->id);

        $branchId = $this->actingBranchId($request);
        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        if ($request->filled('item_id')) {
            $query->where('item_id', $request->input('item_id'));
        }

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->input('warehouse_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $movements = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($movements);
    }
}
