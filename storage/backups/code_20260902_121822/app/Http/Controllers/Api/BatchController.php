<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Resources\BatchResource;
use App\Models\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BatchController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Batch::query();

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->input('course_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        if ($request->filled('search')) {
            $like = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like);
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $batches = $query->with(['course'])->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($batches);
    }

    public function show(int $id): JsonResponse
    {
        $batch = Batch::with(['course', 'academicYear', 'branch'])->find($id);

        if (! $batch) {
            return $this->notFoundResponse('Batch not found.');
        }

        return $this->successResponse(new BatchResource($batch));
    }
}
