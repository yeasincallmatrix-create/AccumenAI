<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Resources\ExamResultResource;
use App\Models\AcademicAssessment;
use App\Models\Exam;
use App\Models\ExamResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssessmentController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Exam::query();

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->input('batch_id'));
        }

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->input('course_id'));
        }

        if ($request->filled('search')) {
            $like = '%'.$request->input('search').'%';
            $query->whereHas('course', function ($q) use ($like) {
                $q->where('title', 'like', $like);
            });
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $exams = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($exams);
    }

    public function results(Request $request, int $id): JsonResponse
    {
        $exam = Exam::find($id);

        if (! $exam) {
            return $this->notFoundResponse('Assessment not found.');
        }

        $query = ExamResult::with(['student'])
            ->where('exam_id', $id);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $results = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($results);
    }
}
