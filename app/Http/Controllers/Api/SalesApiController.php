<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Resources\SalesOrderResource;
use App\Http\Resources\SalesQuotationResource;
use App\Models\Invoice;
use App\Models\SalesDelivery;
use App\Models\SalesOrder;
use App\Models\SalesQuotation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesApiController extends Controller
{
    use ApiResponse, ResolvesInstitute;

    public function quotations(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $query = SalesQuotation::with(['customer', 'currency', 'lines'])
            ->where('institute_id', $institute->id);

        $branchId = $this->actingBranchId($request);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $quotations = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($quotations);
    }

    public function orders(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $query = SalesOrder::with(['customer', 'currency', 'lines'])
            ->where('institute_id', $institute->id);

        $branchId = $this->actingBranchId($request);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $orders = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($orders);
    }

    public function deliveries(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $query = SalesDelivery::with(['customer', 'order', 'warehouse'])
            ->where('institute_id', $institute->id);

        $branchId = $this->actingBranchId($request);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $deliveries = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($deliveries);
    }

    public function invoices(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $query = Invoice::with(['party'])
            ->where('institute_id', $institute->id);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $invoices = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($invoices);
    }
}
