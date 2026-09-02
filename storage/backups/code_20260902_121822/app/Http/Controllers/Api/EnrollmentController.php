<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Resources\EnrollmentResource;
use App\Models\Training\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnrollmentController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Enrollment::with(['student', 'batch']);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->input('batch_id'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $enrollments = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($enrollments);
    }
}
