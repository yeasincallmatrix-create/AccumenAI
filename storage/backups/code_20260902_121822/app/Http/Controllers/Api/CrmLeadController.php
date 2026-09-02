<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Resources\CrmLeadResource;
use App\Models\CrmLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmLeadController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = CrmLead::with(['status', 'source', 'organization', 'contact']);

        if ($request->filled('search')) {
            $like = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($like) {
                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        if ($request->filled('status_id')) {
            $query->where('crm_lead_status_id', $request->input('status_id'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $leads = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($leads);
    }

    public function show(int $id): JsonResponse
    {
        $lead = CrmLead::with(['status', 'source', 'organization', 'contact', 'activities', 'tasks'])->find($id);

        if (! $lead) {
            return $this->notFoundResponse('Lead not found.');
        }

        return $this->successResponse(new CrmLeadResource($lead));
    }
}
