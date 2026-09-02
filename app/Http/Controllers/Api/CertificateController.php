<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CertificateController extends Controller
{
    use ApiResponse;

    public function index(Request $request): JsonResponse
    {
        $query = Certificate::with(['student', 'course', 'batch', 'type']);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $query->where('certificate_number', 'like', '%'.$request->input('search').'%');
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $certificates = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($certificates);
    }

    public function verify(string $number): JsonResponse
    {
        $certificate = Certificate::with(['student', 'course', 'batch', 'type'])
            ->where('certificate_number', $number)
            ->first();

        if (! $certificate) {
            return $this->notFoundResponse('Certificate not found.');
        }

        return $this->successResponse([
            'certificate_number' => $certificate->certificate_number,
            'student_name' => $certificate->student->full_name ?? null,
            'course_name' => $certificate->course->name ?? null,
            'batch_name' => $certificate->batch->name ?? null,
            'type' => $certificate->type->name ?? null,
            'status' => $certificate->status,
            'issue_date' => $certificate->issue_date?->toDateString(),
        ]);
    }
}
