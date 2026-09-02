<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    use ApiResponse, ResolvesInstitute;

    public function index(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $query = Payment::with(['invoice', 'student'])
            ->where('institute_id', $institute->id);

        $branchId = $this->actingBranchId($request);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->input('invoice_id'));
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $payments = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($payments);
    }
}
