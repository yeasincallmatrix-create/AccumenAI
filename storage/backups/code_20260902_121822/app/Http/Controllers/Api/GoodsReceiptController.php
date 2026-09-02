<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Resources\GoodsReceiptResource;
use App\Models\GoodsReceipt;
use App\Services\Purchase\GoodsReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GoodsReceiptController extends Controller
{
    use ApiResponse, ResolvesInstitute;

    public function __construct(
        private readonly GoodsReceiptService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $query = GoodsReceipt::with(['items', 'purchaseOrder', 'supplier', 'warehouse'])
            ->where('institute_id', $institute->id);

        $branchId = $this->actingBranchId($request);
        if ($branchId !== null) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('purchase_order_id')) {
            $query->where('purchase_order_id', $request->input('purchase_order_id'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->input('supplier_id'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $receipts = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($receipts);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $receipt = GoodsReceipt::with(['items.purchaseOrderLine', 'purchaseOrder', 'supplier', 'warehouse'])
            ->where('institute_id', $institute->id)
            ->find($id);

        if (! $receipt) {
            return $this->notFoundResponse('Goods receipt not found.');
        }

        $branchId = $this->actingBranchId($request);
        if ($branchId !== null && $receipt->branch_id !== null && (int) $receipt->branch_id !== (int) $branchId) {
            return $this->notFoundResponse('Goods receipt not found.');
        }

        return $this->successResponse(new GoodsReceiptResource($receipt));
    }

    public function store(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);
        $actorId = $this->actorId($request);
        $branchId = $this->actingBranchId($request);

        try {
            $validated = $request->validate([
                'purchase_order_id' => 'required|integer',
                'supplier_id' => 'nullable|integer',
                'warehouse_id' => 'nullable|integer',
                'receipt_date' => 'nullable|date',
                'notes' => 'nullable|string|max:1000',
                'lines' => 'required|array|min:1',
                'lines.*.purchase_order_line_id' => 'required|integer',
                'lines.*.inventory_item_id' => 'nullable|integer',
                'lines.*.received_quantity' => 'required|numeric|min:0.0001',
                'lines.*.rejected_quantity' => 'nullable|numeric|min:0',
                'lines.*.unit_cost' => 'nullable|numeric|min:0',
                'lines.*.notes' => 'nullable|string|max:500',
                'lines.*.batch_number' => 'nullable|string|max:80',
                'lines.*.lot_number' => 'nullable|string|max:80',
                'lines.*.expiry_date' => 'nullable|date|after:today',
                'lines.*.manufacture_date' => 'nullable|date|before_or_equal:today',
                'lines.*.serial_numbers' => 'nullable|array',
                'lines.*.serial_numbers.*' => 'string|max:100',
                'lines.*.serial_number' => 'nullable|string|max:100',
                'lines.*.received_condition' => 'nullable|string|in:good,damaged,expired,quarantine',
            ]);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        }

        try {
            $receipt = $this->service->create($validated, $institute->id, $branchId, $actorId);

            return $this->successResponse(new GoodsReceiptResource($receipt->load(['items', 'purchaseOrder', 'supplier', 'warehouse'])), 'Goods receipt created.', 201);
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to create goods receipt: ' . $e->getMessage());
        }
    }

    public function confirm(Request $request, int $id): JsonResponse
    {
        $institute = $this->requireInstitute($request);
        $actorId = $this->actorId($request);

        $receipt = GoodsReceipt::where('institute_id', $institute->id)->find($id);

        if (! $receipt) {
            return $this->notFoundResponse('Goods receipt not found.');
        }

        $branchId = $this->actingBranchId($request);
        if ($branchId !== null && $receipt->branch_id !== null && (int) $receipt->branch_id !== (int) $branchId) {
            return $this->notFoundResponse('Goods receipt not found.');
        }

        try {
            $receipt = $this->service->confirm($receipt, $actorId);

            return $this->successResponse(new GoodsReceiptResource($receipt->load(['items', 'purchaseOrder', 'supplier', 'warehouse'])), 'Goods receipt confirmed. Stock updated.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to confirm goods receipt: ' . $e->getMessage());
        }
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $institute = $this->requireInstitute($request);
        $actorId = $this->actorId($request);

        $receipt = GoodsReceipt::where('institute_id', $institute->id)->find($id);

        if (! $receipt) {
            return $this->notFoundResponse('Goods receipt not found.');
        }

        $branchId = $this->actingBranchId($request);
        if ($branchId !== null && $receipt->branch_id !== null && (int) $receipt->branch_id !== (int) $branchId) {
            return $this->notFoundResponse('Goods receipt not found.');
        }

        $reason = $request->input('cancellation_reason');

        try {
            $receipt = $this->service->cancel($receipt, $actorId, $reason);

            return $this->successResponse(new GoodsReceiptResource($receipt->load(['items'])), 'Goods receipt cancelled.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to cancel goods receipt: ' . $e->getMessage());
        }
    }

    public function reverse(Request $request, int $id): JsonResponse
    {
        $institute = $this->requireInstitute($request);
        $actorId = $this->actorId($request);

        $receipt = GoodsReceipt::where('institute_id', $institute->id)->find($id);

        if (! $receipt) {
            return $this->notFoundResponse('Goods receipt not found.');
        }

        $branchId = $this->actingBranchId($request);
        if ($branchId !== null && $receipt->branch_id !== null && (int) $receipt->branch_id !== (int) $branchId) {
            return $this->notFoundResponse('Goods receipt not found.');
        }

        $reason = $request->input('reversal_reason', 'Reversal requested');

        try {
            $receipt = $this->service->reverse($receipt, $actorId, $reason);

            return $this->successResponse(new GoodsReceiptResource($receipt->load(['items'])), 'Goods receipt reversed. Stock reversed.');
        } catch (ValidationException $e) {
            return $this->validationErrorResponse($e);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to reverse goods receipt: ' . $e->getMessage());
        }
    }
}
