<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Resources\InvoiceResource;
use App\Models\Invoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    use ApiResponse, ResolvesInstitute;

    public function index(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $query = Invoice::with(['student', 'items'])
            ->where('institute_id', $institute->id);

        $branchId = $this->actingBranchId($request);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('type')) {
            $query->where('invoice_type', $request->input('type'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $invoices = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($invoices);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $invoice = Invoice::with(['student', 'items', 'installments.payments'])
            ->where('institute_id', $institute->id)
            ->find($id);

        if (! $invoice) {
            return $this->notFoundResponse('Invoice not found.');
        }

        return $this->successResponse(new InvoiceResource($invoice));
    }
}
