<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Models\PurchaseOrder;
use App\Services\Purchase\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PurchaseOrderController extends Controller
{
    use ApiResponse, ResolvesInstitute;

    public function __construct(
        private readonly PurchaseOrderService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $query = PurchaseOrder::with(['lines', 'supplier', 'warehouse'])
            ->where('institute_id', $institute->id);

        $branchId = $this->actingBranchId($request);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $orders = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($orders);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $order = PurchaseOrder::with(['lines.inventoryItem', 'supplier', 'warehouse', 'goodsReceipts'])
            ->where('institute_id', $institute->id)
            ->find($id);

        if (! $order) {
            return $this->notFoundResponse('Purchase order not found.');
        }

        return $this->successResponse($order);
    }

    public function store(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);
        $actorId = $this->actorId($request);
        $branchId = $this->actingBranchId($request);

        try {
            $validated = $request->validate([
                'supplier_id' => 'required|integer',
                'warehouse_id' => 'nullable|integer',
                'order_date' => 'nullable|date',
                'expected_delivery_date' => 'nullable|date',
                'reference_number' => 'nullable|string|max:80',
                'notes' => 'nullable|string|max:1000',
                'currency_id' => 'nullable|integer',
                'items' => 'required|array|min:1',
                'items.*.inventory_item_id' => 'nullable|integer',
                'items.*.description' => 'nullable|string|max:500',
                'items.*.quantity' => 'required|numeric|min:0.0001',
                'items.*.unit_price' => 'required|numeric|min:0',
                'items.*.unit' => 'nullable|string|max:30',
                'items.*.discount_amount' => 'nullable|numeric|min:0',
                'items.*.discount_rate' => 'nullable|numeric|min:0',
                'items.*.discount_type' => 'nullable|string|in:fixed,percentage',
                'items.*.tax_group_id' => 'nullable|integer',
                'items.*.tax_rate' => 'nullable|numeric|min:0',
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        }

        try {
            $order = $this->service->create($validated, $institute->id, $branchId, $actorId);

            return $this->successResponse($order, 'Purchase order created.', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to create purchase order: ' . $e->getMessage());
        }
    }

    public function submit(Request $request, int $id): JsonResponse
    {
        $institute = $this->requireInstitute($request);
        $actorId = $this->actorId($request);

        $order = PurchaseOrder::where('institute_id', $institute->id)->find($id);
        if (! $order) {
            return $this->notFoundResponse('Purchase order not found.');
        }

        try {
            $order = $this->service->submit($order, $actorId);

            return $this->successResponse($order, 'Purchase order submitted.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to submit purchase order: ' . $e->getMessage());
        }
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        $institute = $this->requireInstitute($request);
        $actorId = $this->actorId($request);

        $order = PurchaseOrder::where('institute_id', $institute->id)->find($id);
        if (! $order) {
            return $this->notFoundResponse('Purchase order not found.');
        }

        try {
            $order = $this->service->approve($order, $actorId);

            return $this->successResponse($order, 'Purchase order approved.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to approve purchase order: ' . $e->getMessage());
        }
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $institute = $this->requireInstitute($request);
        $actorId = $this->actorId($request);

        $order = PurchaseOrder::where('institute_id', $institute->id)->find($id);
        if (! $order) {
            return $this->notFoundResponse('Purchase order not found.');
        }

        try {
            $order = $this->service->cancel($order, $actorId);

            return $this->successResponse($order, 'Purchase order cancelled.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to cancel purchase order: ' . $e->getMessage());
        }
    }
}
