<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Services\Purchase\SupplierService;
use App\Services\HrAuditService;
use App\Support\BranchContext;
use App\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class SuppliersController extends Controller
{
    protected SupplierService $supplierService;

    public function __construct(SupplierService $supplierService)
    {
        $this->supplierService = $supplierService;
    }

    /**
     * Display a listing of suppliers with filters and pagination.
     */
    public function index(): \Illuminate\Http\JsonResponse
    {
        // Enforce tenant context - if not set, return 404/403 per conventions
        if (! TenantContext::enabled()) {
            return response()->json([
                'error' => 'Tenant context required',
            ], 403);
        }

        $data = $this->supplierService->index();

        return response()->json($data);
    }

    /**
     * Store a new supplier.
     */
    public function store(Request $request): \Illuminate\Http\JsonResponse
    {
        // Enforce tenant context
        if (! TenantContext::enabled()) {
            return response()->json([
                'error' => 'Tenant context required',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:30', Rule::unique('parties', 'phone')->where(fn ($q) => $q->where('institute_id', TenantContext::id() ?? 0)->where('type', 'supplier'))],
            'email' => ['nullable', 'string', 'max:150', 'email'],
            'address' => ['nullable', 'string'],
            'tin' => ['nullable', 'string', 'max:50'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
        ]);

        $actorId = auth()->id() ?? null;
        $partyId = $this->supplierService->create($validated, $actorId);

        return response()->json([
            'id' => $partyId,
            'message' => 'Supplier created successfully',
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(int $id): \Illuminate\Http\JsonResponse
    {
        // Enforce tenant context
        if (! TenantContext::enabled()) {
            return response()->json([
                'error' => 'Tenant context required',
            ], 403);
        }

        $actorId = auth()->id() ?? null;
        $party = $this->supplierService->get($id, $actorId);

        if (! $party) {
            return response()->json([
                'error' => 'Supplier not found',
            ], 404);
        }

        return response()->json($party);
    }

    /**
     * Update the specified resource.
     */
    public function update(Request $request, int $id): \Illuminate\Http\JsonResponse
    {
        // Enforce tenant context
        if (! TenantContext::enabled()) {
            return response()->json([
                'error' => 'Tenant context required',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'phone' => ['sometimes', 'required', 'string', 'max:30',
                Rule::unique('parties', 'phone')->ignore($id)->where(fn ($q) => $q->where('institute_id', TenantContext::id() ?? 0)->whereIn('type', ['supplier', 'both']))],
            'email' => ['nullable', 'string', 'max:150', 'email'],
            'address' => ['nullable', 'string'],
            'tin' => ['nullable', 'string', 'max:50'],
        ]);

        $actorId = auth()->id() ?? null;
        $success = $this->supplierService->update($id, $validated, $actorId);

        if (! $success) {
            return response()->json([
                'error' => 'Supplier not found or update failed',
            ], 404);
        }

        return response()->json([
            'message' => 'Supplier updated successfully',
        ]);
    }

    /**
     * Soft delete the specified resource.
     */
    public function destroy(int $id): \Illuminate\Http\JsonResponse
    {
        // Enforce tenant context
        if (! TenantContext::enabled()) {
            return response()->json([
                'error' => 'Tenant context required',
            ], 403);
        }

        $actorId = auth()->id() ?? null;
        $success = $this->supplierService->softDelete($id, $actorId);

        if (! $success) {
            return response()->json([
                'error' => 'Supplier not found',
            ], 404);
        }

        return response()->json([
            'message' => 'Supplier soft deleted successfully',
        ]);
    }

    /**
     * Restore a soft-deleted supplier.
     */
    public function restore(int $id): \Illuminate\Http\JsonResponse
    {
        // Enforce tenant context
        if (! TenantContext::enabled()) {
            return response()->json([
                'error' => 'Tenant context required',
            ], 403);
        }

        $actorId = auth()->id() ?? null;
        $success = $this->supplierService->restore($id, $actorId);

        if (! $success) {
            return response()->json([
                'error' => 'Supplier not found or not deleted',
            ], 404);
        }

        return response()->json([
            'message' => 'Supplier restored successfully',
        ]);
    }
}