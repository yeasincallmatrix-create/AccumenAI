<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Resources\StudentResource;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Student::query();

        if ($request->filled('search')) {
            $query->search($request->input('search'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->input('branch_id'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $students = $query->with([])
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->paginatedResponse($students);
    }

    public function show(int $id): JsonResponse
    {
        $student = Student::with(['enrollments.course', 'enrollments.batch', 'branch'])->find($id);

        if (! $student) {
            return $this->notFoundResponse('Student not found.');
        }

        return $this->successResponse(new StudentResource($student));
    }
}
