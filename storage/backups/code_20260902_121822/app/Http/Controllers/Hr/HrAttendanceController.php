<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Concerns\ResolvesInstitute;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\HrAttendance;
use App\Models\HrAttendanceCorrection;
use App\Models\HrDepartment;
use App\Models\HrEmployee;
use App\Models\HrHoliday;
use App\Models\HrWorkShift;
use App\Services\HrAttendanceService;
use App\Services\HrAuditService;
use App\Services\HrShiftService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HrAttendanceController extends Controller
{
    use ResolvesInstitute;

    public function __construct(
        private readonly HrAttendanceService $attendanceService,
        private readonly HrShiftService $shiftService,
    ) {}

    public function dashboard(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $date = $request->query('date', now()->toDateString());
        $branchId = $this->actingBranchId($request) ?? ($request->query('branch_id') ? (int) $request->query('branch_id') : null);

        $query = HrAttendance::query()->where('attendance_date', $date);
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $total = (clone $query)->count();
        $present = (clone $query)->where('status', 'present')->count();
        $absent = (clone $query)->where('status', 'absent')->count();
        $late = (clone $query)->where('status', 'late')->count();
        $leave = (clone $query)->where('status', 'leave')->count();

        // Recent corrections pending
        $pendingCorrections = HrAttendanceCorrection::query()->where('institute_id', $institute->id)->where('status', 'pending')->count();

        return view('hr.attendance.dashboard', [
            'institute' => $institute,
            'date' => $date,
            'branches' => $this->branchOptions($institute->id),
            'summary' => compact('total', 'present', 'absent', 'late', 'leave', 'pendingCorrections'),
            'filters' => $request->query(),
        ]);
    }

    public function index(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $date = $request->query('date', now()->toDateString());
        $query = HrAttendance::query()->with(['employee.department', 'employee.designation', 'branch'])->where('attendance_date', $date);

        if ($this->actingBranchId($request)) {
            $query->where('branch_id', $this->actingBranchId($request));
        } elseif (filled($request->query('branch_id'))) {
            $query->where('branch_id', (int) $request->query('branch_id'));
        }
        if (filled($request->query('department_id'))) {
            $dept = (int) $request->query('department_id');
            $query->whereHas('employee', fn ($q) => $q->where('department_id', $dept));
        }
        if (filled($request->query('status'))) {
            $query->where('status', $request->query('status'));
        }
        if (filled($request->query('q'))) {
            $q = trim($request->query('q'));
            $query->whereHas('employee', fn ($qq) => $qq->where('display_name', 'like', "%{$q}%")->orWhere('employee_code', 'like', "%{$q}%"));
        }

        $attendances = $query->orderBy('created_at')->paginate(30)->withQueryString();
        $departments = HrDepartment::query()->ordered()->get(['id', 'name']);

        return view('hr.attendance.index', [
            'institute' => $institute, 'attendances' => $attendances, 'departments' => $departments,
            'branches' => $this->branchOptions($institute->id), 'filters' => $request->query(), 'date' => $date,
            'employees' => $this->employeeOptions($institute->id),
            'shifts' => HrWorkShift::query()->where('is_active', true)->get(),
            'statuses' => HrAttendance::STATUSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr_employees,id'],
            'attendance_date' => ['required', 'date'],
            'status' => ['required', 'in:present,absent,late,early_departure,leave,holiday,weekend,half_day'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'source' => ['nullable', 'in:manual,system,api,import'],
        ]);
        $this->attendanceService->mark($data, $institute->id, $this->actorId($request));

        return back()->with('status', 'Attendance recorded.');
    }

    public function corrections(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $query = HrAttendanceCorrection::query()->with(['employee', 'attendance'])->where('institute_id', $institute->id);
        if ($this->actingBranchId($request)) {
            $query->whereHas('employee', fn ($q) => $q->where('branch_id', $this->actingBranchId($request)));
        }
        $corrections = $query->latest('id')->paginate(20)->withQueryString();

        return view('hr.attendance.corrections', [
            'institute' => $institute, 'corrections' => $corrections,
            'employees' => $this->employeeOptions($institute->id),
        ]);
    }

    public function requestCorrection(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:hr_employees,id'],
            'correction_date' => ['required', 'date'],
            'requested_status' => ['required', 'in:present,absent,late,early_departure,leave,holiday,weekend,half_day'],
            'requested_check_in' => ['nullable', 'date_format:H:i'],
            'requested_check_out' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);
        $this->attendanceService->requestCorrection($data, $institute->id, $this->actorId($request));

        return back()->with('status', 'Correction requested (pending). Original preserved.');
    }

    public function decideCorrection(Request $request, HrAttendanceCorrection $correction): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'review_notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->attendanceService->decideCorrection($correction, $data['decision'], $data['review_notes'] ?? null, $institute->id, $this->actorId($request));

        return back()->with('status', 'Correction '.$data['decision'].'.');
    }

    public function shifts(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $shifts = HrWorkShift::query()->with(['branch', 'employee'])->orderBy('name')->paginate(20)->withQueryString();

        return view('hr.attendance.shifts', [
            'institute' => $institute, 'shifts' => $shifts,
            'branches' => $this->branchOptions($institute->id),
            'employees' => $this->employeeOptions($institute->id),
        ]);
    }

    public function storeShift(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'employee_id' => ['nullable', 'integer', 'exists:hr_employees,id'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'working_days' => ['nullable', 'array'],
            'working_days.*' => ['integer', 'min:1', 'max:7'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $this->shiftService->create($data, $institute->id, $this->actorId($request));

        return back()->with('status', 'Shift created.');
    }

    public function updateShift(Request $request, HrWorkShift $shift): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'employee_id' => ['nullable', 'integer', 'exists:hr_employees,id'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'working_days' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $this->shiftService->update($shift, $data, $institute->id, $this->actorId($request));

        return back()->with('status', 'Shift updated.');
    }

    public function destroyShift(Request $request, HrWorkShift $shift): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $this->shiftService->delete($shift, $institute->id, $this->actorId($request));

        return back()->with('status', 'Shift deleted.');
    }

    public function holidays(Request $request): View
    {
        $institute = $this->requireInstitute($request);
        $holidays = HrHoliday::query()->with('branch')->orderBy('holiday_date')->paginate(20)->withQueryString();

        return view('hr.attendance.holidays', [
            'institute' => $institute, 'holidays' => $holidays,
            'branches' => $this->branchOptions($institute->id),
        ]);
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'holiday_date' => ['required', 'date'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'is_recurring' => ['nullable', 'boolean'],
        ]);
        HrHoliday::create([
            'institute_id' => $institute->id,
            'branch_id' => $data['branch_id'] ?? null,
            'name' => $data['name'],
            'holiday_date' => $data['holiday_date'],
            'is_recurring' => (bool) ($data['is_recurring'] ?? false),
            'created_by' => $this->actorId($request),
        ]);
        app(HrAuditService::class)->record($institute->id, $this->actorId($request), 'hr_holiday_created', null, null, $data);

        return back()->with('status', 'Holiday created.');
    }

    public function destroyHoliday(Request $request, HrHoliday $holiday): RedirectResponse
    {
        $institute = $this->requireInstitute($request);
        abort_if((int) $holiday->institute_id !== (int) $institute->id, 404);
        $holiday->delete();
        app(HrAuditService::class)->record($institute->id, $this->actorId($request), 'hr_holiday_deleted', $holiday->id, $holiday->getAttributes(), null);

        return back()->with('status', 'Holiday deleted.');
    }

    private function branchOptions(int $instituteId)
    {
        $acting = $this->actingBranchId(request());

        return Branch::query()->where('institute_id', $instituteId)->where('status', 'active')->when($acting !== null, fn ($q) => $q->whereKey($acting))->orderBy('name')->get(['id', 'name']);
    }

    private function employeeOptions(int $instituteId)
    {
        return HrEmployee::query()->where('institute_id', $instituteId)->orderBy('display_name')->limit(200)->get(['id', 'display_name', 'employee_code', 'branch_id']);
    }
}
