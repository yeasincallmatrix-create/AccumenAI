<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Resources\CrmContactResource;
use App\Models\CrmContact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CrmContactController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = CrmContact::with(['contactType', 'organization']);

        if ($request->filled('search')) {
            $like = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($like) {
                $q->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            });
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('contact_type_id')) {
            $query->where('crm_contact_type_id', $request->input('contact_type_id'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $contacts = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($contacts);
    }

    public function show(int $id): JsonResponse
    {
        $contact = CrmContact::with(['contactType', 'organization', 'leads', 'activities', 'tasks'])->find($id);

        if (! $contact) {
            return $this->notFoundResponse('Contact not found.');
        }

        return $this->successResponse(new CrmContactResource($contact));
    }
}
