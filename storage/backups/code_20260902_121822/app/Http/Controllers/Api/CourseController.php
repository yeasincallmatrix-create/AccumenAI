<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Course::query()->where('institute_id', request()->user()->institute_id);

        if ($request->filled('search')) {
            $like = '%'.$request->input('search').'%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhere('code', 'like', $like);
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $courses = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($courses);
    }

    public function show(int $id): JsonResponse
    {
        $course = Course::with(['batches', 'subjects'])
            ->where('institute_id', request()->user()->institute_id)
            ->find($id);

        if (! $course) {
            return $this->notFoundResponse('Course not found.');
        }

        return $this->successResponse(new CourseResource($course));
    }
}
