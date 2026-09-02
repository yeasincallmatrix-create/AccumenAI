<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    use ApiResponse;
    use ResolvesInstitute;

    public function index(Request $request): JsonResponse
    {
        $query = Attendance::with(['student', 'batch']);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->input('student_id'));
        }

        if ($request->filled('batch_id')) {
            $query->where('batch_id', $request->input('batch_id'));
        }

        if ($request->filled('date')) {
            $query->whereDate('class_date', $request->input('date'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $records = $query->orderByDesc('class_date')->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($records);
    }

    public function store(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);
        $branchId = $this->actingBranchId($request);

        $request->validate([
            'student_id' => [
                'required',
                'integer',
                Rule::exists('students', 'id')->where(function ($q) use ($institute, $branchId) {
                    $q->where('institute_id', $institute->id)->whereNull('deleted_at');
                    if ($branchId !== null) {
                        $q->where(function ($qq) use ($branchId) {
                            $qq->whereNull('branch_id')->orWhere('branch_id', $branchId);
                        });
                    }
                }),
            ],
            'batch_id' => [
                'required',
                'integer',
                Rule::exists('batches', 'id')->where(function ($q) use ($institute, $branchId) {
                    $q->where('institute_id', $institute->id);
                    if ($branchId !== null) {
                        $q->where(function ($qq) use ($branchId) {
                            $qq->whereNull('branch_id')->orWhere('branch_id', $branchId);
                        });
                    }
                }),
            ],
            'date' => 'required|date',
            'status' => 'required|in:present,absent,late,leave',
        ]);

        $existing = Attendance::where('student_id', $request->student_id)
            ->where('batch_id', $request->batch_id)
            ->whereDate('class_date', $request->date)
            ->first();

        if ($existing) {
            $existing->update([
                'status' => $request->status,
                'marked_by' => $request->user()->id,
            ]);

            return $this->successResponse(new AttendanceResource($existing->fresh(['student', 'batch'])), 'Attendance updated.');
        }

        $attendance = Attendance::create([
            'institute_id' => $institute->id,
            'student_id' => $request->student_id,
            'batch_id' => $request->batch_id,
            'class_date' => $request->date,
            'status' => $request->status,
            'marked_by' => $request->user()->id,
        ]);

        return $this->successResponse(new AttendanceResource($attendance->load(['student', 'batch'])), 'Attendance recorded.', 201);
    }
}
