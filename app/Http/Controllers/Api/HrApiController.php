<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ApiResponse;
use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Resources\HrEmployeeResource;
use App\Models\HrAttendance;
use App\Models\HrEmployee;
use App\Models\HrPayroll;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HrApiController extends Controller
{
    use ApiResponse, ResolvesInstitute;

    public function employees(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $query = HrEmployee::with(['department', 'designation'])
            ->where('institute_id', $institute->id);

        $branchId = $this->actingBranchId($request);
        if ($branchId !== null) {
            $query->where('branch_id', $branchId);
        }

        if ($request->filled('status')) {
            $query->where('employment_status', $request->input('status'));
        }

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $employees = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse(HrEmployeeResource::collection($employees));
    }

    public function attendance(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $query = HrAttendance::with(['employee'])
            ->where('institute_id', $institute->id);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('date')) {
            $query->whereDate('attendance_date', $request->input('date'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $attendance = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($attendance);
    }

    public function payroll(Request $request): JsonResponse
    {
        $institute = $this->requireInstitute($request);

        $query = HrPayroll::with(['employee', 'period'])
            ->where('institute_id', $institute->id);

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = min((int) $request->input('per_page', 20), 100);
        $payroll = $query->orderByDesc('id')->paginate($perPage);

        return $this->paginatedResponse($payroll);
    }
}
